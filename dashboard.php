<?php
/**
 * LILAC Dashboard - With Database Integration
 */
session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user = $_SESSION['user'];
$token = $_SESSION['token'];

// Include necessary files
require_once __DIR__ . '/api/config.php';

// Get dashboard statistics from database
$statsData = [];
try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        // File-based statistics
        $dataDir = __DIR__ . '/data/';
        $awards = [];

        if (is_dir($dataDir)) {
            $files = glob($dataDir . 'analysis_*.json');
            foreach ($files as $file) {
                $data = json_decode(file_get_contents($file), true);
                if ($data) {
                    $awards[] = $data;
                }
            }
        }

        $statsData['total_awards'] = count($awards);
        $statsData['eligible'] = 0;
        $statsData['almost_eligible'] = 0;
        $statsData['not_eligible'] = 0;
        $statsData['category_distribution'] = [];
        $statsData['category_distribution_mtd'] = [];
        $statsData['category_distribution_ytd'] = [];
        $statsData['recent_uploads'] = [];

        // Helper function to check if date is within range
        $isInDateRange = function($createdAt, $range) {
            if (empty($createdAt)) return false;
            $date = strtotime($createdAt);
            if ($date === false) return false;
            
            if ($range === 'MTD') {
                $monthStart = strtotime(date('Y-m-01'));
                return $date >= $monthStart;
            } elseif ($range === 'YTD') {
                $yearStart = strtotime(date('Y-01-01'));
                return $date >= $yearStart;
            }
            return true; // No filter
        };

        foreach ($awards as $award) {
            $status = $award['analysis_result']['status'] ?? 'Unknown';
            if ($status === 'Eligible') $statsData['eligible']++;
            elseif ($status === 'Almost Eligible') $statsData['almost_eligible']++;
            else $statsData['not_eligible']++;

            $category = $award['analysis_result']['predicted_category'] ?? 'Unknown';
            $createdAt = $award['created_at'] ?? '';
            
            // All time distribution
            $statsData['category_distribution'][$category] = ($statsData['category_distribution'][$category] ?? 0) + 1;
            
            // MTD distribution (only if eligible and within month)
            if ($status === 'Eligible' && $isInDateRange($createdAt, 'MTD')) {
                $statsData['category_distribution_mtd'][$category] = ($statsData['category_distribution_mtd'][$category] ?? 0) + 1;
            }
            
            // YTD distribution (only if eligible and within year)
            if ($status === 'Eligible' && $isInDateRange($createdAt, 'YTD')) {
                $statsData['category_distribution_ytd'][$category] = ($statsData['category_distribution_ytd'][$category] ?? 0) + 1;
            }

            $statsData['recent_uploads'][] = [
                'title' => $award['title'],
                'predicted_category' => $category,
                'match_percentage' => $award['analysis_result']['match_percentage'] ?? 0,
                'status' => $status,
                'created_at' => $createdAt
            ];
        }

        $statsData['recent_uploads'] = array_slice($statsData['recent_uploads'], 0, 5);
        $statsData['avg_match_percentage'] = count($awards) > 0 ?
            array_sum(array_column(array_column($awards, 'analysis_result'), 'match_percentage')) / count($awards) : 0;

    } else {
        // Database statistics - Get ALL awards (not filtered by user)
        // Count only awards that have a title AND file (real uploaded awards, excluding empty/test records)
        // This ensures we only count complete award uploads, not partial or test records
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM awards WHERE title IS NOT NULL AND title != '' AND file_name IS NOT NULL AND file_name != ''");
        $statsData['total_awards'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

        // Status breakdown - Get ALL eligible awards (not filtered by user)
        // Count eligible awards based on match_percentage >= 90 (same as awards management page)
        $stmt = $pdo->query("
            SELECT COUNT(*) as count
            FROM award_analysis aa
            INNER JOIN awards a ON a.id = aa.award_id
            WHERE aa.match_percentage >= 90
        ");
        $statsData['eligible'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Count almost eligible awards (70-89%)
        $stmt = $pdo->query("
            SELECT COUNT(*) as count
            FROM award_analysis aa
            INNER JOIN awards a ON a.id = aa.award_id
            WHERE aa.match_percentage >= 70 AND aa.match_percentage < 90
        ");
        $statsData['almost_eligible'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Count not eligible awards (<70%)
        $stmt = $pdo->query("
            SELECT COUNT(*) as count
            FROM award_analysis aa
            INNER JOIN awards a ON a.id = aa.award_id
            WHERE aa.match_percentage < 70
        ");
        $statsData['not_eligible'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

        // Average match
        $stmt = $pdo->prepare('
            SELECT AVG(aa.match_percentage) as avg_match
            FROM award_analysis aa
            INNER JOIN awards a ON a.id = aa.award_id
            WHERE a.user_id = ?
        ');
        $stmt->execute([$user['id']]);
        $statsData['avg_match_percentage'] = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_match'] ?? 0, 2);

        // Award distribution by specific award names (eligible awards only)
        // List of 8 specific awards to track (with variations for matching)
        $specificAwards = [
            'Global Citizenship Award',
            'Best ASEAN Awareness Award',
            'Internationalization Leadership Award',
            'Outstanding International Education Award',
            'Outstanding International Education Program Award', // Note: full name variation
            'Most Promising IRO/Community Award',
            'Emerging Leadership in Internationalization Award',
            'Best CHED Regional Office for Internationalization',
            'Sustainability in Internationalization Award'
        ];
        
        // Initialize all 8 awards with 0 count (use only the 8 display awards, not the variations)
        $displayAwards = [
            'Global Citizenship Award',
            'Best ASEAN Awareness Award',
            'Internationalization Leadership Award',
            'Outstanding International Education Award',
            'Most Promising IRO/Community Award',
            'Emerging Leadership in Internationalization Award',
            'Best CHED Regional Office for Internationalization',
            'Sustainability in Internationalization Award'
        ];
        
        // Helper function to calculate category distribution for a date range
        $calculateCategoryDistribution = function($dateFilter = null, $monthYear = null) use ($pdo, $displayAwards, $specificAwards) {
            $categoryDistribution = [];
            foreach ($displayAwards as $awardName) {
                $categoryDistribution[$awardName] = 0;
            }
            
            // Build query with optional date filter
            $dateCondition = "";
            if ($dateFilter === 'MTD') {
                if ($monthYear) {
                    // Specific month selected (format: YYYY-MM)
                    $dateCondition = "AND DATE_FORMAT(a.created_at, '%Y-%m') = '" . $monthYear . "'";
                } else {
                    // Current month
                    $dateCondition = "AND DATE(a.created_at) >= DATE_FORMAT(NOW(), '%Y-%m-01')";
                }
            } elseif ($dateFilter === 'YTD') {
                $dateCondition = "AND DATE(a.created_at) >= DATE_FORMAT(NOW(), '%Y-01-01')";
            }
            
            $stmt = $pdo->query("
                SELECT 
                    a.id,
                    a.title as award_title,
                    aa.predicted_category,
                    aa.match_percentage
                FROM award_analysis aa
                INNER JOIN awards a ON a.id = aa.award_id
                WHERE aa.match_percentage >= 90
                $dateCondition
                ORDER BY aa.match_percentage DESC
            ");
            
            $eligibleAwards = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Map each eligible award to a specific award name using fuzzy matching
            foreach ($eligibleAwards as $award) {
            $predictedCategory = $award['predicted_category'] ?? '';
            $awardTitle = $award['award_title'] ?? '';
            $awardName = !empty($predictedCategory) ? $predictedCategory : $awardTitle;
            
            // Normalize award name for matching (remove common variations)
            $normalizedAwardName = str_ireplace([' Program', ' Award'], '', $awardName);
            
            // Try to match with display awards
            $matched = false;
            foreach ($displayAwards as $displayAward) {
                // Normalize display award name
                $normalizedDisplay = str_ireplace([' Program', ' Award'], '', $displayAward);
                
                // Check for match (exact or normalized)
                if (strcasecmp($awardName, $displayAward) === 0 || 
                    stripos($awardName, $displayAward) !== false || 
                    stripos($displayAward, $awardName) !== false ||
                    strcasecmp($normalizedAwardName, $normalizedDisplay) === 0 ||
                    stripos($normalizedAwardName, $normalizedDisplay) !== false ||
                    stripos($normalizedDisplay, $normalizedAwardName) !== false) {
                    
                    // Special case: "Outstanding International Education Program Award" -> "Outstanding International Education Award"
                    if (stripos($awardName, 'Outstanding International Education') !== false) {
                        $categoryDistribution['Outstanding International Education Award']++;
                    } else {
                        $categoryDistribution[$displayAward]++;
                    }
                    $matched = true;
                    break;
                }
            }
            
            // If no match found, try to match by partial string in specific awards list (for variations)
            if (!$matched) {
                foreach ($specificAwards as $specificAward) {
                    if (stripos($awardName, $specificAward) !== false || 
                        stripos($specificAward, $awardName) !== false) {
                        
                        // Map to corresponding display award
                        if (stripos($awardName, 'Outstanding International Education') !== false) {
                            $categoryDistribution['Outstanding International Education Award']++;
                        } else {
                            // Try to find matching display award
                            foreach ($displayAwards as $displayAward) {
                                if (stripos($specificAward, $displayAward) !== false || 
                                    stripos($displayAward, $specificAward) !== false) {
                                    $categoryDistribution[$displayAward]++;
                                    break;
                                }
                            }
                        }
                        break;
                    }
                }
            }
            }
            
            return $categoryDistribution;
        };
        
        // Load user preference for selected month
        $selectedMonth = date('Y-m'); // Default to current month
        $selectedFilter = 'YTD'; // Default filter
        try {
            $prefStmt = $pdo->prepare('SELECT preference_value FROM user_preferences WHERE user_id = ? AND preference_key = ?');
            $prefStmt->execute([$user['id'], 'dashboard_awards_filter']);
            $filterPref = $prefStmt->fetch(PDO::FETCH_ASSOC);
            if ($filterPref) {
                $selectedFilter = $filterPref['preference_value'] ?? 'YTD';
            }
            
            $prefStmt->execute([$user['id'], 'dashboard_awards_month']);
            $monthPref = $prefStmt->fetch(PDO::FETCH_ASSOC);
            if ($monthPref) {
                $selectedMonth = $monthPref['preference_value'] ?? date('Y-m');
            }
        } catch (Exception $e) {
            // Preferences table might not exist yet, use defaults
        }
        
        // Calculate both MTD and YTD distributions
        $statsData['category_distribution'] = $calculateCategoryDistribution('YTD'); // Default to YTD
        $statsData['category_distribution_mtd'] = $calculateCategoryDistribution('MTD', $selectedMonth);
        $statsData['category_distribution_ytd'] = $calculateCategoryDistribution('YTD');
        $statsData['selected_filter'] = $selectedFilter;
        $statsData['selected_month'] = $selectedMonth;
        
        // Get available months for MTD dropdown (last 12 months)
        $availableMonths = [];
        for ($i = 0; $i < 12; $i++) {
            $monthDate = date('Y-m', strtotime("-$i months"));
            $monthLabel = date('M Y', strtotime("-$i months"));
            $availableMonths[] = [
                'value' => $monthDate,
                'label' => $monthLabel
            ];
        }
        $statsData['available_months'] = $availableMonths;

        // Recent uploads
        $stmt = $pdo->prepare('
            SELECT a.*, aa.predicted_category, aa.match_percentage, aa.status
            FROM awards a
            LEFT JOIN award_analysis aa ON aa.award_id = a.id
            WHERE a.user_id = ?
            ORDER BY a.created_at DESC
            LIMIT 5
        ');
        $stmt->execute([$user['id']]);
        $statsData['recent_uploads'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get additional statistics for dashboard
        // Upcoming events - all future events (event_date >= today)
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM events WHERE event_date >= CURDATE()");
        $statsData['upcoming_events'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Active schedules - schedules for today only (scheduled_date = today, status = 'scheduled' only)
        // Only count schedules with status 'scheduled' as active (exclude 'completed' and 'cancelled')
        // Use DATE() function to ensure we're comparing date parts only, and CAST to handle any datetime fields
        // Note: Since scheduled_date is already a DATE type, we can compare directly, but using DATE() ensures compatibility
        $stmt = $pdo->query("
            SELECT COUNT(*) as count 
            FROM schedules 
            WHERE scheduled_date = CURDATE() 
            AND status = 'scheduled'
        ");
        $activeCount = $stmt->fetch(PDO::FETCH_ASSOC);
        $statsData['active_schedules'] = isset($activeCount['count']) ? (int)$activeCount['count'] : 0;
        
        // Additional check: Count all schedules for today regardless of status (for debugging)
        $todayCountStmt = $pdo->query("SELECT COUNT(*) as count FROM schedules WHERE scheduled_date = CURDATE()");
        $todayCount = $todayCountStmt->fetch(PDO::FETCH_ASSOC);
        error_log("Total schedules for today (any status): " . ($todayCount['count'] ?? 0));
        
        // Check if there are schedules with 'scheduled' status for any date today
        $scheduledTodayStmt = $pdo->query("SELECT COUNT(*) as count FROM schedules WHERE scheduled_date = CURDATE() AND status = 'scheduled'");
        $scheduledTodayCount = $scheduledTodayStmt->fetch(PDO::FETCH_ASSOC);
        error_log("Schedules with status 'scheduled' for today: " . ($scheduledTodayCount['count'] ?? 0));
        
        // Debug: Log active schedules query result and check actual data
        error_log("Active schedules today: " . $statsData['active_schedules'] . " (Query: DATE(scheduled_date) = CURDATE() AND status = 'scheduled')");
        
        // Debug: Check all schedules for today regardless of status
        $debugStmt = $pdo->query("SELECT id, title, scheduled_date, status, DATE(scheduled_date) as date_part FROM schedules WHERE DATE(scheduled_date) = CURDATE()");
        $debugSchedules = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("All schedules for today (any status): " . json_encode($debugSchedules));
        
        // Debug: Check what CURDATE() returns
        $dateStmt = $pdo->query("SELECT CURDATE() as today, NOW() as now_time");
        $todayDate = $dateStmt->fetch(PDO::FETCH_ASSOC);
        error_log("CURDATE() returns: " . ($todayDate['today'] ?? 'unknown') . ", NOW() returns: " . ($todayDate['now_time'] ?? 'unknown'));
        
        // Debug: Check all schedules ordered by date to see what's in the database
        $allStmt = $pdo->query("SELECT id, title, scheduled_date, status, DATE(scheduled_date) as date_part FROM schedules ORDER BY scheduled_date DESC LIMIT 10");
        $allSchedules = $allStmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Recent schedules in database (last 10): " . json_encode($allSchedules));
        
        // Debug: Check specifically for schedules with Nov 15 date (to verify what's stored)
        $nov15Stmt = $pdo->query("SELECT id, title, scheduled_date, status, DATE(scheduled_date) as date_part FROM schedules WHERE scheduled_date = '2025-11-15' OR scheduled_date LIKE '%2025-11-15%' OR DATE(scheduled_date) = '2025-11-15'");
        $nov15Schedules = $nov15Stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Schedules for Nov 15, 2025: " . json_encode($nov15Schedules));
        
        // Debug: Check schedules with today's date in different formats
        $todayFormatted = date('Y-m-d');
        $todayStmt = $pdo->prepare("SELECT id, title, scheduled_date, status FROM schedules WHERE scheduled_date = ? OR DATE(scheduled_date) = ?");
        $todayStmt->execute([$todayFormatted, $todayFormatted]);
        $todaySchedules = $todayStmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Schedules for today ($todayFormatted) using PHP date: " . json_encode($todaySchedules));
        
        // Debug: Check all schedules with their status to see if any have non-'scheduled' status
        $allWithStatusStmt = $pdo->query("SELECT id, title, scheduled_date, status FROM schedules ORDER BY scheduled_date DESC LIMIT 5");
        $allWithStatus = $allWithStatusStmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Recent schedules with status (last 5): " . json_encode($allWithStatus));
        
        // Upcoming schedules - schedules for tomorrow (next day, status = 'scheduled' only)
        // Count schedules for tomorrow (next day) that are scheduled (not completed or cancelled)
        $stmt = $pdo->query("
            SELECT COUNT(*) as count 
            FROM schedules 
            WHERE DATE(scheduled_date) = DATE_ADD(CURDATE(), INTERVAL 1 DAY) 
            AND status = 'scheduled'
        ");
        $upcomingCount = $stmt->fetch(PDO::FETCH_ASSOC);
        $statsData['upcoming_schedules'] = isset($upcomingCount['count']) ? (int)$upcomingCount['count'] : 0;
        
        // Debug: Log upcoming schedules query result
        error_log("Upcoming schedules tomorrow: " . $statsData['upcoming_schedules']);
        
        // Total signed MOUs - all uploaded MOUs/MOAs
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM mou_moa");
        $statsData['signed_mous'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // MOUs/MOAs that need renewal (expiring soon - within next 90 days, or already expired)
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM mou_moa WHERE (end_date >= CURDATE() AND end_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)) OR end_date < CURDATE()");
        $statsData['pending_renewal_mous'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Total documents - all files uploaded in Documents page
        // Count from both mou_moa and other_documents tables (same as Documents page)
        $stmt = $pdo->query("
            SELECT COUNT(*) as count FROM (
                SELECT id FROM mou_moa
                UNION ALL
                SELECT id FROM other_documents
            ) as combined_documents
        ");
        $statsData['total_documents'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Get upcoming events for table
        $stmt = $pdo->query("SELECT title, event_date, location, status FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 4");
        $statsData['upcoming_events_list'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get recent activity (simplified - can be enhanced)
        $statsData['recent_activity'] = [];
    }
} catch (Exception $e) {
    error_log('Dashboard stats error: ' . $e->getMessage());
    $statsData = [
        'total_awards' => 0,
        'eligible' => 0,
        'almost_eligible' => 0,
        'not_eligible' => 0,
        'avg_match_percentage' => 0,
        'category_distribution' => [],
        'recent_uploads' => [],
        'total_events' => 0,
        'upcoming_events' => 0,
        'active_schedules' => 0,
        'upcoming_schedules' => 0,
        'signed_mous' => 0,
        'pending_renewal_mous' => 0,
        'total_documents' => 0,
        'upcoming_events_list' => [],
        'recent_activity' => []
    ];
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>LILAC Dashboard</title>
<script>
// XAMPP/Apache version - no port redirect needed
(function() {
    console.log('LILAC Dashboard - Running on XAMPP/Apache with PHP backend');
})();
</script>
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
        
        // Control entrance animation - only show on first login
        (function() {
            // Check if this is a fresh login (no previous session)
            const hasSeenDashboard = sessionStorage.getItem('hasSeenDashboard');
            
            if (hasSeenDashboard) {
                // User has already seen dashboard in this session, disable animation
                document.addEventListener('DOMContentLoaded', function() {
                    document.body.classList.add('no-animation');
                });
            } else {
                // First time seeing dashboard in this session, show animation
                sessionStorage.setItem('hasSeenDashboard', 'true');
            }
        })();
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
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
        /* Sidebar entrance animation */
        @keyframes slideInFromLeft {
            from {
                transform: translateX(-100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideInFromRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes fadeInUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .sidebar {
            animation: slideInFromLeft 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .main-content {
            animation: slideInFromRight 0.6s cubic-bezier(0.4, 0, 0.2, 1) 0.2s both;
        }
        
        /* Only show animation on first visit to dashboard */
        .no-animation .sidebar {
            animation: none;
        }
        
        .no-animation .main-content {
            animation: none;
        }
        
        .no-animation .content-card {
            animation: none;
        }
        
        /* Staggered animations for content */
        .content-card {
            animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1) both;
        }
        
        .content-card:nth-child(1) { animation-delay: 0.4s; }
        .content-card:nth-child(2) { animation-delay: 0.5s; }
        .content-card:nth-child(3) { animation-delay: 0.6s; }
        .content-card:nth-child(4) { animation-delay: 0.7s; }
        .content-card:nth-child(5) { animation-delay: 0.8s; }
        
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

        /* Ensure month selector dropdown opens downward */
        /* The browser automatically opens dropdowns downward when there's enough space below */
        /* This ensures the select element is positioned to encourage downward opening */
        #awardsMonthFilter {
            position: relative;
        }
        
        /* Remove default dropdown arrow from select elements */
        #awardsTimeFilter,
        #awardsMonthFilter {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: none;
        }
        
        /* Custom arrow container for awardsTimeFilter */
        #awardsTimeFilter {
            padding-right: 2rem;
        }
        
        #awardsMonthFilter {
            padding-right: 2rem;
        }
        
        /* Remove all extra spacing from month filter container */
        #awardsMonthFilter + span {
            margin: 0;
            padding: 0;
        }
        
        /* Ensure no extra space around the month filter button */
        .flex.gap-2.items-center > div.relative.inline-block {
            margin: 0;
            padding: 0;
            display: inline-block;
            vertical-align: middle;
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
<a class="flex items-center justify-center gap-3 px-4 py-2.5 rounded-lg bg-blue-50 dark:bg-blue-900/40 text-primary-600 dark:text-primary-400 font-semibold sidebar-nav-link" href="dashboard.php">
<span class="material-symbols-outlined filled">dashboard</span>
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
<div class="p-8">
<header class="flex justify-between items-center mb-8">
<div class="relative w-full max-w-sm">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
<input class="w-full pl-10 pr-4 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition" placeholder="Search..." type="text"/>
</div>
<div class="flex items-center gap-4">
						<div class="relative z-[9999]">
    <button id="notificationBtn" class="p-2 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors relative">
        <span class="material-symbols-outlined text-slate-600 dark:text-slate-400">notifications</span>
        <span id="notificationBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
    </button>
    <div id="notificationDropdown" class="absolute right-0 top-full mt-2 w-96 bg-white dark:bg-background-dark rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 z-[9999] hidden max-h-96 overflow-y-auto">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Notifications</h3>
                <button id="markAllReadBtn" class="text-sm text-primary hover:text-primary/80 dark:text-primary-400 dark:hover:text-primary-300">Mark all read</button>
            </div>
        </div>
        <div id="notificationList" class="max-h-80 overflow-y-auto">
            <div id="noNotifications" class="p-6 text-center text-gray-500 dark:text-gray-400">
                <span class="material-symbols-outlined text-4xl mb-2 block">notifications_off</span>
                <p>No notifications</p>
            </div>
        </div>
        <div class="p-4 border-t border-gray-200 dark:border-gray-700">
            <button id="viewAllNotifications" class="w-full text-center text-sm text-primary hover:text-primary/80 dark:text-primary-400 dark:hover:text-primary-300">View all notifications</button>
        </div>
    </div>
</div>
<button class="p-2 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" id="theme-toggle">
<span class="material-symbols-outlined text-slate-600 dark:text-slate-400 dark:hidden">light_mode</span>
<span class="material-symbols-outlined text-slate-600 dark:text-slate-400 hidden dark:inline">dark_mode</span>
</button>
<div class="h-10 w-px bg-slate-200 dark:bg-slate-700"></div>
<div class="flex items-center gap-3">
<img alt="User avatar" class="w-10 h-10 rounded-full" src="<?php echo !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : 'https://lh3.googleusercontent.com/aida-public/AB6AXuC23fvgOSZIK6K5vguUgvVeU1XYFfp1LB3d4zICMvW6bispRl-eHHfnOtSsvRU3MgvmOpSYMCZhcSBIksvjlEHtkGMxuCFsQkuT0suo2-O9n3py7mlzFFETXCOIfvLVGGUj1aaG8ENOeDXXy_ifek2uG3R3--ghDflKvuAm9vrceoK8doav0lNYVbLz1bnWy6REWcrCPuPZZ8upfPqShoQpSDjICl16zMEcRuHzjt05z9cFITLKPdZTfMF-1dLK-klh8UhjeDeE4Q7p'; ?>"/>
<div>
<p class="font-semibold text-slate-800 dark:text-slate-100 text-sm"><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></p>
<p class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
</div>
</div>
</div>
</header>
<div class="space-y-8">
<div>
<h2 class="text-3xl font-bold text-slate-900 dark:text-white">Dashboard</h2>
<p class="mt-1 text-slate-500 dark:text-slate-400">Overview of your lilac system.</p>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
<a href="user-awards.php" class="bg-primary text-white p-6 rounded-lg flex flex-col justify-between cursor-pointer transform transition-all duration-200 hover:scale-105 hover:shadow-lg active:scale-95">
<div class="flex justify-between items-start">
<h3 class="font-semibold text-white/90">Total Awards</h3>
<span class="material-symbols-outlined text-white/80">emoji_events</span>
</div>
<div>
<p class="text-4xl font-bold"><?php echo htmlspecialchars($statsData['total_awards'] ?? 0); ?></p>
<p class="text-sm text-white/70 mt-1">+<?php echo htmlspecialchars($statsData['eligible'] ?? 0); ?> eligible</p>
</div>
</a>
<a href="events-activities.php" class="bg-white dark:bg-slate-800 p-6 rounded-lg cursor-pointer transform transition-all duration-200 hover:scale-105 hover:shadow-lg active:scale-95 border-2 border-transparent hover:border-primary/20">
<div class="flex justify-between items-start">
<h3 class="font-semibold text-slate-600 dark:text-slate-300">Upcoming Events</h3>
<span class="material-symbols-outlined text-slate-400 dark:text-slate-500">event</span>
</div>
<div>
<p class="text-4xl font-bold mt-4 text-slate-900 dark:text-white"><?php echo htmlspecialchars($statsData['upcoming_events'] ?? 0); ?></p>
<p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Future events</p>
</div>
</a>
<a href="scheduler.php" class="bg-white dark:bg-slate-800 p-6 rounded-lg cursor-pointer transform transition-all duration-200 hover:scale-105 hover:shadow-lg active:scale-95 border-2 border-transparent hover:border-primary/20">
<div class="flex justify-between items-start">
<h3 class="font-semibold text-slate-600 dark:text-slate-300">Active Schedules</h3>
<span class="material-symbols-outlined text-slate-400 dark:text-slate-500">calendar_month</span>
</div>
<div>
<p class="text-4xl font-bold mt-4 text-slate-900 dark:text-white"><?php echo htmlspecialchars($statsData['active_schedules'] ?? 0); ?></p>
<p class="text-sm text-slate-500 dark:text-slate-400 mt-1"><?php echo htmlspecialchars($statsData['upcoming_schedules'] ?? 0); ?> upcoming</p>
</div>
</a>
<a href="mou-moa.php" class="bg-white dark:bg-slate-800 p-6 rounded-lg cursor-pointer transform transition-all duration-200 hover:scale-105 hover:shadow-lg active:scale-95 border-2 border-transparent hover:border-primary/20">
<div class="flex justify-between items-start">
<h3 class="font-semibold text-slate-600 dark:text-slate-300">Signed MOUs</h3>
<span class="material-symbols-outlined text-slate-400 dark:text-slate-500">handshake</span>
</div>
<div>
<p class="text-4xl font-bold mt-4 text-slate-900 dark:text-white"><?php echo htmlspecialchars($statsData['signed_mous'] ?? 0); ?></p>
<p class="text-sm text-slate-500 dark:text-slate-400 mt-1"><?php echo htmlspecialchars($statsData['pending_renewal_mous'] ?? 0); ?> need renewal</p>
</div>
</a>
<a href="documents.php" class="bg-white dark:bg-slate-800 p-6 rounded-lg cursor-pointer transform transition-all duration-200 hover:scale-105 hover:shadow-lg active:scale-95 border-2 border-transparent hover:border-primary/20">
<div class="flex justify-between items-start">
<h3 class="font-semibold text-slate-600 dark:text-slate-300">Documents</h3>
<span class="material-symbols-outlined text-slate-400 dark:text-slate-500">folder</span>
</div>
<div>
<p class="text-4xl font-bold mt-4 text-slate-900 dark:text-white"><?php echo htmlspecialchars($statsData['total_documents'] ?? 0); ?></p>
<p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Total files</p>
</div>
</a>
</div>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
<div class="lg:col-span-2 bg-white dark:bg-slate-800 p-6 rounded-lg">
<div class="flex justify-between items-center mb-4">
<h3 class="text-lg font-semibold text-slate-900 dark:text-white">Awards by Category</h3>
<div class="flex gap-2 items-center" style="position: relative; z-index: 10;">
<div class="relative inline-block" style="position: relative; margin: 0;">
<select id="awardsMonthFilter" class="px-3 py-1.5 pr-8 text-sm border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent hidden">
<?php foreach ($statsData['available_months'] ?? [] as $month): ?>
<option value="<?php echo htmlspecialchars($month['value']); ?>" <?php echo ($month['value'] === ($statsData['selected_month'] ?? date('Y-m'))) ? 'selected' : ''; ?>><?php echo htmlspecialchars($month['label']); ?></option>
<?php endforeach; ?>
</select>
<span class="absolute right-2 top-1/2 transform -translate-y-1/2 pointer-events-none text-slate-500 dark:text-slate-400">
<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
</svg>
</span>
</div>
<div class="relative inline-block">
<select id="awardsTimeFilter" class="px-3 py-1.5 pr-8 text-sm border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
<option value="YTD" <?php echo (($statsData['selected_filter'] ?? 'YTD') === 'YTD') ? 'selected' : ''; ?>>YTD</option>
<option value="MTD" <?php echo (($statsData['selected_filter'] ?? 'YTD') === 'MTD') ? 'selected' : ''; ?>>MTD</option>
</select>
<span class="absolute right-2 top-1/2 transform -translate-y-1/2 pointer-events-none text-slate-500 dark:text-slate-400">
<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
</svg>
</span>
</div>
</div>
</div>
<div class="h-80">
<canvas id="awardsChart"></canvas>
</div>
</div>
<div class="bg-white dark:bg-slate-800 p-6 rounded-lg">
<h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-4">Document Types</h3>
<div class="h-80 flex items-center justify-center">
<canvas id="docsChart"></canvas>
</div>
</div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
<div class="lg:col-span-3 bg-white dark:bg-slate-800 p-6 rounded-lg">
<div class="flex justify-between items-center mb-4">
<h3 class="text-lg font-semibold text-slate-900 dark:text-white">Upcoming Events</h3>
<button class="text-sm font-medium text-primary hover:underline" onclick="window.location.href='events-activities.php'">View All</button>
</div>
<div class="overflow-x-auto">
<table class="w-full text-sm text-left">
<thead class="text-xs text-slate-500 dark:text-slate-400 uppercase border-b border-slate-200 dark:border-slate-700">
<tr>
<th class="py-3 pr-6" scope="col">Event Name</th>
<th class="py-3 px-6" scope="col">Date</th>
<th class="py-3 px-6" scope="col">Location</th>
<th class="py-3 pl-6" scope="col">Status</th>
</tr>
</thead>
<tbody>
<?php if (!empty($statsData['upcoming_events_list'])): ?>
<?php foreach ($statsData['upcoming_events_list'] as $event): ?>
<tr class="border-b border-slate-200 dark:border-slate-700">
<td class="py-4 pr-6 font-medium text-slate-900 dark:text-white"><?php echo htmlspecialchars($event['title'] ?? 'Untitled Event'); ?></td>
<td class="py-4 px-6 text-slate-600 dark:text-slate-300"><?php echo !empty($event['event_date']) ? date('M d, Y', strtotime($event['event_date'])) : 'N/A'; ?></td>
<td class="py-4 px-6 text-slate-600 dark:text-slate-300"><?php echo htmlspecialchars($event['location'] ?? 'TBD'); ?></td>
<td class="py-4 pl-6">
<?php
$status = $event['status'] ?? 'planned';
$statusClass = $status === 'confirmed' ? 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300' : 
               ($status === 'scheduled' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300' : 
               'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300');
$statusText = ucfirst($status);
?>
<span class="text-xs font-medium px-2.5 py-0.5 rounded-full <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusText); ?></span>
</td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr>
<td colspan="4" class="py-8 text-center text-slate-500 dark:text-slate-400">No upcoming events</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
<div class="lg:col-span-2 bg-white dark:bg-slate-800 p-6 rounded-lg">
<div class="flex justify-between items-center mb-4">
<h3 class="text-lg font-semibold text-slate-900 dark:text-white">Recent Activity</h3>
<button class="text-sm font-medium text-primary hover:underline">View Log</button>
</div>
<ul class="space-y-4">
<li class="flex items-start gap-4">
<div class="bg-blue-100 dark:bg-blue-900/50 p-2 rounded-full">
<span class="material-symbols-outlined text-blue-600 dark:text-blue-300 text-base">upload_file</span>
</div>
<div>
<p class="text-sm text-slate-800 dark:text-slate-200"><span class="font-semibold"><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></span> uploaded a new MOU.</p>
<p class="text-xs text-slate-500 dark:text-slate-400">Recently</p>
</div>
</li>
<li class="flex items-start gap-4">
<div class="bg-green-100 dark:bg-green-900/50 p-2 rounded-full">
<span class="material-symbols-outlined text-green-600 dark:text-green-300 text-base">emoji_events</span>
</div>
<div>
<p class="text-sm text-slate-800 dark:text-slate-200">Award submission was processed.</p>
<p class="text-xs text-slate-500 dark:text-slate-400">Recently</p>
</div>
</li>
<li class="flex items-start gap-4">
<div class="bg-purple-100 dark:bg-purple-900/50 p-2 rounded-full">
<span class="material-symbols-outlined text-purple-600 dark:text-purple-300 text-base">event_available</span>
</div>
<div>
<p class="text-sm text-slate-800 dark:text-slate-200">New event was scheduled.</p>
<p class="text-xs text-slate-500 dark:text-slate-400">Recently</p>
</div>
</li>
</ul>
</div>
</div>
</div>
</div>
</main>
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
            // Function to toggle sidebar
            const toggleSidebar = () => {
                const isCollapsed = appContainer.classList.contains('sidebar-collapsed');
                if (isCollapsed) {
                    appContainer.classList.remove('sidebar-collapsed');
                    sidebar.style.width = '16rem';
                    mainContent.style.marginLeft = '0';
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
                    sidebar.style.width = '5rem';
                    mainContent.style.marginLeft = '5rem';
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
                // Add a small delay for the chart to re-render after transition
                setTimeout(() => {
                    Chart.helpers.each(Chart.instances, (instance) => {
                        instance.resize();
                    });
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
                // Charts will update automatically via MutationObserver
            });
            // Chart.js rendering for Awards by Category
            const renderCharts = () => {
                const isDarkMode = () => document.documentElement.classList.contains('dark');
                
                // Awards Chart (Bar Chart)
                const awardsCtx = document.getElementById('awardsChart');
                if (awardsCtx) {
                    // Destroy existing chart instance if it exists
                    if (window.awardsChartInstance) {
                        window.awardsChartInstance.destroy();
                    }
                    
                    // Get initial data from PHP
                    let categoryDataMTD = <?php echo json_encode($statsData['category_distribution_mtd'] ?? []); ?>;
                    const categoryDataYTD = <?php echo json_encode($statsData['category_distribution_ytd'] ?? []); ?>;
                    const selectedFilter = '<?php echo htmlspecialchars($statsData['selected_filter'] ?? 'YTD'); ?>';
                    const selectedMonth = '<?php echo htmlspecialchars($statsData['selected_month'] ?? date('Y-m')); ?>';
                    
                    // Ensure labels are in the correct order (8 specific awards)
                    // Use shortened names for X-axis to save space
                    const awardLabelMap = {
                        'Global Citizenship Award': 'Global Citizenship',
                        'Best ASEAN Awareness Award': 'ASEAN Awareness',
                        'Internationalization Leadership Award': 'Intl. Leadership',
                        'Outstanding International Education Award': 'Intl. Education',
                        'Most Promising IRO/Community Award': 'IRO/Community',
                        'Emerging Leadership in Internationalization Award': 'Emerging Leadership',
                        'Best CHED Regional Office for Internationalization': 'CHED Regional',
                        'Sustainability in Internationalization Award': 'Sustainability'
                    };
                    
                    const specificAwardsOrder = [
                        'Global Citizenship Award',
                        'Best ASEAN Awareness Award',
                        'Internationalization Leadership Award',
                        'Outstanding International Education Award',
                        'Most Promising IRO/Community Award',
                        'Emerging Leadership in Internationalization Award',
                        'Best CHED Regional Office for Internationalization',
                        'Sustainability in Internationalization Award'
                    ];
                    
                    // Store full names for tooltips
                    const fullAwardNames = specificAwardsOrder;
                    const chartLabels = specificAwardsOrder.map(award => awardLabelMap[award] || award);
                    
                    // Function to get chart data based on filter
                    const getChartData = (filter, mtdData = null) => {
                        const categoryData = filter === 'MTD' ? (mtdData || categoryDataMTD) : categoryDataYTD;
                        return specificAwardsOrder.map(label => categoryData[label] || 0);
                    };
                    
                    // Function to save preference
                    const savePreference = async (key, value) => {
                        try {
                            const response = await fetch('api/user-preferences.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({ key: key, value: value })
                            });
                            const result = await response.json();
                            if (!result.success) {
                                console.error('Failed to save preference:', result.error);
                            }
                        } catch (error) {
                            console.error('Error saving preference:', error);
                        }
                    };
                    
                    // Function to fetch MTD data for a specific month
                    const fetchMTDData = async (monthYear) => {
                        try {
                            const response = await fetch(`api/dashboard-mtd-data.php?month=${encodeURIComponent(monthYear)}`);
                            const result = await response.json();
                            if (result.success && result.data) {
                                return result.data;
                            }
                            return {};
                        } catch (error) {
                            console.error('Error fetching MTD data:', error);
                            return {};
                        }
                    };
                    
                    // Initialize with saved filter
                    let currentFilter = selectedFilter;
                    let currentMonth = selectedMonth;
                    let chartData = getChartData(currentFilter);
                    
                    // Create chart instance
                    const awardsChart = new Chart(awardsCtx, {
                        type: 'bar',
                        data: {
                            labels: chartLabels,
                    datasets: [{
                                label: 'Number of Eligible Awards',
                                data: chartData,
                                backgroundColor: '#14704E',
                                borderColor: '#14704E',
                                borderWidth: 1,
                                borderRadius: 6,
                                barPercentage: 0.6,
                            }]
                        },
                        options: {
                    responsive: true,
                            maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                                    enabled: true,
                                    backgroundColor: isDarkMode() ? 'rgba(30, 41, 59, 0.95)' : 'rgba(255, 255, 255, 0.95)',
                                    titleColor: isDarkMode() ? '#F1F5F9' : '#0F172A',
                                    bodyColor: isDarkMode() ? '#CBD5E1' : '#475569',
                                    borderColor: isDarkMode() ? 'rgba(148, 163, 184, 0.2)' : 'rgba(148, 163, 184, 0.2)',
                                    borderWidth: 1,
                                    padding: 12,
                                    displayColors: false,
                            callbacks: {
                                        title: function(context) {
                                            // Show full award name in tooltip
                                            const index = context[0].dataIndex;
                                            return fullAwardNames[index] || context[0].label;
                                        },
                                label: function(context) {
                                            return context.parsed.y + ' eligible award(s)';
                                        },
                                        labelColor: function() {
                                            return {
                                                borderColor: '#14704E',
                                                backgroundColor: '#14704E'
                                            };
                                        }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                                    max: currentFilter === 'MTD' ? 20 : 100, // MTD: 20, YTD: 100
                            grid: {
                                        color: isDarkMode() ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)'
                            },
                            ticks: {
                                        color: isDarkMode() ? '#CBD5E1' : '#475569',
                                        stepSize: currentFilter === 'MTD' ? 5 : 10, // MTD: step 5, YTD: step 10
                                callback: function(value) {
                                            // Show ticks based on filter
                                            if (currentFilter === 'MTD') {
                                                if ([0, 5, 10, 15, 20].includes(value)) {
                                                    return value;
                                                }
                                            } else {
                                                if ([0, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100].includes(value)) {
                                                    return value;
                                                }
                                            }
                                            return '';
                                        },
                                        font: {
                                            size: 11,
                                            weight: '500'
                                        },
                                        padding: 8
                                    },
                                    title: {
                                        display: true,
                                        text: 'Eligible Awards Count',
                                        color: isDarkMode() ? '#94A3B8' : '#64748B',
                                        font: {
                                            size: 11,
                                            weight: '500'
                                        },
                                        padding: {
                                            bottom: 10
                                }
                            }
                        },
                        x: {
                            grid: {
                                        display: false
                            },
                            ticks: {
                                        display: false // Hide X-axis labels - show only on hover
                                    },
                                    title: {
                                        display: true,
                                        text: 'Hover over bars to see award names',
                                        color: isDarkMode() ? '#94A3B8' : '#64748B',
                                        font: {
                                            size: 10
                                        },
                                        padding: {
                                            top: 10
                                        }
                                    }
                                }
                            }
                        }
                    });
                    
                    // Store chart instance globally for updates
                    window.awardsChartInstance = awardsChart;
                    
                    // Get filter elements
                    let timeFilter = document.getElementById('awardsTimeFilter');
                    let monthFilter = document.getElementById('awardsMonthFilter');
                    
                    // Show/hide month filter based on selected filter
                    const updateMonthFilterVisibility = () => {
                        // Re-get element in case it was cloned
                        monthFilter = document.getElementById('awardsMonthFilter');
                        if (monthFilter) {
                            if (currentFilter === 'MTD') {
                                monthFilter.classList.remove('hidden');
                            } else {
                                monthFilter.classList.add('hidden');
                            }
                        }
                    };
                    
                    // Update chart with new data and max value
                    const updateChart = async (filter, month = null) => {
                        let data = chartData;
                        let maxValue = 100;
                        
                        if (filter === 'MTD') {
                            maxValue = 20;
                            if (month && month !== currentMonth) {
                                // Fetch new MTD data for selected month
                                const newMTDData = await fetchMTDData(month);
                                categoryDataMTD = newMTDData;
                                currentMonth = month;
                            }
                            data = getChartData('MTD', categoryDataMTD);
                        } else {
                            maxValue = 100;
                            data = getChartData('YTD');
                        }
                        
                        // Update chart data and max value
                        awardsChart.data.datasets[0].data = data;
                        awardsChart.options.scales.y.max = maxValue;
                        awardsChart.options.scales.y.ticks.stepSize = filter === 'MTD' ? 5 : 10;
                        awardsChart.options.scales.y.ticks.callback = function(value) {
                            if (filter === 'MTD') {
                                if ([0, 5, 10, 15, 20].includes(value)) {
                                    return value;
                                }
                            } else {
                                if ([0, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100].includes(value)) {
                                    return value;
                                }
                            }
                            return '';
                        };
                        awardsChart.update('none');
                    };
                    
                    // Initialize month filter visibility and chart max value
                    updateMonthFilterVisibility();
                    // Set initial max value based on saved filter
                    awardsChart.options.scales.y.max = currentFilter === 'MTD' ? 20 : 100;
                    awardsChart.options.scales.y.ticks.stepSize = currentFilter === 'MTD' ? 5 : 10;
                    
                    // Add event listener to filter dropdown
                    if (timeFilter) {
                        // Store the current selected value
                        const currentValue = timeFilter.value;
                        
                        // Remove existing listener if any (by cloning and replacing the element)
                        const newTimeFilter = timeFilter.cloneNode(true);
                        newTimeFilter.value = currentValue;
                        timeFilter.parentNode.replaceChild(newTimeFilter, timeFilter);
                        
                        // Add event listener to the new element
                        newTimeFilter.addEventListener('change', async function() {
                            currentFilter = this.value;
                            await updateChart(currentFilter);
                            updateMonthFilterVisibility();
                            await savePreference('dashboard_awards_filter', currentFilter);
                        });
                    }
                    
                    // Add event listener to month filter dropdown
                    monthFilter = document.getElementById('awardsMonthFilter');
                    if (monthFilter) {
                        const currentMonthValue = monthFilter.value;
                        const newMonthFilter = monthFilter.cloneNode(true);
                        newMonthFilter.value = currentMonthValue;
                        monthFilter.parentNode.replaceChild(newMonthFilter, monthFilter);
                        
                        // Update the reference to the new element
                        monthFilter = newMonthFilter;
                        
                        // Ensure dropdown opens downward by checking viewport position
                        newMonthFilter.addEventListener('mousedown', function(e) {
                            const rect = this.getBoundingClientRect();
                            const spaceBelow = window.innerHeight - rect.bottom;
                            const spaceAbove = rect.top;
                            
                            // If there's more space above than below, scroll to ensure space below
                            if (spaceBelow < 300 && spaceAbove > spaceBelow) {
                                // Scroll the element into view with space below
                                setTimeout(() => {
                                    this.scrollIntoView({ behavior: 'smooth', block: 'start' });
                                }, 10);
                            }
                        });
                        
                        newMonthFilter.addEventListener('change', async function() {
                            const selectedMonth = this.value;
                            await updateChart('MTD', selectedMonth);
                            await savePreference('dashboard_awards_month', selectedMonth);
                        });
                    }
                }
                
                // Documents Chart (Doughnut Chart)
                const docsCtx = document.getElementById('docsChart');
                if (docsCtx) {
                    new Chart(docsCtx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Contracts', 'Reports', 'Proposals', 'Guides', 'Others'],
                            datasets: [{
                                label: 'Document Types',
                                data: [65, 80, 45, 30, 36],
                                backgroundColor: [
                                    '#14704E', // primary
                                    '#10B981', // emerald-500
                                    '#34D399', // emerald-400
                                    '#6EE7B7', // emerald-300
                                    '#A7F3D0'  // emerald-200
                                ],
                                borderColor: isDarkMode() ? '#181A20' : '#fff',
                                borderWidth: 4,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '70%',
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        color: isDarkMode() ? '#CBD5E1' : '#475569',
                                        usePointStyle: true,
                                        boxWidth: 8
                                    }
                                }
                            }
                        }
                    });
                }
                
                // Theme change observer
                const observer = new MutationObserver((mutations) => {
                    mutations.forEach((mutation) => {
                        if (mutation.attributeName === "class") {
                            Chart.helpers.each(Chart.instances, (instance) => {
                                if(instance.config.type === 'bar') {
                                    instance.options.scales.y.grid.color = isDarkMode() ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
                                    instance.options.scales.y.ticks.color = isDarkMode() ? '#CBD5E1' : '#475569';
                                    instance.options.scales.x.ticks.color = isDarkMode() ? '#CBD5E1' : '#475569';
                                }
                                if(instance.config.type === 'doughnut') {
                                    instance.data.datasets[0].borderColor = isDarkMode() ? '#181A20' : '#fff';
                                    instance.options.plugins.legend.labels.color = isDarkMode() ? '#CBD5E1' : '#475569';
                                }
                                instance.update();
                            });
                        }
                    });
                });
                observer.observe(document.documentElement, { attributes: true });
            };
            renderCharts();
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
    // Notification System
    (function() {
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const notificationBadge = document.getElementById('notificationBadge');
        const notificationList = document.getElementById('notificationList');
        const noNotifications = document.getElementById('noNotifications');
        const markAllReadBtn = document.getElementById('markAllReadBtn');
        const viewAllNotifications = document.getElementById('viewAllNotifications');
        
        let notifications = [];
        
        // Toggle dropdown
        if (notificationBtn && notificationDropdown) {
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
        }
        
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
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer ${notif.is_read ? 'opacity-60' : ''}" 
                         data-id="${notif.id}" 
                         onclick="markNotificationAsRead(${notif.id})">
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-10 h-10 rounded-full ${bgColor} flex items-center justify-center">
                                <span class="material-symbols-outlined text-white text-lg">${icon}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">${escapeHtml(notif.title)}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">${escapeHtml(notif.message)}</p>
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
<script>
    // XAMPP/Apache version - no port redirect needed
</script>
</body></html>



