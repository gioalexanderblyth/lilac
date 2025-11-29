<?php
/**
 * ICONS 2025 Awards Hub
 * Central hub for managing and viewing all ICONS 2025 award evidence
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>ICONS 2025 Awards Hub - LILAC</title>
    <link crossorigin="" href="https://fonts.gstatic.com/" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
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
                        "background-light": "#f1f5f9",
                        "background-dark": "#0f172a",
                        "card-light": "#ffffff",
                        "card-dark": "#1e293b",
                        "text-light": "#0f172a",
                        "text-dark": "#e2e8f0",
                        "text-muted-light": "#64748b",
                        "text-muted-dark": "#94a3b8",
                        "border-light": "#e2e8f0",
                        "border-dark": "#334155",
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"]
                    }
                }
            }
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .material-symbols-outlined.filled {
            font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        
        /* Award card hover effects */
        .award-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .award-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.15);
        }
        
        /* Gradient backgrounds for award categories */
        .gradient-global { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); }
        .gradient-education { background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); }
        .gradient-sustainability { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .gradient-asean { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .gradient-emerging { background: linear-gradient(135deg, #ec4899 0%, #be185d 100%); }
        .gradient-leadership { background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); }
        .gradient-regional { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }
        .gradient-iro { background: linear-gradient(135deg, #84cc16 0%, #65a30d 100%); }
        
        /* Progress ring */
        .progress-ring {
            transform: rotate(-90deg);
        }
        .progress-ring circle {
            transition: stroke-dashoffset 0.5s ease-in-out;
        }
        
        /* Sidebar styles */
        .sidebar {
            width: 16rem;
            min-width: 16rem;
            max-width: 16rem;
            transition: width 0.3s ease;
        }
        .sidebar-collapsed .sidebar {
            width: 5rem;
            min-width: 5rem;
            max-width: 5rem;
        }
        .sidebar-collapsed .sidebar-text { display: none; }
        .sidebar-collapsed .sidebar-logo-text { display: none; }
        .sidebar-collapsed .sidebar-nav-link { justify-content: center; }
        .sidebar-collapsed .sidebar-profile-info { display: none; }
        .sidebar-collapsed .sidebar-profile-picture { display: none; }
        .sidebar-collapsed .sidebar-toggle-container { justify-content: center; }
        .sidebar-collapsed .profile-container { justify-content: center; }
        .sidebar-collapsed .sidebar-toggle-icon-open { display: none; }
        .sidebar-collapsed .sidebar-toggle-icon-closed { display: block; }
        .sidebar-toggle-icon-closed { display: none; }
        
        /* Tab styles */
        .tab-btn.active {
            border-bottom-color: #137fec;
            color: #137fec;
        }
        .tab-btn.active .material-symbols-outlined {
            color: #137fec;
        }
        
        /* AI badge animation */
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 5px rgba(139, 92, 246, 0.5); }
            50% { box-shadow: 0 0 15px rgba(139, 92, 246, 0.8); }
        }
        .ai-badge {
            animation: pulse-glow 2s infinite;
        }
        
        /* Toast animation */
        @keyframes fade-in {
            from { opacity: 0; transform: translate(-50%, 20px); }
            to { opacity: 1; transform: translate(-50%, 0); }
        }
        .animate-fade-in {
            animation: fade-in 0.3s ease-out;
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-text-light dark:text-text-dark">
<div class="flex h-screen sidebar-collapsed" id="app-container">
    <!-- Sidebar -->
    <aside class="sidebar bg-card-light dark:bg-card-dark border-r border-border-light dark:border-border-dark flex flex-col">
        <div class="flex items-center justify-start px-4 h-20 border-b border-border-light dark:border-border-dark">
            <div class="flex items-center gap-3">
                <img alt="CPU LILAC Logo" class="h-11 w-11" src="./api/get-logo.php?v=1" width="32" height="32" onerror="this.style.display='none'; document.getElementById('logo-fallback').style.display='flex';"/>
                <div class="h-11 w-11 bg-primary rounded-lg flex items-center justify-center text-white font-bold text-sm" style="display: none;" id="logo-fallback">CPU</div>
                <h1 class="text-xl font-bold text-text-light dark:text-text-dark sidebar-logo-text hidden">LILAC</h1>
            </div>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2">
            <a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="dashboard.php" title="Dashboard">
                <span class="material-symbols-outlined">dashboard</span>
                <span class="sidebar-text hidden">Dashboard</span>
            </a>
            <a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg bg-gradient-to-r from-purple-500/10 to-blue-500/10 text-primary-600 dark:text-primary-400 font-semibold sidebar-nav-link border border-purple-200 dark:border-purple-800" href="awards-hub.php" title="ICONS 2025 Hub">
                <span class="material-symbols-outlined filled text-purple-600">military_tech</span>
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
            <a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="mou-moa.php" title="MOUs & MOAs">
                <span class="material-symbols-outlined">handshake</span>
                <span class="sidebar-text hidden">MOUs &amp; MOAs</span>
            </a>
            <a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="documents.php" title="Documents">
                <span class="material-symbols-outlined">description</span>
                <span class="sidebar-text hidden">Documents</span>
            </a>
            <a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="trash.php" title="Trash">
                <span class="material-symbols-outlined">delete</span>
                <span class="sidebar-text hidden">Trash</span>
            </a>
        </nav>
        <div class="px-4 py-4 border-t border-border-light dark:border-border-dark">
            <div class="flex items-center justify-between profile-container">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center sidebar-profile-picture hidden">
                        <span class="material-symbols-outlined text-primary">person</span>
                    </div>
                    <div class="sidebar-profile-info hidden">
                        <p class="font-semibold text-text-light dark:text-text-dark"><?php echo htmlspecialchars($user['username'] ?? 'User'); ?></p>
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

    <!-- Main Content -->
    <main class="flex-1 overflow-y-auto">
        <!-- Header -->
        <header class="sticky top-0 bg-background-light/80 dark:bg-background-dark/80 backdrop-blur-sm z-30 px-6 lg:px-8 py-4 border-b border-border-light dark:border-border-dark flex justify-between items-center h-20">
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-500 to-blue-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-white">military_tech</span>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-text-light dark:text-text-dark">ICONS 2025 Awards Hub</h1>
                        <p class="text-sm text-text-muted-light dark:text-text-muted-dark">Internationalization Awards Evidence Management</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="openReportModal()" class="p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-white/10 text-text-muted-light dark:text-text-muted-dark transition-colors" title="Generate Report">
                    <span class="material-symbols-outlined">description</span>
                </button>
                <button onclick="refreshData()" class="p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-white/10 text-text-muted-light dark:text-text-muted-dark transition-colors" title="Refresh">
                    <span class="material-symbols-outlined">refresh</span>
                </button>
                <button id="theme-toggle" class="p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-white/10 text-text-muted-light dark:text-text-muted-dark transition-colors" title="Toggle Theme">
                    <span class="material-symbols-outlined dark:hidden">dark_mode</span>
                    <span class="material-symbols-outlined hidden dark:block">light_mode</span>
                </button>
            </div>
        </header>

        <div class="p-6 lg:p-8">
            <!-- Overview Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-card-light dark:bg-card-dark rounded-xl p-5 border border-border-light dark:border-border-dark">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                            <span class="material-symbols-outlined text-blue-600 dark:text-blue-400">rocket_launch</span>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-text-light dark:text-text-dark" id="stat-ready-to-apply">0</p>
                            <p class="text-sm text-text-muted-light dark:text-text-muted-dark">Awards Ready to Apply</p>
                        </div>
                    </div>
                </div>
                <div class="bg-card-light dark:bg-card-dark rounded-xl p-5 border border-border-light dark:border-border-dark">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                            <span class="material-symbols-outlined text-green-600 dark:text-green-400">work</span>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-text-light dark:text-text-dark" id="stat-in-progress">0</p>
                            <p class="text-sm text-text-muted-light dark:text-text-muted-dark">Awards In Progress</p>
                        </div>
                    </div>
                </div>
                <div class="bg-card-light dark:bg-card-dark rounded-xl p-5 border border-border-light dark:border-border-dark">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                            <span class="material-symbols-outlined text-purple-600 dark:text-purple-400">folder</span>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-text-light dark:text-text-dark" id="stat-total-awards">0</p>
                            <p class="text-sm text-text-muted-light dark:text-text-muted-dark">Total Awards</p>
                        </div>
                    </div>
                </div>
                <div class="bg-card-light dark:bg-card-dark rounded-xl p-5 border border-border-light dark:border-border-dark">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                            <span class="material-symbols-outlined text-amber-600 dark:text-amber-400">trending_up</span>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-text-light dark:text-text-dark" id="stat-avg-readiness">0%</p>
                            <p class="text-sm text-text-muted-light dark:text-text-muted-dark">Avg. Readiness</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Award Categories Section -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-text-light dark:text-text-dark">Award Categories</h2>
                    <div class="relative group">
                        <button class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-text-muted-light dark:text-text-muted-dark transition-colors" title="Award Categories Information">
                            <span class="material-symbols-outlined text-lg">help</span>
                        </button>
                        <div class="absolute right-full top-0 mr-2 w-64 p-3 bg-card-light dark:bg-card-dark rounded-lg shadow-lg border border-border-light dark:border-border-dark opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-10">
                            <p class="text-xs text-text-light dark:text-text-dark">
                                Browse and explore available awards by category. Click on any award card to view detailed requirements and track your application progress.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Category Tabs -->
                <div class="flex gap-2 mb-6 overflow-x-auto pb-2">
                    <button class="category-tab active px-4 py-2 rounded-lg bg-primary text-white font-medium whitespace-nowrap" data-category="all">
                        All Awards
                    </button>
                    <button class="category-tab px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-text-muted-light dark:text-text-muted-dark font-medium whitespace-nowrap hover:bg-gray-200 dark:hover:bg-gray-700" data-category="institutional">
                        Institutional
                    </button>
                    <button class="category-tab px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-text-muted-light dark:text-text-muted-dark font-medium whitespace-nowrap hover:bg-gray-200 dark:hover:bg-gray-700" data-category="individual">
                        Individual
                    </button>
                    <button class="category-tab px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-text-muted-light dark:text-text-muted-dark font-medium whitespace-nowrap hover:bg-gray-200 dark:hover:bg-gray-700" data-category="special">
                        Special
                    </button>
                </div>

                <!-- Awards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="awards-grid">
                    <!-- Award cards will be populated here -->
                    <div class="col-span-full flex items-center justify-center py-12">
                        <div class="flex items-center gap-3 text-text-muted-light dark:text-text-muted-dark">
                            <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Loading awards...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Award Detail Modal -->
<div id="award-detail-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4 hidden">
    <div class="bg-card-light dark:bg-card-dark rounded-2xl max-w-5xl w-full max-h-[90vh] overflow-hidden flex flex-col shadow-2xl">
        <!-- Modal Header -->
        <div id="modal-header" class="p-6 border-b border-border-light dark:border-border-dark">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-4">
                    <div id="modal-icon" class="w-14 h-14 rounded-xl gradient-global flex items-center justify-center">
                        <span class="material-symbols-outlined text-white text-2xl">public</span>
                    </div>
                    <div>
                        <h2 id="modal-title" class="text-xl font-bold text-text-light dark:text-text-dark">Award Title</h2>
                        <p id="modal-subtitle" class="text-sm text-text-muted-light dark:text-text-muted-dark mt-1">Award description</p>
                    </div>
                </div>
                <button onclick="closeAwardModal()" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-text-muted-light dark:text-text-muted-dark">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            
            <!-- Readiness Bar -->
            <div class="mt-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-text-light dark:text-text-dark">Application Readiness</span>
                    <span id="modal-readiness-percent" class="text-sm font-bold text-primary">0%</span>
                </div>
                <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                    <div id="modal-readiness-bar" class="h-full bg-gradient-to-r from-primary to-blue-400 rounded-full transition-all duration-500" style="width: 0%"></div>
                </div>
                <div id="modal-missing-hint" class="text-xs text-amber-600 dark:text-amber-400 mt-2 hidden flex items-center gap-2">
                    <span class="material-symbols-outlined text-xs align-middle">warning</span>
                    <span class="flex-1">Missing: Video (3-min)</span>
                    <button onclick="toggleTabsVisibility()" class="p-1 rounded-lg hover:bg-amber-100 dark:hover:bg-amber-900/30 text-amber-600 dark:text-amber-400 transition-colors" title="Show/Hide tabs">
                        <span id="tabs-toggle-icon" class="material-symbols-outlined text-sm">visibility_off</span>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Modal Tabs -->
        <div id="modal-tabs-container" class="border-b border-border-light dark:border-border-dark bg-gray-50 dark:bg-gray-800/50 hidden">
            <div class="flex overflow-x-auto">
                <button class="tab-btn active px-4 py-3 text-sm font-medium border-b-2 border-primary text-primary flex items-center gap-2 whitespace-nowrap" data-tab="requirements">
                    <span class="material-symbols-outlined text-sm">checklist</span>
                    Requirements
                </button>
                <button class="tab-btn px-4 py-3 text-sm font-medium border-b-2 border-transparent text-text-muted-light dark:text-text-muted-dark hover:text-text-light dark:hover:text-text-dark flex items-center gap-2 whitespace-nowrap" data-tab="all">
                    <span class="material-symbols-outlined text-sm">folder</span>
                    All Evidence
                    <span id="tab-count-all" class="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-700 rounded-full text-xs">0</span>
                </button>
                <button class="tab-btn px-4 py-3 text-sm font-medium border-b-2 border-transparent text-text-muted-light dark:text-text-muted-dark hover:text-text-light dark:hover:text-text-dark flex items-center gap-2 whitespace-nowrap" data-tab="certificates">
                    <span class="material-symbols-outlined text-sm">workspace_premium</span>
                    Certificates
                    <span id="tab-count-certificates" class="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-700 rounded-full text-xs">0</span>
                </button>
                <button class="tab-btn px-4 py-3 text-sm font-medium border-b-2 border-transparent text-text-muted-light dark:text-text-muted-dark hover:text-text-light dark:hover:text-text-dark flex items-center gap-2 whitespace-nowrap" data-tab="mous">
                    <span class="material-symbols-outlined text-sm">handshake</span>
                    MOUs/MOAs
                    <span id="tab-count-mous" class="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-700 rounded-full text-xs">0</span>
                </button>
                <button class="tab-btn px-4 py-3 text-sm font-medium border-b-2 border-transparent text-text-muted-light dark:text-text-muted-dark hover:text-text-light dark:hover:text-text-dark flex items-center gap-2 whitespace-nowrap" data-tab="events">
                    <span class="material-symbols-outlined text-sm">event</span>
                    Events
                    <span id="tab-count-events" class="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-700 rounded-full text-xs">0</span>
                </button>
                <button class="tab-btn px-4 py-3 text-sm font-medium border-b-2 border-transparent text-text-muted-light dark:text-text-muted-dark hover:text-text-light dark:hover:text-text-dark flex items-center gap-2 whitespace-nowrap" data-tab="documents">
                    <span class="material-symbols-outlined text-sm">description</span>
                    Documents
                    <span id="tab-count-documents" class="px-1.5 py-0.5 bg-gray-200 dark:bg-gray-700 rounded-full text-xs">0</span>
                </button>
                <button class="tab-btn px-4 py-3 text-sm font-medium border-b-2 border-transparent text-text-muted-light dark:text-text-muted-dark hover:text-text-light dark:hover:text-text-dark flex items-center gap-2 whitespace-nowrap" data-tab="ched-guidelines">
                    <span class="material-symbols-outlined text-sm">menu_book</span>
                    CHED Guidelines
                </button>
            </div>
        </div>
        
        <!-- Modal Content -->
        <div id="modal-content" class="flex-1 overflow-y-auto p-6">
            <!-- Evidence list will be populated here -->
        </div>
        
        <!-- Modal Footer -->
        <div class="p-4 border-t border-border-light dark:border-border-dark flex justify-end items-center bg-gray-50 dark:bg-gray-800/50">
            <button onclick="closeAwardModal()" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-text-light dark:text-text-dark rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Report Selection Modal -->
<div id="report-selection-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4 hidden">
    <div class="bg-card-light dark:bg-card-dark rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col shadow-2xl">
        <div class="p-6 border-b border-border-light dark:border-border-dark">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-lg bg-amber-500 flex items-center justify-center">
                        <span class="material-symbols-outlined text-white text-xl">description</span>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-text-light dark:text-text-dark">Generate Awards Report</h2>
                        <p class="text-sm text-text-muted-light dark:text-text-muted-dark">Select awards to include in the report</p>
                    </div>
                </div>
                <button onclick="closeReportSelectionModal()" class="p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-white/10 text-text-muted-light dark:text-text-muted-dark">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
        </div>
        
        <div class="p-6 overflow-y-auto flex-1">
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <button onclick="selectAllAwards()" class="px-3 py-1.5 text-sm bg-primary/10 text-primary rounded-lg hover:bg-primary/20 transition-colors">
                        Select All
                    </button>
                    <button onclick="deselectAllAwards()" class="px-3 py-1.5 text-sm bg-gray-200 dark:bg-gray-700 text-text-light dark:text-text-dark rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                        Deselect All
                    </button>
                </div>
                <p class="text-sm text-text-muted-light dark:text-text-muted-dark">
                    <span id="selected-count">0</span> selected
                </p>
            </div>
            
            <div id="award-selection-list" class="space-y-2">
                <!-- Award checkboxes will be populated here -->
            </div>
        </div>
        
        <div class="p-6 border-t border-border-light dark:border-border-dark flex items-center justify-end gap-3">
            <button onclick="closeReportSelectionModal()" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-text-light dark:text-text-dark rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                Cancel
            </button>
            <button onclick="generateReport()" class="px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">print</span>
                Generate Report
            </button>
        </div>
    </div>
</div>

<!-- Notification Modal -->
<div id="notification-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-[60] p-4 hidden">
    <div class="bg-card-light dark:bg-card-dark rounded-2xl max-w-md w-full shadow-2xl transform transition-all">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div id="notification-icon" class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-white text-xl"></span>
                </div>
                <div class="flex-1">
                    <h3 id="notification-title" class="text-lg font-bold text-text-light dark:text-text-dark mb-1"></h3>
                    <p id="notification-message" class="text-sm text-text-muted-light dark:text-text-muted-dark"></p>
                </div>
            </div>
        </div>
        <div class="px-6 pb-6">
            <button onclick="closeNotificationModal()" class="w-full px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors font-medium">
                OK
            </button>
        </div>
    </div>
</div>

<!-- Report View -->
<div id="report-view" class="hidden">
    <div class="min-h-screen bg-white p-8">
        <div class="max-w-5xl mx-auto">
            <!-- Report Header -->
            <div class="mb-8 text-center border-b-2 border-gray-300 pb-6">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">ICONS 2025 Awards Report</h1>
                <p class="text-lg text-gray-600">Internationalization Awards Evidence Management</p>
                <p class="text-sm text-gray-500 mt-2">Generated on: <span id="report-date"></span></p>
            </div>
            
            <!-- Report Content -->
            <div id="report-content">
                <!-- Award reports will be populated here -->
            </div>
            
            <!-- Report Footer -->
            <div class="mt-12 pt-6 border-t border-gray-300 text-center text-sm text-gray-500">
                <p>This report was generated from the LILAC Awards System</p>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #report-view, #report-view * {
        visibility: visible;
    }
    #report-view {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    .no-print {
        display: none !important;
    }
    @page {
        margin: 1.5cm;
    }
}
</style>

<script>
// Award definitions with metadata
const AWARDS_CONFIG = [
    // Institutional Awards
    {
        id: 'global-citizenship',
        title: 'Global Citizenship Award',
        description: 'Fostering responsible global leaders and intercultural understanding',
        category: 'institutional',
        icon: 'public',
        gradient: 'gradient-global',
        keywords: ['global citizenship', 'intercultural', 'global awareness', 'cultural understanding', 'community engagement', 'SDG', 'sustainable development'],
        href: 'awards/global-citizenship-award.php'
    },
    {
        id: 'international-education',
        title: 'Outstanding International Education Program',
        description: 'Excellence in international education and inclusive programs',
        category: 'institutional',
        icon: 'school',
        gradient: 'gradient-education',
        keywords: ['international education', 'exchange program', 'student mobility', 'inclusive', 'academic partnership', 'curriculum'],
        href: 'awards/outstanding-international-education-award.php'
    },
    {
        id: 'sustainability',
        title: 'Sustainability Award',
        description: 'Outstanding commitment to UN SDGs and environmental sustainability',
        category: 'institutional',
        icon: 'eco',
        gradient: 'gradient-sustainability',
        keywords: ['sustainability', 'SDG', 'environment', 'green campus', 'climate', 'renewable', 'eco-friendly'],
        href: 'awards/sustainability-award.php'
    },
    {
        id: 'asean-awareness',
        title: 'Best ASEAN Awareness Initiative',
        description: 'Promoting ASEAN identity, solidarity, and regional cooperation',
        category: 'institutional',
        icon: 'groups',
        gradient: 'gradient-asean',
        keywords: ['ASEAN', 'Southeast Asia', 'regional cooperation', 'cultural exchange', 'ASEAN community'],
        href: 'awards/best-asean-awareness-award.php'
    },
    // Individual Awards
    {
        id: 'emerging-leadership',
        title: 'Emerging Leadership Award',
        description: 'Rising stars in internationalization leadership',
        category: 'individual',
        icon: 'trending_up',
        gradient: 'gradient-emerging',
        keywords: ['emerging leader', 'young leader', 'mentorship', 'innovation', 'leadership growth'],
        href: 'awards/emerging-leadership-award.php'
    },
    {
        id: 'izn-leadership',
        title: 'Internationalization Leadership Award',
        description: 'Strategic, ethical, and sustained leadership excellence',
        category: 'individual',
        icon: 'military_tech',
        gradient: 'gradient-leadership',
        keywords: ['strategic vision', 'governance', 'institutional leadership', 'ethical leadership', 'global excellence'],
        href: 'awards/internationalization-leadership-award.php'
    },
    // Special Awards
    {
        id: 'ched-regional',
        title: 'Best CHED Regional Office',
        description: 'Excellence in regional internationalization promotion',
        category: 'special',
        icon: 'apartment',
        gradient: 'gradient-regional',
        keywords: ['CHED', 'regional office', 'policy', 'coordination', 'governance'],
        href: 'awards/best-ched-regional-office-award.php'
    },
    {
        id: 'iro-community',
        title: 'Most Promising IRO Community',
        description: 'Outstanding collaboration in regional IRO networks',
        category: 'special',
        icon: 'diversity_3',
        gradient: 'gradient-iro',
        keywords: ['IRO network', 'collaboration', 'regional partnership', 'community'],
        href: 'awards/most-promising-iro-community-award.php'
    }
];

// ICONS 2025 Requirements from CHED Briefer
const AWARDS_REQUIREMENTS = {
    'global-citizenship': {
        title: 'Global Citizenship Award',
        videoDuration: '3 minutes',
        implementationPeriod: 'CY 2023-2024',
        documentaryRequirements: [
            { id: 'nomination-form', label: 'Accomplished Nomination Form', required: true, autoDetect: 'form' },
            { id: 'video', label: '3-Minute Video (answering guide questions)', required: true, autoDetect: 'video' },
            { id: 'supporting-docs', label: 'Supporting Documents (max 10)', required: true, maxCount: 10, autoDetect: 'documents' },
            { id: 'mou-agreement', label: 'Signed MOU/MOA or Partnership Agreement (if applicable)', required: false, autoDetect: 'mou' }
        ],
        guideQuestions: [
            'How did your institution, through this initiative, demonstrate a strong commitment to fostering global citizenship among students, faculty, and staff?',
            'How did your institution actively engage with local and global communities to address global challenges and foster partnerships?',
            'What instances during the initiative\'s implementation did your institution show leadership, innovation, and impact in addressing global issues or advancing sustainable development goals?',
            'How did your institution assess the impact of its global citizenship initiatives and measure the development of students\' global competencies?'
        ],
        eligibilityCriteria: [
            { id: 'intercultural', label: 'Ignite Intercultural Understanding', description: 'Creates inclusive learning experiences fostering mutual respect across cultures, backgrounds, and abilities' },
            { id: 'changemakers', label: 'Empower Changemakers', description: 'Students gain knowledge/skills to tackle challenges aligned with the SDGs' },
            { id: 'engagement', label: 'Cultivate Active Engagement', description: 'Provides accessible platforms for students to translate global awareness into concrete action' }
        ]
    },
    'international-education': {
        title: 'Outstanding International Education Program Award',
        videoDuration: '3 minutes',
        implementationPeriod: 'CY 2023-2024',
        documentaryRequirements: [
            { id: 'nomination-form', label: 'Accomplished Nomination Form', required: true, autoDetect: 'form' },
            { id: 'video', label: '3-Minute Video (answering guide questions)', required: true, autoDetect: 'video' },
            { id: 'supporting-docs', label: 'Supporting Documents (max 10)', required: true, maxCount: 10, autoDetect: 'documents' },
            { id: 'mou-agreement', label: 'Signed MOU/MOA or Partnership Agreement (if applicable)', required: false, autoDetect: 'mou' }
        ],
        guideQuestions: [
            'How did this initiative showcase your institution\'s priority towards inclusive internationalization efforts?',
            'How did it facilitate the participation of students from diverse backgrounds?',
            'How did it help your institution integrate indigenous perspectives into international education programs?',
            'How did this program positively impact students\' learning and development? Share success stories that capture the outcomes of this initiative.'
        ],
        eligibilityCriteria: [
            { id: 'access', label: 'Expand Access to Global Opportunities', description: 'Break down barriers to include students from various backgrounds, abilities, and financial situations' },
            { id: 'innovation', label: 'Foster Collaborative Innovation', description: 'Partnerships with local and international partners fuel innovative, culturally-rich experiences' },
            { id: 'inclusivity', label: 'Embrace Inclusivity and Beyond', description: 'Actively dismantle barriers so international education benefits everyone' }
        ]
    },
    'sustainability': {
        title: 'Sustainability Award',
        videoDuration: '3 minutes',
        implementationPeriod: 'CY 2023-2024',
        documentaryRequirements: [
            { id: 'nomination-form', label: 'Accomplished Nomination Form', required: true, autoDetect: 'form' },
            { id: 'video', label: '3-Minute Video (answering guide questions)', required: true, autoDetect: 'video' },
            { id: 'supporting-docs', label: 'Supporting Documents (max 10)', required: true, maxCount: 10, autoDetect: 'documents' },
            { id: 'mou-agreement', label: 'Signed MOU/MOA or Partnership Agreement (if applicable)', required: false, autoDetect: 'mou' }
        ],
        guideQuestions: [
            'What specific SDG/SDGs are being addressed by this initiative? Describe the project/initiative and what measurable results or outcomes it has achieved in key areas (e.g., energy saved, waste diverted, community members reached).',
            'How has this initiative been integrated into the institution\'s core functions (i.e., operations, curriculum, and community engagement)?',
            'What evidence demonstrates the initiative\'s long-term viability and potential for expansion (scalability)? What is the plan to sustain it beyond the initial project period?'
        ],
        eligibilityCriteria: [
            { id: 'integration', label: 'Pioneering Integration', description: 'Strategically integrated UN SDGs across institutional operations, curriculum, and community engagement' },
            { id: 'impact', label: 'Impactful Projects', description: 'Implemented measurable projects in energy efficiency, waste management, and social well-being aligned with UN SDGs' },
            { id: 'commitment', label: 'Long-Term Commitment', description: 'Demonstrates scalability, financial viability, and institutional commitment for continued positive impact' }
        ]
    },
    'asean-awareness': {
        title: 'Best ASEAN Awareness Initiative Award',
        videoDuration: '3 minutes',
        implementationPeriod: 'August 2025 (ASEAN Month)',
        documentaryRequirements: [
            { id: 'nomination-form', label: 'Accomplished Nomination Form', required: true, autoDetect: 'form' },
            { id: 'video', label: '3-Minute Video (answering guide questions)', required: true, autoDetect: 'video' },
            { id: 'supporting-docs', label: 'Supporting Documents (max 10)', required: true, maxCount: 10, autoDetect: 'documents' },
            { id: 'mou-agreement', label: 'Signed MOU/MOA or Partnership Agreement (if applicable)', required: false, autoDetect: 'mou' }
        ],
        guideQuestions: [
            'How did the initiative creatively advance ASEAN awareness and solidarity? Describe the specific activities (e.g., cultural events, curriculum changes, youth dialogues) used to cultivate a sense of shared ASEAN identity.',
            'What is the impact of your cross-cultural programs (exchanges, collaborations) on fostering genuine intercultural exchange? How did these activities specifically contribute to preparing students for an integrated ASEAN community?',
            'What are the measurable results and evidence of the initiative\'s reach and impact? (e.g., participation numbers, survey results, new partnerships created).',
            'What is the specific plan to \'level-up\' or improve the program\'s scope and quality for the succeeding years?'
        ],
        eligibilityCriteria: [
            { id: 'identity', label: 'Promote Regional Identity and Solidarity', description: 'Advance ASEAN awareness through creative activities like curriculum integration, cultural festivals, or youth dialogues' },
            { id: 'crosscultural', label: 'Cross-Cultural Initiative Programs', description: 'Execute impactful exchange and collaboration programs fostering genuine intercultural exchange' },
            { id: 'outreach', label: 'Measurable Outreach and Sustained Commitment', description: 'Clear evidence of reach and impact with long-term commitment to promoting ASEAN narrative' }
        ]
    },
    'emerging-leadership': {
        title: 'Emerging Leadership Award',
        videoDuration: '3 minutes',
        implementationPeriod: 'At least 2 academic years in position',
        documentaryRequirements: [
            { id: 'nomination-form', label: 'Accomplished Nomination Form', required: true, autoDetect: 'form' },
            { id: 'video', label: '3-Minute Video (interview format)', required: true, autoDetect: 'video' },
            { id: 'supporting-docs', label: 'Supporting Documents (max 10)', required: true, maxCount: 10, autoDetect: 'documents' },
            { id: 'mou-agreement', label: 'Signed MOU/MOA or Partnership Agreement (if applicable)', required: false, autoDetect: 'mou' }
        ],
        guideQuestions: [
            'Describe a specific initiative you spearheaded that demonstrates a creative approach to internationalization. How did it foster global collaboration and enrich the student experience at your institution?',
            'Explain how your vision and leadership have contributed to the promotion of diversity, equity, and accessibility within your department and/or institution. Quantify the impact whenever possible.',
            'Describe how you have served as a mentor or advocate for the next generation of international education leaders. How have you helped cultivate future leaders in the field?'
        ],
        eligibilityCriteria: [
            { id: 'innovation', label: 'Practice Innovation', description: 'Spearhead creative approaches to internationalization, fostering global collaboration' },
            { id: 'growth', label: 'Drive Strategic and Inclusive Growth', description: 'Promote diversity, equity, and accessibility, expanding access and impact' },
            { id: 'mentorship', label: 'Empower Others', description: 'Serve as mentors and advocates, cultivating future leaders' }
        ],
        notes: [
            'Nominee must be Director, Manager, Supervisor, Officer, or equivalent in Internationalization Department',
            'Must have held position for at least 2 academic years',
            'Video must be in interview format (nominator interviewing nominee)',
            'Self-nominations are NOT allowed'
        ]
    },
    'izn-leadership': {
        title: 'Internationalization Leadership Award',
        videoDuration: '5 minutes',
        implementationPeriod: 'At least 3 academic years in executive position',
        documentaryRequirements: [
            { id: 'nomination-form', label: 'Accomplished Nomination Form', required: true, autoDetect: 'form' },
            { id: 'video', label: '5-Minute Video (interview format)', required: true, autoDetect: 'video' },
            { id: 'supporting-docs', label: 'Supporting Documents (max 10)', required: true, maxCount: 10, autoDetect: 'documents' },
            { id: 'mou-agreement', label: 'Signed MOU/MOA or Partnership Agreement (if applicable)', required: false, autoDetect: 'mou' }
        ],
        guideQuestions: [
            'Describe the institutional strategy you spearheaded to comprehensively integrate international and intercultural dimensions. Provide a concrete example and explain how it transformed your institution\'s approach.',
            'Provide specific evidence of your commitment to ethical and inclusive leadership in internationalization. Share an instance of integrity and ethical decision-making. Describe your management system for ensuring responsible fiscal management.',
            'Explain how your leadership has built a foundation for sustained impact and development. How have you fostered a culture of continuous improvement and lifelong learning?'
        ],
        eligibilityCriteria: [
            { id: 'vision', label: 'Strategic Vision and Integration', description: 'Champion bold innovation driving comprehensive integration of international dimensions' },
            { id: 'ethics', label: 'Ethical Leadership and Governance', description: 'Exemplify integrity, ethical decision-making, and responsible fiscal management' },
            { id: 'impact', label: 'Sustained Impact and Development', description: 'Foster culture of continuous improvement and lifelong learning' }
        ],
        notes: [
            'Nominee must be President, Vice-President, or equivalent executive position',
            'Must have held position for at least 3 academic years',
            'Video must be in interview format (nominator interviewing nominee)',
            'Self-nominations are NOT allowed'
        ]
    },
    'ched-regional': {
        title: 'Best CHED Regional Office for Internationalization Award',
        videoDuration: '3 minutes',
        implementationPeriod: 'July 2024 to July 2025',
        documentaryRequirements: [
            { id: 'nomination-form', label: 'Accomplished Nomination Form', required: true, autoDetect: 'form' },
            { id: 'video', label: '3-Minute Video (any format)', required: true, autoDetect: 'video' },
            { id: 'supporting-docs', label: 'Supporting Documents (max 10)', required: true, maxCount: 10, autoDetect: 'documents' },
            { id: 'mou-agreement', label: 'Signed MOU/MOA or Partnership Agreement (if applicable)', required: false, autoDetect: 'mou' }
        ],
        guideQuestions: [
            'Provide specific examples of how your office\'s internationalization initiatives have demonstrably increased access and participation for students and faculty from underrepresented groups and fostered intercultural understanding.',
            'Highlight a key event or activity between July 2024 and July 2025 that your region hosted in collaboration with the CHED IAS. How did this collaboration benefit the HEIs in your region?',
            'How does your office maintain a system for operational excellence and governance in internationalization? Detail the procedures for timely submission of mandated reports.',
            'Beyond anecdotal evidence, what are the measurable results of your internationalization initiatives? What are your plans for expanding and sustaining these efforts?'
        ],
        eligibilityCriteria: [
            { id: 'comprehensive', label: 'Comprehensive Internationalization Efforts', description: 'Overall strategy embedding international dimension across regional HEIs with focus on sustainability and inclusivity' },
            { id: 'cooperation', label: 'Cooperation and Collaboration', description: 'Partnership with CHED IAS, proactive execution of national programs, effective communication' },
            { id: 'excellence', label: 'Operational Excellence and Compliance', description: 'High standards of governance, timely submission of mandated reports' },
            { id: 'measurable', label: 'Measurable Impact', description: 'Demonstrated impact through rankings, enrollment data, exchange programs, or survey results' }
        ]
    },
    'iro-community': {
        title: 'Most Promising Regional IRO Community Award',
        videoDuration: '3 minutes',
        implementationPeriod: 'Current evaluation period',
        documentaryRequirements: [
            { id: 'nomination-form', label: 'Accomplished Nomination Form', required: true, autoDetect: 'form' },
            { id: 'video', label: '3-Minute Video (any format)', required: true, autoDetect: 'video' },
            { id: 'supporting-docs', label: 'Supporting Documents (max 10)', required: true, maxCount: 10, autoDetect: 'documents' },
            { id: 'mou-agreement', label: 'Signed MOU/MOA or Partnership Agreement (if applicable)', required: false, autoDetect: 'mou' }
        ],
        guideQuestions: [
            'Describe the core vision and organizational structure of your Regional IRO Community. What specific regional challenges or needs in internationalization does your community aim to address?',
            'What is the most successful collaborative initiative or activity implemented by the community during the evaluation period? Detail the mechanism of collaboration between member HEIs and/or the CHED Regional Office.',
            'What is the most promising future initiative or project planned for the remaining months of 2025? How about for 2026? Describe how this will advance regional IZN capacity.'
        ],
        eligibilityCriteria: [
            { id: 'vision', label: 'Vision and Strategic Plan', description: 'Clear, compelling vision and organizational structure addressing specific regional needs' },
            { id: 'progress', label: 'Early Progress and Collaboration', description: 'Successfully implemented at least one high-impact collaborative activity' },
            { id: 'future', label: 'Promising Future and Value', description: 'Promising plan for initiative creating exceptional value for member HEIs' }
        ]
    }
};

let currentAwardId = null;
let allEvidence = {
    certificates: [],
    mous: [],
    events: [],
    documents: []
};
let awardStats = {};

function ensureStatsStructure(rawStats) {
    const stats = rawStats || {};
    stats.total = stats.total || 0;
    stats.eligible = stats.eligible || 0;
    stats.readiness = stats.readiness || 0;
    stats.aiDetected = stats.aiDetected || 0;
    stats.certificates = Array.isArray(stats.certificates) ? stats.certificates : [];
    stats.documents = Array.isArray(stats.documents) ? stats.documents : [];
    stats.events = Array.isArray(stats.events) ? stats.events : [];
    stats.mous = Array.isArray(stats.mous) ? stats.mous : [];
    return stats;
}

function getAwardStatsById(awardId) {
    awardStats[awardId] = ensureStatsStructure(awardStats[awardId]);
    return awardStats[awardId];
}

// Initialize
document.addEventListener('DOMContentLoaded', async function() {
    initSidebar();
    initTheme();
    initCategoryTabs();
    initModalTabs();
    await loadAllData();
    renderAwardsGrid('all');
});

// Sidebar toggle
function initSidebar() {
    const container = document.getElementById('app-container');
    const toggle = document.getElementById('sidebar-toggle');
    const savedState = localStorage.getItem('sidebarCollapsed');
    
    if (savedState === 'false') {
        container.classList.remove('sidebar-collapsed');
    }
    
    toggle?.addEventListener('click', () => {
        container.classList.toggle('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', container.classList.contains('sidebar-collapsed'));
    });
}

// Theme toggle
function initTheme() {
    const themeToggle = document.getElementById('theme-toggle');
    themeToggle?.addEventListener('click', () => {
        document.documentElement.classList.toggle('dark');
        localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
    });
}

// Category tabs
function initCategoryTabs() {
    document.querySelectorAll('.category-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.category-tab').forEach(t => {
                t.classList.remove('active', 'bg-primary', 'text-white');
                t.classList.add('bg-gray-100', 'dark:bg-gray-800', 'text-text-muted-light', 'dark:text-text-muted-dark');
            });
            tab.classList.add('active', 'bg-primary', 'text-white');
            tab.classList.remove('bg-gray-100', 'dark:bg-gray-800', 'text-text-muted-light', 'dark:text-text-muted-dark');
            renderAwardsGrid(tab.dataset.category);
        });
    });
}

