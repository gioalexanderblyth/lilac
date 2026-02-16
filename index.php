<?php
/**
 * LILAC Landing Page with Login Functionality
 */
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember_me']);

    require_once __DIR__ . '/api/config.php';

    try {
        $pdo = getDatabaseConnection();

        if ($pdo instanceof FileBasedDatabase) {
            // File-based authentication for maintenance
            if (($username === 'admin' && $password === 'admin123') ||
                ($username === 'user' && $password === 'user123')) {

                $userId = $username === 'admin' ? 1 : 2;
                $role = $username === 'admin' ? 'admin' : 'user';

                $_SESSION['user_id'] = $userId;
                $_SESSION['username'] = $username;
                $_SESSION['user'] = [
                    'id' => $userId,
                    'username' => $username,
                    'email' => $username . '@cpu.edu.ph',
                    'role' => $role,
                    'full_name' => $username === 'admin' ? 'System Administrator' : 'Regular User'
                ];
                $_SESSION['token'] = bin2hex(random_bytes(32));

                logActivity('User logged in: ' . $username, 'INFO');
                header('Location: dashboard.php');
                exit();
            } else {
                $error = 'Invalid username or password. For maintenance: admin/admin123 or user/user123';
            }
        } else {
            // Database authentication
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND status = "active"');
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                // Create session token
                $token = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime($remember ? '+30 days' : '+24 hours'));
                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

                $stmt = $pdo->prepare('INSERT INTO sessions (user_id, session_token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$user['id'], $token, $ipAddress, substr($userAgent, 0, 255), $expiresAt]);

                // Update last login
                $stmt = $pdo->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?');
                $stmt->execute([$user['id']]);

                // Log activity
                $stmt = $pdo->prepare('INSERT INTO activity_log (user_id, action, ip_address) VALUES (?, "login", ?)');
                $stmt->execute([$user['id'], $ipAddress]);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'full_name' => $user['full_name']
                ];
                $_SESSION['token'] = $token;

                logActivity('User logged in: ' . $username, 'INFO');
                header('Location: dashboard.php');
                exit();
            } else {
                $error = 'Invalid username or password';
                if (function_exists('logActivity')) {
                    logActivity('Failed login attempt for username: ' . $username, 'WARNING');
                }
            }
        }
    } catch (Exception $e) {
        $error = 'Login error: ' . $e->getMessage();
        if (function_exists('logActivity')) {
            logActivity('Login exception: ' . $e->getMessage(), 'ERROR');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LILAC - Institutional Management Excellence</title>
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <style>
        * {
            scroll-behavior: smooth;
        }
        
        .hero-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
        }
        
        .hero-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }
        
        .image-hover {
            transition: all 0.3s ease;
        }
        .image-hover:hover {
            transform: scale(1.05);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
        }
        .fade-in {
            animation: fadeIn 0.8s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .stagger-1 { animation-delay: 0.1s; }
        .stagger-2 { animation-delay: 0.2s; }
        .stagger-3 { animation-delay: 0.3s; }
        .stagger-4 { animation-delay: 0.4s; }
        
        /* Responsive improvements */
        @media (max-width: 768px) {
            .hero-bg {
                padding-top: 6rem !important;
                padding-bottom: 3rem !important;
            }
            
            .hero-bg h1 {
                font-size: 2.5rem !important;
                line-height: 1.2 !important;
            }
            
            .hero-bg p {
                font-size: 1rem !important;
                padding: 0 1rem;
            }
        }
        
        /* Modern Carousel Styles */
        .carousel-slide {
            transition: all 0.7s ease-in-out;
        }
        
        .carousel-slide.active {
            opacity: 1;
            transform: scale(1);
        }
        
        .carousel-slide:not(.active) {
            opacity: 0.7;
            transform: scale(0.95);
        }
        
        /* Toggle Button Animations */
        .toggle-btn {
            transition: all 0.3s ease-in-out;
        }
        
        .toggle-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        
        /* Progress Bar Animation */
        .progress-bar {
            transition: width 0.1s ease-linear;
        }
        
        /* Modern Carousel Container */
        #modern-carousel {
            transition: transform 0.7s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Indicator Animations */
        .indicator {
            transition: all 0.3s ease-in-out;
        }
        
        .indicator:hover {
            transform: scale(1.2);
        }
        
        .indicator[data-active="true"] {
            transform: scale(1.3);
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
        }

        /* Smooth Gallery Transitions */
        #featured-image,
        #side-image-1,
        #side-image-2,
        #side-title-1,
        #side-title-2,
        #side-description-1,
        #side-description-2 {
            transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Gallery hover effects */
        .lg\:col-span-8:hover #featured-image,
        .lg\:col-span-4:hover img {
            transform: scale(1.05);
        }

        /* Hover preview modal */
        .hover-preview {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .hover-preview.active {
            opacity: 1;
            visibility: visible;
        }

        .hover-preview img {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
            border-radius: 12px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.6);
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }

        .hover-preview.active img {
            transform: scale(1);
        }
        
        /* Custom scrollbar for interval menu - Soft, Eye-Friendly Design */
        #interval-menu::-webkit-scrollbar {
            width: 6px;
        }
        
        #interval-menu::-webkit-scrollbar-track {
            background: rgba(243, 244, 246, 0.3);
            border-radius: 10px;
            margin: 4px 0;
        }
        
        #interval-menu::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        #interval-menu::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        #interval-menu::-webkit-scrollbar-thumb:active {
            background: #64748b;
        }
        
        .dark #interval-menu::-webkit-scrollbar-track {
            background: rgba(31, 41, 55, 0.6);
        }
        
        .dark #interval-menu::-webkit-scrollbar-thumb {
            background: #475569;
        }
        
        .dark #interval-menu::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }
        
        .dark #interval-menu::-webkit-scrollbar-thumb:active {
            background: #94a3b8;
        }
        
        /* Firefox scrollbar styling */
        #interval-menu {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 rgba(243, 244, 246, 0.3);
        }
        
        .dark #interval-menu {
            scrollbar-color: #475569 rgba(31, 41, 55, 0.6);
        }
    </style>
