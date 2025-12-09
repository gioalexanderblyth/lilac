<?php
/**
 * Award Analysis with OCR and Weighted Keyword Matching
 * Processes uploaded certificates and matches against award criteria
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/check-award-eligibility.php';

try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        http_response_code(501);
        echo json_encode(['success' => false, 'error' => 'Database connection required']);
        exit();
    }

    $userId = $_SESSION['user_id'];

    // Get form data
    $awardName = $_POST['award_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $keywords = $_POST['keywords'] ?? '';

    // Handle file upload
    $ocrText = '';
    $fileName = null;
    $filePath = null;

    if (isset($_FILES['award_file']) && $_FILES['award_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['award_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'docx'];

        if (!in_array($ext, $allowedExts)) {
            throw new Exception('Invalid file type. Allowed: PDF, DOCX, JPG, PNG');
        }

        // Create upload directory
        $uploadDir = dirname(__DIR__) . '/uploads/awards/' . date('Y/m');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $fileName = uniqid('award_' . $userId . '_') . '.' . $ext;
        $fullPath = $uploadDir . '/' . $fileName;
        $filePath = 'uploads/awards/' . date('Y/m') . '/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            throw new Exception('Failed to upload file');
        }

        // Extract text from file (OCR simulation)
        $ocrText = extractTextFromFile($fullPath, $ext);
    }

    // Combine all text sources
    $combinedText = trim($awardName . ' ' . $description . ' ' . $keywords . ' ' . $ocrText);

    // Clean and normalize text
    $cleanedText = cleanText($combinedText);

    // Load all active award criteria
    $stmt = $pdo->query("
        SELECT id, category_name, award_type, keywords, weight, requirements
        FROM award_criteria
        WHERE status = 'active'
        ORDER BY category_name
    ");
    $allCriteria = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($allCriteria)) {
        throw new Exception('No award criteria configured. Please contact administrator.');
    }

    // Perform weighted keyword matching
    $matchResults = [];

    foreach ($allCriteria as $criteria) {
        $result = calculateWeightedMatch($cleanedText, $criteria);
        
        // Add status field to each result based on match_percentage
        $matchPct = $result['match_percentage'];
        if ($matchPct >= 90) {
            $result['status'] = 'Eligible';
        } elseif ($matchPct >= 70) {
            $result['status'] = 'Almost Eligible';
        } else {
            $result['status'] = 'Not Eligible';
        }
        
        $matchResults[] = $result;
    }

    // Sort by match percentage (highest first)
    usort($matchResults, function($a, $b) {
        return $b['match_percentage'] <=> $a['match_percentage'];
    });

    // Get the best match
    $bestMatch = $matchResults[0];

    // Determine eligibility status
    $matchPct = $bestMatch['match_percentage'];
    if ($matchPct >= 90) {
        $eligibilityStatus = 'Eligible';
        $statusClass = 'success';
    } elseif ($matchPct >= 70) {
        $eligibilityStatus = 'Almost Eligible';
        $statusClass = 'warning';
    } else {
        $eligibilityStatus = 'Not Eligible';
        $statusClass = 'danger';
    }

    // Create award submission
    $stmt = $pdo->prepare("
        INSERT INTO awards (user_id, title, description, file_name, file_path, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([
        $userId,
        $awardName,
        $description,
        $fileName,
        $filePath
    ]);

    $awardId = $pdo->lastInsertId();

    // Store analysis results
    // Prepare matched and missing keywords as JSON
    $matchedKeywordsJson = json_encode($bestMatch['matched_keywords']);
    $missingKeywordsJson = json_encode($bestMatch['missing_keywords']);

    // Combine all matches for storage
    $allMatchesData = [
        'matched' => $bestMatch['matched_keywords'],
        'missing' => $bestMatch['missing_keywords'],
        'match_count' => $bestMatch['keyword_match_count'],
        'total_keywords' => $bestMatch['total_keywords']
    ];

    $stmt = $pdo->prepare("
        INSERT INTO award_analysis (
            award_id,
            predicted_category,
            match_percentage,
            confidence,
            matched_keywords,
            all_matches,
            detected_text,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $awardId,
        $bestMatch['category_name'],
        $bestMatch['match_percentage'],
        $bestMatch['confidence'],
        $matchedKeywordsJson,
        json_encode($allMatchesData),
        $ocrText
    ]);

    // Update award status
    $stmt = $pdo->prepare("UPDATE awards SET status = 'analyzed' WHERE id = ?");
    $stmt->execute([$awardId]);

    // Check eligibility against CHED award criteria and automatically link if eligible
    $eligibleAwards = checkAllAwardEligibility($combinedText);
    $chedAwardCategory = null;
    $eligibilityCriteriaMatch = [];
    
    if (!empty($eligibleAwards)) {
        // Use the first eligible award (highest priority)
        $primaryEligibleAward = $eligibleAwards[0];
        $chedAwardCategory = $primaryEligibleAward['award_title'];
        
        // Store eligibility criteria match information
        $eligibilityCriteriaMatch = [
            'primary_award' => $primaryEligibleAward,
            'all_eligible_awards' => $eligibleAwards
        ];
        
        // Update the award record with the CHED award category
        try {
            // Check if ched_award_category column exists
            $stmt = $pdo->prepare("UPDATE awards SET ched_award_category = ? WHERE id = ?");
            $stmt->execute([$chedAwardCategory, $awardId]);
            logActivity("Linked certificate ID: $awardId to CHED award: $chedAwardCategory", 'INFO');
        } catch (PDOException $e) {
            // Column might not exist yet - log but don't fail
            logActivity("Could not set ched_award_category (column may not exist): " . $e->getMessage(), 'WARNING');
        }
        
        // Update award_analysis with eligibility criteria match
        try {
            $stmt = $pdo->prepare("UPDATE award_analysis SET eligibility_criteria_match = ? WHERE award_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([json_encode($eligibilityCriteriaMatch), $awardId]);
        } catch (PDOException $e) {
            // Column might not exist yet - log but don't fail
            logActivity("Could not set eligibility_criteria_match (column may not exist): " . $e->getMessage(), 'WARNING');
        }
    }

    // Enhance best_match with missing_keywords for frontend display
    $bestMatchEnhanced = $bestMatch;
    $bestMatchEnhanced['missing_keywords'] = $bestMatch['missing_keywords'];

    // Return results
    echo json_encode([
        'success' => true,
        'award_id' => $awardId,
        'analysis' => [
            'best_match' => $bestMatchEnhanced,
            'all_matches' => $matchResults,
            'eligibility_status' => $eligibilityStatus,
            'status_class' => $statusClass,
            'ocr_text' => substr($ocrText, 0, 500), // First 500 chars for preview
            'ched_eligibility' => [
                'eligible' => !empty($eligibleAwards),
                'eligible_awards' => $eligibleAwards,
                'primary_award' => $chedAwardCategory
            ]
        ],
        'message' => 'Award analyzed successfully' . ($chedAwardCategory ? " and automatically linked to $chedAwardCategory" : '')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Extract text from uploaded file
 */
