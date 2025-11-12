<?php
/**
 * Users API
 * Provides user data for dropdowns and management
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
        // Get users with optional role filtering
        $roleFilter = isset($_GET['role']) ? $_GET['role'] : null;

        $query = "SELECT id, username, email, full_name, role, department, status, created_at
                  FROM users
                  WHERE status = 'active'";

        $params = [];

        if ($roleFilter) {
            // Support comma-separated roles (e.g., "admin,user")
            $roles = array_map('trim', explode(',', $roleFilter));
            $placeholders = str_repeat('?,', count($roles) - 1) . '?';
            $query .= " AND role IN ($placeholders)";
            $params = $roles;
        }

        $query .= " ORDER BY full_name ASC, username ASC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'users' => $users
        ]);

    } elseif ($method === 'POST') {
        // Create new user (admin only)
        $user = $_SESSION['user'];
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden - Admin access required']);
            exit();
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['username']) || !isset($data['email']) || !isset($data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Username, email, and password are required']);
            exit();
        }

        // Check if username or email already exists
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $checkStmt->execute([$data['username'], $data['email']]);
        if ($checkStmt->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode(['error' => 'Username or email already exists']);
            exit();
        }

        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, full_name, role, department, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

        $stmt->execute([
            $data['username'],
            $data['email'],
            $hashedPassword,
            $data['full_name'] ?? '',
            $data['role'] ?? 'user',
            $data['department'] ?? null,
            $data['status'] ?? 'active'
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'User created successfully',
            'id' => $pdo->lastInsertId()
        ]);

    } elseif ($method === 'PUT') {
        // Update user (admin only, or self for basic info)
        $user = $_SESSION['user'];
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID is required']);
            exit();
        }

        // Users can only update themselves unless they're admin
        if ($user['role'] !== 'admin' && $data['id'] != $_SESSION['user_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden - Cannot update other users']);
            exit();
        }

        // Build dynamic update query
        $updates = [];
        $params = [];

        if (isset($data['full_name'])) {
            $updates[] = "full_name = ?";
            $params[] = $data['full_name'];
        }

        if (isset($data['email'])) {
            $updates[] = "email = ?";
            $params[] = $data['email'];
        }

        if (isset($data['department'])) {
            $updates[] = "department = ?";
            $params[] = $data['department'];
        }

        // Only admins can change role and status
        if ($user['role'] === 'admin') {
            if (isset($data['role'])) {
                $updates[] = "role = ?";
                $params[] = $data['role'];
            }

            if (isset($data['status'])) {
                $updates[] = "status = ?";
                $params[] = $data['status'];
            }
        }

        // Handle password change
        if (isset($data['password']) && !empty($data['password'])) {
            $updates[] = "password = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['error' => 'No valid fields to update']);
            exit();
        }

        $updates[] = "updated_at = NOW()";
        $params[] = $data['id'];

        $query = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        echo json_encode([
            'success' => true,
            'message' => 'User updated successfully'
        ]);

    } elseif ($method === 'DELETE') {
        // Delete user (admin only)
        $user = $_SESSION['user'];
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden - Admin access required']);
            exit();
        }

        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if ($id === 0) {
            http_response_code(400);
            echo json_encode(['error' => 'User ID is required']);
            exit();
        }

        // Prevent deleting yourself
        if ($id == $_SESSION['user_id']) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete your own account']);
            exit();
        }

        // Soft delete by setting status to inactive
        $stmt = $pdo->prepare("UPDATE users SET status = 'inactive', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode([
            'success' => true,
            'message' => 'User deleted successfully'
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