// Modal tabs
function initModalTabs() {
    document.querySelectorAll('.tab-btn').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(t => {
                t.classList.remove('active', 'border-primary', 'text-primary');
                t.classList.add('border-transparent', 'text-text-muted-light', 'dark:text-text-muted-dark');
            });
            tab.classList.add('active', 'border-primary', 'text-primary');
            tab.classList.remove('border-transparent', 'text-text-muted-light', 'dark:text-text-muted-dark');
            renderModalContent(tab.dataset.tab);
        });
    });
}

// Load all data
async function loadAllData() {
    try {
        // Load certificates/awards - from awards page
        // These are files uploaded from the awards page and eligibility is determined by match score
        try {
            const awardsRes = await fetch('api/awards.php?action=list');
            if (awardsRes.ok) {
                const awardsData = await awardsRes.json();
                allEvidence.certificates = Array.isArray(awardsData) ? awardsData : [];
            }
        } catch (e) {
            console.warn('Failed to load awards:', e);
            allEvidence.certificates = [];
        }
        
        // Load MOUs/MOAs - from MOU/MOA page
        // These are files uploaded from the MOU/MOA page
        try {
            const mouRes = await fetch('api/mou-moa.php?action=list');
            if (mouRes.ok) {
                const mouData = await mouRes.json();
                allEvidence.mous = Array.isArray(mouData) ? mouData : (mouData.data || []);
            }
        } catch (e) {
            console.warn('Failed to load MOUs:', e);
            allEvidence.mous = [];
        }
        
        // Load Events - from events page
        // These are events created from the events/activities page
        try {
            const eventsRes = await fetch('api/events.php?action=list');
            if (eventsRes.ok) {
                const eventsData = await eventsRes.json();
                // Events API returns { success: true, events: [...] }
                allEvidence.events = eventsData.events || eventsData.data || (Array.isArray(eventsData) ? eventsData : []);
            }
        } catch (e) {
            console.warn('Failed to load events:', e);
            allEvidence.events = [];
        }
        
        // Load Other Documents - from documents page
        // Exclude MOU/MOA documents (they should only appear in MOU/MOA tab)
        // Check category, title, and file_name for MOU/MOA indicators
        try {
            const docsRes = await fetch('api/other-documents.php?action=list');
            if (docsRes.ok) {
                const docsData = await docsRes.json();
                const allDocs = docsData.data || (Array.isArray(docsData) ? docsData : []);
                // Filter out MOU/MOA documents - check category, title, and file_name
                allEvidence.documents = allDocs.filter(doc => {
                    const category = (doc.category || '').toUpperCase();
                    const title = (doc.title || '').toUpperCase();
                    const fileName = (doc.file_name || '').toUpperCase();
                    // Exclude if MOU or MOA appears in category, title, or file_name
                    return !category.includes('MOU') && !category.includes('MOA') &&
                           !title.includes('MOU') && !title.includes('MOA') &&
                           !fileName.includes('MOU') && !fileName.includes('MOA');
                });
            }
        } catch (e) {
            console.warn('Failed to load documents:', e);
            allEvidence.documents = [];
        }
        
        // Calculate stats for each award
        calculateAwardStats();
        updateOverviewStats();
        
    } catch (error) {
        console.error('Error loading data:', error);
    }
}

