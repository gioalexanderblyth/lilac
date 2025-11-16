<?php
/**
 * Advanced Filtering API
 * Provides advanced filtering capabilities for all entities
 */

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['user_id'];

// Get database connection
try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database required for advanced filtering']);
        exit();
    }

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $entityType = $input['entity_type'] ?? '';

        if (empty($entityType)) {
            throw new Exception('Entity type is required');
        }

        $filters = $input['filters'] ?? [];
        $sortBy = $input['sort_by'] ?? 'created_at';
        $sortOrder = $input['sort_order'] ?? 'DESC';
        $limit = (int)($input['limit'] ?? 50);
        $offset = (int)($input['offset'] ?? 0);

        // Build query based on entity type
        $sql = '';
        $params = [];
        $whereConditions = [];

        switch ($entityType) {
            case 'mou_moa':
                $sql = "SELECT m.*, u.username as created_by, u.full_name 
                        FROM mou_moa m 
                        LEFT JOIN users u ON m.user_id = u.id";
                
                // Apply filters
                if (!empty($filters['search'])) {
                    $whereConditions[] = "(m.title LIKE ? OR m.institution LIKE ? OR m.partner LIKE ?)";
                    $searchTerm = '%' . $filters['search'] . '%';
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                }
                
                if (!empty($filters['type'])) {
                    $whereConditions[] = "m.type = ?";
                    $params[] = $filters['type'];
                }
                
                if (!empty($filters['status'])) {
                    $whereConditions[] = "m.status = ?";
                    $params[] = $filters['status'];
                }
                
                if (!empty($filters['date_from'])) {
                    $whereConditions[] = "m.end_date >= ?";
                    $params[] = $filters['date_from'];
                }
                
                if (!empty($filters['date_to'])) {
                    $whereConditions[] = "m.end_date <= ?";
                    $params[] = $filters['date_to'];
                }
                
                if (!empty($filters['location'])) {
                    $whereConditions[] = "m.location LIKE ?";
                    $params[] = '%' . $filters['location'] . '%';
                }
                
                break;

            case 'events':
                $sql = "SELECT e.*, u.username as created_by, u.full_name 
                        FROM events e 
                        LEFT JOIN users u ON e.user_id = u.id";
                
                if (!empty($filters['search'])) {
                    $whereConditions[] = "(e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ?)";
                    $searchTerm = '%' . $filters['search'] . '%';
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                }
                
                if (!empty($filters['status'])) {
                    $whereConditions[] = "e.status = ?";
                    $params[] = $filters['status'];
                }
                
                if (!empty($filters['date_from'])) {
                    $whereConditions[] = "e.event_date >= ?";
                    $params[] = $filters['date_from'];
                }
                
                if (!empty($filters['date_to'])) {
                    $whereConditions[] = "e.event_date <= ?";
                    $params[] = $filters['date_to'];
                }
                
                break;

            case 'awards':
                $sql = "SELECT a.*, u.username as created_by 
                        FROM awards a 
                        LEFT JOIN users u ON a.user_id = u.id";
                
                if (!empty($filters['search'])) {
                    $whereConditions[] = "(a.title LIKE ? OR a.description LIKE ?)";
                    $searchTerm = '%' . $filters['search'] . '%';
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                }
                
                if (!empty($filters['category'])) {
                    $whereConditions[] = "a.category = ?";
                    $params[] = $filters['category'];
                }
                
                if (!empty($filters['date_from'])) {
                    $whereConditions[] = "a.created_at >= ?";
                    $params[] = $filters['date_from'];
                }
                
                if (!empty($filters['date_to'])) {
                    $whereConditions[] = "a.created_at <= ?";
                    $params[] = $filters['date_to'];
                }
                
                break;

            default:
                throw new Exception('Unsupported entity type');
        }

        // Add WHERE clause
        if (!empty($whereConditions)) {
            $sql .= " WHERE " . implode(" AND ", $whereConditions);
        }

        // Add sorting
        $validSortFields = ['created_at', 'updated_at', 'title', 'name'];
        if (in_array($sortBy, $validSortFields)) {
            $sql .= " ORDER BY $sortBy " . ($sortOrder === 'ASC' ? 'ASC' : 'DESC');
        } else {
            $sql .= " ORDER BY created_at DESC";
        }

        // Add pagination
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get total count
        $countSql = str_replace(
            "SELECT " . explode("FROM", $sql)[0],
            "SELECT COUNT(*) as total",
            $sql
        );
        $countSql = preg_replace('/LIMIT \? OFFSET \?/', '', $countSql);
        $countParams = array_slice($params, 0, -2);
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($countParams);
        $total = $countStmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'data' => $results,
            'total' => (int)$total,
            'limit' => $limit,
            'offset' => $offset
        ]);

    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

