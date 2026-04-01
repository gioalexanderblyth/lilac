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
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
            if ($stmt->rowCount() === 0) {
                $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) NULL DEFAULT NULL AFTER full_name");
            }
        } catch (Exception $e) {}
        $stmt = $pdo->prepare('SELECT id, username, email, full_name, role, department, phone, profile_picture, created_at FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($dbUser) {
            $_SESSION['user'] = array_merge($user, $dbUser);
            $user = $_SESSION['user'];
        }
    }
} catch (Exception $e) {
    error_log('Failed to refresh user data: ' . $e->getMessage());
}

$isAdmin = false;
try {
    $pdo = getDatabaseConnection();
    if (!($pdo instanceof FileBasedDatabase)) {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($dbUser) {
            $isAdmin = ($dbUser['role'] === 'admin');
        }
    } else {
        $isAdmin = isset($user['role']) && $user['role'] === 'admin';
    }
} catch (Exception $e) {
    $isAdmin = isset($user['role']) && $user['role'] === 'admin';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Forms - LILAC</title>
    <link rel="preconnect" href="https://fonts.gstatic.com/"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
    <link rel="stylesheet" href="assets/css/tailwind.css"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .material-symbols-outlined.filled { font-variation-settings: 'FILL' 1; }
        .sidebar-collapsed .sidebar-text, .sidebar-collapsed .sidebar-logo-text { display: none; }
        .sidebar { width: 16rem; min-width: 16rem; max-width: 16rem; flex-shrink: 0; }
        .sidebar-collapsed .sidebar { width: 5rem; min-width: 5rem; max-width: 5rem; }
        .sidebar-collapsed .sidebar-nav-link { justify-content: center; padding-left: 0; padding-right: 0; }
        .sidebar-collapsed .sidebar-profile-info, .sidebar-collapsed .sidebar-profile-picture { display: none; }
        .sidebar-collapsed #main-content { margin-left: 5rem; }
        .sidebar-collapsed .sidebar-toggle-icon-open { display: none; }
        .sidebar-collapsed .sidebar-toggle-icon-closed { display: block; }
        .sidebar-toggle-icon-closed { display: none; }
        .view-toggle.active { background: rgba(0,0,0,0.05); }
        .dark .view-toggle.active { background: rgba(255,255,255,0.1); }
        .dark .sidebar-nav-link.bg-gradient-to-r { background-image: linear-gradient(to right, rgba(88, 28, 135, 0.4), rgba(67, 56, 202, 0.4)) !important; }
        /* Single structure: grid vs list layout (Canva-style) */
        #forms-container.view-grid { display: block; padding: 1.25rem; }
        #forms-container.view-list { display: block; padding: 0; }
        #forms-container .forms-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.25rem; align-content: start; }
        #forms-container .forms-table { width: 100%; border-collapse: collapse; }
        #forms-container.view-list .forms-table { display: table; }
        #forms-container.view-grid .forms-table { display: none; }
        #forms-container.view-list .forms-grid { display: none; }
        #forms-container.view-list .forms-table thead th { text-align: left; padding: 0.75rem 1rem; font-weight: 500; font-size: 0.875rem; color: rgb(107 114 128); border-bottom: 1px solid rgba(0,0,0,0.08); background: transparent; }
        .dark #forms-container.view-list .forms-table thead th { color: rgb(156 163 175); border-color: rgba(255,255,255,0.1); }
        #forms-container.view-list .forms-table thead th:last-child { text-align: right; }
        #forms-container.view-list .forms-table tbody tr { border-bottom: 1px solid rgba(0,0,0,0.06); }
        .dark #forms-container.view-list .forms-table tbody tr { border-color: rgba(255,255,255,0.06); }
        #forms-container.view-list .forms-table tbody tr:hover { background: rgba(0,0,0,0.02); }
        .dark #forms-container.view-list .forms-table tbody tr:hover { background: rgba(255,255,255,0.04); }
        #forms-container.view-list .forms-table td { padding: 0.75rem 1rem; vertical-align: middle; }
        #forms-container.view-list .forms-table td:last-child { text-align: right; }
        #forms-container.view-list .form-item-name { display: flex; align-items: center; gap: 0.75rem; }
        #forms-container.view-list .form-item-name .form-item-icon { width: 36px; height: 36px; border-radius: 6px; background: rgba(0,0,0,0.06); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .dark #forms-container.view-list .form-item-name .form-item-icon { background: rgba(255,255,255,0.08); }
        #forms-container.view-list .form-item-name .form-item-title { font-weight: 500; color: inherit; }
        #forms-container.view-list .form-item-type { font-size: 0.875rem; color: rgb(107 114 128); }
        .dark #forms-container.view-list .form-item-type { color: rgb(156 163 175); }
        #forms-container.view-list .form-item-date { font-size: 0.875rem; color: rgb(107 114 128); }
        .dark #forms-container.view-list .form-item-date { color: rgb(156 163 175); }
        #forms-container.view-list .form-item-actions { display: flex; gap: 0.25rem; justify-content: flex-end; align-items: center; }
        #forms-container.view-grid .form-item { display: flex; flex-direction: column; border: 1px solid rgba(0,0,0,0.08); border-radius: 0.75rem; overflow: hidden; background: inherit; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        #forms-container.view-grid .form-item:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-color: rgba(0,0,0,0.12); }
        .dark #forms-container.view-grid .form-item { border-color: rgba(255,255,255,0.08); box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
        .dark #forms-container.view-grid .form-item:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.3); border-color: rgba(255,255,255,0.12); }
        #forms-container.view-grid .form-item-icon-wrap { width: 40px; height: 40px; border-radius: 0.5rem; background: rgba(0,0,0,0.04); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .dark #forms-container.view-grid .form-item-icon-wrap { background: rgba(255,255,255,0.06); }
        #forms-container.view-grid .form-item-footer { padding: 1rem 1.25rem; display: flex; gap: 0.75rem; align-items: center; flex-shrink: 0; background: rgba(0,0,0,0.02); }
        #forms-container.view-grid .form-item-footer .form-item-info { flex: 1; min-w-0; display: flex; align-items: center; gap: 0.75rem; }
        #forms-container.view-grid .form-item-footer .form-item-actions { display: flex; gap: 0.25rem; align-items: center; flex-shrink: 0; }
        .dark #forms-container.view-grid .form-item-footer { background: rgba(255,255,255,0.03); }
        /* Canva-style preview area */
        #forms-container.view-grid .form-item-preview {
            aspect-ratio: 4/3;
            background: rgba(0,0,0,0.04);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .dark #forms-container.view-grid .form-item-preview { background: rgba(255,255,255,0.04); }
        #forms-container.view-grid .form-item-preview img.preview-thumb { width: 100%; height: 100%; object-fit: cover; display: block; }
        #forms-container.view-grid .form-item-preview .preview-icon { font-size: 3rem; color: rgb(156 163 175); }
        .dark #forms-container.view-grid .form-item-preview .preview-icon { color: rgb(107 114 128); }
        #forms-container.view-grid .form-item-preview canvas { width: 100%; height: 100%; object-fit: cover; }
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
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-600 dark:hover:text-purple-400 transition-colors duration-200 sidebar-nav-link" href="documents.php" title="Documents">
                <span class="material-symbols-outlined flex-shrink-0">description</span>
                <span class="sidebar-text whitespace-nowrap">Documents</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg bg-gradient-to-r from-purple-50 to-indigo-50 dark:from-purple-900/40 dark:to-indigo-900/40 text-purple-600 dark:text-purple-400 font-semibold sidebar-nav-link border border-purple-200 dark:border-purple-800 shadow-sm" href="forms.php" title="Forms">
                <span class="material-symbols-outlined filled flex-shrink-0">edit_note</span>
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
    <main class="flex-1 min-w-0 min-h-0 overflow-y-auto overflow-x-hidden transition-all duration-300 ml-64" id="main-content">
        <header class="sticky top-0 bg-background-light/80 dark:bg-background-dark/80 backdrop-blur-sm z-30 px-4 sm:px-6 lg:px-8 py-4 border-b border-border-light dark:border-border-dark flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 min-h-[5rem] overflow-visible">
            <div class="flex items-center gap-3 flex-shrink-0">
                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-amber-500 to-orange-500 flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-white">edit_note</span>
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl sm:text-2xl font-bold text-text-light dark:text-text-dark truncate">Forms</h1>
                    <p class="text-sm text-text-muted-light dark:text-text-muted-dark truncate">Access and manage forms</p>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <button class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-white/10 text-text-muted-light dark:text-text-muted-dark transition-colors flex-shrink-0" id="theme-toggle">
                    <span class="material-symbols-outlined dark:hidden">light_mode</span>
                    <span class="material-symbols-outlined hidden dark:inline">dark_mode</span>
                </button>
            </div>
        </header>
        <div class="p-4 sm:p-6 min-h-0 flex flex-col">
            <!-- View toggle (left) + action buttons (right) -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4 sm:mb-6 flex-shrink-0">
                <div class="flex items-center gap-2">
                    <button type="button" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-white/10 transition-colors view-toggle active" data-view="card" title="Card view">
                        <span class="material-symbols-outlined text-text-muted-light dark:text-text-muted-dark">grid_view</span>
                    </button>
                    <button type="button" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-white/10 transition-colors view-toggle" data-view="list" title="List view">
                        <span class="material-symbols-outlined text-text-muted-light dark:text-text-muted-dark">list</span>
                    </button>
                </div>
                <div class="flex flex-wrap gap-2 sm:justify-end">
                    <button type="button" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-medium transition-colors shadow-sm text-sm" id="btn-upload" title="Upload forms">
                        <span class="material-symbols-outlined text-lg">upload_file</span>
                        <span>Upload Forms</span>
                    </button>
                </div>
            </div>

            <!-- Single forms container (grid or list layout via CSS) -->
            <div class="flex-1 min-h-0 overflow-auto flex flex-col">
                <div id="forms-empty-state" class="flex-1 bg-card-light dark:bg-card-dark border border-border-light dark:border-border-dark rounded-lg p-8 sm:p-12 text-center flex flex-col items-center justify-center min-h-[200px]">
                    <span class="material-symbols-outlined text-6xl text-text-muted-light dark:text-text-muted-dark mb-4 block">edit_note</span>
                    <h2 class="text-xl font-semibold text-text-light dark:text-text-dark mb-2">No forms yet</h2>
                    <p class="text-text-muted-light dark:text-text-muted-dark max-w-md mx-auto">Upload forms or use the buttons above to get started.</p>
                </div>
                <div id="forms-container" class="hidden view-grid flex-1 min-h-0 min-w-0 bg-card-light dark:bg-card-dark border border-border-light dark:border-border-dark rounded-lg overflow-auto">
                    <!-- List view: Canva-style table (Name | Type | Edited | Actions) -->
                    <table class="forms-table w-full">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Edited</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="forms-tbody">
                            <!-- form rows rendered by JS -->
                        </tbody>
                    </table>
                    <!-- Grid view: card items rendered by JS into #forms-grid -->
                    <div id="forms-grid" class="forms-grid">
                        <!-- form cards rendered by JS -->
                    </div>
                </div>
            </div>

            <!-- Upload Forms Modal -->
            <div id="upload-forms-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4 hidden">
                <div class="bg-card-light dark:bg-card-dark rounded-xl shadow-xl max-w-md w-full">
                    <div class="p-6 border-b border-border-light dark:border-border-dark">
                        <h2 class="text-lg font-bold text-text-light dark:text-text-dark">Upload Forms</h2>
                        <p class="text-sm text-text-muted-light dark:text-text-muted-dark mt-1">Upload PDF, Word, or image forms (max 10MB)</p>
                    </div>
                    <form id="upload-forms-form" class="p-6 space-y-4">
                        <div>
                            <label for="form-title" class="block text-sm font-medium text-text-light dark:text-text-dark mb-1">Form Title</label>
                            <input type="text" id="form-title" name="title" class="w-full px-3 py-2 rounded-lg border border-border-light dark:border-border-dark bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="e.g. Leave Request Form (optional – uses file name if empty)"/>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-text-light dark:text-text-dark mb-2">Select File</label>
                            <div id="form-drop-zone" class="border-2 border-dashed border-border-light dark:border-border-dark rounded-lg p-6 text-center cursor-pointer hover:border-primary transition-colors">
                                <input type="file" id="form-file-input" name="file" class="hidden" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.txt" required/>
                                <span class="material-symbols-outlined text-4xl text-text-muted-light dark:text-text-muted-dark mb-2 block">cloud_upload</span>
                                <span class="text-text-muted-light dark:text-text-muted-dark text-sm">Click to select or drag and drop</span>
                                <p id="form-file-name" class="text-sm text-primary mt-2 hidden"></p>
                            </div>
                        </div>
                    </form>
                    <div class="p-6 border-t border-border-light dark:border-border-dark flex justify-end gap-2">
                        <button type="button" id="upload-modal-cancel" class="px-4 py-2 rounded-lg border border-border-light dark:border-border-dark text-text-light dark:text-text-dark hover:bg-gray-100 dark:hover:bg-white/5 transition-colors">Cancel</button>
                        <button type="submit" form="upload-forms-form" id="upload-form-submit" class="px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white font-medium transition-colors flex items-center gap-2">
                            <span class="material-symbols-outlined text-lg">upload_file</span>
                            Upload
                        </button>
                    </div>
                </div>
            </div>

            <!-- File Viewer Modal -->
            <div id="form-file-viewer-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm hidden">
                <div class="w-full max-w-4xl bg-card-light dark:bg-card-dark rounded-xl shadow-2xl m-4 flex flex-col max-h-[90vh]">
                    <div class="p-4 sm:p-6 border-b border-border-light dark:border-border-dark flex-shrink-0 flex items-center justify-between">
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-semibold text-text-light dark:text-text-dark truncate" id="form-file-viewer-title">View File</h3>
                            <p class="text-sm text-text-muted-light dark:text-text-muted-dark truncate" id="form-file-viewer-subtitle"></p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <a id="form-file-viewer-download" href="#" target="_blank" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-primary bg-primary/10 hover:bg-primary/20 rounded-lg">
                                <span class="material-symbols-outlined text-base">download</span> Download
                            </a>
                            <button type="button" id="form-file-viewer-close" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 text-text-muted-light dark:text-text-muted-dark">
                                <span class="material-symbols-outlined">close</span>
                            </button>
                        </div>
                    </div>
                    <div class="p-4 sm:p-6 flex-1 overflow-auto min-h-[400px]">
                        <div class="w-full h-full bg-gray-50 dark:bg-gray-900 rounded-lg">
                            <div id="form-file-viewer-content" class="w-full h-full min-h-[400px]">
                                <div class="flex items-center justify-center h-full">
                                    <span class="material-symbols-outlined text-6xl text-gray-400 animate-pulse">hourglass_empty</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Move to Trash Modal -->
            <div id="trash-form-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4 hidden">
                <div class="bg-card-light dark:bg-card-dark rounded-xl shadow-xl max-w-md w-full">
                    <div class="p-6">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-red-100 dark:bg-red-900/20 rounded-full flex items-center justify-center flex-shrink-0">
                                <span class="material-symbols-outlined text-red-600 dark:text-red-400 text-xl">delete</span>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-text-light dark:text-text-dark">Move to Trash</h3>
                                <p class="text-sm text-text-muted-light dark:text-text-muted-dark mt-1">Are you sure you want to move this form to trash? You can restore it later from the Trash page.</p>
                            </div>
                        </div>
                    </div>
                    <div class="p-6 pt-0 flex justify-end gap-3">
                        <button type="button" id="trash-modal-cancel" class="px-4 py-2 text-sm font-medium text-text-light dark:text-text-dark border border-border-light dark:border-border-dark rounded-lg hover:bg-gray-100 dark:hover:bg-white/5 transition-colors">Cancel</button>
                        <button type="button" id="trash-modal-confirm" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors flex items-center gap-2">
                            <span class="material-symbols-outlined text-lg">delete</span>
                            Move to Trash
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<script>
    if (typeof pdfjsLib !== 'undefined') {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    }
    document.addEventListener('DOMContentLoaded', () => {
        const appContainer = document.getElementById('app-container');
        const mainContent = document.getElementById('main-content');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebarLogoText = document.querySelector('.sidebar-logo-text');
        const sidebarTexts = document.querySelectorAll('.sidebar-text');
        const sidebarProfileInfo = document.querySelector('.sidebar-profile-info');
        const sidebarProfilePicture = document.querySelector('.sidebar-profile-picture');
        const openIcon = document.querySelector('.sidebar-toggle-icon-open');
        const closedIcon = document.querySelector('.sidebar-toggle-icon-closed');
        const profileContainer = document.querySelector('.profile-container');
        const navLinks = document.querySelectorAll('.sidebar-nav-link');

        const initSidebarState = () => {
            const savedState = localStorage.getItem('sidebarCollapsed');
            if (savedState === 'false') {
                appContainer.classList.remove('sidebar-collapsed');
                if (mainContent) {
                    mainContent.classList.remove('ml-20');
                    mainContent.classList.add('ml-64');
                }
                if (sidebarLogoText) sidebarLogoText.classList.remove('hidden');
                sidebarTexts.forEach(t => t.classList.remove('hidden'));
                if (sidebarProfileInfo) sidebarProfileInfo.classList.remove('hidden');
                if (sidebarProfilePicture) sidebarProfilePicture.classList.remove('hidden');
                if (openIcon) { openIcon.classList.remove('hidden'); openIcon.classList.add('block'); }
                if (closedIcon) { closedIcon.classList.add('hidden'); closedIcon.classList.remove('block'); }
                navLinks.forEach(l => l.classList.remove('justify-center'));
                if (profileContainer) profileContainer.classList.remove('justify-center');
            } else {
                appContainer.classList.add('sidebar-collapsed');
                if (mainContent) {
                    mainContent.classList.remove('ml-64');
                    mainContent.classList.add('ml-20');
                }
            }
        };
        initSidebarState();

        const toggleSidebar = () => {
            const isCollapsed = appContainer.classList.contains('sidebar-collapsed');
            if (isCollapsed) {
                appContainer.classList.remove('sidebar-collapsed');
                if (mainContent) { mainContent.classList.remove('ml-20'); mainContent.classList.add('ml-64'); }
                if (sidebarLogoText) sidebarLogoText.classList.remove('hidden');
                sidebarTexts.forEach(t => t.classList.remove('hidden'));
                if (sidebarProfileInfo) sidebarProfileInfo.classList.remove('hidden');
                if (sidebarProfilePicture) sidebarProfilePicture.classList.remove('hidden');
                if (openIcon) { openIcon.classList.remove('hidden'); openIcon.classList.add('block'); }
                if (closedIcon) { closedIcon.classList.add('hidden'); closedIcon.classList.remove('block'); }
                navLinks.forEach(l => l.classList.remove('justify-center'));
                if (profileContainer) profileContainer.classList.remove('justify-center');
                localStorage.setItem('sidebarCollapsed', 'false');
            } else {
                appContainer.classList.add('sidebar-collapsed');
                if (mainContent) { mainContent.classList.remove('ml-64'); mainContent.classList.add('ml-20'); }
                if (sidebarLogoText) sidebarLogoText.classList.add('hidden');
                sidebarTexts.forEach(t => t.classList.add('hidden'));
                if (sidebarProfileInfo) sidebarProfileInfo.classList.add('hidden');
                if (sidebarProfilePicture) sidebarProfilePicture.classList.add('hidden');
                if (openIcon) { openIcon.classList.add('hidden'); openIcon.classList.remove('block'); }
                if (closedIcon) { closedIcon.classList.remove('hidden'); closedIcon.classList.add('block'); }
                navLinks.forEach(l => l.classList.add('justify-center'));
                if (profileContainer) profileContainer.classList.add('justify-center');
                localStorage.setItem('sidebarCollapsed', 'true');
            }
        };

        sidebarToggle?.addEventListener('click', toggleSidebar);

        document.getElementById('theme-toggle')?.addEventListener('click', () => {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
        });

        // Forms API
        const FORMS_API = 'api/forms.php';
        let allForms = [];

        async function loadForms() {
            try {
                const res = await fetch(FORMS_API);
                const data = await res.json();
                if (data.success) {
                    allForms = data.data || [];
                    renderForms();
                    return allForms;
                }
            } catch (e) { console.error('Load forms error:', e); }
            allForms = [];
            renderForms();
            return [];
        }

        function getFileTypeLabel(fileName) {
            if (!fileName) return '—';
            const ext = (fileName.split('.').pop() || '').toLowerCase();
            const types = { pdf: 'PDF', doc: 'Word', docx: 'Word', xls: 'Excel', xlsx: 'Excel', jpg: 'Image', jpeg: 'Image', png: 'Image', webp: 'Image', txt: 'Text' };
            return types[ext] || ext.toUpperCase();
        }

        function renderForms() {
            const emptyState = document.getElementById('forms-empty-state');
            const container = document.getElementById('forms-container');
            const tbody = document.getElementById('forms-tbody');
            const grid = document.getElementById('forms-grid');

            if (allForms.length === 0) {
                emptyState?.classList.remove('hidden');
                container?.classList.add('hidden');
                return;
            }

            emptyState?.classList.add('hidden');
            container?.classList.remove('hidden');

            const rows = allForms.map(f => {
                const date = f.created_at ? new Date(f.created_at).toLocaleDateString() : '—';
                const typeLabel = getFileTypeLabel(f.file_name);
                return `
                    <tr class="form-item-row">
                        <td>
                            <div class="form-item-name">
                                <div class="form-item-icon"><span class="material-symbols-outlined text-lg text-text-muted-light dark:text-text-muted-dark">description</span></div>
                                <button type="button" class="form-view-file form-item-title truncate font-medium text-primary hover:underline cursor-pointer text-left bg-transparent border-0 p-0" data-file-path="${escapeHtml(f.file_path)}" data-file-name="${escapeHtml(f.file_name || '')}" data-title="${escapeHtml(f.title)}">${escapeHtml(f.title)}</button>
                            </div>
                        </td>
                        <td class="form-item-type">${escapeHtml(typeLabel)}</td>
                        <td class="form-item-date">${escapeHtml(date)}</td>
                        <td>
                            <div class="form-item-actions">
                                <button type="button" class="form-remove-btn p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 text-text-muted-light dark:text-text-muted-dark hover:text-red-600 dark:hover:text-red-400 transition-colors" data-id="${f.id}" title="Move to Trash">
                                    <span class="material-symbols-outlined text-lg">delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');

            const cards = allForms.map(f => {
                const ext = (f.file_name || '').split('.').pop().toLowerCase();
                const isImage = ['jpg','jpeg','png','gif','webp','bmp','svg'].includes(ext);
                const isPdf = ext === 'pdf';
                let previewHtml = '';
                if (isImage) {
                    previewHtml = `<img class="preview-thumb" src="${escapeHtml(f.file_path)}" alt="" loading="lazy" onerror="this.style.display='none';this.nextElementSibling?.classList.remove('hidden');"><div class="preview-fallback hidden absolute inset-0 flex items-center justify-center"><span class="material-symbols-outlined preview-icon">image</span></div>`;
                } else if (isPdf) {
                    previewHtml = `<div class="form-pdf-preview w-full h-full min-h-[120px]" data-pdf-url="${escapeHtml(f.file_path)}"></div>`;
                } else {
                    previewHtml = `<span class="material-symbols-outlined preview-icon">description</span>`;
                }
                return `
                    <div class="form-item bg-card-light dark:bg-card-dark">
                        <button type="button" class="form-item-preview relative block w-full text-left bg-transparent border-0 cursor-pointer hover:opacity-90 transition-opacity p-0 form-view-file" data-file-path="${escapeHtml(f.file_path)}" data-file-name="${escapeHtml(f.file_name || '')}" data-title="${escapeHtml(f.title)}" title="View file">${previewHtml}</button>
                        <div class="form-item-footer">
                            <div class="form-item-info">
                                <div class="form-item-icon-wrap">
                                    <span class="material-symbols-outlined text-lg text-text-muted-light dark:text-text-muted-dark">description</span>
                                </div>
                                <button type="button" class="form-item-title text-text-light dark:text-text-dark truncate font-medium hover:text-primary hover:underline min-w-0 flex-1 text-left bg-transparent border-0 p-0 form-view-file" data-file-path="${escapeHtml(f.file_path)}" data-file-name="${escapeHtml(f.file_name || '')}" data-title="${escapeHtml(f.title)}" title="View file">${escapeHtml(f.title)}</button>
                            </div>
                            <div class="form-item-actions">
                                <button type="button" class="form-remove-btn p-1.5 text-text-muted-light dark:text-text-muted-dark hover:bg-red-50 dark:hover:bg-red-900/20 hover:text-red-600 dark:hover:text-red-400 rounded-lg transition-colors" data-id="${f.id}" title="Move to Trash">
                                    <span class="material-symbols-outlined text-base">delete</span>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            if (tbody) tbody.innerHTML = rows;
            if (grid) grid.innerHTML = cards;
            renderPDFThumbnails();
        }

        async function renderPDFThumbnails() {
            if (typeof pdfjsLib === 'undefined') return;
            const containers = document.querySelectorAll('.form-pdf-preview');
            for (const el of containers) {
                const url = el.dataset.pdfUrl;
                if (!url) continue;
                try {
                    const pdf = await pdfjsLib.getDocument(url).promise;
                    const page = await pdf.getPage(1);
                    const viewport = page.getViewport({ scale: 0.3 });
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    canvas.width = viewport.width;
                    canvas.height = viewport.height;
                    await page.render({ canvasContext: ctx, viewport }).promise;
                    el.innerHTML = '';
                    el.appendChild(canvas);
                    canvas.style.width = '100%';
                    canvas.style.height = '100%';
                    canvas.style.objectFit = 'cover';
                } catch (err) {
                    el.innerHTML = '<span class="material-symbols-outlined preview-icon">picture_as_pdf</span>';
                }
            }
        }

        function escapeHtml(s) {
            if (!s) return '';
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        function openUploadModal() {
            document.getElementById('upload-forms-modal').classList.remove('hidden');
            document.getElementById('upload-forms-form').reset();
            document.getElementById('form-file-name').classList.add('hidden');
        }

        function closeUploadModal() {
            document.getElementById('upload-forms-modal').classList.add('hidden');
        }

        document.getElementById('btn-upload')?.addEventListener('click', openUploadModal);

        let pendingTrashFormId = null;
        function showTrashModal(id) {
            pendingTrashFormId = id;
            document.getElementById('trash-form-modal').classList.remove('hidden');
        }
        function hideTrashModal() {
            pendingTrashFormId = null;
            document.getElementById('trash-form-modal').classList.add('hidden');
        }
        document.getElementById('forms-container')?.addEventListener('click', (e) => {
            const btn = e.target.closest('.form-remove-btn');
            if (!btn) return;
            const id = btn.dataset.id;
            if (id) showTrashModal(id);
        });
        document.getElementById('trash-modal-cancel')?.addEventListener('click', hideTrashModal);
        document.getElementById('trash-form-modal')?.addEventListener('click', (e) => {
            if (e.target.id === 'trash-form-modal') hideTrashModal();
        });
        document.getElementById('trash-modal-confirm')?.addEventListener('click', async () => {
            if (!pendingTrashFormId) return;
            const id = pendingTrashFormId;
            const btn = document.getElementById('trash-modal-confirm');
            btn.disabled = true;
            btn.innerHTML = '<span class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent inline-block"></span> Moving...';
            try {
                const res = await fetch(FORMS_API + '?id=' + id, { method: 'DELETE' });
                const data = await res.json();
                if (data.success) {
                    hideTrashModal();
                    window.location.href = 'trash.php';
                } else {
                    alert(data.error || 'Failed to move to trash');
                }
            } catch (err) {
                alert('Failed to move to trash: ' + (err.message || 'Network error'));
            }
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-outlined text-lg">delete</span> Move to Trash';
        });

        function openFormFileViewer(filePath, fileName, title) {
            const modal = document.getElementById('form-file-viewer-modal');
            const contentEl = document.getElementById('form-file-viewer-content');
            const titleEl = document.getElementById('form-file-viewer-title');
            const subtitleEl = document.getElementById('form-file-viewer-subtitle');
            const downloadLink = document.getElementById('form-file-viewer-download');
            if (!modal || !contentEl) return;
            const ext = (fileName || '').split('.').pop().toLowerCase();
            const displayName = (title || fileName || 'File').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            titleEl.textContent = displayName;
            subtitleEl.textContent = fileName || '';
            downloadLink.href = filePath;
            downloadLink.download = fileName || 'file';
            downloadLink.style.display = 'inline-flex';
            contentEl.innerHTML = '<div class="flex items-center justify-center h-full min-h-[400px]"><span class="material-symbols-outlined text-6xl text-gray-400 animate-pulse">hourglass_empty</span></div>';
            modal.classList.remove('hidden');
            if (['jpg','jpeg','png','gif','bmp','webp','svg'].includes(ext)) {
                contentEl.innerHTML = `<div class="w-full h-full flex items-center justify-center p-4 bg-gray-50 dark:bg-gray-900 min-h-[400px]"><img src="${filePath.replace(/"/g, '&quot;')}" alt="${displayName}" class="max-w-full max-h-[70vh] object-contain rounded-lg shadow-lg" onerror="this.parentElement.innerHTML=\\'<div class=\\'text-center p-8\\'><span class=\\'material-symbols-outlined text-6xl text-red-400\\'>error</span><p class=\\'text-red-500 mt-2\\'>Failed to load image</p></div>\\'"></div>`;
            } else if (ext === 'pdf') {
                contentEl.innerHTML = `<div class="w-full h-full bg-gray-50 dark:bg-gray-900 min-h-[400px]"><iframe src="${filePath.replace(/"/g, '&quot;')}" class="w-full h-full min-h-[400px] rounded-lg border-0" frameborder="0"></iframe></div>`;
            } else if (['txt'].includes(ext)) {
                fetch(filePath).then(r => r.text()).then(text => {
                    contentEl.innerHTML = `<div class="w-full h-full p-4 bg-gray-50 dark:bg-gray-900 min-h-[400px] overflow-auto"><pre class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap font-sans">${escapeHtml(text)}</pre></div>`;
                }).catch(() => {
                    contentEl.innerHTML = '<div class="flex flex-col items-center justify-center h-full min-h-[400px] p-8"><span class="material-symbols-outlined text-4xl text-gray-400 mb-4">description</span><p class="text-gray-500 dark:text-gray-400 text-center">Preview not available. Use Download to open the file.</p></div>';
                });
            } else {
                contentEl.innerHTML = '<div class="flex flex-col items-center justify-center h-full min-h-[400px] p-8"><span class="material-symbols-outlined text-4xl text-gray-400 mb-4">description</span><p class="text-gray-500 dark:text-gray-400 text-center">Preview not available for this file type. Use Download to open the file.</p></div>';
            }
        }
        function closeFormFileViewer() {
            document.getElementById('form-file-viewer-modal')?.classList.add('hidden');
        }
        document.getElementById('forms-container')?.addEventListener('click', (e) => {
            const btn = e.target.closest('.form-view-file');
            if (btn && btn.dataset.filePath) {
                e.preventDefault();
                openFormFileViewer(btn.dataset.filePath, btn.dataset.fileName || '', btn.dataset.title || '');
            }
        });
        document.getElementById('form-file-viewer-close')?.addEventListener('click', closeFormFileViewer);
        document.getElementById('form-file-viewer-modal')?.addEventListener('click', (e) => { if (e.target.id === 'form-file-viewer-modal') closeFormFileViewer(); });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !document.getElementById('form-file-viewer-modal')?.classList.contains('hidden')) closeFormFileViewer();
        });

        document.getElementById('upload-modal-cancel')?.addEventListener('click', closeUploadModal);

        document.getElementById('upload-forms-modal')?.addEventListener('click', (e) => {
            if (e.target.id === 'upload-forms-modal') closeUploadModal();
        });
        const dropZone = document.getElementById('form-drop-zone');
        const fileInput = document.getElementById('form-file-input');
        const fileNameEl = document.getElementById('form-file-name');
        dropZone?.addEventListener('click', () => fileInput?.click());
        fileInput?.addEventListener('change', () => {
            const f = fileInput.files[0];
            if (f) {
                fileNameEl.textContent = f.name;
                fileNameEl.classList.remove('hidden');
                const titleInput = document.getElementById('form-title');
                if (titleInput && !titleInput.value.trim()) {
                    titleInput.value = f.name.replace(/\.[^/.]+$/, '');
                }
            } else {
                fileNameEl.classList.add('hidden');
            }
        });
        dropZone?.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('border-primary', 'bg-primary/5'); });
        dropZone?.addEventListener('dragleave', () => { dropZone.classList.remove('border-primary', 'bg-primary/5'); });
        dropZone?.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('border-primary', 'bg-primary/5');
            const f = e.dataTransfer?.files[0];
            if (f) {
                const dt = new DataTransfer();
                dt.items.add(f);
                fileInput.files = dt.files;
                fileNameEl.textContent = f.name;
                fileNameEl.classList.remove('hidden');
            }
        });

        document.getElementById('upload-forms-form')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const fd = new FormData(form);
            const btn = document.getElementById('upload-form-submit');
            const origText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent inline-block"></span> Uploading...';
            try {
                const res = await fetch(FORMS_API, { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    closeUploadModal();
                    await loadForms();
                } else {
                    alert(data.error || 'Upload failed');
                }
            } catch (err) {
                alert('Upload failed: ' + (err.message || 'Network error'));
            }
            btn.disabled = false;
            btn.innerHTML = origText;
        });

        loadForms();

        // View toggle (grid / list) – single container, layout changed via CSS
        const viewToggles = document.querySelectorAll('.view-toggle[data-view]');
        const formsContainer = document.getElementById('forms-container');
        viewToggles.forEach(btn => {
            btn.addEventListener('click', () => {
                const view = btn.dataset.view;
                viewToggles.forEach(b => b.classList.toggle('active', b.dataset.view === view));
                formsContainer?.classList.toggle('view-grid', view === 'card');
                formsContainer?.classList.toggle('view-list', view === 'list');
                localStorage.setItem('formsView', view);
            });
        });
        const savedView = localStorage.getItem('formsView');
        if (savedView === 'list') {
            document.querySelector('.view-toggle[data-view="list"]')?.click();
        }
    });
</script>
</body>
</html>
