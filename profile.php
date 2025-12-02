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

$profileData = [];
try {
    $pdo = getDatabaseConnection();
    if ($pdo instanceof FileBasedDatabase) {
        $profileData = $user;
    } else {
        // Ensure profile_picture column exists
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
            if ($stmt->rowCount() === 0) {
                $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) NULL DEFAULT NULL AFTER full_name");
            }
        } catch (Exception $e) {
            // Column might already exist, continue
        }
        
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $profileData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($profileData) {
            $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM awards WHERE user_id = ?');
            $stmt->execute([$user['id']]);
            $profileData['awards_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM events WHERE user_id = ?');
            $stmt->execute([$user['id']]);
            $profileData['events_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM documents WHERE user_id = ?');
            $stmt->execute([$user['id']]);
            $profileData['documents_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        }
    }
} catch (Exception $e) {
    error_log('Profile load error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>LILAC - User Profile</title>
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
<script>window.tailwind = window.tailwind || {};</script>
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
        .sidebar {
            width: 5rem;
            transition: width 0.3s ease;
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
    <aside class="sidebar bg-card-light dark:bg-card-dark border-r border-border-light dark:border-border-dark flex flex-col fixed h-full z-40 transition-all duration-300">
        <div class="flex items-center justify-start px-4 h-20 border-b border-border-light dark:border-border-dark flex-shrink-0">
            <div class="flex items-center gap-3 overflow-hidden">
                <img alt="CPU LILAC Logo" class="h-11 w-11 flex-shrink-0" src="./api/get-logo.php?v=1" width="44" height="44" onerror="this.style.display='none'; document.getElementById('logo-fallback').style.display='flex'; console.error('Logo failed to load:', this.src);"/>
                <div class="h-11 w-11 bg-primary rounded-lg flex items-center justify-center text-white font-bold text-sm flex-shrink-0" style="display: none;" id="logo-fallback">CPU</div>
                <h1 class="text-xl font-bold text-text-light dark:text-text-dark sidebar-logo-text whitespace-nowrap">LILAC</h1>
            </div>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2">
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="dashboard.php" title="Dashboard">
                <span class="material-symbols-outlined flex-shrink-0">dashboard</span>
                <span class="sidebar-text whitespace-nowrap">Dashboard</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="awards-hub.php" title="ICONS 2025 Hub">
                <span class="material-symbols-outlined flex-shrink-0">military_tech</span>
                <span class="sidebar-text whitespace-nowrap">ICONS 2025</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="awards.php" title="Awards Progress">
                <span class="material-symbols-outlined flex-shrink-0">emoji_events</span>
                <span class="sidebar-text whitespace-nowrap">Awards Progress</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="events-activities.php" title="Events & Activities">
                <span class="material-symbols-outlined flex-shrink-0">event</span>
                <span class="sidebar-text whitespace-nowrap">Events &amp; Activities</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="scheduler.php" title="Scheduler">
                <span class="material-symbols-outlined flex-shrink-0">calendar_today</span>
                <span class="sidebar-text whitespace-nowrap">Scheduler</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="mou-moa.php" title="MOUs & MOAs">
                <span class="material-symbols-outlined flex-shrink-0">handshake</span>
                <span class="sidebar-text whitespace-nowrap">MOUs &amp; MOAs</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="documents.php" title="Documents">
                <span class="material-symbols-outlined flex-shrink-0">description</span>
                <span class="sidebar-text whitespace-nowrap">Documents</span>
            </a>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="trash.php" title="Trash">
                <span class="material-symbols-outlined flex-shrink-0">delete</span>
                <span class="sidebar-text whitespace-nowrap">Trash</span>
            </a>
        </nav>
        <div class="px-4 py-4 border-t border-border-light dark:border-border-dark flex-shrink-0">
            <div class="flex items-center justify-between profile-container overflow-hidden">
                <div class="flex items-center gap-3 overflow-hidden">
                    <div class="w-10 h-10 rounded-full bg-cover bg-center sidebar-profile-picture flex-shrink-0" style='background-image: url("<?php echo !empty($profileData['profile_picture']) ? htmlspecialchars($profileData['profile_picture']) : 'https://lh3.googleusercontent.com/aida-public/AB6AXuC23fvgOSZIK6K5vguUgvVeU1XYFfp1LB3d4zICMvW6bispRl-eHHfnOtSsvRU3MgvmOpSYMCZhcSBIksvjlEHtkGMxuCFsQkuT0suo2-O9n3py7mlzFFETXCOIfvLVGGUj1aaG8ENOeDXXy_ifek2uG3R3--ghDflKvuAm9vrceoK8doav0lNYVbLz1bnWy6REWcrCPuPZZ8upfPqShoQpSDjICl16zMEcRuHzjt05z9cFITLKPdZTfMF-1dLK-klh8UhjeDeE4Q7p'; ?>");'></div>
                    <div class="sidebar-profile-info overflow-hidden">
                        <p class="font-semibold text-text-light dark:text-text-dark truncate"><?php echo htmlspecialchars($user['role'] === 'admin' ? 'Admin User' : $user['username']); ?></p>
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
        <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-sky-500 to-indigo-500 flex items-center justify-center">
            <span class="material-symbols-outlined text-white">person</span>
        </div>
        <div>
            <h1 class="text-2xl font-bold text-text-light dark:text-text-dark">User Profile</h1>
            <p class="text-sm text-text-muted-light dark:text-text-muted-dark">Manage your account information and preferences</p>
        </div>
    </div>
    <div class="flex items-center gap-2">
					<div class="relative z-[9999]">
                        <button id="notificationBtn" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-white/10 text-text-muted-light dark:text-text-muted-dark transition-colors duration-200 relative">
                            <span class="material-symbols-outlined">notifications</span>
                            <span id="notificationBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
                        </button>
                        <div id="notificationDropdown" class="absolute right-0 top-full mt-2 w-96 bg-white dark:bg-background-dark rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 z-[9999] hidden max-h-96 overflow-y-auto">
                            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Notifications</h3>
                                    <button id="markAllReadBtn" class="text-sm text-primary hover:text-primary/80 dark:text-primary-400 dark:hover:text-primary-300">
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
                                <button id="viewAllNotifications" class="w-full text-center text-sm text-primary hover:text-primary/80 dark:text-primary-400 dark:hover:text-primary-300">
                                    View all notifications
                                </button>
                            </div>
                        </div>
                    </div>
<button class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-white/10 text-text-muted-light dark:text-text-muted-dark transition-colors duration-200" id="theme-toggle">
<span class="material-symbols-outlined dark:hidden">light_mode</span>
<span class="material-symbols-outlined hidden dark:inline">dark_mode</span>
</button>
<button class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-red-100 dark:hover:bg-red-900/20 text-red-600 dark:text-red-400 transition-colors duration-200" id="logout-btn" title="Logout">
<span class="material-symbols-outlined">logout</span>
</button>
</div>
</header>
<div class="p-6 lg:p-8 content-animate">
<div class="max-w-4xl mx-auto">
<div class="bg-card-light dark:bg-card-dark p-6 sm:p-8 rounded-xl shadow-soft border border-border-light dark:border-border-dark">
<div class="flex flex-col sm:flex-row items-center gap-6 sm:gap-8">
<div class="relative">
<img id="profileAvatar" alt="User Profile Picture" class="w-32 h-32 rounded-full object-cover border-4 border-primary-500/50" src="<?php echo !empty($profileData['profile_picture']) ? htmlspecialchars($profileData['profile_picture']) : 'https://lh3.googleusercontent.com/aida-public/AB6AXuC23fvgOSZIK6K5vguUgvVeU1XYFfp1LB3d4zICMvW6bispRl-eHHfnOtSsvRU3MgvmOpSYMCZhcSBIksvjlEHtkGMxuCFsQkuT0suo2-O9n3py7mlzFFETXCOIfvLVGGUj1aaG8ENOeDXXy_ifek2uG3R3--ghDflKvuAm9vrceoK8doav0lNYVbLz1bnWy6REWcrCPuPZZ8upfPqShoQpSDjICl16zMEcRuHzjt05z9cFITLKPdZTfMF-1dLK-klh8UhjeDeE4Q7p'; ?>"/>
<button id="btnChangeAvatar" class="absolute bottom-1 right-1 bg-primary text-white p-1.5 rounded-full hover:bg-primary-600 transition-colors">
<span class="material-symbols-outlined text-base">photo_camera</span>
</button>
<input id="avatarInput" type="file" accept="image/*" class="hidden"/>
</div>
<div class="text-center sm:text-left">
<h2 id="profileName" class="text-2xl font-bold text-text-light dark:text-text-dark"><?php echo htmlspecialchars($profileData['username'] ?? 'User'); ?></h2>
<p id="profileRole" class="text-text-muted-light dark:text-text-muted-dark mt-1"><?php echo htmlspecialchars($profileData['role'] === 'admin' ? 'System Administrator' : ucfirst($profileData['role'] ?? 'User')); ?></p>
<p class="text-xs text-text-muted-light dark:text-text-muted-dark mt-1">Member since <?php echo isset($profileData['created_at']) ? date('F Y', strtotime($profileData['created_at'])) : 'N/A'; ?></p>
</div>
<div class="sm:ml-auto flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
<button id="btnEditProfile" class="flex items-center justify-center gap-2 w-full sm:w-auto bg-primary text-white font-semibold px-4 py-2.5 rounded-lg hover:bg-primary-600 transition-colors duration-200">
<span class="material-symbols-outlined">edit</span>
<span>Edit Profile</span>
</button>
</div>
</div>
<div class="border-t border-border-light dark:border-border-dark my-8"></div>
<div>
<h3 class="text-lg font-bold text-text-light dark:text-text-dark mb-4">Contact Information</h3>
<div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
<div class="flex items-center gap-3">
<div class="w-10 h-10 flex-shrink-0 bg-primary-50 dark:bg-primary-900/40 rounded-lg flex items-center justify-center">
<span class="material-symbols-outlined text-primary-500">email</span>
</div>
<div>
<p class="text-sm text-text-muted-light dark:text-text-muted-dark">Email</p>
<p id="profileEmail" class="font-medium text-text-light dark:text-text-dark"><?php echo htmlspecialchars($profileData['email'] ?? 'N/A'); ?></p>
</div>
</div>
<div class="flex items-center gap-3">
<div class="w-10 h-10 flex-shrink-0 bg-primary-50 dark:bg-primary-900/40 rounded-lg flex items-center justify-center">
<span class="material-symbols-outlined text-primary-500">badge</span>
</div>
<div>
<p class="text-sm text-text-muted-light dark:text-text-muted-dark">Username</p>
<p id="profileUsername" class="font-medium text-text-light dark:text-text-dark"><?php echo htmlspecialchars($profileData['username'] ?? 'N/A'); ?></p>
</div>
</div>
<div class="flex items-center gap-3">
<div class="w-10 h-10 flex-shrink-0 bg-primary-50 dark:bg-primary-900/40 rounded-lg flex items-center justify-center">
<span class="material-symbols-outlined text-primary-500">work</span>
</div>
<div>
<p class="text-sm text-text-muted-light dark:text-text-muted-dark">Department</p>
<p id="profileDepartment" class="font-medium text-text-light dark:text-text-dark"><?php echo htmlspecialchars($profileData['department'] ?? 'Not specified'); ?></p>
</div>
</div>
<div class="flex items-center gap-3">
<div class="w-10 h-10 flex-shrink-0 bg-primary-50 dark:bg-primary-900/40 rounded-lg flex items-center justify-center">
<span class="material-symbols-outlined text-primary-500">phone</span>
</div>
<div>
<p class="text-sm text-text-muted-light dark:text-text-muted-dark">Phone</p>
<p id="profilePhone" class="font-medium text-text-light dark:text-text-dark"><?php echo htmlspecialchars($profileData['phone'] ?? 'Not specified'); ?></p>
</div>
</div>
</div>
</div>
<div class="border-t border-border-light dark:border-border-dark my-8"></div>
<div>
<h3 class="text-lg font-bold text-text-light dark:text-text-dark mb-4">Security</h3>
<div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-card-dark rounded-lg border border-border-light dark:border-border-dark">
<div>
<p class="font-semibold text-text-light dark:text-text-dark">Password</p>
<p id="passwordChangedAt" class="text-sm text-text-muted-light dark:text-text-muted-dark">Last changed: 3 months ago</p>
</div>
<button id="btnChangePassword" class="flex items-center justify-center gap-2 text-primary-600 dark:text-primary-400 font-semibold px-4 py-2 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900/30 transition-colors duration-200">
<span class="material-symbols-outlined">lock_reset</span>
<span>Change Password</span>
</button>
</div>
</div>
</div>
</div>
</div>
</main>
</div>
<!-- Edit Profile Modal -->
<div id="editProfileModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-lg rounded-xl bg-card-light dark:bg-card-dark border border-border-light dark:border-border-dark shadow-soft">
        <div class="p-4 border-b border-border-light dark:border-border-dark flex items-center justify-between">
            <h3 class="font-semibold">Edit Profile</h3>
            <button id="closeEditProfile" class="p-2 rounded hover:bg-gray-100 dark:hover:bg-white/10"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form id="editProfileForm" class="p-4 space-y-4">
            <div>
                <label class="text-sm text-text-muted-light dark:text-text-muted-dark">Username</label>
                <input id="inputUsername" type="text" class="mt-1 w-full rounded-lg border border-border-light dark:border-border-dark bg-background-light dark:bg-background-dark px-3 py-2" required />
            </div>
            <div>
                <label class="text-sm text-text-muted-light dark:text-text-muted-dark">Email</label>
                <input id="inputEmail" type="email" class="mt-1 w-full rounded-lg border border-border-light dark:border-border-dark bg-background-light dark:bg-background-dark px-3 py-2" required />
            </div>
            <div>
                <label class="text-sm text-text-muted-light dark:text-text-muted-dark">Department</label>
                <input id="inputDepartment" type="text" class="mt-1 w-full rounded-lg border border-border-light dark:border-border-dark bg-background-light dark:bg-background-dark px-3 py-2" placeholder="e.g., International Affairs Office" />
            </div>
            <div>
                <label class="text-sm text-text-muted-light dark:text-text-muted-dark">Phone</label>
                <input id="inputPhone" type="tel" class="mt-1 w-full rounded-lg border border-border-light dark:border-border-dark bg-background-light dark:bg-background-dark px-3 py-2" placeholder="+63 XXX XXX XXXX" />
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" id="cancelEditProfile" class="px-4 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-white/10">Cancel</button>
                <button type="submit" id="submitEditProfile" class="px-4 py-2 rounded-lg bg-primary text-white hover:bg-primary-600">
                    <span class="flex items-center gap-2">
                        <span class="submit-text">Save Changes</span>
                        <span class="material-symbols-outlined text-sm hidden loading-icon animate-spin">progress_activity</span>
                    </span>
                </button>
            </div>
        </form>
    </div>
    </div>

<!-- Change Password Modal -->
<div id="changePasswordModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-md rounded-xl bg-card-light dark:bg-card-dark border border-border-light dark:border-border-dark shadow-soft">
        <div class="p-4 border-b border-border-light dark:border-border-dark flex items-center justify-between">
            <h3 class="font-semibold">Change Password</h3>
            <button id="closeChangePassword" class="p-2 rounded hover:bg-gray-100 dark:hover:bg-white/10"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form id="changePasswordForm" class="p-4 space-y-4">
            <div>
                <label class="text-sm text-text-muted-light dark:text-text-muted-dark">Current Password</label>
                <div class="relative mt-1">
                    <input id="currentPassword" type="password" class="w-full rounded-lg border border-border-light dark:border-border-dark bg-background-light dark:bg-background-dark pr-10" required />
                    <button type="button" id="toggleCurrentPwd" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 rounded hover:bg-gray-100 dark:hover:bg-white/10">
                        <span class="material-symbols-outlined text-base">visibility</span>
                    </button>
                </div>
            </div>
            <div>
                <label class="text-sm text-text-muted-light dark:text-text-muted-dark">New Password</label>
                <div class="relative mt-1">
                    <input id="newPassword" minlength="6" type="password" class="w-full rounded-lg border border-border-light dark:border-border-dark bg-background-light dark:bg-background-dark pr-10" required />
                    <button type="button" id="toggleNewPwd" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 rounded hover:bg-gray-100 dark:hover:bg-white/10">
                        <span class="material-symbols-outlined text-base">visibility</span>
                    </button>
                </div>
            </div>
            <div>
                <label class="text-sm text-text-muted-light dark:text-text-muted-dark">Confirm Password</label>
                <div class="relative mt-1">
                    <input id="confirmPassword" minlength="6" type="password" class="w-full rounded-lg border border-border-light dark:border-border-dark bg-background-light dark:bg-background-dark pr-10" required />
                    <button type="button" id="toggleConfirmPwd" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 rounded hover:bg-gray-100 dark:hover:bg-white/10">
                        <span class="material-symbols-outlined text-base">visibility</span>
                    </button>
                </div>
            </div>
            <p id="pwdError" class="text-sm text-red-600 hidden">Passwords do not match.</p>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" id="cancelChangePassword" class="px-4 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-white/10">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-primary text-white hover:bg-primary-600">Update</button>
            </div>
        </form>
    </div>
    </div>
<script>
        document.addEventListener('DOMContentLoaded', () => {
            const themeToggle = document.getElementById('theme-toggle');
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const appContainer = document.getElementById('app-container');
            const sidebarProfileInfo = document.querySelector('.sidebar-profile-info');
            const logoutBtn = document.getElementById('logout-btn');
            const sidebarProfilePicture = document.querySelector('.sidebar-profile-picture');
            const sidebarTexts = document.querySelectorAll('.sidebar-text');
            const sidebarLogoText = document.querySelector('.sidebar-logo-text');
            const sidebarToggleIconOpen = document.querySelector('.sidebar-toggle-icon-open');
            const sidebarToggleIconClosed = document.querySelector('.sidebar-toggle-icon-closed');
            const sidebarNavLinks = document.querySelectorAll('.sidebar-nav-link');
            const profileContainer = document.querySelector('.profile-container');
            const sidebarToggleContainer = document.querySelector('.sidebar-toggle-container');
            
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
                    
                    if (sidebarLogoText) sidebarLogoText.classList.remove('hidden');
                    sidebarTexts.forEach(text => text.classList.remove('hidden'));
                    if (sidebarProfileInfo) sidebarProfileInfo.classList.remove('hidden');
                    if (sidebarProfilePicture) sidebarProfilePicture.classList.remove('hidden');
                    if (sidebarToggleIconOpen) {
                        sidebarToggleIconOpen.classList.remove('hidden');
                        sidebarToggleIconOpen.classList.add('block');
                    }
                    if (sidebarToggleIconClosed) {
                        sidebarToggleIconClosed.classList.add('hidden');
                        sidebarToggleIconClosed.classList.remove('block');
                    }
                    sidebarNavLinks.forEach(link => link.classList.remove('justify-center'));
                    if (profileContainer) profileContainer.classList.remove('justify-center');
                    if (sidebarToggleContainer) sidebarToggleContainer.classList.remove('justify-center');
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
                    
                    if (sidebarLogoText) sidebarLogoText.classList.remove('hidden');
                    sidebarTexts.forEach(text => text.classList.remove('hidden'));
                    if (sidebarProfileInfo) sidebarProfileInfo.classList.remove('hidden');
                    if (sidebarProfilePicture) sidebarProfilePicture.classList.remove('hidden');
                    if (sidebarToggleIconOpen) {
                        sidebarToggleIconOpen.classList.remove('hidden');
                        sidebarToggleIconOpen.classList.add('block');
                    }
                    if (sidebarToggleIconClosed) {
                        sidebarToggleIconClosed.classList.add('hidden');
                        sidebarToggleIconClosed.classList.remove('block');
                    }
                    sidebarNavLinks.forEach(link => link.classList.remove('justify-center'));
                    if (profileContainer) profileContainer.classList.remove('justify-center');
                    if (sidebarToggleContainer) sidebarToggleContainer.classList.remove('justify-center');
                    
                    localStorage.setItem('sidebarCollapsed', 'false');
                } else {
                    // Collapse sidebar
                    appContainer.classList.add('sidebar-collapsed');
                    if (mainContent) {
                        mainContent.classList.remove('ml-64');
                        mainContent.classList.add('ml-20');
                    }
                    
                    if (sidebarLogoText) sidebarLogoText.classList.add('hidden');
                    sidebarTexts.forEach(text => text.classList.add('hidden'));
                    if (sidebarProfileInfo) sidebarProfileInfo.classList.add('hidden');
                    if (sidebarProfilePicture) sidebarProfilePicture.classList.add('hidden');
                    if (sidebarToggleIconOpen) {
                        sidebarToggleIconOpen.classList.add('hidden');
                        sidebarToggleIconOpen.classList.remove('block');
                    }
                    if (sidebarToggleIconClosed) {
                        sidebarToggleIconClosed.classList.remove('hidden');
                        sidebarToggleIconClosed.classList.add('block');
                    }
                    sidebarNavLinks.forEach(link => link.classList.add('justify-center'));
                    if (profileContainer) profileContainer.classList.add('justify-center');
                    if (sidebarToggleContainer) sidebarToggleContainer.classList.add('justify-center');
                    
                    localStorage.setItem('sidebarCollapsed', 'true');
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
            };
            
            // Check for saved theme in localStorage
            if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                toggleDarkMode(true);
            } else {
                toggleDarkMode(false);
            }
            
            // Event listener for theme toggle button
            themeToggle.addEventListener('click', () => {
                toggleDarkMode(!document.documentElement.classList.contains('dark'));
            });
            // Profile data from PHP/Database
            const profileData = <?php echo json_encode($profileData); ?>;

            const updateUI = (data) => {
                if (data.username) document.getElementById('profileName').textContent = data.username;
                if (data.email) document.getElementById('profileEmail').textContent = data.email;
                if (data.username) document.getElementById('profileUsername').textContent = data.username;
                if (data.department) document.getElementById('profileDepartment').textContent = data.department || 'Not specified';
                if (data.phone) document.getElementById('profilePhone').textContent = data.phone || 'Not specified';

                // Update role display
                const roleDisplay = data.role === 'admin' ? 'System Administrator' : (data.role ? data.role.charAt(0).toUpperCase() + data.role.slice(1) : 'User');
                document.getElementById('profileRole').textContent = roleDisplay;
            };

            // Initialize with server data
            if (profileData) {
                updateUI(profileData);
            }

            // Edit Profile modal logic
            const editModal = document.getElementById('editProfileModal');
            const btnEdit = document.getElementById('btnEditProfile');
            const closeEdit = document.getElementById('closeEditProfile');
            const cancelEdit = document.getElementById('cancelEditProfile');
            const formEdit = document.getElementById('editProfileForm');
            const submitBtn = document.getElementById('submitEditProfile');

            const openEdit = () => {
                // Load current data into form
                document.getElementById('inputUsername').value = profileData.username || '';
                document.getElementById('inputEmail').value = profileData.email || '';
                document.getElementById('inputDepartment').value = profileData.department || '';
                document.getElementById('inputPhone').value = profileData.phone || '';
                editModal.classList.remove('hidden');
                editModal.classList.add('flex');
            };

            const closeEditFn = () => {
                editModal.classList.add('hidden');
                editModal.classList.remove('flex');
            };

            btnEdit.addEventListener('click', openEdit);
            closeEdit.addEventListener('click', closeEditFn);
            cancelEdit.addEventListener('click', closeEditFn);

            formEdit.addEventListener('submit', async (e) => {
                e.preventDefault();

                // Disable button and show loading
                submitBtn.disabled = true;
                submitBtn.querySelector('.submit-text').textContent = 'Saving...';
                submitBtn.querySelector('.loading-icon').classList.remove('hidden');

                const updatedData = {
                    username: document.getElementById('inputUsername').value.trim(),
                    email: document.getElementById('inputEmail').value.trim(),
                    department: document.getElementById('inputDepartment').value.trim(),
                    phone: document.getElementById('inputPhone').value.trim()
                };

                try {
                    const response = await fetch('api/profile.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'update',
                            ...updatedData
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        // Update local data
                        Object.assign(profileData, updatedData);
                        updateUI(profileData);
                        closeEditFn();
                        alert('✓ Profile updated successfully!');
                        // Reload page to reflect changes
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        alert('✗ Error: ' + (result.error || 'Failed to update profile'));
                    }
                } catch (error) {
                    alert('✗ Error: ' + error.message);
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.querySelector('.submit-text').textContent = 'Save Changes';
                    submitBtn.querySelector('.loading-icon').classList.add('hidden');
                }
            });

            // Avatar change functionality
            const avatarBtn = document.getElementById('btnChangeAvatar');
            const avatarInput = document.getElementById('avatarInput');
            const profileAvatar = document.getElementById('profileAvatar');
            
            if (avatarBtn && avatarInput) {
                avatarBtn.addEventListener('click', () => {
                    avatarInput.click();
                });
                
                avatarInput.addEventListener('change', async (e) => {
                    const file = e.target.files[0];
                    if (!file) return;
                    
                    // Validate file type
                    if (!file.type.match('image.*')) {
                        alert('Please select an image file');
                        return;
                    }
                    
                    // Validate file size (max 5MB)
                    if (file.size > 5 * 1024 * 1024) {
                        alert('File size must be less than 5MB');
                        return;
                    }
                    
                    // Show loading state
                    avatarBtn.disabled = true;
                    avatarBtn.querySelector('span').textContent = 'hourglass_empty';
                    
                    // Create FormData
                    const formData = new FormData();
                    formData.append('profile_picture', file);
                    
                    try {
                        const response = await fetch('api/upload-profile-picture.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        // Check if response is OK
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            // Update profile avatar image immediately
                            if (result.profile_picture_url) {
                                // Add timestamp to prevent caching issues
                                const timestamp = new Date().getTime();
                                const imageUrl = result.profile_picture_url + (result.profile_picture_url.includes('?') ? '&' : '?') + 't=' + timestamp;
                                
                                profileAvatar.src = imageUrl;
                                profileAvatar.onerror = function() {
                                    // If image fails to load, try without timestamp
                                    this.src = result.profile_picture_url;
                                };
                                
                                // Update sidebar profile picture if it exists
                                const sidebarProfilePicture = document.querySelector('.sidebar-profile-picture');
                                if (sidebarProfilePicture) {
                                    sidebarProfilePicture.style.backgroundImage = `url('${imageUrl}')`;
                                }
                                
                                // Update profile data
                                profileData.profile_picture = result.profile_picture_url;
                                
                                // Force a page refresh after 1 second to ensure all pages show the new picture
                                setTimeout(() => {
                                    // Reload the page to ensure database changes are reflected everywhere
                                    window.location.reload();
                                }, 1000);
                            }
                            
                            alert('✓ Profile picture updated and saved to database successfully!');
                        } else {
                            alert('✗ Error: ' + (result.error || 'Failed to upload profile picture'));
                        }
                    } catch (error) {
                        console.error('Profile picture upload error:', error);
                        alert('✗ Error uploading profile picture: ' + error.message + '\n\nPlease try again or contact support if the problem persists.');
                    } finally {
                        avatarBtn.disabled = false;
                        avatarBtn.querySelector('span').textContent = 'photo_camera';
                        avatarInput.value = ''; // Reset input
                    }
                });
            }

            // Change password modal
            const pwdModal = document.getElementById('changePasswordModal');
            const btnPwd = document.getElementById('btnChangePassword');
            const closePwd = document.getElementById('closeChangePassword');
            const cancelPwd = document.getElementById('cancelChangePassword');
            const formPwd = document.getElementById('changePasswordForm');
            const pwdError = document.getElementById('pwdError');
            const openPwd = () => { pwdModal.classList.remove('hidden'); pwdModal.classList.add('flex'); pwdError.classList.add('hidden'); };
            const closePwdFn = () => { pwdModal.classList.add('hidden'); pwdModal.classList.remove('flex'); };
            btnPwd.addEventListener('click', openPwd);
            closePwd.addEventListener('click', closePwdFn);
            cancelPwd.addEventListener('click', closePwdFn);
            const clearPwdInputs = (focusCurrent = true) => {
                document.getElementById('currentPassword').value = '';
                document.getElementById('newPassword').value = '';
                document.getElementById('confirmPassword').value = '';
                if (focusCurrent) document.getElementById('currentPassword').focus();
            };

            // toggle visibility helpers
            const bindToggle = (btnId, inputId) => {
                const btn = document.getElementById(btnId);
                const input = document.getElementById(inputId);
                btn.addEventListener('click', () => {
                    const isPwd = input.type === 'password';
                    input.type = isPwd ? 'text' : 'password';
                    btn.querySelector('span').textContent = isPwd ? 'visibility_off' : 'visibility';
                });
            };
            bindToggle('toggleCurrentPwd', 'currentPassword');
            bindToggle('toggleNewPwd', 'newPassword');
            bindToggle('toggleConfirmPwd', 'confirmPassword');

            formPwd.addEventListener('submit', async (e) => {
                e.preventDefault();
                pwdError.classList.add('hidden');

                const current = document.getElementById('currentPassword').value;
                const next = document.getElementById('newPassword').value;
                const confirm = document.getElementById('confirmPassword').value;

                if (next !== confirm) {
                    pwdError.textContent = 'Passwords do not match.';
                    pwdError.classList.remove('hidden');
                    clearPwdInputs(false);
                    return;
                }

                if (next.length < 6) {
                    pwdError.textContent = 'Password must be at least 6 characters.';
                    pwdError.classList.remove('hidden');
                    return;
                }

                try {
                    const response = await fetch('api/profile.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'change_password',
                            current_password: current,
                            new_password: next
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        alert('✓ Password changed successfully!');
                        closePwdFn();
                        formPwd.reset();
                    } else {
                        pwdError.textContent = result.error || 'Failed to change password';
                        pwdError.classList.remove('hidden');
                        clearPwdInputs(true);
                    }
                } catch (error) {
                    pwdError.textContent = 'Error: ' + error.message;
                    pwdError.classList.remove('hidden');
                }
            });

            // Logout functionality
            if (logoutBtn) {
                logoutBtn.addEventListener('click', () => {
                    if (confirm('Are you sure you want to logout?')) {
                        localStorage.removeItem('isAuthenticated');
                        localStorage.removeItem('userData');
                        sessionStorage.clear();
                        window.location.href = 'index.php';
                    }
                });
            }
        });
    </script>

<script>
const API_BASE = 'api/profile.php';
const AUTH_TOKEN = '<?php echo $token; ?>';

window.updateProfile = async function(profileData) {
    try {
        const response = await fetch(API_BASE + '?action=update', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + AUTH_TOKEN,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(profileData)
        });

        const result = await response.json();

        if (result.success) {
            alert('✓ Profile updated successfully!');
            if (typeof loadProfile === 'function') loadProfile();
            return true;
        } else {
            alert('✗ Error: ' + (result.error || 'Update failed'));
            return false;
        }
    } catch (error) {
        alert('✗ Error: ' + error.message);
        return false;
    }
};

