<?php
/**
 * Departments API
 * Provides department data for dropdowns and management
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/config.php';

try {
    $pdo = getDatabaseConnection();

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        // Get all active departments
        $status = isset($_GET['status']) ? $_GET['status'] : 'active';

        $query = "SELECT id, name, code, description, status, created_at, updated_at
                  FROM departments";

        if ($status !== 'all') {
            $query .= " WHERE status = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$status]);
        } else {
            $stmt = $pdo->query($query);
        }

        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'departments' => $departments
        ]);

    } elseif ($method === 'POST') {
        // Create new department (admin only)
        $user = $_SESSION['user'];
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden - Admin access required']);
            exit();
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['name']) || !isset($data['code'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Name and code are required']);
            exit();
        }

        $stmt = $pdo->prepare("
            INSERT INTO departments (name, code, description, status)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['name'],
            $data['code'],
            $data['description'] ?? '',
            $data['status'] ?? 'active'
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Department created successfully',
            'id' => $pdo->lastInsertId()
        ]);

    } elseif ($method === 'PUT') {
        // Update department (admin only)
        $user = $_SESSION['user'];
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden - Admin access required']);
            exit();
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Department ID is required']);
            exit();
        }

        $stmt = $pdo->prepare("
            UPDATE departments
            SET name = ?, code = ?, description = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $data['name'],
            $data['code'],
            $data['description'] ?? '',
            $data['status'] ?? 'active',
            $data['id']
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Department updated successfully'
        ]);

    } elseif ($method === 'DELETE') {
        // Delete department (admin only)
        $user = $_SESSION['user'];
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden - Admin access required']);
            exit();
        }

        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if ($id === 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Department ID is required']);
            exit();
        }

        // Soft delete by setting status to inactive
        $stmt = $pdo->prepare("UPDATE departments SET status = 'inactive', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode([
            'success' => true,
            'message' => 'Department deleted successfully'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to process request',
        'message' => $e->getMessage()
    ]);
}