// Calculate stats for each award based on keyword matching
function calculateAwardStats() {
    AWARDS_CONFIG.forEach(award => {
        const stats = {
            total: 0,
            eligible: 0,
            aiDetected: 0,
            certificates: [],
            mous: [],
            events: [],
            documents: []
        };
        
        // Process certificates - from awards page
        // Include ALL files, calculate match score to determine eligibility for this award
        allEvidence.certificates.forEach(cert => {
            const text = `${cert.title || ''} ${cert.description || ''}`.toLowerCase();
            const matchScore = calculateMatchScore(text, award.keywords);
            // Include all files, but still calculate match score for display and eligibility
            stats.certificates.push({ ...cert, matchScore, aiDetected: matchScore >= 30 });
            stats.total++;
            if (matchScore >= 30) stats.aiDetected++;
            if (matchScore >= 70) stats.eligible++;
        });
        
        // Process MOUs - from MOU/MOA page
        // Include ALL files regardless of match score
        allEvidence.mous.forEach(mou => {
            const text = `${mou.title || ''} ${mou.institution || ''} ${mou.description || ''}`.toLowerCase();
            const matchScore = calculateMatchScore(text, award.keywords);
            // Include all files, but still calculate match score for display
            stats.mous.push({ ...mou, matchScore, aiDetected: matchScore >= 30 });
            stats.total++;
            if (matchScore >= 30) stats.aiDetected++;
            if (matchScore >= 70) stats.eligible++;
        });
        
        // Process Events - from events page
        // Include ALL files regardless of match score
        allEvidence.events.forEach(event => {
            const text = `${event.title || ''} ${event.description || ''} ${event.location || ''}`.toLowerCase();
            const matchScore = calculateMatchScore(text, award.keywords);
            // Include all files, but still calculate match score for display
            stats.events.push({ ...event, matchScore, aiDetected: matchScore >= 30 });
            stats.total++;
            if (matchScore >= 30) stats.aiDetected++;
            if (matchScore >= 70) stats.eligible++;
        });
        
        // Process Documents - from documents page (excluding MOU/MOA)
        // Include ALL files regardless of match score
        allEvidence.documents.forEach(doc => {
            const text = `${doc.title || ''} ${doc.description || ''} ${doc.category || ''}`.toLowerCase();
            const matchScore = calculateMatchScore(text, award.keywords);
            // Include all files, but still calculate match score for display
            stats.documents.push({ ...doc, matchScore, aiDetected: matchScore >= 30 });
            stats.total++;
            if (matchScore >= 30) stats.aiDetected++;
            if (matchScore >= 70) stats.eligible++;
        });
        
        // Calculate readiness percentage based on requirements and criteria
        stats.readiness = calculateAwardReadiness(award.id, stats);
        
        awardStats[award.id] = stats;
    });
}

