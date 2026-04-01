<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
$user = $_SESSION['user'];
$token = $_SESSION['token'];
$isAdmin = $user['role'] === 'admin';

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
        $stmt->execute([$user['id']]);
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

$schedules = [];
try {
    $pdo = getDatabaseConnection();
    if ($pdo instanceof FileBasedDatabase) {
        $dataDir = __DIR__ . '/data/schedules/';
        if (is_dir($dataDir)) {
            $files = glob($dataDir . '*.json');
            foreach ($files as $file) {
                $data = json_decode(file_get_contents($file), true);
                if ($data) $schedules[] = $data;
            }
        }
    } else {
        if ($isAdmin) {
            $stmt = $pdo->query('SELECT s.*, u.username as created_by FROM schedules s LEFT JOIN users u ON s.user_id = u.id ORDER BY s.scheduled_date DESC');
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->prepare('SELECT s.*, u.username as created_by FROM schedules s LEFT JOIN users u ON s.user_id = u.id WHERE s.user_id = ? ORDER BY s.scheduled_date DESC');
            $stmt->execute([$user['id']]);
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Exception $e) {
    error_log('Schedules load error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>LILAC Calendar</title>
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
<script src="js/notification-bar.js"></script>
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .material-symbols-outlined.filled {
            font-variation-settings: 'FILL' 1;
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
        }
        .sidebar-collapsed .sidebar {
            width: 5rem;
            min-width: 5rem;
            max-width: 5rem;
        }
        .sidebar-collapsed .sidebar-profile-info {
            display: none;
        }
        .sidebar-collapsed .sidebar-profile-picture {
            display: none;
        }
        /* Add styles for expanded sidebar */
        .sidebar-expanded .sidebar-profile-picture {
            display: block;
        }
        .sidebar-expanded .sidebar-profile-info {
            display: block;
        }
        main {
            flex: 1;
            position: relative;
            z-index: 10;
        }
        .sidebar-collapsed main {
            margin-left: 5rem !important;
        }
        .sidebar-expanded main {
            margin-left: 16rem !important;
        }
        .main-content {
            padding-left: 0;
        }
        /* Restore navbar original offset without affecting main content */
        .sidebar-collapsed header {
            margin-left: 2rem;
        }
        .sidebar-expanded header {
            margin-left: 2rem !important;
        }
        .sidebar-collapsed .sidebar-toggle-icon-open {
            display: none;
        }
        .sidebar-collapsed .sidebar-toggle-icon-closed {
            display: block;
        }
        .sidebar-toggle-icon-closed {
            display: none;
        }
        .sidebar-collapsed .sidebar-nav-link {
            justify-content: center;
        }
        .sidebar-collapsed .sidebar-toggle-container {
            justify-content: center;
        }
        .sidebar-collapsed .profile-container {
            justify-content: center;
        }
        .sidebar {
            width: 16rem;
            min-width: 16rem;
            max-width: 16rem;
            flex-shrink: 0;
        }
        .sidebar-collapsed .sidebar {
            width: 5rem;
            min-width: 5rem;
            max-width: 5rem;
        }
        .sidebar-collapsed .sidebar-profile-info {
            display: none;
        }
        .sidebar-collapsed .sidebar-profile-picture {
            display: none;
        }

        .page-animate,
        .page-animate-delay-1,
        .page-animate-delay-2,
        .header-animate,
        .content-animate {
            opacity: 1 !important;
            animation: none !important;
        }
        /* Draggable modal helpers */
        #createModal.dragging { align-items: flex-start; justify-content: flex-start; }
        #createModalHeader { cursor: move; }
        .dragging * { user-select: none; }

        /* Create Event Modal - ensure dark mode styling (override forms plugin and any white backgrounds) */
        .dark #createModalCard {
            background-color: #1e293b !important;
            border-color: #334155 !important;
        }
        .dark #createModalCard .flex-1.overflow-y-auto {
            background-color: transparent;
        }
        .dark #createModalHeader {
            border-color: #334155 !important;
        }
        .dark #createModalCard > div:last-child {
            background-color: #1e293b !important;
            border-color: #334155 !important;
        }
        /* Override @tailwindcss/forms white backgrounds on inputs inside create modal */
        .dark #createModalCard input[type="text"],
        .dark #createModalCard select,
        .dark #createModalCard textarea {
            background-color: rgba(30, 41, 59, 0.5) !important;
            border-color: #475569 !important;
            color: #e2e8f0 !important;
        }
        .dark #createModalCard input::placeholder,
        .dark #createModalCard textarea::placeholder {
            color: #94a3b8 !important;
        }

        /* Time Picker Modal - center on screen and ensure dark mode styling */
        #timePickerModal {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            left: 0 !important;
            right: 0 !important;
            top: 0 !important;
            bottom: 0 !important;
        }
        /* When Upcoming Event modal is open: only show that modal - hide everything else that could overlap */
        body.attendance-modal-open #timePickerModal,
        body.attendance-modal-open #datePickerModal,
        body.attendance-modal-open #createModal,
        body.attendance-modal-open #calendarGrid {
            display: none !important;
            visibility: hidden !important;
            pointer-events: none !important;
            opacity: 0 !important;
        }
        /* Ensure attendance modal content is isolated and only shows: event icon, title, subtitle, date/time, question, No, Yes */
        #eventAttendanceModal #attendanceModalContent,
        #eventAttendanceModal .p-6 {
            overflow: hidden !important;
        }
        #eventAttendanceModal #attendanceModalButtons {
            flex-wrap: nowrap !important;
        }
        #timePickerModal > div {
            margin-left: auto !important;
            margin-right: auto !important;
        }
        .dark #timePickerModal > div {
            background-color: #1e293b !important;
            border-color: #334155 !important;
        }
        .dark #timePickerModal input#timeInput,
        .dark #timePickerModal select {
            background-color: rgba(30, 41, 59, 0.5) !important;
            border-color: #475569 !important;
            color: #e2e8f0 !important;
        }
        #timePickerModal select {
            text-align: center;
            text-align-last: center;
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            background-image: none !important;
            background-position: unset !important;
            background-repeat: unset !important;
            background-size: unset !important;
        }
        

        /* Custom scrollbar styling for all elements */
        * {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f1f5f9;
        }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; }

        /* Firefox scrollbar styling */
        html {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f1f5f9;
        }

        /* Dark mode Firefox scrollbar */
        .dark * {
            scrollbar-color: #475569 #1e293b;
        }

        .dark html {
            scrollbar-color: #475569 #1e293b;
        }

        /* WebKit scrollbar styling (Chrome, Safari, Edge) */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
            transition: background-color 0.2s ease;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        ::-webkit-scrollbar-corner {
            background: #f1f5f9;
        }

        /* Dark mode WebKit scrollbar styling */
        .dark ::-webkit-scrollbar-track {
            background: #1e293b;
        }

        .dark ::-webkit-scrollbar-thumb {
            background: #475569;
        }

        .dark ::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }

        .dark ::-webkit-scrollbar-corner {
            background: #1e293b;
        }

        /* Fix for active sidebar link in dark mode - ensure dark gradient overrides light gradient */
        .dark .sidebar-nav-link.bg-gradient-to-r {
            background-image: linear-gradient(to right, rgba(88, 28, 135, 0.4), rgba(67, 56, 202, 0.4)) !important;
        }
  </style>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-text-light dark:text-text-dark">
<div class="flex h-screen sidebar-collapsed" id="app-container">
<aside class="sidebar bg-card-light dark:bg-card-dark border-r border-border-light dark:border-border-dark flex flex-col fixed h-full z-50 transition-all duration-300">
<div class="flex items-center justify-start px-4 h-20 border-b border-border-light dark:border-border-dark flex-shrink-0">
<div class="flex items-center gap-3 overflow-hidden">
<img alt="CPU LILAC Logo" class="h-11 w-11 flex-shrink-0" src="./api/get-logo.php?v=1" width="44" height="44" onerror="this.style.display='none'; document.getElementById('logo-fallback').style.display='flex'; console.error('Logo failed to load:', this.src);"/>
<div class="h-11 w-11 bg-primary rounded-lg flex items-center justify-center text-white font-bold text-sm flex-shrink-0" style="display: none;" id="logo-fallback">CPU</div>
<h1 class="text-xl font-bold text-text-light dark:text-text-dark sidebar-logo-text whitespace-nowrap">LILAC</h1>
</div>
</div>
<nav class="flex-1 px-4 py-6 space-y-2">
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
<a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-600 dark:hover:text-purple-400 transition-colors duration-200 sidebar-nav-link" href="mobility-programs.php" title="Mobility Programs">
<span class="material-symbols-outlined flex-shrink-0">map</span>
<span class="sidebar-text whitespace-nowrap">Mobility Programs</span>
</a>
<a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-600 dark:hover:text-purple-400 transition-colors duration-200 sidebar-nav-link" href="events-activities.php" title="Events & Activities">
<span class="material-symbols-outlined flex-shrink-0">event</span>
<span class="sidebar-text whitespace-nowrap">Events &amp; Activities</span>
</a>
<a class="flex items-center gap-3 px-4 py-2.5 rounded-lg bg-gradient-to-r from-purple-50 to-indigo-50 dark:from-purple-900/40 dark:to-indigo-900/40 text-purple-600 dark:text-purple-400 font-semibold sidebar-nav-link border border-purple-200 dark:border-purple-800 shadow-sm" href="scheduler.php" title="Scheduler">
<span class="material-symbols-outlined filled flex-shrink-0">calendar_today</span>
<span class="sidebar-text whitespace-nowrap">Scheduler</span>
</a>
<a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-600 dark:hover:text-purple-400 transition-colors duration-200 sidebar-nav-link" href="mou-moa.php" title="MOUs & MOAs">
<span class="material-symbols-outlined flex-shrink-0">handshake</span>
<span class="sidebar-text whitespace-nowrap">MOUs &amp; MOAs</span>
</a>

<a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-600 dark:hover:text-purple-400 transition-colors duration-200 sidebar-nav-link" href="documents.php" title="Documents">
<span class="material-symbols-outlined flex-shrink-0">description</span>
<span class="sidebar-text whitespace-nowrap">Documents</span>
</a>
<a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-600 dark:hover:text-purple-400 transition-colors duration-200 sidebar-nav-link" href="forms.php" title="Forms">
<span class="material-symbols-outlined flex-shrink-0">edit_note</span>
<span class="sidebar-text whitespace-nowrap">Forms</span>
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
<header class="sticky top-0 bg-background-light/80 dark:bg-background-dark/80 backdrop-blur-sm z-30 px-0 lg:px-2 py-4 border-b border-border-light dark:border-border-dark flex justify-between items-center h-20 overflow-visible header-animate">
<div class="flex items-center gap-3">
<div class="w-10 h-10 rounded-lg bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center">
<span class="material-symbols-outlined text-white">calendar_today</span>
</div>
<div>
<h1 class="text-2xl font-bold text-text-light dark:text-text-dark">Scheduler</h1>
<p class="text-sm text-text-muted-light dark:text-text-muted-dark">Manage and view scheduled events and important dates</p>
</div>
</div>
<div class="flex items-center gap-2">
					<div class="relative z-[9999]">
                        <button id="notificationBtn" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-white/10 text-text-muted-light dark:text-text-muted-dark transition-colors duration-200 relative">
                            <span class="material-symbols-outlined">notifications</span>
                            <span id="notificationBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
                        </button>
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
<div class="p-2 content-animate">
<div class="max-w-none">
<!-- Preserve existing schedulerTest main content below -->
<div class="flex-1 flex p-3 space-x-2">
<div class="w-56 flex-shrink-0 flex flex-col space-y-4">
<button class="w-full flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary/90 transition-colors mt-4" onclick="openCreateModal()">
<span class="material-symbols-outlined text-base">add</span>
<span>Create</span>
</button>
<div class="p-2">
<div class="flex items-center justify-between mb-1">
<h3 id="sidebarMonthDisplay" class="text-sm font-medium text-text-muted-light dark:text-text-muted-dark">October 2025</h3>
<div class="flex items-center space-x-1">
<button id="sidebarPrevBtn" class="w-6 h-6 rounded-full bg-gray-100 dark:bg-gray-700 hover:bg-gray-800 dark:hover:bg-gray-200 flex items-center justify-center">
<span class="material-symbols-outlined text-xs text-gray-600 dark:text-gray-400 hover:text-white dark:hover:text-gray-800">chevron_left</span>
</button>
<button id="sidebarNextBtn" class="w-6 h-6 rounded-full bg-gray-100 dark:bg-gray-700 hover:bg-gray-800 dark:hover:bg-gray-200 flex items-center justify-center">
<span class="material-symbols-outlined text-xs text-gray-600 dark:text-gray-400 hover:text-white dark:hover:text-gray-800">chevron_right</span>
</button>
</div>
</div>
<div class="grid grid-cols-7 gap-1 text-center text-xs text-gray-600 dark:text-gray-400 mb-1">
<span>S</span>
<span>M</span>
<span>T</span>
<span>W</span>
<span>T</span>
<span>F</span>
<span>S</span>
</div>
<div id="sidebarCalendarGrid" class="grid grid-cols-7">
<div class="h-8 p-1 flex items-center justify-center text-xs text-gray-500 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">28</div>
<div class="h-8 p-1 flex items-center justify-center text-xs text-gray-500 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">29</div>
<div class="h-8 p-1 flex items-center justify-center text-xs text-blue-600 dark:text-blue-400 font-semibold hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">30</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">
<span class="bg-blue-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-semibold">1</span>
</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">2</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">3</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">4</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">5</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">6</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">7</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">
<span class="bg-blue-100 text-blue-600 rounded-full w-5 h-5 flex items-center justify-center text-xs">8</span>
</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">9</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">10</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">11</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">12</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">13</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">14</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">15</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">16</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">17</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">18</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">19</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">20</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">21</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">22</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">23</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">24</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">25</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">26</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">27</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">28</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">29</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">30</div>
<div class="h-8 p-1 flex items-center justify-center text-xs hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">31</div>
<div class="h-8 p-1 flex items-center justify-center text-xs text-gray-500 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">1</div>
<div class="h-8 p-1 flex items-center justify-center text-xs text-gray-500 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">2</div>
<div class="h-8 p-1 flex items-center justify-center text-xs text-gray-500 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">3</div>
<div class="h-8 p-1 flex items-center justify-center text-xs text-gray-500 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">4</div>
<div class="h-8 p-1 flex items-center justify-center text-xs text-gray-500 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">5</div>
<div class="h-8 p-1 flex items-center justify-center text-xs text-gray-500 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">6</div>
<div class="h-8 p-1 flex items-center justify-center text-xs text-gray-500 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">7</div>
<div class="h-8 p-1 flex items-center justify-center text-xs text-gray-500 dark:text-gray-400 hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer">8</div>
</div>
<div class="mt-4 pt-4 border-t border-border-light dark:border-border-dark">
<div class="flex items-center justify-between">
                 <h4 class="font-medium text-text-muted-light dark:text-text-muted-dark">My Events</h4>
</div>
<div id="myEventsList" class="mt-2 space-y-2 text-sm"></div>
</div>
</div>
</div>
<div class="flex-1 bg-card-light dark:bg-card-dark p-6 rounded-lg shadow">
<div class="flex items-center justify-between mb-6">
<div class="flex items-center space-x-4">
<span class="material-symbols-outlined text-muted-light dark:text-muted-dark">calendar_month</span>
<div class="flex items-center space-x-1">
<button id="mainPrevBtn" class="p-1 rounded-md hover:bg-background-light dark:hover:bg-card-dark text-muted-light dark:text-muted-dark">
<span class="material-symbols-outlined">chevron_left</span>
</button>
<button id="mainNextBtn" class="p-1 rounded-md hover:bg-background-light dark:hover:bg-card-dark text-muted-light dark:text-muted-dark">
<span class="material-symbols-outlined">chevron_right</span>
</button>
</div>
<h3 id="mainMonthDisplay" class="text-xl font-medium text-text-light dark:text-text-dark">October 2025</h3>
</div>
<div class="flex items-center space-x-4">
<button id="todayBtn" class="px-4 py-1.5 rounded-full border border-border-light bg-card-light text-text-light text-sm font-medium hover:bg-gray-50 dark:bg-background-dark/50 dark:text-text-dark dark:border-border-dark dark:hover:bg-card-dark">Today</button>
<div class="relative">
<button id="viewBtn" class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-text-light bg-card-light border border-border-light rounded-full hover:bg-gray-50 dark:bg-background-dark/50 dark:text-text-dark dark:border-border-dark dark:hover:bg-card-dark">
<span id="viewBtnLabel">Month</span>
<span class="material-symbols-outlined text-base">expand_more</span>
</button>
<div id="viewDropdown" class="absolute right-0 mt-2 w-40 bg-card-light dark:bg-background-dark rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-50 hidden overflow-hidden">
<button data-view="Month" class="w-full text-left px-4 py-2 text-sm text-text-light dark:text-text-dark hover:bg-gray-100 dark:hover:bg-gray-800">Month</button>
<button data-view="Week" class="w-full text-left px-4 py-2 text-sm text-text-light dark:text-text-dark hover:bg-gray-100 dark:hover:bg-gray-800">Week</button>
<button data-view="Day" class="w-full text-left px-4 py-2 text-sm text-text-light dark:text-text-dark hover:bg-gray-100 dark:hover:bg-gray-800">Day</button>
</div>
</div>

</div>
</div>
<!-- Main Calendar Grid - Dynamically Generated -->
<div id="mainCalendarGrid" class="grid grid-cols-7 border-t border-l border-border-light dark:border-border-dark">
    <!-- Calendar will be generated here by JavaScript -->
</div>

<!-- Day Calendar Container (hidden by default, shown when selecting a date in mini calendar) -->
<div id="dayCalendarContainer" class="hidden border border-border-light dark:border-border-dark rounded-lg overflow-hidden">
    <!-- Header with timezone, weekday and date number -->
    <div class="grid grid-cols-[80px_1fr] bg-card-light dark:bg-card-dark">
        <div class="p-3 flex flex-col items-start justify-start">
            <div id="dayHeaderDow" class="text-xs text-gray-500 tracking-wide leading-tight">FRI</div>
            <div id="dayHeaderDate" class="text-3xl font-semibold text-gray-800 dark:text-gray-100">3</div>
        </div>
        <div></div>
    </div>
    <!-- Timezone label above the divider line -->
    <div class="grid grid-cols-[80px_1fr]">
        <div class="text-[11px] text-gray-500 px-2">GMT+08</div>
        <div></div>
    </div>
    <!-- Full-width divider line under the timezone label -->
    <div class="h-px bg-gray-200"></div>
    <!-- 24h grid -->
    <div id="dayHoursGrid" class="grid grid-cols-[80px_1fr]"></div>
    
</div>

<!-- Week Calendar Container (hidden by default) -->
<div id="weekCalendarContainer" class="hidden border border-border-light dark:border-border-dark rounded-lg overflow-hidden">
    <div class="bg-card-light dark:bg-card-dark">
        <div id="weekHeader" class="grid" style="grid-template-columns: 80px repeat(7, 1fr);"></div>
    </div>
    <div class="h-px bg-gray-200 dark:bg-gray-700"></div>
    <div id="weekGrid" class="grid" style="grid-template-columns: 80px repeat(7, 1fr);"></div>
</div>

<script>
// Event Details Modal (inserted outside other modals so it can open independently)
</script>

<!-- Event Details Modal -->
<div id="eventDetailModal" class="fixed inset-0 bg-black/30 hidden items-center justify-center z-[9999] flex">
    <div class="bg-white dark:bg-card-dark rounded-2xl shadow-2xl w-full max-w-xl mx-4 max-h-[85vh] overflow-y-auto border border-border-light dark:border-border-dark relative">
        <div class="p-4 border-b border-border-light dark:border-border-dark flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span id="detailColorDot" class="w-3.5 h-3.5 rounded-full bg-blue-500"></span>
                <h3 id="detailTitle" class="text-lg font-medium text-text-light dark:text-text-dark">Power Session</h3>
            </div>
            <div class="flex items-center gap-3 text-text-muted-light dark:text-text-muted-dark">
                <span id="detailEditBtn" class="material-symbols-outlined text-[20px] cursor-pointer hover:text-text-light dark:hover:text-text-dark">edit</span>
                <span id="detailDeleteBtn" class="material-symbols-outlined text-[20px] cursor-pointer hover:text-text-light dark:hover:text-text-dark" title="Delete">delete</span>
                <span id="detailReminderBtn" class="material-symbols-outlined text-[20px] cursor-pointer hover:text-text-light dark:hover:text-text-dark" title="Edit Reminder">notifications</span>
                <button id="detailCloseBtn" class="p-1 rounded-full hover:bg-gray-100 dark:hover:bg-white/10">
                    <span class="material-symbols-outlined text-text-light dark:text-text-dark">close</span>
                </button>
            </div>
        </div>
        <div class="p-4">
            <div class="mb-3">
                <p id="detailDate" class="text-sm text-text-muted-light dark:text-text-muted-dark">Thursday, 2 October</p>
                <p id="detailTime" class="text-sm text-text-muted-light dark:text-text-muted-dark mt-1 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[16px]">schedule</span>
                    <span id="detailTimeText"></span>
                </p>
            </div>

            <div class="mt-4 space-y-3">
                <!-- Event Type -->
                <div id="detailTypeContainer" class="flex items-start gap-3 text-text-light dark:text-text-dark hidden">
                    <span class="material-symbols-outlined text-[20px] text-text-muted-light dark:text-text-muted-dark">category</span>
                    <span id="detailType"></span>
                </div>
                
                <!-- Location -->
                <div id="detailLocationContainer" class="flex items-start gap-3 text-text-light dark:text-text-dark">
                    <span class="material-symbols-outlined text-[20px] text-text-muted-light dark:text-text-muted-dark">location_on</span>
                    <span id="detailLocation" class="flex-1">No location specified</span>
                </div>
                
                <!-- Description -->
                <div id="detailDescriptionContainer" class="flex items-start gap-3 text-text-light dark:text-text-dark">
                    <span class="material-symbols-outlined text-[20px] text-text-muted-light dark:text-text-muted-dark">description</span>
                    <span id="detailDescription" class="flex-1">No description provided</span>
                </div>
                
                <!-- Attachments -->
                <div id="detailAttachmentsContainer" class="flex items-start gap-3 text-text-light dark:text-text-dark hidden">
                    <span class="material-symbols-outlined text-[20px] text-text-muted-light dark:text-text-muted-dark">attach_file</span>
                    <div id="detailAttachments" class="flex-1 space-y-1 text-sm">
                        <!-- Filled dynamically -->
                    </div>
                </div>
                
                <!-- Reminder -->
                <div class="flex items-start gap-3 text-text-light dark:text-text-dark">
                    <span class="material-symbols-outlined text-[20px] text-text-muted-light dark:text-text-muted-dark">notifications</span>
                    <span id="detailReminder">30 minutes before</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Context menu for calendar events
</script>

<!-- Event Attendance Confirmation Modal -->
<div id="eventAttendanceModal" class="fixed inset-0 bg-black/50 dark:bg-black/70 flex items-center justify-center z-[10002] hidden">
    <div class="bg-white dark:bg-card-dark rounded-2xl shadow-2xl w-full max-w-md mx-4 border border-border-light dark:border-border-dark">
        <div class="p-6">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/20 rounded-full flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-blue-600 dark:text-blue-400 text-xl">event</span>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-text-light dark:text-text-dark">Upcoming Event</h3>
                    <p class="text-sm text-text-muted-light dark:text-text-muted-dark mt-1">Please confirm your attendance</p>
                </div>
            </div>
            
            <div class="mb-6">
                <p class="text-sm text-text-muted-light dark:text-text-muted-dark" id="attendanceEventDetails">Date and Time</p>
            </div>
            
            <p class="text-text-light dark:text-text-dark mb-6">
                Are you going to attend this event?
            </p>
            
            <div id="attendanceModalButtons" class="flex gap-3 justify-end">
                <button id="attendanceNoBtn" class="px-6 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-800 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                    No
                </button>
                <button id="attendanceYesBtn" class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                    Yes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Not Attending Confirmation Modal -->
<div id="notAttendingModal" class="fixed inset-0 bg-black/50 dark:bg-black/70 flex items-center justify-center z-[10003] hidden">
    <div class="bg-white dark:bg-card-dark rounded-2xl shadow-2xl w-full max-w-md mx-4 border border-border-light dark:border-border-dark">
        <div class="p-6">
            <div class="flex flex-col items-center text-center mb-6">
                <div class="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mb-4">
                    <span class="text-4xl">😊</span>
                </div>
                <h3 class="text-xl font-bold text-text-light dark:text-text-dark mb-2">Got It</h3>
                <p class="text-sm text-text-muted-light dark:text-text-muted-dark">
                    Noted. Reminders for this event will stop.
                </p>
            </div>
            
            <div class="flex justify-end">
                <button id="notAttendingCloseBtn" class="px-6 py-2 text-sm font-medium text-white bg-gray-600 dark:bg-gray-700 rounded-lg hover:bg-gray-700 dark:hover:bg-gray-600 transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Confirmed (Success) Modal -->
<div id="attendanceConfirmedModal" class="fixed inset-0 bg-black/50 dark:bg-black/70 flex items-center justify-center z-[10004] hidden">
    <div class="bg-white dark:bg-card-dark rounded-2xl shadow-2xl w-full max-w-md mx-4 border border-border-light dark:border-border-dark">
        <div class="p-6">
            <div class="flex flex-col items-center text-center mb-6">
                <div class="w-16 h-16 bg-green-100 dark:bg-green-900/20 rounded-full flex items-center justify-center mb-4">
                    <span class="material-symbols-outlined text-green-600 dark:text-green-400 text-3xl">check_circle</span>
                </div>
                <h3 class="text-xl font-bold text-text-light dark:text-text-dark mb-2">You're all set!</h3>
                <p class="text-sm text-text-muted-light dark:text-text-muted-dark">
                    Great! We'll see you at the event.
                </p>
            </div>
            <div class="flex justify-end">
                <button type="button" id="attendanceConfirmedCloseBtn" class="px-6 py-2 text-sm font-medium text-white bg-green-600 dark:bg-green-700 rounded-lg hover:bg-green-700 dark:hover:bg-green-600 transition-colors">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Success Modal -->
<div id="deleteSuccessModal" class="fixed inset-0 bg-black/50 dark:bg-black/70 flex items-center justify-center z-[10005] hidden">
    <div class="bg-white dark:bg-card-dark rounded-2xl shadow-2xl w-full max-w-md mx-4 border border-border-light dark:border-border-dark">
        <div class="p-6">
            <div class="flex flex-col items-center text-center mb-6">
                <div class="w-16 h-16 bg-green-100 dark:bg-green-900/20 rounded-full flex items-center justify-center mb-4">
                    <span class="material-symbols-outlined text-green-600 dark:text-green-400 text-3xl">check_circle</span>
                </div>
                <h3 class="text-xl font-bold text-text-light dark:text-text-dark mb-2">Schedule Deleted</h3>
                <p class="text-sm text-text-muted-light dark:text-text-muted-dark">
                    Schedule deleted successfully
                </p>
            </div>
            
            <div class="flex justify-end">
                <button id="deleteSuccessCloseBtn" class="px-6 py-2 text-sm font-medium text-white bg-green-600 dark:bg-green-700 rounded-lg hover:bg-green-700 dark:hover:bg-green-600 transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Event Context Menu -->
