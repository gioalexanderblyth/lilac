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

// Restrict this page to admin only
if (!$isAdmin) {
    header('Location: dashboard.php');
    exit();
}

$documents = [];
try {
    $pdo = getDatabaseConnection();
    if ($pdo instanceof FileBasedDatabase) {
        $dataDir = __DIR__ . '/data/documents/';
        if (is_dir($dataDir)) {
            $files = glob($dataDir . '*.json');
            foreach ($files as $file) {
                $data = json_decode(file_get_contents($file), true);
                if ($data) $documents[] = $data;
            }
        }
    } else {
        // Load all documents from MOU, MOA, and Other Documents tables using UNION
        $stmt = $pdo->query("
            SELECT
                m.id,
                m.user_id,
                m.institution as title,
                CONCAT('Institution: ', m.institution, ' | Contact: ', m.contact_email) as description,
                m.file_name,
                m.file_path,
                COALESCE(m.type, 'MOU') as category,
                m.created_at,
                m.updated_at,
                u.username as uploaded_by,
                'mou_moa' as source_table
            FROM mou_moa m
            LEFT JOIN users u ON m.user_id = u.id

            UNION ALL

            SELECT
                od.id,
                od.user_id,
                od.title,
                od.description,
                od.file_name,
                od.file_path,
                od.category,
                od.created_at,
                od.updated_at,
                u.username as uploaded_by,
                'other_documents' as source_table
            FROM other_documents od
            LEFT JOIN users u ON od.user_id = u.id

            ORDER BY created_at DESC
        ");
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log('Documents load error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>

<html lang="en"><head>

<meta charset="utf-8"/>

<meta content="width=device-width, initial-scale=1.0" name="viewport"/>

<title>LILAC Documents</title>

<link crossorigin="" href="https://fonts.gstatic.com/" rel="preconnect"/>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>

<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>

<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
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
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>

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
                        "background-light": "#F8FAFC",
                        "background-dark": "#0F172A",
                        "card-light": "#FFFFFF",
                        "card-dark": "#1E293B",
                        "text-light": "#0f172a",
                        "text-dark": "#e2e8f0",
                        "text-muted-light": "#64748b",
                        "text-muted-dark": "#94a3b8",
                        "border-light": "#E2E8F0",
                        "border-dark": "#334155",

                        "green-light": "#22C55E",
                        "green-dark": "#4ADE80",
                        "red-light": "#EF4444",
                        "red-dark": "#F87171",
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

        .sidebar-collapsed .sidebar-text {

            display: none;

        }

        .sidebar-collapsed .sidebar-logo-text {

            display: none;

        }

        .sidebar-collapsed .sidebar {

            width: 5rem;

        }

        .sidebar-expanded .sidebar {

            width: 16rem;

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

        .sidebar-collapsed main {

            margin-left: 2rem;

        }

        .sidebar-expanded main {

            margin-left: 0 !important;

        }

        .sidebar-expanded .main-content {

            padding-left: 2rem;

        }

        .sidebar-collapsed .main-content {

            padding-left: 2rem;

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

        /* Ensure consistent sidebar icon styling */
        .sidebar-nav-link {
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }
        
        /* Hover effects for non-active links */
        .sidebar-nav-link:not(.bg-primary-50):hover {
            background-color: rgb(243 244 246); /* gray-100 */
            transform: translateY(-1px);
        }
        
        .dark .sidebar-nav-link:not(.bg-primary-50):hover {
            background-color: rgba(255, 255, 255, 0.05);
        }
        
        /* Active state styling for Documents menu item */
        .sidebar-nav-link.bg-primary-50 {
            background-color: rgb(232 242 254) !important; /* blue-50 */
            color: rgb(15, 102, 188) !important; /* blue-600 */
        }
        
        .dark .sidebar-nav-link.bg-primary-50 {
            background-color: rgba(15, 102, 188, 0.4) !important; /* blue-900/40 */
            color: rgb(69, 151, 247) !important; /* blue-400 */
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

    </style>

</head>

<body class="bg-background-light dark:bg-background-dark font-display text-text-light dark:text-text-dark">
<div class="flex h-screen sidebar-collapsed" id="app-container">

<aside class="sidebar bg-card-light dark:bg-card-dark border-r border-border-light dark:border-border-dark flex flex-col">

<div class="flex items-center justify-start px-4 h-20 border-b border-border-light dark:border-border-dark">

<div class="flex items-center gap-3">

<img alt="CPU LILAC Logo" class="h-11 w-11" src="./api/get-logo.php?v=1" width="32" height="32" onerror="this.style.display='none'; document.getElementById('logo-fallback').style.display='flex'; console.error('Logo failed to load:', this.src);"/>

<div class="h-11 w-11 bg-primary rounded-lg flex items-center justify-center text-white font-bold text-sm" style="display: none;" id="logo-fallback">CPU</div>

<h1 class="text-xl font-bold text-text-light dark:text-text-dark sidebar-logo-text hidden">LILAC</h1>
</div>

</div>

<nav class="flex-1 px-4 py-6 space-y-2">

<a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="dashboard.php">
<span class="material-symbols-outlined">dashboard</span>
<span class="sidebar-text hidden">Dashboard</span>
</a>

<a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="awards.php">
<span class="material-symbols-outlined">emoji_events</span>

<span class="sidebar-text hidden">Awards Progress</span>

</a>

<a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="events-activities.php">
<span class="material-symbols-outlined">event</span>

<span class="sidebar-text hidden">Events &amp; Activities</span>

</a>

<a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="scheduler.php">
<span class="material-symbols-outlined">calendar_today</span>

<span class="sidebar-text hidden">Scheduler</span>

</a>

<a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="mou-moa.php">
<span class="material-symbols-outlined">handshake</span>

<span class="sidebar-text hidden">MOUs &amp; MOAs</span>

</a>



<a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg bg-primary-50 dark:bg-primary-900/40 text-primary-600 dark:text-primary-400 font-semibold sidebar-nav-link" href="documents.php">
<span class="material-symbols-outlined filled">description</span>
<span class="sidebar-text hidden">Documents</span>
</a>

</nav>

<div class="px-4 py-4 border-t border-border-light dark:border-border-dark">

<div class="flex items-center justify-between profile-container">

<div class="flex items-center gap-3">

<div class="w-10 h-10 rounded-full bg-cover bg-center sidebar-profile-picture hidden" style='background-image: url("https://lh3.googleusercontent.com/aida-public/AB6AXuC23fvgOSZIK6K5vguUgvVeU1XYFfp1LB3d4zICMvW6bispRl-eHHfnOtSsvRU3MgvmOpSYMCZhcSBIksvjlEHtkGMxuCFsQkuT0suo2-O9n3py7mlzFFETXCOIfvLVGGUj1aaG8ENOeDXXy_ifek2uG3R3--ghDflKvuAm9vrceoK8doav0lNYVbLz1bnWy6REWcrCPuPZZ8upfPqShoQpSDjICl16zMEcRuHzjt05z9cFITLKPdZTfMF-1dLK-klh8UhjeDeE4Q7p");'></div>

<div class="sidebar-profile-info hidden">

<p class="font-semibold text-text-light dark:text-text-dark"><?php echo htmlspecialchars($user['role'] === 'admin' ? 'Admin User' : $user['username']); ?></p>
<div class="flex gap-3">
<a class="text-sm text-primary-600 dark:text-primary-400 hover:underline" href="profile.php">Profile</a>
<span class="text-sm text-gray-400">|</span>
<a class="text-sm text-red-600 dark:text-red-400 hover:underline" href="logout.php">Logout</a>
</div>

</div>

</div>

<button class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-background-dark transition-colors" id="sidebar-toggle">

<span class="material-symbols-outlined sidebar-toggle-icon-open hidden">chevron_left</span>

<span class="material-symbols-outlined sidebar-toggle-icon-closed block">chevron_right</span>

</button>

</div>

</div>

</aside>

<main class="flex-1 overflow-y-auto">

<header class="sticky top-0 bg-background-light/80 dark:bg-background-dark/80 backdrop-blur-sm z-30 px-6 lg:px-8 py-4 border-b border-border-light dark:border-border-dark flex justify-between items-center h-20 overflow-visible header-animate">

<h1 class="text-2xl font-bold text-text-light dark:text-text-dark">Documents</h1>
<div class="flex items-center gap-2">

<div class="relative z-[9999]">
<button id="notificationBell" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-white/10 text-text-muted-light dark:text-text-muted-dark transition-colors duration-200">
<span class="material-symbols-outlined">notifications</span>
<!-- Notification badge -->
<span id="notificationBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-sm rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
</button>

<!-- Notification dropdown -->
<div id="notificationDropdown" class="hidden absolute right-0 top-full mt-2 w-80 bg-white dark:bg-card-dark rounded-lg shadow-xl border border-border-light dark:border-border-dark z-[9999] max-h-96 overflow-y-auto">
<div class="p-4 border-b border-border-light dark:border-border-dark flex items-center justify-between">
<h3 class="font-semibold text-text-light dark:text-text-dark">Notifications</h3>
<button id="clearAllNotifications" class="text-sm text-primary hover:text-primary/80 transition-colors">Clear All</button>
</div>
<div id="notificationList" class="divide-y divide-border-light dark:divide-border-dark">
<!-- Notifications will be populated here -->
</div>
<div id="noNotifications" class="p-4 text-center text-text-muted-light dark:text-text-muted-dark text-sm">
No notifications yet
</div>
</div>
</div>

<button class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-white/10 text-text-muted-light dark:text-text-muted-dark transition-colors duration-200" id="theme-toggle">
<span class="material-symbols-outlined dark:hidden">light_mode</span>

<span class="material-symbols-outlined hidden dark:inline">dark_mode</span>

</button>

</div>

</header>

<div class="p-4 content-animate">
<div class="flex flex-col xl:flex-row gap-4">
<div class="flex-1">
 
<div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
<div class="bg-card-light dark:bg-card-dark p-3 rounded-lg shadow-sm border border-border-light dark:border-border-dark doc-counter-card transition-all duration-200 hover:shadow-md hover:scale-[1.02]">
<div class="flex justify-between items-center h-full">
<div class="flex items-center gap-4">
<div class="bg-blue-100 dark:bg-blue-900/50 p-2 rounded-lg">
<span class="material-icons text-blue-500 dark:text-blue-400">folder</span>
</div>
<h2 class="text-sm font-semibold text-text-light dark:text-text-dark flex items-center">Total Documents</h2>
</div>
<span id="counter-total" class="text-xl font-bold text-text-light dark:text-text-dark">0</span>
</div>
</div>
<div class="bg-card-light dark:bg-card-dark p-3 rounded-lg shadow-sm border border-border-light dark:border-border-dark doc-counter-card transition-all duration-200 hover:shadow-md hover:scale-[1.02]">
<div class="flex justify-between items-center h-full">
<div class="flex items-center gap-4">
<div class="bg-green-100 dark:bg-green-900/50 p-2 rounded-lg">
<span class="material-icons text-green-500 dark:text-green-400">handshake</span>
</div>
<h2 class="text-sm font-semibold text-text-light dark:text-text-dark flex items-center">MOU</h2>
</div>
<span id="counter-mou" class="text-xl font-bold text-text-light dark:text-text-dark">0</span>
</div>
</div>
<div class="bg-card-light dark:bg-card-dark p-3 rounded-lg shadow-sm border border-border-light dark:border-border-dark doc-counter-card transition-all duration-200 hover:shadow-md hover:scale-[1.02]">
<div class="flex justify-between items-center h-full">
<div class="flex items-center gap-4">
<div class="bg-red-100 dark:bg-red-900/50 p-2 rounded-lg">
<span class="material-icons text-red-500 dark:text-red-400">description</span>
</div>
<h2 class="text-sm font-semibold text-text-light dark:text-text-dark flex items-center">Other Documents</h2>
</div>
<span id="counter-templates" class="text-xl font-bold text-text-light dark:text-text-dark">0</span>
</div>
</div>
<div class="bg-card-light dark:bg-card-dark p-3 rounded-lg shadow-sm border border-border-light dark:border-border-dark doc-counter-card transition-all duration-200 hover:shadow-md hover:scale-[1.02]">
<div class="flex justify-between items-center h-full">
<div class="flex items-center gap-4">
<div class="bg-indigo-100 dark:bg-indigo-900/50 p-2 rounded-lg">
<span class="material-icons text-indigo-500 dark:text-indigo-400">assignment_turned_in</span>
</div>
<h2 class="text-sm font-semibold text-text-light dark:text-text-dark flex items-center">MOA</h2>
</div>
<span id="counter-moa" class="text-xl font-bold text-text-light dark:text-text-dark">0</span>
</div>
</div>
</div>
</div>
<div class="w-full xl:w-2/5 xl:max-w-md">
 
<div id="documentsReportCard" class="bg-card-light dark:bg-card-dark p-6 rounded-lg shadow-sm border border-border-light dark:border-border-dark mt-2">
<div class="flex justify-between items-center">
<h2 class="text-sm font-semibold">Documents Report</h2>
<div class="relative">
<select id="monthFilter" class="pl-4 pr-8 py-2 bg-background-light dark:bg-background-dark border border-border-light dark:border-border-dark rounded-md text-sm">
<option value="all">All Time</option>
</select>

</div>

</div>

                <div class="mt-2 flex items-center gap-8">
<div class="relative w-32 h-32">
<svg id="documentsChart" class="w-full h-full transform -rotate-90" viewBox="0 0 120 120">
<circle class="stroke-gray-200 dark:stroke-gray-700" cx="60" cy="60" fill="none" r="54" stroke-width="12"></circle>
<circle id="mouSegment" class="stroke-current text-purple-500" cx="60" cy="60" fill="none" r="54" stroke-dasharray="0 339.292" stroke-linecap="round" stroke-width="12" style="opacity:0"></circle>
<circle id="moaSegment" class="stroke-current text-blue-500" cx="60" cy="60" fill="none" r="54" stroke-dasharray="0 339.292" stroke-dashoffset="0" stroke-linecap="round" stroke-width="12" style="opacity:0"></circle>
<circle id="templatesSegment" class="stroke-current text-green-500" cx="60" cy="60" fill="none" r="54" stroke-dasharray="0 339.292" stroke-dashoffset="0" stroke-linecap="round" stroke-width="12" style="opacity:0"></circle>
</svg>
<div class="absolute inset-0 flex flex-col items-center justify-center">
</div>

</div>

                <div class="flex-1">
<p class="text-sm text-text-muted-light dark:text-text-muted-dark">Document Categories</p>
<div class="flex items-baseline gap-2 mt-1">
<span id="mostUploadedCategory" class="text-base font-bold text-text-light dark:text-text-dark"></span>
</div>
<ul class="mt-2 space-y-3 text-sm">
<li class="flex justify-between items-center">
<div class="flex items-center gap-2">
<span class="w-2 h-2 rounded-full bg-purple-500"></span>
<span class="text-text-muted-light dark:text-text-muted-dark">MOU</span>
</div>
<span id="mouCount" class="font-medium text-text-light dark:text-text-dark">0</span>
</li>
<li class="flex justify-between items-center">
<div class="flex items-center gap-2">
<span class="w-2 h-2 rounded-full bg-blue-500"></span>
<span class="text-text-muted-light dark:text-text-muted-dark">MOA</span>
</div>
<span id="moaCount" class="font-medium text-text-light dark:text-text-dark">0</span>
</li>
<li class="flex justify-between items-center">
<div class="flex items-center gap-2">
<span class="w-2 h-2 rounded-full bg-green-500"></span>
<span class="text-text-muted-light dark:text-text-muted-dark">Other Documents</span>
</div>
<span id="templatesCount" class="font-medium text-text-light dark:text-text-dark">0</span>
</li>
</ul>
</div>
</div>

 

</div>

</div>

</div>

<div class="mt-2">
<div class="flex flex-col md:flex-row justify-between md:items-center mt-10">
<div>
 
<p class="text-text-muted-light dark:text-text-muted-dark mt-1">Upload and track your documents here</p>
</div>
<div class="flex items-center gap-2 mt-2 md:mt-0">
<button class="px-4 py-2 text-sm font-medium rounded-md bg-primary text-white flex items-center gap-2" id="addDocumentBtn">
<span class="material-symbols-outlined text-sm">add</span>
Add Document
</button>
</div>
</div>
<div class="mt-2 bg-card-light dark:bg-card-dark p-4 rounded-lg shadow-sm border border-border-light dark:border-border-dark">
<div class="overflow-x-auto">
<table class="w-full min-w-[800px] text-sm text-left">
<thead class="text-sm text-text-muted-light dark:text-text-muted-dark uppercase border-b border-border-light dark:border-border-dark">
<tr>
<th class="py-3 px-4 font-medium" scope="col">Name/Title of the Document</th>
<th class="py-3 px-4 font-medium" scope="col">Category</th>
<th class="py-3 px-4 font-medium" scope="col">Date Uploaded</th>
<th class="py-3 px-4 font-medium" scope="col">Actions</th>
</tr>

</thead>

<tbody id="documents-table-body">

<tr id="no-documents-row">
<td class="py-6 px-4 text-center text-text-muted-light dark:text-text-muted-dark" colspan="4">No documents yet</td>
</tr>

</tbody>

</table>

</div>
<div class="flex justify-between items-center mt-2 pt-4 border-t border-border-light dark:border-border-dark text-sm">
<button id="pagination-prev" class="flex items-center gap-1 px-3 py-2 rounded-md border border-border-light dark:border-border-dark bg-card-light dark:bg-card-dark hover:bg-gray-100 dark:hover:bg-slate-700">
<span class="material-icons text-base">chevron_left</span> Prev
          </button>
<nav>
<ul id="pagination-pages" class="flex items-center -space-x-px"></ul>
</nav>
<button id="pagination-next" class="flex items-center gap-1 px-3 py-2 rounded-md border border-border-light dark:border-border-dark bg-card-light dark:bg-card-dark hover:bg-gray-100 dark:hover:bg-slate-700">
            Next <span class="material-icons text-base">chevron_right</span>
</button>
</div>
</div>
</div>

</div>

</main>

</div>

<script>
        // Pagination variables - declare at top level
        let currentPage = 1;
        let totalPages = 1;
        const itemsPerPage = 10;
        
        // Filter state
        let currentFilter = null; // null = all, 'MOU', 'MOA', 'Other Documents'
        let allDocuments = []; // Store all documents for filtering
        let currentMonthFilter = 'all'; // 'all' or 'YYYY-MM' format

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

            // Helper: Render documents table rows with the exact structure we expect
            const documentsTableBody = document.getElementById('documents-table-body');
            const noDocumentsRow = document.getElementById('no-documents-row');
            
            // Pagination elements - declare early so they're available for updatePagination
            const prevBtn = document.getElementById('pagination-prev');
            const nextBtn = document.getElementById('pagination-next');
            const pagesEl = document.getElementById('pagination-pages');

            function renderDocuments(documents, filterCategory = null) {
                if (!documentsTableBody) return;

                // Filter documents by category if filter is applied
                let filteredDocuments = documents;
                if (filterCategory) {
                    filteredDocuments = documents.filter(doc => {
                        const cat = doc && doc.category ? String(doc.category).trim() : '';
                        if (filterCategory === 'MOU') {
                            return cat.includes('MOU') || cat === 'MOU';
                        } else if (filterCategory === 'MOA') {
                            return cat.includes('MOA') || cat === 'MOA';
                        } else if (filterCategory === 'Other Documents') {
                            return cat === 'Other Documents' || cat === 'Other';
                        }
                        return false;
                    });
                }

                // If no documents, show placeholder row
                if (!Array.isArray(filteredDocuments) || filteredDocuments.length === 0) {
                    noDocumentsRow.style.display = '';
                    // Remove any existing document rows
                    const existingRows = documentsTableBody.querySelectorAll('tr:not(#no-documents-row)');
                    existingRows.forEach(row => row.remove());
                    return;
                }

                // Hide placeholder row
                noDocumentsRow.style.display = 'none';

                // Remove existing document rows first
                const existingRows = documentsTableBody.querySelectorAll('tr:not(#no-documents-row)');
                existingRows.forEach(row => row.remove());

                // Reset to page 1 when filtering
                currentPage = 1;
                
                // Add new document rows
                filteredDocuments.forEach((doc, idx) => {
                    const name = (doc && doc.title) ? doc.title : (doc && doc.name) ? doc.name : 'Untitled Document';
                    const category = (doc && doc.category) ? doc.category : 'Other';
                    const uploaded = (doc && doc.created_at) ?
                        new Date(doc.created_at).toLocaleDateString() :
                        (doc && doc.uploadDate) ?
                        new Date(doc.uploadDate).toLocaleDateString() :
                        (doc && doc.dateUploaded) ? doc.dateUploaded : 'Unknown';
                    const id = (doc && (doc.id !== undefined && doc.id !== null)) ? doc.id : String(idx);
                    const sourceTable = (doc && doc.source_table) ? doc.source_table : 'unknown';
                    const filePath = (doc && doc.file_path) ? doc.file_path : '';

                    const row = document.createElement('tr');
                    row.className = 'border-b border-border-light dark:border-border-dark hover:bg-gray-50 dark:hover:bg-slate-800';
                    row.innerHTML = `
                        <td class="py-4 px-4 font-medium text-text-light dark:text-text-dark">${name}</td>
                        <td class="py-4 px-4 text-text-muted-light dark:text-text-muted-dark text-center">${category}</td>
                        <td class="py-4 px-4 text-text-muted-light dark:text-text-muted-dark text-center">${uploaded}</td>
                        <td class="py-4 px-4">
                            <div class="flex items-center gap-2">
                                <button class="p-2 rounded-md hover:bg-gray-100 dark:hover:bg-slate-700" aria-label="View" data-action="view" data-id="${id}" data-source="${sourceTable}" data-file="${filePath}">
                                    <span class="material-icons text-base text-text-muted-light dark:text-text-muted-dark">visibility</span>
                                </button>
                                <button class="p-2 rounded-md hover:bg-gray-100 dark:hover:bg-slate-700" aria-label="Edit" data-action="edit" data-id="${id}" data-source="${sourceTable}">
                                    <span class="material-icons text-base text-text-muted-light dark:text-text-muted-dark">edit</span>
                                </button>
                                <button class="p-2 rounded-md hover:bg-gray-100 dark:hover:bg-slate-700 text-red-600 dark:text-red-400" aria-label="Delete" data-action="delete" data-id="${id}" data-source="${sourceTable}">
                                    <span class="material-icons text-base">delete</span>
                                </button>
                            </div>
                        </td>
                    `;
                    documentsTableBody.appendChild(row);
                });
                
                // Apply pagination after rendering
                applyPagination();

                // Update top counters by category (always use all documents, not filtered)
                updateTopCounters(allDocuments);
                
                // Update Documents Report section (use filtered documents for month filter)
                updateDocumentsReport(filteredDocuments);
                
                // Update pagination based on filtered documents
                updatePagination(filteredDocuments.length);
            }

            function updateTopCounters(documents) {
                try {
                    const counts = { total: 0, MOU: 0, MOA: 0, 'Other Documents': 0 };
                    counts.total = Array.isArray(documents) ? documents.length : 0;
                    if (Array.isArray(documents)) {
                        documents.forEach(d => {
                            const cat = d && d.category ? String(d.category).trim() : '';
                            // Handle both short forms and full text
                            if (cat.includes('MOU') || cat === 'MOU') {
                                counts.MOU++;
                            } else if (cat.includes('MOA') || cat === 'MOA') {
                                counts.MOA++;
                            } else if (cat === 'Other Documents' || cat === 'Other') {
                                counts['Other Documents']++;
                            }
                        });
                    }

                    const setText = (id, value) => {
                        const el = document.getElementById(id);
                        if (el) el.textContent = String(value);
                    };
                    setText('counter-total', counts.total);
                    setText('counter-mou', counts.MOU);
                    setText('counter-templates', counts['Other Documents']);
                    setText('counter-moa', counts.MOA);
                } catch (error) {
                    console.error('Error updating counters:', error);
                }
            }
            
            function updateDocumentsReport(documents) {
                try {
                    // Calculate counts from filtered documents
                    const counts = { total: 0, MOU: 0, MOA: 0, 'Other Documents': 0 };
                    counts.total = Array.isArray(documents) ? documents.length : 0;
                    if (Array.isArray(documents)) {
                        documents.forEach(d => {
                            const cat = d && d.category ? String(d.category).trim() : '';
                            // Handle both short forms and full text
                            if (cat.includes('MOU') || cat === 'MOU') {
                                counts.MOU++;
                            } else if (cat.includes('MOA') || cat === 'MOA') {
                                counts.MOA++;
                            } else if (cat === 'Other Documents' || cat === 'Other') {
                                counts['Other Documents']++;
                            }
                        });
                    }

                    // Update individual counts in Documents Report
                    const setText = (id, value) => {
                        const el = document.getElementById(id);
                        if (el) el.textContent = String(value);
                    };
                    setText('mouCount', counts.MOU);
                    setText('moaCount', counts.MOA);
                    setText('templatesCount', counts['Other Documents']);

                    // Find most uploaded category
                    let mostUploaded = '';
                    let maxCount = 0;
                    if (counts.MOU > maxCount) {
                        mostUploaded = 'MOU';
                        maxCount = counts.MOU;
                    }
                    if (counts.MOA > maxCount) {
                        mostUploaded = 'MOA';
                        maxCount = counts.MOA;
                    }
                    if (counts['Other Documents'] > maxCount) {
                        mostUploaded = 'Other Documents';
                        maxCount = counts['Other Documents'];
                    }

                    const mostUploadedEl = document.getElementById('mostUploadedCategory');
                    if (mostUploadedEl) {
                        mostUploadedEl.textContent = maxCount > 0 ? `${mostUploaded} (${maxCount} documents)` : '';
                    }

                    // Update donut chart
                    updateDonutChart(counts);

                } catch (error) {
                    console.error('Error updating documents report:', error);
                }
            }

            function updatePagination(totalDocuments) {
                try {
                    // Calculate total pages based on document count and items per page
                    totalPages = Math.max(1, Math.ceil(totalDocuments / itemsPerPage));
                    
                    // Reset to page 1 if current page is beyond total pages
                    if (currentPage > totalPages) {
                        currentPage = 1;
                    }
                    
                    // Render pagination with updated values
                    renderPagination();
                    
                    // Apply pagination to displayed documents
                    applyPagination();
                } catch (error) {
                    console.error('Error updating pagination:', error);
                }
            }
            
            // Apply pagination to the document table
            function applyPagination() {
                const rows = documentsTableBody.querySelectorAll('tr:not(#no-documents-row)');
                const startIndex = (currentPage - 1) * itemsPerPage;
                const endIndex = startIndex + itemsPerPage;
                
                rows.forEach((row, index) => {
                    if (index >= startIndex && index < endIndex) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }


            function updateDonutChart(counts) {
                try {
                    const total = counts.MOU + counts.MOA + counts['Other Documents'];
                    if (total === 0) {
                        // Hide all segments if no documents
                        const mouSegment = document.getElementById('mouSegment');
                        const moaSegment = document.getElementById('moaSegment');
                        const templatesSegment = document.getElementById('templatesSegment');

                        if (mouSegment) {
                            mouSegment.style.strokeDasharray = '0 339.292';
                            mouSegment.style.opacity = '0';
                        }
                        if (moaSegment) {
                            moaSegment.style.strokeDasharray = '0 339.292';
                            moaSegment.style.opacity = '0';
                        }
                        if (templatesSegment) {
                            templatesSegment.style.strokeDasharray = '0 339.292';
                            templatesSegment.style.opacity = '0';
                        }
                        return;
                    }
                    
                    // Reset opacity when there is data, but only show segments with count > 0
                    const mouSegment = document.getElementById('mouSegment');
                    const moaSegment = document.getElementById('moaSegment');
                    const templatesSegment = document.getElementById('templatesSegment');

                    const circumference = 339.292;
                    let currentOffset = 0;

                    // MOU segment (purple)
                    if (counts.MOU > 0) {
                        const mouPercentage = (counts.MOU / total) * 100;
                        const mouLength = (mouPercentage / 100) * circumference;
                        if (mouSegment) {
                            mouSegment.style.opacity = '1';
                            mouSegment.style.strokeDasharray = `${mouLength} ${circumference}`;
                            mouSegment.style.strokeDashoffset = `-${currentOffset}`;
                        }
                        currentOffset += mouLength;
                    } else {
                        if (mouSegment) {
                            mouSegment.style.opacity = '0';
                            mouSegment.style.strokeDasharray = '0 339.292';
                        }
                    }

                    // MOA segment (blue)
                    if (counts.MOA > 0) {
                        const moaPercentage = (counts.MOA / total) * 100;
                        const moaLength = (moaPercentage / 100) * circumference;
                        if (moaSegment) {
                            moaSegment.style.opacity = '1';
                            moaSegment.style.strokeDasharray = `${moaLength} ${circumference}`;
                            moaSegment.style.strokeDashoffset = `-${currentOffset}`;
                        }
                        currentOffset += moaLength;
                    } else {
                        if (moaSegment) {
                            moaSegment.style.opacity = '0';
                            moaSegment.style.strokeDasharray = '0 339.292';
                        }
                    }

                    // Other Documents segment (green)
                    if (counts['Other Documents'] > 0) {
                        const otherDocsPercentage = (counts['Other Documents'] / total) * 100;
                        const otherDocsLength = (otherDocsPercentage / 100) * circumference;
                        if (templatesSegment) {
                            templatesSegment.style.opacity = '1';
                            templatesSegment.style.strokeDasharray = `${otherDocsLength} ${circumference}`;
                            templatesSegment.style.strokeDashoffset = `-${currentOffset}`;
                        }
                        currentOffset += otherDocsLength;
                    } else {
                        if (templatesSegment) {
                            templatesSegment.style.opacity = '0';
                            templatesSegment.style.strokeDasharray = '0 339.292';
                        }
                    }

                } catch (error) {
                    console.error('Error updating donut chart:', error);
                }
            }

            // Extract unique months from documents
            function getUniqueMonths(documents) {
                const monthMap = new Map();
                documents.forEach(doc => {
                    if (doc && doc.created_at) {
                        const date = new Date(doc.created_at);
                        if (!isNaN(date.getTime())) {
                            const monthKey = date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
                            const sortKey = date.getFullYear() * 12 + date.getMonth(); // Year * 12 + month for sorting
                            if (!monthMap.has(monthKey) || monthMap.get(monthKey) < sortKey) {
                                monthMap.set(monthKey, sortKey);
                            }
                        }
                    }
                });
                // Sort months in descending order (newest first)
                return Array.from(monthMap.keys()).sort((a, b) => {
                    return monthMap.get(b) - monthMap.get(a);
                });
            }
            
            // Populate month filter dropdown
            function populateMonthFilter(documents) {
                const monthFilter = document.getElementById('monthFilter');
                if (!monthFilter) return;
                
                const months = getUniqueMonths(documents);
                const currentValue = monthFilter.value;
                
                // Clear existing options except "All Time"
                monthFilter.innerHTML = '<option value="all">All Time</option>';
                
                // Add month options
                months.forEach(month => {
                    const option = document.createElement('option');
                    option.value = month;
                    option.textContent = month;
                    monthFilter.appendChild(option);
                });
                
                // Restore previous selection if it still exists
                if (currentValue && currentValue !== 'all') {
                    const optionExists = Array.from(monthFilter.options).some(opt => opt.value === currentValue);
                    if (optionExists) {
                        monthFilter.value = currentValue;
                    }
                }
            }
            
            // Filter documents by month
            function filterByMonth(documents, monthFilter) {
                if (monthFilter === 'all') {
                    return documents;
                }
                
                return documents.filter(doc => {
                    if (!doc || !doc.created_at) return false;
                    const docDate = new Date(doc.created_at);
                    if (isNaN(docDate.getTime())) return false;
                    const docMonth = docDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
                    return docMonth === monthFilter;
                });
            }

            // Load documents from localStorage on page load
            function loadDocuments() {
                try {
                    // Load documents from PHP (includes MOU, MOA, and Other Documents)
                    allDocuments = <?php echo json_encode($documents); ?>;
                    
                    // Populate month filter
                    populateMonthFilter(allDocuments);
                    
                    // Apply filters and render
                    applyFiltersAndRender();

                    // Add uppercase conversion for title input
                    const titleInput = document.getElementById('documentTitle');
                    if (titleInput) {
                        titleInput.addEventListener('input', function(e) {
                            const cursorPosition = e.target.selectionStart;
                            e.target.value = e.target.value.toUpperCase();
                            e.target.setSelectionRange(cursorPosition, cursorPosition);
                        });
                    }
                } catch (error) {
                    console.error('Error loading documents:', error);
                    renderDocuments([]);
                }
            }
            
            // Apply all filters (category and month) and render
            function applyFiltersAndRender() {
                let filtered = allDocuments;
                
                // Apply month filter
                filtered = filterByMonth(filtered, currentMonthFilter);
                
                // Apply category filter
                renderDocuments(filtered, currentFilter);
            }

            // Load documents when page loads
            loadDocuments();

            // Expose for future integration (e.g., API fetch -> renderDocuments(data))
            window.renderDocuments = renderDocuments;
            
            // Filter documents by category when counter is clicked
            function filterByCategory(category) {
                // Toggle filter: if same category is clicked again, show all
                if (currentFilter === category) {
                    currentFilter = null;
                } else {
                    currentFilter = category;
                }
                
                // Apply all filters and render
                applyFiltersAndRender();
            }
            
            // Handle month filter change
            const monthFilter = document.getElementById('monthFilter');
            if (monthFilter) {
                monthFilter.addEventListener('change', function(e) {
                    currentMonthFilter = e.target.value;
                    applyFiltersAndRender();
                });
            }
            
            // Add click handlers to counter cards
            const totalCounter = document.getElementById('counter-total');
            const mouCounter = document.getElementById('counter-mou');
            const moaCounter = document.getElementById('counter-moa');
            const templatesCounter = document.getElementById('counter-templates');
            
            if (totalCounter) {
                const totalCard = totalCounter.closest('.doc-counter-card');
                if (totalCard) {
                    totalCard.style.cursor = 'pointer';
                    totalCard.addEventListener('click', () => filterByCategory(null));
                }
            }
            
            if (mouCounter) {
                const mouCard = mouCounter.closest('.doc-counter-card');
                if (mouCard) {
                    mouCard.style.cursor = 'pointer';
                    mouCard.addEventListener('click', () => filterByCategory('MOU'));
                }
            }
            
            if (moaCounter) {
                const moaCard = moaCounter.closest('.doc-counter-card');
                if (moaCard) {
                    moaCard.style.cursor = 'pointer';
                    moaCard.addEventListener('click', () => filterByCategory('MOA'));
                }
            }
            
            if (templatesCounter) {
                const templatesCard = templatesCounter.closest('.doc-counter-card');
                if (templatesCard) {
                    templatesCard.style.cursor = 'pointer';
                    templatesCard.addEventListener('click', () => filterByCategory('Other Documents'));
                }
            }

            // Delegated click handling for Actions (View/Edit/Delete)
            if (documentsTableBody) {
                documentsTableBody.addEventListener('click', async (event) => {
                    const target = event.target.closest('button[data-action]');
                    if (!target) return;
                    const action = target.getAttribute('data-action');
                    const id = target.getAttribute('data-id');
                    const source = target.getAttribute('data-source');
                    const filePath = target.getAttribute('data-file');

                    if (action === 'view') {
                        // Open file in new tab
                        if (filePath) {
                            window.open(filePath, '_blank');
                        } else {
                            alert('File path not found');
                        }
                    } else if (action === 'edit') {
                        // Redirect to appropriate edit page
                        if (source === 'mou_moa') {
                            window.location.href = 'mou-moa.php';
                        } else if (source === 'other_documents') {
                            alert('Editing other documents coming soon');
                        }
                    } else if (action === 'delete') {
                        if (confirm('Are you sure you want to delete this document?')) {
                            try {
                                let apiUrl = '';
                                if (source === 'mou_moa') {
                                    apiUrl = `api/mou-moa.php?id=${encodeURIComponent(id)}`;
                                } else if (source === 'other_documents') {
                                    apiUrl = `api/other-documents.php?id=${encodeURIComponent(id)}`;
                                }

                                const response = await fetch(apiUrl, { method: 'DELETE' });
                                const result = await response.json();

                                if (result.success) {
                                    alert('Document deleted successfully');
                                    location.reload(); // Reload page to refresh document list
                                } else {
                                    alert('Failed to delete: ' + (result.error || 'Unknown error'));
                                }
                            } catch (error) {
                                alert('Error deleting document: ' + error.message);
                            }
                        }
                    }
                });
            }

            // Global delete handler so other pages can hook consistent behavior
            window.onDeleteDocument = async function(id) {
                if (!id) return;
                if (!confirm('Delete document?')) return;
                try {
                    // Remove from Documents storage
                    const existingDocuments = JSON.parse(localStorage.getItem('documents') || '[]');
                    const updatedDocuments = existingDocuments.filter(doc => doc.id !== id);
                    localStorage.setItem('documents', JSON.stringify(updatedDocuments));

                    // Also remove corresponding MOU/MOA entry (imported from Documents)
                    try {
                        const MOU_KEY = 'mou_moa_entries';
                        const mouEntries = JSON.parse(localStorage.getItem(MOU_KEY) || '[]');
                        const filteredMou = mouEntries.filter(entry => Number(entry.id) !== Number(id));
                        localStorage.setItem(MOU_KEY, JSON.stringify(filteredMou));
                    } catch (_) {}

                    // Best-effort backend delete
                    try { await fetch(`api/mou-moa.php?id=${encodeURIComponent(id)}`, { method: 'DELETE' }); } catch (_) {}

                    // Update UI
                    renderDocuments(updatedDocuments);
                } catch (e) {
                    console.error('Delete failed', e);
                }
            };

            // Prevent navigating when clicking the current page link

            // Pagination logic (client-side demo)
            // Note: prevBtn, nextBtn, and pagesEl are declared earlier

            function renderPagination() {
                if (!pagesEl) return;
                const maxAround = 3; // show 1.. then current +/- up to 3, then .. last

                const parts = [];
                function pageItem(page, { active = false, disabled = false } = {}) {
                    const activeClass = active ? 'z-10 px-3 py-2 leading-tight text-white bg-primary rounded-md' : 'px-3 py-2 leading-tight text-text-muted-light dark:text-text-muted-dark hover:text-primary dark:hover:text-primary';
                    const aria = active ? ' aria-current="page"' : '';
                    return `<li><a${aria} data-page="${page}" class="${activeClass}" href="#">${page}</a></li>`;
                }

                // Always show page 1
                parts.push(pageItem(1, { active: currentPage === 1 }));

                // Left ellipsis
                if (currentPage - maxAround > 2) {
                    parts.push('<li><span class="px-3 py-2 leading-tight">...</span></li>');
                }

                // Middle pages
                const start = Math.max(2, currentPage - maxAround);
                const end = Math.min(totalPages - 1, currentPage + maxAround);
                for (let p = start; p <= end; p++) {
                    parts.push(pageItem(p, { active: p === currentPage }));
                }

                // Right ellipsis
                if (currentPage + maxAround < totalPages - 1) {
                    parts.push('<li><span class="px-3 py-2 leading-tight">...</span></li>');
                }

                // Last page
                if (totalPages > 1) {
                    parts.push(pageItem(totalPages, { active: currentPage === totalPages }));
                }

                pagesEl.innerHTML = parts.join('');
            }

            function setPage(page) {
                const newPage = Math.max(1, Math.min(totalPages, page));
                if (newPage === currentPage) return;
                currentPage = newPage;
                renderPagination();
                // Apply pagination to currently displayed documents
                applyPagination();
                // Scroll to top of table
                documentsTableBody.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            // Wire events
            if (prevBtn) prevBtn.addEventListener('click', (e) => { e.preventDefault(); setPage(currentPage - 1); });
            if (nextBtn) nextBtn.addEventListener('click', (e) => { e.preventDefault(); setPage(currentPage + 1); });
            if (pagesEl) pagesEl.addEventListener('click', (e) => {
                const target = e.target;
                if (target && target.matches('a[data-page]')) {
                    e.preventDefault();
                    const page = parseInt(target.getAttribute('data-page'), 10);
                    if (!Number.isNaN(page)) setPage(page);
                }
            });

            renderPagination();

            // Match counter card heights to the Documents Report card (desktop only)
            function syncCounterHeights() {
                const report = document.getElementById('documentsReportCard');
                const counters = document.querySelectorAll('.doc-counter-card');
                // Reset heights first
                counters.forEach(c => c.style.minHeight = '');
                if (!report) return;
                const isDesktop = window.matchMedia('(min-width: 1024px)').matches; // ~lg breakpoint
                if (!isDesktop) return;
                const h = report.getBoundingClientRect().height;
                counters.forEach(c => c.style.minHeight = h / 2 - 12 + 'px'); // two rows, minus small gap
            }

            window.addEventListener('resize', syncCounterHeights);
            // defer to ensure layout complete
            setTimeout(syncCounterHeights, 0);

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

                if (isCollapsed) {

                    appContainer.classList.remove('sidebar-collapsed');

                    appContainer.classList.add('sidebar-expanded');

                    sidebar.style.width = '';

                    mainContent.style.marginLeft = '';

                    sidebarLogoText.classList.remove('hidden');

                    sidebarTexts.forEach(text => text.classList.remove('hidden'));

                    sidebarProfileInfo.classList.remove('hidden');

                    sidebarProfilePicture.classList.remove('hidden');

                    openIcon.style.display = 'block';

                    closedIcon.style.display = 'none';

                    navLinks.forEach(link => link.classList.remove('justify-center'));

                    profileContainer.classList.remove('justify-center');

                    toggleContainer.classList.remove('justify-center');

                } else {

                    appContainer.classList.add('sidebar-collapsed');

                    appContainer.classList.remove('sidebar-expanded');

                    sidebar.style.width = '';

                    mainContent.style.marginLeft = '';

                    sidebarLogoText.classList.add('hidden');

                    sidebarTexts.forEach(text => text.classList.add('hidden'));

                    sidebarProfileInfo.classList.add('hidden');

                    sidebarProfilePicture.classList.add('hidden');

                    openIcon.style.display = 'none';

                    closedIcon.style.display = 'block';

                    navLinks.forEach(link => link.classList.add('justify-center'));

                    profileContainer.classList.add('justify-center');

                    toggleContainer.classList.add('justify-center');

                }

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

            });



            // Advanced Search toggle

            const advancedSearchToggle = document.getElementById('advanced-search-toggle');

            const advancedSearchSection = document.getElementById('advanced-search-section');

            if (advancedSearchToggle && advancedSearchSection) {

                advancedSearchToggle.addEventListener('click', () => {

                    advancedSearchSection.classList.toggle('hidden');

                });

            }

            // Chart.js rendering

            const renderChart = () => {

                const canvas = document.getElementById('awardsProgressChart');

                if (!canvas) {

                    return; // Skip rendering if chart element is not present on this page state

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

                            'rgba(19, 127, 236, 0.2)',

                            'rgba(34, 197, 94, 0.2)',

                            'rgba(234, 179, 8, 0.2)',

                            'rgba(239, 68, 68, 0.2)',

                            'rgba(99, 102, 241, 0.2)',

                        ],

                        borderColor: [

                            'rgb(19, 127, 236)',

                            'rgb(34, 197, 94)',

                            'rgb(234, 179, 8)',

                            'rgb(239, 68, 68)',

                            'rgb(99, 102, 241)',

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

                        legend: { display: false },

                        tooltip: {

                            callbacks: {

                                label: function(context) {

                                    let label = context.dataset.label || '';

                                    if (label) label += ': ';

                                    if (context.parsed.y !== null) label += context.parsed.y + '%';

                                    return label;

                                }

                            }

                        }

                    },

                    scales: {

                        y: {

                            beginAtZero: true,

                            max: 100,

                            grid: { color: gridColor, drawBorder: false },

                            ticks: { color: tickColor, padding: 10, callback: v => v + '%' }

                        },

                        x: { grid: { display: false }, ticks: { color: tickColor, padding: 10 } }

                    }

                };

                window.awardsProgressChartInstance = new Chart(ctx, { type: 'bar', data, options });

            };

            renderChart();

            // Modal functionality
            const addDocumentBtn = document.getElementById('addDocumentBtn');
            const addDocumentModal = document.getElementById('addDocumentModal');
            const closeModal = document.getElementById('closeModal');
            const cancelModal = document.getElementById('cancelModal');
            const addDocumentForm = document.getElementById('addDocumentForm');
            const documentFile = document.getElementById('documentFile');
            const fileDropZone = document.getElementById('fileDropZone');
            const filePreview = document.getElementById('filePreview');
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');
            const removeFile = document.getElementById('removeFile');

            // Open modal
            addDocumentBtn.addEventListener('click', () => {
                addDocumentModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            });

            // Close modal functions
            const closeModalFunc = () => {
                addDocumentModal.classList.add('hidden');
                document.body.style.overflow = '';
                addDocumentForm.reset();
                filePreview.classList.add('hidden');
                documentFile.value = '';
                
                // Reset classification result
                const classificationResult = document.getElementById('classificationResult');
                classificationResult.classList.add('hidden');
                documentFile.removeAttribute('data-classification');
                documentFile.removeAttribute('data-confidence');
            };

            closeModal.addEventListener('click', closeModalFunc);
            cancelModal.addEventListener('click', closeModalFunc);

            // Close modal when clicking outside
            addDocumentModal.addEventListener('click', (e) => {
                if (e.target === addDocumentModal) {
                    closeModalFunc();
                }
            });

            // Close modal with Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !addDocumentModal.classList.contains('hidden')) {
                    closeModalFunc();
                }
            });

            // File upload functionality
            fileDropZone.addEventListener('click', () => {
                documentFile.click();
            });

            // Drag and drop functionality
            fileDropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                fileDropZone.classList.add('border-primary', 'bg-primary/5');
            });

            fileDropZone.addEventListener('dragleave', () => {
                fileDropZone.classList.remove('border-primary', 'bg-primary/5');
            });

            fileDropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                fileDropZone.classList.remove('border-primary', 'bg-primary/5');
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    documentFile.files = files;
                    handleFileSelect();
                }
            });

            documentFile.addEventListener('change', handleFileSelect);

            function handleFileSelect() {
                const file = documentFile.files[0];
                if (file) {
                    // Check file size (10MB limit)
                    if (file.size > 10 * 1024 * 1024) {
                        alert('File size must be less than 10MB');
                        documentFile.value = '';
                        return;
                    }
                    
                    fileName.textContent = file.name;
                    fileSize.textContent = formatFileSize(file.size);
                    filePreview.classList.remove('hidden');
                    
                    // Automatically analyze the document for classification
                    analyzeDocument(file);
                }
            }

            // Quick filename-only classification for instant feedback
            function quickClassifyByFilename(filename) {
                return classifyDocument(filename, filename);
            }

            async function analyzeDocument(file) {
                const classificationResult = document.getElementById('classificationResult');
                const classificationText = document.getElementById('classificationText');
                
                // Show loading state
                classificationResult.classList.remove('hidden');
                classificationText.textContent = 'Analyzing document content...';
                
                try {
                    let extractedText = '';
                    
                    // Extract text based on file type
                    if (file.type === 'application/pdf') {
                        extractedText = await extractTextFromPDF(file);
                    } else if (file.type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || 
                               file.type === 'application/msword') {
                        extractedText = await extractTextFromWord(file);
                    } else if (file.type === 'text/plain') {
                        extractedText = await extractTextFromTxt(file);
                    } else if (file.type.startsWith('image/')) {
                        // 1) Instant, filename-only classification while OCR runs in background
                        const quick = quickClassifyByFilename(file.name);
                        documentFile.dataset.classification = quick.category;
                        documentFile.dataset.confidence = quick.confidence;
                        classificationText.textContent = `Quick classify: ${quick.category} - ${quick.confidence}% (refining with OCR...)`;

                        // 2) Kick off OCR in background (no blocking)
                        extractTextFromImage(file).then(text => {
                            const refined = classifyDocument(text, file.name);
                            // Only update if result is different or confidence is higher
                            const currentCat = documentFile.dataset.classification;
                            const currentConf = parseInt(documentFile.dataset.confidence || '0', 10);
                            if (refined.category !== currentCat || parseInt(refined.confidence, 10) > currentConf) {
                                documentFile.dataset.classification = refined.category;
                                documentFile.dataset.confidence = refined.confidence;
                                classificationText.textContent = `Classified as: ${refined.category} - ${refined.confidence}% confidence`;
                            }
                        }).catch(() => {/* keep quick result */});

                        // Return early; we already set a quick result
                        return;
                    } else {
                        // For other file types, classify based on filename
                        extractedText = file.name;
                    }
                    
                    // Classify the document
                    const classification = classifyDocument(extractedText, file.name);
                    
                    // Update UI with classification result
                    classificationText.textContent = `Classified as: ${classification.category} - ${classification.confidence}% confidence`;
                    
                    // Store classification for form submission
                    documentFile.dataset.classification = classification.category;
                    documentFile.dataset.confidence = classification.confidence;
                    
                } catch (error) {
                    console.error('Error analyzing document:', error);
                    classificationText.textContent = 'Unable to analyze document. Will be classified as "Other".';
                    documentFile.dataset.classification = 'Other';
                    documentFile.dataset.confidence = '0';
                }
            }

            async function extractTextFromPDF(file) {
                return new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = async function(e) {
                        try {
                            const typedarray = new Uint8Array(e.target.result);
                            const pdf = await pdfjsLib.getDocument({ data: typedarray }).promise;
                            let text = '';
                            
                            for (let i = 1; i <= pdf.numPages; i++) {
                                const page = await pdf.getPage(i);
                                const textContent = await page.getTextContent();
                                text += textContent.items.map(item => item.str).join(' ') + ' ';
                            }
                            
                            resolve(text);
                        } catch (error) {
                            reject(error);
                        }
                    };
                    reader.readAsArrayBuffer(file);
                });
            }

            async function extractTextFromWord(file) {
                return new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        mammoth.extractRawText({ arrayBuffer: e.target.result })
                            .then(result => resolve(result.value))
                            .catch(error => reject(error));
                    };
                    reader.readAsArrayBuffer(file);
                });
            }

            async function extractTextFromTxt(file) {
                return new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        resolve(e.target.result);
                    };
                    reader.onerror = reject;
                    reader.readAsText(file);
                });
            }

            async function extractTextFromImage(file) {
                return new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = async function(e) {
                        try {
                            // Update progress message
                            const classificationText = document.getElementById('classificationText');
                            classificationText.textContent = 'Extracting text from image using OCR...';
                            
                            // Downscale image to speed up OCR (max dimension 1200px)
                            const img = new Image();
                            img.src = e.target.result;
                            await img.decode();
                            const maxDim = 1200;
                            const scale = Math.min(1, maxDim / Math.max(img.width, img.height));
                            const canvas = document.createElement('canvas');
                            canvas.width = Math.round(img.width * scale);
                            canvas.height = Math.round(img.height * scale);
                            const ctx = canvas.getContext('2d');
                            // Draw grayscale for better OCR and performance
                            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                            const data = imageData.data;
                            for (let i = 0; i < data.length; i += 4) {
                                const r = data[i], g = data[i+1], b = data[i+2];
                                const y = (r*0.299 + g*0.587 + b*0.114) | 0;
                                data[i] = data[i+1] = data[i+2] = y;
                            }
                            ctx.putImageData(imageData, 0, 0);
                            const optimizedDataUrl = canvas.toDataURL('image/jpeg', 0.7);

                            // Use Tesseract.js for OCR on optimized image
                            const { data: { text } } = await Tesseract.recognize(
                                optimizedDataUrl,
                                'eng',
                                {
                                    logger: (m) => {
                                        if (m.status === 'recognizing text') {
                                            const progress = Math.round(m.progress * 100);
                                            classificationText.textContent = `OCR Progress: ${progress}%`;
                                        }
                                    }
                                }
                            );
                            
                            console.log('OCR extracted text:', text);
                            resolve(text || file.name); // Fallback to filename if no text extracted
                        } catch (error) {
                            console.error('OCR error:', error);
                            // If OCR fails, fallback to filename analysis
                            resolve(file.name);
                        }
                    };
                    reader.onerror = reject;
                    reader.readAsDataURL(file);
                });
            }

            function classifyDocument(text, filename) {
                const textToAnalyze = (text + ' ' + filename).toLowerCase();
                
                // MOU keywords
                const mouKeywords = [
                    'memorandum of understanding', 'mou', 'understanding', 'collaboration agreement',
                    'partnership understanding', 'mutual understanding', 'cooperation agreement',
                    'inter-institutional agreement'
                ];
                
                // MOA keywords
                const moaKeywords = [
                    'memorandum of agreement', 'moa', 'agreement', 'service agreement',
                    'contract agreement', 'binding agreement', 'formal agreement',
                    'cooperative agreement', 'partnership agreement'
                ];
                
                // Other Documents keywords
                const otherDocsKeywords = [
                    'template', 'form', 'sample', 'format', 'draft', 'example',
                    'boilerplate', 'standard form', 'application form', 'report form'
                ];

                let mouScore = 0;
                let moaScore = 0;
                let otherDocsScore = 0;
                
                // Calculate scores for each category
                mouKeywords.forEach(keyword => {
                    if (textToAnalyze.includes(keyword)) {
                        mouScore += keyword.length; // Longer keywords get higher scores
                    }
                });
                
                moaKeywords.forEach(keyword => {
                    if (textToAnalyze.includes(keyword)) {
                        moaScore += keyword.length;
                    }
                });

                otherDocsKeywords.forEach(keyword => {
                    if (textToAnalyze.includes(keyword)) {
                        otherDocsScore += keyword.length;
                    }
                });

                // Determine classification
                let category = 'Other';
                let confidence = 0;

                if (mouScore > moaScore && mouScore > otherDocsScore && mouScore > 0) {
                    category = 'MOU';
                    confidence = Math.min(95, Math.max(60, (mouScore / (mouScore + moaScore + otherDocsScore)) * 100));
                } else if (moaScore > mouScore && moaScore > otherDocsScore && moaScore > 0) {
                    category = 'MOA';
                    confidence = Math.min(95, Math.max(60, (moaScore / (mouScore + moaScore + otherDocsScore)) * 100));
                } else if (otherDocsScore > mouScore && otherDocsScore > moaScore && otherDocsScore > 0) {
                    category = 'Other Documents';
                    confidence = Math.min(95, Math.max(60, (otherDocsScore / (mouScore + moaScore + otherDocsScore)) * 100));
                } else {
                    category = 'Other';
                    confidence = 0;
                }
                
                return { category, confidence: Math.round(confidence) };
            }

            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }

            // Remove file
            removeFile.addEventListener('click', () => {
                documentFile.value = '';
                filePreview.classList.add('hidden');
            });

            // Form submission
            addDocumentForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const submitBtn = addDocumentForm.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                
                // Show loading state
                submitBtn.disabled = true;
                submitBtn.textContent = 'Uploading...';
                
                try {
                    const formData = new FormData(addDocumentForm);
                    const file = documentFile.files[0];
                    const classification = documentFile.dataset.classification || 'Other';
                    const confidence = documentFile.dataset.confidence || '0';
                    
                    const documentData = {
                        title: formData.get('title').toUpperCase(), // Convert title to uppercase
                        description: formData.get('description'),
                        category: classification,
                        confidence: confidence,
                        fileName: file.name,
                        fileSize: file.size,
                        fileType: file.type,
                        uploadDate: new Date().toISOString(),
                        file: file
                    };

                    // Simulate upload process
                    await new Promise(resolve => setTimeout(resolve, 2000));
                    
                    // Store document in appropriate locations based on classification
                    await storeDocument(documentData);
                    
                    closeModalFunc();
                    
                } catch (error) {
                    console.error('Upload error:', error);
                    const errorMessage = error.message || 'Error uploading document. Please try again.';
                    alert('✗ ' + errorMessage);
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            });

            async function storeDocument(documentData) {
                // Determine which API to use based on classification
                const category = documentData.category || 'Other Documents';
                const isMOU = category === 'MOU' || category.includes('MOU');
                const isMOA = category === 'MOA' || category.includes('MOA');
                
                // If it's MOU or MOA, we need to route to mou-moa.php API
                // But since mou-moa requires additional fields (institution, location, etc.),
                // we'll store it in other_documents but with the correct category
                const formData = new FormData();
                formData.append('file', documentData.file);
                formData.append('title', documentData.title);
                formData.append('description', documentData.description || '');
                // Use the detected classification instead of hardcoding
                formData.append('category', category === 'Other' ? 'Other Documents' : category);

                const response = await fetch('api/other-documents.php', {
                    method: 'POST',
                    body: formData
                });

                // Check response status first
                if (!response.ok) {
                    const text = await response.text();
                    console.error('HTTP Error:', response.status, text);
                    throw new Error(`Upload failed (${response.status}). Please check the console for details.`);
                }

                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Non-JSON response:', text.substring(0, 500)); // Limit output
                    throw new Error('Server returned an unexpected response. Please check the console for details.');
                }

                const result = await response.json();

                if (!result.success) {
                    throw new Error(result.error || 'Failed to upload document');
                }

                // Reload page to show new document
                location.reload();
            }

            function getRoutingInfo(category) {
                switch (category) {
                    case 'MOU':
                        return '• Documents page (general documents)\n• MOU & MOAs page (MOU section)';
                    case 'MOA':
                        return '• Documents page (general documents)\n• MOU & MOAs page (MOA section)';
                    case 'Templates':
                        return '• Documents page (general documents)\n• Templates page (templates section)';
                    case 'Other':
                    default:
                        return '• Documents page (general documents only)';
                }
            }

        });

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

