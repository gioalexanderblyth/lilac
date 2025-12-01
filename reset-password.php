<?php
/**
 * Password Reset Page
 */
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

require_once __DIR__ . '/api/config.php';

$token = $_GET['token'] ?? '';
$step = 'request'; // request, show-link, reset, success
$error = '';
$success = '';
$resetLink = '';

// Ensure reset_token columns exist
try {
    $pdo = getDatabaseConnection();
    if (!($pdo instanceof FileBasedDatabase)) {
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
    }
} catch (Exception $e) {
    // Continue if migration fails
}

// Verify token if provided
if (!empty($token)) {
    try {
        $pdo = getDatabaseConnection();
        if (!($pdo instanceof FileBasedDatabase)) {
            $stmt = $pdo->prepare('SELECT id, email FROM users WHERE reset_token = ? AND reset_token_expires > NOW()');
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $step = 'reset';
            } else {
                $error = 'Invalid or expired reset token';
                $step = 'request';
            }
        } else {
            $error = 'Password reset requires database connection';
        }
    } catch (Exception $e) {
        $error = 'Error verifying token: ' . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'request') {
        $email = $_POST['email'] ?? '';
        
        if (empty($email)) {
            $error = 'Email is required';
        } else {
            try {
                $pdo = getDatabaseConnection();
                if ($pdo instanceof FileBasedDatabase) {
                    $error = 'Password reset requires database connection';
                } else {
                    // Find user by email
                    $stmt = $pdo->prepare('SELECT id, username, email, full_name FROM users WHERE email = ? AND status = "active"');
                    $stmt->execute([$email]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
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
                        
                        // Generate reset link
                        $resetLink = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/reset-password.php?token=' . $resetToken;
                        
                        // Set step to show reset link
                        $step = 'show-link';
                        $success = 'Password reset link generated successfully.';
                    } else {
                        // Don't reveal if email exists (security)
                        $success = 'If an account with that email exists, a password reset link has been generated.';
                        $step = 'request';
                    }
                }
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'reset') {
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($password !== $confirmPassword) {
            $error = 'Passwords do not match';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters';
        } else {
            try {
                $pdo = getDatabaseConnection();
                if ($pdo instanceof FileBasedDatabase) {
                    $error = 'Password reset requires database connection';
                } else {
                    // Find user with valid token
                    $stmt = $pdo->prepare('SELECT id, email FROM users WHERE reset_token = ? AND reset_token_expires > NOW()');
                    $stmt->execute([$token]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$user) {
                        $error = 'Invalid or expired reset token';
                    } else {
                        // Update password and clear reset token
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL, updated_at = NOW() WHERE id = ?');
                        $stmt->execute([$hashedPassword, $user['id']]);
                        
                        // Log activity
                        $stmt = $pdo->prepare('INSERT INTO activity_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)');
                        $stmt->execute([$user['id'], 'password_reset_complete', 'Password reset completed', $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
                        
                        $success = 'Password has been reset successfully. You can now login with your new password.';
                        $step = 'success';
                    }
                }
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - LILAC</title>
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-900 dark:to-gray-800 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-8">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Reset Password</h1>
            <p class="text-gray-600 dark:text-gray-400">
                <?php if ($step === 'request'): ?>
                    Enter your email to generate a reset link
                <?php elseif ($step === 'show-link'): ?>
                    Copy your reset link below
                <?php elseif ($step === 'reset'): ?>
                    Enter your new password
                <?php else: ?>
                    Password reset successful
                <?php endif; ?>
            </p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-lg mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 px-4 py-3 rounded-lg mb-4">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($step === 'request'): ?>
            <form method="POST" action="reset-password.php">
                <input type="hidden" name="action" value="request">
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email Address</label>
                    <input type="email" name="email" required class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="your.email@cpu.edu.ph">
                </div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors">
                    Generate Reset Link
                </button>
            </form>
            <div class="mt-6 text-center">
                <a href="index.php" class="text-blue-600 dark:text-blue-400 hover:underline">Back to Login</a>
            </div>
        <?php elseif ($step === 'show-link'): ?>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Reset Link</label>
                <div class="flex gap-2">
                    <input type="text" id="resetLinkInput" value="<?php echo htmlspecialchars($resetLink); ?>" readonly class="flex-1 px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white text-sm font-mono">
                    <button onclick="copyResetLink()" class="px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors">
                        <span class="material-symbols-outlined text-xl">content_copy</span>
                    </button>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">This link will expire in 1 hour. Copy it and open in your browser to reset your password.</p>
            </div>
            <div class="flex gap-3">
                <a href="<?php echo htmlspecialchars($resetLink); ?>" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors text-center">
                    Open Reset Link
                </a>
                <a href="index.php" class="px-4 py-3 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-900 dark:text-white font-semibold rounded-lg transition-colors">
                    Back to Login
                </a>
            </div>
        <?php elseif ($step === 'reset'): ?>
            <form method="POST" action="reset-password.php">
                <input type="hidden" name="action" value="reset">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">New Password</label>
                    <input type="password" name="password" required minlength="6" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Enter new password">
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Confirm Password</label>
                    <input type="password" name="confirm_password" required minlength="6" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Confirm new password">
                </div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors">
                    Reset Password
                </button>
            </form>
        <?php else: ?>
            <div class="text-center">
                <span class="material-symbols-outlined text-green-500 text-6xl mb-4">check_circle</span>
                <p class="text-gray-700 dark:text-gray-300 mb-6">Your password has been reset successfully!</p>
                <a href="index.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors">
                    Go to Login
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function copyResetLink() {
            const input = document.getElementById('resetLinkInput');
            const button = document.querySelector('button[onclick="copyResetLink()"]');
            if (input) {
                input.select();
                input.setSelectionRange(0, 99999); // For mobile devices
                
                const copyText = input.value;
                navigator.clipboard.writeText(copyText).then(() => {
                    // Show feedback
                    if (button) {
                        const originalHTML = button.innerHTML;
                        button.innerHTML = '<span class="material-symbols-outlined text-xl">check</span>';
                        button.classList.add('bg-green-600');
                        button.classList.remove('bg-blue-600');
                        
                        setTimeout(() => {
                            button.innerHTML = originalHTML;
                            button.classList.remove('bg-green-600');
                            button.classList.add('bg-blue-600');
                        }, 2000);
                    }
                }).catch(err => {
                    // Fallback for older browsers
                    document.execCommand('copy');
                    if (button) {
                        const originalHTML = button.innerHTML;
                        button.innerHTML = '<span class="material-symbols-outlined text-xl">check</span>';
                        setTimeout(() => {
                            button.innerHTML = originalHTML;
                        }, 2000);
                    }
                });
            }
        }
    </script>
</body>
</html>