// Simple keyword matching
function calculateMatchScore(text, keywords) {
    let matches = 0;
    keywords.forEach(keyword => {
        if (text.includes(keyword.toLowerCase())) {
            matches++;
        }
    });
    return Math.round((matches / keywords.length) * 100);
}

// ============================
// Requirement Auto-detection Helpers
// ============================
const VIDEO_EXTENSIONS = ['.mp4', '.mov', '.wmv', '.avi', '.mkv', '.webm', '.m4v'];

function getCombinedEvidenceList(stats, key) {
    const fromStats = (stats && stats[key]) ? stats[key] : [];
    const fromAll = (allEvidence && allEvidence[key]) ? allEvidence[key] : [];
    return [...fromStats, ...fromAll];
}

function hasDocumentKeywords(doc, keywords) {
    const text = `${doc.title || ''} ${doc.description || ''} ${doc.category || ''}`.toLowerCase();
    return keywords.some(keyword => text.includes(keyword));
}

function eventHasVideoAttachment(event) {
    const path = (event?.document_path || event?.file_path || event?.video_url || event?.image_path || '').toLowerCase();
    if (!path) return false;
    return VIDEO_EXTENSIONS.some(ext => path.endsWith(ext));
}

function getRequirementAutoStatus(req, stats) {
    const normalizedStats = ensureStatsStructure(stats);
    const detectType = (req.autoDetect || req.id || '').toLowerCase();
    const status = { autoChecked: false, detail: '' };
    
    const documentSources = [
        ...(normalizedStats.documents || []),
        ...(normalizedStats.certificates || [])
    ];
    const mouSources = normalizedStats.mous || [];
    const eventSources = normalizedStats.events || [];
    
    if (detectType.includes('supporting') || detectType === 'documents' || detectType === 'supporting-docs') {
        status.autoChecked = documentSources.length > 0;
        if (status.autoChecked) {
            status.detail = `${documentSources.length} document(s) found`;
        }
        return status;
    }
    
    if (detectType.includes('video')) {
        const videoEvents = eventSources.filter(eventHasVideoAttachment);
        if (videoEvents.length > 0) {
            status.autoChecked = true;
            status.detail = `${videoEvents.length} video event(s) detected`;
        } else if (eventSources.length > 0) {
            status.autoChecked = true;
            status.detail = `${eventSources.length} event(s) available`;
        }
        return status;
    }
    
    if (detectType.includes('mou') || detectType.includes('moa') || detectType.includes('partnership')) {
        status.autoChecked = mouSources.length > 0;
        if (status.autoChecked) {
            status.detail = `${mouSources.length} MOU/MOA record(s) found`;
        }
        return status;
    }
    
    if (detectType.includes('form') || detectType.includes('nomination')) {
        const formDocs = documentSources.filter(doc => hasDocumentKeywords(doc, ['nomination', 'form', 'application']));
        status.autoChecked = formDocs.length > 0;
        if (status.autoChecked) {
            status.detail = `${formDocs.length} nomination form(s) detected`;
        }
        return status;
    }
    
    return status;
}

function isRequirementAutoMet(req, stats) {
    return getRequirementAutoStatus(req, stats).autoChecked;
}

// Check if a requirement is automatically met based on available data
// Calculate award readiness percentage based on requirements and criteria
function calculateAwardReadiness(awardId, stats) {
    const requirements = AWARDS_REQUIREMENTS[awardId];
    const normalizedStats = ensureStatsStructure(stats);
    const state = getChecklistState(awardId);
    
    if (!requirements) {
        // Fallback to evidence-based calculation if no requirements
        return Math.min(100, Math.round((normalizedStats.total / 5) * 100));
    }
    
    // Calculate documentary requirements completion (60% weight)
    let docScore = 0;
    let docPossible = 0;
    const docRequirements = requirements.documentaryRequirements;
    docRequirements.forEach(req => {
        const isRequired = req.required !== false;
        if (!isRequired) {
            return;
        }
        docPossible += 1;
        const isChecked = state[req.id] || false;
        const autoChecked = isRequirementAutoMet(req, normalizedStats);
        if (isChecked || autoChecked) {
            docScore += 1;
        }
    });
    const docPercentage = docPossible > 0 ? (docScore / docPossible) * 60 : 0;
    
    // Calculate evidence matching (40% weight)
    // Check both manual selection and automatic matching
    let evidenceScore = 0;
    const criteriaCount = requirements.eligibilityCriteria.length;
    if (criteriaCount > 0) {
        requirements.eligibilityCriteria.forEach(criteria => {
            const criteriaKey = `criteria-${criteria.id}`;
            const isManuallyChecked = state[criteriaKey] || false;
            const matchCount = countCriteriaMatches(criteria.id, normalizedStats);
            const hasAutoMatch = matchCount > 0;
            
            // Criteria is met if manually checked OR automatically matched
            if (isManuallyChecked || hasAutoMatch) {
                evidenceScore += 1;
            }
        });
    }
    const evidencePercentage = criteriaCount > 0 ? (evidenceScore / criteriaCount) * 40 : 0;
    
    // Total readiness
    return Math.round(docPercentage + evidencePercentage);
}

// Check if an award has fully completed all requirements and criteria
function isAwardFullyCompleted(awardId) {
    const requirements = AWARDS_REQUIREMENTS[awardId];
    const stats = awardStats[awardId] || { total: 0, certificates: [], documents: [], events: [], mous: [] };
    const state = getChecklistState(awardId);
    
    if (!requirements) return false;
    
    // Check all documentary requirements are completed
    const docRequirements = requirements.documentaryRequirements;
    let allDocsCompleted = true;
    
    docRequirements.forEach(req => {
        if (req.required === false) {
            return;
        }
        const isChecked = state[req.id] || false;
        const autoChecked = isRequirementAutoMet(req, stats);
        if (!isChecked && !autoChecked) {
            allDocsCompleted = false;
        }
    });
    
    if (!allDocsCompleted) return false;
    
    // Check all eligibility criteria have matching evidence
    const criteriaCount = requirements.eligibilityCriteria.length;
    if (criteriaCount === 0) return false;
    
    let allCriteriaMet = true;
    requirements.eligibilityCriteria.forEach(criteria => {
        const matchCount = countCriteriaMatches(criteria.id, stats);
        if (matchCount === 0) {
            allCriteriaMet = false;
        }
    });
    
    return allCriteriaMet;
}

// Update overview stats
function updateOverviewStats() {
    let totalEvidence = 0;
    let totalReadiness = 0;
    let fullyCompleted = 0;
    let inProgress = 0;
    
    AWARDS_CONFIG.forEach(award => {
    const stats = getAwardStatsById(award.id);
        totalEvidence += stats.total;
        totalReadiness += stats.readiness;
        
        // Count awards that have fully completed all requirements and criteria
        if (isAwardFullyCompleted(award.id)) {
            fullyCompleted++;
        } else {
            // Count awards in progress (have some evidence or requirements started but not fully completed)
            const requirements = AWARDS_REQUIREMENTS[award.id];
            const state = getChecklistState(award.id);
            
            if (requirements) {
                // Check if award has any progress (evidence or requirements started)
                const hasEvidence = stats.total > 0;
                const hasRequirementsStarted = requirements.documentaryRequirements
                    .filter(req => req.required !== false)
                    .some(req => state[req.id] || isRequirementAutoMet(req, stats));
                
                if (hasEvidence || hasRequirementsStarted) {
                    inProgress++;
                }
            }
        }
    });
    
    document.getElementById('stat-ready-to-apply').textContent = fullyCompleted;
    document.getElementById('stat-in-progress').textContent = inProgress;
    document.getElementById('stat-total-awards').textContent = AWARDS_CONFIG.length;
    document.getElementById('stat-avg-readiness').textContent = Math.round(totalReadiness / AWARDS_CONFIG.length) + '%';
}

