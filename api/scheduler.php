<?php
/**
 * Scheduler API
 * Handles CRUD operations for schedules with authentication
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

// Authentication check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user']['role'] ?? 'user';
$isAdmin = ($userRole === 'admin');

require_once __DIR__ . '/config.php';

try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        http_response_code(503);
        echo json_encode(['success' => false, 'error' => 'Database connection required']);
        exit();
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'list';
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    // GET: List all schedules (admin sees all, user sees only their own)
    if ($method === 'GET' && $action === 'list') {
        if ($isAdmin) {
            $stmt = $pdo->query("
                SELECT s.*, u.username as created_by 
                FROM schedules s 
                LEFT JOIN users u ON s.user_id = u.id 
                ORDER BY s.scheduled_date DESC, s.scheduled_time DESC
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT s.*, u.username as created_by 
                FROM schedules s 
                LEFT JOIN users u ON s.user_id = u.id 
                WHERE s.user_id = ? 
                ORDER BY s.scheduled_date DESC, s.scheduled_time DESC
            ");
            $stmt->execute([$userId]);
        }

        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'schedules' => $schedules]);
        exit();
    }

    // POST: Create new schedule
    if ($method === 'POST' && $action === 'create') {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        // Validate required fields
        if (empty($data['title'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Title is required']);
            exit();
        }

        if (empty($data['scheduled_date']) && empty($data['date'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Scheduled date is required']);
            exit();
        }

        // Extract and format data
        $title = trim($data['title']);
        $description = $data['description'] ?? '';
        $scheduledDate = $data['scheduled_date'] ?? $data['date'] ?? null;
        
        // Handle time - combine start_time and end_time if provided, or use scheduled_time
        $scheduledTime = null;
        if (!empty($data['scheduled_time'])) {
            $scheduledTime = $data['scheduled_time'];
        } elseif (!empty($data['start_time']) || !empty($data['startTime'])) {
            // Use start_time as the scheduled_time
            $scheduledTime = $data['start_time'] ?? $data['startTime'];
        }
        
        $status = $data['status'] ?? 'scheduled';

        // Validate date format
        if ($scheduledDate) {
            $dateParts = date_parse($scheduledDate);
            if (!$dateParts || !checkdate($dateParts['month'], $dateParts['day'], $dateParts['year'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid date format']);
                exit();
            }
        }

        // Insert schedule
        $stmt = $pdo->prepare("
            INSERT INTO schedules (
                user_id, title, description, scheduled_date, scheduled_time, status
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            $title,
            $description,
            $scheduledDate,
            $scheduledTime,
            $status
        ]);

        $newId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Schedule created successfully',
            'id' => $newId
        ]);
        exit();
    }

    // PUT: Update schedule
    if ($method === 'PUT' && $action === 'update' && $id > 0) {
        // Check if schedule exists and user has permission
        $stmt = $pdo->prepare("SELECT user_id FROM schedules WHERE id = ?");
        $stmt->execute([$id]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$schedule) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Schedule not found']);
            exit();
        }

        // User can only update their own schedules (unless admin)
        if (!$isAdmin && $schedule['user_id'] != $userId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Permission denied']);
            exit();
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        // Build update query dynamically
        $updates = [];
        $params = [];

        if (isset($data['title'])) {
            $updates[] = 'title = ?';
            $params[] = trim($data['title']);
        }

        if (isset($data['description'])) {
            $updates[] = 'description = ?';
            $params[] = $data['description'];
        }

        if (isset($data['scheduled_date']) || isset($data['date'])) {
            $updates[] = 'scheduled_date = ?';
            $params[] = $data['scheduled_date'] ?? $data['date'];
        }

        if (isset($data['scheduled_time']) || isset($data['start_time']) || isset($data['startTime'])) {
            $updates[] = 'scheduled_time = ?';
            $params[] = $data['scheduled_time'] ?? $data['start_time'] ?? $data['startTime'];
        }

        if (isset($data['status'])) {
            $updates[] = 'status = ?';
            $params[] = $data['status'];
        }

        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No fields to update']);
            exit();
        }

        $params[] = $id;
        $sql = "UPDATE schedules SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode([
            'success' => true,
            'message' => 'Schedule updated successfully'
        ]);
        exit();
    }

    // DELETE: Delete schedule
    if ($method === 'DELETE' && $action === 'delete' && $id > 0) {
        // Check if schedule exists and user has permission
        $stmt = $pdo->prepare("SELECT user_id FROM schedules WHERE id = ?");
        $stmt->execute([$id]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$schedule) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Schedule not found']);
            exit();
        }

        // User can only delete their own schedules (unless admin)
        if (!$isAdmin && $schedule['user_id'] != $userId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Permission denied']);
            exit();
        }

        $stmt = $pdo->prepare("DELETE FROM schedules WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode([
            'success' => true,
            'message' => 'Schedule deleted successfully'
        ]);
        exit();
    }

    // GET: Get single schedule
    if ($method === 'GET' && $action === 'get' && $id > 0) {
        if ($isAdmin) {
            $stmt = $pdo->prepare("
                SELECT s.*, u.username as created_by 
                FROM schedules s 
                LEFT JOIN users u ON s.user_id = u.id 
                WHERE s.id = ?
            ");
            $stmt->execute([$id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT s.*, u.username as created_by 
                FROM schedules s 
                LEFT JOIN users u ON s.user_id = u.id 
                WHERE s.id = ? AND s.user_id = ?
            ");
            $stmt->execute([$id, $userId]);
        }

        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$schedule) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Schedule not found']);
            exit();
        }

        echo json_encode(['success' => true, 'schedule' => $schedule]);
        exit();
    }

    // Invalid request
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();

} catch (PDOException $e) {
    error_log('Scheduler API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
    exit();
} catch (Exception $e) {
    error_log('Scheduler API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An error occurred']);
    exit();
}
?>