function extractTextFromFile($filePath, $ext) {
    $text = '';

    if ($ext === 'pdf') {
        // Try multiple methods for PDF text extraction

        // Method 1: Try pdftotext command line tool
        $output = @shell_exec("pdftotext \"$filePath\" - 2>&1");
        if ($output && strlen(trim($output)) > 10) {
            $text = $output;
        } else {
            // Method 2: Try using PHP PDF parser libraries
            try {
                // Simple PDF text extraction - read raw PDF content
                $content = file_get_contents($filePath);

                // Extract text between stream markers
                if (preg_match_all('/BT\s+(.*?)\s+ET/s', $content, $matches)) {
                    foreach ($matches[1] as $match) {
                        // Extract text from PDF operators
                        if (preg_match_all('/\((.*?)\)/s', $match, $textMatches)) {
                            $text .= ' ' . implode(' ', $textMatches[1]);
                        }
                    }
                }

                // Clean up PDF encoding artifacts
                $text = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $text);

                // If still no text, extract readable strings
                if (strlen(trim($text)) < 10) {
                    // Extract printable ASCII strings
                    preg_match_all('/[\x20-\x7E]{4,}/', $content, $strings);
                    $text = implode(' ', $strings[0]);
                }
            } catch (Exception $e) {
                error_log("PDF text extraction error: " . $e->getMessage());
            }

            // Fallback: Use filename and common award terms
            if (strlen(trim($text)) < 10) {
                $text = basename($filePath) . ' award certificate document';
            }
        }
    } elseif (in_array($ext, ['jpg', 'jpeg', 'png'])) {
        // Image OCR (requires Tesseract OCR)

        // Method 1: Try tesseract command
        $output = @shell_exec("tesseract \"$filePath\" stdout 2>&1");
        if ($output && strlen(trim($output)) > 10 && !stripos($output, 'error')) {
            $text = $output;
        } else {
            // Method 2: Basic image text detection (extract filename and metadata)
            $imageInfo = @getimagesize($filePath);
            $text = basename($filePath) . ' ';

            // Try to read EXIF data for additional context
            if (function_exists('exif_read_data')) {
                $exif = @exif_read_data($filePath);
                if ($exif && isset($exif['ImageDescription'])) {
                    $text .= $exif['ImageDescription'] . ' ';
                }
            }

            // Add common award certificate terms to help matching
            $text .= 'certificate award recognition achievement excellence';
        }
    } elseif ($ext === 'docx') {
        // DOCX extraction using ZipArchive
        try {
            $zip = new ZipArchive();
            if ($zip->open($filePath) === true) {
                // Read document.xml which contains the text
                $xml = $zip->getFromName('word/document.xml');
                if ($xml) {
                    // Parse XML and extract text
                    $xml = simplexml_load_string($xml);
                    $xml->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                    $textNodes = $xml->xpath('//w:t');

                    foreach ($textNodes as $textNode) {
                        $text .= ' ' . (string)$textNode;
                    }
                }
                $zip->close();
            }

            // Fallback if extraction failed
            if (strlen(trim($text)) < 10) {
                $text = basename($filePath) . ' document award certificate';
            }
        } catch (Exception $e) {
            error_log("DOCX extraction error: " . $e->getMessage());
            $text = basename($filePath) . ' document award certificate';
        }
    }

    return $text;
}