// Render awards grid
function renderAwardsGrid(category) {
    const grid = document.getElementById('awards-grid');
    const filteredAwards = category === 'all' 
        ? AWARDS_CONFIG 
        : AWARDS_CONFIG.filter(a => a.category === category);
    
    if (filteredAwards.length === 0) {
        grid.innerHTML = `
            <div class="col-span-full text-center py-12 text-text-muted-light dark:text-text-muted-dark">
                <span class="material-symbols-outlined text-4xl mb-2">search_off</span>
                <p>No awards found in this category</p>
            </div>
        `;
        return;
    }
    
    grid.innerHTML = filteredAwards.map(award => {
        const stats = awardStats[award.id] || { total: 0, eligible: 0, readiness: 0, certificates: [], documents: [], events: [], mous: [] };
        const readinessColor = stats.readiness >= 70 ? 'text-green-600' : stats.readiness >= 40 ? 'text-amber-600' : 'text-red-600';
        
        return `
            <div class="award-card bg-card-light dark:bg-card-dark rounded-xl border border-border-light dark:border-border-dark overflow-hidden cursor-pointer h-full flex flex-col" onclick="openAwardModal('${award.id}')">
                <!-- Card Header with Gradient -->
                <div class="${award.gradient} p-5 flex-shrink-0">
                    <div class="flex items-center justify-between">
                        <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center">
                            <span class="material-symbols-outlined text-white text-2xl">${award.icon}</span>
                        </div>
                        <div class="text-right">
                            <p class="text-white/80 text-xs uppercase tracking-wider">${award.category}</p>
                            <p class="text-white font-bold text-2xl">${stats.total}</p>
                            <p class="text-white/70 text-xs">evidence items</p>
                        </div>
                    </div>
                </div>
                
                <!-- Card Body -->
                <div class="p-5 flex-grow flex flex-col">
                    <h3 class="font-bold text-text-light dark:text-text-dark mb-2 line-clamp-2 min-h-[3rem]">${award.title}</h3>
                    <p class="text-sm text-text-muted-light dark:text-text-muted-dark mb-4 line-clamp-2 min-h-[2.5rem]">${award.description}</p>
                    
                    <!-- Progress -->
                    <div class="mb-4 mt-auto">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs text-text-muted-light dark:text-text-muted-dark">Readiness</span>
                            <span class="text-xs font-bold ${readinessColor}">${stats.readiness}%</span>
                        </div>
                        <div class="h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                            <div class="h-full ${award.gradient} rounded-full transition-all duration-500" style="width: ${stats.readiness}%"></div>
                        </div>
                    </div>
                    
                    <!-- Stats Row -->
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1 text-green-600 dark:text-green-400">
                            <span class="material-symbols-outlined text-sm">check_circle</span>
                            <span>${stats.eligible} eligible</span>
                        </div>
                        <div class="flex items-center gap-1 text-purple-600 dark:text-purple-400">
                            <span class="material-symbols-outlined text-sm">smart_toy</span>
                            <span>${stats.aiDetected} AI</span>
                        </div>
                    </div>
                </div>
                
                <!-- Card Footer -->
                <div class="px-5 py-3 bg-gray-50 dark:bg-gray-800/50 border-t border-border-light dark:border-border-dark flex-shrink-0 mt-auto">
                    <button class="w-full text-center text-sm font-medium text-primary hover:text-primary-600 flex items-center justify-center gap-2">
                        View Details
                        <span class="material-symbols-outlined text-sm">arrow_forward</span>
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

// Open award detail modal
function openAwardModal(awardId) {
    currentAwardId = awardId;
    const award = AWARDS_CONFIG.find(a => a.id === awardId);
    const stats = getAwardStatsById(awardId);
    
    if (!award) return;
    
    // Update modal header
    document.getElementById('modal-title').textContent = award.title;
    document.getElementById('modal-subtitle').textContent = award.description;
    document.getElementById('modal-icon').className = `w-14 h-14 rounded-xl ${award.gradient} flex items-center justify-center`;
    document.getElementById('modal-icon').innerHTML = `<span class="material-symbols-outlined text-white text-2xl">${award.icon}</span>`;
    
    // Update readiness (recalculate to ensure it's accurate)
    stats.readiness = calculateAwardReadiness(awardId, stats);
    awardStats[awardId].readiness = stats.readiness;
    
    // Calculate and update Application Readiness based on saved state
    setTimeout(() => updateApplicationReadiness(), 100);
    
    // Update tab counts
    document.getElementById('tab-count-all').textContent = stats.total;
    document.getElementById('tab-count-certificates').textContent = stats.certificates?.length || 0;
    document.getElementById('tab-count-mous').textContent = stats.mous?.length || 0;
    document.getElementById('tab-count-events').textContent = stats.events?.length || 0;
    document.getElementById('tab-count-documents').textContent = stats.documents?.length || 0;
    
    // Reset to first tab (Requirements)
    document.querySelectorAll('.tab-btn').forEach((t, i) => {
        if (i === 0) {
            t.classList.add('active', 'border-primary', 'text-primary');
            t.classList.remove('border-transparent', 'text-text-muted-light', 'dark:text-text-muted-dark');
        } else {
            t.classList.remove('active', 'border-primary', 'text-primary');
            t.classList.add('border-transparent', 'text-text-muted-light', 'dark:text-text-muted-dark');
        }
    });
    
    // Render content (default to requirements tab)
    renderModalContent('requirements');
    
    // Calculate and update Application Readiness based on saved state
    setTimeout(() => updateApplicationReadiness(), 100);
    
    // Show modal
    document.getElementById('award-detail-modal').classList.remove('hidden');
}

// Close award modal
function closeAwardModal() {
    document.getElementById('award-detail-modal').classList.add('hidden');
    currentAwardId = null;
}

// Toggle tabs visibility
function toggleTabsVisibility() {
    const tabsContainer = document.getElementById('modal-tabs-container');
    const toggleIcon = document.getElementById('tabs-toggle-icon');
    
    if (tabsContainer.classList.contains('hidden')) {
        tabsContainer.classList.remove('hidden');
        toggleIcon.textContent = 'visibility';
    } else {
        tabsContainer.classList.add('hidden');
        toggleIcon.textContent = 'visibility_off';
    }
}

// Render modal content based on tab
function renderModalContent(tab) {
    const content = document.getElementById('modal-content');
    const stats = getAwardStatsById(currentAwardId);
    const requirements = AWARDS_REQUIREMENTS[currentAwardId];
    
    // Handle Requirements tab
    if (tab === 'requirements') {
        if (!requirements) {
            content.innerHTML = '<p class="text-center text-text-muted-light dark:text-text-muted-dark py-8">Requirements not available for this award</p>';
            return;
        }
        
        content.innerHTML = renderRequirementsTab(requirements, stats);
        return;
    }
    
    // Handle CHED Guidelines tab
    if (tab === 'ched-guidelines') {
        content.innerHTML = renderCHEDGuidelinesTab();
        return;
    }
    
    if (!stats) {
        content.innerHTML = '<p class="text-center text-text-muted-light dark:text-text-muted-dark py-8">No data available</p>';
        return;
    }
    
    let items = [];
    
    switch (tab) {
        case 'all':
            items = [
                ...stats.certificates.map(i => ({ ...i, type: 'certificate' })),
                ...stats.mous.map(i => ({ ...i, type: 'mou' })),
                ...stats.events.map(i => ({ ...i, type: 'event' })),
                ...stats.documents.map(i => ({ ...i, type: 'document' }))
            ];
            break;
        case 'certificates':
            items = stats.certificates.map(i => ({ ...i, type: 'certificate' }));
            break;
        case 'mous':
            items = stats.mous.map(i => ({ ...i, type: 'mou' }));
            break;
        case 'events':
            items = stats.events.map(i => ({ ...i, type: 'event' }));
            break;
        case 'documents':
            items = stats.documents.map(i => ({ ...i, type: 'document' }));
            break;
    }
    
    // Sort by match score
    items.sort((a, b) => (b.matchScore || 0) - (a.matchScore || 0));
    
    if (items.length === 0) {
        content.innerHTML = `
            <div class="text-center py-12">
                <span class="material-symbols-outlined text-4xl text-text-muted-light dark:text-text-muted-dark mb-3">inbox</span>
                <p class="text-text-muted-light dark:text-text-muted-dark">No evidence found for this category</p>
                <p class="text-sm text-text-muted-light dark:text-text-muted-dark mt-1">Upload relevant documents, events, or MOUs to see matches here</p>
            </div>
        `;
        return;
    }
    
    content.innerHTML = `
        <div class="space-y-3">
            ${items.map(item => renderEvidenceItem(item)).join('')}
        </div>
    `;
}

// Render Requirements Tab content
function renderRequirementsTab(requirements, stats) {
    const statsSafe = ensureStatsStructure(stats);
    const totalEvidence = statsSafe.total || 0;
    const supportingDocsCount = Math.min((statsSafe.documents.length + statsSafe.certificates.length), requirements.documentaryRequirements.find(req => req.id === 'supporting-docs')?.maxCount || 10);
    
    // Load saved checklist state from localStorage
    const savedState = getChecklistState(currentAwardId);
    
    return `
        <div class="space-y-6">
            <!-- Documentary Requirements Section -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl p-5 border border-blue-200 dark:border-blue-800">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-600 flex items-center justify-center">
                            <span class="material-symbols-outlined text-white">folder_open</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-text-light dark:text-text-dark">Documentary Requirements</h3>
                            <p class="text-xs text-text-muted-light dark:text-text-muted-dark">Click checkboxes to track your progress</p>
                        </div>
                    </div>
                    <span class="text-xs text-blue-600 dark:text-blue-400 bg-blue-100 dark:bg-blue-900/50 px-2 py-1 rounded-full">
                        Click to mark complete ✓
                    </span>
                </div>
                <div class="space-y-3">
                    ${requirements.documentaryRequirements.map(req => {
                        const autoStatus = getRequirementAutoStatus(req, statsSafe);
                        const isChecked = savedState[req.id] || false;
                        const autoChecked = autoStatus.autoChecked;
                        const checked = isChecked || autoChecked;
                        
                        const autoDetectInfo = autoChecked
                            ? `<p class="text-xs text-green-600 dark:text-green-400 mt-1 flex items-center gap-1"><span class="material-symbols-outlined text-xs">check_circle</span>Auto-detected${autoStatus.detail ? `: ${autoStatus.detail}` : ''}</p>`
                            : '';
                        
                        return `
                        <div class="flex items-center gap-3 p-3 bg-white dark:bg-gray-800 rounded-lg border ${checked ? 'border-green-300 dark:border-green-700' : 'border-gray-200 dark:border-gray-700'} cursor-pointer hover:border-blue-300 dark:hover:border-blue-600 transition-colors"
                             onclick="toggleRequirement('${req.id}', this)">
                            <div id="check-${req.id}" class="w-6 h-6 rounded-full border-2 ${checked ? 'border-green-500 bg-green-500' : 'border-gray-300 dark:border-gray-600 hover:border-blue-400'} flex items-center justify-center flex-shrink-0 transition-all">
                                ${checked ? '<span class="material-symbols-outlined text-white text-sm">check</span>' : ''}
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-text-light dark:text-text-dark">${req.label}</p>
                                ${req.maxCount ? `<p class="text-xs text-text-muted-light dark:text-text-muted-dark">${supportingDocsCount}/${req.maxCount} documents uploaded</p>` : ''}
                                ${autoDetectInfo}
                            </div>
                            ${req.required !== false ? '<span class="px-2 py-0.5 bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 text-xs rounded-full">Required</span>' : '<span class="px-2 py-0.5 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs rounded-full">Optional</span>'}
                        </div>
                    `}).join('')}
                </div>
                <p class="text-xs text-blue-600 dark:text-blue-400 mt-3 flex items-center gap-1">
                    <span class="material-symbols-outlined text-xs">info</span>
                    Implementation Period: ${requirements.implementationPeriod}
                </p>
            </div>

            <!-- Eligibility Criteria Section -->
            <div class="bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-xl p-5 border border-green-200 dark:border-green-800">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-lg bg-green-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-white">verified</span>
                    </div>
                    <div>
                        <h3 class="font-bold text-text-light dark:text-text-dark">Eligibility Criteria</h3>
                        <p class="text-xs text-text-muted-light dark:text-text-muted-dark">Your initiative should demonstrate these qualities</p>
                    </div>
                </div>
                <div class="space-y-3">
                    ${requirements.eligibilityCriteria.map(criteria => {
                        // Check if any evidence matches this criteria
                        const matchCount = countCriteriaMatches(criteria.id, statsSafe);
                        const hasMatch = matchCount > 0;
                        
                        // Check if manually marked as complete
                        const criteriaState = getChecklistState(currentAwardId);
                        const criteriaKey = `criteria-${criteria.id}`;
                        const isManuallyChecked = criteriaState[criteriaKey] || false;
                        const isCompleted = isManuallyChecked || hasMatch;
                        
                        return `
                            <div class="p-4 bg-white dark:bg-gray-800 rounded-lg border ${isCompleted ? 'border-green-300 dark:border-green-700' : 'border-gray-200 dark:border-gray-700'} cursor-pointer hover:border-green-400 dark:hover:border-green-600 transition-colors"
                                 onclick="toggleCriteria('${criteria.id}', this)">
                                <div class="flex items-start gap-3">
                                    <div id="criteria-check-${criteria.id}" class="w-6 h-6 rounded-full ${isCompleted ? 'bg-green-500' : 'bg-gray-200 dark:bg-gray-700'} flex items-center justify-center flex-shrink-0 transition-all">
                                        ${isCompleted ? '<span class="material-symbols-outlined text-white text-sm">check</span>' : '<span class="material-symbols-outlined text-gray-400 text-sm">radio_button_unchecked</span>'}
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold text-text-light dark:text-text-dark">${criteria.label}</p>
                                        <p class="text-xs text-text-muted-light dark:text-text-muted-dark mt-1">${criteria.description}</p>
                                        ${hasMatch && !isManuallyChecked ? `<p class="text-xs text-green-600 dark:text-green-400 mt-2 flex items-center gap-1"><span class="material-symbols-outlined text-xs">check_circle</span>${matchCount} evidence item(s) matched (auto-detected)</p>` : ''}
                                        ${isManuallyChecked ? `<p class="text-xs text-blue-600 dark:text-blue-400 mt-2 flex items-center gap-1"><span class="material-symbols-outlined text-xs">edit</span>Manually marked as complete</p>` : ''}
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
                <p class="text-xs text-green-600 dark:text-green-400 mt-3 flex items-center gap-1">
                    <span class="material-symbols-outlined text-xs">info</span>
                    Click on criteria to manually mark as complete. Auto-detection is based on evidence matching.
                </p>
            </div>

            <!-- Video Guide Questions Section -->
            <div class="bg-gradient-to-r from-purple-50 to-pink-50 dark:from-purple-900/20 dark:to-pink-900/20 rounded-xl p-5 border border-purple-200 dark:border-purple-800">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-lg bg-purple-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-white">videocam</span>
                    </div>
                    <div>
                        <h3 class="font-bold text-text-light dark:text-text-dark">Video Guide Questions</h3>
                        <p class="text-xs text-text-muted-light dark:text-text-muted-dark">Must address all questions in your ${requirements.videoDuration} video</p>
                    </div>
                </div>
                <div class="space-y-3">
                    ${requirements.guideQuestions.map((question, index) => `
                        <div class="p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                            <div class="flex items-start gap-3">
                                <span class="w-6 h-6 rounded-full bg-purple-100 dark:bg-purple-900/50 text-purple-600 dark:text-purple-400 flex items-center justify-center text-sm font-bold flex-shrink-0">${index + 1}</span>
                                <p class="text-sm text-text-light dark:text-text-dark leading-relaxed">${question}</p>
                            </div>
                        </div>
                    `).join('')}
                </div>
                <div class="mt-4 p-3 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                    <p class="text-xs text-purple-700 dark:text-purple-300 flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm">tips_and_updates</span>
                        <strong>Video Format:</strong> ${requirements.videoDuration} max • HD Resolution • 16:9 Landscape • .mp4 format • Include 5-second campus montage
                    </p>
                </div>
            </div>

            ${requirements.notes ? `
                <!-- Special Notes Section -->
                <div class="bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 rounded-xl p-5 border border-amber-200 dark:border-amber-800">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-lg bg-amber-600 flex items-center justify-center">
                            <span class="material-symbols-outlined text-white">warning</span>
                        </div>
                        <div>
                            <h3 class="font-bold text-text-light dark:text-text-dark">Special Notes</h3>
                            <p class="text-xs text-text-muted-light dark:text-text-muted-dark">Important requirements for this award</p>
                        </div>
                    </div>
                    <ul class="space-y-2">
                        ${requirements.notes.map(note => `
                            <li class="flex items-start gap-2 text-sm text-text-light dark:text-text-dark">
                                <span class="material-symbols-outlined text-amber-600 dark:text-amber-400 text-sm mt-0.5">arrow_right</span>
                                ${note}
                            </li>
                        `).join('')}
                    </ul>
                </div>
            ` : ''}
        </div>
    `;
}

// Render CHED Guidelines Tab content
function renderCHEDGuidelinesTab() {
    return `
        <div class="space-y-6">
            <!-- ICONS 2025 Header -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-xl p-6 text-white">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-16 h-16 rounded-lg bg-white/20 flex items-center justify-center">
                        <span class="material-symbols-outlined text-4xl">military_tech</span>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold">ICONS Awards 2025</h2>
                        <p class="text-blue-100">Internationalization Awards for Philippine Higher Education Institutions</p>
                    </div>
                </div>
                <p class="text-sm text-blue-100 leading-relaxed">
                    The <strong>Commission on Higher Education (CHED)</strong> will hold its annual <strong>ICONS Awards</strong> to celebrate Philippine <strong>Higher Education Institutions (HEIs)</strong> for their role in championing the Philippine higher education sector on the international stage.
                </p>
            </div>

            <!-- About ICONS 2025 -->
            <div class="bg-card-light dark:bg-card-dark rounded-xl p-6 border border-border-light dark:border-border-dark">
                <h3 class="text-lg font-bold text-text-light dark:text-text-dark mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">info</span>
                    About ICONS 2025
                </h3>
                <p class="text-sm text-text-muted-light dark:text-text-muted-dark leading-relaxed mb-4">
                    The <strong>ICONS Awards 2025</strong> will continue to recognize the exemplary performance of Philippine HEIs as their presence grows in renowned international ranking tables. This ceremony is a vital avenue for acknowledging the high-impact initiatives of Philippine HEIs toward <strong>sustainability</strong>, <strong>ASEAN awareness</strong>, and <strong>internationalization</strong>, along with the leaders whose vision transformed these goals into concrete achievements and milestones.
                </p>
                <p class="text-sm text-text-muted-light dark:text-text-muted-dark leading-relaxed">
                    The <strong>Internationalization (IZN) Awards</strong> will serve as the highlight of the night, honoring exemplar Philippine HEIs and their leaders who have championed the internationalization of Philippine higher education.
                </p>
            </div>

            <!-- Award Categories -->
            <div class="space-y-4">
                <h3 class="text-lg font-bold text-text-light dark:text-text-dark flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">category</span>
                    Award Categories
                </h3>

                <!-- Institutional Awards -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl p-6 border border-blue-200 dark:border-blue-800">
                    <h4 class="text-md font-bold text-text-light dark:text-text-dark mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-blue-600">business</span>
                        I. Institutional Awards
                    </h4>
                    <p class="text-xs text-text-muted-light dark:text-text-muted-dark mb-4">
                        This award category recognizes <strong>Higher Education Institutions (HEIs)</strong> and their core units for exceptional strategy and results. These awards highlight programs that successfully embed <strong>internationalization, sustainability, and regional solidarity</strong> into their operations and academics.
                    </p>
                    
                    <div class="space-y-3">
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                            <h5 class="font-semibold text-text-light dark:text-text-dark mb-2">Global Citizenship Awards</h5>
                            <p class="text-xs text-text-muted-light dark:text-text-muted-dark mb-2">
                                <strong>Category A:</strong> For Private Higher Education Institutions<br>
                                <strong>Category B:</strong> For State Universities and Colleges (SUCs) and Local Universities and Colleges (LUCs)
                            </p>
                            <p class="text-xs text-text-muted-light dark:text-text-muted-dark">
                                The Global Citizenship Awards celebrate higher education institutions at the forefront of fostering responsible global leaders. We recognize institutions with exceptional programs that ignite intercultural understanding, empower changemakers, and cultivate active engagement.
                            </p>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                            <h5 class="font-semibold text-text-light dark:text-text-dark mb-2">Outstanding International Education Program Awards</h5>
                            <p class="text-xs text-text-muted-light dark:text-text-muted-dark mb-2">
                                <strong>Category A:</strong> For Private Higher Education Institutions<br>
                                <strong>Category B:</strong> For State Universities and Colleges (SUCs) and Local Universities and Colleges (LUCs)
                            </p>
                            <p class="text-xs text-text-muted-light dark:text-text-muted-dark">
                                The Outstanding International Education Program Awards honor higher education institutions that champion inclusive internationalization. We recognize programs that expand access to global opportunities, foster collaborative innovation, and embrace inclusivity and beyond.
                            </p>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                            <h5 class="font-semibold text-text-light dark:text-text-dark mb-2">Sustainability Awards</h5>
                            <p class="text-xs text-text-muted-light dark:text-text-muted-dark mb-2">
                                <strong>Category A:</strong> For Private Higher Education Institutions<br>
                                <strong>Category B:</strong> For State Universities and Colleges (SUCs)<br>
                                <strong>Category C:</strong> For Local Universities and Colleges (LUCs)
                            </p>
                            <p class="text-xs text-text-muted-light dark:text-text-muted-dark">
                                The Sustainability Award recognizes and honors higher education institutions that demonstrate outstanding commitment to sustainability. We seek to highlight programs and initiatives that exemplify pioneering integration, impactful projects, and long-term commitment.
                            </p>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                            <h5 class="font-semibold text-text-light dark:text-text-dark mb-2">Best ASEAN Awareness Initiative Awards</h5>
                            <p class="text-xs text-text-muted-light dark:text-text-muted-dark mb-2">
                                <strong>Category A:</strong> For Private Higher Education Institutions<br>
                                <strong>Category B:</strong> For State Universities and Colleges (SUCs) and Local Universities and Colleges (LUCs)
                            </p>
                            <p class="text-xs text-text-muted-light dark:text-text-muted-dark">
                                The Best ASEAN Awareness Initiative Award honors higher education institutions that have implemented initiatives that boost understanding and awareness of the Association of Southeast Asian Nations (ASEAN). We recognize programs that promote regional identity and solidarity, cross-cultural initiative programs, and measurable outreach and sustained commitment.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Individual Awards -->
                <div class="bg-gradient-to-r from-purple-50 to-pink-50 dark:from-purple-900/20 dark:to-pink-900/20 rounded-xl p-6 border border-purple-200 dark:border-purple-800">
                    <h4 class="text-md font-bold text-text-light dark:text-text-dark mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-purple-600">person</span>
                        II. Individual Awards
                    </h4>
                    <p class="text-xs text-text-muted-light dark:text-text-muted-dark mb-4">
                        This category honors <strong>transformative leaders</strong> who personally drive global excellence. These awards celebrate the <strong>vision, integrity, and ethical governance</strong> required to shape the future of Philippine higher education.
                    </p>
                    
                    <div class="space-y-3">
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                            <h5 class="font-semibold text-text-light dark:text-text-dark mb-2">Emerging Leadership Award</h5>
                            <p class="text-xs text-text-muted-light dark:text-text-muted-dark mb-2">
                                <strong>For:</strong> Internationalization directors, officers, and junior leaders of higher education institutions (Private HEIs, SUCs, and LUCs)
                            </p>
                            <p class="text-xs text-text-muted-light dark:text-text-muted-dark">
                                The Emerging Leadership Award recognizes the exceptional contributions of rising stars who are shaping a more inclusive future of internationalization. We honor International Relations Directors, Officers, and/or Heads of Internationalization Department/Unit/Offices who practice innovation, drive strategic and inclusive growth, and empower others.
                            </p>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                            <h5 class="font-semibold text-text-light dark:text-text-dark mb-2">Internationalization Leadership Award</h5>
                            <p class="text-xs text-text-muted-light dark:text-text-muted-dark mb-2">
                                <strong>For:</strong> Executive officials of higher education institutions (Private HEIs, SUCs, and LUCs)
                            </p>
                            <p class="text-xs text-text-muted-light dark:text-text-muted-dark">
                                The Internationalization (IZN) Leadership Award honors transformative leaders who are shaping the future of Philippine Higher Education. Leaders who demonstrate excellence in strategic vision and integration; ethical leadership and governance; and sustained impact and development.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Special Awards -->
                <div class="bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 rounded-xl p-6 border border-amber-200 dark:border-amber-800">
                    <h4 class="text-md font-bold text-text-light dark:text-text-dark mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-amber-600">star</span>
                        III. Special Awards
                    </h4>
                    <p class="text-xs text-text-muted-light dark:text-text-muted-dark mb-4">
                        This group recognizes <strong>Higher Education Institutions (HEIs)</strong> and their core units for exceptional strategy and results. These awards highlight programs that successfully embed <strong>internationalization, sustainability, and regional solidarity</strong> into their operations and academics.
                    </p>
                    
                    <div class="space-y-3">
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                            <h5 class="font-semibold text-text-light dark:text-text-dark mb-2">Best CHED Regional Office for Internationalization Award</h5>
                            <p class="text-xs text-text-muted-light dark:text-text-muted-dark mb-2">
                                <strong>For:</strong> The Commission on Higher Education Regional Offices
                            </p>
                            <p class="text-xs text-text-muted-light dark:text-text-muted-dark">
                                This award recognizes the CHED Regional Office that demonstrates exceptional commitment, leadership, and operational excellence in promoting sustainable and inclusive internationalization within its region. We celebrate the office whose leadership and innovation have created the most significant, compliant, and well-governed internationalization ecosystem.
                            </p>
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                            <h5 class="font-semibold text-text-light dark:text-text-dark mb-2">Most Promising Regional IRO Community Award</h5>
                            <p class="text-xs text-text-muted-light dark:text-text-muted-dark mb-2">
                                <strong>For:</strong> The Commission on Higher Education Regional Offices
                            </p>
                            <p class="text-xs text-text-muted-light dark:text-text-muted-dark">
                                This recognizes the Regional IRO Community, or Group, or Network, or Association that demonstrates the most significant vision, early progress, and clear potential to strengthen internationalization capacity among Higher Education Institutions (HEIs) within its region. This award celebrates effective <strong>collaboration, resource-sharing, and future impact</strong>, honoring communities that are paving the way for a more connected and skilled regional internationalization ecosystem.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Requirements Section -->
            <div class="bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-xl p-6 border border-green-200 dark:border-green-800">
                <h3 class="text-lg font-bold text-text-light dark:text-text-dark mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-green-600">checklist</span>
                    Nominations and Applications Requirements
                </h3>
                <ul class="space-y-2 mb-4">
                    <li class="flex items-start gap-2 text-sm text-text-light dark:text-text-dark">
                        <span class="material-symbols-outlined text-green-600 text-sm mt-0.5">video_library</span>
                        <span>A <strong>5-min video</strong> for IZN Leadership Award, or <strong>3-min video</strong> for all other awards</span>
                    </li>
                    <li class="flex items-start gap-2 text-sm text-text-light dark:text-text-dark">
                        <span class="material-symbols-outlined text-green-600 text-sm mt-0.5">description</span>
                        <span>Supporting documents (MOA/MOUs, Certificates, Policies approved by the Board)</span>
                    </li>
                </ul>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-green-200 dark:border-green-700">
                    <p class="text-xs text-text-muted-light dark:text-text-muted-dark mb-2">
                        <strong>Click here to download the detailed guidelines.</strong>
                    </p>
                </div>
            </div>

            <!-- Application Information -->
            <div class="bg-card-light dark:bg-card-dark rounded-xl p-6 border border-border-light dark:border-border-dark">
                <h3 class="text-lg font-bold text-text-light dark:text-text-dark mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">assignment</span>
                    Nominations and Applications Form
                </h3>
                <ul class="space-y-2 mb-4">
                    <li class="flex items-start gap-2 text-sm text-text-light dark:text-text-dark">
                        <span class="material-symbols-outlined text-primary text-sm mt-0.5">check_circle</span>
                        <span>All nominations/application must be done <strong>online</strong>.</span>
                    </li>
                    <li class="flex items-start gap-2 text-sm text-text-light dark:text-text-dark">
                        <span class="material-symbols-outlined text-primary text-sm mt-0.5">event</span>
                        <span>Deadline for nominations/application is on <strong>17 November 2025, 05:00 PM (PhST)</strong></span>
                    </li>
                </ul>
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
                    <p class="text-xs text-blue-700 dark:text-blue-300">
                        <strong>Click here to access the online form.</strong>
                    </p>
                </div>
            </div>

            <!-- External Link -->
            <div class="bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 rounded-xl p-6 border border-indigo-200 dark:border-indigo-800 text-center">
                <p class="text-sm text-text-muted-light dark:text-text-muted-dark mb-3">
                    For more information, visit the official ICONS 2025 website:
                </p>
                <a href="https://ieducationphl.ched.gov.ph/icons2025/" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors">
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                    Visit ICONS 2025 Website
                </a>
            </div>
        </div>
    `;
}

// Count evidence matches for a specific criteria
function countCriteriaMatches(criteriaId, stats) {
    // Simple heuristic: check if any evidence keywords match criteria keywords
    const criteriaKeywords = {
        'intercultural': ['intercultural', 'cultural', 'diversity', 'inclusive'],
        'changemakers': ['SDG', 'sustainable', 'development', 'goals', 'changemaker'],
        'engagement': ['engagement', 'community', 'action', 'platform'],
        'access': ['access', 'opportunity', 'barrier', 'diverse', 'financial'],
        'innovation': ['innovation', 'partnership', 'collaborative', 'innovative'],
        'inclusivity': ['inclusive', 'inclusivity', 'equity', 'accessible'],
        'integration': ['integration', 'SDG', 'curriculum', 'operations'],
        'impact': ['impact', 'measurable', 'outcome', 'result'],
        'commitment': ['commitment', 'long-term', 'sustainable', 'scalable'],
        'identity': ['ASEAN', 'regional', 'identity', 'solidarity'],
        'crosscultural': ['exchange', 'cross-cultural', 'intercultural'],
        'outreach': ['outreach', 'reach', 'participation', 'survey'],
        'vision': ['vision', 'strategy', 'plan', 'strategic'],
        'ethics': ['ethical', 'integrity', 'governance', 'fiscal'],
        'growth': ['growth', 'development', 'expansion', 'diversity'],
        'mentorship': ['mentor', 'advocate', 'cultivate', 'leader'],
        'comprehensive': ['comprehensive', 'internationalization', 'regional'],
        'cooperation': ['cooperation', 'collaboration', 'partnership', 'IAS'],
        'excellence': ['excellence', 'compliance', 'governance', 'timely'],
        'measurable': ['measurable', 'data', 'statistics', 'ranking'],
        'progress': ['progress', 'initiative', 'activity', 'collaborative'],
        'future': ['future', 'plan', 'initiative', 'value']
    };
    
    const keywords = criteriaKeywords[criteriaId] || [];
    if (keywords.length === 0) return 0;
    
    let matchCount = 0;
    const allItems = [
        ...(stats.certificates || []),
        ...(stats.mous || []),
        ...(stats.events || []),
        ...(stats.documents || [])
    ];
    
    allItems.forEach(item => {
        const text = `${item.title || ''} ${item.description || ''} ${item.institution || ''}`.toLowerCase();
        const hasMatch = keywords.some(kw => text.includes(kw.toLowerCase()));
        if (hasMatch) matchCount++;
    });
    
    return matchCount;
}

// Render a single evidence item
function renderEvidenceItem(item) {
    const icons = {
        certificate: 'workspace_premium',
        mou: 'handshake',
        event: 'event',
        document: 'description'
    };
    
    const typeLabels = {
        certificate: 'Certificate',
        mou: 'MOU/MOA',
        event: 'Event',
        document: 'Document'
    };
    
    const typeColors = {
        certificate: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        mou: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        event: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        document: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'
    };
    
    const scoreColor = item.matchScore >= 70 ? 'text-green-600 bg-green-50 dark:bg-green-900/20' : 
                       item.matchScore >= 40 ? 'text-amber-600 bg-amber-50 dark:bg-amber-900/20' : 
                       'text-red-600 bg-red-50 dark:bg-red-900/20';
    
    const title = item.title || item.institution || 'Untitled';
    const description = item.description || item.location || '';
    const date = item.created_at || item.event_date || item.sign_date || '';
    
    return `
        <div class="flex items-start gap-4 p-4 rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-border-light dark:border-border-dark hover:border-primary/30 transition-colors">
            <div class="w-10 h-10 rounded-lg ${typeColors[item.type]} flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-lg">${icons[item.type]}</span>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h4 class="font-medium text-text-light dark:text-text-dark line-clamp-1">${escapeHtml(title)}</h4>
                        <p class="text-sm text-text-muted-light dark:text-text-muted-dark line-clamp-2 mt-1">${escapeHtml(description)}</p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        ${item.aiDetected ? '<span class="material-symbols-outlined text-purple-500 text-sm ai-badge rounded-full p-0.5" title="AI Detected">smart_toy</span>' : ''}
                        <span class="px-2 py-1 rounded-full text-xs font-medium ${scoreColor}">${item.matchScore}%</span>
                    </div>
                </div>
                <div class="flex items-center gap-4 mt-2 text-xs text-text-muted-light dark:text-text-muted-dark">
                    <span class="px-2 py-0.5 rounded-full ${typeColors[item.type]} text-xs">${typeLabels[item.type]}</span>
                    ${date ? `<span class="flex items-center gap-1"><span class="material-symbols-outlined text-xs">calendar_today</span>${formatDate(date)}</span>` : ''}
                </div>
            </div>
        </div>
    `;
}

// Helper functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateStr) {
    try {
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    } catch {
        return dateStr;
    }
}

function refreshData() {
    window.location.reload();
}

function exportEvidence() {
    alert('Export functionality coming soon! This will package all evidence for the selected award.');
}

// ==========================================
// CHECKLIST STATE MANAGEMENT
// ==========================================

// Get checklist state from localStorage
function getChecklistState(awardId) {
    try {
        const saved = localStorage.getItem(`lilac_checklist_${awardId}`);
        return saved ? JSON.parse(saved) : {};
    } catch (e) {
        console.error('Error loading checklist state:', e);
        return {};
    }
}

// Save checklist state to localStorage
function saveChecklistState(awardId, state) {
    try {
        localStorage.setItem(`lilac_checklist_${awardId}`, JSON.stringify(state));
    } catch (e) {
        console.error('Error saving checklist state:', e);
    }
}

// Toggle eligibility criteria checkbox
function toggleCriteria(criteriaId, element) {
    const state = getChecklistState(currentAwardId);
    const criteriaKey = `criteria-${criteriaId}`;
    const isCurrentlyChecked = state[criteriaKey] || false;
    
    // Toggle the state
    state[criteriaKey] = !isCurrentlyChecked;
    saveChecklistState(currentAwardId, state);
    
    // Update the UI
    const checkDiv = element.querySelector(`#criteria-check-${criteriaId}`);
    if (checkDiv) {
        if (state[criteriaKey]) {
            // Mark as checked
            checkDiv.classList.remove('bg-gray-200', 'dark:bg-gray-700');
            checkDiv.classList.add('bg-green-500');
            checkDiv.innerHTML = '<span class="material-symbols-outlined text-white text-sm">check</span>';
            element.classList.remove('border-gray-200', 'dark:border-gray-700');
            element.classList.add('border-green-300', 'dark:border-green-700');
        } else {
            // Mark as unchecked
            checkDiv.classList.remove('bg-green-500');
            checkDiv.classList.add('bg-gray-200', 'dark:bg-gray-700');
            checkDiv.innerHTML = '<span class="material-symbols-outlined text-gray-400 text-sm">radio_button_unchecked</span>';
            element.classList.remove('border-green-300', 'dark:border-green-700');
            element.classList.add('border-gray-200', 'dark:border-gray-700');
        }
    }
    
    // Update Application Readiness in modal
    updateApplicationReadiness();
    
    // Update readiness in award stats and refresh grid
    if (awardStats[currentAwardId]) {
        awardStats[currentAwardId].readiness = calculateAwardReadiness(currentAwardId, awardStats[currentAwardId]);
        // Refresh the grid to update the card
        const currentCategory = document.querySelector('.category-tab.active')?.dataset.category || 'all';
        renderAwardsGrid(currentCategory);
        updateOverviewStats();
    }
    
    // Show toast notification
    showChecklistToast(state[criteriaKey] ? 'Criteria marked as complete!' : 'Criteria marked as incomplete');
}

// Toggle requirement checkbox
function toggleRequirement(reqId, element) {
    const state = getChecklistState(currentAwardId);
    const isCurrentlyChecked = state[reqId] || false;
    
    // Toggle the state
    state[reqId] = !isCurrentlyChecked;
    saveChecklistState(currentAwardId, state);
    
    // Update the UI
    const checkDiv = element.querySelector(`#check-${reqId}`);
    if (checkDiv) {
        if (state[reqId]) {
            // Mark as checked
            checkDiv.classList.remove('border-gray-300', 'dark:border-gray-600');
            checkDiv.classList.add('border-green-500', 'bg-green-500');
            checkDiv.innerHTML = '<span class="material-symbols-outlined text-white text-sm">check</span>';
            element.classList.remove('border-gray-200', 'dark:border-gray-700');
            element.classList.add('border-green-300', 'dark:border-green-700');
        } else {
            // Mark as unchecked
            checkDiv.classList.add('border-gray-300', 'dark:border-gray-600');
            checkDiv.classList.remove('border-green-500', 'bg-green-500');
            checkDiv.innerHTML = '';
            element.classList.add('border-gray-200', 'dark:border-gray-700');
            element.classList.remove('border-green-300', 'dark:border-green-700');
        }
    }
    
    // Update Application Readiness in modal
    updateApplicationReadiness();
    
    // Update readiness in award stats and refresh grid
    if (awardStats[currentAwardId]) {
        awardStats[currentAwardId].readiness = calculateAwardReadiness(currentAwardId, awardStats[currentAwardId]);
        // Refresh the grid to update the card
        const currentCategory = document.querySelector('.category-tab.active')?.dataset.category || 'all';
        renderAwardsGrid(currentCategory);
        updateOverviewStats();
    }
    
    // Show toast notification
    showChecklistToast(state[reqId] ? 'Marked as complete!' : 'Marked as incomplete');
}

// Calculate and update Application Readiness
function updateApplicationReadiness() {
    const requirements = AWARDS_REQUIREMENTS[currentAwardId];
    const stats = awardStats[currentAwardId] || { total: 0, certificates: [], documents: [], events: [], mous: [] };
    const state = getChecklistState(currentAwardId);
    
    if (!requirements) return;
    
    // Calculate documentary requirements completion (60% weight)
    let docScore = 0;
    let docPossible = 0;
    const docRequirements = requirements.documentaryRequirements;
    docRequirements.forEach(req => {
        const isRequired = req.required !== false;
        if (!isRequired) {
            return;
        }
        docPossible += 1;
        const isChecked = state[req.id] || false;
        const autoChecked = isRequirementAutoMet(req, stats);
        if (isChecked || autoChecked) {
            docScore += 1;
        }
    });
    const docPercentage = docPossible > 0 ? (docScore / docPossible) * 60 : 0;
    
    // Calculate evidence matching (40% weight)
    // Check both manual selection and automatic matching
    let evidenceScore = 0;
    const criteriaCount = requirements.eligibilityCriteria.length;
    requirements.eligibilityCriteria.forEach(criteria => {
        const criteriaKey = `criteria-${criteria.id}`;
        const isManuallyChecked = state[criteriaKey] || false;
        const matchCount = countCriteriaMatches(criteria.id, stats);
        const hasAutoMatch = matchCount > 0;
        
        // Criteria is met if manually checked OR automatically matched
        if (isManuallyChecked || hasAutoMatch) {
            evidenceScore += 1;
        }
    });
    const evidencePercentage = criteriaCount > 0 ? (evidenceScore / criteriaCount) * 40 : 0;
    
    // Total readiness
    const totalReadiness = Math.round(docPercentage + evidencePercentage);
    
    // Update award stats with new readiness
    if (awardStats[currentAwardId]) {
        awardStats[currentAwardId].readiness = totalReadiness;
    }
    
    // Update the UI
    const readinessPercent = document.getElementById('modal-readiness-percent');
    const readinessBar = document.getElementById('modal-readiness-bar');
    const missingHint = document.getElementById('modal-missing-hint');
    
    if (readinessPercent) {
        readinessPercent.textContent = totalReadiness + '%';
        // Change color based on readiness level
        readinessPercent.className = 'text-sm font-bold ' + 
            (totalReadiness >= 70 ? 'text-green-600' : totalReadiness >= 40 ? 'text-amber-600' : 'text-red-600');
    }
    
    if (readinessBar) {
        readinessBar.style.width = totalReadiness + '%';
        // Change gradient based on readiness level
        if (totalReadiness >= 70) {
            readinessBar.className = 'h-full bg-gradient-to-r from-green-500 to-emerald-400 rounded-full transition-all duration-500';
        } else if (totalReadiness >= 40) {
            readinessBar.className = 'h-full bg-gradient-to-r from-amber-500 to-yellow-400 rounded-full transition-all duration-500';
        } else {
            readinessBar.className = 'h-full bg-gradient-to-r from-red-500 to-orange-400 rounded-full transition-all duration-500';
        }
    }
    
    // Show missing items hint
    if (missingHint) {
        const missing = [];
        requirements.documentaryRequirements.forEach(req => {
            if (!req.required) return;
            const isChecked = state[req.id] || false;
            const autoChecked = isRequirementAutoMet(req, stats);
            if (!isChecked && !autoChecked) {
                missing.push(req.label);
            }
        });
        
        if (missing.length > 0) {
            // Update only the text content, preserving the button
            const textSpan = missingHint.querySelector('span.flex-1');
            if (textSpan) {
                textSpan.textContent = `Missing: ${missing.join(', ')}`;
            } else {
                // Fallback if structure is different
                const warningIcon = missingHint.querySelector('.material-symbols-outlined.text-xs');
                if (warningIcon && warningIcon.nextSibling && warningIcon.nextSibling.nodeType === 3) {
                    warningIcon.nextSibling.textContent = ` Missing: ${missing.join(', ')}`;
                } else {
                    // If structure doesn't match, update innerHTML but preserve button
                    const existingButton = missingHint.querySelector('button');
                    missingHint.innerHTML = `
                        <span class="material-symbols-outlined text-xs align-middle">warning</span>
                        <span class="flex-1">Missing: ${missing.join(', ')}</span>
                    `;
                    if (existingButton) {
                        missingHint.appendChild(existingButton);
                    } else {
                        const toggleButton = document.createElement('button');
                        toggleButton.onclick = toggleTabsVisibility;
                        toggleButton.className = 'p-1 rounded-lg hover:bg-amber-100 dark:hover:bg-amber-900/30 text-amber-600 dark:text-amber-400 transition-colors';
                        toggleButton.title = 'Show/Hide tabs';
                        toggleButton.innerHTML = '<span id="tabs-toggle-icon" class="material-symbols-outlined text-sm">visibility_off</span>';
                        missingHint.appendChild(toggleButton);
                    }
                }
            }
            missingHint.classList.remove('hidden');
        } else {
            missingHint.classList.add('hidden');
        }
    }
    
    // Also update the card stats
    if (awardStats[currentAwardId]) {
        awardStats[currentAwardId].readiness = totalReadiness;
    }
}

// Show notification modal
function showNotificationModal(message, type = 'info') {
    const modal = document.getElementById('notification-modal');
    const icon = document.getElementById('notification-icon');
    const title = document.getElementById('notification-title');
    const messageEl = document.getElementById('notification-message');
    
    const config = {
        warning: {
            bg: 'bg-amber-500',
            icon: 'warning',
            titleText: 'Warning'
        },
        error: {
            bg: 'bg-red-500',
            icon: 'error',
            titleText: 'Error'
        },
        success: {
            bg: 'bg-green-500',
            icon: 'check_circle',
            titleText: 'Success'
        },
        info: {
            bg: 'bg-blue-500',
            icon: 'info',
            titleText: 'Information'
        }
    };
    
    const settings = config[type] || config.info;
    
    icon.className = `w-12 h-12 rounded-full ${settings.bg} flex items-center justify-center flex-shrink-0`;
    icon.innerHTML = `<span class="material-symbols-outlined text-white text-xl">${settings.icon}</span>`;
    title.textContent = settings.titleText;
    messageEl.textContent = message;
    
    modal.classList.remove('hidden');
}

function closeNotificationModal() {
    const modal = document.getElementById('notification-modal');
    modal.classList.add('hidden');
}

// Close notification modal on backdrop click
document.getElementById('notification-modal')?.addEventListener('click', (e) => {
    if (e.target.id === 'notification-modal') {
        closeNotificationModal();
    }
});

// Close notification modal on Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const modal = document.getElementById('notification-modal');
        if (!modal.classList.contains('hidden')) {
            closeNotificationModal();
        }
    }
});

// Show toast notification
function showChecklistToast(message) {
    // Remove existing toast if any
    const existingToast = document.getElementById('checklist-toast');
    if (existingToast) {
        existingToast.remove();
    }
    
    // Create toast
    const toast = document.createElement('div');
    toast.id = 'checklist-toast';
    toast.className = 'fixed bottom-20 left-1/2 transform -translate-x-1/2 bg-gray-900 text-white px-4 py-2 rounded-lg shadow-lg z-[100] flex items-center gap-2 animate-fade-in';
    toast.innerHTML = `
        <span class="material-symbols-outlined text-green-400 text-sm">check_circle</span>
        <span class="text-sm">${message}</span>
    `;
    
    document.body.appendChild(toast);
    
    // Remove after 2 seconds
    setTimeout(() => {
        toast.classList.add('opacity-0', 'transition-opacity', 'duration-300');
        setTimeout(() => toast.remove(), 300);
    }, 2000);
}

// Close modal on escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeAwardModal();
    }
});

