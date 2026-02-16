<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
$user = $_SESSION['user'];
$token = $_SESSION['token'];
$userId = $_SESSION['user_id'];

require_once __DIR__ . '/api/config.php';

// Refresh user data from database to ensure profile picture is up to date
try {
    $pdo = getDatabaseConnection();
    if (!($pdo instanceof FileBasedDatabase)) {
        // Ensure profile_picture column exists
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
            if ($stmt->rowCount() === 0) {
                $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) NULL DEFAULT NULL AFTER full_name");
            }
        } catch (Exception $e) {
            // Column might already exist, continue
        }
        
        // Get fresh user data from database
        $stmt = $pdo->prepare('SELECT id, username, email, full_name, role, department, phone, profile_picture, created_at FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($dbUser) {
            // Update session with fresh data from database
            $_SESSION['user'] = array_merge($user, $dbUser);
            $user = $_SESSION['user'];
        }
    }
} catch (Exception $e) {
    // If database refresh fails, continue with session data
    error_log('Failed to refresh user data from database: ' . $e->getMessage());
}

// Get user role from database to ensure accuracy
$isAdmin = false;
try {
    $pdo = getDatabaseConnection();
    if (!($pdo instanceof FileBasedDatabase)) {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($dbUser) {
            $isAdmin = ($dbUser['role'] === 'admin');
            // Update session if role changed
            if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] !== $dbUser['role']) {
                $_SESSION['user']['role'] = $dbUser['role'];
            }
        }
    } else {
        // Fallback to session role for file-based system
        $isAdmin = isset($user['role']) && $user['role'] === 'admin';
    }
} catch (Exception $e) {
    // Fallback to session role on error
    $isAdmin = isset($user['role']) && $user['role'] === 'admin';
}

$mous = [];
try {
    $pdo = getDatabaseConnection();
    if ($pdo instanceof FileBasedDatabase) {
        $dataDir = __DIR__ . '/data/mou/';
        if (is_dir($dataDir)) {
            $files = glob($dataDir . '*.json');
            foreach ($files as $file) {
                $data = json_decode(file_get_contents($file), true);
                if ($data) $mous[] = $data;
            }
        }
    } else {
        // All authenticated users can see all entries (same as admin)
        $stmt = $pdo->query('SELECT m.*, u.username as created_by FROM mou_moa m LEFT JOIN users u ON m.user_id = u.id ORDER BY m.created_at DESC');
        $mous = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log('MOUs load error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>LILAC MOUs & MOAs</title>
<link crossorigin="" href="https://fonts.gstatic.com/" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
<link rel="stylesheet" href="assets/css/tailwind.css">
<script>
        // Apply theme immediately to prevent flash
        (function() {
            const savedTheme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const shouldBeDark = savedTheme === 'dark' || (!savedTheme && prefersDark);
            if (shouldBeDark) {
                document.documentElement.classList.add('dark');
            }
        })();
</script>
<script src="js/notifications.js"></script>
<script src="js/notification-sound.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Check for entry parameter immediately and store it for later use
    (function() {
        const urlParams = new URLSearchParams(window.location.search);
        let entryId = urlParams.get('entry');
        const renew = urlParams.get('renew');
        const notifId = urlParams.get('notif');

        // If navigation came from the notification "Renewed" flow, we may have stored intent in sessionStorage.
        try {
            const open = sessionStorage.getItem('mouRenewal:open');
            const storedEntryId = sessionStorage.getItem('mouRenewal:entryId');
            const storedNotifId = sessionStorage.getItem('mouRenewal:notificationId');
            if (!entryId && open === '1' && storedEntryId) {
                entryId = storedEntryId;
            }
            if (!notifId && storedNotifId) {
                window.pendingRenewNotificationId = storedNotifId;
            }
            if ((renew === '1' || open === '1') && (entryId || storedEntryId)) {
                window.pendingRenewModal = true;
            }
        } catch (_) {
            // ignore storage failures
        }

        if (notifId) {
            window.pendingRenewNotificationId = notifId;
        }

        if (entryId) {
            console.log('Entry parameter detected:', entryId);
            // Store entry ID for later use when page is fully loaded
            window.pendingEntryId = entryId;
            // Don't clear URL parameter yet - we'll do it after modal opens
        }
    })();
</script>
<!-- Add these libraries for file to image conversion -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<!-- Tailwind runtime config removed; using compiled CSS in assets/css/tailwind.css -->
<style>
    /* Notification bar styles */
    #mouNotificationBarsContainer {
        pointer-events: none;
    }
    #mouNotificationBarsContainer > * {
        pointer-events: auto;
    }
    
    /* Single-card swipe notification (one at a time) */
    .mou-notification-card {
        position: relative;
        box-shadow:
            0 10px 18px rgba(0, 0, 0, 0.18),
            0 4px 10px rgba(0, 0, 0, 0.12);
        will-change: transform, opacity;
        touch-action: pan-y; /* allow horizontal swipe without blocking vertical scroll */
        user-select: none;
        cursor: grab;
    }
    .mou-notification-card.mou-dragging {
        transition: none !important;
        cursor: grabbing;
    }
    .line-clamp-1 {
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    /* Smooth animations for notification bars */
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
</style>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .material-symbols-outlined.filled {
            font-variation-settings: 'FILL' 1;
        }
        /* Ensure the table never overflows horizontally (removes need for scrollbar) */
        .table-fixed-layout {
            table-layout: fixed;
            width: 100%;
        }
        .table-fixed-layout th,
        .table-fixed-layout td {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        /* Don't apply ellipsis to buttons */
        .table-fixed-layout td button {
            white-space: normal;
            overflow: visible;
        }
        
        /* Hide table scrollbar */
        .overflow-x-hidden {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .overflow-x-hidden::-webkit-scrollbar {
            display: none;
        }
        
        /* Custom scrollbar styling for main content */
        #main-content {
            scrollbar-width: thin;
            scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
        }
        
        #main-content::-webkit-scrollbar {
            width: 8px;
        }
        
        #main-content::-webkit-scrollbar-track {
            background: transparent;
        }
        
        #main-content::-webkit-scrollbar-thumb {
            background-color: rgba(156, 163, 175, 0.5);
            border-radius: 4px;
        }
        
        #main-content::-webkit-scrollbar-thumb:hover {
            background-color: rgba(156, 163, 175, 0.7);
        }
        
        /* Dark mode scrollbar */
        .dark #main-content {
            scrollbar-color: rgba(75, 85, 99, 0.5) transparent;
        }
        
        .dark #main-content::-webkit-scrollbar-thumb {
            background-color: rgba(75, 85, 99, 0.5);
        }
        
        .dark #main-content::-webkit-scrollbar-thumb:hover {
            background-color: rgba(75, 85, 99, 0.7);
        }
        
        /* Scrollbar for notification dropdown */
        #notificationList {
            scrollbar-width: thin;
            scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
        }
        
        #notificationList::-webkit-scrollbar {
            width: 6px;
        }
        
        #notificationList::-webkit-scrollbar-track {
            background: transparent;
        }
        
        #notificationList::-webkit-scrollbar-thumb {
            background-color: rgba(156, 163, 175, 0.5);
            border-radius: 3px;
        }
        
        #notificationList::-webkit-scrollbar-thumb:hover {
            background-color: rgba(156, 163, 175, 0.7);
        }
        
        .dark #notificationList {
            scrollbar-color: rgba(75, 85, 99, 0.5) transparent;
        }
        
        .dark #notificationList::-webkit-scrollbar-thumb {
            background-color: rgba(75, 85, 99, 0.5);
        }
        
        .dark #notificationList::-webkit-scrollbar-thumb:hover {
            background-color: rgba(75, 85, 99, 0.7);
        }
        
        /* Scrollbar for sidebar navigation */
        .sidebar nav {
            scrollbar-width: thin;
            scrollbar-color: rgba(156, 163, 175, 0.3) transparent;
        }
        
        .sidebar nav::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar nav::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .sidebar nav::-webkit-scrollbar-thumb {
            background-color: rgba(156, 163, 175, 0.3);
            border-radius: 3px;
        }
        
        .sidebar nav::-webkit-scrollbar-thumb:hover {
            background-color: rgba(156, 163, 175, 0.5);
        }
        
        .dark .sidebar nav {
            scrollbar-color: rgba(75, 85, 99, 0.3) transparent;
        }
        
        .dark .sidebar nav::-webkit-scrollbar-thumb {
            background-color: rgba(75, 85, 99, 0.3);
        }
        
        .dark .sidebar nav::-webkit-scrollbar-thumb:hover {
            background-color: rgba(75, 85, 99, 0.5);
        }
        
        /* Scrollbar for filter dropdown */
        #filterDropdown {
            scrollbar-width: thin;
            scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
        }
        
        #filterDropdown::-webkit-scrollbar {
            width: 6px;
        }
        
        #filterDropdown::-webkit-scrollbar-track {
            background: transparent;
        }
        
        #filterDropdown::-webkit-scrollbar-thumb {
            background-color: rgba(156, 163, 175, 0.5);
            border-radius: 3px;
        }
        
        #filterDropdown::-webkit-scrollbar-thumb:hover {
            background-color: rgba(156, 163, 175, 0.7);
        }
        
        .dark #filterDropdown {
            scrollbar-color: rgba(75, 85, 99, 0.5) transparent;
        }
        
        .dark #filterDropdown::-webkit-scrollbar-thumb {
            background-color: rgba(75, 85, 99, 0.5);
        }
        
        .dark #filterDropdown::-webkit-scrollbar-thumb:hover {
            background-color: rgba(75, 85, 99, 0.7);
        }
        .dark .chartjs-grid-color {
            color: theme('colors.border-dark');
        }
        .chartjs-grid-color {
            color: theme('colors.border-light');
        }
        .dark .chartjs-tick-color {
            color: theme('colors.text-muted-dark');
        }
        .chartjs-tick-color {
            color: theme('colors.text-muted-light');
        }
        .dark .chartjs-legend-color {
            color: theme('colors.text-dark');
        }
        .chartjs-legend-color {
            color: theme('colors.text-light');
        }
        
        /* Date inputs: keep native date picker, but align click-target with the right-side icon */
        input[type="date"] {
            position: relative;
        }
        input[type="date"]::-webkit-calendar-picker-indicator {
            opacity: 0;
            cursor: pointer;
            position: absolute;
            right: 0;
            top: 0;
            width: 2.75rem; /* matches the icon padding area */
            height: 100%;
        }
        .sidebar-collapsed .sidebar-text {
            display: none;
        }
        .sidebar-collapsed .sidebar-logo-text {
            display: none;
        }
        .sidebar {
            width: 16rem;
            min-width: 16rem;
            max-width: 16rem;
            flex-shrink: 0;
            transition: width 0.3s ease, min-width 0.3s ease, max-width 0.3s ease;
        }
        .sidebar-collapsed .sidebar {
            width: 5rem;
            min-width: 5rem;
            max-width: 5rem;
        }
        .sidebar-collapsed .sidebar-text { display: none; }
        .sidebar-collapsed .sidebar-logo-text { display: none; }
        /* Ensure sidebar links are centered when collapsed */
        .sidebar-collapsed .sidebar-nav-link {
            justify-content: center;
            padding-left: 0;
            padding-right: 0;
        }
        .sidebar-collapsed .sidebar-profile-info { display: none; }
        .sidebar-collapsed .sidebar-profile-picture { display: none; }
        .sidebar-collapsed .sidebar-toggle-container { justify-content: center; }
        .sidebar-collapsed .profile-container { justify-content: center; }
        .sidebar-collapsed .sidebar-toggle-icon-open { display: none; }
        .sidebar-collapsed .sidebar-toggle-icon-closed { display: block; }
        .sidebar-toggle-icon-closed { display: none; }
        /* Clickable table rows */
        tbody tr {
            cursor: pointer;
            transition: all 0.2s ease;
        }

        tbody tr:hover {
            background-color: rgba(19, 127, 236, 0.05) !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .dark tbody tr:hover {
            background-color: rgba(19, 127, 236, 0.1) !important;
        }

        /* Prevent hover effects on buttons and checkboxes */
        tbody tr:hover td button,
        tbody tr:hover td input[type="checkbox"] {
            pointer-events: auto;
        }

        /* Page Animation Effects */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .page-animate {
            animation: fadeInUp 0.6s ease-out forwards;
            opacity: 0;
        }

        .page-animate-delay-1 {
            animation: fadeInUp 0.6s ease-out 0.1s forwards;
            opacity: 0;
        }

        .page-animate-delay-2 {
            animation: fadeInUp 0.6s ease-out 0.2s forwards;
            opacity: 0;
        }

        .header-animate {
            animation: fadeIn 0.5s ease-out forwards;
        }

        .content-animate {
            animation: fadeInUp 0.7s ease-out 0.2s forwards;
            opacity: 0;
        }

        /* Fix for active sidebar link in dark mode - ensure dark gradient overrides light gradient */
        .dark .sidebar-nav-link.bg-gradient-to-r {
            background-image: linear-gradient(to right, rgba(88, 28, 135, 0.4), rgba(67, 56, 202, 0.4)) !important;
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-text-light dark:text-text-dark">
<div class="flex h-screen sidebar-collapsed" id="app-container">
    <aside class="sidebar bg-card-light dark:bg-card-dark border-r border-border-light dark:border-border-dark flex flex-col fixed h-full z-40 transition-all duration-300">
        <div class="flex items-center justify-start px-4 h-20 border-b border-border-light dark:border-border-dark flex-shrink-0">
            <div class="flex items-center gap-3 overflow-hidden">
                <img alt="CPU LILAC Logo" class="h-11 w-11 flex-shrink-0" src="./api/get-logo.php?v=1" width="44" height="44" onerror="this.style.display='none'; document.getElementById('logo-fallback').style.display='flex'; console.error('Logo failed to load:', this.src);"/>
                <div class="h-11 w-11 bg-primary rounded-lg flex items-center justify-center text-white font-bold text-sm flex-shrink-0" style="display: none;" id="logo-fallback">CPU</div>
                <h1 class="text-xl font-bold text-text-light dark:text-text-dark sidebar-logo-text whitespace-nowrap">LILAC</h1>
            </div>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-600 dark:hover:text-purple-400 transition-colors duration-200 sidebar-nav-link" href="dashboard.php" title="Dashboard">
                <span class="material-symbols-outlined flex-shrink-0">dashboard</span>
                <span class="sidebar-text whitespace-nowrap">Dashboard</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-600 dark:hover:text-purple-400 transition-colors duration-200 sidebar-nav-link" href="awards-hub.php" title="ICONS 2025 Hub">
                <span class="material-symbols-outlined flex-shrink-0">military_tech</span>
                <span class="sidebar-text whitespace-nowrap">ICONS 2025</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-600 dark:hover:text-purple-400 transition-colors duration-200 sidebar-nav-link" href="awards.php" title="Awards Progress">
                <span class="material-symbols-outlined flex-shrink-0">emoji_events</span>
                <span class="sidebar-text whitespace-nowrap">Awards Progress</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-600 dark:hover:text-purple-400 transition-colors duration-200 sidebar-nav-link" href="events-activities.php" title="Events & Activities">
                <span class="material-symbols-outlined flex-shrink-0">event</span>
                <span class="sidebar-text whitespace-nowrap">Events &amp; Activities</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-600 dark:hover:text-purple-400 transition-colors duration-200 sidebar-nav-link" href="scheduler.php" title="Scheduler">
                <span class="material-symbols-outlined flex-shrink-0">calendar_today</span>
                <span class="sidebar-text whitespace-nowrap">Scheduler</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg bg-gradient-to-r from-purple-50 to-indigo-50 dark:from-purple-900/40 dark:to-indigo-900/40 text-purple-600 dark:text-purple-400 font-semibold sidebar-nav-link border border-purple-200 dark:border-purple-800 shadow-sm" href="mou-moa.php" title="MOUs & MOAs">
                <span class="material-symbols-outlined filled flex-shrink-0">handshake</span>
                <span class="sidebar-text whitespace-nowrap">MOUs &amp; MOAs</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-600 dark:hover:text-purple-400 transition-colors duration-200 sidebar-nav-link" href="documents.php" title="Documents">
                <span class="material-symbols-outlined flex-shrink-0">description</span>
                <span class="sidebar-text whitespace-nowrap">Documents</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-600 dark:hover:text-purple-400 transition-colors duration-200 sidebar-nav-link" href="trash.php" title="Trash">
                <span class="material-symbols-outlined flex-shrink-0">delete</span>
                <span class="sidebar-text whitespace-nowrap">Trash</span>
            </a>
        </nav>
        <div class="px-4 py-4 border-t border-border-light dark:border-border-dark flex-shrink-0">
            <div class="flex items-center justify-between profile-container overflow-hidden">
                <div class="flex items-center gap-3 overflow-hidden">
                    <div class="w-10 h-10 rounded-full bg-cover bg-center sidebar-profile-picture flex-shrink-0" style='background-image: url("<?php echo !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : 'https://lh3.googleusercontent.com/aida-public/AB6AXuC23fvgOSZIK6K5vguUgvVeU1XYFfp1LB3d4zICMvW6bispRl-eHHfnOtSsvRU3MgvmOpSYMCZhcSBIksvjlEHtkGMxuCFsQkuT0suo2-O9n3py7mlzFFETXCOIfvLVGGUj1aaG8ENOeDXXy_ifek2uG3R3--ghDflKvuAm9vrceoK8doav0lNYVbLz1bnWy6REWcrCPuPZZ8upfPqShoQpSDjICl16zMEcRuHzjt05z9cFITLKPdZTfMF-1dLK-klh8UhjeDeE4Q7p'; ?>");'></div>
                    <div class="sidebar-profile-info overflow-hidden">
                        <p class="font-semibold text-text-light dark:text-text-dark truncate"><?php echo htmlspecialchars(!empty($user['full_name']) ? $user['full_name'] : $user['username']); ?></p>
                        <div class="flex gap-3">
                            <a class="text-sm text-primary-600 dark:text-primary-400 hover:underline whitespace-nowrap" href="profile.php">Profile</a>
                            <span class="text-sm text-gray-400">|</span>
                            <a class="text-sm text-red-600 dark:text-red-400 hover:underline whitespace-nowrap" href="logout.php">Logout</a>
                        </div>
                    </div>
                </div>
                <button class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-background-dark transition-colors flex-shrink-0" id="sidebar-toggle">
                    <span class="material-symbols-outlined sidebar-toggle-icon-open">chevron_left</span>
                    <span class="material-symbols-outlined sidebar-toggle-icon-closed hidden">chevron_right</span>
                </button>
            </div>
        </div>
    </aside>
    <main class="flex-1 overflow-y-auto transition-all duration-300 ml-64" id="main-content">
<header class="sticky top-0 bg-background-light/80 dark:bg-background-dark/80 backdrop-blur-sm z-30 px-6 lg:px-8 py-4 border-b border-border-light dark:border-border-dark flex justify-between items-center h-20 overflow-visible header-animate">
<div class="flex items-center gap-3">
<div class="w-10 h-10 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-500 flex items-center justify-center">
<span class="material-symbols-outlined text-white">handshake</span>
</div>
<div>
<h1 class="text-2xl font-bold text-text-light dark:text-text-dark">MOUs &amp; MOAs</h1>
<p class="text-sm text-text-muted-light dark:text-text-muted-dark">Manage international partnership and collaboration agreements</p>
</div>
</div>
<div class="flex items-center gap-2">
						<div class="relative z-[9999]">
    <button id="notificationBtn" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-white/10 text-text-muted-light dark:text-text-muted-dark transition-colors duration-200 relative">
<span class="material-symbols-outlined">notifications</span>
        <!-- Notification Badge -->
        <span id="notificationBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
</button>
    
    <!-- Notification Dropdown -->
    <!-- Make header and footer fixed, inner list scrollable (single inner scrollbar) -->
    <div id="notificationDropdown" class="absolute right-0 top-full mt-2 w-96 bg-white dark:bg-background-dark rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 z-[9999] hidden flex flex-col max-h-96">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Notifications</h3>
                <a href="#" id="markAllReadBtn" class="text-sm text-primary hover:text-primary/80 dark:text-primary-400 dark:hover:text-primary-300">
                    Mark all read
                </a>
            </div>
        </div>
        
        <!-- Scrollable list area -->
        <div id="notificationList" class="flex-1 min-h-0 overflow-y-auto divide-y divide-gray-200 dark:divide-gray-700">
            <!-- Notifications will be populated here -->
        </div>
        <div id="noNotifications" class="p-6 text-center text-gray-500 dark:text-gray-400 flex-shrink-0">
            <span class="material-symbols-outlined text-4xl mb-2 block">notifications_off</span>
            <p>No notifications</p>
        </div>
        
        <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex-shrink-0">
            <button type="button" id="viewAllNotifications" class="w-full text-center text-sm text-primary hover:text-primary/80 dark:text-primary-400 dark:hover:text-primary-300">
                View all notifications
            </button>
        </div>
    </div>
</div>
<button class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-white/10 text-text-muted-light dark:text-text-muted-dark transition-colors duration-200" id="theme-toggle">
<span class="material-symbols-outlined dark:hidden">light_mode</span>
<span class="material-symbols-outlined hidden dark:inline">dark_mode</span>
</button>
</div>
</header>
<div class="pt-1 pr-2 pb-1 lg:pt-2 lg:pr-4 lg:pb-2 main-content content-animate">
<div class="p-3">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
                    <!-- Search Input -->
                    <div class="flex-1 max-w-md">
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 transform -translate-y-1/2 text-text-muted-light dark:text-text-muted-dark text-lg">search</span>
                            <input type="text" id="searchInput" placeholder="Search by university or institution name..." autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" class="w-full pl-10 pr-4 py-2 text-sm text-text-light dark:text-text-dark bg-card-light dark:bg-background-dark/50 border border-border-light dark:border-border-dark rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="relative">
                            <button id="filterBtn" class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-text-light bg-card-light border border-border-light rounded-lg hover:bg-gray-50 dark:bg-background-dark/50 dark:text-text-muted-dark dark:border-border-dark dark:hover:bg-card-dark">
                            <span class="material-symbols-outlined text-base">filter_list</span>
                                <span id="filterText">Filter</span>
                        </button>
                            
                            <!-- Advanced Filter Dropdown Menu -->
                            <div id="filterDropdown" class="absolute right-0 mt-2 w-80 bg-white dark:bg-background-dark rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-50 hidden max-h-96 overflow-y-auto">
                                <div class="py-2">
                                    <!-- Status Filter -->
                                    <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Filter by Status</div>
                                    
                                    <button class="filter-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between" data-filter="status" data-value="all">
                                        <span>All Status</span>
                                        <span class="filter-indicator hidden">✓</span>
                                    </button>
                                    
                                    <button class="filter-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between" data-filter="status" data-value="Active">
                                        <span>Active</span>
                                        <span class="filter-indicator hidden">✓</span>
                                    </button>
                                    
                                    <button class="filter-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between" data-filter="status" data-value="Expires Soon">
                                        <span>Expires Soon</span>
                                        <span class="filter-indicator hidden">✓</span>
                                    </button>
                                    
                                    <button class="filter-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between" data-filter="status" data-value="Expired">
                                        <span>Expired</span>
                                        <span class="filter-indicator hidden">✓</span>
                                    </button>
                                    
                                    <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
                                    
                                    <!-- Institution Filter -->
                                    <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Filter by Institution</div>
                                    
                                    <div class="px-4 py-2">
                                        <label class="sr-only" for="institutionFilter">Search institution</label>
                                        <input type="text" id="institutionFilter" placeholder="Search institution..." class="w-full px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-background-dark text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary">
                                    </div>
                                    
                                    <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
                                    
                                    <!-- Date Range Filters -->
                                    <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Filter by Date Range</div>
                                    
                                    <div class="px-4 py-2 space-y-2">
                                        <div>
                                            <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1" for="signDateFrom">Sign Date From</label>
                                            <input type="date" id="signDateFrom" class="w-full px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-background-dark text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-primary">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1" for="signDateTo">Sign Date To</label>
                                            <input type="date" id="signDateTo" class="w-full px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-background-dark text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-primary">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1" for="endDateFrom">End Date From</label>
                                            <input type="date" id="endDateFrom" class="w-full px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-background-dark text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-primary">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-600 dark:text-gray-400 mb-1" for="endDateTo">End Date To</label>
                                            <input type="date" id="endDateTo" class="w-full px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-background-dark text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-primary">
                                        </div>
                                    </div>
                                    
                                    <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
                                    
                                    <!-- Term Duration Filter -->
                                    <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Filter by Term Duration</div>
                                    
                                    <button class="filter-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between" data-filter="term" data-value="all">
                                        <span>All Terms</span>
                                        <span class="filter-indicator hidden">✓</span>
                                    </button>
                                    
                                    <button class="filter-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between" data-filter="term" data-value="short">
                                        <span>Short Term (≤ 1 year)</span>
                                        <span class="filter-indicator hidden">✓</span>
                                    </button>
                                    
                                    <button class="filter-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between" data-filter="term" data-value="medium">
                                        <span>Medium Term (1-3 years)</span>
                                        <span class="filter-indicator hidden">✓</span>
                                    </button>
                                    
                                    <button class="filter-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between" data-filter="term" data-value="long">
                                        <span>Long Term (> 3 years)</span>
                                        <span class="filter-indicator hidden">✓</span>
                                    </button>
                                    
                                    <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
                                    
                                    <button id="clearFilter" class="w-full text-left px-4 py-2 text-sm text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800">
                                        Clear All Filters
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="relative">
    <button id="sortBtn" class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-text-light bg-card-light border border-border-light rounded-lg hover:bg-gray-50 dark:bg-background-dark/50 dark:text-text-muted-dark dark:border-border-dark dark:hover:bg-card-dark">
        <span class="material-symbols-outlined text-base">swap_vert</span>
        <span id="sortText">Sort</span>
    </button>
    
    <!-- Sort Dropdown Menu -->
    <div id="sortDropdown" class="absolute right-0 mt-2 w-56 bg-white dark:bg-background-dark rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-50 hidden">
        <div class="py-2">
            <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sort by</div>
            
            <button class="sort-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between" data-sort="institution" data-direction="asc">
                <span>Institution (A-Z)</span>
                <span class="sort-indicator hidden">✓</span>
            </button>
            
            <button class="sort-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between" data-sort="institution" data-direction="desc">
                <span>Institution (Z-A)</span>
                <span class="sort-indicator hidden">✓</span>
            </button>
            
            <button class="sort-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between" data-sort="location" data-direction="asc">
                <span>Location (A-Z)</span>
                <span class="sort-indicator hidden">✓</span>
            </button>
            
            <button class="sort-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between" data-sort="signDate" data-direction="desc">
                <span>Sign Date (Newest)</span>
                <span class="sort-indicator hidden">✓</span>
            </button>
            
            <button class="sort-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between" data-sort="signDate" data-direction="asc">
                <span>Sign Date (Oldest)</span>
                <span class="sort-indicator hidden">✓</span>
            </button>
            
            <button class="sort-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between" data-sort="endDate" data-direction="asc">
                <span>End Date (Soonest)</span>
                <span class="sort-indicator hidden">✓</span>
            </button>
            
            <button class="sort-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between" data-sort="endDate" data-direction="desc">
                <span>End Date (Latest)</span>
                <span class="sort-indicator hidden">✓</span>
            </button>
            
            <button class="sort-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between" data-sort="status" data-direction="asc">
                <span>Status (A-Z)</span>
                <span class="sort-indicator hidden">✓</span>
            </button>
            
            <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
            
            <button id="clearSort" class="w-full text-left px-4 py-2 text-sm text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800">
                Clear Sort
            </button>
        </div>
    </div>
</div>
                        <!-- Add File button - available to all authenticated users -->
                        <button id="addFileBtn" class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary/90">
                            <span class="material-symbols-outlined text-base">add</span>
                            Add File
                        </button>
                    </div>
                </div>

                <!-- Bulk Operations Toolbar - available to all authenticated users -->
                <div id="bulkOperationsToolbar" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-4 hidden">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <span class="text-sm font-medium text-blue-700 dark:text-blue-300">
                                <span id="selectedCount">0</span> item(s) selected
                            </span>
                            <div class="flex items-center gap-2">
                                <button id="selectAllBtn" class="text-xs px-3 py-1 bg-blue-100 dark:bg-blue-800 text-blue-700 dark:text-blue-300 rounded hover:bg-blue-200 dark:hover:bg-blue-700">
                                    Select All
                                </button>
                                <button id="selectNoneBtn" class="text-xs px-3 py-1 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-700">
                                    &nbsp;
                                </button>
                            </div>
                        </div>
                        <button id="bulkDeleteBtn" class="disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                            <img src="assets/images/delete.png" alt="Delete" class="w-6 h-6">
                        </button>
                    </div>
                </div>

                <div class="bg-card-light dark:bg-card-dark rounded-xl shadow-soft overflow-hidden border border-border-light dark:border-border-dark">
                    <div class="overflow-x-hidden">
                        <table class="table-fixed-layout divide-y divide-border-light dark:divide-border-dark">
                            <thead class="bg-gray-50 dark:bg-white/5">
                                <tr>
                                    <!-- Checkbox column for bulk operations -->
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-text-muted-light dark:text-text-muted-dark uppercase tracking-wider">
                                        <input type="checkbox" id="selectAllCheckbox" class="w-4 h-4 text-primary bg-gray-100 border-gray-300 rounded focus:ring-primary dark:focus:ring-primary dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600" title="Select All">
                                    </th>
                                    <!-- Institution column -->
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-text-muted-light dark:text-text-muted-dark uppercase tracking-wider">Institution</th>
                                    <!-- Type column -->
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-text-muted-light dark:text-text-muted-dark uppercase tracking-wider">Type</th>
                                    <!-- Sign Date column -->
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-text-muted-light dark:text-text-muted-dark uppercase tracking-wider">Sign Date</th>
                                    <!-- End Date column -->
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-text-muted-light dark:text-text-muted-dark uppercase tracking-wider">End Date</th>
                                    <!-- Status column -->
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-text-muted-light dark:text-text-muted-dark uppercase tracking-wider">Status</th>
                                    <!-- Action column (contains View and Delete) -->
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-text-muted-light dark:text-text-muted-dark uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-light dark:divide-border-dark">
                                <!-- Table rows will be populated from localStorage data -->
                            </tbody>
                        </table>
                    </div>
                    <div class="px-4 py-3 flex items-center justify-between border-t border-border-light dark:border-border-dark sm:px-6">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <button id="prevBtnMobile" class="relative inline-flex items-center px-4 py-2 border border-border-light text-sm font-medium rounded-md text-text-light bg-card-light hover:bg-gray-50 dark:text-text-muted-dark dark:border-border-dark dark:bg-background-dark/50 dark:hover:bg-card-dark disabled:opacity-50 disabled:cursor-not-allowed">Previous</button>
                            <button id="nextBtnMobile" class="ml-3 relative inline-flex items-center px-4 py-2 border border-border-light text-sm font-medium rounded-md text-text-light bg-card-light hover:bg-gray-50 dark:text-text-muted-dark dark:border-border-dark dark:bg-background-dark/50 dark:hover:bg-card-dark disabled:opacity-50 disabled:cursor-not-allowed">Next</button>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-text-muted-light dark:text-text-muted-dark">Showing <span class="font-medium" id="startEntry">1</span> to <span class="font-medium" id="endEntry">6</span> of <span class="font-medium" id="totalEntries">0</span> results</p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination" id="paginationNav">
                                    <!-- Pagination buttons will be generated dynamically -->
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
</div>
<!-- Modal Overlay and Container -->
<div id="addFileModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm hidden">
    <div class="w-full max-w-lg bg-white dark:bg-background-dark rounded-xl shadow-2xl m-4 flex flex-col max-h-[90vh]">
        <!-- Modal Header -->
        <div class="p-6 border-b border-gray-200 dark:border-gray-800 flex-shrink-0">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Add MOU/MOA File</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Fill in the details below to add a new memorandum.</p>
        </div>
        
        <!-- Modal Body -->
        <div class="p-6 space-y-4 overflow-y-auto">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" for="institution">Institution</label>
                    <input class="w-full bg-gray-50 dark:bg-background-dark/50 border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary" id="institution" placeholder="e.g., Central Philippine University" type="text" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false"/>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" id="autoInstitutionText">Will auto-detect from document</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" for="location">Location</label>
                    <input class="w-full bg-gray-50 dark:bg-background-dark/50 border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary" id="location" placeholder="e.g., Iloilo City" type="text" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false"/>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" id="autoLocationText"></p>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" for="contact">Contact Details <span class="text-gray-500 dark:text-gray-400 text-xs">(Optional)</span></label>
                <input class="w-full bg-gray-50 dark:bg-background-dark/50 border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary" id="contact" placeholder="e.g., email@example.com" type="text"/>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" for="sign-date">Sign Date</label>
                    <div class="relative">
                        <input class="w-full bg-gray-50 dark:bg-background-dark/50 border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary cursor-pointer pr-10" id="sign-date" type="date" autocomplete="off" placeholder="mm/dd/yyyy"/>
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 pointer-events-none">calendar_today</span>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" for="end-date">End Date <span class="text-gray-500 dark:text-gray-400 text-xs">(Optional)</span></label>
                    <div class="relative">
                        <input class="w-full bg-gray-50 dark:bg-background-dark/50 border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary cursor-pointer pr-10" id="end-date" type="date" autocomplete="off" placeholder="mm/dd/yyyy"/>
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 pointer-events-none">calendar_today</span>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" for="term">Term <span class="text-gray-500 dark:text-gray-400 text-xs">(Optional)</span></label>
                    <input class="w-full bg-gray-50 dark:bg-background-dark/50 border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary" id="term" placeholder="e.g., 3 Years" type="text"/>
                </div>
                <div aria-live="polite">
                    <p class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status <span class="text-gray-500 dark:text-gray-400 text-xs">(Optional)</span></p>
                    <div class="w-full bg-gray-100 dark:bg-background-dark/30 border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-500 dark:text-gray-400" id="auto-status" role="status">
                        <span id="autoStatusText">Auto-determined</span>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" for="category">Document Type</label>
                    <select id="category" name="category" class="w-full bg-card-light dark:bg-background-dark/50 border border-border-light dark:border-border-dark rounded-lg px-3 py-2 text-sm text-text-light dark:text-text-dark focus:ring-2 focus:ring-primary focus:border-primary">
                        <option value="">Select Type</option>
                        <option value="MOU (Memorandum of Understanding)">MOU (Memorandum of Understanding)</option>
                        <option value="MOA (Memorandum of Agreement)">MOA (Memorandum of Agreement)</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 hidden" aria-hidden="true">
                        <span id="autoCategoryText"></span>
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Add More Files</label>
                    <button type="button" id="addMoreFilesBtn" class="w-full bg-gray-50 dark:bg-background-dark/50 border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-background-dark/70 focus:outline-none focus:ring-2 focus:ring-primary transition-colors flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-sm">add</span>
                        <span>Add More Files</span>
                    </button>
                </div>
                </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Upload Document</label>
                <div id="mouDropZone" class="mt-2 flex justify-center rounded-lg border border-dashed border-gray-300 dark:border-gray-700 px-6 py-6">
                    <div class="text-center">
                        <span class="material-symbols-outlined text-4xl text-gray-400 dark:text-gray-600">cloud_upload</span>
                        <div class="mt-2 flex text-sm leading-6 text-gray-600 dark:text-gray-400">
                            <label class="relative cursor-pointer rounded-md font-semibold text-primary focus-within:outline-none focus-within:ring-2 focus-within:ring-primary focus-within:ring-offset-2 dark:focus-within:ring-offset-background-dark hover:text-primary/80" for="file-upload">
                                <span>Upload file(s)</span>
                                <input class="sr-only" id="file-upload" name="file-upload" type="file" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp"/>
                            </label>
                            <p class="pl-1">or drag and drop</p>
                        </div>
                        <p class="text-xs leading-5 text-gray-600 dark:text-gray-500">PDF, DOCX up to 10MB</p>
                    </div>
                </div>
                <!-- Selected files display -->
                <div id="selected-file-display" class="mt-3 hidden">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs text-gray-600 dark:text-gray-400">
                            Select the file to use for auto-fill (primary).
                        </p>
                    </div>
                    <div id="selected-files-list" class="space-y-2"></div>
                </div>
            </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="p-4 bg-gray-50 dark:bg-background-dark/30 rounded-b-xl flex justify-end gap-3 flex-shrink-0">
            <button id="cancelBtn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-background-dark/50 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-800">Cancel</button>
            <button id="saveBtn" class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary/90">Save</button>
        </div>
    </div>
</div>
<!-- File Viewer Modal -->
<div id="fileViewerModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm hidden">
    <div class="w-full max-w-4xl bg-white dark:bg-background-dark rounded-xl shadow-2xl m-4 flex flex-col max-h-[90vh]">
        <!-- Modal Header -->
        <div class="p-6 border-b border-gray-200 dark:border-gray-800 flex-shrink-0 flex items-center justify-between">
            <div>
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white" id="fileViewerTitle">View File</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1" id="fileViewerSubtitle">Document preview</p>
            </div>
            <button id="closeFileViewer" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500 dark:text-gray-400">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        
        <!-- Modal Body -->
        <div class="p-6 flex-1 overflow-auto" style="min-height: 400px;">
            <div class="w-full h-full bg-gray-50 dark:bg-gray-900 rounded-lg">
                <div id="fileViewerContent" class="w-full h-full">
                    <!-- File content will be displayed here -->
                    <div class="text-center flex items-center justify-center h-full">
                        <div>
                        <span class="material-symbols-outlined text-6xl text-gray-400 dark:text-gray-600 mb-4">description</span>
                        <p class="text-gray-500 dark:text-gray-400">File preview will be displayed here</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="p-4 bg-gray-50 dark:bg-background-dark/30 rounded-b-xl flex justify-between items-center flex-shrink-0">
            <div class="flex items-center gap-2">
                <button id="downloadFile" class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-primary bg-primary/10 rounded-lg hover:bg-primary/20">
                    <span class="material-symbols-outlined text-sm">download</span>
                    Download
                </button>
            </div>
            <!-- Footer close button removed (top-right X + Escape key handle closing) -->
        </div>
    </div>
</div>

<!-- Renew MOU/MOA Modal -->
<div id="renewMouModal" class="fixed inset-0 z-[10010] flex items-center justify-center bg-black/50 backdrop-blur-sm hidden">
    <div class="w-full max-w-lg bg-white dark:bg-background-dark rounded-xl shadow-2xl m-4 flex flex-col max-h-[90vh]">
        <!-- Modal Header -->
        <div class="p-6 border-b border-gray-200 dark:border-gray-800 flex-shrink-0 flex items-center justify-between">
            <div>
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white" id="renewMouTitle">Renew MOU/MOA</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1" id="renewMouSubtitle">Update dates and term for this agreement.</p>
            </div>
            <button id="closeRenewMouModal" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500 dark:text-gray-400" type="button">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        
        <!-- Modal Body -->
        <div class="p-6 space-y-4 overflow-y-auto">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" for="renew-sign-date">Sign Date</label>
                    <div class="relative">
                        <input class="w-full bg-gray-50 dark:bg-background-dark/50 border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary cursor-pointer pr-10" id="renew-sign-date" type="date" autocomplete="off" placeholder="mm/dd/yyyy"/>
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 pointer-events-none">calendar_today</span>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" for="renew-end-date">End Date</label>
                    <div class="relative">
                        <input class="w-full bg-gray-50 dark:bg-background-dark/50 border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary cursor-pointer pr-10" id="renew-end-date" type="date" autocomplete="off" placeholder="mm/dd/yyyy"/>
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 pointer-events-none">calendar_today</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" for="renew-term">Term</label>
                    <input class="w-full bg-gray-50 dark:bg-background-dark/50 border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary" id="renew-term" placeholder="e.g., 3 Years" type="text"/>
                </div>
                <div aria-live="polite">
                    <p class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</p>
                    <div class="w-full bg-gray-100 dark:bg-background-dark/30 border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-500 dark:text-gray-400" id="renew-auto-status" role="status">
                        <span id="renewAutoStatusText">Auto-determined</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="p-4 bg-gray-50 dark:bg-background-dark/30 rounded-b-xl flex justify-end gap-3 flex-shrink-0">
            <button id="renewCancelBtn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-background-dark/50 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-800" type="button">Cancel</button>
            <button id="renewConfirmBtn" class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700" type="button">Renew</button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm hidden">
    <div class="w-full max-w-md bg-white dark:bg-background-dark rounded-xl shadow-2xl m-4">
        <!-- Modal Header -->
        <div class="p-6 border-b border-gray-200 dark:border-gray-800">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-red-100 dark:bg-red-900/20 rounded-full flex items-center justify-center">
                    <span class="material-symbols-outlined text-red-600 dark:text-red-400 text-xl">warning</span>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Delete MOU/MOA</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">This action cannot be undone</p>
                </div>
            </div>
        </div>
        
        <!-- Modal Body -->
        <div class="p-6">
            <p class="text-gray-700 dark:text-gray-300">
                Are you sure you want to delete this MOU/MOA? This will permanently remove the entry and all associated data.
            </p>
        </div>
        
        <!-- Modal Footer -->
        <div class="p-6 bg-gray-50 dark:bg-background-dark/30 rounded-b-xl flex justify-end gap-3">
            <button id="cancelDelete" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-background-dark/50 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-800">
                Cancel
            </button>
            <button id="confirmDelete" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                Delete
            </button>
        </div>
    </div>
</div>

<!-- Bulk Delete Confirmation Modal -->
<div id="bulkDeleteConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm hidden">
    <div class="w-full max-w-md bg-white dark:bg-background-dark rounded-xl shadow-2xl m-4">
        <!-- Modal Header -->
        <div class="p-6 border-b border-gray-200 dark:border-gray-800">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-red-100 dark:bg-red-900/20 rounded-full flex items-center justify-center">
                    <span class="material-symbols-outlined text-red-600 dark:text-red-400 text-xl">warning</span>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Delete Selected Items</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">This action cannot be undone</p>
                </div>
            </div>
        </div>
        
        <!-- Modal Body -->
        <div class="p-6">
            <p class="text-gray-700 dark:text-gray-300">
                Are you sure you want to delete <span id="bulkDeleteCount" class="font-semibold">0</span> selected MOU/MOA entries? This will permanently remove all selected entries and their associated data.
            </p>
        </div>
        
        <!-- Modal Footer -->
        <div class="p-6 bg-gray-50 dark:bg-background-dark/30 rounded-b-xl flex justify-end gap-3">
            <button id="cancelBulkDelete" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-background-dark/50 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-800">
                Cancel
            </button>
            <button id="confirmBulkDelete" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                Delete
            </button>
        </div>
    </div>
</div>

<!-- Global Configuration -->
<script>
    // Pass PHP variables to JavaScript (use window to make globally accessible)
    // All authenticated users have admin access (same privileges)
    window.IS_ADMIN = true;  // Set to true for all authenticated users
    window.USER_ID = <?php echo json_encode($userId); ?>;
    window.USER_ROLE = <?php echo json_encode($user['role'] ?? 'user'); ?>;
    // Also create const aliases for convenience
    const IS_ADMIN = window.IS_ADMIN;
    const USER_ID = window.USER_ID;

</script>

<!-- Bulk Operations JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Bulk operations elements with null checks
        const bulkToolbar = document.getElementById('bulkOperationsToolbar');
        const selectedCount = document.getElementById('selectedCount');
        const selectAllBtn = document.getElementById('selectAllBtn');
        const selectNoneBtn = document.getElementById('selectNoneBtn');
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        const bulkDeleteModal = document.getElementById('bulkDeleteConfirmModal');
        const bulkDeleteCount = document.getElementById('bulkDeleteCount');
        const cancelBulkDeleteBtn = document.getElementById('cancelBulkDelete');
        const confirmBulkDeleteBtn = document.getElementById('confirmBulkDelete');

        // Only proceed if bulk operations elements exist
        if (!bulkToolbar || !selectedCount || !selectAllBtn || !selectNoneBtn || !bulkDeleteBtn || !selectAllCheckbox) {
            console.log('Bulk operations elements not found. Bulk functionality disabled.');
            return;
        }

        // Track selected items
        let selectedItems = new Set();

        // Function to update bulk operations UI
        function updateBulkOperationsUI() {
            // Count actual checked checkboxes instead of using selectedItems Set
            const checkedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
            const count = checkedCheckboxes.length;
            
            if (selectedCount) selectedCount.textContent = count;
            
            if (count > 0) {
                if (bulkToolbar) bulkToolbar.classList.remove('hidden');
                if (bulkDeleteBtn) bulkDeleteBtn.disabled = false;
            } else {
                if (bulkToolbar) bulkToolbar.classList.add('hidden');
                if (bulkDeleteBtn) bulkDeleteBtn.disabled = true;
            }

            // Update select all checkbox state
            const allCheckboxes = document.querySelectorAll('.row-checkbox');
            
            if (selectAllCheckbox) {
                if (checkedCheckboxes.length === 0) {
                    selectAllCheckbox.indeterminate = false;
                    selectAllCheckbox.checked = false;
                } else if (checkedCheckboxes.length === allCheckboxes.length) {
                    selectAllCheckbox.indeterminate = false;
                    selectAllCheckbox.checked = true;
                } else {
                    selectAllCheckbox.indeterminate = true;
                    selectAllCheckbox.checked = false;
                }
            }
        }

        // Function to handle individual checkbox change
        function handleRowCheckboxChange(checkbox) {
            // No need to maintain selectedItems Set anymore
            updateBulkOperationsUI();
        }

        // Add event listener to new checkboxes
        function addCheckboxEventListener(checkbox) {
            checkbox.addEventListener('change', function() {
                handleRowCheckboxChange(this);
            });
        }

        // Select all functionality
        selectAllBtn.addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            updateBulkOperationsUI();
        });

        // Select all checkbox functionality
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkOperationsUI();
        });

        // Select none functionality
        selectNoneBtn.addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            updateBulkOperationsUI();
        });

        // Bulk delete functionality
        bulkDeleteBtn.addEventListener('click', function() {
            const checkedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
            if (checkedCheckboxes.length > 0) {
                bulkDeleteCount.textContent = checkedCheckboxes.length;
                bulkDeleteModal.classList.remove('hidden');
            }
        });

        // Cancel bulk delete
        cancelBulkDeleteBtn.addEventListener('click', function() {
            bulkDeleteModal.classList.add('hidden');
        });

        // Confirm bulk delete
        confirmBulkDeleteBtn.addEventListener('click', async function() {
            // Get IDs from actually checked checkboxes
            const checkedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
            const idsToDelete = Array.from(checkedCheckboxes).map(checkbox => parseInt(checkbox.dataset.id));

            try {
                // Delete via API - remove action parameter, API only needs id for DELETE method
                const API_BASE_URL = 'api/mou-moa.php';
                const deletePromises = idsToDelete.map(id =>
                    fetch(`${API_BASE_URL}?id=${encodeURIComponent(id)}`, { method: 'DELETE' })
                        .then(async response => {
                            if (!response.ok) {
                                const errorText = await response.text();
                                throw new Error(`HTTP ${response.status}: ${errorText || 'Delete failed'}`);
                            }
                            return response.json();
                        })
                        .then(result => {
                            if (!result.success) {
                                throw new Error(result.error || 'Delete failed');
                            }
                            return result;
                        })
                        .catch(error => {
                            console.error(`Error deleting entry ${id}:`, error);
                            throw error;
                        })
                );
                
                await Promise.allSettled(deletePromises);

                // Reload data from database
                if (typeof window.loadFromDatabase === 'function') {
                    await window.loadFromDatabase();
                } else if (typeof loadFromDatabase === 'function') {
                    await loadFromDatabase();
                } else {
                    // Fallback: reload the page if function not available
                    window.location.reload();
                }

                // Close modal
                bulkDeleteModal.classList.add('hidden');

                // Clear selection
                selectedItems.clear();
                updateBulkOperationsUI();

            } catch (error) {
                console.error('Error during bulk delete:', error);
                
                // Show error message (self-contained)
                const errorToast = document.createElement('div');
                errorToast.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
                errorToast.textContent = 'Failed to delete selected entries: ' + error.message;
                document.body.appendChild(errorToast);
                
                setTimeout(() => {
                    if (document.body.contains(errorToast)) {
                        document.body.removeChild(errorToast);
                    }
                }, 5000);
            }
        });

        // Close modal when clicking outside
        bulkDeleteModal.addEventListener('click', function(e) {
            if (e.target === bulkDeleteModal) {
                bulkDeleteModal.classList.add('hidden');
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && bulkDeleteModal && !bulkDeleteModal.classList.contains('hidden')) {
                bulkDeleteModal.classList.add('hidden');
            }
        });

        // Initialize checkboxes for existing rows
        document.querySelectorAll('.row-checkbox').forEach(checkbox => {
            addCheckboxEventListener(checkbox);
        });

        // Make addCheckboxEventListener globally accessible for new rows
        window.addCheckboxEventListener = addCheckboxEventListener;

        // Initialize UI
        updateBulkOperationsUI();
    });