<div id="eventContextMenu" class="fixed z-[10000] hidden bg-white dark:bg-card-dark rounded-xl shadow-xl border border-gray-200 dark:border-border-dark w-56 select-none overflow-hidden">
    <button id="ctxDelete" class="w-full text-left px-2 py-1.5 flex items-center gap-2 hover:bg-gray-100 bg-gray-50 rounded-t-xl">
        <span class="material-symbols-outlined text-gray-600">delete</span>
        <span class="text-gray-800">Delete</span>
    </button>
    <div class="h-px bg-gray-200"></div>
    <div class="px-2 py-1.5 rounded-b-xl">
        <div class="grid grid-cols-6 gap-2">
            <button class="w-5 h-5 rounded-full bg-red-500 cursor-pointer" data-color-choice="bg-red-500" title="Tomato"></button>
            <button class="w-5 h-5 rounded-full bg-orange-500 cursor-pointer" data-color-choice="bg-orange-500" title="Orange"></button>
            <button class="w-5 h-5 rounded-full bg-yellow-400 cursor-pointer" data-color-choice="bg-yellow-400" title="Yellow"></button>
            <button class="w-5 h-5 rounded-full bg-green-400 cursor-pointer" data-color-choice="bg-green-400" title="Mint"></button>
            <button class="w-5 h-5 rounded-full bg-green-600 cursor-pointer" data-color-choice="bg-green-600" title="Forest Green"></button>
            <button class="w-5 h-5 rounded-full bg-blue-400 cursor-pointer" data-color-choice="bg-blue-400" title="Sky Blue"></button>
            <button class="w-5 h-5 rounded-full bg-blue-600 cursor-pointer" data-color-choice="bg-blue-600" title="Navy Blue"></button>
            <button class="w-5 h-5 rounded-full bg-purple-400 cursor-pointer" data-color-choice="bg-purple-400" title="Lavender"></button>
            <button class="w-5 h-5 rounded-full bg-purple-600 cursor-pointer" data-color-choice="bg-purple-600" title="Plum"></button>
            <button class="w-5 h-5 rounded-full bg-gray-400 cursor-pointer" data-color-choice="bg-gray-400" title="Gray"></button>
            <button class="w-5 h-5 rounded-full bg-pink-300 cursor-pointer" data-color-choice="bg-pink-300" title="Pink"></button>
            <button class="w-5 h-5 rounded-full border-2 border-gray-300 bg-transparent cursor-pointer" data-color-choice="bg-transparent" title="No Color"></button>
        </div>
    </div>
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
            
            // Initialize sidebar from saved state (shared with other pages)
            const initSidebarState = () => {
                const savedState = localStorage.getItem('sidebarCollapsed');
                const isCollapsed = savedState !== 'false'; // default collapsed
                const mainContent = document.getElementById('main-content');
                
                if (!isCollapsed) {
                    // Expanded state
                    appContainer.classList.remove('sidebar-collapsed');
                    appContainer.classList.add('sidebar-expanded');
                    if (mainContent) {
                        mainContent.classList.remove('ml-20');
                        mainContent.classList.add('ml-64');
                    }
                    if (sidebarLogoText) sidebarLogoText.classList.remove('hidden');
                    sidebarTexts.forEach(text => text.classList.remove('hidden'));
                    if (sidebarProfileInfo) sidebarProfileInfo.classList.remove('hidden');
                    if (sidebarProfilePicture) sidebarProfilePicture.classList.remove('hidden');
                    if (openIcon) {
                        openIcon.classList.remove('hidden');
                        openIcon.classList.add('block');
                    }
                    if (closedIcon) {
                        closedIcon.classList.add('hidden');
                        closedIcon.classList.remove('block');
                    }
                    navLinks.forEach(link => link.classList.remove('justify-center'));
                    if (profileContainer) profileContainer.classList.remove('justify-center');
                    if (toggleContainer) toggleContainer.classList.remove('justify-center');
                } else {
                    // Collapsed state
                    appContainer.classList.add('sidebar-collapsed');
                    appContainer.classList.remove('sidebar-expanded');
                    if (mainContent) {
                        mainContent.classList.remove('ml-64');
                        mainContent.classList.add('ml-20');
                    }
                    if (sidebarLogoText) sidebarLogoText.classList.add('hidden');
                    sidebarTexts.forEach(text => text.classList.add('hidden'));
                    if (sidebarProfileInfo) sidebarProfileInfo.classList.add('hidden');
                    if (sidebarProfilePicture) sidebarProfilePicture.classList.add('hidden');
                    if (openIcon) {
                        openIcon.classList.add('hidden');
                        openIcon.classList.remove('block');
                    }
                    if (closedIcon) {
                        closedIcon.classList.remove('hidden');
                        closedIcon.classList.add('block');
                    }
                    navLinks.forEach(link => link.classList.add('justify-center'));
                    if (profileContainer) profileContainer.classList.add('justify-center');
                    if (toggleContainer) toggleContainer.classList.add('justify-center');
                }
            };
            initSidebarState();
            
            // Function to toggle sidebar and persist state
            const toggleSidebar = () => {
                const isCollapsed = appContainer.classList.contains('sidebar-collapsed');
                const mainContent = document.getElementById('main-content');
                
                if (isCollapsed) {
                    // Expand sidebar
                    appContainer.classList.remove('sidebar-collapsed');
                    appContainer.classList.add('sidebar-expanded');
                    if (mainContent) {
                        mainContent.classList.remove('ml-20');
                        mainContent.classList.add('ml-64');
                    }
                    if (sidebarLogoText) sidebarLogoText.classList.remove('hidden');
                    sidebarTexts.forEach(text => text.classList.remove('hidden'));
                    if (sidebarProfileInfo) sidebarProfileInfo.classList.remove('hidden');
                    if (sidebarProfilePicture) sidebarProfilePicture.classList.remove('hidden');
                    if (openIcon) {
                        openIcon.classList.remove('hidden');
                        openIcon.classList.add('block');
                    }
                    if (closedIcon) {
                        closedIcon.classList.add('hidden');
                        closedIcon.classList.remove('block');
                    }
                    navLinks.forEach(link => link.classList.remove('justify-center'));
                    if (profileContainer) profileContainer.classList.remove('justify-center');
                    if (toggleContainer) toggleContainer.classList.remove('justify-center');
                    
                    localStorage.setItem('sidebarCollapsed', 'false');
                } else {
                    // Collapse sidebar
                    appContainer.classList.add('sidebar-collapsed');
                    appContainer.classList.remove('sidebar-expanded');
                    if (mainContent) {
                        mainContent.classList.remove('ml-64');
                        mainContent.classList.add('ml-20');
                    }
                    if (sidebarLogoText) sidebarLogoText.classList.add('hidden');
                    sidebarTexts.forEach(text => text.classList.add('hidden'));
                    if (sidebarProfileInfo) sidebarProfileInfo.classList.add('hidden');
                    if (sidebarProfilePicture) sidebarProfilePicture.classList.add('hidden');
                    if (openIcon) {
                        openIcon.classList.add('hidden');
                        openIcon.classList.remove('block');
                    }
                    if (closedIcon) {
                        closedIcon.classList.remove('hidden');
                        closedIcon.classList.add('block');
                    }
                    navLinks.forEach(link => link.classList.add('justify-center'));
                    if (profileContainer) profileContainer.classList.add('justify-center');
                    if (toggleContainer) toggleContainer.classList.add('justify-center');
                    
                    localStorage.setItem('sidebarCollapsed', 'true');
                }
                
                // Force a reflow to ensure layout updates properly
                void appContainer.offsetHeight;
            };
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', toggleSidebar);
            }
            
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
            });

            // Location Autocomplete for Scheduler
            let schedulerAutocompleteService = null;
            const schedulerLocationInput = document.getElementById('locationInput');
            const schedulerLocationSuggestions = document.getElementById('schedulerLocationSuggestions');
            const schedulerSuggestionsList = document.getElementById('schedulerSuggestionsList');

            const loadSchedulerGoogleMaps = () => {
                if (window.google && window.google.maps && window.google.maps.places) {
                    return Promise.resolve();
                }
                
                return new Promise((resolve, reject) => {
                    // Check if Google Maps is already loaded
                    if (document.querySelector('script[src*="maps.googleapis.com"]')) {
                        const checkLoaded = () => {
                            if (window.google && window.google.maps && window.google.maps.places) {
                                resolve();
                            } else {
                                setTimeout(checkLoaded, 100);
                            }
                        };
                        checkLoaded();
                        return;
                    }
                    
                    // If not loaded, try to load it with proper async loading
                    const script = document.createElement('script');
                    script.src = 'https://maps.googleapis.com/maps/api/js?key=AIzaSyD1p_x_nw6wT7_zUnILTuG17fHNOf0zFC4&libraries=places&loading=async&callback=initSchedulerMapsCallback';
                    script.async = true;
                    script.defer = true;
                    script.onerror = () => {
                        console.warn('Google Maps API failed to load for scheduler. Using fallback suggestions only.');
                        reject(new Error('Failed to load Google Maps API'));
                    };
                    
                    // Create callback function
                    window.initSchedulerMapsCallback = () => {
                        delete window.initSchedulerMapsCallback;
                        resolve();
                    };
                    
                    document.head.appendChild(script);
                });
            };

            const showSchedulerLocationSuggestions = async (query) => {
                if (!query.trim() || query.length < 2) {
                    schedulerLocationSuggestions.classList.add('hidden');
                    return;
                }

                console.log('Searching for:', query); // Debug log
                
                // Always show fallback suggestions first for immediate response
                showSchedulerFallbackSuggestions(query);

                // Try to load Google Places API in the background (non-blocking)
                try {
                    await loadSchedulerGoogleMaps();
                    
                    if (!schedulerAutocompleteService && window.google && window.google.maps && window.google.maps.places) {
                        schedulerAutocompleteService = new google.maps.places.AutocompleteService();
                    }

                    if (schedulerAutocompleteService) {
                        // Use the legacy API which is still working - restricted to Iloilo only
                        schedulerAutocompleteService.getPlacePredictions({
                            input: query,
                            types: ['geocode', 'establishment'],
                            componentRestrictions: { country: 'ph' },
                            location: new google.maps.LatLng(10.7202, 122.5621), // Iloilo City coordinates
                            radius: 50000 // 50km radius around Iloilo
                        }, (predictions, status) => {
                            if (status === google.maps.places.PlacesServiceStatus.OK && predictions) {
                                // Filter predictions to only show Iloilo locations (strict filtering)
                                const iloiloPredictions = predictions.filter(prediction => {
                                    const desc = prediction.description.toLowerCase();
                                    return desc.includes('iloilo') && 
                                           !desc.includes('manila') &&
                                           !desc.includes('cebu') &&
                                           !desc.includes('davao') &&
                                           !desc.includes('baguio') &&
                                           !desc.includes('bacolod') &&
                                           !desc.includes('tacloban') &&
                                           !desc.includes('antique') &&
                                           !desc.includes('capiz') &&
                                           !desc.includes('aklan') &&
                                           !desc.includes('negros') &&
                                           !desc.includes('guimaras');
                                });
                                if (iloiloPredictions.length > 0) {
                                    displaySchedulerSuggestions(iloiloPredictions);
                                }
                                // If no Iloilo predictions, keep fallback suggestions
                            } else {
                                console.log('Places API status:', status);
                                // Keep fallback suggestions if API fails
                            }
                        });
                    }

                } catch (error) {
                    // Silently fail - fallback suggestions are already shown
                    console.log('Scheduler Places API unavailable, using fallback suggestions');
                }
            };

            const displaySchedulerSuggestions = (predictions) => {
                schedulerSuggestionsList.innerHTML = '';
                
                predictions.slice(0, 5).forEach((prediction, index) => {
                    const suggestionItem = document.createElement('div');
                    suggestionItem.className = 'flex items-center gap-3 p-2 rounded-lg hover:bg-gray-100 cursor-pointer transition-colors select-none';
                    suggestionItem.style.pointerEvents = 'auto';
                    suggestionItem.innerHTML = `
                        <span class="material-symbols-outlined text-gray-500 text-sm">place</span>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-900">${prediction.structured_formatting.main_text}</p>
                            <p class="text-xs text-gray-500">${prediction.structured_formatting.secondary_text}</p>
                        </div>
                    `;
                    
                    // Add click event with proper event handling
                    suggestionItem.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        schedulerLocationInput.value = prediction.description;
                        schedulerLocationSuggestions.classList.add('hidden');
                        // Trigger input event to update any other listeners
                        schedulerLocationInput.dispatchEvent(new Event('input', { bubbles: true }));
                    });
                    
                    // Add mousedown event as backup
                    suggestionItem.addEventListener('mousedown', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                    });
                    
                    schedulerSuggestionsList.appendChild(suggestionItem);
                });
                
                schedulerLocationSuggestions.classList.remove('hidden');
            };

            const showSchedulerFallbackSuggestions = (query) => {
                console.log('Showing ILOILO-ONLY fallback suggestions for:', query); // Debug log
                
                // STRICT ILOILO-ONLY LOCATIONS - No other provinces or cities
                const commonLocations = [
                    // Iloilo City and Province - Main Areas
                    'Iloilo City, Philippines', 'Central Philippine University, Iloilo City, Philippines', 'University of the Philippines Visayas, Iloilo City, Philippines',
                    'West Visayas State University, Iloilo City, Philippines', 'University of San Agustin, Iloilo City, Philippines',
                    'St. Paul University Iloilo, Iloilo City, Philippines', 'Iloilo Science and Technology University, Iloilo City, Philippines',
                    
                    // Iloilo City Districts and Barangays
                    'Mandurriao, Iloilo City, Philippines', 'Molo, Iloilo City, Philippines', 'Jaro, Iloilo City, Philippines',
                    'La Paz, Iloilo City, Philippines', 'Lapuz, Iloilo City, Philippines', 'Arevalo, Iloilo City, Philippines',
                    'City Proper, Iloilo City, Philippines', 'Villa Arevalo, Iloilo City, Philippines',
                    
                    // Iloilo Province Municipalities
                    'Miagao, Iloilo, Philippines', 'San Joaquin, Iloilo, Philippines', 'Guimbal, Iloilo, Philippines',
                    'Tigbauan, Iloilo, Philippines', 'Oton, Iloilo, Philippines', 'Pavia, Iloilo, Philippines',
                    'Santa Barbara, Iloilo, Philippines', 'New Lucena, Iloilo, Philippines', 'Zarraga, Iloilo, Philippines',
                    'Leganes, Iloilo, Philippines', 'Dumangas, Iloilo, Philippines', 'Barotac Nuevo, Iloilo, Philippines',
                    'Barotac Viejo, Iloilo, Philippines', 'Anilao, Iloilo, Philippines', 'Banate, Iloilo, Philippines',
                    'Bingawan, Iloilo, Philippines', 'Cabatuan, Iloilo, Philippines', 'Calinog, Iloilo, Philippines',
                    'Carles, Iloilo, Philippines', 'Concepcion, Iloilo, Philippines', 'Dingle, Iloilo, Philippines',
                    'Dueñas, Iloilo, Philippines', 'Estancia, Iloilo, Philippines',
                    'Igbaras, Iloilo, Philippines', 'Janiuay, Iloilo, Philippines', 'Lambunao, Iloilo, Philippines',
                    'Maasin, Iloilo, Philippines', 'Passi City, Iloilo, Philippines', 'Pototan, Iloilo, Philippines',
                    'San Dionisio, Iloilo, Philippines', 'San Enrique, Iloilo, Philippines', 'San Miguel, Iloilo, Philippines',
                    'San Rafael, Iloilo, Philippines', 'Sara, Iloilo, Philippines', 'Tubungan, Iloilo, Philippines',
                    
                    // Popular Iloilo Landmarks and Places
                    'Iloilo Business Park, Mandurriao, Iloilo City, Philippines', 'SM City Iloilo, Mandurriao, Iloilo City, Philippines',
                    'Robinsons Place Iloilo, La Paz, Iloilo City, Philippines', 'Festive Walk Iloilo, Mandurriao, Iloilo City, Philippines',
                    'Iloilo Convention Center, Mandurriao, Iloilo City, Philippines', 'Iloilo International Airport, Cabatuan, Iloilo, Philippines',
                    'Miagao Church, Miagao, Iloilo, Philippines', 'Jaro Cathedral, Jaro, Iloilo City, Philippines',
                    'Molo Church, Molo, Iloilo City, Philippines', 'Casa Mariquit, Jaro, Iloilo City, Philippines',
                    'Iloilo Museum of Contemporary Art, Mandurriao, Iloilo City, Philippines', 'Plaza Libertad, City Proper, Iloilo City, Philippines',
                    'Esplanade, Iloilo City, Philippines', 'Iloilo River, Iloilo City, Philippines',
                    
                    // Iloilo Universities and Schools
                    'Central Philippine University, Jaro, Iloilo City, Philippines', 'University of the Philippines Visayas, Miagao, Iloilo, Philippines',
                    'West Visayas State University, La Paz, Iloilo City, Philippines', 'University of San Agustin, Jaro, Iloilo City, Philippines',
                    'St. Paul University Iloilo, Jaro, Iloilo City, Philippines', 'Iloilo Science and Technology University, La Paz, Iloilo City, Philippines',
                    'John B. Lacson Foundation Maritime University, Molo, Iloilo City, Philippines', 'Iloilo Doctors College, Mandurriao, Iloilo City, Philippines',
                    'Assumption Iloilo, Jaro, Iloilo City, Philippines', 'Ateneo de Iloilo, Mandurriao, Iloilo City, Philippines',
                    'Colegio de San Jose, Jaro, Iloilo City, Philippines', 'St. Therese MTC Colleges, La Paz, Iloilo City, Philippines',
                    
                    // Iloilo Hospitals and Medical Centers
                    'Iloilo Doctors Hospital, Mandurriao, Iloilo City, Philippines', 'West Visayas State University Medical Center, La Paz, Iloilo City, Philippines',
                    'St. Paul Hospital Iloilo, Jaro, Iloilo City, Philippines', 'Iloilo Mission Hospital, Jaro, Iloilo City, Philippines',
                    'The Medical City Iloilo, Mandurriao, Iloilo City, Philippines', 'QualiMed Hospital Iloilo, Mandurriao, Iloilo City, Philippines',
                    
                    // Iloilo Government Offices
                    'Iloilo Provincial Capitol, Iloilo City, Philippines', 'Iloilo City Hall, City Proper, Iloilo City, Philippines',
                    'Iloilo City Government Center, Mandurriao, Iloilo City, Philippines', 'Department of Trade and Industry Iloilo, Iloilo City, Philippines',
                    'Bureau of Internal Revenue Iloilo, Iloilo City, Philippines', 'Social Security System Iloilo, Iloilo City, Philippines',
                    
                    // Iloilo Shopping and Commercial Areas
                    'Gaisano City Iloilo, La Paz, Iloilo City, Philippines', 'Atrium Mall, Mandurriao, Iloilo City, Philippines',
                    'Plaza Libertad Commercial Complex, City Proper, Iloilo City, Philippines', 'Iloilo Central Market, City Proper, Iloilo City, Philippines',
                    'Iloilo Terminal Market, City Proper, Iloilo City, Philippines',                     'Megaworld Iloilo Business Park, Mandurriao, Iloilo City, Philippines'
                ];

                const filtered = commonLocations.filter(location => 
                    location.toLowerCase().includes(query.toLowerCase())
                );

                console.log('Filtered results:', filtered.length, 'for query:', query); // Debug log

                if (filtered.length > 0) {
                    schedulerSuggestionsList.innerHTML = '';
                    filtered.slice(0, 8).forEach(location => {
                        const suggestionItem = document.createElement('div');
                        suggestionItem.className = 'flex items-center gap-3 p-2 rounded-lg hover:bg-gray-100 cursor-pointer transition-colors select-none';
                        suggestionItem.style.pointerEvents = 'auto';
                        suggestionItem.innerHTML = `
                            <span class="material-symbols-outlined text-gray-500 text-sm">place</span>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900">${location}</p>
                                <p class="text-xs text-gray-500">Suggested location</p>
                            </div>
                        `;
                        
                        // Add click event with proper event handling
                        suggestionItem.addEventListener('click', (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            schedulerLocationInput.value = location;
                            schedulerLocationSuggestions.classList.add('hidden');
                            // Trigger input event to update any other listeners
                            schedulerLocationInput.dispatchEvent(new Event('input', { bubbles: true }));
                        });
                        
                        // Add mousedown event as backup
                        suggestionItem.addEventListener('mousedown', (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                        });
                        
                        schedulerSuggestionsList.appendChild(suggestionItem);
                    });
                    schedulerLocationSuggestions.classList.remove('hidden');
                } else {
                    schedulerLocationSuggestions.classList.add('hidden');
                }
            };

            // Add event listener to scheduler location input
            if (schedulerLocationInput) {
                let schedulerTimeoutId;
                schedulerLocationInput.addEventListener('input', (e) => {
                    clearTimeout(schedulerTimeoutId);
                    const query = e.target.value.trim();
                    
                    schedulerTimeoutId = setTimeout(() => {
                        showSchedulerLocationSuggestions(query);
                    }, 300);
                });

                // Hide suggestions when clicking outside
                document.addEventListener('click', (e) => {
                    if (!schedulerLocationInput.contains(e.target) && !schedulerLocationSuggestions.contains(e.target)) {
                        schedulerLocationSuggestions.classList.add('hidden');
                    }
                });

                // Handle keyboard navigation
                schedulerLocationInput.addEventListener('keydown', (e) => {
                    const suggestions = schedulerSuggestionsList.querySelectorAll('div');
                    const activeSuggestion = schedulerSuggestionsList.querySelector('[data-active="true"]');
                    
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        if (activeSuggestion) {
                            activeSuggestion.classList.remove('bg-blue-100');
                            activeSuggestion.removeAttribute('data-active');
                            const next = activeSuggestion.nextElementSibling;
                            if (next) {
                                next.classList.add('bg-blue-100');
                                next.setAttribute('data-active', 'true');
                            }
                        } else if (suggestions[0]) {
                            suggestions[0].classList.add('bg-blue-100');
                            suggestions[0].setAttribute('data-active', 'true');
                        }
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        if (activeSuggestion) {
                            activeSuggestion.classList.remove('bg-blue-100');
                            activeSuggestion.removeAttribute('data-active');
                            const prev = activeSuggestion.previousElementSibling;
                            if (prev) {
                                prev.classList.add('bg-blue-100');
                                prev.setAttribute('data-active', 'true');
                            }
                        }
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        if (activeSuggestion) {
                            activeSuggestion.click();
                        }
                    } else if (e.key === 'Escape') {
                        schedulerLocationSuggestions.classList.add('hidden');
                    }
                });
            }
        });
 </script>
 
<!-- Create Event Modal -->
<div id="createModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden" style="background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px);">
    <div id="createModalCard" class="bg-card-light dark:bg-card-dark w-full max-w-md rounded-xl shadow-2xl overflow-hidden border border-border-light dark:border-border-dark flex flex-col max-h-[85vh]">
        <!-- Modal Header -->
        <div id="createModalHeader" class="flex items-center justify-between px-4 py-3 border-b border-border-light dark:border-border-dark">
            <div class="flex-1">
                <input autofocus="" class="w-full text-xl font-semibold bg-transparent border-0 border-none focus:ring-0 focus:outline-none focus:border-0 text-text-light dark:text-text-dark placeholder-slate-400 dark:placeholder-slate-500 px-0 outline-none" placeholder="Add title" type="text" id="eventTitle" name="eventTitle" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" style="border: none !important; outline: none !important; box-shadow: none !important;"/>
            </div>
            <button onclick="closeCreateModal(); return false;" class="p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-full text-slate-400 transition-colors cursor-pointer" data-no-drag type="button">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>
        
        <!-- Modal Content -->
        <div class="flex-1 overflow-y-auto custom-scrollbar p-4 space-y-4">
            <!-- Date and Time Section -->
            <section class="space-y-3">
                <div class="flex items-start space-x-3">
                    <span class="material-symbols-outlined text-slate-400 mt-1 text-lg">schedule</span>
                    <div class="flex-1 space-y-2">
                        <div class="grid grid-cols-1 gap-2">
                            <input type="text" id="eventDateInput" placeholder="00/00/0000" class="w-full bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none cursor-pointer" readonly onclick="toggleTimePicker('date'); this.blur(); return false;" onfocus="toggleTimePicker('date'); this.blur(); return false;"/>
                            <input type="hidden" id="eventDateIso" />
                            <div class="flex items-center space-x-2">
                                <input type="text" id="startTimeInput" readonly class="flex-1 bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none cursor-pointer text-slate-700 dark:text-slate-200" placeholder="Start time" onclick="toggleTimePicker('start'); return false;" onfocus="this.blur(); toggleTimePicker('start'); return false;"/>
                                <span class="text-slate-400 text-xs font-medium">to</span>
                                <input type="text" id="endTimeInput" readonly class="flex-1 bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none cursor-pointer text-slate-700 dark:text-slate-200" placeholder="End time" onclick="toggleTimePicker('end'); return false;" onfocus="this.blur(); toggleTimePicker('end'); return false;"/>
                            </div>
                        </div>
                        <div class="relative">
                            <select id="repeatSelect" class="w-full bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none text-slate-700 dark:text-slate-300" onchange="selectRepeatOption(this.value)">
                                <option value="Does not repeat">Does not repeat</option>
                                <option value="Daily">Daily</option>
                                <option value="Weekly on Thursday">Weekly on Thursday</option>
                                <option value="Monthly">Monthly</option>
                                <option value="Custom...">Custom...</option>
                            </select>
                            <!-- Keep old repeat button and dropdown for compatibility -->
                            <button onclick="toggleRepeatDropdown()" id="repeatButton" class="hidden">Doesn't repeat</button>
                            <div id="repeatDropdown" class="hidden absolute top-full left-0 mt-2 bg-card-light dark:bg-card-dark border border-border-light dark:border-border-dark rounded-lg shadow-lg z-10 min-w-48">
                                <div class="py-1">
                                    <div class="px-3 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer" onclick="selectRepeatOption('Does not repeat')">Does not repeat</div>
                                    <div class="px-3 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer" onclick="selectRepeatOption('Daily')">Daily</div>
                                    <div class="px-3 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer" onclick="selectRepeatOption('Weekly on Wednesday')">Weekly on Wednesday</div>
                                    <div class="px-3 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer" onclick="selectRepeatOption('Monthly on the first Wednesday')">Monthly on the first Wednesday</div>
                                    <div class="px-3 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer" onclick="selectRepeatOption('Annually on October 1')">Annually on October 1</div>
                                    <div class="px-3 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer" onclick="selectRepeatOption('Every weekday (Monday to Friday)')">Every weekday (Monday to Friday)</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- Location Section -->
            <section class="flex items-center space-x-3">
                <span class="material-symbols-outlined text-slate-400 text-lg">location_on</span>
                <div class="flex-1 relative">
                    <input type="text" id="locationInput" name="locationInput" placeholder="Add location or meeting link" class="w-full bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none placeholder-slate-400" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false"/>
                    <div id="schedulerLocationSuggestions" class="hidden absolute top-full left-0 right-0 mt-1 rounded-lg border border-border-light dark:border-border-dark bg-card-light dark:bg-card-dark shadow-lg max-h-48 overflow-y-auto z-50">
                        <div id="schedulerSuggestionsList" class="p-2 space-y-1">
                            <!-- Location suggestions will appear here -->
                        </div>
                    </div>
                    <span id="locationText" class="hidden">Add location</span>
                </div>
            </section>
            
            <!-- Description Section -->
            <section class="flex items-start space-x-3">
                <span class="material-symbols-outlined text-slate-400 mt-1.5 text-lg">notes</span>
                <div class="flex-1">
                    <textarea id="descriptionInput" name="descriptionInput" class="w-full bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary outline-none text-slate-700 dark:text-slate-300 resize-none" placeholder="Add description" rows="3" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false"></textarea>
                    <span id="descriptionText" class="hidden">Add description or a Google Drive attachment</span>
                </div>
            </section>
            
            <!-- Event Type Section -->
            <section class="flex items-center space-x-3">
                <span class="material-symbols-outlined text-slate-400 text-lg">label</span>
                <div class="flex flex-wrap gap-1.5">
                    <button type="button" class="px-3 py-1 bg-primary/10 text-primary rounded-full text-xs font-semibold border border-primary/20 event-type-tag active cursor-pointer" data-type="Event">Event</button>
                    <button type="button" class="px-3 py-1 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 rounded-full text-xs font-medium border border-slate-200 dark:border-slate-700 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors event-type-tag cursor-pointer" data-type="Task">Task</button>
                    <button type="button" class="px-3 py-1 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 rounded-full text-xs font-medium border border-slate-200 dark:border-slate-700 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors event-type-tag cursor-pointer" data-type="Meeting">Meeting</button>
                </div>
                <!-- Hidden select for compatibility with existing JavaScript -->
                <select id="eventTypeSelect" class="hidden">
                    <option value="Event" selected>Event</option>
                    <option value="Task">Task</option>
                    <option value="Meeting">Meeting</option>
                </select>
            </section>
            
            <!-- Reminders and Attachments Section -->
            <section class="pt-3 border-t border-slate-100 dark:border-slate-800 space-y-4">
                <!-- Reminders -->
                <div class="flex items-center space-x-3">
                    <span class="material-symbols-outlined text-slate-400 text-lg">notifications_active</span>
                    <div class="flex-1 flex items-center space-x-2">
                        <div class="flex-1 relative">
                            <button id="reminderButton" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-1.5 flex items-center justify-between cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" onclick="toggleReminderDropdown()">
                                <span id="reminderText" class="text-xs text-slate-600 dark:text-slate-400">30 minutes before</span>
                                <span class="material-symbols-outlined text-slate-400 text-sm">expand_more</span>
                            </button>
                            <!-- Reminder Dropdown -->
                            <div id="reminderDropdown" class="hidden absolute top-full left-0 mt-2 bg-card-light dark:bg-card-dark border border-border-light dark:border-border-dark rounded-lg shadow-xl z-20 w-full max-h-80 overflow-y-auto">
                                <div class="py-2">
                                    <div class="px-4 py-2.5 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide border-b border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">Remind me</div>
                                    
                                    <!-- Time-based reminders -->
                                    <div class="py-1">
                                        <div class="px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-900/20 cursor-pointer transition-colors flex items-center justify-between reminder-option" data-value="At time of event" onclick="selectReminder('At time of event')">
                                            <span>At time of event</span>
                                            <span class="reminder-check hidden material-symbols-outlined text-blue-600 dark:text-blue-400 text-base">check</span>
                                        </div>
                                        <div class="px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-900/20 cursor-pointer transition-colors flex items-center justify-between reminder-option" data-value="5 minutes before" onclick="selectReminder('5 minutes before')">
                                            <span>5 minutes before</span>
                                            <span class="reminder-check hidden material-symbols-outlined text-blue-600 dark:text-blue-400 text-base">check</span>
                                        </div>
                                        <div class="px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-900/20 cursor-pointer transition-colors flex items-center justify-between reminder-option" data-value="10 minutes before" onclick="selectReminder('10 minutes before')">
                                            <span>10 minutes before</span>
                                            <span class="reminder-check hidden material-symbols-outlined text-blue-600 dark:text-blue-400 text-base">check</span>
                                        </div>
                                        <div class="px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-900/20 cursor-pointer transition-colors flex items-center justify-between reminder-option" data-value="15 minutes before" onclick="selectReminder('15 minutes before')">
                                            <span>15 minutes before</span>
                                            <span class="reminder-check hidden material-symbols-outlined text-blue-600 dark:text-blue-400 text-base">check</span>
                                        </div>
                                        <div class="px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-900/20 cursor-pointer transition-colors flex items-center justify-between reminder-option" data-value="30 minutes before" onclick="selectReminder('30 minutes before')">
                                            <span>30 minutes before</span>
                                            <span class="reminder-check hidden material-symbols-outlined text-blue-600 dark:text-blue-400 text-base">check</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Hour-based reminders -->
                                    <div class="py-1 border-t border-slate-100 dark:border-slate-800">
                                        <div class="px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-900/20 cursor-pointer transition-colors flex items-center justify-between reminder-option" data-value="1 hour before" onclick="selectReminder('1 hour before')">
                                            <span>1 hour before</span>
                                            <span class="reminder-check hidden material-symbols-outlined text-blue-600 dark:text-blue-400 text-base">check</span>
                                        </div>
                                        <div class="px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-900/20 cursor-pointer transition-colors flex items-center justify-between reminder-option" data-value="2 hours before" onclick="selectReminder('2 hours before')">
                                            <span>2 hours before</span>
                                            <span class="reminder-check hidden material-symbols-outlined text-blue-600 dark:text-blue-400 text-base">check</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Day-based reminders -->
                                    <div class="py-1 border-t border-slate-100 dark:border-slate-800">
                                        <div class="px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-900/20 cursor-pointer transition-colors flex items-center justify-between reminder-option" data-value="1 day before" onclick="selectReminder('1 day before')">
                                            <span>1 day before</span>
                                            <span class="reminder-check hidden material-symbols-outlined text-blue-600 dark:text-blue-400 text-base">check</span>
                                        </div>
                                        <div class="px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-900/20 cursor-pointer transition-colors flex items-center justify-between reminder-option" data-value="2 days before" onclick="selectReminder('2 days before')">
                                            <span>2 days before</span>
                                            <span class="reminder-check hidden material-symbols-outlined text-blue-600 dark:text-blue-400 text-base">check</span>
                                        </div>
                                        <div class="px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-900/20 cursor-pointer transition-colors flex items-center justify-between reminder-option" data-value="1 week before" onclick="selectReminder('1 week before')">
                                            <span>1 week before</span>
                                            <span class="reminder-check hidden material-symbols-outlined text-blue-600 dark:text-blue-400 text-base">check</span>
                                        </div>
                                    </div>
                                    
                                    <!-- None option -->
                                    <div class="py-1 border-t border-slate-100 dark:border-slate-800">
                                        <div class="px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-red-50 dark:hover:bg-red-900/20 cursor-pointer transition-colors flex items-center justify-between reminder-option" data-value="None" onclick="selectReminder('None')">
                                            <span class="text-red-600 dark:text-red-400">None</span>
                                            <span class="reminder-check hidden material-symbols-outlined text-red-600 dark:text-red-400 text-base">check</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" id="reminderAddBtn" class="p-1.5 text-primary hover:bg-primary/5 rounded-full transition-colors cursor-pointer" onclick="event.stopPropagation(); toggleReminderDropdown();">
                            <span class="material-symbols-outlined text-sm">add</span>
                        </button>
                    </div>
                </div>
                
                <!-- Attachments -->
                <div class="flex items-start space-x-3">
                    <span class="material-symbols-outlined text-slate-400 mt-1.5 text-lg">attach_file</span>
                    <div class="flex-1">
                        <!-- Hidden file input that is triggered by clicking the dropzone -->
                        <input 
                            id="eventAttachmentsInput" 
                            type="file" 
                            class="hidden" 
                            multiple 
                            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                        />
                        <div 
                            id="attachmentsDropzone"
                            class="flex items-center p-3 border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-800/20 hover:border-primary/50 transition-colors cursor-pointer group"
                            tabindex="0"
                            role="button"
                            aria-label="Add attachments"
                        >
                            <div class="w-7 h-7 rounded-full bg-white dark:bg-slate-800 flex items-center justify-center mr-2 shadow-sm group-hover:text-primary transition-colors">
                                <span class="material-symbols-outlined text-base">upload</span>
                            </div>
                            <div>
                                <p class="text-xs font-medium">Add attachments</p>
                                <p class="text-[10px] text-slate-400">PDF, JPG, DOCX (Max 10MB)</p>
                            </div>
                        </div>
                        <!-- Selected attachments preview -->
                        <div id="attachmentsList" class="mt-2 space-y-1 text-[10px] text-slate-600 dark:text-slate-300"></div>
                    </div>
                </div>
            </section>
        </div>
        
        <!-- Modal Footer -->
        <div class="px-4 py-3 border-t border-border-light dark:border-border-dark flex items-center justify-end space-x-2 bg-card-light dark:bg-card-dark">
            <button type="button" class="px-4 py-2 text-xs font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-colors cursor-pointer" onclick="closeCreateModal(); return false;">
                Cancel
            </button>
            <button type="button" class="px-6 py-2 text-xs font-semibold text-white bg-primary hover:bg-primary/90 rounded-lg shadow-md shadow-primary/20 transition-all transform active:scale-[0.98] cursor-pointer" onclick="if(typeof saveEvent === 'function') { saveEvent(); } else { console.error('saveEvent function not found'); } return false;">
                Save Event
            </button>
        </div>
    </div>
