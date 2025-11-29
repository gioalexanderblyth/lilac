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

// Load all deleted items from all tables
$deletedItems = [];
try {
    $pdo = getDatabaseConnection();
    if ($pdo instanceof FileBasedDatabase) {
        // File-based system - no trash support
    } else {
        // Ensure deleted_at columns exist
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
        
        // Load deleted documents from MOU, MOA, and Other Documents tables
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
                m.deleted_at,
                u.username as uploaded_by,
                'mou_moa' as source_table
            FROM mou_moa m
            LEFT JOIN users u ON m.user_id = u.id
            WHERE m.deleted_at IS NOT NULL

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
                od.deleted_at,
                u.username as uploaded_by,
                'other_documents' as source_table
            FROM other_documents od
            LEFT JOIN users u ON od.user_id = u.id
            WHERE od.deleted_at IS NOT NULL

            ORDER BY deleted_at DESC
        ");
        $deletedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log('Trash load error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Trash - LILAC</title>
<link crossorigin="" href="https://fonts.gstatic.com/" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
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
        tbody tr:hover td button,
        tbody tr:hover td input[type="checkbox"] {
            pointer-events: auto;
        }
    </style>
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
<a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="dashboard.php" title="Dashboard">
<span class="material-symbols-outlined">dashboard</span>
<span class="sidebar-text hidden">Dashboard</span>
</a>
<a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="awards-hub.php" title="ICONS 2025 Hub">
<span class="material-symbols-outlined">military_tech</span>
<span class="sidebar-text hidden">ICONS 2025</span>
</a>
<a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="awards.php" title="Awards Progress">
<span class="material-symbols-outlined">emoji_events</span>
<span class="sidebar-text hidden">Awards Progress</span>
</a>
<a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="events-activities.php" title="Events & Activities">
<span class="material-symbols-outlined">event</span>
<span class="sidebar-text hidden">Events &amp; Activities</span>
</a>
<a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="scheduler.php" title="Scheduler">
<span class="material-symbols-outlined">calendar_today</span>
<span class="sidebar-text hidden">Scheduler</span>
</a>
<a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="mou-moa.php" title="MOUs & MOAs">
<span class="material-symbols-outlined">handshake</span>
<span class="sidebar-text hidden">MOUs &amp; MOAs</span>
</a>
<a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="documents.php" title="Documents">
<span class="material-symbols-outlined">description</span>
<span class="sidebar-text hidden">Documents</span>
</a>
<a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg bg-primary-50 dark:bg-primary-900/40 text-primary-600 dark:text-primary-400 font-semibold sidebar-nav-link" href="trash.php" title="Trash">
<span class="material-symbols-outlined filled">delete</span>
<span class="sidebar-text hidden">Trash</span>
</a>
</nav>
<div class="px-4 py-4 border-t border-border-light dark:border-border-dark">
<div class="flex items-center justify-between profile-container">
<div class="flex items-center gap-3">
<div class="w-10 h-10 rounded-full bg-cover bg-center sidebar-profile-picture hidden" style='background-image: url("<?php echo !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : 'https://lh3.googleusercontent.com/aida-public/AB6AXuC23fvgOSZIK6K5vguUgvVeU1XYFfp1LB3d4zICMvW6bispRl-eHHfnOtSsvRU3MgvmOpSYMCZhcSBIksvjlEHtkGMxuCFsQkuT0suo2-O9n3py7mlzFFETXCOIfvLVGGUj1aaG8ENOeDXXy_ifek2uG3R3--ghDflKvuAm9vrceoK8doav0lNYVbLz1bnWy6REWcrCPuPZZ8upfPqShoQpSDjICl16zMEcRuHzjt05z9cFITLKPdZTfMF-1dLK-klh8UhjeDeE4Q7p'; ?>");'></div>
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
<header class="sticky top-0 bg-background-light/80 dark:bg-background-dark/80 backdrop-blur-sm z-30 px-6 lg:px-8 py-4 border-b border-border-light dark:border-border-dark flex justify-between items-center h-20 overflow-visible">
<div class="flex items-center gap-3">
<div class="w-10 h-10 rounded-lg bg-gradient-to-br from-rose-500 to-red-600 flex items-center justify-center">
<span class="material-symbols-outlined text-white">delete</span>
</div>
<div>
<h1 class="text-2xl font-bold text-text-light dark:text-text-dark">Trash</h1>
<p class="text-sm text-text-muted-light dark:text-text-muted-dark">Review, restore, or permanently delete removed items</p>
</div>
</div>
<div class="flex items-center gap-2">
<button class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-white/10 text-text-muted-light dark:text-text-muted-dark transition-colors duration-200" id="theme-toggle">
<span class="material-symbols-outlined dark:hidden">light_mode</span>
<span class="material-symbols-outlined hidden dark:inline">dark_mode</span>
</button>
</div>
</header>
<div class="p-4">
<div class="bg-card-light dark:bg-card-dark p-4 rounded-lg shadow-sm border border-border-light dark:border-border-dark">
<!-- Bulk Actions Bar -->
<div id="bulk-actions-bar" class="hidden mb-3 pb-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
    <div class="flex items-center gap-4">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
            <span id="selected-count">0</span> selected
        </span>
    </div>
    <div class="flex items-center gap-2">
        <button id="bulk-restore-btn" class="px-4 py-1.5 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors duration-200 flex items-center gap-2">
            <span class="material-symbols-outlined text-base">restore</span>
            Restore Selected
        </button>
        <button id="bulk-delete-btn" class="px-4 py-1.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors duration-200 flex items-center gap-2">
            <span class="material-symbols-outlined text-base">delete_forever</span>
            Delete Selected
        </button>
    </div>
</div>

<div class="overflow-x-auto">
<table class="w-full min-w-[800px] text-sm text-left">
<thead class="text-sm text-text-muted-light dark:text-text-muted-dark uppercase border-b border-border-light dark:border-border-dark">
<tr>
<th class="py-3 px-4 font-medium text-left" scope="col" style="width: 40px;">
    <input type="checkbox" id="select-all-header" class="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary" title="Select All Visible">
</th>
<th class="py-3 px-4 font-medium text-left" scope="col">Name/Title</th>
<th class="py-3 px-4 font-medium text-center" scope="col">Category</th>
<th class="py-3 px-4 font-medium text-center" scope="col">Deleted Date</th>
<th class="py-3 px-4 font-medium text-center" scope="col">Actions</th>
</tr>
</thead>
<tbody id="trash-table-body">
<?php if (empty($deletedItems)): ?>
<tr>
<td class="py-6 px-4 text-center text-text-muted-light dark:text-text-muted-dark" colspan="5">
<div class="flex flex-col items-center justify-center">
<span class="material-symbols-outlined text-6xl text-gray-400 dark:text-gray-500 mb-4">delete</span>
<p class="text-lg font-medium text-text-muted-light dark:text-text-muted-dark mb-2">Trash is empty</p>
<p class="text-sm text-text-muted-light dark:text-text-muted-dark">Deleted items will appear here</p>
</div>
</td>
</tr>
<?php else: ?>
<?php foreach ($deletedItems as $item): ?>
<tr class="border-b border-border-light dark:border-border-dark">
<td class="py-4 px-4">
    <input type="checkbox" class="trash-item-checkbox w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary" data-id="<?php echo htmlspecialchars($item['id']); ?>" data-source="<?php echo htmlspecialchars($item['source_table']); ?>">
</td>
<td class="py-4 px-4 font-medium text-text-light dark:text-text-dark"><?php echo htmlspecialchars($item['title'] ?? 'Untitled'); ?></td>
<td class="py-4 px-4 text-text-muted-light dark:text-text-muted-dark text-center"><?php echo htmlspecialchars($item['category'] ?? 'N/A'); ?></td>
<td class="py-4 px-4 text-text-muted-light dark:text-text-muted-dark text-center"><?php echo $item['deleted_at'] ? date('M d, Y H:i', strtotime($item['deleted_at'])) : 'N/A'; ?></td>
<td class="py-4 px-4">
<div class="flex items-center justify-center gap-2">
<button class="p-2 rounded-md hover:bg-gray-100 dark:hover:bg-slate-700 text-green-600 dark:text-green-400 transition-colors" aria-label="Restore" data-action="restore" data-id="<?php echo htmlspecialchars($item['id']); ?>" data-source="<?php echo htmlspecialchars($item['source_table']); ?>" title="Restore">
<span class="material-symbols-outlined text-base">restore</span>
</button>
<button class="p-2 rounded-md hover:bg-gray-100 dark:hover:bg-slate-700 text-red-600 dark:text-red-400 transition-colors" aria-label="Delete Permanently" data-action="delete" data-id="<?php echo htmlspecialchars($item['id']); ?>" data-source="<?php echo htmlspecialchars($item['source_table']); ?>" title="Delete Permanently">
<span class="material-symbols-outlined text-base">delete_forever</span>
</button>
</div>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
</main>
</div>

<!-- Restore Confirmation Modal -->
<div id="restoreModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm hidden">
    <div class="w-full max-w-md bg-white dark:bg-card-dark rounded-xl shadow-2xl m-4">
        <div class="p-6 border-b border-gray-200 dark:border-gray-800">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-green-100 dark:bg-green-900/20 rounded-full flex items-center justify-center">
                    <span class="material-symbols-outlined text-green-600 dark:text-green-400 text-xl">restore</span>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Restore Item</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Restore this item from trash</p>
                </div>
            </div>
        </div>
        <div class="p-6">
            <p class="text-gray-700 dark:text-gray-300">
                Are you sure you want to restore this item? It will be moved back to its original location.
            </p>
        </div>
        <div class="p-6 bg-gray-50 dark:bg-gray-800/50 rounded-b-xl flex justify-end gap-3">
            <button id="cancelRestore" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-700">
                Cancel
            </button>
            <button id="confirmRestore" class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                Restore
            </button>
        </div>
    </div>
</div>

<!-- Permanent Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm hidden">
    <div class="w-full max-w-md bg-white dark:bg-card-dark rounded-xl shadow-2xl m-4">
        <div class="p-6 border-b border-gray-200 dark:border-gray-800">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-red-100 dark:bg-red-900/20 rounded-full flex items-center justify-center">
                    <span class="material-symbols-outlined text-red-600 dark:text-red-400 text-xl">warning</span>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Delete Permanently</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">This action cannot be undone</p>
                </div>
            </div>
        </div>
        <div class="p-6">
            <p class="text-gray-700 dark:text-gray-300">
                Are you sure you want to permanently delete this item? This will permanently remove the file and all associated data. This action cannot be undone.
            </p>
        </div>
        <div class="p-6 bg-gray-50 dark:bg-gray-800/50 rounded-b-xl flex justify-end gap-3">
            <button id="cancelDelete" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-700">
                Cancel
            </button>
            <button id="confirmDelete" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 flex items-center gap-2">
                <span class="material-symbols-outlined text-base">delete_forever</span>
                Delete Permanently
            </button>
        </div>
    </div>
</div>

<script>
    const AUTH_TOKEN = '<?php echo $_SESSION['token'] ?? ''; ?>';
    let pendingAction = null;
    let selectedTrashItems = new Set();

    document.addEventListener('DOMContentLoaded', () => {
        // Theme toggle
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                document.documentElement.classList.toggle('dark');
                localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
            });
        }

        // Sidebar toggle
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const appContainer = document.getElementById('app-container');
        if (sidebarToggle && appContainer) {
            sidebarToggle.addEventListener('click', () => {
                appContainer.classList.toggle('sidebar-collapsed');
            });
        }

        // Restore modal
        const restoreModal = document.getElementById('restoreModal');
        const cancelRestoreBtn = document.getElementById('cancelRestore');
        const confirmRestoreBtn = document.getElementById('confirmRestore');

        function showRestoreModal(id, source) {
            pendingAction = { id, source, action: 'restore' };
            if (restoreModal) restoreModal.classList.remove('hidden');
        }

        function hideRestoreModal() {
            pendingAction = null;
            if (restoreModal) restoreModal.classList.add('hidden');
        }

        if (cancelRestoreBtn) {
            cancelRestoreBtn.addEventListener('click', hideRestoreModal);
        }

        if (restoreModal) {
            restoreModal.addEventListener('click', (e) => {
                if (e.target === restoreModal) hideRestoreModal();
            });
        }

        if (confirmRestoreBtn) {
            confirmRestoreBtn.addEventListener('click', async () => {
                if (!pendingAction || pendingAction.action !== 'restore') return;
                const { id, source } = pendingAction;
                
                const originalText = confirmRestoreBtn.textContent;
                confirmRestoreBtn.textContent = 'Restoring...';
                confirmRestoreBtn.disabled = true;

                try {
                    let apiUrl = '';
                    if (source === 'mou_moa') {
                        apiUrl = `api/mou-moa.php?action=restore&id=${encodeURIComponent(id)}`;
                    } else if (source === 'other_documents') {
                        apiUrl = `api/other-documents.php?action=restore&id=${encodeURIComponent(id)}`;
                    } else if (source === 'awards') {
                        apiUrl = `api/awards.php?action=restore&id=${encodeURIComponent(id)}`;
                    } else {
                        apiUrl = `api/other-documents.php?action=restore&id=${encodeURIComponent(id)}`;
                    }

                    const response = await fetch(apiUrl, { 
                        method: 'GET',
                        headers: {
                            'Authorization': 'Bearer ' + AUTH_TOKEN
                        }
                    });
                    const result = await response.json();

                    if (result.success) {
                        location.reload();
                    } else {
                        alert('Failed to restore: ' + (result.error || 'Unknown error'));
                    }
                } catch (error) {
                    alert('Error restoring item: ' + error.message);
                } finally {
                    hideRestoreModal();
                    confirmRestoreBtn.textContent = originalText;
                    confirmRestoreBtn.disabled = false;
                }
            });
        }

        // Delete modal
        const deleteModal = document.getElementById('deleteModal');
        const cancelDeleteBtn = document.getElementById('cancelDelete');
        const confirmDeleteBtn = document.getElementById('confirmDelete');

        function showDeleteModal(id, source) {
            pendingAction = { id, source, action: 'delete' };
            if (deleteModal) deleteModal.classList.remove('hidden');
        }

        function hideDeleteModal() {
            pendingAction = null;
            if (deleteModal) deleteModal.classList.add('hidden');
        }

        if (cancelDeleteBtn) {
            cancelDeleteBtn.addEventListener('click', hideDeleteModal);
        }

        if (deleteModal) {
            deleteModal.addEventListener('click', (e) => {
                if (e.target === deleteModal) hideDeleteModal();
            });
        }

        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener('click', async () => {
                if (!pendingAction || pendingAction.action !== 'delete') return;
                const { id, source } = pendingAction;
                
                const originalText = confirmDeleteBtn.textContent;
                confirmDeleteBtn.textContent = 'Deleting...';
                confirmDeleteBtn.disabled = true;

                try {
                    let apiUrl = '';
                    if (source === 'mou_moa') {
                        apiUrl = `api/mou-moa.php?id=${encodeURIComponent(id)}&permanent=true`;
                    } else if (source === 'other_documents') {
                        apiUrl = `api/other-documents.php?id=${encodeURIComponent(id)}&permanent=true`;
                    } else if (source === 'awards') {
                        apiUrl = `api/delete-award.php?id=${encodeURIComponent(id)}&permanent=true`;
                    } else {
                        apiUrl = `api/other-documents.php?id=${encodeURIComponent(id)}&permanent=true`;
                    }

                    const response = await fetch(apiUrl, { method: 'DELETE' });
                    const result = await response.json();

                    if (result.success) {
                        location.reload();
                    } else {
                        alert('Failed to delete: ' + (result.error || 'Unknown error'));
                    }
                } catch (error) {
                    alert('Error deleting item: ' + error.message);
                } finally {
                    hideDeleteModal();
                    confirmDeleteBtn.textContent = originalText;
                    confirmDeleteBtn.disabled = false;
                }
            });
        }

        // Handle action buttons
        const tableBody = document.getElementById('trash-table-body');
        if (tableBody) {
            tableBody.addEventListener('click', (e) => {
                const button = e.target.closest('button[data-action]');
                if (!button) return;
                
                const action = button.getAttribute('data-action');
                const id = button.getAttribute('data-id');
                const source = button.getAttribute('data-source');
                
                if (action === 'restore') {
                    showRestoreModal(id, source);
                } else if (action === 'delete') {
                    showDeleteModal(id, source);
                }
            });
        }

        // Bulk selection tracking
        function updateBulkActionsBar() {
            const bulkActionsBar = document.getElementById('bulk-actions-bar');
            const selectedCount = document.getElementById('selected-count');
            const totalSelected = selectedTrashItems.size;
            
            if (totalSelected > 0) {
                bulkActionsBar.classList.remove('hidden');
                selectedCount.textContent = totalSelected;
            } else {
                bulkActionsBar.classList.add('hidden');
            }
            
            // Update select all header checkbox state
            const selectAllHeaderCheckbox = document.getElementById('select-all-header');
            const visibleCheckboxes = document.querySelectorAll('.trash-item-checkbox');
            const visibleChecked = Array.from(visibleCheckboxes).filter(cb => cb.checked).length;
            
            if (selectAllHeaderCheckbox && visibleCheckboxes.length > 0) {
                selectAllHeaderCheckbox.checked = visibleChecked === visibleCheckboxes.length;
                selectAllHeaderCheckbox.indeterminate = visibleChecked > 0 && visibleChecked < visibleCheckboxes.length;
            }
        }

        // Handle select all header checkbox (this serves as "Select All Visible")
        const selectAllHeaderCheckbox = document.getElementById('select-all-header');
        if (selectAllHeaderCheckbox) {
            selectAllHeaderCheckbox.addEventListener('change', function() {
                const visibleCheckboxes = document.querySelectorAll('.trash-item-checkbox');
                visibleCheckboxes.forEach(checkbox => {
                    checkbox.checked = selectAllHeaderCheckbox.checked;
                    const id = checkbox.dataset.id;
                    const source = checkbox.dataset.source;
                    const key = `${id}_${source}`;
                    if (selectAllHeaderCheckbox.checked) {
                        selectedTrashItems.add(key);
                    } else {
                        selectedTrashItems.delete(key);
                    }
                });
                updateBulkActionsBar();
            });
        }

        // Handle individual checkbox changes
        if (tableBody) {
            tableBody.addEventListener('change', function(e) {
                if (e.target.classList.contains('trash-item-checkbox')) {
                    const checkbox = e.target;
                    const id = checkbox.dataset.id;
                    const source = checkbox.dataset.source;
                    const key = `${id}_${source}`;
                    
                    if (checkbox.checked) {
                        selectedTrashItems.add(key);
                    } else {
                        selectedTrashItems.delete(key);
                    }
                    updateBulkActionsBar();
                }
            });
        }

        // Bulk restore function
        async function bulkRestoreItems() {
            if (selectedTrashItems.size === 0) {
                alert('Please select at least one item to restore');
                return;
            }

            const items = Array.from(selectedTrashItems).map(key => {
                const [id, source] = key.split('_');
                return { id, source };
            });

            if (!confirm(`Are you sure you want to restore ${items.length} selected item${items.length > 1 ? 's' : ''}?`)) {
                return;
            }

            let successCount = 0;
            let failCount = 0;

            for (const item of items) {
                try {
                    let apiUrl = '';
                    if (item.source === 'mou_moa') {
                        apiUrl = `api/mou-moa.php?action=restore&id=${encodeURIComponent(item.id)}`;
                    } else if (item.source === 'other_documents') {
                        apiUrl = `api/other-documents.php?action=restore&id=${encodeURIComponent(item.id)}`;
                    } else {
                        apiUrl = `api/other-documents.php?action=restore&id=${encodeURIComponent(item.id)}`;
                    }

                    const response = await fetch(apiUrl, { 
                        method: 'GET',
                        headers: {
                            'Authorization': 'Bearer ' + AUTH_TOKEN
                        }
                    });
                    const result = await response.json();

                    if (result.success) {
                        successCount++;
                    } else {
                        failCount++;
                    }
                } catch (error) {
                    console.error('Error restoring item:', error);
                    failCount++;
                }
            }

            if (successCount > 0) {
                alert(`Successfully restored ${successCount} item${successCount > 1 ? 's' : ''}${failCount > 0 ? `. ${failCount} failed.` : ''}`);
                location.reload();
            } else {
                alert('Failed to restore items. Please try again.');
            }
        }

        // Bulk delete function
        async function bulkDeleteItems() {
            if (selectedTrashItems.size === 0) {
                alert('Please select at least one item to delete permanently');
                return;
            }

            const items = Array.from(selectedTrashItems).map(key => {
                const [id, source] = key.split('_');
                return { id, source };
            });

            if (!confirm(`Are you sure you want to permanently delete ${items.length} selected item${items.length > 1 ? 's' : ''}? This action cannot be undone.`)) {
                return;
            }

            let successCount = 0;
            let failCount = 0;

            for (const item of items) {
                try {
                    let apiUrl = '';
                    if (item.source === 'mou_moa') {
                        apiUrl = `api/mou-moa.php?id=${encodeURIComponent(item.id)}&permanent=true`;
                    } else if (item.source === 'other_documents') {
                        apiUrl = `api/other-documents.php?id=${encodeURIComponent(item.id)}&permanent=true`;
                    } else if (item.source === 'awards') {
                        apiUrl = `api/delete-award.php?id=${encodeURIComponent(item.id)}&permanent=true`;
                    } else {
                        apiUrl = `api/other-documents.php?id=${encodeURIComponent(item.id)}&permanent=true`;
                    }

                    const response = await fetch(apiUrl, { method: 'DELETE' });
                    const result = await response.json();

                    if (result.success) {
                        successCount++;
                    } else {
                        failCount++;
                    }
                } catch (error) {
                    console.error('Error deleting item:', error);
                    failCount++;
                }
            }

            if (successCount > 0) {
                alert(`Successfully deleted ${successCount} item${successCount > 1 ? 's' : ''}${failCount > 0 ? `. ${failCount} failed.` : ''}`);
                location.reload();
            } else {
                alert('Failed to delete items. Please try again.');
            }
        }

        // Setup bulk action buttons
        const bulkRestoreBtn = document.getElementById('bulk-restore-btn');
        const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
        
        if (bulkRestoreBtn) {
            bulkRestoreBtn.addEventListener('click', bulkRestoreItems);
        }
        
        if (bulkDeleteBtn) {
            bulkDeleteBtn.addEventListener('click', bulkDeleteItems);
        }
    });
</script>
</body>
</html>

