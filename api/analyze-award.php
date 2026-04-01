<?php
// Clean version of analyze-award.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// DEBUG: Log everything to a debug file
$debugFile = __DIR__ . '/../logs/debug_analysis.log';
function debugLog($message) {
    global $debugFile;
    file_put_contents($debugFile, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

session_start(); // Start session to access user info

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

debugLog("Request received: " . $_SERVER['REQUEST_METHOD']);
debugLog("POST Data: " . print_r($_POST, true));
debugLog("FILES Data: " . print_r($_FILES, true));

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Suppress PHP errors to prevent JSON corruption
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Clean any existing output
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Set error handler to catch any PHP errors
set_error_handler(function($severity, $message, $file, $line) {
    debugLog("PHP Error: $message in $file:$line");
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    // Log the analysis attempt with timestamp
    $timestamp = date('Y-m-d H:i:s');
    error_log("[$timestamp] FRESH ANALYSIS STARTED - File count: " . count($_FILES) . ", POST count: " . count($_POST));
    
    // Get form data
    $awardName = $_POST['award_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $isReanalyze = isset($_POST['reanalyze']) && $_POST['reanalyze'] === 'true';
    $originalFilePath = $_POST['original_file_path'] ?? '';
    $documentId = isset($_POST['document_id']) ? (int)$_POST['document_id'] : null;
    $sourcePage = $_POST['source_page'] ?? null;
    $eventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : null;
    
    debugLog("Parsed IDs - Event: $eventId, Document: $documentId");

    // Get current user
    $createdBy = $_SESSION['user']['username'] ?? 'System';
    $userId = $_SESSION['user']['id'] ?? null;
    
    // If document_id provided but no file, try to fetch from database
    $uploadedFile = $_FILES['award_file'] ?? null;
    
    if ($documentId && !$uploadedFile) {
        debugLog("Attempting to fetch existing document ID: $documentId");
        try {
            require_once 'config.php'; // Ensure we have DB access early
            $pdo = getDatabaseConnection();
            $stmt = $pdo->prepare("SELECT file_path, title, file_name FROM other_documents WHERE id = ?");
            $stmt->execute([$documentId]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($doc) {
                $fullPath = __DIR__ . '/../' . $doc['file_path'];
                // Normalize path
                $fullPath = realpath($fullPath) ?: $fullPath;
                
                debugLog("Found document in DB. Path: $fullPath");
                
                if (file_exists($fullPath)) {
                    $uploadedFile = [
                        'name' => $doc['file_name'] ?: basename($doc['file_path']),
                        'tmp_name' => $fullPath,
                        'error' => UPLOAD_ERR_OK,
                        'size' => filesize($fullPath)
                    ];
                    debugLog("Using existing document for analysis: " . $fullPath);
                } else {
                    debugLog("Document file not found at: " . $fullPath);
                }
            } else {
                debugLog("Document ID $documentId not found in database");
            }
        } catch (Exception $e) {
            debugLog("Error fetching document: " . $e->getMessage());
        }
    }
    
    // Log analysis type
    if ($isReanalyze) {
        error_log("[$timestamp] ANALYSIS TYPE: Re-analysis of existing file");
    } else {
        error_log("[$timestamp] ANALYSIS TYPE: Fresh upload analysis");
    }
    
    // Handle re-analysis vs new upload
    if ($isReanalyze && !empty($originalFilePath)) {
        // Re-analysis: use existing file
        error_log("Re-analysis request for file: " . $originalFilePath);
        
        // Convert relative path to absolute path if needed
        $absolutePath = $originalFilePath;
        if (!file_exists($absolutePath)) {
            // Try relative to project root
            $projectRoot = dirname(__DIR__);
            $absolutePath = $projectRoot . '/' . ltrim($originalFilePath, '/');
            
            // Normalize path separators
            $absolutePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $absolutePath);
        }
        
        // Check if the original file exists
        if (!file_exists($absolutePath)) {
            throw new Exception('Original file not found: ' . $originalFilePath . ' (tried: ' . $absolutePath . ')');
        }
        
        $uploadedFile = [
            'name' => basename($absolutePath),
            'tmp_name' => $absolutePath,
            'size' => filesize($absolutePath),
            'error' => UPLOAD_ERR_OK
        ];
    }
    
    error_log("Form data - Award name: '$awardName', Description: '$description', File: " . ($uploadedFile ? $uploadedFile['name'] : 'none') . ", Reanalyze: " . ($isReanalyze ? 'yes' : 'no'));

    // Default title when clients omit award_name (still required by validation for non-reanalyze uploads)
    if ($uploadedFile && !empty($uploadedFile['name']) && trim($awardName) === '') {
        $awardName = pathinfo($uploadedFile['name'], PATHINFO_FILENAME) ?: 'Document analysis';
    }

    // IMMEDIATE BACKUP - Create award file as soon as we have the uploaded file data
    if ($uploadedFile && $uploadedFile['error'] === UPLOAD_ERR_OK && !$isReanalyze) {
        try {
            $immediateData = [
                'title' => $awardName,
                'description' => $description,
                'file_name' => $uploadedFile['name'],
                'file_path' => '',
                'detected_text' => '',
                'analysis_results' => '[]',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $dataDir = __DIR__ . '/../data/';
            if (!is_dir($dataDir)) {
                @mkdir($dataDir, 0755, true);
            }
            
            $immediateFile = $dataDir . 'upload_analysis_' . time() . '_' . uniqid() . '.json';
            $result = @file_put_contents($immediateFile, json_encode($immediateData, JSON_PRETTY_PRINT));
            
            if ($result !== false) {
                error_log("IMMEDIATE BACKUP SUCCESS in analyze-award.php: " . $immediateFile);
                $GLOBALS['award_backup_file'] = $immediateFile;
            }
        } catch (Exception $e) {
            error_log("Immediate backup failed in analyze-award.php: " . $e->getMessage());
        }
    }

    // Validate required fields
    if ($isReanalyze) {
        // For re-analysis, only need the file
        if (!$uploadedFile) {
            throw new Exception('Missing required fields: file upload is required for re-analysis');
        }
    } else {
        // For new uploads, need all fields
        // If eventId is present, file is optional (we can analyze title/description)
        if (empty($awardName)) {
             throw new Exception('Missing required fields: award name is required');
        }
        if (!$uploadedFile && !$eventId) {
            throw new Exception('Missing required fields: file upload or event reference is required');
        }
    }

    // Load functions and config
    require_once 'award-analysis-functions.php';
    
    $extractedText = '';
    
    if ($uploadedFile) {
        // Validate file upload
        error_log("Starting file upload validation");
        debugLog("Validating file upload...");
        validateFileUpload($uploadedFile);
        error_log("File upload validation passed");
        
        // Use same MIME resolution as validateFileUpload (fixes DOCX as application/zip on Windows)
        $fileType = resolveAwardAnalysisMimeType($uploadedFile);
        if (!$fileType) {
            throw new Exception('Could not determine file type for analysis');
        }
        error_log("File type detected: " . $fileType);
        debugLog("File type: $fileType");

        // Extract text from uploaded file
        error_log("Starting text extraction for file: " . $uploadedFile['name']);
        $extractedText = extractTextFromFile($uploadedFile['tmp_name'], $fileType);
    } else {
        // No file - use Title and Description
        $extractedText = $awardName . "\n\n" . $description;
        error_log("Using Event Title/Description for analysis (No File)");
        debugLog("Using text content only (Title+Desc)");
    }
    
    error_log("Text extraction completed, length: " . strlen($extractedText));
    error_log("Extracted text preview: " . substr($extractedText, 0, 100) . "...");
    debugLog("Text extraction complete. Length: " . strlen($extractedText));
    
    // Check if the extraction returned a JSON error response (e.g., missing OCR)
    $jsonError = json_decode($extractedText, true);
    if ($jsonError && isset($jsonError['error'])) {
        // Handle OCR-related errors gracefully
        error_log("OCR error detected: " . ($jsonError['error'] ?? 'unknown'));
        
        http_response_code(400);
        $response = [
            'success' => false,
            'error' => $jsonError['user_message'] ?? $jsonError['message'],
            'error_type' => $jsonError['error'],
            'file_type' => $fileType ?? 'unknown',
            'instructions' => $jsonError['instructions'] ?? null
        ];
        
        ob_clean();
        echo json_encode($response);
        exit;
    }
    
    if (empty($extractedText)) {
        throw new Exception('Could not extract text from the uploaded file');
    }

    // Load active awards from DATABASE instead of JSON file
    error_log("Loading active awards from database");
    $pdo = getDatabaseConnection();
    
    $dbAwards = [];
    try {
        if (!($pdo instanceof FileBasedDatabase)) {
            $stmt = $pdo->query("SELECT category_name, keywords, weight, award_type FROM award_criteria WHERE status = 'active'");
            $dbAwards = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        error_log('analyze-award: award_criteria query failed (continuing with empty criteria): ' . $e->getMessage());
        $dbAwards = [];
    }
    
    // Perform analysis using strict weighted matching (Same as Awards Page)
    error_log("Starting Strict Analysis (Awards Page Logic)");
    $cleanedText = cleanText($extractedText);
    
    $analysis = [];
    foreach ($dbAwards as $dbAward) {
        // Parse criteria from DB row
        $criteria = [
            'category_name' => $dbAward['category_name'],
            'keywords' => $dbAward['keywords'], // calculateStrictWeightedMatch handles splitting
            'weight' => $dbAward['weight'] ?? 50,
            'award_type' => $dbAward['award_type'] ?? 'General'
        ];
        
        $result = calculateStrictWeightedMatch($cleanedText, $criteria);
        
        // Map result to frontend expectation
        $analysis[] = [
            'title' => $result['category_name'],
            'category' => $result['category_name'], // for award-analyzer.js
            'name' => $result['category_name'], // for compatibility
            'match_percentage' => $result['match_percentage'], // Primary score field for frontend
            'score' => $result['match_percentage'], // Fallback
            'status' => $result['status'],
            'matched_keywords' => $result['matched_keywords'],
            'missing_keywords' => $result['missing_keywords'],
            'recommendation' => '', // Let frontend handle it
            'confidence' => $result['confidence'] // This is the 'high'/'medium'/'low' string
        ];
    }
    
    // Sort by score descending
    usort($analysis, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });
    
    error_log("Analysis completed: " . count($analysis) . " results");
    debugLog("Analysis completed. Found " . count($analysis) . " matches.");

    // Store the analysis in database (with fallback)
    error_log("Starting to store analysis results");
    $analysisId = storeAnalysisResults($awardName, $description, $extractedText, $analysis, $uploadedFile, $isReanalyze, $documentId, $sourcePage, $createdBy, $userId, $eventId);
    error_log("Analysis results stored with ID: " . $analysisId);
    debugLog("Stored analysis results. ID: " . $analysisId);

    
    // Build counts for eligibility
    $eligibleCount = 0; $partialCount = 0; $notEligibleCount = 0;
    foreach ($analysis as $r) {
        $status = strtolower($r['status'] ?? '');
        if ($status === 'eligible') { $eligibleCount++; }
        elseif ($status === 'partially eligible') { $partialCount++; }
        else { $notEligibleCount++; }
    }
    
    // Log completion
    error_log("[$timestamp] ANALYSIS COMPLETED SUCCESSFULLY - Results count: " . count($analysis));
    
    // UPDATE BACKUP FILE with complete analysis results
    if (isset($GLOBALS['award_backup_file']) && file_exists($GLOBALS['award_backup_file'])) {
        try {
            $completeData = [
                'title' => $awardName,
                'description' => $description,
                'file_name' => $uploadedFile['name'] ?? 'unknown',
                'file_path' => $uploadedFile['tmp_name'] ?? '',
                'detected_text' => $extractedText,
                'analysis_results' => json_encode($analysis),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $result = @file_put_contents($GLOBALS['award_backup_file'], json_encode($completeData, JSON_PRETTY_PRINT));
            if ($result !== false) {
                error_log("BACKUP UPDATED with analysis results: " . $GLOBALS['award_backup_file']);
            }
        } catch (Exception $e) {
            error_log("Failed to update backup file: " . $e->getMessage());
        }
    }
    
    // Return the analysis results
    $response = [
        'success' => true,
        'analysis_id' => $analysisId,
        'detected_text' => $extractedText,
        'analysis' => $analysis,
        'eligible_count' => $eligibleCount,
        'partial_count' => $partialCount,
        'not_eligible_count' => $notEligibleCount,
        'total_count' => count($analysis),
        'timestamp' => $timestamp,
        'is_fresh_analysis' => !$isReanalyze
    ];
    
    // Ensure clean JSON output
    ob_clean();
    echo json_encode($response);
    exit;

} catch (Exception $e) {
    // Log the error
    $errorMsg = $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine();
    error_log("Analyze award error: " . $errorMsg);
    debugLog("EXCEPTION: " . $errorMsg);
    
    http_response_code(400);
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
    
    // Ensure clean JSON output
    ob_clean();
    echo json_encode($response);
    exit;
} catch (Error $e) {
    // Catch PHP 7+ errors
    $errorMsg = $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine();
    error_log("Analyze award PHP error: " . $errorMsg);
    debugLog("FATAL ERROR: " . $errorMsg);
    
    http_response_code(500);
    $response = [
        'success' => false,
        'error' => 'Internal server error occurred'
    ];
    
    // Ensure clean JSON output
    ob_clean();
    echo json_encode($response);
    exit;
}
?>