</div>
 
<!-- Date Picker Calendar Popup -->
<div id="datePickerModal" class="fixed flex items-center justify-center z-[60] hidden" style="background: transparent;">
    <div class="bg-white dark:bg-card-dark rounded-lg shadow-xl w-full max-w-sm mx-4 relative z-10" style="pointer-events: auto;">
         <!-- Calendar Content -->
         <div class="p-4">
             <!-- Month Navigation -->
             <div class="flex items-center justify-between mb-4">
                 <div class="flex items-center gap-1">
                     <button class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg" onclick="event.stopPropagation(); prevMonth();">
                         <span class="material-symbols-outlined text-gray-600 dark:text-gray-400">chevron_left</span>
                     </button>
                 </div>
                 <div class="flex-1 flex items-center justify-center gap-2 relative">
                     <h4 id="currentMonthDisplay" class="text-lg font-medium text-gray-900 dark:text-gray-100 text-center">October 2025</h4>
                 </div>
                 <div class="flex items-center gap-1">
                     <button class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg" onclick="event.stopPropagation(); nextMonth();">
                         <span class="material-symbols-outlined text-gray-600 dark:text-gray-400">chevron_right</span>
                     </button>
                     <button id="closeDatePickerBtn" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg cursor-pointer relative" type="button" onclick="closeDatePickerModal(); return false;" style="pointer-events: auto !important; cursor: pointer !important; z-index: 9999;">
                         <span class="material-symbols-outlined text-gray-600 dark:text-gray-400" style="pointer-events: none;">close</span>
                     </button>
                 </div>
             </div>
             
             <!-- Days of Week -->
             <div class="grid grid-cols-7 gap-1 mb-2">
                 <div class="text-center text-sm font-medium text-gray-500 py-2">S</div>
                 <div class="text-center text-sm font-medium text-gray-500 py-2">M</div>
                 <div class="text-center text-sm font-medium text-gray-500 py-2">T</div>
                 <div class="text-center text-sm font-medium text-gray-500 py-2">W</div>
                 <div class="text-center text-sm font-medium text-gray-500 py-2">T</div>
                 <div class="text-center text-sm font-medium text-gray-500 py-2">F</div>
                 <div class="text-center text-sm font-medium text-gray-500 py-2">S</div>
             </div>
             
            <!-- Calendar Grid -->
            <div id="calendarGrid" class="grid grid-cols-7 gap-1" style="pointer-events: auto;">
                <!-- Calendar will be dynamically generated here -->
            </div>
         </div>
     </div>
 </div>
 
<!-- Time Picker Popup - hidden by default; only shown when user is in Create/Edit Event modal and clicks time -->
<div id="timePickerModal" class="fixed inset-0 bg-black/50 dark:bg-black/70 flex items-center justify-center z-50 hidden" style="display: none !important;">
    <div class="bg-card-light dark:bg-card-dark rounded-lg shadow-xl w-full max-w-xs mx-4 border border-border-light dark:border-border-dark">
        <!-- Time Picker Header -->
        <div class="p-4 border-b border-border-light dark:border-border-dark">
            <div class="flex items-center justify-center space-x-2">
                <span id="timeDisplay" class="text-blue-600 dark:text-blue-400 font-medium border-b border-blue-600 dark:border-blue-400 cursor-pointer" onclick="switchTimeType('start')">4:30pm</span>
                <span class="text-gray-400 dark:text-gray-500">-</span>
                <span id="otherTimeDisplay" class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-gray-700 dark:text-gray-300 cursor-pointer" onclick="switchTimeType('end')">5:30pm</span>
            </div>
        </div>
        
        <!-- Hour & Minute Pickers -->
        <div class="p-4 border-b border-border-light dark:border-border-dark">
            <div class="flex items-center justify-center gap-3 mb-3">
                <div class="flex flex-col items-center gap-0.5 min-w-[4rem]">
                    <label class="text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-wide">Hour</label>
                    <select id="timeHourSelect" class="w-full max-w-[4rem] text-center py-2 px-1 border border-border-light dark:border-border-dark rounded-lg text-sm bg-slate-50 dark:bg-slate-800/50 text-text-light dark:text-text-dark focus:ring-2 focus:ring-primary outline-none">
                        <!-- populated by JS -->
                    </select>
                </div>
                <span class="text-slate-400 dark:text-slate-500 font-bold mt-5">:</span>
                <div class="flex flex-col items-center gap-0.5 min-w-[4rem]">
                    <label class="text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-wide">Minute</label>
                    <select id="timeMinuteSelect" class="w-full max-w-[4rem] text-center py-2 px-1 border border-border-light dark:border-border-dark rounded-lg text-sm bg-slate-50 dark:bg-slate-800/50 text-text-light dark:text-text-dark focus:ring-2 focus:ring-primary outline-none">
                        <!-- populated by JS -->
                    </select>
                </div>
                <div class="flex flex-col items-center gap-0.5 min-w-[4rem]">
                    <label class="text-[10px] text-slate-500 dark:text-slate-400 uppercase tracking-wide">Period</label>
                    <select id="timePeriodSelect" class="w-full max-w-[4rem] text-center py-2 px-1 border border-border-light dark:border-border-dark rounded-lg text-sm bg-slate-50 dark:bg-slate-800/50 text-text-light dark:text-text-dark focus:ring-2 focus:ring-primary outline-none">
                        <option value="am">AM</option>
                        <option value="pm">PM</option>
                    </select>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <input type="text" id="timeInput" placeholder="Or type: 2:30pm" class="flex-1 px-3 py-2 border border-border-light dark:border-border-dark rounded-lg text-sm bg-slate-50 dark:bg-slate-800/50 text-text-light dark:text-text-dark placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-primary" onkeydown="handleTimeInputKeydown(event)"/>
                <button onclick="applyTimeInput()" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary">Apply</button>
            </div>
        </div>
    </div>
</div>
 
 <script>
// Global variables to track calendar dates
// Initialize calendar to current month/year (not hardcoded to October)
const now = new Date();
let currentCalendarDate = new Date(now.getFullYear(), now.getMonth(), 1); // Current month - Main calendar & modal
let sidebarCalendarDate = new Date(now.getFullYear(), now.getMonth(), 1); // Current month - Sidebar mini calendar (independent when using its arrows)
 
 function openCreateModal() {
   const overlay = document.getElementById('createModal');
   const card = document.getElementById('createModalCard');
   
   // Reset any positioning styles that might interfere
   if (card) {
       card.style.position = '';
       card.style.left = '';
       card.style.top = '';
       card.style.right = '';
       card.style.bottom = '';
       card.style.transform = '';
       card.style.margin = '';
       card.style.marginTop = '';
   }
   
   // Reset overlay positioning
   overlay.style.top = '';
   overlay.style.left = '';
   overlay.style.right = '';
   overlay.style.bottom = '';
   overlay.style.transform = '';
   overlay.style.paddingTop = '';
   
   overlay.classList.remove('hidden');
   overlay.classList.remove('dragging');
   overlay.classList.add('flex');
   overlay.style.display = 'flex';
  overlay.style.visibility = 'visible';

  // Default date/time values for the new inputs
  // Date starts as all zeroes until the user picks a date.
  const dateDisplayInput = document.getElementById('eventDateInput'); // MM/DD/YYYY display
  const dateIsoInput = document.getElementById('eventDateIso'); // YYYY-MM-DD value
  if (!isEditingExisting) {
      if (dateDisplayInput) dateDisplayInput.value = '00/00/0000';
      if (dateIsoInput) dateIsoInput.value = '';
  } else {
      const iso = dateIsoInput ? dateIsoInput.value : '';
      if (
          dateDisplayInput &&
          (!dateDisplayInput.value || dateDisplayInput.value === '00/00/0000') &&
          iso &&
          /^\d{4}-\d{2}-\d{2}$/.test(iso)
      ) {
          const [y, m, d] = iso.split('-');
          dateDisplayInput.value = `${m}/${d}/${y}`;
      }
  }
  const startTimeInput = document.getElementById('startTimeInput');
  const endTimeInput = document.getElementById('endTimeInput');
  if (startTimeInput && !startTimeInput.value) startTimeInput.value = '2:00pm';
  if (endTimeInput && !endTimeInput.value) endTimeInput.value = '3:30pm';
   
   // Reset reminder to default if not editing
   if (!isEditingExisting) {
       const reminderText = document.getElementById('reminderText');
       if (reminderText && !reminderText.textContent) {
           reminderText.textContent = '30 minutes before';
       }
   }
 }
 
 function closeCreateModal() {
    // Close time picker whenever create modal closes (time picker only belongs to add/edit schedule flow)
    if (typeof closeTimePickerModal === 'function') closeTimePickerModal();
    
    const overlay = document.getElementById('createModal');
    if (overlay) {
        // Inline display styles override Tailwind's `hidden`, so reset both class + inline styles.
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
        overlay.classList.remove('dragging');
        overlay.style.display = 'none';
        overlay.style.visibility = 'hidden';
    }
    const card = document.getElementById('createModalCard');
    if (card) {
        // Reset position to center for next open
        card.style.position = '';
        card.style.left = '';
        card.style.top = '';
        card.style.transform = '';
        card.style.margin = '';
    }
}

// Make closeCreateModal globally accessible
window.closeCreateModal = closeCreateModal;
 
 let currentTimeType = 'start'; // Track which time button was clicked
 
 function toggleTimePicker(type = 'date') {
     if (type === 'date') {
        // If a date is already selected in the modal, open the picker on that month/year
        const dateIsoInput = document.getElementById('eventDateIso');
        const isoVal = dateIsoInput ? dateIsoInput.value : '';
        if (isoVal && /^\d{4}-\d{2}-\d{2}$/.test(isoVal)) {
            const [y, m] = isoVal.split('-').map(n => parseInt(n, 10));
            if (!isNaN(y) && !isNaN(m)) {
                currentCalendarDate = new Date(y, m - 1, 1);
            }
        }

         // Ensure calendar is rendered before showing modal (this recreates the buttons with fresh handlers)
         renderCalendar();
         
         const datePickerModal = document.getElementById('datePickerModal');
         const createModalCard = document.getElementById('createModalCard');
         
         // Reset modal styles to ensure it can be reopened
         if (datePickerModal) {
             datePickerModal.style.visibility = 'visible';
             datePickerModal.style.display = 'flex';
         }
         
         if (createModalCard && datePickerModal) {
             // Use requestAnimationFrame to ensure layout is calculated after calendar renders
             requestAnimationFrame(() => {
                 requestAnimationFrame(() => {
                     // Get the position and dimensions of the create modal card
                     const cardRect = createModalCard.getBoundingClientRect();
                     
                     // Position the calendar modal container to match the create modal card's bounds
                     datePickerModal.style.position = 'fixed';
                     datePickerModal.style.top = `${cardRect.top}px`;
                     datePickerModal.style.left = `${cardRect.left}px`;
                     datePickerModal.style.width = `${cardRect.width}px`;
                     datePickerModal.style.height = `${cardRect.height}px`;
                     datePickerModal.style.margin = '0';
                     datePickerModal.style.display = 'flex';
                     datePickerModal.style.alignItems = 'center';
                     datePickerModal.style.justifyContent = 'center';
                     datePickerModal.style.visibility = 'visible';
                     
                    // Show the modal after positioning
                    datePickerModal.classList.remove('hidden');
                });
             });
         } else {
             if (datePickerModal) {
                 datePickerModal.classList.remove('hidden');
                 datePickerModal.style.display = 'flex';
                 datePickerModal.style.visibility = 'visible';
             }
         }
    } else {
        // Never show time picker when Upcoming Event notification is open
        if (document.body.classList.contains('attendance-modal-open')) {
            closeTimePickerModal();
            return;
        }
        // Only show time picker when the Add/Edit Event modal is open (user is creating/editing a schedule)
        const createModal = document.getElementById('createModal');
        if (!createModal || createModal.classList.contains('hidden')) {
            closeTimePickerModal();
            return;
        }
        currentTimeType = type;
        renderTimePicker();
        const timePickerEl = document.getElementById('timePickerModal');
        timePickerEl.classList.remove('hidden');
        timePickerEl.style.setProperty('display', 'flex', 'important');
        timePickerEl.style.alignItems = 'center';
        timePickerEl.style.justifyContent = 'center';
        // Focus the input field after a short delay to ensure it's visible
        setTimeout(() => {
            const timeInput = document.getElementById('timeInput');
            if (timeInput) {
                timeInput.focus();
                timeInput.select();
            }
        }, 100);
    }
 }
 
 function closeDatePickerModal(event) {
     // Stop any event propagation to prevent closing create modal
     if (event) {
         if (event.stopPropagation) event.stopPropagation();
         if (event.preventDefault) event.preventDefault();
         if (event.stopImmediatePropagation) event.stopImmediatePropagation();
     }
     
     const datePickerModal = document.getElementById('datePickerModal');
     if (!datePickerModal) {
         return false;
     }
     
     // Force hide the modal completely - remove inline display first
     datePickerModal.style.removeProperty('display');
     datePickerModal.style.removeProperty('alignItems');
     datePickerModal.style.removeProperty('justifyContent');
     
     // Then set to hidden
     datePickerModal.style.display = 'none';
     datePickerModal.style.visibility = 'hidden';
     datePickerModal.classList.add('hidden');
     
     // Clear all positioning styles
     datePickerModal.style.top = '';
     datePickerModal.style.left = '';
     datePickerModal.style.width = '';
     datePickerModal.style.height = '';
     datePickerModal.style.margin = '';
     datePickerModal.style.position = '';
     
     return false;
 }

 // Make function globally accessible
 window.closeDatePickerModal = closeDatePickerModal;
 
 function closeTimePickerModal() {
     const el = document.getElementById('timePickerModal');
     if (!el) return;
     el.classList.add('hidden');
     el.style.setProperty('display', 'none', 'important');
 }
 
function renderTimePicker() {
    
     // Get current time values from inputs
     const startTimeInput = document.getElementById('startTimeInput');
     const endTimeInput = document.getElementById('endTimeInput');
     const currentTime = currentTimeType === 'start' ? (startTimeInput && startTimeInput.value) : (endTimeInput && endTimeInput.value);
     
     // Update header display to show both times
     const timeDisplay = document.getElementById('timeDisplay');
     const otherTimeDisplay = document.getElementById('otherTimeDisplay');
     
    if (currentTimeType === 'start') {
        timeDisplay.textContent = currentTime || '2:00pm';
        otherTimeDisplay.textContent = (endTimeInput && endTimeInput.value) || '3:30pm';
        // Update styling to show which is active
        timeDisplay.className = 'text-blue-600 dark:text-blue-400 font-medium border-b border-blue-600 dark:border-blue-400 cursor-pointer';
        otherTimeDisplay.className = 'px-2 py-1 bg-slate-100 dark:bg-slate-700 rounded text-slate-700 dark:text-slate-200 cursor-pointer';
    } else {
        timeDisplay.textContent = (startTimeInput && startTimeInput.value) || '2:00pm';
        otherTimeDisplay.textContent = currentTime || '3:30pm';
        // Update styling to show which is active
        timeDisplay.className = 'px-2 py-1 bg-slate-100 dark:bg-slate-700 rounded text-slate-700 dark:text-slate-200 cursor-pointer';
        otherTimeDisplay.className = 'text-blue-600 dark:text-blue-400 font-medium border-b border-blue-600 dark:border-blue-400 cursor-pointer';
    }
    
    // Populate Hour & Minute picker dropdowns
    const hourSelect = document.getElementById('timeHourSelect');
    const minuteSelect = document.getElementById('timeMinuteSelect');
    const periodSelect = document.getElementById('timePeriodSelect');
    
    if (hourSelect && minuteSelect && periodSelect) {
        hourSelect.innerHTML = '';
        for (let h = 1; h <= 12; h++) {
            const opt = document.createElement('option');
            opt.value = h;
            opt.textContent = h;
            hourSelect.appendChild(opt);
        }
        minuteSelect.innerHTML = '';
        for (let m = 0; m < 60; m++) {
            const opt = document.createElement('option');
            opt.value = m;
            opt.textContent = String(m).padStart(2, '0');
            minuteSelect.appendChild(opt);
        }
        
        const parsed = parseTimeInput(currentTime);
        if (parsed) {
            const displayHour = parsed.hour > 12 ? parsed.hour - 12 : parsed.hour === 0 ? 12 : parsed.hour;
            hourSelect.value = displayHour;
            minuteSelect.value = parsed.minute;
            periodSelect.value = parsed.hour >= 12 ? 'pm' : 'am';
        }

        // Make the hour & minute dropdowns shorter so they don't stretch to the top of the screen
        const collapseHourDropdown = () => {
            hourSelect.removeAttribute('size');
        };
        const collapseMinuteDropdown = () => {
            minuteSelect.removeAttribute('size');
        };

        hourSelect.addEventListener('focus', () => {
            // Show only a few options at once
            hourSelect.setAttribute('size', '6');
        });
        minuteSelect.addEventListener('focus', () => {
            // Show only a few options at once
            minuteSelect.setAttribute('size', '6');
        });
        periodSelect.addEventListener('focus', () => {
            // Only AM/PM, keep it short
            periodSelect.setAttribute('size', '2');
        });

        hourSelect.addEventListener('blur', collapseHourDropdown);
        hourSelect.addEventListener('change', collapseHourDropdown);
        minuteSelect.addEventListener('blur', collapseMinuteDropdown);
        minuteSelect.addEventListener('change', collapseMinuteDropdown);
        periodSelect.addEventListener('blur', () => periodSelect.removeAttribute('size'));
        periodSelect.addEventListener('change', () => periodSelect.removeAttribute('size'));
        
        const syncInputFromSelects = () => {
            const h = parseInt(hourSelect.value, 10);
            const m = parseInt(minuteSelect.value, 10);
            const p = periodSelect.value;
            let hour24 = h;
            if (p === 'pm' && h !== 12) hour24 += 12;
            if (p === 'am' && h === 12) hour24 = 0;
            const ti = document.getElementById('timeInput');
            if (ti) ti.value = formatTime(hour24, m);
        };
        hourSelect.onchange = minuteSelect.onchange = periodSelect.onchange = syncInputFromSelects;
    }
    
    // Set input field value to current time
    const timeInput = document.getElementById('timeInput');
    if (timeInput) {
        timeInput.value = currentTime;
        timeInput.select();
    }
}
 
 function formatTime(hour, minute) {
     const period = hour >= 12 ? 'pm' : 'am';
     const displayHour = hour > 12 ? hour - 12 : hour === 0 ? 12 : hour;
     const displayMinute = minute.toString().padStart(2, '0');
     return `${displayHour}:${displayMinute}${period}`;
 }
 // Convert 24h (e.g. "14:00") to 12h display (e.g. "2:00pm") for form population
 function time24ToDisplay(val) {
     if (!val || typeof val !== 'string') return val || '';
     const m = val.trim().match(/^(\d{1,2}):(\d{2})$/);
     if (m) {
         let h = parseInt(m[1], 10);
         const min = parseInt(m[2], 10) || 0;
         const period = h >= 12 ? 'pm' : 'am';
         h = h > 12 ? h - 12 : h === 0 ? 12 : h;
         return `${h}:${String(min).padStart(2, '0')}${period}`;
     }
     return val; // already in display format
 }
 
 function selectTime(timeString) {
     if (currentTimeType === 'start') {
         const el = document.getElementById('startTimeInput');
         if (el) el.value = timeString;
     } else {
         const el = document.getElementById('endTimeInput');
         if (el) el.value = timeString;
     }
     closeTimePickerModal();
 }
 
function switchTimeType(type) {
    currentTimeType = type;
    renderTimePicker();
}

// Parse time input from various formats (e.g., "2:30pm", "14:30", "2:30 PM", "14:30:00")
function parseTimeInput(input) {
    if (!input || !input.trim()) return null;
    
    const timeStr = input.trim().toLowerCase();
    
    // Remove extra spaces and normalize
    let normalized = timeStr.replace(/\s+/g, '');
    
    // Try to match various formats
    // Format 1: "2:30pm" or "2:30am"
    let match = normalized.match(/^(\d{1,2}):(\d{2})(am|pm)$/);
    if (match) {
        let hour = parseInt(match[1]);
        const minute = parseInt(match[2]);
        const period = match[3];
        
        if (minute < 0 || minute >= 60) return null;
        if (hour < 1 || hour > 12) return null;
        
        // Convert to 24-hour format
        if (period === 'pm' && hour !== 12) hour += 12;
        if (period === 'am' && hour === 12) hour = 0;
        
        // Check if time is within valid range (6:00am to 11:45pm)
        if (hour < 6 || hour > 23) return null;
        if (hour === 23 && minute > 45) return null;
        
        return { hour, minute };
    }
    
    // Format 2: "14:30" (24-hour format)
    match = normalized.match(/^(\d{1,2}):(\d{2})$/);
    if (match) {
        let hour = parseInt(match[1]);
        const minute = parseInt(match[2]);
        
        if (minute < 0 || minute >= 60) return null;
        if (hour < 0 || hour > 23) return null;
        
        // Check if time is within valid range (6:00am to 11:45pm)
        if (hour < 6 || hour > 23) return null;
        if (hour === 23 && minute > 45) return null;
        
        return { hour, minute };
    }
    
    // Format 3: "230pm" or "230pm" (no colon)
    match = normalized.match(/^(\d{1,4})(am|pm)$/);
    if (match) {
        const numStr = match[1];
        const period = match[2];
        let hour, minute;
        
        if (numStr.length <= 2) {
            hour = parseInt(numStr);
            minute = 0;
        } else if (numStr.length === 3) {
            hour = parseInt(numStr.substring(0, 1));
            minute = parseInt(numStr.substring(1));
        } else {
            hour = parseInt(numStr.substring(0, 2));
            minute = parseInt(numStr.substring(2));
        }
        
        if (minute < 0 || minute >= 60) return null;
        if (hour < 1 || hour > 12) return null;
        
        // Convert to 24-hour format
        if (period === 'pm' && hour !== 12) hour += 12;
        if (period === 'am' && hour === 12) hour = 0;
        
        // Check if time is within valid range
        if (hour < 6 || hour > 23) return null;
        if (hour === 23 && minute > 45) return null;
        
        return { hour, minute };
    }
    
    return null;
}

// Find closest time option (rounds to nearest 15-minute interval)
function findClosestTime(hour, minute) {
    // Round to nearest 15-minute interval
    const roundedMinute = Math.round(minute / 15) * 15;
    let finalHour = hour;
    let finalMinute = roundedMinute;
    
    if (finalMinute >= 60) {
        finalMinute = 0;
        finalHour += 1;
    }
    
    if (finalHour > 23) {
        finalHour = 23;
        finalMinute = 45;
    }
    
    return formatTime(finalHour, finalMinute);
}

// Handle Enter key in time input
function handleTimeInputKeydown(event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        applyTimeInput();
    }
}

// Handle blur event (when user clicks away)
function handleTimeInputBlur() {
    // Don't auto-apply on blur, let user explicitly click Apply or press Enter
    // This prevents accidental changes
}

// Apply time from input field
function applyTimeInput() {
    const hourSelect = document.getElementById('timeHourSelect');
    const minuteSelect = document.getElementById('timeMinuteSelect');
    const periodSelect = document.getElementById('timePeriodSelect');
    const timeInput = document.getElementById('timeInput');
    
    // Always prioritize hour/minute/period selects if they exist
    if (hourSelect && minuteSelect && periodSelect) {
        let hour = parseInt(hourSelect.value, 10);
        const minute = parseInt(minuteSelect.value, 10);
        const period = periodSelect.value;
        
        // Convert to 24-hour format
        if (period === 'pm' && hour !== 12) hour += 12;
        if (period === 'am' && hour === 12) hour = 0;
        
        selectTime(formatTime(hour, minute));
        return;
    }
    
    // Fallback to input field if selects don't exist
    const inputValue = timeInput && timeInput.value.trim();
    if (!inputValue) return;
    
    const parsedTime = parseTimeInput(inputValue);
    
    if (!parsedTime) {
        if (timeInput) {
            timeInput.classList.add('border-red-500', 'ring-2', 'ring-red-500');
            timeInput.placeholder = 'Invalid. Try: 2:30pm or 14:30';
            setTimeout(() => {
                timeInput.classList.remove('border-red-500', 'ring-2', 'ring-red-500');
                timeInput.placeholder = 'Or type: 2:30pm';
            }, 2000);
        }
        return;
    }
    
    selectTime(formatTime(parsedTime.hour, parsedTime.minute));
}
 
// Edit functions for all clickable elements
async function editTimeZone() {
    const currentValue = document.querySelector('button[onclick="editTimeZone()"]').textContent;
    const newValue = await showPrompt('Enter time zone:', 'Time Zone', currentValue);
    if (newValue !== null && newValue.trim() !== '') {
        document.querySelector('button[onclick="editTimeZone()"]').textContent = newValue.trim();
    }
}
 
 function toggleRepeatDropdown() {
     const dropdown = document.getElementById('repeatDropdown');
     dropdown.classList.toggle('hidden');
 }
 
