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

$events = [];
try {
    $pdo = getDatabaseConnection();
    if ($pdo instanceof FileBasedDatabase) {
        $dataDir = __DIR__ . '/data/events/';
        if (is_dir($dataDir)) {
            $files = glob($dataDir . '*.json');
            foreach ($files as $file) {
                $data = json_decode(file_get_contents($file), true);
                if ($data) $events[] = $data;
            }
        }
    } else {
        // Load all events for calendar display (both admin and users see all events)
        $stmt = $pdo->query('SELECT e.*, u.username as created_by, u.full_name FROM events e LEFT JOIN users u ON e.user_id = u.id ORDER BY e.event_date DESC');
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log('Events load error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>LILAC Events & Activities</title>
<link crossorigin="" href="https://fonts.gstatic.com/" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
<link rel="icon" href="assets/images/cpu-logo.svg.png">
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
<link rel="stylesheet" href="css/award-analyzer.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="js/notifications.js"></script>
<script src="js/award-analyzer.js"></script>
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
        /* Add styles for expanded sidebar */
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
        .sidebar-collapsed main {
            margin-left: 0;
        }
        .main-content {
            padding-left: 0;
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

        /* Draggable modal styles */
        .dragging {
            user-select: none;
        }
        
        .dragging * {
            pointer-events: none;
        }
        
        #addEventModalHeader {
            cursor: move;
        }
        
        #addEventModalHeader:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .dark #addEventModalHeader:hover {
            background-color: rgba(255, 255, 255, 0.02);
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
<a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="dashboard.php" title="Dashboard">
<span class="material-symbols-outlined">dashboard</span>
<span class="sidebar-text hidden">Dashboard</span>
</a>
<a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-gray-100 dark:hover:bg-background-dark hover:text-text-light dark:hover:text-text-dark transition-colors duration-200 sidebar-nav-link" href="awards.php" title="Awards Progress">
<span class="material-symbols-outlined">emoji_events</span>
<span class="sidebar-text hidden">Awards Progress</span>
</a>
<a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg bg-blue-50 dark:bg-blue-900/40 text-primary-600 dark:text-primary-400 font-semibold sidebar-nav-link" href="events-activities.php" title="Events & Activities">
<span class="material-symbols-outlined filled">event</span>
<span class="sidebar-text hidden">Events & Activities</span>
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
<header class="sticky top-0 bg-background-light/80 dark:bg-background-dark/80 backdrop-blur-sm z-30 px-6 lg:px-8 py-4 border-b border-border-light dark:border-border-dark flex justify-between items-center h-20 overflow-visible header-animate">
<h1 class="text-2xl font-bold text-text-light dark:text-text-dark">Events & Activities</h1>
<div class="flex items-center gap-2">
<div class="relative z-[9999]">
<button id="notificationBell" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-200 dark:hover:bg-white/10 text-text-muted-light dark:text-text-muted-dark transition-colors duration-200">
<span class="material-symbols-outlined">notifications</span>
<!-- Notification badge -->
<span id="notificationBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
</button>

<!-- Notification dropdown -->
<div id="notificationDropdown" class="hidden absolute right-0 top-full mt-2 w-80 bg-white dark:bg-card-dark rounded-lg shadow-xl border border-border-light dark:border-border-dark z-[9999] max-h-96 overflow-y-auto">
<div class="p-4 border-b border-border-light dark:border-border-dark flex items-center justify-between">
<h3 class="font-semibold text-text-light dark:text-text-dark">Notifications</h3>
<button id="clearAllNotifications" class="text-xs text-primary hover:text-primary/80 transition-colors">Clear All</button>
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
<div class="p-2 content-animate">
  <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-6">
      <div id="upcomingEventsSection" class="bg-card-light dark:bg-card-dark p-6 rounded-xl shadow-soft hidden">
        <div class="flex justify-between items-center mb-4">
          <div>
            <h3 class="text-xl font-bold text-text-light dark:text-text-dark">Upcoming Events</h3>
            <?php if (!$isAdmin): ?>
            <p class="text-sm text-text-muted-light dark:text-text-muted-dark">Don't miss out—get notified for your favorite upcoming events.</p>
            <?php endif; ?>
          </div>
        </div>
        <div id="upcomingEventsContainer" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <!-- Upcoming events will be injected here -->
        </div>
      </div>

      

      <div id="completedEventsSection" class="bg-card-light dark:bg-card-dark p-6 rounded-xl shadow-soft hidden">
        <div class="flex justify-between items-center mb-4">
          <div>
            <h3 class="text-xl font-bold text-text-light dark:text-text-dark">Completed Events</h3>
            <p class="text-sm text-text-muted-light dark:text-text-muted-dark">Recent events that have already taken place.</p>
          </div>
          
      </div>
        <div id="completedEventsContainer" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <!-- Completed events will be injected here -->
        </div>
  </div>

      <!-- Events Table View -->
      <div class="bg-card-light dark:bg-card-dark p-6 rounded-xl shadow-soft">
        <div class="flex justify-between items-center mb-4">
          <div>
            <h3 class="text-xl font-bold text-text-light dark:text-text-dark">All Events</h3>
            <p class="text-sm text-text-muted-light dark:text-text-muted-dark">View all events in a table format</p>
          </div>
        </div>
        <div class="bg-card-light dark:bg-card-dark rounded-xl shadow-soft overflow-hidden border border-border-light dark:border-border-dark">
          <div class="overflow-x-auto">
            <table class="w-full divide-y divide-border-light dark:divide-border-dark">
              <thead class="bg-gray-50 dark:bg-white/5">
                <tr>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-text-muted-light dark:text-text-muted-dark uppercase tracking-wider">Event Name</th>
                  <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-text-muted-light dark:text-text-muted-dark uppercase tracking-wider">Date</th>
                  <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-text-muted-light dark:text-text-muted-dark uppercase tracking-wider">Time</th>
                  <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-text-muted-light dark:text-text-muted-dark uppercase tracking-wider">Location</th>
                  <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-text-muted-light dark:text-text-muted-dark uppercase tracking-wider">Status</th>
                  <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-text-muted-light dark:text-text-muted-dark uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody id="eventsTableBody" class="divide-y divide-border-light dark:divide-border-dark">
                <!-- Table rows will be populated dynamically -->
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>

    <div class="space-y-6">
      <!-- Add Event button - available to all authenticated users -->
      <div class="flex justify-end">
        <button id="addEventBtn" class="flex items-center gap-2 px-3 py-2 text-sm font-semibold text-white bg-primary rounded-lg hover:bg-primary/90 transition-colors">
          <span class="material-symbols-outlined">add</span>
          <span>Add Event</span>
        </button>
      </div>
      <div class="bg-card-light dark:bg-card-dark p-4 rounded-xl shadow-soft">
        <div class="flex justify-between items-center mb-4">
          <button id="prevMonth" class="p-1 rounded-full hover:bg-gray-100 dark:hover:bg-background-dark transition-colors">
            <span class="material-symbols-outlined">chevron_left</span>
          </button>
          <div class="relative">
            <button id="calendarTitle" type="button" class="flex items-center gap-1 px-3 py-1.5 rounded-lg border border-border-light dark:border-border-dark bg-background-light dark:bg-background-dark hover:bg-gray-50 dark:hover:bg-card-dark transition-colors font-semibold text-text-light dark:text-text-dark cursor-pointer">
              <span id="calendarTitleText">Loading...</span>
              <span class="material-symbols-outlined text-sm">expand_more</span>
            </button>
            <div id="monthYearDropdown" class="hidden absolute left-0 top-full mt-2 w-48 bg-white dark:bg-card-dark rounded-lg shadow-xl border border-border-light dark:border-border-dark z-50 max-h-80 overflow-y-auto">
              <!-- Month/Year options will be generated here -->
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button id="nextMonth" class="p-1 rounded-full hover:bg-gray-100 dark:hover:bg-background-dark transition-colors">
            <span class="material-symbols-outlined">chevron_right</span>
            </button>
          </div>
        </div>
        <div class="grid grid-cols-7 text-center text-xs text-text-muted-light dark:text-text-muted-dark" id="calendarGrid">
          <div class="py-2">S</div>
          <div class="py-2">M</div>
          <div class="py-2">T</div>
          <div class="py-2">W</div>
          <div class="py-2">T</div>
          <div class="py-2">F</div>
          <div class="py-2">S</div>
          <!-- Calendar days will be generated dynamically -->
        </div>
        <script>
          // Immediate update of calendar title and render calendar - runs as soon as this element is parsed
          (function() {
            function updateTitleAndRenderCalendar() {
              const titleEl = document.getElementById('calendarTitleText');
              if (titleEl && titleEl.textContent === 'Loading...') {
                const now = new Date();
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                titleEl.textContent = months[now.getMonth()] + ' ' + now.getFullYear();
              }
              
              // Also try to render calendar immediately if functions are available
              if (typeof window.renderCalendar === 'function' && typeof window.currentDate !== 'undefined') {
                try {
                  window.renderCalendar();
                  console.log('✅ Calendar rendered immediately from inline script');
                } catch (e) {
                  console.error('❌ Error rendering calendar from inline script:', e);
                }
              }
            }
            
            // Try immediately
            if (document.readyState === 'loading') {
              document.addEventListener('DOMContentLoaded', function() {
                setTimeout(updateTitleAndRenderCalendar, 200);
              });
            } else {
              setTimeout(updateTitleAndRenderCalendar, 200);
            }
            
            // Also try after delays to catch when functions are defined
            setTimeout(updateTitleAndRenderCalendar, 500);
            setTimeout(updateTitleAndRenderCalendar, 1000);
          })();
        </script>
        <div class="mt-3 pt-3 border-t border-border-light dark:border-border-dark">
          <div class="flex items-center justify-center gap-4 text-xs text-text-muted-light dark:text-text-muted-dark">
            <div class="flex items-center gap-1">
              <div class="w-1 h-1 bg-primary rounded-full"></div>
              <span id="scheduledEventsTitle">Scheduled Events</span>
            </div>
          </div>
        </div>
      </div>
      <div class="bg-card-light dark:bg-card-dark p-4 rounded-xl shadow-soft">
        <div id="todayEventsContainer" class="space-y-4">
          <!-- Today's events will be dynamically loaded here -->
        </div>
      </div>
      <!-- Popular Event removed -->
      <div class="hidden">
        <div class="flex justify-between items-center mb-4">
          <div>
            <h3 class="text-xl font-bold text-text-light dark:text-text-dark">Popular Event</h3>
            <p class="text-sm text-text-muted-light dark:text-text-muted-dark">These events are selling fast—join the crowd!</p>
          </div>
            
        </div>
        <div class="space-y-4">
          <div class="flex items-center gap-4">
            <img alt="Art Exhibition" class="w-16 h-16 object-cover rounded-lg" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAv0_OWoSDnuUvuNpwv6pHQPF_6_kjVJMntw1rTrEaK_h0uhsRNvK6fAzvjFZ7YLratne-m1Ysmcm6Ghzfo1CMq1ZH-vPo8sgSIIUKHiOmL_NuKptRDeF6MtLr0RiMppNYyEFtYC1yIgY-W6w6rqYXwZtgbk71jPww0S5iVMezgNFNHfe1SJaNMhBfiGdM1l_JBveuiQryjRAXj0DoZa36Tu8PZPLo1A4bIaOFqsWDxEcMk8nUAz6u4knynKEY1y25YMDrP2i60hvwh"/>
            <div class="flex-1">
              <p class="font-semibold text-text-light dark:text-text-dark">Art Exhibition</p>
              <p class="text-xs text-text-muted-light dark:text-text-muted-dark"><span class="material-symbols-outlined text-xs align-middle">calendar_today</span> June 10 <span class="material-symbols-outlined text-xs align-middle ml-2">schedule</span> 4:00 PM</p>
              <p class="text-xs text-text-muted-light dark:text-text-muted-dark"><span class="material-symbols-outlined text-xs align-middle">location_on</span> Paris, FR</p>
      </div>
            <p class="font-bold text-lg text-primary">$40 <span class="text-sm font-normal text-text-muted-light dark:text-text-muted-dark">/ticket</span></p>
    </div>
          <div class="flex items-center gap-4">
            <img alt="Future of AI" class="w-16 h-16 object-cover rounded-lg" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCGPeGTPD62y9D6O23CKkvIBndIHtFt4YP_DEZoItI0grinOA7lTx1rm5uSj6LTqEaNBqQxMbyYURSXJWXUXkp0ggM8nYYR3jAJJEN-CYyAAuTMWff00ahrBiyAlFe0TL1RNK5wd32ZIFbyggH_Yxx_f7BnxQFZpTDEgUWqUKGgu_enMdMsNcyI2MOSjy8mxIRh9RXPZ9HjVuEAHOAmduX9-3bUtGPKZQVKfF4d3eL6gghd450OnC5fsjhgJM5BAX1cF0mKmjf3SrxT"/>
            <div class="flex-1">
              <p class="font-semibold text-text-light dark:text-text-dark">Future of AI</p>
              <p class="text-xs text-text-muted-light dark:text-text-muted-dark"><span class="material-symbols-outlined text-xs align-middle">calendar_today</span> July 5 <span class="material-symbols-outlined text-xs align-middle ml-2">schedule</span> 5:00 PM</p>
              <p class="text-xs text-text-muted-light dark:text-text-muted-dark"><span class="material-symbols-outlined text-xs align-middle">location_on</span> Berlin, DE</p>
  </div>
            <p class="font-bold text-lg text-primary">$75 <span class="text-sm font-normal text-text-muted-light dark:text-text-muted-dark">/ticket</span></p>
</div>
          <div class="flex items-center gap-4">
            <img alt="Outdoor Festival" class="w-16 h-16 object-cover rounded-lg" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBvnGdmpp_wRbkMXjOYMPoCvwfD9x_20mxYnYF7qlPQyDxEXgmgm2rMjUg8k-iLNn5YgExBTU-aer8QElFFzTUQaAIDldZk6yvOZhQd7AAIeBS5-JIK1EjdLrRk02m8MJouOpqK6yUOPMYaq0mYI_6YcGRT3ujSzdMb4x_enn7NZv7Dmi-cbZt5snxdbE2z4cqwJm9_u5ik9ukTiBkODaj4YtemYUbMyVGRG-LRaZX68O0lkEkhnps3VUMeFd0L7oEitgGm_Pt6pppz"/>
            <div class="flex-1">
              <p class="font-semibold text-text-light dark:text-text-dark">Outdoor Festival</p>
              <p class="text-xs text-text-muted-light dark:text-text-muted-dark"><span class="material-symbols-outlined text-xs align-middle">calendar_today</span> July 8 <span class="material-symbols-outlined text-xs align-middle ml-2">schedule</span> 3:00 PM</p>
              <p class="text-xs text-text-muted-light dark:text-text-muted-dark"><span class="material-symbols-outlined text-xs align-middle">location_on</span> New York, US</p>
    </div>
            <p class="font-bold text-lg text-primary">$55 <span class="text-sm font-normal text-text-muted-light dark:text-text-muted-dark">/ticket</span></p>
      </div>
      </div>
      </div>
    </div>
  </div>
</div>
</main>
</div>
<!-- Add Event Modal -->
<div id="addEventModal" class="fixed inset-0 bg-black/30 hidden items-center justify-center z-[9999]">
  <div id="addEventModalCard" class="bg-white dark:bg-card-dark rounded-lg shadow-xl w-full max-w-md mx-4 max-h-[85vh] overflow-y-auto border border-border-light dark:border-border-dark relative">
    <!-- Modal Header with hamburger and close icons -->
    <div id="addEventModalHeader" class="flex justify-between items-center p-3 border-b border-border-light dark:border-border-dark cursor-move">
      <button class="p-2 hover:bg-gray-100 dark:hover:bg-white/10 rounded-full transition-colors">
        <span class="material-symbols-outlined text-text-muted-light dark:text-text-muted-dark">menu</span>
      </button>
      <button id="closeAddEvent" class="p-2 hover:bg-gray-100 dark:hover:bg-white/10 rounded-full transition-colors" data-no-drag>
        <span class="material-symbols-outlined text-text-muted-light dark:text-text-muted-dark">close</span>
      </button>
    </div>
    <div class="p-3">
      <input id="evTitle" type="text" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" class="w-full text-xl font-medium bg-transparent border-0 border-b border-border-light dark:border-border-dark focus:border-primary focus:ring-0 focus:outline-none pb-1.5 mb-2 placeholder:text-text-muted-light dark:placeholder:text-text-muted-dark" placeholder="Add title" />
      <div class="space-y-3">
        <div class="flex items-start gap-3">
          <span class="material-symbols-outlined text-text-muted-light dark:text-text-muted-dark mt-1">schedule</span>
          <div class="flex-1">
            <div class="flex items-center gap-2 flex-wrap relative" id="timeRangeContainer">
              <input id="evDate" type="date" autocomplete="off" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-full text-sm font-medium text-text-light dark:text-text-dark dark:bg-white/10 border-0 outline-none focus:outline-none ring-0 focus:ring-0" placeholder="mm/dd/yyyy" />
              <button id="evTimeRangeBtn" type="button" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-full text-sm font-medium text-text-light dark:text-text-dark dark:bg-white/10 border-0 outline-none">
                <span id="evTimeRangeText">--:-- -- - --:-- --</span>
              </button>
              <!-- Time range popover -->
              <div id="timeRangePopover" class="hidden absolute z-50 top-12 left-0 bg-white dark:bg-card-dark border border-border-light dark:border-border-dark rounded-xl shadow-xl w-72 max-h-80 overflow-y-auto">
                <div class="p-2 border-b border-border-light dark:border-border-dark flex items-center gap-2">
                  <button id="timeTabStart" class="px-3 py-1.5 rounded-lg text-sm font-medium bg-primary/10 text-primary">Start</button>
                  <button id="timeTabEnd" class="px-3 py-1.5 rounded-lg text-sm font-medium">End</button>
                </div>
                <div id="timeOptions" class="p-2 space-y-1"></div>
              </div>
            </div>
            
          </div>
        </div>
        
        <div class="flex items-center gap-3">
          <span class="material-symbols-outlined text-text-muted-light dark:text-text-muted-dark">place</span>
          <div class="flex-1">
            <input id="evLocation" type="text" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" class="w-full bg-transparent border-0 border-b border-transparent outline-none focus:outline-none ring-0 focus:ring-0 focus:border-border-light dark:focus:border-border-dark shadow-none focus:shadow-none text-sm transition-colors" placeholder="Add location (e.g., Iloilo)" style="box-shadow: none !important;" />
            <div id="locationSuggestions" class="hidden mt-2 rounded-lg border border-border-light dark:border-border-dark bg-white dark:bg-card-dark shadow-lg max-h-48 overflow-y-auto">
              <div id="suggestionsList" class="p-2 space-y-1">
                <!-- Location suggestions will appear here -->
              </div>
            </div>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <span class="material-symbols-outlined text-text-muted-light dark:text-text-muted-dark">description</span>
          <input id="evDesc" type="text" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" class="flex-1 bg-transparent border-0 border-b border-transparent outline-none focus:outline-none ring-0 focus:ring-0 focus:border-border-light dark:focus:border-border-dark shadow-none focus:shadow-none text-sm transition-colors" placeholder="Add description or a Google Drive attachment" style="box-shadow: none !important;" />
        </div>
        <div class="flex items-center gap-3">
          <span class="material-symbols-outlined text-text-muted-light dark:text-text-muted-dark">attach_file</span>
          <div class="flex-1">
            <button type="button" id="evAttachDocumentBtn" class="text-sm text-primary hover:text-primary/80 flex items-center gap-2">
              <span class="material-symbols-outlined text-sm">add</span>
              <span id="evAttachDocumentText">Add Supporting Document</span>
            </button>
            <input type="file" id="evDocumentFileInput" accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png" class="hidden" />
            <input type="hidden" id="evDocumentId" />
            <div id="evAttachedDocument" class="hidden mt-2 p-2 bg-gray-50 dark:bg-slate-800 rounded text-sm">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <span id="evAttachedDocumentName" class="text-text-light dark:text-text-dark"></span>
                  <span id="evAnalyzingStatus" class="hidden text-xs text-primary flex items-center gap-1">
                    <span class="material-symbols-outlined text-xs animate-spin">hourglass_empty</span>
                    Analyzing...
                  </span>
                </div>
                <button type="button" id="evRemoveDocument" class="text-red-500 hover:text-red-700">
                  <span class="material-symbols-outlined text-sm">close</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="flex items-center justify-end mt-3">
        <button id="saveAddEvent" class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary/90 font-medium">Save</button>
      </div>
    </div>
  </div>
  
</div>

<!-- Reminder Selection Modal -->
<div id="reminderModal" class="fixed inset-0 bg-black/30 hidden items-center justify-center z-[9999]">
  <div class="bg-white dark:bg-card-dark rounded-xl shadow-lg w-full max-w-md mx-4 border border-border-light dark:border-border-dark relative p-6">
    <button id="closeReminderModal" class="absolute top-3 right-3 rounded-full p-1.5 hover:bg-gray-100 dark:hover:bg-white/10">
      <span class="material-symbols-outlined">close</span>
    </button>
    <div class="flex justify-between items-start mb-4">
      <div>
        <h3 class="text-2xl font-bold text-text-light dark:text-text-dark">Set Reminder</h3>
        <p class="text-subtext-light dark:text-subtext-dark text-sm">Choose when to be reminded.</p>
      </div>
    </div>
    <div class="bg-background-light dark:bg-background-dark p-4 rounded-lg mb-6">
      <p class="font-semibold text-text-light dark:text-text-dark" id="reminderEventTitle"></p>
      <p class="text-sm text-subtext-light dark:text-subtext-dark" id="reminderEventDateTime"></p>
    </div>

    <div class="space-y-4">
      <p class="text-sm font-medium text-subtext-light dark:text-subtext-dark">QUICK REMINDERS</p>
      <div class="grid grid-cols-3 gap-2">
        <button type="button" class="flex flex-col items-center justify-center p-3 border border-border-light dark:border-border-dark rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:border-primary transition-colors text-center group" onclick="document.querySelector('input[name=reminderTime][value=custom]').checked=true; document.getElementById('customDays').value=0; document.getElementById('customTime').value='00:10';">
          <span class="material-symbols-outlined text-subtext-light dark:text-subtext-dark group-hover:text-primary">schedule</span>
          <span class="text-sm font-medium text-text-light dark:text-text-dark mt-1">10 min before</span>
        </button>
        <button type="button" class="flex flex-col items-center justify-center p-3 border border-border-light dark:border-border-dark rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:border-primary transition-colors text-center group" onclick="document.querySelector('input[name=reminderTime][value=1hour]').checked=true;">
          <span class="material-symbols-outlined text-primary">schedule</span>
          <span class="text-sm font-medium text-primary mt-1">1 hour before</span>
        </button>
        <button type="button" class="flex flex-col items-center justify-center p-3 border border-border-light dark:border-border-dark rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:border-primary transition-colors text-center group" onclick="document.querySelector('input[name=reminderTime][value=1day]').checked=true;">
          <span class="material-symbols-outlined text-subtext-light dark:text-subtext-dark group-hover:text-primary">event</span>
          <span class="text-sm font-medium text-text-light dark:text-text-dark mt-1">1 day before</span>
        </button>
      </div>

      <div>
        <p class="text-sm font-medium text-subtext-light dark:text-subtext-dark mt-6 mb-2">CUSTOM REMINDER</p>
        <div class="grid grid-cols-2 gap-4">
          <button type="button" class="flex items-center justify-between w-full p-3 border border-border-light dark:border-border-dark bg-background-light dark:bg-background-dark rounded-lg hover:border-primary transition-colors group" onclick="document.querySelector('input[name=reminderTime][value=custom]').checked=true; document.getElementById('customDate').showPicker && document.getElementById('customDate').showPicker();">
            <div class="flex items-center">
              <span class="material-symbols-outlined text-subtext-light dark:text-subtext-dark group-hover:text-primary mr-3">calendar_today</span>
              <div>
                <p class="text-xs font-medium text-subtext-light dark:text-subtext-dark">Date</p>
                <p class="text-sm font-semibold text-text-light dark:text-text-dark" id="customDatePreview">—</p>
              </div>
            </div>
            <span class="material-symbols-outlined text-subtext-light dark:text-subtext-dark">arrow_drop_down</span>
          </button>
          <button type="button" class="flex items-center justify-between w-full p-3 border border-border-light dark:border-border-dark bg-background-light dark:bg-background-dark rounded-lg hover:border-primary transition-colors group" onclick="document.querySelector('input[name=reminderTime][value=custom]').checked=true; document.getElementById('customTime').showPicker && document.getElementById('customTime').showPicker();">
            <div class="flex items-center">
              <span class="material-symbols-outlined text-subtext-light dark:text-subtext-dark group-hover:text-primary mr-3">schedule</span>
              <div>
                <p class="text-xs font-medium text-subtext-light dark:text-subtext-dark">Time</p>
                <p class="text-sm font-semibold text-text-light dark:text-text-dark" id="customTimePreview">—</p>
              </div>
            </div>
            <span class="material-symbols-outlined text-subtext-light dark:text-subtext-dark">arrow_drop_down</span>
          </button>
        </div>
      </div>

      <!-- Hidden actual controls to keep existing logic working -->
      <div class="sr-only">
        <label><input type="radio" name="reminderTime" value="1hour">1 hour</label>
        <label><input type="radio" name="reminderTime" value="1day">1 day</label>
        <label><input type="radio" name="reminderTime" value="1week">1 week</label>
        <label><input type="radio" name="reminderTime" value="custom">custom</label>
        <input type="date" id="customDate">
        <input type="time" id="customTime">
        <input type="number" id="customDays">
      </div>
    </div>

    <div class="flex justify-end space-x-3 mt-6">
      <button id="cancelReminder" class="px-4 py-2 rounded-lg text-sm font-semibold text-subtext-light dark:text-subtext-dark hover:bg-background-light dark:hover:bg-background-dark transition-colors">Cancel</button>
      <button id="setReminder" class="px-4 py-2 rounded-lg text-sm font-semibold bg-primary text-white hover:bg-blue-700 transition-colors">Set Reminder</button>
    </div>
  </div>
</div>

<script>
        // User role from PHP
        const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
        const currentUserId = <?php echo $_SESSION['user_id']; ?>;

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
                
                // Add a small delay for the chart to re-render after transition
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
            // Chart.js rendering
            const renderChart = () => {
                const canvas = document.getElementById('awardsProgressChart');
                if (!canvas) return; // no chart on this page
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
            if (typeof renderChart === 'function') renderChart();

            // Add Event modal handlers (restored)
            const addEventBtn = document.getElementById('addEventBtn');
            const addEventModal = document.getElementById('addEventModal');
            const closeAddEvent = document.getElementById('closeAddEvent');
            const cancelAddEvent = document.getElementById('cancelAddEvent');
            const saveAddEvent = document.getElementById('saveAddEvent');

            const openModal = () => {
                if (addEventModal) { addEventModal.classList.remove('hidden'); addEventModal.classList.add('flex'); }
            };
            let closeModal = () => {
                if (addEventModal) { addEventModal.classList.add('hidden'); addEventModal.classList.remove('flex'); }
            };
            addEventBtn && addEventBtn.addEventListener('click', openModal);
            closeAddEvent && closeAddEvent.addEventListener('click', closeModal);
            cancelAddEvent && cancelAddEvent.addEventListener('click', closeModal);

            // Draggable Add Event Modal
            const addEventModalHeader = document.getElementById('addEventModalHeader');
            const addEventModalCard = document.getElementById('addEventModalCard');
            if (addEventModalHeader && addEventModalCard && addEventModal) {
                let isDragging = false;
                let startX = 0, startY = 0, origLeft = 0, origTop = 0;

                function onMouseDown(e) {
                    // Ignore drags starting from non-draggable controls
                    const target = e.target;
                    if (target.closest('[data-no-drag]') || target.closest('button') || target.closest('input') || target.closest('select') || target.closest('a')) {
                        return;
                    }
                    isDragging = true;
                    addEventModal.classList.add('dragging');
                    const rect = addEventModalCard.getBoundingClientRect();
                    // Switch to absolute positioned card anchored to viewport
                    addEventModalCard.style.position = 'absolute';
                    addEventModalCard.style.left = `${rect.left}px`;
                    addEventModalCard.style.top = `${rect.top + window.scrollY}px`;
                    addEventModalCard.style.transform = 'none';
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
                    const cr = addEventModalCard.getBoundingClientRect();
                    const width = cr.width;
                    const height = cr.height;
                    nextLeft = Math.max(8, Math.min(nextLeft, vw - width - 8));
                    nextTop = Math.max(8 + window.scrollY, Math.min(nextTop, window.scrollY + vh - height - 8));
                    addEventModalCard.style.left = `${nextLeft}px`;
                    addEventModalCard.style.top = `${nextTop}px`;
                }

                function onMouseUp() {
                    isDragging = false;
                    addEventModal.classList.remove('dragging');
                    document.removeEventListener('mousemove', onMouseMove);
                    // Persist final position
                    const rect = addEventModalCard.getBoundingClientRect();
                    const saved = { left: rect.left, top: rect.top + window.scrollY };
                    localStorage.setItem('addEventModalPos', JSON.stringify(saved));
                }

                addEventModalHeader.addEventListener('mousedown', onMouseDown);
            }

            // Document attachment handler
            const evAttachDocumentBtn = document.getElementById('evAttachDocumentBtn');
            const evDocumentFileInput = document.getElementById('evDocumentFileInput');
            const evDocumentId = document.getElementById('evDocumentId');
            const evAttachedDocument = document.getElementById('evAttachedDocument');
            const evAttachedDocumentName = document.getElementById('evAttachedDocumentName');
            const evRemoveDocument = document.getElementById('evRemoveDocument');
            const evAttachDocumentText = document.getElementById('evAttachDocumentText');

            // Trigger file picker when button is clicked
            if (evAttachDocumentBtn && evDocumentFileInput) {
                evAttachDocumentBtn.addEventListener('click', () => {
                    evDocumentFileInput.click();
                });
            }

            // Handle file selection and upload
            if (evDocumentFileInput) {
                evDocumentFileInput.addEventListener('change', async (e) => {
                    const file = e.target.files[0];
                    if (!file) return;

                    // Validate file size (max 10MB)
                    const maxSize = 10 * 1024 * 1024;
                    if (file.size > maxSize) {
                        alert('File size exceeds 10MB limit');
                        evDocumentFileInput.value = '';
                        return;
                    }

                    // Show uploading state
                    evAttachedDocumentName.textContent = 'Uploading...';
                    evAttachedDocument.classList.remove('hidden');
                    evAttachDocumentText.textContent = 'Change Document';
                    evAttachDocumentBtn.disabled = true;

                    try {
                        const formData = new FormData();
                        formData.append('file', file);
                        formData.append('title', file.name.replace(/\.[^/.]+$/, ''));
                        formData.append('description', '');
                        formData.append('category', 'Other Documents');
                        formData.append('source_page', 'events');

                        const uploadResponse = await fetch('api/other-documents.php', {
                            method: 'POST',
                            body: formData
                        });

                        if (!uploadResponse.ok) {
                            const text = await uploadResponse.text();
                            throw new Error(`Upload failed: ${text}`);
                        }

                        const uploadResult = await uploadResponse.json();

                        if (!uploadResult.success) {
                            throw new Error(uploadResult.error || 'Upload failed');
                        }

                        // Attach the uploaded document to the event
                        const docId = uploadResult.data.id;
                        const docName = uploadResult.data.title || uploadResult.data.file_name;
                        const docPath = uploadResult.data.file_path;

                        evDocumentId.value = docId;
                        evAttachedDocumentName.textContent = docName;
                        evAttachedDocument.classList.remove('hidden');
                        evAttachDocumentText.textContent = 'Change Document';
                        evAttachDocumentBtn.disabled = false;
                    } catch (error) {
                        console.error('Upload error:', error);
                        alert('Error uploading document: ' + error.message);
                        evAttachedDocument.classList.add('hidden');
                        evAttachDocumentText.textContent = 'Add Supporting Document';
                        evAttachDocumentBtn.disabled = false;
                        evDocumentFileInput.value = '';
                    }
                });
            }

            if (evRemoveDocument) {
                evRemoveDocument.addEventListener('click', () => {
                    evDocumentId.value = '';
                    evAttachedDocument.classList.add('hidden');
                    evAttachDocumentText.textContent = 'Add Supporting Document';
                });
            }

            // Clear document attachment when modal closes
            const originalCloseModal = closeModal;
            closeModal = () => {
                if (evDocumentId) evDocumentId.value = '';
                if (evAttachedDocument) evAttachedDocument.classList.add('hidden');
                if (evAttachDocumentText) evAttachDocumentText.textContent = 'Add Supporting Document';
                originalCloseModal();
            };

            saveAddEvent && saveAddEvent.addEventListener('click', async () => {
                // Get event data from modal
                const title = document.getElementById('evTitle').value || 'Untitled Event';
                const date = document.getElementById('evDate').value;
                const timeRange = document.getElementById('evTimeRangeText').textContent || '--:-- -- - --:-- --';
                const location = document.getElementById('evLocation').value || '';
                const description = document.getElementById('evDesc').value || '';
                
                // Validate required fields
                if (!title.trim() || !date) {
                    showToast('Please fill in the event title and date.', 'warning');
                    return;
                }
                
                // Parse time range to get start and end times
                const timeMatch = timeRange.match(/(\d{1,2}:\d{2})\s*(AM|PM)?\s*-\s*(\d{1,2}:\d{2})\s*(AM|PM)?/i);
                let startTime = '09:00:00';
                let endTime = '17:00:00';
                
                if (timeMatch) {
                    startTime = parseTime(timeMatch[1], timeMatch[2]);
                    endTime = parseTime(timeMatch[3], timeMatch[4]);
                }
                
                // Create event object for database
                const eventData = {
                    title: title.trim(),
                    type: 'event',
                    location: location.trim(),
                    start_time: startTime,
                    end_time: endTime,
                    date: date,
                    category: 'General',
                    description: description.trim(),
                    image_url: getRandomEventImage(),
                    eligible_for_awards: 0
                };
                
                try {
                    // Decide if API calls are allowed (same rules as loaders)
                    const serverEnabled = (localStorage.getItem('enableServerAPI') === '1');
                    const isFileProtocol = window.location.protocol === 'file:';
                    const isPhpServer = window.location.protocol === 'http:' && (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1');
                    
                    // Auto-enable server API if running on localhost/127.0.0.1
                    if (isPhpServer && !serverEnabled) {
                        localStorage.setItem('enableServerAPI', '1');
                        console.log('Auto-enabled server API for localhost/127.0.0.1');
                    }
                    
                    if (isFileProtocol || !isPhpServer) {
                        console.warn('Server API unavailable. Saving event locally to browser storage.');
                        // Fallback: save to localStorage in the same shape used by renderers
                        const savedEvents = JSON.parse(localStorage.getItem('upcomingEvents') || '[]');
                        const localEvent = {
                            id: Date.now(),
                            title: eventData.title,
                            date: eventData.date,
                            timeRange: `${timeMatch ? timeMatch[1] : '09:00'}${timeMatch && timeMatch[2] ? ' ' + timeMatch[2].toUpperCase() : ''} - ${timeMatch ? timeMatch[3] : '05:00'}${timeMatch && timeMatch[4] ? ' ' + timeMatch[4].toUpperCase() : ''}`,
                            location: eventData.location,
                            description: eventData.description,
                            imageUrl: eventData.image_url || getRandomEventImage(),
                            createdAt: new Date().toISOString()
                        };
                        savedEvents.push(localEvent);
                        localStorage.setItem('upcomingEvents', JSON.stringify(savedEvents));
                        if (typeof loadUpcomingEvents === 'function') loadUpcomingEvents();
                        if (typeof loadTodayEvents === 'function') loadTodayEvents();
                        if (typeof loadCompletedEvents === 'function') loadCompletedEvents();
                        closeModal();
                        return;
                    }
                    
                    // Get document ID if attached
                    const documentId = document.getElementById('evDocumentId').value || null;
                    
                    // Save to database
                    const eventPayload = {
                        title: eventData.title,
                        description: eventData.description,
                        event_date: eventData.date,
                        start_time: eventData.start_time,
                        end_time: eventData.end_time,
                        location: eventData.location,
                        status: 'planned',
                        document_id: documentId
                    };

                    const response = await fetch('api/events.php?action=create', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(eventPayload)
                    });
                    
                    let result;
                    if (response.ok) {
                        const responseText = await response.text();
                        try {
                            result = JSON.parse(responseText);
                        } catch (parseError) {
                            console.error('Failed to parse save response as JSON:', parseError);
                            console.log('Response was:', responseText);
                            throw new Error('API returned invalid JSON');
                        }
                    } else {
                        const respText = await response.text().catch(()=> '');
                        throw new Error(`HTTP ${response.status}: ${response.statusText}${respText ? ' - ' + respText : ''}`);
                    }
                    
                    if (response.ok && result.success) {
                        // Database save successful
                        console.log('Event saved to database:', result);
                        // Optimistic local update to reflect immediately
                        try {
                            const savedEvents = JSON.parse(localStorage.getItem('upcomingEvents') || '[]');
                            const optimisticEvent = {
                                id: result.id || Date.now(),
                                title: eventData.title,
                                date: eventData.date,
                                timeRange: `${timeMatch ? timeMatch[1] : '09:00'}${timeMatch && timeMatch[2] ? ' ' + timeMatch[2].toUpperCase() : ''} - ${timeMatch ? timeMatch[3] : '05:00'}${timeMatch && timeMatch[4] ? ' ' + timeMatch[4].toUpperCase() : ''}`,
                                location: eventData.location,
                                description: eventData.description,
                                imageUrl: eventData.image_url || getRandomEventImage(),
                                createdAt: new Date().toISOString()
                            };
                            savedEvents.push(optimisticEvent);
                            localStorage.setItem('upcomingEvents', JSON.stringify(savedEvents));
                            if (typeof loadUpcomingEvents === 'function') loadUpcomingEvents();
                            if (typeof loadTodayEvents === 'function') loadTodayEvents();
                            if (typeof loadCompletedEvents === 'function') loadCompletedEvents();
                        } catch {}
                        
                        // Re-fetch from DB so UI mirrors server state
                        if (typeof loadEventsFromDatabase === 'function') {
                            await loadEventsFromDatabase();
                        }
                        
                        // Clear form
                        document.getElementById('evTitle').value = '';
                        document.getElementById('evDate').value = '';
                        document.getElementById('evTimeRangeText').textContent = '--:-- -- - --:-- --';
                        document.getElementById('evLocation').value = '';
                        document.getElementById('evDesc').value = '';
                        if (evDocumentId) evDocumentId.value = '';
                        if (evAttachedDocument) evAttachedDocument.classList.add('hidden');
                        if (evAttachDocumentText) evAttachDocumentText.textContent = 'Add Supporting Document';
                        
                        // Close modal
                        closeModal();
                        
                    } else {
                        throw new Error(result.error || 'Failed to save event to database');
                    }
                    
                } catch (error) {
                    console.error('Database save failed, saving locally instead:', error);
                    // Fallback: save to localStorage so the UI still updates
                    try {
                        const savedEvents = JSON.parse(localStorage.getItem('upcomingEvents') || '[]');
                        const localEvent = {
                            id: Date.now(),
                            title: eventData.title,
                            date: eventData.date,
                            timeRange: `${timeMatch ? timeMatch[1] : '09:00'}${timeMatch && timeMatch[2] ? ' ' + timeMatch[2].toUpperCase() : ''} - ${timeMatch ? timeMatch[3] : '05:00'}${timeMatch && timeMatch[4] ? ' ' + timeMatch[4].toUpperCase() : ''}`,
                            location: eventData.location,
                            description: eventData.description,
                            imageUrl: eventData.image_url || getRandomEventImage(),
                            createdAt: new Date().toISOString()
                        };
                        savedEvents.push(localEvent);
                        localStorage.setItem('upcomingEvents', JSON.stringify(savedEvents));
                        if (typeof loadUpcomingEvents === 'function') loadUpcomingEvents();
                        if (typeof loadTodayEvents === 'function') loadTodayEvents();
                        if (typeof loadCompletedEvents === 'function') loadCompletedEvents();
                        closeModal();
                    } catch (e) {
                        showToast('Failed to save event. If you are not running on localhost, events will not persist.', 'error');
                    }
                }
            });

            // Helper function to parse time format
            function parseTime(timeStr, ampm) {
                if (!timeStr) return '09:00:00';
                
                let [hours, minutes] = timeStr.split(':');
                hours = parseInt(hours);
                minutes = parseInt(minutes) || 0;
                
                // Handle AM/PM
                if (ampm && ampm.toUpperCase() === 'PM' && hours !== 12) {
                    hours += 12;
                } else if (ampm && ampm.toUpperCase() === 'AM' && hours === 12) {
                    hours = 0;
                }
                
                return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:00`;
            }

            // Function to create a fallback data URL image
            function createFallbackImage(title) {
                const canvas = document.createElement('canvas');
                canvas.width = 400;
                canvas.height = 200;
                const ctx = canvas.getContext('2d');
                
                // Create gradient background
                const gradient = ctx.createLinearGradient(0, 0, 400, 200);
                gradient.addColorStop(0, '#137fec');
                gradient.addColorStop(1, '#0c4c8d');
                
                ctx.fillStyle = gradient;
                ctx.fillRect(0, 0, 400, 200);
                
                // Add text
                ctx.fillStyle = 'white';
                ctx.font = 'bold 24px Inter, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                
                // Wrap text if too long
                const words = title.toUpperCase().split(' ');
                let line = '';
                let y = 80;
                
                for (let i = 0; i < words.length; i++) {
                    const testLine = line + words[i] + ' ';
                    const metrics = ctx.measureText(testLine);
                    const testWidth = metrics.width;
                    
                    if (testWidth > 350 && i > 0) {
                        ctx.fillText(line, 200, y);
                        line = words[i] + ' ';
                        y += 30;
                    } else {
                        line = testLine;
                    }
                }
                ctx.fillText(line, 200, y);
                
                // Add event icon
                ctx.font = '48px Material Symbols Outlined';
                ctx.fillText('📅', 200, 120);
                
                return canvas.toDataURL();
            }

            // Function to get unique random event image
            function getRandomEventImage() {
                // Use local assets from Events & Activities folder
                const eventImages = [
                    './assets/Events & Activities/1.jpg',
                    './assets/Events & Activities/2.jpg',
                    './assets/Events & Activities/3.jpg',
                    './assets/Events & Activities/4.jpg',
                    './assets/Events & Activities/5.png',
                    './assets/Events & Activities/6.jpg'
                ];
                
                console.log('Using local event images:', eventImages);
                
                // Get currently used images from stored events
                const savedEvents = JSON.parse(localStorage.getItem('upcomingEvents') || '[]');
                const usedImages = new Set();
                
                // Add images from stored events
                savedEvents.forEach(event => {
                    if (event.imageUrl) {
                        usedImages.add(event.imageUrl);
                    }
                });
                
                // Filter out used images
                const availableImages = eventImages.filter(img => !usedImages.has(img));
                
                // If all images are used, reset and use any image
                if (availableImages.length === 0) {
                    return eventImages[Math.floor(Math.random() * eventImages.length)];
                }
                
                // Return a random image from available images
                return availableImages[Math.floor(Math.random() * availableImages.length)];
            }

            // Function to add event to upcoming events section
            function addEventToUpcomingSection(eventData) {
                const upcomingEventsContainer = document.getElementById('upcomingEventsContainer');
                if (!upcomingEventsContainer) return;
                
                // FORCE use local images - ignore any stored external URLs
                let eventImage = getRandomEventImage();
                
                console.log('Forcing local image for event:', eventData.title, 'Image:', eventImage);
                
                // Create event card
                const eventCard = document.createElement('div');
                eventCard.className = 'space-y-3';
                eventCard.setAttribute('data-dynamic', 'true');
                eventCard.setAttribute('data-event-id', eventData.id);
                eventCard.innerHTML = `
                    <div class="w-full h-32 bg-gradient-to-br from-primary/20 to-primary/40 rounded-lg overflow-hidden relative">
                        <img alt="${eventData.title} banner" 
                             class="w-full h-full object-cover rounded-lg transition-opacity duration-300" 
                             src="${eventImage}"
                             onerror="console.error('Failed to load image:', this.src); this.style.display='none'; this.nextElementSibling.style.display='flex';"
                             onload="console.log('Successfully loaded image:', this.src); this.nextElementSibling.style.display='none';"
                             loading="lazy"/>
                        <div class="hidden absolute inset-0 bg-gradient-to-br from-primary/30 to-primary/50 rounded-lg flex items-center justify-center">
                            <div class="text-center text-white">
                                <span class="material-symbols-outlined text-4xl mb-2">event</span>
                                <p class="text-sm font-medium">${eventData.title.toUpperCase()}</p>
                            </div>
                        </div>
                    </div>
                    <h4 class="font-semibold text-text-light dark:text-text-dark">${eventData.title.toUpperCase()}</h4>
                    <p class="text-xs text-text-muted-light dark:text-text-muted-dark"><span class="material-symbols-outlined text-sm align-middle">location_on</span> ${eventData.location || 'Location not specified'}</p>
                    <p class="text-xs text-text-muted-light dark:text-text-muted-dark"><span class="material-symbols-outlined text-sm align-middle">calendar_today</span> ${formatEventDate(eventData.date)} ⋅ ${eventData.timeRange}</p>
                    ${isAdmin ? `
                        <div class="flex gap-2">
                            <button class="flex-1 bg-green-500/10 text-green-600 dark:text-green-400 font-semibold py-2 rounded-lg hover:bg-green-500/20 transition-colors text-sm update-event-btn" data-event-id="${eventData.id}" title="Update Event">
                                <span class="material-symbols-outlined text-sm align-middle">edit</span>
                            </button>
                            <button class="flex-1 bg-yellow-500/10 text-yellow-600 dark:text-yellow-400 font-semibold py-2 rounded-lg hover:bg-yellow-500/20 transition-colors text-sm cancel-event-btn" data-event-id="${eventData.id}" title="Cancel Event">
                                <span class="material-symbols-outlined text-sm align-middle">cancel</span>
                            </button>
                            <button class="flex-1 bg-red-500/10 text-red-600 dark:text-red-400 font-semibold py-2 rounded-lg hover:bg-red-500/20 transition-colors text-sm delete-event-btn" data-event-id="${eventData.id}" title="Delete Event">
                                <span class="material-symbols-outlined text-sm align-middle">delete</span>
                            </button>
                        </div>
                    ` : `
                        <button class="w-full bg-primary/10 text-primary font-semibold py-2 rounded-lg hover:bg-primary/20 transition-colors remind-me-btn" data-event-id="${eventData.id}" data-event-title="${eventData.title}" data-event-date="${eventData.date}" data-event-time="${eventData.timeRange}">Remind Me</button>
                    `}
                `;
                
                // Add to the container
                upcomingEventsContainer.appendChild(eventCard);
                
                // Limit to 6 events maximum (remove oldest dynamic events if needed)
                const dynamicEvents = upcomingEventsContainer.querySelectorAll('[data-dynamic="true"]');
                if (dynamicEvents.length > 6) {
                    dynamicEvents[0].remove(); // Remove the oldest (first) event
                }
            }

            // Function to format event date
            function formatEventDate(dateString) {
                const date = new Date(dateString);
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                return `${months[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
            }

            // Parse a YYYY-MM-DD or other date string to a local Date at 00:00
            function parseDateOnly(dateString) {
                if (!dateString) return new Date('Invalid Date');
                // Prefer explicit midnight to avoid timezone shifting the day
                // If date already contains a time, new Date will respect it
                const isoMatch = /^\d{4}-\d{2}-\d{2}$/;
                if (isoMatch.test(dateString)) {
                    return new Date(`${dateString}T00:00:00`);
                }
                return new Date(dateString);
            }

            // Function to load existing events from database (via in-memory data)
            function loadUpcomingEvents() {
                // Use in-memory events if available, otherwise fetch from database
                if (window.currentEvents) {
                    loadUpcomingEventsFromData(window.currentEvents);
                    return;
                }
                // If no in-memory data, fetch from database
                if (typeof loadEventsFromDatabase === 'function') {
                    loadEventsFromDatabase();
                    return;
                }
                // Fallback: show empty state
                const upcomingEventsContainer = document.getElementById('upcomingEventsContainer');
                if (upcomingEventsContainer) {
                    upcomingEventsContainer.innerHTML = '<div class="col-span-3 text-center text-text-muted-light dark:text-text-muted-dark py-6">No upcoming events yet.</div>';
                }
            }
            
            // Helper function to load upcoming events from data array
            function loadUpcomingEventsFromData(events) {
                const savedEvents = Array.isArray(events) ? events : [];
                console.log('loadUpcomingEventsFromData - events:', savedEvents);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                console.log('loadUpcomingEventsFromData - today date:', today.toISOString().split('T')[0]);
                const futureEvents = savedEvents
                    .filter(event => {
                        const eventDate = parseDateOnly(event.date);
                        const isFuture = eventDate >= today;
                        console.log(`Event "${event.title}" (${event.date}): isFuture = ${isFuture}`);
                        return isFuture;
                    })
                    .sort((a, b) => new Date(a.date) - new Date(b.date))
                    .slice(0, 3);
                console.log('loadUpcomingEventsFromData - futureEvents after filtering:', futureEvents);
                const upcomingSection = document.getElementById('upcomingEventsSection');
                const upcomingEventsContainer = document.getElementById('upcomingEventsContainer');
                if (!upcomingEventsContainer) return;
                // Always clear any static/demo content
                upcomingEventsContainer.innerHTML = '';
                if (futureEvents.length === 0) {
                    // Show empty state
                    const empty = document.createElement('div');
                    empty.className = 'col-span-3 text-center text-text-muted-light dark:text-text-muted-dark py-6';
                    empty.textContent = 'No upcoming events yet.';
                    upcomingEventsContainer.appendChild(empty);
                    if (upcomingSection) upcomingSection.classList.remove('hidden');
                    return;
                }
                // We have future events; show section and render
                if (upcomingSection) upcomingSection.classList.remove('hidden');
                futureEvents.forEach(event => addEventToUpcomingSection(event));
            }

            // Function to load completed events (past events) from database
            function loadCompletedEvents() {
                // Use in-memory events if available, otherwise fetch from database
                if (window.currentEvents) {
                    loadCompletedEventsFromData(window.currentEvents);
                    return;
                }
                // If no in-memory data, fetch from database
                if (typeof loadEventsFromDatabase === 'function') {
                    loadEventsFromDatabase();
                    return;
                }
            }
            
            // Helper function to load completed events from data array
            function loadCompletedEventsFromData(events) {
                let savedEvents = Array.isArray(events) ? events : [];
                // Sanitize events to remove any seeded demo/test items
                const sanitized = savedEvents.filter(ev => !/\btest\b/i.test((ev.title || '')));
                savedEvents = sanitized;
                
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                // Filter past events and sort by date (most recent first)
                const pastEvents = savedEvents
                    .filter(event => parseDateOnly(event.date) < today)
                    .sort((a, b) => new Date(b.date) - new Date(a.date))
                    .slice(0, 6); // Show up to 6 completed events
                
                const completedSection = document.getElementById('completedEventsSection');
                const completedEventsContainer = document.getElementById('completedEventsContainer');
                if (!completedEventsContainer) return;
                
                // If we have past events, replace the static content
                // Always replace content
                completedEventsContainer.innerHTML = '';
                if (pastEvents.length === 0) {
                    // Show the section with an empty state when there are no past events
                    if (completedSection) completedSection.classList.remove('hidden');
                    const empty = document.createElement('div');
                    empty.className = 'col-span-3 text-center text-text-muted-light dark:text-text-muted-dark py-6';
                    empty.textContent = 'No completed events yet.';
                    completedEventsContainer.appendChild(empty);
                    return;
                }
                // We have past events; show the section
                if (completedSection) completedSection.classList.remove('hidden');
                // Add past events
                pastEvents.forEach(event => { addCompletedEventCard(event); });
            }
            
            // Function to add a completed event card
            function addCompletedEventCard(eventData) {
                const completedEventsContainer = document.getElementById('completedEventsContainer');
                if (!completedEventsContainer) return;
                
                // FORCE use local images - ignore any stored external URLs
                const eventImage = getRandomEventImage();
                
                console.log('Forcing local image for completed event:', eventData.title, 'Image:', eventImage);
                
                const eventCard = document.createElement('div');
                eventCard.className = 'relative rounded-lg overflow-hidden';
                eventCard.innerHTML = `
                    <img alt="${eventData.title}" class="w-full h-24 object-cover" src="${eventImage}"/>
                    <div class="absolute inset-0 bg-black/40 flex items-center justify-center">
                        <p class="text-white font-bold text-lg">${eventData.title.toUpperCase()}</p>
                    </div>
                `;
                
                completedEventsContainer.appendChild(eventCard);
            }
            
            // Function to show placeholder cards when no completed events exist
            function showCompletedEventsPlaceholder() { /* not used anymore */ }

            // Function to select a calendar date and load its events
            window.selectCalendarDate = function(dateString) {
                window.selectedCalendarDate = dateString;
                console.log('Selected date:', dateString);
                
                // Re-render calendar to update highlighting
                window.renderCalendar();
                
                // Load events for selected date
                loadEventsForDate(dateString);
            };

            // Function to load events for a specific date
            function loadEventsForDate(dateString) {
                const todayEventsContainer = document.getElementById('todayEventsContainer');
                const scheduledEventsTitle = document.getElementById('scheduledEventsTitle');
                if (!todayEventsContainer) return;
                
                // Use in-memory events from database (single source of truth)
                const savedEvents = window.currentEvents || [];
                const eventsForDate = savedEvents.filter(event => event.date === dateString).slice(0, 5);
                
                // Clear container
                todayEventsContainer.innerHTML = '';
                
                // Format date for display
                const dateObj = new Date(dateString);
                const formattedDate = dateObj.toLocaleDateString('en-US', { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
                
                // Update section title
                if (scheduledEventsTitle) {
                    scheduledEventsTitle.textContent = `Events for ${formattedDate}`;
                }
                
                if (eventsForDate.length === 0) {
                    // Show "No events" message
                    todayEventsContainer.innerHTML = `
                        <div class="text-center py-8">
                            <span class="material-symbols-outlined text-4xl text-text-muted-light dark:text-text-muted-dark mb-2">event_busy</span>
                            <p class="text-text-muted-light dark:text-text-muted-dark">No events scheduled for ${formattedDate}</p>
                        </div>
                    `;
                } else {
                    // Show events for the selected date
                    eventsForDate.forEach(event => {
                        const eventElement = document.createElement('div');
                        eventElement.className = 'flex items-start space-x-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg';
                        eventElement.innerHTML = `
                            <div class="flex-shrink-0">
                                <div class="w-2 h-2 bg-primary rounded-full mt-2"></div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="text-sm font-medium text-text-light dark:text-text-dark">${event.title.toUpperCase()}</h4>
                                <p class="text-xs text-text-muted-light dark:text-text-muted-dark"><span class="material-symbols-outlined text-xs align-middle">schedule</span> ${event.timeRange}</p>
                                <p class="text-xs text-text-muted-light dark:text-text-muted-dark"><span class="material-symbols-outlined text-xs align-middle">location_on</span> ${event.location || 'Location not specified'}</p>
                            </div>
                        `;
                        // Open details modal on click
                        eventElement.style.cursor = 'pointer';
                        eventElement.addEventListener('click', () => {
                            if (typeof openDetailsModalSmart === 'function') {
                                openDetailsModalSmart(event);
                            }
                        });
                        todayEventsContainer.appendChild(eventElement);
                    });
                }
            }

            // Function to load today's events (now just calls loadEventsForDate with today's date)
            function loadTodayEvents() {
                // Use in-memory events if available
                if (window.currentEvents) {
                    loadTodayEventsFromData(window.currentEvents);
                    return;
                }
                // Otherwise use the date-based loading
                const today = new Date();
                const todayString = today.toISOString().split('T')[0]; // Format: YYYY-MM-DD
                
                // Set today as selected if no date is selected yet
                if (!window.selectedCalendarDate) {
                    window.selectedCalendarDate = todayString;
                }
                
                // Load events for today
                loadEventsForDate(todayString);
            }
            
            // Helper function to load today's events from data array
            function loadTodayEventsFromData(events) {
                const savedEvents = Array.isArray(events) ? events : [];
                const today = new Date();
                const todayString = today.toISOString().split('T')[0]; // Format: YYYY-MM-DD
                
                // Set today as selected if no date is selected yet
                if (!window.selectedCalendarDate) {
                    window.selectedCalendarDate = todayString;
                }
                
                // Filter events for today
                const todayEvents = savedEvents.filter(event => event.date === todayString);
                
                // Load events for today using the existing function
                loadEventsForDate(todayString);
            }

            // Function to load events from database
            async function loadEventsFromDatabase() {
                try {
                    // Determine if we're likely running a PHP-capable local server
                    const isFileProtocol = window.location.protocol === 'file:';
                    const isPhpServer = window.location.protocol === 'http:' && (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1');

                    if (isFileProtocol) {
                        console.log('Running in file mode - skipping database load, using localStorage only');
                        loadUpcomingEvents();
                        loadTodayEvents();
                        return false;
                    }
                    if (!isPhpServer) {
                        console.log('Not on localhost - using localStorage fallback');
                        loadUpcomingEvents();
                        loadTodayEvents();
                        loadCompletedEvents();
                        if (typeof window.renderCalendar === 'function') { window.renderCalendar(); }
                        return false;
                    }

                    console.log('Loading events from database...');
                    
                    const response = await fetch('api/events.php?action=calendar', {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        }
                    });
                    
                    if (response.ok) {
                        const contentType = response.headers.get('content-type') || '';
                        const responseText = await response.text();
                        // Guard against static servers returning raw PHP instead of executing it
                        if (responseText.trim().startsWith('<?php')) {
                            throw new Error('Non-JSON response from API (PHP not executing)');
                        }
                        const result = JSON.parse(responseText);
                        const dbEvents = result.success ? (result.events || []) : [];

                        console.log('✅ Raw API response:', result);
                        console.log('✅ Events loaded from database:', dbEvents);
                        console.log('✅ Number of events loaded:', dbEvents.length);
                        
                        // Update calendar title immediately after loading (even if empty)
                        if (typeof window.updateCalendarTitle === 'function') {
                            window.updateCalendarTitle();
                        }

                        // Convert database events to localStorage format for compatibility
                        let convertedEvents = dbEvents.map(event => ({
                            id: event.id,
                            title: event.title || 'Untitled Event',
                            date: event.date || event.event_date,
                            timeRange: event.timeRange || (event.start_time && event.end_time ? 
                                `${event.start_time.substring(0, 5)} - ${event.end_time.substring(0, 5)}` : 'N/A'),
                            location: event.location || '',
                            description: event.description || '',
                            imageUrl: getRandomEventImage(),
                            createdAt: event.createdAt || event.created_at,
                            status: event.status || 'planned'
                        }));

                        console.log('✅ Converted events:', convertedEvents);

                        // Database is the single source of truth - no localStorage needed
                        console.log('✅ Total events loaded from database:', convertedEvents.length);
                        
                        // Store events in memory for UI functions (not localStorage)
                        window.currentEvents = convertedEvents;
                        
                        // Reload the UI with database events directly
                        loadUpcomingEventsFromData(convertedEvents);
                        loadTodayEventsFromData(convertedEvents);
                        loadCompletedEventsFromData(convertedEvents);
                        
                        // Populate events table - CRITICAL: Must be called to show events
                        if (typeof renderEventsTable === 'function') {
                            try {
                                renderEventsTable(convertedEvents);
                                console.log('✅ Events table rendered with', convertedEvents.length, 'events');
                            } catch (error) {
                                console.error('❌ Error rendering events table:', error);
                            }
                        } else {
                            console.error('❌ renderEventsTable function not found!');
                        }
                        
                        // Update calendar title and render calendar - CRITICAL: Must be called to show date cells
                        if (typeof window.updateCalendarTitle === 'function') {
                            window.updateCalendarTitle();
                        }
                        if (typeof window.renderCalendar === 'function') {
                            try {
                                window.renderCalendar();
                                console.log('✅ Calendar rendered after events loaded');
                            } catch (error) {
                                console.error('❌ Error rendering calendar:', error);
                            }
                        } else {
                            console.error('❌ window.renderCalendar function not found!');
                        }
                        
                        return true;
                    } else {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                } catch (error) {
                    console.error('Error loading events:', error);
                    
                    // Update calendar title even on error
                    if (typeof window.updateCalendarTitle === 'function') {
                        window.updateCalendarTitle();
                    }
                    
                    // Silent fallback to localStorage to avoid noisy console errors in non-server environments
                    // Fall back to localStorage
                    loadUpcomingEvents();
                    loadTodayEvents();
                    loadCompletedEvents();
                    
                    // Try to populate table from localStorage
                    try {
                        const savedEvents = JSON.parse(localStorage.getItem('upcomingEvents') || '[]');
                        renderEventsTable(savedEvents);
                    } catch (e) {
                        renderEventsTable([]);
                    }
                    
                    // Initialize calendar even with no events - CRITICAL: Must be called to show date cells
                    if (typeof window.renderCalendar === 'function') {
                        try {
                            window.renderCalendar();
                            console.log('✅ Calendar rendered (fallback mode)');
                        } catch (error) {
                            console.error('❌ Error rendering calendar (fallback):', error);
                        }
                    } else {
                        console.error('❌ window.renderCalendar function not found (fallback)!');
                    }
                    
                    return false;
                }
            }

            // Function to render events in table format
            function renderEventsTable(events) {
                const tableBody = document.getElementById('eventsTableBody');
                if (!tableBody) return;

                // Clear existing rows
                tableBody.innerHTML = '';

                // Show empty state if no events
                if (!Array.isArray(events) || events.length === 0) {
                    const emptyRow = document.createElement('tr');
                    emptyRow.innerHTML = `
                        <td colspan="6" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center justify-center">
                                <span class="material-symbols-outlined text-6xl text-gray-400 dark:text-gray-500 mb-4">event</span>
                                <p class="text-lg font-medium text-text-muted-light dark:text-text-muted-dark mb-2">No events found</p>
                                <p class="text-sm text-text-muted-light dark:text-text-muted-dark">Click "Add Event" to create your first event</p>
                            </div>
                        </td>
                    `;
                    tableBody.appendChild(emptyRow);
                    console.log('✅ Events table: No events found, showing empty state');
                    return;
                }
                
                // Sort events by date (newest first)
                const sortedEvents = [...events].sort((a, b) => {
                    const dateA = new Date(a.date || a.event_date || 0);
                    const dateB = new Date(b.date || b.event_date || 0);
                    return dateB - dateA;
                });

                console.log('🔵 Rendering', sortedEvents.length, 'events in table');

                // Add rows for each event
                sortedEvents.forEach(event => {
                    const row = document.createElement('tr');
                    row.className = 'border-b border-border-light dark:border-border-dark';
                    
                    const eventDate = event.date || event.event_date || 'N/A';
                    const eventTime = event.timeRange || event.time || 'N/A';
                    const eventLocation = event.location || 'N/A';
                    const eventTitle = event.title || 'Untitled Event';
                    
                    // Determine status
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    const eventDateObj = new Date(eventDate);
                    eventDateObj.setHours(0, 0, 0, 0);
                    
                    let statusText = 'Upcoming';
                    let statusClass = 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300';
                    
                    if (eventDateObj < today) {
                        statusText = 'Completed';
                        statusClass = 'bg-gray-100 text-gray-800 dark:bg-gray-900/50 dark:text-gray-300';
                    } else if (eventDateObj.getTime() === today.getTime()) {
                        statusText = 'Today';
                        statusClass = 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300';
                    }

                    // Format date
                    let formattedDate = 'N/A';
                    if (eventDate !== 'N/A') {
                        try {
                            const date = new Date(eventDate);
                            formattedDate = date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                        } catch (e) {
                            formattedDate = eventDate;
                        }
                    }

                    row.innerHTML = `
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-text-light dark:text-text-dark text-left">
                            ${escapeHtml(eventTitle)}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-text-muted-light dark:text-text-muted-dark text-center">
                            ${formattedDate}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-text-muted-light dark:text-text-muted-dark text-center">
                            ${escapeHtml(eventTime)}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-text-muted-light dark:text-text-muted-dark text-center">
                            ${escapeHtml(eventLocation)}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusClass}">
                                ${statusText}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button class="p-2 rounded-md hover:bg-gray-100 dark:hover:bg-slate-700 text-primary view-event-btn" data-event-id="${event.id}" aria-label="View" title="View Details">
                                    <span class="material-symbols-outlined text-base">visibility</span>
                                </button>
                                ${isAdmin ? `
                                    <button class="p-2 rounded-md hover:bg-gray-100 dark:hover:bg-slate-700 text-primary update-event-btn" data-event-id="${event.id}" aria-label="Edit" title="Edit Event">
                                        <span class="material-symbols-outlined text-base">edit</span>
                                    </button>
                                    <button class="p-2 rounded-md hover:bg-gray-100 dark:hover:bg-slate-700 text-red-600 dark:text-red-400" aria-label="Delete" onclick="if(typeof deleteEvent === 'function') deleteEvent(${event.id})" title="Delete Event">
                                        <span class="material-symbols-outlined text-base">delete</span>
                                    </button>
                                ` : ''}
                            </div>
                        </td>
                    `;
                    
                    tableBody.appendChild(row);
                    
                    // Add event listener for view button
                    const viewBtn = row.querySelector('.view-event-btn');
                    if (viewBtn) {
                        viewBtn.addEventListener('click', function(e) {
                            e.stopPropagation();
                            if (typeof openDetailsModalSmart === 'function') {
                                openDetailsModalSmart(event);
                            }
                        });
                    }
                });
            }

            // Disable demo seeding: no sample events
            function createSamplePastEvents() { /* intentionally no-op */ }

            // Load events when page loads - try database first, fallback to localStorage
            loadEventsFromDatabase();
            
            // Demo seeding disabled
            
            // Show helpful message about server mode
            document.addEventListener('DOMContentLoaded', () => {
                // Wait a bit for calendar functions to be defined (they're defined later in the script)
                setTimeout(() => {
                    // Initialize calendar immediately (don't wait for events to load)
                    const calendarTitleText = document.getElementById('calendarTitleText');
                    if (calendarTitleText && calendarTitleText.textContent === 'Loading...') {
                        // Initialize window.currentDate if not already set
                        if (!window.currentDate) {
                            window.currentDate = new Date();
                        }
                        // Initialize monthNames if not already set
                        if (!window.monthNames) {
                            window.monthNames = [
                                'January', 'February', 'March', 'April', 'May', 'June',
                                'July', 'August', 'September', 'October', 'November', 'December'
                            ];
                        }
                        // Update calendar title immediately
                        if (typeof window.updateCalendarTitle === 'function') {
                            window.updateCalendarTitle();
                        } else {
                            // Fallback: set title directly
                            const now = new Date();
                            const month = window.monthNames ? window.monthNames[now.getMonth()] : now.toLocaleString('default', { month: 'long' });
                            const year = now.getFullYear();
                            const shortMonth = month.substring(0, 3);
                            calendarTitleText.textContent = `${shortMonth} ${year}`;
                        }
                        // Initialize calendar grid
                        if (typeof window.renderCalendar === 'function') {
                            window.renderCalendar();
                        }
                    }
                }, 100); // Small delay to ensure calendar functions are defined
                
                // Fallback: If still loading after 2 seconds, force update
                setTimeout(() => {
                    const titleText = document.getElementById('calendarTitleText');
                    if (titleText && titleText.textContent === 'Loading...') {
                        if (!window.currentDate) {
                            window.currentDate = new Date();
                        }
                        if (!window.monthNames) {
                            window.monthNames = [
                                'January', 'February', 'March', 'April', 'May', 'June',
                                'July', 'August', 'September', 'October', 'November', 'December'
                            ];
                        }
                        if (typeof window.updateCalendarTitle === 'function') {
                            window.updateCalendarTitle();
                        } else {
                            const now = new Date();
                            const month = window.monthNames ? window.monthNames[now.getMonth()] : now.toLocaleString('default', { month: 'long' });
                            const year = now.getFullYear();
                            const shortMonth = month.substring(0, 3);
                            titleText.textContent = `${shortMonth} ${year}`;
                        }
                        if (typeof window.renderCalendar === 'function') {
                            window.renderCalendar();
                        }
                    }
                }, 2000);
                
                const isFileProtocol = window.location.protocol === 'file:';
                if (isFileProtocol) {
                    console.log(`
🚀 LILAC Events System - File Mode
===================================
You're running in file mode (opening HTML directly).
For full database functionality, please:

1. Open Command Prompt/PowerShell
2. Navigate to the project folder:
   cd "C:\\Users\\Admin\\Documents\\GitHub\\LILAC-v.2.1"
3. Start the PHP server:
   php -S localhost:8000
4. Open in browser: http://localhost:8000/events-activities.php

Current mode: localStorage only (events will persist in browser)
                    `);
                } else {
                    console.log('🚀 LILAC Events System - Server Mode (Full database functionality enabled)');
                }
                
                // Disable auto-clearing cached events in non-server mode to prevent wiping user-added items

                // Load completed events after DOM is ready
                loadCompletedEvents();

                // Utility: allow quick clearing of local cached events
                window.clearEventCache = () => {
                    try {
                        localStorage.removeItem('upcomingEvents');
                        if (typeof loadUpcomingEvents === 'function') loadUpcomingEvents();
                        if (typeof loadCompletedEvents === 'function') loadCompletedEvents();
                        if (typeof loadTodayEvents === 'function') loadTodayEvents();
                        console.log('Local event cache cleared.');
                    } catch {}
                };
                // Utility: clear all events from server DB (localhost only)
                window.clearServerEvents = async () => {
                    try {
                        const isPhpServer = window.location.protocol === 'http:' && (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1');
                        if (!isPhpServer) { showToast('Server clear only works on localhost.', 'warning'); return; }
                        const resp = await fetch('api/events.php?action=clear', { method: 'POST' });
                        const j = await resp.json().catch(()=>({}));
                        if (!resp.ok || j.error) throw new Error(j.error || 'Failed to clear');
                        window.clearEventCache();
                        console.log('Server events cleared.');
                    } catch (e) {
                        showToast('Failed to clear server events.', 'error');
                    }
                };
                // Keyboard shortcut: Ctrl+Alt+E to clear cached events
                window.addEventListener('keydown', (e) => {
                    if (e.ctrlKey && e.altKey && (e.key === 'E' || e.key === 'e')) {
                        e.preventDefault();
                        window.clearEventCache();
                    }
                    if (e.ctrlKey && e.altKey && (e.key === 'D' || e.key === 'd')) {
                        e.preventDefault();
                        window.clearServerEvents();
                    }
                });
            });

            // Reminder System Functionality
            let currentReminderEvent = null;

            // Function to open reminder modal
            function openReminderModal(eventData) {
                currentReminderEvent = eventData;
                const modal = document.getElementById('reminderModal');
                const titleElement = document.getElementById('reminderEventTitle');
                const dateTimeElement = document.getElementById('reminderEventDateTime');
                const datePrev = document.getElementById('customDatePreview');
                const timePrev = document.getElementById('customTimePreview');
                
                if (modal && titleElement && dateTimeElement) {
                    titleElement.textContent = eventData.title || 'Event';
                    const formattedDate = formatEventDate(eventData.date);
                    dateTimeElement.textContent = `${formattedDate} ⋅ ${eventData.timeRange}`;
                    if (datePrev) datePrev.textContent = formattedDate;
                    if (timePrev) timePrev.textContent = (eventData.timeRange || '').split('-')[0]?.trim() || '—';
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                }
            }

            // Function to close reminder modal
            function closeReminderModal() {
                const modal = document.getElementById('reminderModal');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    currentReminderEvent = null;
                    
                    // Reset form
                    document.querySelectorAll('input[name="reminderTime"]').forEach(radio => {
                        radio.checked = false;
                    });
                    document.getElementById('customDays').value = '';
                    document.getElementById('customDate').value = '';
                    document.getElementById('customTime').value = '';
                }
            }

            // Function to set reminder
            async function setReminder() {
                if (!currentReminderEvent) return;

                const selectedReminder = document.querySelector('input[name="reminderTime"]:checked');
                if (!selectedReminder) {
                    showToast('Please select a reminder time.', 'warning');
                    return;
                }

                let reminderTime = null;
                const eventDate = new Date(currentReminderEvent.date);
                
                // Calculate reminder time based on selection
                switch (selectedReminder.value) {
                    case '1hour':
                        reminderTime = new Date(eventDate.getTime() - (60 * 60 * 1000)); // 1 hour before
                        break;
                    case '1day':
                        reminderTime = new Date(eventDate.getTime() - (24 * 60 * 60 * 1000)); // 1 day before
                        break;
                    case '1week':
                        reminderTime = new Date(eventDate.getTime() - (7 * 24 * 60 * 60 * 1000)); // 1 week before
                        break;
                    case 'custom':
                        // Check if custom date and time are provided
                        const customDate = document.getElementById('customDate').value;
                        const customTime = document.getElementById('customTime').value;
                        const customDays = parseInt(document.getElementById('customDays').value);
                        
                        if (customDate && customTime) {
                            // Use specific date and time
                            reminderTime = new Date(`${customDate}T${customTime}`);
                            console.log('Custom date/time reminder set:', {
                                customDate: customDate,
                                customTime: customTime,
                                reminderTime: reminderTime.toISOString(),
                                currentTime: new Date().toISOString()
                            });
                        } else if (customDays && !isNaN(customDays) && customDays >= 0) {
                            // Use days before event
                            reminderTime = new Date(eventDate.getTime() - (customDays * 24 * 60 * 60 * 1000));
                        } else {
                            showToast('Please specify either a custom date & time or number of days before the event.', 'warning');
                            return;
                        }
                        break;
                }

                // Check if reminder time is in the past
                if (reminderTime <= new Date()) {
                    showToast('Reminder time cannot be in the past. Please choose a different time.', 'warning');
                    return;
                }

                // Check if reminder already exists for this event
                const savedReminders = JSON.parse(localStorage.getItem('eventReminders') || '[]');
                const existingReminder = savedReminders.find(r => r.eventId === currentReminderEvent.id);
                
                if (existingReminder) {
                    const shouldReplace = await showConfirm(`A reminder has already been set for this event.\n\nCurrent reminder: ${formatReminderDate(new Date(existingReminder.reminderTime))}\n\nDo you want to replace it with a new reminder?`, 'Replace Reminder', 'Replace', 'Cancel');
                    
                    if (shouldReplace) {
                        // Remove existing reminder
                        const updatedReminders = savedReminders.filter(r => r.eventId !== currentReminderEvent.id);
                        localStorage.setItem('eventReminders', JSON.stringify(updatedReminders));
                        updateReminderButton(currentReminderEvent.id, false);
                    } else {
                        return; // User cancelled
                    }
                }

                // Store reminder
                const reminder = {
                    id: Date.now(),
                    eventId: currentReminderEvent.id,
                    eventTitle: currentReminderEvent.title,
                    eventDate: currentReminderEvent.date,
                    eventTime: currentReminderEvent.timeRange,
                    reminderTime: reminderTime.toISOString(),
                    reminderType: selectedReminder.value,
                    createdAt: new Date().toISOString()
                };

                // Save to localStorage
                savedReminders.push(reminder);
                localStorage.setItem('eventReminders', JSON.stringify(savedReminders));

                // Show confirmation with more details
                const confirmationMessage = `Reminder set successfully!\n\nEvent: ${currentReminderEvent.title}\nReminder Time: ${formatReminderDate(reminderTime)}\n\nYou will be notified at this time.`;
                showToast(confirmationMessage, 'success');
                
                // Close modal
                closeReminderModal();

                // Update button text to show reminder is set
                updateReminderButton(currentReminderEvent.id, true);
            }

            // Function to format reminder date
            function formatReminderDate(date) {
                const now = new Date();
                const reminderDate = new Date(date);
                
                // Get dates without time for comparison
                const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                const reminderDay = new Date(reminderDate.getFullYear(), reminderDate.getMonth(), reminderDate.getDate());
                const tomorrow = new Date(today);
                tomorrow.setDate(tomorrow.getDate() + 1);
                
                // Calculate difference in days
                const diffTime = reminderDay.getTime() - today.getTime();
                const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));
                
                console.log('Reminder formatting debug:', {
                    now: now.toISOString(),
                    reminderDate: reminderDate.toISOString(),
                    today: today.toISOString(),
                    reminderDay: reminderDay.toISOString(),
                    diffDays: diffDays
                });
                
                if (diffDays === 0) {
                    return 'today at ' + reminderDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                } else if (diffDays === 1) {
                    return 'tomorrow at ' + reminderDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                } else if (diffDays > 1) {
                    return `in ${diffDays} days at ` + reminderDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                } else {
                    return reminderDate.toLocaleDateString() + ' at ' + reminderDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                }
            }

            // Helper functions for reminder modal
            function setToday() {
                const today = new Date();
                const dateString = today.toISOString().split('T')[0]; // Format: YYYY-MM-DD
                document.getElementById('customDate').value = dateString;
                console.log('Set today\'s date:', dateString);
            }
            
            function setCurrentTime() {
                const now = new Date();
                const timeString = now.toTimeString().slice(0, 5); // Format: HH:MM
                document.getElementById('customTime').value = timeString;
                console.log('Set current time:', timeString);
            }
            
            // Make functions globally available
            window.setToday = setToday;
            window.setCurrentTime = setCurrentTime;

            // Function to update reminder button state
            function updateReminderButton(eventId, hasReminder) {
                const button = document.querySelector(`button[data-event-id="${eventId}"]`);
                if (button) {
                    if (hasReminder) {
                        button.textContent = 'Reminder Set ✓';
                        button.classList.add('bg-green-100', 'text-green-700', 'dark:bg-green-900/20', 'dark:text-green-400');
                        button.classList.remove('bg-primary/10', 'text-primary');
                    } else {
                        button.textContent = 'Remind Me';
                        button.classList.remove('bg-green-100', 'text-green-700', 'dark:bg-green-900/20', 'dark:text-green-400');
                        button.classList.add('bg-primary/10', 'text-primary');
                    }
                }
            }

            // Function to check for reminders
            function checkReminders() {
                const savedReminders = JSON.parse(localStorage.getItem('eventReminders') || '[]');
                const shownNotifications = JSON.parse(localStorage.getItem('shownNotifications') || '[]');
                const now = new Date();
                
                savedReminders.forEach(reminder => {
                    const reminderTime = new Date(reminder.reminderTime);
                    
                    // Check if it's time to show the reminder (within 1 minute)
                    if (Math.abs(now.getTime() - reminderTime.getTime()) <= 60000) {
                        // Check if notification has already been shown for this reminder
                        const alreadyShown = shownNotifications.some(shown => shown.reminderId === reminder.id);
                        
                        if (!alreadyShown) {
                            showNotification(reminder);
                            
                            // Mark notification as shown
                            shownNotifications.push({
                                reminderId: reminder.id,
                                eventId: reminder.eventId,
                                shownAt: now.toISOString()
                            });
                            
                            // Keep only last 100 shown notifications to prevent storage bloat
                            if (shownNotifications.length > 100) {
                                shownNotifications.splice(0, shownNotifications.length - 100);
                            }
                            localStorage.setItem('shownNotifications', JSON.stringify(shownNotifications));
                        }
                        
                        // Remove the reminder after showing (regardless of whether notification was shown)
                        const updatedReminders = savedReminders.filter(r => r.id !== reminder.id);
                        localStorage.setItem('eventReminders', JSON.stringify(updatedReminders));
                        
                        // Update button state
                        updateReminderButton(reminder.eventId, false);
                    }
                });
            }

            // Function to show notification
            function showNotification(reminder) {
                // Request notification permission if not already granted
                if (Notification.permission === 'default') {
                    Notification.requestPermission();
                }
                
                if (Notification.permission === 'granted') {
                    // Use a unique tag to prevent duplicate browser notifications
                    const notificationTag = `reminder-${reminder.eventId}-${reminder.id}`;
                    
                    const notification = new Notification(`Event Reminder: ${reminder.eventTitle}`, {
                        body: `Your event is coming up on ${formatEventDate(reminder.eventDate)} at ${reminder.eventTime}`,
                        icon: 'assets/images/cpu-logo.svg.png',
                        tag: notificationTag, // Browser will replace any existing notification with same tag
                        requireInteraction: true
                    });
                    
                    // Auto-close after 10 seconds
                    setTimeout(() => notification.close(), 10000);
                } else {
                    // Fallback to alert if notifications are not allowed
                    showToast(`Reminder: ${reminder.eventTitle}\nYour event is coming up on ${formatEventDate(reminder.eventDate)} at ${reminder.eventTime}`, 'info', 8000);
                }
            }
            
            // Function to clean up old shown notifications (call this periodically)
            function cleanupOldNotifications() {
                const shownNotifications = JSON.parse(localStorage.getItem('shownNotifications') || '[]');
                const oneWeekAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000);
                
                const recentNotifications = shownNotifications.filter(shown => 
                    new Date(shown.shownAt) > oneWeekAgo
                );
                
                if (recentNotifications.length !== shownNotifications.length) {
                    localStorage.setItem('shownNotifications', JSON.stringify(recentNotifications));
                    console.log('Cleaned up old notification records');
                }
            }

            // Event listeners for reminder modal
            document.addEventListener('click', (e) => {
                // Handle "Remind Me" button clicks
                if (e.target.classList.contains('remind-me-btn')) {
                    const eventData = {
                        id: e.target.getAttribute('data-event-id'),
                        title: e.target.getAttribute('data-event-title'),
                        date: e.target.getAttribute('data-event-date'),
                        timeRange: e.target.getAttribute('data-event-time')
                    };
                    openReminderModal(eventData);
                }
                
                // Handle modal close buttons - check both the button and its child elements
                if (e.target.id === 'closeReminderModal' || 
                    e.target.closest('#closeReminderModal') || 
                    e.target.id === 'cancelReminder') {
                    closeReminderModal();
                }
                
                // Handle set reminder button
                if (e.target.id === 'setReminder') {
                    setReminder();
                }
                
                // Handle custom inputs
                if (e.target.id === 'customDays' || e.target.id === 'customDate' || e.target.id === 'customTime') {
                    document.querySelector('input[name="reminderTime"][value="custom"]').checked = true;
                }
                
                // Handle clicking outside the modal to close it
                if (e.target.id === 'reminderModal') {
                    closeReminderModal();
                }
            });

            // Check reminders every minute
            setInterval(checkReminders, 60000);
            
            // Clean up old notifications every hour
            setInterval(cleanupOldNotifications, 60 * 60 * 1000);
            
            // Run cleanup once on page load
            cleanupOldNotifications();

            // Check for existing reminders on page load
            setTimeout(() => {
                const savedReminders = JSON.parse(localStorage.getItem('eventReminders') || '[]');
                savedReminders.forEach(reminder => {
                    updateReminderButton(reminder.eventId, true);
                });
            }, 1000);

            // Notification System for Navbar
            let notifications = JSON.parse(localStorage.getItem('appNotifications') || '[]');
            
            // Function to add notification
            function addNotification(type, title, message, data = {}) {
                // Check for duplicate notifications (same type, title, and data within last 5 minutes)
                const now = new Date();
                const fiveMinutesAgo = new Date(now.getTime() - 5 * 60 * 1000);
                
                const isDuplicate = notifications.some(notif => 
                    notif.type === type && 
                    notif.title === title && 
                    JSON.stringify(notif.data) === JSON.stringify(data) &&
                    new Date(notif.timestamp) > fiveMinutesAgo
                );
                
                if (isDuplicate) {
                    console.log('Duplicate notification prevented:', title);
                    return; // Don't add duplicate notification
                }
                
                const notification = {
                    id: Date.now(),
                    type: type, // 'reminder', 'event', 'system'
                    title: title,
                    message: message,
                    data: data,
                    timestamp: new Date().toISOString(),
                    read: false
                };
                
                notifications.unshift(notification); // Add to beginning
                
                // Keep only last 50 notifications
                if (notifications.length > 50) {
                    notifications = notifications.slice(0, 50);
                }
                
                localStorage.setItem('appNotifications', JSON.stringify(notifications));
                updateNotificationUI();
            }
            
            // Function to update notification UI
            function updateNotificationUI() {
                const badge = document.getElementById('notificationBadge');
                const list = document.getElementById('notificationList');
                const noNotifications = document.getElementById('noNotifications');
                
                const unreadCount = notifications.filter(n => !n.read).length;
                
                // Update badge
                if (unreadCount > 0) {
                    badge.textContent = unreadCount > 99 ? '99+' : unreadCount.toString();
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
                
                // Update notification list
                if (notifications.length === 0) {
                    list.innerHTML = '';
                    noNotifications.classList.remove('hidden');
                } else {
                    noNotifications.classList.add('hidden');
                    list.innerHTML = notifications.map(notification => {
                        const timeAgo = getTimeAgo(new Date(notification.timestamp));
                        const iconClass = notification.type === 'reminder' ? 'schedule' : 
                                        notification.type === 'event' ? 'event' : 'info';
                        
                        return `
                            <div class="p-3 hover:bg-gray-50 dark:hover:bg-white/5 cursor-pointer ${notification.read ? 'opacity-60' : ''}" 
                                 onclick="markNotificationAsRead(${notification.id})">
                                <div class="flex items-start gap-3">
                                    <span class="material-symbols-outlined text-lg text-primary mt-0.5">${iconClass}</span>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-sm text-text-light dark:text-text-dark">${notification.title}</p>
                                        <p class="text-xs text-text-muted-light dark:text-text-muted-dark mt-1">${notification.message}</p>
                                        <p class="text-xs text-text-muted-light dark:text-text-muted-dark mt-1">${timeAgo}</p>
                                    </div>
                                    ${!notification.read ? '<div class="w-2 h-2 bg-primary rounded-full mt-2"></div>' : ''}
                                </div>
                            </div>
                        `;
                    }).join('');
                }
            }
            
            // Function to mark notification as read
            window.markNotificationAsRead = function(notificationId) {
                const notification = notifications.find(n => n.id === notificationId);
                if (notification) {
                    notification.read = true;
                    localStorage.setItem('appNotifications', JSON.stringify(notifications));
                    updateNotificationUI();
                }
            }
            
            // Function to get time ago
            function getTimeAgo(date) {
                const now = new Date();
                const diffInSeconds = Math.floor((now - date) / 1000);
                
                if (diffInSeconds < 60) return 'Just now';
                if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
                if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
                if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)}d ago`;
                return date.toLocaleDateString();
            }
            
            // Function to clear all notifications
            function clearAllNotifications() {
                notifications = [];
                localStorage.setItem('appNotifications', JSON.stringify(notifications));
                // Also clear reminders and any shown flags to avoid phantom counts
                localStorage.removeItem('eventReminders');
                localStorage.removeItem('shownNotifications');
                updateNotificationUI();
            }
            
            // Update the showNotification function to also add to navbar
            const originalShowNotification = showNotification;
            function showNotification(reminder) {
                // Add to navbar notifications
                addNotification(
                    'reminder',
                    `Event Reminder: ${reminder.eventTitle}`,
                    `Your event is coming up on ${formatEventDate(reminder.eventDate)} at ${reminder.eventTime}`,
                    { eventId: reminder.eventId }
                );
                
                // Still show browser notification as backup
                originalShowNotification(reminder);
            }
            
            // Notification bell click handler
            document.addEventListener('click', (e) => {
                const bell = document.getElementById('notificationBell');
                const dropdown = document.getElementById('notificationDropdown');
                const clearAllBtn = document.getElementById('clearAllNotifications');
                
                if (bell && bell.contains(e.target)) {
                    dropdown.classList.toggle('hidden');
                } else if (clearAllBtn && clearAllBtn.contains(e.target)) {
                    clearAllNotifications();
                } else if (dropdown && !dropdown.contains(e.target)) {
                    dropdown.classList.add('hidden');
                }
            });
            
            // Initialize notification UI on page load (cleanup if there are no events)
            (function syncNotificationsWithEvents(){
                try {
                    const savedEvents = JSON.parse(localStorage.getItem('upcomingEvents') || '[]');
                    if (!savedEvents || savedEvents.length === 0) {
                        // No events -> clear reminders and unread notifications
                        localStorage.removeItem('eventReminders');
                        localStorage.removeItem('shownNotifications');
                        notifications = [];
                        localStorage.setItem('appNotifications', JSON.stringify([]));
                    }
                } catch {}
            })();
            updateNotificationUI();

            // Add direct event listeners for reminder modal close button
            document.addEventListener('DOMContentLoaded', () => {
                const closeReminderBtn = document.getElementById('closeReminderModal');
                if (closeReminderBtn) {
                    closeReminderBtn.addEventListener('click', closeReminderModal);
                }
                
                const cancelReminderBtn = document.getElementById('cancelReminder');
                if (cancelReminderBtn) {
                    cancelReminderBtn.addEventListener('click', closeReminderModal);
                }
            });

            // Fetch and render award-eligible cards
            const renderEligibleState = () => {};

            // Details modal (simple)
            const ensureDetailsModal = () => {
                if (document.getElementById('eventDetailsModal')) return;
                const modal = document.createElement('div');
                modal.id = 'eventDetailsModal';
                modal.className = 'fixed inset-0 bg-black/30 hidden items-center justify-center z-[9999]';
                modal.innerHTML = `
                  <div class="bg-white dark:bg-card-dark rounded-2xl shadow-2xl w-full max-w-xl mx-4 max-h-[85vh] overflow-y-auto border border-border-light dark:border-border-dark">
                    <div class="p-4 border-b border-border-light dark:border-border-dark flex items-center justify-between">
                      <h3 class="text-lg font-semibold text-text-light dark:text-text-dark">Event Details</h3>
                      <button class="rounded-full p-2 hover:bg-gray-100 dark:hover:bg-white/10" id="closeEventDetails"><span class="material-symbols-outlined text-text-light dark:text-text-dark">close</span></button>
                    </div>
                    <div id="eventDetailsBody" class="p-4 grid gap-3 text-sm"></div>
                    <div class="p-4 border-t border-border-light dark:border-border-dark flex items-center justify-end gap-2">
                      <button id="deleteEventBtn" class="px-3 py-1.5 text-sm rounded-lg bg-red-600 text-white hover:bg-red-700">Delete</button>
                    </div>
                  </div>`;
                document.body.appendChild(modal);
                modal.querySelector('#closeEventDetails').addEventListener('click', () => closeDetailsModal());
                modal.addEventListener('click', (e)=>{ if(e.target===modal) closeDetailsModal(); });
            };
            // Helper: remove from localStorage and refresh UI
            const removeEventLocally = (evt) => {
                try {
                    const list = JSON.parse(localStorage.getItem('upcomingEvents') || '[]');
                    const filtered = list.filter(e => {
                        if (evt && evt.id && e.id) return e.id !== evt.id;
                        // Fallback fuzzy match on key fields
                        const sameTitle = (e.title || '').trim() === (evt.title || '').trim();
                        const sameDate = (e.date || '') === (evt.date || '');
                        const sameTime = (e.timeRange || '') === (evt.timeRange || '');
                        const sameLoc = (e.location || '') === (evt.location || '');
                        return !(sameTitle && sameDate && sameTime && sameLoc);
                    });
                    localStorage.setItem('upcomingEvents', JSON.stringify(filtered));
                    // Refresh sections
                    if (typeof loadUpcomingEvents === 'function') loadUpcomingEvents();
                    if (typeof loadTodayEvents === 'function') loadTodayEvents();
                    if (typeof loadCompletedEvents === 'function') loadCompletedEvents();
                } catch {}
            };
            const openDetailsModal = async (id) => {
                ensureDetailsModal();
                const modal = document.getElementById('eventDetailsModal');
                const body = document.getElementById('eventDetailsBody');
                const delBtn = document.getElementById('deleteEventBtn');
                body.innerHTML = '<div class="text-center text-text-muted-light dark:text-text-muted-dark">Loading...</div>';
                modal.classList.remove('hidden'); modal.classList.add('flex');
                try {
                    const res = await fetch(`api/events.php?id=${id}`);
                    const result = await res.json();
                    if (!res.ok || !result.success || result.error) {
                        throw new Error(result.error || 'Fetch failed');
                    }
                    
                    // Extract event object from API response
                    const itm = result.event || result;
                    const eventId = itm.id || id;
                    
                    console.log('Event details loaded:', { result, itm, eventId });
                    
                    body.innerHTML = `
                      <div class="flex items-start gap-4">
                        <img src="${itm.image_url || itm.thumbnail_url || 'https://via.placeholder.com/120x80?text=img'}" class="w-28 h-20 object-cover rounded" alt="Event image"/>
                        <div>
                          <h4 class="text-lg font-bold text-text-light dark:text-text-dark">${(itm.title || '').toUpperCase()}</h4>
                          <p class="text-xs text-text-muted-light dark:text-text-muted-dark"><span class="material-symbols-outlined text-xs align-middle">location_on</span> ${itm.location || ''}</p>
                        </div>
                      </div>
                      <div class="grid grid-cols-2 gap-3">
                        <div><span class="text-text-muted-light dark:text-text-muted-dark">Start</span><p class="font-semibold text-text-light dark:text-text-dark">${itm.start_time || ''}</p></div>
                        <div><span class="text-text-muted-light dark:text-text-muted-dark">End</span><p class="font-semibold text-text-light dark:text-text-dark">${itm.end_time || ''}</p></div>
                        <div><span class="text-text-muted-light dark:text-text-muted-dark">Date</span><p class="font-semibold text-text-light dark:text-text-dark">${itm.event_date || itm.date ? new Date(itm.event_date || itm.date).toLocaleDateString() : ''}</p></div>
                        <div><span class="text-text-muted-light dark:text-text-muted-dark">Eligible</span><p class="font-semibold text-text-light dark:text-text-dark">${itm.eligible_for_awards ? 'Yes' : 'No'}</p></div>
                      </div>
                      <div class="mt-2 text-xs text-text-muted-light dark:text-text-muted-dark">Awards eligibility details will appear on the Awards page.</div>`;
                    // Wire delete
                    if (delBtn) {
                        delBtn.onclick = async () => {
                            const ok = await showConfirm('Delete this event? This cannot be undone.', 'Delete Event', 'Delete', 'Cancel');
                            if (!ok) return;
                            try {
                                console.log('Attempting to delete event with ID:', eventId);
                                const resp = await fetch(`api/events.php?id=${encodeURIComponent(eventId)}`, { 
                                    method: 'DELETE',
                                    headers: {
                                        'Content-Type': 'application/json'
                                    }
                                });
                                
                                console.log('Delete response status:', resp.status);
                                const responseText = await resp.text();
                                console.log('Delete response text:', responseText);
                                
                                let j;
                                try {
                                    j = JSON.parse(responseText);
                                } catch (e) {
                                    console.error('Failed to parse JSON response:', e);
                                    throw new Error('Server returned invalid response. Check console for details.');
                                }
                                
                                console.log('Delete result:', j);
                                
                                if (!resp.ok || j.error || !j.success) {
                                    throw new Error(j.error || j.message || 'Delete failed');
                                }
                                
                                closeDetailsModal();
                                
                                // Reload directly from database (single source of truth)
                                // No localStorage manipulation - database is authoritative
                                if (typeof loadEventsFromDatabase === 'function') {
                                    await loadEventsFromDatabase();
                                } else if (typeof loadTodayEvents === 'function') {
                                    loadTodayEvents();
                                } else {
                                    // Fallback: reload page
                                    window.location.reload();
                                }
                            } catch (err) {
                                console.error('Delete error:', err);
                                showToast('Error deleting event: ' + err.message, 'error');
                            }
                        };
                    }
                } catch {
                    body.innerHTML = '<div class="text-center text-text-muted-light dark:text-text-muted-dark">Unable to load full details from the server. If available, open from local data.</div>';
                    const delBtn2 = document.getElementById('deleteEventBtn');
                    if (delBtn2) delBtn2.style.display = 'none';
                }
            };
            // Smart open: try server by id; on failure, fall back to local object
            const openDetailsModalSmart = async (candidate) => {
                if (candidate && candidate.id) {
                    await openDetailsModal(candidate.id);
                    const body = document.getElementById('eventDetailsBody');
                    if (body && body.textContent && body.textContent.includes('Unable to load full details')) {
                        openDetailsModalFromData(candidate);
                    }
                } else {
                    openDetailsModalFromData(candidate || {});
                }
            };
            // Fallback: show modal from existing data object (no server fetch)
            const openDetailsModalFromData = (itm) => {
                ensureDetailsModal();
                const modal = document.getElementById('eventDetailsModal');
                const body = document.getElementById('eventDetailsBody');
                const delBtn = document.getElementById('deleteEventBtn');
                modal.classList.remove('hidden'); modal.classList.add('flex');
                body.innerHTML = `
                  <div class="flex items-start gap-4">
                    <img src="${itm.imageUrl || itm.thumbnail_url || 'https://via.placeholder.com/120x80?text=img'}" class="w-28 h-20 object-cover rounded" alt="Event image"/>
                    <div>
                      <h4 class="text-lg font-bold text-text-light dark:text-text-dark">${itm.title.toUpperCase()}</h4>
                      <p class="text-xs text-text-muted-light dark:text-text-muted-dark"><span class="material-symbols-outlined text-xs align-middle">location_on</span> ${itm.location || ''}</p>
                    </div>
                  </div>
                  <div class="grid grid-cols-2 gap-3">
                    <div><span class="text-text-muted-light dark:text-text-muted-dark">Time</span><p class="font-semibold text-text-light dark:text-text-dark">${itm.timeRange || ''}</p></div>
                    <div><span class="text-text-muted-light dark:text-text-muted-dark">Date</span><p class="font-semibold text-text-light dark:text-text-dark">${itm.date ? new Date(itm.date).toLocaleDateString() : ''}</p></div>
                  </div>`;
                if (delBtn) {
                    // Always allow local deletion here; hide only if no local data
                    delBtn.style.display = '';
                    delBtn.onclick = async () => {
                        const ok = await showConfirm('Remove this event from the page?', 'Remove Event', 'Remove', 'Cancel');
                        if (!ok) return;
                        removeEventLocally(itm || {});
                        closeDetailsModal();
                    };
                }
            };
            const closeDetailsModal = () => {
                const modal = document.getElementById('eventDetailsModal');
                if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
            };

            // Daily refresh at midnight local
            const scheduleRefresh = () => {
                const now = new Date();
                const midnight = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1, 0,0,1);
                setTimeout(()=>{ scheduleRefresh(); }, midnight - now);
            };
            scheduleRefresh();

            // Time range popover logic
            const timeBtn = document.getElementById('evTimeRangeBtn');
            const timePopover = document.getElementById('timeRangePopover');
            const timeOptions = document.getElementById('timeOptions');
            const tabStart = document.getElementById('timeTabStart');
            const tabEnd = document.getElementById('timeTabEnd');
            const timeText = document.getElementById('evTimeRangeText');
            let selecting = 'start';
            let startVal = '';
            let endVal = '';

            const buildTimes = () => {
                if (!timeOptions) return;
                const items = [];
                const pad = (n) => (n<10? '0'+n : ''+n);
                for (let h = 0; h < 24; h++) {
                    for (let m = 0; m < 60; m += 15) {
                        const dt = new Date(0,0,0,h,m);
                        const label = dt.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                        items.push(label);
                    }
                }
                timeOptions.innerHTML = items.map(t => `<button class="w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-white/10 rounded-md">${t}</button>`).join('');
                Array.from(timeOptions.children).forEach(btn => {
                    btn.addEventListener('click', () => {
                        const val = btn.textContent.trim();
                        if (selecting === 'start') {
                            startVal = val;
                            selecting = 'end';
                            tabStart.classList.remove('bg-primary/10','text-primary');
                            tabEnd.classList.add('bg-primary/10','text-primary');
                        } else {
                            endVal = val;
                            selecting = 'start';
                            tabEnd.classList.remove('bg-primary/10','text-primary');
                            tabStart.classList.add('bg-primary/10','text-primary');
                            timePopover.classList.add('hidden');
                        }
                        timeText.textContent = `${startVal || '--:-- --'} - ${endVal || '--:-- --'}`;
                    });
                });
            };
            if (timeBtn) {
                timeBtn.addEventListener('click', () => {
                    if (!timePopover) return;
                    timePopover.classList.toggle('hidden');
                    if (!timeOptions.innerHTML) buildTimes();
                });
            }
            tabStart && tabStart.addEventListener('click', () => {
                selecting = 'start';
                tabStart.classList.add('bg-primary/10','text-primary');
                tabEnd.classList.remove('bg-primary/10','text-primary');
            });
            tabEnd && tabEnd.addEventListener('click', () => {
                selecting = 'end';
                tabEnd.classList.add('bg-primary/10','text-primary');
                tabStart.classList.remove('bg-primary/10','text-primary');
            });
            document.addEventListener('click', (e) => {
                if (!timePopover || !timeBtn) return;
                const container = document.getElementById('timeRangeContainer');
                if (container && !container.contains(e.target)) {
                    timePopover.classList.add('hidden');
                }
            });

            // Location Autocomplete for Location Field
            let autocompleteService = null;
            let placesService = null;
            const locationInput = document.getElementById('evLocation');
            const locationSuggestions = document.getElementById('locationSuggestions');
            const suggestionsList = document.getElementById('suggestionsList');

            const loadGoogleMaps = () => {
                if (window.google && window.google.maps && window.google.maps.places) {
                    return Promise.resolve();
                }
                
                return new Promise((resolve, reject) => {
                    // Check if script already exists
                    if (document.querySelector('script[src*="maps.googleapis.com"]')) {
                        // Script exists but might not be loaded yet
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
                    
                    const script = document.createElement('script');
                    script.src = 'https://maps.googleapis.com/maps/api/js?key=AIzaSyD1p_x_nw6wT7_zUnILTuG17fHNOf0zFC4&libraries=places&loading=async&callback=initPlacesCallback';
                    script.async = true;
                    script.defer = true;
                    script.onerror = () => {
                        console.warn('Google Maps API failed to load. Please check your API key and internet connection.');
                        reject(new Error('Failed to load Google Maps API'));
                    };
                    
                    // Create callback function
                    window.initPlacesCallback = () => {
                        delete window.initPlacesCallback; // Clean up
                        resolve();
                    };
                    
                    document.head.appendChild(script);
                });
            };

            const showLocationSuggestions = async (query) => {
                if (!query.trim() || query.length < 2) {
                    locationSuggestions.classList.add('hidden');
                    return;
                }

                // Always show fallback suggestions first for better UX
                showFallbackSuggestions(query);

                // Try to load Google Places API in the background
                try {
                    await loadGoogleMaps();
                    
                    if (!autocompleteService) {
                        autocompleteService = new google.maps.places.AutocompleteService();
                    }

                    // Use the legacy API which is still working - restricted to Iloilo only
                    autocompleteService.getPlacePredictions({
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
                                displaySuggestions(iloiloPredictions);
                            }
                            // If no Iloilo predictions, keep fallback suggestions
                        } else {
                            console.log('Places API status:', status);
                            // Keep fallback suggestions if API fails
                        }
                    });

                } catch (error) {
                    console.log('Places API failed:', error);
                    // Fallback suggestions are already shown
                }
            };

            const displaySuggestions = (predictions) => {
                suggestionsList.innerHTML = '';
                
                predictions.slice(0, 5).forEach((prediction, index) => {
                    const suggestionItem = document.createElement('div');
                    suggestionItem.className = 'flex items-center gap-3 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-white/10 cursor-pointer transition-colors select-none';
                    suggestionItem.style.pointerEvents = 'auto';
                    suggestionItem.innerHTML = `
                        <span class="material-symbols-outlined text-text-muted-light dark:text-text-muted-dark text-sm">place</span>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-text-light dark:text-text-dark">${prediction.structured_formatting.main_text}</p>
                            <p class="text-xs text-text-muted-light dark:text-text-muted-dark">${prediction.structured_formatting.secondary_text}</p>
                        </div>
                    `;
                    
                    // Add click event with proper event handling
                    suggestionItem.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        locationInput.value = prediction.description;
                        locationInput.setAttribute('data-chosen-location', prediction.description);
                        locationSuggestions.classList.add('hidden');
                        // Trigger input event to update any other listeners
                        locationInput.dispatchEvent(new Event('input', { bubbles: true }));
                        // Immediately hide suggestions and blur to close any overlays
                        setTimeout(() => { locationSuggestions.classList.add('hidden'); locationInput.blur(); }, 0);
                    });
                    
                    // Add mousedown event as backup
                    suggestionItem.addEventListener('mousedown', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                    });
                    
                    suggestionsList.appendChild(suggestionItem);
                });
                
                locationSuggestions.classList.remove('hidden');
            };

            const showFallbackSuggestions = (query) => {
                console.log('Showing ILOILO-ONLY Events fallback suggestions for:', query); // Debug log
                
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
                    'Iloilo Terminal Market, City Proper, Iloilo City, Philippines', 'Megaworld Iloilo Business Park, Mandurriao, Iloilo City, Philippines'
                ];

                const filtered = commonLocations.filter(location => 
                    location.toLowerCase().includes(query.toLowerCase())
                );

                if (filtered.length > 0) {
                    suggestionsList.innerHTML = '';
                    filtered.slice(0, 8).forEach(location => {
                        const suggestionItem = document.createElement('div');
                        suggestionItem.className = 'flex items-center gap-3 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-white/10 cursor-pointer transition-colors select-none';
                        suggestionItem.style.pointerEvents = 'auto';
                        suggestionItem.innerHTML = `
                            <span class="material-symbols-outlined text-text-muted-light dark:text-text-muted-dark text-sm">place</span>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-text-light dark:text-text-dark">${location}</p>
                                <p class="text-xs text-text-muted-light dark:text-text-muted-dark">Suggested location</p>
                            </div>
                        `;
                        
                        // Add click event with proper event handling
                        suggestionItem.addEventListener('click', (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            locationInput.value = location;
                            locationInput.setAttribute('data-chosen-location', location);
                            locationSuggestions.classList.add('hidden');
                            // Trigger input event to update any other listeners
                            locationInput.dispatchEvent(new Event('input', { bubbles: true }));
                            setTimeout(() => { locationSuggestions.classList.add('hidden'); locationInput.blur(); }, 0);
                        });
                        
                        // Add mousedown event as backup
                        suggestionItem.addEventListener('mousedown', (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                        });
                        
                        suggestionsList.appendChild(suggestionItem);
                    });
                    locationSuggestions.classList.remove('hidden');
                } else {
                    locationSuggestions.classList.add('hidden');
                }
            };

            // Add event listener to location input
            if (locationInput) {
                let timeoutId;
                locationInput.addEventListener('input', (e) => {
                    clearTimeout(timeoutId);
                    const query = e.target.value.trim();
                    
                    timeoutId = setTimeout(() => {
                        // Do not show suggestions if the value matches the last chosen suggestion exactly
                        const lastChosen = locationInput.getAttribute('data-chosen-location');
                        if (lastChosen && lastChosen === locationInput.value.trim()) {
                            locationSuggestions.classList.add('hidden');
                            return;
                        }
                        showLocationSuggestions(query);
                    }, 300); // Debounce for 300ms
                });

                // Hide suggestions when clicking outside
                document.addEventListener('click', (e) => {
                    if (!locationInput.contains(e.target) && !locationSuggestions.contains(e.target)) {
                        locationSuggestions.classList.add('hidden');
                    }
                });

                // Handle keyboard navigation
                locationInput.addEventListener('keydown', (e) => {
                    const suggestions = suggestionsList.querySelectorAll('div');
                    const activeSuggestion = suggestionsList.querySelector('[data-active="true"]');
                    
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        if (activeSuggestion) {
                            activeSuggestion.classList.remove('bg-primary/10');
                            activeSuggestion.removeAttribute('data-active');
                            const next = activeSuggestion.nextElementSibling;
                            if (next) {
                                next.classList.add('bg-primary/10');
                                next.setAttribute('data-active', 'true');
                            }
                        } else if (suggestions[0]) {
                            suggestions[0].classList.add('bg-primary/10');
                            suggestions[0].setAttribute('data-active', 'true');
                        }
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        if (activeSuggestion) {
                            activeSuggestion.classList.remove('bg-primary/10');
                            activeSuggestion.removeAttribute('data-active');
                            const prev = activeSuggestion.previousElementSibling;
                            if (prev) {
                                prev.classList.add('bg-primary/10');
                                prev.setAttribute('data-active', 'true');
                            }
                        }
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        if (activeSuggestion) {
                            activeSuggestion.click();
                        }
                    } else if (e.key === 'Escape') {
                        locationSuggestions.classList.add('hidden');
                    }
                });
            }

            // Calendar Navigation Functionality
            window.currentDate = new Date();
            
            window.monthNames = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];

            window.updateCalendarTitle = function() {
                const month = window.monthNames[window.currentDate.getMonth()];
                const year = window.currentDate.getFullYear();
                const titleTextElement = document.getElementById('calendarTitleText');
                if (titleTextElement) {
                    // Format as "Nov 2025" style
                    const shortMonth = month.substring(0, 3);
                    titleTextElement.textContent = `${shortMonth} ${year}`;
                }
            }

            // Function to populate month/year dropdown
            window.populateMonthYearDropdown = function() {
                const dropdown = document.getElementById('monthYearDropdown');
                if (!dropdown) return;

                dropdown.innerHTML = '';
                const now = new Date();
                const currentYear = now.getFullYear();
                const currentMonth = now.getMonth();
                
                // Use a Set to track added months and avoid duplicates
                const addedMonths = new Set();
                
                // Helper function to add a month option
                const addMonthOption = (year, month) => {
                    const key = `${year}-${month}`;
                    if (addedMonths.has(key)) return; // Skip duplicates
                    addedMonths.add(key);
                    
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'w-full text-left px-4 py-2 text-sm text-text-light dark:text-text-dark hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors';
                    const shortMonth = window.monthNames[month].substring(0, 3);
                    option.textContent = `${shortMonth} ${year}`;
                    option.dataset.year = year;
                    option.dataset.month = month;
                    
                    option.addEventListener('click', () => {
                        window.currentDate.setFullYear(year);
                        window.currentDate.setMonth(month);
                        window.updateCalendarTitle();
                        window.renderCalendar();
                        dropdown.classList.add('hidden');
                    });
                    
                    dropdown.appendChild(option);
                };
                
                // Past 12 months (excluding current month which will be added in future section)
                for (let i = 12; i >= 1; i--) {
                    const pastDate = new Date(currentYear, currentMonth - i, 1);
                    const year = pastDate.getFullYear();
                    const month = pastDate.getMonth();
                    addMonthOption(year, month);
                }
                
                // Current month and all future months (current year from current month, then next 5 years)
                for (let yearOffset = 0; yearOffset <= 5; yearOffset++) {
                    const year = currentYear + yearOffset;
                    const startMonth = yearOffset === 0 ? currentMonth : 0;
                    
                    // Always include all remaining months for each year
                    for (let month = startMonth; month < 12; month++) {
                        addMonthOption(year, month);
                    }
                }
            }

            // Track selected calendar date
            window.selectedCalendarDate = null;

            window.renderCalendar = function() {
                console.log('🔵 renderCalendar() called');
                const year = window.currentDate.getFullYear();
                const month = window.currentDate.getMonth();
                
                // Get first day of month and number of days
                const firstDay = new Date(year, month, 1);
                const lastDay = new Date(year, month + 1, 0);
                const daysInMonth = lastDay.getDate();
                const startDay = firstDay.getDay(); // 0 = Sunday, 1 = Monday, etc.

                console.log('🔵 Rendering calendar for:', year, month + 1, '- Days in month:', daysInMonth, 'Start day:', startDay);

                // Get the calendar days container
                const calendarContainer = document.getElementById('calendarGrid');
                if (!calendarContainer) {
                    console.error('❌ calendarGrid element not found in renderCalendar!');
                    return;
                }

                // Clear existing days (keep the first 7 header days: S M T W T F S)
                // Get all child divs, but only remove those after the first 7 (the headers)
                const allChildren = Array.from(calendarContainer.children);
                console.log('🔵 Found', allChildren.length, 'children in calendarGrid');
                // Remove all children except the first 7 (day headers)
                for (let i = 7; i < allChildren.length; i++) {
                    allChildren[i].remove();
                }
                console.log('✅ Calendar grid cleared, ready to render dates for', year, month + 1);

                // Add empty cells for days before the first day of the month
                for (let i = 0; i < startDay; i++) {
                    const prevMonth = new Date(year, month, -startDay + i + 1);
                    const dayElement = document.createElement('div');
                    dayElement.className = 'py-2 text-gray-400 dark:text-gray-600 relative';
                    
                    // Check if this previous month day has events
                    const hasEvent = checkForEvents(prevMonth.getFullYear(), prevMonth.getMonth(), prevMonth.getDate());
                    
                    if (hasEvent) {
                        dayElement.innerHTML = `
                            <div class="relative inline-block w-full text-center">
                                <span class="text-gray-400 dark:text-gray-600">${prevMonth.getDate()}</span>
                                <div class="absolute top-0 right-0 w-1.5 h-1.5 bg-primary rounded-full transform translate-x-0.5 -translate-y-0.5 border-0 outline-none" style="border-radius: 50%; min-width: 6px; min-height: 6px; max-width: 6px; max-height: 6px;"></div>
                            </div>
                        `;
                    } else {
                        dayElement.textContent = prevMonth.getDate();
                    }
                    
                    calendarContainer.appendChild(dayElement);
                }

                // Add days of the current month
                for (let day = 1; day <= daysInMonth; day++) {
                    const dayElement = document.createElement('div');
                    dayElement.className = 'py-2 relative cursor-pointer transition-colors';
                    
                    // Create date string for this day
                    const dateString = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                    
                    // Check if this day has events
                    const hasEvent = checkForEvents(year, month, day);
                    const isToday = isCurrentDay(year, month, day);
                    const isSelected = window.selectedCalendarDate === dateString;
                    
                    // Determine styling based on state (use fixed circle size for alignment)
                    let dayClasses = 'inline-flex items-center justify-center w-6 h-6 mx-auto font-medium';
                    if (isToday) {
                        dayClasses += ' text-white bg-primary rounded-full';
                    } else {
                        dayClasses += ' text-text-light dark:text-text-dark';
                    }
                    
                    if (hasEvent) {
                        // Show day number with a dot indicator in upper right
                        dayElement.innerHTML = `
                            <div class="relative inline-block w-full text-center">
                                <span class="${dayClasses}">${day}</span>
                                <div class="absolute top-0 right-0 w-1.5 h-1.5 bg-primary rounded-full transform translate-x-0.5 -translate-y-0.5 border-0 outline-none" style="border-radius: 50%; min-width: 6px; min-height: 6px; max-width: 6px; max-height: 6px;"></div>
                            </div>
                        `;
                    } else {
                        // Regular day without events
                        dayElement.innerHTML = `<span class="${dayClasses}">${day}</span>`;
                    }
                    
                    // Add click listener
                    dayElement.addEventListener('click', () => {
                        selectCalendarDate(dateString);
                    });
                    
                    calendarContainer.appendChild(dayElement);
                }
                
                console.log('🔵 Added', daysInMonth, 'days for current month. Total children now:', calendarContainer.children.length);

                // Add empty cells for days after the last day of the month
                const remainingCells = 42 - (startDay + daysInMonth); // 6 rows * 7 days = 42
                for (let i = 1; i <= remainingCells; i++) {
                    const nextMonth = new Date(year, month + 1, i);
                    const dayElement = document.createElement('div');
                    dayElement.className = 'py-2 text-gray-400 dark:text-gray-600 relative';
                    
                    // Check if this next month day has events
                    const hasEvent = checkForEvents(nextMonth.getFullYear(), nextMonth.getMonth(), nextMonth.getDate());
                    
                    if (hasEvent) {
                        dayElement.innerHTML = `
                            <div class="relative inline-block w-full text-center">
                                <span class="text-gray-400 dark:text-gray-600">${nextMonth.getDate()}</span>
                                <div class="absolute top-0 right-0 w-1.5 h-1.5 bg-primary rounded-full transform translate-x-0.5 -translate-y-0.5 border-0 outline-none" style="border-radius: 50%; min-width: 6px; min-height: 6px; max-width: 6px; max-height: 6px;"></div>
                            </div>
                        `;
                    } else {
                        dayElement.textContent = nextMonth.getDate();
                    }
                    
                    calendarContainer.appendChild(dayElement);
                }
            }

            function checkForEvents(year, month, day) {
                // Check if this date has any scheduled events from database (single source of truth)
                // Use in-memory events from database, not localStorage
                const savedEvents = window.currentEvents || [];
                
                // Build local date string to avoid timezone shifts
                const y = String(year);
                const m = String(month + 1).padStart(2, '0');
                const d = String(day).padStart(2, '0');
                const dateString = `${y}-${m}-${d}`;
                
                // Check if there are events on this date (past, present, or future)
                const eventsOnDate = savedEvents.filter(event => event.date === dateString);
                const hasEvents = eventsOnDate.length > 0;
                
                // Debug logging - only show if there are actually events
                if (hasEvents) {
                    console.log(`Events found for ${dateString}:`, eventsOnDate);
                }
                
                return hasEvents;
            }

            function isCurrentDay(year, month, day) {
                // Check if this date is today
                const today = new Date();
                const checkDate = new Date(year, month, day);
                
                return today.getFullYear() === year &&
                       today.getMonth() === month &&
                       today.getDate() === day;
            }


            // Event listeners for navigation buttons
            const prevBtn = document.getElementById('prevMonth');
            const nextBtn = document.getElementById('nextMonth');
            
            if (prevBtn) {
                prevBtn.addEventListener('click', () => {
                    window.currentDate.setMonth(window.currentDate.getMonth() - 1);
                    window.updateCalendarTitle();
                    window.renderCalendar();
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', () => {
                    window.currentDate.setMonth(window.currentDate.getMonth() + 1);
                    window.updateCalendarTitle();
                    window.renderCalendar();
                });
            }

            // Initialize calendar when DOM is ready
            function initializeCalendarOnReady() {
                // Check if DOM is ready
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initializeCalendarOnReady);
                    return;
                }
                
                // Wait a bit to ensure calendar functions are defined
                setTimeout(() => {
                    // Initialize calendar variables if not already set
                    if (!window.currentDate) {
                        window.currentDate = new Date();
                    }
                    if (!window.monthNames) {
                        window.monthNames = [
                            'January', 'February', 'March', 'April', 'May', 'June',
                            'July', 'August', 'September', 'October', 'November', 'December'
                        ];
                    }
                    
                    // DOM is ready, initialize calendar
                    const calendarTitleText = document.getElementById('calendarTitleText');
                    if (calendarTitleText) {
                        // Update title if still showing "Loading..."
                        if (calendarTitleText.textContent === 'Loading...') {
                            if (typeof window.updateCalendarTitle === 'function') {
                                window.updateCalendarTitle();
                            } else {
                                // Fallback: set title directly
                                const now = new Date();
                                const month = window.monthNames ? window.monthNames[now.getMonth()] : now.toLocaleString('default', { month: 'long' });
                                const year = now.getFullYear();
                                const shortMonth = month.substring(0, 3);
                                calendarTitleText.textContent = `${shortMonth} ${year}`;
                            }
                        }
                    }
                    
                    // Render calendar - CRITICAL: Must be called to show date cells
                    if (typeof window.renderCalendar === 'function') {
                        try {
                            window.renderCalendar();
                            console.log('✅ Calendar rendered successfully');
                        } catch (error) {
                            console.error('❌ Error rendering calendar:', error);
                        }
                    } else {
                        console.error('❌ window.renderCalendar function not found!');
                    }
                    
                    // Populate dropdown
                    if (typeof window.populateMonthYearDropdown === 'function') {
                        window.populateMonthYearDropdown();
                    }
                    
                    // Set today as the initial selected date
                    const today = new Date();
                    const todayString = today.toISOString().split('T')[0];
                    window.selectedCalendarDate = todayString;
                    
                    // Ensure events table is rendered (even if empty)
                    const tableBody = document.getElementById('eventsTableBody');
                    if (tableBody && (!tableBody.innerHTML || tableBody.innerHTML.trim() === '')) {
                        // If table is empty, render empty state
                        if (typeof renderEventsTable === 'function') {
                            renderEventsTable([]);
                        }
                    }
                }, 100); // Increased delay to ensure all functions are defined
            }
            
            // Start initialization
            initializeCalendarOnReady();
            
            // Also add immediate fallback that runs right away
            (function() {
                // This runs immediately when script loads
                function forceUpdateTitleAndRender() {
                    const titleEl = document.getElementById('calendarTitleText');
                    if (titleEl && titleEl.textContent === 'Loading...') {
                        const now = new Date();
                        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                        const month = months[now.getMonth()];
                        const year = now.getFullYear();
                        titleEl.textContent = `${month} ${year}`;
                    }
                    
                    // Force render calendar if function exists
                    if (typeof window.renderCalendar === 'function') {
                        if (!window.currentDate) {
                            window.currentDate = new Date();
                        }
                        try {
                            console.log('🔵 Force rendering calendar from fallback');
                            window.renderCalendar();
                        } catch (e) {
                            console.error('❌ Error in force render:', e);
                        }
                    }
                }
                
                // Try immediately if DOM is ready
                if (document.readyState !== 'loading') {
                    setTimeout(forceUpdateTitleAndRender, 300);
                } else {
                    document.addEventListener('DOMContentLoaded', function() {
                        setTimeout(forceUpdateTitleAndRender, 300);
                    });
                }
                
                // Final fallback after 1 second
                setTimeout(forceUpdateTitleAndRender, 1000);
                // Another fallback after 2 seconds
                setTimeout(forceUpdateTitleAndRender, 2000);
            })();
            
            // CRITICAL: Force render calendar after a delay to ensure everything is loaded
            setTimeout(function() {
                console.log('🔵 Final fallback: Attempting to render calendar');
                if (typeof window.renderCalendar === 'function') {
                    if (!window.currentDate) {
                        window.currentDate = new Date();
                    }
                    try {
                        window.renderCalendar();
                        console.log('✅ Calendar rendered from final fallback');
                    } catch (e) {
                        console.error('❌ Error in final fallback render:', e);
                    }
                } else {
                    console.error('❌ window.renderCalendar not available in final fallback');
                }
                
                // Also ensure events table is rendered
                const tableBody = document.getElementById('eventsTableBody');
                if (tableBody) {
                    const hasRows = tableBody.querySelectorAll('tr').length > 0;
                    if (!hasRows && typeof renderEventsTable === 'function') {
                        console.log('🔵 Final fallback: Rendering empty events table');
                        renderEventsTable([]);
                    }
                }
            }, 1500);

            // Dropdown toggle functionality
            const calendarTitleBtn = document.getElementById('calendarTitle');
            const monthYearDropdown = document.getElementById('monthYearDropdown');
            
            if (calendarTitleBtn && monthYearDropdown) {
                calendarTitleBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    // Regenerate dropdown each time it opens to ensure it's always current
                    window.populateMonthYearDropdown();
                    monthYearDropdown.classList.toggle('hidden');
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', (e) => {
                    if (!calendarTitleBtn.contains(e.target) && !monthYearDropdown.contains(e.target)) {
                        monthYearDropdown.classList.add('hidden');
                    }
                });
            }
            
            // Add manual refresh function for debugging
            window.refreshCalendar = function() {
                console.log('Manual calendar refresh triggered');
                window.renderCalendar();
            };
            
            // Add function to check stored events
            window.checkStoredEvents = function() {
                const events = JSON.parse(localStorage.getItem('upcomingEvents') || '[]');
                console.log('All stored events:', events);
                events.forEach((event, index) => {
                    console.log(`Event ${index + 1}:`, {
                        title: event.title,
                        date: event.date,
                        id: event.id
                    });
                });
                return events;
            };
        });

        // Also initialize calendar when page loads (backup)
        window.addEventListener('load', () => {
            if (typeof window.updateCalendarTitle === 'function' && typeof window.renderCalendar === 'function') {
                window.updateCalendarTitle();
                window.renderCalendar();
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

<script>
const API_BASE = 'api/events.php';
const AUTH_TOKEN = '<?php echo $token; ?>';

window.createEvent = async function(eventData) {
    try {
        const response = await fetch(API_BASE + '?action=create', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + AUTH_TOKEN,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(eventData)
        });

        const result = await response.json();

        if (result.success) {
            showToast('Event created successfully!', 'success');
            if (typeof loadEvents === 'function') loadEvents();
            return true;
        } else {
            showToast('Error: ' + (result.error || 'Event creation failed'), 'error');
            return false;
        }
    } catch (error) {
        showToast('Error: ' + error.message, 'error');
        return false;
    }
};

window.loadEvents = async function() {
    try {
        const response = await fetch(API_BASE + '?action=list', {
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + AUTH_TOKEN
            }
        });

        const result = await response.json();

        if (result.success && result.events) {
            renderEvents(result.events);
            return result.events;
        }
    } catch (error) {
        console.error('Load events error:', error);
    }
};

window.deleteEvent = async function(eventId) {
    if (!eventId) {
        showToast('Error: Event ID is missing', 'error');
        return;
    }
    
    const confirmed = await showConfirm('Are you sure you want to delete this event?', 'Delete Event', 'Delete', 'Cancel');
    if (!confirmed) return;

    console.log('Attempting to delete event with ID:', eventId);
    
    try {
        const response = await fetch(`api/events.php?id=${encodeURIComponent(eventId)}`, {
            method: 'DELETE',
            headers: {
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
            showToast('Event deleted successfully', 'success');
            
            // Reload directly from database (single source of truth)
            // No localStorage manipulation - database is authoritative
            if (typeof loadEvents === 'function') {
                await loadEvents();
            } else if (typeof loadEventsFromDatabase === 'function') {
                await loadEventsFromDatabase();
            } else {
                // Fallback: reload page
                window.location.reload();
            }
        } else {
            const errorMsg = result.error || result.message || 'Delete failed';
            console.error('Delete failed:', errorMsg);
            showToast('Error: ' + errorMsg, 'error');
        }
    } catch (error) {
        console.error('Error deleting event:', error);
        showToast('Error: ' + error.message, 'error');
    }
};

window.updateEvent = async function(eventId, eventData) {
    try {
        const response = await fetch(API_BASE + '?action=update&id=' + eventId, {
            method: 'PUT',
            headers: {
                'Authorization': 'Bearer ' + AUTH_TOKEN,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(eventData)
        });

        const result = await response.json();

        if (result.success) {
            showToast('Event updated successfully', 'success');
            if (typeof loadEvents === 'function') loadEvents();
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

function renderEvents(events) {
    const container = document.getElementById('eventsContainer');
    if (!container) return;

    if (events.length === 0) {
        container.innerHTML = '<p class="text-center text-text-muted-light dark:text-text-muted-dark">No events found</p>';
        return;
    }

    container.innerHTML = events.map(event => `
        <div class="event-card p-4 border border-border-light dark:border-border-dark rounded-lg">
            <h3 class="font-semibold">${escapeHtml(event.title)}</h3>
            <p class="text-sm text-text-muted-light dark:text-text-muted-dark">${escapeHtml(event.description || '')}</p>
            <p class="text-xs text-text-muted-light dark:text-text-muted-dark mt-1">${event.event_date} ${event.event_time || ''}</p>
            <div class="mt-2 flex gap-2">
                <button onclick="editEvent(${event.id})" class="text-sm text-primary hover:underline">Edit</button>
                <button onclick="deleteEvent(${event.id})" class="text-sm text-red-500 hover:underline">Delete</button>
            </div>
        </div>
    `).join('');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Event delegation for admin event management buttons
document.addEventListener('click', async function(e) {
    const target = e.target.closest('button');
    if (!target) return;

    // Update Event
    if (target.classList.contains('update-event-btn')) {
        const eventId = target.dataset.eventId;
        // TODO: Implement update modal
        showToast(`Update event ${eventId} - Feature coming soon`, 'info');
    }

    // Cancel Event (update status to cancelled)
    if (target.classList.contains('cancel-event-btn')) {
        const eventId = target.dataset.eventId;
        const confirmed = await showConfirm('Are you sure you want to cancel this event?', 'Cancel Event', 'Cancel Event', 'No');
        if (!confirmed) return;

        try {
            const response = await fetch(`api/events.php?id=${eventId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ status: 'cancelled' })
            });

            const result = await response.json();
            if (result.success) {
                showToast('Event cancelled successfully', 'success');
                if (typeof loadEventsFromDatabase === 'function') {
                    await loadEventsFromDatabase();
                }
            } else {
                showToast('Error: ' + (result.error || 'Failed to cancel event'), 'error');
            }
        } catch (error) {
            showToast('Error cancelling event: ' + error.message, 'error');
        }
    }

    // Delete Event
    if (target.classList.contains('delete-event-btn')) {
        const eventId = target.dataset.eventId;
        if (!eventId) {
            showToast('Error: Event ID is missing', 'error');
            return;
        }
        
        const confirmed = await showConfirm('Are you sure you want to permanently delete this event?', 'Delete Event', 'Delete', 'Cancel');
        if (!confirmed) return;

        console.log('Attempting to delete event with ID:', eventId);
        
        try {
            const response = await fetch(`api/events.php?id=${encodeURIComponent(eventId)}`, {
                method: 'DELETE',
                headers: {
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
                showToast('Event deleted successfully', 'success');
                
                // Reload directly from database (single source of truth)
                // No localStorage manipulation - database is authoritative
                if (typeof loadEventsFromDatabase === 'function') {
                    await loadEventsFromDatabase();
                } else {
                    // Fallback: reload page if function doesn't exist
                    window.location.reload();
                }
            } else {
                const errorMsg = result.error || result.message || 'Failed to delete event';
                console.error('Delete failed:', errorMsg);
                showToast('Error: ' + errorMsg, 'error');
            }
        } catch (error) {
            console.error('Error deleting event:', error);
            showToast('Error deleting event: ' + error.message, 'error');
        }
    }
});
</script>

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
                    <div class="p-4 border-b border-border-light dark:border-border-dark hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/60 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-background-dark ${notif.is_read ? 'opacity-60' : ''}" 
                         role="button"
                         tabindex="0"
                         data-id="${notif.id}"
                         data-notification-id="${notif.id}"${urlAttribute}>
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-10 h-10 rounded-full ${bgColor} flex items-center justify-center">
                                <span class="material-symbols-outlined text-white text-lg">${icon}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-text-light dark:text-text-dark">${escapeHtml(notif.title)}</p>
                                <p class="text-sm text-text-muted-light dark:text-text-muted-dark mt-1">${escapeHtml(notif.message)}</p>
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
    <!-- Award Analysis Results Container -->
    <div id="award-analysis-results" class="award-analysis-results-container" style="display: none;"></div>
</body></html>




