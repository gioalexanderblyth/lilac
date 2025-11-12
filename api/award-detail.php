<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config.php';

if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

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

$method = $_SERVER['REQUEST_METHOD'];
$awardId = $_GET['id'] ?? null;

try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        echo json_encode(['success' => false, 'error' => 'Database required']);
        exit();
    }

    if ($method === 'GET' && $awardId) {
        // Get detailed award information
        $stmt = $pdo->prepare("
            SELECT
                a.id,
                a.user_id,
                a.title,
                a.description,
                a.file_name,
                a.file_path,
                a.status as award_status,
                a.created_at,
                a.updated_at,
                u.username,
                u.email,
                aa.id as analysis_id,
                aa.predicted_category,
                aa.match_percentage,
                aa.status as analysis_status,
                aa.detected_text,
                aa.matched_keywords,
                aa.all_matches,
                aa.recommendations
            FROM awards a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN award_analysis aa ON a.id = aa.award_id
            WHERE a.id = ?
        ");
        $stmt->execute([$awardId]);
        $award = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$award) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Award not found']);
            exit();
        }

        // Parse analysis data
        if ($award['all_matches']) {
            $matches = json_decode($award['all_matches'], true);
            if ($matches && is_array($matches) && !empty($matches)) {
                $firstMatch = $matches[0];
                $award['matched_criteria'] = $firstMatch['met_criteria'] ?? [];
                $award['unmatched_criteria'] = $firstMatch['unmet_criteria'] ?? [];
                $award['criteria_met'] = $firstMatch['criteria_met'] ?? 0;
                $award['criteria_total'] = $firstMatch['criteria_total'] ?? 0;
                $award['similarity_score'] = $firstMatch['similarity_score'] ?? ($award['match_percentage'] / 100);
                $award['keyword_score'] = $firstMatch['keyword_score'] ?? 0;
                $award['final_score'] = $firstMatch['final_score'] ?? $award['match_percentage'];
            }
        }

        // Parse matched keywords
        if ($award['matched_keywords']) {
            $award['keywords'] = json_decode($award['matched_keywords'], true);
        }

        // Remove raw JSON data
        unset($award['all_matches']);
        unset($award['matched_keywords']);

        echo json_encode([
            'success' => true,
            'award' => $award
        ]);

    } elseif ($method === 'POST' && $awardId) {
        // Update award status
        $data = json_decode(file_get_contents('php://input'), true);
        $newStatus = $data['status'] ?? null;

        if (!in_array($newStatus, ['pending', 'recognized', 'processed'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid status']);
            exit();
        }

        // Update award status
        $stmt = $pdo->prepare("UPDATE awards SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $awardId]);

        // Also update analysis status if needed
        if ($newStatus === 'recognized') {
            $stmt = $pdo->prepare("UPDATE award_analysis SET status = 'Eligible' WHERE award_id = ?");
            $stmt->execute([$awardId]);
        } elseif ($newStatus === 'pending') {
            $stmt = $pdo->prepare("UPDATE award_analysis SET status = 'Under Review' WHERE award_id = ?");
            $stmt->execute([$awardId]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully',
            'new_status' => $newStatus
        ]);

    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