function selectRepeatOption(option) {
    // Update select element if it exists
    const repeatSelect = document.getElementById('repeatSelect');
    if (repeatSelect) {
        repeatSelect.value = option;
    }
    // Update button if it exists (for backward compatibility)
    const repeatButton = document.getElementById('repeatButton');
    if (repeatButton) {
        repeatButton.textContent = option;
    }
    // Hide dropdown if it exists
    const repeatDropdown = document.getElementById('repeatDropdown');
    if (repeatDropdown) {
        repeatDropdown.classList.add('hidden');
    }
}
 
 function editLocation() {
     const locationText = document.getElementById('locationText');
     const locationInput = document.getElementById('locationInput');
     
     if (locationInput.classList.contains('hidden')) {
         // Show input, hide text
         locationText.classList.add('hidden');
         locationInput.classList.remove('hidden');
         locationInput.focus();
         locationInput.value = locationText.textContent === 'Add location' ? '' : locationText.textContent;
     } else {
         // Save and hide input, show text
         if (locationInput.value.trim() !== '') {
             locationText.textContent = locationInput.value.trim();
             locationText.className = 'text-gray-800';
         } else {
             locationText.textContent = 'Add location';
             locationText.className = '';
         }
         locationInput.classList.add('hidden');
         locationText.classList.remove('hidden');
     }
 }
 
 function editDescription() {
     const descriptionText = document.getElementById('descriptionText');
     const descriptionInput = document.getElementById('descriptionInput');
     
     if (descriptionInput.classList.contains('hidden')) {
         // Show input, hide text
         descriptionText.classList.add('hidden');
         descriptionInput.classList.remove('hidden');
         descriptionInput.focus();
         descriptionInput.value = descriptionText.textContent === 'Add description or a Google Drive attachment' ? '' : descriptionText.textContent;
     } else {
         // Save and hide input, show text
         if (descriptionInput.value.trim() !== '') {
             descriptionText.textContent = descriptionInput.value.trim();
             descriptionText.className = 'text-gray-800';
         } else {
             descriptionText.textContent = 'Add description or a Google Drive attachment';
             descriptionText.className = '';
         }
         descriptionInput.classList.add('hidden');
         descriptionText.classList.remove('hidden');
     }
 }
 
async function editMoreOptions() {
    const options = await showPrompt('Enter additional options (visibility, notifications, etc.):', 'Additional Options', '');
    if (options !== null && options.trim() !== '') {
        console.log('More options set to:', options.trim());
        showToast('Options saved: ' + options.trim(), 'success');
    }
}
 
// Global events storage - load from local storage or initialize empty array
let events = JSON.parse(localStorage.getItem('calendarEvents') || '[]');

// Track attachments selected for the event being edited/created (File objects in the browser)
let currentEventAttachments = [];

// Track which event is currently shown in the details modal
let currentOpenedEventId = null;
let isEditingExisting = false;
// Track which event is targeted for context menu actions
let ctxTargetEventId = null;

// Make saveEvent globally accessible
window.saveEvent = function() {
     console.log('Save button clicked!'); // Debug log
     
     try {
        const title = document.getElementById('eventTitle').value;
         console.log('Title:', title); // Debug log
         
        // Get date and time from new inputs or fallback to old buttons
        const dateInput = document.getElementById('eventDateInput'); // display
        const dateIsoInput = document.getElementById('eventDateIso'); // ISO
        const startTimeInput = document.getElementById('startTimeInput');
        const endTimeInput = document.getElementById('endTimeInput');
        const startTimeBtn = document.getElementById('startTimeBtn');
        const endTimeBtn = document.getElementById('endTimeBtn');
        
        let date, startTime, endTime;
        let scheduledDate = null;
        let dayNumber = null;
        if (dateInput && startTimeInput && endTimeInput) {
            // Use new input fields
            scheduledDate = dateIsoInput ? dateIsoInput.value : '';
            startTime = startTimeInput.value;
            endTime = endTimeInput.value;

            if (!scheduledDate) {
                showToast('Please choose a date.', 'error');
                return;
            }

            // Convert ISO (YYYY-MM-DD) into the legacy display format used across the app
            if (scheduledDate && /^\d{4}-\d{2}-\d{2}$/.test(scheduledDate)) {
                const [y, m, d] = scheduledDate.split('-').map(n => parseInt(n, 10));
                const localDate = new Date(y, (m || 1) - 1, d || 1);
                const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                date = `${dayNames[localDate.getDay()]}, ${localDate.getDate()} ${monthNames[localDate.getMonth()]} ${localDate.getFullYear()}`;
                dayNumber = localDate.getDate();
            } else {
                date = scheduledDate || '';
            }
        } else if (startTimeBtn && endTimeBtn) {
            // Fallback to old buttons
            startTime = startTimeBtn.textContent;
            endTime = endTimeBtn.textContent;
            const dateButton = document.querySelector('button[onclick="toggleTimePicker()"]');
            date = dateButton ? dateButton.textContent : '';
        } else {
            date = '';
            startTime = '';
            endTime = '';
        }
        
        // Get location and description from new always-visible inputs or fallback to old system
        const locationInput = document.getElementById('locationInput');
        const descriptionInput = document.getElementById('descriptionInput');
        const locationText = document.getElementById('locationText');
        const descriptionText = document.getElementById('descriptionText');
        
        let location, description;
        if (locationInput && !locationInput.classList.contains('hidden')) {
            location = locationInput.value;
        } else if (locationText) {
            location = locationText.textContent;
        } else {
            location = '';
        }
        
        if (descriptionInput && descriptionInput.tagName === 'TEXTAREA') {
            description = descriptionInput.value;
        } else if (descriptionText) {
            description = descriptionText.textContent;
        } else {
            description = '';
        }
        const eventType = (document.getElementById('eventTypeSelect') && document.getElementById('eventTypeSelect').value) || 'Event';
        const reminder = (document.getElementById('reminderText') && document.getElementById('reminderText').textContent) || '30 minutes before';
         
        console.log('Form data:', { title, startTime, endTime, date, location, description, eventType, reminder }); // Debug log
         
         if (!title.trim()) {
             showToast('Please enter a title for the event', 'warning');
             return;
         }
         
         // Get selected color from color picker button
         const colorButton = document.querySelector('button[onclick="toggleColorPicker()"]');
         console.log('Color button found:', colorButton); // Debug log
         
         let selectedColor = 'bg-blue-500'; // Default color
         if (colorButton) {
             const colorClass = colorButton.className.match(/bg-\w+-\d+|bg-transparent/);
             selectedColor = colorClass ? colorClass[0] : 'bg-blue-500';
         }
         console.log('Selected color:', selectedColor); // Debug log
         
        // If we didn't get dayNumber/scheduledDate from the ISO date input, derive it from legacy display string
        if (dayNumber == null) {
            const dn = date && typeof date === 'string' ? date.match(/\d{1,2}/) : null;
            dayNumber = dn ? parseInt(dn[0], 10) : 1;
        }
        console.log('Day number:', dayNumber); // Debug log

        // If scheduledDate wasn't set from the ISO input, parse the legacy display string
        if (!scheduledDate) {
            try {
                console.log('Original date string:', date); // Debug log

                // Remove day name prefix if present (e.g., "Friday, " or "Friday,")
                let dateToParse = (date || '').replace(/^[A-Za-z]+,\s*/i, '').trim();
                console.log('Date after removing day name:', dateToParse); // Debug log

                // Parse the date: "15 November 2025" or "15 November"
                const dateMatch = dateToParse.match(/^(\d{1,2})\s+(\w+)(?:\s+(\d{4}))?$/);
                if (dateMatch) {
                    const day = parseInt(dateMatch[1], 10);
                    const monthName = dateMatch[2];
                    const year = dateMatch[3] ? parseInt(dateMatch[3], 10) : currentCalendarDate.getFullYear();

                    const monthNames = ['january', 'february', 'march', 'april', 'may', 'june',
                                      'july', 'august', 'september', 'october', 'november', 'december'];
                    const monthIndex = monthNames.findIndex(m => m === monthName.toLowerCase());

                    if (monthIndex !== -1) {
                        const month = String(monthIndex + 1).padStart(2, '0');
                        const dayStr = String(day).padStart(2, '0');
                        scheduledDate = `${year}-${month}-${dayStr}`;
                    }
                }

                // Fallback if still missing
                if (!scheduledDate) {
                    const fallbackDate = currentCalendarDate;
                    const fallbackYear = fallbackDate.getFullYear();
                    const fallbackMonth = String(fallbackDate.getMonth() + 1).padStart(2, '0');
                    const fallbackDay = String(fallbackDate.getDate()).padStart(2, '0');
                    scheduledDate = `${fallbackYear}-${fallbackMonth}-${fallbackDay}`;
                }
            } catch (e) {
                console.error('❌ Date parsing error:', e);
            }
        }
         
        // Create or update event object
        let eventData;
        if (isEditingExisting && currentOpenedEventId) {
            const idx = events.findIndex(ev => String(ev.id) === String(currentOpenedEventId));
            if (idx !== -1) {
                events[idx] = {
                    ...events[idx],
                    title: title.trim(),
                    date: date,
                    dayNumber: parseInt(dayNumber, 10),
                    startTime: startTime,
                    endTime: endTime,
                    location: location !== 'Add location' ? location : '',
                    description: description !== 'Add description or a Google Drive attachment' ? description : '',
                    color: selectedColor,
                    type: eventType,
                    reminder: reminder
                };
                eventData = events[idx];
            }
        }
        if (!eventData) {
            eventData = {
                id: Date.now(), // Unique ID
                title: title.trim(),
                date: date,
                dayNumber: parseInt(dayNumber, 10),
                startTime: startTime,
                endTime: endTime,
                location: location !== 'Add location' ? location : '',
                description: description !== 'Add description or a Google Drive attachment' ? description : '',
                color: selectedColor,
                type: eventType,
                reminder: reminder,
                // Store a lightweight representation of attachments on the client side
                attachments: currentEventAttachments.map(file => ({
                    name: file.name,
                    size: file.size,
                    type: file.type
                }))
            };
            // Add event to storage
            events.push(eventData);
        } else {
            // Update existing event
            eventData.reminder = reminder;
            eventData.attachments = currentEventAttachments.map(file => ({
                name: file.name,
                size: file.size,
                type: file.type
            }));
        }
         
         console.log('Event data created:', eventData); // Debug log
         
        console.log('Events array:', events); // Debug log
         
        // Save to local storage so events persist after refresh
         localStorage.setItem('calendarEvents', JSON.stringify(events));
         console.log('Events saved to local storage'); // Debug log
         
         // Also save to database via API
         if (scheduledDate) {
             const scheduleData = {
                 title: title.trim(),
                 description: description !== 'Add description or a Google Drive attachment' ? description : '',
                 scheduled_date: scheduledDate,
                 start_time: startTime,
                 reminder: reminder, // Include reminder in database save
                 status: 'scheduled'
             };
             
             // Schedule reminder notification
             scheduleReminderNotification(eventData);
             
             // Collect attachment files from the hidden input for upload
             const attachmentInput = document.getElementById('eventAttachmentsInput');
             const attachmentFiles = attachmentInput && attachmentInput.files ? Array.from(attachmentInput.files) : [];

             // Check if we're editing an existing schedule or creating a new one
             if (isEditingExisting && currentOpenedEventId && typeof updateSchedule === 'function') {
                 // Update existing schedule
                updateSchedule(currentOpenedEventId, scheduleData).then(success => {
                    if (success) {
                        console.log('Schedule updated in database');
                        // Reload schedules from database
                        if (typeof loadSchedules === 'function') {
                            loadSchedules();
                        }
                        
                        // Check for notifications after updating schedule
                        setTimeout(async () => {
                            if (typeof checkNotifications === 'function') {
                                await checkNotifications();
                            }
                            // Reload notifications to display them immediately
                            if (typeof loadNotifications === 'function') {
                                await loadNotifications();
                            }
                            // Update badge
                            if (typeof updateNotificationBadge === 'function') {
                                await updateNotificationBadge();
                            }
                        }, 500);
                    }
                }).catch(error => {
                    console.error('Error updating schedule in database:', error);
                });
             } else if (typeof createSchedule === 'function') {
                 // Create new schedule (and upload any selected attachments); pass eventData.id so reminder can be re-keyed to DB id
                createSchedule(scheduleData, attachmentFiles, eventData.id).then(success => {
                    if (success) {
                        console.log('Schedule saved to database');
                        // Reload schedules from database
                        if (typeof loadSchedules === 'function') {
                            loadSchedules();
                        }
                        
                        // Check for notifications after creating schedule
                        setTimeout(async () => {
                            if (typeof checkNotifications === 'function') {
                                await checkNotifications();
                            }
                            // Reload notifications to display them immediately
                            if (typeof loadNotifications === 'function') {
                                await loadNotifications();
                            }
                            // Update badge
                            if (typeof updateNotificationBadge === 'function') {
                                await updateNotificationBadge();
                            }
                        }, 500);
                    }
                }).catch(error => {
                    console.error('Error saving schedule to database:', error);
                });
             }
         }
         
        // Update calendar display
        renderEventsOnCalendar();
        renderMyEvents();
         console.log('Calendar rendered'); // Debug log
         
        // Check for today's events after adding/updating event
        setTimeout(() => {
            checkTodaysEvents();
        }, 500);
         
        // Close modal automatically - no popup alert
         closeCreateModal();
        isEditingExisting = false;
         
     } catch (error) {
         console.error('Error in saveEvent:', error);
         showToast('Error saving event: ' + error.message, 'error');
     }
 };
 
 function nextMonth() {
     currentCalendarDate.setMonth(currentCalendarDate.getMonth() + 1);
    renderCalendar();
    renderEventsOnCalendar();
    // Keep sidebar in sync with main when main changes
    sidebarCalendarDate = new Date(currentCalendarDate.getTime());
    renderSidebarCalendar();
 }
 
function renderEventsOnCalendar() {
   // Clear existing events from the main calendar only
   document.querySelectorAll('#mainCalendarGrid .event-bar').forEach(event => event.remove());
   
   // Main calendar day cells (contain a <span> with the day number)
   const mainDayCells = document.querySelectorAll('#mainCalendarGrid > div');
   
   // Only render events for the currently displayed month/year
   const displayMonth = currentCalendarDate.getMonth();
   const displayYear = currentCalendarDate.getFullYear();
   const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
   
   console.log('🔄 Rendering events on calendar. Display month/year:', displayMonth + 1, displayYear, 'Total events:', events.length);
     
   // Add events to their respective days (filtered by month/year)
   events.forEach(event => {
       // Determine event month/year from stored fields or parse from date string
       let eventMonth = typeof event.month === 'number' ? event.month : null;
       let eventYear = typeof event.year === 'number' ? event.year : null;
       if (eventMonth === null) {
           const parsedMonthIndex = monthNames.findIndex(name => (event.date || '').includes(name));
           eventMonth = parsedMonthIndex !== -1 ? parsedMonthIndex : displayMonth;
       }
       if (eventYear === null) {
           const yearMatch = (event.date || '').match(/\b(19|20)\d{2}\b/);
           eventYear = yearMatch ? parseInt(yearMatch[0]) : displayYear;
       }
       
       console.log('  Checking event:', { 
           title: event.title, 
           eventMonth: eventMonth + 1, 
           eventYear, 
           eventDayNumber: event.dayNumber,
           displayMonth: displayMonth + 1, 
           displayYear,
           matches: eventMonth === displayMonth && eventYear === displayYear
       });
       
       // Skip events not in the currently displayed month/year
       if (eventMonth !== displayMonth || eventYear !== displayYear) {
           console.log('  ⏭️ Skipping event (not in current month/year)');
           return;
       }
       
       console.log('  ✅ Event matches current month/year, rendering...');

       // Render in main calendar grid only
       mainDayCells.forEach(cell => {
           const span = cell.querySelector('span');
           if (cell.dataset && cell.dataset.monthType === 'current' && span && span.textContent.trim() === event.dayNumber.toString()) {
               // Create a container for events stacked under the date number
               let eventContainer = cell.querySelector('.events-container');
               if (!eventContainer) {
                   eventContainer = document.createElement('div');
                   eventContainer.className = 'events-container w-full mt-1 space-y-1 pr-1 mx-auto flex-grow flex flex-col justify-start';
                   // Allow clicks on event elements
                   eventContainer.style.pointerEvents = 'auto';
                   cell.appendChild(eventContainer);
               }
               const eventBar = document.createElement('div');
               eventBar.className = `event-bar ${event.color} text-white text-xs px-2 py-1 rounded font-medium truncate cursor-pointer relative z-10`;
               eventBar.textContent = event.title;
               eventBar.style.fontSize = '12px';
               eventBar.style.lineHeight = '1.2';
               eventBar.style.maxWidth = '100%';
               eventBar.dataset.eventId = String(event.id);
               // Direct click handler as well for reliability
               eventBar.addEventListener('click', (ev) => {
                   ev.stopPropagation();
                   openEventDetail(event);
               });
               eventContainer.appendChild(eventBar);
           }
       });
   });
}
// Also keep sidebar list in sync whenever we render
try { renderMyEvents(); } catch (_) {}

// Render sidebar My Events list
function renderMyEvents() {
    const list = document.getElementById('myEventsList');
    if (!list) return;
    list.innerHTML = '';
    if (!events || events.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'text-gray-500 text-xs';
        empty.textContent = 'No events yet';
        list.appendChild(empty);
        return;
    }
    // Sort by date then time
    const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const toDate = (ev) => {
        const text = ev.date || '';
        const yearMatch = text.match(/\b(19|20)\d{2}\b/);
        const monthIndex = monthNames.findIndex(n => text.includes(n));
        const dayMatch = text.match(/\b\d{1,2}\b/);
        const y = yearMatch ? parseInt(yearMatch[0]) : currentCalendarDate.getFullYear();
        const m = monthIndex !== -1 ? monthIndex : currentCalendarDate.getMonth();
        const d = dayMatch ? parseInt(dayMatch[0]) : ev.dayNumber || 1;
        return new Date(y, m, d);
    };
    	const sorted = [...events].sort((a,b) => toDate(a) - toDate(b));
		// Helper to format date like "Oct 1, 2025"
					sorted.forEach(ev => {
				const row = document.createElement('div');
				row.className = 'group flex items-center justify-between rounded-lg px-2 py-1 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800/60';

				const title = document.createElement('div');
				title.className = 'text-[13px] font-medium text-text-muted-light dark:text-text-muted-dark truncate';
				title.textContent = ev.title || 'Untitled Event';

				const menu = document.createElement('button');
				menu.type = 'button';
				menu.className = 'ml-3 shrink-0 text-text-muted-light dark:text-text-muted-dark opacity-0 group-hover:opacity-100 transition-opacity hover:text-text-light dark:hover:text-text-dark';
				menu.innerHTML = '<span class="material-symbols-outlined text-[18px]">more_horiz</span>';
				menu.dataset.eventId = String(ev.id);
				menu.title = 'More options';

				// Add click handler for the menu button
				menu.addEventListener('click', (e) => {
					e.stopPropagation();
					e.preventDefault();
					
					// Get the context menu
					const ctxMenu = document.getElementById('eventContextMenu');
					if (!ctxMenu) return;
					
					// Set the target event ID
					ctxTargetEventId = String(ev.id);
					
					// Position menu near the button
					const rect = menu.getBoundingClientRect();
					const vw = window.innerWidth;
					const vh = window.innerHeight;
					ctxMenu.style.display = 'block';
					ctxMenu.classList.remove('hidden');
					const menuRect = ctxMenu.getBoundingClientRect();
					const left = Math.min(rect.right, vw - menuRect.width - 8);
					const top = Math.min(rect.bottom + 4, vh - menuRect.height - 8) + window.scrollY;
					ctxMenu.style.left = left + 'px';
					ctxMenu.style.top = top + 'px';
				});

				row.appendChild(title);
				row.appendChild(menu);

				// Open details when clicking anywhere except the menu icon
				row.addEventListener('click', (e) => {
					if (!(e.target.closest && e.target.closest('button'))) {
						openEventDetail(ev);
					}
				});
				list.appendChild(row);
			});
}

function setView(viewName) {
    const btn = document.getElementById('viewBtnLabel');
    const dropdown = document.getElementById('viewDropdown');
    if (btn) btn.textContent = viewName;
    if (dropdown) dropdown.classList.add('hidden');
    const dayContainer = document.getElementById('dayCalendarContainer');
    const weekContainer = document.getElementById('weekCalendarContainer');
    const mainGrid = document.getElementById('mainCalendarGrid');
    if (dayContainer) dayContainer.classList.add('hidden');
    if (weekContainer) weekContainer.classList.add('hidden');
    if (mainGrid) mainGrid.innerHTML = '';
    if (viewName === 'Month') {
        renderCalendar();
        renderEventsOnCalendar();
    } else if (viewName === 'Day') {
        if (dayContainer) dayContainer.classList.remove('hidden');
        renderDayView(new Date(currentCalendarDate.getFullYear(), currentCalendarDate.getMonth(), 1));
    } else if (viewName === 'Week') {
        if (weekContainer) weekContainer.classList.remove('hidden');
        renderWeekView(new Date(currentCalendarDate.getFullYear(), currentCalendarDate.getMonth(), 1));
    }
}

// Render Day view for a specific date
function renderDayView(dateObj) {
    const container = document.getElementById('dayCalendarContainer');
    const hoursGrid = document.getElementById('dayHoursGrid');
    if (!container || !hoursGrid) return;
    hoursGrid.innerHTML = '';

    // Update header weekday and date
    const dow = ['SUN','MON','TUE','WED','THU','FRI','SAT'][dateObj.getDay()];
    const dayNum = dateObj.getDate();
    const dowEl = document.getElementById('dayHeaderDow');
    const dateEl = document.getElementById('dayHeaderDate');
    if (dowEl) dowEl.textContent = dow;
    if (dateEl) dateEl.textContent = String(dayNum);

    // Build 24 rows (1AM..12AM)
    for (let h = 0; h < 24; h++) {
        const label = document.createElement('div');
        label.className = 'text-[11px] text-gray-500 px-2 py-3 border-b border-border-light dark:border-border-dark';
        const displayHour = ((h + 11) % 12) + 1; // 0->12, 13->1
        const ampm = h < 12 ? 'AM' : 'PM';
        label.textContent = `${displayHour} ${ampm}`;

        const slot = document.createElement('div');
        slot.className = 'relative h-12 border-b border-border-light dark:border-border-dark';
        hoursGrid.appendChild(label);
        hoursGrid.appendChild(slot);
    }

    // Render events that match this specific date
    const day = dateObj.getDate();
    const month = dateObj.getMonth();
    const year = dateObj.getFullYear();

    const dayEvents = events.filter(ev => {
        const text = ev.date || '';
        const yearMatch = text.match(/\b(19|20)\d{2}\b/);
        const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        const monthIndex = monthNames.findIndex(n => text.includes(n));
        const dayMatch = text.match(/\b\d{1,2}\b/);
        const evYear = yearMatch ? parseInt(yearMatch[0]) : year;
        const evMonth = monthIndex !== -1 ? monthIndex : month;
        const evDay = dayMatch ? parseInt(dayMatch[0]) : ev.dayNumber || day;
        return evYear === year && evMonth === month && evDay === day;
    });

    // Place as simple blocks at approximate hour positions using startTime
    dayEvents.forEach(ev => {
        const time = (ev.startTime || '9:00am').toLowerCase();
        const match = time.match(/(\d{1,2}):(\d{2})(am|pm)/);
        let hourIndex = 9;
        if (match) {
            let hour = parseInt(match[1]);
            const minute = parseInt(match[2]);
            const meridiem = match[3];
            if (meridiem === 'pm' && hour !== 12) hour += 12;
            if (meridiem === 'am' && hour === 12) hour = 0;
            hourIndex = hour;
            // We have 24 rows where each row corresponds to an hour, 0..23
        }
        const targetSlot = hoursGrid.children[hourIndex * 2 + 1];
        if (!targetSlot) return;
        const block = document.createElement('div');
        block.className = `${ev.color || 'bg-blue-500'} text-white text-xs px-2 py-1 rounded absolute left-2 right-2 top-1`;
        block.textContent = ev.title;
        targetSlot.appendChild(block);
    });
}
 
// Render Week view for a given date (week of the 1st day's week)
function renderWeekView(dateObj) {
    const weekHeader = document.getElementById('weekHeader');
    const weekGrid = document.getElementById('weekGrid');
    if (!weekHeader || !weekGrid) return;
    weekHeader.innerHTML = '';
    weekGrid.innerHTML = '';

    // Determine start of week (Sunday)
    const ref = new Date(dateObj.getFullYear(), dateObj.getMonth(), dateObj.getDate());
    const start = new Date(ref);
    start.setDate(ref.getDate() - ref.getDay());

    const dayNames = ['SUN','MON','TUE','WED','THU','FRI','SAT'];

    // Header row: empty corner + 7 days
    const corner = document.createElement('div');
    corner.className = 'p-3 text-[11px] text-gray-500';
    corner.textContent = 'GMT+08';
    weekHeader.appendChild(corner);
    for (let i = 0; i < 7; i++) {
        const d = new Date(start);
        d.setDate(start.getDate() + i);
        const head = document.createElement('div');
        head.className = 'p-3 border-l border-border-light dark:border-border-dark';
        head.innerHTML = `<div class="text-[11px] text-gray-500 text-center">${dayNames[d.getDay()]}</div><div class="text-2xl font-semibold text-gray-800 dark:text-gray-100 text-center">${d.getDate()}</div>`;
        weekHeader.appendChild(head);
    }

    // Left time labels column + 7 columns each with 24 slots
    for (let h = 0; h < 24; h++) {
        const label = document.createElement('div');
        label.className = 'text-[11px] text-gray-500 px-2 py-3 border-b border-border-light dark:border-border-dark';
        const displayHour = ((h + 11) % 12) + 1;
        const ampm = h < 12 ? 'AM' : 'PM';
        label.textContent = `${displayHour} ${ampm}`;
        weekGrid.appendChild(label);
        for (let i = 0; i < 7; i++) {
            const cell = document.createElement('div');
            cell.className = 'relative h-12 border-l border-b border-border-light dark:border-border-dark';
            weekGrid.appendChild(cell);
        }
    }

    // Place events in appropriate day column and hour
    const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const toYMD = (ev) => {
        const text = ev.date || '';
        const yMatch = text.match(/\b(19|20)\d{2}\b/);
        const mIndex = monthNames.findIndex(n => text.includes(n));
        const dMatch = text.match(/\b\d{1,2}\b/);
        const y = yMatch ? parseInt(yMatch[0]) : currentCalendarDate.getFullYear();
        const m = mIndex !== -1 ? mIndex : currentCalendarDate.getMonth();
        const d = dMatch ? parseInt(dMatch[0]) : ev.dayNumber || 1;
        return { y, m, d };
    };

    events.forEach(ev => {
        const { y, m, d } = toYMD(ev);
        const evDate = new Date(y, m, d);
        // Only show events within this week range
        const end = new Date(start);
        end.setDate(start.getDate() + 6);
        if (evDate < start || evDate > end) return;

        // Compute target column offset: +1 for time labels column, +h*8 stride
        const dayOffset = Math.floor((evDate - start) / 86400000); // 0..6
        const time = (ev.startTime || '9:00am').toLowerCase();
        const match = time.match(/(\d{1,2}):(\d{2})(am|pm)/);
        let hourIndex = 9;
        if (match) {
            let hour = parseInt(match[1]);
            const minute = parseInt(match[2]);
            const meridiem = match[3];
            if (meridiem === 'pm' && hour !== 12) hour += 12;
            if (meridiem === 'am' && hour === 12) hour = 0;
            hourIndex = hour;
        }
        // In the weekGrid, each row adds 8 columns (1 label + 7 days). Target cell index:
        const rowStartIndex = hourIndex * 8; // 8 columns per hour row
        const cellIndex = rowStartIndex + 1 + dayOffset; // +1 to skip label column
        const cell = weekGrid.children[cellIndex];
        if (!cell) return;
        const block = document.createElement('div');
        block.className = `${ev.color || 'bg-blue-500'} text-white text-xs px-2 py-1 rounded absolute left-2 right-2 top-1 cursor-pointer`;
        block.textContent = ev.title;
        block.dataset.eventId = String(ev.id);
        block.addEventListener('click', (e) => {
            e.stopPropagation();
            openEventDetail(ev);
        });
        cell.appendChild(block);
    });
}

