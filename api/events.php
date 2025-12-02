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

    // Helper function to check if a column exists in a table
    function columnExists($pdo, $table, $column) {
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $stmt->execute([$column]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    // Check if image_path column exists
    $hasImagePathColumn = columnExists($pdo, 'events', 'image_path');

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'list';
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    // Debug logging for DELETE requests
    if ($method === 'DELETE') {
        error_log("DELETE API call: method=$method, id=$id, action=$action, userId=$userId, isAdmin=" . ($isAdmin ? 'true' : 'false'));
    }

    // GET: List all events (admin sees all, user sees only their own)
    if ($method === 'GET' && $action === 'list' && $id === 0) {
        if ($isAdmin) {
        $stmt = $pdo->query("
            SELECT
                e.*,
                u.username,
                u.full_name as created_by,
                od.title as document_title,
                od.file_path as document_path
            FROM events e
            LEFT JOIN users u ON e.user_id = u.id
            LEFT JOIN other_documents od ON e.document_id = od.id
            WHERE e.deleted_at IS NULL
            ORDER BY e.event_date DESC, e.start_time DESC
        ");
        } else {
            $stmt = $pdo->prepare("
                SELECT
                    e.*,
                    u.username,
                    u.full_name as created_by,
                    od.title as document_title,
                    od.file_path as document_path
                FROM events e
                LEFT JOIN users u ON e.user_id = u.id
                LEFT JOIN other_documents od ON e.document_id = od.id
                WHERE e.user_id = ? AND e.deleted_at IS NULL
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
                u.full_name as created_by,
                od.file_path as document_path,
                od.title as document_title
            FROM events e
            LEFT JOIN users u ON e.user_id = u.id
            LEFT JOIN other_documents od ON e.document_id = od.id
            WHERE e.deleted_at IS NULL
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
                'createdAt' => $event['created_at'],
                'document_path' => $event['document_path'] ?? null,
                'document_title' => $event['document_title'] ?? null,
                'image_path' => $event['image_path'] ?? null
            ];
        }, $events);

        echo json_encode(['success' => true, 'events' => $transformedEvents]);
        exit();
    }

    // GET: Get specific event details (all authenticated users can view any event)
    if ($method === 'GET' && $id > 0 && $action !== 'restore') {
        $stmt = $pdo->prepare("
            SELECT
                e.*,
                u.username,
                u.full_name as created_by,
                od.title as document_title,
                od.file_path as document_path,
                od.id as document_id
            FROM events e
            LEFT JOIN users u ON e.user_id = u.id
            LEFT JOIN other_documents od ON e.document_id = od.id
            WHERE e.id = ? AND e.deleted_at IS NULL
        ");
        $stmt->execute([$id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Event not found']);
            exit();
        }

        // Allow all authenticated users to view any event (removed permission check)
        echo json_encode(['success' => true, 'event' => $event]);
        exit();
    }

    // POST: Create new event (all authenticated users can create events)
    if ($method === 'POST' && $action === 'create') {
        // Allow all authenticated users to create events
        // Removed admin-only restriction

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
        $documentId = isset($data['document_id']) && $data['document_id'] !== '' ? (int)$data['document_id'] : null;
        $imagePath = $data['image_path'] ?? null;

        // Insert event - conditionally include image_path if column exists
        if ($hasImagePathColumn) {
            $stmt = $pdo->prepare("
                INSERT INTO events (
                    user_id, title, description, event_date,
                    start_time, end_time, location, status, document_id, image_path
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $title,
                $description,
                $eventDate,
                $startTime,
                $endTime,
                $location,
                $status,
                $documentId,
                $imagePath
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO events (
                    user_id, title, description, event_date,
                    start_time, end_time, location, status, document_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $title,
                $description,
                $eventDate,
                $startTime,
                $endTime,
                $location,
                $status,
                $documentId
            ]);
        }

        $newId = $pdo->lastInsertId();

        // Trigger AI analysis for ICONS 2025 award matching
        $aiAnalysis = null;
        try {
            require_once __DIR__ . '/ai-award-analyzer.php';
            // The analyzer functions are already defined, call directly
            $text = implode(' ', [$title, $description, $location]);
            $matches = analyzeTextAgainstAwards($text);
            if (!empty($matches)) {
                saveEventMatches($pdo, $newId, $matches);
                $aiAnalysis = [
                    'analyzed' => true,
                    'matches' => count($matches),
                    'top_match' => $matches[0]['award_title'] ?? null,
                    'top_score' => $matches[0]['match_score'] ?? 0
                ];
            }
        } catch (Exception $e) {
            // Log error but don't fail the request
            error_log("AI analysis error: " . $e->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => 'Event created successfully',
            'id' => $newId,
            'ai_analysis' => $aiAnalysis
        ]);
        exit();
    }

    // DELETE: Delete event (all authenticated users can delete any event)
    if ($method === 'DELETE' && $id > 0) {
        // Log the delete attempt for debugging
        error_log("DELETE request received: method=$method, id=$id, userId=$userId");
        
        // Check if permanent delete is requested
        $permanent = isset($_GET['permanent']) && $_GET['permanent'] === 'true';
        
        // Check if event exists
        $stmt = $pdo->prepare("SELECT id, user_id FROM events WHERE id = ? AND (deleted_at IS NULL OR ? = true)");
        $stmt->execute([$id, $permanent]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            error_log("DELETE failed: Event not found with id=$id");
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Event not found']);
            exit();
        }

        // Allow all authenticated users to delete events (removed permission check)
        error_log("DELETE proceeding: Deleting event id=$id, permanent=$permanent");

        if ($permanent) {
            // Permanent delete - remove from database
            $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
            $stmt->execute([$id]);
            
            $rowsAffected = $stmt->rowCount();
            error_log("DELETE result: rows_affected=$rowsAffected for event id=$id (permanent)");
            
            echo json_encode([
                'success' => true, 
                'message' => 'Event permanently deleted', 
                'rows_affected' => $rowsAffected
            ]);
        } else {
            // Soft delete - move to trash (set deleted_at)
            try {
                // Ensure deleted_at column exists
                $pdo->exec("ALTER TABLE events ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
            } catch (PDOException $e) {
                // Column might already exist, ignore
            }
            
            $stmt = $pdo->prepare("UPDATE events SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            
            $rowsAffected = $stmt->rowCount();
            error_log("DELETE result: rows_affected=$rowsAffected for event id=$id (soft delete)");
            
            echo json_encode([
                'success' => true, 
                'message' => 'Event moved to trash', 
                'rows_affected' => $rowsAffected
            ]);
        }
        exit();
    }
    
    // GET: Restore event from trash
    if ($method === 'GET' && $action === 'restore' && $id > 0) {
        // Ensure deleted_at column exists
        try {
            $pdo->exec("ALTER TABLE events ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
        } catch (PDOException $e) {
            // Column might already exist, ignore
        }
        
        // Check if event exists and is deleted
        $stmt = $pdo->prepare("SELECT id FROM events WHERE id = ? AND deleted_at IS NOT NULL");
        $stmt->execute([$id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Deleted event not found']);
            exit();
        }

        // Restore event (clear deleted_at)
        $stmt = $pdo->prepare("UPDATE events SET deleted_at = NULL WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Event restored successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to restore event']);
        }
        exit();
    }
    
    // If DELETE method but no id or id is 0, return error
    if ($method === 'DELETE') {
        error_log("DELETE request failed: method=$method, id=$id (invalid id)");
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid event ID']);
        exit();
    }

    // PUT: Update event (admin only, or user can update their own)
    if ($method === 'PUT' && $id > 0) {
        // Check if event exists and get owner (exclude deleted events)
        $stmt = $pdo->prepare("SELECT user_id FROM events WHERE id = ? AND deleted_at IS NULL");
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
        if (isset($data['document_id'])) {
            $updateFields[] = "document_id = ?";
            $updateValues[] = $data['document_id'] !== '' ? (int)$data['document_id'] : null;
        }
        if (isset($data['image_path']) && $hasImagePathColumn) {
            $updateFields[] = "image_path = ?";
            $updateValues[] = $data['image_path'];
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