window.changePassword = async function(currentPassword, newPassword) {
    try {
        const response = await fetch(API_BASE + '?action=change_password', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + AUTH_TOKEN,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                current_password: currentPassword,
                new_password: newPassword
            })
        });

        const result = await response.json();

        if (result.success) {
            alert('✓ Password changed successfully!');
            return true;
        } else {
            alert('✗ Error: ' + (result.error || 'Password change failed'));
            return false;
        }
    } catch (error) {
        alert('✗ Error: ' + error.message);
        return false;
    }
};

window.loadProfile = async function() {
    try {
        const response = await fetch(API_BASE + '?action=get', {
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + AUTH_TOKEN
            }
        });

        const result = await response.json();

        if (result.success && result.profile) {
            renderProfile(result.profile);
        }
    } catch (error) {
        console.error('Load profile error:', error);
    }
};

function renderProfile(profile) {
    const usernameEl = document.getElementById('profileUsername');
    const emailEl = document.getElementById('profileEmail');
    const roleEl = document.getElementById('profileRole');

    if (usernameEl) usernameEl.textContent = profile.username || '';
    if (emailEl) emailEl.textContent = profile.email || '';
    if (roleEl) roleEl.textContent = profile.role || '';
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
                const actionHint = targetUrl ? '<p class="text-xs text-primary mt-2 font-semibold flex items-center gap-1">Open related record<span class="material-symbols-outlined text-sm">arrow_outward</span></p>' : '';
                
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
            refreshNotificationIndicators();
            
            // Refresh notifications every 5 minutes
            setInterval(() => {
                refreshNotificationIndicators();
            }, 5 * 60 * 1000);
        });
    })();
</script>

</body></html>
