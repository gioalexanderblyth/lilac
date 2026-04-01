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

// Allow all authenticated users to access the documents page
// Removed admin-only restriction - all authenticated users have full access

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
        // Exclude deleted items (where deleted_at IS NULL)
        // First, ensure deleted_at columns exist
        try {
            $pdo->exec("ALTER TABLE mou_moa ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
        } catch (PDOException $e) {
            // Column might already exist, ignore
        }
        try {
            $pdo->exec("ALTER TABLE other_documents ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
        } catch (PDOException $e) {
            // Column might already exist, ignore
        }

        $fetchDocSegment = function (PDO $pdo, string $sql) {
            try {
                return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                error_log('Documents: segment skipped — ' . $e->getMessage());
                return [];
            }
        };

        $documents = array_merge(
            $fetchDocSegment($pdo, "
            SELECT
                m.id,
                m.user_id,
                m.institution as title,
                CONCAT('Institution: ', IFNULL(m.institution,''), ' | Contact: ', IFNULL(m.contact_email,'')) as description,
                m.file_name,
                m.file_path,
                COALESCE(m.type, 'MOU') as category,
                m.created_at,
                m.updated_at,
                u.username as uploaded_by,
                'mou_moa' as source_table
            FROM mou_moa m
            LEFT JOIN users u ON m.user_id = u.id
            WHERE m.deleted_at IS NULL
            "),
            $fetchDocSegment($pdo, "
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
            WHERE od.deleted_at IS NULL
            ")
        );
        
        // Deduplicate: If an item exists in both tables (based on title and recent creation), prioritize other_documents
        $uniqueDocs = [];
        $otherDocTitles = [];
        
        // First pass: Collect other_documents
        foreach ($documents as $doc) {
            if ($doc['source_table'] === 'other_documents') {
                // Create a key based on title
                $key = strtolower(trim($doc['title']));
                $otherDocTitles[$key] = $doc['created_at'];
                $uniqueDocs[] = $doc;
            }
        }
        
        // Second pass: Add mou_moa if not a duplicate
        foreach ($documents as $doc) {
            if ($doc['source_table'] === 'mou_moa') {
                $key = strtolower(trim($doc['title']));
                
                // Check if duplicate (same title and created within 60 seconds)
                $isDuplicate = false;
                if (isset($otherDocTitles[$key])) {
                    $t1 = strtotime($otherDocTitles[$key]);
                    $t2 = strtotime($doc['created_at']);
                    // If created within 1 minute of each other, assume it's a copy
                    if (abs($t1 - $t2) < 60) {
                        $isDuplicate = true;
                    }
                }
                
                if (!$isDuplicate) {
                    $uniqueDocs[] = $doc;
                }
            }
        }
        
        // Re-sort by date desc
        usort($uniqueDocs, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        $documents = $uniqueDocs;
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
<link rel="stylesheet" href="css/award-analyzer.css">
<script src="js/notifications.js"></script>
<script src="js/notification-sound.js"></script>
<script src="js/notification-bar.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
<script src="js/award-analyzer.js"></script>

<!-- Tailwind runtime config removed; using compiled CSS in assets/css/tailwind.css -->

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

        /* Clickable table rows */
        tbody tr {
            cursor: pointer;
        }

        tbody tr:hover {
            background-color: rgba(19, 127, 236, 0.05) !important;
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

        /* Ensure consistent sidebar icon styling */
        .sidebar-nav-link {
            border-radius: 0.5rem;
        }
        
        /* Hover effects for non-active links */
        .sidebar-nav-link:not(.bg-primary-50):hover {
            background-color: rgb(243 244 246); /* gray-100 */
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

        .page-animate,
        .page-animate-delay-1,
        .page-animate-delay-2,
        .header-animate,
        .content-animate {
            opacity: 1 !important;
            animation: none !important;
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
    <aside class="sidebar bg-card-light dark:bg-card-dark border-r border-border-light dark:border-border-dark flex flex-col fixed h-full z-40 transition-all duration-300">
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
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-600 dark:hover:text-purple-400 transition-colors duration-200 sidebar-nav-link" href="scheduler.php" title="Scheduler">
                <span class="material-symbols-outlined flex-shrink-0">calendar_today</span>
                <span class="sidebar-text whitespace-nowrap">Scheduler</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-600 dark:hover:text-purple-400 transition-colors duration-200 sidebar-nav-link" href="mou-moa.php" title="MOUs & MOAs">
                <span class="material-symbols-outlined flex-shrink-0">handshake</span>
                <span class="sidebar-text whitespace-nowrap">MOUs &amp; MOAs</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg bg-gradient-to-r from-purple-50 to-indigo-50 dark:from-purple-900/40 dark:to-indigo-900/40 text-purple-600 dark:text-purple-400 font-semibold sidebar-nav-link border border-purple-200 dark:border-purple-800 shadow-sm" href="documents.php" title="Documents">
                <span class="material-symbols-outlined filled flex-shrink-0">description</span>
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

<header class="sticky top-0 bg-background-light/80 dark:bg-background-dark/80 backdrop-blur-sm z-30 px-6 lg:px-8 py-4 border-b border-border-light dark:border-border-dark flex justify-between items-center h-20 overflow-visible header-animate">

<div class="flex items-center gap-3">
<div class="w-10 h-10 rounded-lg bg-gradient-to-br from-indigo-500 to-sky-500 flex items-center justify-center">
<span class="material-symbols-outlined text-white">description</span>
</div>
<div>
<h1 class="text-2xl font-bold text-text-light dark:text-text-dark">Documents</h1>
<p class="text-sm text-text-muted-light dark:text-text-muted-dark">Organize and manage supporting documents for awards and partnerships</p>
</div>
</div>
<div class="flex items-center gap-2">

<div class="relative z-[9999]">
<button id="notificationBell" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-white/10 text-text-muted-light dark:text-text-muted-dark transition-colors duration-200">
<span class="material-symbols-outlined">notifications</span>
<!-- Notification badge -->
<span id="notificationBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-sm rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
</button>

<!-- Notification dropdown -->
<div id="notificationDropdown" class="absolute right-0 top-full mt-2 w-96 bg-white dark:bg-background-dark rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 z-[9999] hidden flex flex-col max-h-96">
<div class="p-4 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
<div class="flex items-center justify-between">
<h3 class="text-lg font-semibold text-gray-900 dark:text-white">Notifications</h3>
<a href="#" id="markAllReadBtn" class="text-sm text-primary hover:text-primary/80 dark:text-primary-400 dark:hover:text-primary-300">Mark all read</a>
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
                    <!-- Filter & Sort buttons (mirroring MOU page styles, but wired into existing category filter) -->
                    <div class="flex items-center gap-2">
                        <div class="relative">
                            <button id="docFilterBtn" class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-text-light bg-card-light border border-border-light rounded-lg hover:bg-gray-50 dark:bg-background-dark/50 dark:text-text-muted-dark dark:border-border-dark dark:hover:bg-card-dark">
                                <span class="material-symbols-outlined text-base">filter_list</span>
                                <span id="docFilterText">Filter</span>
                            </button>
                            <!-- Filter Dropdown Menu -->
                            <div id="docFilterDropdown" class="absolute right-0 mt-2 w-80 bg-white dark:bg-background-dark rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-50 hidden max-h-96 overflow-y-auto">
                                <div class="py-2">
                                    <!-- Category Filter -->
                                    <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Filter by Category</div>
                                    <button class="doc-filter-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between" data-category="all">
                                        <span>All Documents</span>
                                        <span class="doc-filter-indicator hidden">✓</span>
                                    </button>
                                    <button class="doc-filter-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between" data-category="MOU">
                                        <span>MOU</span>
                                        <span class="doc-filter-indicator hidden">✓</span>
                                    </button>
                                    <button class="doc-filter-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between" data-category="MOA">
                                        <span>MOA</span>
                                        <span class="doc-filter-indicator hidden">✓</span>
                                    </button>
                                    <button class="doc-filter-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between" data-category="Other Documents">
                                        <span>Other Documents</span>
                                        <span class="doc-filter-indicator hidden">✓</span>
                                    </button>

                                    <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>

                                    <button id="docClearFilter" class="w-full text-left px-4 py-2 text-sm text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800">
                                        Clear All Filters
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Sort Button (UI only for now, future-ready) -->
                        <div class="relative">
                            <button id="docSortBtn" class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-text-light bg-card-light border border-border-light rounded-lg hover:bg-gray-50 dark:bg-background-dark/50 dark:text-text-muted-dark dark:border-border-dark dark:hover:bg-card-dark">
                                <span class="material-symbols-outlined text-base">swap_vert</span>
                                <span id="docSortText">Sort</span>
                            </button>
                            <!-- Sort Dropdown Menu -->
                            <div id="docSortDropdown" class="absolute right-0 mt-2 w-56 bg-white dark:bg-background-dark rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-50 hidden">
                                <div class="py-2">
                                    <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sort by</div>
                                    <button class="doc-sort-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between" data-sort="name" data-direction="asc">
                                        <span>Document Name (A-Z)</span>
                                        <span class="doc-sort-indicator hidden">✓</span>
                                    </button>
                                    <button class="doc-sort-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between" data-sort="name" data-direction="desc">
                                        <span>Document Name (Z-A)</span>
                                        <span class="doc-sort-indicator hidden">✓</span>
                                    </button>
                                    <button class="doc-sort-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between" data-sort="date" data-direction="desc">
                                        <span>Date Uploaded (Newest)</span>
                                        <span class="doc-sort-indicator hidden">✓</span>
                                    </button>
                                    <button class="doc-sort-option w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between" data-sort="date" data-direction="asc">
                                        <span>Date Uploaded (Oldest)</span>
                                        <span class="doc-sort-indicator hidden">✓</span>
                                    </button>

                                    <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>

                                    <button id="docClearSort" class="w-full text-left px-4 py-2 text-sm text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800">
                                        Clear Sort
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add Document button -->
                    <button class="px-4 py-2 text-sm font-medium rounded-md bg-primary text-white flex items-center gap-2" id="addDocumentBtn">
                        <span class="material-symbols-outlined text-sm">add</span>
                        Add Document
                    </button>
                </div>
            </div>
<div class="mt-2 bg-card-light dark:bg-card-dark p-4 rounded-lg shadow-sm border border-border-light dark:border-border-dark">
<!-- Bulk Actions Bar -->
<div id="bulk-actions-bar" class="hidden mb-3 pb-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
    <div class="flex items-center gap-4">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
            <span id="selected-count">0</span> selected
        </span>
    </div>
                <button id="bulk-delete-btn" class="px-4 py-1.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors duration-200 flex items-center gap-2">
                    <span class="material-symbols-outlined text-base">delete</span>
                    Move to Trash
                </button>
</div>

<!-- Select All Option -->
<div class="mb-2 flex items-center justify-end">
    <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 transition-colors">
        <input type="checkbox" id="select-all-visible-checkbox" class="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary">
        <span>Select All Visible</span>
    </label>
</div>

<div class="overflow-x-auto">
<table class="w-full min-w-[800px] text-sm text-left">
<thead class="text-sm text-text-muted-light dark:text-text-muted-dark uppercase border-b border-border-light dark:border-border-dark">
<tr>
<th class="py-3 px-4 font-medium text-left" scope="col" style="width: 40px;">
    <input type="checkbox" id="select-all-header" class="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary">
</th>
<th class="py-3 px-4 font-medium text-left" scope="col">Name/Title of the Document</th>
<th class="py-3 px-4 font-medium text-center" scope="col">Category</th>
<th class="py-3 px-4 font-medium text-center" scope="col">Date Uploaded</th>
<th class="py-3 px-4 font-medium text-center" scope="col">Actions</th>
</tr>

</thead>

<tbody id="documents-table-body">

<tr id="no-documents-row">
<td class="py-6 px-4 text-center text-text-muted-light dark:text-text-muted-dark" colspan="5">No documents yet</td>
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

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm hidden">
    <div class="w-full max-w-md bg-white dark:bg-card-dark rounded-xl shadow-2xl m-4">
        <!-- Modal Header -->
        <div class="p-6 border-b border-gray-200 dark:border-gray-800">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-red-100 dark:bg-red-900/20 rounded-full flex items-center justify-center">
                    <span class="material-symbols-outlined text-red-600 dark:text-red-400 text-xl">warning</span>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Move to Trash</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">This will move the document to trash</p>
                </div>
            </div>
        </div>
        
        <!-- Modal Body -->
        <div class="p-6">
            <p class="text-gray-700 dark:text-gray-300">
                Are you sure you want to move this document to trash? You can restore it later from the Trash page.
            </p>
        </div>
        
        <!-- Modal Footer -->
        <div class="p-6 bg-gray-50 dark:bg-gray-800/50 rounded-b-xl flex justify-end gap-3">
            <button id="cancelDelete" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-700">
                Cancel
            </button>
            <button id="confirmDelete" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                Move to Trash
            </button>
        </div>
    </div>
</div>

<!-- Bulk Delete Confirmation Modal -->
<div id="bulkDeleteModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 transform transition-all duration-300 scale-95 opacity-0" id="bulkDeleteModalContent">
        <div class="p-6">
            <!-- Modal Header -->
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 dark:bg-red-900/20 rounded-full">
                <span class="material-symbols-outlined text-red-600 dark:text-red-400 text-2xl">warning</span>
            </div>

            <!-- Modal Title -->
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white text-center mb-2">
                Move Selected to Trash
            </h3>

            <!-- Modal Message -->
            <p id="bulkDeleteMessage" class="text-sm text-gray-600 dark:text-gray-300 text-center mb-6">
                Are you sure you want to move the selected documents to trash? You can restore them later from the Trash page.
            </p>

            <!-- Modal Actions -->
            <div class="flex gap-3 justify-center">
                <button id="bulkDeleteCancelBtn"
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors duration-200">
                    Cancel
                </button>
                <button id="bulkDeleteConfirmBtn"
                    class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors duration-200">
                    Move to Trash
                </button>
            </div>
        </div>
    </div>
</div>

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

            // Bulk selection tracking (Moved to top scope)
            const selectedDocumentIds = new Set();
            let pendingBulkDeleteItems = [];

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
                    // Remove any existing document rows
                    const existingRows = documentsTableBody.querySelectorAll('tr:not(#no-documents-row)');
                    existingRows.forEach(row => row.remove());
                    
                    // Update empty state row with better styling
                    if (noDocumentsRow) {
                        noDocumentsRow.style.display = '';
                        noDocumentsRow.innerHTML = `
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <span class="material-symbols-outlined text-6xl text-gray-400 dark:text-gray-500 mb-4">description</span>
                                    <p class="text-lg font-medium text-text-muted-light dark:text-text-muted-dark mb-2">No documents found</p>
                                    <p class="text-sm text-text-muted-light dark:text-text-muted-dark">Click "Add Document" to upload your first document</p>
                                </div>
                            </td>
                        `;
                    }
                    return;
                }

                // Hide placeholder row
                if (noDocumentsRow) {
                    noDocumentsRow.style.display = 'none';
                }

                // Remove existing document rows first
                const existingRows = documentsTableBody.querySelectorAll('tr:not(#no-documents-row)');
                existingRows.forEach(row => row.remove());
                
                // Clear selections when re-rendering
                selectedDocumentIds.clear();
                updateBulkActionsBar();

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
                    row.className = 'border-b border-border-light dark:border-border-dark';
                    row.innerHTML = `
                        <td class="py-4 px-4">
                            <input type="checkbox" class="document-checkbox w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary" data-id="${id}" data-source="${sourceTable}">
                        </td>
                        <td class="py-4 px-4 font-medium text-text-light dark:text-text-dark">${name}</td>
                        <td class="py-4 px-4 text-text-muted-light dark:text-text-muted-dark text-center">${category}</td>
                        <td class="py-4 px-4 text-text-muted-light dark:text-text-muted-dark text-center">${uploaded}</td>
                        <td class="py-4 px-4">
                            <div class="flex items-center justify-center gap-2">
                                <button class="p-2 rounded-md hover:bg-gray-100 dark:hover:bg-slate-700" aria-label="View" data-action="view" data-id="${id}" data-source="${sourceTable}" data-file="${filePath}">
                                    <span class="material-icons text-base text-text-muted-light dark:text-text-muted-dark">visibility</span>
                                </button>
                                <button class="p-2 rounded-md hover:bg-gray-100 dark:hover:bg-slate-700" aria-label="Analysis" data-action="analyze" data-id="${id}" data-source="${sourceTable}" data-file="${filePath}" data-title="${name}" title="View/Run Analysis">
                                    <span class="material-icons text-base text-text-muted-light dark:text-text-muted-dark">psychology</span>
                                </button>
                                <button class="p-2 rounded-md hover:bg-gray-100 dark:hover:bg-slate-700" aria-label="Edit" data-action="edit" data-id="${id}" data-source="${sourceTable}">
                                    <span class="material-icons text-base text-text-muted-light dark:text-text-muted-dark">edit</span>
                                </button>
                                <button class="p-2 rounded-md hover:bg-gray-100 dark:hover:bg-slate-700 text-red-600 dark:text-red-400" aria-label="Move to Trash" data-action="delete" data-id="${id}" data-source="${sourceTable}" title="Move to Trash">
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

            // --- Documents Filter & Sort (UI aligned with MOU page) ---
            const docFilterBtn = document.getElementById('docFilterBtn');
            const docFilterDropdown = document.getElementById('docFilterDropdown');
            const docFilterOptions = document.querySelectorAll('.doc-filter-option');
            const docFilterText = document.getElementById('docFilterText');
            const docClearFilter = document.getElementById('docClearFilter');

            const docSortBtn = document.getElementById('docSortBtn');
            const docSortDropdown = document.getElementById('docSortDropdown');
            const docSortOptions = document.querySelectorAll('.doc-sort-option');
            const docSortText = document.getElementById('docSortText');
            const docClearSort = document.getElementById('docClearSort');

            let currentSort = null;
            let currentSortDirection = null;

            // Hook filter dropdown into existing category filter logic
            if (docFilterBtn && docFilterDropdown) {
                docFilterBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    docFilterDropdown.classList.toggle('hidden');
                });

                docFilterOptions.forEach(option => {
                    option.addEventListener('click', function() {
                        const category = this.getAttribute('data-category');

                        // Map UI category to existing filterByCategory categories
                        let mappedCategory = null;
                        if (category === 'MOU') mappedCategory = 'MOU';
                        else if (category === 'MOA') mappedCategory = 'MOA';
                        else if (category === 'Other Documents') mappedCategory = 'Other Documents';
                        else mappedCategory = null; // all

                        filterByCategory(mappedCategory);

                        // Update indicators
                        docFilterOptions.forEach(o => {
                            const ind = o.querySelector('.doc-filter-indicator');
                            if (ind) ind.classList.add('hidden');
                        });
                        const indicator = this.querySelector('.doc-filter-indicator');
                        if (indicator) indicator.classList.remove('hidden');

                        const label = this.querySelector('span')?.textContent || 'Filter';
                        docFilterText.textContent = label;

                        docFilterDropdown.classList.add('hidden');
                    });
                });

                if (docClearFilter) {
                    docClearFilter.addEventListener('click', function() {
                        filterByCategory(null);
                        docFilterOptions.forEach(o => {
                            const ind = o.querySelector('.doc-filter-indicator');
                            if (ind) ind.classList.add('hidden');
                        });
                        docFilterText.textContent = 'Filter';
                        docFilterDropdown.classList.add('hidden');
                    });
                }

                // Close when clicking outside
                document.addEventListener('click', function(e) {
                    if (docFilterDropdown && !docFilterDropdown.contains(e.target) && !docFilterBtn.contains(e.target)) {
                        docFilterDropdown.classList.add('hidden');
                    }
                });
            }

            // Simple client-side sort on already rendered rows (per page)
            function applyDocumentSort() {
                if (!currentSort || !documentsTableBody) return;

                const rows = Array.from(documentsTableBody.querySelectorAll('tr'))
                    .filter(row => row.id !== 'no-documents-row');

                const getData = (row) => {
                    const cells = row.querySelectorAll('td');
                    const name = cells[1]?.textContent.trim().toLowerCase() || '';
                    const category = cells[2]?.textContent.trim().toLowerCase() || '';
                    const dateText = cells[3]?.dataset.date || cells[3]?.textContent.trim() || '';
                    const date = dateText ? new Date(dateText) : new Date(0);
                    return { name, category, date };
                };

                rows.sort((a, b) => {
                    const da = getData(a);
                    const db = getData(b);
                    let cmp = 0;
                    if (currentSort === 'name') {
                        cmp = da.name.localeCompare(db.name);
                    } else if (currentSort === 'date') {
                        cmp = da.date - db.date;
                    }
                    return currentSortDirection === 'desc' ? -cmp : cmp;
                });

                rows.forEach(row => documentsTableBody.appendChild(row));
            }

            if (docSortBtn && docSortDropdown) {
                docSortBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    docSortDropdown.classList.toggle('hidden');
                });

                docSortOptions.forEach(option => {
                    option.addEventListener('click', function() {
                        currentSort = this.getAttribute('data-sort');
                        currentSortDirection = this.getAttribute('data-direction');

                        docSortOptions.forEach(o => {
                            const ind = o.querySelector('.doc-sort-indicator');
                            if (ind) ind.classList.add('hidden');
                        });
                        const indicator = this.querySelector('.doc-sort-indicator');
                        if (indicator) indicator.classList.remove('hidden');

                        const label = this.querySelector('span')?.textContent || 'Sort';
                        docSortText.textContent = label;

                        applyDocumentSort();
                        docSortDropdown.classList.add('hidden');
                    });
                });

                if (docClearSort) {
                    docClearSort.addEventListener('click', function() {
                        currentSort = null;
                        currentSortDirection = null;
                        docSortOptions.forEach(o => {
                            const ind = o.querySelector('.doc-sort-indicator');
                            if (ind) ind.classList.add('hidden');
                        });
                        docSortText.textContent = 'Sort';
                        docSortDropdown.classList.add('hidden');
                        // Re-render current page without sorting
                        applyFiltersAndRender();
                    });
                }

                document.addEventListener('click', function(e) {
                    if (docSortDropdown && !docSortDropdown.contains(e.target) && !docSortBtn.contains(e.target)) {
                        docSortDropdown.classList.add('hidden');
                    }
                });
            }

            // Setup bulk delete functionality
            setupCheckboxListeners();
            setupSelectAllVisibleListener();
            setupSelectAllHeaderListener();
            setupBulkDeleteButton();
            setupBulkDeleteModalListeners();

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

            // File Viewer Modal Functionality
            const fileViewerModal = document.getElementById('fileViewerModal');
            const closeFileViewer = document.getElementById('closeFileViewer');
            const closeFileViewerBtn = document.getElementById('closeFileViewerBtn');
            const fileViewerTitle = document.getElementById('fileViewerTitle');
            const fileViewerSubtitle = document.getElementById('fileViewerSubtitle');
            const fileViewerContent = document.getElementById('fileViewerContent');
            const downloadFile = document.getElementById('downloadFile');
            
            let currentFileData = null;
            
            // Function to show file viewer modal
            function showFileViewer(filePath, fileName) {
                currentFileData = { path: filePath, name: fileName };
                
                // Update modal title and subtitle
                fileViewerTitle.textContent = fileName || 'View File';
                fileViewerSubtitle.textContent = 'Document preview';
                
                // Clear previous content
                fileViewerContent.innerHTML = '';
                
                // Show loading state
                fileViewerContent.innerHTML = `
                    <div class="text-center">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
                        <p class="text-gray-500 dark:text-gray-400">Loading file...</p>
                    </div>
                `;
                
                // Show modal
                fileViewerModal.classList.remove('hidden');
                
                // Load file content
                setTimeout(() => {
                    loadFileContent(filePath, fileName);
                }, 100);
            }
            
            // Function to load file content
            function loadFileContent(filePath, fileName) {
                const fileExtension = fileName ? fileName.split('.').pop().toLowerCase() : '';
                
                // Handle different file types
                if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'].includes(fileExtension)) {
                    // Show image preview
                    fileViewerContent.innerHTML = `
                        <div class="w-full h-full flex items-center justify-center p-4">
                            <img src="${filePath}" alt="${fileName}" class="max-w-full max-h-[70vh] object-contain rounded-lg shadow-lg" 
                                 onerror="this.parentElement.innerHTML='<div class=\\'text-center\\'><span class=\\'material-symbols-outlined text-6xl text-gray-400 mb-4\\'>error</span><p class=\\'text-gray-500\\'>Failed to load image</p></div>'">
                        </div>
                    `;
                } else if (fileExtension === 'pdf') {
                    // Show PDF in iframe
                    fileViewerContent.innerHTML = `
                        <div class="w-full h-full">
                            <iframe src="${filePath}" class="w-full h-full min-h-[600px] rounded-lg" frameborder="0"></iframe>
                        </div>
                    `;
                } else if (['mp3', 'wav', 'ogg', 'webm', 'm4a', 'aac', 'flac'].includes(fileExtension)) {
                    // Show audio player
                    fileViewerContent.innerHTML = `
                        <div class="w-full h-full flex flex-col items-center justify-center p-8">
                            <div class="text-center max-w-md w-full">
                                <div class="w-24 h-24 mx-auto mb-6 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                                    <span class="material-symbols-outlined text-5xl text-blue-600 dark:text-blue-400">music_note</span>
                                </div>
                                <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-2">${fileName || 'Audio File'}</h4>
                                <div class="mt-6 bg-white dark:bg-gray-800 rounded-lg p-4 shadow-lg">
                                    <audio controls class="w-full" preload="metadata">
                                        <source src="${filePath}" type="audio/${fileExtension === 'mp3' ? 'mpeg' : fileExtension}">
                                        Your browser does not support the audio element.
                                    </audio>
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-4">Click play to listen to the audio file</p>
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
                                <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-2">${fileName || 'Document'}</h4>
                                <p class="text-gray-500 dark:text-gray-400 mb-4">Preview not available for this file type. You can download the file using the button below.</p>
                            </div>
                        </div>
                    `;
                }
            }
            
            // Function to download current file
            function downloadCurrentFile() {
                if (currentFileData && currentFileData.path) {
                    const link = document.createElement('a');
                    link.href = currentFileData.path;
                    link.download = currentFileData.name || 'document';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
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
            
            if (closeFileViewerBtn) {
                closeFileViewerBtn.addEventListener('click', closeFileViewerModal);
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

            // Function to edit other documents
            async function editOtherDocument(id, docData) {
                try {
                    // Fetch document details from API
                    const response = await fetch(`api/other-documents.php?id=${id}`, {
                        headers: {
                            'Authorization': 'Bearer ' + (typeof AUTH_TOKEN !== 'undefined' ? AUTH_TOKEN : '')
                        }
                    });
                    
                    const result = await response.json();
                    const document = result.document || docData;
                    
                    if (!document) {
                        alert('Document not found');
                        return;
                    }
                    
                    // Pre-fill the add document modal with existing data
                    const modal = document.getElementById('addDocumentModal');
                    const form = document.getElementById('addDocumentForm');
                    const titleField = document.getElementById('documentTitle');
                    const descField = document.getElementById('documentDescription');
                    
                    if (titleField) titleField.value = document.title || '';
                    if (descField) descField.value = document.description || '';
                    
                    // Set editing mode
                    window.editingDocumentId = id;
                    window.editingDocumentSource = 'other_documents';
                    
                    // Update modal title
                    const modalTitle = modal?.querySelector('h2');
                    if (modalTitle) modalTitle.textContent = 'Edit Document';
                    
                    // Update submit button
                    const submitBtn = form?.querySelector('button[type="submit"]');
                    if (submitBtn) submitBtn.textContent = 'Update Document';
                    
                    // Show modal
                    if (modal) {
                        modal.classList.remove('hidden');
                        document.body.style.overflow = 'hidden';
                    }
                } catch (error) {
                    console.error('Error loading document for edit:', error);
                    alert('Failed to load document for editing');
                }
            }

            // Bulk selection tracking - Removed from here as it was moved to top scope
            
            // Update bulk actions bar
            function updateBulkActionsBar() {
                const bulkActionsBar = document.getElementById('bulk-actions-bar');
                const selectedCount = document.getElementById('selected-count');
                const totalSelected = selectedDocumentIds.size;
                
                if (totalSelected > 0) {
                    bulkActionsBar.classList.remove('hidden');
                    selectedCount.textContent = totalSelected;
                } else {
                    bulkActionsBar.classList.add('hidden');
                }
                
                // Update select all visible checkbox state
                const selectAllVisibleCheckbox = document.getElementById('select-all-visible-checkbox');
                const selectAllHeaderCheckbox = document.getElementById('select-all-header');
                const visibleCheckboxes = document.querySelectorAll('.document-checkbox');
                const visibleChecked = Array.from(visibleCheckboxes).filter(cb => cb.checked).length;
                
                if (selectAllVisibleCheckbox && visibleCheckboxes.length > 0) {
                    selectAllVisibleCheckbox.checked = visibleChecked === visibleCheckboxes.length;
                    selectAllVisibleCheckbox.indeterminate = visibleChecked > 0 && visibleChecked < visibleCheckboxes.length;
                }
                
                if (selectAllHeaderCheckbox && visibleCheckboxes.length > 0) {
                    selectAllHeaderCheckbox.checked = visibleChecked === visibleCheckboxes.length;
                    selectAllHeaderCheckbox.indeterminate = visibleChecked > 0 && visibleChecked < visibleCheckboxes.length;
                }
            }

            // Handle select all visible checkbox
            function setupSelectAllVisibleListener() {
                const selectAllVisibleCheckbox = document.getElementById('select-all-visible-checkbox');
                if (selectAllVisibleCheckbox) {
                    selectAllVisibleCheckbox.addEventListener('change', function() {
                        const visibleCheckboxes = document.querySelectorAll('.document-checkbox');
                        visibleCheckboxes.forEach(checkbox => {
                            checkbox.checked = selectAllVisibleCheckbox.checked;
                            const id = checkbox.dataset.id;
                            const source = checkbox.dataset.source;
                            if (selectAllVisibleCheckbox.checked) {
                                selectedDocumentIds.add(`${id}_${source}`);
                            } else {
                                selectedDocumentIds.delete(`${id}_${source}`);
                            }
                        });
                        updateBulkActionsBar();
                    });
                }
            }

            // Handle select all header checkbox
            function setupSelectAllHeaderListener() {
                const selectAllHeaderCheckbox = document.getElementById('select-all-header');
                if (selectAllHeaderCheckbox) {
                    selectAllHeaderCheckbox.addEventListener('change', function() {
                        const visibleCheckboxes = document.querySelectorAll('.document-checkbox');
                        visibleCheckboxes.forEach(checkbox => {
                            checkbox.checked = selectAllHeaderCheckbox.checked;
                            const id = checkbox.dataset.id;
                            const source = checkbox.dataset.source;
                            if (selectAllHeaderCheckbox.checked) {
                                selectedDocumentIds.add(`${id}_${source}`);
                            } else {
                                selectedDocumentIds.delete(`${id}_${source}`);
                            }
                        });
                        updateBulkActionsBar();
                    });
                }
            }

            // Handle individual checkbox changes
            function setupCheckboxListeners() {
                if (documentsTableBody) {
                    documentsTableBody.addEventListener('change', function(e) {
                        if (e.target.classList.contains('document-checkbox')) {
                            const checkbox = e.target;
                            const id = checkbox.dataset.id;
                            const source = checkbox.dataset.source;
                            const key = `${id}_${source}`;
                            
                            if (checkbox.checked) {
                                selectedDocumentIds.add(key);
                            } else {
                                selectedDocumentIds.delete(key);
                            }
                            updateBulkActionsBar();
                        }
                    });
                }
            }

            // Show bulk delete confirmation modal
            function showBulkDeleteModal(items) {
                pendingBulkDeleteItems = items;
                const count = items.length;
                const modal = document.getElementById('bulkDeleteModal');
                const modalContent = document.getElementById('bulkDeleteModalContent');
                const messageElement = document.getElementById('bulkDeleteMessage');

                messageElement.textContent = `Are you sure you want to move ${count} selected document${count > 1 ? 's' : ''} to trash? You can restore them later from the Trash page.`;

                modal.classList.remove('hidden');

                setTimeout(() => {
                    modalContent.classList.remove('scale-95', 'opacity-0');
                    modalContent.classList.add('scale-100', 'opacity-100');
                }, 10);
            }

            // Hide bulk delete modal
            function hideBulkDeleteModal() {
                const modal = document.getElementById('bulkDeleteModal');
                const modalContent = document.getElementById('bulkDeleteModalContent');

                modalContent.classList.remove('scale-100', 'opacity-100');
                modalContent.classList.add('scale-95', 'opacity-0');

                setTimeout(() => {
                    modal.classList.add('hidden');
                    pendingBulkDeleteItems = [];
                }, 300);
            }

            // Bulk delete function
            async function bulkDeleteDocuments() {
                if (selectedDocumentIds.size === 0) {
                    alert('Please select at least one document to move to trash');
                    return;
                }

                // Prepare items for deletion (source may be "other_documents" or "mou_moa" — split on first "_" only)
                const items = Array.from(selectedDocumentIds).map(key => {
                    const u = key.indexOf('_');
                    const id = u === -1 ? key : key.slice(0, u);
                    const source = u === -1 ? 'other_documents' : key.slice(u + 1);
                    return { id, source };
                });
                
                showBulkDeleteModal(items);
            }

            // Confirm bulk delete
            async function confirmBulkDelete() {
                if (pendingBulkDeleteItems.length === 0) {
                    hideBulkDeleteModal();
                    return;
                }

                const items = [...pendingBulkDeleteItems];
                const count = items.length;

                try {
                    // Delete items one by one (since they might be from different tables)
                    let successCount = 0;
                    let failCount = 0;

                    for (const item of items) {
                        try {
                            let apiUrl = '';
                            if (item.source === 'mou_moa') {
                                apiUrl = `api/mou-moa.php?id=${encodeURIComponent(item.id)}`;
                            } else if (item.source === 'other_documents') {
                                apiUrl = `api/other-documents.php?id=${encodeURIComponent(item.id)}`;
                            } else {
                                apiUrl = `api/other-documents.php?id=${encodeURIComponent(item.id)}`;
                            }

                            const response = await fetch(apiUrl, { method: 'DELETE' });
                            const result = await response.json();

                            if (result.success) {
                                successCount++;
                            } else {
                                failCount++;
                            }
                        } catch (error) {
                            console.error('Error deleting document:', error);
                            failCount++;
                        }
                    }

                    if (successCount > 0) {
                        alert(`Successfully moved ${successCount} document${successCount > 1 ? 's' : ''} to trash${failCount > 0 ? `. ${failCount} failed.` : ''}`);
                        location.reload(); // Reload page to refresh document list
                    } else {
                        alert('Failed to move documents to trash. Please try again.');
                    }
                } catch (error) {
                    console.error('Bulk delete error:', error);
                    alert('Error deleting documents: ' + error.message);
                } finally {
                    hideBulkDeleteModal();
                }
            }

            // Setup bulk delete button
            function setupBulkDeleteButton() {
                const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
                if (bulkDeleteBtn) {
                    bulkDeleteBtn.addEventListener('click', bulkDeleteDocuments);
                }
            }

            // Setup bulk delete modal event listeners
            function setupBulkDeleteModalListeners() {
                document.getElementById('bulkDeleteCancelBtn')?.addEventListener('click', hideBulkDeleteModal);
                document.getElementById('bulkDeleteConfirmBtn')?.addEventListener('click', confirmBulkDelete);

                const bulkDeleteModal = document.getElementById('bulkDeleteModal');
                if (bulkDeleteModal) {
                    bulkDeleteModal.addEventListener('click', function(e) {
                        if (e.target === bulkDeleteModal) {
                            hideBulkDeleteModal();
                        }
                    });
                }

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && !document.getElementById('bulkDeleteModal').classList.contains('hidden')) {
                        hideBulkDeleteModal();
                    }
                });
            }

            // Delete Modal Logic
            const deleteModal = document.getElementById('deleteConfirmModal');
            const cancelDeleteBtn = document.getElementById('cancelDelete');
            const confirmDeleteBtn = document.getElementById('confirmDelete');
            let pendingDelete = null;

            function showDeleteModal(id, source) {
                pendingDelete = { id, source };
                if (deleteModal) deleteModal.classList.remove('hidden');
            }

            function hideDeleteModal() {
                pendingDelete = null;
                if (deleteModal) deleteModal.classList.add('hidden');
            }

            if (cancelDeleteBtn) {
                cancelDeleteBtn.addEventListener('click', hideDeleteModal);
            }
            
            if (deleteModal) {
                // Close on backdrop click
                deleteModal.addEventListener('click', (e) => {
                    if (e.target === deleteModal) hideDeleteModal();
                });
            }

            if (confirmDeleteBtn) {
                confirmDeleteBtn.addEventListener('click', async () => {
                    if (!pendingDelete) return;
                    const { id, source } = pendingDelete;
                    
                    // Show loading state
                    const originalText = confirmDeleteBtn.textContent;
                    confirmDeleteBtn.textContent = 'Deleting...';
                    confirmDeleteBtn.disabled = true;

                    try {
                        let apiUrl = '';
                        if (source === 'mou_moa') {
                            apiUrl = `api/mou-moa.php?id=${encodeURIComponent(id)}`;
                        } else if (source === 'other_documents') {
                            apiUrl = `api/other-documents.php?id=${encodeURIComponent(id)}`;
                        } else {
                            // Default to other_documents if unknown
                            apiUrl = `api/other-documents.php?id=${encodeURIComponent(id)}`;
                        }

                        const response = await fetch(apiUrl, { method: 'DELETE' });
                        const result = await response.json();

                        if (result.success) {
                            // alert('Document moved to trash successfully');
                            location.reload(); // Reload page to refresh document list
                        } else {
                            alert('Failed to move to trash: ' + (result.error || 'Unknown error'));
                        }
                    } catch (error) {
                        alert('Error deleting document: ' + error.message);
                    } finally {
                        hideDeleteModal();
                        confirmDeleteBtn.textContent = originalText;
                        confirmDeleteBtn.disabled = false;
                    }
                });
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
                    
                    // Find the document data from the row
                    const row = target.closest('tr');
                    let doc = null;
                    if (row) {
                        const docName = row.querySelector('td:first-child')?.textContent?.trim();
                        doc = allDocuments.find(d => (d.title || d.name) === docName);
                    }

                    if (action === 'view') {
                        // Show file viewer modal
                        if (filePath) {
                            const fileName = (doc && doc.file_name) ? doc.file_name : filePath.split('/').pop();
                            showFileViewer(filePath, fileName);
                        } else {
                            alert('File path not found');
                        }
                    } else if (action === 'analyze') {
                        // Show/run analysis for document
                        const docTitle = target.getAttribute('data-title') || (doc && doc.title) || (doc && doc.name) || 'Untitled Document';
                        await showDocumentAnalysis(id, filePath, docTitle, source);
                    } else if (action === 'edit') {
                        // Handle edit based on source
                        if (source === 'mou_moa') {
                            // For MOU/MOA, redirect to mou-moa page and trigger edit
                            // Store the ID in sessionStorage so mou-moa.php can pick it up
                            sessionStorage.setItem('editMouId', id);
                            window.location.href = 'mou-moa.php';
                        } else if (source === 'other_documents') {
                            // Edit other documents
                            editOtherDocument(id, doc);
                        }
                    } else if (action === 'delete') {
                        showDeleteModal(id, source);
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
                
                // Reset edit mode
                window.editingDocumentId = null;
                window.editingDocumentSource = null;
                
                // Reset modal title and button text
                const modalTitle = addDocumentModal.querySelector('h2');
                if (modalTitle) modalTitle.textContent = 'Add New Document';
                const submitBtn = addDocumentForm.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.textContent = 'Upload Document';
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
                    classificationText.textContent = 'Unable to analyze document. Will be classified as "Other Documents".';
                    documentFile.dataset.classification = 'Other Documents';
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
                
                // MOU keywords - more specific, removing generic words
                // Only match full phrases or very specific terms
                const mouKeywords = [
                    'memorandum of understanding', // Full phrase - high priority
                    'mou', // Only if part of specific context
                    'inter-institutional memorandum of understanding',
                    'memorandum of understanding between',
                    'mutual understanding memorandum'
                ];
                
                // MOA keywords - more specific, removing generic words like standalone "agreement"
                // Only match full phrases or very specific terms
                const moaKeywords = [
                    'memorandum of agreement', // Full phrase - high priority
                    'moa', // Only if part of specific context
                    'memorandum of agreement between',
                    'memorandum of agreement with'
                ];
                
                // Other Documents keywords
                const otherDocsKeywords = [
                    'template', 'form', 'sample', 'format', 'draft', 'example',
                    'boilerplate', 'standard form', 'application form', 'report form'
                ];

                let mouScore = 0;
                let moaScore = 0;
                let otherDocsScore = 0;
                let mouMatches = 0; // Count of specific matches
                let moaMatches = 0; // Count of specific matches
                
                // Calculate scores for each category - prioritize longer, more specific phrases
                mouKeywords.forEach(keyword => {
                    if (textToAnalyze.includes(keyword)) {
                        // Give much higher weight to full phrase matches
                        if (keyword.includes('memorandum of understanding')) {
                            mouScore += keyword.length * 5; // 5x weight for full phrase
                            mouMatches++;
                        } else if (keyword === 'mou') {
                            // Only count "mou" if it appears near "memorandum" or in specific contexts
                            const mouContext = textToAnalyze.includes('memorandum') || 
                                             textToAnalyze.includes('understanding') ||
                                             filename.toLowerCase().includes('mou');
                            if (mouContext) {
                                mouScore += keyword.length * 2;
                                mouMatches++;
                            }
                        } else {
                            mouScore += keyword.length * 3;
                            mouMatches++;
                        }
                    }
                });
                
                moaKeywords.forEach(keyword => {
                    if (textToAnalyze.includes(keyword)) {
                        // Give much higher weight to full phrase matches
                        if (keyword.includes('memorandum of agreement')) {
                            moaScore += keyword.length * 5; // 5x weight for full phrase
                            moaMatches++;
                        } else if (keyword === 'moa') {
                            // Only count "moa" if it appears near "memorandum" or in specific contexts
                            const moaContext = textToAnalyze.includes('memorandum') || 
                                             textToAnalyze.includes('agreement') ||
                                             filename.toLowerCase().includes('moa');
                            if (moaContext) {
                                moaScore += keyword.length * 2;
                                moaMatches++;
                            }
                        } else {
                            moaScore += keyword.length * 3;
                            moaMatches++;
                        }
                    }
                });

                otherDocsKeywords.forEach(keyword => {
                    if (textToAnalyze.includes(keyword)) {
                        otherDocsScore += keyword.length;
                    }
                });

                // Determine classification - require strong evidence for MOU/MOA
                // Default to "Other Documents" to prevent false positives
                let category = 'Other Documents';
                let confidence = 0;

                // Require strong evidence for MOU/MOA classification to prevent false positives
                // This prevents generic words like "agreement" from triggering MOA classification
                const minMouMatches = 1; // At least one specific match
                const minMoaMatches = 1; // At least one specific match
                const minConfidenceThreshold = 70; // Require at least 70% confidence

                if (mouMatches >= minMouMatches && mouScore > moaScore && mouScore > otherDocsScore && mouScore > 0) {
                    const calculatedConfidence = (mouScore / (mouScore + moaScore + otherDocsScore + 1)) * 100;
                    if (calculatedConfidence >= minConfidenceThreshold) {
                        category = 'MOU';
                        confidence = Math.min(95, Math.max(minConfidenceThreshold, calculatedConfidence));
                    } else {
                        // Low confidence - default to Other Documents
                        category = 'Other Documents';
                        confidence = Math.round(calculatedConfidence);
                    }
                } else if (moaMatches >= minMoaMatches && moaScore > mouScore && moaScore > otherDocsScore && moaScore > 0) {
                    const calculatedConfidence = (moaScore / (mouScore + moaScore + otherDocsScore + 1)) * 100;
                    if (calculatedConfidence >= minConfidenceThreshold) {
                        category = 'MOA';
                        confidence = Math.min(95, Math.max(minConfidenceThreshold, calculatedConfidence));
                    } else {
                        // Low confidence - default to Other Documents
                        category = 'Other Documents';
                        confidence = Math.round(calculatedConfidence);
                    }
                } else if (otherDocsScore > mouScore && otherDocsScore > moaScore && otherDocsScore > 0) {
                    category = 'Other Documents';
                    confidence = Math.min(95, Math.max(60, (otherDocsScore / (mouScore + moaScore + otherDocsScore + 1)) * 100));
                } else {
                    // Default to Other Documents when no clear classification
                    category = 'Other Documents';
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
                
                // Check if we're in edit mode
                const isEditMode = window.editingDocumentId && window.editingDocumentSource;
                
                // Show loading state
                submitBtn.disabled = true;
                submitBtn.textContent = isEditMode ? 'Updating...' : 'Uploading...';
                
                try {
                    const formData = new FormData(addDocumentForm);
                    const file = documentFile.files[0];
                    const classification = documentFile.dataset.classification || 'Other';
                    const confidence = documentFile.dataset.confidence || '0';
                    
                    if (isEditMode) {
                        // Update existing document
                        const updateData = {
                            title: formData.get('title').toUpperCase(),
                            description: formData.get('description'),
                            category: classification === 'Other' ? 'Other Documents' : classification
                        };
                        
                        // If a new file is selected, include it
                        if (file) {
                            const updateFormData = new FormData();
                            updateFormData.append('file', file);
                            updateFormData.append('title', updateData.title);
                            updateFormData.append('description', updateData.description);
                            updateFormData.append('category', updateData.category);
                            
                            const response = await fetch(`api/other-documents.php?id=${window.editingDocumentId}`, {
                                method: 'PUT',
                                body: updateFormData
                            });
                            
                            const result = await response.json();
                            
                            if (result.success) {
                                alert('✓ Document updated successfully!');
                                closeModalFunc();
                                // Reset edit mode
                                window.editingDocumentId = null;
                                window.editingDocumentSource = null;
                                // Reload documents
                                location.reload();
                            } else {
                                throw new Error(result.error || 'Update failed');
                            }
                        } else {
                            // Update without file change
                            const response = await fetch(`api/other-documents.php?id=${window.editingDocumentId}`, {
                                method: 'PUT',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify(updateData)
                            });
                            
                            const result = await response.json();
                            
                            if (result.success) {
                                alert('✓ Document updated successfully!');
                                closeModalFunc();
                                // Reset edit mode
                                window.editingDocumentId = null;
                                window.editingDocumentSource = null;
                                // Reload documents
                                location.reload();
                            } else {
                                throw new Error(result.error || 'Update failed');
                            }
                        }
                    } else {
                        // Create new document
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
                        
                        // Note: closeModalFunc() is called inside storeDocument after analysis starts
                    }
                    
                } catch (error) {
                    console.error('Upload/Update error:', error);
                    const errorMessage = error.message || 'Error processing document. Please try again.';
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
                // Use the detected classification, defaulting to Other Documents
                formData.append('category', (category === 'Other' || !category) ? 'Other Documents' : category);
                
                // Set source_page for all document uploads
                formData.append('source_page', 'documents');

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

                // Automatically analyze ALL uploaded documents
                if (result.data) {
                    const uploadedDoc = result.data;
                    // Explicitly set source_table for newly uploaded documents so delete/edit works immediately
                    uploadedDoc.source_table = 'other_documents';
                    
                    // UPDATE UI: Add to list and re-render without reload
                    try {
                        // Check if we have the documents list available
                        if (typeof allDocuments !== 'undefined') {
                            const idx = allDocuments.findIndex(d => d.id == uploadedDoc.id);
                            if (idx !== -1) {
                                 allDocuments[idx] = uploadedDoc;
                            } else {
                                 allDocuments.unshift(uploadedDoc);
                            }
                            
                            // Update counters
                            if (typeof updateTopCounters === 'function') updateTopCounters(allDocuments);
                            
                            // Render (this uses currentFilter automatically inside renderDocuments if passed? No, we pass it)
                            if (typeof renderDocuments === 'function') {
                                 renderDocuments(allDocuments, currentFilter);
                                 // Apply pagination to ensure only first page is shown
                                 if (typeof applyPagination === 'function') applyPagination();
                            }
                        }
                    } catch (e) {
                        console.error('Error updating UI:', e);
                        // Fallback to reload if UI update fails
                        location.reload();
                        return;
                    }

                    // Close modal first
                    closeModalFunc();
                    
                    // Automatic analysis disabled - users can manually analyze documents if needed
                    // await analyzeDocumentFromLibrary(uploadedDoc.id, uploadedDoc.file_path, uploadedDoc.title);
                } else {
                    // Reload page to show new document
                    location.reload();
                }
            }

            // Show/Run analysis for a document (called from Actions button)
            async function showDocumentAnalysis(docId, filePath, docTitle = '', source = 'other_documents') {
                if (!window.awardAnalyzer) {
                    console.error('Award analyzer not loaded');
                    alert('Analysis feature is not available. Please refresh the page.');
                    return;
                }
                
                // Show loading notification
                const loadingNotification = document.createElement('div');
                loadingNotification.className = 'fixed top-4 right-4 bg-blue-600 text-white px-6 py-3 rounded-lg shadow-lg z-50 flex items-center gap-2';
                loadingNotification.innerHTML = `
                    <span class="material-symbols-outlined animate-spin">hourglass_empty</span>
                    <span>Loading analysis...</span>
                `;
                document.body.appendChild(loadingNotification);
                
                try {
                    // Ensure file path is in correct format for API
                    let fullFilePath = filePath;
                    if (!filePath.startsWith('uploads/') && !filePath.includes(':\\') && !filePath.startsWith('/')) {
                        // If it's just a filename, prepend the uploads directory
                        if (source === 'mou_moa') {
                            fullFilePath = `uploads/mou/${filePath}`;
                        } else {
                            fullFilePath = `uploads/other_documents/${filePath}`;
                        }
                    }
                    
                    // Run analysis using award analyzer
                    await window.awardAnalyzer.analyzeFile(null, {
                        filePath: fullFilePath,
                        document_id: docId,
                        award_name: docTitle,
                        source_page: 'documents',
                        targetContainer: document.querySelector('.content-animate') || document.body
                    });
                    
                    // Update notification to success
                    loadingNotification.className = 'fixed top-4 right-4 bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg z-50 flex items-center gap-2';
                    loadingNotification.innerHTML = `
                        <span class="material-symbols-outlined">check_circle</span>
                        <span>Analysis complete! Scroll down to view results.</span>
                    `;
                    
                    // Scroll to analysis results
                    setTimeout(() => {
                        const analysisContainer = document.querySelector('.award-analysis-results') || document.querySelector('[id*="analysis"]');
                        if (analysisContainer) {
                            analysisContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    }, 500);
                    
                    // Remove notification after 3 seconds
                    setTimeout(() => {
                        loadingNotification.remove();
                    }, 3000);
                } catch (error) {
                    console.error('Analysis error:', error);
                    loadingNotification.className = 'fixed top-4 right-4 bg-red-600 text-white px-6 py-3 rounded-lg shadow-lg z-50 flex items-center gap-2';
                    loadingNotification.innerHTML = `
                        <span class="material-symbols-outlined">error</span>
                        <span>Analysis failed: ${error.message}</span>
                    `;
                    setTimeout(() => {
                        loadingNotification.remove();
                    }, 5000);
                }
            }

            // Analyze document from library (automatically called)
            async function analyzeDocumentFromLibrary(docId, filePath, docTitle = '') {
                if (!window.awardAnalyzer) {
                    console.error('Award analyzer not loaded');
                    return;
                }

                // Show loading notification
                const loadingNotification = document.createElement('div');
                loadingNotification.className = 'fixed top-4 right-4 bg-primary text-white px-6 py-3 rounded-lg shadow-lg z-50 flex items-center gap-2';
                loadingNotification.innerHTML = `
                    <span class="material-symbols-outlined animate-spin">hourglass_empty</span>
                    <span>Analyzing document for awards...</span>
                `;
                document.body.appendChild(loadingNotification);

                try {
                    // Ensure file path is in correct format for API
                    // API expects path relative to project root (e.g., "uploads/other_documents/file.docx")
                    let fullFilePath = filePath;
                    if (!filePath.startsWith('uploads/') && !filePath.includes(':\\') && !filePath.startsWith('/')) {
                        // If it's just a filename, prepend the uploads directory
                        fullFilePath = `uploads/other_documents/${filePath}`;
                    }
                    
                    await window.awardAnalyzer.analyzeFile(null, {
                        filePath: fullFilePath,
                        document_id: docId,
                        award_name: docTitle, // Pass the document title as the award name
                        source_page: 'documents',
                        targetContainer: document.querySelector('.content-animate') || document.body
                    });
                    
                    // Update notification to success
                    loadingNotification.className = 'fixed top-4 right-4 bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg z-50 flex items-center gap-2';
                    loadingNotification.innerHTML = `
                        <span class="material-symbols-outlined">check_circle</span>
                        <span>Analysis complete! Scroll down to view results.</span>
                    `;
                    
                    // Remove notification after 3 seconds
                    setTimeout(() => {
                        loadingNotification.remove();
                    }, 3000);
                } catch (error) {
                    console.error('Analysis error:', error);
                    loadingNotification.className = 'fixed top-4 right-4 bg-red-600 text-white px-6 py-3 rounded-lg shadow-lg z-50 flex items-center gap-2';
                    loadingNotification.innerHTML = `
                        <span class="material-symbols-outlined">error</span>
                        <span>Analysis failed: ${error.message}</span>
                    `;
                    setTimeout(() => {
                        loadingNotification.remove();
                    }, 5000);
                }
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

<!-- File Viewer Modal -->
<div id="fileViewerModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm hidden">
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
        <div class="p-6 flex-1 overflow-hidden">
            <div class="w-full h-full flex items-center justify-center bg-gray-50 dark:bg-gray-900 rounded-lg">
                <div id="fileViewerContent" class="w-full h-full flex items-center justify-center">
                    <!-- File content will be displayed here -->
                    <div class="text-center">
                        <span class="material-symbols-outlined text-6xl text-gray-400 dark:text-gray-600 mb-4">description</span>
                        <p class="text-gray-500 dark:text-gray-400">File preview will be displayed here</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="p-4 bg-gray-50 dark:bg-background-dark/30 rounded-b-xl flex justify-between items-center flex-shrink-0">
            <button id="downloadFile" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-background-dark/50 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-800">
                <span class="material-symbols-outlined text-sm align-middle">download</span>
                Download
            </button>
            <button id="closeFileViewerBtn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-background-dark/50 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-800">Close</button>
        </div>
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
        const markAllReadBtn = document.getElementById('markAllReadBtn');
        
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
                    const previousNotifications = notifications || [];
                    notifications = data.notifications;
                    updateNotificationDisplay();
                    updateNotificationBadge();
                    
                    // Process notifications for bars and sounds
                    if (window.processNotificationsForBars) {
                        window.processNotificationsForBars(notifications, previousNotifications);
                    }
                    
                    // Play sound for new MOU/MOA notifications
                    const newNotifications = previousNotifications.length > 0 
                        ? notifications.filter(n => !previousNotifications.some(p => p.id === n.id))
                        : notifications;
                    
                    if (window.NotificationSound && window.NotificationSound.checkAndPlay) {
                        window.NotificationSound.checkAndPlay(newNotifications);
                    } else if (window.checkAndPlayMouNotificationSound) {
                        window.checkAndPlayMouNotificationSound(newNotifications);
                    }
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
        
        // View all notifications button
        const viewAllNotifications = document.getElementById('viewAllNotifications');
        if (viewAllNotifications) {
            viewAllNotifications.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                showAllNotificationsModal();
            });
        }
        
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
        
        function showAllNotificationsModal() {
            createAllNotificationsModal();
            const modal = document.getElementById('allNotificationsModal');
            if (modal) {
                modal.classList.remove('hidden');
                loadAllNotificationsIntoModal();
            }
        }
        
        function closeAllNotificationsModal() {
            const modal = document.getElementById('allNotificationsModal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }
        
        async function loadAllNotificationsIntoModal() {
            const modalList = document.getElementById('allNotificationsList');
            const countElement = document.getElementById('notificationsCount');
            
            if (!modalList) return;
            
            try {
                const response = await fetch('api/notifications.php');
                const data = await response.json();
                
                if (data.notifications && Array.isArray(data.notifications)) {
                    let allNotifications = data.notifications;
                    
                    allNotifications.sort((a, b) => {
                        const dateA = new Date(a.created_at);
                        const dateB = new Date(b.created_at);
                        return dateB - dateA;
                    });
                    
                    if (countElement) {
                        countElement.textContent = allNotifications.length;
                    }
                    
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
                            const urlAttribute = targetUrl ? ` data-url="${encodeURIComponent(targetUrl)}"` : '';
                            const actionHint = targetUrl ? '<p class="text-xs text-primary mt-2 font-semibold flex items-center gap-1">Open related record<span class="material-symbols-outlined text-sm">arrow_outward</span></p>' : '';
                            
                            return `
                                <div class="p-4 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors ${notif.is_read ? 'opacity-60' : ''}" 
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
                                        </div>
                                        ${!notif.is_read ? '<div class="flex-shrink-0 w-2 h-2 bg-primary rounded-full mt-2"></div>' : ''}
                                    </div>
                                </div>
                            `;
                        }).join('');
                        
                        modalList.querySelectorAll('[data-notification-id]').forEach(item => {
                            item.addEventListener('click', async function(e) {
                                if (e.target.closest('button')) return;
                                
                                const notificationId = item.getAttribute('data-notification-id');
                                const url = item.getAttribute('data-url');
                                
                                try {
                                    await fetch(`api/notifications.php?id=${notificationId}`, {
                                        method: 'PUT',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ action: 'mark_read' })
                                    });
                                    
                                    if (typeof updateNotificationBadge === 'function') {
                                        await updateNotificationBadge();
                                    }
                                    
                                    if (url) {
                                        closeAllNotificationsModal();
                                        window.location.href = decodeURIComponent(url);
                                    } else {
                                        await loadAllNotificationsIntoModal();
                                    }
                                } catch (error) {
                                    console.error('Error handling notification click:', error);
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
        
        function setupAllNotificationsModalEvents() {
            const modal = document.getElementById('allNotificationsModal');
            if (!modal) return;
            
            const closeBtn2 = document.getElementById('closeAllNotificationsModalBtn2');
            const markAllReadBtn = document.getElementById('markAllReadModalBtn');
            const clearOldBtn = document.getElementById('clearOldNotifications');
            
            if (closeBtn2) {
                closeBtn2.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeAllNotificationsModal();
                });
            }
            
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeAllNotificationsModal();
                }
            });
            
            const escapeHandler = function(e) {
                if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                    closeAllNotificationsModal();
                }
            };
            document.addEventListener('keydown', escapeHandler);
            
            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', async function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    try {
                        const response = await fetch('api/notifications.php', {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json' },
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
            
            if (clearOldBtn) {
                clearOldBtn.addEventListener('click', async function(e) {
                    e.preventDefault();
                    e.stopPropagation();
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
                    }
                });
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
</script>

</body></html>

