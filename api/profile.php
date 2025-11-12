<?php
/**
 * User Profile Management API
 * Handles profile updates and password changes
 */

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config.php';

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login.']);
    exit();
}

$userId = $_SESSION['user_id'];

// Get database connection
try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'MySQL database required for profile management']);
        exit();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Handle different HTTP methods
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Get user profile
            $stmt = $pdo->prepare('SELECT id, username, email, role, department, phone, created_at FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($profile) {
                echo json_encode(['success' => true, 'profile' => $profile]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Profile not found']);
            }
            break;

        case 'POST':
            // Get JSON input
            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? '';

            if ($action === 'update') {
                // Update profile
                $username = trim($input['username'] ?? '');
                $email = trim($input['email'] ?? '');
                $department = trim($input['department'] ?? '');
                $phone = trim($input['phone'] ?? '');

                // Validation
                if (empty($username)) {
                    throw new Exception('Username is required');
                }

                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Valid email is required');
                }

                // Check if username is already taken by another user
                $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
                $stmt->execute([$username, $userId]);
                if ($stmt->fetch()) {
                    throw new Exception('Username is already taken');
                }

                // Check if email is already taken by another user
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
                $stmt->execute([$email, $userId]);
                if ($stmt->fetch()) {
                    throw new Exception('Email is already taken');
                }

                // Update user profile
                $stmt = $pdo->prepare('
                    UPDATE users
                    SET username = ?, email = ?, department = ?, phone = ?, updated_at = NOW()
                    WHERE id = ?
                ');
                $stmt->execute([$username, $email, $department, $phone, $userId]);

                // Update session data
                $_SESSION['user']['username'] = $username;
                $_SESSION['user']['email'] = $email;

                // Get updated profile
                $stmt = $pdo->prepare('SELECT id, username, email, role, department, phone, created_at FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                $updatedProfile = $stmt->fetch(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'message' => 'Profile updated successfully',
                    'profile' => $updatedProfile
                ]);

            } elseif ($action === 'change_password') {
                // Change password
                $currentPassword = $input['current_password'] ?? '';
                $newPassword = $input['new_password'] ?? '';

                // Validation
                if (empty($currentPassword)) {
                    throw new Exception('Current password is required');
                }

                if (empty($newPassword)) {
                    throw new Exception('New password is required');
                }

                if (strlen($newPassword) < 6) {
                    throw new Exception('New password must be at least 6 characters');
                }

                // Get current user password
                $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    throw new Exception('User not found');
                }

                // Verify current password
                if (!password_verify($currentPassword, $user['password_hash'])) {
                    throw new Exception('Current password is incorrect');
                }

                // Hash new password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                // Update password
                $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$hashedPassword, $userId]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Password changed successfully'
                ]);

            } else {
                throw new Exception('Invalid action');
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
