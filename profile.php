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
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-text-light dark:text-text-dark">
<div class="flex h-screen sidebar-collapsed" id="app-container">
<aside class="sidebar bg-card-light dark:bg-card-dark border-r border-border-light dark:border-border-dark flex flex-col">
<div class="flex items-center justify-start px-4 h-20 border-b border-border-light dark:border-border-dark">
<div class="flex items-center gap-3">
<img alt="CPU LILAC Logo" class="h-11 w-11" src="../assets/images/cpu-logo.png?v=1" width="32" height="32" onerror="this.style.display='none'; document.getElementById('logo-fallback').style.display='flex'; console.error('Logo failed to load:', this.src);"/>
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

<a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="documents.php">
<span class="material-symbols-outlined">description</span>
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
<header class="sticky top-0 bg-background-light/80 dark:bg-background-dark/80 backdrop-blur-sm z-30 px-6 lg:px-8 py-4 border-b border-border-light dark:border-border-dark flex justify-between items-center h-20">
<h1 class="text-2xl font-bold text-text-light dark:text-text-dark">User Profile</h1>
<div class="flex items-center gap-2">
					<button class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-white/10 text-text-muted-light dark:text-text-muted-dark transition-colors duration-200">
<span class="material-symbols-outlined">notifications</span>
</button>
<button class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-white/10 text-text-muted-light dark:text-text-muted-dark transition-colors duration-200" id="theme-toggle">
<span class="material-symbols-outlined dark:hidden">light_mode</span>
<span class="material-symbols-outlined hidden dark:inline">dark_mode</span>
</button>
<button class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-red-100 dark:hover:bg-red-900/20 text-red-600 dark:text-red-400 transition-colors duration-200" id="logout-btn" title="Logout">
<span class="material-symbols-outlined">logout</span>
</button>
</div>
</header>
<div class="p-6 lg:p-8">
<div class="max-w-4xl mx-auto">
<div class="bg-card-light dark:bg-card-dark p-6 sm:p-8 rounded-xl shadow-soft border border-border-light dark:border-border-dark">
<div class="flex flex-col sm:flex-row items-center gap-6 sm:gap-8">
<div class="relative">
<img id="profileAvatar" alt="User Profile Picture" class="w-32 h-32 rounded-full object-cover border-4 border-primary-500/50" src="https://lh3.googleusercontent.com/aida-public/AB6AXuC23fvgOSZIK6K5vguUgvVeU1XYFfp1LB3d4zICMvW6bispRl-eHHfnOtSsvRU3MgvmOpSYMCZhcSBIksvjlEHtkGMxuCFsQkuT0suo2-O9n3py7mlzFFETXCOIfvLVGGUj1aaG8ENOeDXXy_ifek2uG3R3--ghDflKvuAm9vrceoK8doav0lNYVbLz1bnWy6REWcrCPuPZZ8upfPqShoQpSDjICl16zMEcRuHzjt05z9cFITLKPdZTfMF-1dLK-klh8UhjeDeE4Q7p"/>
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
            
            // Function to toggle sidebar
            const toggleSidebar = () => {
                appContainer.classList.toggle('sidebar-collapsed');
                appContainer.classList.toggle('sidebar-expanded');
                
                // Toggle profile info visibility
                if (sidebarProfileInfo) {
                    sidebarProfileInfo.classList.toggle('hidden');
                }
                if (sidebarProfilePicture) {
                    sidebarProfilePicture.classList.toggle('hidden');
                }
                
                // Toggle sidebar text visibility
                sidebarTexts.forEach(text => {
                    text.classList.toggle('hidden');
                });
                
                // Toggle logo text visibility
                if (sidebarLogoText) {
                    sidebarLogoText.classList.toggle('hidden');
                }
                
                // Toggle toggle icons
                if (sidebarToggleIconOpen) {
                    sidebarToggleIconOpen.classList.toggle('hidden');
                }
                if (sidebarToggleIconClosed) {
                    sidebarToggleIconClosed.classList.toggle('hidden');
                }
                
                // Toggle nav link justification
                sidebarNavLinks.forEach(link => {
                    link.classList.toggle('justify-center');
                });
                
                // Toggle container justification
                if (profileContainer) {
                    profileContainer.classList.toggle('justify-center');
                }
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

            // Avatar change (future enhancement)
            const avatarBtn = document.getElementById('btnChangeAvatar');
            if (avatarBtn) {
                avatarBtn.addEventListener('click', () => {
                    alert('Avatar upload feature coming soon!');
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

</body></html>