<!-- Add Document Modal -->
<div id="addDocumentModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-card-dark rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-6 border-b border-border-light dark:border-border-dark">
            <h2 class="text-xl font-semibold text-text-light dark:text-text-dark">Add New Document</h2>
            <button id="closeModal" class="text-text-muted-light dark:text-text-muted-dark hover:text-text-light dark:hover:text-text-dark transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        
        <form id="addDocumentForm" class="p-6 space-y-6">
            <div>
                <label for="documentTitle" class="block text-sm font-medium text-text-light dark:text-text-dark mb-2">Document Title</label>
                <input type="text" id="documentTitle" name="title" required 
                       class="w-full px-3 py-2 border border-border-light dark:border-border-dark rounded-md bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent uppercase"
                       placeholder="Enter document title"
                       style="text-transform: uppercase;">
            </div>
            
            <div id="classificationResult" class="hidden">
                <div class="flex items-center gap-2 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-md border border-blue-200 dark:border-blue-800">
                    <span class="material-symbols-outlined text-blue-500">auto_awesome</span>
                    <div>
                        <p class="text-sm font-medium text-blue-700 dark:text-blue-300">Document Classification</p>
                        <p id="classificationText" class="text-xs text-blue-600 dark:text-blue-400"></p>
                    </div>
                </div>
            </div>
            
            <div>
                <label for="documentDescription" class="block text-sm font-medium text-text-light dark:text-text-dark mb-2">Description (Optional)</label>
                <textarea id="documentDescription" name="description" rows="3"
                          class="w-full px-3 py-2 border border-border-light dark:border-border-dark rounded-md bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent resize-none"
                          placeholder="Enter document description"></textarea>
            </div>
            
            <div>
                <label for="documentFile" class="block text-sm font-medium text-text-light dark:text-text-dark mb-2">Upload File</label>
                <div class="border-2 border-dashed border-border-light dark:border-border-dark rounded-lg p-6 text-center hover:border-primary transition-colors">
                    <input type="file" id="documentFile" name="file" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png" required
                           class="hidden">
                    <div id="fileDropZone" class="cursor-pointer">
                        <span class="material-symbols-outlined text-4xl text-text-muted-light dark:text-text-muted-dark mb-2 block">cloud_upload</span>
                        <p class="text-text-muted-light dark:text-text-muted-dark mb-2">
                            <span class="font-medium text-primary">Click to upload</span> or drag and drop
                        </p>
                        <p class="text-sm text-text-muted-light dark:text-text-muted-dark">
                            PDF, DOC, DOCX, TXT, JPG, PNG with OCR support (Max 10MB)
                        </p>
                    </div>
                    <div id="filePreview" class="hidden mt-3">
                        <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-md">
                            <span class="material-symbols-outlined text-green-500">description</span>
                            <div class="flex-1">
                                <p id="fileName" class="text-sm font-medium text-text-light dark:text-text-dark"></p>
                                <p id="fileSize" class="text-xs text-text-muted-light dark:text-text-muted-dark"></p>
                            </div>
                            <button type="button" id="removeFile" class="text-red-500 hover:text-red-700">
                                <span class="material-symbols-outlined">close</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 pt-4 border-t border-border-light dark:border-border-dark">
                <button type="button" id="cancelModal" 
                        class="px-4 py-2 text-sm font-medium text-text-muted-light dark:text-text-muted-dark hover:text-text-light dark:hover:text-text-dark transition-colors">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 text-sm font-medium bg-primary text-white rounded-md hover:bg-primary/90 transition-colors">
                    Upload Document
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Notification System -->
<script>
    // Notification System - Reusable for all pages
    (function() {
        const notificationBtn = document.getElementById('notificationBell') || document.getElementById('notificationBtn');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const notificationBadge = document.getElementById('notificationBadge');
        const notificationList = document.getElementById('notificationList');
        const noNotifications = document.getElementById('noNotifications');
        const markAllReadBtn = document.getElementById('markAllReadBtn') || document.getElementById('clearAllNotifications');
        
        if (!notificationBtn || !notificationDropdown) return; // Exit if elements don't exist
        
        let notifications = [];
        
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
                
                return `
                    <div class="p-4 border-b border-border-light dark:border-border-dark hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer ${notif.is_read ? 'opacity-60' : ''}" 
                         data-id="${notif.id}" 
                         onclick="markNotificationAsRead(${notif.id})">
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-10 h-10 rounded-full ${bgColor} flex items-center justify-center">
                                <span class="material-symbols-outlined text-white text-lg">${icon}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-text-light dark:text-text-dark">${escapeHtml(notif.title)}</p>
                                <p class="text-sm text-text-muted-light dark:text-text-muted-dark mt-1">${escapeHtml(notif.message)}</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">${timeAgo}</p>
                            </div>
                            ${!notif.is_read ? '<div class="flex-shrink-0 w-2 h-2 bg-primary rounded-full mt-2"></div>' : ''}
                        </div>
                    </div>
                `;
            }).join('');
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
                }
            } catch (error) {
                console.error('Error marking notification as read:', error);
            }
        };
        
        // Mark all as read / Clear all
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
        
        // Initialize: Check for notifications and load them
        document.addEventListener('DOMContentLoaded', () => {
            checkNotifications();
            updateNotificationBadge();
            
            // Refresh notifications every 5 minutes
            setInterval(() => {
                checkNotifications();
                updateNotificationBadge();
            }, 5 * 60 * 1000);
        });
    })();
</script>

</body></html>

