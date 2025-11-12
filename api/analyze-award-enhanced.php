<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config.php';

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$token = $matches[1];

session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['token']) || $_SESSION['token'] !== $token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid token']);
    exit();
}

$user = $_SESSION['user'];

if (!isset($_FILES['file']) || !isset($_POST['title'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        echo json_encode(['success' => false, 'error' => 'Database connection required for enhanced analysis']);
        exit();
    }

    $file = $_FILES['file'];
    $title = $_POST['title'];
    $description = $_POST['description'] ?? '';

    $uploadDir = __DIR__ . '/../uploads/awards/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = time() . '_' . basename($file['name']);
    $filePath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Failed to upload file');
    }

    $stmt = $pdo->prepare('
        INSERT INTO awards (user_id, title, description, file_name, file_path, file_type, file_size, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');

    $stmt->execute([
        $user['id'],
        $title,
        $description,
        $file['name'],
        $filePath,
        $file['type'],
        $file['size'],
        'pending'
    ]);

    $awardId = $pdo->lastInsertId();

    $documentText = extractTextFromFile($filePath, $file['type']);

    $stmt = $pdo->query('SELECT * FROM award_criteria WHERE status = "active" ORDER BY weight DESC');
    $allCriteria = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $analysisResults = [];
    $bestMatch = null;
    $bestScore = 0;

    foreach ($allCriteria as $criteria) {
        $requirements = json_decode($criteria['requirements'], true);
        $keywords = array_map('trim', explode(',', $criteria['keywords']));

        $metCriteria = [];
        $unmetal = [];
        $totalCriteria = count($requirements);
        $metCount = 0;

        foreach ($requirements as $requirement) {
            $ismat = checkRequirementInText($documentText, $requirement);
            if ($ismet) {
                $metCriteria[] = $requirement;
                $metCount++;
            } else {
                $unmetCriteria[] = $requirement;
            }
        }

        $matchPercentage = $totalCriteria > 0 ? ($metCount / $totalCriteria) * 100 : 0;

        $keywordMatches = 0;
        foreach ($keywords as $keyword) {
            if (stripos($documentText, $keyword) !== false) {
                $keywordMatches++;
            }
        }

        $keywordScore = count($keywords) > 0 ? ($keywordMatches / count($keywords)) * 100 : 0;

        $finalScore = ($matchPercentage * 0.7) + ($keywordScore * 0.3);

        $status = 'Not Eligible';
        if ($matchPercentage >= $criteria['min_match_percentage']) {
            $status = 'Eligible';
        } elseif ($matchPercentage >= ($criteria['min_match_percentage'] - 20)) {
            $status = 'Almost Eligible';
        }

        $analysisResults[] = [
            'category' => $criteria['category_name'],
            'award_type' => $criteria['award_type'],
            'status' => $status,
            'match_percentage' => round($matchPercentage, 2),
            'criteria_met' => $metCount,
            'criteria_total' => $totalCriteria,
            'met_criteria' => $metCriteria,
            'unmet_criteria' => $unmetCriteria,
            'keyword_score' => round($keywordScore, 2),
            'final_score' => round($finalScore, 2),
            'min_required' => $criteria['min_match_percentage']
        ];

        if ($finalScore > $bestScore) {
            $bestScore = $finalScore;
            $bestMatch = $criteria['category_name'];
        }
    }

    usort($analysisResults, function($a, $b) {
        return $b['final_score'] <=> $a['final_score'];
    });

    $topMatch = $analysisResults[0] ?? null;

    if ($topMatch) {
        $stmt = $pdo->prepare('
            INSERT INTO award_analysis
            (award_id, predicted_category, match_percentage, status, detected_text, matched_keywords, all_matches, recommendations)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $recommendations = generateRecommendations($topMatch);

        $stmt->execute([
            $awardId,
            $topMatch['category'],
            $topMatch['match_percentage'],
            $topMatch['status'],
            substr($documentText, 0, 10000),
            json_encode($topMatch['met_criteria']),
            json_encode($analysisResults),
            $recommendations
        ]);

        $stmt = $pdo->prepare('UPDATE awards SET status = ? WHERE id = ?');
        $stmt->execute(['analyzed', $awardId]);
    }

    echo json_encode([
        'success' => true,
        'award_id' => $awardId,
        'best_match' => $bestMatch,
        'best_score' => round($bestScore, 2),
        'analysis_results' => $analysisResults,
        'top_match' => $topMatch,
        'message' => 'Award analyzed successfully'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function extractTextFromFile($filePath, $fileType) {
    $text = '';

    if (strpos($fileType, 'pdf') !== false) {
        $text = file_get_contents($filePath);
    } elseif (strpos($fileType, 'text') !== false || strpos($fileType, 'plain') !== false) {
        $text = file_get_contents($filePath);
    } else {
        $text = file_get_contents($filePath);
    }

    return $text;
}

function checkRequirementInText($text, $requirement) {
    $keywords = extractKeywordsFromRequirement($requirement);
    $text = strtolower($text);

    $matchCount = 0;
    foreach ($keywords as $keyword) {
        if (stripos($text, strtolower($keyword)) !== false) {
            $matchCount++;
        }
    }

    return $matchCount >= (count($keywords) * 0.6);
}

function extractKeywordsFromRequirement($requirement) {
    $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'been', 'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those'];

    $words = preg_split('/\s+/', strtolower($requirement));
    $keywords = array_filter($words, function($word) use ($stopWords) {
        return strlen($word) > 3 && !in_array($word, $stopWords);
    });

    return array_values($keywords);
}

function generateRecommendations($topMatch) {
    $recommendations = [];

    if ($topMatch['status'] === 'Eligible') {
        $recommendations[] = "You meet the criteria for {$topMatch['category']}. Consider submitting your application.";
    } elseif ($topMatch['status'] === 'Almost Eligible') {
        $recommendations[] = "You are close to meeting the criteria for {$topMatch['category']}.";
        if (!empty($topMatch['unmet_criteria'])) {
            $recommendations[] = "Focus on: " . implode(', ', array_slice($topMatch['unmet_criteria'], 0, 3));
        }
    } else {
        $recommendations[] = "Your document does not fully match {$topMatch['category']} criteria.";
        $recommendations[] = "Consider reviewing the requirements and updating your documentation.";
    }

    return implode(' ', $recommendations);
}
