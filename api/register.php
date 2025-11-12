<?php
/**
 * User Registration API
 * Handles new user signup
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        http_response_code(501);
        echo json_encode([
            'success' => false,
            'error' => 'Registration requires database connection'
        ]);
        exit();
    }

    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    // If data is not JSON, try to get from POST
    if (!$data) {
        $data = $_POST;
    }

    // Validate required fields
    $requiredFields = ['username', 'email', 'password', 'full_name'];
    $missingFields = [];

    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $missingFields[] = $field;
        }
    }

    if (!empty($missingFields)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Missing required fields: ' . implode(', ', $missingFields)
        ]);
        exit();
    }

    $username = trim($data['username']);
    $email = trim($data['email']);
    $password = $data['password'];
    $fullName = trim($data['full_name']);
    $department = $data['department'] ?? null;
    $phone = $data['phone'] ?? null;

    // Validate username format (alphanumeric, underscore, dash only)
    if (!preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $username)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Username must be 3-50 characters and contain only letters, numbers, underscores, and dashes'
        ]);
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid email format'
        ]);
        exit();
    }

    // Validate password strength
    if (strlen($password) < 8) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Password must be at least 8 characters long'
        ]);
        exit();
    }

    // Check if username already exists
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'Username already exists'
        ]);
        exit();
    }

    // Check if email already exists
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'Email address already registered'
        ]);
        exit();
    }

    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user (default role is 'user', status is 'active')
    $stmt = $pdo->prepare('
        INSERT INTO users (username, email, password_hash, full_name, department, phone, role, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ');

    $stmt->execute([
        $username,
        $email,
        $passwordHash,
        $fullName,
        $department,
        $phone,
        'user', // Default role
        'active' // Default status
    ]);

    $userId = $pdo->lastInsertId();

    // Log registration activity
    try {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $pdo->prepare('INSERT INTO activity_log (user_id, action, ip_address) VALUES (?, "registration", ?)');
        $stmt->execute([$userId, $ipAddress]);
    } catch (Exception $e) {
        // Log error but don't fail registration
        error_log('Failed to log registration activity: ' . $e->getMessage());
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Registration successful! You can now log in.',
        'user_id' => $userId,
        'username' => $username
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Registration failed: ' . $e->getMessage()
    ]);
    error_log('Registration error: ' . $e->getMessage());
}
