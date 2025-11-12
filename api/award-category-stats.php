<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

$category = $_GET['category'] ?? '';

try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        echo json_encode(['success' => false, 'error' => 'Database required']);
        exit();
    }

    if ($category) {
        // Get detailed stats for a specific category
        $stmt = $pdo->prepare("
            SELECT
                a.id,
                a.title,
                a.description,
                a.created_at,
                u.username,
                aa.match_percentage,
                aa.status,
                aa.all_matches
            FROM awards a
            INNER JOIN award_analysis aa ON a.id = aa.award_id
            LEFT JOIN users u ON a.user_id = u.id
            WHERE aa.predicted_category = ?
            ORDER BY aa.match_percentage DESC, a.created_at DESC
        ");
        $stmt->execute([$category]);
        $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Parse matched/unmatched criteria for each
        foreach ($submissions as &$sub) {
            if ($sub['all_matches']) {
                $matches = json_decode($sub['all_matches'], true);
                if ($matches && is_array($matches) && !empty($matches)) {
                    $sub['matched_criteria'] = $matches[0]['met_criteria'] ?? [];
                    $sub['unmatched_criteria'] = $matches[0]['unmet_criteria'] ?? [];
                    $sub['criteria_met'] = $matches[0]['criteria_met'] ?? 0;
                    $sub['criteria_total'] = $matches[0]['criteria_total'] ?? 0;
                    $sub['similarity_score'] = $matches[0]['similarity_score'] ?? ($sub['match_percentage'] / 100);
                }
            }
            unset($sub['all_matches']); // Remove raw JSON from response
        }

        $stats = [
            'total' => count($submissions),
            'eligible' => count(array_filter($submissions, fn($s) => $s['status'] === 'Eligible')),
            'almost_eligible' => count(array_filter($submissions, fn($s) => $s['status'] === 'Almost Eligible')),
            'pending' => count(array_filter($submissions, fn($s) => !in_array($s['status'], ['Eligible', 'Almost Eligible'])))
        ];

        echo json_encode([
            'success' => true,
            'category' => $category,
            'stats' => $stats,
            'submissions' => $submissions
        ]);

    } else {
        // Get overview of all categories
        $stmt = $pdo->query("
            SELECT
                aa.predicted_category as category,
                COUNT(*) as total,
                SUM(CASE WHEN aa.status = 'Eligible' THEN 1 ELSE 0 END) as eligible,
                SUM(CASE WHEN aa.status = 'Almost Eligible' THEN 1 ELSE 0 END) as almost_eligible,
                SUM(CASE WHEN aa.status NOT IN ('Eligible', 'Almost Eligible') THEN 1 ELSE 0 END) as pending,
                AVG(aa.match_percentage) as avg_match_percentage
            FROM award_analysis aa
            GROUP BY aa.predicted_category
            ORDER BY total DESC
        ");

        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'categories' => $categories
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