// Close modal on backdrop click
document.getElementById('award-detail-modal')?.addEventListener('click', (e) => {
    if (e.target.id === 'award-detail-modal') {
        closeAwardModal();
    }
});

// Report Generation Functions
let selectedAwardIds = new Set();

function openReportModal() {
    const modal = document.getElementById('report-selection-modal');
    const list = document.getElementById('award-selection-list');
    
    // Populate award checkboxes
    list.innerHTML = AWARDS_CONFIG.map(award => {
        const stats = getAwardStatsById(award.id);
        const requirements = AWARDS_REQUIREMENTS[award.id];
        const isFullyCompleted = isAwardFullyCompleted(award.id);
        
        return `
            <label class="flex items-start gap-3 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-border-light dark:border-border-dark cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                <input type="checkbox" 
                       class="mt-1 w-5 h-5 text-amber-500 rounded focus:ring-amber-500" 
                       value="${award.id}"
                       onchange="updateSelectedCount()"
                       ${selectedAwardIds.has(award.id) ? 'checked' : ''}>
                <div class="flex-1">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h3 class="font-semibold text-text-light dark:text-text-dark">${award.title}</h3>
                            <p class="text-sm text-text-muted-light dark:text-text-muted-dark mt-1">${award.description}</p>
                            <div class="flex items-center gap-3 mt-2">
                                <span class="text-xs px-2 py-1 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400">${award.category.toUpperCase()}</span>
                                <span class="text-xs text-text-muted-light dark:text-text-muted-dark">${stats.total} evidence items</span>
                                <span class="text-xs text-text-muted-light dark:text-text-muted-dark">${stats.readiness}% readiness</span>
                                ${isFullyCompleted ? '<span class="text-xs px-2 py-1 rounded-full bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400">Ready to Apply</span>' : ''}
                            </div>
                        </div>
                    </div>
                </div>
            </label>
        `;
    }).join('');
    
    updateSelectedCount();
    modal.classList.remove('hidden');
}

