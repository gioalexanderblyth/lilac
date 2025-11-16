<?php
/**
 * Comments API
 * Handles comments and collaboration features
 */

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/activity-history.php';

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
        echo json_encode(['success' => false, 'error' => 'Database required for comments']);
        exit();
    }

    // Ensure comments table exists
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS comments (
                id INT(11) NOT NULL AUTO_INCREMENT,
                user_id INT(11) NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INT(11) NOT NULL,
                comment_text TEXT NOT NULL,
                parent_id INT(11) DEFAULT NULL,
                is_resolved TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user_id (user_id),
                KEY idx_entity (entity_type, entity_id),
                KEY idx_parent_id (parent_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Exception $e) {
        // Table might already exist, continue
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    if ($method === 'GET') {
        // Get comments
        $entityType = $_GET['entity_type'] ?? '';
        $entityId = (int)($_GET['entity_id'] ?? 0);

        if (empty($entityType) || $entityId === 0) {
            throw new Exception('Entity type and ID are required');
        }

        $stmt = $pdo->prepare("
            SELECT c.*, u.username, u.full_name, u.email, u.profile_picture
            FROM comments c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.entity_type = ? AND c.entity_id = ?
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([$entityType, $entityId]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $comments
        ]);

    } elseif ($method === 'POST') {
        // Create comment
        $input = json_decode(file_get_contents('php://input'), true);
        
        $entityType = $input['entity_type'] ?? '';
        $entityId = (int)($input['entity_id'] ?? 0);
        $commentText = trim($input['comment_text'] ?? '');
        $parentId = !empty($input['parent_id']) ? (int)$input['parent_id'] : null;

        if (empty($entityType) || $entityId === 0) {
            throw new Exception('Entity type and ID are required');
        }

        if (empty($commentText)) {
            throw new Exception('Comment text is required');
        }

        $stmt = $pdo->prepare("
            INSERT INTO comments (user_id, entity_type, entity_id, comment_text, parent_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $entityType, $entityId, $commentText, $parentId]);
        
        $commentId = $pdo->lastInsertId();

        // Get created comment with user info
        $stmt = $pdo->prepare("
            SELECT c.*, u.username, u.full_name, u.email, u.profile_picture
            FROM comments c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);

        // Log activity
        logActivityHistory($pdo, $userId, 'create', 'comment', $commentId, "Added comment on $entityType #$entityId");

        echo json_encode([
            'success' => true,
            'data' => $comment,
            'message' => 'Comment added successfully'
        ]);

    } elseif ($method === 'PUT') {
        // Update comment
        $id = (int)($_GET['id'] ?? 0);
        $input = json_decode(file_get_contents('php://input'), true);
        $commentText = trim($input['comment_text'] ?? '');
        $isResolved = isset($input['is_resolved']) ? (int)$input['is_resolved'] : null;

        if ($id === 0) {
            throw new Exception('Comment ID is required');
        }

        // Check if user owns the comment
        $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
        $stmt->execute([$id]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$comment) {
            throw new Exception('Comment not found');
        }

        if ($comment['user_id'] != $userId) {
            // Check if user is admin
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user || $user['role'] !== 'admin') {
                throw new Exception('You can only edit your own comments');
            }
        }

        $updates = [];
        $params = [];

        if (!empty($commentText)) {
            $updates[] = "comment_text = ?";
            $params[] = $commentText;
        }

        if ($isResolved !== null) {
            $updates[] = "is_resolved = ?";
            $params[] = $isResolved;
        }

        if (empty($updates)) {
            throw new Exception('No fields to update');
        }

        $params[] = $id;
        $sql = "UPDATE comments SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Log activity
        logActivityHistory($pdo, $userId, 'update', 'comment', $id, 'Updated comment');

        echo json_encode([
            'success' => true,
            'message' => 'Comment updated successfully'
        ]);

    } elseif ($method === 'DELETE') {
        // Delete comment
        $id = (int)($_GET['id'] ?? 0);

        if ($id === 0) {
            throw new Exception('Comment ID is required');
        }

        // Check if user owns the comment or is admin
        $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
        $stmt->execute([$id]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$comment) {
            throw new Exception('Comment not found');
        }

        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $isAdmin = $user && $user['role'] === 'admin';

        if ($comment['user_id'] != $userId && !$isAdmin) {
            throw new Exception('You can only delete your own comments');
        }

        $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
        $stmt->execute([$id]);

        // Log activity
        logActivityHistory($pdo, $userId, 'delete', 'comment', $id, 'Deleted comment');

        echo json_encode([
            'success' => true,
            'message' => 'Comment deleted successfully'
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