</head>

<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="fixed top-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-md shadow-lg border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-3 sm:py-4">
                <div class="flex items-center space-x-2 sm:space-x-4">
                    <img src="./api/get-logo.php" alt="CPU Logo" class="h-10 w-10 sm:h-12 sm:w-12 object-contain flex-shrink-0">
                    <div>
                        <h1 class="text-xl sm:text-2xl font-bold bg-gradient-to-r from-purple-600 to-blue-600 bg-clip-text text-transparent">LILAC</h1>
                        <p class="text-xs sm:text-sm text-gray-600 hidden sm:block">Central Philippine University</p>
                    </div>
                </div>
                
                <!-- Login Button -->
                <div class="flex items-center">
                    <button onclick="openLoginModal()" class="group relative overflow-hidden bg-gradient-to-r from-purple-600 to-blue-600 text-white px-4 py-2 sm:px-6 sm:py-3 rounded-lg sm:rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 active:scale-95 transition-all duration-300 font-semibold text-sm sm:text-base">
                        <div class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-300"></div>
                        <div class="relative flex items-center space-x-1 sm:space-x-2">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                            </svg>
                            <span class="hidden sm:inline">Login</span>
                            <span class="sm:hidden">Sign In</span>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-bg pt-20 sm:pt-24 pb-12 sm:pb-20 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center text-white">
                <h1 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-black mb-4 sm:mb-6 fade-in leading-tight px-2">
                    Welcome to <span class="bg-gradient-to-r from-yellow-300 to-pink-300 bg-clip-text text-transparent">LILAC</span>
                </h1>
                <p class="text-base sm:text-xl md:text-2xl mb-6 sm:mb-8 fade-in stagger-1 max-w-4xl mx-auto px-4 leading-relaxed">
                    Your comprehensive institutional management solution for documents, partnerships, and organizational excellence
                </p>
                <div class="flex flex-col sm:flex-row justify-center items-center gap-4 fade-in stagger-2 px-4">
                    <a href="#gallery" class="w-full sm:w-auto border-2 border-white text-white px-6 sm:px-8 py-3 sm:py-4 rounded-xl font-bold text-base sm:text-lg hover:bg-white hover:text-purple-600 transition-all duration-300 transform hover:scale-105 active:scale-95 text-center">
                        View Gallery
                    </a>
                    <button onclick="openLoginModal()" class="w-full sm:w-auto bg-white text-purple-600 px-6 sm:px-8 py-3 sm:py-4 rounded-xl font-bold text-base sm:text-lg hover:bg-gray-100 transition-all duration-300 transform hover:scale-105 active:scale-95 shadow-lg">
                        Get Started
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-12 sm:py-16 lg:py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-10 sm:mb-16">
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-gray-900 mb-3 sm:mb-4 px-4">Institutional Management Excellence</h2>
                <p class="text-lg sm:text-xl text-gray-600 max-w-3xl mx-auto px-4">
                    Streamline your institutional operations with our comprehensive management system
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                <!-- Document Management -->
                <div class="group relative overflow-hidden bg-gradient-to-br from-blue-50 to-indigo-100 rounded-2xl sm:rounded-3xl p-6 sm:p-8 hover:shadow-2xl transition-all duration-500 border border-blue-100">
                    <div class="absolute -top-4 -right-4 w-24 h-24 sm:w-32 sm:h-32 bg-blue-200 opacity-20 rounded-full"></div>
                    <div class="relative">
                        <div class="w-14 h-14 sm:w-16 sm:h-16 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl sm:rounded-2xl flex items-center justify-center mb-4 sm:mb-6 group-hover:scale-110 transition-transform">
                            <svg class="w-7 h-7 sm:w-8 sm:h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-3 sm:mb-4">Document Management</h3>
                        <p class="text-sm sm:text-base text-gray-600 leading-relaxed">Organize, track, and manage all institutional documents with intelligent categorization and search capabilities.</p>
                    </div>
                </div>

                <!-- Partnership Management -->
                <div class="group relative overflow-hidden bg-gradient-to-br from-green-50 to-emerald-100 rounded-2xl sm:rounded-3xl p-6 sm:p-8 hover:shadow-2xl transition-all duration-500 border border-green-100">
                    <div class="absolute -top-4 -right-4 w-24 h-24 sm:w-32 sm:h-32 bg-green-200 opacity-20 rounded-full"></div>
                    <div class="relative">
                        <div class="w-14 h-14 sm:w-16 sm:h-16 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl sm:rounded-2xl flex items-center justify-center mb-4 sm:mb-6 group-hover:scale-110 transition-transform">
                            <svg class="w-7 h-7 sm:w-8 sm:h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-3 sm:mb-4">Partnership Management</h3>
                        <p class="text-sm sm:text-base text-gray-600 leading-relaxed">Track MOUs, MOAs, and institutional partnerships with automated reminders and comprehensive reporting.</p>
                    </div>
                </div>

                <!-- Event Management -->
                <div class="group relative overflow-hidden bg-gradient-to-br from-purple-50 to-pink-100 rounded-2xl sm:rounded-3xl p-6 sm:p-8 hover:shadow-2xl transition-all duration-500 border border-purple-100 md:col-span-2 lg:col-span-1">
                    <div class="absolute -top-4 -right-4 w-24 h-24 sm:w-32 sm:h-32 bg-purple-200 opacity-20 rounded-full"></div>
                    <div class="relative">
                        <div class="w-14 h-14 sm:w-16 sm:h-16 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl sm:rounded-2xl flex items-center justify-center mb-4 sm:mb-6 group-hover:scale-110 transition-transform">
                            <svg class="w-7 h-7 sm:w-8 sm:h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-3 sm:mb-4">Event Management</h3>
                        <p class="text-sm sm:text-base text-gray-600 leading-relaxed">Plan, organize, and track institutional events and activities with integrated calendar and notification systems.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Modern Gallery Section -->
    <section id="gallery" class="py-12 sm:py-16 lg:py-24 bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 relative overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute inset-0" style="background-image: radial-gradient(circle at 25% 25%, rgba(255,255,255,0.1) 0%, transparent 50%), radial-gradient(circle at 75% 75%, rgba(255,255,255,0.1) 0%, transparent 50%);"></div>
        </div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center mb-10 sm:mb-16 lg:mb-20">
                <h2 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-black text-white mb-4 sm:mb-6 leading-tight px-4">
                    Campus Life in
                    <span class="bg-gradient-to-r from-purple-400 via-pink-400 to-blue-400 bg-clip-text text-transparent">Motion</span>
                </h2>
                <p class="text-base sm:text-lg lg:text-xl text-gray-300 max-w-3xl mx-auto leading-relaxed px-4">
                    Experience the vibrant energy and academic excellence of Central Philippine University through our curated visual stories
                </p>
            </div>
            
            <!-- Modern Gallery - Single Image -->
            <div class="max-w-5xl mx-auto mb-10 sm:mb-16">
                <div class="group" onclick="showImagePreview(this)">
                    <div class="relative h-[400px] sm:h-[500px] lg:h-[600px] rounded-2xl sm:rounded-3xl overflow-hidden shadow-2xl transform transition-all duration-700 hover:scale-[1.02] active:scale-[0.98] cursor-pointer touch-manipulation">
                        <img id="featured-image" src="assets/Events & Activities/1.jpg" alt="Campus Life" class="w-full h-full object-cover transition-all duration-1000 ease-in-out group-hover:scale-110">
                    </div>
                </div>
            </div>
            
            <!-- Gallery Navigation -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-8 sm:mb-12 px-4">
                <div class="flex items-center space-x-3 sm:space-x-4">
                    <div class="flex space-x-2">
                        <button class="w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full bg-purple-400 transition-all duration-300 hover:bg-purple-300 active:scale-90 touch-manipulation" onclick="goToSlide(0)" aria-label="Go to slide 1"></button>
                        <button class="w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full bg-white/30 hover:bg-white/50 transition-all duration-300 active:scale-90 touch-manipulation" onclick="goToSlide(1)" aria-label="Go to slide 2"></button>
                        <button class="w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full bg-white/30 hover:bg-white/50 transition-all duration-300 active:scale-90 touch-manipulation" onclick="goToSlide(2)" aria-label="Go to slide 3"></button>
                        <button class="w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full bg-white/30 hover:bg-white/50 transition-all duration-300 active:scale-90 touch-manipulation" onclick="goToSlide(3)" aria-label="Go to slide 4"></button>
                        <button class="w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full bg-white/30 hover:bg-white/50 transition-all duration-300 active:scale-90 touch-manipulation" onclick="goToSlide(4)" aria-label="Go to slide 5"></button>
                        <button class="w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full bg-white/30 hover:bg-white/50 transition-all duration-300 active:scale-90 touch-manipulation" onclick="goToSlide(5)" aria-label="Go to slide 6"></button>
                    </div>
                    <span id="gallery-counter" class="text-gray-400 text-xs sm:text-sm font-medium">1 of 6</span>
                </div>
                
                <div class="flex items-center space-x-2 sm:space-x-3">
                    <!-- Interval Selector -->
                    <div class="relative">
                        <button id="interval-btn" class="group flex items-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2 sm:py-2.5 rounded-full bg-white/10 backdrop-blur-sm border border-white/20 hover:bg-white/20 active:scale-90 transition-all duration-300 touch-manipulation" onclick="toggleIntervalMenu()" aria-label="Change interval">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span id="interval-display" class="text-white text-xs sm:text-sm font-medium hidden sm:inline">10s</span>
                        </button>
                        <!-- Interval Dropdown -->
                        <div id="interval-menu" class="hidden absolute right-0 top-full mt-2 w-36 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 z-50 overflow-y-scroll max-h-48 custom-scrollbar">
                            <button class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors flex items-center justify-between interval-option" data-interval="0" onclick="setGalleryInterval(0)">
                                <span>Off</span>
                                <span class="interval-check hidden">✓</span>
                            </button>
                            <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
                            <button class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors flex items-center justify-between interval-option" data-interval="3000" onclick="setGalleryInterval(3)">
                                <span>3 seconds</span>
                                <span class="interval-check hidden">✓</span>
                            </button>
                            <button class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors flex items-center justify-between interval-option" data-interval="5000" onclick="setGalleryInterval(5)">
                                <span>5 seconds</span>
                                <span class="interval-check hidden">✓</span>
                            </button>
                            <button class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors flex items-center justify-between interval-option active" data-interval="10000" onclick="setGalleryInterval(10)">
                                <span>10 seconds</span>
                                <span class="interval-check">✓</span>
                            </button>
                            <button class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors flex items-center justify-between interval-option" data-interval="15000" onclick="setGalleryInterval(15)">
                                <span>15 seconds</span>
                                <span class="interval-check hidden">✓</span>
                            </button>
                            <button class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors flex items-center justify-between interval-option" data-interval="30000" onclick="setGalleryInterval(30)">
                                <span>30 seconds</span>
                                <span class="interval-check hidden">✓</span>
                            </button>
                        </div>
                    </div>
                    
                    <button class="group p-2.5 sm:p-3 rounded-full bg-white/10 backdrop-blur-sm border border-white/20 hover:bg-white/20 active:scale-90 transition-all duration-300 touch-manipulation" onclick="previousSlide()" aria-label="Previous slide">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </button>
                    <button class="group p-2.5 sm:p-3 rounded-full bg-white/10 backdrop-blur-sm border border-white/20 hover:bg-white/20 active:scale-90 transition-all duration-300 touch-manipulation" onclick="nextSlide()" aria-label="Next slide">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Call to Action -->
            <div class="text-center space-y-4 px-4">
                <button onclick="showAllImages()" class="group relative inline-flex items-center px-6 sm:px-8 py-3 sm:py-4 bg-gradient-to-r from-purple-600 to-blue-600 text-white rounded-xl sm:rounded-2xl font-bold text-base sm:text-lg overflow-hidden transition-all duration-300 transform hover:scale-105 active:scale-95 shadow-2xl hover:shadow-purple-500/25 w-full sm:w-auto touch-manipulation">
                    <div class="absolute inset-0 bg-gradient-to-r from-purple-600 to-blue-600 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    <div class="relative flex items-center space-x-2 sm:space-x-3">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 group-hover:rotate-12 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span>Explore Gallery</span>
                        <div class="bg-white/20 px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-medium backdrop-blur-sm">
                            6 Photos
                        </div>
                    </div>
                </button>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="py-12 sm:py-16 lg:py-20 bg-gradient-to-r from-purple-600 to-blue-600">
        <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-white mb-4 sm:mb-6">Ready to Transform Your Institution?</h2>
            <p class="text-base sm:text-lg lg:text-xl text-purple-100 mb-6 sm:mb-8 max-w-2xl mx-auto leading-relaxed">
                Join Central Philippine University in embracing digital excellence with LILAC - your comprehensive institutional management solution.
            </p>
            <button onclick="openLoginModal()" class="group inline-flex items-center bg-white text-purple-600 px-6 sm:px-8 py-3 sm:py-4 rounded-xl font-bold text-base sm:text-lg hover:bg-gray-100 active:scale-95 transition-all duration-300 transform hover:scale-105 shadow-xl w-full sm:w-auto justify-center touch-manipulation">
                Access LILAC System
                <svg class="w-4 h-4 sm:w-5 sm:h-5 ml-2 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                </svg>
            </button>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-8 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                <div>
                    <div class="flex items-center space-x-3 mb-4">
                        <img src="./api/get-logo.php" alt="CPU Logo" class="h-10 w-10 object-contain flex-shrink-0">
                        <div>
                            <h3 class="text-xl sm:text-2xl font-bold">LILAC</h3>
                            <p class="text-gray-400 text-xs sm:text-sm">Central Philippine University</p>
                        </div>
                    </div>
                    <p class="text-sm sm:text-base text-gray-400 leading-relaxed">
                        Empowering institutional excellence through innovative management solutions.
                    </p>
                </div>
                
                <div>
                    <h4 class="text-base sm:text-lg font-semibold mb-3 sm:mb-4">Features</h4>
                    <ul class="space-y-2 text-sm sm:text-base text-gray-400">
                        <li>Document Management</li>
                        <li>Partnership Tracking</li>
                        <li>Event Management</li>
                        <li>Award Recognition</li>
                        <li>Budget Management</li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="text-base sm:text-lg font-semibold mb-3 sm:mb-4">Contact</h4>
                    <div class="space-y-2 text-sm sm:text-base text-gray-400">
                        <p>Central Philippine University</p>
                        <p>Jaro, Iloilo City, Philippines</p>
                        <p>Email: info@cpu.edu.ph</p>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-800 pt-6 sm:pt-8 mt-6 sm:mt-8 text-center text-xs sm:text-sm text-gray-400">
                <p>&copy; 2025 Central Philippine University | LILAC System - All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Image Preview Modal -->
    <div id="hover-preview" class="hover-preview">
        <img id="hover-preview-image" src="" alt="Preview">
    </div>

    <!-- JavaScript for smooth scrolling, animations, and carousel -->
    <script>
        // Modern Gallery functionality
        let currentSlideIndex = 0;
        const totalSlides = 6; // Total number of images
        let autoplayInterval;
        let autoplayDelay = 10000; // Default 10 seconds (can be changed by user)
        let isAutoplayEnabled = true;

        // Gallery images data
        const galleryImages = [
            { src: 'assets/Events & Activities/1.jpg', title: 'Campus Life', description: 'Vibrant student community and academic excellence in action' },
            { src: 'assets/Events & Activities/2.jpg', title: 'University Activities', description: 'Engaging events and memorable experiences' },
            { src: 'assets/Events & Activities/3.jpg', title: 'Academic Excellence', description: 'Leading education and research innovation' },
            { src: 'assets/Events & Activities/4.jpg', title: 'Student Life', description: 'Building friendships and creating memories' },
            { src: 'assets/Events & Activities/5.png', title: 'Campus Events', description: 'Celebrating achievements and milestones' },
            { src: 'assets/Events & Activities/6.jpg', title: 'University Programs', description: 'Innovative academic programs and research' }
        ];

        // Initialize Modern Gallery
        document.addEventListener('DOMContentLoaded', function() {
            initializeModernGallery();
            updateGalleryIndicators();
            startAutoplay(); // Start autoplay automatically
        });

        function initializeModernGallery() {
            console.log('Initializing Modern Gallery...');
            updateGalleryDisplay();
        }

        function updateGalleryDisplay() {
            // Get elements
            const featuredImage = document.getElementById('featured-image');
            const featuredTitle = document.getElementById('featured-title');
            const featuredDescription = document.getElementById('featured-description');

            // Fade out current content
            if (featuredImage) {
                featuredImage.style.opacity = '0.3';
                featuredImage.style.transform = 'scale(0.95)';
            }
            if (featuredTitle) featuredTitle.style.opacity = '0.5';
            if (featuredDescription) featuredDescription.style.opacity = '0.5';

            // Update content after fade out
            setTimeout(() => {
                // Update featured image and text
                if (featuredImage && galleryImages[currentSlideIndex]) {
                    featuredImage.src = galleryImages[currentSlideIndex].src;
                    featuredImage.alt = galleryImages[currentSlideIndex].title;
                }
                if (featuredTitle && galleryImages[currentSlideIndex]) {
                    featuredTitle.textContent = galleryImages[currentSlideIndex].title;
                }
                if (featuredDescription && galleryImages[currentSlideIndex]) {
                    featuredDescription.textContent = galleryImages[currentSlideIndex].description;
                }

                // Fade in new content
                setTimeout(() => {
                    if (featuredImage) {
                        featuredImage.style.opacity = '1';
                        featuredImage.style.transform = 'scale(1)';
                    }
                    if (featuredTitle) featuredTitle.style.opacity = '1';
                    if (featuredDescription) featuredDescription.style.opacity = '1';
                }, 100);

            }, 300);

            updateGalleryIndicators();
            updateSlideCounter();
        }

        function nextSlide() {
            currentSlideIndex = (currentSlideIndex + 1) % totalSlides;
            updateGalleryDisplay();
            if (isAutoplayEnabled) {
                resetAutoplay(); // Reset autoplay timer when manually navigating
            }
        }

        function previousSlide() {
            currentSlideIndex = (currentSlideIndex - 1 + totalSlides) % totalSlides;
            updateGalleryDisplay();
            if (isAutoplayEnabled) {
                resetAutoplay(); // Reset autoplay timer when manually navigating
            }
        }

        function goToSlide(slideIndex) {
            currentSlideIndex = slideIndex;
            updateGalleryDisplay();
            if (isAutoplayEnabled) {
                resetAutoplay(); // Reset autoplay timer when manually navigating
            }
        }

        function updateGalleryIndicators() {
            // More reliable selector for indicators
            const indicatorContainer = document.querySelector('.flex.space-x-2');
            if (!indicatorContainer) return;
            
            const indicators = indicatorContainer.querySelectorAll('button.rounded-full');
            indicators.forEach((indicator, index) => {
                if (indicator) {
                    if (index === currentSlideIndex) {
                        indicator.classList.remove('bg-white/30');
                        indicator.classList.add('bg-purple-400');
                    } else {
                        indicator.classList.remove('bg-purple-400');
                        indicator.classList.add('bg-white/30');
                    }
                }
            });
        }

        function updateSlideCounter() {
            // Use the ID selector for reliable access
            const counter = document.getElementById('gallery-counter');
            if (counter) {
                counter.textContent = `${currentSlideIndex + 1} of ${totalSlides}`;
            }
        }

        function startAutoplay() {
            if (isAutoplayEnabled) {
                clearInterval(autoplayInterval); // Clear any existing interval
                autoplayInterval = setInterval(() => {
                    nextSlide();
                }, autoplayDelay);
            }
        }

        function resetAutoplay() {
            clearInterval(autoplayInterval);
            startAutoplay();
        }

        // Interval selector functions
        function toggleIntervalMenu() {
            const menu = document.getElementById('interval-menu');
            if (menu) {
                menu.classList.toggle('hidden');
            }
        }

        function setGalleryInterval(seconds) {
            if (seconds === 0) {
                // Turn off autoplay
                isAutoplayEnabled = false;
                clearInterval(autoplayInterval);
                const display = document.getElementById('interval-display');
                if (display) {
                    display.textContent = 'Off';
                }
            } else {
                // Turn on autoplay with specified interval
                isAutoplayEnabled = true;
                autoplayDelay = seconds * 1000; // Convert to milliseconds
                const display = document.getElementById('interval-display');
                if (display) {
                    display.textContent = seconds + 's';
                }
                // Start autoplay with new interval
                startAutoplay();
            }
            
            // Update active state in menu
            const options = document.querySelectorAll('.interval-option');
            options.forEach(option => {
                const check = option.querySelector('.interval-check');
                const optionInterval = parseInt(option.getAttribute('data-interval'));
                const expectedInterval = seconds === 0 ? 0 : seconds * 1000;
                
                if (optionInterval === expectedInterval) {
                    option.classList.add('active');
                    if (check) check.classList.remove('hidden');
                } else {
                    option.classList.remove('active');
                    if (check) check.classList.add('hidden');
                }
            });
            
            // Close menu
            const menu = document.getElementById('interval-menu');
            if (menu) {
                menu.classList.add('hidden');
            }
        }

        // Close interval menu when clicking outside
        document.addEventListener('click', function(event) {
            const intervalBtn = document.getElementById('interval-btn');
            const intervalMenu = document.getElementById('interval-menu');
            
            if (intervalBtn && intervalMenu && 
                !intervalBtn.contains(event.target) && 
                !intervalMenu.contains(event.target)) {
                intervalMenu.classList.add('hidden');
            }
        });

        // Test hover preview function
        function testHoverPreview() {
            const hoverPreview = document.getElementById('hover-preview');
            const hoverPreviewImage = document.getElementById('hover-preview-image');
            
            if (hoverPreview && hoverPreviewImage) {
                hoverPreviewImage.src = 'assets/Events & Activities/1.jpg';
                hoverPreviewImage.alt = 'Test Image';
                hoverPreview.classList.add('active');
                
                setTimeout(() => {
                    hoverPreview.classList.remove('active');
                }, 3000);
                
                console.log('Test hover preview activated');
            } else {
                console.error('Hover preview elements not found for test');
            }
        }

        // Click to show image preview
        function showImagePreview(element) {
            const img = element.querySelector('img');
            const hoverPreview = document.getElementById('hover-preview');
            const hoverPreviewImage = document.getElementById('hover-preview-image');
            
            if (img && hoverPreview && hoverPreviewImage) {
                console.log('Showing image preview for:', img.src);
                hoverPreviewImage.src = img.src;
                hoverPreviewImage.alt = img.alt;
                hoverPreview.classList.add('active');
            }
        }

        // Close modal when clicking on it
        document.addEventListener('DOMContentLoaded', function() {
            const hoverPreview = document.getElementById('hover-preview');
            if (hoverPreview) {
                hoverPreview.addEventListener('click', function() {
                    this.classList.remove('active');
                });
            }
        });




        function showAllImages() {
            // All images from the assets/Events & Activities folder
            const allImages = [
                { src: 'assets/Events & Activities/1.jpg', title: 'Campus Life' },
                { src: 'assets/Events & Activities/2.jpg', title: 'University Activities' },
                { src: 'assets/Events & Activities/3.jpg', title: 'Academic Excellence' },
                { src: 'assets/Events & Activities/4.jpg', title: 'Student Life' },
                { src: 'assets/Events & Activities/5.png', title: 'Campus Events' },
                { src: 'assets/Events & Activities/6.jpg', title: 'University Programs' }
            ];
            
            // Create modal HTML
            const modalHTML = `
                <div id="gallery-modal" class="fixed inset-0 z-[100] bg-black bg-opacity-90 backdrop-blur-sm flex items-center justify-center p-4" onclick="closeGalleryModal(event)">
                    <div class="bg-white rounded-3xl max-w-6xl w-full max-h-[90vh] overflow-hidden shadow-2xl" onclick="event.stopPropagation()">
                        <!-- Modal Header -->
                        <div class="bg-gradient-to-r from-purple-600 to-blue-600 text-white p-6 flex justify-between items-center">
                            <div>
                                <h3 class="text-2xl font-bold">Gallery</h3>
                                <p class="text-purple-100">Central Philippine University - ${allImages.length} Photos</p>
                            </div>
                            <button onclick="closeGalleryModal()" class="text-white hover:bg-white/20 p-2 rounded-full transition-all duration-300">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <!-- Modal Content -->
                        <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                                ${allImages.map((image, index) => `
                                    <div class="group cursor-pointer" onclick="openLightbox(${index})">
                                        <div class="relative overflow-hidden rounded-xl shadow-lg group-hover:shadow-2xl transition-all duration-300">
                                            <img src="${image.src}" alt="${image.title}" class="w-full h-48 object-cover group-hover:scale-110 transition-transform duration-500">
                                            <!-- Zoom icon -->
                                            <div class="absolute top-3 right-3 bg-white/20 backdrop-blur-md p-2 rounded-full opacity-0 group-hover:opacity-100 transition-all duration-300 transform scale-75 group-hover:scale-100">
                                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"></path>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            
            // Prevent body scroll
            document.body.style.overflow = 'hidden';
            
            // Add animation
            const modal = document.getElementById('gallery-modal');
            modal.style.opacity = '0';
            setTimeout(() => {
                modal.style.opacity = '1';
                modal.style.transition = 'opacity 0.3s ease-in-out';
            }, 10);
        }

        function closeGalleryModal(event) {
            // Only close if clicking the backdrop or close button
            if (event && event.target !== event.currentTarget && !event.target.closest('button')) return;
            
            const modal = document.getElementById('gallery-modal');
            if (modal) {
                modal.style.opacity = '0';
                setTimeout(() => {
                    modal.remove();
                    document.body.style.overflow = '';
                }, 300);
            }
        }

        function openLightbox(imageIndex) {
            const allImages = [
                { src: 'assets/Events & Activities/1.jpg', title: 'Campus Life' },
                { src: 'assets/Events & Activities/2.jpg', title: 'University Activities' },
                { src: 'assets/Events & Activities/3.jpg', title: 'Academic Excellence' },
                { src: 'assets/Events & Activities/4.jpg', title: 'Student Life' },
                { src: 'assets/Events & Activities/5.png', title: 'Campus Events' },
                { src: 'assets/Events & Activities/6.jpg', title: 'University Programs' }
            ];
            
            const image = allImages[imageIndex];
            
            const lightboxHTML = `
                <div id="lightbox-modal" class="fixed inset-0 z-[110] bg-black bg-opacity-95 backdrop-blur-sm flex items-center justify-center p-4" onclick="closeLightbox(event)">
                    <div class="relative max-w-5xl w-full h-full flex items-center justify-center" onclick="event.stopPropagation()">
                        <!-- Close button -->
                        <button onclick="closeLightbox()" class="absolute top-4 right-4 text-white hover:bg-white/20 p-2 rounded-full transition-all duration-300 z-10">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                        
                        <!-- Navigation arrows -->
                        <button onclick="previousLightboxImage(${imageIndex})" class="absolute left-4 top-1/2 transform -translate-y-1/2 text-white hover:bg-white/20 p-3 rounded-full transition-all duration-300 z-10">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                        </button>
                        
                        <button onclick="nextLightboxImage(${imageIndex})" class="absolute right-4 top-1/2 transform -translate-y-1/2 text-white hover:bg-white/20 p-3 rounded-full transition-all duration-300 z-10">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>
                        
                        <!-- Image -->
                        <div class="flex items-center justify-center w-full h-full">
                            <img src="${image.src}" alt="${image.title}" class="max-w-full max-h-[85vh] w-auto h-auto object-contain rounded-2xl shadow-2xl mx-auto transition-opacity duration-300">
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', lightboxHTML);
            
            const lightbox = document.getElementById('lightbox-modal');
            if (lightbox) {
                lightbox.style.opacity = '0';
                setTimeout(() => {
                    lightbox.style.opacity = '1';
                    lightbox.style.transition = 'opacity 0.3s ease-in-out';
                }, 10);
            }
        }

        function closeLightbox(event) {
            // If event is provided, only close if clicking the backdrop (not content or buttons)
            if (event) {
                if (event.target && event.target !== event.currentTarget) {
                    // Check if clicking inside the content area
                    const content = event.target.closest('.relative.max-w-5xl');
                    if (content) {
                        return; // Don't close when clicking content area
                    }
                    // Check if clicking a button (except close button)
                    const button = event.target.closest('button');
                    if (button && button.onclick) {
                        const onclickStr = button.onclick.toString();
                        if (!onclickStr.includes('closeLightbox()')) {
                            return; // Don't close for navigation buttons
                        }
                    }
                }
            }
            
            const lightbox = document.getElementById('lightbox-modal');
            if (lightbox) {
                lightbox.style.opacity = '0';
                setTimeout(() => {
                    if (lightbox && lightbox.parentNode) {
                        lightbox.remove();
                    }
                }, 300);
            }
        }

        function nextLightboxImage(currentIndex) {
            const allImages = [
                { src: 'assets/Events & Activities/1.jpg', title: 'Campus Life' },
                { src: 'assets/Events & Activities/2.jpg', title: 'University Activities' },
                { src: 'assets/Events & Activities/3.jpg', title: 'Academic Excellence' },
                { src: 'assets/Events & Activities/4.jpg', title: 'Student Life' },
                { src: 'assets/Events & Activities/5.png', title: 'Campus Events' },
                { src: 'assets/Events & Activities/6.jpg', title: 'University Programs' }
            ];
            const nextIndex = (currentIndex + 1) % 6;
            const lightbox = document.getElementById('lightbox-modal');
            if (lightbox) {
                // Ensure modal stays visible (opacity 1) during navigation
                lightbox.style.opacity = '1';
                
                // Update image source smoothly without closing modal
                const img = lightbox.querySelector('img');
                if (img) {
                    // Quick fade out
                    img.style.opacity = '0';
                    img.style.transition = 'opacity 0.2s ease-in-out';
                    
                    setTimeout(() => {
                        // Update image
                        img.src = allImages[nextIndex].src;
                        img.alt = allImages[nextIndex].title;
                        
                        // Wait for image to load, then fade in
                        img.onload = function() {
                            img.style.opacity = '1';
                        };
                        // If image is cached, fade in immediately
                        if (img.complete) {
                            img.style.opacity = '1';
                        }
                        
                        // Update navigation buttons with new index
                        const prevBtn = lightbox.querySelector('button[onclick*="previousLightboxImage"]');
                        const nextBtn = lightbox.querySelector('button[onclick*="nextLightboxImage"]');
                        if (prevBtn) {
                            prevBtn.setAttribute('onclick', `previousLightboxImage(${nextIndex})`);
                        }
                        if (nextBtn) {
                            nextBtn.setAttribute('onclick', `nextLightboxImage(${nextIndex})`);
                        }
                    }, 200);
                }
            } else {
                openLightbox(nextIndex);
            }
        }

        function previousLightboxImage(currentIndex) {
            const allImages = [
                { src: 'assets/Events & Activities/1.jpg', title: 'Campus Life' },
                { src: 'assets/Events & Activities/2.jpg', title: 'University Activities' },
                { src: 'assets/Events & Activities/3.jpg', title: 'Academic Excellence' },
                { src: 'assets/Events & Activities/4.jpg', title: 'Student Life' },
                { src: 'assets/Events & Activities/5.png', title: 'Campus Events' },
                { src: 'assets/Events & Activities/6.jpg', title: 'University Programs' }
            ];
            const prevIndex = (currentIndex - 1 + 6) % 6;
            const lightbox = document.getElementById('lightbox-modal');
            if (lightbox) {
                // Ensure modal stays visible (opacity 1) during navigation
                lightbox.style.opacity = '1';
                
                // Update image source smoothly without closing modal
                const img = lightbox.querySelector('img');
                if (img) {
                    // Quick fade out
                    img.style.opacity = '0';
                    img.style.transition = 'opacity 0.2s ease-in-out';
                    
                    setTimeout(() => {
                        // Update image
                        img.src = allImages[prevIndex].src;
                        img.alt = allImages[prevIndex].title;
                        
                        // Wait for image to load, then fade in
                        img.onload = function() {
                            img.style.opacity = '1';
                        };
                        // If image is cached, fade in immediately
                        if (img.complete) {
                            img.style.opacity = '1';
                        }
                        
                        // Update navigation buttons with new index
                        const prevBtn = lightbox.querySelector('button[onclick*="previousLightboxImage"]');
                        const nextBtn = lightbox.querySelector('button[onclick*="nextLightboxImage"]');
                        if (prevBtn) {
                            prevBtn.setAttribute('onclick', `previousLightboxImage(${prevIndex})`);
                        }
                        if (nextBtn) {
                            nextBtn.setAttribute('onclick', `nextLightboxImage(${prevIndex})`);
                        }
                    }, 200);
                }
            } else {
                openLightbox(prevIndex);
            }
        }

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add scroll animation to images
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationDelay = Math.random() * 0.5 + 's';
                    entry.target.classList.add('fade-in');
                }
            });
        }, observerOptions);

        // Observe all images in the gallery
        document.querySelectorAll('#gallery img').forEach(img => {
            observer.observe(img);
        });

        // Add parallax effect to hero section
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const hero = document.querySelector('.hero-bg');
            if (hero) {
                hero.style.transform = `translateY(${scrolled * 0.5}px)`;
            }
        });

        // Login Modal Functions
        function openLoginModal() {
            const modal = document.getElementById('login-modal');
            if (modal) {
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                setTimeout(() => {
                    modal.style.opacity = '1';
                    const modalContent = modal.querySelector('.modal-content');
                    if (modalContent) {
                        modalContent.style.transform = 'scale(1)';
                    }
                }, 10);
                // Focus on username field
                setTimeout(() => {
                    const usernameField = document.getElementById('modal-username');
                    if (usernameField) {
                        usernameField.focus();
                    }
                }, 100);
            }
        }

        function closeLoginModal() {
            const modal = document.getElementById('login-modal');
            if (modal) {
                modal.style.opacity = '0';
                const modalContent = modal.querySelector('.modal-content');
                if (modalContent) {
                    modalContent.style.transform = 'scale(0.95)';
                }
                setTimeout(() => {
                    modal.classList.add('hidden');
                    document.body.style.overflow = '';
                    // Clear form
                    const loginForm = document.getElementById('modal-login-form');
                    if (loginForm) {
                        loginForm.reset();
                    }
                    const loginError = document.getElementById('modal-login-error');
                    if (loginError) {
                        loginError.classList.add('hidden');
                    }
                    const loginLoading = document.getElementById('modal-login-loading');
                    if (loginLoading) {
                        loginLoading.classList.add('hidden');
                    }
                }, 300);
            }
        }

        // Close modal when clicking outside
        function closeModalOnOutsideClick(event) {
            if (event.target.id === 'login-modal') {
                closeLoginModal();
            }
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLoginModal();
            }
        });

        // Login form submission - Now uses PHP backend
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.getElementById('modal-toggle-password');
            const passwordInput = document.getElementById('modal-password');
            const eyeIcon = document.getElementById('modal-eye-icon');

            // Auto-open modal if there's a PHP error
            <?php if ($error): ?>
            openLoginModal();
            <?php endif; ?>

            // Password toggle functionality
            if (togglePassword && passwordInput && eyeIcon) {
                let passwordVisible = false;
                togglePassword.addEventListener('click', function() {
                    passwordVisible = !passwordVisible;
                    passwordInput.type = passwordVisible ? 'text' : 'password';
                    eyeIcon.innerHTML = passwordVisible
                        ? `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.956 9.956 0 012.042-3.368m3.087-2.742A9.956 9.956 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.956 9.956 0 01-4.421 5.568M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18" />`
                        : `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />`;
                });
            }
        });
    </script>

    <!-- Login Modal -->
    <div id="login-modal" class="fixed inset-0 z-[120] bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center p-4 hidden transition-all duration-300 overflow-y-auto" onclick="closeModalOnOutsideClick(event)" style="opacity: 0;">
        <div class="modal-content w-full max-w-md bg-white rounded-xl sm:rounded-2xl shadow-2xl border border-gray-200 flex flex-col items-center p-6 sm:p-8 relative transform scale-95 transition-transform duration-300 my-4 sm:my-8">
            <!-- Close Button -->
            <button onclick="closeLoginModal()" class="absolute top-4 right-4 w-10 h-10 bg-gray-100 hover:bg-gray-200 rounded-full flex items-center justify-center transition-all duration-200 group" title="Close">
                <svg class="w-5 h-5 text-gray-600 group-hover:text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
            
            <div class="flex flex-col items-center mb-4 sm:mb-6">
                <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-white shadow-lg flex items-center justify-center mb-3 sm:mb-4 border-4 border-blue-200">
                    <img src="./api/get-logo.php" alt="CPU Logo" class="w-16 h-16 sm:w-20 sm:h-20 object-contain rounded-full">
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-blue-900 mb-1 tracking-tight">Welcome to LILAC</h1>
                <p class="text-xs sm:text-sm text-gray-500 mb-1 sm:mb-2">Central Philippine University</p>
                <p class="text-xs text-gray-400">Institutional Management System</p>
            </div>
            
            <form id="modal-login-form" method="POST" autocomplete="off" class="space-y-4 sm:space-y-6 w-full">
                <input type="hidden" name="action" value="login">
                <?php if ($error): ?>
                <div class="text-red-700 bg-red-100 border border-red-200 rounded-lg px-4 py-2 text-center text-sm font-medium">
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                <div>
                    <label for="modal-username" class="block text-gray-700 font-medium mb-1 text-sm sm:text-base">Username</label>
                    <input id="modal-username" name="username" type="text" required autofocus class="mt-1 w-full border border-gray-300 rounded-lg px-3 sm:px-4 py-2.5 sm:py-3 text-sm sm:text-base focus:outline-none focus:ring-2 focus:ring-blue-400 transition" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                <div>
                    <label for="modal-password" class="block text-gray-700 font-medium mb-1 text-sm sm:text-base">Password</label>
                    <div class="flex items-center">
                        <input id="modal-password" name="password" type="password" required class="flex-1 border border-gray-300 rounded-l-lg px-3 sm:px-4 py-2.5 sm:py-3 text-sm sm:text-base focus:outline-none focus:ring-2 focus:ring-blue-400 transition">
                        <button type="button" id="modal-toggle-password" class="bg-gray-200 px-3 py-2.5 sm:py-3 rounded-r-lg text-gray-700 flex items-center justify-center hover:bg-gray-300 active:bg-gray-400 transition touch-manipulation" aria-label="Show or hide password">
                            <svg id="modal-eye-icon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-0">
                    <div class="flex items-center">
                        <input type="checkbox" name="remember_me" id="remember" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <label for="remember" class="ml-2 text-gray-700 text-xs sm:text-sm">Remember me for 30 days</label>
                    </div>
                    <a href="reset-password.php" class="text-xs sm:text-sm text-blue-600 hover:text-blue-800 hover:underline transition-colors">Forgot Password?</a>
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white py-2.5 sm:py-3 rounded-lg font-bold text-base sm:text-lg shadow hover:bg-blue-700 active:bg-blue-800 transition touch-manipulation">Login</button>

                <!-- Sign Up Link -->
                <div class="text-center pt-4 border-t border-gray-200 mt-4">
                    <p class="text-sm text-gray-600">
                        Don't have an account?
                        <a href="signup.php" class="font-medium text-blue-600 hover:text-blue-500 transition-colors">
                            Sign up here
                        </a>
                    </p>
                </div>
            </form>
        </div>
    </div>
</body>

</html>