function openEventDetail(event) {
    const modal = document.getElementById('eventDetailModal');
    if (!modal) { console.error('eventDetailModal not found'); return; }
    currentOpenedEventId = event && event.id ? event.id : null;
    document.getElementById('detailTitle').textContent = event.title || 'Event';
    const colorDot = document.getElementById('detailColorDot');
    colorDot.className = `w-3.5 h-3.5 rounded-full ${event.color || 'bg-blue-500'}`;
    
    // Compose readable date from stored date string if available
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    let dateText = event.date;
    if (!dateText && typeof event.dayNumber === 'number') {
        const d = new Date(currentCalendarDate.getFullYear(), currentCalendarDate.getMonth(), event.dayNumber);
        const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        dateText = `${dayNames[d.getDay()]}, ${event.dayNumber} ${monthNames[d.getMonth()]}`;
    }
    document.getElementById('detailDate').textContent = dateText || '';
    
    // Display time if available
    const detailTime = document.getElementById('detailTime');
    const detailTimeText = document.getElementById('detailTimeText');
    let timeText = '';
    
    if (event.startTime && event.endTime) {
        timeText = `${event.startTime} - ${event.endTime}`;
    } else if (event.startTime) {
        timeText = event.startTime;
    } else if (event.scheduled_time) {
        timeText = event.scheduled_time;
    } else if (event.time) {
        timeText = event.time;
    }
    
    if (detailTimeText) {
        detailTimeText.textContent = timeText;
    }
    
    // Show/hide time element based on whether time exists
    if (timeText.trim() === '') {
        detailTime.style.display = 'none';
    } else {
        detailTime.style.display = 'flex';
    }
    
    // Display event type
    const detailTypeContainer = document.getElementById('detailTypeContainer');
    const detailType = document.getElementById('detailType');
    if (event.type && event.type !== 'Event') {
        detailType.textContent = event.type;
        detailTypeContainer.classList.remove('hidden');
    } else {
        detailTypeContainer.classList.add('hidden');
    }
    
    // Display location (always show, with placeholder if empty)
    const detailLocationContainer = document.getElementById('detailLocationContainer');
    const detailLocation = document.getElementById('detailLocation');
    if (event.location && event.location.trim() !== '' && event.location !== 'Add location') {
        detailLocation.textContent = event.location;
        detailLocation.classList.remove('text-text-muted-light', 'dark:text-text-muted-dark', 'italic');
        detailLocation.classList.add('text-text-light', 'dark:text-text-dark');
    } else {
        detailLocation.textContent = 'No location specified';
        detailLocation.classList.remove('text-text-light', 'dark:text-text-dark');
        detailLocation.classList.add('text-text-muted-light', 'dark:text-text-muted-dark', 'italic');
    }
    detailLocationContainer.classList.remove('hidden');
    
    // Display description (always show, with placeholder if empty)
    const detailDescriptionContainer = document.getElementById('detailDescriptionContainer');
    const detailDescription = document.getElementById('detailDescription');
    if (event.description && event.description.trim() !== '' && event.description !== 'Add description or a Google Drive attachment') {
        detailDescription.textContent = event.description;
        detailDescription.classList.remove('text-text-muted-light', 'dark:text-text-muted-dark', 'italic');
        detailDescription.classList.add('text-text-light', 'dark:text-text-dark');
    } else {
        detailDescription.textContent = 'No description provided';
        detailDescription.classList.remove('text-text-light', 'dark:text-text-dark');
        detailDescription.classList.add('text-text-muted-light', 'dark:text-text-muted-dark', 'italic');
    }
    detailDescriptionContainer.classList.remove('hidden');
    
    // Load and display attachments (if any) for this schedule/event
    const detailAttachmentsContainer = document.getElementById('detailAttachmentsContainer');
    const detailAttachments = document.getElementById('detailAttachments');
    if (detailAttachmentsContainer && detailAttachments) {
        // Clear previous list
        detailAttachments.innerHTML = '';
        detailAttachmentsContainer.classList.add('hidden');

        const scheduleId = event.id;
        if (scheduleId) {
            fetch(`${ATTACHMENTS_API}?schedule_id=${encodeURIComponent(scheduleId)}`)
                .then(resp => resp.json())
                .then(data => {
                    if (!data || !data.success || !Array.isArray(data.attachments) || data.attachments.length === 0) {
                        return;
                    }

                    data.attachments.forEach(att => {
                        const link = document.createElement('a');
                        link.href = att.file_path || '#';
                        link.target = '_blank';
                        link.rel = 'noopener noreferrer';
                        link.className = 'flex items-center gap-2 text-primary hover:underline text-sm';

                        const icon = document.createElement('span');
                        icon.className = 'material-symbols-outlined text-[18px]';
                        icon.textContent = 'description';

                        const nameSpan = document.createElement('span');
                        nameSpan.textContent = att.original_name || att.file_name;

                        const sizeSpan = document.createElement('span');
                        sizeSpan.className = 'text-[11px] text-text-muted-light dark:text-text-muted-dark';
                        if (att.file_size) {
                            const sizeMb = (att.file_size / (1024 * 1024)).toFixed(1);
                            sizeSpan.textContent = `(${sizeMb}MB)`;
                        }

                        link.appendChild(icon);
                        link.appendChild(nameSpan);
                        if (sizeSpan.textContent) {
                            link.appendChild(sizeSpan);
                        }

                        detailAttachments.appendChild(link);
                    });

                    if (detailAttachments.children.length > 0) {
                        detailAttachmentsContainer.classList.remove('hidden');
                    }
                })
                .catch(err => {
                    console.error('Failed to load schedule attachments:', err);
                });
        }
    }
    
    // Display reminder (use saved reminder or default)
    const detailReminder = document.getElementById('detailReminder');
    detailReminder.textContent = event.reminder || '30 minutes before';

    // Ensure modal is properly centered
    const card = document.querySelector('#eventDetailModal > div');
    if (card) {
        // Reset any inline positioning styles that might interfere
        card.style.marginTop = '';
        card.style.marginLeft = '';
        card.style.marginRight = '';
        card.style.marginBottom = '';
        card.style.top = '';
        card.style.left = '';
        card.style.right = '';
        card.style.bottom = '';
        card.style.transform = '';
        card.style.position = '';
    }
    // Reset modal positioning
    modal.style.top = '';
    modal.style.left = '';
    modal.style.right = '';
    modal.style.bottom = '';
    modal.style.transform = '';
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.style.display = 'flex';
}

 function prevMonth() {
     currentCalendarDate.setMonth(currentCalendarDate.getMonth() - 1);
    renderCalendar();
    renderEventsOnCalendar();
    // Keep sidebar in sync with main when main changes
    sidebarCalendarDate = new Date(currentCalendarDate.getTime());
    renderSidebarCalendar();
 }
 
 function renderCalendar() {
     const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
     const currentMonth = currentCalendarDate.getMonth();
     const currentYear = currentCalendarDate.getFullYear();
     
    // Update month display
    document.getElementById('currentMonthDisplay').textContent = `${monthNames[currentMonth]} ${currentYear}`;
    const sidebarMonthEl = document.getElementById('sidebarMonthDisplay');
    if (sidebarMonthEl) sidebarMonthEl.textContent = `${monthNames[currentMonth]} ${currentYear}`;
    const mainMonthEl = document.getElementById('mainMonthDisplay');
    if (mainMonthEl) mainMonthEl.textContent = `${monthNames[currentMonth]} ${currentYear}`;
     
   // Clear small calendar grid (date picker modal) and ensure pointer events
   const calendarGrid = document.getElementById('calendarGrid');
   if (calendarGrid) {
       calendarGrid.innerHTML = '';
       calendarGrid.style.pointerEvents = 'auto';
   }
   // Do not touch sidebar mini grid here; it's rendered separately
     
     // Clear main calendar grid
     const mainCalendarGrid = document.getElementById('mainCalendarGrid');
     mainCalendarGrid.innerHTML = '';
    // Ensure day view is hidden when rendering month grid
    const dayContainer = document.getElementById('dayCalendarContainer');
    if (dayContainer) dayContainer.classList.add('hidden');
     
     // Add day headers to main calendar
     const dayHeaders = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];
     dayHeaders.forEach(day => {
         const headerDiv = document.createElement('div');
         headerDiv.className = 'text-center py-2 border-r border-b border-border-light dark:border-border-dark text-sm font-semibold text-muted-light dark:text-muted-dark';
         headerDiv.textContent = day;
         mainCalendarGrid.appendChild(headerDiv);
     });
     
     // Get first day of month and number of days
     const firstDay = new Date(currentYear, currentMonth, 1);
     const lastDay = new Date(currentYear, currentMonth + 1, 0);
     const daysInMonth = lastDay.getDate();
     const startingDayOfWeek = firstDay.getDay();
     
     // Previous month days
     const prevMonth = new Date(currentYear, currentMonth - 1, 0);
     const daysInPrevMonth = prevMonth.getDate();
     
    // Add previous month days
     for (let i = startingDayOfWeek - 1; i >= 0; i--) {
         const day = daysInPrevMonth - i;
         
         // Small calendar button
         const button = document.createElement('button');
         button.className = 'h-10 text-center text-sm text-gray-400 dark:text-gray-500 hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer';
         button.textContent = day;
        button.dataset.monthType = 'prev';
         button.style.pointerEvents = 'auto';
         button.type = 'button';
        button.onclick = function(e) {
            if (e) {
                e.stopPropagation();
                e.stopImmediatePropagation();
                e.preventDefault();
            }
            selectDate(button);
            return false;
        };
       calendarGrid.appendChild(button);
         
        // Main calendar cell
        const cellDiv = document.createElement('div');
        cellDiv.className = 'h-28 border-r border-b border-border-light dark:border-border-dark flex flex-col items-start pt-2 px-1 text-muted-light dark:text-muted-dark overflow-hidden';
        cellDiv.innerHTML = `<span class="text-xs mx-auto">${day}</span>`;
        cellDiv.dataset.monthType = 'prev';
         mainCalendarGrid.appendChild(cellDiv);
     }
     
     // Current month days
     const today = new Date();
     for (let day = 1; day <= daysInMonth; day++) {
        // Small calendar button
         const button = document.createElement('button');
         button.className = 'h-10 text-center text-sm hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer';
         button.textContent = day;
        button.dataset.monthType = 'current';
         button.style.pointerEvents = 'auto';
         button.type = 'button';
        button.onclick = function(e) {
            if (e) {
                e.stopPropagation();
                e.stopImmediatePropagation();
                e.preventDefault();
            }
            selectDate(button);
            return false;
        };
       calendarGrid.appendChild(button);
         
        // Main calendar cell
        const cellDiv = document.createElement('div');
         const isToday = today.getDate() === day && today.getMonth() === currentMonth && today.getFullYear() === currentYear;
         
        // Use consistent structure for all dates to ensure proper event alignment
        cellDiv.className = 'h-28 border-r border-b border-border-light dark:border-border-dark flex flex-col items-start pt-2 px-1 overflow-hidden';
        
        if (isToday) {
           cellDiv.innerHTML = `
             <div class="relative w-full flex justify-center mb-1">
               <span class="bg-blue-600 text-white rounded-full w-6 h-6 inline-flex items-center justify-center text-xs leading-none relative z-10">${day}</span>
             </div>
           `;
         } else {
           cellDiv.innerHTML = `
             <div class="relative w-full flex justify-center mb-1">
               <span class="text-xs mx-auto w-6 h-6 inline-flex items-center justify-center">${day}</span>
             </div>
           `;
         }
        cellDiv.dataset.monthType = 'current';
         mainCalendarGrid.appendChild(cellDiv);
     }
     
     // Next month days to fill the grid
     const remainingCells = 42 - (startingDayOfWeek + daysInMonth); // 6 rows * 7 days
     for (let day = 1; day <= remainingCells; day++) {
        // Small calendar button
         const button = document.createElement('button');
         button.className = 'h-10 text-center text-sm text-gray-400 dark:text-gray-500 hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg cursor-pointer';
         button.textContent = day;
        button.dataset.monthType = 'next';
         button.style.pointerEvents = 'auto';
         button.type = 'button';
        button.onclick = function(e) {
            if (e) {
                e.stopPropagation();
                e.stopImmediatePropagation();
                e.preventDefault();
            }
            selectDate(button);
            return false;
        };
       calendarGrid.appendChild(button);
         
        // Main calendar cell
        const cellDiv = document.createElement('div');
        cellDiv.className = 'h-28 border-r border-b border-border-light dark:border-border-dark flex flex-col items-start pt-2 px-1 text-muted-light dark:text-muted-dark overflow-hidden';
        cellDiv.innerHTML = `<span class="text-xs mx-auto">${day}</span>`;
        cellDiv.dataset.monthType = 'next';
         mainCalendarGrid.appendChild(cellDiv);
     }
 }

// Render the sidebar mini calendar independently with clickable days
function renderSidebarCalendar() {
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const currentMonth = sidebarCalendarDate.getMonth();
    const currentYear = sidebarCalendarDate.getFullYear();
    const sidebarMonthEl = document.getElementById('sidebarMonthDisplay');
    if (sidebarMonthEl) sidebarMonthEl.textContent = `${monthNames[currentMonth]} ${currentYear}`;

    const grid = document.getElementById('sidebarCalendarGrid');
    if (!grid) return;
    grid.innerHTML = '';

    const firstDay = new Date(currentYear, currentMonth, 1);
    const lastDay = new Date(currentYear, currentMonth + 1, 0);
    const daysInMonth = lastDay.getDate();
    const startingDayOfWeek = firstDay.getDay();

    const prevMonth = new Date(currentYear, currentMonth - 1, 0);
    const daysInPrevMonth = prevMonth.getDate();

    // Previous month spillover
    for (let i = startingDayOfWeek - 1; i >= 0; i--) {
        const day = daysInPrevMonth - i;
        const button = document.createElement('button');
        button.className = 'h-8 text-center text-[12px] text-gray-400 dark:text-gray-500 hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg';
        button.textContent = day;
        button.dataset.monthType = 'prev';
        button.onclick = () => selectDate(button);
        grid.appendChild(button);
    }

    // Current month
    for (let day = 1; day <= daysInMonth; day++) {
        const button = document.createElement('button');
        button.className = 'h-8 text-center text-[12px] hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg';
        button.textContent = day;
        button.dataset.monthType = 'current';
        button.onclick = () => selectDate(button);
        grid.appendChild(button);
    }

    // Next month spillover to fill 6 rows
    const remainingCells = 42 - (startingDayOfWeek + daysInMonth);
    for (let day = 1; day <= remainingCells; day++) {
        const button = document.createElement('button');
        button.className = 'h-8 text-center text-[12px] text-gray-400 dark:text-gray-500 hover:bg-gray-800 dark:hover:bg-gray-200 hover:text-white dark:hover:text-gray-800 rounded-lg';
        button.textContent = day;
        button.dataset.monthType = 'next';
        button.onclick = () => selectDate(button);
        grid.appendChild(button);
    }
}

 // (Year dropdown removed from the date picker header; month+year is shown in #currentMonthDisplay)
 
 function selectDate(dateElement) {
     if (!dateElement) {
         return;
     }
     
     const dateText = dateElement.textContent ? dateElement.textContent.trim() : '';
     if (!dateText || isNaN(parseInt(dateText))) {
         return;
     }
     
     const selectedDate = parseInt(dateText);
     
     // Check if this is from the date picker modal (calendarGrid) or main calendar
     const calendarGrid = document.getElementById('calendarGrid');
     const datePickerModal = document.getElementById('datePickerModal');
     const isFromDatePicker = calendarGrid && calendarGrid.contains(dateElement);
     
     // Get month type from data attribute
     const monthType = dateElement.dataset.monthType || 'current';
     
     // Get the correct month and year based on the month type
     const currentMonth = currentCalendarDate.getMonth();
     const currentYear = currentCalendarDate.getFullYear();
     let selectedMonth = currentMonth;
     let selectedYear = currentYear;
     
     if (monthType === 'prev') {
         selectedMonth = currentMonth - 1;
         if (selectedMonth < 0) {
             selectedMonth = 11;
             selectedYear = currentYear - 1;
         }
     } else if (monthType === 'next') {
         selectedMonth = currentMonth + 1;
         if (selectedMonth > 11) {
             selectedMonth = 0;
             selectedYear = currentYear + 1;
         }
     }
     
     const date = new Date(selectedYear, selectedMonth, selectedDate);
     
     // Validate the date
     if (isNaN(date.getTime())) {
         console.error('Invalid date created:', { selectedYear, selectedMonth, selectedDate });
         return;
     }
     
    // If this is from the date picker modal, update the date field and close modal
     const datePickerVisible = datePickerModal && !datePickerModal.classList.contains('hidden');
     if (isFromDatePicker || datePickerVisible) {
       // Update the new date inputs in the create modal (preferred)
       const eventDateInput = document.getElementById('eventDateInput'); // display (MM/DD/YYYY)
       const eventDateIso = document.getElementById('eventDateIso'); // ISO (YYYY-MM-DD)
       const mm = String(date.getMonth() + 1).padStart(2, '0');
       const dd = String(date.getDate()).padStart(2, '0');
       const yyyy = String(date.getFullYear());
       if (eventDateIso) eventDateIso.value = `${yyyy}-${mm}-${dd}`;
       if (eventDateInput) eventDateInput.value = `${mm}/${dd}/${yyyy}`;

        // Backward compatibility: update the old date button if it exists
        const dateButton = document.querySelector('button[onclick="toggleTimePicker()"]');
        if (dateButton) {
            const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            const dayName = dayNames[date.getDay()];
            const monthName = monthNames[date.getMonth()];
            dateButton.textContent = `${dayName}, ${selectedDate} ${monthName} ${selectedYear}`;
        }
         
         // Close the date picker modal
         closeDatePickerModal();
         return;
     }
     
     // Otherwise, this is from the main calendar or sidebar calendar
     // Update the date button text if create modal is open
     const dateButton = document.querySelector('button[onclick="toggleTimePicker()"]');
     if (dateButton) {
         const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
         const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
         
         const dayName = dayNames[date.getDay()];
         const monthName = monthNames[date.getMonth()];
        
         dateButton.textContent = `${dayName}, ${selectedDate} ${monthName} ${selectedYear}`;
     }
     
     // Update the main calendar view to show the selected date
     const mainGrid = document.getElementById('mainCalendarGrid');
     const dayContainer = document.getElementById('dayCalendarContainer');
     const weekContainer = document.getElementById('weekCalendarContainer');
     const viewLabel = document.getElementById('viewBtnLabel');
     const isWeek = viewLabel && viewLabel.textContent.trim() === 'Week';
     if (mainGrid) mainGrid.innerHTML = '';
     if (isWeek) {
         if (dayContainer) dayContainer.classList.add('hidden');
         if (weekContainer) weekContainer.classList.remove('hidden');
         renderWeekView(date);
     } else {
         if (weekContainer) weekContainer.classList.add('hidden');
         if (dayContainer) dayContainer.classList.remove('hidden');
         renderDayView(date);
         if (viewLabel) viewLabel.textContent = 'Day';
     }
}

// Make function globally accessible
window.selectDate = selectDate;

// Event delegation for calendar grid date buttons - handles clicks reliably
document.addEventListener('click', function(e) {
    const calendarGrid = document.getElementById('calendarGrid');
    if (!calendarGrid || !calendarGrid.contains(e.target)) {
        return;
    }
    
    // Find the button that was clicked
    const button = e.target.closest('button');
    if (button && button.dataset.monthType !== undefined) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        selectDate(button);
        return false;
    }
}, true); // Use capture phase

// Close modal when clicking outside
const createModalElement = document.getElementById('createModal');
if (createModalElement) {
    createModalElement.addEventListener('click', function(e) {
        const datePickerModal = document.getElementById('datePickerModal');
        
        // If date picker modal is open, don't close create modal - let date picker handle its own clicks
        if (datePickerModal && !datePickerModal.classList.contains('hidden')) {
            // Don't close create modal if date picker is open
            return;
        }
        
        // Only close if clicking directly on the backdrop (this element)
        if (e.target === this) {
            if (typeof closeCreateModal === 'function') {
                closeCreateModal();
            }
        }
    });
}

// Close date picker modal when clicking outside - REMOVED to prevent blocking date button clicks

