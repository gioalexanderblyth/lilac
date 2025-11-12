<?php
/**
 * Events API
 * Handles CRUD operations for events with authentication and role-based access
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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
$userRole = $_SESSION['role'] ?? 'user';
$isAdmin = ($userRole === 'admin');

require_once __DIR__ . '/config.php';

try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        echo json_encode(['success' => false, 'error' => 'Database required']);
        exit();
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'list';
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    // GET: List all events (admin sees all, user sees only their own)
    if ($method === 'GET' && $action === 'list') {
        if ($isAdmin) {
            $stmt = $pdo->query("
                SELECT
                    e.*,
                    u.username,
                    u.full_name as created_by
                FROM events e
                LEFT JOIN users u ON e.user_id = u.id
                ORDER BY e.event_date DESC, e.start_time DESC
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT
                    e.*,
                    u.username,
                    u.full_name as created_by
                FROM events e
                LEFT JOIN users u ON e.user_id = u.id
                WHERE e.user_id = ?
                ORDER BY e.event_date DESC, e.start_time DESC
            ");
            $stmt->execute([$userId]);
        }

        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'events' => $events]);
        exit();
    }

    // GET: Get events for calendar (all users see all events)
    if ($method === 'GET' && $action === 'calendar') {
        $stmt = $pdo->query("
            SELECT
                e.*,
                u.username,
                u.full_name as created_by
            FROM events e
            LEFT JOIN users u ON e.user_id = u.id
            ORDER BY e.event_date ASC, e.start_time ASC
        ");

        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Transform to frontend format
        $transformedEvents = array_map(function($event) {
            $startTime = $event['start_time'] ?? '09:00:00';
            $endTime = $event['end_time'] ?? '17:00:00';

            return [
                'id' => $event['id'],
                'user_id' => $event['user_id'],
                'title' => $event['title'],
                'date' => $event['event_date'],
                'timeRange' => substr($startTime, 0, 5) . ' - ' . substr($endTime, 0, 5),
                'start_time' => $startTime,
                'end_time' => $endTime,
                'location' => $event['location'],
                'description' => $event['description'],
                'status' => $event['status'],
                'created_by' => $event['created_by'] ?? $event['username'],
                'createdAt' => $event['created_at']
            ];
        }, $events);

        echo json_encode(['success' => true, 'events' => $transformedEvents]);
        exit();
    }

    // GET: Get specific event details
    if ($method === 'GET' && $id > 0) {
        $stmt = $pdo->prepare("
            SELECT
                e.*,
                u.username,
                u.full_name as created_by
            FROM events e
            LEFT JOIN users u ON e.user_id = u.id
            WHERE e.id = ?
        ");
        $stmt->execute([$id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Event not found']);
            exit();
        }

        // Check permission (users can only view their own events unless admin)
        if (!$isAdmin && $event['user_id'] != $userId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            exit();
        }

        echo json_encode(['success' => true, 'event' => $event]);
        exit();
    }

    // POST: Create new event (admin only)
    if ($method === 'POST' && $action === 'create') {
        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Only admins can create events']);
            exit();
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        // Validate required fields
        $required = ['title', 'event_date'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => "Field '$field' is required"]);
                exit();
            }
        }

        // Set defaults
        $title = $data['title'];
        $description = $data['description'] ?? '';
        $eventDate = $data['event_date'] ?? $data['date'] ?? null;
        $startTime = $data['start_time'] ?? '09:00:00';
        $endTime = $data['end_time'] ?? '17:00:00';
        $location = $data['location'] ?? '';
        $status = $data['status'] ?? 'planned';

        // Insert event
        $stmt = $pdo->prepare("
            INSERT INTO events (
                user_id, title, description, event_date,
                start_time, end_time, location, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            $title,
            $description,
            $eventDate,
            $startTime,
            $endTime,
            $location,
            $status
        ]);

        $newId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Event created successfully',
            'id' => $newId
        ]);
        exit();
    }

    // DELETE: Delete event (admin only, or user can delete their own)
    if ($method === 'DELETE' && $id > 0) {
        // Check if event exists and get owner
        $stmt = $pdo->prepare("SELECT user_id FROM events WHERE id = ?");
        $stmt->execute([$id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Event not found']);
            exit();
        }

        // Check permission
        if (!$isAdmin && $event['user_id'] != $userId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            exit();
        }

        // Delete event
        $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Event deleted successfully']);
        exit();
    }

    // PUT: Update event (admin only, or user can update their own)
    if ($method === 'PUT' && $id > 0) {
        // Check if event exists and get owner
        $stmt = $pdo->prepare("SELECT user_id FROM events WHERE id = ?");
        $stmt->execute([$id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Event not found']);
            exit();
        }

        // Check permission
        if (!$isAdmin && $event['user_id'] != $userId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            exit();
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        // Build dynamic UPDATE query based on provided fields
        $updateFields = [];
        $updateValues = [];

        if (isset($data['title'])) {
            $updateFields[] = "title = ?";
            $updateValues[] = $data['title'];
        }
        if (isset($data['description'])) {
            $updateFields[] = "description = ?";
            $updateValues[] = $data['description'];
        }
        if (isset($data['event_date']) || isset($data['date'])) {
            $updateFields[] = "event_date = ?";
            $updateValues[] = $data['event_date'] ?? $data['date'];
        }
        if (isset($data['start_time'])) {
            $updateFields[] = "start_time = ?";
            $updateValues[] = $data['start_time'];
        }
        if (isset($data['end_time'])) {
            $updateFields[] = "end_time = ?";
            $updateValues[] = $data['end_time'];
        }
        if (isset($data['location'])) {
            $updateFields[] = "location = ?";
            $updateValues[] = $data['location'];
        }
        if (isset($data['status'])) {
            $updateFields[] = "status = ?";
            $updateValues[] = $data['status'];
        }

        if (empty($updateFields)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No fields to update']);
            exit();
        }

        // Add ID to values array
        $updateValues[] = $id;

        // Execute update
        $sql = "UPDATE events SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateValues);

        echo json_encode(['success' => true, 'message' => 'Event updated successfully']);
        exit();
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>