function closeReportSelectionModal() {
    const modal = document.getElementById('report-selection-modal');
    modal.classList.add('hidden');
}

function selectAllAwards() {
    AWARDS_CONFIG.forEach(award => {
        selectedAwardIds.add(award.id);
    });
    document.querySelectorAll('#award-selection-list input[type="checkbox"]').forEach(cb => {
        cb.checked = true;
    });
    updateSelectedCount();
}

function deselectAllAwards() {
    selectedAwardIds.clear();
    document.querySelectorAll('#award-selection-list input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    selectedAwardIds.clear();
    document.querySelectorAll('#award-selection-list input[type="checkbox"]:checked').forEach(cb => {
        selectedAwardIds.add(cb.value);
    });
    document.getElementById('selected-count').textContent = selectedAwardIds.size;
}

function generateReport() {
    if (selectedAwardIds.size === 0) {
        showNotificationModal('Please select at least one award to generate a report.', 'warning');
        return;
    }
    
    const reportContent = document.getElementById('report-content');
    const reportDate = document.getElementById('report-date');
    const reportView = document.getElementById('report-view');
    
    // Set report date
    reportDate.textContent = new Date().toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    // Generate report content
    let html = '';
    Array.from(selectedAwardIds).forEach((awardId, index) => {
        const award = AWARDS_CONFIG.find(a => a.id === awardId);
        const stats = getAwardStatsById(awardId);
        const requirements = AWARDS_REQUIREMENTS[awardId];
        
        if (!award) return;
        
        const isFullyCompleted = isAwardFullyCompleted(awardId);
        
        html += `
            <div class="mb-12 ${index > 0 ? 'page-break-before' : ''}">
                <!-- Award Header -->
                <div class="mb-6 pb-4 border-b-2 border-gray-300">
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">${award.title}</h2>
                    <p class="text-lg text-gray-600">${award.description}</p>
                    <div class="flex items-center gap-4 mt-3">
                        <span class="px-3 py-1 bg-gray-200 text-gray-900 rounded-full text-sm font-medium border border-gray-400">${award.category.toUpperCase()}</span>
                        ${isFullyCompleted ? '<span class="px-3 py-1 bg-gray-300 text-gray-900 rounded-full text-sm font-medium border border-gray-500">Ready to Apply</span>' : ''}
                    </div>
                </div>
                
                <!-- Award Statistics -->
                <div class="mb-6 grid grid-cols-4 gap-4">
                    <div class="p-4 bg-white rounded-lg border-2 border-gray-400">
                        <p class="text-sm text-gray-700 mb-1 font-medium">Total Evidence</p>
                        <p class="text-2xl font-bold text-gray-900">${stats.total || 0}</p>
                    </div>
                    <div class="p-4 bg-white rounded-lg border-2 border-gray-400">
                        <p class="text-sm text-gray-700 mb-1 font-medium">Readiness</p>
                        <p class="text-2xl font-bold text-gray-900">${stats.readiness || 0}%</p>
                    </div>
                    <div class="p-4 bg-white rounded-lg border-2 border-gray-400">
                        <p class="text-sm text-gray-700 mb-1 font-medium">Certificates</p>
                        <p class="text-2xl font-bold text-gray-900">${stats.certificates?.length || 0}</p>
                    </div>
                    <div class="p-4 bg-white rounded-lg border-2 border-gray-400">
                        <p class="text-sm text-gray-700 mb-1 font-medium">Documents</p>
                        <p class="text-2xl font-bold text-gray-900">${(stats.mous?.length || 0) + (stats.documents?.length || 0)}</p>
                    </div>
                </div>
        `;
        
        if (requirements) {
            // Documentary Requirements
            html += `
                <div class="mb-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Documentary Requirements</h3>
                    <ul class="list-disc list-inside space-y-2 ml-4">
            `;
            requirements.documentaryRequirements.forEach(req => {
                const state = getChecklistState(awardId);
                const isChecked = state[req.id] || (req.id === 'supporting-docs' && stats.total > 0);
                html += `
                    <li class="text-gray-700">
                        ${isChecked ? '✓' : '○'} ${req.label} ${req.required ? '<span class="font-bold">(Required)</span>' : ''}
                    </li>
                `;
            });
            html += `
                    </ul>
                    <p class="text-sm text-gray-600 mt-2"><strong>Implementation Period:</strong> ${requirements.implementationPeriod}</p>
                </div>
            `;
            
            // Eligibility Criteria
            html += `
                <div class="mb-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Eligibility Criteria</h3>
                    <div class="space-y-3">
            `;
            requirements.eligibilityCriteria.forEach(criteria => {
                const matchCount = countCriteriaMatches(criteria.id, stats);
                html += `
                    <div class="p-4 bg-gray-50 rounded-lg border border-gray-300">
                        <h4 class="font-semibold text-gray-900 mb-1">${criteria.label}</h4>
                        <p class="text-gray-700 text-sm mb-2">${criteria.description}</p>
                        <p class="text-sm text-gray-700">
                            ${matchCount > 0 ? `✓ ${matchCount} evidence item(s) matched` : '○ No evidence matched yet'}
                        </p>
                    </div>
                `;
            });
            html += `
                    </div>
                </div>
            `;
            
            // Video Guide Questions
            html += `
                <div class="mb-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Video Guide Questions (${requirements.videoDuration})</h3>
                    <ol class="list-decimal list-inside space-y-3 ml-4">
            `;
            requirements.guideQuestions.forEach(question => {
                html += `<li class="text-gray-700">${question}</li>`;
            });
            html += `
                    </ol>
                </div>
            `;
            
            // Special Notes
            if (requirements.notes && requirements.notes.length > 0) {
                html += `
                    <div class="mb-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Special Notes</h3>
                        <ul class="list-disc list-inside space-y-2 ml-4">
                `;
                requirements.notes.forEach(note => {
                    html += `<li class="text-gray-700">${note}</li>`;
                });
                html += `
                        </ul>
                    </div>
                `;
            }
        }
        
        html += `</div>`;
    });
    
    reportContent.innerHTML = html;
    reportView.classList.remove('hidden');
    closeReportSelectionModal();
    
    // Scroll to top
    window.scrollTo(0, 0);
    
    // Automatically open print dialog after a short delay to ensure content is rendered
    setTimeout(() => {
        window.print();
    }, 300);
}

function closeReportView() {
    const reportView = document.getElementById('report-view');
    reportView.classList.add('hidden');
}

// Add CSS for page breaks and remove colors from print
const style = document.createElement('style');
style.textContent = `
    #report-view {
        color: black !important;
    }
    #report-view * {
        color: inherit !important;
        background-color: white !important;
    }
    #report-view .bg-gray-50,
    #report-view .bg-gray-100,
    #report-view .bg-gray-200,
    #report-view .bg-gray-300 {
        background-color: white !important;
    }
    #report-view .border-gray-200,
    #report-view .border-gray-300,
    #report-view .border-gray-400,
    #report-view .border-gray-500 {
        border-color: #000000 !important;
    }
    @media print {
        .page-break-before {
            page-break-before: always;
        }
        #report-view * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color: black !important;
            background: white !important;
        }
        #report-view .bg-gray-50,
        #report-view .bg-gray-100,
        #report-view .bg-gray-200,
        #report-view .bg-gray-300,
        #report-view .bg-blue-100,
        #report-view .bg-green-100 {
            background: white !important;
        }
        #report-view .border-gray-200,
        #report-view .border-gray-300,
        #report-view .border-gray-400,
        #report-view .border-gray-500,
        #report-view .border-blue-500,
        #report-view .border-green-500 {
            border-color: black !important;
        }
        #report-view .text-blue-800,
        #report-view .text-green-800,
        #report-view .text-red-600,
        #report-view .text-green-600 {
            color: black !important;
        }
    }
`;
document.head.appendChild(style);

// Close report selection modal on backdrop click
document.getElementById('report-selection-modal')?.addEventListener('click', (e) => {
    if (e.target.id === 'report-selection-modal') {
        closeReportSelectionModal();
    }
});
</script>
</body>
</html>