// Close button for date picker modal - use event delegation for reliability
document.addEventListener('click', function(e) {
    // Check if the click is on the close button or its icon
    const closeBtn = e.target.closest('#closeDatePickerBtn');
    const isCloseIcon = e.target.tagName === 'SPAN' && e.target.parentElement && e.target.parentElement.id === 'closeDatePickerBtn';
    
    if (e.target.id === 'closeDatePickerBtn' || closeBtn || isCloseIcon) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        console.log('Close button clicked, closing date picker modal');
        closeDatePickerModal(e);
        return false;
    }
    
}, true); // Use capture phase to catch it early
 
 // Close time picker modal when clicking outside
 document.getElementById('timePickerModal').addEventListener('click', function(e) {
     if (e.target === this) {
         closeTimePickerModal();
     }
 });
 
 // Add event listeners for input fields
 document.addEventListener('DOMContentLoaded', function() {
     // Location input events (only if it's hidden, otherwise it's always visible)
     const locationInput = document.getElementById('locationInput');
     if (locationInput && locationInput.classList.contains('hidden')) {
         locationInput.addEventListener('keypress', function(e) {
             if (e.key === 'Enter') {
                 editLocation();
             }
         });
         locationInput.addEventListener('blur', function() {
             editLocation();
         });
     }
     
     // Description input events (only if it's not a textarea)
     const descriptionInput = document.getElementById('descriptionInput');
     if (descriptionInput && descriptionInput.tagName !== 'TEXTAREA') {
         descriptionInput.addEventListener('keypress', function(e) {
             if (e.key === 'Enter') {
                 editDescription();
             }
         });
         descriptionInput.addEventListener('blur', function() {
             editDescription();
         });
     }
     
     // Event type tag buttons
     document.querySelectorAll('.event-type-tag').forEach(button => {
         button.addEventListener('click', function() {
             // Remove active class from all buttons
             document.querySelectorAll('.event-type-tag').forEach(btn => {
                 btn.classList.remove('active', 'bg-primary/10', 'text-primary', 'border-primary/20', 'font-semibold');
                 btn.classList.add('bg-slate-100', 'dark:bg-slate-800', 'text-slate-600', 'dark:text-slate-400', 'border-slate-200', 'dark:border-slate-700', 'font-medium');
             });
             // Add active class to clicked button
             this.classList.add('active', 'bg-primary/10', 'text-primary', 'border-primary/20', 'font-semibold');
             this.classList.remove('bg-slate-100', 'dark:bg-slate-800', 'text-slate-600', 'dark:text-slate-400', 'border-slate-200', 'dark:border-slate-700', 'font-medium');
             // Update hidden select
             const eventTypeSelect = document.getElementById('eventTypeSelect');
             if (eventTypeSelect) {
                 eventTypeSelect.value = this.getAttribute('data-type');
             }
         });
     });
     
     // Repeat select handler
     const repeatSelect = document.getElementById('repeatSelect');
     if (repeatSelect) {
         repeatSelect.addEventListener('change', function() {
             selectRepeatOption(this.value);
         });
     }
     
     // Close repeat dropdown when clicking outside
     document.addEventListener('click', function(e) {
         const repeatDropdown = document.getElementById('repeatDropdown');
         const repeatButton = document.getElementById('repeatButton');
         if (!repeatDropdown.contains(e.target) && !repeatButton.contains(e.target)) {
             repeatDropdown.classList.add('hidden');
         }
         
     // Close color picker when clicking outside
     const colorPicker = document.getElementById('colorPicker');
     const colorButton = document.querySelector('button[onclick="toggleColorPicker()"]');
     if (colorPicker && colorButton && !colorPicker.contains(e.target) && !colorButton.contains(e.target)) {
         colorPicker.classList.add('hidden');
     }
     
     // Close notification dropdown when clicking outside
     const notificationDropdown = document.getElementById('notificationDropdown');
     const notificationButton = document.querySelector('div[onclick="toggleNotificationDropdown()"]');
     if (notificationDropdown && notificationButton && !notificationDropdown.contains(e.target) && !notificationButton.contains(e.target)) {
         notificationDropdown.classList.add('hidden');
     }
     
     // Close reminder dropdown when clicking outside (don't close when clicking the + button)
     const reminderDropdown = document.getElementById('reminderDropdown');
     const reminderButton = document.getElementById('reminderButton');
     const reminderAddBtn = document.getElementById('reminderAddBtn');
     const inReminderArea = reminderDropdown && (reminderDropdown.contains(e.target) || (reminderButton && reminderButton.contains(e.target)) || (reminderAddBtn && reminderAddBtn.contains(e.target)));
     if (reminderDropdown && !inReminderArea) {
         reminderDropdown.classList.add('hidden');
     }
     });
     
    // Hook up main and sidebar navigation buttons
    const mainPrevBtn = document.getElementById('mainPrevBtn');
    const mainNextBtn = document.getElementById('mainNextBtn');
    if (mainPrevBtn) mainPrevBtn.addEventListener('click', prevMonth);
    if (mainNextBtn) mainNextBtn.addEventListener('click', nextMonth);
   const sidebarPrevBtn = document.getElementById('sidebarPrevBtn');
   const sidebarNextBtn = document.getElementById('sidebarNextBtn');
   // Sidebar arrows advance only the sidebar mini calendar
   function sidebarPrevMonth() {
       sidebarCalendarDate.setMonth(sidebarCalendarDate.getMonth() - 1);
       renderSidebarCalendar();
   }
   function sidebarNextMonth() {
       sidebarCalendarDate.setMonth(sidebarCalendarDate.getMonth() + 1);
       renderSidebarCalendar();
   }
   if (sidebarPrevBtn) sidebarPrevBtn.addEventListener('click', sidebarPrevMonth);
   if (sidebarNextBtn) sidebarNextBtn.addEventListener('click', sidebarNextMonth);

    // Today button behavior: jump to current month and sync sidebar
    const todayBtn = document.getElementById('todayBtn');
    const viewSelect = document.getElementById('viewSelect');
    if (todayBtn) todayBtn.addEventListener('click', function() {
        const now = new Date();
        currentCalendarDate = new Date(now.getFullYear(), now.getMonth(), 1);
        renderCalendar();
        renderEventsOnCalendar();
        sidebarCalendarDate = new Date(currentCalendarDate.getTime());
        renderSidebarCalendar();
    });
    // Replace native select with custom dropdown handlers
    const viewBtn = document.getElementById('viewBtn');
    const viewDropdown = document.getElementById('viewDropdown');
    const viewLabel = document.getElementById('viewBtnLabel');
    if (viewBtn && viewDropdown) {
        viewBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            viewDropdown.classList.toggle('hidden');
        });
        viewDropdown.querySelectorAll('button[data-view]').forEach(btn => {
            btn.addEventListener('click', () => setView(btn.getAttribute('data-view')));
        });
        document.addEventListener('click', (e) => {
            if (!viewDropdown.contains(e.target) && !viewBtn.contains(e.target)) {
                viewDropdown.classList.add('hidden');
            }
        });
    }


    // Render the calendars
    renderCalendar();
    renderSidebarCalendar();
     
    // Load and render existing events when page loads
    renderEventsOnCalendar();
    renderMyEvents();
    renderMyEvents();

    // Draggable Create Modal
    const createOverlay = document.getElementById('createModal');
    const createHeader = document.getElementById('createModalHeader');
    const createCard = document.getElementById('createModalCard');
    if (createHeader && createCard && createOverlay) {
        let isDragging = false;
        let startX = 0, startY = 0, origLeft = 0, origTop = 0;

        function onMouseDown(e) {
            // Ignore drags starting from non-draggable controls
            const target = e.target;
            if (target.closest('[data-no-drag]') || target.closest('button') || target.closest('input') || target.closest('select') || target.closest('a')) {
                return;
            }
            isDragging = true;
            createOverlay.classList.add('dragging');
            const rect = createCard.getBoundingClientRect();
            // Switch to absolute positioned card anchored to viewport
            createCard.style.position = 'absolute';
            createCard.style.left = `${rect.left}px`;
            createCard.style.top = `${rect.top + window.scrollY}px`;
            createCard.style.transform = 'none';
            startX = e.clientX;
            startY = e.clientY;
            origLeft = rect.left;
            origTop = rect.top + window.scrollY;
            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp, { once: true });
        }

        function onMouseMove(e) {
            if (!isDragging) return;
            const dx = e.clientX - startX;
            const dy = e.clientY - startY;
            let nextLeft = origLeft + dx;
            let nextTop = origTop + dy;
            // Constrain within viewport
            const vw = window.innerWidth;
            const vh = window.innerHeight;
            const cr = createCard.getBoundingClientRect();
            const width = cr.width;
            const height = cr.height;
            nextLeft = Math.max(8, Math.min(nextLeft, vw - width - 8));
            nextTop = Math.max(8 + window.scrollY, Math.min(nextTop, window.scrollY + vh - height - 8));
            createCard.style.left = `${nextLeft}px`;
            createCard.style.top = `${nextTop}px`;
        }

        function onMouseUp() {
            isDragging = false;
            createOverlay.classList.remove('dragging');
            document.removeEventListener('mousemove', onMouseMove);
            // Don't persist position - always reset to center on next open
            // This ensures the modal is always centered when opened
        }

        createHeader.addEventListener('mousedown', onMouseDown);
    }

    // Event detail modal close and backdrop click
    const detailModal = document.getElementById('eventDetailModal');
    const detailCloseBtn = document.getElementById('detailCloseBtn');
    if (detailCloseBtn) detailCloseBtn.addEventListener('click', () => {
        detailModal.classList.add('hidden');
        detailModal.classList.remove('flex');
        detailModal.style.display = 'none';
    });
    // Edit current event -> open create modal prefilled
    const detailEditBtn = document.getElementById('detailEditBtn');
    if (detailEditBtn) detailEditBtn.addEventListener('click', () => {
        if (!currentOpenedEventId) return;
        const evt = events.find(ev => String(ev.id) === String(currentOpenedEventId));
        if (!evt) return;
        // Prefill fields
        document.getElementById('eventTitle').value = evt.title || '';
        // Date/time (new inputs)
        const eventDateInput = document.getElementById('eventDateInput'); // display
        const eventDateIso = document.getElementById('eventDateIso'); // ISO
        if ((eventDateInput || eventDateIso) && evt.date) {
            // evt.date format: "DayName, D MonthName YYYY"
            const dateToParse = String(evt.date).replace(/^[A-Za-z]+,\s*/i, '').trim();
            const m = dateToParse.match(/^(\d{1,2})\s+(\w+)\s+(\d{4})$/);
            if (m) {
                const day = parseInt(m[1], 10);
                const monthName = m[2].toLowerCase();
                const year = parseInt(m[3], 10);
                const monthNames = ['january','february','march','april','may','june','july','august','september','october','november','december'];
                const monthIndex = monthNames.indexOf(monthName);
                if (monthIndex !== -1) {
                    const mm = String(monthIndex + 1).padStart(2, '0');
                    const dd = String(day).padStart(2, '0');
                    if (eventDateIso) eventDateIso.value = `${year}-${mm}-${dd}`;
                    if (eventDateInput) eventDateInput.value = `${mm}/${dd}/${year}`;
                }
            }
        }
        const startTimeInput = document.getElementById('startTimeInput');
        const endTimeInput = document.getElementById('endTimeInput');
        if (startTimeInput && evt.startTime) startTimeInput.value = time24ToDisplay(evt.startTime);
        if (endTimeInput && evt.endTime) endTimeInput.value = time24ToDisplay(evt.endTime);

        // Back-compat elements (only if they exist)
        const dateBtn = document.querySelector('button[onclick="toggleTimePicker()"]');
        if (dateBtn && evt.date) dateBtn.textContent = evt.date;
        const startTimeBtn = document.getElementById('startTimeBtn');
        const endTimeBtn = document.getElementById('endTimeBtn');
        if (startTimeBtn && evt.startTime) startTimeBtn.textContent = time24ToDisplay(evt.startTime);
        if (endTimeBtn && evt.endTime) endTimeBtn.textContent = time24ToDisplay(evt.endTime);

        // Location/description (new inputs)
        const locationInput = document.getElementById('locationInput');
        if (locationInput) locationInput.value = evt.location || '';
        const descriptionInput = document.getElementById('descriptionInput');
        if (descriptionInput && descriptionInput.tagName === 'TEXTAREA') descriptionInput.value = evt.description || '';

        // Back-compat text spans (only if they exist)
        const locationText = document.getElementById('locationText');
        if (locationText) locationText.textContent = evt.location ? evt.location : 'Add location';
        const descriptionText = document.getElementById('descriptionText');
        if (descriptionText) descriptionText.textContent = evt.description ? evt.description : 'Add description or a Google Drive attachment';
        // Reminder
        const reminderText = document.getElementById('reminderText');
        if (reminderText) {
            reminderText.textContent = evt.reminder || '30 minutes before';
        }
        // Color button
        const colorBtn = document.querySelector('button[onclick="toggleColorPicker()"]');
        if (colorBtn && evt.color) {
            colorBtn.className = colorBtn.className.replace(/bg-\w+-\d+|bg-transparent|border-2\sborder-\w+-\d+/g, '').trim();
            colorBtn.className += ` ${evt.color}`;
        }
        // Close detail modal, open create modal in edit mode
        detailModal.classList.add('hidden');
        detailModal.classList.remove('flex');
        detailModal.style.display = 'none';
        isEditingExisting = true;
        openCreateModal();
    });
    
    // Reminder button -> open create modal prefilled and focus on reminder
    const detailReminderBtn = document.getElementById('detailReminderBtn');
    if (detailReminderBtn) detailReminderBtn.addEventListener('click', () => {
        if (!currentOpenedEventId) return;
        const evt = events.find(ev => String(ev.id) === String(currentOpenedEventId));
        if (!evt) return;
        // Prefill fields
        document.getElementById('eventTitle').value = evt.title || '';
        // Date/time (new inputs)
        const eventDateInput = document.getElementById('eventDateInput'); // display
        const eventDateIso = document.getElementById('eventDateIso'); // ISO
        if ((eventDateInput || eventDateIso) && evt.date) {
            const dateToParse = String(evt.date).replace(/^[A-Za-z]+,\s*/i, '').trim();
            const m = dateToParse.match(/^(\d{1,2})\s+(\w+)\s+(\d{4})$/);
            if (m) {
                const day = parseInt(m[1], 10);
                const monthName = m[2].toLowerCase();
                const year = parseInt(m[3], 10);
                const monthNames = ['january','february','march','april','may','june','july','august','september','october','november','december'];
                const monthIndex = monthNames.indexOf(monthName);
                if (monthIndex !== -1) {
                    const mm = String(monthIndex + 1).padStart(2, '0');
                    const dd = String(day).padStart(2, '0');
                    if (eventDateIso) eventDateIso.value = `${year}-${mm}-${dd}`;
                    if (eventDateInput) eventDateInput.value = `${mm}/${dd}/${year}`;
                }
            }
        }
        const startTimeInput = document.getElementById('startTimeInput');
        const endTimeInput = document.getElementById('endTimeInput');
        if (startTimeInput && evt.startTime) startTimeInput.value = time24ToDisplay(evt.startTime);
        if (endTimeInput && evt.endTime) endTimeInput.value = time24ToDisplay(evt.endTime);

        // Back-compat elements (only if they exist)
        const dateBtn = document.querySelector('button[onclick="toggleTimePicker()"]');
        if (dateBtn && evt.date) dateBtn.textContent = evt.date;
        const startTimeBtn = document.getElementById('startTimeBtn');
        const endTimeBtn = document.getElementById('endTimeBtn');
        if (startTimeBtn && evt.startTime) startTimeBtn.textContent = time24ToDisplay(evt.startTime);
        if (endTimeBtn && evt.endTime) endTimeBtn.textContent = time24ToDisplay(evt.endTime);

        // Location/description (new inputs)
        const locationInput = document.getElementById('locationInput');
        if (locationInput) locationInput.value = evt.location || '';
        const descriptionInput = document.getElementById('descriptionInput');
        if (descriptionInput && descriptionInput.tagName === 'TEXTAREA') descriptionInput.value = evt.description || '';

        // Back-compat text spans (only if they exist)
        const locationText = document.getElementById('locationText');
        if (locationText) locationText.textContent = evt.location ? evt.location : 'Add location';
        const descriptionText = document.getElementById('descriptionText');
        if (descriptionText) descriptionText.textContent = evt.description ? evt.description : 'Add description or a Google Drive attachment';
        // Reminder
        const reminderText = document.getElementById('reminderText');
        if (reminderText) {
            reminderText.textContent = evt.reminder || '30 minutes before';
        }
        // Color button
        const colorBtn = document.querySelector('button[onclick="toggleColorPicker()"]');
        if (colorBtn && evt.color) {
            colorBtn.className = colorBtn.className.replace(/bg-\w+-\d+|bg-transparent|border-2\sborder-\w+-\d+/g, '').trim();
            colorBtn.className += ` ${evt.color}`;
        }
        // Close detail modal, open create modal in edit mode
        detailModal.classList.add('hidden');
        detailModal.classList.remove('flex');
        detailModal.style.display = 'none';
        isEditingExisting = true;
        openCreateModal();
        
        // Update reminder dropdown visual state and focus
        setTimeout(() => {
            const currentReminder = evt.reminder || '30 minutes before';
            const reminderOptions = document.querySelectorAll('.reminder-option');
            reminderOptions.forEach(opt => {
                const check = opt.querySelector('.reminder-check');
                if (opt.getAttribute('data-value') === currentReminder) {
                    opt.classList.add('bg-blue-50', 'dark:bg-blue-900/20');
                    if (check) check.classList.remove('hidden');
                } else {
                    opt.classList.remove('bg-blue-50', 'dark:bg-blue-900/20');
                    if (check) check.classList.add('hidden');
                }
            });
            
            const reminderButton = document.getElementById('reminderButton');
            if (reminderButton) {
                reminderButton.scrollIntoView({ behavior: 'smooth', block: 'center' });
                // Open the reminder dropdown
                toggleReminderDropdown();
            }
        }, 150);
    });
    // Delete current event/schedule
    const detailDeleteBtn = document.getElementById('detailDeleteBtn');
    if (detailDeleteBtn) {
        // Remove any existing listeners by cloning the element
        const newDeleteBtn = detailDeleteBtn.cloneNode(true);
        detailDeleteBtn.parentNode.replaceChild(newDeleteBtn, detailDeleteBtn);
        
        newDeleteBtn.addEventListener('click', async function(e) {
            e.stopPropagation();
            e.preventDefault();
            
            console.log('🗑️ Delete button clicked, currentOpenedEventId:', currentOpenedEventId);
            
            if (currentOpenedEventId == null) {
                console.warn('No event ID to delete');
                showToast('No event selected to delete', 'error');
                return;
            }
            
            // Use native confirm for reliability
            const confirmed = window.confirm('Are you sure you want to delete this schedule?');
            console.log('Confirmation result:', confirmed);
            
            if (!confirmed) {
                console.log('Delete cancelled by user');
                return;
            }
            
            console.log('✅ User confirmed deletion, proceeding...');
            
            try {
                console.log('Proceeding with delete, ID:', currentOpenedEventId);
                console.log('Current events array length:', events.length);
                
                let deleted = false;
                
                // First, try to delete from database (database is source of truth)
                if (typeof API_BASE !== 'undefined' && typeof AUTH_TOKEN !== 'undefined') {
                    try {
                        console.log('Attempting API delete for ID:', currentOpenedEventId);
                        const deleteUrl = API_BASE + '?id=' + encodeURIComponent(currentOpenedEventId);
                        console.log('Delete URL:', deleteUrl);
                        
                        const response = await fetch(deleteUrl, {
                            method: 'DELETE',
                            headers: {
                                'Authorization': 'Bearer ' + AUTH_TOKEN,
                                'Content-Type': 'application/json'
                            }
                        });
                        
                        console.log('API response status:', response.status);
                        const responseText = await response.text();
                        console.log('API response text:', responseText);
                        
                        if (response.ok) {
                            let result;
                            try {
                                result = JSON.parse(responseText);
                                console.log('API delete result:', result);
                                if (result.success) {
                                    deleted = true;
                                    console.log('✅ Successfully deleted from database');
                                } else {
                                    console.warn('⚠️ Database delete returned success: false', result);
                                }
                            } catch (e) {
                                console.error('Failed to parse API response as JSON:', e);
                                console.log('Raw response:', responseText);
                                // If response is OK but not JSON, assume success
                                if (response.status === 200 || response.status === 204) {
                                    deleted = true;
                                    console.log('✅ Delete successful (non-JSON response)');
                                }
                            }
                        } else {
                            console.warn('⚠️ API delete failed with status:', response.status, responseText);
                        }
                    } catch (apiError) {
                        console.error('❌ API delete error:', apiError);
                        showToast('Error deleting from database: ' + apiError.message, 'error');
                    }
                } else {
                    console.warn('API_BASE or AUTH_TOKEN not defined');
                }
                
                // Also delete from localStorage if it exists there (for locally created events)
                const eventIndex = events.findIndex(ev => {
                    const evId = String(ev.id);
                    const targetId = String(currentOpenedEventId);
                    return evId === targetId;
                });
                
                if (eventIndex !== -1) {
                    console.log('Found event in localStorage, removing...');
                    events.splice(eventIndex, 1);
                    localStorage.setItem('calendarEvents', JSON.stringify(events));
                    deleted = true;
                    console.log('✅ Removed from localStorage');
                } else {
                    console.log('Event not found in localStorage (may be database-only)');
                }
                
                // Close modal and remove any overlays - do this FIRST before reloading
                if (detailModal) {
                    detailModal.classList.add('hidden');
                    detailModal.classList.remove('flex');
                    detailModal.style.display = 'none';
                    detailModal.style.visibility = 'hidden';
                }
                
                // Close attendance modal if it's open for this deleted event
                const attendanceModal = document.getElementById('eventAttendanceModal');
                if (attendanceModal && attendanceModal.dataset.eventId === String(currentOpenedEventId)) {
                    attendanceModal.classList.add('hidden');
                    document.body.classList.remove('attendance-modal-open');
                }
                
                // Also remove any confirmation modals that might be lingering
                const confirmModal = document.getElementById('confirmModal');
                if (confirmModal) {
                    confirmModal.remove();
                }
                
                // Force remove any backdrop overlays (the grey background)
                document.querySelectorAll('[class*="bg-black"][class*="fixed"][class*="inset-0"]').forEach(overlay => {
                    const id = overlay.id;
                    // Don't remove active modals, only stale ones
                    if (!id || (id !== 'createModal' && id !== 'timePickerModal' && id !== 'datePickerModal' && id !== 'eventDetailModal')) {
                        if (overlay.classList.contains('bg-black') || overlay.classList.contains('bg-black/30') || overlay.classList.contains('bg-black/50')) {
                            overlay.style.display = 'none';
                            overlay.style.visibility = 'hidden';
                            overlay.classList.add('hidden');
                        }
                    }
                });
                
                currentOpenedEventId = null;
                
                // Reload from database to get updated list (this will update the calendar display)
                if (typeof loadSchedules === 'function') {
                    console.log('Reloading schedules from database...');
                    try {
                        await loadSchedules();
                        console.log('✅ Schedules reloaded');
                    } catch (loadError) {
                        console.error('Error reloading schedules:', loadError);
                        // Still update display manually
                        renderEventsOnCalendar();
                        renderMyEvents();
                    }
                } else {
                    // Fallback: update display manually
                    console.log('loadSchedules not available, updating display manually...');
                    renderEventsOnCalendar();
                    renderMyEvents();
                }
                
                if (deleted) {
                    showDeleteSuccessModal();
                } else {
                    showToast('Event removed from display', 'info');
                }
                console.log('✅ Delete process completed');
            } catch (error) {
                console.error('❌ Error in delete process:', error);
                showToast('Error: ' + (error.message || 'Failed to delete schedule'), 'error');
            }
        });
    }
    if (detailModal) detailModal.addEventListener('click', (e) => {
        if (e.target === detailModal) {
            detailModal.classList.add('hidden');
            detailModal.classList.remove('flex');
            detailModal.style.display = 'none';
        }
    });

    // Event delegation for opening event details
    const grid = document.getElementById('mainCalendarGrid');
    grid.addEventListener('click', function(e) {
        const el = e.target.closest ? e.target.closest('.event-bar') : null;
        if (el) {
            const id = el.dataset.eventId;
            const evt = events.find(ev => String(ev.id) === String(id));
            openEventDetail(evt || {});
        }
    });

   // Right-click context menu for events
   const ctxMenu = document.getElementById('eventContextMenu');
   grid.addEventListener('contextmenu', function(e) {
       const el = e.target.closest ? e.target.closest('.event-bar') : null;
       if (!el) return; // let browser default for non-events
       e.preventDefault();
       ctxTargetEventId = el.dataset.eventId;
       // Position menu at cursor with viewport constraints
       const menu = ctxMenu;
       const vw = window.innerWidth;
       const vh = window.innerHeight;
       menu.style.display = 'block';
       menu.classList.remove('hidden');
       const rect = menu.getBoundingClientRect();
       const left = Math.min(e.clientX, vw - rect.width - 8);
       const top = Math.min(e.clientY, vh - rect.height - 8) + window.scrollY;
       menu.style.left = left + 'px';
       menu.style.top = top + 'px';
   });

   // Hide context menu on click elsewhere or scroll/resize
   document.addEventListener('click', function(e) {
       if (!ctxMenu.classList.contains('hidden') && !ctxMenu.contains(e.target)) {
           ctxMenu.classList.add('hidden');
           ctxMenu.style.display = 'none';
       }
   });
   window.addEventListener('scroll', () => { ctxMenu.classList.add('hidden'); ctxMenu.style.display = 'none'; });
   window.addEventListener('resize', () => { ctxMenu.classList.add('hidden'); ctxMenu.style.display = 'none'; });

   // Context menu actions - Delete (use API, not localStorage)
   const ctxDelete = document.getElementById('ctxDelete');
  if (ctxDelete) ctxDelete.addEventListener('click', async () => {
      if (!ctxTargetEventId) return;
      // Direct delete without confirmation modal / dark overlay
       try {
           console.log('Attempting to delete schedule with ID:', ctxTargetEventId);
           
           // Delete from database via API
           const response = await fetch(API_BASE + '?id=' + encodeURIComponent(ctxTargetEventId), {
               method: 'DELETE',
               headers: {
                   'Authorization': 'Bearer ' + AUTH_TOKEN,
                   'Content-Type': 'application/json'
               }
           });
           
           console.log('Delete response status:', response.status);
           const responseText = await response.text();
           console.log('Delete response text:', responseText);
           
           let result;
           try {
               result = JSON.parse(responseText);
           } catch (e) {
               console.error('Failed to parse JSON response:', e);
               showToast('Error: Server returned invalid response. Check console for details.', 'error');
               return;
           }
           
           console.log('Delete result:', result);
           
           if (result.success) {
               // Close attendance modal if it's open for this deleted event
               const attendanceModal = document.getElementById('eventAttendanceModal');
               if (attendanceModal && attendanceModal.dataset.eventId === String(ctxTargetEventId)) {
                   attendanceModal.classList.add('hidden');
                   document.body.classList.remove('attendance-modal-open');
               }
               
               showDeleteSuccessModal();
               
               // Reload directly from database (single source of truth)
               // No localStorage manipulation - database is authoritative
               if (typeof loadSchedules === 'function') {
                   await loadSchedules();
               } else {
                   // Fallback: reload page
                   window.location.reload();
               }
               
               // Close context menu
               ctxMenu.classList.add('hidden');
               ctxMenu.style.display = 'none';
           } else {
               const errorMsg = result.error || result.message || 'Failed to delete schedule';
               console.error('Delete failed:', errorMsg);
               showToast('Error: ' + errorMsg, 'error');
           }
       } catch (error) {
           console.error('Error deleting schedule:', error);
           showToast('Error: ' + error.message, 'error');
       }
   });
   // Color choices (apply and persist with tooltip names)
   ctxMenu.querySelectorAll('[data-color-choice]').forEach(btn => {
       btn.addEventListener('click', () => {
           const color = btn.getAttribute('data-color-choice');
           const idx = events.findIndex(ev => String(ev.id) === String(ctxTargetEventId));
           if (idx !== -1) {
               events[idx].color = color;
               localStorage.setItem('calendarEvents', JSON.stringify(events));
               renderEventsOnCalendar();
              renderMyEvents();
           }
           ctxMenu.classList.add('hidden');
           ctxMenu.style.display = 'none';
       });
   });
 });
 
 // Toggle user settings panel
function toggleUserSettings() {
    const userSettingsPanel = document.getElementById('userSettingsPanel');
    if (userSettingsPanel) {
        userSettingsPanel.classList.toggle('hidden');
    }
}
 
 // Toggle color picker
 function toggleColorPicker() {
     const colorPicker = document.getElementById('colorPicker');
     colorPicker.classList.toggle('hidden');
 }
 
 // Toggle notification dropdown
 function toggleNotificationDropdown() {
     const notificationDropdown = document.getElementById('notificationDropdown');
     notificationDropdown.classList.toggle('hidden');
 }
 
// Select notification option
function selectNotification(option) {
    // Replace "Add notification" text with the selected option
    const addNotificationElement = document.querySelector('div[onclick="toggleNotificationDropdown()"]');
    
    // Handle "Days before expiry" option
    if (option === 'Days before expiry') {
        addNotificationElement.innerHTML = `
            <div class="flex items-center gap-2">
                <span class="text-sm text-gray-700 dark:text-gray-300">Days before expiry:</span>
                <input type="number" id="notificationDaysInput" min="1" max="365" placeholder="Enter days" class="w-20 px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-slate-800 text-gray-900 dark:text-gray-100" value="" />
                <button onclick="saveNotificationDays()" class="px-2 py-1 text-xs bg-primary text-white rounded hover:bg-primary/90">Save</button>
            </div>
            
            <!-- Notification Dropdown -->
            <div id="notificationDropdown" class="hidden absolute bottom-6 left-0 bg-white dark:bg-slate-900 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-20 min-w-48">
                <div class="py-2">
                    <div class="px-4 py-2 text-xs font-medium text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700">When the event starts</div>
                    <div class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" onclick="selectNotification('5 minutes before')">5 minutes before</div>
                    <div class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" onclick="selectNotification('10 minutes before')">10 minutes before</div>
                    <div class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" onclick="selectNotification('15 minutes before')">15 minutes before</div>
                    <div class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" onclick="selectNotification('30 minutes before')">30 minutes before</div>
                    <div class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" onclick="selectNotification('1 hour before')">1 hour before</div>
                    <div class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" onclick="selectNotification('1 day before')">1 day before</div>
                    <div class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" onclick="selectNotification('Days before expiry')">Days before expiry</div>
                    <div class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" onclick="selectNotification('Custom...')">Custom...</div>
                </div>
            </div>
        `;
        // Focus on the input field and add Enter key handler
        setTimeout(() => {
            const input = document.getElementById('notificationDaysInput');
            if (input) {
                input.focus();
                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        saveNotificationDays();
                    }
                });
            }
        }, 100);
    } else {
        addNotificationElement.innerHTML = `
            ${option}
            
            <!-- Notification Dropdown -->
            <div id="notificationDropdown" class="hidden absolute bottom-6 left-0 bg-white dark:bg-slate-900 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-20 min-w-48">
                <div class="py-2">
                    <div class="px-4 py-2 text-xs font-medium text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700">When the event starts</div>
                    <div class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" onclick="selectNotification('5 minutes before')">5 minutes before</div>
                    <div class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" onclick="selectNotification('10 minutes before')">10 minutes before</div>
                    <div class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" onclick="selectNotification('15 minutes before')">15 minutes before</div>
                    <div class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" onclick="selectNotification('30 minutes before')">30 minutes before</div>
                    <div class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" onclick="selectNotification('1 hour before')">1 hour before</div>
                    <div class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" onclick="selectNotification('1 day before')">1 day before</div>
                    <div class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" onclick="selectNotification('Days before expiry')">Days before expiry</div>
                    <div class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" onclick="selectNotification('Custom...')">Custom...</div>
                </div>
            </div>
        `;
    }
    
   // Close dropdown
   const notificationDropdown = document.getElementById('notificationDropdown');
   notificationDropdown.classList.add('hidden');
}

// Save notification days before expiry
function saveNotificationDays() {
    const input = document.getElementById('notificationDaysInput');
    const days = parseInt(input.value);
    
    if (!days || days < 1 || days > 365) {
        alert('Please enter a valid number of days (1-365)');
        return;
    }
    
    const addNotificationElement = document.querySelector('div[onclick="toggleNotificationDropdown()"]');
    addNotificationElement.innerHTML = `
        <span class="text-sm text-gray-700 dark:text-gray-300">${days} days before expiry</span>
        
        <!-- Notification Dropdown -->
        <div id="notificationDropdown" class="hidden absolute bottom-6 left-0 bg-white dark:bg-slate-900 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-20 min-w-48">
            <div class="py-2">
                <div class="px-4 py-2 text-xs font-medium text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700">When the event starts</div>
                <div class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" onclick="selectNotification('5 minutes before')">5 minutes before</div>
                <div class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" onclick="selectNotification('10 minutes before')">10 minutes before</div>
                <div class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" onclick="selectNotification('15 minutes before')">15 minutes before</div>
                <div class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" onclick="selectNotification('30 minutes before')">30 minutes before</div>
                <div class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" onclick="selectNotification('1 hour before')">1 hour before</div>
                <div class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" onclick="selectNotification('1 day before')">1 day before</div>
                <div class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" onclick="selectNotification('Days before expiry')">Days before expiry</div>
                <div class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer" onclick="selectNotification('Custom...')">Custom...</div>
            </div>
        </div>
    `;
    
    // Store the notification preference (you can save this to your event data)
    if (typeof window.currentEventData !== 'undefined') {
        window.currentEventData.notificationDaysBeforeExpiry = days;
    }
}

// Toggle reminder dropdown
function toggleReminderDropdown() {
    const reminderDropdown = document.getElementById('reminderDropdown');
    if (reminderDropdown) {
        reminderDropdown.classList.toggle('hidden');
    }
}

// Select reminder option
function selectReminder(option) {
    const reminderText = document.getElementById('reminderText');
    if (reminderText) {
        reminderText.textContent = option;
    }
    
    // Update visual selection in dropdown
    const reminderOptions = document.querySelectorAll('.reminder-option');
    reminderOptions.forEach(opt => {
        const check = opt.querySelector('.reminder-check');
        if (opt.getAttribute('data-value') === option) {
            opt.classList.add('bg-blue-50', 'dark:bg-blue-900/20');
            if (check) check.classList.remove('hidden');
        } else {
            opt.classList.remove('bg-blue-50', 'dark:bg-blue-900/20');
            if (check) check.classList.add('hidden');
        }
    });
    
    // Close the dropdown
    toggleReminderDropdown();
}

// Select color from picker
function selectColor(colorName, element) {
     const colorButton = document.querySelector('button[onclick="toggleColorPicker()"]');
     const colorPicker = document.getElementById('colorPicker');
     
     // Remove previous checkmark
     const previousCheckmark = colorPicker.querySelector('.checkmark');
     if (previousCheckmark) {
         previousCheckmark.remove();
     }
     
     // Add checkmark to selected color
     const checkmark = document.createElement('div');
     checkmark.className = 'checkmark absolute top-0 left-0 w-full h-full flex items-center justify-center';
     checkmark.innerHTML = '<span class="material-symbols-outlined text-white text-xs">check</span>';
     element.appendChild(checkmark);
     
     // Update button color
     const colorMap = {
         'red': 'bg-red-500',
         'orange': 'bg-orange-500',
         'green': 'bg-green-400',
         'blue': 'bg-blue-400',
         'purple': 'bg-purple-400',
         'gray': 'bg-gray-400',
         'pink': 'bg-pink-300',
         'yellow': 'bg-yellow-400',
         'darkgreen': 'bg-green-600',
         'darkblue': 'bg-blue-600',
         'darkpurple': 'bg-purple-600',
         'transparent': 'bg-transparent border-2 border-gray-300'
     };
     
     // Remove all color classes and add new one
     colorButton.className = colorButton.className.replace(/bg-\w+-\d+|border-\d+/, '');
     colorButton.className += ` ${colorMap[colorName]}`;
     
     // Close color picker
     colorPicker.classList.add('hidden');
 }
 </script>
 
<script>
const API_BASE = 'api/scheduler.php';
const AUTH_TOKEN = '<?php echo $token; ?>';
const ATTACHMENTS_API = 'api/scheduler-attachments.php';

/**
 * Upload a single attachment file for a schedule.
 * Returns true/false for success; errors are logged and surfaced via toast.
 */
async function uploadScheduleAttachment(scheduleId, file) {
    try {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('schedule_id', scheduleId);

        const response = await fetch(ATTACHMENTS_API, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            console.error('Attachment upload failed:', result);
            showToast('Attachment upload failed: ' + (result.error || 'Unknown error'), 'error');
            return false;
        }

        return true;
    } catch (error) {
        console.error('Attachment upload error:', error);
        showToast('Attachment upload failed: ' + error.message, 'error');
        return false;
    }
}

window.createSchedule = async function(scheduleData, attachmentFiles = []) {
    try {
        const response = await fetch(API_BASE + '?action=create', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + AUTH_TOKEN,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(scheduleData)
        });

        const result = await response.json();

        if (result.success) {
            const scheduleId = result.id;

            // Update reminder's eventId from temp client id to database id so the reminder still fires
            const tempEventId = arguments[2]; // optional third param from saveEvent
            if (scheduleId && tempEventId && scheduleData.reminder && scheduleData.reminder !== 'None') {
                const reminders = JSON.parse(localStorage.getItem('scheduleReminders') || '[]');
                const updated = reminders.map(r => (String(r.eventId) === String(tempEventId) ? { ...r, eventId: scheduleId } : r));
                localStorage.setItem('scheduleReminders', JSON.stringify(updated));
            }

            // If we have a schedule ID and attachments, upload them
            if (scheduleId && Array.isArray(attachmentFiles) && attachmentFiles.length > 0) {
                for (const file of attachmentFiles) {
                    // Client-side guard: enforce 10MB and allowed extensions before hitting API
                    const maxSize = 10 * 1024 * 1024;
                    if (file.size > maxSize) {
                        showToast(`"${file.name}" is larger than 10MB and was skipped.`, 'warning');
                        continue;
                    }

                    const ext = file.name.split('.').pop().toLowerCase();
                    const allowedExts = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
                    if (!allowedExts.includes(ext)) {
                        showToast(`"${file.name}" has an invalid type and was skipped.`, 'warning');
                        continue;
                    }

                    await uploadScheduleAttachment(scheduleId, file);
                }
            }

            if (typeof loadSchedules === 'function') loadSchedules();

            // Check for notifications after creating schedule (including schedule notifications)
            setTimeout(async () => {
                if (typeof checkNotifications === 'function') {
                    await checkNotifications();
                }
                // Reload notifications to display them immediately
                if (typeof loadNotifications === 'function') {
                    await loadNotifications();
                }
                // Update badge
                if (typeof updateNotificationBadge === 'function') {
                    await updateNotificationBadge();
                }
            }, 500);

            // Clear attachment input and preview after successful save
            const attachmentInput = document.getElementById('eventAttachmentsInput');
            const attachmentsList = document.getElementById('attachmentsList');
            if (attachmentInput) {
                attachmentInput.value = '';
            }
            if (attachmentsList) {
                attachmentsList.innerHTML = '';
            }
            currentEventAttachments = [];

            return true;
        } else {
            showToast('Error: ' + (result.error || 'Schedule creation failed'), 'error');
            return false;
        }
    } catch (error) {
        showToast('Error: ' + error.message, 'error');
        return false;
    }
};

window.loadSchedules = async function() {
    try {
        // Clear events array FIRST to prevent showing stale data
        console.log('🔄 Loading schedules from database...');
        events = [];
        localStorage.setItem('calendarEvents', JSON.stringify(events));
        // Clear the calendar display immediately
        renderEventsOnCalendar();
        renderMyEvents();
        
        const response = await fetch(API_BASE + '?action=list', {
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + AUTH_TOKEN
            }
        });

        const result = await response.json();

        if (result.success) {
            // Handle both empty array and undefined/null cases
            const schedules = result.schedules || [];
            console.log('✅ Loaded schedules from database:', schedules.length);
            
            if (schedules.length === 0) {
                // No schedules in database - ensure events array is empty
                console.log('ℹ️ No schedules found in database, clearing events');
                events = [];
                localStorage.setItem('calendarEvents', JSON.stringify(events));
                
                // Clean up all reminders since there are no events
                if (typeof cleanupRemindersForDeletedEvents === 'function') {
                    cleanupRemindersForDeletedEvents();
                }
                
                renderEventsOnCalendar();
                renderMyEvents();
                
                // Clear schedules container if it exists
                if (typeof renderSchedules === 'function') {
                    renderSchedules([]);
                }
                
                return [];
            }
            
            // Update the events array with database schedules (database is single source of truth)
            // Convert database schedules to calendar events format
            // IMPORTANT: Parse date manually to avoid timezone conversion issues
            const dbSchedules = schedules.map(schedule => {
                // Parse date string manually (format: YYYY-MM-DD) to avoid timezone issues
                // new Date("2025-11-15") interprets as UTC midnight, which can shift the date
                const dateStr = schedule.scheduled_date; // e.g., "2025-11-15"
                const dateParts = dateStr.split('-');
                const year = parseInt(dateParts[0]);
                const month = parseInt(dateParts[1]) - 1; // JavaScript months are 0-indexed
                const day = parseInt(dateParts[2]);
                
                // Create a date object using local timezone (not UTC)
                const scheduleDate = new Date(year, month, day);
                
                const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                
                const eventObj = {
                    id: schedule.id, // Use database ID
                    title: schedule.title,
                    description: schedule.description || '',
                    location: '', // Location is separate from description
                    date: `${dayNames[scheduleDate.getDay()]}, ${day} ${monthNames[month]} ${year}`,
                    dayNumber: day,
                    month: month,
                    year: year,
                    startTime: schedule.scheduled_time || '',
                    scheduled_time: schedule.scheduled_time || '', // Keep for compatibility
                    time: schedule.scheduled_time || '', // Keep for compatibility
                    color: 'bg-blue-500', // Default color
                    dbId: schedule.id // Store database ID for reference
                };
                
                console.log('✅ Converted schedule to event:', { 
                    id: schedule.id, 
                    title: schedule.title,
                    dateStr, 
                    year, 
                    month: month + 1, 
                    day,
                    eventMonth: month,
                    eventYear: year,
                    eventDayNumber: day,
                    eventDate: eventObj.date
                });
                
                return eventObj;
            });
            
            console.log('✅ Total events after conversion:', dbSchedules.length);
            console.log('✅ Events array:', dbSchedules);
            
            // Replace localStorage events with database schedules
            events = dbSchedules;
            localStorage.setItem('calendarEvents', JSON.stringify(events));
            
            console.log('✅ Events array updated, current calendar month/year:', currentCalendarDate.getMonth() + 1, currentCalendarDate.getFullYear());
            
            // Clean up reminders for deleted events
            if (typeof cleanupRemindersForDeletedEvents === 'function') {
                cleanupRemindersForDeletedEvents();
            }
            
            // Re-render calendar with database schedules
            renderEventsOnCalendar();
            renderMyEvents();
            
            // Also render in schedules container if it exists
            if (typeof renderSchedules === 'function') {
                renderSchedules(schedules);
            }
            
            // Check for today's events and show attendance confirmation modal
            setTimeout(() => {
                checkTodaysEvents();
            }, 500);
            
            console.log('✅ Calendar re-rendered with schedules');
            
            return schedules;
        } else {
            console.error('❌ Failed to load schedules:', result);
            // On error, keep events cleared to avoid showing stale data
            events = [];
            localStorage.setItem('calendarEvents', JSON.stringify(events));
            renderEventsOnCalendar();
            renderMyEvents();
        }
    } catch (error) {
        console.error('Load schedules error:', error);
        // On error, keep events cleared to avoid showing stale data
        events = [];
        localStorage.setItem('calendarEvents', JSON.stringify(events));
        renderEventsOnCalendar();
        renderMyEvents();
    }
};

