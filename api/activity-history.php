<?php
/**
 * Activity History API
 * Tracks and retrieves system activity history
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
        echo json_encode(['success' => false, 'error' => 'Database required for activity history']);
        exit();
    }

    // Ensure activity_history table exists
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS activity_history (
                id INT(11) NOT NULL AUTO_INCREMENT,
                user_id INT(11) DEFAULT NULL,
                action_type VARCHAR(50) NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INT(11) DEFAULT NULL,
                description TEXT,
                old_value TEXT,
                new_value TEXT,
                ip_address VARCHAR(45),
                user_agent VARCHAR(255),
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user_id (user_id),
                KEY idx_entity_type (entity_type),
                KEY idx_entity_id (entity_id),
                KEY idx_created_at (created_at),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Exception $e) {
        // Table might already exist, continue
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    if ($method === 'GET') {
        if ($action === 'list' || $action === '') {
            // Get activity history
            $entityType = $_GET['entity_type'] ?? null;
            $entityId = $_GET['entity_id'] ?? null;
            $limit = (int)($_GET['limit'] ?? 50);
            $offset = (int)($_GET['offset'] ?? 0);

            $sql = "SELECT ah.*, u.username, u.full_name, u.email 
                    FROM activity_history ah 
                    LEFT JOIN users u ON ah.user_id = u.id 
                    WHERE 1=1";
            $params = [];

            if ($entityType) {
                $sql .= " AND ah.entity_type = ?";
                $params[] = $entityType;
            }

            if ($entityId) {
                $sql .= " AND ah.entity_id = ?";
                $params[] = $entityId;
            }

            $sql .= " ORDER BY ah.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count
            $countSql = "SELECT COUNT(*) FROM activity_history WHERE 1=1";
            $countParams = [];
            if ($entityType) {
                $countSql .= " AND entity_type = ?";
                $countParams[] = $entityType;
            }
            if ($entityId) {
                $countSql .= " AND entity_id = ?";
                $countParams[] = $entityId;
            }
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($countParams);
            $total = $countStmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'data' => $activities,
                'total' => (int)$total,
                'limit' => $limit,
                'offset' => $offset
            ]);

        } else {
            throw new Exception('Invalid action');
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Log activity to history
 * This function should be called from other API endpoints
 */
function logActivityHistory($pdo, $userId, $actionType, $entityType, $entityId, $description, $oldValue = null, $newValue = null) {
    try {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);

        $stmt = $pdo->prepare("
            INSERT INTO activity_history 
            (user_id, action_type, entity_type, entity_id, description, old_value, new_value, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            $actionType,
            $entityType,
            $entityId,
            $description,
            $oldValue ? json_encode($oldValue) : null,
            $newValue ? json_encode($newValue) : null,
            $ipAddress,
            $userAgent
        ]);

        return true;
    } catch (Exception $e) {
        error_log('Failed to log activity history: ' . $e->getMessage());
        return false;
    }
}
?>

