<?php
/**
 * LILAC Awards Management - With Upload & Analysis
 */
session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user = $_SESSION['user'];
$token = $_SESSION['token'];

require_once __DIR__ . '/api/config.php';

// Verify admin status from database to ensure accuracy
$isAdmin = false;
try {
    $pdo = getDatabaseConnection();
    if (!($pdo instanceof FileBasedDatabase)) {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($dbUser) {
            $isAdmin = ($dbUser['role'] === 'admin');
            // Update session if role changed
            if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] !== $dbUser['role']) {
                $_SESSION['user']['role'] = $dbUser['role'];
                $_SESSION['role'] = $dbUser['role'];
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

$awards = [];
$canPersistRequirementTracker = false;
$chedRequirementState = [];
$CHED_REQUIREMENT_PREF_KEY = 'ched_requirement_tracker';
try {
    $pdo = getDatabaseConnection();
    if ($pdo instanceof FileBasedDatabase) {
        $dataDir = __DIR__ . '/data/';
        if (is_dir($dataDir)) {
            $files = glob($dataDir . 'analysis_*.json');
            foreach ($files as $file) {
                $data = json_decode(file_get_contents($file), true);
                if ($data)
                    $awards[] = $data;
            }
        }
    } else {
        if ($isAdmin) {
            $stmt = $pdo->query('SELECT a.*, aa.predicted_category, aa.match_percentage, aa.status FROM awards a LEFT JOIN award_analysis aa ON aa.award_id = a.id ORDER BY a.created_at DESC');
            $awards = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->prepare('SELECT a.*, aa.predicted_category, aa.match_percentage, aa.status FROM awards a LEFT JOIN award_analysis aa ON aa.award_id = a.id WHERE a.user_id = ? ORDER BY a.created_at DESC');
            $stmt->execute([$user['id']]);
            $awards = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        try {
            $canPersistRequirementTracker = true;
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS user_preferences (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    user_id INT(11) NOT NULL,
                    preference_key VARCHAR(100) NOT NULL,
                    preference_value TEXT DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY unique_user_preference (user_id, preference_key),
                    KEY idx_user_id (user_id),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $prefStmt = $pdo->prepare('SELECT preference_value FROM user_preferences WHERE user_id = ? AND preference_key = ?');
            $prefStmt->execute([$user['id'], $CHED_REQUIREMENT_PREF_KEY]);
            $pref = $prefStmt->fetch(PDO::FETCH_ASSOC);
            if ($pref && !empty($pref['preference_value'])) {
                $decoded = json_decode($pref['preference_value'], true);
                if (is_array($decoded)) {
                    $chedRequirementState = $decoded;
                }
            }
        } catch (Exception $prefError) {
            $canPersistRequirementTracker = false;
            error_log('CHED requirement tracker persistence unavailable: ' . $prefError->getMessage());
        }
    }
} catch (Exception $e) {
    error_log('Awards load error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>LILAC Awards Management</title>
    <link crossorigin="" href="https://fonts.gstatic.com/" rel="preconnect" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link rel="stylesheet" href="css/award-analyzer.css">
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Apply theme immediately to prevent flash
        (function () {
            const savedTheme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const shouldBeDark = savedTheme === 'dark' || (!savedTheme && prefersDark);
            if (shouldBeDark) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <script src="js/notifications.js"></script>
    <script src="js/award-analyzer.js"></script>
    <script>
        window.chedRequirementConfig = <?php echo json_encode([
            'canPersist' => $canPersistRequirementTracker,
            'initialState' => !empty($chedRequirementState) ? $chedRequirementState : new stdClass(),
            'storageKey' => 'chedRequirementTracker',
            'preferenceKey' => $CHED_REQUIREMENT_PREF_KEY,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": {
                            DEFAULT: "#137fec",
                            "50": "#e8f2fe",
                            "100": "#d1e6fd",
                            "200": "#a2cbfb",
                            "300": "#74b1f9",
                            "400": "#4596f7",
                            "500": "#137fec",
                            "600": "#0f66bc",
                            "700": "#0c4c8d",
                            "800": "#08335d",
                            "900": "#04192e"
                        },
                        "background-light": "#e8ecf1",
                        "background-dark": "#0f172a",
                        "card-light": "#fafbfc",
                        "card-dark": "#1e293b",
                        "text-light": "#1e293b",
                        "text-dark": "#e2e8f0",
                        "text-muted-light": "#64748b",
                        "text-muted-dark": "#94a3b8",
                        "border-light": "#d1d5db",
                        "border-dark": "#334155",
                        // Modern Professional Color Palette
                        'kpi-blue': '#2563eb',
                        'kpi-green': '#059669',
                        'kpi-purple': '#7c3aed',
                        'kpi-yellow': '#f59e0b',
                        'kpi-indigo': '#4f46e5',
                        'kpi-teal': '#0d9488',
                        'kpi-orange': '#ea580c',
                        'kpi-pink': '#ec4899',
                        // Status Chart Colors
                        'status-fully': '#3b82f6',
                        'status-partial': '#a78bfa',
                        'status-review': '#facc15',
                        'status-unqualified': '#ef4444',
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.5rem",
                        "lg": "0.75rem",
                        "xl": "1rem",
                        "full": "9999px"
                    },
                    boxShadow: {
                        'soft': '0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05)',
                    }
                },
            },
        }
    </script>
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
            transition: width 0.3s ease, min-width 0.3s ease, max-width 0.3s ease;
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

        .sidebar-expanded .sidebar-profile-picture {
            display: block;
        }

        .sidebar-expanded .sidebar-profile-info {
            display: block;
        }

        main {
            flex: 1;
            transition: margin-left 0.3s ease;
        }

        /* When sidebar is collapsed, add margin to main to account for sidebar width */
        .sidebar-collapsed main {
            margin-left: 0;
        }
        
        /* Ensure main content doesn't get pushed incorrectly */
        #app-container.sidebar-collapsed main {
            margin-left: 0;
        }

        .main-content {
            padding-left: 0;
        }

        /* Analytics Dashboard Styles */
        .analytics-card {
            background-color: white;
            padding: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px 0 rgba(0, 0, 0, 0.02);
            border: 1px solid #f3f4f6;
            transition: all 0.2s ease-in-out;
        }

        .dark .analytics-card {
            background-color: #1e293b;
            border-color: #334155;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.3), 0 1px 2px 0 rgba(0, 0, 0, 0.2);
        }

        .analytics-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .dark .analytics-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.4), 0 2px 4px -1px rgba(0, 0, 0, 0.3);
        }

        .kpi-box {
            padding: 1.5rem 1rem;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: flex-start;
            min-height: 5rem;
        }

        /* Document Scanning Animation */
        @keyframes scan-line {
            0% {
                top: 0;
                opacity: 0.75;
            }
            50% {
                opacity: 1;
            }
            100% {
                top: 100%;
                opacity: 0.75;
            }
        }

        .animate-scan-line {
            animation: scan-line 2s linear infinite;
            box-shadow: 0 0 20px rgba(19, 127, 236, 0.5);
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

        /* Modern Dropdown Styles */
        .modern-dropdown {
            max-height: 280px;
            overflow-y: auto;
            animation: dropdownFadeIn 0.2s ease-out;
        }

        @keyframes dropdownFadeIn {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-content {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 10px 38px -10px rgba(22, 23, 24, 0.35),
                        0 10px 20px -15px rgba(22, 23, 24, 0.2);
            border: 1px solid rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .dark .dropdown-content {
            background: #1e293b;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 10px 38px -10px rgba(0, 0, 0, 0.5),
                        0 10px 20px -15px rgba(0, 0, 0, 0.3);
        }

        .dropdown-option {
            padding: 0.75rem 1rem;
            cursor: pointer;
            transition: all 0.15s ease;
            font-size: 0.875rem;
            color: #0f172a;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .dropdown-option:last-child {
            border-bottom: none;
        }

        .dark .dropdown-option {
            color: #e2e8f0;
            border-bottom-color: rgba(255, 255, 255, 0.05);
        }

        .dropdown-option:hover,
        .dropdown-option.highlighted {
            background: linear-gradient(135deg, rgba(19, 127, 236, 0.08) 0%, rgba(19, 127, 236, 0.05) 100%);
            color: #137fec;
            padding-left: 1.25rem;
        }

        .dark .dropdown-option:hover,
        .dark .dropdown-option.highlighted {
            background: linear-gradient(135deg, rgba(19, 127, 236, 0.15) 0%, rgba(19, 127, 236, 0.08) 100%);
            color: #4596f7;
        }

        .dropdown-option:active {
            background: linear-gradient(135deg, rgba(19, 127, 236, 0.12) 0%, rgba(19, 127, 236, 0.08) 100%);
        }

        .dark .dropdown-option:active {
            background: linear-gradient(135deg, rgba(19, 127, 236, 0.2) 0%, rgba(19, 127, 236, 0.12) 100%);
        }

        /* Hide native datalist dropdown arrow and datalist */
        #award_name::-webkit-calendar-picker-indicator {
            display: none;
        }
        
        /* Completely hide native datalist dropdown */
        datalist#award-suggestions {
            display: none !important;
        }

        .progress-circle {
            transform: rotate(-90deg);
        }

        /* Hide scrollbar utility */
        .no-scrollbar {
            -ms-overflow-style: none;
            /* IE and Edge */
            scrollbar-width: none;
            /* Firefox */
        }

        .no-scrollbar::-webkit-scrollbar {
            display: none;
            /* Chrome, Safari */
        }

        /* Custom scrollbar styling for all elements */
        * {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f1f5f9;
        }

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

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
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

        .page-animate-delay-3 {
            animation: fadeInUp 0.6s ease-out 0.3s forwards;
            opacity: 0;
        }

        .header-animate {
            animation: fadeIn 0.5s ease-out forwards;
        }

        .content-animate {
            animation: fadeInUp 0.7s ease-out 0.2s forwards;
            opacity: 0;
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

        /* New counter cards styling is handled by Tailwind classes */
        .tab-underline {
            position: relative;
        }

        .tab-underline::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0.25rem;
            right: 0.25rem;
            height: 2px;
            background-color: rgb(19, 127, 236);
            transform: scaleX(0);
            transition: transform 0.3s ease-in-out;
            transform-origin: left;
        }

        @media (prefers-color-scheme: dark) {
            .tab-underline::after {
                background-color: rgb(69, 151, 247);
            }
        }

        .dark .tab-underline::after {
            background-color: rgb(69, 151, 247);
        }

        .active.tab-underline::after {
            transform: scaleX(1);
        }

        .tab-content {
            display: block;
        }

        .tab-content.hidden {
            display: none;
        }

        .chart-tooltip {
            position: absolute;
            background-color: rgb(19, 127, 236);
            color: white;
            padding: 4px 8px;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            visibility: hidden;
            opacity: 0;
            transition: opacity 0.2s;
            z-index: 10;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
        }

        @media (prefers-color-scheme: dark) {
            .chart-tooltip {
                background-color: rgb(69, 151, 247);
            }
        }

        .dark .chart-tooltip {
            background-color: rgb(69, 151, 247);
        }

        .group:hover .chart-tooltip {
            visibility: visible;
            opacity: 1;
        }
    </style>
</head>

<body class="bg-background-light dark:bg-background-dark font-display text-text-light dark:text-text-dark">
    <div class="flex h-screen sidebar-collapsed" id="app-container">
        <aside
            class="sidebar bg-card-light dark:bg-card-dark border-r border-border-light dark:border-border-dark flex flex-col">
            <div class="flex items-center justify-start px-4 h-20 border-b border-border-light dark:border-border-dark">
                <div class="flex items-center gap-3">
                    <img alt="CPU LILAC Logo" class="h-11 w-11" src="./api/get-logo.php?v=1" width="32"
                        height="32"
                        onerror="this.style.display='none'; document.getElementById('logo-fallback').style.display='flex'; console.error('Logo failed to load:', this.src);" />
                    <div class="h-11 w-11 bg-primary rounded-lg flex items-center justify-center text-white font-bold text-sm"
                        style="display: none;" id="logo-fallback">CPU</div>
                    <h1 class="text-xl font-bold text-text-light dark:text-text-dark sidebar-logo-text hidden">LILAC
                    </h1>
                </div>
            </div>
            <nav class="flex-1 px-4 py-6 space-y-2">
                <a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link"
                    href="dashboard.php">
                    <span class="material-symbols-outlined">dashboard</span>
                    <span class="sidebar-text hidden">Dashboard</span>
                </a>
                <a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg bg-primary-50 dark:bg-primary-900/40 text-primary-600 dark:text-primary-400 font-semibold sidebar-nav-link"
                    href="user-awards.php">
                    <span class="material-symbols-outlined filled">emoji_events</span>
                    <span class="sidebar-text hidden">Awards Management</span>
                </a>
                <a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link"
                    href="events-activities.php">
                    <span class="material-symbols-outlined">event</span>
                    <span class="sidebar-text hidden">Events &amp; Activities</span>
                </a>
                <a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link"
                    href="scheduler.php">
                    <span class="material-symbols-outlined">calendar_today</span>
                    <span class="sidebar-text hidden">Scheduler</span>
                </a>
                <a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link"
                    href="mou-moa.php">
                    <span class="material-symbols-outlined">handshake</span>
                    <span class="sidebar-text hidden">MOUs &amp; MOAs</span>
                </a>

                <a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link"
                    href="documents.php">
                    <span class="material-symbols-outlined">description</span>
                    <span class="sidebar-text hidden">Documents</span>
                </a>
            </nav>
            <div class="px-4 py-4 border-t border-border-light dark:border-border-dark">
                <div class="flex items-center justify-between profile-container">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-cover bg-center sidebar-profile-picture hidden"
                            style='background-image: url("<?php echo !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : 'https://lh3.googleusercontent.com/aida-public/AB6AXuC23fvgOSZIK6K5vguUgvVeU1XYFfp1LB3d4zICMvW6bispRl-eHHfnOtSsvRU3MgvmOpSYMCZhcSBIksvjlEHtkGMxuCFsQkuT0suo2-O9n3py7mlzFFETXCOIfvLVGGUj1aaG8ENOeDXXy_ifek2uG3R3--ghDflKvuAm9vrceoK8doav0lNYVbLz1bnWy6REWcrCPuPZZ8upfPqShoQpSDjICl16zMEcRuHzjt05z9cFITLKPdZTfMF-1dLK-klh8UhjeDeE4Q7p'; ?>");'>
                        </div>
                        <div class="sidebar-profile-info hidden">
                            <p class="font-semibold text-text-light dark:text-text-dark"><?php echo htmlspecialchars($user['role'] === 'admin' ? 'Admin User' : $user['username']); ?></p>
                            <div class="flex gap-3">
                                <a class="text-sm text-primary-600 dark:text-primary-400 hover:underline" href="profile.php">Profile</a>
                                <span class="text-sm text-gray-400">|</span>
                                <a class="text-sm text-red-600 dark:text-red-400 hover:underline" href="logout.php">Logout</a>
                            </div>
                        </div>
                    </div>
                    <button class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-background-dark transition-colors"
                        id="sidebar-toggle">
                        <span class="material-symbols-outlined sidebar-toggle-icon-open hidden">chevron_left</span>
                        <span class="material-symbols-outlined sidebar-toggle-icon-closed block">chevron_right</span>
                    </button>
                </div>
            </div>
        </aside>
        <main class="flex-1 overflow-y-auto">
            <header
                class="sticky top-0 bg-background-light/80 dark:bg-background-dark/80 backdrop-blur-sm z-30 px-6 lg:px-8 py-4 border-b border-border-light dark:border-border-dark flex justify-between items-center h-20 overflow-visible header-animate">
                <h1 class="text-2xl font-bold text-text-light dark:text-text-dark">Awards Management</h1>
                <div class="flex items-center gap-2">
                    <div class="relative z-[9999]">
                        <button id="notificationBtn"
                            class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-white/10 text-text-muted-light dark:text-text-muted-dark transition-colors duration-200 relative">
                            <span class="material-symbols-outlined">notifications</span>
                            <span id="notificationBadge"
                                class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
                        </button>
                        <div id="notificationDropdown"
                            class="absolute right-0 top-full mt-2 w-96 bg-white dark:bg-background-dark rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 z-[9999] hidden max-h-96 overflow-y-auto">
                            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Notifications</h3>
                                    <button id="markAllReadBtn"
                                        class="text-sm text-primary hover:text-primary/80 dark:text-primary-400 dark:hover:text-primary-300">
                                        Mark all read
                                    </button>
                                </div>
                            </div>
                            <div id="notificationList" class="max-h-80 overflow-y-auto">
                                <div id="noNotifications" class="p-6 text-center text-gray-500 dark:text-gray-400">
                                    <span class="material-symbols-outlined text-4xl mb-2 block">notifications_off</span>
                                    <p>No notifications</p>
                                </div>
                            </div>
                            <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                                <button id="viewAllNotifications"
                                    class="w-full text-center text-sm text-primary hover:text-primary/80 dark:text-primary-400 dark:hover:text-primary-300">
                                    View all notifications
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- MOU Notification Detail Modal -->
                    <div id="mouNotificationModal" class="fixed inset-0 z-[10000] flex items-center justify-center bg-black/50 backdrop-blur-sm hidden">
                        <div class="w-full max-w-md bg-white dark:bg-background-dark rounded-xl shadow-2xl m-4 border border-gray-200 dark:border-gray-700">
                            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">MOU/MOA Notification</h3>
                                    <button id="closeMouModal" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500 dark:text-gray-400">
                                        <span class="material-symbols-outlined">close</span>
                                    </button>
                                </div>
                            </div>
                            <div class="p-6">
                                <div id="mouModalContent">
                                    <!-- Content will be populated dynamically -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button
                        class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-white/10 text-text-muted-light dark:text-text-muted-dark transition-colors duration-200"
                        id="theme-toggle">
                        <span class="material-symbols-outlined dark:hidden">light_mode</span>
                        <span class="material-symbols-outlined hidden dark:inline">dark_mode</span>
                    </button>
                </div>
            </header>
            <div class="p-4 lg:px-10 lg:py-6 main-content content-animate">
                <div class="max-w-7xl mx-auto">
                    <div class="flex flex-col gap-8">
                        <div class="border-b border-border-light dark:border-border-dark page-animate-delay-1">
                            <nav aria-label="Tabs" class="-mb-px flex space-x-8">
                                <a class="active tab-underline text-primary whitespace-nowrap py-4 px-1 font-bold text-sm relative"
                                    href="#" id="process-tab">Process Award</a>
                                <a class="tab-underline border-transparent text-text-muted-light dark:text-text-muted-dark hover:text-text-light dark:hover:text-text-dark hover:border-border-light dark:hover:border-border-dark whitespace-nowrap py-4 px-1 font-medium text-sm transition-colors duration-300"
                                    href="#" id="analytics-tab">Analytics Dashboard</a>
                                <a class="tab-underline border-transparent text-text-muted-light dark:text-text-muted-dark hover:text-text-light dark:hover:text-text-dark hover:border-border-light dark:hover:border-border-dark whitespace-nowrap py-4 px-1 font-medium text-sm transition-colors duration-300"
                                    href="#" id="award-list-tab">Award List & Criteria</a>
                                <a class="tab-underline border-transparent text-text-muted-light dark:text-text-muted-dark hover:text-text-light dark:hover:text-text-dark hover:border-border-light dark:hover:border-border-dark whitespace-nowrap py-4 px-1 font-medium text-sm transition-colors duration-300"
                                    href="#" id="ched-guidance-tab">CHED Guidelines</a>
                            </nav>
                        </div>

                        <!-- CHED ICONS 2024 Guidance & Gaps -->
                        <div id="ched-guidance-content" class="tab-content hidden">
                        <section id="ched-guidance" class="space-y-6 page-animate-delay-1">
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                                <div class="bg-card-light dark:bg-card-dark border border-border-light dark:border-border-dark rounded-2xl p-6 shadow-soft">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <h3 class="text-lg font-bold text-text-light dark:text-text-dark">CHED ICONS Awards 2024 Snapshot</h3>
                                            <p class="text-sm text-text-muted-light dark:text-text-muted-dark">Celebrating HEIs that lead on sustainability, ASEAN awareness, and internationalization.</p>
                                        </div>
                                        <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-primary/10 text-primary-700 dark:text-primary-300">Institutional · Individual · Special</span>
                                    </div>
                                    <ul class="mt-4 text-sm text-text-muted-light dark:text-text-muted-dark space-y-2 list-disc list-inside">
                                        <li>Icons Awards highlight CPU’s role in bringing PH higher education to the global stage.</li>
                                        <li>Internationalization (IZN) Awards remain the highlight—proof of strategic leadership is crucial.</li>
                                        <li>Focus areas lifted from CHED brief: intercultural understanding, inclusive access, and measurable impact.</li>
                                    </ul>
                                </div>
                                <div class="bg-card-light dark:bg-card-dark border border-border-light dark:border-border-dark rounded-2xl p-6 shadow-soft space-y-4">
                                    <div>
                                        <h3 class="text-lg font-bold text-text-light dark:text-text-dark">Deadlines & Official References</h3>
                                        <p class="text-sm text-text-muted-light dark:text-text-muted-dark">Nominations close <strong>01 October 2024 · 10:00 PM PhST</strong> (extended).</p>
                                    </div>
                                    <div class="p-4 rounded-xl border border-dashed border-primary/30 bg-primary/5">
                                        <p class="text-xs uppercase tracking-wide text-primary-700 dark:text-primary-300 mb-1">Countdown</p>
                                        <p id="ched-deadline-countdown" class="text-base font-semibold text-text-light dark:text-text-dark">Loading timeline…</p>
                                    </div>
                                    <div class="flex flex-wrap gap-3">
                                        <a href="https://ieducationphl.ched.gov.ph/asia-pacific/" target="_blank" rel="noopener"
                                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary/90 transition-colors">
                                            <span class="material-symbols-outlined text-sm">description</span>
                                            CHED Guidelines
                                        </a>
                                        <a href="https://forms.office.com/" target="_blank" rel="noopener"
                                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-border-light dark:border-border-dark text-sm font-semibold text-text-light dark:text-text-dark hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                                            <span class="material-symbols-outlined text-sm">open_in_new</span>
                                            Nomination Form
                                        </a>
                                    </div>
                                </div>
                                <div class="bg-card-light dark:bg-card-dark border border-border-light dark:border-border-dark rounded-2xl p-6 shadow-soft" id="milestoneTimelineCard">
                                    <div class="flex items-center justify-between flex-wrap gap-3">
                                        <div>
                                            <h3 class="text-lg font-bold text-text-light dark:text-text-dark">Milestone Timeline</h3>
                                            <p class="text-sm text-text-muted-light dark:text-text-muted-dark">Auto-order tasks based on your evidence tracker progress.</p>
                                        </div>
                                        <span class="px-3 py-1 text-xs rounded-full bg-primary/10 text-primary dark:bg-primary/20 dark:text-primary-200">CHED 2024 cycle</span>
                                    </div>
                                    <div class="mt-6 space-y-4" id="milestoneTimelineList">
                                        <div class="flex items-start gap-3 timeline-step" data-requirements="videos,supporting-docs">
                                            <div class="timeline-indicator h-10 w-10 rounded-full bg-primary/10 text-primary flex items-center justify-center font-semibold">1</div>
                                            <div>
                                                <p class="text-sm font-semibold text-text-light dark:text-text-dark">Compile evidence package</p>
                                                <p class="text-xs text-text-muted-light dark:text-text-muted-dark">Upload pitch video, MOUs, certificates, and related policies.</p>
                                                <p class="timeline-status text-xs font-semibold mt-1 text-primary" data-default-label="Due soon">Due soon</p>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-3 timeline-step" data-requirements="impact-metrics,deadline-plan">
                                            <div class="timeline-indicator h-10 w-10 rounded-full bg-primary/10 text-primary flex items-center justify-center font-semibold">2</div>
                                            <div>
                                                <p class="text-sm font-semibold text-text-light dark:text-text-dark">Reviewer alignment</p>
                                                <p class="text-xs text-text-muted-light dark:text-text-muted-dark">Lock timeline & assign reviewers to validate impact metrics.</p>
                                                <p class="timeline-status text-xs font-semibold mt-1 text-primary" data-default-label="Schedule ASAP">Schedule ASAP</p>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-3 timeline-step" data-requirements="nomination-form">
                                            <div class="timeline-indicator h-10 w-10 rounded-full bg-primary/10 text-primary flex items-center justify-center font-semibold">3</div>
                                            <div>
                                                <p class="text-sm font-semibold text-text-light dark:text-text-dark">CHED submission prep</p>
                                                <p class="text-xs text-text-muted-light dark:text-text-muted-dark">Lock the nomination form and readiness survey before the cutoff.</p>
                                                <p class="timeline-status text-xs font-semibold mt-1 text-primary" data-default-label="01 Oct 2024 · 10:00 PM PhST">01 Oct 2024 · 10:00 PM PhST</p>
                                            </div>
                                        </div>
                                    </div>
                                    <button id="refreshMilestoneTimeline" class="mt-6 inline-flex items-center gap-2 text-sm text-primary hover:text-primary/80">
                                        <span class="material-symbols-outlined text-base">update</span>
                                        Refresh timeline
                                    </button>
                                </div>
                            </div>

                            <div class="bg-card-light dark:bg-card-dark border border-border-light dark:border-border-dark rounded-2xl p-6 shadow-soft space-y-6">
                                <div class="flex items-center justify-between flex-wrap gap-3">
                                    <div>
                                        <h3 class="text-xl font-bold text-text-light dark:text-text-dark">Award Families & Readiness Cues</h3>
                                        <p class="text-sm text-text-muted-light dark:text-text-muted-dark">Use these quick cues when validating similarity scores against CHED’s narrative requirements.</p>
                                    </div>
                                    <span class="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-400">Source: CHED ICONS Awards 2024 content</span>
                                </div>
                                <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                                    <div class="p-5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-900/30">
                                        <div class="flex items-center justify-between mb-3">
                                            <h4 class="text-lg font-semibold text-text-light dark:text-text-dark">Global Citizenship Awards</h4>
                                            <span class="px-3 py-1 text-xs rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">Institutional · Cat A/B</span>
                                        </div>
                                        <ul class="text-sm text-text-muted-light dark:text-text-muted-dark space-y-1.5 list-disc list-inside">
                                            <li>Ignite intercultural understanding with inclusive learning arenas.</li>
                                            <li>Empower changemakers aligned with SDGs.</li>
                                            <li>Provide platforms for students to convert awareness into equitable action.</li>
                                        </ul>
                                    </div>
                                    <div class="p-5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-900/30">
                                        <div class="flex items-center justify-between mb-3">
                                            <h4 class="text-lg font-semibold text-text-light dark:text-text-dark">Outstanding International Education Program</h4>
                                            <span class="px-3 py-1 text-xs rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">Institutional · Cat A/B</span>
                                        </div>
                                        <ul class="text-sm text-text-muted-light dark:text-text-muted-dark space-y-1.5 list-disc list-inside">
                                            <li>Expand access—track inclusion of students across backgrounds and abilities.</li>
                                            <li>Foster collaborative innovation with varied local/foreign partners.</li>
                                            <li>Document how barriers are dismantled so mobility benefits everyone.</li>
                                        </ul>
                                    </div>
                                    <div class="p-5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-900/30">
                                        <div class="flex items-center justify-between mb-3">
                                            <h4 class="text-lg font-semibold text-text-light dark:text-text-dark">Emerging Leadership Award</h4>
                                            <span class="px-3 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">Individual · Directors/Managers</span>
                                        </div>
                                        <ul class="text-sm text-text-muted-light dark:text-text-muted-dark space-y-1.5 list-disc list-inside">
                                            <li>Spotlight innovative approaches in IRO operations or student services.</li>
                                            <li>Show how leaders drive inclusive, strategic growth for all stakeholders.</li>
                                            <li>Capture mentoring stories proving they empower next-gen champions.</li>
                                        </ul>
                                    </div>
                                    <div class="p-5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-900/30">
                                        <div class="flex items-center justify-between mb-3">
                                            <h4 class="text-lg font-semibold text-text-light dark:text-text-dark">Internationalization Leadership Award</h4>
                                            <span class="px-3 py-1 text-xs rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300">Individual · Executives</span>
                                        </div>
                                        <ul class="text-sm text-text-muted-light dark:text-text-muted-dark space-y-1.5 list-disc list-inside">
                                            <li>Champion bold innovation that embeds IZN across governance, curriculum, and services.</li>
                                            <li>Demonstrate how leaders cultivate ethical, inclusive, and globally ready graduates.</li>
                                            <li>Highlight metrics that prove lifelong learning and resilience mindsets.</li>
                                        </ul>
                                    </div>
                                    <div class="p-5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-900/30 xl:col-span-2">
                                        <div class="flex items-center justify-between mb-3">
                                            <h4 class="text-lg font-semibold text-text-light dark:text-text-dark">Best Regional Office for Internationalization</h4>
                                            <span class="px-3 py-1 text-xs rounded-full bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">Special · CHED ROs</span>
                                        </div>
                                        <ul class="text-sm text-text-muted-light dark:text-text-muted-dark space-y-1.5 list-disc list-inside">
                                            <li>Log comprehensive regional initiatives promoting sustainable & inclusive IZN.</li>
                                            <li>Track cooperation with CHED IAS and responsiveness to national programs.</li>
                                            <li>Maintain evidence of measurable impact (rankings, mobility, faculty exchange, July 15 survey readiness).</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-card-light dark:bg-card-dark border border-border-light dark:border-border-dark rounded-2xl p-6 shadow-soft space-y-4">
                                <div class="flex items-center justify-between flex-wrap gap-3">
                                    <div>
                                        <h3 class="text-xl font-bold text-text-light dark:text-text-dark">Submission Requirement Tracker</h3>
                                        <p class="text-sm text-text-muted-light dark:text-text-muted-dark">Mark items once the evidence is ready. Progress now saves to your account so teams can resume later.</p>
                                    </div>
                                    <div class="text-sm font-semibold text-primary-700 dark:text-primary-300" id="ched-requirement-progress-count">0/0 ready</div>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                                    <div class="bg-primary h-2.5 rounded-full transition-all duration-500" id="ched-requirement-progress-bar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div class="divide-y divide-border-light dark:divide-border-dark text-sm text-text-light dark:text-text-dark">
                                    <label class="flex items-start gap-3 py-3 cursor-pointer">
                                        <input type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary ched-requirement" data-requirement-id="videos">
                                        <div>
                                            <p class="font-semibold">Official video pitch</p>
                                            <p class="text-xs text-text-muted-light dark:text-text-muted-dark">5-min for IZN Leadership; 3-min for other awards. Confirm scripting, narration, and subtitles.</p>
                                        </div>
                                    </label>
                                    <label class="flex items-start gap-3 py-3 cursor-pointer">
                                        <input type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary ched-requirement" data-requirement-id="supporting-docs">
                                        <div>
                                            <p class="font-semibold">Supporting documents</p>
                                            <p class="text-xs text-text-muted-light dark:text-text-muted-dark">MOAs/MOUs, certificates, and board-approved policies referenced in CHED guidelines.</p>
                                        </div>
                                    </label>
                                    <label class="flex items-start gap-3 py-3 cursor-pointer">
                                        <input type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary ched-requirement" data-requirement-id="nomination-form">
                                        <div>
                                            <p class="font-semibold">Nomination/Application form</p>
                                            <p class="text-xs text-text-muted-light dark:text-text-muted-dark">Ensure all sections (institutional data, focal person, narrative answers) are filled before the deadline.</p>
                                        </div>
                                    </label>
                                    <label class="flex items-start gap-3 py-3 cursor-pointer">
                                        <input type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary ched-requirement" data-requirement-id="impact-metrics">
                                        <div>
                                            <p class="font-semibold">Impact & collaboration metrics</p>
                                            <p class="text-xs text-text-muted-light dark:text-text-muted-dark">Regional rankings, student/faculty mobility numbers, CHED IAS cooperation logs, July 15 survey readiness.</p>
                                        </div>
                                    </label>
                                    <label class="flex items-start gap-3 py-3 cursor-pointer">
                                        <input type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary ched-requirement" data-requirement-id="deadline-plan">
                                        <div>
                                            <p class="font-semibold">Deadline & reviewer plan</p>
                                            <p class="text-xs text-text-muted-light dark:text-text-muted-dark">Internal milestone plan leading to 01 Oct 2024 10:00 PM PhST submission, with assigned reviewers.</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </section>
                        </div>

                        <!-- Process Award Tab Content -->
                        <div id="process-content" class="tab-content">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                                <div class="space-y-6">
                                    <h3 class="text-xl font-bold text-text-light dark:text-text-dark">Input &amp; Upload
                                    </h3>
                                    <form class="space-y-4" id="award-analysis-form" enctype="multipart/form-data">
                                        <label class="block relative">
                                            <span class="text-sm font-medium text-text-light dark:text-text-dark">Award
                                                Name / Category</span>
                                            <input
                                                class="mt-1 block w-full rounded-lg border-border-light dark:border-border-dark bg-card-light dark:bg-card-dark text-text-light dark:text-text-dark focus:ring-primary focus:border-primary"
                                                placeholder="e.g. Global Citizenship Award" type="text" name="award_name"
                                                id="award_name" autocomplete="off" required />
                                            <!-- Native datalist removed - using custom dropdown instead -->
                                            <datalist id="award-suggestions" style="display: none;">
                                                <option value="Global Citizenship Award">
                                                <option value="Outstanding International Education Program Award">
                                                <option value="Sustainability Award">
                                                <option value="Emerging Leadership Award">
                                                <option value="International Leadership Award">
                                            </datalist>
                                            <!-- Modern Custom Dropdown -->
                                            <div id="award-name-dropdown" class="modern-dropdown hidden absolute left-0 right-0 mt-1 z-50">
                                                <div class="dropdown-content">
                                                    <div class="dropdown-option" data-value="Global Citizenship Award">Global Citizenship Award</div>
                                                    <div class="dropdown-option" data-value="Outstanding International Education Program Award">Outstanding International Education Program Award</div>
                                                    <div class="dropdown-option" data-value="Sustainability Award">Sustainability Award</div>
                                                    <div class="dropdown-option" data-value="Emerging Leadership Award">Emerging Leadership Award</div>
                                                    <div class="dropdown-option" data-value="International Leadership Award">International Leadership Award</div>
                                                </div>
                                            </div>
                                        </label>
                                        <label class="block">
                                            <span
                                                class="text-sm font-medium text-text-light dark:text-text-dark">Description</span>
                                            <textarea
                                                class="mt-1 block w-full rounded-lg border-border-light dark:border-border-dark bg-card-light dark:bg-card-dark text-text-light dark:text-text-dark focus:ring-primary focus:border-primary"
                                                placeholder="A short summary of the award accomplishment..." name="description"
                                                id="description" autocomplete="off" rows="3" required></textarea>
                                        </label>
                                        <label class="block">
                                            <span class="text-sm font-medium text-text-light dark:text-text-dark">Keywords
                                                <span class="text-xs text-gray-500">(Optional - helps with matching)</span>
                                            </span>
                                            <input
                                                class="mt-1 block w-full rounded-lg border-border-light dark:border-border-dark bg-card-light dark:bg-card-dark text-text-light dark:text-text-dark focus:ring-primary focus:border-primary"
                                                placeholder="e.g. international, sustainability, leadership, innovation"
                                                type="text" name="keywords"
                                                id="keywords" autocomplete="off" />
                                            <p class="mt-1 text-xs text-gray-500">Comma-separated keywords that describe your achievement</p>
                                        </label>
                                        <div>
                                            <span class="text-sm font-medium text-text-light dark:text-text-dark">Award
                                                Documentation</span>
                                            <!-- Document Selection Options -->
                                            <div class="flex items-center gap-2 mb-4">
                                                <button type="button" id="selectFromDocumentsBtn" class="flex-1 px-4 py-2 text-sm font-medium rounded-md border border-border-light dark:border-border-dark hover:bg-gray-50 dark:hover:bg-slate-700 flex items-center justify-center gap-2">
                                                    <span class="material-symbols-outlined text-sm">folder</span>
                                                    <span>Select from Documents</span>
                                                </button>
                                                <button type="button" id="selectFromEventsBtn" class="flex-1 px-4 py-2 text-sm font-medium rounded-md border border-border-light dark:border-border-dark hover:bg-gray-50 dark:hover:bg-slate-700 flex items-center justify-center gap-2">
                                                    <span class="material-symbols-outlined text-sm">event</span>
                                                    <span>Select from Events</span>
                                                </button>
                                            </div>
                                            <div id="selectedDocumentInfo" class="hidden p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg mb-4">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center gap-2">
                                                        <span class="material-symbols-outlined text-green-600 dark:text-green-400">check_circle</span>
                                                        <span class="text-sm font-medium text-green-800 dark:text-green-300" id="selectedDocumentName"></span>
                                                    </div>
                                                    <button type="button" id="clearSelectedDocument" class="text-red-500 hover:text-red-700">
                                                        <span class="material-symbols-outlined text-sm">close</span>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="text-center text-sm text-text-muted-light dark:text-text-muted-dark mb-2">OR</div>
                                            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-border-light dark:border-border-dark border-dashed rounded-lg bg-card-light dark:bg-card-dark/50 group hover:border-primary dark:hover:border-primary transition-colors duration-300"
                                                id="drop-zone">
                                                <div class="space-y-1 text-center">
                                                    <svg aria-hidden="true"
                                                        class="mx-auto h-12 w-12 text-text-muted-light dark:text-text-muted-dark group-hover:text-primary dark:group-hover:text-primary transition-colors"
                                                        fill="none" stroke="currentColor" viewBox="0 0 48 48">
                                                        <path
                                                            d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28"
                                                            stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"></path>
                                                    </svg>
                                                    <div
                                                        class="flex text-sm text-text-muted-light dark:text-text-muted-dark">
                                                        <label
                                                            class="relative cursor-pointer bg-transparent rounded-md font-medium text-primary hover:text-primary/80 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-primary"
                                                            for="file-upload">
                                                            <span>Upload a file</span>
                                                            <input class="sr-only" id="file-upload" name="award_file"
                                                                type="file" accept=".pdf,.docx,.jpg,.jpeg,.png" />
                                                        </label>
                                                        <p class="pl-1">or drag and drop</p>
                                                    </div>
                                                    <p class="text-xs text-text-muted-light dark:text-text-muted-dark">
                                                        PDF, DOCX, JPG, PNG up to 10MB</p>
                                                </div>
                                            </div>
                                            <div class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center hidden"
                                                id="file-error">
                                                <span
                                                    class="material-symbols-outlined text-base align-middle mr-1">error</span>
                                                <span id="file-error-message">File type not supported. Please upload a
                                                    PDF, DOCX, JPG, or PNG file.</span>
                                            </div>
                                            <div class="mt-2 text-sm text-green-600 dark:text-green-400 flex items-center hidden"
                                                id="file-success">
                                                <span
                                                    class="material-symbols-outlined text-base align-middle mr-1">check_circle</span>
                                                <span id="file-success-message">File uploaded successfully!</span>
                                            </div>
                                        </div>
                                        <div class="pt-2 space-y-4 hidden" id="upload-progress-section">
                                            <!-- Document Scanning Animation -->
                                            <div class="relative w-full h-32 bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden mb-4 border border-border-light dark:border-border-dark">
                                                <div class="absolute inset-0 flex items-center justify-center">
                                                    <span class="material-symbols-outlined text-6xl text-gray-300 dark:text-gray-600">description</span>
                                                </div>
                                                <div class="absolute top-0 left-0 w-full h-1 bg-primary opacity-75 animate-scan-line"></div>
                                            </div>

                                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                                                <div class="bg-primary h-2.5 rounded-full transition-all duration-500"
                                                    id="progress-bar" style="width: 0%"></div>
                                            </div>
                                            <div
                                                class="flex items-center justify-between text-sm text-text-muted-light dark:text-text-muted-dark">
                                                <div class="flex items-center">
                                                    <svg class="animate-spin h-5 w-5 mr-3 text-primary" fill="none"
                                                        viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10"
                                                            stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75"
                                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                                            fill="currentColor"></path>
                                                    </svg>
                                                    <span id="progress-text">Processing... Analyzing document with
                                                        OCR.</span>
                                                </div>
                                                <span id="progress-percentage" class="font-semibold text-primary">0%</span>
                                            </div>
                                        </div>
                                        <div class="flex justify-end pt-4">
                                            <button
                                                class="w-40 inline-flex justify-center items-center py-2 px-4 border border-transparent shadow-sm text-sm font-bold rounded-lg text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-all duration-300"
                                                type="submit" id="analyze-btn">
                                                <span class="btn-text">Analyze Award</span>
                                                <span class="material-symbols-outlined ml-2 animate-pulse hidden"
                                                    id="loading-icon">hourglass_empty</span>
                                                <span class="material-symbols-outlined ml-2 hidden"
                                                    id="success-icon">check_circle</span>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                <div class="space-y-6 overflow-hidden">
                                    <h3 class="text-xl font-bold text-text-light dark:text-text-dark">Analysis &amp;
                                        Recommendations Results</h3>

                                    <!-- Initial state -->
                                    <div id="analysis-placeholder"
                                        class="bg-card-light dark:bg-card-dark/50 rounded-xl p-8 border border-border-light dark:border-border-dark text-center">
                                        <div class="flex flex-col items-center space-y-4">
                                            <span
                                                class="material-symbols-outlined text-6xl text-text-muted-light dark:text-text-muted-dark">analytics</span>
                                            <h4 class="text-lg font-semibold text-text-light dark:text-text-dark">Ready
                                                for Analysis</h4>
                                            <p class="text-sm text-text-muted-light dark:text-text-muted-dark max-w-md">
                                                Upload your award document and click "Analyze Award" to see which awards
                                                your document qualifies for.</p>
                                        </div>
                                    </div>

                                    <!-- Analysis Results Container -->
                                    <div id="analysis-results" class="hidden space-y-4">

                                        <!-- Award Analysis Results -->
                                        <div id="award-analysis-list" class="space-y-4">
                                            <!-- Dynamic award analysis cards will be inserted here -->
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Extracted Text Modal -->
                    <div id="extracted-text-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
                        <div class="flex items-center justify-center min-h-screen p-4">
                            <div
                                class="bg-card-light dark:bg-card-dark rounded-xl max-w-4xl w-full max-h-[80vh] overflow-hidden">
                                <div
                                    class="flex items-center justify-between p-6 border-b border-border-light dark:border-border-dark">
                                    <h3 class="text-xl font-bold text-text-light dark:text-text-dark">Extracted Text
                                    </h3>
                                    <button onclick="closeExtractedTextModal()"
                                        class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                                        <span class="material-symbols-outlined">close</span>
                                    </button>
                                </div>
                                <div class="p-6 overflow-y-auto max-h-[60vh]">
                                    <div id="full-extracted-text"
                                        class="text-sm text-text-light dark:text-text-dark whitespace-pre-wrap bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                                    </div>
                                </div>
                                <div
                                    class="flex justify-end gap-3 p-6 border-t border-border-light dark:border-border-dark">
                                    <button onclick="closeExtractedTextModal()"
                                        class="px-4 py-2 text-sm font-medium text-text-muted-light dark:text-text-muted-dark hover:text-text-light dark:hover:text-text-dark transition-colors">Close</button>
                                    <button onclick="exportExtractedText()"
                                        class="px-4 py-2 text-sm font-medium text-white bg-primary hover:bg-primary/90 rounded-lg transition-colors">Export
                                        Text</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Analytics Dashboard Tab Content -->
                    <div id="analytics-content" class="tab-content hidden">
                        <div class="p-6 max-w-7xl mx-auto">

                            <div class="grid grid-cols-12 gap-6">

                                <div class="col-span-12 lg:col-span-9 space-y-6">

                                    <div class="grid grid-cols-4 gap-4">
                                        <div class="kpi-box analytics-card cursor-pointer hover:shadow-md transition-shadow border-t-4 border-gray-200 dark:border-gray-600"
                                            onclick="showAwardsMet()">
                                            <p id="kpi-total-awards" class="text-3xl font-extrabold text-gray-900 dark:text-gray-100">
                                                <svg class="animate-spin h-8 w-8 mx-auto text-primary" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </p>
                                            <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mt-1">Total
                                                Awards Met</p>
                                        </div>

                                        <div class="kpi-box analytics-card cursor-pointer hover:shadow-md transition-shadow border-t-4 border-gray-200 dark:border-gray-600"
                                            onclick="showEligibleDepartments()">
                                            <p id="kpi-eligible-awards" class="text-3xl font-extrabold text-gray-900 dark:text-gray-100">
                                                <svg class="animate-spin h-8 w-8 mx-auto text-primary" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </p>
                                            <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mt-1">
                                                Awards Eligible For</p>
                                        </div>

                                        <div class="kpi-box analytics-card cursor-pointer hover:shadow-md transition-shadow border-t-4 border-gray-200 dark:border-gray-600"
                                            onclick="showNewPartnerships()">
                                            <p id="kpi-orc-data" class="text-3xl font-extrabold text-gray-900 dark:text-gray-100">
                                                <svg class="animate-spin h-8 w-8 mx-auto text-primary" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </p>
                                            <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mt-1">ORC Data
                                                Analyzed</p>
                                        </div>

                                        <div class="kpi-box analytics-card border-t-4 border-gray-200 dark:border-gray-600">
                                            <p id="kpi-success-rate" class="text-3xl font-extrabold text-gray-900 dark:text-gray-100">
                                                <svg class="animate-spin h-8 w-8 mx-auto text-primary" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </p>
                                            <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mt-1">Success
                                                Rate</p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div class="analytics-card p-6">
                                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                                                Status Analysis</h3>
                                            <div class="px-4 py-2" style="width: 100%; max-width: 300px; margin: 0 auto;">
                                                <canvas id="statusChart"></canvas>
                                            </div>
                                        </div>

                                        <div class="analytics-card p-6">
                                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                                                Award Fulfillment Trends</h3>
                                            <div class="flex items-center justify-center" style="min-height: 280px;">
                                                <div class="w-full max-w-full" style="height: 280px;">
                                                    <canvas id="trendsChart"></canvas>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <!-- Recent Award Processing Activity -->
                                        <div class="analytics-card p-6">
                                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                                                <span class="material-symbols-outlined text-primary">timeline</span>
                                                Recent Award Processing Activity
                                            </h3>
                                        <div class="space-y-3" id="processing-timeline">
                                            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                                <span class="material-symbols-outlined animate-spin text-base">progress_activity</span>
                                                <span>Loading recent activity…</span>
                                            </div>
                                        </div>
                                        </div>

                                        <!-- Award Momentum Insights -->
                                        <div class="analytics-card p-6">
                                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                                                <span class="material-symbols-outlined text-primary">trending_up</span>
                                                Award Momentum (Top Candidates)
                                            </h3>
                                            <div id="topAwardsMomentum" class="space-y-4 text-sm text-gray-600 dark:text-gray-300">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-10 h-10 rounded-full bg-primary/10 animate-pulse"></div>
                                                    <div>
                                                        <p class="font-medium text-gray-700 dark:text-gray-200">Analyzing submissions…</p>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">Please wait</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Eligible Requirements per Award -->
                                    <div class="analytics-card p-6">
                                        <div class="flex items-center justify-between mb-6">
                                            <div>
                                                <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">Eligible Requirements per Award</h3>
                                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Track progress and eligibility across all awards</p>
                                            </div>
                                        </div>
                                        <div class="overflow-x-auto">
                                            <div class="bg-white/80 dark:bg-gray-900/60 border border-gray-200/70 dark:border-gray-700/60 rounded-2xl shadow-lg backdrop-blur-sm overflow-hidden">
                                                <table class="w-full text-sm">
                                                    <thead>
                                                        <tr class="bg-white/70 dark:bg-gray-900/60 border-b border-gray-200/70 dark:border-gray-700/60">
                                                            <th class="text-left py-4 px-6 font-semibold text-xs uppercase tracking-wider text-gray-600 dark:text-gray-400">Award Name</th>
                                                            <th class="text-center py-4 px-6 font-semibold text-xs uppercase tracking-wider text-gray-600 dark:text-gray-400">Criteria Met / Total</th>
                                                            <th class="text-center py-4 px-6 font-semibold text-xs uppercase tracking-wider text-gray-600 dark:text-gray-400 w-1/5">Progress</th>
                                                            <th class="text-center py-4 px-6 font-semibold text-xs uppercase tracking-wider text-gray-600 dark:text-gray-400">Remarks</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="analyticsTableBody">
                                                    <!-- Dynamic content will be loaded here -->
                                                    <tr id="loadingRow">
                                                        <td colspan="4"
                                                            class="py-8 text-center text-gray-500 dark:text-gray-400">
                                                            <div class="flex items-center justify-center">
                                                                <svg class="animate-spin h-5 w-5 mr-3" fill="none"
                                                                    viewBox="0 0 24 24">
                                                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                                                        stroke="currentColor" stroke-width="4"></circle>
                                                                    <path class="opacity-75" fill="currentColor"
                                                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                                    </path>
                                                                </svg>
                                                                Loading analytics data...
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                </div>

                                <div class="col-span-12 lg:col-span-3">
                                    <div
                                        class="analytics-card p-6 min-h-full border-l-4 border-primary bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-900 content-card">
                                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                                            <span class="material-symbols-outlined text-primary">auto_awesome</span>
                                            Analysis & Recommendations
                                        </h3>
                                        <div id="recommendationsContainer" class="space-y-4">
                                            <!-- Performance Summary -->
                                            <div class="flex items-start gap-3 bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
                                                <span
                                                    class="material-symbols-outlined text-green-600 dark:text-green-400 text-xl pt-1">check_circle</span>
                                                <div>
                                                    <p class="text-sm text-gray-700 dark:text-gray-300 font-medium">
                                                        Your department achieved <span
                                                            class="font-bold text-green-700 dark:text-green-400">80%</span>
                                                        of award qualifications this cycle.
                                                    </p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                        This is <strong>12% above</strong> the institutional average.
                                                    </p>
                                                </div>
                                            </div>

                                            <!-- Priority Action Item -->
                                            <div
                                                class="flex items-start gap-3 border-t border-gray-200 dark:border-gray-600 pt-4 bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
                                                <span
                                                    class="material-symbols-outlined text-yellow-600 dark:text-yellow-400 text-xl pt-1">priority_high</span>
                                                <div>
                                                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Priority Action</p>
                                                    <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">
                                                        Complete missing supporting documents for the <strong>Innovation Award</strong>. You're 1-2 documents away from eligibility.
                                                    </p>
                                                    <div class="mt-2 flex gap-2">
                                                        <span class="px-2 py-1 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-200 text-xs rounded-full">High Impact</span>
                                                        <span class="px-2 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200 text-xs rounded-full">Quick Win</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Strength Analysis -->
                                            <div
                                                class="flex items-start gap-3 border-t border-gray-200 dark:border-gray-600 pt-4 bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
                                                <span
                                                    class="material-symbols-outlined text-blue-600 dark:text-blue-400 text-xl pt-1">trending_up</span>
                                                <div>
                                                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Strength Identified</p>
                                                    <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">
                                                        Exceptional performance on <span class="font-semibold">leadership-based awards</span> (95% success rate).
                                                    </p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                        💡 Leverage this strength to pursue the <strong>International Leadership Award</strong>.
                                                    </p>
                                                </div>
                                            </div>

                                            <!-- Collaboration Opportunity -->
                                            <div
                                                class="flex items-start gap-3 border-t border-gray-200 dark:border-gray-600 pt-4 bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
                                                <span
                                                    class="material-symbols-outlined text-purple-600 dark:text-purple-400 text-xl pt-1">groups</span>
                                                <div>
                                                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Collaboration Opportunity</p>
                                                    <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">
                                                        Partner with <strong>Engineering department</strong> to meet international partnership criteria.
                                                    </p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                        They have 3 active international projects that align with your goals.
                                                    </p>
                                                </div>
                                            </div>

                                            <!-- OCR Insight -->
                                            <div
                                                class="flex items-start gap-3 border-t border-gray-200 dark:border-gray-600 pt-4 bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
                                                <span
                                                    class="material-symbols-outlined text-indigo-600 dark:text-indigo-400 text-xl pt-1">psychology</span>
                                                <div>
                                                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Document Quality Insight</p>
                                                    <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">
                                                        Recent submissions show <strong>94.2% OCR accuracy</strong>. Consider scanning documents at 300 DPI or higher for optimal results.
                                                    </p>
                                                    <div class="mt-2 flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                                                        <span class="material-symbols-outlined text-sm">science</span>
                                                        <span>AI confidence score improved by 8% this month</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Timeline Prediction -->
                                            <div
                                                class="flex items-start gap-3 border-t border-gray-200 dark:border-gray-600 pt-4 bg-white dark:bg-gray-800 p-3 rounded-lg shadow-sm">
                                                <span
                                                    class="material-symbols-outlined text-emerald-600 dark:text-emerald-400 text-xl pt-1">schedule</span>
                                                <div>
                                                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Timeline Prediction</p>
                                                    <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">
                                                        Based on current progress, you're on track to qualify for <strong>4 additional awards</strong> by end of semester.
                                                    </p>
                                                    <div class="mt-2 w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                                                        <div class="bg-emerald-500 h-1.5 rounded-full" style="width: 67%"></div>
                                                    </div>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">67% to next milestone</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- Modal for KPI Details -->
                        <div id="kpiModal"
                            class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-start justify-center p-4 overflow-y-auto">
                            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full mt-16 mb-8">
                                <div
                                    class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
                                    <h2 id="modalTitle" class="text-xl font-bold text-gray-900 dark:text-gray-100"></h2>
                                    <button id="modalCloseButton" onclick="closeModal()"
                                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                        <span class="material-symbols-outlined">close</span>
                                    </button>
                                </div>
                                <div id="modalContent" class="p-6">
                                    <!-- Dynamic content will be inserted here -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Award List Tab Content -->
                    <div id="award-list-content" class="tab-content hidden">

                        <!-- Award Criteria Management Section - Available to all authenticated users -->
                        <div class="mt-8 mb-6">
                            <div class="bg-card-light dark:bg-card-dark border border-border-light dark:border-border-dark rounded-lg p-6">
                                <div class="flex items-center justify-between mb-6">
                                    <div>
                                        <h2 class="text-2xl font-bold text-text-light dark:text-text-dark">Award Criteria Management</h2>
                                        <p class="text-sm text-text-muted-light dark:text-text-muted-dark mt-1">Define and manage award categories, criteria, and requirements</p>
                                    </div>
                                    <button onclick="showAddCriteriaModal()" class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors">
                                        <span class="material-symbols-outlined text-lg mr-2">add</span>
                                        Add Award Criteria
                                    </button>
                                </div>
                                <div id="criteriaListContainer" class="space-y-4">
                                    <!-- Criteria cards will be loaded here -->
                                </div>
                            </div>
                        </div>

                        <!-- Award List Filters -->
                        <div
                            class="mt-8 bg-card-light dark:bg-card-dark border border-border-light dark:border-border-dark rounded-lg p-4 mb-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 items-center">
                                <div class="relative lg:col-span-2">
                                    <span
                                        class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-text-muted-light dark:text-text-muted-dark text-lg">search</span>
                                    <input id="searchInput"
                                        class="w-full pl-10 pr-4 py-2 text-sm rounded-lg border border-border-light dark:border-border-dark bg-card-light dark:bg-card-dark text-text-light dark:text-text-dark focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all"
                                        placeholder="Search document title..." type="text"
                                        oninput="filterAwardCriteriaList()" />
                                </div>
                                <select id="categoryFilter"
                                    class="w-full py-2 px-3 text-sm rounded-lg border border-border-light dark:border-border-dark bg-card-light dark:bg-card-dark text-text-muted-light dark:text-text-muted-dark focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all"
                                    onchange="filterAwardCriteriaList()">
                                    <option value="">Award Category</option>
                                    <option value="Global Citizenship Award">Global Citizenship Award</option>
                                    <option value="Outstanding International Education Program Award">Outstanding
                                        International Education Program Award</option>
                                    <option value="Sustainability Award">Sustainability Award</option>
                                    <option value="Best ASEAN Awareness Initiative Award">Best ASEAN Awareness
                                        Initiative Award</option>
                                    <option value="Emerging Leadership Award">Emerging Leadership Award</option>
                                    <option value="Internationalization Leadership Award">Internationalization
                                        Leadership Award</option>
                                    <option value="Best CHED Regional Office for Internationalization Award">Best CHED
                                        Regional Office for Internationalization Award</option>
                                    <option value="Most Promising Regional IRO Community Award">Most Promising Regional
                                        IRO Community Award</option>
                                </select>
                                <input id="dateFilter"
                                    class="w-full py-2 px-3 text-sm rounded-lg border border-border-light dark:border-border-dark bg-card-light dark:bg-card-dark text-text-muted-light dark:text-text-muted-dark focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all"
                                    onblur="(this.type='text'); filterAwardCriteriaList()" onfocus="(this.type='date')"
                                    placeholder="Date Range" type="text" onchange="filterAwardCriteriaList()" />
                                <select id="confidenceFilter"
                                    class="w-full py-2 px-3 text-sm rounded-lg border border-border-light dark:border-border-dark bg-card-light dark:bg-card-dark text-text-muted-light dark:text-text-muted-dark focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all"
                                    onchange="filterAwardCriteriaList()">
                                    <option value="">Match Confidence</option>
                                    <option value="90-100">&gt;90%</option>
                                    <option value="75-90">75%-90%</option>
                                    <option value="60-75">60%-75%</option>
                                    <option value="0-60">&lt;60%</option>
                                </select>
                                <button id="clearFiltersBtn"
                                    class="px-2 py-2 text-sm font-medium text-primary hover:bg-primary/10 rounded-lg border border-primary/20 hover:border-primary transition-all whitespace-nowrap"
                                    onclick="clearAwardCriteriaFilters()">Clear Filters</button>
                            </div>
                        </div>
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <span
                                    class="inline-flex items-center gap-2 py-1.5 px-3 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900/40 text-primary dark:text-blue-400">Total
                                    Processed: <span id="total-processed-count" class="font-bold">0</span></span>
                                <span
                                    class="inline-flex items-center gap-2 py-1.5 px-3 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400">Recognized:
                                    <span id="recognized-count" class="font-bold">0</span></span>
                                <span
                                    class="inline-flex items-center gap-2 py-1.5 px-3 rounded-full text-xs font-medium bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-400">Pending
                                    Review: <span id="pending-count" class="font-bold">0</span></span>
                            </div>
                        </div>

                        <!-- Bulk Actions Section -->
                        <div id="bulk-actions"
                            class="hidden mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span id="selected-count"
                                        class="text-sm font-medium text-blue-700 dark:text-blue-300">0 selected</span>
                                </div>
                                <button id="bulk-delete-btn"
                                    class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white text-sm font-medium rounded-lg transition-colors"
                                    onclick="bulkDeleteSelected()">
                                    <span class="material-symbols-outlined text-sm mr-1">delete</span>
                                    Delete Selected
                                </button>
                            </div>
                        </div>

                        <div
                            class="bg-card-light dark:bg-card-dark border border-border-light dark:border-border-dark rounded-lg overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm text-left">
                                    <thead
                                        class="bg-gray-50 dark:bg-gray-800 text-xs text-text-muted-light dark:text-text-muted-dark uppercase tracking-wider">
                                        <tr>
                                            <th class="px-6 py-3 font-medium">Award Name</th>
                                            <th class="px-6 py-3 font-medium text-center">Type</th>
                                            <th class="px-6 py-3 font-medium text-center">Requirements</th>
                                            <th class="px-6 py-3 font-medium text-center">Total Applicants</th>
                                            <th class="px-6 py-3 font-medium text-center">Pending</th>
                                            <th class="px-6 py-3 font-medium text-center">Recognized</th>
                                            <th class="px-6 py-3 font-medium text-center">Processed</th>
                                        </tr>
                                    </thead>
                                    <tbody id="award-criteria-tbody" class="divide-y divide-gray-100 dark:divide-gray-700">
                                        <tr id="criteria-loading-row">
                                            <td colspan="7" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                                <div class="flex items-center justify-center gap-2">
                                                    <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    Loading award criteria...
                                                </div>
                                            </td>
                                        </tr>
                                        <!-- Dynamic content will be loaded here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="flex items-center justify-center mt-6">
                            <nav class="flex items-center gap-2">
                                <a class="p-2 rounded-full hover:bg-primary/10 text-text-muted-light dark:text-text-muted-dark"
                                    href="#">
                                    <span class="material-symbols-outlined text-base">chevron_left</span>
                                </a>
                                <a class="w-8 h-8 flex items-center justify-center rounded-full bg-primary text-white font-semibold text-sm"
                                    href="#">1</a>
                                <a class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-primary/10 text-text-muted-light dark:text-text-muted-dark font-medium text-sm"
                                    href="#">2</a>
                                <a class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-primary/10 text-text-muted-light dark:text-text-muted-dark font-medium text-sm"
                                    href="#">3</a>
                                <span class="text-text-muted-light dark:text-text-muted-dark">...</span>
                                <a class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-primary/10 text-text-muted-light dark:text-text-muted-dark font-medium text-sm"
                                    href="#">10</a>
                                <a class="p-2 rounded-full hover:bg-primary/10 text-text-muted-light dark:text-text-muted-dark"
                                    href="#">
                                    <span class="material-symbols-outlined text-base">chevron_right</span>
                                </a>
                            </nav>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all duration-300 scale-95 opacity-0"
                id="deleteModalContent">
                <div class="p-6">
                    <!-- Modal Header -->
                    <div
                        class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 dark:bg-red-900/20 rounded-full">
                        <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 19.5c-.77.833.192 2.5 1.732 2.5z">
                            </path>
                        </svg>
                    </div>

                    <!-- Modal Title -->
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white text-center mb-2">
                        Delete Award
                    </h3>

                    <!-- Modal Message -->
                    <p class="text-sm text-gray-600 dark:text-gray-300 text-center mb-6">
                        Are you sure you want to delete this award? This action cannot be undone and will permanently
                        remove the award and its associated files.
                    </p>

                    <!-- Modal Actions -->
                    <div class="flex gap-3 justify-center">
                        <button id="deleteCancelBtn"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors duration-200">
                            Cancel
                        </button>
                        <button id="deleteConfirmBtn"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors duration-200">
                            Delete Award
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Delete Confirmation Modal -->
    <div id="bulkDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all duration-300 scale-95 opacity-0"
                id="bulkDeleteModalContent">
                <div class="p-6">
                    <!-- Modal Header -->
                    <div
                        class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 dark:bg-red-900/20 rounded-full">
                        <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 19.5c-.77.833.192 2.5 1.732 2.5z">
                            </path>
                        </svg>
                    </div>

                    <!-- Modal Title -->
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white text-center mb-2">
                        Delete Selected Awards
                    </h3>

                    <!-- Modal Message -->
                    <p class="text-sm text-gray-600 dark:text-gray-300 text-center mb-6" id="bulkDeleteMessage">
                        Are you sure you want to delete the selected awards? This action cannot be undone and will
                        permanently remove the awards and their associated files.
                    </p>

                    <!-- Modal Actions -->
                    <div class="flex gap-3 justify-center">
                        <button id="bulkDeleteCancelBtn"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors duration-200">
                            Cancel
                        </button>
                        <button id="bulkDeleteConfirmBtn"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors duration-200">
                            Delete Awards
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Re-analyze Confirmation Modal -->
    <div id="reanalyzeModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all duration-300 scale-95 opacity-0"
                id="reanalyzeModalContent">
                <div class="p-6">
                    <!-- Modal Header -->
                    <div
                        class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-blue-100 dark:bg-blue-900/20 rounded-full">
                        <svg class="w-6 h-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                            </path>
                        </svg>
                    </div>

                    <!-- Modal Title -->
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white text-center mb-2">
                        Re-analyze Award
                    </h3>

                    <!-- Modal Message -->
                    <p class="text-sm text-gray-600 dark:text-gray-300 text-center mb-6">
                        Are you sure you want to re-analyze this award? This will update the analysis results using the
                        latest criteria and may change the award category or confidence score.
                    </p>

                    <!-- Modal Actions -->
                    <div class="flex gap-3 justify-center">
                        <button id="reanalyzeCancelBtn"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors duration-200">
                            Cancel
                        </button>
                        <button id="reanalyzeConfirmBtn"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors duration-200">
                            Re-analyze Award
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Award Details Modal -->
    <div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto transform transition-all duration-300 scale-95 opacity-0"
                id="viewModalContent">
                <div class="p-6">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 bg-blue-100 dark:bg-blue-900/20 rounded-full">
                                <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                    </path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                                Award Analysis Details
                            </h3>
                        </div>
                        <button id="viewCloseBtn"
                            class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                            <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Content -->
                    <div id="viewModalBody" class="space-y-6">
                        <!-- Loading state -->
                        <div id="viewLoading" class="flex items-center justify-center py-8">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                            <span class="ml-3 text-gray-600 dark:text-gray-400">Loading award details...</span>
                        </div>

                        <!-- Error state -->
                        <div id="viewError" class="hidden text-center py-8">
                            <div
                                class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 dark:bg-red-900/20 rounded-full">
                                <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Error Loading Details
                            </h4>
                            <p class="text-gray-600 dark:text-gray-400">Failed to load award analysis details.</p>
                        </div>

                        <!-- Content will be populated here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Eligible Awards Modal -->
    <div id="eligibleAwardsModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[80vh] overflow-y-auto transform transition-all duration-300 scale-95 opacity-0"
                id="eligibleAwardsModalContent">
                <div class="p-6">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 bg-green-100 dark:bg-green-900/20 rounded-full">
                                <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                                Eligible Awards
                            </h3>
                        </div>
                        <button id="eligibleAwardsCloseBtn"
                            class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                            <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Content -->
                    <div id="eligibleAwardsModalBody" class="space-y-4">
                        <!-- Content will be populated here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Description Modal -->
    <div id="descriptionModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[80vh] overflow-y-auto transform transition-all duration-300 scale-95 opacity-0"
                id="descriptionModalContent">
                <div class="p-6">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 bg-blue-100 dark:bg-blue-900/20 rounded-full">
                                <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                    </path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                                Award Description
                            </h3>
                        </div>
                        <button id="descriptionCloseBtn"
                            class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                            <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Content -->
                    <div id="descriptionModalBody" class="space-y-4">
                        <!-- Content will be populated here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Admin Panel Tab Content -->
    <?php if ($isAdmin): ?>
        <div id="admin-panel-content" class="tab-content hidden">
            <div class="mt-8 space-y-6">
                <div
                    class="bg-card-light dark:bg-card-dark border border-border-light dark:border-border-dark rounded-lg p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-text-light dark:text-text-dark">Award Criteria Management
                            </h2>
                            <p class="text-sm text-text-muted-light dark:text-text-muted-dark mt-1">Define award categories,
                                criteria, and requirements</p>
                        </div>
                        <button onclick="showAddCriteriaModal()"
                            class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors">
                            <span class="material-symbols-outlined text-lg mr-2">add</span>
                            Add Award Criteria
                        </button>
                    </div>

                    <div id="criteriaListContainer" class="space-y-4">
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

        <div id="addCriteriaModal"
            class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto transform transition-all duration-300 scale-95 opacity-0"
                id="addCriteriaModalContent">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white" id="criteriaModalTitle">Add Award Criteria</h3>
                        <button onclick="hideAddCriteriaModal()"
                            class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>

                    <form id="addCriteriaForm" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Award
                                    Category Name</label>
                                <input type="text" name="category_name" required
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-primary"
                                    placeholder="e.g., Global Citizenship Award">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Award
                                    Type</label>
                                <select name="award_type" required
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-primary">
                                    <option value="Individual">Individual</option>
                                    <option value="Institutional">Institutional</option>
                                    <option value="Regional">Regional</option>
                                </select>
                            </div>

                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label>
                                <select name="status" required
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-primary">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>

                            <div class="md:col-span-2">
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description</label>
                                <textarea name="description" rows="3" required
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-primary"
                                    placeholder="Describe the award..."></textarea>
                            </div>

                            <div class="md:col-span-2">
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Requirements/Criteria
                                    (one per line)</label>
                                <textarea name="requirements" rows="6" required
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-primary font-mono text-sm"
                                    placeholder="Must have international collaboration programs&#10;Demonstrated community service activities&#10;Participation in at least 3 international events"></textarea>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Each line will be treated as a
                                    separate criterion</p>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Keywords
                                    (comma-separated)</label>
                                <input type="text" name="keywords"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-primary"
                                    placeholder="global, citizenship, community, international">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">These keywords will be used for
                                    document analysis matching</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Minimum Match
                                    Percentage</label>
                                <input type="number" name="min_match_percentage" min="0" max="100" value="60" required
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-primary">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Minimum % of criteria that must be
                                    met</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Weight (for
                                    scoring)</label>
                                <input type="number" name="weight" min="1" max="10" value="5" required
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary focus:border-primary">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Higher weight = more important
                                    (1-10)</p>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button type="button" onclick="hideAddCriteriaModal()"
                                class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                                Cancel
                            </button>
                            <button type="submit" id="criteriaSubmitBtn"
                                class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors flex items-center">
                                <span class="material-symbols-outlined text-lg mr-2">save</span>
                                <span id="criteriaSubmitText">Save Criteria</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <script>
        // Notification function - defined early so it's available everywhere
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm transform transition-all duration-300 translate-x-full`;

            // Set colors based on type
            const colors = {
                success: 'bg-green-500 dark:bg-green-600 text-white',
                error: 'bg-red-500 dark:bg-red-600 text-white',
                warning: 'bg-yellow-500 dark:bg-yellow-600 text-white',
                info: 'bg-blue-500 dark:bg-blue-600 text-white'
            };

            notification.className += ` ${colors[type] || colors.info}`;
            notification.innerHTML = `
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">${type === 'success' ? 'check_circle' : type === 'error' ? 'error' : 'info'}</span>
                    <span class="text-sm font-medium">${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-white hover:text-gray-200">
                        <span class="material-symbols-outlined text-sm">close</span>
                    </button>
                </div>
            `;

            document.body.appendChild(notification);

            // Animate in
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
            }, 100);

            // Auto remove after 5 seconds
            setTimeout(() => {
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.remove();
                    }
                }, 300);
            }, 5000);
        }

        // Modal functions
        let pendingDeleteId = null;
        let pendingReanalyzeId = null;

        function showDeleteModal(awardId) {
            console.log('showDeleteModal called with awardId:', awardId);
            pendingDeleteId = awardId;
            const modal = document.getElementById('deleteModal');
            const modalContent = document.getElementById('deleteModalContent');

            modal.classList.remove('hidden');

            // Trigger animation
            setTimeout(() => {
                modalContent.classList.remove('scale-95', 'opacity-0');
                modalContent.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function hideDeleteModal() {
            const modal = document.getElementById('deleteModal');
            const modalContent = document.getElementById('deleteModalContent');

            modalContent.classList.remove('scale-100', 'opacity-100');
            modalContent.classList.add('scale-95', 'opacity-0');

            setTimeout(() => {
                modal.classList.add('hidden');
                pendingDeleteId = null;
            }, 300);
        }

        async function performDelete(awardId) {
            console.log('performDelete called with awardId:', awardId);
            try {
                // Extract the actual file ID from the award ID
                // awardId format: "file_analysis_1760327658_68ec77ead5ee9"
                // We need: "68ec77ead5ee9" (the unique part at the end)
                let fileId;
                if (awardId.startsWith('file_')) {
                    // Remove 'file_' prefix and get the last part after the last underscore
                    const withoutPrefix = awardId.replace('file_', '');
                    const parts = withoutPrefix.split('_');
                    fileId = parts[parts.length - 1]; // Get the last part (unique ID)
                } else {
                    fileId = awardId; // Use as-is if not in expected format
                }
                console.log('Extracted fileId:', fileId, 'from awardId:', awardId);

                if (!fileId) {
                    throw new Error('Could not extract file ID from award ID');
                }

                // Find and delete the analysis file
                const deleteUrl = `api/delete-award.php?id=${encodeURIComponent(fileId)}`;
                console.log('Making DELETE request to:', deleteUrl);

                const response = await fetch(deleteUrl, {
                    method: 'DELETE'
                });

                console.log('Delete response status:', response.status);

                if (response.ok) {
                    const result = await response.json();
                    console.log('Delete successful:', result);
                    showNotification('Award deleted successfully!', 'success');
                    // Refresh the awards list
                    await window.loadProcessAwardData();
                } else {
                    const errorData = await response.json();
                    console.error('Delete failed:', errorData);
                    throw new Error(errorData.error || 'Failed to delete award');
                }

            } catch (error) {
                console.error('Delete error:', error);
                showNotification('Failed to delete award: ' + error.message, 'error');
            }
        }

        function showReanalyzeModal(awardId) {
            console.log('showReanalyzeModal called with awardId:', awardId);
            pendingReanalyzeId = awardId;
            const modal = document.getElementById('reanalyzeModal');
            const modalContent = document.getElementById('reanalyzeModalContent');

            modal.classList.remove('hidden');

            // Trigger animation
            setTimeout(() => {
                modalContent.classList.remove('scale-95', 'opacity-0');
                modalContent.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function hideReanalyzeModal() {
            const modal = document.getElementById('reanalyzeModal');
            const modalContent = document.getElementById('reanalyzeModalContent');

            modalContent.classList.remove('scale-100', 'opacity-100');
            modalContent.classList.add('scale-95', 'opacity-0');

            setTimeout(() => {
                modal.classList.add('hidden');
                pendingReanalyzeId = null;
            }, 300);
        }

        async function performReanalyze(awardId) {
            console.log('performReanalyze called with awardId:', awardId);
            try {
                showNotification('Re-analyzing award...', 'info');

                // Get the current award details first (with cache busting)
                const response = await fetch(`api/awards.php?action=detail&id=${awardId}&t=${Date.now()}`);
                if (!response.ok) {
                    throw new Error('Failed to load award details for re-analysis');
                }

                const data = await response.json();
                const award = data.award || {};

                if (!award.file_path) {
                    throw new Error('Original file not found for re-analysis');
                }

                // Create a new FormData for re-analysis
                const formData = new FormData();
                formData.append('award_name', award.title || 'Re-analyzed Award');
                formData.append('description', award.description || '');

                // For re-analysis, we'll simulate file upload with the existing file
                // In a real implementation, you might want to re-extract text from the original file
                formData.append('reanalyze', 'true');
                formData.append('original_file_path', award.file_path);

                // Add cache busting parameters for re-analysis
                formData.append('timestamp', Date.now().toString());
                formData.append('cache_bust', Math.random().toString(36).substring(7));

                console.log('Making re-analysis request with form data:', {
                    award_name: award.title,
                    description: award.description,
                    reanalyze: 'true',
                    original_file_path: award.file_path
                });

                // Check if the file path exists
                if (!award.file_path) {
                    throw new Error('No file path found in award data');
                }

                console.log('Original file path:', award.file_path);

                const reanalyzeResponse = await fetch('api/analyze-award.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    }
                });

                console.log('Re-analysis response status:', reanalyzeResponse.status);

                if (!reanalyzeResponse.ok) {
                    // Get the error details from the response
                    const errorText = await reanalyzeResponse.text();
                    console.error('Re-analysis error response:', errorText);
                    throw new Error(`Re-analysis failed (${reanalyzeResponse.status}): ${errorText}`);
                }

                const reanalyzeData = await reanalyzeResponse.json();
                console.log('Re-analysis result:', reanalyzeData);

                if (reanalyzeData.success) {
                    showNotification('Award re-analyzed successfully!', 'success');
                    // Refresh the awards list to show updated results
                    await window.loadProcessAwardData();
                } else {
                    // Handle OCR-related errors with better messaging
                    let errorMessage = reanalyzeData.error || 'Re-analysis failed';

                    if (reanalyzeData.error_type === 'OCR_NOT_INSTALLED') {
                        errorMessage = 'Image Processing Unavailable\n\nThis server does not have OCR (Optical Character Recognition) installed. Please convert your image to PDF or DOCX format for text extraction.';

                        if (reanalyzeData.instructions) {
                            errorMessage += '\n\nInstallation Instructions:';
                            reanalyzeData.instructions.forEach(instruction => {
                                errorMessage += '\n• ' + instruction;
                            });
                        }
                    } else if (reanalyzeData.error_type === 'OCR_DISABLED') {
                        errorMessage = 'Image Processing Disabled\n\nPlease convert your image to PDF or DOCX format for text extraction.';
                    }

                    throw new Error(errorMessage);
                }

            } catch (error) {
                console.error('Re-analysis error:', error);
                showNotification('Failed to re-analyze award: ' + error.message, 'error');
            }
        }

        function confirmReanalyze() {
            console.log('Confirm reanalyze called, pendingReanalyzeId:', pendingReanalyzeId);
            if (pendingReanalyzeId) {
                hideReanalyzeModal();
                // Add a small delay to allow modal to close before re-analysis
                setTimeout(() => {
                    performReanalyze(pendingReanalyzeId);
                }, 100);
            }
        }

        function confirmDelete() {
            console.log('Confirm delete called, pendingDeleteId:', pendingDeleteId);
            if (pendingDeleteId) {
                hideDeleteModal();
                // Add a small delay to allow modal to close before deletion
                setTimeout(() => {
                    performDelete(pendingDeleteId);
                }, 100);
            }
        }

        function showViewModal(awardId) {
            console.log('showViewModal called with awardId:', awardId);
            const modal = document.getElementById('viewModal');
            const modalContent = document.getElementById('viewModalContent');

            // Show loading state
            document.getElementById('viewLoading').classList.remove('hidden');
            document.getElementById('viewError').classList.add('hidden');

            modal.classList.remove('hidden');

            // Trigger animation
            setTimeout(() => {
                modalContent.classList.remove('scale-95', 'opacity-0');
                modalContent.classList.add('scale-100', 'opacity-100');
            }, 10);

            // Load award details
            loadAwardDetails(awardId);
        }

        function hideViewModal() {
            const modal = document.getElementById('viewModal');
            const modalContent = document.getElementById('viewModalContent');

            modalContent.classList.remove('scale-100', 'opacity-100');
            modalContent.classList.add('scale-95', 'opacity-0');

            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        async function loadAwardDetails(awardId) {
            try {
                console.log('Loading award details for ID:', awardId);

                // Get award details
                const response = await fetch(`api/awards.php?action=detail&id=${awardId}`);
                if (!response.ok) {
                    throw new Error('Failed to load award details');
                }

                const data = await response.json();
                console.log('Award details loaded:', data);

                // Hide loading and show content
                document.getElementById('viewLoading').classList.add('hidden');
                document.getElementById('viewError').classList.add('hidden');

                // Populate modal content
                populateViewModal(data);

            } catch (error) {
                console.error('Error loading award details:', error);
                document.getElementById('viewLoading').classList.add('hidden');
                document.getElementById('viewError').classList.remove('hidden');
            }
        }

        function populateViewModal(data) {
            const modalBody = document.getElementById('viewModalBody');
            const award = data.award || {};
            const analysis = data.analysis || {};

            // Parse analysis results if available
            let analysisResults = [];
            if (analysis.analysis_results) {
                try {
                    analysisResults = JSON.parse(analysis.analysis_results);
                } catch (e) {
                    console.warn('Failed to parse analysis results:', e);
                }
            }

            modalBody.innerHTML = `
                <!-- Basic Information -->
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Document Information
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Document Title</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">${award.title || 'Untitled'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">File Name</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">${award.file_name || 'Unknown'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">${award.description || 'No description provided'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Submitted By</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">${award.created_by || 'Unknown'}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Date Analyzed</label>
                             <p class="mt-1 text-sm text-gray-900 dark:text-white">${formatDate(award.created_at)}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusBadgeClass(analysis.status || 'Not Analyzed')}">
                                ${analysis.status || 'Not Analyzed'}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Analysis Results -->
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Analysis Results
                    </h4>
                    ${analysisResults.length > 0 ? `
                        <div class="space-y-4">
                            ${analysisResults.map(result => `
                                <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <h5 class="font-medium text-gray-900 dark:text-white">${result.category}</h5>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getScoreBadgeClass(result.score)}">
                                            ${typeof result.score === 'number' && result.score <= 1 ? (result.score * 100).toFixed(1) : Number(result.score).toFixed(1)}%
                                        </span>
                                    </div>
                                    <div class="mb-2">
                                        <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400 mb-1">
                                            <span>Confidence Score</span>
                                            <span>${typeof result.score === 'number' && result.score <= 1 ? (result.score * 100).toFixed(1) : Number(result.score).toFixed(1)}%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                            <div class="bg-primary h-2 rounded-full" style="width: ${Math.min((typeof result.score === 'number' && result.score <= 1 ? (result.score * 100) : Number(result.score)), 100)}%"></div>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusBadgeClass(result.status)}">
                                            ${result.status}
                                        </span>
                                    </div>
                                    ${result.recommendation ? `
                                        <div class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Recommendation</label>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">${result.recommendation}</p>
                                        </div>
                                    ` : ''}
                                    ${result.checklist ? `
                                        <div class="mt-3">
                                            <details class="group">
                                                <summary class="text-sm cursor-pointer select-none text-primary">Checklist (${result.checklist.criteria_met} of ${result.checklist.total_criteria} met)</summary>
                                                <div class="mt-2 space-y-1">
                                                    ${result.checklist.criteria.map(c => `
                                                        <div class=\"flex items-start gap-2 text-sm\">
                                                            <span class=\"mt-0.5\">${c.met ? '✅' : '⚪'}</span>
                                                            <span class=\"text-gray-800 dark:text-gray-200\">${c.text}</span>
                                                        </div>
                                                    `).join('')}
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">${Number(result.checklist.percentage_met).toFixed(2)}% met</div>
                                                </div>
                                            </details>
                                        </div>
                                    ` : ''}
                                </div>
                            `).join('')}
                        </div>
                    ` : `
                        <p class="text-gray-500 dark:text-gray-400 text-center py-4">No analysis results available</p>
                    `}
                </div>

            `;
        }

        function getStatusBadgeClass(status) {
            switch (status.toLowerCase()) {
                case 'eligible': return 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400';
                case 'partially eligible': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400';
                case 'not eligible': return 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400';
                case 'recognized': return 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400';
                case 'pending review': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400';
                default: return 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400';
            }
        }

        function getScoreBadgeClass(score) {
            if (score >= 90) return 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400';
            if (score >= 70) return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400';
            return 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400';
        }

        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            try {
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } catch (error) {
                console.warn('Failed to format date:', dateString, error);
                return dateString;
            }
        }

        // Modern Dropdown Functionality for Award Name
        (function() {
            const awardInput = document.getElementById('award_name');
            const dropdown = document.getElementById('award-name-dropdown');
            const dropdownOptions = dropdown?.querySelectorAll('.dropdown-option');
            
            if (!awardInput || !dropdown || !dropdownOptions) return;
            
            let highlightedIndex = -1;
            const options = Array.from(dropdownOptions);
            let filteredOptions = [...options];
            
            function showDropdown() {
                if (dropdown) {
                    dropdown.classList.remove('hidden');
                }
            }
            
            function hideDropdown() {
                if (dropdown) {
                    dropdown.classList.add('hidden');
                }
                highlightedIndex = -1;
            }
            
            function filterOptions(searchTerm) {
                const term = searchTerm.toLowerCase().trim();
                
                options.forEach(option => {
                    const value = option.getAttribute('data-value').toLowerCase();
                    if (term === '' || value.includes(term)) {
                        option.style.display = '';
                    } else {
                        option.style.display = 'none';
                    }
                });
                
                // Update filtered options to only visible ones
                filteredOptions = options.filter(option => option.style.display !== 'none');
                highlightedIndex = -1;
            }
            
            function highlightOption(index) {
                filteredOptions.forEach((opt, i) => {
                    opt.classList.remove('highlighted');
                });
                
                if (index >= 0 && index < filteredOptions.length) {
                    filteredOptions[index].classList.add('highlighted');
                    filteredOptions[index].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                }
            }
            
            function selectOption(option) {
                const value = option.getAttribute('data-value');
                awardInput.value = value;
                hideDropdown();
                awardInput.focus();
            }
            
            // Show dropdown on focus or when typing
            awardInput.addEventListener('focus', () => {
                filterOptions(awardInput.value);
                showDropdown();
            });
            
            awardInput.addEventListener('input', (e) => {
                filterOptions(e.target.value);
                showDropdown();
            });
            
            // Handle option clicks
            options.forEach(option => {
                option.addEventListener('click', () => {
                    selectOption(option);
                });
                
                option.addEventListener('mouseenter', () => {
                    const index = filteredOptions.indexOf(option);
                    if (index !== -1) {
                        highlightedIndex = index;
                        highlightOption(index);
                    }
                });
            });
            
            // Handle keyboard navigation
            awardInput.addEventListener('keydown', (e) => {
                if (filteredOptions.length === 0) return;
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    highlightedIndex = (highlightedIndex + 1) % filteredOptions.length;
                    highlightOption(highlightedIndex);
                    if (dropdown.classList.contains('hidden')) {
                        showDropdown();
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (highlightedIndex <= 0) {
                        highlightedIndex = filteredOptions.length - 1;
                    } else {
                        highlightedIndex--;
                    }
                    highlightOption(highlightedIndex);
                    if (dropdown.classList.contains('hidden')) {
                        showDropdown();
                    }
                } else if (e.key === 'Enter' && highlightedIndex >= 0 && highlightedIndex < filteredOptions.length) {
                    e.preventDefault();
                    selectOption(filteredOptions[highlightedIndex]);
                } else if (e.key === 'Escape') {
                    hideDropdown();
                }
            });
            
            // Hide dropdown when clicking outside
            document.addEventListener('click', (e) => {
                const inputContainer = awardInput.closest('label');
                if (inputContainer && !inputContainer.contains(e.target)) {
                    hideDropdown();
                }
            });
        })();

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
            // Function to toggle sidebar
            const toggleSidebar = () => {
                const isCollapsed = appContainer.classList.contains('sidebar-collapsed');
                if (isCollapsed) {
                    // Expand sidebar
                    appContainer.classList.remove('sidebar-collapsed');
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
                    // Collapse sidebar
                    appContainer.classList.add('sidebar-collapsed');
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
                }
                
                // Force a reflow to ensure layout updates properly
                void appContainer.offsetHeight;
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
                // Re-render charts on theme change with existing data
                if (window.analyticsData) {
                    // Update Status Chart with current data
                    if (typeof updateStatusChart === 'function') {
                        if (window.analyticsData.statusDistribution && window.analyticsData.statusDistribution.length > 0) {
                            const statusLabels = window.analyticsData.statusDistribution.map(s => s.eligibility_status);
                            const statusCounts = window.analyticsData.statusDistribution.map(s => parseInt(s.count));
                            
                            const statusChartData = {
                                fully_met: statusCounts[statusLabels.indexOf('Eligible')] || 0,
                                partially_met: statusCounts[statusLabels.indexOf('Almost Eligible')] || 0,
                                under_review: 0,
                                unqualified: statusCounts[statusLabels.indexOf('Not Eligible')] || 0
                            };
                            
                            const hasUploads = statusCounts.reduce((a, b) => a + b, 0) > 0;
                            updateStatusChart(statusChartData, hasUploads);
                        } else {
                            // No data - show empty state
                            updateStatusChart({ fully_met: 0, partially_met: 0, under_review: 0, unqualified: 0 }, false);
                        }
                    }
                    
                    // Update Trends Chart with current data
                    if (typeof updateTrendsChart === 'function' && window.analyticsData.trends) {
                        if (window.analyticsData.trends.length > 0) {
                            const months = window.analyticsData.trends.map(t => {
                                const date = new Date(t.month + '-01');
                                return date.toLocaleDateString('en-US', { month: 'short' });
                            });
                            const eligibleData = window.analyticsData.trends.map(t => parseInt(t.eligible_submissions) || 0);
                            const totalData = window.analyticsData.trends.map(t => parseInt(t.total_submissions) || 0);
                            
                            updateTrendsChart({
                                labels: months,
                                eligible: eligibleData,
                                total: totalData
                            });
                        } else {
                            // Empty state
                            updateTrendsChart({
                                labels: [],
                                eligible: [],
                                total: []
                            });
                        }
                    }
                } else if (window.statusChartInstance || window.trendsChartInstance) {
                    // If charts exist but no analytics data, just re-initialize with placeholder data
                    if (typeof initializeAnalyticsCharts === 'function') {
                        initializeAnalyticsCharts();
                    }
                }
            });
            // Awards cards are now responsive grid - no carousel controls needed

            // CHED requirement tracker state (persisted per user)
            const requirementConfig = window.chedRequirementConfig || {};
            const CHED_REQUIREMENT_STORAGE_KEY = requirementConfig.storageKey || 'chedRequirementTracker';
            const requirementPreferenceKey = requirementConfig.preferenceKey || 'ched_requirement_tracker';
            const requirementCheckboxes = document.querySelectorAll('.ched-requirement');
            const requirementProgressCount = document.getElementById('ched-requirement-progress-count');
            const requirementProgressBar = document.getElementById('ched-requirement-progress-bar');
            const timelineCard = document.getElementById('milestoneTimelineCard');
            const timelineSteps = timelineCard ? timelineCard.querySelectorAll('.timeline-step') : [];
            const refreshTimelineButton = document.getElementById('refreshMilestoneTimeline');

            function loadBackupRequirementState() {
                try {
                    return JSON.parse(localStorage.getItem(CHED_REQUIREMENT_STORAGE_KEY) || '{}') || {};
                } catch (error) {
                    console.warn('Failed to parse CHED requirement tracker state:', error);
                    return {};
                }
            }

            let requirementState = {};
            if (requirementConfig.canPersist && requirementConfig.initialState) {
                requirementState = Object.assign({}, requirementConfig.initialState);
                if (!Object.keys(requirementState).length) {
                    const backupState = loadBackupRequirementState();
                    if (Object.keys(backupState).length) {
                        requirementState = backupState;
                        setTimeout(() => persistRequirementState(), 250);
                    }
                } else {
                    backupRequirementState();
                }
            } else {
                requirementState = loadBackupRequirementState();
            }

            function updateRequirementProgress() {
                if (!requirementCheckboxes.length || !requirementProgressCount || !requirementProgressBar) {
                    return;
                }
                const total = requirementCheckboxes.length;
                const completed = Array.from(requirementCheckboxes).filter(cb => cb.checked).length;
                const percent = Math.round((completed / total) * 100);
                requirementProgressCount.textContent = `${completed}/${total} ready`;
                requirementProgressBar.style.width = `${percent}%`;
                requirementProgressBar.setAttribute('aria-valuenow', percent);
            }

            function setIndicatorState(indicatorEl, state) {
                if (!indicatorEl) return;
                indicatorEl.classList.remove('bg-primary/10', 'text-primary', 'bg-emerald-500', 'bg-amber-500', 'text-white');
                if (state === 'complete') {
                    indicatorEl.classList.add('bg-emerald-500', 'text-white');
                } else if (state === 'partial') {
                    indicatorEl.classList.add('bg-amber-500', 'text-white');
                } else {
                    indicatorEl.classList.add('bg-primary/10', 'text-primary');
                }
            }

            function updateMilestoneTimeline() {
                if (!timelineSteps.length) return;
                timelineSteps.forEach((step) => {
                    const requirementAttr = step.dataset.requirements || '';
                    const requirements = requirementAttr.split(',').map(id => id.trim()).filter(Boolean);
                    const statusEl = step.querySelector('.timeline-status');
                    const indicatorEl = step.querySelector('.timeline-indicator');
                    if (!statusEl) return;

                    let completed = 0;
                    requirements.forEach((id) => {
                        if (requirementState[id]) {
                            completed++;
                        }
                    });
                    const total = requirements.length;

                    let statusText = statusEl.dataset.defaultLabel || 'Pending';
                    let statusClasses = 'timeline-status text-xs font-semibold mt-1 text-primary';
                    if (total > 0) {
                        if (completed === total) {
                            statusText = 'Ready';
                            statusClasses = 'timeline-status text-xs font-semibold mt-1 text-emerald-600 dark:text-emerald-400';
                            setIndicatorState(indicatorEl, 'complete');
                        } else if (completed > 0) {
                            statusText = `In progress — ${completed}/${total} done`;
                            statusClasses = 'timeline-status text-xs font-semibold mt-1 text-amber-600 dark:text-amber-400';
                            setIndicatorState(indicatorEl, 'partial');
                        } else {
                            statusText = statusEl.dataset.defaultLabel || 'Pending';
                            statusClasses = 'timeline-status text-xs font-semibold mt-1 text-primary dark:text-primary-300';
                            setIndicatorState(indicatorEl, 'pending');
                        }
                    } else {
                        setIndicatorState(indicatorEl, 'pending');
                    }

                    statusEl.textContent = statusText;
                    statusEl.className = statusClasses;
                });
            }

            function backupRequirementState() {
                try {
                    localStorage.setItem(CHED_REQUIREMENT_STORAGE_KEY, JSON.stringify(requirementState));
                } catch (storageError) {
                    console.warn('Failed to back up CHED requirement tracker state:', storageError);
                }
            }

            let requirementSaveTimeout = null;
            function persistRequirementState() {
                if (!requirementConfig.canPersist) {
                    return;
                }
                if (requirementSaveTimeout) {
                    clearTimeout(requirementSaveTimeout);
                }
                requirementSaveTimeout = setTimeout(async () => {
                    requirementSaveTimeout = null;
                    try {
                        const response = await fetch('api/user-preferences.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                key: requirementPreferenceKey,
                                value: JSON.stringify(requirementState)
                            })
                        });
                        const result = await response.json();
                        if (!result.success) {
                            console.error('Failed to save CHED requirement tracker state:', result.error);
                            backupRequirementState();
                        }
                    } catch (error) {
                        console.error('Error saving CHED requirement tracker state:', error);
                        backupRequirementState();
                    }
                }, 300);
            }

            requirementCheckboxes.forEach((checkbox) => {
                const id = checkbox.dataset.requirementId;
                if (Object.prototype.hasOwnProperty.call(requirementState, id)) {
                    checkbox.checked = !!requirementState[id];
                }
                checkbox.addEventListener('change', () => {
                    requirementState[id] = checkbox.checked;
                    backupRequirementState();
                    if (requirementConfig.canPersist) {
                        persistRequirementState();
                    }
                    updateRequirementProgress();
                    updateMilestoneTimeline();
                });
            });

            updateRequirementProgress();
            updateMilestoneTimeline();

            if (refreshTimelineButton) {
                refreshTimelineButton.addEventListener('click', () => {
                    updateMilestoneTimeline();
                });
            }

            // Deadline countdown logic
            const deadlineElement = document.getElementById('ched-deadline-countdown');
            const chedDeadline = new Date('2024-10-01T22:00:00+08:00');
            const DAY_MS = 1000 * 60 * 60 * 24;
            const HOUR_MS = 1000 * 60 * 60;
            const MIN_MS = 1000 * 60;

            function renderDeadlineCountdown() {
                if (!deadlineElement || Number.isNaN(chedDeadline.getTime())) return;
                const now = new Date();
                const diff = chedDeadline - now;

                if (diff <= 0) {
                    deadlineElement.textContent = 'Submission window closed — prepare assets for the next ICONS cycle.';
                    deadlineElement.classList.add('text-red-600', 'dark:text-red-400');
                    return;
                }

                const days = Math.floor(diff / DAY_MS);
                const hours = Math.floor((diff % DAY_MS) / HOUR_MS);
                const minutes = Math.floor((diff % HOUR_MS) / MIN_MS);
                deadlineElement.textContent = `${days} day(s), ${hours} hr(s), ${minutes} min(s) remaining`;

                deadlineElement.classList.remove('text-red-600', 'dark:text-red-400', 'text-yellow-600', 'dark:text-yellow-400');
                if (diff <= 3 * DAY_MS) {
                    deadlineElement.classList.add('text-red-600', 'dark:text-red-400');
                } else if (diff <= 14 * DAY_MS) {
                    deadlineElement.classList.add('text-yellow-600', 'dark:text-yellow-400');
                }
            }

            renderDeadlineCountdown();
            if (deadlineElement) {
                setInterval(renderDeadlineCountdown, 60000);
            }

            // Show notification function

            // Award action functions
            async function viewAward(awardId) {
                try {
                    const response = await fetch(`api/awards.php?action=detail&id=${awardId}`);
                    if (!response.ok) {
                        throw new Error('Failed to load award details');
                    }

                    const data = await response.json();
                    const award = data.award || {};
                    const analysis = data.analysis || {};

                    // Create modal for viewing award details
                    const modal = document.createElement('div');
                    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
                    modal.innerHTML = `
                        <div class="bg-white dark:bg-gray-800 rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                            <div class="p-6">
                                <div class="flex justify-between items-start mb-4">
                                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Award Details</h3>
                                    <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                                        <span class="material-symbols-outlined">close</span>
                                    </button>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
                                            <p class="text-gray-900 dark:text-white">${escapeHtml(award.title || 'N/A')}</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">File Name</label>
                                            <p class="text-gray-900 dark:text-white">${escapeHtml(award.file_name || 'N/A')}</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                                            <p class="text-gray-900 dark:text-white">${escapeHtml(award.description || 'N/A')}</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date Analyzed</label>
                                            <p class="text-gray-900 dark:text-white">${award.created_at ? new Date(award.created_at).toLocaleDateString() : 'N/A'}</p>
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Predicted Category</label>
                                            <p class="text-gray-900 dark:text-white font-semibold">${escapeHtml(analysis.predicted_category || 'Not Analyzed')}</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confidence Score</label>
                                            <div class="flex items-center gap-2">
                                                <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                                    <div class="bg-primary h-2 rounded-full" style="width: ${(analysis.confidence * 100) || 0}%"></div>
                                                </div>
                                                <span class="text-sm font-medium">${((analysis.confidence * 100) || 0).toFixed(0)}%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-6">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Extracted Text</label>
                                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg max-h-40 overflow-y-auto">
                                        <p class="text-sm text-gray-900 dark:text-white">${escapeHtml(award.ocr_text || 'No text extracted')}</p>
                                    </div>
                                </div>
                                
                                <div class="mt-6 flex justify-end gap-3">
                                    <button onclick="this.closest('.fixed').remove()" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                                        Close
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;

                    document.body.appendChild(modal);

                } catch (error) {
                    showNotification('Failed to load award details: ' + error.message, 'error');
                }
            }

            function reanalyzeAward(awardId) {
                showReanalyzeModal(awardId);
            }


            function deleteAward(awardId) {
                showDeleteModal(awardId);
            }


            // Helper function to escape HTML
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Global variables for awards data
            let allAwardsData = [];
            let filteredAwardsData = [];

            // Filter functions for awards list
            function filterAwardsList() {
                const searchTerm = document.getElementById('searchInput').value.toLowerCase();
                const categoryFilter = document.getElementById('categoryFilter').value;
                const dateFilter = document.getElementById('dateFilter').value;
                const confidenceFilter = document.getElementById('confidenceFilter').value;

                filteredAwardsData = allAwardsData.filter(award => {
                    // Enhanced search filter - search across multiple fields
                    if (searchTerm) {
                        const searchLower = searchTerm.toLowerCase();
                        const titleMatch = award.title?.toLowerCase().includes(searchLower) || false;
                        const descriptionMatch = award.description?.toLowerCase().includes(searchLower) || false;
                        const fileNameMatch = award.file_name?.toLowerCase().includes(searchLower) || false;
                        const categoryMatch = award.analysis?.predicted_category?.toLowerCase().includes(searchLower) || false;
                        // If no field matches the search term, exclude this award
                        if (!titleMatch && !descriptionMatch && !fileNameMatch && !categoryMatch) {
                            return false;
                        }
                    }

                    // Category filter
                    if (categoryFilter && award.analysis?.predicted_category !== categoryFilter) {
                        return false;
                    }


                    // Date filter
                    if (dateFilter) {
                        const awardDate = new Date(award.created_at);
                        const filterDate = new Date(dateFilter);
                        if (awardDate.toDateString() !== filterDate.toDateString()) {
                            return false;
                        }
                    }

                    // Confidence filter
                    if (confidenceFilter) {
                        const confidence = (award.analysis?.confidence || 0) * 100;
                        switch (confidenceFilter) {
                            case '90-100':
                                if (confidence < 90) return false;
                                break;
                            case '75-90':
                                if (confidence < 75 || confidence >= 90) return false;
                                break;
                            case '60-75':
                                if (confidence < 60 || confidence >= 75) return false;
                                break;
                            case '0-60':
                                if (confidence >= 60) return false;
                                break;
                        }
                    }

                    return true;
                });

                // Re-render the filtered awards
                renderAwardList(filteredAwardsData);

                // Update counters for filtered results
                updateAwardListCounters({
                    total: filteredAwardsData.length,
                    recognized: filteredAwardsData.filter(a => (a.analysis?.confidence || 0) >= 0.8).length,
                    pending: filteredAwardsData.filter(a => (a.analysis?.confidence || 0) < 0.8).length
                });

                // Show search results feedback
                if (searchTerm) {
                    const searchResultsCount = filteredAwardsData.length;
                    const totalCount = allAwardsData.length;

                    if (searchResultsCount === 0) {
                        showSearchFeedback(`No awards found for "${searchTerm}"`, 'warning');
                    } else if (searchResultsCount === 1) {
                        showSearchFeedback(`Found 1 award matching "${searchTerm}"`, 'success');
                    } else {
                        showSearchFeedback(`Found ${searchResultsCount} awards matching "${searchTerm}"`, 'info');
                    }
                } else {
                    hideSearchFeedback();
                }
            }

            function clearFilters() {
                document.getElementById('searchInput').value = '';
                document.getElementById('categoryFilter').value = '';
                document.getElementById('dateFilter').value = '';
                document.getElementById('confidenceFilter').value = '';

                // Show all awards
                filteredAwardsData = [...allAwardsData];
                renderAwardList(filteredAwardsData);

                // Update counters with original data
                updateAwardListCounters({
                    total: allAwardsData.length,
                    recognized: allAwardsData.filter(a => (a.analysis?.confidence || 0) >= 0.8).length,
                    pending: allAwardsData.filter(a => (a.analysis?.confidence || 0) < 0.8).length
                });

                showNotification('Filters cleared', 'info');

                // Hide search feedback
                hideSearchFeedback();
            }

            // Search feedback functions
            function showSearchFeedback(message, type = 'info') {
                // Remove existing feedback if any
                hideSearchFeedback();

                // Create feedback element
                const feedback = document.createElement('div');
                feedback.id = 'searchFeedback';
                feedback.className = `mt-2 p-3 rounded-lg text-sm font-medium ${type === 'success' ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400' :
                        type === 'warning' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400' :
                            'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400'
                    }`;
                feedback.textContent = message;

                // Insert after the filter section
                const filterSection = document.querySelector('#award-list-content .grid');
                if (filterSection) {
                    filterSection.parentNode.insertBefore(feedback, filterSection.nextSibling);
                }
            }

            function hideSearchFeedback() {
                const existingFeedback = document.getElementById('searchFeedback');
                if (existingFeedback) {
                    existingFeedback.remove();
                }
            }

            // Eligible Awards Modal Functions
            function showEligibleAwards(awardId) {
                console.log('showEligibleAwards called with awardId:', awardId);

                // Find the award data
                const award = allAwardsData.find(a => a.id === awardId);
                if (!award) {
                    showNotification('Award not found', 'error');
                    return;
                }

                // Get the analysis data
                const analysis = award.analysis;
                if (!analysis) {
                    showNotification('Analysis data not found', 'error');
                    return;
                }

                // Parse analysis results
                let analysisResults = [];
                try {
                    analysisResults = analysis.analysis_results ? JSON.parse(analysis.analysis_results) : [];
                } catch (e) {
                    console.warn('Failed to parse analysis results:', e);
                    showNotification('Failed to parse analysis results', 'error');
                    return;
                }

                // Filter eligible awards
                const eligibleAwards = analysisResults.filter(result =>
                    result.status === 'Eligible' || result.status === 'Partially Eligible'
                );

                if (eligibleAwards.length === 0) {
                    showNotification('No eligible awards found', 'warning');
                    return;
                }

                // Show the modal
                const modal = document.getElementById('eligibleAwardsModal');
                const modalContent = document.getElementById('eligibleAwardsModalContent');
                const modalBody = document.getElementById('eligibleAwardsModalBody');

                // Populate modal content
                modalBody.innerHTML = eligibleAwards.map(award => `
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">${escapeHtml(award.category)}</h4>
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusBadgeClass(award.status)}">
                                        ${award.status}
                                    </span>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">${award.type || 'Institutional'}</span>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold text-gray-900 dark:text-white">${Math.round(award.score || 0)}%</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Confidence</div>
                            </div>
                        </div>
                        
                        ${award.matched_criteria && award.matched_criteria.length > 0 ? `
                            <div class="mb-3">
                                <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Matched Criteria:</h5>
                                <div class="flex flex-wrap gap-1">
                                    ${award.matched_criteria.map(criteria => `
                                        <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400">
                                            ${escapeHtml(criteria)}
                                        </span>
                                    `).join('')}
                                </div>
                            </div>
                        ` : ''}
                        
                        ${award.recommendation ? `
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                <strong>Recommendation:</strong> ${escapeHtml(award.recommendation)}
                            </div>
                        ` : ''}
                    </div>
                `).join('');

                // Show modal with animation
                modal.classList.remove('hidden');
                setTimeout(() => {
                    modalContent.classList.remove('scale-95', 'opacity-0');
                    modalContent.classList.add('scale-100', 'opacity-100');
                }, 10);
            }

            function hideEligibleAwardsModal() {
                const modal = document.getElementById('eligibleAwardsModal');
                const modalContent = document.getElementById('eligibleAwardsModalContent');

                modalContent.classList.remove('scale-100', 'opacity-100');
                modalContent.classList.add('scale-95', 'opacity-0');

                setTimeout(() => {
                    modal.classList.add('hidden');
                }, 300);
            }

            // Description Modal Functions
            function showDescriptionModal(awardId) {
                console.log('showDescriptionModal called with awardId:', awardId);

                // Find the award data
                const award = allAwardsData.find(a => a.id === awardId);
                if (!award) {
                    showNotification('Award not found', 'error');
                    return;
                }

                // Get the description
                const description = award.description || 'No description provided';

                // Show the modal
                const modal = document.getElementById('descriptionModal');
                const modalContent = document.getElementById('descriptionModalContent');
                const modalBody = document.getElementById('descriptionModalBody');

                // Populate modal content
                modalBody.innerHTML = `
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                        <div class="mb-3">
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Document Title:</h4>
                            <p class="text-gray-900 dark:text-white font-medium">${escapeHtml(award.title || 'Untitled')}</p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description:</h4>
                            <p class="text-gray-900 dark:text-white leading-relaxed">${escapeHtml(description)}</p>
                        </div>
                    </div>
                `;

                // Show modal with animation
                modal.classList.remove('hidden');
                setTimeout(() => {
                    modalContent.classList.remove('scale-95', 'opacity-0');
                    modalContent.classList.add('scale-100', 'opacity-100');
                }, 10);
            }

            function hideDescriptionModal() {
                const modal = document.getElementById('descriptionModal');
                const modalContent = document.getElementById('descriptionModalContent');

                modalContent.classList.remove('scale-100', 'opacity-100');
                modalContent.classList.add('scale-95', 'opacity-0');

                setTimeout(() => {
                    modal.classList.add('hidden');
                }, 300);
            }

            // Export functions
            function exportToCsv() {
                if (filteredAwardsData.length === 0) {
                    showNotification('No data to export', 'warning');
                    return;
                }

                try {
                    // Create CSV header
                    const headers = ['Title', 'Award Category', 'Confidence Score', 'Status', 'Description', 'Date Analyzed', 'File Name'];
                    const csvContent = [
                        headers.join(','),
                        ...filteredAwardsData.map(award => [
                            `"${(award.title || '').replace(/"/g, '""')}"`,
                            `"${(award.analysis?.predicted_category || 'Not Analyzed').replace(/"/g, '""')}"`,
                            `"${((award.analysis?.confidence || 0) * 100).toFixed(0)}%"`,
                            `"${getAwardStatus((award.analysis?.confidence || 0) * 100)}"`,
                            `"${(award.description || 'No description provided').replace(/"/g, '""')}"`,
                            `"${award.created_at ? new Date(award.created_at).toLocaleDateString() : 'N/A'}"`,
                            `"${(award.file_name || '').replace(/"/g, '""')}"`
                        ].join(','))
                    ].join('\n');

                    // Create and download file
                    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    const url = URL.createObjectURL(blob);
                    link.setAttribute('href', url);
                    link.setAttribute('download', `awards_export_${new Date().toISOString().split('T')[0]}.csv`);
                    link.style.visibility = 'hidden';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);

                    showNotification('CSV exported successfully!', 'success');
                } catch (error) {
                    showNotification('Failed to export CSV: ' + error.message, 'error');
                }
            }

            function exportToPdf() {
                if (filteredAwardsData.length === 0) {
                    showNotification('No data to export', 'warning');
                    return;
                }

                try {
                    // Use html2canvas and jsPDF (already included)
                    const table = document.querySelector('#award-list-content table');
                    if (!table) {
                        showNotification('Table not found for PDF export', 'error');
                        return;
                    }

                    showNotification('Generating PDF...', 'info');

                    html2canvas(table).then(canvas => {
                        const imgData = canvas.toDataURL('image/png');
                        const pdf = new jsPDF('l', 'mm', 'a4');
                        const imgWidth = 297; // A4 width in mm
                        const pageHeight = 210; // A4 height in mm
                        const imgHeight = (canvas.height * imgWidth) / canvas.width;
                        let heightLeft = imgHeight;

                        let position = 0;

                        pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                        heightLeft -= pageHeight;

                        while (heightLeft >= 0) {
                            position = heightLeft - imgHeight;
                            pdf.addPage();
                            pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                            heightLeft -= pageHeight;
                        }

                        pdf.save(`awards_export_${new Date().toISOString().split('T')[0]}.pdf`);
                        showNotification('PDF exported successfully!', 'success');
                    }).catch(error => {
                        showNotification('Failed to generate PDF: ' + error.message, 'error');
                    });
                } catch (error) {
                    showNotification('Failed to export PDF: ' + error.message, 'error');
                }
            }

            // Load Process Award tab data from database (make it global)
            window.loadProcessAwardData = async function () {
                try {
                    // Load awards with their analysis data
                    const response = await fetch('api/awards.php?action=list');
                    if (!response.ok) {
                        console.error('Failed to load award data');
                        return;
                    }

                    const awards = await response.json();

                    // Load analysis data for each award
                    const awardsWithAnalysis = await Promise.all(awards.map(async (award) => {
                        try {
                            const analysisResponse = await fetch(`api/awards.php?action=detail&id=${award.id}`);
                            if (analysisResponse.ok) {
                                const detailData = await analysisResponse.json();
                                return { ...award, analysis: detailData.analysis };
                            }
                        } catch (error) {
                            console.warn(`Failed to load analysis for award ${award.id}:`, error);
                        }
                        return award;
                    }));

                    // Store in global variables
                    allAwardsData = awardsWithAnalysis;
                    filteredAwardsData = [...awardsWithAnalysis];

                    renderAwardList(filteredAwardsData);

                    // Update counters with calculated stats
                    const calculatedStats = {
                        total: awardsWithAnalysis.length,
                        recognized: awardsWithAnalysis.filter(a => (a.analysis?.confidence || 0) >= 0.8).length,
                        pending: awardsWithAnalysis.filter(a => (a.analysis?.confidence || 0) < 0.8).length
                    };
                    updateAwardListCounters(calculatedStats);

                } catch (error) {
                    console.error('Error loading award data:', error);
                }
            };

            // Update award list counters
            function updateAwardListCounters(stats) {
                const totalElement = document.getElementById('total-processed-count');
                const recognizedElement = document.getElementById('recognized-count');
                const pendingElement = document.getElementById('pending-count');

                if (totalElement) {
                    totalElement.textContent = stats.total || 0;
                }
                if (recognizedElement) {
                    recognizedElement.textContent = stats.recognized || 0;
                }
                if (pendingElement) {
                    pendingElement.textContent = stats.pending || 0;
                }
            }

            // Global variable to store user submissions
            let allUserSubmissions = [];

            // Load award list data (conditional for admin vs user) - make it globally accessible
            window.loadAwardListData = async function() {
                const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
                console.log('loadAwardListData called, isAdmin:', isAdmin);
                console.log('PHP isAdmin value:', <?php echo $isAdmin ? 'true' : 'false'; ?>);
                console.log('User role from session:', '<?php echo htmlspecialchars($user['role'] ?? 'unknown'); ?>');

                try {
                    // Load award criteria for all users (for the criteria management section)
                    const criteriaResponse = await fetch('api/award-criteria.php?action=list', {
                        headers: {
                            'Authorization': 'Bearer ' + AUTH_TOKEN
                        }
                    });
                    
                    let criteriaData = null;
                    if (criteriaResponse.ok) {
                        const criteriaResult = await criteriaResponse.json();
                        if (criteriaResult.success && criteriaResult.criteria) {
                            criteriaData = criteriaResult.criteria;
                            // Load and render criteria cards in the management section for all users
                            if (typeof renderCriteriaList === 'function') {
                                renderCriteriaList(criteriaData);
                            } else if (typeof window.loadCriteriaList === 'function') {
                                window.loadCriteriaList();
                            }
                        }
                    }


                    // ALL USERS (admin and regular) see the same view - award categories with statistics
                    console.log('Loading award categories with statistics...');
                    // Load award criteria with applicant counts (same view for everyone)
                    const response = await fetch('api/award-applicants.php?list_all=true', {
                        headers: {
                            'Authorization': 'Bearer ' + AUTH_TOKEN
                        }
                    });

                    if (!response.ok) {
                        throw new Error('Failed to load award criteria');
                    }

                    const result = await response.json();
                    if (!result.success) {
                        throw new Error(result.error || 'Failed to load criteria');
                    }

                    renderAwardCriteriaList(result.criteria);

                } catch (error) {
                    console.error('Error loading award list:', error);
                    const tbody = document.getElementById('award-criteria-tbody');
                    if (tbody) {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-red-600 dark:text-red-400">
                                    Failed to load: ${error.message}
                                </td>
                            </tr>
                        `;
                    }
                }
            };

            // Legacy function name for backward compatibility
            async function loadAwardCriteria() {
                await window.loadAwardListData();
            }

            // Render award criteria in the table
            function renderAwardCriteriaList(criteria) {
                const tbody = document.getElementById('award-criteria-tbody');
                if (!tbody) return;

                if (criteria.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                No award criteria found. Admin can add criteria using the button above.
                            </td>
                        </tr>
                    `;
                    return;
                }

                // Calculate totals across all awards
                let totalApplicants = 0;
                let totalPending = 0;
                let totalRecognized = 0;
                let totalProcessed = 0;

                criteria.forEach(c => {
                    totalApplicants += parseInt(c.total_applicants) || 0;
                    totalPending += parseInt(c.pending_count) || 0;
                    totalRecognized += parseInt(c.recognized_count) || 0;
                    totalProcessed += parseInt(c.processed_count) || 0;
                });

                // Update the counters at the top
                updateAwardListCounters({
                    total: totalProcessed,
                    recognized: totalRecognized,
                    pending: totalPending
                });

                tbody.innerHTML = criteria.map(c => {
                    const total = parseInt(c.total_applicants) || 0;
                    const pending = parseInt(c.pending_count) || 0;
                    const recognized = parseInt(c.recognized_count) || 0;
                    const processed = parseInt(c.processed_count) || 0;

                    return `
                        <tr class="hover:bg-primary/5 transition-colors cursor-pointer" onclick="window.location.href='award-applicants.php?category=${encodeURIComponent(c.category_name)}'">
                            <td class="px-6 py-4">
                                <div class="font-semibold text-primary hover:text-primary/80">
                                    ${escapeHtml(c.category_name)}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    ${escapeHtml(c.description || '').substring(0, 80)}${c.description && c.description.length > 80 ? '...' : ''}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 text-xs font-medium rounded-full ${
                                    c.award_type === 'Individual' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400' :
                                    c.award_type === 'Institutional' ? 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400' :
                                    'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'
                                }">
                                    ${escapeHtml(c.award_type)}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-600 dark:text-gray-400">
                                ${c.requirements_count} criteria
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="text-lg font-bold text-gray-900 dark:text-white">${total}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400">
                                    ${pending}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400">
                                    ${recognized}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400">
                                    ${processed}
                                </span>
                            </td>
                        </tr>
                    `;
                }).join('');
            }

            // Render user submissions list (for non-admin users)
            function renderUserSubmissionsList(submissions) {
                const tbody = document.getElementById('award-criteria-tbody');
                if (!tbody) return;

                if (submissions.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                <div class="flex flex-col items-center gap-3">
                                    <span class="material-symbols-outlined text-5xl">folder_open</span>
                                    <p class="text-lg font-medium">No awards submitted yet</p>
                                    <p class="text-sm">Go to <strong>Process Award</strong> tab to submit your first award!</p>
                                </div>
                            </td>
                        </tr>
                    `;
                    return;
                }

                // Update counters
                const eligible = submissions.filter(s => parseFloat(s.match_percentage || 0) >= 90).length;
                const pending = submissions.filter(s => s.status === 'pending').length;
                const recognized = submissions.filter(s => s.status === 'approved').length;

                document.getElementById('total-processed-count').textContent = submissions.length;
                document.getElementById('recognized-count').textContent = recognized;
                document.getElementById('pending-count').textContent = pending;

                tbody.innerHTML = submissions.map(sub => {
                    const matchPct = parseFloat(sub.match_percentage || 0);
                    const criteriaMet = sub.criteria_met || 0;
                    const criteriaTotal = sub.criteria_total || 0;

                    // Status badge
                    let statusBadge, statusText;
                    if (sub.status === 'approved') {
                        statusBadge = 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400';
                        statusText = '✅ Recognized';
                    } else if (sub.status === 'analyzed' && matchPct >= 90) {
                        statusBadge = 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400';
                        statusText = '✅ Eligible';
                    } else if (sub.status === 'analyzed' && matchPct >= 70) {
                        statusBadge = 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400';
                        statusText = '🟡 Almost Eligible';
                    } else if (sub.status === 'analyzed') {
                        statusBadge = 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400';
                        statusText = '❌ Not Eligible';
                    } else {
                        statusBadge = 'bg-gray-100 dark:bg-gray-900/30 text-gray-700 dark:text-gray-400';
                        statusText = '⏳ Pending';
                    }

                    return `
                        <tr class="hover:bg-primary/5 transition-colors cursor-pointer"
                            onclick="viewSubmissionDetail(${sub.award_id})">
                            <td class="px-6 py-4">
                                <div class="font-semibold text-primary hover:text-primary/80">
                                    ${escapeHtml(sub.award_name || 'Unknown Award')}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                    ${escapeHtml(sub.submission_title || 'Untitled')}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-lg font-bold ${matchPct >= 90 ? 'text-green-600 dark:text-green-400' : matchPct >= 70 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400'}">
                                    ${criteriaMet}/${criteriaTotal}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div class="h-2 rounded-full transition-all ${
                                            matchPct >= 90 ? 'bg-green-500' :
                                            matchPct >= 70 ? 'bg-yellow-500' :
                                            'bg-red-500'
                                        }" style="width: ${matchPct}%"></div>
                                    </div>
                                    <span class="text-sm font-semibold ${
                                        matchPct >= 90 ? 'text-green-600 dark:text-green-400' :
                                        matchPct >= 70 ? 'text-yellow-600 dark:text-yellow-400' :
                                        'text-red-600 dark:text-red-400'
                                    }">${matchPct.toFixed(1)}%</span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs font-medium rounded-full ${statusBadge}">
                                    ${statusText}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                                ${new Date(sub.created_at).toLocaleDateString()}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button class="p-2 rounded-md hover:bg-primary/10 text-primary"
                                        onclick="event.stopPropagation(); viewSubmissionDetail(${sub.award_id})"
                                        title="View Details">
                                    <span class="material-symbols-outlined text-lg">visibility</span>
                                </button>
                            </td>
                        </tr>
                    `;
                }).join('');
            }

            // View submission detail function (make it global)
            window.viewSubmissionDetail = function(awardId) {
                window.location.href = `user-award-detail.php?id=${awardId}`;
            };

            // Render available awards list for non-admin users (when they have no submissions)
            function renderAvailableAwardsList(criteria) {
                const tbody = document.getElementById('award-criteria-tbody');
                if (!tbody) return;

                if (criteria.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                <div class="flex flex-col items-center gap-3">
                                    <span class="material-symbols-outlined text-5xl">folder_open</span>
                                    <p class="text-lg font-medium">No awards available</p>
                                    <p class="text-sm">Contact administrator to add award criteria</p>
                                </div>
                            </td>
                        </tr>
                    `;
                    return;
                }

                // Update counters to show available awards
                document.getElementById('total-processed-count').textContent = criteria.length;
                document.getElementById('recognized-count').textContent = '0';
                document.getElementById('pending-count').textContent = '0';

                tbody.innerHTML = criteria.map(c => {
                    const requirements = c.requirements ? JSON.parse(c.requirements) : [];
                    const requirementsCount = Array.isArray(requirements) ? requirements.length : 0;
                    
                    return `
                        <tr class="hover:bg-primary/5 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-semibold text-primary">
                                    ${escapeHtml(c.category_name || 'Unknown Award')}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    ${escapeHtml(c.description || '').substring(0, 80)}${c.description && c.description.length > 80 ? '...' : ''}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                    Not submitted yet
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-lg font-bold text-gray-400">
                                    0/${requirementsCount}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div class="h-2 rounded-full bg-gray-400" style="width: 0%"></div>
                                    </div>
                                    <span class="text-sm font-semibold text-gray-400">0%</span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 dark:bg-gray-900/30 text-gray-700 dark:text-gray-400">
                                    Not Started
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                                -
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button class="p-2 rounded-md hover:bg-primary/10 text-primary"
                                        onclick="window.location.href='#process-award'"
                                        title="Submit Award">
                                    <span class="material-symbols-outlined text-lg">add</span>
                                </button>
                            </td>
                        </tr>
                    `;
                }).join('');
            }

            // Filter function for Award List & Criteria tab (works for both admin and user views) - make global
            window.filterAwardCriteriaList = function() {
                const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;

                if (!isAdmin) {
                    // User filter logic for submissions
                    const searchTerm = document.getElementById('searchInput')?.value.toLowerCase() || '';
                    const categoryFilter = document.getElementById('categoryFilter')?.value || '';
                    const confidenceFilter = document.getElementById('confidenceFilter')?.value || '';
                    const dateFilter = document.getElementById('dateFilter')?.value || '';

                    let filtered = [...allUserSubmissions];

                    // Search filter
                    if (searchTerm) {
                        filtered = filtered.filter(sub =>
                            (sub.award_name && sub.award_name.toLowerCase().includes(searchTerm)) ||
                            (sub.submission_title && sub.submission_title.toLowerCase().includes(searchTerm))
                        );
                    }

                    // Category filter
                    if (categoryFilter) {
                        filtered = filtered.filter(sub => sub.award_name === categoryFilter);
                    }

                    // Confidence/Match percentage filter
                    if (confidenceFilter) {
                        const [min, max] = confidenceFilter.split('-').map(Number);
                        filtered = filtered.filter(sub => {
                            const matchPct = parseFloat(sub.match_percentage || 0);
                            return matchPct >= min && matchPct <= max;
                        });
                    }

                    // Date filter
                    if (dateFilter) {
                        filtered = filtered.filter(sub => {
                            const subDate = new Date(sub.created_at).toISOString().split('T')[0];
                            return subDate === dateFilter;
                        });
                    }

                    renderUserSubmissionsList(filtered);
                }
            };

            // Clear filters for Award List & Criteria tab - make global
            window.clearAwardCriteriaFilters = function() {
                const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;

                document.getElementById('searchInput').value = '';
                document.getElementById('categoryFilter').value = '';
                document.getElementById('confidenceFilter').value = '';
                document.getElementById('dateFilter').value = '';

                if (!isAdmin) {
                    renderUserSubmissionsList(allUserSubmissions);
                }
            };


            // Render award list in the table (Process Award tab ONLY)
            function renderAwardList(awards) {
                // NOTE: This function is for the Process Award tab, NOT the Award List & Criteria tab
                // Use specific ID to avoid conflicts with award-criteria-tbody
                const tbody = document.querySelector('#process-content tbody');
                if (!tbody) return;

                if (awards.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="9" class="px-6 py-8 text-center text-text-muted-light dark:text-text-muted-dark">
                                <div class="flex flex-col items-center gap-2">
                                    <span class="material-symbols-outlined text-4xl text-text-muted-light dark:text-text-muted-dark">folder_open</span>
                                    <p class="text-lg font-medium">No awards uploaded yet</p>
                                    <p class="text-sm">Upload your first award document to get started</p>
                                </div>
                            </td>
                        </tr>
                    `;
                    return;
                }

                tbody.innerHTML = awards.map(award => {
                    const analysis = award.analysis || {};
                    const confidence = (analysis.confidence * 100) || 0;
                    const status = getAwardStatus(confidence);
                    const statusColor = getStatusColor(status);
                    const actualStatus = award.status || 'pending';

                    return `
                        <tr class="hover:bg-primary/5 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" class="row-checkbox rounded border-gray-300 text-primary focus:ring-primary" value="${award.id}" onchange="updateBulkActions()">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button class="font-semibold text-primary hover:text-primary/80 transition-colors cursor-pointer text-left" onclick="showAwardApplicantsModal('${escapeHtml(analysis.predicted_category || '')}', '${escapeHtml(award.title || award.file_name || 'Untitled')}')">
                                    ${escapeHtml(award.title || award.file_name || 'Untitled')}
                                </button>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-text-muted-light dark:text-text-muted-dark">${escapeHtml(analysis.predicted_category || 'Not Analyzed')}</td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <div class="w-24 bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                                            <div class="h-2 rounded-full transition-all duration-500 ${
                                                confidence >= 90 ? 'bg-green-500' :
                                                confidence >= 75 ? 'bg-blue-500' :
                                                confidence >= 60 ? 'bg-yellow-500' : 'bg-red-500'
                                            }" style="width: ${Math.min(confidence, 100)}%"></div>
                                        </div>
                                        <span class="text-sm font-semibold ${
                                            confidence >= 90 ? 'text-green-600 dark:text-green-400' :
                                            confidence >= 75 ? 'text-blue-600 dark:text-blue-400' :
                                            confidence >= 60 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400'
                                        }">${confidence.toFixed(0)}%</span>
                                    </div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Similarity Score</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-text-muted-light dark:text-text-muted-dark">
                                ${(() => {
                            try {
                                const analysisResults = analysis.analysis_results ? JSON.parse(analysis.analysis_results) : [];
                                const eligibleAwards = analysisResults.filter(result => result.status === 'Eligible' || result.status === 'Partially Eligible');
                                const count = eligibleAwards.length;
                                if (count > 0) {
                                    return `<button class="text-gray-600 dark:text-gray-400 hover:text-primary cursor-pointer font-medium transition-colors duration-200" onclick="showEligibleAwards('${award.id}')" title="Click to view eligible awards">${count} eligible award${count === 1 ? '' : 's'}</button>`;
                                }
                                return '0 eligible awards';
                            } catch (e) {
                                const criteriaCount = analysis.matched_categories_json ? JSON.parse(analysis.matched_categories_json).length : 0;
                                if (criteriaCount > 0) {
                                    return `<button class="text-gray-600 dark:text-gray-400 hover:text-primary cursor-pointer font-medium transition-colors duration-200" onclick="showEligibleAwards('${award.id}')" title="Click to view criteria">${criteriaCount} criteria met</button>`;
                                }
                                return '0 criteria met';
                            }
                        })()}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-text-muted-light dark:text-text-muted-dark">
                                ${(() => {
                            const description = award.description || 'No description provided';
                            const truncated = description.length > 10 ? description.substring(0, 10) + '...' : description;
                            if (description.length > 10) {
                                return `<button class="text-gray-600 dark:text-gray-400 hover:text-primary cursor-pointer font-medium transition-colors duration-200" onclick="showDescriptionModal('${award.id}')" title="Click to view full description">${escapeHtml(truncated)}</button>`;
                            }
                            return `<span title="${escapeHtml(description)}">${escapeHtml(truncated)}</span>`;
                        })()}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-text-muted-light dark:text-text-muted-dark">${formatDate(award.created_at)}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-xs font-medium bg-${statusColor}100 text-${statusColor}800">${status}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <button class="p-2 rounded-md hover:bg-primary/10 text-text-muted-light dark:text-text-muted-dark" onclick="viewAward('${award.id}')" title="View Details">
                                        <span class="material-symbols-outlined text-lg">visibility</span>
                                    </button>
                                    <button class="p-2 rounded-md hover:bg-primary/10 text-text-muted-light dark:text-text-muted-dark" onclick="reanalyzeAward('${award.id}')" title="Re-analyze">
                                        <span class="material-symbols-outlined text-lg">refresh</span>
                                    </button>
                                    <button class="p-2 rounded-md hover:bg-red-500/10 text-red-500" onclick="deleteAward('${award.id}')" title="Delete">
                                        <span class="material-symbols-outlined text-lg">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                }).join('');

                // Update bulk actions after rendering
                updateBulkActions();
            }

            // Show detailed award modal with user info and status management
            async function showAwardDetailModal(awardId) {
                try {
                    const response = await fetch(`api/award-detail.php?id=${awardId}`, {
                        headers: {
                            'Authorization': 'Bearer ' + AUTH_TOKEN
                        }
                    });

                    if (!response.ok) throw new Error('Failed to fetch award details');

                    const result = await response.json();
                    if (!result.success) throw new Error(result.error || 'Failed to load details');

                    const award = result.award;
                    const similarity = Math.round((award.similarity_score || award.match_percentage / 100) * 100);
                    const unmatchedList = normalizeCriteriaList(award.unmatched_criteria);
                    const detailGuidanceHtml = buildGuidanceTips(
                        unmatchedList,
                        similarity,
                        award.predicted_category || award.title || 'CHED ICONS Award'
                    );

                    // Determine status badge color
                    const statusColors = {
                        'pending': { bg: 'bg-yellow-100 dark:bg-yellow-900/30', text: 'text-yellow-700 dark:text-yellow-400' },
                        'recognized': { bg: 'bg-green-100 dark:bg-green-900/30', text: 'text-green-700 dark:text-green-400' },
                        'processed': { bg: 'bg-blue-100 dark:bg-blue-900/30', text: 'text-blue-700 dark:text-blue-400' },
                        'analyzed': { bg: 'bg-purple-100 dark:bg-purple-900/30', text: 'text-purple-700 dark:text-purple-400' }
                    };

                    const currentStatus = award.award_status || 'pending';
                    const statusColor = statusColors[currentStatus] || statusColors['pending'];

                    let content = `
                        <div class="space-y-6">
                            <!-- User Information -->
                            <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                                    <span class="material-symbols-outlined">person</span>
                                    Submitted By
                                </h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Username</div>
                                        <div class="font-medium text-gray-900 dark:text-white">${award.username || 'Unknown'}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Email</div>
                                        <div class="font-medium text-gray-900 dark:text-white">${award.email || 'N/A'}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Submitted On</div>
                                        <div class="font-medium text-gray-900 dark:text-white">${new Date(award.created_at).toLocaleDateString()}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Current Status</div>
                                        <span class="inline-flex px-3 py-1 ${statusColor.bg} ${statusColor.text} rounded-full text-xs font-medium mt-1">
                                            ${currentStatus.charAt(0).toUpperCase() + currentStatus.slice(1)}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Analysis Results -->
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
                                    <span class="material-symbols-outlined">analytics</span>
                                    Analysis Results
                                </h4>
                                <div class="space-y-4">
                                    <!-- Similarity Score Progress Bar -->
                                    <div>
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Similarity Score</span>
                                            <span class="text-lg font-bold ${
                                                similarity >= 90 ? 'text-green-600 dark:text-green-400' :
                                                similarity >= 75 ? 'text-blue-600 dark:text-blue-400' :
                                                similarity >= 60 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400'
                                            }">${similarity}%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4 overflow-hidden">
                                            <div class="h-4 rounded-full transition-all duration-500 flex items-center justify-end pr-2 ${
                                                similarity >= 90 ? 'bg-gradient-to-r from-green-400 to-green-600' :
                                                similarity >= 75 ? 'bg-gradient-to-r from-blue-400 to-blue-600' :
                                                similarity >= 60 ? 'bg-gradient-to-r from-yellow-400 to-yellow-600' : 'bg-gradient-to-r from-red-400 to-red-600'
                                            }" style="width: ${similarity}%">
                                                <span class="text-xs font-bold text-white">${similarity}%</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Matched Criteria -->
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="bg-green-50 dark:bg-green-900/20 p-3 rounded-lg">
                                            <div class="text-2xl font-bold text-green-600 dark:text-green-400">${award.criteria_met || 0}</div>
                                            <div class="text-xs text-gray-600 dark:text-gray-400">Criteria Met</div>
                                        </div>
                                        <div class="bg-gray-50 dark:bg-gray-900/20 p-3 rounded-lg">
                                            <div class="text-2xl font-bold text-gray-600 dark:text-gray-400">${award.criteria_total || 0}</div>
                                            <div class="text-xs text-gray-600 dark:text-gray-400">Total Criteria</div>
                                        </div>
                                    </div>

                                    <!-- Predicted Category -->
                                    <div>
                                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Predicted Category</div>
                                        <div class="bg-purple-50 dark:bg-purple-900/20 px-3 py-2 rounded-lg">
                                            <span class="font-medium text-purple-700 dark:text-purple-400">${award.predicted_category || 'Not categorized'}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Matched Criteria Details -->
                            ${award.matched_criteria && award.matched_criteria.length > 0 ? `
                                <div>
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                                        <span class="material-symbols-outlined text-green-600">check_circle</span>
                                        Matched Criteria
                                    </h4>
                                    <div class="flex flex-wrap gap-2">
                                        ${award.matched_criteria.map(c => `
                                            <span class="px-3 py-1.5 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded-lg text-sm font-medium">
                                                ✓ ${c}
                                            </span>
                                        `).join('')}
                                    </div>
                                </div>
                            ` : ''}

                            <!-- Unmatched Criteria -->
                            ${unmatchedList && unmatchedList.length > 0 ? `
                                <div>
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                                        <span class="material-symbols-outlined text-red-600">cancel</span>
                                        Unmatched Criteria
                                    </h4>
                                    <div class="flex flex-wrap gap-2">
                                        ${unmatchedList.map(c => `
                                            <span class="px-3 py-1.5 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded-lg text-sm font-medium">
                                                ✗ ${c}
                                            </span>
                                        `).join('')}
                                    </div>
                                </div>
                            ` : ''}

                            <!-- Eligibility Guidance -->
                            ${detailGuidanceHtml}

                            <!-- Status Management -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Change Status</h4>
                                <div class="flex gap-2">
                                    <button onclick="updateAwardStatus('${award.id}', 'pending')"
                                        class="flex-1 px-4 py-2 ${currentStatus === 'pending' ? 'bg-yellow-600 text-white' : 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400'} rounded-lg font-medium hover:opacity-80 transition-opacity">
                                        Pending
                                    </button>
                                    <button onclick="updateAwardStatus('${award.id}', 'recognized')"
                                        class="flex-1 px-4 py-2 ${currentStatus === 'recognized' ? 'bg-green-600 text-white' : 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'} rounded-lg font-medium hover:opacity-80 transition-opacity">
                                        Recognized
                                    </button>
                                    <button onclick="updateAwardStatus('${award.id}', 'processed')"
                                        class="flex-1 px-4 py-2 ${currentStatus === 'processed' ? 'bg-blue-600 text-white' : 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400'} rounded-lg font-medium hover:opacity-80 transition-opacity">
                                        Processed
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;

                    showModal(`📄 ${award.title || 'Award Details'}`, content);

                } catch (error) {
                    console.error('Error loading award details:', error);
                    showModal('Error', `<p class="text-red-600">Failed to load award details: ${error.message}</p>`);
                }
            }

            // Update award status
            async function updateAwardStatus(awardId, newStatus) {
                try {
                    const response = await fetch(`api/award-detail.php?id=${awardId}`, {
                        method: 'POST',
                        headers: {
                            'Authorization': 'Bearer ' + AUTH_TOKEN,
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ status: newStatus })
                    });

                    if (!response.ok) throw new Error('Failed to update status');

                    const result = await response.json();
                    if (!result.success) throw new Error(result.error || 'Failed to update');

                    // Close modal
                    const modal = document.getElementById('kpiModal');
                    if (modal) modal.classList.add('hidden');

                    // Show success notification
                    showNotification(`Status updated to: ${newStatus}`, 'success');

                    // Reload award list
                    await loadAwardListData();

                } catch (error) {
                    console.error('Error updating status:', error);
                    showNotification('Failed to update status: ' + error.message, 'error');
                }
            }

            // Show all applicants for a specific award category (make it global)
            window.showAwardApplicantsModal = async function(category, awardTitle) {
                if (!category) {
                    showModal('Error', '<p class="text-red-600">Award category not found</p>');
                    return;
                }

                try {
                    const response = await fetch(`api/award-applicants.php?category=${encodeURIComponent(category)}`, {
                        headers: {
                            'Authorization': 'Bearer ' + AUTH_TOKEN
                        }
                    });

                    if (!response.ok) throw new Error('Failed to fetch applicants');

                    const result = await response.json();
                    if (!result.success) throw new Error(result.error || 'Failed to load applicants');

                    const { applicants, stats } = result;

                    // Build stats header
                    const statsHtml = `
                        <div class="grid grid-cols-4 gap-3 mb-6">
                            <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg text-center">
                                <div class="text-3xl font-bold text-blue-600 dark:text-blue-400">${stats.total}</div>
                                <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">Total Applicants</div>
                            </div>
                            <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg text-center">
                                <div class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">${stats.pending}</div>
                                <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">Pending Review</div>
                            </div>
                            <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg text-center">
                                <div class="text-3xl font-bold text-green-600 dark:text-green-400">${stats.recognized}</div>
                                <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">Recognized</div>
                            </div>
                            <div class="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg text-center">
                                <div class="text-3xl font-bold text-purple-600 dark:text-purple-400">${stats.processed}</div>
                                <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">Processed</div>
                            </div>
                        </div>
                    `;

                    // Build applicants list
                    let applicantsHtml = '';
                    if (applicants.length === 0) {
                        applicantsHtml = '<p class="text-center text-gray-500 py-8">No applicants found for this award</p>';
                    } else {
                        applicantsHtml = applicants.map(app => {
                            const similarity = Math.round((app.similarity_score || app.match_percentage / 100) * 100);
                            const unmatchedList = normalizeCriteriaList(app.unmatched_criteria);
                            const applicantGuidance = buildGuidanceTips(
                                unmatchedList,
                                similarity,
                                app.predicted_category || category
                            );
                            const statusColors = {
                                'pending': { bg: 'bg-yellow-100 dark:bg-yellow-900/30', text: 'text-yellow-700 dark:text-yellow-400', border: 'border-yellow-200 dark:border-yellow-800', label: 'Pending' },
                                'approved': { bg: 'bg-green-100 dark:bg-green-900/30', text: 'text-green-700 dark:text-green-400', border: 'border-green-200 dark:border-green-800', label: 'Recognized' },
                                'analyzed': { bg: 'bg-purple-100 dark:bg-purple-900/30', text: 'text-purple-700 dark:text-purple-400', border: 'border-purple-200 dark:border-purple-800', label: 'Processed' },
                                'rejected': { bg: 'bg-red-100 dark:bg-red-900/30', text: 'text-red-700 dark:text-red-400', border: 'border-red-200 dark:border-red-800', label: 'Rejected' }
                            };

                            const currentStatus = (app.award_status || 'pending').toLowerCase();
                            const statusColor = statusColors[currentStatus] || statusColors['pending'];

                            return `
                                <div class="border ${statusColor.border} rounded-lg p-4 hover:shadow-md transition-shadow">
                                    <!-- User Header -->
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="material-symbols-outlined text-gray-600 dark:text-gray-400">person</span>
                                                <h4 class="font-semibold text-gray-900 dark:text-white">${app.username || 'Unknown User'}</h4>
                                            </div>
                                            <p class="text-sm text-gray-500 dark:text-gray-400 ml-7">${app.email || 'No email'}</p>
                                            <p class="text-xs text-gray-400 dark:text-gray-500 ml-7 mt-1">Submitted: ${new Date(app.created_at).toLocaleDateString()}</p>
                                        </div>
                                        <span class="px-3 py-1 ${statusColor.bg} ${statusColor.text} rounded-full text-xs font-medium">
                                            ${statusColor.label}
                                        </span>
                                    </div>

                                    <!-- Similarity Score Progress Bar -->
                                    <div class="mb-3">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Similarity Score</span>
                                            <span class="text-sm font-bold ${
                                                similarity >= 90 ? 'text-green-600 dark:text-green-400' :
                                                similarity >= 75 ? 'text-blue-600 dark:text-blue-400' :
                                                similarity >= 60 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400'
                                            }">${similarity}%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
                                            <div class="h-3 rounded-full transition-all duration-500 ${
                                                similarity >= 90 ? 'bg-gradient-to-r from-green-400 to-green-600' :
                                                similarity >= 75 ? 'bg-gradient-to-r from-blue-400 to-blue-600' :
                                                similarity >= 60 ? 'bg-gradient-to-r from-yellow-400 to-yellow-600' : 'bg-gradient-to-r from-red-400 to-red-600'
                                            }" style="width: ${similarity}%"></div>
                                        </div>
                                    </div>

                                    <!-- Matched Criteria -->
                                    ${app.matched_criteria && app.matched_criteria.length > 0 ? `
                                        <div class="mb-3">
                                            <div class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                                Matched Criteria (${app.criteria_met}/${app.criteria_total})
                                            </div>
                                            <div class="flex flex-wrap gap-1">
                                                ${app.matched_criteria.slice(0, 3).map(c => `
                                                    <span class="px-2 py-0.5 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded text-xs">
                                                        ✓ ${c}
                                                    </span>
                                                `).join('')}
                                                ${app.matched_criteria.length > 3 ? `
                                                    <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded text-xs">
                                                        +${app.matched_criteria.length - 3} more
                                                    </span>
                                                ` : ''}
                                            </div>
                                        </div>
                                    ` : ''}

                                    ${unmatchedList && unmatchedList.length > 0 ? `
                                        <div class="mb-3">
                                            <div class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                                Unmatched Criteria
                                            </div>
                                            <div class="flex flex-wrap gap-1">
                                                ${unmatchedList.slice(0, 5).map(c => `
                                                    <span class="px-2 py-0.5 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded text-xs">
                                                        ✗ ${c}
                                                    </span>
                                                `).join('')}
                                            </div>
                                        </div>
                                    ` : ''}

                                    ${applicantGuidance}

                                    <!-- Status Update Buttons -->
                                    <div class="border-t border-gray-200 dark:border-gray-700 pt-3 mt-3">
                                        <div class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">Update Status:</div>
                                        <div class="flex gap-2">
                                            <button onclick="updateApplicantStatus('${app.award_id}', 'pending')"
                                                class="flex-1 px-2 py-1 text-xs ${currentStatus === 'pending' ? 'bg-yellow-600 text-white' : 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400'} rounded hover:opacity-80 transition-opacity">
                                                Pending
                                            </button>
                                            <button onclick="updateApplicantStatus('${app.award_id}', 'recognized')"
                                                class="flex-1 px-2 py-1 text-xs ${currentStatus === 'approved' ? 'bg-green-600 text-white' : 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'} rounded hover:opacity-80 transition-opacity">
                                                Recognized
                                            </button>
                                            <button onclick="updateApplicantStatus('${app.award_id}', 'processed')"
                                                class="flex-1 px-2 py-1 text-xs ${currentStatus === 'analyzed' ? 'bg-purple-600 text-white' : 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400'} rounded hover:opacity-80 transition-opacity">
                                                Processed
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }).join('');
                    }

                    const content = `
                        <div class="space-y-4">
                            <div class="bg-gradient-to-r from-primary/10 to-primary/5 dark:from-primary/20 dark:to-primary/10 rounded-lg p-4 mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">${category}</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">All applicants for this award category</p>
                            </div>

                            ${statsHtml}

                            <div class="space-y-3 max-h-96 overflow-y-auto">
                                ${applicantsHtml}
                            </div>
                        </div>
                    `;

                    showModal(`👥 Applicants for ${category}`, content);

                } catch (error) {
                    console.error('Error loading applicants:', error);
                    showModal('Error', `<p class="text-red-600">Failed to load applicants: ${error.message}</p>`);
                }
            }

            // Update individual applicant status
            async function updateApplicantStatus(awardId, newStatus) {
                try {
                    const response = await fetch(`api/award-applicants.php?award_id=${awardId}`, {
                        method: 'POST',
                        headers: {
                            'Authorization': 'Bearer ' + AUTH_TOKEN,
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ status: newStatus })
                    });

                    if (!response.ok) throw new Error('Failed to update status');

                    const result = await response.json();
                    if (!result.success) throw new Error(result.error || 'Failed to update');

                    showNotification(`Status updated to: ${newStatus}`, 'success');

                    // Close and reopen modal to refresh
                    const modal = document.getElementById('kpiModal');
                    if (modal) modal.classList.add('hidden');

                    // Reload award list
                    await loadAwardListData();

                } catch (error) {
                    console.error('Error updating status:', error);
                    showNotification('Failed to update status: ' + error.message, 'error');
                }
            }

            // Helper functions
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text || '';
                return div.innerHTML;
            }

            function formatDate(dateString) {
                if (!dateString) return 'Unknown';
                const date = new Date(dateString);
                return date.toLocaleDateString();
            }

            function getAwardStatus(confidence) {
                if (confidence >= 90) return 'Recognized';
                if (confidence >= 75) return 'Pending Review';
                return 'Needs Attention';
            }

            function getStatusColor(status) {
                switch (status) {
                    case 'Recognized': return 'green-500';
                    case 'Pending Review': return 'yellow-500';
                    case 'Needs Attention': return 'red-500';
                    default: return 'gray-500';
                }
            }

    const CHED_GUIDANCE_URL = 'https://sites.google.com/ched.gov.ph/icons-awards-2024/home?authuser=0';
    const ELIGIBILITY_GUIDANCE_THRESHOLD = 70;
    const CHED_CRITERIA_GUIDANCE = {
        equity: 'Show how the initiative creates inclusive, intercultural experiences that welcome diverse learners and remove participation barriers.',
        poverty: 'Document measurable contributions to SDG-aligned poverty or inequality challenges (e.g., livelihood, scholarships, community outreach).',
        innovation: 'Highlight fresh, scalable approaches to internationalization or global engagement that go beyond legacy practices.',
        community: 'Provide evidence of student and community partnerships that turn global awareness into on-the-ground action.',
        international: 'Include MOUs, exchanges, or collaborations with overseas partners that demonstrate global reach.',
        sustainability: 'Explain how outcomes are sustained long term and aligned with just, equitable, and environmentally responsible practices.',
        citizenship: 'Describe how students are empowered as global citizens—equipped with mindset, skills, and experiences to act.',
        global: 'Connect the submission to cross-border impact, intercultural understanding, and SDG commitments.',
        access: 'Show policies or support systems that keep internationalization accessible to all learners.',
        partnerships: 'List strategic local/international partners and explain the shared impact achieved.',
        default: 'Add documentation or narrative proof that clearly links your submission to this CHED ICONS criterion.'
    };

    function normalizeCriteriaList(criteria) {
        if (!criteria) return [];
        if (Array.isArray(criteria)) return criteria;

        if (typeof criteria === 'string') {
            const trimmed = criteria.trim();
            if (!trimmed) return [];
            try {
                const parsed = JSON.parse(trimmed);
                if (Array.isArray(parsed)) return parsed;
            } catch (error) {
                // Not JSON, fallback to comma-separated text
            }
            return trimmed.split(',').map(item => item.trim()).filter(Boolean);
        }

        return [criteria].filter(Boolean);
    }

    function buildGuidanceTips(unmatchedCriteria = [], similarityScore = 0, awardLabel = '') {
        const normalizedList = normalizeCriteriaList(unmatchedCriteria);
        if (!normalizedList.length) return '';
        if (similarityScore >= ELIGIBILITY_GUIDANCE_THRESHOLD) return '';

        const renderedItems = [];
        const seen = new Set();

        normalizedList.forEach((label) => {
            if (!label) return;
            const normalized = label.toString().trim().toLowerCase();
            if (seen.has(normalized)) return;
            seen.add(normalized);
            const description = CHED_CRITERIA_GUIDANCE[normalized] || CHED_CRITERIA_GUIDANCE.default;
            renderedItems.push(`
                <li class="flex items-start gap-2">
                    <span class="material-symbols-outlined text-sm text-red-500 mt-0.5">priority_high</span>
                    <div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">${label}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400 leading-snug">${description}</p>
                    </div>
                </li>
            `);
        });

        if (!renderedItems.length) return '';

        return `
            <div class="mt-4 border border-red-200 dark:border-red-800 bg-red-50/70 dark:bg-red-900/20 rounded-lg p-4">
                <div class="flex items-center gap-2 text-red-700 dark:text-red-300 font-semibold mb-2">
                    <span class="material-symbols-outlined text-base">campaign</span>
                    <span>${awardLabel ? `${awardLabel}: ` : ''}Action needed to reach eligibility (${similarityScore}% match)</span>
                </div>
                <ul class="space-y-3">${renderedItems.join('')}</ul>
                <a href="${CHED_GUIDANCE_URL}" target="_blank" rel="noopener" class="mt-3 inline-flex items-center gap-1 text-xs font-medium text-red-700 dark:text-red-200 underline">
                    Review CHED ICONS 2024 criteria
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                </a>
            </div>
        `;
    }

            function updateAwardListCounters(stats) {
                // Handle both object and array formats
                let totalProcessed, recognized, pending;

                if (Array.isArray(stats)) {
                    // Legacy array format
                    totalProcessed = stats.reduce((sum, stat) => sum + (stat.count || 0), 0);
                    recognized = Math.floor(totalProcessed * 0.8);
                    pending = Math.floor(totalProcessed * 0.2);
                } else {
                    // Object format with direct counts
                    totalProcessed = stats.total || 0;
                    recognized = stats.recognized || 0;
                    pending = stats.pending || 0;
                }

                // Update counter badges
                const totalElement = document.querySelector('#award-list-content .bg-blue-100 .font-bold');
                const recognizedElement = document.querySelector('#award-list-content .bg-green-100 .font-bold');
                const pendingElement = document.querySelector('#award-list-content .bg-yellow-100 .font-bold');

                if (totalElement) totalElement.textContent = totalProcessed;
                if (recognizedElement) recognizedElement.textContent = recognized;
                if (pendingElement) pendingElement.textContent = pending;
            }

            // Award action functions (make them global)
            window.viewAward = function (id) {
                showViewModal(id);
            };

            // Make the reanalyzeAward function globally available
            window.reanalyzeAward = reanalyzeAward;

            // Make the deleteAward function globally available
            window.deleteAward = deleteAward;

            // Make the showEligibleAwards function globally available
            window.showEligibleAwards = showEligibleAwards;

            // Make the showDescriptionModal function globally available
            window.showDescriptionModal = showDescriptionModal;

            // Make the clearFilters function globally available
            window.clearFilters = clearFilters;

            // Make the filterAwardsList function globally available
            window.filterAwardsList = filterAwardsList;

            // Bulk selection functions
            function toggleSelectAll() {
                const selectAllCheckbox = document.getElementById('select-all');
                const rowCheckboxes = document.querySelectorAll('.row-checkbox');

                rowCheckboxes.forEach(checkbox => {
                    checkbox.checked = selectAllCheckbox.checked;
                });

                updateBulkActions();
            }

            function updateBulkActions() {
                const selectedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
                const bulkActionsDiv = document.getElementById('bulk-actions');
                const selectedCountSpan = document.getElementById('selected-count');
                const selectAllCheckbox = document.getElementById('select-all');
                const rowCheckboxes = document.querySelectorAll('.row-checkbox');

                if (selectedCheckboxes.length > 0) {
                    bulkActionsDiv.classList.remove('hidden');
                    selectedCountSpan.textContent = `${selectedCheckboxes.length} selected`;
                } else {
                    bulkActionsDiv.classList.add('hidden');
                }

                // Update select all checkbox state
                if (rowCheckboxes.length === 0) {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = false;
                } else if (selectedCheckboxes.length === rowCheckboxes.length) {
                    selectAllCheckbox.checked = true;
                    selectAllCheckbox.indeterminate = false;
                } else if (selectedCheckboxes.length > 0) {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = true;
                } else {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = false;
                }
            }

            // Global variable to store selected IDs for bulk delete
            let pendingBulkDeleteIds = [];

            async function bulkDeleteSelected() {
                const selectedCheckboxes = document.querySelectorAll('.row-checkbox:checked');

                if (selectedCheckboxes.length === 0) {
                    showNotification('No awards selected for deletion', 'error');
                    return;
                }

                const selectedIds = Array.from(selectedCheckboxes).map(cb => cb.value);

                // Show the bulk delete modal instead of browser confirm
                showBulkDeleteModal(selectedIds);
            }

            function showBulkDeleteModal(selectedIds) {
                pendingBulkDeleteIds = selectedIds;
                const modal = document.getElementById('bulkDeleteModal');
                const modalContent = document.getElementById('bulkDeleteModalContent');
                const messageElement = document.getElementById('bulkDeleteMessage');

                // Update the message with the count
                messageElement.textContent = `Are you sure you want to delete ${selectedIds.length} selected award(s)? This action cannot be undone and will permanently remove the awards and their associated files.`;

                modal.classList.remove('hidden');

                // Trigger animation
                setTimeout(() => {
                    modalContent.classList.remove('scale-95', 'opacity-0');
                    modalContent.classList.add('scale-100', 'opacity-100');
                }, 10);
            }

            function hideBulkDeleteModal() {
                const modal = document.getElementById('bulkDeleteModal');
                const modalContent = document.getElementById('bulkDeleteModalContent');

                modalContent.classList.remove('scale-100', 'opacity-100');
                modalContent.classList.add('scale-95', 'opacity-0');

                setTimeout(() => {
                    modal.classList.add('hidden');
                    pendingBulkDeleteIds = [];
                }, 300);
            }

            async function confirmBulkDelete() {
                if (pendingBulkDeleteIds.length === 0) {
                    return;
                }

                hideBulkDeleteModal();

                try {
                    const deletePromises = pendingBulkDeleteIds.map(async (awardId) => {
                        // Extract file ID similar to the individual delete function
                        let fileId;
                        if (awardId.startsWith('file_')) {
                            const withoutPrefix = awardId.replace('file_', '');
                            const parts = withoutPrefix.split('_');
                            fileId = parts[parts.length - 1];
                        } else {
                            fileId = awardId;
                        }

                        if (!fileId) {
                            throw new Error(`Could not extract file ID from award ID: ${awardId}`);
                        }

                        // Use the correct DELETE method and URL format like the individual delete function
                        const deleteUrl = `api/delete-award.php?id=${encodeURIComponent(fileId)}`;
                        const response = await fetch(deleteUrl, {
                            method: 'DELETE'
                        });

                        if (!response.ok) {
                            const errorData = await response.json().catch(() => ({ error: response.statusText }));
                            throw new Error(errorData.error || 'Failed to delete award');
                        }

                        return await response.json();
                    });

                    const results = await Promise.all(deletePromises);

                    // Check if all deletions were successful
                    const failedCount = results.filter(r => !r.success).length;
                    const successCount = results.length - failedCount;

                    if (successCount > 0) {
                        showNotification(`Successfully deleted ${successCount} award(s)`, 'success');

                        // Refresh the award list
                        await loadAwardListData();

                        // Hide bulk actions
                        updateBulkActions();
                    }

                    if (failedCount > 0) {
                        showNotification(`${failedCount} award(s) could not be deleted`, 'error');
                    }

                } catch (error) {
                    console.error('Bulk delete error:', error);
                    showNotification('Error during bulk deletion: ' + error.message, 'error');
                }
            }

            // Make bulk functions globally available
            window.toggleSelectAll = toggleSelectAll;
            window.updateBulkActions = updateBulkActions;
            window.bulkDeleteSelected = bulkDeleteSelected;
            window.hideBulkDeleteModal = hideBulkDeleteModal;
            window.confirmBulkDelete = confirmBulkDelete;

            // Modal event listeners
            document.getElementById('deleteCancelBtn').addEventListener('click', hideDeleteModal);
            document.getElementById('deleteConfirmBtn').addEventListener('click', confirmDelete);
            document.getElementById('bulkDeleteCancelBtn').addEventListener('click', hideBulkDeleteModal);
            document.getElementById('bulkDeleteConfirmBtn').addEventListener('click', confirmBulkDelete);
            document.getElementById('reanalyzeCancelBtn').addEventListener('click', hideReanalyzeModal);
            document.getElementById('reanalyzeConfirmBtn').addEventListener('click', confirmReanalyze);
            document.getElementById('viewCloseBtn').addEventListener('click', hideViewModal);
            document.getElementById('eligibleAwardsCloseBtn').addEventListener('click', hideEligibleAwardsModal);

            // Close modals when clicking outside
            document.getElementById('deleteModal').addEventListener('click', function (e) {
                if (e.target === this) {
                    hideDeleteModal();
                }
            });

            document.getElementById('reanalyzeModal').addEventListener('click', function (e) {
                if (e.target === this) {
                    hideReanalyzeModal();
                }
            });

            document.getElementById('viewModal').addEventListener('click', function (e) {
                if (e.target === this) {
                    hideViewModal();
                }
            });

            document.getElementById('eligibleAwardsModal').addEventListener('click', function (e) {
                if (e.target === this) {
                    hideEligibleAwardsModal();
                }
            });

            document.getElementById('bulkDeleteModal').addEventListener('click', function (e) {
                if (e.target === this) {
                    hideBulkDeleteModal();
                }
            });

            // Description Modal Event Listeners
            document.getElementById('descriptionCloseBtn').addEventListener('click', hideDescriptionModal);

            // Close description modal when clicking outside
            document.getElementById('descriptionModal').addEventListener('click', function (e) {
                if (e.target === this) {
                    hideDescriptionModal();
                }
            });

            // Close modals with Escape key
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    if (!document.getElementById('deleteModal').classList.contains('hidden')) {
                        hideDeleteModal();
                    } else if (!document.getElementById('reanalyzeModal').classList.contains('hidden')) {
                        hideReanalyzeModal();
                    } else if (!document.getElementById('viewModal').classList.contains('hidden')) {
                        hideViewModal();
                    } else if (!document.getElementById('eligibleAwardsModal').classList.contains('hidden')) {
                        hideEligibleAwardsModal();
                    } else if (!document.getElementById('descriptionModal').classList.contains('hidden')) {
                        hideDescriptionModal();
                    } else if (!document.getElementById('bulkDeleteModal').classList.contains('hidden')) {
                        hideBulkDeleteModal();
                    }
                }
            });

            // Tab functionality
            const processTab = document.getElementById('process-tab');
            const chedGuidanceTab = document.getElementById('ched-guidance-tab');
            const analyticsTab = document.getElementById('analytics-tab');
            const awardListTab = document.getElementById('award-list-tab');
            const processContent = document.getElementById('process-content');
            const chedGuidanceContent = document.getElementById('ched-guidance-content');
            const analyticsContent = document.getElementById('analytics-content');
            const awardListContent = document.getElementById('award-list-content');
            const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;

            const switchToProcess = () => {
                if (!processTab || !processContent) return;

                // Hide other tabs first
                analyticsContent.classList.add('hidden');
                analyticsContent.style.display = 'none';
                awardListContent.classList.add('hidden');
                awardListContent.style.display = 'none';
                if (chedGuidanceContent) {
                    chedGuidanceContent.classList.add('hidden');
                    chedGuidanceContent.style.display = 'none';
                }
                
                // Reset styles for clean animation
                processContent.style.opacity = '';
                processContent.style.transform = '';
                processContent.style.transition = '';

                processTab.classList.add('active', 'font-bold', 'text-primary');
                processTab.classList.remove('font-medium', 'text-text-muted-light', 'dark:text-text-muted-dark');

                analyticsTab.classList.remove('active', 'font-bold', 'text-primary');
                analyticsTab.classList.add('font-medium', 'text-text-muted-light', 'dark:text-text-muted-dark');

                awardListTab.classList.remove('active', 'font-bold', 'text-primary');
                awardListTab.classList.add('font-medium', 'text-text-muted-light', 'dark:text-text-muted-dark');

                if (chedGuidanceTab) {
                    chedGuidanceTab.classList.remove('active', 'font-bold', 'text-primary');
                    chedGuidanceTab.classList.add('font-medium', 'text-text-muted-light', 'dark:text-text-muted-dark');
                }

                // Show and animate new content
                processContent.classList.remove('hidden');
                processContent.style.display = 'block';
                
                // Force reflow
                processContent.offsetHeight;
                
                // Trigger animation
                processContent.style.opacity = '0';
                processContent.style.transform = 'translateY(15px)';
                processContent.style.transition = 'opacity 0.3s ease-out, transform 0.3s ease-out';
                
                requestAnimationFrame(() => {
                    processContent.style.opacity = '1';
                    processContent.style.transform = 'translateY(0)';
                });
            };

            const switchToAnalytics = () => {
                // Hide other tabs first
                if (processContent) {
                    processContent.classList.add('hidden');
                    processContent.style.display = 'none';
                }
                awardListContent.classList.add('hidden');
                awardListContent.style.display = 'none';
                if (chedGuidanceContent) {
                    chedGuidanceContent.classList.add('hidden');
                    chedGuidanceContent.style.display = 'none';
                }
                
                // Reset styles for clean animation
                analyticsContent.style.opacity = '';
                analyticsContent.style.transform = '';
                analyticsContent.style.transition = '';

                analyticsTab.classList.add('active', 'font-bold', 'text-primary');
                analyticsTab.classList.remove('font-medium', 'text-text-muted-light', 'dark:text-text-muted-dark');

                if (processTab) {
                    processTab.classList.remove('active', 'font-bold', 'text-primary');
                    processTab.classList.add('font-medium', 'text-text-muted-light', 'dark:text-text-muted-dark');
                }

                awardListTab.classList.remove('active', 'font-bold', 'text-primary');
                awardListTab.classList.add('font-medium', 'text-text-muted-light', 'dark:text-text-muted-dark');

                if (chedGuidanceTab) {
                    chedGuidanceTab.classList.remove('active', 'font-bold', 'text-primary');
                    chedGuidanceTab.classList.add('font-medium', 'text-text-muted-light', 'dark:text-text-muted-dark');
                }

                // Show and animate new content
                analyticsContent.classList.remove('hidden');
                analyticsContent.style.display = 'block';
                
                // Force reflow
                analyticsContent.offsetHeight;
                
                // Trigger animation
                analyticsContent.style.opacity = '0';
                analyticsContent.style.transform = 'translateY(15px)';
                analyticsContent.style.transition = 'opacity 0.3s ease-out, transform 0.3s ease-out';
                
                requestAnimationFrame(() => {
                    analyticsContent.style.opacity = '1';
                    analyticsContent.style.transform = 'translateY(0)';
                });

                setTimeout(() => {
                    loadAnalyticsData();
                    loadProcessingActivity();
                }, 100);
            };

            const switchToAwardList = () => {
                // Hide other tabs first
                if (processContent) {
                    processContent.classList.add('hidden');
                    processContent.style.display = 'none';
                }
                analyticsContent.classList.add('hidden');
                analyticsContent.style.display = 'none';
                if (chedGuidanceContent) {
                    chedGuidanceContent.classList.add('hidden');
                    chedGuidanceContent.style.display = 'none';
                }
                
                // Reset styles for clean animation
                awardListContent.style.opacity = '';
                awardListContent.style.transform = '';
                awardListContent.style.transition = '';

                awardListTab.classList.add('active', 'font-bold', 'text-primary');
                awardListTab.classList.remove('font-medium', 'text-text-muted-light', 'dark:text-text-muted-dark');

                if (processTab) {
                    processTab.classList.remove('active', 'font-bold', 'text-primary');
                    processTab.classList.add('font-medium', 'text-text-muted-light', 'dark:text-text-muted-dark');
                }

                analyticsTab.classList.remove('active', 'font-bold', 'text-primary');
                analyticsTab.classList.add('font-medium', 'text-text-muted-light', 'dark:text-text-muted-dark');

                if (chedGuidanceTab) {
                    chedGuidanceTab.classList.remove('active', 'font-bold', 'text-primary');
                    chedGuidanceTab.classList.add('font-medium', 'text-text-muted-light', 'dark:text-text-muted-dark');
                }

                // Show and animate new content
                awardListContent.classList.remove('hidden');
                awardListContent.style.display = 'block';
                
                // Force reflow
                awardListContent.offsetHeight;
                
                // Trigger animation
                awardListContent.style.opacity = '0';
                awardListContent.style.transform = 'translateY(15px)';
                awardListContent.style.transition = 'opacity 0.3s ease-out, transform 0.3s ease-out';
                
                requestAnimationFrame(() => {
                    awardListContent.style.opacity = '1';
                    awardListContent.style.transform = 'translateY(0)';
                });

                // Load award criteria from database
                loadAwardCriteria();

                // Load criteria management if admin
                if (isAdmin) {
                    loadCriteriaList();
                }
            };

            const switchToGuidance = () => {
                if (processContent) {
                    processContent.classList.add('hidden');
                    processContent.style.display = 'none';
                }
                analyticsContent.classList.add('hidden');
                analyticsContent.style.display = 'none';
                awardListContent.classList.add('hidden');
                awardListContent.style.display = 'none';

                if (chedGuidanceContent) {
                    chedGuidanceContent.style.opacity = '';
                    chedGuidanceContent.style.transform = '';
                    chedGuidanceContent.style.transition = '';
                }

                if (chedGuidanceTab) {
                    chedGuidanceTab.classList.add('active', 'font-bold', 'text-primary');
                    chedGuidanceTab.classList.remove('font-medium', 'text-text-muted-light', 'dark:text-text-muted-dark');
                }

                if (processTab) {
                    processTab.classList.remove('active', 'font-bold', 'text-primary');
                    processTab.classList.add('font-medium', 'text-text-muted-light', 'dark:text-text-muted-dark');
                }

                analyticsTab.classList.remove('active', 'font-bold', 'text-primary');
                analyticsTab.classList.add('font-medium', 'text-text-muted-light', 'dark:text-text-muted-dark');

                awardListTab.classList.remove('active', 'font-bold', 'text-primary');
                awardListTab.classList.add('font-medium', 'text-text-muted-light', 'dark:text-text-muted-dark');

                if (chedGuidanceContent) {
                    chedGuidanceContent.classList.remove('hidden');
                    chedGuidanceContent.style.display = 'block';
                    chedGuidanceContent.offsetHeight;
                    chedGuidanceContent.style.opacity = '0';
                    chedGuidanceContent.style.transform = 'translateY(15px)';
                    chedGuidanceContent.style.transition = 'opacity 0.3s ease-out, transform 0.3s ease-out';
                    requestAnimationFrame(() => {
                        chedGuidanceContent.style.opacity = '1';
                        chedGuidanceContent.style.transform = 'translateY(0)';
                    });
                }
            };

            if (processTab) {
                processTab.addEventListener('click', (e) => {
                    e.preventDefault();
                    switchToProcess();
                });
            }

            if (chedGuidanceTab) {
                chedGuidanceTab.addEventListener('click', (e) => {
                    e.preventDefault();
                    switchToGuidance();
                });
            }

            analyticsTab.addEventListener('click', (e) => {
                e.preventDefault();
                switchToAnalytics();
            });

            awardListTab.addEventListener('click', (e) => {
                e.preventDefault();
                switchToAwardList();
            });

            // Set initial state - check for hash to determine which tab to show
            if (window.location.hash === '#analytics-content') {
                switchToAnalytics();
            } else if (window.location.hash === '#award-list') {
                switchToAwardList();
            } else if (window.location.hash === '#ched-guidelines') {
                switchToGuidance();
            } else {
                // Default: show Process Award for regular users, Analytics for admins
                if (processTab && processContent) {
                    switchToProcess();
                } else {
                    switchToAnalytics();
                }
            }

            // Awards functionality is handled by awards.js

            // Initialize award analysis form
            initializeAwardAnalysisForm();

            // Note: Analytics charts are initialized when switching to analytics tab via loadAnalyticsData()
        });

        // Award Analysis Form Functions
        function initializeAwardAnalysisForm() {
            const form = document.getElementById('award-analysis-form');
            const fileInput = document.getElementById('file-upload');
            const dropZone = document.getElementById('drop-zone');
            const fileError = document.getElementById('file-error');
            const fileSuccess = document.getElementById('file-success');

            // Form submission
            form.addEventListener('submit', handleFormSubmission);

            // File input change
            fileInput.addEventListener('change', handleFileSelection);

            // Drag and drop functionality
            dropZone.addEventListener('dragover', handleDragOver);
            dropZone.addEventListener('dragleave', handleDragLeave);
            dropZone.addEventListener('drop', handleDrop);

            // Document selection buttons
            const selectFromDocumentsBtn = document.getElementById('selectFromDocumentsBtn');
            const selectFromEventsBtn = document.getElementById('selectFromEventsBtn');
            const selectedDocumentInfo = document.getElementById('selectedDocumentInfo');
            const selectedDocumentName = document.getElementById('selectedDocumentName');
            const clearSelectedDocument = document.getElementById('clearSelectedDocument');
            
            // Store selected document ID globally
            window.selectedDocumentId = null;
            window.selectedDocumentPath = null;

            if (selectFromDocumentsBtn) {
                selectFromDocumentsBtn.addEventListener('click', () => showDocumentSelectionModal('documents'));
            }
            if (selectFromEventsBtn) {
                selectFromEventsBtn.addEventListener('click', () => showDocumentSelectionModal('events'));
            }
            if (clearSelectedDocument) {
                clearSelectedDocument.addEventListener('click', () => {
                    window.selectedDocumentId = null;
                    window.selectedDocumentPath = null;
                    selectedDocumentInfo.classList.add('hidden');
                    fileInput.required = true;
                });
            }

            // Note: Click to upload is handled by the label element with for="file-upload"
            // No need for additional click handler on drop zone
        }

        async function showDocumentSelectionModal(source) {
            try {
                const response = await fetch(`api/get-documents.php?source=${source}`);
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.error || 'Failed to load documents');
                }

                const documents = result.data || [];
                const sourceLabel = source === 'events' ? 'Event Attachments' : 'Documents';
                
                const modalHTML = `
                    <div id="documentSelectionModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[9999]">
                        <div class="bg-white dark:bg-card-dark rounded-lg shadow-xl w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto">
                            <div class="p-6 border-b border-border-light dark:border-border-dark">
                                <div class="flex justify-between items-center">
                                    <h2 class="text-xl font-semibold text-text-light dark:text-text-dark">Select from ${sourceLabel}</h2>
                                    <button id="closeDocumentModal" class="text-text-muted-light dark:text-text-muted-dark hover:text-text-light dark:hover:text-text-dark">
                                        <span class="material-symbols-outlined">close</span>
                                    </button>
                                </div>
                            </div>
                            <div class="p-6">
                                <div id="documentsList" class="space-y-2">
                                    ${documents.map(doc => `
                                        <button 
                                            class="w-full text-left p-3 rounded-lg border border-border-light dark:border-border-dark hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors document-item-btn"
                                            data-document-id="${doc.id}"
                                            data-document-name="${doc.title || doc.file_name}"
                                            data-document-path="${doc.file_path}"
                                        >
                                            <div class="font-medium text-text-light dark:text-text-dark">${doc.title || doc.file_name}</div>
                                            ${doc.description ? `<div class="text-sm text-text-muted-light dark:text-text-muted-dark mt-1">${doc.description}</div>` : ''}
                                            ${doc.event_title ? `<div class="text-xs text-primary mt-1">Event: ${doc.event_title}</div>` : ''}
                                        </button>
                                    `).join('')}
                                </div>
                                ${documents.length === 0 ? `<p class="text-sm text-text-muted-light dark:text-text-muted-dark">No ${source} available.</p>` : ''}
                            </div>
                            <div class="p-6 border-t border-border-light dark:border-border-dark flex justify-end gap-2">
                                <button id="cancelDocumentModal" class="px-4 py-2 text-sm rounded-md border border-border-light dark:border-border-dark hover:bg-gray-50 dark:hover:bg-slate-700">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                `;

                document.body.insertAdjacentHTML('beforeend', modalHTML);
                const modal = document.getElementById('documentSelectionModal');
                
                document.getElementById('closeDocumentModal').addEventListener('click', () => modal.remove());
                document.getElementById('cancelDocumentModal').addEventListener('click', () => modal.remove());
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) modal.remove();
                });

                document.querySelectorAll('.document-item-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        window.selectedDocumentId = btn.dataset.documentId;
                        window.selectedDocumentPath = btn.dataset.documentPath;
                        const docName = btn.dataset.documentName;
                        
                        document.getElementById('selectedDocumentName').textContent = docName;
                        document.getElementById('selectedDocumentInfo').classList.remove('hidden');
                        document.getElementById('file-upload').required = false;
                        
                        modal.remove();
                    });
                });
            } catch (error) {
                console.error('Error loading documents:', error);
                alert('Error loading documents: ' + error.message);
            }
        }

        function handleFormSubmission(e) {
            e.preventDefault();

            // Debug: Check form fields before creating FormData
            const awardName = document.getElementById('award_name').value;
            const description = document.getElementById('description').value;
            const fileInput = document.getElementById('file-upload');
            const file = fileInput.files[0];

            console.log('Form field values:');
            console.log('Award name:', awardName);
            console.log('Description:', description);
            console.log('File:', file);
            console.log('File name:', file ? file.name : 'No file');

            // Validate fields before proceeding
            if (!awardName.trim()) {
                showNotification('Please enter an award name', 'error');
                return;
            }
            if (!description.trim()) {
                showNotification('Please enter a description', 'error');
                return;
            }
            
            // Check if document is selected from existing documents or new file uploaded
            const selectedDocId = window.selectedDocumentId;
            const selectedDocPath = window.selectedDocumentPath;
            
            if (!file && !selectedDocId) {
                showNotification('Please select a file to upload or choose from existing documents', 'error');
                return;
            }

            const formData = new FormData(e.target);
            
            // If using existing document, add document_id and file_path for re-analysis
            if (selectedDocId && selectedDocPath) {
                formData.append('document_id', selectedDocId);
                formData.append('original_file_path', selectedDocPath);
                formData.append('reanalyze', 'true');
                formData.append('source_page', 'awards');
            }
            // Add cache busting parameters
            formData.append('timestamp', Date.now().toString());
            formData.append('cache_bust', Math.random().toString(36).substring(7));
            const analyzeBtn = document.getElementById('analyze-btn');
            const loadingIcon = document.getElementById('loading-icon');
            const btnText = analyzeBtn.querySelector('.btn-text');
            const progressSection = document.getElementById('upload-progress-section');
            const progressBar = document.getElementById('progress-bar');
            const progressText = document.getElementById('progress-text');

            // Show loading state
            analyzeBtn.disabled = true;
            btnText.textContent = 'Analyzing...';
            loadingIcon.classList.remove('hidden');
            progressSection.classList.remove('hidden');

            // Enhanced multi-stage progress simulation with realistic OCR processing
            let progress = 0;
            let currentStage = 0;
            const stages = [
                { end: 15, messages: ['Uploading document...', 'Validating file format...', 'Securing upload connection...'] },
                { end: 25, messages: ['Pre-processing image...', 'Adjusting image contrast...', 'Detecting text regions...'] },
                { end: 45, messages: ['Initializing OCR engine...', 'Analyzing document layout...', 'Identifying text blocks...'] },
                { end: 65, messages: ['Extracting characters...', 'Recognizing words and phrases...', 'Processing page 1 of 1...'] },
                { end: 80, messages: ['Applying language models...', 'Validating extracted text...', 'Enhancing OCR accuracy...'] },
                { end: 90, messages: ['Analyzing against award criteria...', 'Matching requirements...', 'Computing eligibility scores...'] }
            ];

            let messageIndex = 0;
            const progressInterval = setInterval(() => {
                // Realistic incremental progress with variable speed
                const increment = Math.random() * 3 + 1;
                progress += increment;

                // Find current stage
                for (let i = 0; i < stages.length; i++) {
                    if (progress < stages[i].end) {
                        currentStage = i;
                        break;
                    }
                }

                if (progress > 90) progress = 90;
                progressBar.style.width = progress + '%';

                // Update percentage display
                const percentageEl = document.getElementById('progress-percentage');
                if (percentageEl) {
                    percentageEl.textContent = Math.floor(progress) + '%';
                }

                // Update message every 800ms for more realistic feel
                if (messageIndex % 4 === 0 && currentStage < stages.length) {
                    const stageMessages = stages[currentStage].messages;
                    const msgIdx = Math.floor(Math.random() * stageMessages.length);
                    progressText.textContent = stageMessages[msgIdx];
                }
                messageIndex++;
            }, 200);

            // Debug: Log form data
            console.log('Form data being sent:');
            for (let [key, value] of formData.entries()) {
                console.log(key + ':', value);
            }

            // Submit to API with cache busting (using new OCR-based analysis)
            console.log('Starting fresh analysis for new upload...');
            fetch('api/analyze-award-ocr.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache'
                }
            })
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers);
                    return response.text();
                })
                .then(text => {
                    console.log('Raw response text:', text);
                    try {
                        const data = JSON.parse(text);
                        console.log('Parsed JSON data:', data);
                        return data;
                    } catch (e) {
                        console.error('Failed to parse JSON:', e);
                        throw new Error('Invalid JSON response: ' + text.substring(0, 200));
                    }
                })
                .then(data => {
                    clearInterval(progressInterval);
                    progressBar.style.width = '100%';
                    progressText.textContent = 'Analysis complete!';

                    setTimeout(() => {
                        if (data.success) {
                            console.log('Analysis completed successfully:', data);
                            console.log('Analysis timestamp:', data.timestamp);
                            console.log('Is fresh analysis:', data.is_fresh_analysis);
                            console.log('Results count:', data.analysis ? data.analysis.length : 0);
                            // Dispatch eligibility counts for dashboards/charts
                            window.latestEligibilityCounts = {
                                eligible: data.eligible_count || 0,
                                partial: data.partial_count || 0,
                                notEligible: data.not_eligible_count || 0,
                                total: data.total_count || (data.analysis ? data.analysis.length : 0)
                            };
                            try {
                                document.dispatchEvent(new CustomEvent('analysis:updated', { detail: window.latestEligibilityCounts }));
                            } catch (e) { console.warn('Failed to dispatch analysis:updated event', e); }

                            displayAnalysisResults(data);

                            // Clear the input form after successful analysis (with a small delay)
                            setTimeout(() => {
                                clearInputForm();
                            }, 1500);

                            progressSection.classList.add('hidden');

                            // Reset button state
                            analyzeBtn.disabled = false;
                            btnText.textContent = 'Analyze Award';
                            loadingIcon.classList.add('hidden');

                            // Refresh the awards list
                            setTimeout(() => {
                                window.loadProcessAwardData();
                            }, 1000);

                        } else {
                            // Handle different types of errors gracefully
                            let errorMessage = data.error || 'Analysis failed';
                            let errorTitle = 'Analysis Error';

                            if (data.error_type === 'OCR_NOT_INSTALLED') {
                                errorTitle = 'Image Processing Unavailable';
                                errorMessage = data.error + '\n\nThis server does not have OCR (Optical Character Recognition) installed. Please convert your image to PDF or DOCX format for text extraction.';

                                if (data.instructions) {
                                    errorMessage += '\n\nInstallation Instructions:';
                                    data.instructions.forEach(instruction => {
                                        errorMessage += '\n• ' + instruction;
                                    });
                                }
                            } else if (data.error_type === 'OCR_DISABLED') {
                                errorTitle = 'Image Processing Disabled';
                                errorMessage = data.error + '\n\nPlease convert your image to PDF or DOCX format for text extraction.';
                            } else if (data.error_type === 'OCR_LOW_QUALITY') {
                                errorTitle = 'Image Text Extraction Failed';
                                errorMessage = data.error + '\n\nOnly extracted ' + (data.extracted_length || 0) + ' characters from the image.';

                                if (data.suggestions) {
                                    errorMessage += '\n\nSuggestions:';
                                    data.suggestions.forEach(suggestion => {
                                        errorMessage += '\n• ' + suggestion;
                                    });
                                }

                                if (data.sample_text) {
                                    errorMessage += '\n\nExtracted text preview: "' + data.sample_text + '"';
                                }
                            }

                            // Reset button state and progress
                            clearInterval(progressInterval);
                            progressSection.classList.add('hidden');
                            analyzeBtn.disabled = false;
                            btnText.textContent = 'Analyze Award';
                            loadingIcon.classList.add('hidden');

                            // Show error in a more user-friendly way with better formatting
                            const formattedMessage = errorMessage.replace(/\n/g, '\n');
                            alert(errorTitle + ':\n\n' + formattedMessage);
                            return;
                        }
                    }, 500);
                })
                .catch(error => {
                    clearInterval(progressInterval);
                    progressSection.classList.add('hidden');

                    // Reset button state
                    analyzeBtn.disabled = false;
                    btnText.textContent = 'Analyze Award';
                    loadingIcon.classList.add('hidden');

                    // Show error
                    alert('Analysis failed: ' + error.message);
                });
        }

        function handleFileSelection(e) {
            console.log('handleFileSelection called - award analysis form is active');
            const file = e.target.files[0];
            if (file) {
                console.log('File selected:', file.name, 'Size:', file.size);

                // Clear previous analysis results when new file is selected
                clearPreviousAnalysisResults();

                validateAndShowFile(file);
            }
        }

        function clearPreviousAnalysisResults() {
            console.log('Clearing previous analysis results for new upload...');

            // Reset analysis state
            window.currentAnalysisId = null;
            window.lastAnalysisData = null;

            // Hide results and show placeholder
            const placeholder = document.getElementById('analysis-placeholder');
            const results = document.getElementById('analysis-results');

            if (placeholder && results) {
                results.classList.add('hidden');
                placeholder.classList.remove('hidden');
            }

            // Clear analysis containers
            const awardAnalysisList = document.getElementById('award-analysis-list');
            const fullExtractedText = document.getElementById('full-extracted-text');

            if (awardAnalysisList) awardAnalysisList.innerHTML = '';
            if (fullExtractedText) fullExtractedText.textContent = '';
        }

        function clearInputForm() {
            console.log('Clearing input form after successful analysis...');

            // Clear award name field
            const awardNameInput = document.getElementById('award_name');
            if (awardNameInput) {
                console.log('Clearing award name field:', awardNameInput.value);
                awardNameInput.value = '';
            } else {
                console.warn('Award name input field not found');
            }

            // Clear description field
            const descriptionInput = document.getElementById('description');
            if (descriptionInput) {
                console.log('Clearing description field:', descriptionInput.value);
                descriptionInput.value = '';
            } else {
                console.warn('Description input field not found');
            }

            // Clear file input
            const fileInput = document.getElementById('file-upload');
            if (fileInput) {
                console.log('Clearing file input field:', fileInput.files.length, 'files');
                fileInput.value = '';
            } else {
                console.warn('File input field not found');
            }

            // Reset file upload area to initial state
            const fileUploadArea = document.getElementById('file-upload-area');
            const fileError = document.getElementById('file-error');
            const fileSuccess = document.getElementById('file-success');
            const fileSuccessMessage = document.getElementById('file-success-message');

            if (fileUploadArea) {
                // Remove any drag-over styling
                fileUploadArea.classList.remove('border-primary', 'bg-primary/5');
            }

            if (fileError) {
                fileError.classList.add('hidden');
            }

            if (fileSuccess) {
                fileSuccess.classList.add('hidden');
            }

            if (fileSuccessMessage) {
                fileSuccessMessage.textContent = '';
            }

            // Reset form validation states
            const form = document.getElementById('award-analysis-form');
            if (form) {
                // Remove any validation classes or states
                form.classList.remove('was-validated');
            }

            console.log('Input form cleared successfully');
        }


        function handleDragOver(e) {
            e.preventDefault();
            e.currentTarget.classList.add('border-primary', 'bg-primary/5');
        }

        function handleDragLeave(e) {
            e.preventDefault();
            e.currentTarget.classList.remove('border-primary', 'bg-primary/5');
        }

        function handleDrop(e) {
            e.preventDefault();
            e.currentTarget.classList.remove('border-primary', 'bg-primary/5');

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const fileInput = document.getElementById('file-upload');
                fileInput.files = files;
                validateAndShowFile(files[0]);
            }
        }

        function validateAndShowFile(file) {
            const fileError = document.getElementById('file-error');
            const fileSuccess = document.getElementById('file-success');
            const fileErrorMessage = document.getElementById('file-error-message');
            const fileSuccessMessage = document.getElementById('file-success-message');

            // Hide previous messages
            fileError.classList.add('hidden');
            fileSuccess.classList.add('hidden');

            // Validate file
            const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            const maxSize = 10 * 1024 * 1024; // 10MB

            if (!allowedTypes.includes(file.type)) {
                fileErrorMessage.textContent = 'Invalid file type. Please upload a PDF, DOCX, JPG, or PNG file.';
                fileError.classList.remove('hidden');
                return;
            }

            if (file.size > maxSize) {
                fileErrorMessage.textContent = 'File size exceeds 10MB limit.';
                fileError.classList.remove('hidden');
                return;
            }

            // Show success
            fileSuccessMessage.textContent = `File uploaded: ${file.name}`;
            fileSuccess.classList.remove('hidden');
        }

        function displayAnalysisResults(data) {
            console.log('displayAnalysisResults called with data:', data);

            const placeholder = document.getElementById('analysis-placeholder');
            const results = document.getElementById('analysis-results');
            const fullExtractedText = document.getElementById('full-extracted-text');
            const awardAnalysisList = document.getElementById('award-analysis-list');

            // Clear any cached results first
            console.log('Clearing previous analysis results...');
            window.currentAnalysisId = null;
            window.lastAnalysisData = null;

            // Store analysis ID for export functionality
            window.currentAnalysisId = data.award_id;
            window.lastAnalysisData = data;

            // Hide placeholder and show results
            placeholder.classList.add('hidden');
            results.classList.remove('hidden');

            // Set extracted text for modal
            if (fullExtractedText) {
                fullExtractedText.textContent = data.analysis?.ocr_text || data.detected_text || '';
            }

            // Clear and display award analysis
            if (awardAnalysisList) {
                awardAnalysisList.innerHTML = '';

                // Handle new API response format
                const analysisData = data.analysis || data;
                const allMatches = analysisData.all_matches || [];
                const bestMatch = analysisData.best_match;

                if (bestMatch || (Array.isArray(allMatches) && allMatches.length > 0)) {
                    const matchesToDisplay = allMatches.length > 0 ? allMatches : [bestMatch];

                    console.log('Total analysis results received:', matchesToDisplay.length);
                    console.log('Analysis data sample:', JSON.stringify(matchesToDisplay.slice(0, 2), null, 2));

                    // Filter and limit results based on match_percentage
                    const eligibleAwards = matchesToDisplay.filter(award => award.match_percentage >= 90);
                    const almostEligibleAwards = matchesToDisplay.filter(award => award.match_percentage >= 70 && award.match_percentage < 90);
                    const notEligibleAwards = matchesToDisplay.filter(award => award.match_percentage < 70);

                    console.log('Filtering results:', {
                        eligible: eligibleAwards.length,
                        almostEligible: almostEligibleAwards.length,
                        notEligible: notEligibleAwards.length
                    });

                    // Limit to max 5 results total, prioritizing eligible awards
                    let displayAwards = [];
                    displayAwards = displayAwards.concat(eligibleAwards);
                    displayAwards = displayAwards.concat(almostEligibleAwards.slice(0, 2)); // Max 2 almost eligible
                    displayAwards = displayAwards.concat(notEligibleAwards.slice(0, 2)); // Max 2 not eligible
                    displayAwards = displayAwards.slice(0, 5); // Total max 5

                    console.log('Displaying', displayAwards.length, 'filtered analysis results');
                    if (displayAwards.length > 0) {
                        displayAwards.forEach((award, index) => {
                            console.log(`Processing award ${index + 1}:`, award.category || award.name || award.title, `(${award.status})`);
                            const card = createAwardAnalysisCard(award);
                            awardAnalysisList.appendChild(card);
                        });
                    } else {
                        // Fallback: Show any results with a score > 0, even if status doesn't match expected categories
                        console.log('No results found with standard categories, trying fallback with any results with score > 0');
                        const fallbackAwards = data.analysis.filter(award => (award.score || 0) > 0);
                        console.log('Fallback found', fallbackAwards.length, 'results with score > 0');

                        if (fallbackAwards.length > 0) {
                            fallbackAwards.slice(0, 5).forEach((award, index) => {
                                console.log(`Processing fallback award ${index + 1}:`, award.category || award.name || award.title, `(${award.status})`);
                                const card = createAwardAnalysisCard(award);
                                awardAnalysisList.appendChild(card);
                            });
                        } else {
                            console.log('No analysis results found after fallback');
                            awardAnalysisList.innerHTML = `
                                <div class="text-center py-8">
                                    <div class="material-symbols-outlined text-6xl text-gray-400 mb-4">search_off</div>
                                    <p class="text-text-muted-light dark:text-text-muted-dark text-lg mb-2">No Award Matches Found</p>
                                    <p class="text-text-muted-light dark:text-text-muted-dark">Your document didn't match the criteria for any ICONS Awards. Consider revising the content to better align with award requirements.</p>
                                </div>
                            `;
                        }
                    }
                } else {
                    console.log('No analysis results found in data');
                    awardAnalysisList.innerHTML = '<p class="text-text-muted-light dark:text-text-muted-dark">No analysis results available.</p>';
                }
            }


            // Scroll to results
            results.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function createAwardAnalysisCard(award) {
            const card = document.createElement('div');
            card.className = 'bg-card-light dark:bg-card-dark/50 rounded-xl p-6 border border-border-light dark:border-border-dark';

            // Determine status based on match_percentage
            const matchPct = award.match_percentage || award.score || 0;
            let statusColor, statusIcon, statusMessage, eligibilityStatus;

            if (matchPct >= 90) {
                statusColor = 'green';
                statusIcon = 'check_circle';
                eligibilityStatus = 'Eligible';
                statusMessage = `🎉 Excellent! Your document qualifies for the ${award.category_name || award.title}. You should apply for this award!`;
            } else if (matchPct >= 70) {
                statusColor = 'yellow';
                statusIcon = 'warning';
                eligibilityStatus = 'Almost Eligible';
                statusMessage = `👍 Good potential for the ${award.category_name || award.title}. With some refinements, you could be eligible for this award.`;
            } else {
                statusColor = 'red';
                statusIcon = 'cancel';
                eligibilityStatus = 'Not Eligible';
                statusMessage = `Your document doesn't currently meet the criteria for the ${award.category_name || award.title}. Consider revising to better align with the award requirements.`;
            }

            // Score is already in percent 0..100
            const displayScore = Number(matchPct).toFixed(1);
            const widthScore = Math.min(Number(matchPct), 100);

            // Build keyword checklist from matched_keywords and missing_keywords
            const matchedKeywords = award.matched_keywords || [];
            const missingKeywords = award.missing_keywords || [];
            const totalKeywords = award.total_keywords || (matchedKeywords.length + missingKeywords.length);
            const keywordMatchCount = award.keyword_match_count || matchedKeywords.length;

            let keywordChecklistBlock = '';
            if (matchedKeywords.length > 0 || missingKeywords.length > 0) {
                keywordChecklistBlock = `
                    <div class="mt-3">
                        <details class="group" open>
                            <summary class="text-sm cursor-pointer select-none text-primary font-medium">
                                Keyword Analysis (${keywordMatchCount} of ${totalKeywords} keywords matched)
                            </summary>
                            <div class="mt-2 space-y-1">
                                ${matchedKeywords.map(keyword => `
                                    <div class="flex items-start gap-2 text-sm">
                                        <span class="mt-0.5 text-green-600">✅</span>
                                        <span class="text-gray-800 dark:text-gray-200">${keyword}</span>
                                    </div>
                                `).join('')}
                                ${missingKeywords.map(keyword => `
                                    <div class="flex items-start gap-2 text-sm">
                                        <span class="mt-0.5 text-gray-400">⚪</span>
                                        <span class="text-gray-500 dark:text-gray-400">${keyword} (missing)</span>
                                    </div>
                                `).join('')}
                            </div>
                        </details>
                    </div>
                `;
            }

            card.innerHTML = `
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <h4 class="text-lg font-bold text-text-light dark:text-text-dark mb-1">${award.category_name || award.title || award.category}</h4>
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-text-muted-light dark:text-text-muted-dark">${award.award_type || 'Award Type'}</span>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary/10 text-primary">${displayScore}%</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-${statusColor}-500">${statusIcon}</span>
                        <span class="text-sm font-bold text-${statusColor}-600">${eligibilityStatus}</span>
                    </div>
                </div>

                <div class="mb-4">
                    <div class="flex justify-between items-center text-sm mb-1">
                        <span class="text-text-light dark:text-text-dark">Match Confidence</span>
                        <span class="font-medium text-${statusColor}-600">${displayScore}% (${award.confidence || 'medium'})</span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                        <div class="bg-${statusColor}-500 h-2 rounded-full transition-all duration-500" style="width: ${widthScore}%"></div>
                    </div>
                </div>

                <div class="mb-4 p-3 rounded-lg bg-${statusColor}-50 dark:bg-${statusColor}-900/20 border border-${statusColor}-200 dark:border-${statusColor}-800">
                    <div class="flex items-start gap-2">
                        <span class="material-symbols-outlined text-${statusColor}-600 text-lg mt-0.5">${statusIcon}</span>
                        <p class="text-sm text-${statusColor}-800 dark:text-${statusColor}-200 leading-relaxed">${statusMessage}</p>
                    </div>
                </div>

                ${keywordChecklistBlock}
            `;

            return card;
        }

        // Modal functions
        function toggleExtractedText() {
            const modal = document.getElementById('extracted-text-modal');
            modal.classList.remove('hidden');
        }

        function closeExtractedTextModal() {
            const modal = document.getElementById('extracted-text-modal');
            modal.classList.add('hidden');
        }

        function exportExtractedText() {
            const text = document.getElementById('full-extracted-text').textContent;
            const blob = new Blob([text], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'extracted-text.txt';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        function exportAnalysis(format) {
            const analysisId = window.currentAnalysisId;
            if (!analysisId) {
                alert('No analysis data available to export');
                return;
            }

            // Show loading state
            const button = event.target.closest('button');
            const originalText = button.innerHTML;
            button.innerHTML = '<span class="material-symbols-outlined animate-spin">hourglass_empty</span> Exporting...';
            button.disabled = true;

            fetch('api/export-analysis.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    analysis_id: analysisId,
                    format: format,
                    include_extracted_text: true
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Create download link
                        const a = document.createElement('a');
                        a.href = data.download_url;
                        a.download = data.filename;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                    } else {
                        throw new Error(data.error || 'Export failed');
                    }
                })
                .catch(error => {
                    alert('Export failed: ' + error.message);
                })
                .finally(() => {
                    // Reset button state
                    button.innerHTML = originalText;
                    button.disabled = false;
                });
        }
    </script>

    <!-- OCR and PDF libs -->
    <!-- Tesseract.js removed - using server-side OCR instead -->
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <!-- External awards.js script removed to prevent conflicts with inline form handling -->

    <script>
        // XAMPP/Apache version - no port redirect needed
        (function () {
            console.log('LILAC Awards - Running on XAMPP/Apache');
        })();

        // Analytics Dashboard Functions
        function initializeAnalyticsCharts() {
            // Status Analysis Donut Chart
            const statusCtx = document.getElementById('statusChart');
            if (statusCtx) {
                // Destroy existing chart if it exists
                if (window.statusChartInstance) {
                    window.statusChartInstance.destroy();
                }

                const isDarkMode = document.documentElement.classList.contains('dark');
                const textColor = isDarkMode ? '#ffffff' : '#0f172a';

                window.statusChartInstance = new Chart(statusCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Fully Met', 'Partially Met', 'Under Review', 'Unqualified/Rejected'],
                        datasets: [{
                            data: [45, 25, 20, 10],
                            backgroundColor: ['#3b82f6', '#a78bfa', '#facc15', '#ef4444'],
                            borderWidth: 0,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        aspectRatio: 1.2,
                        animation: false,
                        cutout: '75%',
                        layout: {
                            padding: {
                                top: 10,
                                bottom: 10,
                                left: 10,
                                right: 10
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    padding: 20,
                                    color: textColor,
                                    font: {
                                        size: 12,
                                        family: 'Inter',
                                        color: textColor
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        let label = context.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.parsed !== null) {
                                            label += context.parsed + '%';
                                        }
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Award Fulfillment Trends Chart
            const trendsCtx = document.getElementById('trendsChart');
            if (trendsCtx) {
                // Destroy existing chart if it exists
                if (window.trendsChartInstance) {
                    window.trendsChartInstance.destroy();
                }

                const isDarkMode = document.documentElement.classList.contains('dark');
                const textColor = isDarkMode ? '#ffffff' : '#0f172a';
                const gridColor = isDarkMode ? '#334155' : '#e2e8f0';
                const tickColor = isDarkMode ? '#94a3b8' : '#64748b';

                window.trendsChartInstance = new Chart(trendsCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'],
                        datasets: [
                            {
                                label: 'Eligible Submissions',
                                data: [10, 18, 12, 5, 8, 11, 14, 15, 12, 11],
                                backgroundColor: '#2563eb',
                                borderColor: '#2563eb',
                                borderWidth: 0,
                                barPercentage: 0.9,
                                categoryPercentage: 0.8,
                            },
                            {
                                label: 'Total Submissions',
                                data: [12, 20, 15, 6, 10, 13, 16, 17, 14, 13],
                                backgroundColor: 'rgba(148, 163, 184, 0.6)',
                                borderColor: 'rgb(148, 163, 184)',
                                borderWidth: 0,
                                barPercentage: 0.9,
                                categoryPercentage: 0.8,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 25,
                                grid: {
                                    color: gridColor,
                                    drawBorder: false,
                                },
                                ticks: {
                                    color: tickColor,
                                    stepSize: 5,
                                }
                            },
                            x: {
                                grid: {
                                    display: false,
                                },
                                ticks: {
                                    color: tickColor,
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    padding: 20,
                                    color: textColor,
                                    font: {
                                        size: 12,
                                        family: 'Inter',
                                        color: textColor
                                    }
                                }
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                            }
                        }
                    }
                });
            }

            // Award Category Performance Radar Chart
            const radarCtx = document.getElementById('radarChart');
            if (radarCtx) {
                // Destroy existing chart if it exists
                if (window.radarChartInstance) {
                    window.radarChartInstance.destroy();
                }

                window.radarChartInstance = new Chart(radarCtx.getContext('2d'), {
                    type: 'radar',
                    data: {
                        labels: ['Leadership', 'Innovation', 'Sustainability', 'Global Citizenship', 'Research', 'Teaching Excellence'],
                        datasets: [{
                            label: 'Your Performance',
                            data: [85, 72, 90, 68, 78, 82],
                            backgroundColor: 'rgba(59, 130, 246, 0.2)',
                            borderColor: 'rgba(59, 130, 246, 1)',
                            borderWidth: 2,
                            pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                            pointBorderColor: '#fff',
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: 'rgba(59, 130, 246, 1)'
                        }, {
                            label: 'Institutional Average',
                            data: [70, 65, 75, 72, 68, 70],
                            backgroundColor: 'rgba(148, 163, 184, 0.1)',
                            borderColor: 'rgba(148, 163, 184, 0.5)',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            pointBackgroundColor: 'rgba(148, 163, 184, 0.5)',
                            pointBorderColor: '#fff',
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: 'rgba(148, 163, 184, 1)'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        animation: {
                            duration: 1000,
                            easing: 'easeInOutQuart'
                        },
                        scales: {
                            r: {
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                    stepSize: 20,
                                    color: '#64748b',
                                    backdropColor: 'transparent'
                                },
                                grid: {
                                    color: 'rgba(148, 163, 184, 0.2)'
                                },
                                pointLabels: {
                                    color: '#64748b',
                                    font: {
                                        size: 11,
                                        family: 'Inter'
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    padding: 15,
                                    font: {
                                        size: 11,
                                        family: 'Inter'
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.parsed.r !== null) {
                                            label += context.parsed.r + '%';
                                        }
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }

        // Modal functions
        function showModal(title, content) {
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalContent').innerHTML = content;
            document.getElementById('kpiModal').classList.remove('hidden');

            // Hide X button for "Details & Requirements" modal
            const closeButton = document.getElementById('modalCloseButton');
            if (title.includes('Details & Requirements')) {
                closeButton.style.display = 'none';
            } else {
                closeButton.style.display = 'block';
            }
        }

        function closeModal() {
            document.getElementById('kpiModal').classList.add('hidden');
        }

        // Award Details Function
        async function showAwardDetails(awardName) {
            try {
                // Fetch detailed submissions for this award category
                const response = await fetch(`api/award-category-stats.php?category=${encodeURIComponent(awardName)}`, {
                    headers: {
                        'Authorization': 'Bearer ' + AUTH_TOKEN
                    }
                });

                if (!response.ok) throw new Error('Failed to fetch award details');

                const result = await response.json();

                if (!result.success) throw new Error(result.error || 'Failed to load details');

                const { stats, submissions } = result;

                // Build detailed modal content
                let content = `
                    <div class="space-y-6">
                        <div class="grid grid-cols-4 gap-4">
                            <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">${stats.total}</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Total Submissions</div>
                            </div>
                            <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-green-600 dark:text-green-400">${stats.eligible}</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Eligible</div>
                            </div>
                            <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">${stats.almost_eligible}</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Almost Eligible</div>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-900/20 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-gray-600 dark:text-gray-400">${stats.pending}</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Pending Review</div>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <h4 class="font-semibold text-gray-900 dark:text-white">All Submissions:</h4>`;

                submissions.forEach((sub, index) => {
                    const statusColor = sub.status === 'Eligible' ? 'green' :
                                      sub.status === 'Almost Eligible' ? 'yellow' : 'gray';
                    const statusBg = `bg-${statusColor}-100 dark:bg-${statusColor}-900/30`;
                    const statusText = `text-${statusColor}-700 dark:text-${statusColor}-400`;
                    const submissionSimilarity = Math.round((sub.similarity_score || sub.match_percentage / 100) * 100);
                    const unmatchedList = normalizeCriteriaList(sub.unmatched_criteria);
                    const submissionGuidance = buildGuidanceTips(
                        unmatchedList,
                        submissionSimilarity,
                        awardName
                    );

                    content += `
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1">
                                    <h5 class="font-medium text-gray-900 dark:text-white">${sub.title}</h5>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Submitted by: ${sub.username || 'Unknown'}</p>
                                </div>
                                <span class="px-3 py-1 ${statusBg} ${statusText} rounded-full text-xs font-medium">
                                    ${sub.status}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-4 mt-3">
                                <div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Similarity Score</div>
                                    <div class="text-lg font-semibold text-gray-900 dark:text-white">${submissionSimilarity}%</div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Matched Criteria</div>
                                    <div class="text-lg font-semibold text-gray-900 dark:text-white">${sub.criteria_met || 0}/${sub.criteria_total || 0}</div>
                                </div>
                            </div>

                            ${sub.matched_criteria && sub.matched_criteria.length > 0 ? `
                                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                                    <div class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">Matched Criteria:</div>
                                    <div class="flex flex-wrap gap-1">
                                        ${sub.matched_criteria.map(c => `
                                            <span class="px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded text-xs">
                                                ✓ ${c}
                                            </span>
                                        `).join('')}
                                    </div>
                                </div>
                            ` : ''}

                            ${unmatchedList && unmatchedList.length > 0 ? `
                                <div class="mt-2">
                                    <div class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">Unmatched Criteria:</div>
                                    <div class="flex flex-wrap gap-1">
                                        ${unmatchedList.map(c => `
                                            <span class="px-2 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded text-xs">
                                                ✗ ${c}
                                            </span>
                                        `).join('')}
                                    </div>
                                </div>
                            ` : ''}

                            ${submissionGuidance}
                        </div>
                    `;
                });

                content += `
                        </div>
                    </div>
                `;

                showModal(`🏆 ${awardName} - Detailed View`, content);

            } catch (error) {
                console.error('Error loading award details:', error);
                showModal('Error', `<p class="text-red-600">Failed to load award details: ${error.message}</p>`);
            }
        }

        // Fallback for older static award details
        function showOldAwardDetails(awardName) {
            // Award details mapping
            const awardDetails = {
                'Global Citizenship Award': {
                    criteria: 'Global leadership, intercultural understanding, community engagement, inclusive programs, ethical governance, sustainable impact',
                    requirements: 'Documentation of global initiatives, partnership agreements, impact reports, leadership evidence',
                    progress: '4/6 criteria met',
                    nextSteps: 'Complete global partnership documentation and sustainability reports'
                },
                'Outstanding International Education Program Award': {
                    criteria: 'International education program, inclusive internationalization, collaborative innovation, student mobility, research partnership, access to global opportunities',
                    requirements: 'Program documentation, student mobility statistics, partnership agreements, impact assessment',
                    progress: '5/5 criteria met',
                    nextSteps: 'All requirements satisfied - ready for submission'
                },
                'Sustainability Award': {
                    criteria: 'Environmental sustainability initiatives, green campus programs, sustainable development goals, environmental impact reduction, sustainability education, community environmental programs',
                    requirements: 'Environmental policy documents, sustainability metrics, program reports, impact assessments',
                    progress: '3/7 criteria met',
                    nextSteps: 'Develop comprehensive environmental policy and implement sustainability education programs'
                },
                'Best ASEAN Awareness Initiative Award': {
                    criteria: 'ASEAN partnership networks, regional collaboration, ASEAN cultural programs, regional research initiatives, ASEAN student exchanges, community ASEAN awareness',
                    requirements: 'ASEAN partnership documentation, cultural program reports, exchange statistics, community engagement records',
                    progress: '6/8 criteria met',
                    nextSteps: 'Expand community ASEAN awareness programs and document regional research collaborations'
                },
                'Emerging Leadership Award': {
                    criteria: 'Leadership development, leadership excellence, innovation, strategic growth, empowering others, internationalization leadership',
                    requirements: 'Leadership program documentation, innovation projects, strategic planning documents, team development records',
                    progress: '4/5 criteria met',
                    nextSteps: 'Complete strategic growth documentation and team empowerment evidence'
                },
                'Internationalization Leadership Award': {
                    criteria: 'Internationalization leadership, institutional leadership, strategic leadership, executive leadership, transformational leader, visionary leadership, leadership excellence',
                    requirements: 'Leadership vision documents, institutional transformation records, executive leadership evidence, strategic planning documentation',
                    progress: '7/7 criteria met',
                    nextSteps: 'Exemplary internationalization leadership - ready for submission'
                },
                'Best CHED Regional Office for Internationalization Award': {
                    criteria: 'Regional office coordination, CHED compliance, regional internationalization support, regional partnership development, regional resource management',
                    requirements: 'CHED compliance documentation, regional coordination reports, partnership development records, resource management plans',
                    progress: '2/4 criteria met',
                    nextSteps: 'Strengthen regional office coordination and complete CHED compliance documentation'
                },
                'Most Promising Regional IRO Community Award': {
                    criteria: 'IRO community building, regional networking, community development, collaborative initiatives, knowledge sharing, regional growth potential',
                    requirements: 'Community development plans, networking documentation, collaboration records, knowledge sharing initiatives, growth metrics',
                    progress: '3/6 criteria met',
                    nextSteps: 'Develop comprehensive IRO community building strategy and implement knowledge sharing programs'
                }
            };

            const details = awardDetails[awardName] || {
                criteria: 'No criteria information available',
                requirements: 'No requirements information available',
                progress: 'Progress information not available',
                nextSteps: 'Contact administrator for more information'
            };

            const content = `
            <div class="space-y-6">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 dark:bg-blue-900/20 dark:border-blue-700">
                    <h3 class="font-bold text-blue-800 dark:text-blue-300 mb-4 text-lg">${awardName}</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200 mb-2">Current Progress</h4>
                            <p class="text-sm text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 p-3 rounded border">${details.progress}</p>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200 mb-2">Next Steps</h4>
                            <p class="text-sm text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 p-3 rounded border">${details.nextSteps}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 dark:bg-gray-800/50 dark:border-gray-600">
                    <h4 class="font-semibold text-gray-800 dark:text-gray-200 mb-3">Evaluation Criteria</h4>
                    <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">${details.criteria}</p>
                </div>

                <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 dark:bg-gray-800/50 dark:border-gray-600">
                    <h4 class="font-semibold text-gray-800 dark:text-gray-200 mb-3">Required Documentation</h4>
                    <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">${details.requirements}</p>
                </div>

                <div class="flex justify-between items-center pt-4 border-t border-gray-200 dark:border-gray-600">
                    <button onclick="viewFullDetailsForAward('${awardName}')" class="px-4 py-2 text-sm font-medium text-white bg-primary hover:bg-primary/90 rounded-lg transition-colors">
                        View Full Details
                    </button>
                    <div class="flex gap-3">
                        <button onclick="closeModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-lg transition-colors">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        `;

            showModal(`📋 ${awardName} - Details & Requirements`, content);
        }

        // View Full Details for Award - navigates to dedicated page with all uploads for specific award
        function viewFullDetailsForAward(awardName) {
            // Close the current modal first
            closeModal();

            // Map award names to page file names
            const awardPageMap = {
                'Global Citizenship Award': 'awards/global-citizenship-award.html',
                'Outstanding International Education Program Award': 'awards/outstanding-international-education-award.html',
                'Sustainability Award': 'awards/sustainability-award.html',
                'Best ASEAN Awareness Initiative Award': 'awards/best-asean-awareness-award.html',
                'Emerging Leadership Award': 'awards/emerging-leadership-award.html',
                'Internationalization Leadership Award': 'awards/internationalization-leadership-award.html',
                'Best CHED Regional Office for Internationalization Award': 'awards/best-ched-regional-office-award.html',
                'Most Promising Regional IRO Community Award': 'awards/most-promising-iro-community-award.html'
            };

            const pageName = awardPageMap[awardName];
            if (!pageName) {
                showNotification('Award page not found', 'error');
                return;
            }

            // Navigate to dedicated award page
            const currentPath = window.location.pathname;
            const baseUrl = currentPath.includes('/LILAC-v.2.1/') ?
                window.location.origin + '/LILAC-v.2.1/' + pageName :
                window.location.origin + '/' + pageName;

            window.location.href = baseUrl;
        }

        // Show modal with all uploads for a specific award
        function showAwardSpecificModal(awardName, matchingAwards) {
            // Create modal HTML content
            const uploadsContent = matchingAwards.map((award, index) => {
                const analysis = award.analysis || {};
                let analysisResults = [];
                try {
                    analysisResults = analysis.analysis_results ? JSON.parse(analysis.analysis_results) : [];
                } catch (e) {
                    console.warn('Error parsing analysis results:', e);
                }

                // Filter results for this specific award
                const awardResults = analysisResults.filter(result =>
                    result.award_name === awardName ||
                    result.award_category === awardName ||
                    result.category === awardName
                );

                return `
                <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 mb-4">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h4 class="font-semibold text-gray-900 dark:text-white">${award.title || 'Untitled Document'}</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">${award.file_name || 'Unknown file'}</p>
                        </div>
                        <button onclick="window.viewAward('${award.id}')" class="px-3 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">
                            View Details
                        </button>
                    </div>
                    
                    ${awardResults.length > 0 ? `
                        <div class="space-y-2">
                            <h5 class="font-medium text-gray-700 dark:text-gray-300">Analysis Results for ${awardName}:</h5>
                            ${awardResults.map(result => `
                                <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded">
                                    <div class="flex items-center justify-between">
                                        <span class="font-medium text-gray-900 dark:text-white">${result.award_name || result.category || awardName}</span>
                                        <span class="px-2 py-1 rounded text-xs font-medium ${result.status === 'Eligible' ? 'bg-green-100 text-green-800' : result.status === 'Partially Eligible' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'}">
                                            ${result.status || 'Unknown'}
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">${result.recommendation || 'No recommendation available'}</p>
                                </div>
                            `).join('')}
                        </div>
                    ` : `
                        <p class="text-sm text-gray-500 dark:text-gray-400">No analysis results available for this award category.</p>
                    `}
                </div>
            `;
            }).join('');

            const modalContent = `
            <div class="space-y-4">
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100 mb-2">${awardName}</h3>
                    <p class="text-sm text-blue-700 dark:text-blue-300">Found ${matchingAwards.length} upload(s) related to this award.</p>
                </div>
                
                <div class="max-h-96 overflow-y-auto">
                    ${uploadsContent || '<p class="text-gray-500 dark:text-gray-400 text-center py-8">No uploads found for this award.</p>'}
                </div>
            </div>
        `;

            // Create and show the modal
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
            modal.innerHTML = `
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full max-h-[80vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 bg-blue-100 dark:bg-blue-900/20 rounded-full">
                                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Award Details: ${awardName}</h2>
                        </div>
                        <button onclick="this.closest('.fixed').remove()" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                            <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    
                    <div class="modal-content">
                        ${modalContent}
                    </div>
                </div>
            </div>
        `;

            document.body.appendChild(modal);
        }

        // KPI Click Functions
        function showAwardsMet() {
            const data = window.analyticsData;
            if (!data || !data.requirements) {
                showModal('🏆 Total Awards Met', '<p class="text-gray-600">No awards data available.</p>');
                return;
            }

            const requirements = data.requirements;
            const totalCount = requirements.length;

            let tableRows = '';
            if (totalCount === 0) {
                tableRows = '<tr><td colspan="4" class="py-4 text-center text-gray-500">No awards submitted yet.</td></tr>';
            } else {
                requirements.forEach(req => {
                    const matchPct = parseFloat(req.match_percentage || 0);
                    const criteriaMet = req.criteria_met || 0;
                    const criteriaTotal = req.criteria_total || 0;
                    const dateAchieved = new Date(req.created_at).toLocaleDateString();
                    const awardName = req.award_name || 'Unknown Award';
                    const submissionTitle = req.submission_title || 'Untitled';

                    // Status badge based on match percentage
                    let statusBadge = '';
                    if (matchPct >= 90) {
                        statusBadge = '<span class="px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded text-xs font-medium">✅ Eligible</span>';
                    } else if (matchPct >= 70) {
                        statusBadge = '<span class="px-2 py-1 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-200 rounded text-xs font-medium">🟡 Almost Eligible</span>';
                    } else {
                        statusBadge = '<span class="px-2 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded text-xs font-medium">❌ Not Eligible</span>';
                    }

                    tableRows += `
                        <tr class="border-b border-green-100 dark:border-green-800/50 hover:bg-green-50 dark:hover:bg-green-900/20">
                            <td class="py-3 text-left text-gray-800 dark:text-gray-100 font-medium">${awardName}</td>
                            <td class="py-3 text-center text-gray-600 dark:text-gray-300 text-sm">${submissionTitle}</td>
                            <td class="py-3 text-center text-gray-600 dark:text-gray-300 text-sm">${dateAchieved}</td>
                            <td class="py-3 text-center text-gray-700 dark:text-gray-200 font-medium">${criteriaMet}/${criteriaTotal}</td>
                            <td class="py-3 text-center">${statusBadge}</td>
                        </tr>
                    `;
                });
            }

            const content = `
                <div class="space-y-4">
                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                        <h3 class="font-semibold text-green-800 dark:text-green-300 mb-3">Total Awards Submitted (${totalCount})</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            All your award submissions with criteria matching results
                        </p>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b-2 border-green-300 dark:border-green-700">
                                        <th class="text-left py-2 px-2 font-medium text-green-800 dark:text-green-300">Award Name</th>
                                        <th class="text-center py-2 px-2 font-medium text-green-800 dark:text-green-300">Submission Title</th>
                                        <th class="text-center py-2 px-2 font-medium text-green-800 dark:text-green-300">Date Submitted</th>
                                        <th class="text-center py-2 px-2 font-medium text-green-800 dark:text-green-300">Criteria Met</th>
                                        <th class="text-center py-2 px-2 font-medium text-green-800 dark:text-green-300">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${tableRows}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
            showModal('🏆 Total Awards Met - Detailed View', content);
        }

        function showDocumentsProcessed() {
            const content = `
            <div class="space-y-4">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h3 class="font-semibold text-blue-800 mb-3">Processed Documents (1,204)</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div class="bg-white p-3 rounded border">
                            <p class="text-2xl font-bold text-blue-600">856</p>
                            <p class="text-xs text-gray-600">Approved</p>
                        </div>
                        <div class="bg-white p-3 rounded border">
                            <p class="text-2xl font-bold text-yellow-600">248</p>
                            <p class="text-xs text-gray-600">Pending</p>
                        </div>
                        <div class="bg-white p-3 rounded border">
                            <p class="text-2xl font-bold text-red-600">100</p>
                            <p class="text-xs text-gray-600">Rejected</p>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-blue-200">
                                    <th class="text-left py-2 font-medium text-blue-800">Document Title</th>
                                    <th class="text-left py-2 font-medium text-blue-800">Type</th>
                                    <th class="text-left py-2 font-medium text-blue-800">Department</th>
                                    <th class="text-left py-2 font-medium text-blue-800">Status</th>
                                    <th class="text-left py-2 font-medium text-blue-800">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="border-b border-blue-100">
                                    <td class="py-2 text-gray-700">Partnership Agreement - UTokyo</td>
                                    <td class="py-2 text-gray-700">MOU</td>
                                    <td class="py-2 text-gray-700">International Affairs</td>
                                    <td class="py-2"><span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">Approved</span></td>
                                    <td class="py-2 text-gray-700">2024-11-15</td>
                                </tr>
                                <tr class="border-b border-blue-100">
                                    <td class="py-2 text-gray-700">Faculty Exchange Program</td>
                                    <td class="py-2 text-gray-700">Award Criteria</td>
                                    <td class="py-2 text-gray-700">College of Engineering</td>
                                    <td class="py-2"><span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">Pending</span></td>
                                    <td class="py-2 text-gray-700">2024-11-20</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
            showModal('📄 Documents Processed - Audit View', content);
        }

        function showEligibleDepartments() {
            const data = window.analyticsData;
            if (!data || !data.requirements) {
                showModal('✅ Awards Eligible For', '<p class="text-gray-600">No awards data available.</p>');
                return;
            }

            // Filter awards where match_percentage >= 90%
            const eligibleAwards = data.requirements.filter(req => parseFloat(req.match_percentage || 0) >= 90);
            const totalCount = eligibleAwards.length;

            let tableRows = '';
            if (totalCount === 0) {
                tableRows = '<tr><td colspan="4" class="py-4 text-center text-gray-500">No eligible awards yet. Keep submitting to reach 90% match!</td></tr>';
            } else {
                eligibleAwards.forEach(req => {
                    const matchPct = parseFloat(req.match_percentage || 0).toFixed(1);
                    const criteriaMet = req.criteria_met || 0;
                    const criteriaTotal = req.criteria_total || 0;
                    const dateSubmitted = new Date(req.created_at).toLocaleDateString();
                    const awardName = req.award_name || 'Unknown Award';
                    const submissionTitle = req.submission_title || 'Untitled';

                    tableRows += `
                        <tr class="border-b border-green-100 hover:bg-green-50">
                            <td class="py-3 text-gray-800 font-medium">${awardName}</td>
                            <td class="py-3 text-gray-600 text-sm">${submissionTitle}</td>
                            <td class="py-3 text-gray-700 font-medium">${criteriaMet}/${criteriaTotal}</td>
                            <td class="py-3">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 bg-gray-200 rounded-full h-2">
                                        <div class="bg-green-500 h-2 rounded-full transition-all" style="width: ${matchPct}%"></div>
                                    </div>
                                    <span class="text-sm font-semibold text-green-600">${matchPct}%</span>
                                </div>
                            </td>
                        </tr>
                    `;
                });
            }

            const content = `
                <div class="space-y-4">
                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                        <h3 class="font-semibold text-green-800 dark:text-green-300 mb-3">Awards You're Eligible For (${totalCount})</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            These awards have a match percentage of 90% or higher based on your submissions
                        </p>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b-2 border-green-300 dark:border-green-700">
                                        <th class="text-left py-2 px-2 font-medium text-green-800 dark:text-green-300">Award Name</th>
                                        <th class="text-left py-2 px-2 font-medium text-green-800 dark:text-green-300">Submission Title</th>
                                        <th class="text-left py-2 px-2 font-medium text-green-800 dark:text-green-300">Criteria Met</th>
                                        <th class="text-left py-2 px-2 font-medium text-green-800 dark:text-green-300">Match %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${tableRows}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
            showModal('✅ Awards Eligible For - Detailed View', content);
        }

        function showEligibleDepartmentsOld() {
            const content = `
            <div class="space-y-4">
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                    <h3 class="font-semibold text-purple-800 mb-3">Eligible Departments (8)</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-white p-4 rounded border">
                            <h4 class="font-semibold text-gray-800 mb-2">College of Business</h4>
                            <p class="text-sm text-gray-600 mb-2">Awards Targeted: 3</p>
                            <div class="mb-2">
                                <div class="flex justify-between text-xs mb-1">
                                    <span>Progress</span>
                                    <span>4/5</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-purple-500 h-2 rounded-full" style="width: 80%"></div>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500">Assigned: Dr. Smith, Dr. Johnson</p>
                        </div>
                        <div class="bg-white p-4 rounded border">
                            <h4 class="font-semibold text-gray-800 mb-2">College of Education</h4>
                            <p class="text-sm text-gray-600 mb-2">Awards Targeted: 2</p>
                            <div class="mb-2">
                                <div class="flex justify-between text-xs mb-1">
                                    <span>Progress</span>
                                    <span>5/5</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-500 h-2 rounded-full" style="width: 100%"></div>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500">Assigned: Dr. Davis, Dr. Wilson</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
            showModal('🏫 Eligible Departments - Progress Tracking', content);
        }

        function showActiveCycle() {
            const content = `
            <div class="space-y-4">
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <h3 class="font-semibold text-yellow-800 mb-3">2024-25 Active Cycle Timeline</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-3">Cycle Information</h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Start Date:</span>
                                    <span class="font-medium">September 1, 2024</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">End Date:</span>
                                    <span class="font-medium">August 31, 2025</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Days Remaining:</span>
                                    <span class="font-medium text-orange-600">247 days</span>
                                </div>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800 mb-3">Key Deadlines</h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Mid-cycle Review:</span>
                                    <span class="font-medium">March 15, 2025</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Final Submissions:</span>
                                    <span class="font-medium">July 31, 2025</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 p-3 bg-white rounded border">
                        <h4 class="font-semibold text-gray-800 mb-2">Cycle Summary</h4>
                        <div class="grid grid-cols-3 gap-4 text-center">
                            <div>
                                <p class="text-2xl font-bold text-blue-600">12</p>
                                <p class="text-xs text-gray-600">Awards Met</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-green-600">45</p>
                                <p class="text-xs text-gray-600">Submissions</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-purple-600">8</p>
                                <p class="text-xs text-gray-600">Departments</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
            showModal('📆 Active Cycle (2024-25) - Timeline & Progress', content);
        }

        function showFacultyInvolved() {
            const content = `
            <div class="space-y-4">
                <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                    <h3 class="font-semibold text-indigo-800 mb-3">Faculty Participants (25)</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-indigo-200">
                                    <th class="text-left py-2 font-medium text-indigo-800">Faculty Name</th>
                                    <th class="text-left py-2 font-medium text-indigo-800">Department</th>
                                    <th class="text-left py-2 font-medium text-indigo-800">Awards Contributed</th>
                                    <th class="text-left py-2 font-medium text-indigo-800">Documents</th>
                                    <th class="text-left py-2 font-medium text-indigo-800">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="border-b border-indigo-100">
                                    <td class="py-2 text-gray-700">Dr. Sarah Johnson</td>
                                    <td class="py-2 text-gray-700">Business</td>
                                    <td class="py-2 text-gray-700">3</td>
                                    <td class="py-2 text-gray-700">12</td>
                                    <td class="py-2"><span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">Active</span></td>
                                </tr>
                                <tr class="border-b border-indigo-100">
                                    <td class="py-2 text-gray-700">Dr. Michael Chen</td>
                                    <td class="py-2 text-gray-700">Engineering</td>
                                    <td class="py-2 text-gray-700">2</td>
                                    <td class="py-2 text-gray-700">8</td>
                                    <td class="py-2"><span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">Active</span></td>
                                </tr>
                                <tr class="border-b border-indigo-100">
                                    <td class="py-2 text-gray-700">Dr. Emily Rodriguez</td>
                                    <td class="py-2 text-gray-700">Education</td>
                                    <td class="py-2 text-gray-700">4</td>
                                    <td class="py-2 text-gray-700">15</td>
                                    <td class="py-2"><span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">Pending</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
            showModal('👩‍🏫 Faculty Involved - Participation Details', content);
        }

        function showNewPartnerships() {
            const data = window.analyticsData;
            if (!data || !data.requirements) {
                showModal('📄 ORC Data Analyzed', '<p class="text-gray-600">No awards data available.</p>');
                return;
            }

            const requirements = data.requirements;
            const totalCount = requirements.length;

            // Count documents with files
            const documentsWithFiles = requirements.filter(req => req.file_name || req.file_path);
            const documentsCount = documentsWithFiles.length;

            let tableRows = '';
            if (totalCount === 0) {
                tableRows = '<tr><td colspan="5" class="py-4 text-center text-gray-500">No documents analyzed yet.</td></tr>';
            } else {
                requirements.forEach(req => {
                    const matchPct = parseFloat(req.match_percentage || 0).toFixed(1);
                    const dateAnalyzed = new Date(req.created_at).toLocaleDateString();
                    const awardName = req.award_name || 'Unknown Award';
                    const submissionTitle = req.submission_title || 'Untitled';

                    // Determine if file was uploaded
                    const hasFile = req.file_name || req.file_path;
                    const fileIcon = hasFile ? '📄' : '📝';
                    const fileText = hasFile ? 'Document' : 'Text Only';

                    // Status badge
                    let statusBadge = '';
                    if (matchPct >= 90) {
                        statusBadge = '<span class="px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded text-xs font-medium">✅ Eligible</span>';
                    } else if (matchPct >= 70) {
                        statusBadge = '<span class="px-2 py-1 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-200 rounded text-xs font-medium">🟡 Almost</span>';
                    } else {
                        statusBadge = '<span class="px-2 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded text-xs font-medium">❌ Not Eligible</span>';
                    }

                    tableRows += `
                        <tr class="border-b border-blue-100 dark:border-blue-800/60 hover:bg-blue-50 dark:hover:bg-blue-900/20">
                            <td class="py-3 text-gray-800 dark:text-gray-100 font-medium">${submissionTitle}</td>
                            <td class="py-3 text-gray-600 dark:text-gray-300 text-sm">${awardName}</td>
                            <td class="py-3 text-gray-600 dark:text-gray-300 text-sm">${fileIcon} ${fileText}</td>
                            <td class="py-3 text-gray-600 dark:text-gray-300 text-sm">${dateAnalyzed}</td>
                            <td class="py-3">${statusBadge}</td>
                        </tr>
                    `;
                });
            }

            const content = `
                <div class="space-y-4">
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                        <h3 class="font-semibold text-blue-800 dark:text-blue-300 mb-3">ORC Data Analyzed (${totalCount})</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Documents and text submissions processed through optical character recognition and keyword analysis
                        </p>
                        <div class="grid grid-cols-3 gap-4 mb-4">
                            <div class="bg-white dark:bg-gray-800 p-3 rounded border border-blue-200 dark:border-blue-700">
                                <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">${totalCount}</p>
                                <p class="text-xs text-gray-600 dark:text-gray-400">Total Analyzed</p>
                            </div>
                            <div class="bg-white dark:bg-gray-800 p-3 rounded border border-blue-200 dark:border-blue-700">
                                <p class="text-2xl font-bold text-green-600 dark:text-green-400">${documentsCount}</p>
                                <p class="text-xs text-gray-600 dark:text-gray-400">With Documents</p>
                            </div>
                            <div class="bg-white dark:bg-gray-800 p-3 rounded border border-blue-200 dark:border-blue-700">
                                <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">${totalCount - documentsCount}</p>
                                <p class="text-xs text-gray-600 dark:text-gray-400">Text Only</p>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b-2 border-blue-300 dark:border-blue-700">
                                        <th class="text-left py-2 px-2 font-medium text-blue-800 dark:text-blue-300">Submission Title</th>
                                        <th class="text-left py-2 px-2 font-medium text-blue-800 dark:text-blue-300">Award Category</th>
                                        <th class="text-left py-2 px-2 font-medium text-blue-800 dark:text-blue-300">Type</th>
                                        <th class="text-left py-2 px-2 font-medium text-blue-800 dark:text-blue-300">Date Analyzed</th>
                                        <th class="text-left py-2 px-2 font-medium text-blue-800 dark:text-blue-300">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${tableRows}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
            showModal('📄 ORC Data Analyzed - Detailed View', content);
        }

        function showNewPartnershipsOld() {
            const content = `
            <div class="space-y-4">
                <div class="bg-teal-50 border border-teal-200 rounded-lg p-4">
                    <h3 class="font-semibold text-teal-800 mb-3">New Partnerships (15)</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-white p-4 rounded border">
                            <h4 class="font-semibold text-gray-800 mb-2">University of Tokyo</h4>
                            <p class="text-sm text-gray-600 mb-2">Type: International Academic</p>
                            <p class="text-sm text-gray-600 mb-2">Date Signed: 2024-10-15</p>
                            <p class="text-sm text-gray-600 mb-2">Related Awards: Global Citizenship</p>
                            <a href="#" class="text-blue-600 hover:underline text-sm">View Agreement →</a>
                        </div>
                        <div class="bg-white p-4 rounded border">
                            <h4 class="font-semibold text-gray-800 mb-2">Microsoft Philippines</h4>
                            <p class="text-sm text-gray-600 mb-2">Type: Industry Partnership</p>
                            <p class="text-sm text-gray-600 mb-2">Date Signed: 2024-11-02</p>
                            <p class="text-sm text-gray-600 mb-2">Related Awards: Innovation Award</p>
                            <a href="#" class="text-blue-600 hover:underline text-sm">View Agreement →</a>
                        </div>
                    </div>
                </div>
            </div>
        `;
            showModal('🤝 New Partnerships - Collaboration Overview', content);
        }

        function showPendingReviews() {
            const content = `
            <div class="space-y-4">
                <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                    <h3 class="font-semibold text-orange-800 mb-3">Pending Reviews (3)</h3>
                    <div class="space-y-3">
                        <div class="bg-white p-4 rounded border border-orange-200">
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="font-semibold text-gray-800">Innovation in Global Learning</h4>
                                <span class="px-2 py-1 bg-orange-100 text-orange-800 rounded text-xs">Under Review</span>
                            </div>
                            <p class="text-sm text-gray-600 mb-2">Department: College of Engineering</p>
                            <p class="text-sm text-gray-600 mb-2">Submitted: 2024-11-18</p>
                            <p class="text-sm text-gray-600 mb-2">Reviewer: Dr. Anderson</p>
                            <p class="text-sm text-gray-600">Expected: 2024-12-02</p>
                        </div>
                        <div class="bg-white p-4 rounded border border-orange-200">
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="font-semibold text-gray-800">Community Engagement Award</h4>
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">Initial Review</span>
                            </div>
                            <p class="text-sm text-gray-600 mb-2">Department: College of Arts & Sciences</p>
                            <p class="text-sm text-gray-600 mb-2">Submitted: 2024-11-20</p>
                            <p class="text-sm text-gray-600 mb-2">Reviewer: Dr. Martinez</p>
                            <p class="text-sm text-gray-600">Expected: 2024-12-05</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
            showModal('🚩 Pending Reviews - Action Items', content);
        }


        // Chart update functions
        function updateStatusChart(statusData, hasUploads = null) {
            const statusCtx = document.getElementById('statusChart');
            if (!statusCtx) {
                console.error('Status chart canvas not found');
                return;
            }

            console.log('Updating status chart with data:', statusData, 'hasUploads:', hasUploads);

            // Destroy existing chart if it exists
            if (window.statusChartInstance) {
                window.statusChartInstance.destroy();
            }

            const isDarkMode = document.documentElement.classList.contains('dark');
            const textColor = isDarkMode ? '#e2e8f0' : '#0f172a';

            // Use provided hasUploads flag or calculate it from data
            const hasAnyUploads = hasUploads !== null ? hasUploads : (statusData.fully_met + statusData.partially_met + statusData.under_review) > 0;

            let chartLabels, chartData, chartColors;

            if (!hasAnyUploads) {
                // Empty state - show gray circle like empty progress bar
                chartLabels = ['No Uploads Yet'];
                chartData = [100];
                chartColors = [isDarkMode ? '#374151' : '#d1d5db']; // Gray color
            } else {
                // Have uploads - show status breakdown
                chartLabels = ['Fully Met', 'Partially Met', 'Under Review', 'Unqualified/Rejected'];
                chartData = [
                    statusData.fully_met || 0,
                    statusData.partially_met || 0,
                    statusData.under_review || 0,
                    statusData.unqualified || 0
                ];
                chartColors = ['#3b82f6', '#a78bfa', '#facc15', '#ef4444'];
            }

            window.statusChartInstance = new Chart(statusCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        data: chartData,
                        backgroundColor: chartColors,
                        borderWidth: 0,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 1.2,
                    animation: false,
                    cutout: '75%',
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle',
                                padding: 20,
                                color: function(context) {
                                    return textColor;
                                },
                                font: {
                                    size: 12,
                                    family: 'Inter',
                                    color: textColor
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed !== null) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = total > 0 ? Math.round((context.parsed / total) * 100) : 0;
                                        label += context.parsed + ' (' + percentage + '%)';
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }

        function updateTrendsChart(trendsData) {
            const trendsCtx = document.getElementById('trendsChart');
            if (!trendsCtx) return;

            // Destroy existing chart if it exists
            if (window.trendsChartInstance) {
                window.trendsChartInstance.destroy();
            }

            const isDarkMode = document.documentElement.classList.contains('dark');
            const textColor = isDarkMode ? '#e2e8f0' : '#0f172a';
            const gridColor = isDarkMode ? '#334155' : '#e2e8f0';
            const tickColor = isDarkMode ? '#94a3b8' : '#64748b';

            // Calculate max value for Y-axis based on actual data
            const maxEligible = Math.max(...(trendsData.eligible || [0]), 0);
            const maxTotal = Math.max(...(trendsData.total || [0]), 0);
            const maxValue = Math.max(maxEligible, maxTotal, 1);
            // Round up to nearest even number and ensure minimum of 10
            const yAxisMax = Math.max(Math.ceil(maxValue / 2) * 2, 10);

            window.trendsChartInstance = new Chart(trendsCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: trendsData.labels || ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'],
                    datasets: [
                        {
                            label: 'Eligible Submissions',
                            data: trendsData.eligible || [],
                            backgroundColor: '#2563eb',
                            borderColor: '#2563eb',
                            borderWidth: 0,
                            barPercentage: 0.9,
                            categoryPercentage: 0.8,
                        },
                        {
                            label: 'Total Submissions',
                            data: trendsData.total || [],
                            backgroundColor: '#6b7280',
                            borderColor: '#6b7280',
                            borderWidth: 0,
                            barPercentage: 0.9,
                            categoryPercentage: 0.8,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle',
                                padding: 20,
                                color: function(context) {
                                    return textColor;
                                },
                                font: {
                                    size: 12,
                                    family: 'Inter',
                                    color: textColor
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: tickColor,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: gridColor
                            },
                            max: yAxisMax,
                            ticks: {
                                color: tickColor,
                                font: {
                                    size: 11
                                },
                                stepSize: 2,
                                callback: function(value) {
                                    // Show only even numbers: 0, 2, 4, 6, 8, 10, etc.
                                    if (Number.isInteger(value) && value % 2 === 0) {
                                        return value;
                                    }
                                    return '';
                                }
                            }
                        }
                    }
                }
            });
        }

        function updateRecommendations(recommendationsData) {
            const recommendationsContainer = document.getElementById('recommendationsContainer');
            if (!recommendationsContainer) {
                console.error('Recommendations container not found');
                return;
            }

            recommendationsContainer.innerHTML = recommendationsData.map((rec, index) => {
                const shouldAddBorder = index > 0 && rec.type === 'suggestion';
                const borderClass = shouldAddBorder ? 'border-t border-gray-200 dark:border-gray-600 pt-4' : '';

                return `
                <div class="flex items-start gap-3 ${borderClass}">
                    <span class="material-symbols-outlined text-${rec.iconColor} dark:text-${rec.iconColor.replace('-600', '-400')} text-xl pt-1">${rec.icon}</span>
                    <p class="text-sm text-gray-700 dark:text-gray-300">${rec.text}</p>
                </div>
            `;
            }).join('');
        }

        // Helper function to convert Tailwind color classes to hex values for progress bars
        function getProgressBarColor(colorClass) {
            const colorMap = {
                'red-500': '#ef4444',
                'yellow-500': '#eab308',
                'blue-500': '#3b82f6',
                'green-500': '#10b981'
            };
            return colorMap[colorClass] || '#6b7280'; // fallback to gray
        }

        // Load KPI stats
        async function loadKPIStats() {
            try {
                const response = await fetch('api/awards-stats.php');
                if (!response.ok) {
                    throw new Error('Failed to fetch KPI stats');
                }

                const result = await response.json();
                if (!result.success || !result.kpi_stats) {
                    throw new Error('Failed to load KPI stats');
                }

                const stats = result.kpi_stats;

                // Update KPI boxes
                const totalAwardsEl = document.getElementById('kpi-total-awards');
                const eligibleDepartmentsEl = document.getElementById('kpi-eligible-departments');
                const newPartnershipsEl = document.getElementById('kpi-new-partnerships');
                const successRateEl = document.getElementById('kpi-success-rate');

                if (totalAwardsEl) totalAwardsEl.textContent = stats.total_awards_met || 0;
                if (eligibleDepartmentsEl) eligibleDepartmentsEl.textContent = stats.eligible_departments || 0;
                if (newPartnershipsEl) newPartnershipsEl.textContent = stats.new_partnerships || 0;
                if (successRateEl) successRateEl.textContent = (stats.success_rate || 0) + '%';

            } catch (error) {
                console.error('Error loading KPI stats:', error);
                // Show 0 values on error
                const totalAwardsEl = document.getElementById('kpi-total-awards');
                const eligibleDepartmentsEl = document.getElementById('kpi-eligible-departments');
                const newPartnershipsEl = document.getElementById('kpi-new-partnerships');
                const successRateEl = document.getElementById('kpi-success-rate');

                if (totalAwardsEl) totalAwardsEl.textContent = '0';
                if (eligibleDepartmentsEl) eligibleDepartmentsEl.textContent = '0';
                if (newPartnershipsEl) newPartnershipsEl.textContent = '0';
                if (successRateEl) successRateEl.textContent = '0%';
            }
        }

        // Load dynamic analytics data for the awards table
        async function loadAnalyticsData() {
            try {
                const response = await fetch('api/user-award-analytics.php');
                if (!response.ok) {
                    throw new Error('Failed to fetch analytics data');
                }

                const result = await response.json();
                if (!result.success) {
                    throw new Error(result.error || 'Failed to load analytics data');
                }

                const data = result.data;

                // Store analytics data globally for modals
                window.analyticsData = data;

                // Update KPIs
                if (data.kpis) {
                    // Use awards_eligible (90%+) for "Total Awards Met" - only count eligible awards
                    document.getElementById('kpi-total-awards').textContent = data.kpis.awards_eligible || 0;
                    document.getElementById('kpi-eligible-awards').textContent = data.kpis.awards_eligible || 0;
                    document.getElementById('kpi-orc-data').textContent = data.kpis.orc_data_analyzed || 0;
                    document.getElementById('kpi-success-rate').textContent = (data.kpis.success_rate || 0) + '%';
                }

                // Update Status Chart (Eligibility Distribution Pie Chart)
                if (data.statusDistribution && data.statusDistribution.length > 0) {
                    const statusLabels = data.statusDistribution.map(s => s.eligibility_status);
                    const statusCounts = data.statusDistribution.map(s => parseInt(s.count));

                    const statusChartData = {
                        fully_met: statusCounts[statusLabels.indexOf('Eligible')] || 0,
                        partially_met: statusCounts[statusLabels.indexOf('Almost Eligible')] || 0,
                        under_review: 0,
                        unqualified: statusCounts[statusLabels.indexOf('Not Eligible')] || 0
                    };

                    const hasUploads = statusCounts.reduce((a, b) => a + b, 0) > 0;
                    updateStatusChart(statusChartData, hasUploads);
                } else {
                    // No data - show empty state
                    updateStatusChart({ fully_met: 0, partially_met: 0, under_review: 0, unqualified: 0 }, false);
                }

                // Update Trends Chart (Award Fulfillment over time)
                if (data.trends && data.trends.length > 0) {
                    const months = data.trends.map(t => {
                        const date = new Date(t.month + '-01');
                        return date.toLocaleDateString('en-US', { month: 'short' });
                    });
                    const eligibleData = data.trends.map(t => parseInt(t.eligible_submissions) || 0);
                    const totalData = data.trends.map(t => parseInt(t.total_submissions) || 0);

                    updateTrendsChart({
                        labels: months,
                        eligible: eligibleData,
                        total: totalData
                    });
                } else {
                    // No data - show empty state with placeholder months
                    const currentMonth = new Date().getMonth();
                    const months = [];
                    for (let i = 5; i >= 0; i--) {
                        const monthIndex = (currentMonth - i + 12) % 12;
                        const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                        months.push(monthNames[monthIndex]);
                    }
                    updateTrendsChart({
                        labels: months,
                        eligible: [0, 0, 0, 0, 0, 0],
                        total: [0, 0, 0, 0, 0, 0]
                    });
                }

                // Update Requirements Table
                const tableBody = document.getElementById('analyticsTableBody');
                if (tableBody) {
                    // Clear loading row
                    const loadingRow = document.getElementById('loadingRow');
                    if (loadingRow) {
                        loadingRow.remove();
                    }

                    if (data.requirements && data.requirements.length > 0) {
                        tableBody.innerHTML = data.requirements.map((req, index) => {
                            const isLast = index === data.requirements.length - 1;
                            const borderClass = isLast ? '' : 'border-b border-gray-100 dark:border-gray-700';

                            const criteriaMet = parseInt(req.criteria_met) || 0;
                            const totalCriteria = parseInt(req.total_criteria) || 0;
                            const matchPct = parseFloat(req.match_percentage) || 0;

                            // Calculate progress percentage
                            const progressPercentage = matchPct;

                            // Determine progress color and remark
                            let progressColor, remark;
                            if (matchPct >= 90) {
                                progressColor = 'bg-green-500';
                                remark = 'Excellent performance';
                            } else if (matchPct >= 70) {
                                progressColor = 'bg-blue-500';
                                remark = 'Good progress';
                            } else if (matchPct >= 50) {
                                progressColor = 'bg-yellow-500';
                                remark = 'Needs improvement';
                            } else {
                                progressColor = 'bg-red-500';
                                remark = 'Requires attention';
                            }

                            // Determine remark badge color
                            let remarkBadgeClass;
                            if (matchPct >= 90) {
                                remarkBadgeClass = 'bg-green-100/80 text-green-700 dark:bg-green-900/40 dark:text-green-300 border-green-200 dark:border-green-800';
                            } else if (matchPct >= 70) {
                                remarkBadgeClass = 'bg-blue-100/80 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 border-blue-200 dark:border-blue-800';
                            } else if (matchPct >= 50) {
                                remarkBadgeClass = 'bg-yellow-100/80 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300 border-yellow-200 dark:border-yellow-800';
                            } else {
                                remarkBadgeClass = 'bg-red-100/80 text-red-700 dark:bg-red-900/40 dark:text-red-300 border-red-200 dark:border-red-800';
                            }

                            // Progress bar gradient
                            let progressGradient = '';
                            if (matchPct >= 90) {
                                progressGradient = 'bg-gradient-to-r from-green-500 to-green-600';
                            } else if (matchPct >= 70) {
                                progressGradient = 'bg-gradient-to-r from-blue-500 to-blue-600';
                            } else if (matchPct >= 50) {
                                progressGradient = 'bg-gradient-to-r from-yellow-500 to-yellow-600';
                            } else {
                                progressGradient = 'bg-gradient-to-r from-red-500 to-red-600';
                            }

                            return `
                            <tr class="group bg-white/60 dark:bg-gray-900/40 ${borderClass} hover:bg-white/90 dark:hover:bg-gray-800/70 transition-all duration-200 hover:shadow-md">
                                <td class="py-4 px-6">
                                    <div class="text-left">
                                        <div class="flex items-center gap-2 mb-1.5">
                                            <span class="material-symbols-outlined text-lg text-primary">emoji_events</span>
                                            <span class="text-base font-bold text-gray-900 dark:text-white">${req.award_name || 'Unknown Award'}</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 ml-7">
                                            <span class="material-symbols-outlined text-sm">description</span>
                                            <span>${req.submission_title || 'No title'} • ${req.status_label || 'Unknown'}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <div class="flex flex-col items-center">
                                        <div class="flex items-baseline gap-2 mb-1">
                                            <span class="text-2xl font-bold text-gray-900 dark:text-white">${matchPct.toFixed(0)}</span>
                                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">%</span>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                            <span class="material-symbols-outlined text-sm">checklist</span>
                                            <span>${criteriaMet}/${totalCriteria} criteria</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <div class="space-y-2 flex flex-col items-center">
                                        <div class="w-full max-w-[200px] bg-gray-200/70 dark:bg-gray-700/70 rounded-full h-3 overflow-hidden backdrop-blur-sm">
                                            <div class="h-3 rounded-full transition-all duration-500 ${progressGradient} shadow-sm" style="width: ${Math.min(progressPercentage, 100)}%"></div>
                                        </div>
                                        <div class="text-xs font-medium text-gray-600 dark:text-gray-400">${Math.min(progressPercentage, 100).toFixed(1)}% complete</div>
                                    </div>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <div class="flex justify-center">
                                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold border ${remarkBadgeClass} backdrop-blur-sm">
                                            <span class="material-symbols-outlined text-sm mr-1">${matchPct >= 90 ? 'check_circle' : matchPct >= 70 ? 'trending_up' : matchPct >= 50 ? 'warning' : 'error'}</span>
                                            ${remark}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            `;
                        }).join('');
                    } else {
                        tableBody.innerHTML = `
                            <tr>
                                <td colspan="4" class="py-8 text-center text-gray-500 dark:text-gray-400">
                                    No award data available yet. Upload awards to see analytics.
                                </td>
                            </tr>
                        `;
                    }
                    updateTopAwardsMomentum(data.requirements || []);
                } else {
                    updateTopAwardsMomentum([]);
                }

                // Update recommendations based on insights
                if (data.insights) {
                    updateUserRecommendations(data.insights);
                }

            } catch (error) {
                console.error('Error loading analytics data:', error);
            }
        }

        function updateTopAwardsMomentum(requirements) {
            const container = document.getElementById('topAwardsMomentum');
            if (!container) return;

            if (!requirements || !requirements.length) {
                container.innerHTML = `
                    <p class="text-sm text-gray-500 dark:text-gray-400">Upload more awards to see top-performing candidates.</p>
                `;
                return;
            }

            const topItems = [...requirements]
                .sort((a, b) => (parseFloat(b.match_percentage) || 0) - (parseFloat(a.match_percentage) || 0))
                .slice(0, 3);

            const badgeClasses = [
                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
                'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300'
            ];

            container.innerHTML = topItems.map((item, index) => {
                const match = Math.round(parseFloat(item.match_percentage) || 0);
                const statusLabel = item.status_label || item.status || 'Pending';
                const badgeClass = badgeClasses[index] || 'bg-gray-100 text-gray-600 dark:bg-gray-800/40 dark:text-gray-300';
                return `
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-semibold ${badgeClass}">
                            ${index + 1}
                        </div>
                        <div class="flex-1 space-y-1">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">${item.award_name || 'Award Opportunity'}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">${statusLabel} • Match score ${match}%</p>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="h-2 rounded-full bg-gradient-to-r from-primary to-blue-500 transition-all duration-500" style="width: ${Math.min(match, 100)}%"></div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        const processingStatusStyles = {
            success: { icon: 'check_circle', bg: 'bg-emerald-100 dark:bg-emerald-900/30', color: 'text-emerald-600 dark:text-emerald-400' },
            review: { icon: 'hourglass_empty', bg: 'bg-blue-100 dark:bg-blue-900/30', color: 'text-blue-600 dark:text-blue-400' },
            info: { icon: 'analytics', bg: 'bg-purple-100 dark:bg-purple-900/30', color: 'text-purple-600 dark:text-purple-400' },
            warning: { icon: 'upload_file', bg: 'bg-gray-100 dark:bg-gray-800/40', color: 'text-gray-600 dark:text-gray-300' },
            danger: { icon: 'warning', bg: 'bg-red-100 dark:bg-red-900/30', color: 'text-red-600 dark:text-red-400' }
        };

        function formatRelativeTime(timestamp) {
            if (!timestamp) return 'Just now';
            const date = new Date(timestamp);
            if (Number.isNaN(date.getTime())) return 'Just now';

            const diff = Date.now() - date.getTime();
            const minute = 60 * 1000;
            const hour = 60 * minute;
            const day = 24 * hour;

            if (diff < minute) return 'Just now';
            if (diff < hour) {
                const mins = Math.floor(diff / minute);
                return `${mins} minute${mins !== 1 ? 's' : ''} ago`;
            }
            if (diff < day) {
                const hours = Math.floor(diff / hour);
                return `${hours} hour${hours !== 1 ? 's' : ''} ago`;
            }
            const days = Math.floor(diff / day);
            return days === 1 ? 'Yesterday' : `${days} days ago`;
        }

        function renderProcessingActivityItem(activity, index, total) {
            const styles = processingStatusStyles[activity.status_type] || processingStatusStyles.warning;
            const borderClass = index < total - 1 ? 'pb-3 border-b border-gray-200 dark:border-gray-700' : '';
            const matchText = activity.match_percentage !== null && activity.match_percentage !== undefined
                ? `Match score: ${activity.match_percentage}%`
                : null;
            const subtitleParts = [];
            if (activity.detail) subtitleParts.push(activity.detail);
            if (matchText) subtitleParts.push(matchText);
            subtitleParts.push(formatRelativeTime(activity.timestamp));

            return `
                <div class="flex items-start gap-3 ${borderClass}">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full ${styles.bg} flex items-center justify-center">
                        <span class="material-symbols-outlined text-lg ${styles.color}">${styles.icon}</span>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">${activity.title} - ${activity.status_label}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">${subtitleParts.join(' • ')}</p>
                    </div>
                </div>
            `;
        }

        async function loadProcessingActivity() {
            const timeline = document.getElementById('processing-timeline');
            if (!timeline) return;

            timeline.innerHTML = `
                <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <span class="material-symbols-outlined animate-spin text-base">progress_activity</span>
                    <span>Loading recent activity…</span>
                </div>
            `;

            try {
                const response = await fetch('api/award-activity.php');
                if (!response.ok) {
                    throw new Error('Failed to fetch processing activity');
                }
                const result = await response.json();
                if (!result.success) {
                    throw new Error(result.error || 'Unable to load activity');
                }

                const activities = result.activities || [];
                if (!activities.length) {
                    timeline.innerHTML = `
                        <p class="text-sm text-gray-500 dark:text-gray-400">No recent award activity yet. Upload a document to get started.</p>
                    `;
                    return;
                }

                timeline.innerHTML = activities
                    .map((activity, index) => renderProcessingActivityItem(activity, index, activities.length))
                    .join('');
            } catch (error) {
                timeline.innerHTML = `
                    <p class="text-sm text-red-500 dark:text-red-400">Unable to load recent activity. ${error.message}</p>
                `;
            }
        }

        // Update user-specific recommendations based on insights from API
        function updateUserRecommendations(insights) {
            const container = document.getElementById('recommendationsContainer');
            if (!container) return;

            let html = '';

            // Show achievements
            if (insights.achieved && insights.achieved.length > 0) {
                html += `
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-green-600 dark:text-green-400 text-xl pt-1">check_circle</span>
                        <p class="text-sm text-gray-700 dark:text-gray-300 font-medium">
                            Congratulations! You've qualified for <span class="font-bold text-green-700 dark:text-green-400">${insights.achieved.length}</span> award(s).
                        </p>
                    </div>
                `;
            }

            // Show close to achieving awards
            if (insights.close_to_achieving && insights.close_to_achieving.length > 0) {
                const closest = insights.close_to_achieving[0];
                html += `
                    <div class="flex items-start gap-3 border-t border-gray-200 dark:border-gray-600 pt-4">
                        <span class="material-symbols-outlined text-yellow-600 dark:text-yellow-400 text-xl pt-1">lightbulb</span>
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            You're only <span class="font-semibold text-yellow-700 dark:text-yellow-400">${closest.missing.toFixed(1)}%</span> away from qualifying for <span class="font-semibold">${closest.name}</span>!
                        </p>
                    </div>
                `;
            }

            // Show recommendations from API (filter out duplicates)
            if (insights.recommendations && insights.recommendations.length > 0) {
                insights.recommendations.forEach((rec, index) => {
                    if (!rec.message) return;
                    
                    const messageLower = rec.message.toLowerCase();
                    
                    // Skip if message is about qualifying/achieved (duplicate of insights.achieved)
                    if (messageLower.includes('qualified') || 
                        messageLower.includes('qualified for') || 
                        messageLower.includes('congratulations') || 
                        messageLower.includes('great job') ||
                        (messageLower.includes('achieved') && messageLower.includes('award'))) {
                        return;
                    }
                    
                    // Skip if message is about being X% away (duplicate of insights.close_to_achieving)
                    if (messageLower.includes('away from') || 
                        messageLower.includes('away from qualifying') || 
                        messageLower.includes('away from achieving')) {
                        return;
                    }
                    
                    const iconMap = {
                        'success': 'check_circle',
                        'opportunity': 'trending_up',
                        'improvement': 'psychology'
                    };
                    const colorMap = {
                        'success': 'text-green-600 dark:text-green-400',
                        'opportunity': 'text-blue-600 dark:text-blue-400',
                        'improvement': 'text-purple-600 dark:text-purple-400'
                    };

                    const icon = iconMap[rec.type] || 'info';
                    const color = colorMap[rec.type] || 'text-gray-600 dark:text-gray-400';

                    html += `
                        <div class="flex items-start gap-3 border-t border-gray-200 dark:border-gray-600 pt-4">
                            <span class="material-symbols-outlined ${color} text-xl pt-1">${icon}</span>
                            <p class="text-sm text-gray-700 dark:text-gray-300">${rec.message}</p>
                        </div>
                    `;
                });
            }

            // Show needs work
            if (insights.needs_work && insights.needs_work.length > 0 && insights.recommendations.length === 0) {
                html += `
                    <div class="flex items-start gap-3 border-t border-gray-200 dark:border-gray-600 pt-4">
                        <span class="material-symbols-outlined text-red-600 dark:text-red-400 text-xl pt-1">warning</span>
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            These awards need more documentation: <span class="font-semibold">${insights.needs_work.slice(0, 2).join(', ')}</span>
                        </p>
                    </div>
                `;
            }

            if (html) {
                container.innerHTML = html;
            } else {
                container.innerHTML = `
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-blue-600 dark:text-blue-400 text-xl pt-1">info</span>
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            Upload your awards to get personalized recommendations and insights!
                        </p>
                    </div>
                `;
            }
        }

        // Load profile picture from localStorage if available
        document.addEventListener('DOMContentLoaded', function () {
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

            // Load analytics data when the page loads
            loadAnalyticsData();
            loadProcessingActivity();
        });

        const API_BASE = 'api/analyze-award-v2.php';
        const AUTH_TOKEN = '<?php echo $token ?? ''; ?>';

        if (!AUTH_TOKEN) {
            console.error('AUTH_TOKEN is not set! Session may have expired.');
        }

        window.uploadAward = async function (formElement) {
            const formData = new FormData();
            const fileInput = formElement.querySelector('input[type="file"]');
            const titleInput = formElement.querySelector('input[name="title"]');
            const descInput = formElement.querySelector('textarea[name="description"]');

            if (!fileInput.files[0]) {
                alert('Please select a file');
                return false;
            }

            formData.append('file', fileInput.files[0]);
            formData.append('title', titleInput.value);
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
                    alert('✓ Upload successful!');
                    formElement.reset();
                    if (typeof loadAwards === 'function') loadAwards();
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

        window.loadAwards = async function () {
            try {
                const response = await fetch(API_BASE + '?action=list', {
                    headers: {
                        'Authorization': 'Bearer ' + AUTH_TOKEN
                    }
                });

                const result = await response.json();

                if (result.success && result.awards) {
                    return result.awards;
                }
                return [];
            } catch (error) {
                console.error('Error loading awards:', error);
                return [];
            }
        };

        window.deleteAward = async function (awardId) {
            if (!confirm('Are you sure you want to delete this award?')) {
                return false;
            }

            try {
                const response = await fetch(API_BASE + '?action=delete&id=' + awardId, {
                    method: 'DELETE',
                    headers: {
                        'Authorization': 'Bearer ' + AUTH_TOKEN
                    }
                });

                const result = await response.json();

                if (result.success) {
                    alert('Award deleted successfully');
                    if (typeof loadAwards === 'function') loadAwards();
                    return true;
                } else {
                    alert('Error: ' + (result.error || 'Delete failed'));
                    return false;
                }
            } catch (error) {
                alert('Error: ' + error.message);
                return false;
            }
        };

        window.viewAwardDetails = async function (awardId) {
            try {
                const response = await fetch(API_BASE + '?action=details&id=' + awardId, {
                    headers: {
                        'Authorization': 'Bearer ' + AUTH_TOKEN
                    }
                });

                const result = await response.json();

                if (result.success && result.analysis) {
                    return result.analysis;
                }
                return null;
            } catch (error) {
                console.error('Error loading details:', error);
                return null;
            }
        };

        let criteriaFormMode = 'create';
        let editingCriteriaId = null;

        window.showAddCriteriaModal = function (mode = 'create', criteriaData = null) {
            const modalTitle = document.getElementById('criteriaModalTitle');
            const submitText = document.getElementById('criteriaSubmitText');
            const form = document.getElementById('addCriteriaForm');
            if (!form) {
                console.error('addCriteriaForm not found');
                return;
            }

            criteriaFormMode = mode === 'edit' ? 'edit' : 'create';
            editingCriteriaId = null;

            if (criteriaFormMode === 'edit') {
                if (!criteriaData || !criteriaData.id) {
                    showNotification('Unable to edit this criteria right now.', 'error');
                    return;
                }
                editingCriteriaId = criteriaData.id;
                if (modalTitle) modalTitle.textContent = 'Edit Award Criteria';
                if (submitText) submitText.textContent = 'Save Changes';

                form.elements['category_name'].value = criteriaData.category_name || '';
                form.elements['award_type'].value = criteriaData.award_type || 'Individual';
                form.elements['status'].value = criteriaData.status || 'active';
                form.elements['description'].value = criteriaData.description || '';

                let requirementsFieldValue = '';
                const requirementsData = criteriaData.requirementsArray || criteriaData.requirements || [];
                if (Array.isArray(requirementsData)) {
                    requirementsFieldValue = requirementsData.join('\n');
                } else if (typeof requirementsData === 'string' && requirementsData.trim().length > 0) {
                    try {
                        const parsedReq = JSON.parse(requirementsData);
                        requirementsFieldValue = Array.isArray(parsedReq) ? parsedReq.join('\n') : requirementsData;
                    } catch (parseError) {
                        requirementsFieldValue = requirementsData;
                    }
                }
                form.elements['requirements'].value = requirementsFieldValue;

                const keywordsValue = Array.isArray(criteriaData.keywords)
                    ? criteriaData.keywords.join(', ')
                    : (criteriaData.keywords || '');
                form.elements['keywords'].value = keywordsValue;
                form.elements['min_match_percentage'].value = criteriaData.min_match_percentage ?? 60;
                form.elements['weight'].value = criteriaData.weight ?? 5;
            } else {
                if (modalTitle) modalTitle.textContent = 'Add Award Criteria';
                if (submitText) submitText.textContent = 'Save Criteria';
                form.reset();
                form.elements['min_match_percentage'].value = 60;
                form.elements['weight'].value = 5;
            }

            const modal = document.getElementById('addCriteriaModal');
            const modalContent = document.getElementById('addCriteriaModalContent');
            modal.classList.remove('hidden');
            setTimeout(() => {
                modalContent.classList.remove('scale-95', 'opacity-0');
                modalContent.classList.add('scale-100', 'opacity-100');
            }, 10);
        };

        window.hideAddCriteriaModal = function () {
            const modal = document.getElementById('addCriteriaModal');
            const modalContent = document.getElementById('addCriteriaModalContent');
            modalContent.classList.remove('scale-100', 'opacity-100');
            modalContent.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
                const form = document.getElementById('addCriteriaForm');
                if (form) {
                    form.reset();
                    form.elements['min_match_percentage'].value = 60;
                    form.elements['weight'].value = 5;
                }
                criteriaFormMode = 'create';
                editingCriteriaId = null;
                const modalTitle = document.getElementById('criteriaModalTitle');
                const submitText = document.getElementById('criteriaSubmitText');
                if (modalTitle) modalTitle.textContent = 'Add Award Criteria';
                if (submitText) submitText.textContent = 'Save Criteria';
            }, 300);
        };

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        window.loadCriteriaList = async function () {
            console.log('loadCriteriaList called, AUTH_TOKEN:', AUTH_TOKEN);
            const container = document.getElementById('criteriaListContainer');
            if (!container) {
                console.error('criteriaListContainer not found!');
                return;
            }

            container.innerHTML = '<p class="text-center py-8"><svg class="animate-spin h-8 w-8 mx-auto text-primary" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg></p>';

            try {
                console.log('Fetching from: api/award-criteria.php?action=list');
                const response = await fetch('api/award-criteria.php?action=list', {
                    headers: {
                        'Authorization': 'Bearer ' + AUTH_TOKEN
                    }
                });

                console.log('Response status:', response.status);
                const result = await response.json();
                console.log('Result:', result);

                if (result.success && result.criteria) {
                    console.log('Rendering', result.criteria.length, 'criteria');
                    renderCriteriaList(result.criteria);
                } else {
                    container.innerHTML = '<div class="text-center py-8 text-red-600">Error: ' + (result.error || 'Failed to load criteria') + '</div>';
                }
            } catch (error) {
                console.error('Error loading criteria:', error);
                container.innerHTML = '<div class="text-center py-8 text-red-600">Error: ' + error.message + '</div>';
            }
        };

        function renderCriteriaList(criteriaList) {
            console.log('renderCriteriaList called with', criteriaList.length, 'items');
            const container = document.getElementById('criteriaListContainer');
            if (!container) {
                console.error('criteriaListContainer not found in DOM!');
                return;
            }

            if (criteriaList.length === 0) {
                container.innerHTML = '<p class="text-center text-text-muted-light dark:text-text-muted-dark py-8">No award criteria defined yet. Click "Add Award Criteria" to get started.</p>';
                return;
            }

            try {
                const html = criteriaList.map(criteria => {
                    console.log('Rendering criteria:', criteria.category_name);
                    const requirements = criteria.requirements ? (typeof criteria.requirements === 'string' ? JSON.parse(criteria.requirements) : criteria.requirements) : [];
                    const keywords = criteria.keywords ? (typeof criteria.keywords === 'string' ? criteria.keywords.split(',') : criteria.keywords) : [];
                    console.log('Requirements:', requirements.length, 'Keywords:', keywords.length);

                    return `
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white">${escapeHtml(criteria.category_name)}</h3>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${criteria.status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400' : 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400'}">
                                    ${criteria.status}
                                </span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400">
                                    ${escapeHtml(criteria.award_type)}
                                </span>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">${escapeHtml(criteria.description)}</p>
                        </div>
                        <div class="flex items-center gap-2 ml-4">
                            <button onclick="toggleCriteriaDetails(${criteria.id})" class="p-2 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors" title="Toggle details">
                                <span class="material-symbols-outlined text-lg criteria-toggle-icon-${criteria.id}">expand_more</span>
                            </button>
                            <button onclick="editCriteria(${criteria.id})" class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors">
                                <span class="material-symbols-outlined text-lg">edit</span>
                            </button>
                            <button onclick="deleteCriteria(${criteria.id})" class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                <span class="material-symbols-outlined text-lg">delete</span>
                            </button>
                        </div>
                    </div>

                    <div class="criteria-details-${criteria.id} collapsed transition-all duration-300 overflow-hidden" style="max-height: 0; opacity: 0;">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">checklist</span>
                                    Requirements (${requirements.length})
                                </h4>
                                <ul class="space-y-1">
                                    ${requirements.slice(0, 3).map(req => `
                                        <li class="text-sm text-gray-600 dark:text-gray-400 flex items-start gap-2">
                                            <span class="material-symbols-outlined text-xs text-green-600 dark:text-green-400 mt-0.5">check_circle</span>
                                            <span>${escapeHtml(req)}</span>
                                        </li>
                                    `).join('')}
                                    ${requirements.length > 3 ? `<li class="text-sm text-gray-500 dark:text-gray-500 pl-6">+${requirements.length - 3} more</li>` : ''}
                                </ul>
                            </div>

                            <div>
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">label</span>
                                    Keywords (${keywords.length})
                                </h4>
                                <div class="flex flex-wrap gap-1">
                                    ${keywords.slice(0, 6).map(kw => `
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                            ${escapeHtml(kw.trim())}
                                        </span>
                                    `).join('')}
                                    ${keywords.length > 6 ? `<span class="text-xs text-gray-500">+${keywords.length - 6}</span>` : ''}
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-6 text-sm text-gray-600 dark:text-gray-400 pt-3 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">percent</span>
                                Min Match: <span class="font-semibold">${criteria.min_match_percentage}%</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">weight</span>
                                Weight: <span class="font-semibold">${criteria.weight}/10</span>
                            </div>
                            <div class="flex items-center gap-1 ml-auto">
                                <span class="material-symbols-outlined text-sm">schedule</span>
                                Updated: ${new Date(criteria.updated_at).toLocaleDateString()}
                            </div>
                        </div>
                    </div>
                </div>
            `;
                }).join('');

                console.log('Generated HTML length:', html.length);
                container.innerHTML = html;
                console.log('HTML injected into container');
            } catch (error) {
                console.error('Error rendering criteria list:', error);
                container.innerHTML = '<div class="text-center py-8 text-red-600">Error rendering criteria: ' + error.message + '</div>';
            }
        }

        document.getElementById('addCriteriaForm')?.addEventListener('submit', async function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = {
                category_name: formData.get('category_name'),
                award_type: formData.get('award_type'),
                status: formData.get('status'),
                description: formData.get('description'),
                requirements: formData.get('requirements').split('\n').filter(r => r.trim()),
                keywords: formData.get('keywords'),
                min_match_percentage: parseInt(formData.get('min_match_percentage')),
                weight: parseInt(formData.get('weight'))
            };

            try {
                const isEditing = criteriaFormMode === 'edit' && editingCriteriaId;
                const endpoint = isEditing
                    ? `api/award-criteria.php?action=update&id=${editingCriteriaId}`
                    : 'api/award-criteria.php?action=create';
                const method = isEditing ? 'PUT' : 'POST';

                const response = await fetch(endpoint, {
                    method,
                    headers: {
                        'Authorization': 'Bearer ' + AUTH_TOKEN,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showNotification(isEditing ? 'Award criteria updated successfully!' : 'Award criteria created successfully!', 'success');
                    hideAddCriteriaModal();
                    
                    // Refresh both the card view and table view
                    if (typeof loadCriteriaList === 'function') {
                        loadCriteriaList();
                    }
                    if (typeof loadAwardCriteria === 'function') {
                        loadAwardCriteria();
                    } else if (typeof window.loadAwardListData === 'function') {
                        window.loadAwardListData();
                    }
                    
                    criteriaFormMode = 'create';
                    editingCriteriaId = null;
                } else {
                    showNotification('Error: ' + (result.error || 'Failed to create criteria'), 'error');
                }
            } catch (error) {
                showNotification('Error: ' + error.message, 'error');
            }
        });

        window.deleteCriteria = async function (criteriaId) {
            if (!confirm('Are you sure you want to delete this award criteria? This action cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch('api/award-criteria.php?action=delete&id=' + criteriaId, {
                    method: 'DELETE',
                    headers: {
                        'Authorization': 'Bearer ' + AUTH_TOKEN
                    }
                });

                const result = await response.json();

                if (result.success) {
                    showNotification('Award criteria deleted successfully', 'success');
                    
                    // Refresh both the card view and table view
                    if (typeof loadCriteriaList === 'function') {
                        loadCriteriaList();
                    }
                    if (typeof loadAwardCriteria === 'function') {
                        loadAwardCriteria();
                    } else if (typeof window.loadAwardListData === 'function') {
                        window.loadAwardListData();
                    }
                } else {
                    showNotification('Error: ' + (result.error || 'Delete failed'), 'error');
                }
            } catch (error) {
                showNotification('Error: ' + error.message, 'error');
            }
        };

        window.toggleCriteriaDetails = function (criteriaId) {
            const detailsContainer = document.querySelector(`.criteria-details-${criteriaId}`);
            const toggleIcon = document.querySelector(`.criteria-toggle-icon-${criteriaId}`);
            
            if (!detailsContainer || !toggleIcon) return;
            
            // Check if currently collapsed
            const isCollapsed = detailsContainer.classList.contains('collapsed');
            
            if (isCollapsed) {
                // Expand
                detailsContainer.classList.remove('collapsed');
                const naturalHeight = detailsContainer.scrollHeight;
                detailsContainer.style.maxHeight = naturalHeight + 'px';
                detailsContainer.style.opacity = '1';
                toggleIcon.textContent = 'expand_less';
                
                // After transition, set to auto for dynamic content
                setTimeout(() => {
                    if (!detailsContainer.classList.contains('collapsed')) {
                        detailsContainer.style.maxHeight = 'none';
                    }
                }, 300);
            } else {
                // Collapse
                const currentHeight = detailsContainer.scrollHeight;
                detailsContainer.style.maxHeight = currentHeight + 'px';
                detailsContainer.offsetHeight; // Force reflow
                detailsContainer.classList.add('collapsed');
                detailsContainer.style.maxHeight = '0px';
                detailsContainer.style.opacity = '0';
                toggleIcon.textContent = 'expand_more';
            }
        };

        window.editCriteria = async function (criteriaId) {
            try {
                const response = await fetch(`api/award-criteria.php?action=get&id=${criteriaId}`, {
                    headers: {
                        'Authorization': 'Bearer ' + AUTH_TOKEN
                    }
                });

                const result = await response.json();

                if (!response.ok || !result.success || !result.criteria) {
                    throw new Error(result.error || 'Unable to load criteria details');
                }

                const criteria = result.criteria;
                let requirementsArray = [];

                if (Array.isArray(criteria.requirements)) {
                    requirementsArray = criteria.requirements;
                } else if (typeof criteria.requirements === 'string') {
                    try {
                        const parsedReqs = JSON.parse(criteria.requirements);
                        requirementsArray = Array.isArray(parsedReqs) ? parsedReqs : criteria.requirements.split('\n').map(req => req.trim()).filter(Boolean);
                    } catch (parseError) {
                        requirementsArray = criteria.requirements
                            .split('\n')
                            .map(req => req.trim())
                            .filter(Boolean);
                    }
                }

                showAddCriteriaModal('edit', {
                    ...criteria,
                    requirementsArray
                });
            } catch (error) {
                console.error('Error loading criteria for edit:', error);
                showNotification(error.message || 'Failed to load award criteria details', 'error');
            }
        };

        // Load award list data when award list tab is clicked
        document.getElementById('award-list-tab')?.addEventListener('click', () => {
            loadAwardListData();
            // Also load criteria cards for all users
            if (typeof window.loadCriteriaList === 'function') {
                window.loadCriteriaList();
            }
        });

        // Also load on page load if award list tab is active (check URL hash)
        if (window.location.hash === '#award-list') {
            setTimeout(() => {
                loadAwardListData();
                // Also load criteria cards for all users
                if (typeof window.loadCriteriaList === 'function') {
                    window.loadCriteriaList();
                }
            }, 100);
        }
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
            
            // Toggle dropdown
            notificationBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                notificationDropdown.classList.toggle('hidden');
                if (!notificationDropdown.classList.contains('hidden')) {
                    loadNotifications();
                }
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!notificationBtn.contains(e.target) && !notificationDropdown.contains(e.target)) {
                    notificationDropdown.classList.add('hidden');
                }
            });
            
            // Check for new notifications and create them
            async function checkNotifications() {
                try {
                    const response = await fetch('api/notifications.php?action=check');
                    const data = await response.json();
                    if (data.success) {
                        console.log('Notifications checked:', data);
                    }
                } catch (error) {
                    console.error('Error checking notifications:', error);
                }
            }
            
            // Load notifications from API
            async function loadNotifications() {
                try {
                    const response = await fetch('api/notifications.php');
                    const data = await response.json();
                    if (data.notifications) {
                        notifications = data.notifications;
                        updateNotificationDisplay();
                        updateNotificationBadge();
                    }
                } catch (error) {
                    console.error('Error loading notifications:', error);
                }
            }
            
            // Get unread count
            async function updateNotificationBadge() {
                try {
                    const response = await fetch('api/notifications.php?action=count');
                    const data = await response.json();
                    const count = data.count || 0;
                    
                    if (notificationBadge) {
                        if (count > 0) {
                            notificationBadge.textContent = count > 99 ? '99+' : count;
                            notificationBadge.classList.remove('hidden');
                        } else {
                            notificationBadge.classList.add('hidden');
                        }
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
                    
                    // Show status indicator for confirmed MOU notifications
                    let statusIndicator = '';
                    if (isMouNotification && isConfirmed && notif.mou_renewal_status === 'renewed') {
                        statusIndicator = `
                            <div class="mt-2">
                                <p class="text-xs text-green-500 font-medium flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">check_circle</span>
                                    Status: Renewed
                                </p>
                            </div>
                        `;
                    } else if (isMouNotification) {
                        // Show click hint for unconfirmed MOU notifications
                        statusIndicator = `
                            <div class="mt-2">
                                <p class="text-xs text-primary font-medium flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">touch_app</span>
                                    Click to view details and confirm status
                                </p>
                            </div>
                        `;
                    }
                    
                    const actionHint = targetUrl && !isMouNotification ? '<p class="text-xs text-primary mt-2 font-semibold flex items-center gap-1">Open related record<span class="material-symbols-outlined text-sm">arrow_outward</span></p>' : '';
                    const clickableClass = 'cursor-pointer';
                    const mouClickHandler = isMouNotification ? `onclick="event.stopPropagation(); showMouNotificationModal(${notif.id})"` : '';
                    
                    return `
                        <div class="p-4 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 ${clickableClass} focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/60 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-background-dark ${notif.is_read ? 'opacity-60' : ''}" 
                             ${!isMouNotification ? `role="button" tabindex="0" data-id="${notif.id}" data-notification-id="${notif.id}"${urlAttribute}` : ''}
                             ${mouClickHandler}>
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full ${bgColor} flex items-center justify-center">
                                    <span class="material-symbols-outlined text-white text-lg">${icon}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(notif.title)}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">${escapeHtml(notif.message)}</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">${timeAgo}</p>
                                ${actionHint}
                                ${statusIndicator}
                                </div>
                                ${!notif.is_read ? '<div class="flex-shrink-0 w-2 h-2 bg-primary rounded-full mt-2"></div>' : ''}
                            </div>
                        </div>
                    `;
                }).join('');
            }
            
            function handleNotificationListClick(event) {
                // Don't handle clicks on confirmation buttons
                if (event.target.closest('button') || event.target.closest('[onclick*="confirmMouRenewal"]')) {
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
            
            // Show MOU notification modal
            window.showMouNotificationModal = function(notificationId) {
                const notif = notifications.find(n => n.id === notificationId);
                if (!notif || notif.related_type !== 'mou_moa') return;
                
                const modal = document.getElementById('mouNotificationModal');
                const modalContent = document.getElementById('mouModalContent');
                if (!modal || !modalContent) return;
                
                // Determine criticality
                const isExpired = notif.mou_is_expired || false;
                const daysUntilExpiry = notif.mou_days_until_expiry || 0;
                const isExpiringSoon = !isExpired && daysUntilExpiry <= 30;
                
                let criticalityLevel = 'Low';
                let criticalityColor = 'text-blue-500';
                let criticalityBg = 'bg-blue-50 dark:bg-blue-900/20';
                let criticalityIcon = 'info';
                
                if (isExpired) {
                    criticalityLevel = 'Critical';
                    criticalityColor = 'text-red-500';
                    criticalityBg = 'bg-red-50 dark:bg-red-900/20';
                    criticalityIcon = 'error';
                } else if (isExpiringSoon) {
                    criticalityLevel = 'High';
                    criticalityColor = 'text-yellow-500';
                    criticalityBg = 'bg-yellow-50 dark:bg-yellow-900/20';
                    criticalityIcon = 'warning';
                }
                
                const mouTitle = notif.mou_institution || notif.mou_partner || 'Unknown';
                const endDate = notif.mou_end_date ? new Date(notif.mou_end_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'Not specified';
                const statusText = notif.mou_status || 'Unknown';
                
                modalContent.innerHTML = `
                    <div class="space-y-4">
                        <div class="${criticalityBg} p-4 rounded-lg border border-current ${criticalityColor}">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined">${criticalityIcon}</span>
                                <p class="font-semibold">Priority Level: ${criticalityLevel}</p>
                            </div>
                            ${isExpired ? 
                                `<p class="text-sm mt-2">This MOU/MOA has expired and requires immediate attention.</p>` :
                                isExpiringSoon ?
                                `<p class="text-sm mt-2">This MOU/MOA will expire in ${daysUntilExpiry} day(s). Please take action soon.</p>` :
                                `<p class="text-sm mt-2">This MOU/MOA is still active but should be monitored.</p>`
                            }
                        </div>
                        
                        <div class="space-y-2">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Institution/Partner</p>
                                <p class="text-base text-gray-900 dark:text-white">${escapeHtml(mouTitle)}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">End Date</p>
                                <p class="text-base text-gray-900 dark:text-white">${endDate}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</p>
                                <p class="text-base text-gray-900 dark:text-white">${escapeHtml(statusText)}</p>
                            </div>
                            ${isExpired ? 
                                `<div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Days Since Expiration</p>
                                    <p class="text-base text-red-600 dark:text-red-400 font-semibold">${Math.abs(daysUntilExpiry)} day(s)</p>
                                </div>` :
                                `<div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Days Until Expiration</p>
                                    <p class="text-base ${isExpiringSoon ? 'text-yellow-600 dark:text-yellow-400' : 'text-gray-900 dark:text-white'} font-semibold">${daysUntilExpiry} day(s)</p>
                                </div>`
                            }
                        </div>
                        
                        <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Please confirm the renewal status:</p>
                            <div class="flex gap-2">
                                <button onclick="confirmMouRenewal(${notif.id}, 'renewed'); closeMouModal();" 
                                        class="flex-1 px-4 py-2 text-sm font-medium bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors flex items-center justify-center gap-2">
                                    <span class="material-symbols-outlined text-sm">check_circle</span>
                                    Mark as Renewed
                                </button>
                                <button onclick="confirmMouRenewal(${notif.id}, 'not_renewed'); closeMouModal();" 
                                        class="flex-1 px-4 py-2 text-sm font-medium bg-red-500 hover:bg-red-600 text-white rounded-lg transition-colors flex items-center justify-center gap-2">
                                    <span class="material-symbols-outlined text-sm">cancel</span>
                                    Not Renewed
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Note: If marked as "Not Renewed", you will continue to receive notifications until it is renewed.</p>
                        </div>
                    </div>
                `;
                
                modal.classList.remove('hidden');
            };
            
            // Close MOU modal
            window.closeMouModal = function() {
                const modal = document.getElementById('mouNotificationModal');
                if (modal) {
                    modal.classList.add('hidden');
                }
            };
            
            // Setup modal close handlers
            document.addEventListener('DOMContentLoaded', () => {
                const closeBtn = document.getElementById('closeMouModal');
                const modal = document.getElementById('mouNotificationModal');
                
                if (closeBtn) {
                    closeBtn.addEventListener('click', closeMouModal);
                }
                
                if (modal) {
                    modal.addEventListener('click', (e) => {
                        if (e.target === modal) {
                            closeMouModal();
                        }
                    });
                }
                
                // Close on Escape key
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                        closeMouModal();
                    }
                });
            });
            
            // Confirm MOU renewal status
            window.confirmMouRenewal = async function(notificationId, renewalStatus) {
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
                        // Reload notifications to reflect the confirmation
                        await loadNotifications();
                        await updateNotificationBadge();
                        
                        if (renewalStatus === 'renewed') {
                            if (typeof showToast === 'function') {
                                showToast('MOU/MOA marked as renewed. Notification will be removed.', 'success');
                            } else {
                                alert('MOU/MOA marked as renewed. Notification will be removed.');
                            }
                        } else {
                            if (typeof showToast === 'function') {
                                showToast('MOU/MOA marked as not renewed. You will continue to receive notifications.', 'info');
                            } else {
                                alert('MOU/MOA marked as not renewed. You will continue to receive notifications.');
                            }
                        }
                    } else {
                        console.error('Failed to confirm MOU renewal:', data.error);
                        alert('Failed to confirm MOU renewal status. Please try again.');
                    }
                } catch (error) {
                    console.error('Error confirming MOU renewal:', error);
                    alert('An error occurred while confirming MOU renewal status. Please try again.');
                }
            };
            
            async function refreshNotificationIndicators() {
                try {
                    await checkNotifications();
                    await updateNotificationBadge();
                } catch (error) {
                    console.error('Error refreshing notification indicators:', error);
                }
            }
            
            // Initialize: Check for notifications and load them
            document.addEventListener('DOMContentLoaded', () => {
                refreshNotificationIndicators();
                
                // Refresh notifications every 5 minutes
                setInterval(() => {
                    refreshNotificationIndicators();
                }, 5 * 60 * 1000);
            });
        })();
    </script>
</body>

</html>