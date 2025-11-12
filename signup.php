<?php
/**
 * LILAC User Registration Page
 */
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $fullName = trim($_POST['full_name'] ?? '');
    $department = $_POST['department'] ?? null;
    $phone = trim($_POST['phone'] ?? '');

    // Basic validation
    if (empty($username) || empty($email) || empty($password) || empty($fullName)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        // Call registration API
        require_once __DIR__ . '/api/config.php';

        try {
            $pdo = getDatabaseConnection();

            if ($pdo instanceof FileBasedDatabase) {
                $error = 'Registration requires database connection. Please contact administrator.';
            } else {
                // Check if username exists
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
                $stmt->execute([$username]);
                if ($stmt->fetchColumn() > 0) {
                    $error = 'Username already exists';
                } else {
                    // Check if email exists
                    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
                    $stmt->execute([$email]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = 'Email address already registered';
                    } else {
                        // Create user
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                        $stmt = $pdo->prepare('
                            INSERT INTO users (username, email, password_hash, full_name, department, phone, role, status, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ');

                        $stmt->execute([
                            $username,
                            $email,
                            $passwordHash,
                            $fullName,
                            !empty($department) ? $department : null,
                            !empty($phone) ? $phone : null,
                            'user',
                            'active'
                        ]);

                        $userId = $pdo->lastInsertId();

                        // Log activity
                        try {
                            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                            $stmt = $pdo->prepare('INSERT INTO activity_log (user_id, action, ip_address) VALUES (?, "registration", ?)');
                            $stmt->execute([$userId, $ipAddress]);
                        } catch (Exception $e) {
                            // Ignore activity log errors
                        }

                        $success = 'Registration successful! You can now <a href="index.php" class="underline font-semibold">log in</a>.';
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'Registration failed: ' . $e->getMessage();
            error_log('Registration error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - LILAC</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <style>
        .hero-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .fade-in {
            animation: fadeIn 0.8s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            pointer-events: none;
        }

        .input-with-icon {
            padding-left: 40px;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #9ca3af;
            transition: color 0.2s;
        }

        .password-toggle:hover {
            color: #4b5563;
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <img src="assets/images/cpu-logo.png" alt="CPU Logo" class="h-12 w-auto mr-3"
                        onerror="this.style.display='none'">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">LILAC</h1>
                        <p class="text-sm text-gray-600">Awards Management System</p>
                    </div>
                </div>
                <a href="index.php"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                    <span class="material-symbols-outlined text-lg mr-2">login</span>
                    Sign In
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl w-full">
            <!-- Registration Card -->
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden fade-in">
                <!-- Card Header -->
                <div class="hero-bg px-8 py-6">
                    <h2 class="text-3xl font-bold text-white text-center">Create Your Account</h2>
                    <p class="mt-2 text-center text-purple-100">Join LILAC Awards Management System</p>
                </div>

                <!-- Card Body -->
                <div class="px-8 py-8">
                    <?php if ($error): ?>
                        <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded">
                            <div class="flex">
                                <span class="material-symbols-outlined text-red-400 mr-3">error</span>
                                <p class="text-sm text-red-700"><?= htmlspecialchars($error) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4 rounded">
                            <div class="flex">
                                <span class="material-symbols-outlined text-green-400 mr-3">check_circle</span>
                                <p class="text-sm text-green-700"><?= $success ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="signup.php" class="space-y-5">
                        <input type="hidden" name="action" value="register">

                        <!-- Full Name -->
                        <div>
                            <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1">
                                Full Name <span class="text-red-500">*</span>
                            </label>
                            <div class="input-group">
                                <span class="material-symbols-outlined input-icon">person</span>
                                <input type="text" id="full_name" name="full_name" required
                                    value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                                    class="input-with-icon block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors"
                                    placeholder="Juan Dela Cruz">
                            </div>
                        </div>

                        <!-- Username and Email Row -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <!-- Username -->
                            <div>
                                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">
                                    Username <span class="text-red-500">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="material-symbols-outlined input-icon">badge</span>
                                    <input type="text" id="username" name="username" required
                                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                        pattern="[a-zA-Z0-9_-]{3,50}"
                                        class="input-with-icon block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors"
                                        placeholder="juan_delacruz">
                                    <small class="text-xs text-gray-500 mt-1 block">3-50 characters, letters, numbers, _ and - only</small>
                                </div>
                            </div>

                            <!-- Email -->
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                                    Email Address <span class="text-red-500">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="material-symbols-outlined input-icon">mail</span>
                                    <input type="email" id="email" name="email" required
                                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                        class="input-with-icon block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors"
                                        placeholder="juan@cpu.edu.ph">
                                </div>
                            </div>
                        </div>

                        <!-- Department and Phone Row -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <!-- Department -->
                            <div>
                                <label for="department" class="block text-sm font-medium text-gray-700 mb-1">
                                    Department
                                </label>
                                <div class="input-group">
                                    <span class="material-symbols-outlined input-icon">business</span>
                                    <select id="department" name="department"
                                        class="input-with-icon block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors">
                                        <option value="">Select Department</option>
                                        <?php
                                        require_once __DIR__ . '/api/config.php';
                                        try {
                                            $pdo = getDatabaseConnection();
                                            if (!($pdo instanceof FileBasedDatabase)) {
                                                $stmt = $pdo->query("SELECT name, code FROM departments WHERE status = 'active' ORDER BY name");
                                                while ($dept = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                    $selected = (isset($_POST['department']) && $_POST['department'] === $dept['name']) ? 'selected' : '';
                                                    echo "<option value=\"{$dept['name']}\" {$selected}>{$dept['name']} ({$dept['code']})</option>";
                                                }
                                            }
                                        } catch (Exception $e) {
                                            // Silently fail
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Phone -->
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">
                                    Phone Number
                                </label>
                                <div class="input-group">
                                    <span class="material-symbols-outlined input-icon">phone</span>
                                    <input type="tel" id="phone" name="phone"
                                        value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                                        class="input-with-icon block w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors"
                                        placeholder="+63 912 345 6789">
                                </div>
                            </div>
                        </div>

                        <!-- Password Row -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <!-- Password -->
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                                    Password <span class="text-red-500">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="material-symbols-outlined input-icon">lock</span>
                                    <input type="password" id="password" name="password" required minlength="8"
                                        class="input-with-icon block w-full px-4 py-3 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors"
                                        placeholder="Enter password">
                                    <span class="material-symbols-outlined password-toggle" onclick="togglePassword('password')">visibility</span>
                                    <small class="text-xs text-gray-500 mt-1 block">Minimum 8 characters</small>
                                </div>
                            </div>

                            <!-- Confirm Password -->
                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">
                                    Confirm Password <span class="text-red-500">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="material-symbols-outlined input-icon">lock_reset</span>
                                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                                        class="input-with-icon block w-full px-4 py-3 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors"
                                        placeholder="Confirm password">
                                    <span class="material-symbols-outlined password-toggle" onclick="togglePassword('confirm_password')">visibility</span>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-4">
                            <button type="submit"
                                class="w-full flex justify-center items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-white hero-bg hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-all">
                                <span class="material-symbols-outlined mr-2">person_add</span>
                                Create Account
                            </button>
                        </div>

                        <!-- Sign In Link -->
                        <div class="text-center pt-4 border-t border-gray-200">
                            <p class="text-sm text-gray-600">
                                Already have an account?
                                <a href="index.php" class="font-medium text-purple-600 hover:text-purple-500 transition-colors">
                                    Sign in here
                                </a>
                            </p>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-8 text-center">
                <p class="text-sm text-gray-600">
                    By creating an account, you agree to LILAC's Terms of Service and Privacy Policy
                </p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const toggle = field.parentElement.querySelector('.password-toggle');

            if (field.type === 'password') {
                field.type = 'text';
                toggle.textContent = 'visibility_off';
            } else {
                field.type = 'password';
                toggle.textContent = 'visibility';
            }
        }

        // Password strength indicator
        document.getElementById('password')?.addEventListener('input', function(e) {
            const password = e.target.value;
            const strength = document.getElementById('password-strength');

            if (!strength) return;

            let score = 0;
            if (password.length >= 8) score++;
            if (password.match(/[a-z]/)) score++;
            if (password.match(/[A-Z]/)) score++;
            if (password.match(/[0-9]/)) score++;
            if (password.match(/[^a-zA-Z0-9]/)) score++;

            const strengthText = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
            const strengthColor = ['text-red-600', 'text-orange-600', 'text-yellow-600', 'text-blue-600', 'text-green-600'];

            strength.textContent = strengthText[score - 1] || '';
            strength.className = 'text-xs mt-1 block ' + (strengthColor[score - 1] || '');
        });

        // Confirm password validation
        document.getElementById('confirm_password')?.addEventListener('input', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = e.target.value;

            if (confirmPassword && password !== confirmPassword) {
                e.target.setCustomValidity('Passwords do not match');
            } else {
                e.target.setCustomValidity('');
            }
        });
    </script>
</body>

</html>
