<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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

$user = $_SESSION['user'];
$isAdmin = $user['role'] === 'admin';

if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit();
}

$action = $_GET['action'] ?? '';

try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        echo json_encode(['success' => false, 'error' => 'Database connection required for this feature']);
        exit();
    }

    switch ($action) {
        case 'list':
            $stmt = $pdo->query('SELECT * FROM award_criteria ORDER BY created_at DESC');
            $criteria = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'criteria' => $criteria
            ]);
            break;

        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['category_name']) || !isset($data['description']) || !isset($data['requirements'])) {
                throw new Exception('Missing required fields');
            }

            $stmt = $pdo->prepare('
                INSERT INTO award_criteria
                (category_name, award_type, description, requirements, keywords, min_match_percentage, weight, status, created_by, department, assignee_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');

            $requirements = is_array($data['requirements']) ? json_encode($data['requirements']) : $data['requirements'];

            $stmt->execute([
                $data['category_name'],
                $data['award_type'] ?? 'Institutional',
                $data['description'],
                $requirements,
                $data['keywords'] ?? '',
                $data['min_match_percentage'] ?? 60,
                $data['weight'] ?? 5,
                $data['status'] ?? 'active',
                $user['id'],
                $data['department'] ?? null,
                $data['assignee_id'] ?? null
            ]);

            echo json_encode([
                'success' => true,
                'id' => $pdo->lastInsertId(),
                'message' => 'Award criteria created successfully'
            ]);
            break;

        case 'update':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Criteria ID required');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $stmt = $pdo->prepare('
                UPDATE award_criteria
                SET category_name = ?,
                    award_type = ?,
                    description = ?,
                    requirements = ?,
                    keywords = ?,
                    min_match_percentage = ?,
                    weight = ?,
                    status = ?,
                    department = ?,
                    assignee_id = ?
                WHERE id = ?
            ');

            $requirements = is_array($data['requirements']) ? json_encode($data['requirements']) : $data['requirements'];

            $stmt->execute([
                $data['category_name'],
                $data['award_type'],
                $data['description'],
                $requirements,
                $data['keywords'],
                $data['min_match_percentage'],
                $data['weight'],
                $data['status'],
                $data['department'] ?? null,
                $data['assignee_id'] ?? null,
                $id
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Award criteria updated successfully'
            ]);
            break;

        case 'delete':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Criteria ID required');
            }

            $stmt = $pdo->prepare('DELETE FROM award_criteria WHERE id = ?');
            $stmt->execute([$id]);

            echo json_encode([
                'success' => true,
                'message' => 'Award criteria deleted successfully'
            ]);
            break;

        case 'get':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Criteria ID required');
            }

            $stmt = $pdo->prepare('SELECT * FROM award_criteria WHERE id = ?');
            $stmt->execute([$id]);
            $criteria = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$criteria) {
                throw new Exception('Criteria not found');
            }

            echo json_encode([
                'success' => true,
                'criteria' => $criteria
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