</script>


</main>
</div>
<script>
        document.addEventListener('DOMContentLoaded', () => {
            const themeToggle = document.getElementById('theme-toggle');
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const appContainer = document.getElementById('app-container');
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('main');
            const sidebarLogoText = document.querySelector('.sidebar-logo-text');
            const sidebarTexts = document.querySelectorAll('.sidebar-text');
            const sidebarProfileInfo = document.querySelector('.sidebar-profile-info');
            const sidebarProfilePicture = document.querySelector('.sidebar-profile-picture');
            const openIcon = document.querySelector('.sidebar-toggle-icon-open');
            const closedIcon = document.querySelector('.sidebar-toggle-icon-closed');
            const navLinks = document.querySelectorAll('.sidebar-nav-link');
            const profileContainer = document.querySelector('.profile-container');
            const toggleContainer = document.querySelector('.sidebar-toggle-container');
            
            // Initialize sidebar state
            const initSidebarState = () => {
                const savedState = localStorage.getItem('sidebarCollapsed');
                const mainContent = document.getElementById('main-content');
                if (savedState === 'false') {
                    appContainer.classList.remove('sidebar-collapsed');
                    if (mainContent) {
                        mainContent.classList.remove('ml-20');
                        mainContent.classList.add('ml-64');
                    }
                    
                    sidebarLogoText.classList.remove('hidden');
                    sidebarTexts.forEach(text => text.classList.remove('hidden'));
                    sidebarProfileInfo.classList.remove('hidden');
                    sidebarProfilePicture.classList.remove('hidden');
                    openIcon.classList.remove('hidden');
                    openIcon.classList.add('block');
                    closedIcon.classList.add('hidden');
                    closedIcon.classList.remove('block');
                    navLinks.forEach(link => link.classList.remove('justify-center'));
                    if (profileContainer) profileContainer.classList.remove('justify-center');
                    if (toggleContainer) toggleContainer.classList.remove('justify-center');
                } else {
                    // Default or true
                    appContainer.classList.add('sidebar-collapsed');
                    if (mainContent) {
                        mainContent.classList.remove('ml-64');
                        mainContent.classList.add('ml-20');
                    }
                }
            };
            initSidebarState();
            
            // Prevent navigating when clicking the current page link
            const currentPath = window.location.pathname.split('/').pop();
            navLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href === currentPath) {
                    link.addEventListener('click', (e) => {
                        e.preventDefault();
                    });
                }
            });
            
            // Function to toggle sidebar
            const toggleSidebar = () => {
                const isCollapsed = appContainer.classList.contains('sidebar-collapsed');
                const mainContent = document.getElementById('main-content');
                
                if (isCollapsed) {
                    // Expand sidebar
                    appContainer.classList.remove('sidebar-collapsed');
                    if (mainContent) {
                        mainContent.classList.remove('ml-20');
                        mainContent.classList.add('ml-64');
                    }
                    
                    sidebarLogoText.classList.remove('hidden');
                    sidebarTexts.forEach(text => text.classList.remove('hidden'));
                    sidebarProfileInfo.classList.remove('hidden');
                    sidebarProfilePicture.classList.remove('hidden');
                    openIcon.classList.remove('hidden');
                    openIcon.classList.add('block');
                    closedIcon.classList.add('hidden');
                    closedIcon.classList.remove('block');
                    navLinks.forEach(link => link.classList.remove('justify-center'));
                    if (profileContainer) profileContainer.classList.remove('justify-center');
                    if (toggleContainer) toggleContainer.classList.remove('justify-center');
                    
                    localStorage.setItem('sidebarCollapsed', 'false');
                } else {
                    // Collapse sidebar
                    appContainer.classList.add('sidebar-collapsed');
                    if (mainContent) {
                        mainContent.classList.remove('ml-64');
                        mainContent.classList.add('ml-20');
                    }
                    
                    sidebarLogoText.classList.add('hidden');
                    sidebarTexts.forEach(text => text.classList.add('hidden'));
                    sidebarProfileInfo.classList.add('hidden');
                    sidebarProfilePicture.classList.add('hidden');
                    openIcon.classList.add('hidden');
                    openIcon.classList.remove('block');
                    closedIcon.classList.remove('hidden');
                    closedIcon.classList.add('block');
                    navLinks.forEach(link => link.classList.add('justify-center'));
                    if (profileContainer) profileContainer.classList.add('justify-center');
                    if (toggleContainer) toggleContainer.classList.add('justify-center');
                    
                    localStorage.setItem('sidebarCollapsed', 'true');
                }
                
                // Force a reflow to ensure layout updates properly
                void appContainer.offsetHeight;
                
                setTimeout(() => {
                    if (window.awardsProgressChartInstance) {
                        window.awardsProgressChartInstance.resize();
                    }
                }, 350);
            };
            sidebarToggle.addEventListener('click', toggleSidebar);
            // Function to toggle dark mode
            const toggleDarkMode = (enable) => {
                if (enable) {
                    document.documentElement.classList.add('dark');
                    localStorage.setItem('theme', 'dark');
                } else {
                    document.documentElement.classList.remove('dark');
                    localStorage.setItem('theme', 'light');
                }
                // Force a re-render to ensure all elements update
                document.body.offsetHeight;
            };
            // Check for saved theme in localStorage
            if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                toggleDarkMode(true);
            } else {
                toggleDarkMode(false);
            }
            // Event listener for theme toggle button
            themeToggle.addEventListener('click', () => {
                const isCurrentlyDark = document.documentElement.classList.contains('dark');
                toggleDarkMode(!isCurrentlyDark);
                // Re-render chart on theme change
                if (window.awardsProgressChartInstance) {
                    window.awardsProgressChartInstance.destroy();
                }
                renderChart();
                // Reinitialize date pickers with new theme
                // No-op for native date inputs (styling updates automatically)
            });
            // Chart.js rendering
            const renderChart = () => {
                const canvas = document.getElementById('awardsProgressChart');
                if (!canvas) {
                    console.log('Chart canvas not found, skipping chart rendering');
                    return;
                }
                
                const ctx = canvas.getContext('2d');
                const isDarkMode = document.documentElement.classList.contains('dark');
                const gridColor = isDarkMode ? tailwind.theme.extend.colors.border-dark : tailwind.theme.extend.colors.border-light;
                const tickColor = isDarkMode ? tailwind.theme.extend.colors['text-muted-dark'] : tailwind.theme.extend.colors['text-muted-light'];
                const legendColor = isDarkMode ? tailwind.theme.extend.colors['text-dark'] : tailwind.theme.extend.colors['text-light'];
                const data = {
                    labels: ['Teaching', 'Research', 'Extension', 'Support', 'Admin'],
                    datasets: [{
                        label: 'Award Progress',
                        data: [75, 60, 90, 45, 82],
                        backgroundColor: [
                            'rgba(19, 127, 236, 0.2)', // primary
                            'rgba(34, 197, 94, 0.2)', // green
                            'rgba(234, 179, 8, 0.2)', // yellow
                            'rgba(239, 68, 68, 0.2)', // red
                            'rgba(99, 102, 241, 0.2)', // indigo
                        ],
                        borderColor: [
                            'rgb(19, 127, 236)', // primary
                            'rgb(34, 197, 94)', // green
                            'rgb(234, 179, 8)', // yellow
                            'rgb(239, 68, 68)', // red
                            'rgb(99, 102, 241)', // indigo
                        ],
                        borderWidth: 2,
                        borderRadius: 8,
                        borderSkipped: false,
                    }]
                };
                const options = {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += context.parsed.y + '%';
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            grid: {
                                color: gridColor,
                                drawBorder: false,
                            },
                            ticks: {
                                color: tickColor,
                                padding: 10,
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false,
                            },
                            ticks: {
                                color: tickColor,
                                padding: 10,
                            }
                        }
                    }
                };
                window.awardsProgressChartInstance = new Chart(ctx, {
                    type: 'bar',
                    data: data,
                    options: options
                });
            };
            renderChart();
        });
    </script>

    <!-- Modal functionality -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const addFileBtn = document.getElementById('addFileBtn');
            const modal = document.getElementById('addFileModal');
            const cancelBtn = document.getElementById('cancelBtn');
            const saveBtn = document.getElementById('saveBtn');
            const fileUploadInput = document.getElementById('file-upload');
            const selectedFileDisplay = document.getElementById('selected-file-display');
            const selectedFilesList = document.getElementById('selected-files-list');

            // Multi-file selection state (for the Add/Edit modal)
            let selectedMouFiles = [];
            let primaryFileIndex = 0;

            function syncFileInputFromState() {
                if (!fileUploadInput) return;
                try {
                    const dt = new DataTransfer();
                    selectedMouFiles.forEach(f => dt.items.add(f));
                    fileUploadInput.files = dt.files;
                } catch (e) {
                    console.warn('Could not sync file input from state:', e);
                }
            }

            function runAutoFillFromPrimary(options = {}) {
                const primaryFile = selectedMouFiles[primaryFileIndex] || null;
                if (!primaryFile) return;

                const { fromPrimaryChange = false } = options;

                // When user switches the primary MOU file, start fresh so new OCR
                // results can overwrite the previous institution/location.
                if (fromPrimaryChange) {
                    const institutionInputEl = document.getElementById('institution');
                    const locationInputEl = document.getElementById('location');
                    const signDateEl = document.getElementById('sign-date');
                    const endDateEl = document.getElementById('end-date');
                    const termEl = document.getElementById('term');

                    if (institutionInputEl) institutionInputEl.value = '';
                    if (locationInputEl) locationInputEl.value = '';
                    if (signDateEl) signDateEl.value = '';
                    if (endDateEl) endDateEl.value = '';
                    if (termEl) termEl.value = '';

                    // Allow OCR to write new values
                    institutionTouched = false;
                    locationTouched = false;
                }

                analyzeDocumentType(primaryFile);
                analyzeInstitution(primaryFile);
            }

            // Pagination variables
            let currentPage = 1;
            const itemsPerPage = 10;
            let allEntries = [];
            let originalEntries = []; // Store original unfiltered entries for search
            
            // OCR field extraction endpoint (auto-fill)
            const MOU_FIELDS_OCR_URL = 'api/mou-moa-ocr.php';
            let institutionTouched = false;
            let locationTouched = false;
            let termTouched = false;
            
            // Native date pickers (match Events & Activities behavior)
            function initDatePickers() {
                const signDateInput = document.getElementById('sign-date');
                const endDateInput = document.getElementById('end-date');

                // Auto-open native picker on click where supported
                if (signDateInput) {
                    signDateInput.addEventListener('click', function(event) {
                        // Guard against programmatic clicks, which are not considered a user gesture
                        if (!event.isTrusted) return;
                        if (typeof this.showPicker === 'function') {
                            try {
                                this.showPicker();
                            } catch (e) {
                                // Some browsers may still throw if they consider this non-user initiated
                                console.warn('showPicker could not be opened:', e);
                            }
                        }
                    });
                }
                if (endDateInput) {
                    endDateInput.addEventListener('click', function(event) {
                        // Guard against programmatic clicks, which are not considered a user gesture
                        if (!event.isTrusted) return;
                        if (typeof this.showPicker === 'function') {
                            try {
                                this.showPicker();
                            } catch (e) {
                                // Some browsers may still throw if they consider this non-user initiated
                                console.warn('showPicker could not be opened:', e);
                            }
                        }
                    });
                }
            }

            // Normalize incoming date strings so <input type="date"> can display them (expects YYYY-MM-DD)
            function normalizeDateForInput(dateStr) {
                if (!dateStr) return '';
                const s = String(dateStr).trim();
                if (!s) return '';

                // Already in correct format
                if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s;

                // MM/DD/YYYY
                const mdy = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
                if (mdy) {
                    const mm = String(mdy[1]).padStart(2, '0');
                    const dd = String(mdy[2]).padStart(2, '0');
                    const yyyy = mdy[3];
                    return `${yyyy}-${mm}-${dd}`;
                }

                // Try Date parsing (handles "March 20, 2025", etc.)
                const d = new Date(s);
                if (!isNaN(d.getTime())) {
                    const yyyy = d.getFullYear();
                    const mm = String(d.getMonth() + 1).padStart(2, '0');
                    const dd = String(d.getDate()).padStart(2, '0');
                    return `${yyyy}-${mm}-${dd}`;
                }

                return '';
            }

            // Map stored category values to the exact <select> option values
            function normalizeCategoryForSelect(category) {
                if (!category) return '';
                const s = String(category).trim();
                if (!s) return '';

                // Exact matches to option values
                if (s === 'MOU (Memorandum of Understanding)' || s === 'MOA (Memorandum of Agreement)') return s;

                const upper = s.toUpperCase();

                // Common short forms / variants
                if (upper === 'MOU' || upper.includes('MEMORANDUM OF UNDERSTANDING') || upper.includes(' MOU')) {
                    return 'MOU (Memorandum of Understanding)';
                }
                if (upper === 'MOA' || upper.includes('MEMORANDUM OF AGREEMENT') || upper.includes(' MOA')) {
                    return 'MOA (Memorandum of Agreement)';
                }

                // Fallback: simple contains checks
                if (upper.includes('MOU')) return 'MOU (Memorandum of Understanding)';
                if (upper.includes('MOA')) return 'MOA (Memorandum of Agreement)';

                return '';
            }

            // Show modal when Add File button is clicked
            if (addFileBtn && modal) {
                addFileBtn.addEventListener('click', function() {
                    // Reset editing state
                    window.editingEntryId = undefined;
                    
                    // Reset modal title and button text for adding
                    const modalTitle = document.querySelector('#addFileModal h3');
                    const saveBtn = document.getElementById('saveBtn');
                    modalTitle.textContent = 'Add MOU/MOA File';
                    saveBtn.textContent = 'Save';
                    
                    // Check if details modal is open - if so, keep it open and stack add modal on top
                    const detailsModal = document.getElementById('mouDetailsModal');
                    const isDetailsModalOpen = detailsModal && !detailsModal.classList.contains('hidden');
                    
                    // Show the add modal - it will appear on top of details modal if open (higher z-index)
                    modal.classList.remove('hidden');
                    // Ensure modal appears on top (z-index already set to z-[100] in HTML)
                    modal.style.zIndex = '100';

                    // Ensure the Add modal starts clean (prevents previous file name from persisting)
                    try {
                        if (typeof resetForm === 'function') resetForm();
                        if (fileUploadInput) fileUploadInput.value = '';
                        selectedMouFiles = [];
                        primaryFileIndex = 0;
                        updateSelectedFileDisplay([]);
                        resetCategoryDetection();
                        resetInstitutionDetection();
                    } catch (_) {}
                    
                    // If details modal is open, ensure it stays open (don't close it)
                    if (isDetailsModalOpen) {
                        // Keep details modal open behind the add modal
                        detailsModal.style.zIndex = '50';
                        console.log('Add modal opened on top of details modal');
                    }
                    
                    // Clear selected file display when opening modal
                    selectedMouFiles = [];
                    primaryFileIndex = 0;
                    updateSelectedFileDisplay([]);
                    resetInstitutionDetection();
                    
                    // Ensure native date picker click behavior is wired
                    setTimeout(() => {
                        initDatePickers();
                    }, 0);
                });
            }

            // Hide modal when Cancel button is clicked
            if (cancelBtn && modal) {
                cancelBtn.addEventListener('click', function() {
                    modal.classList.add('hidden');
                    // Clear selected file display when closing modal
                    try {
                        if (fileUploadInput) fileUploadInput.value = '';
                        selectedMouFiles = [];
                        primaryFileIndex = 0;
                        updateSelectedFileDisplay([]);
                        resetCategoryDetection();
                        resetInstitutionDetection();
                        if (typeof resetForm === 'function') resetForm();
                    } catch (_) {}
                });
            }

            // Hide modal when clicking outside of it
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        modal.classList.add('hidden');
                        // Clear selected file display when closing modal
                        try {
                            if (fileUploadInput) fileUploadInput.value = '';
                            selectedMouFiles = [];
                            primaryFileIndex = 0;
                            updateSelectedFileDisplay([]);
                            resetCategoryDetection();
                            resetInstitutionDetection();
                            if (typeof resetForm === 'function') resetForm();
                        } catch (_) {}
                    }
                });
            }

            // Function to update selected files display (shows count only)
            function updateSelectedFileDisplay(files) {
                if (!selectedFileDisplay || !selectedFilesList) return;

                if (Array.isArray(files) && files.length) {
                    selectedFileDisplay.classList.remove('hidden');
                    
                    // Show only the count
                    const count = files.length;
                    const fileText = count === 1 ? 'file' : 'files';
                    selectedFilesList.innerHTML = `
                        <div class="flex items-center gap-2 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg px-3 py-2">
                            <span class="material-symbols-outlined text-green-600 dark:text-green-400 text-sm">check_circle</span>
                            <span class="text-sm text-green-700 dark:text-green-300">
                                ${count} ${fileText} added
                            </span>
                        </div>
                    `;
                } else {
                    selectedFilesList.innerHTML = '';
                    selectedFileDisplay.classList.add('hidden');
                }
            }

            function setInstitutionHint(text, kind = 'neutral') {
                const el = document.getElementById('autoInstitutionText');
                if (!el) return;
                // Per request: keep the UI silent (no status/hint text shown)
                el.textContent = '';
                el.className = 'mt-1 text-xs text-gray-500 dark:text-gray-400';
            }

            function setLocationHint(text, kind = 'neutral') {
                const el = document.getElementById('autoLocationText');
                if (!el) return;
                // Per request: keep the UI silent (no status/hint text shown)
                el.textContent = '';
                el.className = 'mt-1 text-xs text-gray-500 dark:text-gray-400';
            }

            function resetInstitutionDetection() {
                institutionTouched = false;
                locationTouched = false;
                termTouched = false;
                setInstitutionHint('Will auto-detect from document', 'neutral');
            }

            async function analyzeInstitution(file) {
                const institutionInput = document.getElementById('institution');
                const locationInput = document.getElementById('location');
                if (!institutionInput || !file) return;

                setInstitutionHint('Scanning institution...', 'loading');

                // Speed optimization: for OCR, downscale/compress images before sending to server/browser OCR.
                // This does NOT affect the actual file you will save—only the temporary OCR request.
                async function createOcrOptimizedFile(originalFile) {
                    try {
                        if (!originalFile || !originalFile.type || !originalFile.type.startsWith('image/')) return originalFile;

                        const MAX_DIM = 1400; // tuned for speed + accuracy on typical scanned documents
                        const QUALITY = 0.75;

                        let w = 0;
                        let h = 0;
                        let drawSource = null;

                        if (typeof createImageBitmap === 'function') {
                            const bmp = await createImageBitmap(originalFile);
                            w = bmp.width;
                            h = bmp.height;
                            drawSource = bmp;
                        } else {
                            const url = URL.createObjectURL(originalFile);
                            const img = await new Promise((resolve, reject) => {
                                const i = new Image();
                                i.onload = () => resolve(i);
                                i.onerror = reject;
                                i.src = url;
                            });
                            URL.revokeObjectURL(url);
                            w = img.naturalWidth || img.width;
                            h = img.naturalHeight || img.height;
                            drawSource = img;
                        }

                        if (!w || !h || !drawSource) return originalFile;

                        const scale = Math.min(1, MAX_DIM / Math.max(w, h));
                        const outW = Math.max(1, Math.round(w * scale));
                        const outH = Math.max(1, Math.round(h * scale));

                        // If already small enough, avoid extra work
                        if (scale === 1 && (originalFile.type === 'image/jpeg' || originalFile.type === 'image/jpg')) {
                            return originalFile;
                        }

                        const canvas = document.createElement('canvas');
                        canvas.width = outW;
                        canvas.height = outH;
                        const ctx = canvas.getContext('2d');
                        if (!ctx) return originalFile;
                        ctx.drawImage(drawSource, 0, 0, outW, outH);

                        const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', QUALITY));
                        if (!blob) return originalFile;

                        const baseName = (originalFile.name || 'ocr').replace(/\.[^/.]+$/, '');
                        return new File([blob], `${baseName}_ocr.jpg`, { type: 'image/jpeg' });
                    } catch (e) {
                        console.warn('OCR optimization failed, using original file', e);
                        return originalFile;
                    }
                }

                // Helper: clean institution name by removing location/address information
                function cleanInstitutionName(s) {
                    if (!s) return '';
                    s = String(s).trim().replace(/\s+/g, ' ');
                    
                    // Remove leading labels
                    s = s.replace(/^(the\s+)?(partner\s+)?institution\s*[:\-]\s*/i, '');
                    s = s.replace(/^(name\s+of\s+)?(the\s+)?institution\s*[:\-]\s*/i, '');
                    
                    // Remove trailing boilerplate
                    s = s.replace(/\s*,?\s*(hereinafter|herein after).*$/i, '');
                    s = s.replace(/\s*[.,;:\-]+$/, '');
                    
                    // Remove location/address information - stop at location indicators
                    s = s.replace(/\s*,\s*(located\s+(?:at|in)|in\s+[A-Z][a-z]+|at\s+[A-Z][a-z]+).*$/i, '');
                    
                    // Remove country/city names at the end
                    const locationPatterns = [
                        /\s*,\s*(china|japan|philippines|philippine|united\s+states|usa|korea|canada|australia|singapore|malaysia|thailand|vietnam|indonesia|india|taiwan).*$/i,
                        /\s*,\s*([A-Z][a-z]+\s*(?:city|province|state|region|prefecture|county)).*$/i,
                        /\s*,\s*([A-Z][a-z]+,\s*(?:china|japan|philippines|philippine|united\s+states|usa|korea|canada|australia|singapore|malaysia|thailand|vietnam|indonesia|india|taiwan)).*$/i,
                    ];
                    locationPatterns.forEach(pattern => {
                        s = s.replace(pattern, '');
                    });
                    
                    // If there are multiple commas with location keywords, take only first part
                    const locMatch = s.match(/^([^,]+(?:,\s*[^,]+)*?)(?:,\s*[^,]*?(?:located|address|in\s+[A-Z]|city|province|state|country|china|japan|philippines|usa|korea|canada|australia))/i);
                    if (locMatch) {
                        s = locMatch[1];
                    }
                    
                    // Remove surrounding quotes
                    s = s.trim().replace(/^["'"]+|["'"]+$/g, '');
                    
                    // Guardrails
                    if (s.length < 4) return '';
                    if (/^(memorandum|agreement|understanding|contract|page)\b/i.test(s)) return '';
                    
                    // Reject person names: patterns like "nee", initials only, or name-like patterns
                    const lower = s.toLowerCase();
                    if (/\bnee\b/i.test(s)) return ''; // "nee" indicates a person's name (maiden name)
                    if (/^(mr|mrs|ms|dr|prof|professor)\s+/i.test(s)) return ''; // Titles indicate person names
                    if (/\b(sr|jr|iii|iv|ii)\b/i.test(s)) return ''; // Suffixes indicate person names
                    
                    // Reject if it looks like initials or very short name patterns (e.g., "KM SANG")
                    // But allow if it contains institution keywords
                    const hasInstitutionKeyword = /\b(university|college|institute|institution|school|academy|polytechnic|foundation|corporation|inc|llc|ltd)\b/i.test(s);
                    if (!hasInstitutionKeyword) {
                        // If it's mostly uppercase initials/words without institution keywords, likely a person name
                        const words = s.split(/\s+/);
                        let shortWords = 0;
                        words.forEach(word => {
                            const cleanWord = word.replace(/[^A-Za-z]/g, '');
                            if (cleanWord.length <= 3) shortWords++;
                        });
                        // If more than half are short words, likely initials/name
                        if (words.length > 0 && shortWords >= Math.ceil(words.length / 2) && words.length <= 6) {
                            return '';
                        }
                    }
                    
                    return s.trim();
                }

                // Helper: parse partner institution (second party) from OCR text
                function extractPartnerInstitutionFromText(text) {
                    const raw = String(text || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
                    const lower = raw.toLowerCase();
                    const lines = raw.split('\n').map(l => l.trim()).filter(Boolean);
                    
                    // Prefer explicit "between A and B"
                    const m = raw.match(/\bbetween\b\s+([\s\S]{0,200}?)\s+(?:and|&)\s+([\s\S]{0,200}?)(?:\n|,|\.|;)/i);
                    if (m && m[2]) {
                        const cleaned = cleanInstitutionName(String(m[2]).trim());
                        if (cleaned) {
                            // Accept if has keywords OR if not obviously a person name
                            const hasKeyword = /\b(university|college|institute|institution|school|academy|polytechnic|foundation|corporation|inc|llc|ltd)\b/i.test(cleaned);
                            if (hasKeyword) {
                                return cleaned;
                            }
                            // Without keywords, check it's not a person name (initials pattern)
                            const words = cleaned.split(/\s+/);
                            let shortWords = 0;
                            words.forEach(word => {
                                const cleanWord = word.replace(/[^A-Za-z]/g, '');
                                if (cleanWord.length <= 3) shortWords++;
                            });
                            // Accept if reasonable length and not mostly short words
                            if (cleaned.length >= 8 && cleaned.length <= 160 && !(shortWords >= Math.ceil(words.length / 2) && words.length <= 6)) {
                                return cleaned;
                            }
                        }
                    }

                    // Scanned layout: between (line), A (line), and (line), B (line)
                    let idxBetween = lines.findIndex(l => /\bbetween\b/i.test(l));
                    if (idxBetween >= 0) {
                        // Inline between line: "between A and B"
                        const inline = lines[idxBetween];
                        const m2 = inline.match(/\bbetween\b\s+(.+?)\s+(?:and|&)\s+(.+)\s*$/i);
                        if (m2 && m2[2]) {
                            const cleaned = cleanInstitutionName(String(m2[2]).trim());
                            if (cleaned) {
                                const hasKeyword = /\b(university|college|institute|institution|school|academy|polytechnic|foundation|corporation|inc|llc|ltd)\b/i.test(cleaned);
                                if (hasKeyword || (cleaned.length >= 8 && cleaned.length <= 160)) {
                                    return cleaned;
                                }
                            }
                        }

                        // Seek next meaningful lines
                        let partyA = '';
                        let partyB = '';
                        let state = 'seekA';
                        for (let j = idxBetween + 1; j < Math.min(lines.length, idxBetween + 12); j++) {
                            const l = lines[j];
                            if (!l) continue;
                            if (/\bmemorandum\b/i.test(l)) continue;

                            if (state === 'seekA') {
                                partyA = l;
                                state = 'seekAnd';
                                continue;
                            }
                            if (state === 'seekAnd') {
                                if (/^(and|&)$/i.test(l)) {
                                    state = 'seekB';
                                    continue;
                                }
                                // OCR sometimes misses standalone "and"
                                partyB = l;
                                break;
                            }
                            if (state === 'seekB') {
                                partyB = l;
                                break;
                            }
                        }
                        if (partyB) {
                            const cleaned = cleanInstitutionName(partyB);
                            if (cleaned) {
                                const hasKeyword = /\b(university|college|institute|institution|school|academy|polytechnic|foundation|corporation|inc|llc|ltd)\b/i.test(cleaned);
                                if (hasKeyword || (cleaned.length >= 8 && cleaned.length <= 160)) {
                                    return cleaned;
                                }
                            }
                        }
                    }

                    // As last resort, pick the best-looking "school/university/college..." line
                    // This already filters by institution keywords, so safe
                    const keywords = /(university|college|institute|school|academy|polytechnic|foundation|corporation)/i;
                    const best = lines.filter(l => keywords.test(l) && l.length >= 6 && l.length <= 120);
                    return best[0] ? cleanInstitutionName(best[0]) : '';
                }

                // Enhanced rule-based country detector (matches PHP version)
                function detectCountryFromText(text) {
                    const raw = String(text || '').trim();
                    if (!raw) return '';
                    
                    const lower = raw.toLowerCase();
                    const normalized = lower.replace(/[^\w\s]/g, ' ').replace(/\s+/g, ' ');
                    
                    // Comprehensive country detection rules
                    const countryRules = {
                        'China': {
                            patterns: [/\bchina\b/, /\bfujian\b/, /\bzhangzhou\b/, /\bchinese\b/, /\bprc\b/, /\bcn\b/],
                            cities: ['beijing', 'shanghai', 'guangzhou', 'shenzhen', 'chengdu', 'hangzhou', 'wuhan', 'xian', 'nanjing', 'tianjin', 'chongqing', 'dalian', 'xiamen', 'qingdao', 'fuzhou', 'suzhou', 'ningbo', 'wenzhou'],
                            provinces: ['guangdong', 'jiangsu', 'zhejiang', 'shandong', 'henan', 'sichuan', 'hubei', 'hunan', 'fujian', 'anhui', 'liaoning', 'hebei', 'shaanxi', 'jiangxi', 'guangxi', 'yunnan', 'heilongjiang']
                        },
                        'Japan': {
                            patterns: [/\bjapan\b/, /\bjapanese\b/, /\bjp\b/],
                            cities: ['tokyo', 'osaka', 'yokohama', 'nagoya', 'sapporo', 'fukuoka', 'kobe', 'kyoto', 'saitama', 'hiroshima', 'sendai', 'kawasaki', 'chiba', 'kitakyushu', 'sakai', 'shizuoka', 'nagata'],
                            provinces: ['hokkaido', 'aomori', 'iwate', 'miyagi', 'akita', 'yamagata', 'fukushima', 'ibaraki', 'tochigi', 'gunma', 'saitama', 'chiba', 'tokyo', 'kanagawa', 'yamanashi', 'nagano', 'niigata', 'toyama']
                        },
                        'Philippines': {
                            patterns: [/\bphilippines\b/, /\bphilippine\b/, /\bph\b/, /\bphl\b/],
                            cities: ['manila', 'quezon city', 'davao', 'caloocan', 'cebu', 'zamboanga', 'antipolo', 'pasig', 'tagig', 'valenzuela', 'paranaque', 'makati', 'san jose del monte', 'las pinas', 'bacolod', 'iloilo', 'muntinlupa', 'calamba', 'marikina', 'butuan', 'mandaluyong'],
                            provinces: ['metro manila', 'cavite', 'laguna', 'rizal', 'bulacan', 'pampanga', 'bataan', 'nueva ecija', 'tarlac', 'pangasinan', 'batangas', 'quezon']
                        },
                        'United States': {
                            patterns: [/\bunited\s+states\b/, /\busa\b/, /\bu\.s\.a\b/, /\bu\.s\b/, /\bus\b/, /\bamerican\b/],
                            cities: ['new york', 'los angeles', 'chicago', 'houston', 'phoenix', 'philadelphia', 'san antonio', 'san diego', 'dallas', 'san jose', 'austin', 'jacksonville', 'san francisco', 'indianapolis', 'columbus', 'fort worth', 'charlotte'],
                            provinces: ['california', 'texas', 'florida', 'new york', 'pennsylvania', 'illinois', 'ohio', 'georgia', 'north carolina', 'michigan', 'new jersey', 'virginia']
                        },
                        'Korea': {
                            patterns: [/\bsouth\s+korea\b/, /\bkorea\b/, /\bkorean\b/, /\bkr\b/, /\bkorea\s+republic\b/],
                            cities: ['seoul', 'busan', 'incheon', 'daegu', 'daejeon', 'gwangju', 'suwon', 'ulsan', 'changwon', 'goyang', 'yongin', 'bucheon'],
                            provinces: ['gyeonggi', 'gangwon', 'chungbuk', 'chungnam', 'jeonbuk', 'jeonnam', 'gyeongbuk', 'gyeongnam', 'jeju']
                        },
                        'Canada': {
                            patterns: [/\bcanada\b/, /\bcanadian\b/, /\bca\b/, /\bcan\b/],
                            cities: ['toronto', 'montreal', 'calgary', 'ottawa', 'edmonton', 'winnipeg', 'vancouver', 'mississauga', 'brampton', 'hamilton', 'quebec'],
                            provinces: ['ontario', 'quebec', 'british columbia', 'alberta', 'manitoba', 'saskatchewan', 'nova scotia', 'new brunswick', 'newfoundland']
                        },
                        'Australia': {
                            patterns: [/\baustralia\b/, /\baustralian\b/, /\bau\b/, /\baus\b/],
                            cities: ['sydney', 'melbourne', 'brisbane', 'perth', 'adelaide', 'gold coast', 'newcastle', 'canberra', 'sunshine coast', 'wollongong', 'hobart'],
                            provinces: ['new south wales', 'victoria', 'queensland', 'western australia', 'south australia', 'tasmania', 'australian capital territory']
                        },
                        'Singapore': {
                            patterns: [/\bsingapore\b/, /\bsingaporean\b/, /\bsg\b/, /\bsgp\b/],
                            cities: ['singapore']
                        },
                        'Malaysia': {
                            patterns: [/\bmalaysia\b/, /\bmalaysian\b/, /\bmy\b/, /\bmys\b/],
                            cities: ['kuala lumpur', 'george town', 'ipoh', 'shah alam', 'petaling jaya', 'johor bahru', 'melaka', 'kuching', 'kota kinabalu', 'seremban'],
                            provinces: ['johor', 'kedah', 'kelantan', 'melaka', 'negeri sembilan', 'pahang', 'penang', 'perak', 'perlis', 'sabah', 'sarawak', 'selangor']
                        },
                        'Thailand': {
                            patterns: [/\bthailand\b/, /\bthai\b/, /\bth\b/, /\btha\b/],
                            cities: ['bangkok', 'nonthaburi', 'nakhon ratchasima', 'chiang mai', 'hat yai', 'udon thani', 'pak kret', 'khon kaen'],
                            provinces: ['bangkok', 'chiang mai', 'chiang rai', 'phuket', 'pattaya', 'ayutthaya', 'sukhothai', 'kanchanaburi']
                        },
                        'Vietnam': {
                            patterns: [/\bvietnam\b/, /\bvietnamese\b/, /\bvn\b/, /\bvnm\b/],
                            cities: ['ho chi minh city', 'hanoi', 'haiphong', 'can tho', 'da nang', 'bian hoa', 'hue', 'nha trang', 'vung tau', 'quy nhon'],
                            provinces: ['ho chi minh', 'hanoi', 'haiphong', 'can tho', 'da nang', 'dong nai', 'binh duong', 'long an', 'tien giang']
                        },
                        'Indonesia': {
                            patterns: [/\bindonesia\b/, /\bindonesian\b/, /\bid\b/, /\bidn\b/],
                            cities: ['jakarta', 'surabaya', 'bandung', 'medan', 'semarang', 'makassar', 'palembang', 'tangerang', 'depok', 'bekasi', 'yogyakarta', 'bogor'],
                            provinces: ['java', 'sumatra', 'kalimantan', 'sulawesi', 'papua', 'bali', 'west java', 'east java', 'central java', 'jakarta']
                        },
                        'India': {
                            patterns: [/\bindia\b/, /\bindian\b/, /\bin\b/, /\bind\b/],
                            cities: ['mumbai', 'delhi', 'bangalore', 'hyderabad', 'chennai', 'kolkata', 'pune', 'ahmedabad', 'surat', 'jaipur', 'lucknow', 'kanpur'],
                            provinces: ['maharashtra', 'karnataka', 'tamil nadu', 'delhi', 'gujarat', 'west bengal', 'rajasthan', 'uttar pradesh', 'andhra pradesh']
                        },
                        'Taiwan': {
                            patterns: [/\btaiwan\b/, /\btaiwanese\b/, /\broc\b/, /\btw\b/, /\btwn\b/],
                            cities: ['taipei', 'kaohsiung', 'taichung', 'tainan', 'banqiao', 'hsinchu', 'taoyuan', 'keelung', 'chiayi', 'changhua'],
                            provinces: ['taipei', 'new taipei', 'taoyuan', 'taichung', 'tainan', 'kaohsiung']
                        },
                        'United Kingdom': {
                            patterns: [/\bunited\s+kingdom\b/, /\buk\b/, /\bbritain\b/, /\bbritish\b/, /\bgb\b/, /\bgbr\b/],
                            cities: ['london', 'manchester', 'birmingham', 'glasgow', 'liverpool', 'leeds', 'edinburgh'],
                            provinces: ['england', 'scotland', 'wales', 'northern ireland']
                        },
                        'Germany': {
                            patterns: [/\bgermany\b/, /\bgerman\b/, /\bde\b/, /\bdeu\b/],
                            cities: ['berlin', 'munich', 'hamburg', 'frankfurt', 'cologne', 'stuttgart'],
                            provinces: ['bavaria', 'baden-wurttemberg', 'north rhine-westphalia', 'hesse']
                        },
                        'France': {
                            patterns: [/\bfrance\b/, /\bfrench\b/, /\bfr\b/, /\bfra\b/],
                            cities: ['paris', 'marseille', 'lyon', 'toulouse', 'nice', 'nantes'],
                            provinces: ['ile-de-france', 'provence-alpes-cote d\'azur', 'auvergne-rhone-alpes']
                        }
                    };
                    
                    // Step 1: Check direct country patterns (highest priority)
                    for (const [country, rules] of Object.entries(countryRules)) {
                        if (rules.patterns) {
                            for (const pattern of rules.patterns) {
                                if (pattern.test(normalized)) {
                                    return country;
                                }
                            }
                        }
                    }
                    
                    // Step 2: Check cities (medium priority)
                    for (const [country, rules] of Object.entries(countryRules)) {
                        if (rules.cities) {
                            for (const city of rules.cities) {
                                const cityPattern = new RegExp('\\b' + city.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b');
                                if (cityPattern.test(normalized)) {
                                    return country;
                                }
                            }
                        }
                    }
                    
                    // Step 3: Check provinces/states (lower priority)
                    for (const [country, rules] of Object.entries(countryRules)) {
                        if (rules.provinces) {
                            for (const province of rules.provinces) {
                                const provincePattern = new RegExp('\\b' + province.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b');
                                if (provincePattern.test(normalized)) {
                                    return country;
                                }
                            }
                        }
                    }
                    
                    return '';
                }

                function detectPartnerCountryFromOcr(fullText, partnerInstitution, partnerLocationStr) {
                    const fullLower = String(fullText || '').toLowerCase();
                    const partnerLower = String(partnerInstitution || '').toLowerCase();
                    const locLower = String(partnerLocationStr || '').toLowerCase();

                    // 1) Trust country found in partner-tied location string
                    const fromLoc = detectCountryFromText(partnerLocationStr);
                    if (fromLoc) return fromLoc;

                    // 2) Prefer non-PH countries from full text (avoid CPU address dominating)
                    const nonPhChecks = [
                        ['China', ['china', 'fujian', 'zhangzhou']],
                        ['Japan', ['japan']],
                        ['United States', ['united states', 'usa', 'u.s.a']],
                        ['Korea', ['korea']],
                        ['Canada', ['canada']],
                        ['Australia', ['australia']],
                        ['Singapore', ['singapore']],
                        ['Malaysia', ['malaysia']],
                        ['Thailand', ['thailand']],
                        ['Vietnam', ['vietnam']],
                        ['Indonesia', ['indonesia']],
                        ['India', ['india']],
                        ['Taiwan', ['taiwan']],
                    ];
                    for (const [country, needles] of nonPhChecks) {
                        for (const n of needles) {
                            if (fullLower.includes(n)) return country;
                        }
                    }

                    // 3) Philippines only if tied to partner name/location
                    if (locLower.includes('philippine') || partnerLower.includes('philippine') || partnerLower.includes('philippines')) {
                        return 'Philippines';
                    }

                    return '';
                }

                function normalizeLocationToCountry(loc) {
                    const raw = String(loc || '').trim();
                    if (!raw) return raw;
                    
                    // First try: detect country from the full location string
                    const detected = detectCountryFromText(raw);
                    if (detected) return detected;
                    
                    // Second try: if location has commas, check the last segment (common pattern: "City, Country")
                    const parts = raw.split(',').map(p => p.trim()).filter(Boolean);
                    if (parts.length > 1) {
                        // Try last segment first (most common pattern)
                        const last = parts[parts.length - 1];
                        const lastCountry = detectCountryFromText(last);
                        if (lastCountry) return lastCountry;
                        
                        // Also check second-to-last segment (some formats: "City, State, Country")
                        if (parts.length >= 2) {
                            const secondLast = parts[parts.length - 2];
                            const secondLastCountry = detectCountryFromText(secondLast);
                            if (secondLastCountry) return secondLastCountry;
                        }
                    }
                    
                    // No country detected — return empty to avoid filling with cities/regions
                    return '';
                }

                // Helper: parse partner location (second located-at) from OCR text
                function extractPartnerLocationFromText(text) {
                    const raw = String(text || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
                    const locations = [];

                    const locRegex = /([\w\s\.,\-\&()]+?)\s+located\s+at\s+([^\.;\n]+)(?:[\.;\n]|,\s*hereinafter|\s+hereinafter|\s+and\s)/gi;
                    let m;
                    while ((m = locRegex.exec(raw)) !== null) {
                        const loc = (m[2] || '').trim().replace(/\s+/g, ' ').replace(/\s*(hereinafter.*)$/i, '');
                        if (loc) locations.push(loc);
                    }
                    if (locations.length === 0) {
                        const locRegex2 = /located\s+at\s+([^\.;\n]+)(?:[\.;\n]|,\s*hereinafter|\s+hereinafter|\s+and\s)/gi;
                        while ((m = locRegex2.exec(raw)) !== null) {
                            const loc = (m[1] || '').trim().replace(/\s+/g, ' ').replace(/\s*(hereinafter.*)$/i, '');
                            if (loc) locations.push(loc);
                        }
                    }
                    return locations[1] || locations[0] || '';
                }

                try {
                    const ocrFile = await createOcrOptimizedFile(file);
                    const fd = new FormData();
                    fd.append('file', ocrFile);
                    const resp = await fetch(MOU_FIELDS_OCR_URL, { method: 'POST', body: fd });
                    
                    // Check if response is actually JSON
                    const contentType = resp.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        const text = await resp.text();
                        console.error('Server returned non-JSON response:', text.substring(0, 200));
                        throw new Error('Server returned invalid response. Please check server logs.');
                    }
                    
                    const result = await resp.json();

                    if (!result || !result.success) {
                        throw new Error((result && result.error) ? result.error : 'Server OCR failed');
                    }

                    const detected = (result.fields && result.fields.institution) ? String(result.fields.institution).trim() : '';
                    if (!detected) {
                        throw new Error('No institution detected from server OCR');
                    }

                    // Get location with confidence information
                    const locationMeta = result.meta || {};
                    const locationConfidence = locationMeta.location_confidence || 0;
                    const locationSource = locationMeta.location_source || 'unknown';
                    const requiresReview = locationMeta.location_requires_review || false;
                    const confidenceThreshold = 0.5; // Minimum confidence to auto-populate
                    
                    const detectedLocation = 'location' in (result.fields || {}) && result.fields.location
                        ? String(result.fields.location).trim()
                        : '';

                    // Auto-fill only if user hasn't typed anything meaningful yet
                    const current = String(institutionInput.value || '').trim();
                    if (!current && !institutionTouched) {
                        institutionInput.value = detected;
                        setInstitutionHint(`Auto-filled: ${detected}`, 'success');
                    } else {
                        // Don't overwrite user input; still show what we detected
                        setInstitutionHint(`Detected: ${detected} (not applied)`, 'warning');
                    }

                    // Auto-fill location only if confidence is acceptable and field is empty
                    if (locationInput && detectedLocation && !locationTouched && !String(locationInput.value || '').trim()) {
                        if (locationConfidence >= confidenceThreshold && !requiresReview) {
                            locationInput.value = detectedLocation;
                            const sourceLabel = locationSource === 'explicit' ? 'explicitly detected' : 
                                              locationSource === 'inferred' ? 'inferred' : 'detected';
                            setLocationHint(`Auto-filled: ${detectedLocation} (${sourceLabel}, confidence: ${Math.round(locationConfidence * 100)}%)`, 'success');
                        } else {
                            // Low confidence - show hint but don't auto-fill
                            const sourceLabel = locationSource === 'explicit' ? 'explicitly detected' : 
                                              locationSource === 'inferred' ? 'inferred' : 'detected';
                            const confidenceText = locationConfidence > 0 ? ` (confidence: ${Math.round(locationConfidence * 100)}%)` : '';
                            setLocationHint(`Detected: ${detectedLocation}${confidenceText} - Please review (${sourceLabel})`, 'warning');
                        }
                    } else if (locationInput && detectedLocation && locationConfidence >= confidenceThreshold) {
                        // Location detected but field already has value - show hint
                        const sourceLabel = locationSource === 'explicit' ? 'explicitly detected' : 
                                          locationSource === 'inferred' ? 'inferred' : 'detected';
                        setLocationHint(`Detected: ${detectedLocation} (${sourceLabel}, confidence: ${Math.round(locationConfidence * 100)}%) - not applied`, 'info');
                    }

                    // Handle term extraction
                    const termInput = document.getElementById('term');
                    const termMeta = result.meta || {};
                    const termConfidence = termMeta.term_confidence || 0;
                    const termMethod = termMeta.term_method || '';
                    const detectedTerm = 'term' in (result.fields || {}) && result.fields.term
                        ? String(result.fields.term).trim()
                        : '';
                    
                    if (termInput && detectedTerm) {
                        const currentTerm = String(termInput.value || '').trim();
                        
                        // Auto-fill term only if confidence is acceptable and field is empty
                        if (!currentTerm && !termTouched && termConfidence >= confidenceThreshold) {
                            termInput.value = detectedTerm;
                        } else if (currentTerm && termConfidence >= confidenceThreshold) {
                            // Term detected but field already has value - could show hint if needed
                            console.log(`Detected term: ${detectedTerm} (confidence: ${Math.round(termConfidence * 100)}%, method: ${termMethod}) - not applied`);
                        }
                    }
                } catch (e) {
                    console.warn('Institution OCR (server) failed', e);

                    // Fallback: for images, do browser OCR (Tesseract.js) and parse locally
                    if (file && file.type && file.type.startsWith('image/')) {
                        try {
                            const ocrFile = await createOcrOptimizedFile(file);
                            await loadTesseract();
                            const imageUrl = URL.createObjectURL(ocrFile);
                            const { data } = await window.Tesseract.recognize(imageUrl, 'eng', { logger: () => {} });
                            URL.revokeObjectURL(imageUrl);
                            const text = (data && data.text) ? data.text : '';
                            const partner = extractPartnerInstitutionFromText(text);
                            const partnerLocRaw = extractPartnerLocationFromText(text);
                            const countryFromText = detectPartnerCountryFromOcr(text, partner, partnerLocRaw);
                            const partnerLoc = countryFromText || normalizeLocationToCountry(partnerLocRaw);

                            if (partner) {
                                const current = String(institutionInput.value || '').trim();
                                if (!current && !institutionTouched) {
                                    // Don't force uppercase - keep original casing from cleaned name
                                    institutionInput.value = partner;
                                    setInstitutionHint(`Auto-filled: ${partner}`, 'success');
                                } else {
                                    setInstitutionHint(`Detected: ${partner} (not applied)`, 'warning');
                                }
                                // Ensure location is country-only (already normalized, but double-check)
                                const countryOnly = normalizeLocationToCountry(partnerLoc);
                                if (locationInput && countryOnly && !locationTouched && !String(locationInput.value || '').trim()) {
                                    locationInput.value = countryOnly;
                                }
                                return;
                            }
                        } catch (e2) {
                            console.warn('Institution OCR (browser) failed', e2);
                        }
                    }

                    setInstitutionHint('Could not scan institution (please type manually)', 'neutral');
                }
            }
            
            // Function to analyze document type (MOU vs MOA)
            async function analyzeDocumentType(file) {
                try {
                    let detectedType = 'Unknown';

                    // Prefer server-side detection (more reliable than browser-only heuristics/OCR)
                    try {
                        const fd = new FormData();
                        fd.append('file', file);
                        const resp = await fetch(TYPE_DETECT_URL, { method: 'POST', body: fd });
                        const result = await resp.json();
                        if (result && result.success && (result.type === 'MOU' || result.type === 'MOA')) {
                            detectedType = result.type;
                        }
                    } catch (e) {
                        console.warn('Server-side type detection failed, falling back to local analysis', e);
                    }
                    
                    // For PDF files, we'll use text extraction
                    if (detectedType === 'Unknown' && file.type === 'application/pdf') {
                        detectedType = await analyzePDFContent(file);
                    } 
                    // For DOCX files, we'll analyze the filename and basic content
                    else if (detectedType === 'Unknown' && file.type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
                        detectedType = await analyzeDOCXContent(file);
                    }
                    // For Images, run OCR (Tesseract.js)
                    else if (detectedType === 'Unknown' && file.type.startsWith('image/')) {
                        detectedType = await analyzeImageContent(file);
                    }
                    
                    // Update the display
                    updateCategoryDisplay(detectedType);
                    
                } catch (error) {
                    console.error('Error analyzing document:', error);
                    updateCategoryDisplay('Unknown');
                }
            }
            
            // Function to analyze PDF content
            async function analyzePDFContent(file) {
                // For now, we'll use filename analysis as a fallback
                // In a real implementation, you would use a PDF text extraction library
                return analyzeFilename(file.name);
            }
            
            // Function to analyze DOCX content
            async function analyzeDOCXContent(file) {
                // For now, we'll use filename analysis
                // In a real implementation, you would extract text from DOCX
                return analyzeFilename(file.name);
            }
            
            // Lazy-load Tesseract.js
            async function loadTesseract() {
                return new Promise((resolve, reject) => {
                    if (window.Tesseract) return resolve();
                    const script = document.createElement('script');
                    script.src = 'https://unpkg.com/tesseract.js@4.0.2/dist/tesseract.min.js';
                    script.async = true;
                    script.onload = () => resolve();
                    script.onerror = () => reject(new Error('Failed to load Tesseract.js'));
                    document.head.appendChild(script);
                });
            }

            // OCR for images to detect MOU/MOA
            async function analyzeImageContent(file) {
                // 1) Try server-side detection first (more reliable, works even if CDN/worker downloads are blocked)
                try {
                    const formData = new FormData();
                    formData.append('file', file);
                    const resp = await fetch(TYPE_DETECT_URL, { method: 'POST', body: formData });
                    const result = await resp.json();
                    if (result && result.success && (result.type === 'MOU' || result.type === 'MOA')) {
                        return result.type;
                    }
                } catch (e) {
                    console.warn('Server-side type detection failed, falling back to browser OCR', e);
                }

                // 2) Fallback: browser OCR (Tesseract.js)
                try {
                    await loadTesseract();
                    const imageUrl = URL.createObjectURL(file);
                    const { data } = await window.Tesseract.recognize(imageUrl, 'eng', { logger: () => {} });
                    URL.revokeObjectURL(imageUrl);
                    const text = (data && data.text ? data.text : '').toLowerCase();
                    if (!text) return analyzeFilename(file.name);

                    // More tolerant acronym matching ("M O U" / "M O A")
                    const hasMou = text.includes('memorandum of understanding') || /\bm\s*o\s*u\b/.test(text);
                    const hasMoa = text.includes('memorandum of agreement') || /\bm\s*o\s*a\b/.test(text);

                    if (hasMou && !hasMoa) return 'MOU';
                    if (hasMoa && !hasMou) return 'MOA';

                    // Tie-breaker: prefer exact phrases over acronyms
                    if (text.indexOf('memorandum of understanding') !== -1 && text.indexOf('memorandum of agreement') === -1) return 'MOU';
                    if (text.indexOf('memorandum of agreement') !== -1 && text.indexOf('memorandum of understanding') === -1) return 'MOA';
                    return 'Unknown';
                } catch (e) {
                    console.warn('Browser OCR failed, falling back to filename heuristic', e);
                    return analyzeFilename(file.name);
                }
            }
            
            // Function to analyze filename for MOU/MOA indicators
            function analyzeFilename(filename) {
                const lowerFilename = filename.toLowerCase();
                
                // Check for MOU indicators
                const mouKeywords = ['mou', 'memorandum of understanding', 'understanding'];
                const mouScore = mouKeywords.reduce((score, keyword) => {
                    return score + (lowerFilename.includes(keyword) ? 1 : 0);
                }, 0);
                
                // Check for MOA indicators
                const moaKeywords = ['moa', 'memorandum of agreement', 'agreement'];
                const moaScore = moaKeywords.reduce((score, keyword) => {
                    return score + (lowerFilename.includes(keyword) ? 1 : 0);
                }, 0);
                
                // Determine type based on scores
                if (mouScore > moaScore && mouScore > 0) {
                    return 'MOU';
                } else if (moaScore > mouScore && moaScore > 0) {
                    return 'MOA';
                } else {
                    // If no clear indicators, use additional heuristics
                    if (lowerFilename.includes('research') || lowerFilename.includes('collaboration')) {
                        return 'MOU';
                    } else if (lowerFilename.includes('service') || lowerFilename.includes('contract')) {
                        return 'MOA';
                    } else {
                        return 'Unknown';
                    }
                }
            }
            
            // Function to update category display
            function updateCategoryDisplay(detectedType) {
                const categorySelect = document.getElementById('category');

                // Update the select dropdown with full text
                if (categorySelect && detectedType) {
                    if (detectedType === 'MOU') {
                        categorySelect.value = 'MOU (Memorandum of Understanding)';
                    } else if (detectedType === 'MOA') {
                        categorySelect.value = 'MOA (Memorandum of Agreement)';
                    }
                }

                // Store the detected type for saving
                window.detectedDocumentType = detectedType;
            }
            
            // Function to reset category detection
            function resetCategoryDetection() {
                const categorySelect = document.getElementById('category');

                if (categorySelect) categorySelect.value = '';
                window.detectedDocumentType = null;
            }

            // Event listener for file input change
            const dropZone = document.getElementById('mouDropZone');

            function isValidMouUpload(file) {
                if (!file) return false;
                // Validate file size (10MB)
                if (file.size > 10 * 1024 * 1024) return false;

                const allowedTypes = [
                    'application/pdf',
                    'application/msword', // .doc
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
                    'application/x-zip-compressed', // sometimes .docx detected as zip
                    'application/zip',
                    'image/png',
                    'image/jpeg',
                    'image/jpg',
                    'image/webp'
                ];

                const name = String(file.name || '');
                const ext = name.includes('.') ? name.split('.').pop().toLowerCase() : '';
                const allowedExt = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp'];

                return allowedTypes.includes(file.type) || allowedExt.includes(ext);
            }

            function handleSelectedMouFile(file) {
                if (!file) {
                    selectedMouFiles = [];
                    primaryFileIndex = 0;
                    updateSelectedFileDisplay([]);
                    resetCategoryDetection();
                    resetInstitutionDetection();
                    return;
                }

                if (!isValidMouUpload(file)) {
                    alert('Please select a PDF, DOC/DOCX, or an image (PNG, JPG, WEBP) up to 10MB.');
                    if (fileUploadInput) fileUploadInput.value = '';
                    selectedMouFiles = [];
                    primaryFileIndex = 0;
                    updateSelectedFileDisplay([]);
                    resetCategoryDetection();
                    resetInstitutionDetection();
                    return;
                }

                selectedMouFiles = [file];
                primaryFileIndex = 0;
                syncFileInputFromState();
                updateSelectedFileDisplay(selectedMouFiles);
                runAutoFillFromPrimary();
            }

            if (fileUploadInput) {
                fileUploadInput.addEventListener('change', function(event) {
                    const newFiles = Array.from(this.files || []);
                    if (!newFiles.length) {
                        // If no files selected and we have existing files, keep them
                        if (selectedMouFiles.length > 0) {
                            syncFileInputFromState();
                            return;
                        }
                        selectedMouFiles = [];
                        primaryFileIndex = 0;
                        updateSelectedFileDisplay([]);
                        resetCategoryDetection();
                        resetInstitutionDetection();
                        return;
                    }

                    // Validate new files
                    for (const f of newFiles) {
                        if (!isValidMouUpload(f)) {
                            alert('Please select PDF, DOC/DOCX, or image files (PNG, JPG, WEBP) up to 10MB each.');
                            if (fileUploadInput) fileUploadInput.value = '';
                            syncFileInputFromState();
                            return;
                        }
                    }

                    // Check for duplicate files by name
                    const existingNames = new Set(selectedMouFiles.map(f => f.name));
                    const uniqueNewFiles = newFiles.filter(f => !existingNames.has(f.name));
                    
                    if (uniqueNewFiles.length === 0 && newFiles.length > 0) {
                        alert('Some files are already selected. Please select different files.');
                        if (fileUploadInput) fileUploadInput.value = '';
                        syncFileInputFromState();
                        return;
                    }

                    // Append new files to existing ones
                    selectedMouFiles = [...selectedMouFiles, ...uniqueNewFiles];
                    
                    // If this is the first file, set it as primary
                    if (selectedMouFiles.length === uniqueNewFiles.length) {
                        primaryFileIndex = 0;
                    }
                    
                    syncFileInputFromState();
                    updateSelectedFileDisplay(selectedMouFiles);
                    
                    // Only run auto-fill if this is the first file or if user wants to switch primary
                    if (selectedMouFiles.length === uniqueNewFiles.length) {
                        runAutoFillFromPrimary();
                    }
                });
            }

            // Add More Files button event listener
            const addMoreFilesBtn = document.getElementById('addMoreFilesBtn');
            if (addMoreFilesBtn && fileUploadInput) {
                addMoreFilesBtn.addEventListener('click', () => {
                    fileUploadInput.click();
                });
            }

            // Drag & drop support for the upload area
            if (dropZone && fileUploadInput) {
                const dragActiveClasses = ['ring-2', 'ring-primary', 'bg-primary/5', 'border-primary'];

                function setDropZoneActive(active) {
                    dragActiveClasses.forEach(cls => dropZone.classList.toggle(cls, active));
                }

                // Prevent browser from opening the file on drop
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(evt => {
                    dropZone.addEventListener(evt, (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                    });
                });

                dropZone.addEventListener('dragenter', () => setDropZoneActive(true));
                dropZone.addEventListener('dragover', () => setDropZoneActive(true));
                dropZone.addEventListener('dragleave', () => setDropZoneActive(false));
                dropZone.addEventListener('drop', (e) => {
                    setDropZoneActive(false);
                    const newFiles = e.dataTransfer && e.dataTransfer.files ? Array.from(e.dataTransfer.files) : [];
                    if (!newFiles.length) return;

                    // Validate new files
                    for (const f of newFiles) {
                        if (!isValidMouUpload(f)) {
                            alert('Please drop PDF, DOC/DOCX, or image files (PNG, JPG, WEBP) up to 10MB each.');
                            return;
                        }
                    }

                    // Check for duplicate files by name
                    const existingNames = new Set(selectedMouFiles.map(f => f.name));
                    const uniqueNewFiles = newFiles.filter(f => !existingNames.has(f.name));
                    
                    if (uniqueNewFiles.length === 0 && newFiles.length > 0) {
                        alert('Some files are already selected. Please drop different files.');
                        return;
                    }

                    // Append new files to existing ones
                    selectedMouFiles = [...selectedMouFiles, ...uniqueNewFiles];
                    
                    // If this is the first file, set it as primary
                    if (selectedMouFiles.length === uniqueNewFiles.length) {
                        primaryFileIndex = 0;
                    }
                    
                    syncFileInputFromState();
                    updateSelectedFileDisplay(selectedMouFiles);
                    
                    // Only run auto-fill if this is the first file
                    if (selectedMouFiles.length === uniqueNewFiles.length) {
                        runAutoFillFromPrimary();
                    }
                });
            }

            // Global drop guard: when Add modal is open, prevent the browser from navigating to dropped files.
            // This also makes drop work even if the user drops slightly outside the dashed drop zone.
            function isAddModalOpen() {
                return !!(modal && !modal.classList.contains('hidden'));
            }

            function getDroppedFiles(e) {
                const files = e && e.dataTransfer && e.dataTransfer.files ? e.dataTransfer.files : null;
                return files ? Array.from(files) : [];
            }

            document.addEventListener('dragover', function(e) {
                if (!isAddModalOpen()) return;
                e.preventDefault();
            }, true);

            document.addEventListener('drop', function(e) {
                if (!isAddModalOpen()) return;
                e.preventDefault();

                const newFiles = getDroppedFiles(e);
                if (!newFiles.length) return;

                // Validate new files
                for (const f of newFiles) {
                    if (!isValidMouUpload(f)) {
                        alert('Please drop PDF, DOC/DOCX, or image files (PNG, JPG, WEBP) up to 10MB each.');
                        return;
                    }
                }

                // Check for duplicate files by name
                const existingNames = new Set(selectedMouFiles.map(f => f.name));
                const uniqueNewFiles = newFiles.filter(f => !existingNames.has(f.name));
                
                if (uniqueNewFiles.length === 0 && newFiles.length > 0) {
                    alert('Some files are already selected. Please drop different files.');
                    return;
                }

                // Append new files to existing ones
                selectedMouFiles = [...selectedMouFiles, ...uniqueNewFiles];
                
                // If this is the first file, set it as primary
                if (selectedMouFiles.length === uniqueNewFiles.length) {
                    primaryFileIndex = 0;
                }
                
                syncFileInputFromState();
                updateSelectedFileDisplay(selectedMouFiles);
                
                // Only run auto-fill if this is the first file
                if (selectedMouFiles.length === uniqueNewFiles.length) {
                    runAutoFillFromPrimary();
                }
            }, true);

            // Track manual edits and force uppercase so OCR doesn't overwrite user input
            const institutionInput = document.getElementById('institution');
            if (institutionInput) {
                institutionInput.addEventListener('input', function(e) {
                    const cursorPosition = e.target.selectionStart;
                    e.target.value = e.target.value.toUpperCase();
                    e.target.setSelectionRange(cursorPosition, cursorPosition);
                    if (String(e.target.value || '').trim().length > 0) {
                        institutionTouched = true;
                    }
                });

                institutionInput.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pastedText = (e.clipboardData || window.clipboardData).getData('text');
                    const cursorPosition = e.target.selectionStart;
                    const textBefore = e.target.value.substring(0, cursorPosition);
                    const textAfter = e.target.value.substring(e.target.selectionEnd);
                    e.target.value = textBefore + pastedText.toUpperCase() + textAfter;
                    const newCursorPosition = cursorPosition + pastedText.length;
                    e.target.setSelectionRange(newCursorPosition, newCursorPosition);
                    if (String(e.target.value || '').trim().length > 0) {
                        institutionTouched = true;
                    }
                });
            }

            // Track manual edits to term field
            const termInput = document.getElementById('term');
            if (termInput) {
                termInput.addEventListener('input', function(e) {
                    if (String(e.target.value || '').trim().length > 0) {
                        termTouched = true;
                    }
                });

                termInput.addEventListener('paste', function(e) {
                    setTimeout(() => {
                        if (String(e.target.value || '').trim().length > 0) {
                            termTouched = true;
                        }
                    }, 0);
                });
            }

            // Enhanced status determination with "Expires Soon"
            // Uses 180 days as the threshold for "Expires Soon" to match
            // the default MOU/MOA expiration notification window.
            function determineStatus(endDate) {
                if (!endDate) {
                    return 'Auto-determined';
                }
                
                const today = new Date();
                const endDateObj = new Date(endDate);
                
                // Set time to start of day for accurate comparison
                today.setHours(0, 0, 0, 0);
                endDateObj.setHours(0, 0, 0, 0);
                
                // Calculate days until expiry
                const daysUntilExpiry = Math.ceil((endDateObj - today) / (1000 * 60 * 60 * 24));
                
                if (endDateObj < today) {
                    return 'Expired';
                } else if (daysUntilExpiry <= 180) {
                    // Within 180 days: treat as "Expires Soon"
                    return 'Expires Soon';
                } else {
                    return 'Active';
                }
            }
            
            // Function to get status value for saving
            function getStatusValue(endDate) {
                if (!endDate) {
                    return 'Pending'; // Default if no end date provided
                }
                
                const today = new Date();
                const endDateObj = new Date(endDate);
                
                // Set time to start of day for accurate comparison
                today.setHours(0, 0, 0, 0);
                endDateObj.setHours(0, 0, 0, 0);
                
                // Calculate days until expiry
                const daysUntilExpiry = Math.ceil((endDateObj - today) / (1000 * 60 * 60 * 24));
                
                if (endDateObj < today) {
                    return 'Expired';
                } else if (daysUntilExpiry <= 30) {
                    return 'Expires Soon';
                } else {
                    return 'Active';
                }
            }
            
            // Update status when end date changes
            const endDateInput = document.getElementById('end-date');
            const autoStatusText = document.getElementById('autoStatusText');
            
            endDateInput.addEventListener('change', function() {
                const status = determineStatus(this.value);
                autoStatusText.textContent = status;
                
                // Update text color based on status
                if (status === 'Active') {
                    autoStatusText.className = 'text-green-600 dark:text-green-400';
                } else if (status === 'Expired') {
                    autoStatusText.className = 'text-red-600 dark:text-red-400';
                } else if (status === 'Expires Soon') {
                    autoStatusText.className = 'text-yellow-600 dark:text-yellow-400';
                } else {
                    autoStatusText.className = 'text-gray-500 dark:text-gray-400';
                }
            });
            
            // Function to calculate end date based on sign date and term
            function calculateEndDate(signDate, term) {
                if (!signDate || !term) return null;
                
                let number, unit;
                
                // First try to parse with explicit unit (e.g., "5 years", "3 months")
                const termMatch = term.match(/(\d+)\s*(year|years|month|months|day|days)/i);
                
                if (termMatch) {
                    number = parseInt(termMatch[1]);
                    unit = termMatch[2].toLowerCase();
                } else {
                    // If no unit specified, try to parse just a number (assume years)
                    const numberMatch = term.match(/(\d+)/);
                    if (numberMatch) {
                        number = parseInt(numberMatch[1]);
                        unit = 'years'; // Default to years if no unit specified
                    } else {
                        return null;
                    }
                }
                
                const startDate = new Date(signDate);
                if (isNaN(startDate.getTime())) return null;
                
                const endDate = new Date(startDate);
                
                switch (unit) {
                    case 'year':
                    case 'years':
                        endDate.setFullYear(startDate.getFullYear() + number);
                        break;
                    case 'month':
                    case 'months':
                        endDate.setMonth(startDate.getMonth() + number);
                        break;
                    case 'day':
                    case 'days':
                        endDate.setDate(startDate.getDate() + number);
                        break;
                    default:
                        return null;
                }
                
                // Format as YYYY-MM-DD
                return endDate.toISOString().split('T')[0];
            }
            
            // Function to auto-calculate end date when sign date or term changes
            function autoCalculateEndDate() {
                const signDateInput = document.getElementById('sign-date');
                const signDate = signDateInput ? signDateInput.value : '';
                const term = document.getElementById('term').value.trim();
                const endDateInput = document.getElementById('end-date');
                
                if (signDate && term) {
                    const calculatedEndDate = calculateEndDate(signDate, term);
                    if (calculatedEndDate) {
                        endDateInput.value = calculatedEndDate;
                        
                        // Update status display
                        const status = determineStatus(calculatedEndDate);
                        autoStatusText.textContent = status;
                        
                        // Update text color based on status
                        if (status === 'Active') {
                            autoStatusText.className = 'text-green-600 dark:text-green-400';
                        } else if (status === 'Expired') {
                            autoStatusText.className = 'text-red-600 dark:text-red-400';
                        } else if (status === 'Expires Soon') {
                            autoStatusText.className = 'text-yellow-600 dark:text-yellow-400';
                        } else {
                            autoStatusText.className = 'text-gray-500 dark:text-gray-400';
                        }
                        
                        // Notification removed - no message will be shown when end date is calculated
                    }
                }
            }
            
            // Function to show temporary messages
            function showTemporaryMessage(message, type = 'info') {
                const messageDiv = document.createElement('div');
                const bgColor = type === 'success' ? 'bg-green-500' : 'bg-blue-500';
                messageDiv.className = `fixed top-4 right-4 ${bgColor} text-white px-4 py-2 rounded-lg shadow-lg z-50 text-sm`;
                messageDiv.textContent = message;
                document.body.appendChild(messageDiv);
                
                setTimeout(() => {
                    if (document.body.contains(messageDiv)) {
                        document.body.removeChild(messageDiv);
                    }
                }, 2000);
            }
            
            // Add event listeners for auto-calculation
            const signDateInput = document.getElementById('sign-date');
            
            signDateInput.addEventListener('change', autoCalculateEndDate);
            signDateInput.addEventListener('input', autoCalculateEndDate);
            
            if (termInput) {
                termInput.addEventListener('change', autoCalculateEndDate);
                termInput.addEventListener('input', autoCalculateEndDate);
            }
            
            // (Institution uppercase + manual tracking handled above)
            
            // Handle Save button
            if (saveBtn) {
                saveBtn.addEventListener('click', async function() {
                    // Get form data
                    const institution = document.getElementById('institution').value.trim().toUpperCase();
                    const location = document.getElementById('location').value.trim();
                    const contact = document.getElementById('contact').value.trim();
                    const term = document.getElementById('term').value.trim();
                    const category = document.getElementById('category').value || 'Unknown';
                    const signDateInput = document.getElementById('sign-date');
                    const endDateInput = document.getElementById('end-date');
                    const signDate = signDateInput ? signDateInput.value : '';
                    const endDate = endDateInput ? endDateInput.value : '';
                    const fileInputEl = document.getElementById('file-upload');
                    const selectedFiles = fileInputEl && fileInputEl.files ? Array.from(fileInputEl.files) : [];
                    const fileUpload = selectedFiles.length ? (selectedFiles[primaryFileIndex] || selectedFiles[0]) : null;
                    
                    // Validate required fields (contact details is now optional)
                    // Term, End Date, and Status are optional. Require only Institution, Location, and Sign Date.
                    if (!institution || !location || !signDate) {
                        alert('Please fill in all required fields (Institution, Location, and Sign Date).');
                        return;
                    }
                    
                    // Create new MOU/MOA entry
                    let calculatedStatus;
                    try {
                        // Calculate status directly here instead of calling getStatusValue
                        if (!endDate) {
                            calculatedStatus = 'Pending';
                        } else {
                            const today = new Date();
                            const endDateObj = new Date(endDate);
                            
                            // Set time to start of day for accurate comparison
                            today.setHours(0, 0, 0, 0);
                            endDateObj.setHours(0, 0, 0, 0);
                            
                            // Calculate days until expiry
                            const daysUntilExpiry = Math.ceil((endDateObj - today) / (1000 * 60 * 60 * 24));
                            
                            if (endDateObj < today) {
                                calculatedStatus = 'Expired';
                            } else if (daysUntilExpiry <= 30) {
                                calculatedStatus = 'Expires Soon';
                            } else {
                                calculatedStatus = 'Active';
                            }
                        }
                        console.log('Status calculated:', calculatedStatus);
                    } catch (error) {
                        console.error('Error calculating status:', error);
                        calculatedStatus = 'Active'; // Fallback
                    }
                    
                    const newEntry = {
                        institution: institution,
                        location: location,
                        contact_email: contact || null, // Send null if empty string
                        term: term,
                        category: category,
                        sign_date: signDate,
                        end_date: endDate,
                        status: calculatedStatus || 'Active', // Ensure status is never undefined
                        file_name: fileUpload ? fileUpload.name : null,
                        file_path: fileUpload ? `/uploads/${fileUpload.name}` : null
                    };
                    
                    console.log('New Entry:', newEntry);
                    
                    try {
                        // Show loading state
                        saveBtn.disabled = true;
                        saveBtn.textContent = 'Saving...';
                        
                        const fileInput = document.getElementById('file-upload');
                        let result;
                        if (window.editingEntryId) {
                            // Update existing entry
                            newEntry.id = window.editingEntryId;
                            result = await updateEntry(newEntry, fileInput);

                            // Reload all data from database
                            await loadFromDatabase();
                            
                            // Check for notifications after updating (especially if status changed to expired)
                            if (typeof checkNotifications === 'function') {
                                await checkNotifications();
                            }

                            // Reset editing state
                            window.editingEntryId = undefined;
                        } else {
                            // Save new entry to database
                            result = await saveToDatabase(newEntry, fileInput);

                            // Reload all data from database
                            await loadFromDatabase();
                            
                            // Check for notifications after saving (especially for expired MOU)
                            // Add a small delay to ensure database is updated
                            setTimeout(async () => {
                                if (typeof checkNotifications === 'function') {
                                    console.log('Checking notifications after saving new MOU...');
                                    await checkNotifications();
                                }
                                // Also reload notifications to display them immediately
                                if (typeof loadNotifications === 'function') {
                                    await loadNotifications();
                                } else if (window.loadNotifications) {
                                    await window.loadNotifications();
                                }
                                // Update badge
                                if (typeof updateNotificationBadge === 'function') {
                                    await updateNotificationBadge();
                                } else if (window.updateNotificationBadge) {
                                    await window.updateNotificationBadge();
                                }
                            }, 500);
                        }
                        
                        // Reset form
                        resetForm();
                        
                        // Close modal - ensure it closes properly after successful save
                        const modal = document.getElementById('addFileModal');
                        if (modal) {
                            // Force close the modal
                            modal.classList.add('hidden');
                            modal.style.display = 'none';
                            modal.style.visibility = 'hidden';
                            modal.style.opacity = '0';
                            
                            // Also ensure it's closed after a brief delay to handle any async operations
                            setTimeout(() => {
                                if (modal) {
                                    modal.classList.add('hidden');
                                    modal.style.display = 'none';
                                }
                            }, 100);
                        }
                        
                        // Success message removed - no notification will be shown
                        
                    } catch (error) {
                        showErrorMessage('Failed to save data: ' + error.message);
                        // Don't close modal on error so user can fix and retry
                    } finally {
                        // Reset button state
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'Save';
                    }
                });
            }

            // Function to delete entry with custom modal
            function deleteEntry(id) {
                // Store the ID for the confirmation
                window.pendingDeleteId = Number(id);
                
                // Show the delete confirmation modal
                const deleteModal = document.getElementById('deleteConfirmModal');
                deleteModal.classList.remove('hidden');
            }
            
            // Function to confirm delete
            async function confirmDelete() {
                const id = window.pendingDeleteId;
                if (!id) return;

                try {
                    // Delete via API - API only needs id for DELETE method
                    const API_BASE_URL = 'api/mou-moa.php';
                    const response = await fetch(`${API_BASE_URL}?id=${encodeURIComponent(id)}`, {
                        method: 'DELETE'
                    });

                    const result = await response.json();
                    if (!result.success) {
                        throw new Error(result.error || 'Delete failed');
                    }

                    // Reload all data from database
                    await loadFromDatabase();

                    // Remove related notifications (localStorage cleanup)
                    if (typeof removeNotificationsForEntry === 'function') {
                        removeNotificationsForEntry(id);
                    }

                    // Reload notifications from API to reflect deleted notifications
                    if (typeof loadNotifications === 'function') {
                        await loadNotifications();
                    }
                    if (typeof updateNotificationBadge === 'function') {
                        await updateNotificationBadge();
                    }

                    // Close modal
                    const deleteModal = document.getElementById('deleteConfirmModal');
                    deleteModal.classList.add('hidden');

                    // Clear pending delete ID
                    window.pendingDeleteId = null;

                    // Show success message
                    showSuccessMessage('MOU/MOA deleted successfully!');
                } catch (error) {
                    showErrorMessage('Failed to delete entry: ' + error.message);
                }
            }
            
            // Function to cancel delete
            function cancelDelete() {
                // Close modal
                const deleteModal = document.getElementById('deleteConfirmModal');
                deleteModal.classList.add('hidden');
                
                // Clear pending delete ID
                window.pendingDeleteId = null;
            }
            
            // Make functions globally accessible
            window.confirmDelete = confirmDelete;
            window.cancelDelete = cancelDelete;
            
            // Function to reset form
            function resetForm() {
                document.getElementById('institution').value = '';
                document.getElementById('location').value = '';
                document.getElementById('contact').value = '';
                document.getElementById('term').value = '';
                document.getElementById('sign-date').value = '';
                document.getElementById('end-date').value = '';
                document.getElementById('file-upload').value = '';
                autoStatusText.textContent = 'Auto-determined';
                autoStatusText.className = 'text-gray-500 dark:text-gray-400';
                
                // Reset category detection
                resetCategoryDetection();
                
                // Hide selected file display
                const selectedFileDisplay = document.getElementById('selected-file-display');
                if (selectedFileDisplay) {
                    selectedFileDisplay.classList.add('hidden');
                }
            }
            
            // Function to show error message - Move this before loadFromDatabase
            function showErrorMessage(message) {
                const toast = document.createElement('div');
                toast.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
                toast.textContent = message;
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    document.body.removeChild(toast);
                }, 5000);
            }

            // Function to show success message
            function showSuccessMessage(message) {
                const toast = document.createElement('div');
                toast.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
                toast.textContent = message;
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    document.body.removeChild(toast);
                }, 3000);
            }

            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                    modal.classList.add('hidden');
                    // Clear selected file display when closing modal
                    try {
                        if (fileUploadInput) fileUploadInput.value = '';
                        updateSelectedFileDisplay(null);
                        resetCategoryDetection();
                        resetInstitutionDetection();
                        if (typeof resetForm === 'function') resetForm();
                    } catch (_) {}
                }
            });

            // File Viewer Modal Functionality
            const fileViewerModal = document.getElementById('fileViewerModal');
            const closeFileViewer = document.getElementById('closeFileViewer');
            const fileViewerTitle = document.getElementById('fileViewerTitle');
            const fileViewerSubtitle = document.getElementById('fileViewerSubtitle');
            const fileViewerContent = document.getElementById('fileViewerContent');
            const downloadFile = document.getElementById('downloadFile');
            
            let currentFileData = null;
            
            // Function to show file viewer modal
            function showFileViewer(filePath, fileName) {
                if (!filePath) {
                    alert('File path not available');
                    return;
                }
                
                console.log('showFileViewer called with:', { filePath, fileName });
                
                // Normalize file path - database stores paths like "uploads/mou/filename.pdf"
                let normalizedPath = filePath.trim();
                
                // Remove leading slash if present (paths should be relative from root)
                if (normalizedPath.startsWith('/')) {
                    normalizedPath = normalizedPath.substring(1);
                }
                
                // If it's already a full URL, use it directly
                // Otherwise, use the path as-is (should already be "uploads/mou/filename.pdf")
                if (!normalizedPath.startsWith('http://') && !normalizedPath.startsWith('https://')) {
                    // Path from database is already in correct format: "uploads/mou/filename.pdf"
                    // Just clean up any double slashes
                    normalizedPath = normalizedPath.replace(/\/+/g, '/');
                }
                
                console.log('Final file path:', normalizedPath);
                
                // Verify file path format
                if (!normalizedPath || normalizedPath.length === 0) {
                    alert('Invalid file path. Cannot view file.');
                    return;
                }
                
                currentFileData = { path: normalizedPath, name: fileName };
                
                // Update modal title and subtitle
                fileViewerTitle.textContent = fileName || 'View File';
                fileViewerSubtitle.textContent = 'Document preview';
                
                // Clear previous content
                fileViewerContent.innerHTML = '';
                
                // Show loading state
                fileViewerContent.innerHTML = `
                    <div class="w-full h-full flex items-center justify-center" style="min-height: 500px;">
                    <div class="text-center">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
                        <p class="text-gray-500 dark:text-gray-400">Loading file...</p>
                        </div>
                    </div>
                `;
                
                // Show modal
                fileViewerModal.classList.remove('hidden');
                
                // Load file content immediately
                setTimeout(() => {
                    loadFileContent(normalizedPath, fileName);
                }, 100);
            }
            
            // Function to load file content
            function loadFileContent(filePath, fileName) {
                if (!filePath) {
                    fileViewerContent.innerHTML = `
                        <div class="text-center p-8">
                            <span class="material-symbols-outlined text-6xl text-gray-400 mb-4">error</span>
                            <p class="text-gray-500">File path not available</p>
                        </div>
                    `;
                    return;
                }
                
                console.log('Loading file content:', { filePath, fileName });
                
                const fileExtension = fileName ? fileName.split('.').pop().toLowerCase() : '';
                
                // Escape only for display text (not for URLs in src attributes)
                const displayFileName = (fileName || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                
                // Reset content area - remove flex classes that might interfere  
                fileViewerContent.className = 'w-full h-full';
                fileViewerContent.style.display = 'block';
                
                // Handle different file types
                if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'].includes(fileExtension)) {
                    // Show image preview - create img element directly
                    fileViewerContent.innerHTML = '';
                    const imgContainer = document.createElement('div');
                    imgContainer.className = 'w-full h-full flex items-center justify-center p-4 bg-gray-50 dark:bg-gray-900';
                    imgContainer.style.minHeight = '500px';
                    
                    const img = document.createElement('img');
                    img.src = filePath;
                    img.alt = fileName || 'Image';
                    img.className = 'max-w-full max-h-[75vh] object-contain rounded-lg shadow-lg';
                    img.style.display = 'block';
                    
                    img.onload = function() {
                        console.log('✓ Image loaded successfully:', filePath);
                    };
                    
                    img.onerror = function() {
                        console.error('✗ Failed to load image:', filePath);
                        imgContainer.innerHTML = `
                            <div class="text-center p-8">
                                <span class="material-symbols-outlined text-6xl text-red-400 mb-4">error</span>
                                <p class="text-red-500 font-medium mb-2">Failed to load image</p>
                                <p class="text-sm text-gray-400 mb-4">Path: ${filePath}</p>
                                <button onclick="window.open('${filePath}', '_blank')" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                                    Open in New Tab
                                </button>
                        </div>
                    `;
                    };
                    
                    imgContainer.appendChild(img);
                    fileViewerContent.appendChild(imgContainer);
                } else if (fileExtension === 'pdf') {
                    // Show PDF in iframe - create iframe element directly
                    fileViewerContent.innerHTML = '';
                    const iframeContainer = document.createElement('div');
                    iframeContainer.className = 'w-full h-full bg-gray-50 dark:bg-gray-900';
                    iframeContainer.style.minHeight = '600px';
                    
                    const iframe = document.createElement('iframe');
                    iframe.src = filePath;
                    iframe.className = 'w-full h-full min-h-[600px] rounded-lg border border-gray-300 dark:border-gray-700';
                    iframe.frameBorder = '0';
                    iframe.style.display = 'block';
                    iframe.style.width = '100%';
                    iframe.style.height = '75vh';
                    iframe.style.minHeight = '600px';
                    
                    iframe.onload = function() {
                        console.log('✓ PDF loaded successfully:', filePath);
                    };
                    
                    iframe.onerror = function() {
                        console.error('✗ Failed to load PDF:', filePath);
                        iframeContainer.innerHTML = `
                            <div class="text-center p-8">
                                <span class="material-symbols-outlined text-6xl text-red-400 mb-4">error</span>
                                <p class="text-red-500 font-medium mb-2">Failed to load PDF</p>
                                <p class="text-sm text-gray-400 mb-4">Path: ${filePath}</p>
                                <button onclick="window.open('${filePath}', '_blank')" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90">
                                    Open in New Tab
                                </button>
                            </div>
                        `;
                    };
                    
                    iframeContainer.appendChild(iframe);
                    fileViewerContent.appendChild(iframeContainer);
                } else if (['mp3', 'wav', 'ogg', 'webm', 'm4a', 'aac', 'flac'].includes(fileExtension)) {
                    // Show audio player
                    fileViewerContent.innerHTML = '';
                    const audioContainer = document.createElement('div');
                    audioContainer.className = 'w-full h-full flex flex-col items-center justify-center p-8 bg-gray-50 dark:bg-gray-900';
                    audioContainer.style.minHeight = '500px';
                    
                    const audioWrapper = document.createElement('div');
                    audioWrapper.className = 'text-center max-w-md w-full';
                    
                    const iconDiv = document.createElement('div');
                    iconDiv.className = 'w-24 h-24 mx-auto mb-6 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center';
                    
                    const icon = document.createElement('span');
                    icon.className = 'material-symbols-outlined text-5xl text-blue-600 dark:text-blue-400';
                    icon.textContent = 'music_note';
                    iconDiv.appendChild(icon);
                    
                    const title = document.createElement('h4');
                    title.className = 'text-lg font-medium text-gray-900 dark:text-white mb-2';
                    title.textContent = displayFileName || 'Audio File';
                    
                    const playerWrapper = document.createElement('div');
                    playerWrapper.className = 'mt-6 bg-white dark:bg-gray-800 rounded-lg p-4 shadow-lg';
                    
                    const audio = document.createElement('audio');
                    audio.controls = true;
                    audio.className = 'w-full';
                    audio.preload = 'metadata';
                    
                    const source = document.createElement('source');
                    const audioType = fileExtension === 'mp3' ? 'mpeg' : (fileExtension === 'm4a' ? 'mp4' : fileExtension);
                    source.src = filePath;
                    source.type = `audio/${audioType}`;
                    audio.appendChild(source);
                    
                    audio.onloadedmetadata = function() {
                        console.log('✓ Audio loaded successfully:', filePath);
                    };
                    
                    audio.onerror = function() {
                        console.error('✗ Failed to load audio:', filePath);
                        playerWrapper.innerHTML = `
                            <div class="text-center p-4">
                                <span class="material-symbols-outlined text-4xl text-red-400 mb-2">error</span>
                                <p class="text-red-500 text-sm">Failed to load audio file</p>
                                <button onclick="window.open('${filePath}', '_blank')" class="mt-2 px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm">
                                    Open in New Tab
                                </button>
                            </div>
                        `;
                    };
                    
                    playerWrapper.appendChild(audio);
                    
                    const helpText = document.createElement('p');
                    helpText.className = 'text-sm text-gray-500 dark:text-gray-400 mt-4';
                    helpText.textContent = 'Click play to listen to the audio file';
                    
                    audioWrapper.appendChild(iconDiv);
                    audioWrapper.appendChild(title);
                    audioWrapper.appendChild(playerWrapper);
                    audioWrapper.appendChild(helpText);
                    audioContainer.appendChild(audioWrapper);
                    fileViewerContent.appendChild(audioContainer);
                } else if (['doc', 'docx'].includes(fileExtension)) {
                    // For Word documents, show download option with info
                    fileViewerContent.innerHTML = `
                        <div class="w-full h-full flex flex-col items-center justify-center p-8">
                            <div class="text-center max-w-md">
                                <div class="w-24 h-24 mx-auto mb-4 bg-gray-100 dark:bg-gray-800 rounded-lg flex items-center justify-center">
                                    <span class="material-symbols-outlined text-4xl text-gray-400 dark:text-gray-600">description</span>
                                </div>
                                <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-2">${displayFileName || 'Document'}</h4>
                                <p class="text-gray-500 dark:text-gray-400 mb-4">Word documents cannot be previewed in the browser. Please download the file to view it.</p>
                            </div>
                        </div>
                    `;
                } else {
                    // Show generic preview with download option
                    fileViewerContent.innerHTML = `
                        <div class="w-full h-full flex flex-col items-center justify-center p-8">
                            <div class="text-center max-w-md">
                                <div class="w-24 h-24 mx-auto mb-4 bg-gray-100 dark:bg-gray-800 rounded-lg flex items-center justify-center">
                                    <span class="material-symbols-outlined text-4xl text-gray-400 dark:text-gray-600">description</span>
                                </div>
                                <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-2">${displayFileName || 'Document'}</h4>
                                <p class="text-gray-500 dark:text-gray-400 mb-4">Preview not available for this file type. You can download the file using the button below.</p>
                            </div>
                        </div>
                    `;
                }
            }
            
            // Remove complex conversion functions as we use iframe/native support now
            // Legacy functions removed: convertPdfToImage, convertDocxToImage, showImagePreview, showGenericPreview

            
            // Function to download current file
            function downloadCurrentFile() {
                if (currentFileData && currentFileData.path) {
                    // Create a temporary link to download the file
                    const link = document.createElement('a');
                    link.href = currentFileData.path;
                    link.download = currentFileData.name || 'document';
                    link.target = '_blank'; // Open in new tab as fallback
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                } else {
                    alert('File not available for download');
                }
            }
            
            // Close file viewer modal
            function closeFileViewerModal() {
                fileViewerModal.classList.add('hidden');
                currentFileData = null;
            }
            
            // Event listeners for file viewer modal
            if (closeFileViewer) {
                closeFileViewer.addEventListener('click', closeFileViewerModal);
            }
            
            if (downloadFile) {
                downloadFile.addEventListener('click', downloadCurrentFile);
            }
            
            // Close modal when clicking outside
            if (fileViewerModal) {
                fileViewerModal.addEventListener('click', function(e) {
                    if (e.target === fileViewerModal) {
                        closeFileViewerModal();
                    }
                });
            }
            
            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && fileViewerModal && !fileViewerModal.classList.contains('hidden')) {
                    closeFileViewerModal();
                }
            });
            
            // Make downloadCurrentFile and showFileViewer globally accessible
            window.downloadCurrentFile = downloadCurrentFile;
            window.showFileViewer = showFileViewer;
            window.loadFileContent = loadFileContent;

            // API-based Database Integration
            const API_BASE_URL = 'api/mou-moa.php';
            const TYPE_DETECT_URL = 'api/detect-mou-moa-type.php';

            // Function to get all entries from API
            window.getAllEntries = async function() {
                try {
                    const response = await fetch(`${API_BASE_URL}?action=list`);
                    const result = await response.json();
                    if (result.success) {
                        return result.data;
                    } else {
                        console.error('Failed to fetch entries:', result.error);
                        return [];
                    }
                } catch (error) {
                    console.error('Error fetching entries:', error);
                    return [];
                }
            };

            // Function to save entry to API
            async function saveEntry(entry, fileInput) {
                try {
                    const formData = new FormData();
                    formData.append('institution', entry.institution);
                    formData.append('location', entry.location);
                    // Only append contact_email if it has a value (it's optional)
                    if (entry.contact_email && entry.contact_email.trim()) {
                    formData.append('contact_email', entry.contact_email);
                    }
                    formData.append('term', entry.term);
                    formData.append('sign_date', entry.sign_date);
                    formData.append('end_date', entry.end_date);
                    if (entry.title) formData.append('title', entry.title);
                    if (entry.partner) formData.append('partner', entry.partner);
                    if (entry.category) formData.append('category', entry.category);
                    if (entry.description) formData.append('description', entry.description);

                    // Add files if present (support multiple uploads)
                    if (fileInput && fileInput.files && fileInput.files.length) {
                        Array.from(fileInput.files).forEach(f => {
                            formData.append('files[]', f);
                        });
                    }

                    const response = await fetch(API_BASE_URL, {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    if (!result.success) {
                        throw new Error(result.error || 'Failed to save entry');
                    }
                    return result;
                } catch (error) {
                    throw new Error('Failed to save data: ' + error.message);
                }
            }

            // Function to save data to database
            async function saveToDatabase(entry, fileInput) {
                try {
                    const result = await saveEntry(entry, fileInput);
                    return { id: result.id, message: result.message };
                } catch (error) {
                    throw new Error('Failed to save data: ' + error.message);
                }
            }

            // Function to update existing entry
            async function updateEntry(entry, fileInput) {
                try {
                    const formData = new FormData();
                    formData.append('institution', entry.institution);
                    formData.append('location', entry.location);
                    // Only append contact_email if it has a value (it's optional)
                    if (entry.contact_email && entry.contact_email.trim()) {
                    formData.append('contact_email', entry.contact_email);
                    }
                    formData.append('term', entry.term);
                    formData.append('sign_date', entry.sign_date);
                    formData.append('end_date', entry.end_date);
                    if (entry.title) formData.append('title', entry.title);
                    if (entry.partner) formData.append('partner', entry.partner);
                    if (entry.category) formData.append('category', entry.category);
                    if (entry.description) formData.append('description', entry.description);

                    // Add files if present (support multiple uploads)
                    if (fileInput && fileInput.files && fileInput.files.length) {
                        Array.from(fileInput.files).forEach(f => {
                            formData.append('files[]', f);
                        });
                    }

                    // Use POST for FormData (PHP handles POST FormData correctly)
                    const response = await fetch(`${API_BASE_URL}?action=update&id=${entry.id}`, {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    if (!result.success) {
                        throw new Error(result.error || 'Entry not found');
                    }
                    return { id: entry.id, message: result.message };
                } catch (error) {
                    throw new Error('Failed to update data: ' + error.message);
                }
            }
            
            // Function to update table row
            function updateTableRow(entry) {
                const row = document.querySelector(`tr[data-id="${entry.id}"]`);
                if (row) {
                    // Update the row content
                    const cells = row.querySelectorAll('td');
                    if (cells.length >= 9) {
                        cells[1].textContent = entry.institution;
                        cells[2].textContent = entry.location;
                        cells[3].textContent = entry.contact_email;
                        cells[4].textContent = entry.term;
                        cells[5].textContent = entry.sign_date;
                        cells[6].textContent = entry.end_date;
                        
                        // Update status badge
                        let statusBadgeClass = '';
                        if (entry.status === 'Active') {
                            statusBadgeClass = 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300';
                        } else if (entry.status === 'Expired') {
                            statusBadgeClass = 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300';
                        } else if (entry.status === 'Expires Soon') {
                            statusBadgeClass = 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300';
                        } else {
                            statusBadgeClass = 'bg-gray-100 text-gray-800 dark:bg-gray-900/50 dark:text-gray-300';
                        }
                        cells[7].innerHTML = `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusBadgeClass}">${entry.status}</span>`;
                        
                        // Update category
                        cells[8].textContent = entry.category || 'N/A';
                        
                        // Update file link if needed
                        if (cells[9]) {
                            cells[9].innerHTML = entry.file_name ? 
                                `<button class="view-file-btn text-primary hover:text-primary/80 dark:text-primary-400 dark:hover:text-primary-300 flex items-center gap-1 mx-auto" data-file-path="${entry.file_path}" data-file-name="${entry.file_name}" title="View File">
                                    <span class="material-symbols-outlined text-sm">visibility</span>
                                    <span>View</span>
                                </button>` : 
                                `<span class="text-gray-400 dark:text-gray-500 text-sm">No file</span>`;
                        }
                    }
                }
            }
            
            // Function to load data from API
            async function loadFromDatabase() {
                try {
                    // Fetch all entries from API
                    originalEntries = await getAllEntries();
                    allEntries = [...originalEntries]; // Copy to allEntries for filtering

                    // Apply search filter if there's a search query
                    applySearch();

                    currentPage = 1; // Reset to first page
                    displayCurrentPage();
                    updatePaginationDisplay();

                    // Ensure notifications reflect existing entries
                    if (typeof reconcileNotificationsWithEntries === 'function') {
                        try { await reconcileNotificationsWithEntries(); } catch (_) {}
                    }
                    
                    // Add delete event listeners to existing rows
                    document.querySelectorAll('.delete-btn').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const id = parseInt(this.dataset.id);
                            deleteEntry(id);
                        });
                    });

                    // Add view file event listeners to existing rows
                    document.querySelectorAll('.view-file-btn').forEach(btn => {
                        btn.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            const filePath = this.dataset.filePath || this.getAttribute('data-file-path');
                            const fileName = this.dataset.fileName || this.getAttribute('data-file-name');
                            if (filePath && fileName) {
                            showFileViewer(filePath, fileName);
                            } else {
                                console.error('View File button clicked but file path or name is missing', { filePath, fileName });
                                alert('File information is missing. Cannot view file.');
                            }
                        });
                    });
                } catch (error) {
                    console.error('Error loading data:', error);
                    showErrorMessage('Failed to load data from storage');
                }
            }
            
            // Make loadFromDatabase globally accessible for bulk operations
            window.loadFromDatabase = loadFromDatabase;
            
            // Function to apply search filter
            function applySearch() {
                const searchInput = document.getElementById('searchInput');
                const searchQuery = searchInput ? searchInput.value.trim().toLowerCase() : '';
                
                if (!searchQuery) {
                    // No search query, show all entries
                    allEntries = [...originalEntries];
                } else {
                    // Filter entries by institution name (case-insensitive)
                    allEntries = originalEntries.filter(entry => {
                        const institution = (entry.institution || '').toLowerCase();
                        const location = (entry.location || '').toLowerCase();
                        // Search in both institution name and location
                        return institution.includes(searchQuery) || location.includes(searchQuery);
                    });
                }

                // Default ordering: keep everything as-is, but push Expired entries behind other MOUs
                // (so "Expired" is last unless the user explicitly uses the Sort dropdown)
                const nonExpired = [];
                const expired = [];
                allEntries.forEach(e => {
                    if ((e && e.status) === 'Expired') {
                        expired.push(e);
                    } else {
                        nonExpired.push(e);
                    }
                });
                allEntries = nonExpired.concat(expired);
                
                // Reset to first page when search changes
                currentPage = 1;
            }
            
            // Function to display current page entries
            function displayCurrentPage() {
                const tableBody = document.querySelector('tbody');
                tableBody.innerHTML = '';
                
                // Clamp current page to valid range based on total entries
                const totalPages = Math.max(1, Math.ceil(allEntries.length / itemsPerPage));
                if (currentPage > totalPages) currentPage = totalPages;
                if (currentPage < 1) currentPage = 1;
                
                // Show empty state if no entries
                if (allEntries.length === 0) {
                    const emptyRow = document.createElement('tr');
                    emptyRow.innerHTML = `
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center justify-center">
                                <span class="material-symbols-outlined text-6xl text-gray-400 dark:text-gray-500 mb-4">description</span>
                                <p class="text-lg font-medium text-text-muted-light dark:text-text-muted-dark mb-2">No MOUs/MOAs found</p>
                                <p class="text-sm text-text-muted-light dark:text-text-muted-dark">Click "Add File" to upload your first MOU/MOA</p>
                            </div>
                        </td>
                    `;
                    tableBody.appendChild(emptyRow);
                    return;
                }
                
                const startIndex = (currentPage - 1) * itemsPerPage;
                const endIndex = startIndex + itemsPerPage;
                const currentPageEntries = allEntries.slice(startIndex, endIndex);
                
                currentPageEntries.forEach(entry => {
                    addRowToTable(entry);
                });
            }
            
            // Function to update pagination display
            function updatePaginationDisplay() {
                const totalEntries = allEntries.length;
                const totalPages = Math.ceil(totalEntries / itemsPerPage);
                
                // Clamp current page to valid range
                if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
                if (currentPage < 1) currentPage = 1;
                
                // Update pagination text
                const startEntry = totalEntries === 0 ? 0 : (currentPage - 1) * itemsPerPage + 1;
                const endEntry = totalEntries === 0 ? 0 : Math.min(currentPage * itemsPerPage, totalEntries);
                
                document.getElementById('startEntry').textContent = startEntry;
                document.getElementById('endEntry').textContent = endEntry;
                document.getElementById('totalEntries').textContent = totalEntries;
                
                // Generate pagination buttons
                generatePaginationButtons(totalPages);
                
                // Update mobile buttons
                const prevBtnMobile = document.getElementById('prevBtnMobile');
                const nextBtnMobile = document.getElementById('nextBtnMobile');
                
                if (prevBtnMobile && nextBtnMobile) {
                    prevBtnMobile.disabled = currentPage === 1;
                    nextBtnMobile.disabled = currentPage === totalPages;
                }
            }
            
            // Function to generate pagination buttons
            function generatePaginationButtons(totalPages) {
                const paginationNav = document.getElementById('paginationNav');
                if (!paginationNav) return;
                
                let paginationHTML = '';
                
                // Previous button
                paginationHTML += `
                    <button class="pagination-btn relative inline-flex items-center px-2 py-2 rounded-l-md border border-border-light bg-card-light text-sm font-medium text-text-muted-light hover:bg-gray-50 dark:border-border-dark dark:bg-background-dark/50 dark:text-text-muted-dark dark:hover:bg-card-dark disabled:opacity-50 disabled:cursor-not-allowed" data-page="${currentPage - 1}" ${currentPage === 1 ? 'disabled' : ''}>
                        <span class="sr-only">Previous</span>
                        <span class="material-symbols-outlined text-sm">chevron_left</span>
                    </button>
                `;
                
                // Page numbers
                const startPage = Math.max(1, currentPage - 2);
                const endPage = Math.min(totalPages, currentPage + 2);
                
                if (startPage > 1) {
                    paginationHTML += `
                        <button class="pagination-btn bg-card-light dark:bg-background-dark/50 border-border-light dark:border-border-dark text-text-muted-light dark:text-text-muted-dark hover:bg-gray-50 dark:hover:bg-card-dark relative inline-flex items-center px-4 py-2 border text-sm font-medium" data-page="1">1</button>
                    `;
                    if (startPage > 2) {
                        paginationHTML += `<span class="relative inline-flex items-center px-4 py-2 border border-border-light dark:border-border-dark bg-card-light dark:bg-background-dark/50 text-sm font-medium text-text-muted-light dark:text-text-muted-dark">...</span>`;
                    }
                }
                
                for (let i = startPage; i <= endPage; i++) {
                    const isActive = i === currentPage;
                    paginationHTML += `
                        <button class="pagination-btn relative inline-flex items-center px-4 py-2 border text-sm font-medium ${isActive ? 'z-10 bg-primary/10 border-primary text-primary' : 'bg-card-light dark:bg-background-dark/50 border-border-light dark:border-border-dark text-text-muted-light dark:text-text-muted-dark hover:bg-gray-50 dark:hover:bg-card-dark'}" data-page="${i}">${i}</button>
                    `;
                }
                
                if (endPage < totalPages) {
                    if (endPage < totalPages - 1) {
                        paginationHTML += `<span class="relative inline-flex items-center px-4 py-2 border border-border-light dark:border-border-dark bg-card-light dark:bg-background-dark/50 text-sm font-medium text-text-muted-light dark:text-text-muted-dark">...</span>`;
                    }
                    paginationHTML += `
                        <button class="pagination-btn bg-card-light dark:bg-background-dark/50 border-border-light dark:border-border-dark text-text-muted-light dark:text-text-muted-dark hover:bg-gray-50 dark:hover:bg-card-dark relative inline-flex items-center px-4 py-2 border text-sm font-medium" data-page="${totalPages}">${totalPages}</button>
                    `;
                }
                
                // Next button
                paginationHTML += `
                    <button class="pagination-btn relative inline-flex items-center px-2 py-2 rounded-r-md border border-border-light bg-card-light text-sm font-medium text-text-muted-light hover:bg-gray-50 dark:border-border-dark dark:bg-background-dark/50 dark:text-text-muted-dark dark:hover:bg-card-dark disabled:opacity-50 disabled:cursor-not-allowed" data-page="${currentPage + 1}" ${currentPage === totalPages ? 'disabled' : ''}>
                        <span class="sr-only">Next</span>
                        <span class="material-symbols-outlined text-sm">chevron_right</span>
                    </button>
                `;
                
                paginationNav.innerHTML = paginationHTML;
                
                // Add event listeners to pagination buttons
                document.querySelectorAll('.pagination-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const page = parseInt(this.dataset.page);
                        if (page && page !== currentPage && page >= 1 && page <= totalPages) {
                            goToPage(page);
                        }
                    });
                });
            }
            
            // Function to go to a specific page
            function goToPage(page) {
                currentPage = page;
                displayCurrentPage();
                updatePaginationDisplay();
            }
            
            // Update the addRowToTable function to not automatically update pagination
            function addRowToTable(data) {
                const tableBody = document.querySelector('tbody');
                const newRow = document.createElement('tr');

                // Determine status badge class
                let statusBadgeClass = '';
                if (data.status === 'Active') {
                    statusBadgeClass = 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300';
                } else if (data.status === 'Expired') {
                    statusBadgeClass = 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300';
                } else if (data.status === 'Expires Soon') {
                    statusBadgeClass = 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300';
                } else {
                    statusBadgeClass = 'bg-gray-100 text-gray-800 dark:bg-gray-900/50 dark:text-gray-300';
                }

                newRow.setAttribute('data-id', data.id);
                newRow.innerHTML = `
                    <!-- Checkbox column -->
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                        <input type="checkbox" class="row-checkbox w-4 h-4 text-primary bg-gray-100 border-gray-300 rounded focus:ring-primary dark:focus:ring-primary dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600" data-id="${data.id}">
                    </td>
                    <!-- Institution column -->
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-text-light dark:text-text-dark text-left">
                        <div>
                            <div class="font-semibold">${data.institution || 'N/A'}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">${data.location || ''}</div>
                        </div>
                    </td>
                    <!-- Type column -->
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${data.category && data.category.includes('MOU (Memorandum of Understanding)') ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300' : data.category && data.category.includes('MOA (Memorandum of Agreement)') ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-900/50 dark:text-gray-300'}">
                            ${data.category || data.type || 'N/A'}
                        </span>
                    </td>
                    <!-- Sign Date column -->
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-text-muted-light dark:text-text-muted-dark text-center">${data.sign_date || 'N/A'}</td>
                    <!-- End Date column -->
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-text-muted-light dark:text-text-muted-dark text-center">${data.end_date || 'N/A'}</td>
                    <!-- Status column -->
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusBadgeClass}">${data.status || 'N/A'}</span>
                    </td>
                    <!-- Action column (contains View and Delete buttons) -->
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                        <div class="flex items-center justify-center gap-2">
                            ${data.file_name ?
                                `<button type="button" class="view-file-btn text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200" data-file-path="${data.file_path}" data-file-name="${data.file_name}" title="View File">
                                    <img src="assets/images/view.png" alt="View" class="w-4 h-4">
                                </button>` :
                                `<button class="view-file-btn text-gray-400 dark:text-gray-500 cursor-not-allowed" disabled title="No file attached">
                                    <img src="assets/images/view.png" alt="View" class="w-4 h-4 opacity-50">
                                </button>`
                            }
                            <button class="delete-btn text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200" data-id="${data.id}" title="Delete MOU/MOA">
                                <img src="assets/images/trash.png" alt="Delete" class="w-4 h-4">
                            </button>
                        </div>
                    </td>
                `;
                
                tableBody.appendChild(newRow);
                
                // Add checkbox event listener to the new checkbox
                const checkbox = newRow.querySelector('.row-checkbox');
                if (checkbox && typeof window.addCheckboxEventListener === 'function') {
                    window.addCheckboxEventListener(checkbox);
                }
                
                // Add delete event listener to the new button
                const deleteBtn = newRow.querySelector('.delete-btn');
                if (deleteBtn) {
                    deleteBtn.addEventListener('click', function() {
                        deleteEntry(data.id);
                    });
                }

                // Add view file event listener to the new button (only if it exists)
                const viewFileBtn = newRow.querySelector('.view-file-btn');
                if (viewFileBtn && !viewFileBtn.disabled) {
                    viewFileBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const filePath = this.dataset.filePath || this.getAttribute('data-file-path');
                        const fileName = this.dataset.fileName || this.getAttribute('data-file-name');
                        if (filePath && fileName) {
                        showFileViewer(filePath, fileName);
                        } else {
                            console.error('View File button clicked but file path or name is missing', { filePath, fileName });
                            alert('File information is missing. Cannot view file.');
                        }
                    });
                }

                // Add edit event listener to the new button
                const editBtn = newRow.querySelector('.edit-btn');
                if (editBtn) {
                    editBtn.addEventListener('click', function() {
                        console.log('Edit button clicked for ID:', data.id, 'Type:', typeof data.id);
                        editEntry(data.id);
                    });
                }
                
                // Add row click listener for MOU details
                newRow.addEventListener('click', function(e) {
                    // Don't trigger if clicking on buttons, checkboxes, or links
                    if (e.target.tagName === 'BUTTON' || 
                        e.target.tagName === 'INPUT' || 
                        e.target.tagName === 'A' ||
                        e.target.closest('button') ||
                        e.target.closest('input') ||
                        e.target.closest('a')) {
                        return;
                    }
                    
                    // Show MOU details
                    if (window.showMouDetails) {
                        window.showMouDetails(data);
                    }
                });
            }

            // Note: confirmDelete function is defined earlier (line ~1769) and uses API properly
            // Duplicate function removed to prevent override

            // Update the save button handler to refresh pagination
            if (saveBtn) {
                saveBtn.addEventListener('click', async function() {
                    // ... existing save logic ...
                    
                    // After successful save, refresh pagination
                    loadFromDatabase();
                });
            }
            
            // Add mobile pagination event listeners
            const prevBtnMobile = document.getElementById('prevBtnMobile');
            const nextBtnMobile = document.getElementById('nextBtnMobile');
            
            if (prevBtnMobile) {
                prevBtnMobile.addEventListener('click', function() {
                    if (currentPage > 1) {
                        goToPage(currentPage - 1);
                    }
                });
            }
            
            if (nextBtnMobile) {
                nextBtnMobile.addEventListener('click', function() {
                    const totalPages = Math.ceil(allEntries.length / itemsPerPage);
                    if (currentPage < totalPages) {
                        goToPage(currentPage + 1);
                    }
                });
            }
            
            // Load existing data when page loads
            (async () => {
                await loadFromDatabase();
                
                // Check for notifications on page load (especially for expired MOU)
                if (typeof checkNotifications === 'function') {
                    await checkNotifications();
                } else if (typeof loadNotifications === 'function') {
                    await loadNotifications();
                }
                // Ensure badge is updated after loading
                if (typeof updateNotificationBadge === 'function') {
                    await updateNotificationBadge();
                } else if (window.updateNotificationBadge) {
                    await window.updateNotificationBadge();
                }
                
                // Check if we need to open edit mode (from documents page)
                const editMouId = sessionStorage.getItem('editMouId');
                if (editMouId) {
                    // Clear the sessionStorage
                    sessionStorage.removeItem('editMouId');
                    // Wait a bit for data to be fully loaded, then trigger edit
                    setTimeout(() => {
                        if (typeof editEntry === 'function') {
                            editEntry(editMouId);
                        }
                    }, 500);
                }
            })();
            
            
            // Add a manual refresh function for debugging
            window.debugRefreshMOUData = function() {
                console.log('Manually refreshing MOU data...');
                loadFromDatabase();
            };
            
            // Also refresh data when page becomes visible (user navigates back to this page)
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    loadFromDatabase();
                }
            });
            
            // Refresh data when window gains focus (user switches back to this tab)
            window.addEventListener('focus', function() {
                loadFromDatabase();
            });
            
            // Handle search input
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                // Search on input (real-time search)
                searchInput.addEventListener('input', function() {
                    applySearch();
                    displayCurrentPage();
                    updatePaginationDisplay();
                });
                
                // Also search on Enter key
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        applySearch();
                        displayCurrentPage();
                        updatePaginationDisplay();
                    }
                });
            }

            // Function to edit entry
            async function editEntry(id) {
                // Get the entry data from API
                const entries = await getAllEntries();
                // Try both string and number comparison for ID matching
                const entryToEdit = entries.find(entry => entry.id == id || entry.id === id);
                
                if (!entryToEdit) {
                    console.error('Entry not found for ID:', id, 'Available entries:', entries.map(e => ({ id: e.id, type: typeof e.id, institution: e.institution })));
                    console.log('Full entries array:', entries);
                    showErrorMessage('Entry not found. Please refresh the page and try again.');
                    return;
                }
                
                // Store the ID for editing
                window.editingEntryId = id;
                
                // Pre-fill the modal with existing data
                try {
                    const institutionField = document.getElementById('institution');
                    const locationField = document.getElementById('location');
                    const contactField = document.getElementById('contact');
                    const termField = document.getElementById('term');
                    const categoryField = document.getElementById('category');
                    const signDateField = document.getElementById('sign-date');
                    const endDateField = document.getElementById('end-date');
                    
                    if (institutionField) institutionField.value = entryToEdit.institution || '';
                    if (locationField) locationField.value = entryToEdit.location || '';
                    if (contactField) contactField.value = entryToEdit.contact_email || '';
                    if (termField) termField.value = entryToEdit.term || '';
                    if (categoryField) categoryField.value = normalizeCategoryForSelect(entryToEdit.category);

                    // Ensure native date picker click behavior is wired and set values
                    setTimeout(() => {
                        if (typeof initDatePickers === 'function') initDatePickers();
                        if (signDateField) signDateField.value = normalizeDateForInput(entryToEdit.sign_date);
                        if (endDateField) endDateField.value = normalizeDateForInput(entryToEdit.end_date);
                    }, 0);
                    
                    console.log('Form fields populated successfully');
                } catch (error) {
                    console.error('Error populating form fields:', error);
                }
                
                // Set the detected document type for editing
                try {
                    if (entryToEdit.category) {
                        window.detectedDocumentType = entryToEdit.category;
                        // Directly update category display without function dependency
                    } else {
                        // No UI hint needed
                    }
                } catch (error) {
                    console.error('Error setting category display:', error);
                }
                
                // Update status display
                try {
                    const status = typeof determineStatus === 'function' ? determineStatus(entryToEdit.end_date) : 'Active';
                    const autoStatusText = document.getElementById('autoStatusText');
                    if (autoStatusText) {
                        autoStatusText.textContent = status;
                        
                        // Update text color based on status
                        if (status === 'Active') {
                            autoStatusText.className = 'text-green-600 dark:text-green-400';
                        } else if (status === 'Expired') {
                            autoStatusText.className = 'text-red-600 dark:text-red-400';
                        } else if (status === 'Expires Soon') {
                            autoStatusText.className = 'text-yellow-600 dark:text-yellow-400';
                        } else {
                            autoStatusText.className = 'text-gray-500 dark:text-gray-400';
                        }
                    }
                } catch (error) {
                    console.error('Error updating status display:', error);
                }
                
                // Update modal title and button text
                try {
                    const modalTitle = document.querySelector('#addFileModal h3');
                    const saveBtn = document.getElementById('saveBtn');
                    if (modalTitle) modalTitle.textContent = 'Edit MOU/MOA File';
                    if (saveBtn) saveBtn.textContent = 'Update';
                    
                    // Check if details modal is open - if so, keep it open and stack edit modal on top
                    const detailsModal = document.getElementById('mouDetailsModal');
                    const isDetailsModalOpen = detailsModal && !detailsModal.classList.contains('hidden');
                    
                    // Show the edit modal - it will appear on top of details modal if open (higher z-index)
                    const modal = document.getElementById('addFileModal');
                    if (modal) {
                        modal.classList.remove('hidden');
                        // Ensure modal appears on top (z-index already set to z-[100] in HTML)
                        modal.style.zIndex = '100';
                        
                        // If details modal is open, ensure it stays open (don't close it)
                        if (isDetailsModalOpen) {
                            // Keep details modal open behind the edit modal
                            detailsModal.style.zIndex = '50';
                            console.log('Edit modal opened on top of details modal');
                        }
                        
                        console.log('Modal opened successfully');
                    } else {
                        console.error('Modal element not found');
                    }
                } catch (error) {
                    console.error('Error opening modal:', error);
                }
            }

            // Expose a few helpers for other modals/scripts (e.g., Renew modal in details view)
            try {
                window.__mouMoa = window.__mouMoa || {};
                window.__mouMoa.updateEntry = updateEntry;
                window.__mouMoa.loadFromDatabase = loadFromDatabase;
                window.__mouMoa.calculateEndDate = calculateEndDate;
                window.__mouMoa.determineStatus = determineStatus;
                window.__mouMoa.normalizeDateForInput = normalizeDateForInput;
                window.__mouMoa.normalizeCategoryForSelect = normalizeCategoryForSelect;
            } catch (e) {
                console.warn('Failed to expose MOU/MOA helpers', e);
            }
        });
    </script>

    <!-- Sort functionality -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sortBtn = document.getElementById('sortBtn');
            const sortDropdown = document.getElementById('sortDropdown');
            const sortText = document.getElementById('sortText');
            const sortOptions = document.querySelectorAll('.sort-option');
            const clearSortBtn = document.getElementById('clearSort');
            const tableBody = document.querySelector('tbody');
            
            let currentSort = null;
            let currentDirection = null;
            
            // Toggle dropdown
            sortBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                sortDropdown.classList.toggle('hidden');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!sortBtn.contains(e.target) && !sortDropdown.contains(e.target)) {
                    sortDropdown.classList.add('hidden');
                }
            });
            
            // Sort function
            function sortTable(column, direction) {
                const rows = Array.from(tableBody.querySelectorAll('tr'));
                
                rows.sort((a, b) => {
                    let aValue, bValue;
                    
                    switch(column) {
                        case 'institution':
                            aValue = a.cells[0].textContent.trim();
                            bValue = b.cells[0].textContent.trim();
                            break;
                        case 'location':
                            aValue = a.cells[1].textContent.trim();
                            bValue = b.cells[1].textContent.trim();
                            break;
                        case 'signDate':
                            aValue = new Date(a.cells[4].textContent.trim());
                            bValue = new Date(b.cells[4].textContent.trim());
                            break;
                        case 'endDate':
                            aValue = new Date(a.cells[5].textContent.trim());
                            bValue = new Date(b.cells[5].textContent.trim());
                            break;
                        case 'status':
                            aValue = a.cells[6].textContent.trim(); // Status column (moved from position 7 to 6)
                            bValue = b.cells[6].textContent.trim(); // Status column (moved from position 7 to 6)
                            break;
                        default:
                            return 0;
                    }
                    
                    if (column === 'signDate' || column === 'endDate') {
                        return direction === 'asc' ? aValue - bValue : bValue - aValue;
                    } else {
                        if (direction === 'asc') {
                            return aValue.localeCompare(bValue);
                        } else {
                            return bValue.localeCompare(aValue);
                        }
                    }
                });
                
                // Clear table body and re-append sorted rows
                tableBody.innerHTML = '';
                rows.forEach(row => tableBody.appendChild(row));
                
                // Update current sort
                currentSort = column;
                currentDirection = direction;
                
                // Update UI
                updateSortUI();
            }
            
            // Update sort UI indicators
            function updateSortUI() {
                // Clear all indicators
                document.querySelectorAll('.sort-indicator').forEach(indicator => {
                    indicator.classList.add('hidden');
                });
                
                // Show current sort indicator
                if (currentSort && currentDirection) {
                    const activeOption = document.querySelector(`[data-sort="${currentSort}"][data-direction="${currentDirection}"]`);
                    if (activeOption) {
                        activeOption.querySelector('.sort-indicator').classList.remove('hidden');
                    }
                }
                
                // Update sort button text (without arrows)
                if (currentSort && currentDirection) {
                    const sortLabels = {
                        'institution': 'Institution',
                        'location': 'Location', 
                        'signDate': 'Sign Date',
                        'endDate': 'End Date',
                        'status': 'Status'
                    };
                    sortText.textContent = `${sortLabels[currentSort]}`;
                } else {
                    sortText.textContent = 'Sort';
                }
            }
            
            // Handle sort option clicks
            sortOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const column = this.dataset.sort;
                    const direction = this.dataset.direction;
                    sortTable(column, direction);
                    sortDropdown.classList.add('hidden');
                });
            });
            
            // Handle clear sort
            clearSortBtn.addEventListener('click', function() {
                // Reset to original order (you might want to store original order)
                location.reload(); // Simple way to reset, or implement original order storage
                sortDropdown.classList.add('hidden');
            });
        });
    </script>

    <!-- Filter functionality -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterBtn = document.getElementById('filterBtn');
            const filterDropdown = document.getElementById('filterDropdown');
            const filterOptions = document.querySelectorAll('.filter-option');
            const clearFilterBtn = document.getElementById('clearFilter');
            const institutionFilter = document.getElementById('institutionFilter');
            const signDateFrom = document.getElementById('signDateFrom');
            const signDateTo = document.getElementById('signDateTo');
            const endDateFrom = document.getElementById('endDateFrom');
            const endDateTo = document.getElementById('endDateTo');
            const filterText = document.getElementById('filterText');
            
            // Toggle dropdown
            filterBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                filterDropdown.classList.toggle('hidden');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!filterBtn.contains(e.target) && !filterDropdown.contains(e.target)) {
                    filterDropdown.classList.add('hidden');
                }
            });
            
            // Advanced filter functionality
            let currentFilter = { 
                status: 'all', 
                institution: '',
                signDateFrom: '',
                signDateTo: '',
                endDateFrom: '',
                endDateTo: '',
                term: 'all'
            };
            
            // Function to parse term duration
            function parseTermDuration(termText) {
                if (!termText) return 'unknown';
                
                const term = termText.toLowerCase();
                const yearMatch = term.match(/(\d+)\s*year/i);
                const monthMatch = term.match(/(\d+)\s*month/i);
                const dayMatch = term.match(/(\d+)\s*day/i);
                
                if (yearMatch) {
                    const years = parseInt(yearMatch[1]);
                    if (years <= 1) return 'short';
                    if (years <= 3) return 'medium';
                    return 'long';
                } else if (monthMatch) {
                    const months = parseInt(monthMatch[1]);
                    if (months <= 12) return 'short';
                    return 'medium';
                } else if (dayMatch) {
                    return 'short';
                }
                
                return 'unknown';
            }
            
            // Function to compare dates
            function isDateInRange(dateStr, fromDate, toDate) {
                if (!dateStr) return false;
                const date = new Date(dateStr);
                const from = fromDate ? new Date(fromDate) : null;
                const to = toDate ? new Date(toDate) : null;
                
                if (from && date < from) return false;
                if (to && date > to) return false;
                return true;
            }
            
            // Function to apply filters
            function applyFilters() {
                const tableBody = document.querySelector('tbody');
                const rows = Array.from(tableBody.querySelectorAll('tr'));

                rows.forEach(row => {
                    const hasCheckbox = !!row.querySelector('.row-checkbox');
                    const columnIndex = hasCheckbox ? {
                        institution: 1,
                        signDate: 3,
                        endDate: 4,
                        status: 5
                    } : {
                        institution: 0,
                        signDate: 2,
                        endDate: 3,
                        status: 4
                    };
                    let showRow = true;

                    // Skip if row doesn't have enough cells
                    if (!row.cells || row.cells.length === 0) {
                        return;
                    }

                    // Filter by status
                    if (currentFilter.status !== 'all') {
                        const statusCell = row.cells[columnIndex.status]; // Status column
                        if (!statusCell) return;
                        const statusSpan = statusCell.querySelector('span');
                        const statusText = statusSpan ? statusSpan.textContent.trim() : statusCell.textContent.trim();
                        if (statusText !== currentFilter.status) {
                            showRow = false;
                        }
                    }

                    // Filter by institution
                    if (currentFilter.institution) {
                        const institutionCell = row.cells[columnIndex.institution]; // Institution column
                        if (!institutionCell) return;
                        const institutionText = institutionCell.textContent.trim().toLowerCase();
                        if (!institutionText.includes(currentFilter.institution.toLowerCase())) {
                            showRow = false;
                        }
                    }

                    // Filter by sign date range
                    if (currentFilter.signDateFrom || currentFilter.signDateTo) {
                        const signDateCell = row.cells[columnIndex.signDate]; // Sign Date column
                        if (!signDateCell) return;
                        const signDateText = signDateCell.textContent.trim();
                        if (!isDateInRange(signDateText, currentFilter.signDateFrom, currentFilter.signDateTo)) {
                            showRow = false;
                        }
                    }

                    // Filter by end date range
                    if (currentFilter.endDateFrom || currentFilter.endDateTo) {
                        const endDateCell = row.cells[columnIndex.endDate]; // End Date column
                        if (!endDateCell) return;
                        const endDateText = endDateCell.textContent.trim();
                        if (!isDateInRange(endDateText, currentFilter.endDateFrom, currentFilter.endDateTo)) {
                            showRow = false;
                        }
                    }

                    // Note: Term column was removed from table, so term filter is disabled
                    // Filter by term duration - DISABLED (column removed)
                    // if (currentFilter.term !== 'all') {
                    //     // Term column no longer exists in simplified table
                    // }

                    // Show/hide row
                    row.style.display = showRow ? '' : 'none';
                });
            }
            
            // Function to update filter UI
            function updateFilterUI() {
                // Clear all indicators
                document.querySelectorAll('.filter-indicator').forEach(indicator => {
                    indicator.classList.add('hidden');
                });
                
                // Show current filter indicators
                Object.keys(currentFilter).forEach(filterType => {
                    if (filterType === 'status' || filterType === 'term') {
                        const value = currentFilter[filterType];
                        if (value !== 'all') {
                            const activeOption = document.querySelector(`[data-filter="${filterType}"][data-value="${value}"]`);
                    if (activeOption) {
                        activeOption.querySelector('.filter-indicator').classList.remove('hidden');
                    }
                } else {
                            const allOption = document.querySelector(`[data-filter="${filterType}"][data-value="all"]`);
                    if (allOption) {
                        allOption.querySelector('.filter-indicator').classList.remove('hidden');
                    }
                }
                    }
                });
                
                // Update filter button text
                const activeFilters = [];
                if (currentFilter.status !== 'all') activeFilters.push(currentFilter.status);
                if (currentFilter.institution) activeFilters.push('Institution');
                if (currentFilter.signDateFrom || currentFilter.signDateTo) activeFilters.push('Sign Date');
                if (currentFilter.endDateFrom || currentFilter.endDateTo) activeFilters.push('End Date');
                if (currentFilter.term !== 'all') activeFilters.push('Term');
                
                if (activeFilters.length > 0) {
                    filterText.textContent = `Filter (${activeFilters.length})`;
                } else {
                    filterText.textContent = 'Filter';
                }
            }
            
            // Handle filter option clicks
            filterOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const filter = this.dataset.filter;
                    const value = this.dataset.value;
                    
                    if (filter === 'status') {
                        currentFilter.status = value;
                    } else if (filter === 'term') {
                        currentFilter.term = value;
                    }
                    
                    applyFilters();
                    updateFilterUI();
                    filterDropdown.classList.add('hidden');
                });
            });
            
            // Handle text input filters
            institutionFilter.addEventListener('input', function() {
                currentFilter.institution = this.value;
                applyFilters();
                updateFilterUI();
            });
            
            // Handle date range filters
            signDateFrom.addEventListener('change', function() {
                currentFilter.signDateFrom = this.value;
                applyFilters();
                updateFilterUI();
            });
            
            signDateTo.addEventListener('change', function() {
                currentFilter.signDateTo = this.value;
                applyFilters();
                updateFilterUI();
            });
            
            endDateFrom.addEventListener('change', function() {
                currentFilter.endDateFrom = this.value;
                applyFilters();
                updateFilterUI();
            });
            
            endDateTo.addEventListener('change', function() {
                currentFilter.endDateTo = this.value;
                applyFilters();
                updateFilterUI();
            });
            
            // Handle clear filter
            clearFilterBtn.addEventListener('click', function() {
                currentFilter = { 
                    status: 'all', 
                    institution: '',
                    signDateFrom: '',
                    signDateTo: '',
                    endDateFrom: '',
                    endDateTo: '',
                    term: 'all'
                };
                
                // Clear all input fields
                institutionFilter.value = '';
                signDateFrom.value = '';
                signDateTo.value = '';
                endDateFrom.value = '';
                endDateTo.value = '';
                
                applyFilters();
                updateFilterUI();
                filterDropdown.classList.add('hidden');
            });
            
            // Initialize filter UI
            updateFilterUI();
        });
    </script>

    <!-- Delete Confirmation Modal Event Listeners -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const deleteConfirmModal = document.getElementById('deleteConfirmModal');
            const cancelDeleteBtn = document.getElementById('cancelDelete');
            const confirmDeleteBtn = document.getElementById('confirmDelete');
            
            // Event listeners for delete confirmation modal
            if (cancelDeleteBtn) {
                cancelDeleteBtn.addEventListener('click', function() {
                    if (window.cancelDelete) {
                        window.cancelDelete();
                    }
                });
            }
            
            if (confirmDeleteBtn) {
                confirmDeleteBtn.addEventListener('click', function() {
                    if (window.confirmDelete) {
                        window.confirmDelete();
                    }
                });
            }
            
            // Close modal when clicking outside
            if (deleteConfirmModal) {
                deleteConfirmModal.addEventListener('click', function(e) {
                    if (e.target === deleteConfirmModal) {
                        if (window.cancelDelete) {
                            window.cancelDelete();
                        }
                    }
                });
            }
            
            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && deleteConfirmModal && !deleteConfirmModal.classList.contains('hidden')) {
                    if (window.cancelDelete) {
                        window.cancelDelete();
                    }
                }
            });
        });
    </script>

    <!-- Notification System -->
    <script>
        // Define MOU renewal handlers globally BEFORE the notification system
        // Handle Renew button - opens MOU/MOA modal
        window.handleRenewMou = function(notificationId, entryId) {
            console.log('handleRenewMou called with:', { notificationId, entryId });
            
            if (!entryId) {
                console.error('No entry ID provided');
                alert('Error: No entry ID found');
                return;
            }
            
            // Open the modal directly if we're on the mou-moa page
            if (typeof window.showMouDetails === 'function') {
                console.log('Opening modal directly on mou-moa page');
                (async function() {
                    try {
                        const response = await fetch(`api/mou-moa.php?action=get&id=${entryId}`);
                        const result = await response.json();
                        
                        if (result.success && result.data) {
                            console.log('Entry data loaded, opening modal');
                            window.showMouDetails(result.data);
                            
                            // Ensure modal is visible
                            const modal = document.getElementById('mouDetailsModal');
                            if (modal) {
                                modal.classList.remove('hidden');
                                modal.style.display = 'flex';
                                console.log('Modal should be visible now');
                            } else {
                                console.error('Modal element not found');
                            }
                        } else {
                            console.error('Failed to load entry:', result.error);
                            alert('Failed to load MOU/MOA details: ' + (result.error || 'Unknown error'));
                        }
                    } catch (error) {
                        console.error('Error loading entry:', error);
                        // Fallback: navigate to URL
                        window.location.href = `mou-moa.php?entry=${entryId}`;
                    }
                })();
            } else {
                console.log('showMouDetails not available, navigating to page');
                // Navigate to mou-moa page with entry parameter to open modal
                window.location.href = `mou-moa.php?entry=${entryId}`;
            }
        };
        
        // Handle Renewed button - marks as renewed and removes notification
        window.handleMouRenewed = async function(notificationId) {
            console.log('handleMouRenewed called with notificationId:', notificationId);
            
            if (!notificationId) {
                console.error('No notification ID provided');
                alert('Error: No notification ID found');
                return;
            }
            
            try {
                console.log('Sending renewal confirmation request...');
                const response = await fetch('api/notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'confirm_mou_renewal',
                        notification_id: notificationId,
                        renewal_status: 'renewed'
                    })
                });
                
                console.log('Response status:', response.status);
                const data = await response.json();
                console.log('Response data:', data);
                
                if (data.success) {
                    console.log('Successfully marked as renewed');
                    // Clean up localStorage when notification is renewed
                    if (notificationId) {
                        removeMouBarSeenId(notificationId);
                    }
                    // Reload notifications to reflect the confirmation
                    if (window.loadNotifications) {
                        await window.loadNotifications();
                    }
                    if (window.updateNotificationBadge) {
                        await window.updateNotificationBadge();
                    }
                    
                    // Show success message
                    if (typeof showToast === 'function') {
                        showToast('MOU/MOA marked as renewed. Notification removed.', 'success');
                    } else {
                        alert('MOU/MOA marked as renewed. Notification removed.');
                    }
                    
                    // Reload the page to refresh notifications if functions aren't available
                    if (!window.loadNotifications) {
                        setTimeout(() => location.reload(), 1000);
                    }
                } else {
                    console.error('Failed to confirm MOU renewal:', data.error);
                    alert('Failed to mark as renewed: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error confirming MOU renewal:', error);
                alert('Error marking as renewed: ' + error.message);
            }
        };
    </script>
    <script>
        // Setup wrapper function for notification sound (compatibility)
        (function() {
            // Wait for NotificationSound to be available
            function setupSoundWrapper() {
                if (window.NotificationSound && !window.checkAndPlayMouNotificationSound) {
                    window.checkAndPlayMouNotificationSound = function(notifications) {
                        if (window.NotificationSound && window.NotificationSound.checkAndPlay) {
                            window.NotificationSound.checkAndPlay(notifications);
                        }
                    };
                } else if (!window.NotificationSound) {
                    // Retry after a short delay if NotificationSound isn't loaded yet
                    setTimeout(setupSoundWrapper, 100);
                }
            }
            setupSoundWrapper();
        })();
        
        document.addEventListener('DOMContentLoaded', function() {
            const notificationBtn = document.getElementById('notificationBtn');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const notificationBadge = document.getElementById('notificationBadge');
            const notificationList = document.getElementById('notificationList');
            const noNotifications = document.getElementById('noNotifications');
            const markAllReadBtn = document.getElementById('markAllReadBtn');
            const viewAllNotifications = document.getElementById('viewAllNotifications');
            
            if (!notificationBtn || !notificationDropdown) return; // Exit if elements don't exist

            // Toggle dropdown - Always refresh notifications when opening to ensure sync across all pages
            notificationBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                notificationDropdown.classList.toggle('hidden');
                if (!notificationDropdown.classList.contains('hidden')) {
                    // Always reload notifications from API to ensure we have the latest from all pages
                    loadNotifications();
                }
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!notificationBtn.contains(e.target) && !notificationDropdown.contains(e.target)) {
                    notificationDropdown.classList.add('hidden');
                }
            });
            
            // Mark all notifications as read
            markAllReadBtn.addEventListener('click', function() {
                markAllNotificationsAsRead();
            });
            
            // View all notifications - open modal
            if (viewAllNotifications && !viewAllNotifications.dataset.listenerAttached) {
                viewAllNotifications.dataset.listenerAttached = 'true';
                viewAllNotifications.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    if (notificationDropdown) {
                        notificationDropdown.classList.add('hidden');
                    }
                    // Use existing modal function
                    if (typeof showAllNotificationsModal === 'function') {
                        showAllNotificationsModal();
                    }
                }, true); // Use capture phase to ensure it runs first
            }
            
            // Use API-based notification system instead of localStorage
            let notifications = [];
            
            if (notificationList) {
                notificationList.addEventListener('click', handleNotificationListClick);
                notificationList.addEventListener('keydown', handleNotificationListKeydown);
            }
            
            // Get current notifications array
            function getNotifications() {
                return notifications;
            }
            
            // Get notification by ID
            function getNotificationById(id) {
                return notifications.find(n => n.id == id);
            }
            
            // Check for new notifications and create them
            async function checkNotifications() {
                try {
                    const response = await fetch('api/notifications.php?action=check');
                    
                    // Check if response is OK
                    if (!response.ok) {
                        const text = await response.text();
                        console.error('Error checking notifications: HTTP ' + response.status, text);
                        return;
                    }
                    
                    // Check if response is JSON
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        const text = await response.text();
                        console.error('Error checking notifications: Expected JSON but got:', text.substring(0, 200));
                        return;
                    }
                    
                    const data = await response.json();
                    if (data.success) {
                        console.log('Notifications checked:', data);
                        // Reload notifications after check to update badge and dropdown
                        await loadNotifications();
                        await updateNotificationBadge();
                    }
                } catch (error) {
                    console.error('Error checking notifications:', error);
                }
            }
            
            // Load notifications from API
            let hasPlayedInitialNotificationSound = false;
            async function loadNotifications() {
                try {
                    const response = await fetch('api/notifications.php');
                    const data = await response.json();
                    if (data.notifications) {
                        console.log('Loaded notifications from API:', data.notifications.length, 'total');
                        console.log('Notification types:', data.notifications.map(n => ({ type: n.type, related_type: n.related_type, title: n.title })));
                        
                        const previousNotificationIds = new Set(notifications.map(n => n.id));
                        
                        // Deduplicate MOU notifications: keep only the most recent one for each MOU+type combination
                        const mouNotificationMap = new Map();
                        const otherNotifications = [];
                        
                        data.notifications.forEach(notif => {
                            if (notif.related_type === 'mou_moa' && notif.related_id) {
                                // Create a unique key for MOU notifications: related_id + type
                                const key = `${notif.related_id}_${notif.type}`;
                                
                                if (!mouNotificationMap.has(key)) {
                                    mouNotificationMap.set(key, notif);
                                } else {
                                    // Keep the most recent one
                                    const existing = mouNotificationMap.get(key);
                                    const existingDate = new Date(existing.created_at);
                                    const currentDate = new Date(notif.created_at);
                                    
                                    if (currentDate > existingDate) {
                                        mouNotificationMap.set(key, notif);
                                    }
                                }
                            } else {
                                // Keep ALL non-MOU notifications as-is (schedules, events, etc.)
                                // This ensures all notifications from all pages are shown
                                otherNotifications.push(notif);
                            }
                        });
                        
                        // Combine deduplicated MOU notifications with ALL other notifications
                        // This ensures schedules, events, and all other notification types are included
                        notifications = [...Array.from(mouNotificationMap.values()), ...otherNotifications];
                        
                        console.log('After processing - MOU notifications:', mouNotificationMap.size, 'Other notifications:', otherNotifications.length);
                        console.log('Total notifications to display:', notifications.length);
                        
                        // Sort by created_at (most recent first)
                        notifications.sort((a, b) => {
                            const dateA = new Date(a.created_at);
                            const dateB = new Date(b.created_at);
                            return dateB - dateA;
                        });
                        
                        updateNotificationDisplay();
                        updateNotificationBadge();
                        
                        // Show notification bars for expired MOU notifications
                        const newNotifications = notifications.filter(n => !previousNotificationIds.has(n.id));
                        const expiredMouNotifications = newNotifications.filter(n =>
                            n.type === 'mou_expired' &&
                            n.related_type === 'mou_moa'
                        );
                        
                        // Play sound for new MOU/MOA notifications (including expired)
                        // Only pass new notifications to avoid re-playing sounds for existing notifications.
                        // Also skip sound on very first page load to avoid a beep when simply opening the page.
                        if (hasPlayedInitialNotificationSound && newNotifications.length > 0) {
                            if (window.checkAndPlayMouNotificationSound) {
                                window.checkAndPlayMouNotificationSound(newNotifications);
                            } else if (window.NotificationSound && window.NotificationSound.checkAndPlay) {
                                // Fallback to direct call if wrapper not available
                                window.NotificationSound.checkAndPlay(newNotifications);
                            }
                        }
                        
                        expiredMouNotifications.forEach(notif => {
                            if (typeof showMouNotificationBar === 'function') {
                                showMouNotificationBar(notif);
                            }
                        });
                        
                        // On first load, show all expired MOU notifications (no sound on first open)
                        if (previousNotificationIds.size === 0) {
                            const allExpiredMouNotifications = notifications.filter(n =>
                                n.type === 'mou_expired' &&
                                n.related_type === 'mou_moa'
                            );
                            
                            // Play sound for initial expired MOU notifications
                            if (allExpiredMouNotifications.length > 0) {
                                // We no longer auto-play sound on first page load to avoid
                                // beeping every time the user opens the page.
                            }
                            
                            allExpiredMouNotifications.forEach((notif) => {
                                if (notif.id != null && typeof removeMouBarSeenId === 'function') {
                                    removeMouBarSeenId(notif.id);
                                }
                                if (typeof showMouNotificationBar === 'function') {
                                    showMouNotificationBar(notif);
                                }
                            });
                        }
                        
                        // Mark that we've handled the first load
                        hasPlayedInitialNotificationSound = true;
                    }
                } catch (error) {
                    console.error('Error loading notifications:', error);
                }
            }
            
            // Get unread count
            async function updateNotificationBadge() {
                try {
                    // Get badge element dynamically to avoid scope issues
                    const badge = document.getElementById('notificationBadge');
                    
                    const enabled = window.areNotificationsEnabled ? await window.areNotificationsEnabled() : true;
                    if (!enabled) {
                        if (badge) {
                            badge.classList.add('hidden');
                        }
                        return;
                    }

                    const response = await fetch('api/notifications.php?action=count');
                    if (!response.ok) {
                        console.error('Failed to get notification count:', response.status, response.statusText);
                        return;
                    }
                    const data = await response.json();
                    const count = data.count || 0;
                    
                    if (badge) {
                        if (count > 0) {
                            badge.textContent = count > 99 ? '99+' : count;
                            badge.classList.remove('hidden');
                            console.log('Notification badge updated:', count, 'unread notifications');
                        } else {
                            badge.classList.add('hidden');
                            console.log('Notification badge hidden: no unread notifications');
                        }
                    } else {
                        console.warn('Notification badge element not found');
                    }
                } catch (error) {
                    console.error('Error updating notification badge:', error);
                }
            }
            
            // Update notification display - EXACT COPY FROM documents.php
            function updateNotificationDisplay() {
                if (!notificationList) return;
                
                if (notifications.length === 0) {
                    if (noNotifications) {
                        noNotifications.classList.remove('hidden');
                    }
                    notificationList.innerHTML = '';
                    return;
                }
                
                if (noNotifications) {
                    noNotifications.classList.add('hidden');
                }
                
                console.log('Updating notification display with', notifications.length, 'notifications');
                console.log('Notification breakdown:', {
                    mou: notifications.filter(n => n.related_type === 'mou_moa').length,
                    schedule: notifications.filter(n => n.related_type === 'schedule').length,
                    event: notifications.filter(n => n.related_type === 'event').length,
                    other: notifications.filter(n => !n.related_type || (n.related_type !== 'mou_moa' && n.related_type !== 'schedule' && n.related_type !== 'event')).length
                });
                
                // Display ALL notifications from ALL pages - no filtering
                // This ensures MOU, schedule, event, and all other notification types are shown
                notificationList.innerHTML = notifications.map(notif => {
                    const timeAgo = getTimeAgo(notif.created_at);
                    const icon = getNotificationIcon(notif.type);
                    const bgColor = getNotificationBgColor(notif.type);
                    const targetUrl = getNotificationUrl(notif);
                    const urlAttribute = targetUrl ? ` data-url="${encodeURIComponent(targetUrl)}"` : '';
                    const relatedTypeAttr = notif.related_type ? ` data-related-type="${notif.related_type}"` : '';
                    const relatedIdAttr = notif.related_id ? ` data-related-id="${notif.related_id}"` : '';
                    const isMouNotification = notif.related_type === 'mou_moa';
                    const isConfirmed = notif.is_confirmed || false;
                    
                    // Show confirmation buttons for MOU notifications that haven't been confirmed (same as dashboard)
                    let confirmationButtons = '';
                    if (isMouNotification && !isConfirmed) {
                        confirmationButtons = `
                            <div class="mt-3 flex gap-2">
                                <button onclick="event.stopPropagation(); confirmMouRenewal(${notif.id}, 'renewed', ${notif.related_id})" 
                                        class="px-3 py-1.5 text-xs font-medium bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">check_circle</span>
                                    Renewed
                                </button>
                                <button onclick="event.stopPropagation(); confirmMouRenewal(${notif.id}, 'not_renewed', ${notif.related_id})" 
                                        class="px-3 py-1.5 text-xs font-medium bg-red-500 hover:bg-red-600 text-white rounded-lg transition-colors flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">cancel</span>
                                    Not Renewed
                                </button>
                            </div>
                        `;
                    } else if (isMouNotification && isConfirmed) {
                        const statusText = notif.mou_renewal_status === 'renewed' ? 'Renewed' : 'Not Renewed';
                        const statusColor = notif.mou_renewal_status === 'renewed' ? 'text-green-500' : 'text-red-500';
                        confirmationButtons = `
                            <div class="mt-2">
                                <p class="text-xs ${statusColor} font-medium flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">${notif.mou_renewal_status === 'renewed' ? 'check_circle' : 'cancel'}</span>
                                    Status: ${statusText}
                                </p>
                            </div>
                        `;
                    }
                    
                    const actionHint = targetUrl && !isMouNotification ? '<p class="text-xs text-primary mt-2 font-semibold flex items-center gap-1">Open related record<span class="material-symbols-outlined text-sm">arrow_outward</span></p>' : '';
                    // All notifications should be clickable, including unconfirmed MOU notifications
                    const clickableClass = 'cursor-pointer';
                    
                    return `
                        <div class="p-4 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 ${clickableClass} focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/60 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-background-dark ${notif.is_read ? 'opacity-60' : ''}" 
                             role="button" tabindex="0" data-id="${notif.id}" data-notification-id="${notif.id}"${relatedTypeAttr}${relatedIdAttr}${urlAttribute}>
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full ${bgColor} flex items-center justify-center">
                                    <span class="material-symbols-outlined text-white text-lg">${icon}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(notif.title)}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">${escapeHtml(notif.message)}</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">${timeAgo}</p>
                                    ${actionHint}
                                    ${confirmationButtons}
                                </div>
                                ${!notif.is_read ? '<div class="flex-shrink-0 w-2 h-2 bg-primary rounded-full mt-2"></div>' : ''}
                            </div>
                        </div>
                    `;
                }).join('');
            }
            
            function handleNotificationListClick(event) {
                // Check if clicked on a button (including confirmation buttons)
                const clickedButton = event.target.closest('button');
                if (clickedButton && (clickedButton.textContent.includes('Renewed') || clickedButton.textContent.includes('Not Renewed'))) {
                    // Let the button's onclick handler handle it (it has event.stopPropagation())
                    return;
                }
                
                // Check if clicked on a button
                const renewBtn = event.target.closest('.renew-mou-btn');
                const renewedBtn = event.target.closest('.renewed-mou-btn');
                
                if (renewBtn) {
                    event.stopPropagation();
                    event.preventDefault();
                    const notificationId = renewBtn.getAttribute('data-notification-id');
                    const entryId = renewBtn.getAttribute('data-entry-id');
                    console.log('Renew button clicked:', { notificationId, entryId, btn: renewBtn });
                    
                    if (!entryId) {
                        console.error('Entry ID not found in button data attributes');
                        alert('Error: Entry ID not found');
                        return;
                    }
                    
                    if (window.handleRenewMou) {
                        console.log('Calling handleRenewMou');
                        window.handleRenewMou(notificationId, entryId);
                    } else {
                        console.error('handleRenewMou function not found on window object');
                        console.log('Available window functions:', Object.keys(window).filter(k => k.includes('handle') || k.includes('Renew')));
                        alert('Error: Renew function not available. Please refresh the page.');
                    }
                    return;
                }
                
                if (renewedBtn) {
                    event.stopPropagation();
                    event.preventDefault();
                    const notificationId = renewedBtn.getAttribute('data-notification-id');
                    console.log('Renewed button clicked:', { notificationId, btn: renewedBtn });
                    
                    if (!notificationId) {
                        console.error('Notification ID not found in button data attributes');
                        alert('Error: Notification ID not found');
                        return;
                    }
                    
                    if (window.handleMouRenewed) {
                        console.log('Calling handleMouRenewed');
                        window.handleMouRenewed(notificationId);
                    } else {
                        console.error('handleMouRenewed function not found on window object');
                        console.log('Available window functions:', Object.keys(window).filter(k => k.includes('handle') || k.includes('Renew')));
                        alert('Error: Renewed function not available. Please refresh the page.');
                    }
                    return;
                }
                
                const target = event.target.closest('[data-notification-id]');
                if (!target) return;
                event.preventDefault();
                handleNotificationSelection(target);
            }
            
            function handleNotificationListKeydown(event) {
                if (event.key !== 'Enter' && event.key !== ' ') return;
                const target = event.target.closest('[data-notification-id]');
                if (!target) return;
                event.preventDefault();
                handleNotificationSelection(target);
            }
            
            async function handleNotificationSelection(element) {
                const notificationId = Number(element.dataset.notificationId);
                if (!notificationId) return;
                
                await markNotificationAsRead(notificationId);
                
                const relatedType = element.dataset.relatedType;
                const relatedId = element.dataset.relatedId;
                
                if (relatedType === 'mou_moa' && relatedId) {
                    if (notificationDropdown) {
                        notificationDropdown.classList.add('hidden');
                    }
                    
                    // Open the modal directly if we're already on the mou-moa page
                    if (typeof window.showMouDetails === 'function') {
                        // Fetch the entry and open modal
                        (async function() {
                            try {
                                const response = await fetch(`api/mou-moa.php?action=get&id=${relatedId}`);
                                const result = await response.json();
                                
                                if (result.success && result.data) {
                                    window.showMouDetails(result.data);
                                    
                                    // Ensure modal is visible
                                    const modal = document.getElementById('mouDetailsModal');
                                    if (modal) {
                                        modal.classList.remove('hidden');
                                        modal.style.display = 'flex';
                                    }
                                    
                                    // Highlight the entry
                                    if (typeof highlightEntry === 'function') {
                                        highlightEntry(parseInt(relatedId));
                                    }
                                }
                            } catch (error) {
                                console.error('Error loading entry:', error);
                                // Fallback: navigate to URL
                                window.location.href = `mou-moa.php?entry=${relatedId}`;
                            }
                        })();
                    } else {
                        // Fallback: navigate to URL with entry parameter
                        window.location.href = `mou-moa.php?entry=${relatedId}`;
                    }
                    return;
                }
                
                const targetUrl = decodeUrlAttribute(element.dataset.url);
                if (targetUrl) {
                    if (notificationDropdown) {
                        notificationDropdown.classList.add('hidden');
                    }
                    window.location.href = targetUrl;
                }
            }
            
            // Mark notification as read
            window.markNotificationAsRead = async function(id) {
                try {
                    const response = await fetch(`api/notifications.php?id=${id}`, {
                        method: 'PUT'
                    });
                    const data = await response.json();
                    if (data.success) {
                        const notif = notifications.find(n => n.id === id);
                        if (notif) {
                            notif.is_read = true;
                            updateNotificationDisplay();
                            updateNotificationBadge();
                        }
                        return true;
                    }
                    return false;
                } catch (error) {
                    console.error('Error marking notification as read:', error);
                    return false;
                }
            };
            
            // Mark all notifications as read
            async function markAllNotificationsAsRead() {
                try {
                    const response = await fetch('api/notifications.php', {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ action: 'mark_all_read' })
                    });
                    const data = await response.json();
                    if (data.success) {
                        notifications.forEach(n => n.is_read = true);
                        if (typeof updateNotificationDisplay === 'function') {
                            updateNotificationDisplay();
                        }
                        if (typeof updateNotificationBadge === 'function') {
                            updateNotificationBadge();
                        }
                        return true;
                    }
                    return false;
                } catch (error) {
                    console.error('Error marking all as read:', error);
                    return false;
                }
            }
            
            // Helper functions
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            function getTimeAgo(dateString) {
                const date = new Date(dateString);
                const now = new Date();
                const diffInSeconds = Math.floor((now - date) / 1000);
                
                if (diffInSeconds < 60) return 'Just now';
                if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
                if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
                if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`;
                return date.toLocaleDateString();
            }
            
            function getNotificationIcon(type) {
                const icons = {
                    'mou_expiring': 'schedule',
                    'mou_expired': 'warning',
                    'event_upcoming': 'event',
                    'event_today': 'today',
                    'system': 'info'
                };
                return icons[type] || 'notifications';
            }
            
            function getNotificationBgColor(type) {
                const colors = {
                    'mou_expiring': 'bg-yellow-500',
                    'mou_expired': 'bg-red-500',
                    'event_upcoming': 'bg-blue-500',
                    'event_today': 'bg-green-500',
                    'system': 'bg-gray-500'
                };
                return colors[type] || 'bg-gray-500';
            }
            
            function getNotificationUrl(notif) {
                if (!notif || !notif.related_type || !notif.related_id) {
                    return '';
                }
                
                const encodedId = encodeURIComponent(notif.related_id);
                
                if (notif.related_type === 'mou_moa') {
                    return `mou-moa.php?entry=${encodedId}`;
                }
                
                if (notif.related_type === 'event') {
                    return `events-activities.php?event=${encodedId}`;
                }
                
                if (notif.related_type === 'schedule') {
                    return `scheduler.php`;
                }
                
                return '';
            }
            
            function decodeUrlAttribute(value) {
                if (!value) return '';
                try {
                    return decodeURIComponent(value);
                } catch (error) {
                    console.warn('Unable to decode notification URL attribute:', error);
                    return value;
                }
            }
            
            /**
             * Show Facebook-style notification bar in lower right corner
             * @param {Object} notification - Notification object
             */
            function showMouNotificationBar(notification) {
                // Check if user has disabled notification bars
                const barsEnabled = localStorage.getItem('notification_bars_enabled') !== 'false';
                if (!barsEnabled) {
                    console.log('Notification bars are disabled');
                    return;
                }

                if (!notification || !notification.id) {
                    console.error('Invalid notification object:', notification);
                    return;
                }

                // For expired MOUs: Always show if notification exists (not yet renewed)
                if (notification?.type === 'mou_expired' && notification?.id != null) {
                    if (!shouldShowMouExpiredBar(notification.id)) {
                        console.log('Notification bar blocked for notification ID:', notification.id);
                        return;
                    }
                }
                
                // Play sound when notification bar appears for MOU/MOA notifications
                const isMouNotification = (notification.type === 'mou_expiring' || notification.type === 'mou_expired');
                if (isMouNotification) {
                    console.log('[MOU Notification Bar] Playing sound for notification:', notification.id, notification.type, 'related_type:', notification.related_type);
                    
                    // Check if this notification has already played sound
                    const hasPlayedSound = window.NotificationSound && 
                                         window.NotificationSound._hasPlayedSound && 
                                         window.NotificationSound._hasPlayedSound(notification.id);
                    
                    if (!hasPlayedSound) {
                        // Directly call play function - bypass filter since we know it's a MOU notification
                        if (window.NotificationSound && window.NotificationSound.play) {
                            console.log('[MOU Notification Bar] Directly calling NotificationSound.play()');
                            window.NotificationSound.play();
                            
                            // Mark this notification as having played sound
                            if (window.NotificationSound._markAsPlayed) {
                                window.NotificationSound._markAsPlayed(notification.id);
                            }
                        } else if (window.NotificationSound && window.NotificationSound.checkAndPlay) {
                            console.log('[MOU Notification Bar] Calling NotificationSound.checkAndPlay');
                            window.NotificationSound.checkAndPlay([notification]);
                        } else if (window.checkAndPlayMouNotificationSound) {
                            console.log('[MOU Notification Bar] Calling checkAndPlayMouNotificationSound wrapper');
                            window.checkAndPlayMouNotificationSound([notification]);
                        } else {
                            console.warn('[MOU Notification Bar] NotificationSound not available!');
                        }
                    } else {
                        console.log('[MOU Notification Bar] Sound already played for notification:', notification.id);
                    }
                } else {
                    console.log('[MOU Notification Bar] Not a MOU notification:', notification.type, notification.related_type);
                }
                
                console.log('Showing notification bar for:', notification.title, 'ID:', notification.id);

                // One-at-a-time queue for MOU bars (swipe right to dismiss)
                if (!window.__mouBarQueue) window.__mouBarQueue = [];
                if (!window.__mouBarShowing) window.__mouBarShowing = false;
                if (!window.__mouBarEnqueuedIds) window.__mouBarEnqueuedIds = new Set();

                // Prevent duplicates within a single page load
                if (notification && notification.id != null && window.__mouBarEnqueuedIds.has(notification.id)) {
                    return;
                }
                if (notification && notification.id != null) {
                    window.__mouBarEnqueuedIds.add(notification.id);
                }

                window.__mouBarQueue.push(notification);
                renderNextMouNotificationBar();
            }

            function getMouExpiredBarSeenSet() {
                if (!window.__mouExpiredBarSeenSet) {
                    let ids = [];
                    try {
                        const raw = localStorage.getItem('mou_expired_bar_seen_ids_v1');
                        if (raw) ids = JSON.parse(raw) || [];
                    } catch (_) {
                        ids = [];
                    }
                    window.__mouExpiredBarSeenSet = new Set((ids || []).map(v => String(v)));
                }
                return window.__mouExpiredBarSeenSet;
            }

            function shouldShowMouExpiredBar(notificationId) {
                if (notificationId == null) return true;
                
                // For expired MOUs: Always show if notification exists (not yet renewed)
                // Check if notification still exists in current notifications
                try {
                    const currentNotifications = getNotifications();
                    if (currentNotifications && Array.isArray(currentNotifications) && currentNotifications.length > 0) {
                        const notificationExists = currentNotifications.some(
                            n => n.id == notificationId && n.type === 'mou_expired' && n.related_type === 'mou_moa'
                        );
                        
                        if (notificationExists) {
                            // Notification exists = MOU is still expired and not renewed
                            // Always show it, clear localStorage so it can show again
                            removeMouBarSeenId(notificationId);
                            return true;
                        } else {
                            // Notification no longer exists (was renewed/deleted)
                            removeMouBarSeenId(notificationId);
                            return false;
                        }
                    }
                } catch (_) {
                    // If getNotifications fails, allow showing (will be verified when loaded)
                }
                
                // If notifications not loaded yet, allow showing (expired MOUs should persist until renewed)
                return true;
            }

            function markMouExpiredBarSeen(notificationId) {
                if (notificationId == null) return;
                const set = getMouExpiredBarSeenSet();
                set.add(String(notificationId));
                try {
                    localStorage.setItem('mou_expired_bar_seen_ids_v1', JSON.stringify(Array.from(set)));
                    // Store timestamp for cleanup purposes
                    try {
                        const raw = localStorage.getItem('mou_expired_bar_seen_timestamps_v1');
                        const timestamps = raw ? JSON.parse(raw) : {};
                        timestamps[String(notificationId)] = Date.now();
                        localStorage.setItem('mou_expired_bar_seen_timestamps_v1', JSON.stringify(timestamps));
                    } catch (_) {
                        // ignore timestamp storage failures
                    }
                } catch (_) {
                    // ignore storage failures
                }
            }

            /**
             * Clean up stale notification IDs from localStorage
             * Removes IDs that no longer exist in the current notifications
             */
            function cleanupStaleMouBarSeenIds(currentNotifications) {
                if (!currentNotifications || !Array.isArray(currentNotifications)) return;
                
                const seenSet = getMouExpiredBarSeenSet();
                if (seenSet.size === 0) return;
                
                // Get all current expired MOU notification IDs
                const currentExpiredMouIds = new Set(
                    currentNotifications
                        .filter(n => n.type === 'mou_expired' && n.related_type === 'mou_moa' && n.id != null)
                        .map(n => String(n.id))
                );
                
                // Remove IDs that no longer exist
                let hasChanges = false;
                const idsToRemove = [];
                seenSet.forEach(id => {
                    if (!currentExpiredMouIds.has(id)) {
                        idsToRemove.push(id);
                        hasChanges = true;
                    }
                });
                
                if (hasChanges) {
                    idsToRemove.forEach(id => seenSet.delete(id));
                    try {
                        localStorage.setItem('mou_expired_bar_seen_ids_v1', JSON.stringify(Array.from(seenSet)));
                        // Reset the cached set so it reloads on next access
                        window.__mouExpiredBarSeenSet = null;
                    } catch (_) {
                        // ignore storage failures
                    }
                }
            }

            /**
             * Clean up old localStorage entries (older than 90 days)
             * This prevents localStorage from growing indefinitely
             */
            function cleanupOldMouBarSeenIds() {
                try {
                    // Store timestamps with IDs to track when they were added
                    const raw = localStorage.getItem('mou_expired_bar_seen_timestamps_v1');
                    if (!raw) return;
                    
                    const timestamps = JSON.parse(raw) || {};
                    const ninetyDaysAgo = Date.now() - (90 * 24 * 60 * 60 * 1000);
                    
                    let hasChanges = false;
                    const idsToRemove = [];
                    
                    Object.keys(timestamps).forEach(id => {
                        if (timestamps[id] < ninetyDaysAgo) {
                            idsToRemove.push(id);
                            delete timestamps[id];
                            hasChanges = true;
                        }
                    });
                    
                    if (hasChanges) {
                        // Remove from seen set
                        const seenSet = getMouExpiredBarSeenSet();
                        idsToRemove.forEach(id => seenSet.delete(id));
                        
                        localStorage.setItem('mou_expired_bar_seen_ids_v1', JSON.stringify(Array.from(seenSet)));
                        localStorage.setItem('mou_expired_bar_seen_timestamps_v1', JSON.stringify(timestamps));
                        window.__mouExpiredBarSeenSet = null;
                    }
                } catch (_) {
                    // ignore storage failures
                }
            }

            /**
             * Remove notification ID from localStorage when MOU is renewed
             */
            function removeMouBarSeenId(notificationId) {
                if (notificationId == null) return;
                const set = getMouExpiredBarSeenSet();
                if (set.has(String(notificationId))) {
                    set.delete(String(notificationId));
                    try {
                        localStorage.setItem('mou_expired_bar_seen_ids_v1', JSON.stringify(Array.from(set)));
                        // Also remove from timestamps if it exists
                        try {
                            const raw = localStorage.getItem('mou_expired_bar_seen_timestamps_v1');
                            if (raw) {
                                const timestamps = JSON.parse(raw) || {};
                                delete timestamps[String(notificationId)];
                                localStorage.setItem('mou_expired_bar_seen_timestamps_v1', JSON.stringify(timestamps));
                            }
                        } catch (_) {
                            // ignore timestamp cleanup failures
                        }
                        window.__mouExpiredBarSeenSet = null;
                    } catch (_) {
                        // ignore storage failures
                    }
                }
            }
            
            function getOrCreateMouNotificationContainer() {
                let container = document.getElementById('mouNotificationBarsContainer');
                if (!container) {
                    container = document.createElement('div');
                    container.id = 'mouNotificationBarsContainer';
                    container.className = 'fixed bottom-4 right-4 z-[10000] flex flex-col items-end max-w-sm';
                    document.body.appendChild(container);
                }
                return container;
            }

            function buildMouNotificationCard(notification) {
                const card = document.createElement('div');
                const notificationId = 'mou-notif-' + (notification?.id ?? 'unknown') + '-' + Date.now();
                card.id = notificationId;
                card.dataset.notificationId = notification?.id != null ? String(notification.id) : '';
                card.dataset.notificationType = notification?.type || '';

                const isExpired = notification?.type === 'mou_expired';
                const bgColor = isExpired ? 'bg-red-500' : 'bg-yellow-500';
                const icon = isExpired ? 'warning' : 'schedule';
                const iconColor = isExpired ? 'text-red-50' : 'text-yellow-50';

                card.className = `${bgColor} text-white rounded-lg p-4 transform transition-all duration-300 translate-x-full opacity-0 mou-notification-card`;
                card.style.minWidth = '320px';
                card.style.maxWidth = '400px';
                card.style.zIndex = 10001;
                card.setAttribute('data-related-type', notification?.related_type || '');
                card.setAttribute('data-related-id', notification?.related_id || '');

                card.innerHTML = `
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 w-10 h-10 rounded-full ${isExpired ? 'bg-red-600' : 'bg-yellow-600'} flex items-center justify-center">
                            <span class="material-symbols-outlined ${iconColor}">${icon}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="font-semibold text-sm mb-1 line-clamp-1">${escapeHtml(notification?.title || 'MOU/MOA Notification')}</h4>
                            <p class="text-xs opacity-90 line-clamp-2">${escapeHtml(notification?.message || '')}</p>
                            <p class="text-xs opacity-75 mt-1">${getTimeAgo(notification?.created_at)}</p>
                            <p class="text-[11px] opacity-80 mt-2">Drag right to dismiss</p>
                        </div>
                        <button onclick="removeNotificationPaper(this)" 
                                class="flex-shrink-0 text-white hover:text-gray-200 transition-colors opacity-70 hover:opacity-100 z-10 relative">
                            <span class="material-symbols-outlined text-sm">close</span>
                        </button>
                    </div>
                `;

                // Click to open MOU/MOA Details modal (unless it was a drag)
                if (notification?.related_id && notification?.related_type === 'mou_moa') {
                    card.addEventListener('click', function(e) {
                        // Don't trigger if it was a drag/swipe
                        if (card.dataset.suppressClick === '1') return;
                        // Don't trigger if clicking on a button
                        if (e.target.closest('button')) return;

                        // Fetch the entry and open the modal
                        (async function() {
                            try {
                                const response = await fetch(`api/mou-moa.php?action=get&id=${notification.related_id}`);
                                const result = await response.json();
                                
                                if (result.success && result.data) {
                                    // Open the modal using the correct function
                                    if (typeof window.showMouDetails === 'function') {
                                        window.showMouDetails(result.data);
                                        
                                        // Ensure modal is visible
                                        const modal = document.getElementById('mouDetailsModal');
                                        if (modal) {
                                            modal.classList.remove('hidden');
                                            modal.style.display = 'flex';
                                        }
                                        
                                        // Highlight the entry
                                        if (typeof highlightEntry === 'function') {
                                            highlightEntry(parseInt(notification.related_id));
                                        }
                                    } else {
                                        // Fallback: navigate to URL
                                        window.location.href = `mou-moa.php?entry=${notification.related_id}`;
                                    }
                                } else {
                                    console.error('Failed to load entry:', result.error);
                                    // Fallback: navigate to URL
                                    window.location.href = `mou-moa.php?entry=${notification.related_id}`;
                                }
                            } catch (error) {
                                console.error('Error loading entry:', error);
                                // Fallback: navigate to URL
                                window.location.href = `mou-moa.php?entry=${notification.related_id}`;
                            }
                        })();
                        
                        // Once opened, move to the next queued notification
                        dismissCurrentMouNotificationBar(card, { animate: false });
                    });
                }

                attachSwipeToDismiss(card);
                return card;
            }

            function renderNextMouNotificationBar() {
                if (!window.__mouBarQueue || window.__mouBarQueue.length === 0) {
                    const existing = document.getElementById('mouNotificationBarsContainer');
                    if (existing && existing.children.length === 0) existing.remove();
                    return;
                }
                if (window.__mouBarShowing) return;

                const container = getOrCreateMouNotificationContainer();
                // Ensure only one card is visible at a time
                container.innerHTML = '';

                const next = window.__mouBarQueue.shift();
                const card = buildMouNotificationCard(next);
                container.appendChild(card);
                window.__mouBarShowing = true;

                // Animate in
                setTimeout(() => {
                    card.classList.remove('translate-x-full');
                    card.style.transform = 'translateX(0)';
                    card.style.opacity = '1';
                }, 50);
            }

            function dismissCurrentMouNotificationBar(card, { animate = true } = {}) {
                const container = document.getElementById('mouNotificationBarsContainer');
                const el = card || (container ? container.querySelector('.mou-notification-card') : null);
                if (!el) {
                    window.__mouBarShowing = false;
                    renderNextMouNotificationBar();
                    return;
                }

                // Mark "mou_expired" notifications as seen so they can be queued once per browser (unless storage is cleared)
                if (el.dataset.notificationType === 'mou_expired' && el.dataset.notificationId) {
                    markMouExpiredBarSeen(el.dataset.notificationId);
                }

                if (!animate) {
                    el.remove();
                    window.__mouBarShowing = false;
                    if (container && container.children.length === 0 && (!window.__mouBarQueue || window.__mouBarQueue.length === 0)) {
                        container.remove();
                    }
                    renderNextMouNotificationBar();
                    return;
                }

                el.style.transition = 'transform 0.25s ease, opacity 0.25s ease';
                el.style.transform = `translateX(${Math.max(el.offsetWidth, 360) + 80}px)`;
                el.style.opacity = '0';
                setTimeout(() => {
                    if (el.parentElement) el.remove();
                    window.__mouBarShowing = false;
                    if (container && container.children.length === 0 && (!window.__mouBarQueue || window.__mouBarQueue.length === 0)) {
                        container.remove();
                    }
                    renderNextMouNotificationBar();
                }, 260);
            }

            function attachSwipeToDismiss(card) {
                let startX = 0;
                let currentX = 0;
                let dragging = false;
                let moved = false;

                const onPointerDown = (e) => {
                    // Ignore right-click / non-primary mouse button
                    if (e.pointerType === 'mouse' && e.button !== 0) return;
                    // Don't start swipe from the close button
                    if (e.target.closest('button')) return;

                    dragging = true;
                    moved = false;
                    startX = e.clientX;
                    currentX = 0;
                    card.classList.add('mou-dragging');
                    card.dataset.suppressClick = '0';
                    try { card.setPointerCapture(e.pointerId); } catch (_) {}
                };

                const onPointerMove = (e) => {
                    if (!dragging) return;
                    const dx = e.clientX - startX;
                    currentX = Math.max(0, dx); // only allow dragging to the right
                    // Only consider it a "move" if moved more than 20px to avoid suppressing legitimate clicks
                    if (currentX > 20) moved = true;

                    const rotate = Math.min(6, currentX / 40); // subtle
                    const opacity = Math.max(0.2, 1 - currentX / 320);
                    card.style.transform = `translateX(${currentX}px) rotate(${rotate}deg)`;
                    card.style.opacity = String(opacity);
                };

                const finish = () => {
                    card.classList.remove('mou-dragging');
                    const threshold = Math.min(140, (card.offsetWidth || 320) * 0.35);
                    if (currentX >= threshold) {
                        card.dataset.suppressClick = '1';
                        dismissCurrentMouNotificationBar(card, { animate: true });
                        return;
                    }
                    // Snap back
                    card.style.transition = 'transform 0.2s ease, opacity 0.2s ease';
                    card.style.transform = 'translateX(0)';
                    card.style.opacity = '1';
                    // Only suppress click if there was significant movement (more than 20px)
                    if (moved && currentX > 20) {
                        card.dataset.suppressClick = '1';
                        setTimeout(() => { card.dataset.suppressClick = '0'; }, 250);
                    } else {
                        // Clear suppress flag immediately for small movements
                        card.dataset.suppressClick = '0';
                    }
                };

                const onPointerUp = (e) => {
                    if (!dragging) return;
                    dragging = false;
                    try { card.releasePointerCapture(e.pointerId); } catch (_) {}
                    finish();
                };

                const onPointerCancel = () => {
                    if (!dragging) return;
                    dragging = false;
                    finish();
                };

                card.addEventListener('pointerdown', onPointerDown);
                card.addEventListener('pointermove', onPointerMove);
                card.addEventListener('pointerup', onPointerUp);
                card.addEventListener('pointercancel', onPointerCancel);
            }
            
            /**
             * Remove notification paper with animation
             */
            function removeNotificationPaper(button) {
                const card = button.closest('.mou-notification-card');
                dismissCurrentMouNotificationBar(card, { animate: true });
            }
            
            // Make removeNotificationPaper globally accessible
            window.removeNotificationPaper = removeNotificationPaper;
            
            // Highlight entry in table
            function highlightEntry(entryId) {
                // Try to find checkbox with both string and number ID
                let checkbox = document.querySelector(`.row-checkbox[data-id="${entryId}"]`);
                if (!checkbox) {
                    // Try with the other type (string vs number)
                    const altId = typeof entryId === 'string' ? parseInt(entryId) : entryId.toString();
                    checkbox = document.querySelector(`.row-checkbox[data-id="${altId}"]`);
                }
                
                if (checkbox) {
                    const row = checkbox.closest('tr');
                    if (row) {
                        // Scroll to the row
                        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        
                        // Highlight the row temporarily
                        row.classList.add('bg-yellow-100', 'dark:bg-yellow-900/20');
                        setTimeout(() => {
                            row.classList.remove('bg-yellow-100', 'dark:bg-yellow-900/20');
                        }, 3000);
                    }
                }
            }
            
            async function refreshNotificationIndicators() {
                try {
                    await checkNotifications();
                    // Reload notifications after checking to show newly created ones
                    await loadNotifications();
                    await updateNotificationBadge();
                } catch (error) {
                    console.error('Error refreshing notification indicators:', error);
                }
            }
            
            // Make loadNotifications, checkNotifications, and updateNotificationBadge globally accessible
            window.loadNotifications = loadNotifications;
            window.checkNotifications = checkNotifications;
            window.updateNotificationBadge = updateNotificationBadge;
            
            // Confirm MOU renewal function (same as dashboard)
            window.confirmMouRenewal = async function(notificationId, renewalStatus, entryId) {
                // For "renewed": open the MOU/MOA renew flow (edit sign date + term) instead of immediately confirming.
                if (renewalStatus === 'renewed') {
                    if (typeof window.openMouRenewalFlow === 'function') {
                        window.openMouRenewalFlow(notificationId, entryId);
                    } else {
                        if (!entryId) {
                            alert('Error: missing MOU/MOA entry id for renewal.');
                            return;
                        }
                        window.location.href = `mou-moa.php?entry=${encodeURIComponent(entryId)}&renew=1&notif=${encodeURIComponent(notificationId)}`;
                    }
                    return;
                }
                try {
                    const response = await fetch('api/notifications.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'confirm_mou_renewal',
                            notification_id: notificationId,
                            renewal_status: renewalStatus
                        })
                    });
                    const data = await response.json();
                    if (data.success) {
                        // Reload notifications to update the display
                        await loadNotifications();
                        await updateNotificationBadge();
                    } else {
                        console.error('Failed to confirm MOU renewal:', data);
                        alert('Failed to update renewal status: ' + (data.error || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Error confirming MOU renewal:', error);
                    alert('Error updating renewal status: ' + error.message);
                }
            };
            
            // Update badge on page load (like documents.php)
            updateNotificationBadge();
            
            // Update badge every 30 seconds for real-time updates
            setInterval(() => {
                updateNotificationBadge();
            }, 30000);
            
            // Show all notifications modal
            function showAllNotificationsModal() {
                console.log('showAllNotificationsModal called');
                const modal = document.getElementById('allNotificationsModal');
                if (!modal) {
                    console.error('allNotificationsModal not found, creating it...');
                    createAllNotificationsModal();
                    // Try again after creating
                    setTimeout(() => showAllNotificationsModal(), 100);
                    return;
                }
                
                // Load all notifications into the modal
                loadAllNotificationsIntoModal();
                
                // Show the modal
                modal.classList.remove('hidden');
                modal.style.display = 'flex';
            }
            
            // Load all notifications into the modal
            async function loadAllNotificationsIntoModal() {
                const modalList = document.getElementById('allNotificationsList');
                const countElement = document.getElementById('notificationsCount');
                
                if (!modalList) {
                    console.error('allNotificationsList not found');
                    return;
                }
                
                try {
                    const response = await fetch('api/notifications.php');
                    const data = await response.json();
                    
                    if (data.notifications && Array.isArray(data.notifications)) {
                        let allNotifications = data.notifications;
                        
                        // Deduplicate MOU notifications: keep only the most recent one for each MOU+type combination
                        const mouNotificationMap = new Map();
                        const otherNotifications = [];
                        
                        allNotifications.forEach(notif => {
                            if (notif.related_type === 'mou_moa' && notif.related_id) {
                                // Create a unique key for MOU notifications: related_id + type
                                const key = `${notif.related_id}_${notif.type}`;
                                
                                if (!mouNotificationMap.has(key)) {
                                    mouNotificationMap.set(key, notif);
                                } else {
                                    // Keep the most recent one
                                    const existing = mouNotificationMap.get(key);
                                    const existingDate = new Date(existing.created_at);
                                    const currentDate = new Date(notif.created_at);
                                    
                                    if (currentDate > existingDate) {
                                        mouNotificationMap.set(key, notif);
                                    }
                                }
                            } else {
                                // Keep non-MOU notifications as-is
                                otherNotifications.push(notif);
                            }
                        });
                        
                        // Combine deduplicated MOU notifications with other notifications
                        allNotifications = [...Array.from(mouNotificationMap.values()), ...otherNotifications];
                        
                        // Sort by created_at (most recent first)
                        allNotifications.sort((a, b) => {
                            const dateA = new Date(a.created_at);
                            const dateB = new Date(b.created_at);
                            return dateB - dateA;
                        });
                        
                        // Update count
                        if (countElement) {
                            countElement.textContent = allNotifications.length;
                        }
                        
                        // Render notifications
                        if (allNotifications.length === 0) {
                            modalList.innerHTML = `
                                <div class="text-center py-12">
                                    <span class="material-symbols-outlined text-6xl text-gray-400 dark:text-gray-600 mb-4 block">notifications_off</span>
                                    <p class="text-gray-500 dark:text-gray-400 text-lg">No notifications</p>
                                </div>
                            `;
                } else {
                            modalList.innerHTML = allNotifications.map(notif => {
                                const timeAgo = getTimeAgo(notif.created_at);
                                const icon = getNotificationIcon(notif.type);
                                const bgColor = getNotificationBgColor(notif.type);
                                const targetUrl = getNotificationUrl(notif);
                                const isMouNotification = notif.related_type === 'mou_moa';
                                const isConfirmed = notif.is_confirmed || false;
                                
                                // Add Renew/Renewed buttons for MOU notifications that aren't confirmed
                                let actionButtons = '';
                                if (isMouNotification && !isConfirmed) {
                                    actionButtons = `
                                        <div class="mt-3 flex gap-2">
                                            <button data-action="renew" data-notification-id="${notif.id}" data-entry-id="${notif.related_id}" 
                                                    class="renew-mou-btn px-3 py-1.5 text-xs font-medium bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-colors flex items-center gap-1">
                                                <span class="material-symbols-outlined text-sm">edit</span>
                                                Renew
                                            </button>
                                            <button data-action="renewed" data-notification-id="${notif.id}" 
                                                    class="renewed-mou-btn px-3 py-1.5 text-xs font-medium bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors flex items-center gap-1">
                                                <span class="material-symbols-outlined text-sm">check_circle</span>
                                                Renewed
                                            </button>
                                        </div>
                                    `;
                                } else if (isMouNotification && isConfirmed && notif.mou_renewal_status === 'renewed') {
                                    actionButtons = `
                                        <div class="mt-2">
                                            <p class="text-xs text-green-500 font-medium flex items-center gap-1">
                                                <span class="material-symbols-outlined text-sm">check_circle</span>
                                                Status: Renewed
                                            </p>
                                        </div>
                                    `;
                                }
                                
                                return `
                                    <div class="p-4 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 ${notif.is_read ? 'opacity-60' : ''}" 
                                         data-notification-id="${notif.id}"
                                         data-related-type="${notif.related_type || ''}"
                                         data-related-id="${notif.related_id || ''}">
                                        <div class="flex items-start gap-3">
                                            <div class="flex-shrink-0 w-10 h-10 rounded-full ${bgColor} flex items-center justify-center">
                                                <span class="material-symbols-outlined text-white text-lg">${icon}</span>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(notif.title)}</p>
                                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">${escapeHtml(notif.message)}</p>
                                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">${timeAgo}</p>
                                                ${actionButtons}
                                            </div>
                                            ${!notif.is_read ? '<div class="flex-shrink-0 w-2 h-2 bg-primary rounded-full mt-2"></div>' : ''}
                                        </div>
                                    </div>
                                `;
                            }).join('');
                            
                            // Add click handlers for notifications in modal
                            modalList.querySelectorAll('[data-notification-id]').forEach(item => {
                                item.addEventListener('click', function(e) {
                                    // Don't trigger if clicking on buttons
                                    if (e.target.closest('.renew-mou-btn') || e.target.closest('.renewed-mou-btn')) {
                                        return;
                                    }
                                    handleNotificationSelection(item);
                                });
                            });
                            
                            // Add click handlers for Renew/Renewed buttons in modal
                            modalList.querySelectorAll('.renew-mou-btn').forEach(btn => {
                                btn.addEventListener('click', function(e) {
                                    e.stopPropagation();
                                    const notificationId = btn.getAttribute('data-notification-id');
                                    const entryId = btn.getAttribute('data-entry-id');
                                    if (window.handleRenewMou) {
                                        window.handleRenewMou(notificationId, entryId);
                                    }
                                });
                            });
                            
                            modalList.querySelectorAll('.renewed-mou-btn').forEach(btn => {
                                btn.addEventListener('click', function(e) {
                                    e.stopPropagation();
                                    const notificationId = btn.getAttribute('data-notification-id');
                                    if (window.handleMouRenewed) {
                                        window.handleMouRenewed(notificationId).then(() => {
                                            // Reload modal notifications after renewal
                                            loadAllNotificationsIntoModal();
                                            // Also update the dropdown
                                            if (typeof loadNotifications === 'function') {
                    loadNotifications();
                                            }
                                        });
                                    }
                                });
                            });
                        }
                    }
                } catch (error) {
                    console.error('Error loading notifications into modal:', error);
                    modalList.innerHTML = `
                        <div class="text-center py-12">
                            <p class="text-red-500">Error loading notifications. Please try again.</p>
                        </div>
                    `;
                }
            }
            
            // Close all notifications modal
            function closeAllNotificationsModal() {
                const modal = document.getElementById('allNotificationsModal');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.style.display = 'none';
                }
            }
            
            // Create the all notifications modal
            function createAllNotificationsModal() {
                const modalHTML = `
                    <div id="allNotificationsModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm hidden">
                        <div class="w-full max-w-4xl bg-white dark:bg-background-dark rounded-xl shadow-2xl m-4 flex flex-col max-h-[90vh]">
                            <!-- Modal Header -->
                            <div class="p-6 border-b border-gray-200 dark:border-gray-800 flex-shrink-0">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">All Notifications</h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage your MOU/MOA notifications</p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <button id="markAllReadModalBtn" class="px-4 py-2 text-sm font-medium text-primary bg-primary/10 dark:bg-primary/20 rounded-lg hover:bg-primary/20 dark:hover:bg-primary/30">
                                            Mark All Read
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Filter Tabs -->
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-800 flex-shrink-0">
                                <div class="flex space-x-1 bg-gray-100 dark:bg-gray-800 rounded-lg p-1">
                                    <button class="notification-tab px-4 py-2 text-sm font-medium rounded-md transition-colors" data-filter="all">
                                        All
                                    </button>
                                    <button class="notification-tab px-4 py-2 text-sm font-medium rounded-md transition-colors" data-filter="critical">
                                        Critical
                                    </button>
                                    <button class="notification-tab px-4 py-2 text-sm font-medium rounded-md transition-colors" data-filter="unread">
                                        Unread
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Notifications List -->
                            <div id="allNotificationsList" class="flex-1 overflow-y-auto p-6">
                                <!-- Notifications will be populated here -->
                            </div>
                            
                            <!-- Modal Footer -->
                            <div class="p-6 border-t border-gray-200 dark:border-gray-800 flex-shrink-0">
                                <div class="flex items-center justify-between">
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        <span id="notificationsCount">0</span> notifications
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <button id="clearOldNotifications" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                                            Clear All
                                        </button>
                                        <button id="closeAllNotificationsModalBtn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-background-dark/50 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-800">
                                            Close
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                document.body.insertAdjacentHTML('beforeend', modalHTML);
                
                // Add event listeners for the modal
                setupAllNotificationsModalEvents();
            }
            
            // Setup event listeners for the all notifications modal
            function setupAllNotificationsModalEvents() {
                // Remove old event listeners if they exist by cloning and replacing elements
                const modal = document.getElementById('allNotificationsModal');
                if (!modal) {
                    console.error('Modal not found when setting up events');
                    return;
                }
                
                const closeBtn = document.getElementById('closeAllNotificationsModalBtn');
                const markAllReadBtn = document.getElementById('markAllReadModalBtn');
                const clearOldBtn = document.getElementById('clearOldNotifications');
                const tabs = document.querySelectorAll('.notification-tab');
                
                // Close modal
                if (closeBtn) {
                    closeBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        closeAllNotificationsModal();
                    });
                }
                
                // Close modal when clicking outside
                if (modal) {
                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) {
                            closeAllNotificationsModal();
                        }
                    });
                }
                
                // Close modal with Escape key
                const escapeHandler = function(e) {
                    if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                        closeAllNotificationsModal();
                        document.removeEventListener('keydown', escapeHandler);
                    }
                };
                document.addEventListener('keydown', escapeHandler);
                
                // Mark all as read
                if (markAllReadBtn) {
                    markAllReadBtn.addEventListener('click', async function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        try {
                            await markAllNotificationsAsRead();
                            await updateAllNotificationsModal();
                            if (typeof updateNotificationBadge === 'function') {
                                updateNotificationBadge();
                            }
                        } catch (error) {
                            console.error('Error marking all as read:', error);
                        }
                    });
                }
                
                // Clear all notifications
                let isClearingAll = false;
                if (clearOldBtn) {
                    clearOldBtn.addEventListener('click', async function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        // Prevent double-click while a clear operation is in progress
                        if (isClearingAll) {
                            return;
                        }
                        isClearingAll = true;
                        try {
                            await clearOldNotifications();
                        } catch (error) {
                            console.error('Error clearing notifications:', error);
                        } finally {
                            isClearingAll = false;
                        }
                    });
                }
                
                // Tab filtering
                if (tabs && tabs.length > 0) {
                    tabs.forEach(tab => {
                        tab.addEventListener('click', function() {
                            // Update active tab
                            tabs.forEach(t => t.classList.remove('bg-white', 'dark:bg-gray-700', 'text-gray-900', 'dark:text-white', 'shadow-sm'));
                            tabs.forEach(t => t.classList.add('text-gray-600', 'dark:text-gray-400'));
                            
                            this.classList.remove('text-gray-600', 'dark:text-gray-400');
                            this.classList.add('bg-white', 'dark:bg-gray-700', 'text-gray-900', 'dark:text-white', 'shadow-sm');
                            
                            // Filter notifications
                            const filter = this.dataset.filter;
                            filterAllNotifications(filter);
                        });
                    });
                }
                
                // Set default active tab
                if (tabs && tabs.length > 0) {
                    tabs[0].classList.remove('text-gray-600', 'dark:text-gray-400');
                    tabs[0].classList.add('bg-white', 'dark:bg-gray-700', 'text-gray-900', 'dark:text-white', 'shadow-sm');
                }
            }
            
            // Close all notifications modal
            function closeAllNotificationsModal() {
                const modal = document.getElementById('allNotificationsModal');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.style.display = 'none';
                }
            }
            
            // Update all notifications modal content
            async function updateAllNotificationsModal() {
                // Reload notifications from API to get latest data
                await loadAllNotificationsIntoModal();
            }
            
            // Group notifications by date
            function groupNotificationsByDate(notifications) {
                const groups = {};
                const today = new Date();
                const yesterday = new Date(today);
                yesterday.setDate(yesterday.getDate() - 1);
                
                notifications.forEach(notification => {
                    const notificationDate = notification.created_at || notification.timestamp;
                    const date = new Date(notificationDate);
                    let groupKey;
                    
                    if (date.toDateString() === today.toDateString()) {
                        groupKey = 'Today';
                    } else if (date.toDateString() === yesterday.toDateString()) {
                        groupKey = 'Yesterday';
                    } else {
                        groupKey = date.toLocaleDateString('en-US', { 
                            weekday: 'long', 
                            year: 'numeric', 
                            month: 'long', 
                            day: 'numeric' 
                        });
                    }
                    
                    if (!groups[groupKey]) {
                        groups[groupKey] = [];
                    }
                    groups[groupKey].push(notification);
                });
                
                return groups;
            }
            
            // Setup event listeners for notification items in the modal
            function setupAllNotificationsItemEvents() {
                // Click to mark as read and highlight entry
                document.querySelectorAll('.notification-item-modal').forEach(item => {
                    item.addEventListener('click', function(e) {
                        if (e.target.closest('.delete-notification-btn')) return; // Don't trigger on delete button
                        
                        const notificationId = this.dataset.id;
                        markNotificationAsRead(notificationId);
                        
                        const notification = getNotificationById(notificationId);
                        if (notification && notification.entryId) {
                            highlightEntry(notification.entryId);
                            closeAllNotificationsModal();
                        }
                        
                        updateAllNotificationsModal();
                        updateNotificationBadge();
                    });
                });
                
                // Delete notification
                document.querySelectorAll('.delete-notification-btn').forEach(btn => {
                    btn.addEventListener('click', async function(e) {
                        e.stopPropagation();
                        const notificationId = this.dataset.id;
                        await deleteNotification(notificationId);
                        await updateAllNotificationsModal();
                        updateNotificationBadge();
                    });
                });
            }
            
            // Filter notifications in the modal
            function filterAllNotifications(filter) {
                const notifications = getNotifications();
                let filteredNotifications = notifications;
                
                if (filter !== 'all') {
                    if (filter === 'unread') {
                        filteredNotifications = notifications.filter(n => !n.is_read);
                    } else {
                        // Filter by priority/type for MOU notifications
                        // Map filter to notification types
                        const typeMap = {
                            'critical': 'mou_expired',
                            'high': 'mou_expiring',
                            'medium': 'event_today',
                            'low': 'event_upcoming'
                        };
                        const targetType = typeMap[filter];
                        if (targetType) {
                            filteredNotifications = notifications.filter(n => n.type === targetType);
                        } else {
                            filteredNotifications = notifications;
                        }
                    }
                }
                
                // Update the display with filtered notifications
                updateAllNotificationsModalWithFilter(filteredNotifications);
            }
            
            // Update modal with filtered notifications
            function updateAllNotificationsModalWithFilter(filteredNotifications) {
                const notificationsList = document.getElementById('allNotificationsList');
                const notificationsCount = document.getElementById('notificationsCount');
                
                if (!notificationsList || !notificationsCount) return;
                
                // Deduplicate MOU notifications in filtered list
                const mouNotificationMap = new Map();
                const otherNotifications = [];
                
                filteredNotifications.forEach(notif => {
                    if (notif.related_type === 'mou_moa' && notif.related_id) {
                        const key = `${notif.related_id}_${notif.type}`;
                        
                        if (!mouNotificationMap.has(key)) {
                            mouNotificationMap.set(key, notif);
                        } else {
                            const existing = mouNotificationMap.get(key);
                            const existingDate = new Date(existing.created_at);
                            const currentDate = new Date(notif.created_at);
                            
                            if (currentDate > existingDate) {
                                mouNotificationMap.set(key, notif);
                            }
                        }
                    } else {
                        otherNotifications.push(notif);
                    }
                });
                
                // Combine deduplicated notifications
                filteredNotifications = [...Array.from(mouNotificationMap.values()), ...otherNotifications];
                
                notificationsCount.textContent = filteredNotifications.length;
                
                if (filteredNotifications.length === 0) {
                    notificationsList.innerHTML = `
                        <div class="text-center py-12">
                            <span class="material-symbols-outlined text-6xl text-gray-300 dark:text-gray-600 mb-4 block">notifications_off</span>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No notifications</h3>
                            <p class="text-gray-500 dark:text-gray-400">No notifications match this filter.</p>
                        </div>
                    `;
                    return;
                }
                
                // Sort and group filtered notifications
                const sortedNotifications = filteredNotifications.sort((a, b) => {
                    // Map notification types to priority order
                    const typePriority = { 
                        'mou_expired': 4, 
                        'mou_expiring': 3, 
                        'event_today': 2, 
                        'event_upcoming': 1,
                        'system': 0
                    };
                    const aPriority = typePriority[a.type] || 0;
                    const bPriority = typePriority[b.type] || 0;
                    if (aPriority !== bPriority) {
                        return bPriority - aPriority;
                    }
                    const aDate = new Date(a.created_at || a.timestamp || 0);
                    const bDate = new Date(b.created_at || b.timestamp || 0);
                    return bDate - aDate;
                });
                
                const groupedNotifications = groupNotificationsByDate(sortedNotifications);
                
                notificationsList.innerHTML = Object.keys(groupedNotifications).map(date => {
                    const dayNotifications = groupedNotifications[date];
                    return `
                        <div class="mb-6">
                            <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">${date}</h4>
                            <div class="space-y-3">
                                ${dayNotifications.map(notification => {
                                    const notificationDate = notification.created_at || notification.timestamp;
                                    const timeAgo = getTimeAgo(new Date(notificationDate));
                                    const bgColor = getNotificationBgColor(notification.type);
                                    const readClass = notification.is_read ? 'opacity-60' : '';
                                    const icon = getNotificationIcon(notification.type);
                                    const targetUrl = getNotificationUrl(notification);
                                    const isMouNotification = notification.related_type === 'mou_moa';
                                    const isConfirmed = notification.is_confirmed || false;
                                    
                                    // Add Renew/Renewed buttons for MOU notifications that aren't confirmed
                                    let actionButtons = '';
                                    if (isMouNotification && !isConfirmed) {
                                        actionButtons = `
                                            <div class="mt-3 flex gap-2">
                                                <button data-action="renew" data-notification-id="${notification.id}" data-entry-id="${notification.related_id}" 
                                                        class="renew-mou-btn px-3 py-1.5 text-xs font-medium bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-colors flex items-center gap-1">
                                                    <span class="material-symbols-outlined text-sm">edit</span>
                                                    Renew
                                                </button>
                                                <button data-action="renewed" data-notification-id="${notification.id}" 
                                                        class="renewed-mou-btn px-3 py-1.5 text-xs font-medium bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors flex items-center gap-1">
                                                    <span class="material-symbols-outlined text-sm">check_circle</span>
                                                    Renewed
                                                </button>
                                            </div>
                                        `;
                                    } else if (isMouNotification && isConfirmed && notification.mou_renewal_status === 'renewed') {
                                        actionButtons = `
                                            <div class="mt-2">
                                                <p class="text-xs text-green-500 font-medium flex items-center gap-1">
                                                    <span class="material-symbols-outlined text-sm">check_circle</span>
                                                    Status: Renewed
                                                </p>
                                            </div>
                                        `;
                                    }
                                    
                                    const urlAttr = targetUrl ? ' data-url="' + encodeURIComponent(targetUrl) + '"' : '';
                                    
                                    return `
                                        <div class="notification-item-modal p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer transition-colors ${readClass}" 
                                             data-id="${notification.id}"
                                             data-notification-id="${notification.id}"
                                             data-related-type="${notification.related_type || ''}"
                                             data-related-id="${notification.related_id || ''}"${urlAttr}>
                                            <div class="flex items-start gap-4">
                                                <div class="flex-shrink-0">
                                                    <div class="w-10 h-10 rounded-full flex items-center justify-center ${bgColor}">
                                                        <span class="material-symbols-outlined text-white text-lg">
                                                            ${icon}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div class="flex-1">
                                                            <h5 class="text-sm font-semibold text-gray-900 dark:text-white mb-1">
                                                                ${escapeHtml(notification.title)}
                                                            </h5>
                                                            <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">
                                                                ${escapeHtml(notification.message)}
                                                            </p>
                                                            <div class="flex items-center gap-2">
                                                                <span class="text-xs text-gray-500 dark:text-gray-400">${timeAgo}</span>
                                                            </div>
                                                            ${actionButtons}
                                                        </div>
                                                        <div class="flex items-center gap-2">
                                                            ${!notification.is_read ? '<div class="w-3 h-3 bg-primary rounded-full"></div>' : ''}
                                                            <button class="delete-notification-btn p-1 text-gray-400 hover:text-red-500 transition-colors" data-id="${notification.id}" title="Delete notification">
                                                                <span class="material-symbols-outlined text-sm">delete</span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                        </div>
                    `;
                }).join('');
                
                // Add event listeners
                setupAllNotificationsItemEvents();
            }
            
            // Delete a specific notification
            async function deleteNotification(id) {
                try {
                    const response = await fetch(`api/notifications.php?id=${id}`, {
                        method: 'DELETE'
                    });
                    const data = await response.json();
                    if (data.success) {
                        // Remove from local array
                        notifications = notifications.filter(n => n.id != id);
                        // Reload notifications to sync with server
                        await loadNotifications();
                        updateNotificationBadge();
                    }
                } catch (error) {
                    console.error('Error deleting notification:', error);
                }
            }

            // Remove all notifications related to a specific entry
            function removeNotificationsForEntry(entryId) {
                try {
                    const notifications = getNotifications();
                    const filtered = notifications.filter(n => {
                        // Remove if it references the entry explicitly
                        if (n.entryId && Number(n.entryId) === Number(entryId)) return false;
                        // Or if the notification id pattern ends with _<entryId>
                        if (typeof n.id === 'string' && n.id.endsWith(`_${entryId}`)) return false;
                        return true;
                    });
                    localStorage.setItem(NOTIFICATIONS_KEY, JSON.stringify(filtered));
                    // Update UI indicators
                    if (typeof updateNotificationBadge === 'function') {
                        updateNotificationBadge();
                    }
                    if (typeof updateNotificationDisplay === 'function') {
                        updateNotificationDisplay();
                    }
                } catch (e) {
                    console.error('Failed to remove notifications for entry', entryId, e);
                }
            }

            // "Clear all" in the modal: permanently delete all notifications
            async function clearOldNotifications() {
                try {
                    const currentNotifications = getNotifications();
                    
                    if (currentNotifications.length === 0) {
                        if (typeof showToast === 'function') {
                            showToast('No notifications to clear', 'info');
                        } else {
                            alert('No notifications to clear.');
                        }
                        return;
                    }
                    
                    let totalCleared = 0;
                    
                    // Helper to delete a batch of notifications
                    const deleteBatch = async (notifs) => {
                        if (!notifs || notifs.length === 0) return 0;
                        const deletePromises = notifs.map(notif =>
                            fetch(`api/notifications.php?id=${notif.id}`, {
                                method: 'DELETE'
                            }).then(res => res.json()).catch(() => ({ success: false }))
                        );
                        const results = await Promise.all(deletePromises);
                        return results.filter(r => r && r.success).length;
                    };
                    
                    // First deletion pass
                    totalCleared += await deleteBatch(currentNotifications);
                    
                    // Reload notifications to sync with server
                    await loadNotifications();
                    
                    // Safety second pass: if anything is still left, try once more
                    const remaining = getNotifications();
                    if (remaining && remaining.length > 0) {
                        totalCleared += await deleteBatch(remaining);
                        await loadNotifications();
                    }
                    
                    // Refresh modal + badge after all passes
                    await loadAllNotificationsIntoModal();
                    updateNotificationBadge();
                    
                    if (typeof showToast === 'function') {
                        showToast(`Cleared ${totalCleared} notification(s)`, 'success');
                    }
                } catch (error) {
                    console.error('Error clearing notifications:', error);
                    if (typeof showToast === 'function') {
                        showToast('Error clearing notifications', 'error');
                    }
                }
            }

            // Reconcile notifications with existing entries
            async function reconcileNotificationsWithEntries() {
                try {
                    const notifications = getNotifications();
                    const entries = (typeof getAllEntries === 'function') ? await getAllEntries() : [];
                    const existingIds = new Set(entries.map(e => Number(e.id)));
                    const filtered = notifications.filter(n => {
                        const relatedId = n.entryId ? Number(n.entryId) : (typeof n.id === 'string' && n.id.lastIndexOf('_') !== -1 ? Number(n.id.split('_').pop()) : NaN);
                        if (!isNaN(relatedId)) {
                            return existingIds.has(relatedId);
                        }
                        // Keep non-entry-specific notifications (if any)
                        return true;
                    });
                    localStorage.setItem(NOTIFICATIONS_KEY, JSON.stringify(filtered));
                    if (typeof updateNotificationBadge === 'function') updateNotificationBadge();
                    if (typeof updateNotificationDisplay === 'function') updateNotificationDisplay();
                } catch (e) {
                    console.error('Failed to reconcile notifications with entries', e);
                }
            }
        });
    </script>

    <!-- MOU Details Modal -->
    <div id="mouDetailsModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm hidden">
        <div class="w-full max-w-4xl bg-white dark:bg-background-dark rounded-xl shadow-2xl m-4 flex flex-col max-h-[90vh]">
            <!-- Modal Header -->
            <div class="p-6 border-b border-gray-200 dark:border-gray-800 flex-shrink-0">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 id="mouDetailsTitle" class="text-xl font-semibold text-gray-900 dark:text-white">MOU/MOA Details</h3>
                        <p id="mouDetailsSubtitle" class="text-sm text-gray-500 dark:text-gray-400 mt-1">Memorandum information</p>
                    </div>
                    <button id="closeMouDetails" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500 dark:text-gray-400">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6 flex-1 overflow-y-auto">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Left Column -->
                    <div class="space-y-6">
                        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary">business</span>
                                Institution Information
                            </h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Institution Name</label>
                                    <p id="detailInstitution" class="text-base text-gray-900 dark:text-white font-medium">-</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Location</label>
                                    <p id="detailLocation" class="text-base text-gray-900 dark:text-white">-</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Contact Details</label>
                                    <p id="detailContact" class="text-base text-gray-900 dark:text-white">-</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary">schedule</span>
                                Agreement Timeline
                            </h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Term Duration</label>
                                    <p id="detailTerm" class="text-base text-gray-900 dark:text-white">-</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Sign Date</label>
                                    <p id="detailSignDate" class="text-base text-gray-900 dark:text-white">-</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">End Date</label>
                                    <p id="detailEndDate" class="text-base text-gray-900 dark:text-white">-</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column -->
                    <div class="space-y-6">
                        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary">info</span>
                                Status Information
                            </h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Current Status</label>
                                    <span id="detailStatus" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium">-</span>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Days Until Expiry</label>
                                    <p id="detailDaysUntilExpiry" class="text-base text-gray-900 dark:text-white">-</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary">description</span>
                                Document Information
                            </h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Attached File</label>
                                    <div id="detailFileInfo" class="text-base text-gray-900 dark:text-white">-</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="p-6 bg-gray-50 dark:bg-background-dark/30 rounded-b-xl flex-shrink-0">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <button id="renewMouFromDetails" class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-green-500 rounded-lg hover:bg-green-600 hidden">
                            <span class="material-symbols-outlined text-sm">autorenew</span>
                            Renew MOU
                        </button>
                        <button id="editMouFromDetails" class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-primary bg-primary/10 rounded-lg hover:bg-primary/20">
                            <span class="material-symbols-outlined text-sm">edit</span>
                            Edit MOU
                        </button>
                        <button id="viewFileFromDetails" class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 hidden">
                            <span class="material-symbols-outlined text-sm">visibility</span>
                            View File
                        </button>
                    </div>
                    <!-- Footer close button removed (top-right X + Escape key handle closing) -->
                </div>
            </div>
        </div>
    </div>

    <!-- MOU Details Modal Functionality -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mouDetailsModal = document.getElementById('mouDetailsModal');
            const closeMouDetails = document.getElementById('closeMouDetails');
            const editMouFromDetails = document.getElementById('editMouFromDetails');
            const viewFileFromDetails = document.getElementById('viewFileFromDetails');
            const renewMouFromDetails = document.getElementById('renewMouFromDetails');
            
            let currentMouData = null;

            // Renew modal elements
            const renewMouModal = document.getElementById('renewMouModal');
            const closeRenewMouModal = document.getElementById('closeRenewMouModal');
            const renewCancelBtn = document.getElementById('renewCancelBtn');
            const renewConfirmBtn = document.getElementById('renewConfirmBtn');
            const renewSignDateInput = document.getElementById('renew-sign-date');
            const renewEndDateInput = document.getElementById('renew-end-date');
            const renewTermInput = document.getElementById('renew-term');
            const renewAutoStatusText = document.getElementById('renewAutoStatusText');

            let currentRenewMouData = null;

            function determineStatusForRenew(endDate) {
                if (window.__mouMoa && typeof window.__mouMoa.determineStatus === 'function') {
                    return window.__mouMoa.determineStatus(endDate);
                }
                if (!endDate) return 'Auto-determined';
                const today = new Date();
                const endDateObj = new Date(endDate);
                today.setHours(0, 0, 0, 0);
                endDateObj.setHours(0, 0, 0, 0);
                const daysUntilExpiry = Math.ceil((endDateObj - today) / (1000 * 60 * 60 * 24));
                if (endDateObj < today) return 'Expired';
                if (daysUntilExpiry <= 180) return 'Expires Soon';
                return 'Active';
            }

            function setRenewStatusDisplay(status) {
                if (!renewAutoStatusText) return;
                renewAutoStatusText.textContent = status || 'Auto-determined';
                if (status === 'Active') {
                    renewAutoStatusText.className = 'text-green-600 dark:text-green-400';
                } else if (status === 'Expired') {
                    renewAutoStatusText.className = 'text-red-600 dark:text-red-400';
                } else if (status === 'Expires Soon') {
                    renewAutoStatusText.className = 'text-yellow-600 dark:text-yellow-400';
                } else {
                    renewAutoStatusText.className = 'text-gray-500 dark:text-gray-400';
                }
            }

            function calculateRenewEndDate(signDate, term) {
                if (window.__mouMoa && typeof window.__mouMoa.calculateEndDate === 'function') {
                    return window.__mouMoa.calculateEndDate(signDate, term);
                }
                if (!signDate || !term) return null;

                let number, unit;
                const termMatch = term.match(/(\d+)\s*(year|years|month|months|day|days)/i);
                if (termMatch) {
                    number = parseInt(termMatch[1]);
                    unit = termMatch[2].toLowerCase();
                } else {
                    const numberMatch = term.match(/(\d+)/);
                    if (numberMatch) {
                        number = parseInt(numberMatch[1]);
                        unit = 'years';
                    } else {
                        return null;
                    }
                }

                const startDate = new Date(signDate);
                if (isNaN(startDate.getTime())) return null;
                const endDate = new Date(startDate);
                switch (unit) {
                    case 'year':
                    case 'years':
                        endDate.setFullYear(startDate.getFullYear() + number);
                        break;
                    case 'month':
                    case 'months':
                        endDate.setMonth(startDate.getMonth() + number);
                        break;
                    case 'day':
                    case 'days':
                        endDate.setDate(startDate.getDate() + number);
                        break;
                    default:
                        return null;
                }
                return endDate.toISOString().split('T')[0];
            }

            function openRenewModal(mouData) {
                if (!renewMouModal) return;
                currentRenewMouData = mouData;

                // Prefill fields (default to existing values)
                if (renewSignDateInput) renewSignDateInput.value = mouData.sign_date || '';
                if (renewTermInput) renewTermInput.value = mouData.term || '';

                // If we have sign_date + term but no end_date, compute; otherwise use existing end_date
                let endVal = mouData.end_date || '';
                if (!endVal && mouData.sign_date && mouData.term) {
                    endVal = calculateRenewEndDate(mouData.sign_date, mouData.term) || '';
                }
                if (renewEndDateInput) renewEndDateInput.value = endVal;

                // Update status display
                setRenewStatusDisplay(determineStatusForRenew(endVal));

                // Show modal (stack above details modal)
                renewMouModal.classList.remove('hidden');
                renewMouModal.style.display = 'flex';
                // Ensure it's above the details modal even if details modal was forced to a very high z-index
                renewMouModal.style.zIndex = '10010';

                // Auto-open native picker on genuine user click where supported
                if (renewSignDateInput) {
                    renewSignDateInput.addEventListener('click', function(event) {
                        // Guard against programmatic clicks, which are not considered a user gesture
                        if (!event.isTrusted) return;
                        if (typeof this.showPicker === 'function') {
                            try {
                                this.showPicker();
                            } catch (e) {
                                // Some browsers may still throw if they consider this non-user initiated
                                console.warn('showPicker could not be opened:', e);
                            }
                        }
                    }, { once: true });
                }
                if (renewEndDateInput) {
                    renewEndDateInput.addEventListener('click', function(event) {
                        if (!event.isTrusted) return;
                        if (typeof this.showPicker === 'function') {
                            try {
                                this.showPicker();
                            } catch (e) {
                                console.warn('showPicker could not be opened:', e);
                            }
                        }
                    }, { once: true });
                }
            }

            // Make renew modal opener globally accessible (used by notification "Renewed" flow)
            window.openRenewModal = openRenewModal;

            function closeRenewModal() {
                if (!renewMouModal) return;
                renewMouModal.classList.add('hidden');
                renewMouModal.style.display = 'none';
                currentRenewMouData = null;
            }

            async function confirmMouRenewalNotifications(entryId) {
                if (!entryId) return;
                try {
                    const response = await fetch('api/notifications.php');
                    const data = await response.json();
                    if (data.notifications && Array.isArray(data.notifications)) {
                        const mouNotifications = data.notifications.filter(
                            n => n.related_type === 'mou_moa' && n.related_id == entryId && !n.is_confirmed
                        );
                        for (const notif of mouNotifications) {
                            try {
                                const renewResponse = await fetch('api/notifications.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({
                                        action: 'confirm_mou_renewal',
                                        notification_id: notif.id,
                                        renewal_status: 'renewed'
                                    })
                                });
                                const renewData = await renewResponse.json();
                                if (renewData.success) {
                                    // Clean up localStorage when notification is renewed
                                    removeMouBarSeenId(notif.id);
                                } else {
                                    console.error('Failed to mark notification as renewed:', renewData.error);
                                }
                            } catch (error) {
                                console.error('Error marking notification as renewed:', error);
                            }
                        }
                    }
                } catch (error) {
                    console.error('Error fetching notifications:', error);
                }
            }

            // Renew modal event listeners
            if (closeRenewMouModal) closeRenewMouModal.addEventListener('click', closeRenewModal);
            if (renewCancelBtn) renewCancelBtn.addEventListener('click', closeRenewModal);
            if (renewMouModal) {
                renewMouModal.addEventListener('click', function(e) {
                    if (e.target === renewMouModal) closeRenewModal();
                });
            }

            function autoUpdateRenewEndAndStatus() {
                const signDate = renewSignDateInput ? renewSignDateInput.value : '';
                const term = renewTermInput ? renewTermInput.value.trim() : '';
                if (signDate && term && renewEndDateInput) {
                    const calculated = calculateRenewEndDate(signDate, term);
                    if (calculated) {
                        renewEndDateInput.value = calculated;
                    }
                }
                const endDate = renewEndDateInput ? renewEndDateInput.value : '';
                setRenewStatusDisplay(determineStatusForRenew(endDate));
            }

            if (renewSignDateInput) {
                renewSignDateInput.addEventListener('change', autoUpdateRenewEndAndStatus);
                renewSignDateInput.addEventListener('input', autoUpdateRenewEndAndStatus);
            }
            if (renewTermInput) {
                renewTermInput.addEventListener('change', autoUpdateRenewEndAndStatus);
                renewTermInput.addEventListener('input', autoUpdateRenewEndAndStatus);
            }
            if (renewEndDateInput) {
                renewEndDateInput.addEventListener('change', autoUpdateRenewEndAndStatus);
                renewEndDateInput.addEventListener('input', autoUpdateRenewEndAndStatus);
            }

            if (renewConfirmBtn) {
                renewConfirmBtn.addEventListener('click', async function() {
                    if (!currentRenewMouData || !currentRenewMouData.id) {
                        alert('Error: MOU/MOA ID not found');
                        return;
                    }
                    const signDate = renewSignDateInput ? renewSignDateInput.value : '';
                    const endDate = renewEndDateInput ? renewEndDateInput.value : '';
                    const term = renewTermInput ? renewTermInput.value.trim() : '';
                    if (!signDate || !endDate || !term) {
                        alert('Please fill in Sign Date, End Date, and Term.');
                        return;
                    }

                    const status = determineStatusForRenew(endDate);
                    const updatedEntry = {
                        ...currentRenewMouData,
                        sign_date: signDate,
                        end_date: endDate,
                        term: term,
                        status: status
                    };

                    try {
                        renewConfirmBtn.disabled = true;
                        renewConfirmBtn.textContent = 'Renewing...';

                        if (!window.__mouMoa || typeof window.__mouMoa.updateEntry !== 'function') {
                            throw new Error('Update function not available. Please refresh the page.');
                        }

                        await window.__mouMoa.updateEntry(updatedEntry, null);

                        if (window.__mouMoa && typeof window.__mouMoa.loadFromDatabase === 'function') {
                            await window.__mouMoa.loadFromDatabase();
                        }

                        // Refresh the details modal content behind
                        if (typeof window.showMouDetails === 'function') {
                            window.showMouDetails(updatedEntry);
                        }

                        // Mark related notifications as renewed (only after successful renew)
                        await confirmMouRenewalNotifications(updatedEntry.id);
                        if (window.loadNotifications) window.loadNotifications();
                        if (window.updateNotificationBadge) window.updateNotificationBadge();

                        closeRenewModal();
                    } catch (error) {
                        console.error('Renew failed:', error);
                        alert('Failed to renew MOU/MOA: ' + (error.message || 'Unknown error'));
                    } finally {
                        renewConfirmBtn.disabled = false;
                        renewConfirmBtn.textContent = 'Renew';
                    }
                });
            }
            
            // Function to show MOU details modal
            function showMouDetails(mouData) {
                console.log('showMouDetails called with data:', mouData);
                currentMouData = mouData;
                
                // Update modal content
                const titleEl = document.getElementById('mouDetailsTitle');
                const subtitleEl = document.getElementById('mouDetailsSubtitle');
                if (titleEl) titleEl.textContent = `${mouData.institution} - MOU/MOA Details`;
                if (subtitleEl) subtitleEl.textContent = `Agreement with ${mouData.institution}`;
                
                // Fill in the details
                const instEl = document.getElementById('detailInstitution');
                const locEl = document.getElementById('detailLocation');
                const contactEl = document.getElementById('detailContact');
                if (instEl) instEl.textContent = mouData.institution || '-';
                if (locEl) locEl.textContent = mouData.location || '-';
                if (contactEl) contactEl.textContent = mouData.contact_email || 'No contact information';
                
                // Format term duration to always include proper unit
                let termText = mouData.term || '-';
                if (termText !== '-' && termText) {
                    // If it's just a number, add appropriate unit
                    if (/^\d+$/.test(termText.trim())) {
                        const number = parseInt(termText.trim());
                        termText = number === 1 ? '1 year' : number + ' years';
                    }
                    // If it already has a unit, keep it as is
                }
                const termEl = document.getElementById('detailTerm');
                if (termEl) termEl.textContent = termText;
                
                const signDateEl = document.getElementById('detailSignDate');
                const endDateEl = document.getElementById('detailEndDate');
                if (signDateEl) signDateEl.textContent = mouData.sign_date ? formatDate(mouData.sign_date) : '-';
                if (endDateEl) endDateEl.textContent = mouData.end_date ? formatDate(mouData.end_date) : '-';
                
                // Update status with appropriate styling
                const statusElement = document.getElementById('detailStatus');
                statusElement.textContent = mouData.status || '-';
                
                // Remove existing status classes
                statusElement.className = 'inline-flex items-center px-3 py-1 rounded-full text-sm font-medium';
                
                // Add status-specific classes
                if (mouData.status === 'Active') {
                    statusElement.classList.add('bg-green-100', 'text-green-800', 'dark:bg-green-900/50', 'dark:text-green-300');
                } else if (mouData.status === 'Expired') {
                    statusElement.classList.add('bg-red-100', 'text-red-800', 'dark:bg-red-900/50', 'dark:text-red-300');
                } else if (mouData.status === 'Expires Soon') {
                    statusElement.classList.add('bg-yellow-100', 'text-yellow-800', 'dark:bg-yellow-900/50', 'dark:text-yellow-300');
                } else {
                    statusElement.classList.add('bg-gray-100', 'text-gray-800', 'dark:bg-gray-900/50', 'dark:text-gray-300');
                }
                
                // Calculate days until expiry
                if (mouData.end_date) {
                    const today = new Date();
                    const endDate = new Date(mouData.end_date);
                    const daysUntilExpiry = Math.ceil((endDate - today) / (1000 * 60 * 60 * 24));
                    
                    let expiryText;
                    if (daysUntilExpiry < 0) {
                        expiryText = `Expired ${Math.abs(daysUntilExpiry)} days ago`;
                    } else if (daysUntilExpiry === 0) {
                        expiryText = 'Expires today';
                    } else if (daysUntilExpiry === 1) {
                        expiryText = 'Expires tomorrow';
                    } else {
                        expiryText = `${daysUntilExpiry} days remaining`;
                    }
                    
                    document.getElementById('detailDaysUntilExpiry').textContent = expiryText;
                } else {
                    document.getElementById('detailDaysUntilExpiry').textContent = '-';
                }
                
                // Handle file information - display all files
                const fileInfoElement = document.getElementById('detailFileInfo');
                const viewFileBtn = document.getElementById('viewFileFromDetails');

                if (fileInfoElement) {
                    // Clear any previous content safely
                    fileInfoElement.innerHTML = '';
                }

                // Get all files from mouData.files array or fallback to single file
                const allFiles = mouData.files && Array.isArray(mouData.files) && mouData.files.length > 0 
                    ? mouData.files 
                    : (mouData.file_name && mouData.file_path ? [{
                        file_name: mouData.file_name,
                        file_path: mouData.file_path,
                        is_primary: 1
                    }] : []);

                if (allFiles.length > 0 && fileInfoElement) {
                    // Create a container for all files
                    const filesContainer = document.createElement('div');
                    filesContainer.className = 'space-y-2';

                    allFiles.forEach((file, index) => {
                        const row = document.createElement('div');
                        row.className = 'flex items-center justify-between gap-3 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700/50';

                        const left = document.createElement('div');
                        left.className = 'flex items-center gap-2 min-w-0 flex-1';

                        const docIcon = document.createElement('span');
                        docIcon.className = 'material-symbols-outlined text-primary flex-shrink-0';
                        docIcon.textContent = 'description';

                        const nameContainer = document.createElement('div');
                        nameContainer.className = 'min-w-0 flex-1';

                        const nameSpan = document.createElement('span');
                        nameSpan.className = 'font-medium truncate block text-sm';
                        nameSpan.textContent = file.file_name || file.file_name;
                        nameSpan.title = file.file_name || file.file_name;

                        // Add primary badge if this is the primary file
                        if (file.is_primary === 1 || (index === 0 && allFiles.length > 1)) {
                            const primaryBadge = document.createElement('span');
                            primaryBadge.className = 'ml-2 px-2 py-0.5 text-xs font-medium bg-primary/20 text-primary rounded';
                            primaryBadge.textContent = 'Primary';
                            nameContainer.appendChild(primaryBadge);
                        }

                        nameContainer.insertBefore(nameSpan, nameContainer.firstChild);
                        left.appendChild(docIcon);
                        left.appendChild(nameContainer);

                        const inlineViewBtn = document.createElement('button');
                        inlineViewBtn.type = 'button';
                        inlineViewBtn.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-primary bg-primary/10 rounded-lg hover:bg-primary/20 flex-shrink-0';

                        const viewIcon = document.createElement('span');
                        viewIcon.className = 'material-symbols-outlined text-base';
                        viewIcon.textContent = 'visibility';

                        const viewText = document.createElement('span');
                        viewText.textContent = 'View';

                        inlineViewBtn.appendChild(viewIcon);
                        inlineViewBtn.appendChild(viewText);
                        inlineViewBtn.onclick = () => showFileViewer(file.file_path, file.file_name);

                        row.appendChild(left);
                        row.appendChild(inlineViewBtn);
                        filesContainer.appendChild(row);
                    });

                    fileInfoElement.appendChild(filesContainer);

                    // Set primary file for the main view button
                    const primaryFile = allFiles.find(f => f.is_primary === 1) || allFiles[0];
                    if (viewFileBtn && primaryFile) {
                        viewFileBtn.classList.remove('hidden');
                        viewFileBtn.onclick = () => showFileViewer(primaryFile.file_path, primaryFile.file_name);
                    }
                } else if (fileInfoElement) {
                    fileInfoElement.textContent = 'No file attached';
                    if (viewFileBtn) viewFileBtn.classList.add('hidden');
                }

                // Show/hide edit button based on admin status
                const editBtn = document.getElementById('editMouFromDetails');
                if (window.IS_ADMIN) {
                    editBtn.classList.remove('hidden');
                } else {
                    editBtn.classList.add('hidden');
                }
                
                // Local function to determine status
                function determineStatusLocal(endDate) {
                    if (!endDate) {
                        return 'Auto-determined';
                    }
                    
                    const today = new Date();
                    const endDateObj = new Date(endDate);
                    
                    // Set time to start of day for accurate comparison
                    today.setHours(0, 0, 0, 0);
                    endDateObj.setHours(0, 0, 0, 0);
                    
                    // Calculate days until expiry
                    const daysUntilExpiry = Math.ceil((endDateObj - today) / (1000 * 60 * 60 * 24));
                    
                    if (endDateObj < today) {
                        return 'Expired';
                    } else if (daysUntilExpiry <= 30) {
                        return 'Expires Soon';
                    } else {
                        return 'Active';
                    }
                }
                
                // Show/hide Renew button for expired or expiring MOU/MOA
                const renewBtn = document.getElementById('renewMouFromDetails');
                const status = determineStatusLocal(mouData.end_date);
                if (renewBtn && (status === 'Expired' || status === 'Expires Soon')) {
                    renewBtn.classList.remove('hidden');
                } else if (renewBtn) {
                    renewBtn.classList.add('hidden');
                }
                
                // Set up Renew button handler
                if (renewBtn) {
                    renewBtn.onclick = () => openRenewModal(mouData);
                }
                
                // Set up edit button with better error handling
                editMouFromDetails.onclick = () => {
                    console.log('Edit MOU button clicked for ID:', mouData.id);

                    // Check if user is admin
                    if (!window.IS_ADMIN) {
                        alert('You do not have permission to edit entries. Admin access required.');
                        return;
                    }

                    // Keep the details modal open but open edit modal on top (higher z-index)
                    // The edit modal will appear in front of the details modal
                    (async () => {
                        try {
                            // Get the entry data from API
                            const entries = await getAllEntries();
                            const entryToEdit = entries.find(entry => entry.id === mouData.id);

                            if (!entryToEdit) {
                                console.error('Entry not found for ID:', mouData.id);
                                alert('Entry not found. Please refresh the page and try again.');
                                return;
                            }
                            
                            console.log('Found entry to edit:', entryToEdit);
                            
                            // Store the ID for editing
                            window.editingEntryId = mouData.id;
                            
                            // Pre-fill the modal with existing data
                            try {
                                const institutionField = document.getElementById('institution');
                                const locationField = document.getElementById('location');
                                const contactField = document.getElementById('contact');
                                const termField = document.getElementById('term');
                                const categoryField = document.getElementById('category');
                                const signDateField = document.getElementById('sign-date');
                                const endDateField = document.getElementById('end-date');
                                
                                if (institutionField) institutionField.value = entryToEdit.institution || '';
                                if (locationField) locationField.value = entryToEdit.location || '';
                                if (contactField) contactField.value = entryToEdit.contact_email || '';
                                if (termField) termField.value = entryToEdit.term || '';
                                if (categoryField) {
                                    const normalizeCategory = (window.__mouMoa && typeof window.__mouMoa.normalizeCategoryForSelect === 'function')
                                        ? window.__mouMoa.normalizeCategoryForSelect
                                        : (v) => (v ? String(v) : '');
                                    categoryField.value = normalizeCategory(entryToEdit.category);
                                }
                                
                                // Initialize date pickers (if available here) and set values
                                setTimeout(() => {
                                    if (typeof initDatePickers === 'function') initDatePickers();
                                    const normalize = (window.__mouMoa && typeof window.__mouMoa.normalizeDateForInput === 'function')
                                        ? window.__mouMoa.normalizeDateForInput
                                        : (v) => (v ? String(v) : '');
                                    if (signDateField) signDateField.value = normalize(entryToEdit.sign_date);
                                    if (endDateField) endDateField.value = normalize(entryToEdit.end_date);
                                }, 0);
                                
                                console.log('Form fields populated successfully from details modal');
                            } catch (error) {
                                console.error('Error populating form fields from details modal:', error);
                            }
                            
                            // Set the detected document type for editing
                            if (entryToEdit.category) {
                                window.detectedDocumentType = entryToEdit.category;
                                // Directly update category display without function dependency
                                const autoCategoryText = document.getElementById('autoCategoryText');
                                if (autoCategoryText) {
                                    autoCategoryText.textContent = `Auto-detected: ${entryToEdit.category}`;
                                    autoCategoryText.className = 'text-primary-600 dark:text-primary-400';
                                }
                            } else {
                                // Reset category display
                                const autoCategoryText = document.getElementById('autoCategoryText');
                                if (autoCategoryText) {
                                    autoCategoryText.textContent = 'Auto-detected';
                                    autoCategoryText.className = 'text-gray-500 dark:text-gray-400';
                                }
                            }
                            
                            // Update status display
                            const status = typeof determineStatusLocal === 'function' ? determineStatusLocal(entryToEdit.end_date) : 'Active';
                            const autoStatusText = document.getElementById('autoStatusText');
                            if (autoStatusText) {
                                autoStatusText.textContent = status;
                                
                                // Update text color based on status
                                if (status === 'Active') {
                                    autoStatusText.className = 'text-green-600 dark:text-green-400';
                                } else if (status === 'Expired') {
                                    autoStatusText.className = 'text-red-600 dark:text-red-400';
                                } else if (status === 'Expires Soon') {
                                    autoStatusText.className = 'text-yellow-600 dark:text-yellow-400';
                                } else {
                                    autoStatusText.className = 'text-gray-500 dark:text-gray-400';
                                }
                            }
                            
                            // Update modal title and button text
                            const modalTitle = document.querySelector('#addFileModal h3');
                            const saveBtn = document.getElementById('saveBtn');
                            if (modalTitle) modalTitle.textContent = 'Edit MOU/MOA File';
                            if (saveBtn) saveBtn.textContent = 'Update';
                            
                            // Show the edit modal
                            const modal = document.getElementById('addFileModal');
                            if (modal) {
                                modal.classList.remove('hidden');
                                console.log('Edit modal opened successfully');
                            } else {
                                console.error('Edit modal not found');
                                alert('Edit modal not found. Please refresh the page.');
                            }
                            
                        } catch (error) {
                            console.error('Error opening edit modal:', error);
                            alert('Error opening edit form: ' + error.message);
                        }
                    })();
                };
                
                // Show modal - ensure it's visible
                console.log('Removing hidden class from modal');
                mouDetailsModal.classList.remove('hidden');
                mouDetailsModal.style.display = 'flex'; // Ensure flex display
                mouDetailsModal.style.visibility = 'visible';
                mouDetailsModal.style.opacity = '1';
                console.log('Modal classes after show:', mouDetailsModal.className);
                console.log('Modal computed display:', window.getComputedStyle(mouDetailsModal).display);
            }
            
            // Function to format date
            function formatDate(dateString) {
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
            }
            
            // Close modal functions
            function closeMouDetailsModal() {
                mouDetailsModal.classList.add('hidden');
                mouDetailsModal.style.display = 'none';
                currentMouData = null;
            }
            
            // Event listeners
            closeMouDetails.addEventListener('click', closeMouDetailsModal);
            
            // Close modal when clicking outside
            mouDetailsModal.addEventListener('click', function(e) {
                if (e.target === mouDetailsModal) {
                    closeMouDetailsModal();
                }
            });
            
            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && mouDetailsModal && !mouDetailsModal.classList.contains('hidden')) {
                    closeMouDetailsModal();
                }
            });
            
            // Make showMouDetails globally accessible
            window.showMouDetails = showMouDetails;
            
            // Check for pending entry ID from URL parameter (set early in page load)
            // This will open the modal when the page is ready
            function checkAndOpenEntry() {
                const entryId = window.pendingEntryId;
                if (!entryId) return;
                
                console.log('Opening MOU/MOA entry from notification:', entryId);
                
                // Make sure modal and function are ready
                const modal = document.getElementById('mouDetailsModal');
                if (!modal) {
                    console.log('Modal not ready yet, retrying...');
                    setTimeout(checkAndOpenEntry, 200);
                    return;
                }
                
                if (typeof showMouDetails !== 'function') {
                    console.log('showMouDetails not ready yet, retrying...');
                    setTimeout(checkAndOpenEntry, 200);
                    return;
                }
                
                // Clear the pending ID and URL parameter
                delete window.pendingEntryId;
                const newUrl = window.location.pathname;
                window.history.replaceState({}, '', newUrl);
                
                // Fetch and open the entry
                (async function() {
                    try {
                        console.log('Fetching entry data for ID:', entryId);
                        const response = await fetch(`api/mou-moa.php?action=get&id=${entryId}`);
                        const result = await response.json();
                        
                        console.log('API response:', result);
                        
                        if (result.success && result.data) {
                            console.log('Opening MOU details modal with data:', result.data);
                            
                            // First, populate the modal content manually to ensure it's ready
                            try {
                                const titleEl = document.getElementById('mouDetailsTitle');
                                const subtitleEl = document.getElementById('mouDetailsSubtitle');
                                if (titleEl) titleEl.textContent = `${result.data.institution || 'MOU/MOA'} - Details`;
                                if (subtitleEl) subtitleEl.textContent = `Agreement with ${result.data.institution || 'Partner'}`;
                                
                                const instEl = document.getElementById('detailInstitution');
                                const locEl = document.getElementById('detailLocation');
                                const contactEl = document.getElementById('detailContact');
                                if (instEl) instEl.textContent = result.data.institution || '-';
                                if (locEl) locEl.textContent = result.data.location || '-';
                                if (contactEl) contactEl.textContent = result.data.contact_email || 'No contact information';
                            } catch (e) {
                                console.warn('Error populating modal fields:', e);
                            }
                            
                            // Call the function to show the modal
                            console.log('About to call showMouDetails...');
                            if (typeof showMouDetails === 'function') {
                                console.log('Calling showMouDetails function');
                                showMouDetails(result.data);
                                console.log('showMouDetails called');
                            } else {
                                console.error('showMouDetails function not found!');
                            }

                            // If we came from a "Renewed" notification intent, open the Renew modal immediately
                            if (window.pendingRenewModal && typeof openRenewModal === 'function') {
                                try {
                                    openRenewModal(result.data);
                                } catch (e) {
                                    console.warn('Failed to auto-open renew modal:', e);
                                }
                                window.pendingRenewModal = false;
                                // Clear stored intent (best-effort)
                                try {
                                    sessionStorage.removeItem('mouRenewal:open');
                                    sessionStorage.removeItem('mouRenewal:entryId');
                                    sessionStorage.removeItem('mouRenewal:notificationId');
                                } catch (_) {}
                            }
                            
                            // IMPORTANT: Force modal to be visible immediately
                            console.log('Forcing modal to be visible...');
                            modal.classList.remove('hidden');
                            modal.style.display = 'flex';
                            modal.style.visibility = 'visible';
                            modal.style.opacity = '1';
                            modal.style.zIndex = '9999';
                            console.log('Modal forced visible. Hidden class removed:', !modal.classList.contains('hidden'));
                            
                            // Double-check after a short delay to ensure it stays visible
                            setTimeout(() => {
                                console.log('Double-checking modal visibility...');
                                if (modal.classList.contains('hidden')) {
                                    console.log('Modal was hidden again, forcing show...');
                                    modal.classList.remove('hidden');
                                }
                                modal.style.display = 'flex';
                                modal.style.visibility = 'visible';
                                modal.style.opacity = '1';
                                modal.style.zIndex = '9999';
                                
                                const computedStyle = window.getComputedStyle(modal);
                                console.log('Modal display:', computedStyle.display);
                                console.log('Modal visibility:', computedStyle.visibility);
                                console.log('Modal opacity:', computedStyle.opacity);
                                console.log('Modal z-index:', computedStyle.zIndex);
                                
                                // If still not visible, try one more time
                                if (computedStyle.display === 'none' || modal.classList.contains('hidden')) {
                                    console.log('Modal still not visible, trying one more time...');
                                    modal.classList.remove('hidden');
                                    modal.style.display = 'flex';
                                    modal.style.visibility = 'visible';
                                    modal.style.opacity = '1';
                                }
                            }, 200);
                            
                            // Highlight the entry
                            setTimeout(() => {
                                const entryIdNum = parseInt(entryId);
                                if (typeof highlightEntry === 'function') {
                                    highlightEntry(entryIdNum);
                                }
                            }, 300);
                        } else {
                            console.error('Failed to load entry:', result.error);
                            alert('Failed to load MOU/MOA entry: ' + (result.error || 'Unknown error'));
                        }
                    } catch (error) {
                        console.error('Error loading entry:', error);
                        alert('Error loading MOU/MOA entry: ' + error.message);
                    }
                })();
            }
            
            // Start checking after a short delay
            setTimeout(checkAndOpenEntry, 500);
            // Also check periodically in case things load slowly
            const checkInterval = setInterval(() => {
                if (window.pendingEntryId && document.getElementById('mouDetailsModal') && typeof showMouDetails === 'function') {
                    clearInterval(checkInterval);
                    checkAndOpenEntry();
                }
            }, 200);
            
            // Stop checking after 10 seconds
            setTimeout(() => clearInterval(checkInterval), 10000);
            
            // Add click event listeners to table rows
            function addRowClickListener(row, mouData) {
                row.addEventListener('click', function(e) {
                    // Don't trigger if clicking on buttons, checkboxes, or links
                    if (e.target.tagName === 'BUTTON' || 
                        e.target.tagName === 'INPUT' || 
                        e.target.tagName === 'A' ||
                        e.target.closest('button') ||
                        e.target.closest('input') ||
                        e.target.closest('a')) {
                        return;
                    }
                    
                    // Show MOU details
                    showMouDetails(mouData);
                });
            }
            
            // Make addRowClickListener globally accessible
            window.addRowClickListener = addRowClickListener;
        });

        // Location field is now a simple text input - no autocomplete functionality

        // Load profile picture from localStorage if available
        document.addEventListener('DOMContentLoaded', function() {
            try {
                const profile = JSON.parse(localStorage.getItem('lilac_profile'));
                if (profile && profile.avatar) {
                    const sidebarProfilePicture = document.querySelector('.sidebar-profile-picture');
                    if (sidebarProfilePicture) {
                        sidebarProfilePicture.style.backgroundImage = `url('${profile.avatar}')`;
                    }
                    
                    // Also update the profile name if available
                    const profileName = document.querySelector('.sidebar-profile-info p');
                    if (profileName && profile.name) {
                        profileName.textContent = profile.name;
                    }
                }
            } catch (error) {
                console.log('No profile data found in localStorage');
            }
        });
    </script>

<script>
const API_BASE = 'api/mou.php';
const AUTH_TOKEN = '<?php echo $token; ?>';

window.uploadMOU = async function(formElement) {
    const formData = new FormData();
    const fileInput = formElement.querySelector('input[type="file"]');
    const titleInput = formElement.querySelector('input[name="title"]');
    const partnerInput = formElement.querySelector('input[name="partner"]');
    const typeInput = formElement.querySelector('select[name="type"]');
    const dateInput = formElement.querySelector('input[name="date"]');
    const descInput = formElement.querySelector('textarea[name="description"]');

    if (!fileInput.files[0]) {
        alert('Please select a file');
        return false;
    }

    formData.append('file', fileInput.files[0]);
    formData.append('title', titleInput.value);
    formData.append('partner', partnerInput.value);
    formData.append('type', typeInput.value);
    formData.append('date', dateInput.value);
    formData.append('description', descInput.value);

    try {
        const response = await fetch(API_BASE + '?action=upload', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + AUTH_TOKEN
            },
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            alert('✓ MOU/MOA uploaded successfully!');
            formElement.reset();
            if (typeof loadMOUs === 'function') loadMOUs();
            return true;
        } else {
            alert('✗ Error: ' + (result.error || 'Upload failed'));
            return false;
        }
    } catch (error) {
        alert('✗ Error: ' + error.message);
        return false;
    }
};

window.loadMOUs = async function() {
    try {
        const response = await fetch(API_BASE + '?action=list', {
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + AUTH_TOKEN
            }
        });

        const result = await response.json();

        if (result.success && result.mous) {
            renderMOUs(result.mous);
        }
    } catch (error) {
        console.error('Load MOUs error:', error);
    }
};

window.deleteMOU = async function(mouId) {
    if (!confirm('Are you sure you want to delete this MOU/MOA?')) return;

    try {
        const response = await fetch(API_BASE + '?action=delete&id=' + mouId, {
            method: 'DELETE',
            headers: {
                'Authorization': 'Bearer ' + AUTH_TOKEN
            }
        });

        const result = await response.json();

        if (result.success) {
            alert('✓ MOU/MOA deleted successfully');
            if (typeof loadMOUs === 'function') loadMOUs();
        } else {
            alert('✗ Error: ' + (result.error || 'Delete failed'));
        }
    } catch (error) {
        alert('✗ Error: ' + error.message);
    }
};

window.viewMOUDetails = async function(mouId) {
    try {
        const response = await fetch(API_BASE + '?action=view&id=' + mouId, {
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + AUTH_TOKEN
            }
        });

        const result = await response.json();

        if (result.success && result.mou) {
            showMOUModal(result.mou);
        } else {
            alert('✗ Error: Unable to load MOU/MOA details');
        }
    } catch (error) {
        alert('✗ Error: ' + error.message);
    }
};

function renderMOUs(mous) {
    const container = document.getElementById('mousContainer');
    if (!container) return;

    if (mous.length === 0) {
        container.innerHTML = '<p class="text-center text-text-muted-light dark:text-text-muted-dark">No MOUs/MOAs found</p>';
        return;
    }

    container.innerHTML = mous.map(mou => `
        <div class="mou-card p-4 border border-border-light dark:border-border-dark rounded-lg">
            <h3 class="font-semibold">${escapeHtml(mou.title)}</h3>
            <p class="text-sm text-text-muted-light dark:text-text-muted-dark">${escapeHtml(mou.partner || '')}</p>
            <p class="text-xs text-text-muted-light dark:text-text-muted-dark mt-1">${mou.type || ''} - ${mou.date || ''}</p>
            <div class="mt-2 flex gap-2">
                <button onclick="viewMOUDetails(${mou.id})" class="text-sm text-primary hover:underline">View</button>
                <button onclick="deleteMOU(${mou.id})" class="text-sm text-red-500 hover:underline">Delete</button>
            </div>
        </div>
    `).join('');
}

function showMOUModal(mou) {
    console.log('MOU details:', mou);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.getElementById('addMOUForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    uploadMOU(this);
});
</script>
</body></html>