window.deleteSchedule = async function(scheduleId) {
    if (!scheduleId) {
        showToast('Error: Schedule ID is missing', 'error');
        return;
    }
    console.log('Attempting to delete schedule with ID:', scheduleId);
    
    try {
        // Delete from database via API (accepts either ?action=delete&id=X or just ?id=X)
        const response = await fetch(API_BASE + '?id=' + encodeURIComponent(scheduleId), {
            method: 'DELETE',
            headers: {
                'Authorization': 'Bearer ' + AUTH_TOKEN,
                'Content-Type': 'application/json'
            }
        });

        console.log('Delete response status:', response.status);
        const responseText = await response.text();
        console.log('Delete response text:', responseText);
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            console.error('Failed to parse JSON response:', e);
            showToast('Error: Server returned invalid response. Check console for details.', 'error');
            return;
        }
        
        console.log('Delete result:', result);

        if (result.success) {
            // Close attendance modal if it's open for this deleted event
            const attendanceModal = document.getElementById('eventAttendanceModal');
            if (attendanceModal && attendanceModal.dataset.eventId === String(scheduleId)) {
                attendanceModal.classList.add('hidden');
                document.body.classList.remove('attendance-modal-open');
            }
            
            showDeleteSuccessModal();
            
            // Reload directly from database (single source of truth)
            // No localStorage manipulation - database is authoritative
            if (typeof loadSchedules === 'function') {
                await loadSchedules();
            } else {
                // Fallback: reload page
                window.location.reload();
            }
        } else {
            const errorMsg = result.error || result.message || 'Failed to delete schedule';
            console.error('Delete failed:', errorMsg);
            showToast('Error: ' + errorMsg, 'error');
        }
    } catch (error) {
        console.error('Error deleting schedule:', error);
        showToast('Error: ' + error.message, 'error');
    }
};

window.updateSchedule = async function(scheduleId, scheduleData) {
    try {
        const response = await fetch(API_BASE + '?action=update&id=' + scheduleId, {
            method: 'PUT',
            headers: {
                'Authorization': 'Bearer ' + AUTH_TOKEN,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(scheduleData)
        });

        const result = await response.json();

        if (result.success) {
            showToast('Schedule updated successfully', 'success');
            if (typeof loadSchedules === 'function') loadSchedules();
            return true;
        } else {
            showToast('Error: ' + (result.error || 'Update failed'), 'error');
            return false;
        }
    } catch (error) {
        showToast('Error: ' + error.message, 'error');
        return false;
    }
};

function renderSchedules(schedules) {
    const container = document.getElementById('schedulesContainer');
    if (!container) return;

    if (schedules.length === 0) {
        container.innerHTML = '<p class="text-center text-text-muted-light dark:text-text-muted-dark">No schedules found</p>';
        return;
    }

    container.innerHTML = schedules.map(schedule => `
        <div class="schedule-card p-4 border border-border-light dark:border-border-dark rounded-lg">
            <h3 class="font-semibold">${escapeHtml(schedule.title)}</h3>
            <p class="text-sm text-text-muted-light dark:text-text-muted-dark">${escapeHtml(schedule.description || '')}</p>
            <p class="text-xs text-text-muted-light dark:text-text-muted-dark mt-1">${schedule.scheduled_date} ${schedule.scheduled_time || ''}</p>
            <div class="mt-2 flex gap-2">
                <button onclick="editSchedule(${schedule.id})" class="text-sm text-primary hover:underline">Edit</button>
                <button onclick="deleteSchedule(${schedule.id})" class="text-sm text-red-500 hover:underline">Delete</button>
            </div>
        </div>
    `).join('');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Load schedules from database on page load (database is single source of truth)
document.addEventListener('DOMContentLoaded', async function() {
    // Load schedules from database first to populate calendar
    if (typeof loadSchedules === 'function') {
        await loadSchedules();
    }
    
    try {
        const profile = JSON.parse(localStorage.getItem('lilac_profile'));
        if (profile && profile.avatar) {
            const sidebarProfilePicture = document.querySelector('.sidebar-profile-picture');
            if (sidebarProfilePicture) {
                sidebarProfilePicture.style.backgroundImage = `url('${profile.avatar}')`;
            }

            const profileName = document.querySelector('.sidebar-profile-info p');
            if (profileName && profile.name) {
                profileName.textContent = profile.name;
            }
        }
    } catch (error) {
        console.log('No profile data found in localStorage');
    }

    // Initialize attachments dropzone behaviour
    try {
        const dropzone = document.getElementById('attachmentsDropzone');
        const fileInput = document.getElementById('eventAttachmentsInput');
        const listContainer = document.getElementById('attachmentsList');

        if (dropzone && fileInput) {
            const refreshAttachmentPreview = () => {
                currentEventAttachments = Array.from(fileInput.files || []);
                if (!listContainer) return;

                listContainer.innerHTML = '';
                if (currentEventAttachments.length === 0) return;

                currentEventAttachments.forEach(file => {
                    const item = document.createElement('div');
                    item.className = 'flex items-center text-[10px] text-slate-600 dark:text-slate-300';

                    const sizeMb = (file.size / (1024 * 1024)).toFixed(1);
                    item.innerHTML = `
                        <span class="material-symbols-outlined mr-1 text-xs">description</span>
                        <span class="truncate max-w-[180px]" title="${file.name}">${file.name}</span>
                        <span class="ml-1 text-[9px] text-slate-400">(${sizeMb}MB)</span>
                    `;
                    listContainer.appendChild(item);
                });
            };

            dropzone.addEventListener('click', () => fileInput.click());
            dropzone.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    fileInput.click();
                }
            });

            fileInput.addEventListener('change', () => {
                // Client-side validation for size/type before preview
                const dt = new DataTransfer();
                Array.from(fileInput.files || []).forEach(file => {
                    const maxSize = 10 * 1024 * 1024;
                    const ext = file.name.split('.').pop().toLowerCase();
                    const allowedExts = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

                    if (file.size > maxSize) {
                        showToast(`"${file.name}" is larger than 10MB and was skipped.`, 'warning');
                        return;
                    }
                    if (!allowedExts.includes(ext)) {
                        showToast(`"${file.name}" has an invalid type and was skipped.`, 'warning');
                        return;
                    }

                    dt.items.add(file);
                });

                fileInput.files = dt.files;
                refreshAttachmentPreview();
            });
        }
    } catch (err) {
        console.error('Error initializing attachments UI:', err);
    }
});
</script>

<!-- Notification System -->
<script>
    // Notification System - Reusable for all pages
    (function() {
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const notificationBadge = document.getElementById('notificationBadge');
        const notificationList = document.getElementById('notificationList');
        const noNotifications = document.getElementById('noNotifications');
        const markAllReadBtn = document.getElementById('markAllReadBtn');
        const viewAllNotifications = document.getElementById('viewAllNotifications');
        
        if (!notificationBtn || !notificationDropdown) return; // Exit if elements don't exist
        
        let notifications = [];
        
        if (notificationList) {
            notificationList.addEventListener('click', handleNotificationListClick);
            notificationList.addEventListener('keydown', handleNotificationListKeydown);
        }
        
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
        document.addEventListener('click', (e) => {
            if (!notificationBtn.contains(e.target) && !notificationDropdown.contains(e.target)) {
                notificationDropdown.classList.add('hidden');
            }
        });
        
        // Create all notifications modal if it doesn't exist
        function createAllNotificationsModal() {
            if (document.getElementById('allNotificationsModal')) {
                return; // Modal already exists
            }
            
            const modalHTML = `
                <div id="allNotificationsModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 dark:bg-black/70 backdrop-blur-sm hidden">
                    <div class="w-full max-w-4xl bg-card-light dark:bg-card-dark rounded-xl shadow-2xl m-4 flex flex-col max-h-[90vh] border border-border-light dark:border-border-dark">
                        <!-- Modal Header -->
                        <div class="p-6 border-b border-border-light dark:border-border-dark flex-shrink-0">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-xl font-semibold text-text-light dark:text-text-dark">All Notifications</h3>
                                    <p class="text-sm text-text-muted-light dark:text-text-muted-dark mt-1">Manage your MOU/MOA notifications</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <button id="markAllReadModalBtn" class="px-4 py-2 text-sm font-medium text-primary bg-primary/10 dark:bg-primary/20 rounded-lg hover:bg-primary/20 dark:hover:bg-primary/30">
                                        Mark All Read
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Filter Tabs -->
                        <div class="px-6 py-4 border-b border-border-light dark:border-border-dark flex-shrink-0">
                            <div class="flex space-x-1 bg-gray-100 dark:bg-gray-800 rounded-lg p-1">
                                <button class="notification-tab px-4 py-2 text-sm font-medium rounded-md transition-colors bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm" data-filter="all">
                                    All
                                </button>
                                <button class="notification-tab px-4 py-2 text-sm font-medium rounded-md transition-colors text-gray-600 dark:text-gray-400" data-filter="critical">
                                    Critical
                                </button>
                                <button class="notification-tab px-4 py-2 text-sm font-medium rounded-md transition-colors text-gray-600 dark:text-gray-400" data-filter="unread">
                                    Unread
                                </button>
                            </div>
                        </div>
                        
                        <!-- Notifications List -->
                        <div id="allNotificationsList" class="flex-1 overflow-y-auto p-6">
                            <div class="text-center py-12">
                                <span class="material-symbols-outlined text-6xl text-text-muted-light dark:text-text-muted-dark mb-4 block">notifications_off</span>
                                <p class="text-text-muted-light dark:text-text-muted-dark text-lg">Loading notifications...</p>
                            </div>
                        </div>
                        
                        <!-- Modal Footer -->
                        <div class="p-6 border-t border-border-light dark:border-border-dark flex-shrink-0">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-text-muted-light dark:text-text-muted-dark">
                                    <span id="notificationsCount">0</span> notifications
                                </div>
                                <div class="flex items-center gap-3">
                                    <button id="clearOldNotifications" class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                                        Clear All
                                    </button>
                                    <button id="closeAllNotificationsModalBtn2" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-background-dark/50 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-800">
                                        Close
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            setupAllNotificationsModalEvents();
        }
        
        // Setup event listeners for the all notifications modal
        function setupAllNotificationsModalEvents() {
            const modal = document.getElementById('allNotificationsModal');
            if (!modal) return;
            
            const closeBtn2 = document.getElementById('closeAllNotificationsModalBtn2');
            const markAllReadBtn = document.getElementById('markAllReadModalBtn');
            const clearOldBtn = document.getElementById('clearOldNotifications');
            const tabs = document.querySelectorAll('.notification-tab');
            
            // Close modal handler
            if (closeBtn2) {
                closeBtn2.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeAllNotificationsModal();
                });
            }
            
            // Close modal when clicking outside
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeAllNotificationsModal();
                }
            });
            
            // Close modal with Escape key
            const escapeHandler = function(e) {
                if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                    closeAllNotificationsModal();
                }
            };
            document.addEventListener('keydown', escapeHandler);
            
            // Mark all as read
            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', async function(e) {
                    e.preventDefault();
                    e.stopPropagation();
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
                            await loadAllNotificationsIntoModal();
                            if (typeof updateNotificationBadge === 'function') {
                                await updateNotificationBadge();
                            }
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
                    
                    if (isClearingAll) return;
                    isClearingAll = true;
                    
                    try {
                        const response = await fetch('api/notifications.php', {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'mark_all_read' })
                        });
                        if (response.ok) {
                            await loadAllNotificationsIntoModal();
                            if (typeof updateNotificationBadge === 'function') {
                                await updateNotificationBadge();
                            }
                        }
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
                        tabs.forEach(t => {
                            t.classList.remove('bg-white', 'dark:bg-gray-700', 'text-gray-900', 'dark:text-white', 'shadow-sm');
                            t.classList.add('text-gray-600', 'dark:text-gray-400');
                        });
                        this.classList.remove('text-gray-600', 'dark:text-gray-400');
                        this.classList.add('bg-white', 'dark:bg-gray-700', 'text-gray-900', 'dark:text-white', 'shadow-sm');
                        const filter = this.dataset.filter;
                        filterAllNotifications(filter);
                    });
                });
            }
        }
        
        // Store notifications for filtering
        let allNotificationsData = [];
        
        // Filter notifications
        function filterAllNotifications(filter) {
            const modalList = document.getElementById('allNotificationsList');
            if (!modalList) return;
            let filteredNotifications = allNotificationsData;
            if (filter === 'unread') {
                filteredNotifications = allNotificationsData.filter(n => !n.is_read);
            } else if (filter === 'critical') {
                filteredNotifications = allNotificationsData.filter(n => 
                    n.type === 'mou_expired' || 
                    (n.type === 'mou_expiring_soon' && n.mou_days_until_expiry !== undefined && n.mou_days_until_expiry <= 3)
                );
            }
            renderNotificationsInModal(filteredNotifications);
        }
        
        // Render notifications in modal
        function renderNotificationsInModal(notifications) {
            const modalList = document.getElementById('allNotificationsList');
            const countElement = document.getElementById('notificationsCount');
            if (!modalList) return;
            if (countElement) countElement.textContent = notifications.length;
            if (notifications.length === 0) {
                modalList.innerHTML = `<div class="text-center py-12"><span class="material-symbols-outlined text-6xl text-gray-400 dark:text-gray-600 mb-4 block">notifications_off</span><p class="text-gray-500 dark:text-gray-400 text-lg">No notifications</p></div>`;
                return;
            }
            // Use existing rendering logic from loadAllNotificationsIntoModal
        }
        
        // Show all notifications modal
        function showAllNotificationsModal() {
            createAllNotificationsModal();
            const modal = document.getElementById('allNotificationsModal');
            if (modal) {
                modal.classList.remove('hidden');
                loadAllNotificationsIntoModal();
            }
        }
        
        // Close all notifications modal
        function closeAllNotificationsModal() {
            const modal = document.getElementById('allNotificationsModal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }
        
        // Load all notifications into the modal
        async function loadAllNotificationsIntoModal() {
            const modalList = document.getElementById('allNotificationsList');
            const countElement = document.getElementById('notificationsCount');
            
            if (!modalList) return;
            
            try {
                const response = await fetch('api/notifications.php');
                const data = await response.json();
                
                if (data.notifications && Array.isArray(data.notifications)) {
                    let allNotifications = data.notifications;
                    
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
                                <span class="material-symbols-outlined text-6xl text-text-muted-light dark:text-text-muted-dark mb-4 block">notifications_off</span>
                                <p class="text-text-muted-light dark:text-text-muted-dark text-lg">No notifications</p>
                            </div>
                        `;
                    } else {
                        modalList.innerHTML = allNotifications.map(notif => {
                            const timeAgo = getTimeAgo(notif.created_at);
                            const icon = getNotificationIcon(notif.type);
                            const bgColor = getNotificationBgColor(notif.type);
                            const targetUrl = getNotificationUrl(notif);
                            const urlAttribute = targetUrl ? ` data-url="${encodeURIComponent(targetUrl)}"` : '';
                            const isMouNotification = notif.related_type === 'mou_moa';
                            const isConfirmed = notif.is_confirmed || false;
                            
                            // Add Renew/Renewed buttons for MOU notifications that aren't confirmed
                            let actionButtons = '';
                            if (isMouNotification && !isConfirmed) {
                                actionButtons = `
                                    <div class="mt-3 flex gap-2">
                                        <button onclick="event.stopPropagation(); if(typeof confirmMouRenewal === 'function') confirmMouRenewal(${notif.id}, 'renewed', ${notif.related_id})" 
                                                class="px-3 py-1.5 text-xs font-medium bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors flex items-center gap-1">
                                            <span class="material-symbols-outlined text-sm">check_circle</span>
                                            Renewed
                                        </button>
                                        <button onclick="event.stopPropagation(); if(typeof confirmMouRenewal === 'function') confirmMouRenewal(${notif.id}, 'not_renewed', ${notif.related_id})" 
                                                class="px-3 py-1.5 text-xs font-medium bg-red-500 hover:bg-red-600 text-white rounded-lg transition-colors flex items-center gap-1">
                                            <span class="material-symbols-outlined text-sm">cancel</span>
                                            Not Renewed
                                        </button>
                                    </div>
                                `;
                            } else if (isMouNotification && isConfirmed) {
                                const statusText = notif.mou_renewal_status === 'renewed' ? 'Renewed' : 'Not Renewed';
                                const statusColor = notif.mou_renewal_status === 'renewed' ? 'text-green-500' : 'text-red-500';
                                actionButtons = `
                                    <div class="mt-2">
                                        <p class="text-xs ${statusColor} font-medium flex items-center gap-1">
                                            <span class="material-symbols-outlined text-sm">${notif.mou_renewal_status === 'renewed' ? 'check_circle' : 'cancel'}</span>
                                            Status: ${statusText}
                                        </p>
                                    </div>
                                `;
                            }
                            
                            const actionHint = targetUrl && !isMouNotification ? '<p class="text-xs text-primary mt-2 font-semibold flex items-center gap-1">Open related record<span class="material-symbols-outlined text-sm">arrow_outward</span></p>' : '';
                            
                            return `
                                <div class="p-4 border-b border-border-light dark:border-border-dark hover:bg-background-light dark:hover:bg-background-dark cursor-pointer transition-colors ${notif.is_read ? 'opacity-60' : ''}" 
                                     data-notification-id="${notif.id}"${urlAttribute}>
                                    <div class="flex items-start gap-3">
                                        <div class="flex-shrink-0 w-10 h-10 rounded-full ${bgColor} flex items-center justify-center">
                                            <span class="material-symbols-outlined text-white text-lg">${icon}</span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-text-light dark:text-text-dark">${escapeHtml(notif.title)}</p>
                                            <p class="text-sm text-text-muted-light dark:text-text-muted-dark mt-1">${escapeHtml(notif.message)}</p>
                                            <p class="text-xs text-text-muted-light dark:text-text-muted-dark mt-1">${timeAgo}</p>
                                            ${actionHint}
                                            ${actionButtons}
                                        </div>
                                        ${!notif.is_read ? '<div class="flex-shrink-0 w-2 h-2 bg-primary rounded-full mt-2"></div>' : ''}
                                    </div>
                                </div>
                            `;
                        }).join('');
                        
                        // Add click handlers for notifications in modal
                        modalList.querySelectorAll('[data-notification-id]').forEach(item => {
                            item.addEventListener('click', async function(e) {
                                // Don't trigger if clicking on buttons
                                if (e.target.closest('button')) {
                                    return;
                                }
                                
                                const notificationId = Number(item.dataset.notificationId);
                                if (notificationId) {
                                    await markNotificationAsRead(notificationId);
                                    const targetUrl = decodeUrlAttribute(item.dataset.url);
                                    if (targetUrl) {
                                        closeAllNotificationsModal();
                                        window.location.href = targetUrl;
                                    }
                                }
                            });
                        });
                    }
                }
            } catch (error) {
                console.error('Error loading notifications into modal:', error);
                modalList.innerHTML = `
                    <div class="text-center py-12">
                        <span class="material-symbols-outlined text-6xl text-red-500 mb-4 block">error</span>
                        <p class="text-text-light dark:text-text-dark text-lg">Error loading notifications</p>
                    </div>
                `;
            }
        }
        
        // View all notifications - open modal
        if (viewAllNotifications) {
            viewAllNotifications.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                // Close dropdown first
                if (notificationDropdown) {
                    notificationDropdown.classList.add('hidden');
                }
                showAllNotificationsModal();
            }, true); // Use capture phase to ensure it runs first
        }
        
        // Check for new notifications and create them
        async function checkNotifications() {
            try {
                console.log('Checking for notifications...');
                const response = await fetch('api/notifications.php?action=check');
                const data = await response.json();
                if (data.success) {
                    console.log('Notifications checked:', data);
                    if (data.mou_notifications_created > 0) {
                        console.log(`✓ Created ${data.mou_notifications_created} MOU notification(s)`);
                    }
                    if (data.schedule_notifications_created > 0) {
                        console.log(`✓ Created ${data.schedule_notifications_created} schedule notification(s)`);
                    } else {
                        console.log('No new schedule notifications created (may already exist or no schedules found)');
                    }
                } else {
                    console.warn('Notification check failed:', data);
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
                    const previousNotifications = notifications || [];

                    // Use the notifications exactly as returned by the API so the
                    // count and list match what other pages (awards, events, documents)
                    // display, without any extra de-duplication on the client side.
                    notifications = data.notifications.slice();
                    
                    // Sort by created_at (most recent first)
                    notifications.sort((a, b) => {
                        const dateA = new Date(a.created_at);
                        const dateB = new Date(b.created_at);
                        return dateB - dateA;
                    });
                    
                    updateNotificationDisplay();
                    updateNotificationBadge();
                    
                    // Process notifications for bars and sounds
                    if (window.processNotificationsForBars) {
                        window.processNotificationsForBars(notifications, previousNotifications);
                    }
                    
                    // Play sound for new MOU/MOA notifications (skip on very first page load)
                    const newNotifications = previousNotifications.length > 0 
                        ? notifications.filter(n => !previousNotifications.some(p => p.id === n.id))
                        : notifications;
                    
                    if (hasPlayedInitialNotificationSound) {
                        if (window.NotificationSound && window.NotificationSound.checkAndPlay) {
                            window.NotificationSound.checkAndPlay(newNotifications);
                        } else if (window.checkAndPlayMouNotificationSound) {
                            window.checkAndPlayMouNotificationSound(newNotifications);
                        }
                    }
                    
                    // Mark that we've handled the first load
                    hasPlayedInitialNotificationSound = true;
                }
            } catch (error) {
                console.error('Error loading notifications:', error);
            }
        }
        
        // Get unread count - prefer using the notifications already loaded
        // on this page so the badge matches exactly what is rendered.
        async function updateNotificationBadge() {
            try {
                const badge = document.getElementById('notificationBadge');
                const enabled = window.areNotificationsEnabled ? await window.areNotificationsEnabled() : true;
                
                if (!enabled) {
                    if (badge) {
                        badge.classList.add('hidden');
                    }
                    return;
                }

                let count = 0;

                // If notifications are loaded, derive the unread count locally so
                // duplicate collapsing in the UI is reflected in the badge.
                if (Array.isArray(notifications) && notifications.length > 0) {
                    count = notifications.filter(n => !n.is_read).length;
                } else {
                    // Fallback: ask the API for the unread count when we haven't
                    // populated the notifications array yet on this page.
                    const response = await fetch('api/notifications.php?action=count');
                    if (!response.ok) {
                        console.error('Failed to get notification count:', response.status, response.statusText);
                        return;
                    }
                    const data = await response.json();
                    count = data.count || 0;
                }
                
                if (badge) {
                    if (count > 0) {
                        badge.textContent = count > 99 ? '99+' : count;
                        badge.classList.remove('hidden');
                    } else {
                        badge.classList.add('hidden');
                    }
                } else {
                    console.warn('Notification badge element not found');
                }
            } catch (error) {
                console.error('Error updating notification badge:', error);
            }
        }
        
        // Update notification display
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
            
            notificationList.innerHTML = notifications.map(notif => {
                const timeAgo = getTimeAgo(notif.created_at);
                const icon = getNotificationIcon(notif.type);
                const bgColor = getNotificationBgColor(notif.type);
                const targetUrl = getNotificationUrl(notif);
                const urlAttribute = targetUrl ? ` data-url="${encodeURIComponent(targetUrl)}"` : '';
                const isMouNotification = notif.related_type === 'mou_moa';
                const isConfirmed = notif.is_confirmed || false;
                
                // Show confirmation buttons for MOU notifications that haven't been confirmed
                let confirmationButtons = '';
                if (isMouNotification && !isConfirmed) {
                    confirmationButtons = `
                        <div class="mt-3 flex gap-2">
                            <button onclick="event.stopPropagation(); if(typeof confirmMouRenewal === 'function') confirmMouRenewal(${notif.id}, 'renewed', ${notif.related_id})" 
                                    class="px-3 py-1.5 text-xs font-medium bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">check_circle</span>
                                Renewed
                            </button>
                            <button onclick="event.stopPropagation(); if(typeof confirmMouRenewal === 'function') confirmMouRenewal(${notif.id}, 'not_renewed', ${notif.related_id})" 
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
                
                return `
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/60 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-background-dark ${notif.is_read ? 'opacity-60' : ''}" 
                         role="button"
                         tabindex="0"
                         data-id="${notif.id}"
                         data-notification-id="${notif.id}"${urlAttribute}>
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
        
        // Mark all as read
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', async () => {
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
                        updateNotificationDisplay();
                        updateNotificationBadge();
                    }
                } catch (error) {
                    console.error('Error marking all as read:', error);
                }
            });
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
        
        async function refreshNotificationIndicators() {
            try {
                await checkNotifications();
                await loadNotifications(); // Load notifications after checking
                await updateNotificationBadge();
            } catch (error) {
                console.error('Error refreshing notification indicators:', error);
            }
        }
        
        // Initialize: Check for notifications and load them
        document.addEventListener('DOMContentLoaded', () => {
            // Update badge immediately on page load
            updateNotificationBadge();
            
            refreshNotificationIndicators();
            
            // Refresh notifications every 5 minutes
            setInterval(() => {
                refreshNotificationIndicators();
            }, 5 * 60 * 1000);
            
            // Update badge every 30 seconds for real-time updates
            setInterval(() => {
                updateNotificationBadge();
            }, 30000);
        });
    })();

// Schedule Reminder Notification System
function scheduleReminderNotification(event) {
    if (!event || !event.reminder || event.reminder === 'None') {
        return;
    }
    
    // Parse the event date and time
    const dateStr = event.date || '';
    const timeStr = event.startTime || event.time || '';
    
    if (!dateStr || !timeStr) {
        console.warn('Cannot schedule reminder: missing date or time', event);
        return;
    }
    
    // Parse date (format: "Wednesday, 1 January 2026" or "Wednesday, 1 January")
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                       'July', 'August', 'September', 'October', 'November', 'December'];
    
    let day, month, year;
    const dayMatch = dateStr.match(/\b(\d{1,2})\b/);
    if (dayMatch) day = parseInt(dayMatch[1]);
    
    const monthIndex = monthNames.findIndex(m => dateStr.includes(m));
    if (monthIndex !== -1) month = monthIndex;
    
    const yearMatch = dateStr.match(/\b(19|20)\d{2}\b/);
    if (yearMatch) {
        year = parseInt(yearMatch[0]);
    } else {
        year = new Date().getFullYear();
    }
    
    if (!day || month === undefined) {
        console.warn('Cannot parse event date:', dateStr);
        return;
    }
    
    // Parse time (format: "4:30pm" or "16:30")
    let hour, minute;
    const timeMatch = timeStr.match(/(\d{1,2}):(\d{2})(am|pm)?/i);
    if (timeMatch) {
        hour = parseInt(timeMatch[1]);
        minute = parseInt(timeMatch[2]);
        const meridiem = timeMatch[3] ? timeMatch[3].toLowerCase() : null;
        
        if (meridiem === 'pm' && hour !== 12) hour += 12;
        if (meridiem === 'am' && hour === 12) hour = 0;
    } else {
        console.warn('Cannot parse event time:', timeStr);
        return;
    }
    
    // Create event datetime
    const eventDateTime = new Date(year, month, day, hour, minute);
    
    // Calculate reminder time based on reminder setting
    let reminderTime = new Date(eventDateTime);
    const reminderText = event.reminder.toLowerCase();
    
    if (reminderText.includes('at time of event') || reminderText.includes('at time')) {
        // No adjustment needed
    } else if (reminderText.includes('5 minutes')) {
        reminderTime.setMinutes(reminderTime.getMinutes() - 5);
    } else if (reminderText.includes('10 minutes')) {
        reminderTime.setMinutes(reminderTime.getMinutes() - 10);
    } else if (reminderText.includes('15 minutes')) {
        reminderTime.setMinutes(reminderTime.getMinutes() - 15);
    } else if (reminderText.includes('30 minutes')) {
        reminderTime.setMinutes(reminderTime.getMinutes() - 30);
    } else if (reminderText.includes('1 hour')) {
        reminderTime.setHours(reminderTime.getHours() - 1);
    } else if (reminderText.includes('2 hours')) {
        reminderTime.setHours(reminderTime.getHours() - 2);
    } else if (reminderText.includes('1 day')) {
        reminderTime.setDate(reminderTime.getDate() - 1);
    } else if (reminderText.includes('2 days')) {
        reminderTime.setDate(reminderTime.getDate() - 2);
    } else if (reminderText.includes('1 week')) {
        reminderTime.setDate(reminderTime.getDate() - 7);
    }
    
    // Only schedule if reminder time is in the future
    if (reminderTime <= new Date()) {
        console.log('Reminder time has already passed, not scheduling:', reminderTime);
        return;
    }
    
    // Save reminder to localStorage
    const reminders = JSON.parse(localStorage.getItem('scheduleReminders') || '[]');
    const reminderId = `reminder-${event.id}-${Date.now()}`;
    
    reminders.push({
        id: reminderId,
        eventId: event.id,
        eventTitle: event.title,
        eventDate: dateStr,
        eventTime: timeStr,
        reminderTime: reminderTime.toISOString(),
        reminderText: event.reminder,
        shown: false,
        confirmed: false, // Track if user has confirmed attendance
        lastShown: null // Track when reminder was last shown
    });
    
    localStorage.setItem('scheduleReminders', JSON.stringify(reminders));
    console.log('✅ Reminder scheduled for:', reminderTime, 'Event:', event.title);
}