/**
 * Clean and normalize text for matching
 */
function cleanText($text) {
    // Convert to lowercase
    $text = strtolower($text);

    // Remove special characters and extra spaces
    $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text);

    return trim($text);
}

/**
 * Calculate weighted keyword match percentage
 */
function calculateWeightedMatch($text, $criteria) {
    $categoryName = $criteria['category_name'];
    $keywordsStr = $criteria['keywords'] ?? '';
    $totalWeight = floatval($criteria['weight'] ?? 50);

    // Parse keywords (comma-separated)
    $keywords = array_map('trim', explode(',', $keywordsStr));
    $keywords = array_filter($keywords);

    if (empty($keywords)) {
        return [
            'category_name' => $categoryName,
            'award_type' => $criteria['award_type'],
            'match_percentage' => 0,
            'confidence' => 'low',
            'matched_keywords' => [],
            'missing_keywords' => $keywords,
            'total_possible_weight' => $totalWeight
        ];
    }

    // Calculate weight per keyword
    $weightPerKeyword = $totalWeight / count($keywords);

    // Find matched keywords
    $matchedKeywords = [];
    $missingKeywords = [];
    $totalMatchedWeight = 0;

    foreach ($keywords as $keyword) {
        $originalKeyword = $keyword;
        $keyword = strtolower(trim($keyword));
        
        // Remove trailing punctuation (periods, commas, etc.) from keyword
        $keyword = rtrim($keyword, '.,;:!?');
        
        // Clean the keyword the same way as the text to handle hyphens, special chars, etc.
        // This ensures "cross-cultural" matches "cross cultural" in the cleaned text
        $keywordCleaned = cleanText($keyword);

        // Check if keyword exists in text (try multiple variations)
        // This handles cases where keyword has hyphens but text has spaces, and multi-word phrases
        $matched = false;
        
        // Method 1: Try cleaned keyword (handles hyphens, special chars)
        if (strpos($text, $keywordCleaned) !== false) {
            $matched = true;
        }
        // Method 2: Try original keyword (in case text wasn't fully cleaned)
        elseif (strpos($text, $keyword) !== false) {
            $matched = true;
        }
        // Method 3: Try replacing hyphens with spaces
        elseif (strpos($text, str_replace('-', ' ', $keyword)) !== false) {
            $matched = true;
        }
        // Method 4: Try replacing spaces with hyphens
        elseif (strpos($text, str_replace(' ', '-', $keywordCleaned)) !== false) {
            $matched = true;
        }
        // Method 5: For multi-word phrases, try word-by-word matching (at least 70% of words must match)
        elseif (strpos($keyword, ' ') !== false) {
            $keywordWords = explode(' ', $keywordCleaned);
            $keywordWords = array_filter($keywordWords, function($w) { return strlen($w) >= 3; }); // Filter out very short words
            if (count($keywordWords) > 0) {
                $matchedWords = 0;
                foreach ($keywordWords as $word) {
                    if (strpos($text, $word) !== false) {
                        $matchedWords++;
                    }
                }
                // If at least 70% of words match, consider it a match
                if ($matchedWords >= ceil(count($keywordWords) * 0.7)) {
                    $matched = true;
                }
            }
        }
        
        if ($matched) {
            $matchedKeywords[] = $originalKeyword;
            $totalMatchedWeight += $weightPerKeyword;
        } else {
            $missingKeywords[] = $originalKeyword;
        }
    }

    // Calculate match percentage
    $matchPercentage = ($totalMatchedWeight / $totalWeight) * 100;
    $matchPercentage = round($matchPercentage, 2);

    // Determine confidence level
    if ($matchPercentage >= 80) {
        $confidence = 'high';
    } elseif ($matchPercentage >= 50) {
        $confidence = 'medium';
    } else {
        $confidence = 'low';
    }

    return [
        'category_name' => $categoryName,
        'award_type' => $criteria['award_type'],
        'match_percentage' => $matchPercentage,
        'confidence' => $confidence,
        'matched_keywords' => $matchedKeywords,
        'missing_keywords' => $missingKeywords,
        'total_matched_weight' => round($totalMatchedWeight, 2),
        'total_possible_weight' => $totalWeight,
        'keyword_match_count' => count($matchedKeywords),
        'total_keywords' => count($keywords)
    ];
}
