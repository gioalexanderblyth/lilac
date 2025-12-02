<?php
/**
 * Bulk Operations API
 * Handles bulk delete, export, and status update operations
 */

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config.php';

// Include activity history logging function
require_once __DIR__ . '/activity-history.php';

// Make sure the function is available
if (!function_exists('logActivityHistory')) {
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
}

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
        echo json_encode(['success' => false, 'error' => 'Database required for bulk operations']);
        exit();
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);

    if ($method === 'DELETE') {
        // Bulk delete
        $entityType = $input['entity_type'] ?? '';
        $ids = $input['ids'] ?? [];

        if (empty($entityType)) {
            throw new Exception('Entity type is required');
        }

        if (empty($ids) || !is_array($ids)) {
            throw new Exception('IDs array is required');
        }

        $ids = array_filter(array_map('intval', $ids));
        if (empty($ids)) {
            throw new Exception('No valid IDs provided');
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $deleted = 0;
        $errors = [];

        switch ($entityType) {
            case 'awards':
                $stmt = $pdo->prepare("DELETE FROM awards WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $deleted = $stmt->rowCount();
                
                // Log activity for each deleted award
                foreach ($ids as $id) {
                    logActivityHistory($pdo, $userId, 'delete', 'award', $id, "Bulk deleted award #$id");
                }
                break;

            case 'mou_moa':
                // Get file paths before deleting
                $stmt = $pdo->prepare("SELECT id, file_path FROM mou_moa WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare("DELETE FROM mou_moa WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $deleted = $stmt->rowCount();
                
                // Delete physical files
                foreach ($files as $file) {
                    if (!empty($file['file_path'])) {
                        $filePath = __DIR__ . '/../' . $file['file_path'];
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                    }
                    logActivityHistory($pdo, $userId, 'delete', 'mou_moa', $file['id'], "Bulk deleted MOU/MOA #{$file['id']}");
                }
                break;

            case 'events':
                // Soft delete - move to trash (set deleted_at)
                try {
                    // Ensure deleted_at column exists
                    $pdo->exec("ALTER TABLE events ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
                } catch (PDOException $e) {
                    // Column might already exist, ignore
                }
                
                $stmt = $pdo->prepare("UPDATE events SET deleted_at = NOW() WHERE id IN ($placeholders) AND deleted_at IS NULL");
                $stmt->execute($ids);
                $deleted = $stmt->rowCount();
                
                foreach ($ids as $id) {
                    logActivityHistory($pdo, $userId, 'delete', 'event', $id, "Bulk deleted event #$id");
                }
                break;

            case 'schedules':
                $stmt = $pdo->prepare("DELETE FROM schedules WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $deleted = $stmt->rowCount();
                
                foreach ($ids as $id) {
                    logActivityHistory($pdo, $userId, 'delete', 'schedule', $id, "Bulk deleted schedule #$id");
                }
                break;

            case 'documents':
                // Get file paths before deleting
                $stmt = $pdo->prepare("SELECT id, file_path FROM other_documents WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare("DELETE FROM other_documents WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $deleted = $stmt->rowCount();
                
                // Delete physical files
                foreach ($files as $file) {
                    if (!empty($file['file_path'])) {
                        $filePath = __DIR__ . '/../' . $file['file_path'];
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                    }
                    logActivityHistory($pdo, $userId, 'delete', 'document', $file['id'], "Bulk deleted document #{$file['id']}");
                }
                break;

            default:
                throw new Exception('Unsupported entity type');
        }

        echo json_encode([
            'success' => true,
            'deleted' => $deleted,
            'message' => "Successfully deleted $deleted item(s)"
        ]);

    } elseif ($method === 'POST') {
        $action = $input['action'] ?? '';

        if ($action === 'update_status') {
            // Bulk status update
            $entityType = $input['entity_type'] ?? '';
            $ids = $input['ids'] ?? [];
            $status = $input['status'] ?? '';

            if (empty($entityType) || empty($ids) || empty($status)) {
                throw new Exception('Entity type, IDs, and status are required');
            }

            $ids = array_filter(array_map('intval', $ids));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            switch ($entityType) {
                case 'awards':
                    $stmt = $pdo->prepare("UPDATE awards SET status = ? WHERE id IN ($placeholders)");
                    break;
                case 'events':
                    $stmt = $pdo->prepare("UPDATE events SET status = ? WHERE id IN ($placeholders)");
                    break;
                case 'schedules':
                    $stmt = $pdo->prepare("UPDATE schedules SET status = ? WHERE id IN ($placeholders)");
                    break;
                default:
                    throw new Exception('Unsupported entity type for status update');
            }

            $params = array_merge([$status], $ids);
            $stmt->execute($params);
            $updated = $stmt->rowCount();

            foreach ($ids as $id) {
                logActivityHistory($pdo, $userId, 'update', $entityType, $id, "Bulk updated status to '$status'");
            }

            echo json_encode([
                'success' => true,
                'updated' => $updated,
                'message' => "Successfully updated $updated item(s)"
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
?>