// Clean up reminders for events that no longer exist
function cleanupRemindersForDeletedEvents() {
    const reminders = JSON.parse(localStorage.getItem('scheduleReminders') || '[]');
    const eventIds = new Set(events.map(e => String(e.id)));
    
    // Filter out reminders for events that no longer exist
    const validReminders = reminders.filter(reminder => {
        const eventExists = eventIds.has(String(reminder.eventId));
        if (!eventExists) {
            console.log('🧹 Cleaning up reminder for deleted event:', reminder.eventId, reminder.eventTitle);
        }
        return eventExists;
    });
    
    if (validReminders.length !== reminders.length) {
        localStorage.setItem('scheduleReminders', JSON.stringify(validReminders));
        console.log(`🧹 Cleaned up ${reminders.length - validReminders.length} reminders for deleted events`);
    }
}

// Check reminders periodically
function checkScheduleReminders() {
    // First, clean up reminders for deleted events
    cleanupRemindersForDeletedEvents();
    
    const reminders = JSON.parse(localStorage.getItem('scheduleReminders') || '[]');
    const now = new Date();
    const eventIds = new Set(events.map(e => String(e.id)));
    
    reminders.forEach(reminder => {
        // Skip if already confirmed
        if (reminder.confirmed) return;
        
        // Skip if event no longer exists
        if (!eventIds.has(String(reminder.eventId))) {
            console.log('⏭️ Skipping reminder for deleted event:', reminder.eventId);
            return;
        }
        
        const reminderTime = new Date(reminder.reminderTime);
        const timeDiff = reminderTime.getTime() - now.getTime();
        
        // Show reminder if it's time (within 1 minute before or after) OR if it's past reminder time and not confirmed
        const shouldShow = Math.abs(timeDiff) <= 60000 || (timeDiff <= 0 && !reminder.confirmed);
        
        if (shouldShow) {
            // Check if we should show again (every 2 minutes if not confirmed)
            const lastShown = reminder.lastShown ? new Date(reminder.lastShown) : null;
            const twoMinutesAgo = new Date(now.getTime() - 2 * 60 * 1000);
            
            if (!lastShown || lastShown < twoMinutesAgo) {
                showScheduleReminder(reminder);
                
                // Update last shown time
                const updatedReminders = JSON.parse(localStorage.getItem('scheduleReminders') || '[]');
                const index = updatedReminders.findIndex(r => r.id === reminder.id);
                if (index !== -1) {
                    updatedReminders[index].lastShown = now.toISOString();
                    localStorage.setItem('scheduleReminders', JSON.stringify(updatedReminders));
                }
            }
        }
    });
    
    // Clean up old confirmed reminders (older than 1 day)
    const oneDayAgo = new Date(now.getTime() - 24 * 60 * 60 * 1000);
    const activeReminders = reminders.filter(r => {
        // Remove reminders for deleted events
        if (!eventIds.has(String(r.eventId))) {
            return false;
        }
        if (r.confirmed) {
            const reminderTime = new Date(r.reminderTime);
            return reminderTime > oneDayAgo;
        }
        return true; // Keep unconfirmed reminders for existing events
    });
    localStorage.setItem('scheduleReminders', JSON.stringify(activeReminders));
}

// Show reminder notification and confirmation modal
function showScheduleReminder(reminder) {
    // Verify event still exists before showing reminder
    const eventExists = events.some(e => String(e.id) === String(reminder.eventId));
    if (!eventExists) {
        console.log('⏭️ Skipping reminder - event no longer exists:', reminder.eventId);
        // Remove the reminder from localStorage
        const reminders = JSON.parse(localStorage.getItem('scheduleReminders') || '[]');
        const filteredReminders = reminders.filter(r => r.id !== reminder.id);
        localStorage.setItem('scheduleReminders', JSON.stringify(filteredReminders));
        return;
    }
    
    // Request browser notification permission
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
    
    const message = `${reminder.eventTitle}\n${reminder.eventDate} at ${reminder.eventTime}`;
    
    // Show browser notification if permitted
    if ('Notification' in window && Notification.permission === 'granted') {
        const notification = new Notification('Schedule Reminder', {
            body: message + '\n\nPlease confirm your attendance.',
            icon: '/favicon.ico',
            tag: `schedule-${reminder.eventId}`,
            requireInteraction: true // Keep notification until user interacts
        });
        
        // Don't auto-close - let user interact with it
        notification.onclick = () => {
            window.focus();
            showAttendanceConfirmationModal(reminder);
        };
    }
    
    // Show toast notification
    showToast(`Reminder: ${reminder.eventTitle} - ${reminder.eventDate} at ${reminder.eventTime}`, 'info', 8000);
    
    // Play reminder sound using shared notification sound system (respects user sound settings)
    if (window.NotificationSound && typeof window.NotificationSound.play === 'function') {
        try {
            window.NotificationSound.play();
        } catch (e) {
            console.warn('Failed to play schedule reminder sound:', e);
        }
    }
    
    // Show attendance confirmation modal
    showAttendanceConfirmationModal(reminder);
}

// Show attendance confirmation modal
function showAttendanceConfirmationModal(reminder) {
    // Close create modal, date picker, and time picker so only the notification is visible (no calendar/date elements showing through)
    if (typeof closeTimePickerModal === 'function') closeTimePickerModal();
    if (typeof closeDatePickerModal === 'function') closeDatePickerModal();
    if (typeof closeCreateModal === 'function') closeCreateModal();
    
    const modal = document.getElementById('eventAttendanceModal');
    if (!modal) return;
    
    // Verify event still exists before showing modal
    const eventExists = events.some(e => String(e.id) === String(reminder.eventId));
    if (!eventExists) {
        console.log('⏭️ Skipping attendance modal - event no longer exists:', reminder.eventId);
        // Remove the reminder from localStorage
        const reminders = JSON.parse(localStorage.getItem('scheduleReminders') || '[]');
        const filteredReminders = reminders.filter(r => r.id !== reminder.id);
        localStorage.setItem('scheduleReminders', JSON.stringify(filteredReminders));
        // Close modal if it's open for this event
        if (modal.dataset.eventId === String(reminder.eventId)) {
            modal.classList.add('hidden');
            document.body.classList.remove('attendance-modal-open');
        }
        return;
    }
    
    // Don't show if modal is already open (unless it's for a different event)
    const currentReminderId = modal.dataset.reminderId;
    if (!modal.classList.contains('hidden') && currentReminderId === reminder.id) {
        return; // Already showing this reminder
    }
    
    // Rebuild modal body so it shows ONLY: event icon, Upcoming Event, Please confirm your attendance, date/time, question, No and Yes.
    const card = modal.firstElementChild;
    if (card) {
        const dateTimeStr = `${reminder.eventDate || ''} at ${reminder.eventTime || ''}`.trim() || 'Date and time';
        card.innerHTML = `
            <div class="p-6 overflow-hidden" id="attendanceModalContent">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/20 rounded-full flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-blue-600 dark:text-blue-400 text-xl">event</span>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-text-light dark:text-text-dark">Upcoming Event</h3>
                        <p class="text-sm text-text-muted-light dark:text-text-muted-dark mt-1">Please confirm your attendance</p>
                    </div>
                </div>
                <div class="mb-6">
                    <p class="text-sm text-text-muted-light dark:text-text-muted-dark" id="attendanceEventDetails">${escapeHtml(dateTimeStr)}</p>
                </div>
                <p class="text-text-light dark:text-text-dark mb-6">Are you going to attend this event?</p>
                <div id="attendanceModalButtons" class="flex gap-3 justify-end flex-nowrap">
                    <button type="button" id="attendanceNoBtn" class="px-6 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-800 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors shrink-0">No</button>
                    <button type="button" id="attendanceYesBtn" class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors shrink-0">Yes</button>
                </div>
            </div>
        `;
        // Re-attach button handlers (they were lost when we replaced innerHTML)
        if (typeof setupAttendanceModalButtonHandlers === 'function') setupAttendanceModalButtonHandlers();
    } else {
        const detailsEl = document.getElementById('attendanceEventDetails');
        if (detailsEl) detailsEl.textContent = `${reminder.eventDate || ''} at ${reminder.eventTime || ''}`.trim() || 'Date and time';
    }
    
    // Store current reminder ID and event ID for button handlers
    modal.dataset.reminderId = reminder.id;
    modal.dataset.eventId = reminder.eventId;
    
    // Force time picker, date picker, and create modal fully off-screen so "Yest" / "24" can't show on top
    document.body.classList.add('attendance-modal-open');
    if (typeof closeTimePickerModal === 'function') closeTimePickerModal();
    if (typeof closeDatePickerModal === 'function') closeDatePickerModal();
    function hideOtherOverlays() {
        [document.getElementById('timePickerModal'), document.getElementById('datePickerModal'), document.getElementById('createModal')].forEach(function(el) {
            if (!el) return;
            el.classList.add('hidden');
            el.style.setProperty('display', 'none', 'important');
            el.style.setProperty('visibility', 'hidden', 'important');
            el.style.setProperty('position', 'fixed', 'important');
            el.style.setProperty('left', '-99999px', 'important');
            el.style.setProperty('top', '0', 'important');
            el.style.setProperty('z-index', '-1', 'important');
            el.style.setProperty('pointer-events', 'none', 'important');
        });
    }
    hideOtherOverlays();
    
    // Show modal (ensure it's on top)
    modal.style.setProperty('z-index', '999999', 'important');
    modal.classList.remove('hidden');
    
    setTimeout(hideOtherOverlays, 0);
    
    // Remove any injected nodes immediately via MutationObserver (only allow our event content + No/Yes)
    function removeUnwantedAttendanceNodes() {
        const row = document.getElementById('attendanceModalButtons');
        if (row) {
            for (let i = row.children.length - 1; i >= 0; i--) {
                const el = row.children[i];
                if (!el.id || (el.id !== 'attendanceNoBtn' && el.id !== 'attendanceYesBtn')) el.remove();
            }
        }
        const p6 = modal.querySelector('.p-6, #attendanceModalContent');
        if (p6) {
            p6.querySelectorAll('button').forEach(function(btn) {
                if (btn.id !== 'attendanceNoBtn' && btn.id !== 'attendanceYesBtn') btn.remove();
            });
            p6.querySelectorAll('span, div').forEach(function(el) {
                const text = (el.textContent || '').trim();
                if (el.children.length === 0 && /^\d{1,2}$/.test(text) && el.className && /\brounded-full\b/.test(el.className)) el.remove();
            });
        }
    }
    removeUnwantedAttendanceNodes();
    if (modal._attendanceCleanupInterval) clearInterval(modal._attendanceCleanupInterval);
    modal._attendanceCleanupInterval = setInterval(function() {
        if (!modal || modal.classList.contains('hidden')) {
            if (modal) {
                if (modal._attendanceCleanupInterval) clearInterval(modal._attendanceCleanupInterval);
                modal._attendanceCleanupInterval = null;
                if (modal._attendanceObserver) { modal._attendanceObserver.disconnect(); modal._attendanceObserver = null; }
                modal.style.removeProperty('z-index');
                [document.getElementById('datePickerModal'), document.getElementById('createModal')].forEach(function(el) {
                    if (el) { el.style.removeProperty('left'); el.style.removeProperty('top'); el.style.removeProperty('z-index'); el.style.removeProperty('position'); el.style.removeProperty('visibility'); el.style.removeProperty('display'); el.style.removeProperty('pointer-events'); }
                });
            }
            return;
        }
        hideOtherOverlays();
        removeUnwantedAttendanceNodes();
    }, 80);
    // MutationObserver: remove injected nodes as soon as they appear
    if (modal._attendanceObserver) modal._attendanceObserver.disconnect();
    modal._attendanceObserver = new MutationObserver(function() { removeUnwantedAttendanceNodes(); });
    modal._attendanceObserver.observe(card || modal, { childList: true, subtree: true });
    
    // Focus the modal for accessibility
    const yesBtn = document.getElementById('attendanceYesBtn');
    if (yesBtn) yesBtn.focus();
}

// Show not attending confirmation modal (only: emoji, Got It, message, Close — no Yest/24)
function showNotAttendingModal() {
    const modal = document.getElementById('notAttendingModal');
    if (!modal) return;
    
    // Rebuild modal body so it shows only intended content; push other overlays off-screen
    const card = modal.firstElementChild;
    if (card) {
        card.innerHTML = `
            <div class="p-6 overflow-hidden">
                <div class="flex flex-col items-center text-center mb-6">
                    <div class="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mb-4">
                        <span class="text-4xl">😊</span>
                    </div>
                    <h3 class="text-xl font-bold text-text-light dark:text-text-dark mb-2">Got It</h3>
                    <p class="text-sm text-text-muted-light dark:text-text-muted-dark">
                        Noted. Reminders for this event will stop.
                    </p>
                </div>
                <div class="flex justify-end">
                    <button type="button" id="notAttendingCloseBtn" class="px-6 py-2 text-sm font-medium text-white bg-gray-600 dark:bg-gray-700 rounded-lg hover:bg-gray-700 dark:hover:bg-gray-600 transition-colors">Close</button>
                </div>
            </div>
        `;
        const closeBtn = document.getElementById('notAttendingCloseBtn');
        if (closeBtn) closeBtn.addEventListener('click', function() {
            modal.classList.add('hidden');
            if (modal._notAttendingCleanup) { clearInterval(modal._notAttendingCleanup); modal._notAttendingCleanup = null; }
            modal.style.removeProperty('z-index');
        });
    }
    
    // Keep date picker / create modal off-screen so "Yest" and "24" don't show on top
    [document.getElementById('timePickerModal'), document.getElementById('datePickerModal'), document.getElementById('createModal')].forEach(function(el) {
        if (!el) return;
        el.classList.add('hidden');
        el.style.setProperty('display', 'none', 'important');
        el.style.setProperty('visibility', 'hidden', 'important');
        el.style.setProperty('position', 'fixed', 'important');
        el.style.setProperty('left', '-99999px', 'important');
        el.style.setProperty('z-index', '-1', 'important');
    });
    
    modal.style.setProperty('z-index', '999999', 'important');
    modal.classList.remove('hidden');
    
    const closeBtn = document.getElementById('notAttendingCloseBtn');
    if (closeBtn) closeBtn.focus();
    
    // Remove any injected Yest/24 while modal is open
    if (modal._notAttendingCleanup) clearInterval(modal._notAttendingCleanup);
    modal._notAttendingCleanup = setInterval(function() {
        if (!modal || modal.classList.contains('hidden')) {
            if (modal) { clearInterval(modal._notAttendingCleanup); modal._notAttendingCleanup = null; modal.style.removeProperty('z-index'); }
            return;
        }
        const btnRow = modal.querySelector('.flex.justify-end');
        if (btnRow) {
            btnRow.querySelectorAll('button').forEach(function(btn) {
                if (btn.id !== 'notAttendingCloseBtn') btn.remove();
            });
        }
        modal.querySelectorAll('span, div').forEach(function(el) {
            const t = (el.textContent || '').trim();
            if (el.children.length === 0 && /^\d{1,2}$/.test(t) && el.className && /\brounded-full\b/.test(el.className)) el.remove();
        });
    }, 100);
    
    setTimeout(() => {
        if (modal && !modal.classList.contains('hidden')) {
            modal.classList.add('hidden');
            if (modal._notAttendingCleanup) { clearInterval(modal._notAttendingCleanup); modal._notAttendingCleanup = null; }
            modal.style.removeProperty('z-index');
        }
    }, 5000);
}

// Show attendance confirmed (success) modal instead of toast
function showAttendanceConfirmedModal() {
    const modal = document.getElementById('attendanceConfirmedModal');
    if (!modal) return;
    const card = modal.firstElementChild;
    if (card) {
        card.innerHTML = `
            <div class="p-6 overflow-hidden">
                <div class="flex flex-col items-center text-center mb-6">
                    <div class="w-16 h-16 bg-green-100 dark:bg-green-900/20 rounded-full flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-green-600 dark:text-green-400 text-3xl">check_circle</span>
                    </div>
                    <h3 class="text-xl font-bold text-text-light dark:text-text-dark mb-2">You're all set!</h3>
                    <p class="text-sm text-text-muted-light dark:text-text-muted-dark">Great! We'll see you at the event.</p>
                </div>
                <div class="flex justify-end">
                    <button type="button" id="attendanceConfirmedCloseBtn" class="px-6 py-2 text-sm font-medium text-white bg-green-600 dark:bg-green-700 rounded-lg hover:bg-green-700 dark:hover:bg-green-600 transition-colors">Close</button>
                </div>
            </div>
        `;
        const closeBtn = document.getElementById('attendanceConfirmedCloseBtn');
        if (closeBtn) closeBtn.addEventListener('click', function() { modal.classList.add('hidden'); modal.style.removeProperty('z-index'); });
    }
    [document.getElementById('timePickerModal'), document.getElementById('datePickerModal'), document.getElementById('createModal')].forEach(function(el) {
        if (!el) return;
        el.style.setProperty('left', '-99999px', 'important');
        el.style.setProperty('z-index', '-1', 'important');
    });
    modal.style.setProperty('z-index', '999999', 'important');
    modal.classList.remove('hidden');
    const closeBtn = document.getElementById('attendanceConfirmedCloseBtn');
    if (closeBtn) closeBtn.focus();
}

// Show delete success feedback (no full-screen dark overlay)
function showDeleteSuccessModal() {
    // Ensure the full-screen overlay stays hidden
    const modal = document.getElementById('deleteSuccessModal');
    if (modal && !modal.classList.contains('hidden')) {
        modal.classList.add('hidden');
    }

    // Use existing toast system instead
    if (typeof showToast === 'function') {
        showToast('Schedule deleted successfully', 'success');
    }
}

// Handle attendance confirmation
function confirmAttendance(reminderId, attending) {
    const reminders = JSON.parse(localStorage.getItem('scheduleReminders') || '[]');
    const index = reminders.findIndex(r => r.id === reminderId);
    
    if (index !== -1) {
        reminders[index].confirmed = true;
        reminders[index].attending = attending;
        reminders[index].confirmedAt = new Date().toISOString();
        
        // Also mark all reminders for this event as confirmed
        const eventId = reminders[index].eventId;
        reminders.forEach(r => {
            if (r.eventId === eventId) {
                r.confirmed = true;
                r.attending = attending;
            }
        });
        
        // Store confirmed event IDs separately for today's events check
        const confirmedEvents = JSON.parse(localStorage.getItem('confirmedAttendanceEvents') || '[]');
        if (!confirmedEvents.includes(eventId)) {
            confirmedEvents.push(eventId);
            localStorage.setItem('confirmedAttendanceEvents', JSON.stringify(confirmedEvents));
        }
        
        localStorage.setItem('scheduleReminders', JSON.stringify(reminders));
        
        // Close attendance confirmation modal and stop cleanup / observer; restore other modals
        const modal = document.getElementById('eventAttendanceModal');
        if (modal) {
            if (modal._attendanceCleanupInterval) { clearInterval(modal._attendanceCleanupInterval); modal._attendanceCleanupInterval = null; }
            if (modal._attendanceObserver) { modal._attendanceObserver.disconnect(); modal._attendanceObserver = null; }
            modal.classList.add('hidden');
            modal.style.removeProperty('z-index');
            document.body.classList.remove('attendance-modal-open');
            [document.getElementById('datePickerModal'), document.getElementById('createModal')].forEach(function(el) {
                if (el) { el.style.removeProperty('left'); el.style.removeProperty('top'); el.style.removeProperty('z-index'); el.style.removeProperty('position'); el.style.removeProperty('visibility'); el.style.removeProperty('display'); el.style.removeProperty('pointer-events'); }
            });
        }
        
        // Show confirmation message or modal
        if (attending) {
            if (typeof showAttendanceConfirmedModal === 'function') showAttendanceConfirmedModal();
            else showToast('Great! We\'ll see you at the event.', 'success');
        } else {
            // Show not attending modal instead of toast
            showNotAttendingModal();
        }
    }
}

// Set up attendance modal button handlers (used on DOMContentLoaded and after rebuilding modal body)
function setupAttendanceModalButtonHandlers() {
    const attendanceModal = document.getElementById('eventAttendanceModal');
    const yesBtn = document.getElementById('attendanceYesBtn');
    const noBtn = document.getElementById('attendanceNoBtn');
    if (!attendanceModal) return;
    if (yesBtn && !yesBtn._attendanceBound) {
        yesBtn._attendanceBound = true;
        yesBtn.addEventListener('click', () => {
            const reminderId = attendanceModal.dataset.reminderId;
            const eventId = attendanceModal.dataset.eventId;
            if (reminderId) {
                confirmAttendance(reminderId, true);
            } else if (eventId) {
                const reminders = JSON.parse(localStorage.getItem('scheduleReminders') || '[]');
                const reminder = reminders.find(r => r.eventId === eventId);
                if (reminder) {
                    confirmAttendance(reminder.id, true);
                } else {
                    const confirmedEvents = JSON.parse(localStorage.getItem('confirmedAttendanceEvents') || '[]');
                    if (!confirmedEvents.includes(eventId)) {
                        confirmedEvents.push(eventId);
                        localStorage.setItem('confirmedAttendanceEvents', JSON.stringify(confirmedEvents));
                    }
                    attendanceModal.classList.add('hidden');
                    document.body.classList.remove('attendance-modal-open');
                    if (typeof showAttendanceConfirmedModal === 'function') showAttendanceConfirmedModal();
                    else showToast('Great! We\'ll see you at the event.', 'success');
                }
            }
        });
    }
    if (noBtn && !noBtn._attendanceBound) {
        noBtn._attendanceBound = true;
        noBtn.addEventListener('click', () => {
            const reminderId = attendanceModal.dataset.reminderId;
            const eventId = attendanceModal.dataset.eventId;
            if (reminderId) {
                confirmAttendance(reminderId, false);
            } else if (eventId) {
                const reminders = JSON.parse(localStorage.getItem('scheduleReminders') || '[]');
                const reminder = reminders.find(r => r.eventId === eventId);
                if (reminder) {
                    confirmAttendance(reminder.id, false);
                } else {
                    const confirmedEvents = JSON.parse(localStorage.getItem('confirmedAttendanceEvents') || '[]');
                    if (!confirmedEvents.includes(eventId)) {
                        confirmedEvents.push(eventId);
                        localStorage.setItem('confirmedAttendanceEvents', JSON.stringify(confirmedEvents));
                    }
                    attendanceModal.classList.add('hidden');
                    document.body.classList.remove('attendance-modal-open');
                    showNotAttendingModal();
                }
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    setupAttendanceModalButtonHandlers();
    
    const attendanceModal = document.getElementById('eventAttendanceModal');
    // Set up not attending modal close button
    const notAttendingModal = document.getElementById('notAttendingModal');
    const notAttendingCloseBtn = document.getElementById('notAttendingCloseBtn');
    
    if (notAttendingCloseBtn) {
        notAttendingCloseBtn.addEventListener('click', () => {
            if (notAttendingModal) {
                notAttendingModal.classList.add('hidden');
            }
        });
    }
    
    // Close not attending modal when clicking outside
    if (notAttendingModal) {
        notAttendingModal.addEventListener('click', (e) => {
            if (e.target === notAttendingModal) {
                notAttendingModal.classList.add('hidden');
            }
        });
    }
    
    // Set up attendance confirmed (success) modal close button and click-outside
    const attendanceConfirmedModal = document.getElementById('attendanceConfirmedModal');
    const attendanceConfirmedCloseBtn = document.getElementById('attendanceConfirmedCloseBtn');
    if (attendanceConfirmedCloseBtn) {
        attendanceConfirmedCloseBtn.addEventListener('click', () => {
            if (attendanceConfirmedModal) {
                attendanceConfirmedModal.classList.add('hidden');
                attendanceConfirmedModal.style.removeProperty('z-index');
            }
        });
    }
    if (attendanceConfirmedModal) {
        attendanceConfirmedModal.addEventListener('click', (e) => {
            if (e.target === attendanceConfirmedModal) {
                attendanceConfirmedModal.classList.add('hidden');
                attendanceConfirmedModal.style.removeProperty('z-index');
            }
        });
    }
    
    // Close modal when clicking outside
    if (attendanceModal) {
        attendanceModal.addEventListener('click', (e) => {
            if (e.target === attendanceModal) {
                // Don't close on outside click - force user to respond
                // attendanceModal.classList.add('hidden');
            }
        });
    }
    
    // Set up delete success modal close button
    const deleteSuccessModal = document.getElementById('deleteSuccessModal');
    const deleteSuccessCloseBtn = document.getElementById('deleteSuccessCloseBtn');
    
    if (deleteSuccessCloseBtn) {
        deleteSuccessCloseBtn.addEventListener('click', () => {
            if (deleteSuccessModal) {
                deleteSuccessModal.classList.add('hidden');
            }
        });
    }
    
    // Close delete success modal when clicking outside
    if (deleteSuccessModal) {
        deleteSuccessModal.addEventListener('click', (e) => {
            if (e.target === deleteSuccessModal) {
                deleteSuccessModal.classList.add('hidden');
            }
        });
    }
});

// Check for events that are today and show attendance confirmation modal
function checkTodaysEvents() {
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const confirmedEvents = JSON.parse(localStorage.getItem('confirmedAttendanceEvents') || '[]');
    
    // Check if modal is already open
    const modal = document.getElementById('eventAttendanceModal');
    if (modal && !modal.classList.contains('hidden')) {
        return; // Don't show another modal if one is already open
    }
    
    // Find events that are today and not confirmed
    const todaysEvents = events.filter(event => {
        // Skip if already confirmed
        if (confirmedEvents.includes(event.id)) {
            return false;
        }
        
        // Parse event date
        const dateStr = event.date || '';
        if (!dateStr) return false;
        
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                           'July', 'August', 'September', 'October', 'November', 'December'];
        
        let day, month, year;
        const dayMatch = dateStr.match(/\b(\d{1,2})\b/);
        if (dayMatch) day = parseInt(dayMatch[1]);
        
        const monthIndex = monthNames.findIndex(m => dateStr.includes(m));
        if (monthIndex !== -1) month = monthIndex;
        
        const yearMatch = dateStr.match(/\b(19|20)\d{2}\b/);
        if (yearMatch) {
            year = parseInt(yearMatch[0]);
        } else {
            year = now.getFullYear();
        }
        
        if (!day || month === undefined) {
            return false;
        }
        
        // Check if event is today
        const eventDate = new Date(year, month, day);
        return eventDate.getTime() === today.getTime();
    });
    
    // Show modal for the first unconfirmed event that's today
    if (todaysEvents.length > 0) {
        const event = todaysEvents[0]; // Show first event
        const timeStr = event.startTime || event.time || '';
        
        // Check if we already have a reminder for this event today
        const reminders = JSON.parse(localStorage.getItem('scheduleReminders') || '[]');
        let reminder = reminders.find(r => r.eventId === event.id && r.reminderText === 'Today');
        
        // If no reminder exists, create one
        if (!reminder) {
            reminder = {
                id: `today-${event.id}-${Date.now()}`,
                eventId: event.id,
                eventTitle: event.title,
                eventDate: event.date,
                eventTime: timeStr,
                reminderTime: new Date().toISOString(),
                reminderText: 'Today',
                shown: false,
                confirmed: false
            };
            reminders.push(reminder);
            localStorage.setItem('scheduleReminders', JSON.stringify(reminders));
        }
        
        // Only show if not already confirmed
        if (!reminder.confirmed) {
            showAttendanceConfirmationModal(reminder);
        }
    }
}

// Start checking reminders every 30 seconds for more responsive notifications
setInterval(checkScheduleReminders, 30000);
// Check for today's events every 30 seconds as well
setInterval(checkTodaysEvents, 30000);

// Check reminders on page load and schedule reminders for existing events
document.addEventListener('DOMContentLoaded', () => {
    // Ensure time picker is never visible on load (only when user opens Add schedule and clicks time)
    if (typeof closeTimePickerModal === 'function') closeTimePickerModal();
    
    checkScheduleReminders();
    
    // Check for today's events on page load (after a short delay to ensure events are loaded)
    setTimeout(() => {
        checkTodaysEvents();
        
        // Schedule reminders for existing events
        events.forEach(event => {
            if (event.reminder && event.reminder !== 'None') {
                scheduleReminderNotification(event);
            }
        });
    }, 1000);
});
</script>
</body></html>

