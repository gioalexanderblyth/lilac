<?php
/**
 * Password Reset API
 * Handles password reset requests and confirmation
 */

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config.php';

// Check if user is authenticated (not required for password reset)
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        throw new Exception('Password reset requires database connection');
    }

    // Ensure reset_token columns exist
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'reset_token'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) NULL DEFAULT NULL AFTER password_hash");
        }
    } catch (Exception $e) {
        // Column might already exist, continue
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'reset_token_expires'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN reset_token_expires DATETIME NULL DEFAULT NULL AFTER reset_token");
        }
    } catch (Exception $e) {
        // Column might already exist, continue
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if ($action === 'request') {
            // Request password reset
            $email = $input['email'] ?? '';

            if (empty($email)) {
                throw new Exception('Email is required');
            }

            // Find user by email
            $stmt = $pdo->prepare('SELECT id, username, email, full_name FROM users WHERE email = ? AND status = "active"');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Always return success message (security: don't reveal if email exists)
            $response = [
                'success' => true,
                'message' => 'If an account with that email exists, a password reset link has been sent.'
            ];

            if ($user) {
                // Generate reset token
                $resetToken = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Save reset token
                $stmt = $pdo->prepare('UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?');
                $stmt->execute([$resetToken, $expiresAt, $user['id']]);

                // Log activity
                $stmt = $pdo->prepare('INSERT INTO activity_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)');
                $stmt->execute([$user['id'], 'password_reset_request', 'Password reset requested', $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            }

            echo json_encode($response);

        } elseif ($action === 'verify') {
            // Verify reset token
            $token = $input['token'] ?? '';

            if (empty($token)) {
                throw new Exception('Reset token is required');
            }

            $stmt = $pdo->prepare('SELECT id, email FROM users WHERE reset_token = ? AND reset_token_expires > NOW()');
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                echo json_encode([
                    'success' => true,
                    'valid' => true,
                    'message' => 'Token is valid'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'valid' => false,
                    'message' => 'Invalid or expired reset token'
                ]);
            }

        } elseif ($action === 'reset') {
            // Reset password
            $token = $input['token'] ?? '';
            $newPassword = $input['password'] ?? '';

            if (empty($token)) {
                throw new Exception('Reset token is required');
            }

            if (empty($newPassword)) {
                throw new Exception('New password is required');
            }

            if (strlen($newPassword) < 6) {
                throw new Exception('Password must be at least 6 characters');
            }

            // Find user with valid token
            $stmt = $pdo->prepare('SELECT id, email FROM users WHERE reset_token = ? AND reset_token_expires > NOW()');
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new Exception('Invalid or expired reset token');
            }

            // Update password and clear reset token
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$hashedPassword, $user['id']]);

            // Log activity
            $stmt = $pdo->prepare('INSERT INTO activity_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)');
            $stmt->execute([$user['id'], 'password_reset_complete', 'Password reset completed', $_SERVER['REMOTE_ADDR'] ?? 'unknown']);

            echo json_encode([
                'success' => true,
                'message' => 'Password has been reset successfully'
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
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

