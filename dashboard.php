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

if (!function_exists('formatTimeAgo')) {
    function formatTimeAgo($datetime): string
    {
        if (empty($datetime)) {
            return 'Just now';
        }

        $timestamp = is_numeric($datetime) ? (int)$datetime : strtotime($datetime);
        if (!$timestamp) {
            return 'Just now';
        }

        $diff = time() - $timestamp;
        if ($diff < 60) {
            return 'Just now';
        }

        $periods = [
            31536000 => 'year',
            2592000 => 'month',
            604800 => 'week',
            86400 => 'day',
            3600 => 'hour',
            60 => 'minute',
        ];

        foreach ($periods as $seconds => $label) {
            if ($diff >= $seconds) {
                $value = (int)floor($diff / $seconds);
                return $value . ' ' . $label . ($value > 1 ? 's' : '') . ' ago';
            }
        }

        return 'Just now';
    }
}

if (!function_exists('getActivityMeta')) {
    function getActivityMeta(?string $actionType): array
    {
        $map = [
            'create' => [
                'icon' => 'add_circle',
                'bg' => 'bg-blue-100 dark:bg-blue-900/50',
                'color' => 'text-blue-600 dark:text-blue-300',
            ],
            'update' => [
                'icon' => 'sync',
                'bg' => 'bg-purple-100 dark:bg-purple-900/50',
                'color' => 'text-purple-600 dark:text-purple-300',
            ],
            'delete' => [
                'icon' => 'delete',
                'bg' => 'bg-red-100 dark:bg-red-900/50',
                'color' => 'text-red-600 dark:text-red-300',
            ],
            'upload' => [
                'icon' => 'upload_file',
                'bg' => 'bg-blue-100 dark:bg-blue-900/50',
                'color' => 'text-blue-600 dark:text-blue-300',
            ],
            'comment' => [
                'icon' => 'chat',
                'bg' => 'bg-emerald-100 dark:bg-emerald-900/50',
                'color' => 'text-emerald-600 dark:text-emerald-300',
            ],
            'status_change' => [
                'icon' => 'task_alt',
                'bg' => 'bg-amber-100 dark:bg-amber-900/50',
                'color' => 'text-amber-600 dark:text-amber-300',
            ],
            'default' => [
                'icon' => 'info',
                'bg' => 'bg-slate-100 dark:bg-slate-800/50',
                'color' => 'text-slate-600 dark:text-slate-300',
            ],
        ];

        $key = strtolower($actionType ?? 'default');
        return $map[$key] ?? $map['default'];
    }
}

if (!function_exists('getDefaultRecentActivity')) {
    function getDefaultRecentActivity(array $user): array
    {
        $displayName = $user['full_name'] ?? $user['username'] ?? 'You';

        $defaults = [
            [
                'meta' => getActivityMeta('upload'),
                'title' => sprintf('%s uploaded a new MOU.', $displayName),
                'subtitle' => 'Recently',
            ],
            [
                'meta' => getActivityMeta('create'),
                'title' => 'Award submission was processed.',
                'subtitle' => 'Recently',
            ],
            [
                'meta' => getActivityMeta('status_change'),
                'title' => 'New event was scheduled.',
                'subtitle' => 'Recently',
            ],
        ];

        return array_map(static function ($activity) {
            // For default activities, show current date
            $currentDate = date('M d, Y H:i');
            $subtitle = 'Recently • ' . $currentDate;
            
            return [
                'icon' => $activity['meta']['icon'],
                'icon_bg' => $activity['meta']['bg'],
                'icon_color' => $activity['meta']['color'],
                'title' => $activity['title'],
                'subtitle' => $subtitle,
            ];
        }, $defaults);
    }
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
        
        // Ensure renewal_confirmed and renewal_confirmed_at columns exist in mou_moa table
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM mou_moa LIKE 'renewal_confirmed'");
            if ($stmt->rowCount() === 0) {
                $pdo->exec("ALTER TABLE mou_moa ADD COLUMN renewal_confirmed TINYINT(1) DEFAULT 0 AFTER status");
            }
        } catch (Exception $e) {
            // Column might already exist, continue
        }
        
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM mou_moa LIKE 'renewal_confirmed_at'");
            if ($stmt->rowCount() === 0) {
                $pdo->exec("ALTER TABLE mou_moa ADD COLUMN renewal_confirmed_at TIMESTAMP NULL DEFAULT NULL AFTER renewal_confirmed");
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
        $statsData['recent_activity'] = [];

    } else {
        // Ensure deleted_at column exists
        try {
            $pdo->exec("ALTER TABLE awards ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
        } catch (PDOException $e) {
            // Column might already exist, ignore
        }
        
        // Ensure source_page column exists in award_analysis table
        try {
            $pdo->exec("ALTER TABLE award_analysis ADD COLUMN source_page ENUM('documents', 'events', 'awards') NULL DEFAULT 'awards'");
        } catch (PDOException $e) {
            // Column might already exist, ignore
        }
        
        // Database statistics - Count only awards uploaded from awards progress page
        // Awards from awards progress page have source_page = 'awards' in award_analysis
        // OR source_page IS NULL (default is 'awards' for awards uploaded from awards page)
        // Exclude awards with source_page = 'documents' (those came from documents page)
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT a.id) as count
            FROM awards a
            LEFT JOIN award_analysis aa ON a.id = aa.award_id
            WHERE (a.deleted_at IS NULL OR a.deleted_at = '')
              AND a.title IS NOT NULL AND a.title != ''
              AND a.file_name IS NOT NULL AND a.file_name != ''
              AND (
                  aa.source_page = 'awards' 
                  OR aa.source_page IS NULL
                  OR aa.id IS NULL  -- Awards without analysis yet (assumed from awards page)
              )
              AND (aa.source_page IS NULL OR aa.source_page != 'documents')
        ");
        $statsData['total_awards'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

        // Status breakdown - Get ALL eligible awards (not filtered by user)
        // Count eligible awards based on match_percentage >= 90 (same as awards management page),
        // but only for non-trashed awards
        $stmt = $pdo->query("
            SELECT COUNT(*) as count
            FROM award_analysis aa
            INNER JOIN awards a ON a.id = aa.award_id
            WHERE aa.match_percentage >= 90
              AND a.deleted_at IS NULL
        ");
        $statsData['eligible'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Count almost eligible awards (70-89%), excluding trashed awards
        $stmt = $pdo->query("
            SELECT COUNT(*) as count
            FROM award_analysis aa
            INNER JOIN awards a ON a.id = aa.award_id
            WHERE aa.match_percentage >= 70 AND aa.match_percentage < 90
              AND a.deleted_at IS NULL
        ");
        $statsData['almost_eligible'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Count not eligible awards (<70%), excluding trashed awards
        $stmt = $pdo->query("
            SELECT COUNT(*) as count
            FROM award_analysis aa
            INNER JOIN awards a ON a.id = aa.award_id
            WHERE aa.match_percentage < 70
              AND a.deleted_at IS NULL
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
        $calculateCategoryDistribution = function($dateFilter = null, $monthYear = null, $yearFilter = null) use ($pdo, $displayAwards, $specificAwards) {
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
                $yearValue = (int)($yearFilter ?: date('Y'));
                if ($yearValue < 2000 || $yearValue > 2100) {
                    $yearValue = (int)date('Y');
                }
                $dateCondition = "AND YEAR(a.created_at) = " . $yearValue;
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
                  AND a.deleted_at IS NULL
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
        $selectedYear = date('Y');
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

            $prefStmt->execute([$user['id'], 'dashboard_awards_year']);
            $yearPref = $prefStmt->fetch(PDO::FETCH_ASSOC);
            if ($yearPref && isset($yearPref['preference_value']) && preg_match('/^\d{4}$/', $yearPref['preference_value'])) {
                $selectedYear = $yearPref['preference_value'];
            }
        } catch (Exception $e) {
            // Preferences table might not exist yet, use defaults
        }
        
        // Calculate both MTD and YTD distributions
        $statsData['category_distribution'] = $calculateCategoryDistribution('YTD', null, $selectedYear); // Default to selected YTD
        $statsData['category_distribution_mtd'] = $calculateCategoryDistribution('MTD', $selectedMonth);
        $statsData['category_distribution_ytd'] = $calculateCategoryDistribution('YTD', null, $selectedYear);
        $statsData['selected_filter'] = $selectedFilter;
        $statsData['selected_month'] = $selectedMonth;
        $statsData['selected_year'] = $selectedYear;
        
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

        // Get available years for YTD dropdown (distinct years from awards)
        $availableYears = [];
        try {
            $yearStmt = $pdo->query("SELECT DISTINCT YEAR(created_at) as year FROM awards WHERE created_at IS NOT NULL ORDER BY year DESC");
            $yearResults = $yearStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($yearResults as $yearVal) {
                if ($yearVal) {
                    $availableYears[] = (int)$yearVal;
                }
            }
        } catch (Exception $e) {
            // Ignore errors, fallback to default year list
        }
        if (empty($availableYears)) {
            $availableYears[] = (int)date('Y');
        }
        if (!in_array((int)$selectedYear, $availableYears, true)) {
            array_unshift($availableYears, (int)$selectedYear);
        }
        $statsData['available_years'] = array_values(array_unique($availableYears));

        // Recent uploads (excluding deleted awards)
        $stmt = $pdo->prepare('
            SELECT a.*, aa.predicted_category, aa.match_percentage, aa.status
            FROM awards a
            LEFT JOIN award_analysis aa ON aa.award_id = a.id
            WHERE a.user_id = ?
              AND (a.deleted_at IS NULL OR a.deleted_at = \'\')
            ORDER BY a.created_at DESC
            LIMIT 5
        ');
        $stmt->execute([$user['id']]);
        $statsData['recent_uploads'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get additional statistics for dashboard
        // Upcoming events - all future events (event_date >= today), excluding deleted events
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM events WHERE event_date >= CURDATE() AND deleted_at IS NULL");
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
        
        // Total signed MOUs - all uploaded MOUs/MOAs (excluding deleted)
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM mou_moa WHERE (deleted_at IS NULL OR deleted_at = '')");
        $statsData['signed_mous'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // MOUs/MOAs that need renewal (expiring soon - within next 90 days, or already expired, excluding deleted)
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM mou_moa WHERE ((end_date >= CURDATE() AND end_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)) OR end_date < CURDATE()) AND (deleted_at IS NULL OR deleted_at = '')");
        $statsData['pending_renewal_mous'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Ensure deleted_at column exists in both tables
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
        
        // Total documents - all files uploaded in Documents page
        // Count from both mou_moa and other_documents tables (same as Documents page)
        // Exclude deleted documents (those in trash)
        $stmt = $pdo->query("
            SELECT COUNT(*) as count FROM (
                SELECT id FROM mou_moa WHERE (deleted_at IS NULL OR deleted_at = '')
                UNION ALL
                SELECT id FROM other_documents WHERE (deleted_at IS NULL OR deleted_at = '')
            ) as combined_documents
        ");
        $statsData['total_documents'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Get upcoming events for table, excluding deleted events
        $stmt = $pdo->query("SELECT title, event_date, location, status FROM events WHERE event_date >= CURDATE() AND deleted_at IS NULL ORDER BY event_date ASC LIMIT 4");
        $statsData['upcoming_events_list'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get recent activity feed - only show real activities, no defaults
        $statsData['recent_activity'] = [];
        try {
            $activityStmt = $pdo->prepare("
                SELECT 
                    ah.action_type,
                    ah.description,
                    ah.created_at,
                    u.full_name,
                    u.username
                FROM activity_history ah
                LEFT JOIN users u ON u.id = ah.user_id
                ORDER BY ah.created_at DESC
                LIMIT 5
            ");
            $activityStmt->execute();
            $activities = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($activities)) {
                $formattedActivities = [];
                foreach ($activities as $activity) {
                    $meta = getActivityMeta($activity['action_type'] ?? null);
                    $actor = $activity['full_name'] ?? $activity['username'] ?? 'System';
                    $description = $activity['description'] ?: sprintf('%s activity', ucfirst($activity['action_type'] ?? 'Recent'));
                    
                    // Format date - show both time ago and actual date
                    $createdAt = $activity['created_at'] ?? null;
                    $timeAgo = formatTimeAgo($createdAt);
                    $actualDate = '';
                    if ($createdAt) {
                        try {
                            $dateObj = new DateTime($createdAt);
                            $actualDate = $dateObj->format('M d, Y H:i');
                        } catch (Exception $e) {
                            $actualDate = date('M d, Y H:i', strtotime($createdAt));
                        }
                    }
                    
                    $subtitle = $timeAgo;
                    if (!empty($actualDate)) {
                        $subtitle .= ' • ' . $actualDate;
                    }
                    if (!empty($actor)) {
                        $subtitle .= ' • ' . $actor;
                    }

                    $formattedActivities[] = [
                        'icon' => $meta['icon'],
                        'icon_bg' => $meta['bg'],
                        'icon_color' => $meta['color'],
                        'title' => $description,
                        'subtitle' => $subtitle,
                    ];
                }

                if (!empty($formattedActivities)) {
                    $statsData['recent_activity'] = $formattedActivities;
                }
            }
        } catch (Exception $activityError) {
            error_log('Failed to load recent activity: ' . $activityError->getMessage());
            $statsData['recent_activity'] = [];
        }
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
<script src="js/toast-notifications.js?v=<?php echo time(); ?>"></script>
<script src="js/notification-sound.js"></script>
<script src="js/notification-bar.js"></script>
<script src="js/loading-states.js"></script>
    <!-- Tailwind is now compiled via assets/css/tailwind.css; no runtime config needed -->
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
        .sidebar {
            width: 16rem;
            min-width: 16rem;
            max-width: 16rem;
            flex-shrink: 0;
            transition: width 0.3s ease, min-width 0.3s ease, max-width 0.3s ease;
        }
        .custom-select-wrapper {
            position: relative;
        }
        .custom-select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            padding-right: 2.5rem;
            background-image: none;
        }
        .custom-select::-ms-expand {
            display: none;
        }
        .custom-select-arrow {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: #475569;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .dark .custom-select-arrow {
            color: #cbd5f5;
        }
        .custom-select.hidden + .custom-select-arrow {
            display: none;
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
<a class="flex items-center gap-3 px-4 py-2.5 rounded-lg bg-gradient-to-r from-purple-50 to-indigo-50 dark:from-purple-900/40 dark:to-indigo-900/40 text-purple-600 dark:text-purple-400 font-semibold sidebar-nav-link border border-purple-200 dark:border-purple-800 shadow-sm" href="dashboard.php" title="Dashboard">
<span class="material-symbols-outlined filled flex-shrink-0">dashboard</span>
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
<div class="p-8">
<header class="flex justify-between items-center mb-8 header-animate relative" style="z-index: 50;">
<div class="flex items-center gap-6">
<div class="flex items-center gap-3">
<div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-500 to-blue-600 flex items-center justify-center">
<span class="material-symbols-outlined text-white">dashboard</span>
</div>
<div>
<h1 class="text-2xl font-bold text-text-light dark:text-text-dark">LILAC Dashboard</h1>
<p class="text-sm text-text-muted-light dark:text-text-muted-dark">Overview of awards, events, documents, and recent activity</p>
</div>
</div>
</div>
<div class="flex items-center gap-4">
						<div class="relative z-[9999]" style="pointer-events: auto;">
    <button id="notificationBtn" class="p-2 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors relative" style="pointer-events: auto; cursor: pointer; z-index: 10000;">
        <span class="material-symbols-outlined text-slate-600 dark:text-slate-400">notifications</span>
        <span id="notificationBadge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
    </button>
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
            <button type="button" id="viewAllNotifications" class="w-full text-center text-sm text-primary hover:text-primary/80 dark:text-primary-400 dark:hover:text-primary-300">View all notifications</button>
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
<p class="font-semibold text-slate-800 dark:text-slate-100 text-sm" id="user-greeting"><?php 
$fullName = $user['full_name'] ?? $user['username'];
$firstName = explode(' ', $fullName)[0];
echo htmlspecialchars($firstName);
?></p>
<p class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
</div>
</div>
</div>
</header>
<div class="space-y-8 content-animate relative" style="z-index: 1;">
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
<a href="user-awards.php" class="bg-gradient-to-br from-purple-600 via-purple-500 to-indigo-600 text-white p-6 rounded-xl flex flex-col justify-between cursor-pointer transform transition-all duration-200 hover:scale-105 hover:shadow-2xl hover:shadow-purple-500/25 active:scale-95 page-animate-delay-1 relative overflow-hidden" style="background: linear-gradient(to bottom right, #9333ea, #a855f7, #4f46e5);">
<div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16"></div>
<div class="relative z-10 flex justify-between items-start">
<h3 class="font-semibold text-white/95">Total Awards</h3>
<span class="material-symbols-outlined text-white/90">emoji_events</span>
</div>
<div class="relative z-10">
<p class="text-4xl font-bold"><?php echo htmlspecialchars($statsData['total_awards'] ?? 0); ?></p>
<p class="text-sm text-white/80 mt-1">+<?php echo htmlspecialchars($statsData['eligible'] ?? 0); ?> eligible</p>
</div>
</a>
<a href="events-activities.php" class="bg-gradient-to-br from-pink-500 via-rose-500 to-pink-600 text-white p-6 rounded-xl cursor-pointer transform transition-all duration-200 hover:scale-105 hover:shadow-2xl hover:shadow-pink-500/25 active:scale-95 page-animate-delay-1 relative overflow-hidden" style="background: linear-gradient(to bottom right, #ec4899, #f43f5e, #db2777);">
<div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16"></div>
<div class="relative z-10 flex justify-between items-start">
<h3 class="font-semibold text-white/95">Upcoming Events</h3>
<span class="material-symbols-outlined text-white/90">event</span>
</div>
<div class="relative z-10">
<p class="text-4xl font-bold mt-4"><?php echo htmlspecialchars($statsData['upcoming_events'] ?? 0); ?></p>
<p class="text-sm text-white/80 mt-1">Future events</p>
</div>
</a>
<a href="scheduler.php" class="bg-gradient-to-br from-blue-500 via-cyan-500 to-blue-600 text-white p-6 rounded-xl cursor-pointer transform transition-all duration-200 hover:scale-105 hover:shadow-2xl hover:shadow-blue-500/25 active:scale-95 page-animate-delay-2 relative overflow-hidden" style="background: linear-gradient(to bottom right, #3b82f6, #06b6d4, #2563eb);">
<div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16"></div>
<div class="relative z-10 flex justify-between items-start">
<h3 class="font-semibold text-white/95">Active Schedules</h3>
<span class="material-symbols-outlined text-white/90">calendar_month</span>
</div>
<div class="relative z-10">
<p class="text-4xl font-bold mt-4"><?php echo htmlspecialchars($statsData['active_schedules'] ?? 0); ?></p>
<p class="text-sm text-white/80 mt-1"><?php echo htmlspecialchars($statsData['upcoming_schedules'] ?? 0); ?> upcoming</p>
</div>
</a>
<a href="mou-moa.php" class="bg-gradient-to-br from-emerald-400 via-emerald-300 to-emerald-500 text-white p-6 rounded-xl cursor-pointer transform transition-all duration-200 hover:scale-105 hover:shadow-2xl hover:shadow-emerald-400/25 active:scale-95 page-animate-delay-2 relative overflow-hidden" style="background: linear-gradient(to bottom right, #34d399, #6ee7b7, #10b981);">
<div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16"></div>
<div class="relative z-10 flex justify-between items-start">
<h3 class="font-semibold text-white/95">Signed MOUs</h3>
<span class="material-symbols-outlined text-white/90">handshake</span>
</div>
<div class="relative z-10">
<p class="text-4xl font-bold mt-4"><?php echo htmlspecialchars($statsData['signed_mous'] ?? 0); ?></p>
<p class="text-sm text-white/80 mt-1"><?php echo htmlspecialchars($statsData['pending_renewal_mous'] ?? 0); ?> need renewal</p>
</div>
</a>
<a href="documents.php" class="bg-gradient-to-br from-amber-300 via-amber-400 to-amber-500 text-white p-6 rounded-xl cursor-pointer transform transition-all duration-200 hover:scale-105 hover:shadow-2xl hover:shadow-amber-300/25 active:scale-95 page-animate-delay-2 relative overflow-hidden" style="background: linear-gradient(to bottom right, #fcd34d, #fbbf24, #f59e0b);">
<div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16"></div>
<div class="relative z-10 flex justify-between items-start">
<h3 class="font-semibold text-white/95">Documents</h3>
<span class="material-symbols-outlined text-white/90">folder</span>
</div>
<div class="relative z-10">
<p class="text-4xl font-bold mt-4"><?php echo htmlspecialchars($statsData['total_documents'] ?? 0); ?></p>
<p class="text-sm text-white/80 mt-1">Total files</p>
</div>
</a>
</div>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 page-animate-delay-1">
<div class="lg:col-span-2 bg-white dark:bg-card-dark rounded-xl shadow-lg border-l-4 border-purple-400 overflow-hidden">
<div class="bg-gradient-to-r from-violet-50 via-violet-100 to-purple-50 dark:from-violet-900/30 dark:via-purple-900/30 dark:to-violet-800/30 px-6 py-4 border-b border-violet-100 dark:border-violet-800">
<div class="flex justify-between items-center">
<h3 class="text-lg font-semibold text-purple-700 dark:text-purple-300 flex items-center gap-2">
<span class="material-symbols-outlined text-purple-600 dark:text-purple-400">trending_up</span>
Awards Performance
</h3>
<div class="flex gap-2 items-center">
                    <div class="relative inline-block custom-select-wrapper" style="position: relative; margin: 0;">
<select id="awardsMonthFilter" class="custom-select px-3 py-1.5 pr-8 text-sm border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent hidden">
<?php foreach ($statsData['available_months'] ?? [] as $month): ?>
<option value="<?php echo htmlspecialchars($month['value']); ?>" <?php echo ($month['value'] === ($statsData['selected_month'] ?? date('Y-m'))) ? 'selected' : ''; ?>><?php echo htmlspecialchars($month['label']); ?></option>
<?php endforeach; ?>
</select>
<span class="custom-select-arrow" aria-hidden="true">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
    </svg>
</span>
</div>
                    <div class="relative inline-block custom-select-wrapper" style="position: relative; margin: 0;">
                        <select id="awardsYearFilter" class="custom-select px-3 py-1.5 pr-8 text-sm border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent hidden">
<?php foreach ($statsData['available_years'] ?? [] as $yearOption): ?>
<option value="<?php echo htmlspecialchars($yearOption); ?>" <?php echo ((string)$yearOption === (string)($statsData['selected_year'] ?? date('Y'))) ? 'selected' : ''; ?>><?php echo htmlspecialchars($yearOption); ?></option>
<?php endforeach; ?>
</select>
                        <span class="custom-select-arrow" aria-hidden="true">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </span>
                    </div>
<div class="relative inline-block custom-select-wrapper">
<select id="awardsTimeFilter" class="custom-select px-3 py-1.5 pr-8 text-sm border border-slate-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
<option value="YTD" <?php echo (($statsData['selected_filter'] ?? 'YTD') === 'YTD') ? 'selected' : ''; ?>>YTD</option>
<option value="MTD" <?php echo (($statsData['selected_filter'] ?? 'YTD') === 'MTD') ? 'selected' : ''; ?>>MTD</option>
</select>
<span class="custom-select-arrow" aria-hidden="true">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
    </svg>
</span>
</div>
</div>
</div>
</div>
<div class="px-6 py-4">
<div class="h-80">
<canvas id="awardsChart"></canvas>
</div>
</div>
</div>
<div class="bg-white dark:bg-card-dark rounded-xl shadow-lg border-l-4 border-indigo-400 overflow-hidden page-animate-delay-1">
<div class="bg-gradient-to-r from-violet-50 via-violet-100 to-purple-50 dark:from-violet-900/30 dark:via-purple-900/30 dark:to-violet-800/30 px-6 py-4 border-b border-violet-100 dark:border-violet-800">
<h3 class="text-lg font-semibold text-indigo-700 dark:text-indigo-300 flex items-center gap-2 mb-0">
<span class="material-symbols-outlined text-indigo-600 dark:text-indigo-400">notifications</span>
MOU/MOA Notifications
</h3>
</div>
<div class="overflow-hidden">
<div id="renewalsContainer" class="flex items-center justify-center min-h-full p-6">
<!-- Renewals will be loaded here via JavaScript -->
<div class="flex items-center justify-center text-slate-400 dark:text-slate-300">
<span class="material-symbols-outlined animate-spin mr-2">sync</span>
<span>Loading renewals...</span>
</div>
</div>
</div>
</div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-5 gap-6 page-animate-delay-2">
<div class="lg:col-span-3 bg-white dark:bg-card-dark rounded-xl shadow-lg border-l-4 border-pink-400 overflow-hidden">
<div class="bg-gradient-to-r from-violet-50 via-violet-100 to-purple-50 dark:from-violet-900/30 dark:via-purple-900/30 dark:to-violet-800/30 px-6 py-4 border-b border-violet-100 dark:border-violet-800">
<div class="flex justify-between items-center">
<h3 class="text-lg font-semibold text-pink-700 dark:text-pink-300 flex items-center gap-2">
<span class="material-symbols-outlined text-pink-600 dark:text-pink-400">event</span>
Upcoming Events
</h3>
<button class="text-sm font-medium text-purple-600 dark:text-purple-400 hover:text-purple-700 dark:hover:text-purple-300 hover:underline transition-colors" onclick="window.location.href='events-activities.php'">View All</button>
</div>
</div>
<div class="p-6">
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
</div>
<div class="lg:col-span-2 bg-white dark:bg-card-dark rounded-xl shadow-lg border-l-4 border-violet-400 overflow-hidden page-animate-delay-2">
<div class="bg-gradient-to-r from-violet-50 via-violet-100 to-purple-50 dark:from-violet-900/30 dark:via-purple-900/30 dark:to-violet-800/30 px-6 py-4 border-b border-violet-100 dark:border-violet-800">
<div class="flex justify-between items-center">
<h3 class="text-lg font-semibold text-violet-700 dark:text-violet-300 flex items-center gap-2">
<span class="material-symbols-outlined text-violet-600 dark:text-violet-400">history</span>
Recent Activity
</h3>
<button type="button" class="text-sm font-medium text-purple-600 dark:text-purple-400 hover:text-purple-700 dark:hover:text-purple-300 hover:underline transition-colors" data-view-log>View Log</button>
</div>
</div>
<div class="p-6">
<?php $recentActivities = $statsData['recent_activity'] ?? []; ?>
<ul class="space-y-4">
<?php if (!empty($recentActivities)): ?>
<?php foreach ($recentActivities as $activity): ?>
<li class="flex items-start gap-4">
<div class="<?php echo htmlspecialchars($activity['icon_bg'] ?? 'bg-slate-100 dark:bg-slate-800/50'); ?> p-2 rounded-full">
<span class="material-symbols-outlined <?php echo htmlspecialchars($activity['icon_color'] ?? 'text-slate-600 dark:text-slate-300'); ?> text-base">
<?php echo htmlspecialchars($activity['icon'] ?? 'info'); ?>
</span>
</div>
<div>
<p class="text-sm text-slate-800 dark:text-slate-200"><?php echo htmlspecialchars($activity['title'] ?? 'Recent activity update'); ?></p>
<p class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($activity['subtitle'] ?? 'Recently'); ?></p>
</div>
</li>
<?php endforeach; ?>
<?php else: ?>
<li class="flex flex-col items-center justify-center py-12 text-center">
<div class="w-16 h-16 bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center mb-4">
<span class="material-symbols-outlined text-3xl text-slate-400 dark:text-slate-500">history</span>
</div>
<p class="text-base font-medium text-slate-700 dark:text-slate-300 mb-1">No Recent Activity</p>
<p class="text-sm text-slate-500 dark:text-slate-400">Activity history will appear here as actions are performed</p>
</li>
<?php endif; ?>
</ul>
</div>
</div>
</div>
</div>
</div>

<!-- Clear Activity Confirmation Modal -->
<div id="clear-activity-confirm-modal" class="fixed inset-0 z-[60] hidden overflow-y-auto">
    <div class="flex min-h-screen items-center justify-center p-4">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeClearConfirmModal()"></div>

        <!-- Modal Content -->
        <div class="relative w-full max-w-md bg-white dark:bg-background-dark rounded-xl shadow-2xl">
            <div class="p-6">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 bg-red-100 dark:bg-red-900/20 rounded-full flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-red-600 dark:text-red-400 text-xl">warning</span>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-slate-900 dark:text-slate-50">Clear All Activity</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">This action cannot be undone</p>
                    </div>
                </div>
                <p class="text-slate-700 dark:text-slate-300 mb-6">
                    Are you sure you want to clear all activity history? This will permanently delete all activity records.
                </p>
                <div class="flex gap-3 justify-end">
                    <button type="button" id="cancel-clear-activity-btn" class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 dark:bg-slate-800 dark:text-slate-300 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
                        Cancel
                    </button>
                    <button type="button" id="confirm-clear-activity-btn" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">delete_sweep</span>
                        Clear All
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Activity Log Modal (matches full-screen modal style) -->
<div id="activity-log-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex min-h-screen items-start justify-center p-4 pt-12">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" ></div>

        <!-- Modal Content -->
        <div class="relative w-full max-w-4xl bg-white dark:bg-background-dark rounded-xl shadow-2xl">
            <div class="flex items-center justify-between px-8 pt-6 pb-4 border-b border-border-light dark:border-border-dark">
                <div>
                    <h3 class="text-xl font-bold text-slate-900 dark:text-slate-50">Recent Activity</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Latest actions in your LILAC workspace</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" id="modal-refresh-activity-btn" class="p-2 rounded-lg text-purple-600 dark:text-purple-400 hover:bg-purple-100 dark:hover:bg-purple-900/30 transition-colors" title="Refresh Activity">
                        <span class="material-symbols-outlined text-base">refresh</span>
                    </button>
                    <button type="button" id="modal-clear-activity-btn" class="p-2 rounded-lg text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors" title="Clear All Activity">
                        <span class="material-symbols-outlined text-base">delete_sweep</span>
                    </button>
                    <button type="button" id="activity-log-close" class="flex size-10 items-center justify-center rounded-lg bg-slate-200 dark:bg-slate-800 text-slate-900 dark:text-slate-50 hover:bg-slate-300 dark:hover:bg-slate-700 transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
            </div>
            <div class="px-8 py-6 overflow-y-auto max-h-[80vh] bg-slate-50 dark:bg-slate-900/40 rounded-b-xl" id="activity-modal-content">
                <?php $modalActivities = $statsData['recent_activity'] ?? []; ?>
                <?php if (!empty($modalActivities)): ?>
                    <ul class="space-y-4" id="activity-list">
                        <?php foreach ($modalActivities as $activity): ?>
                            <li class="flex items-start gap-3">
                                <div class="<?php echo htmlspecialchars($activity['icon_bg'] ?? 'bg-slate-100 dark:bg-slate-800/50'); ?> p-2 rounded-full shrink-0">
                                    <span class="material-symbols-outlined <?php echo htmlspecialchars($activity['icon_color'] ?? 'text-slate-600 dark:text-slate-300'); ?> text-base">
                                        <?php echo htmlspecialchars($activity['icon'] ?? 'info'); ?>
                                    </span>
                                </div>
                                <div>
                                    <p class="text-sm text-slate-800 dark:text-slate-200"><?php echo htmlspecialchars($activity['title'] ?? 'Recent activity update'); ?></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($activity['subtitle'] ?? 'Recently'); ?></p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-sm text-slate-600 dark:text-slate-300" id="activity-empty-state">No recent activity.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</main>
</div>
<script type="application/json" id="dashboard-chart-data"><?php echo json_encode([
    'categoryDataMTD' => $statsData['category_distribution_mtd'] ?? [],
    'categoryDataYTD' => $statsData['category_distribution_ytd'] ?? [],
    'selectedFilter' => $statsData['selected_filter'] ?? 'YTD',
    'selectedMonth' => $statsData['selected_month'] ?? date('Y-m'),
    'selectedYear' => $statsData['selected_year'] ?? date('Y'),
], JSON_HEX_TAG); ?></script>
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
            const viewLogButton = document.querySelector('[data-view-log]');
            const activityLogModal = document.getElementById('activity-log-modal');
            const activityLogClose = document.getElementById('activity-log-close');
            
            // Initialize sidebar state
            const initSidebarState = () => {
                const savedState = localStorage.getItem('sidebarCollapsed');
                if (savedState === 'false') {
                    appContainer.classList.remove('sidebar-collapsed');
                    mainContent.classList.remove('ml-20');
                    mainContent.classList.add('ml-64');
                    
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
                    // Default or true - ensure collapsed state matches
                    appContainer.classList.add('sidebar-collapsed');
                    mainContent.classList.remove('ml-64');
                    mainContent.classList.add('ml-20');
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
                    mainContent.classList.remove('ml-20');
                    mainContent.classList.add('ml-64');
                    
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
                    mainContent.classList.remove('ml-64');
                    mainContent.classList.add('ml-20');
                    
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
                
                // Add a small delay for the chart to re-render after transition
                setTimeout(() => {
                    Chart.helpers.each(Chart.instances, (instance) => {
                        instance.resize();
                    });
                }, 350);
            };
            sidebarToggle.addEventListener('click', toggleSidebar);

            // Activity log modal handlers
            if (viewLogButton && activityLogModal) {
                const openLogModal = () => {
                    activityLogModal.classList.remove('hidden');
                };
                const closeLogModal = () => {
                    activityLogModal.classList.add('hidden');
                };

                viewLogButton.addEventListener('click', openLogModal);
                if (activityLogClose) {
                    activityLogClose.addEventListener('click', closeLogModal);
                }

                // Close when clicking on backdrop
                activityLogModal.addEventListener('click', (e) => {
                    if (e.target === activityLogModal) {
                        closeLogModal();
                    }
                });

                // Close on Escape key
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && !activityLogModal.classList.contains('hidden')) {
                        closeLogModal();
                    }
                });
            }

            // Function to refresh dashboard widget
            async function refreshDashboardWidget() {
                try {
                    // Fetch fresh activity from API
                    const response = await fetch('api/activity-history.php?limit=5');
                    const result = await response.json();

                    // Find the Recent Activity widget - look for the h3 with "Recent Activity" text
                    const activityHeader = Array.from(document.querySelectorAll('h3')).find(h3 => 
                        h3.textContent.includes('Recent Activity') && !h3.closest('#activity-log-modal')
                    );
                    
                    if (!activityHeader) {
                        console.log('Recent Activity widget header not found');
                        return;
                    }

                    // Find the activity list in the widget (ul inside the widget container)
                    const widgetContainer = activityHeader.closest('.bg-white, .dark\\:bg-card-dark') || 
                                          activityHeader.closest('[class*="col-span"]');
                    
                    if (!widgetContainer) {
                        console.log('Widget container not found');
                        return;
                    }

                    const activityList = widgetContainer.querySelector('ul.space-y-4');
                    if (!activityList) {
                        console.log('Activity list not found in widget');
                        return;
                    }

                    let activities = [];
                    const wasJustCleared = sessionStorage.getItem('activityJustCleared') === 'true';
                    
                    if (result.success && result.data && result.data.length > 0) {
                        // Format activities to match PHP format
                        activities = result.data.slice(0, 5).map(activity => {
                            const meta = getActivityMetaJS(activity.action_type || null);
                            const actor = activity.full_name || activity.username || 'System';
                            const description = activity.description || `${(activity.action_type || 'Recent').charAt(0).toUpperCase() + (activity.action_type || 'Recent').slice(1)} activity`;
                            
                            // Format date
                            const timeAgo = formatTimeAgoJS(activity.created_at);
                            let actualDate = '';
                            if (activity.created_at) {
                                try {
                                    const dateObj = new Date(activity.created_at);
                                    actualDate = formatDateJS(dateObj);
                                } catch (e) {
                                    actualDate = '';
                                }
                            }
                            
                            let subtitle = timeAgo;
                            if (actualDate) {
                                subtitle += ' • ' + actualDate;
                            }
                            if (actor && actor !== 'System') {
                                subtitle += ' • ' + actor;
                            }
                            
                            return {
                                icon: meta.icon,
                                icon_bg: meta.bg,
                                icon_color: meta.color,
                                title: description,
                                subtitle: subtitle
                            };
                        });
                    }
                    // If no activities, leave empty array to show empty state

                    // Update widget content
                    if (activities.length > 0) {
                        const formattedActivities = activities.map(activity => {
                            return `
                                <li class="flex items-start gap-4">
                                    <div class="${activity.icon_bg} p-2 rounded-full">
                                        <span class="material-symbols-outlined ${activity.icon_color} text-base">${activity.icon}</span>
                                    </div>
                                    <div>
                                        <p class="text-sm text-slate-800 dark:text-slate-200">${escapeHtml(activity.title)}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">${escapeHtml(activity.subtitle)}</p>
                                    </div>
                                </li>
                            `;
                        }).join('');

                        activityList.innerHTML = formattedActivities;
                    } else {
                        // Show empty state with modern design
                        activityList.innerHTML = `
                            <li class="flex flex-col items-center justify-center py-12 text-center">
                                <div class="w-16 h-16 bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center mb-4">
                                    <span class="material-symbols-outlined text-3xl text-slate-400 dark:text-slate-500">history</span>
                                </div>
                                <p class="text-base font-medium text-slate-700 dark:text-slate-300 mb-1">No Recent Activity</p>
                                <p class="text-sm text-slate-500 dark:text-slate-400">Activity history has been cleared</p>
                            </li>
                        `;
                    }
                } catch (error) {
                    console.error('Error refreshing dashboard widget:', error);
                }
            }

            // Function to load and refresh activity in modal
            async function refreshModalActivity() {
                const activityContent = document.getElementById('activity-modal-content');
                
                try {
                    // Show loading state
                    if (activityContent) {
                        activityContent.innerHTML = '<div class="text-center py-8"><span class="text-sm text-slate-500 dark:text-slate-400">Loading...</span></div>';
                    }

                    // Fetch fresh activity from API
                    const response = await fetch('api/activity-history.php?limit=50');
                    const result = await response.json();

                    let activities = [];
                    let useDefaults = false;
                    
                    if (result.success && result.data && result.data.length > 0) {
                        // Format activities to match PHP format
                        activities = result.data.map(activity => {
                            const meta = getActivityMetaJS(activity.action_type || null);
                            const actor = activity.full_name || activity.username || 'System';
                            const description = activity.description || `${(activity.action_type || 'Recent').charAt(0).toUpperCase() + (activity.action_type || 'Recent').slice(1)} activity`;
                            
                            // Format date - show both time ago and actual date
                            const timeAgo = formatTimeAgoJS(activity.created_at);
                            let actualDate = '';
                            if (activity.created_at) {
                                try {
                                    const dateObj = new Date(activity.created_at);
                                    actualDate = formatDateJS(dateObj);
                                } catch (e) {
                                    actualDate = '';
                                }
                            }
                            
                            let subtitle = timeAgo;
                            if (actualDate) {
                                subtitle += ' • ' + actualDate;
                            }
                            if (actor && actor !== 'System') {
                                subtitle += ' • ' + actor;
                            }
                            
                            return {
                                icon: meta.icon,
                                icon_bg: meta.bg,
                                icon_color: meta.color,
                                title: description,
                                subtitle: subtitle
                            };
                        });
                    } else {
                        // No real activity from database - always show empty state (never show defaults on refresh)
                        activities = [];
                    }

                    // Render activities
                    if (activities.length > 0) {
                        const formattedActivities = activities.map(activity => {
                            return `
                                <li class="flex items-start gap-3">
                                    <div class="${activity.icon_bg} p-2 rounded-full shrink-0">
                                        <span class="material-symbols-outlined ${activity.icon_color} text-base">${activity.icon}</span>
                                    </div>
                                    <div>
                                        <p class="text-sm text-slate-800 dark:text-slate-200">${escapeHtml(activity.title)}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">${escapeHtml(activity.subtitle)}</p>
                                    </div>
                                </li>
                            `;
                        }).join('');

                        if (activityContent) {
                            activityContent.innerHTML = `<ul class="space-y-4" id="activity-list">${formattedActivities}</ul>`;
                        }
                    } else {
                        // Show empty state with modern design
                        if (activityContent) {
                            activityContent.innerHTML = `
                                <div class="flex flex-col items-center justify-center py-16 text-center">
                                    <div class="w-20 h-20 bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center mb-5">
                                        <span class="material-symbols-outlined text-4xl text-slate-400 dark:text-slate-500">history</span>
                                    </div>
                                    <p class="text-lg font-semibold text-slate-700 dark:text-slate-300 mb-2">No Recent Activity</p>
                                    <p class="text-sm text-slate-500 dark:text-slate-400 max-w-sm">Activity history has been cleared. New activities will appear here as they occur.</p>
                                </div>
                            `;
                        }
                    }
                } catch (error) {
                    console.error('Error refreshing activity:', error);
                    // On error, show error message (never show defaults)
                    if (activityContent) {
                        activityContent.innerHTML = '<div class="text-center py-8"><span class="text-sm text-red-500">Error loading activity</span></div>';
                    }
                }
            }

            // Function to get default activities (matches PHP getDefaultRecentActivity)
            function getDefaultActivitiesJS() {
                // Try to get user info from page (if available)
                const userDisplayName = 'You'; // Could be enhanced to get from a data attribute or API
                
                // Format current date
                const now = new Date();
                const currentDate = formatDateJS(now);
                
                return [
                    {
                        icon: 'upload_file',
                        icon_bg: 'bg-blue-100 dark:bg-blue-900/50',
                        icon_color: 'text-blue-600 dark:text-blue-300',
                        title: userDisplayName + ' uploaded a new MOU.',
                        subtitle: 'Recently • ' + currentDate
                    },
                    {
                        icon: 'add_circle',
                        icon_bg: 'bg-blue-100 dark:bg-blue-900/50',
                        icon_color: 'text-blue-600 dark:text-blue-300',
                        title: 'Award submission was processed.',
                        subtitle: 'Recently • ' + currentDate
                    },
                    {
                        icon: 'task_alt',
                        icon_bg: 'bg-amber-100 dark:bg-amber-900/50',
                        icon_color: 'text-amber-600 dark:text-amber-300',
                        title: 'New event was scheduled.',
                        subtitle: 'Recently • ' + currentDate
                    }
                ];
            }

            // Helper function to format date (matches PHP format: M d, Y H:i)
            function formatDateJS(date) {
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                const month = months[date.getMonth()];
                const day = date.getDate();
                const year = date.getFullYear();
                const hours = String(date.getHours()).padStart(2, '0');
                const minutes = String(date.getMinutes()).padStart(2, '0');
                return `${month} ${day}, ${year} ${hours}:${minutes}`;
            }

            // Helper function to escape HTML
            function escapeHtml(text) {
                if (!text) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Helper function to format time ago (matches PHP formatTimeAgo)
            function formatTimeAgoJS(datetime) {
                if (!datetime) {
                    return 'Just now';
                }

                const timestamp = typeof datetime === 'string' ? new Date(datetime).getTime() / 1000 : datetime;
                if (!timestamp || isNaN(timestamp)) {
                    return 'Just now';
                }

                const now = Math.floor(Date.now() / 1000);
                const diff = now - timestamp;
                
                if (diff < 60) {
                    return 'Just now';
                }

                const periods = {
                    31536000: 'year',
                    2592000: 'month',
                    604800: 'week',
                    86400: 'day',
                    3600: 'hour',
                    60: 'minute',
                };

                for (const [seconds, label] of Object.entries(periods)) {
                    const secs = parseInt(seconds);
                    if (diff >= secs) {
                        const value = Math.floor(diff / secs);
                        return value + ' ' + label + (value > 1 ? 's' : '') + ' ago';
                    }
                }

                return 'Just now';
            }

            // Helper function to get activity meta (matches PHP getActivityMeta)
            function getActivityMetaJS(actionType) {
                const map = {
                    'create': {
                        'icon': 'add_circle',
                        'bg': 'bg-blue-100 dark:bg-blue-900/50',
                        'color': 'text-blue-600 dark:text-blue-300',
                    },
                    'update': {
                        'icon': 'sync',
                        'bg': 'bg-purple-100 dark:bg-purple-900/50',
                        'color': 'text-purple-600 dark:text-purple-300',
                    },
                    'delete': {
                        'icon': 'delete',
                        'bg': 'bg-red-100 dark:bg-red-900/50',
                        'color': 'text-red-600 dark:text-red-300',
                    },
                    'upload': {
                        'icon': 'upload_file',
                        'bg': 'bg-blue-100 dark:bg-blue-900/50',
                        'color': 'text-blue-600 dark:text-blue-300',
                    },
                    'comment': {
                        'icon': 'chat',
                        'bg': 'bg-emerald-100 dark:bg-emerald-900/50',
                        'color': 'text-emerald-600 dark:text-emerald-300',
                    },
                    'status_change': {
                        'icon': 'task_alt',
                        'bg': 'bg-amber-100 dark:bg-amber-900/50',
                        'color': 'text-amber-600 dark:text-amber-300',
                    },
                    'default': {
                        'icon': 'info',
                        'bg': 'bg-slate-100 dark:bg-slate-800/50',
                        'color': 'text-slate-600 dark:text-slate-300',
                    },
                };

                const key = (actionType || '').toLowerCase();
                return map[key] || map['default'];
            }


            // Modal refresh activity button
            const modalRefreshActivityBtn = document.getElementById('modal-refresh-activity-btn');
            if (modalRefreshActivityBtn) {
                modalRefreshActivityBtn.addEventListener('click', async () => {
                    // Add rotation animation
                    modalRefreshActivityBtn.classList.add('animate-spin');
                    await refreshModalActivity();
                    // Remove animation after a short delay
                    setTimeout(() => {
                        modalRefreshActivityBtn.classList.remove('animate-spin');
                    }, 500);
                });
            }

            // Clear Activity Confirmation Modal functions
            const clearConfirmModal = document.getElementById('clear-activity-confirm-modal');
            const cancelClearBtn = document.getElementById('cancel-clear-activity-btn');
            const confirmClearBtn = document.getElementById('confirm-clear-activity-btn');
            const modalClearActivityBtn = document.getElementById('modal-clear-activity-btn');

            function openClearConfirmModal() {
                if (clearConfirmModal) {
                    clearConfirmModal.classList.remove('hidden');
                }
            }

            function closeClearConfirmModal() {
                if (clearConfirmModal) {
                    clearConfirmModal.classList.add('hidden');
                }
            }

            // Make function available globally for backdrop click
            window.closeClearConfirmModal = closeClearConfirmModal;

            // Cancel button
            if (cancelClearBtn) {
                cancelClearBtn.addEventListener('click', closeClearConfirmModal);
            }

            // Confirm button - handle the actual clearing
            if (confirmClearBtn) {
                confirmClearBtn.addEventListener('click', async () => {
                    // Close the confirmation modal first
                    closeClearConfirmModal();

                    try {
                        // Show loading state on both buttons
                        if (modalClearActivityBtn) {
                            modalClearActivityBtn.disabled = true;
                            modalClearActivityBtn.innerHTML = '<span class="material-symbols-outlined text-base animate-spin">hourglass_empty</span>';
                        }

                        if (confirmClearBtn) {
                            confirmClearBtn.disabled = true;
                            const originalHTML = confirmClearBtn.innerHTML;
                            confirmClearBtn.innerHTML = '<span class="material-symbols-outlined text-base animate-spin">hourglass_empty</span> Clearing...';
                        }

                        console.log('Sending DELETE request to clear activity...');
                        const response = await fetch('api/activity-history.php?action=clear', {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json'
                            }
                        });

                        console.log('Response status:', response.status);
                        const responseText = await response.text();
                        console.log('Response text:', responseText);

                        let result;
                        try {
                            result = JSON.parse(responseText);
                        } catch (parseError) {
                            console.error('Failed to parse JSON:', parseError);
                            throw new Error('Invalid response from server: ' + responseText.substring(0, 100));
                        }

                        console.log('Parsed result:', result);

                        if (result.success) {
                            console.log('Activity cleared successfully');
                            // Refresh modal content to show cleared state (will show empty state)
                            await refreshModalActivity();
                            // Also refresh the dashboard widget
                            await refreshDashboardWidget();
                        } else {
                            console.error('Clear failed:', result.error);
                            alert('Failed to clear activity: ' + (result.error || 'Unknown error'));
                        }
                    } catch (error) {
                        console.error('Error clearing activity:', error);
                        alert('Error clearing activity: ' + error.message);
                    } finally {
                        // Reset button states
                        if (modalClearActivityBtn) {
                            modalClearActivityBtn.disabled = false;
                            modalClearActivityBtn.innerHTML = '<span class="material-symbols-outlined text-base">delete_sweep</span>';
                        }
                        if (confirmClearBtn) {
                            confirmClearBtn.disabled = false;
                            confirmClearBtn.innerHTML = '<span class="material-symbols-outlined text-base">delete_sweep</span> Clear All';
                        }
                    }
                });
            }

            // Modal clear all activity button - opens confirmation modal
            if (modalClearActivityBtn) {
                modalClearActivityBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Clear button clicked, opening confirmation modal');
                    openClearConfirmModal();
                });
            }

            // Close modal on Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && clearConfirmModal && !clearConfirmModal.classList.contains('hidden')) {
                    closeClearConfirmModal();
                }
            });
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
            // Chart.js rendering for Awards Performance
            const renderCharts = () => {
                const isDarkMode = () => document.documentElement.classList.contains('dark');
                
                // Awards Chart (Bar Chart)
                const awardsCtx = document.getElementById('awardsChart');
                if (awardsCtx) {
                    // Destroy existing chart instance if it exists
                    if (window.awardsChartInstance) {
                        window.awardsChartInstance.destroy();
                    }
                    
                    // Get initial data from JSON block (no PHP in script = no parse errors)
                    var chartDataEl = document.getElementById('dashboard-chart-data');
                    var dashboardChartConfig = chartDataEl ? JSON.parse(chartDataEl.textContent) : {};
                    let categoryDataMTD = dashboardChartConfig.categoryDataMTD || {};
                    let categoryDataYTD = dashboardChartConfig.categoryDataYTD || {};
                    const selectedFilter = dashboardChartConfig.selectedFilter || 'YTD';
                    const selectedMonth = dashboardChartConfig.selectedMonth || '';
                    const selectedYear = dashboardChartConfig.selectedYear || '';
                    
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

                    // Function to fetch YTD data for a specific year
                    const fetchYTDData = async (yearValue) => {
                        try {
                            const response = await fetch(`api/dashboard-ytd-data.php?year=${encodeURIComponent(yearValue)}`);
                            const result = await response.json();
                            if (result.success && result.data) {
                                return result.data;
                            }
                            return {};
                        } catch (error) {
                            console.error('Error fetching YTD data:', error);
                            return {};
                        }
                    };
                    
                    // Initialize with saved filter
                    let currentFilter = selectedFilter;
                    let currentMonth = selectedMonth;
                    let currentYear = selectedYear;
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
                                        color: isDarkMode() ? '#CBD5E1' : '#0F172A',
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
                                        color: isDarkMode() ? '#CBD5E1' : '#0F172A',
                                        font: {
                                            size: 16,
                                            weight: '400',
                                            family: 'inherit'
                                        },
                                        padding: {
                                            bottom: 15
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
                                        color: isDarkMode() ? '#CBD5E1' : '#0F172A',
                                        font: {
                                            size: 16,
                                            weight: '400',
                                            family: 'inherit'
                                        },
                                        padding: {
                                            top: 15
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
                    let yearFilter = document.getElementById('awardsYearFilter');
                    
                    // Show/hide filters based on selected timeframe
                    const updateFilterVisibility = () => {
                        monthFilter = document.getElementById('awardsMonthFilter');
                        yearFilter = document.getElementById('awardsYearFilter');
                        if (monthFilter) {
                            if (currentFilter === 'MTD') {
                                monthFilter.classList.remove('hidden');
                            } else {
                                monthFilter.classList.add('hidden');
                            }
                        }
                        if (yearFilter) {
                            if (currentFilter === 'YTD') {
                                yearFilter.classList.remove('hidden');
                            } else {
                                yearFilter.classList.add('hidden');
                            }
                        }
                    };
                    
                    // Update chart with new data and max value
                    const updateChart = async (filter, month = null, year = null) => {
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
                            const desiredYear = year || currentYear;
                            if (desiredYear && desiredYear !== currentYear) {
                                const newYTDData = await fetchYTDData(desiredYear);
                                categoryDataYTD = newYTDData;
                                currentYear = desiredYear;
                            }
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
                    
                    // Initialize filter visibility and chart max value
                    updateFilterVisibility();
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
                            if (currentFilter === 'MTD') {
                                await updateChart(currentFilter, currentMonth);
                            } else {
                                await updateChart(currentFilter, null, currentYear);
                            }
                            updateFilterVisibility();
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

                    // Add event listener to year filter dropdown
                    yearFilter = document.getElementById('awardsYearFilter');
                    if (yearFilter) {
                        const currentYearValue = yearFilter.value || currentYear;
                        const newYearFilter = yearFilter.cloneNode(true);
                        newYearFilter.value = currentYearValue;
                        yearFilter.parentNode.replaceChild(newYearFilter, yearFilter);
                        yearFilter = newYearFilter;
                        
                        newYearFilter.addEventListener('change', async function() {
                            const selectedYearValue = this.value;
                            await updateChart('YTD', null, selectedYearValue);
                            await savePreference('dashboard_awards_year', selectedYearValue);
                        });
                    }
                }
                
                // Load MOU-MOA Renewals
                async function loadRenewals() {
                    const container = document.getElementById('renewalsContainer');
                    if (!container) return;
                    
                    try {
                        const response = await fetch('api/mou-moa.php?action=renewals');
                        const result = await response.json();
                        
                        if (!result.success) {
                            throw new Error(result.error || 'Failed to load renewals');
                        }
                        
                        const renewals = result.data || [];
                        
                        if (renewals.length === 0) {
                            container.classList.add('flex', 'items-center', 'justify-center');
                            container.innerHTML = `
                                <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                                    <span class="material-symbols-outlined text-4xl mb-3">check_circle</span>
                                    <p class="text-sm font-medium text-center">No renewals needed</p>
                                    <p class="text-xs mt-1 text-center">All MOU/MOA are up to date</p>
                                </div>
                            `;
                            return;
                        }
                        
                        container.classList.remove('flex', 'items-center', 'justify-center');
                        container.className = 'px-6 pb-6 pt-0';
                        
                        container.innerHTML = `
                            <table class="w-full table-auto">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="text-center py-1 px-2 text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide whitespace-nowrap">File name</th>
                                        <th class="text-center py-1 px-2 text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide whitespace-nowrap">End Date</th>
                                        <th class="text-center py-1 px-2 text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide whitespace-nowrap">Days Left</th>
                                        <th class="text-center py-1 px-2 text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide whitespace-nowrap">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${renewals.map((renewal, index) => {
                                        const endDate = new Date(renewal.end_date);
                                        const formattedDate = endDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                                        
                                        const title = renewal.title || renewal.institution || 'Untitled';
                                        const daysRemaining = renewal.days_remaining || 0;
                                        const isUrgent = daysRemaining <= 7;
                                        const isExpiringSoon = daysRemaining > 7 && daysRemaining <= 30;
                                        
                                        let statusDot = '';
                                        let statusText = '';
                                        if (isUrgent) {
                                            statusDot = 'bg-red-500';
                                            statusText = '<p class="text-sm font-medium text-red-600 dark:text-red-500">Urgent</p>';
                                        } else if (isExpiringSoon) {
                                            statusDot = 'bg-yellow-500';
                                            statusText = '<p class="text-sm font-medium text-yellow-600 dark:text-yellow-500">Expiring Soon</p>';
                                        } else {
                                            statusDot = 'bg-gray-400 dark:bg-gray-500';
                                            statusText = '<p class="text-sm font-medium text-gray-600 dark:text-gray-400">Standard</p>';
                                        }
                                        
                                        const daysText = daysRemaining > 0 ? `${daysRemaining} ${daysRemaining === 1 ? 'day' : 'days'} left` : 'Overdue';
                                        
                                        return `
                                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                                <td class="py-1 px-2 text-center">
                                                    <span class="text-sm font-bold text-gray-900 dark:text-white truncate block">${title}</span>
                                                </td>
                                                <td class="py-1 px-2 text-center">
                                                    <span class="text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">${formattedDate}</span>
                                                </td>
                                                <td class="py-1 px-2 text-center">
                                                    <span class="text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">${daysText}</span>
                                                </td>
                                                <td class="py-1 px-2 text-center">
                                                    <div class="flex items-center gap-2 text-sm font-medium whitespace-nowrap justify-center">
                                                        <button 
                                                            onclick="viewMouDetails(${renewal.id})"
                                                            class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 rounded"
                                                            title="View Details">
                                                            View
                                                        </button>
                                                        <button 
                                                            onclick="showRenewalModal(${renewal.id})"
                                                            class="text-green-600 dark:text-green-500 hover:text-green-700 dark:hover:text-green-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 rounded"
                                                            title="Mark as Renewed">
                                                            Renew
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        `;
                                    }).join('')}
                                </tbody>
                            </table>
                        `;
                    } catch (error) {
                        console.error('Error loading renewals:', error);
                        container.innerHTML = `
                            <div class="flex flex-col items-center justify-center h-full text-red-400 dark:text-red-500">
                                <span class="material-symbols-outlined text-4xl mb-2">error</span>
                                <p class="text-sm">Failed to load renewals</p>
                                <button onclick="loadRenewals()" class="mt-2 text-xs text-primary hover:underline">Retry</button>
                            </div>
                        `;
                    }
                }
                
                // Show renewal confirmation modal
                window.showRenewalModal = function(id) {
                    const modal = document.getElementById('renewalConfirmModal');
                    const confirmBtn = document.getElementById('confirmRenewalBtn');
                    const cancelBtn = document.getElementById('cancelRenewalBtn');
                    
                    // Close modal function
                    const closeModal = () => {
                        modal.classList.add('hidden');
                    };
                    
                    // Remove existing event listeners by cloning
                    const newConfirmBtn = confirmBtn.cloneNode(true);
                    const newCancelBtn = cancelBtn.cloneNode(true);
                    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
                    cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
                    
                    // Add new event listeners
                    newConfirmBtn.onclick = () => {
                        closeModal();
                        markAsRenewed(id);
                    };
                    newCancelBtn.onclick = closeModal;
                    
                    modal.classList.remove('hidden');
                };
                
                // Mark as renewed function (global for onclick handlers)
                window.markAsRenewed = async function(id) {
                    
                    try {
                        const response = await fetch(`api/mou-moa.php?action=mark-renewed&id=${id}`, {
                            method: 'PATCH'
                        });
                        const result = await response.json();
                        
                        if (!result.success) {
                            throw new Error(result.error || 'Failed to mark as renewed');
                        }
                        
                        // Reload renewals
                        await loadRenewals();
                        
                        // Show success message (optional)
                        const notification = document.createElement('div');
                        notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50 flex items-center gap-2';
                        notification.innerHTML = `
                            <span class="material-symbols-outlined text-sm">check_circle</span>
                            <span class="text-sm">Marked as renewed successfully</span>
                        `;
                        document.body.appendChild(notification);
                        setTimeout(() => notification.remove(), 3000);
                    } catch (error) {
                        console.error('Error marking as renewed:', error);
                        alert('Failed to mark as renewed: ' + error.message);
                    }
                }
                
                // Load renewals on page load
                loadRenewals();
                
                // Make loadRenewals globally accessible for retry
                window.loadRenewals = loadRenewals;
                
                // View MOU Details function
                window.viewMouDetails = async function(id) {
                    try {
                        const response = await fetch(`api/mou-moa.php?action=get&id=${id}`);
                        const result = await response.json();
                        
                        if (!result.success || !result.data) {
                            throw new Error(result.error || 'Failed to load MOU details');
                        }
                        
                        showMouDetailsModal(result.data);
                    } catch (error) {
                        console.error('Error loading MOU details:', error);
                        alert('Failed to load MOU details: ' + error.message);
                    }
                };
                
                // Theme change observer
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.attributeName === 'class') {
                            Chart.helpers.each(Chart.instances, function(instance) {
                                if (instance.config.type === 'bar') {
                                    instance.options.scales.y.grid.color = isDarkMode() ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
                                    instance.options.scales.y.ticks.color = isDarkMode() ? '#CBD5E1' : '#475569';
                                    instance.options.scales.x.ticks.color = isDarkMode() ? '#CBD5E1' : '#475569';
                                }
                                if (instance.config.type === 'doughnut') {
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

        // Profile pictures are loaded from database via PHP - no localStorage needed
        
        // Update greeting based on time of day
        document.addEventListener('DOMContentLoaded', function() {
            const greetingElement = document.getElementById('user-greeting');
            if (greetingElement) {
                const hour = new Date().getHours();
                const firstName = greetingElement.textContent.trim();
                let greeting = '';
                
                if (hour >= 0 && hour < 12) {
                    greeting = 'Good Morning';
                } else if (hour >= 12 && hour < 18) {
                    greeting = 'Good Afternoon';
                } else {
                    greeting = 'Good Evening';
                }
                
                greetingElement.textContent = greeting + ', ' + firstName + '!';
            }
        });
    </script>

<script>
    // Notification System
    (function() {
        let notifications = [];
        let notificationBtn, notificationDropdown, notificationBadge, notificationList, noNotifications, markAllReadBtn, viewAllNotifications;
        
        function initNotificationSystem() {
            notificationBtn = document.getElementById('notificationBtn');
            notificationDropdown = document.getElementById('notificationDropdown');
            notificationBadge = document.getElementById('notificationBadge');
            notificationList = document.getElementById('notificationList');
            noNotifications = document.getElementById('noNotifications');
            markAllReadBtn = document.getElementById('markAllReadBtn');
            viewAllNotifications = document.getElementById('viewAllNotifications');
            
            if (!notificationBtn || !notificationDropdown) {
                // Retry if elements not ready yet
                if (document.readyState === 'loading') {
                    setTimeout(initNotificationSystem, 100);
                }
                return;
            }
            
            // Toggle dropdown - Always refresh notifications when opening to ensure sync across all pages
            notificationBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                notificationDropdown.classList.toggle('hidden');
                if (!notificationDropdown.classList.contains('hidden')) {
                    // Always reload notifications from API to ensure we have the latest from all pages
                    loadNotifications();
                }
            });
            
            if (notificationList) {
                notificationList.addEventListener('click', handleNotificationListClick);
                notificationList.addEventListener('keydown', handleNotificationListKeydown);
            }
            
            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                const btn = document.getElementById('notificationBtn');
                const dropdown = document.getElementById('notificationDropdown');
                if (btn && dropdown && !btn.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.add('hidden');
                }
            });
        }
        
        // Define handler functions before they're used
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
        
        // Initialize notification system
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initNotificationSystem);
        } else {
            initNotificationSystem();
        }
        
        // Check for new notifications and create them
        async function checkNotifications() {
            try {
                const enabled = window.areNotificationsEnabled ? await window.areNotificationsEnabled() : true;
                if (!enabled) return;

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
                const enabled = window.areNotificationsEnabled ? await window.areNotificationsEnabled() : true;
                if (!enabled) {
                    notifications = [];
                    updateNotificationDisplay();
                    if (notificationBadge) {
                        notificationBadge.classList.add('hidden');
                    }
                    return;
                }

                const response = await fetch('api/notifications.php');
                const data = await response.json();
                if (data.notifications) {
                    const previousNotifications = notifications || [];
                    
                    // Standardize notification processing across all pages
                    // Deduplicate MOU notifications: keep only the most recent one for each MOU+type combination
                    const mouNotificationMap = new Map();
                    const otherNotifications = [];
                    
                    data.notifications.forEach(notif => {
                        if (notif.related_type === 'mou_moa' && notif.related_id) {
                            // Create a unique key for MOU notifications: related_id + type
                            const key = `${notif.related_id}_${notif.type}`;
                            
                            if (!mouNotificationMap.has(key)) {
                                mouNotificationMap.set(key, notif);
                            } else {
                                // Keep the most recent one
                                const existing = mouNotificationMap.get(key);
                                const existingDate = new Date(existing.created_at);
                                const currentDate = new Date(notif.created_at);
                                
                                if (currentDate > existingDate) {
                                    mouNotificationMap.set(key, notif);
                                }
                            }
                        } else {
                            // Keep ALL non-MOU notifications as-is (schedules, events, etc.)
                            otherNotifications.push(notif);
                        }
                    });
                    
                    // Combine deduplicated MOU notifications with ALL other notifications
                    notifications = [...Array.from(mouNotificationMap.values()), ...otherNotifications];
                    
                    // Sort by created_at (most recent first)
                    notifications.sort((a, b) => {
                        const dateA = new Date(a.created_at);
                        const dateB = new Date(b.created_at);
                        return dateB - dateA;
                    });
                    
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
                            <button onclick="event.stopPropagation(); confirmMouRenewal(${notif.id}, 'renewed', ${notif.related_id})" 
                                    class="px-3 py-1.5 text-xs font-medium bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">check_circle</span>
                                Renewed
                            </button>
                            <button onclick="event.stopPropagation(); confirmMouRenewal(${notif.id}, 'not_renewed', ${notif.related_id})" 
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
                const clickableClass = isMouNotification && !isConfirmed ? '' : 'cursor-pointer';
                
                return `
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 ${clickableClass} focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/60 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-background-dark ${notif.is_read ? 'opacity-60' : ''}" 
                         ${!isMouNotification || isConfirmed ? `role="button" tabindex="0" data-id="${notif.id}" data-notification-id="${notif.id}"${urlAttribute}` : ''}>
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
        
        // Setup event listeners for the all notifications modal
        function setupAllNotificationsModalEvents() {
            const modal = document.getElementById('allNotificationsModal');
            if (!modal) return;
            
            const closeBtn2 = document.getElementById('closeAllNotificationsModalBtn2');
            const markAllReadBtn = document.getElementById('markAllReadModalBtn');
            const clearOldBtn = document.getElementById('clearOldNotifications');
            const tabs = document.querySelectorAll('.notification-tab');
            
            // Close modal handler
            if (closeBtn2) {
                closeBtn2.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeAllNotificationsModal();
                });
            }
            
            // Close modal when clicking outside
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeAllNotificationsModal();
                }
            });
            
            // Close modal with Escape key
            const escapeHandler = function(e) {
                if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                    closeAllNotificationsModal();
                }
            };
            document.addEventListener('keydown', escapeHandler);
            
            // Mark all as read
            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', async function(e) {
                    e.preventDefault();
                    e.stopPropagation();
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
                            await loadAllNotificationsIntoModal();
                            if (typeof updateNotificationBadge === 'function') {
                                await updateNotificationBadge();
                            }
                            if (typeof updateNotificationDisplay === 'function') {
                                updateNotificationDisplay();
                            }
                        }
                    } catch (error) {
                        console.error('Error marking all as read:', error);
                    }
                });
            }
            
            // Clear all notifications
            let isClearingAll = false;
            if (clearOldBtn) {
                clearOldBtn.addEventListener('click', async function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (isClearingAll) return;
                    isClearingAll = true;
                    
                    try {
                        const response = await fetch('api/notifications.php', {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json'
                            },
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
                    } finally {
                        isClearingAll = false;
                    }
                });
            }
            
            // Tab filtering
            if (tabs && tabs.length > 0) {
                tabs.forEach(tab => {
                    tab.addEventListener('click', function() {
                        // Update active tab
                        tabs.forEach(t => {
                            t.classList.remove('bg-white', 'dark:bg-gray-700', 'text-gray-900', 'dark:text-white', 'shadow-sm');
                            t.classList.add('text-gray-600', 'dark:text-gray-400');
                        });
                        
                        this.classList.remove('text-gray-600', 'dark:text-gray-400');
                        this.classList.add('bg-white', 'dark:bg-gray-700', 'text-gray-900', 'dark:text-white', 'shadow-sm');
                        
                        // Filter notifications
                        const filter = this.dataset.filter;
                        filterAllNotifications(filter);
                    });
                });
            }
        }
        
        // Store notifications for filtering
        let allNotificationsData = [];
        
        // Filter notifications
        function filterAllNotifications(filter) {
            const modalList = document.getElementById('allNotificationsList');
            if (!modalList) return;
            
            let filteredNotifications = allNotificationsData;
            
            if (filter === 'unread') {
                filteredNotifications = allNotificationsData.filter(n => !n.is_read);
            } else if (filter === 'critical') {
                filteredNotifications = allNotificationsData.filter(n => 
                    n.type === 'mou_expired' || 
                    (n.type === 'mou_expiring_soon' && n.mou_days_until_expiry !== undefined && n.mou_days_until_expiry <= 3)
                );
            }
            
            // Re-render filtered notifications
            renderNotificationsInModal(filteredNotifications);
        }
        
        // Render notifications in modal
        function renderNotificationsInModal(notifications) {
            const modalList = document.getElementById('allNotificationsList');
            const countElement = document.getElementById('notificationsCount');
            
            if (!modalList) return;
            
            if (countElement) {
                countElement.textContent = notifications.length;
            }
            
            if (notifications.length === 0) {
                modalList.innerHTML = `
                    <div class="text-center py-12">
                        <span class="material-symbols-outlined text-6xl text-gray-400 dark:text-gray-600 mb-4 block">notifications_off</span>
                        <p class="text-gray-500 dark:text-gray-400 text-lg">No notifications</p>
                    </div>
                `;
                return;
            }
            
            // Use existing rendering logic from loadAllNotificationsIntoModal
            // This will be handled by updating loadAllNotificationsIntoModal to store data
        }
        
        // Show all notifications modal
        function showAllNotificationsModal() {
            createAllNotificationsModal();
            const modal = document.getElementById('allNotificationsModal');
            if (modal) {
                modal.classList.remove('hidden');
                loadAllNotificationsIntoModal();
            }
        }
        
        // Close all notifications modal
        function closeAllNotificationsModal() {
            const modal = document.getElementById('allNotificationsModal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }
        
        // Load all notifications into the modal
        async function loadAllNotificationsIntoModal() {
            const modalList = document.getElementById('allNotificationsList');
            const countElement = document.getElementById('notificationsCount');
            
            if (!modalList) return;
            
            try {
                const response = await fetch('api/notifications.php');
                const data = await response.json();
                
                if (data.notifications && Array.isArray(data.notifications)) {
                    let allNotifications = data.notifications;
                    
                    // Sort by created_at (most recent first)
                    allNotifications.sort((a, b) => {
                        const dateA = new Date(a.created_at);
                        const dateB = new Date(b.created_at);
                        return dateB - dateA;
                    });
                    
                    // Store notifications for filtering
                    allNotificationsData = allNotifications;
                    
                    // Render notifications
                    renderNotificationsInModal(allNotifications);
                } else {
                    allNotificationsData = [];
                    if (countElement) {
                        countElement.textContent = 0;
                    }
                    modalList.innerHTML = `
                        <div class="text-center py-12">
                            <span class="material-symbols-outlined text-6xl text-gray-400 dark:text-gray-600 mb-4 block">notifications_off</span>
                            <p class="text-gray-500 dark:text-gray-400 text-lg">No notifications</p>
                        </div>
                    `;
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
        
        // Render notifications in modal (updated with full rendering logic)
        function renderNotificationsInModal(notifications) {
            const modalList = document.getElementById('allNotificationsList');
            const countElement = document.getElementById('notificationsCount');
            
            if (!modalList) return;
            
            if (countElement) {
                countElement.textContent = notifications.length;
            }
            
            if (notifications.length === 0) {
                modalList.innerHTML = `
                    <div class="text-center py-12">
                        <span class="material-symbols-outlined text-6xl text-gray-400 dark:text-gray-600 mb-4 block">notifications_off</span>
                        <p class="text-gray-500 dark:text-gray-400 text-lg">No notifications</p>
                    </div>
                `;
                return;
            }
            
            modalList.innerHTML = notifications.map(notif => {
                const timeAgo = getTimeAgo(notif.created_at);
                const icon = getNotificationIcon(notif.type);
                const bgColor = getNotificationBgColor(notif.type);
                const targetUrl = getNotificationUrl(notif);
                const urlAttribute = targetUrl ? ` data-url="${encodeURIComponent(targetUrl)}"` : '';
                const isMouNotification = notif.related_type === 'mou_moa';
                const isConfirmed = notif.is_confirmed || false;
                
                // Add Renew/Renewed buttons for MOU notifications that aren't confirmed
                let actionButtons = '';
                if (isMouNotification && !isConfirmed) {
                    actionButtons = `
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
                    actionButtons = `
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
                                ${actionButtons}
                            </div>
                            ${!notif.is_read ? '<div class="flex-shrink-0 w-2 h-2 bg-primary rounded-full mt-2"></div>' : ''}
                        </div>
                    </div>
                `;
            }).join('');
            
            // Add click handlers for notifications in modal
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
        
        // Old rendering code removed - now using renderNotificationsInModal
        /*
                    if (allNotifications.length === 0) {
                        modalList.innerHTML = `
                            <div class="text-center py-12">
                                <span class="material-symbols-outlined text-6xl text-text-muted-light dark:text-text-muted-dark mb-4 block">notifications_off</span>
                                <p class="text-text-muted-light dark:text-text-muted-dark text-lg">No notifications</p>
                            </div>
                        `;
                    } else {
                        modalList.innerHTML = allNotifications.map(notif => {
                            const timeAgo = getTimeAgo(notif.created_at);
                            const icon = getNotificationIcon(notif.type);
                            const bgColor = getNotificationBgColor(notif.type);
                            const targetUrl = getNotificationUrl(notif);
                            const urlAttribute = targetUrl ? ` data-url="${encodeURIComponent(targetUrl)}"` : '';
                            const isMouNotification = notif.related_type === 'mou_moa';
                            const isConfirmed = notif.is_confirmed || false;
                            
                            // Add Renew/Renewed buttons for MOU notifications that aren't confirmed
                            let actionButtons = '';
                            if (isMouNotification && !isConfirmed) {
                                actionButtons = `
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
                                actionButtons = `
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
                                <div class="p-4 border-b border-border-light dark:border-border-dark hover:bg-background-light dark:hover:bg-background-dark cursor-pointer transition-colors ${notif.is_read ? 'opacity-60' : ''}" 
                                     data-notification-id="${notif.id}"${urlAttribute}>
                                    <div class="flex items-start gap-3">
                                        <div class="flex-shrink-0 w-10 h-10 rounded-full ${bgColor} flex items-center justify-center">
                                            <span class="material-symbols-outlined text-white text-lg">${icon}</span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-text-light dark:text-text-dark">${escapeHtml(notif.title)}</p>
                                            <p class="text-sm text-text-muted-light dark:text-text-muted-dark mt-1">${escapeHtml(notif.message)}</p>
                                            <p class="text-xs text-text-muted-light dark:text-text-muted-dark mt-1">${timeAgo}</p>
                                            ${actionHint}
                                            ${actionButtons}
                                        </div>
                                        ${!notif.is_read ? '<div class="flex-shrink-0 w-2 h-2 bg-primary rounded-full mt-2"></div>' : ''}
                                    </div>
                                </div>
                            `;
                        }).join('');
                        
                        // Add click handlers for notifications in modal
                        modalList.querySelectorAll('[data-notification-id]').forEach(item => {
                            item.addEventListener('click', async function(e) {
                                // Don't trigger if clicking on buttons
                                if (e.target.closest('button')) {
                                    return;
                                }
                                
                                const notificationId = Number(item.dataset.notificationId);
                                if (notificationId) {
                                    await markNotificationAsRead(notificationId);
                                    const targetUrl = decodeUrlAttribute(item.dataset.url);
                                    if (targetUrl) {
                                        closeAllNotificationsModal();
                                        window.location.href = targetUrl;
                                    }
                                }
                            });
                        });
                    }
                }
            } catch (error) {
                console.error('Error loading notifications into modal:', error);
                modalList.innerHTML = `
                    <div class="text-center py-12">
                        <span class="material-symbols-outlined text-6xl text-red-500 mb-4 block">error</span>
                        <p class="text-text-light dark:text-text-dark text-lg">Error loading notifications</p>
                    </div>
                `;
            }
        }
        */
        
        // View all notifications - open modal
        if (viewAllNotifications) {
            viewAllNotifications.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                // Close dropdown first
                if (notificationDropdown) {
                    notificationDropdown.classList.add('hidden');
                }
                showAllNotificationsModal();
            }, true); // Use capture phase to ensure it runs first
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
        
        // Confirm MOU renewal status
        window.confirmMouRenewal = async function(notificationId, renewalStatus, entryId) {
            // For "renewed": open the MOU/MOA renew flow (edit sign date + term) instead of immediately confirming.
            if (renewalStatus === 'renewed') {
                if (typeof window.openMouRenewalFlow === 'function') {
                    window.openMouRenewalFlow(notificationId, entryId);
                } else {
                    // Fallback: navigate to MOU/MOA page
                    if (!entryId) {
                        alert('Error: missing MOU/MOA entry id for renewal.');
                        return;
                    }
                    window.location.href = `mou-moa.php?entry=${encodeURIComponent(entryId)}&renew=1&notif=${encodeURIComponent(notificationId)}`;
                }
                return;
            }
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
                const enabled = window.areNotificationsEnabled ? await window.areNotificationsEnabled() : true;
                if (!enabled) {
                    if (notificationBadge) {
                        notificationBadge.classList.add('hidden');
                    }
                    return;
                }

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
<script>
    // Global Search Functionality
    (function() {
        const searchInput = document.getElementById('globalSearchInput');
        const searchDropdown = document.getElementById('searchResultsDropdown');
        const searchResultsContent = document.getElementById('searchResultsContent');
        
        if (!searchInput || !searchDropdown || !searchResultsContent) return;
        
        let searchTimeout;
        let currentSearchQuery = '';
        
        // Debounced search function
        function performSearch(query) {
            if (query.length < 2) {
                searchDropdown.classList.add('hidden');
                return;
            }
            
            currentSearchQuery = query;
            
            // Show loading state
            searchResultsContent.innerHTML = '<div class="p-4 text-sm text-slate-500 dark:text-slate-400 text-center">Searching...</div>';
            searchDropdown.classList.remove('hidden');
            
            fetch(`api/search.php?q=${encodeURIComponent(query)}&limit=5`)
                .then(response => {
                    console.log('Search API response status:', response.status);
                    if (!response.ok) {
                        throw new Error('Search request failed: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Search response:', data);
                    if (currentSearchQuery === query) {
                        if (data.success) {
                            console.log('Results:', data.results);
                            console.log('Results count:', Array.isArray(data.results) ? data.results.length : 'Not an array');
                            console.log('Counts:', data.counts);
                            
                            // Ensure counts object exists with defaults
                            const counts = data.counts || {
                                awards: 0,
                                events: 0,
                                documents: 0,
                                mous: 0
                            };
                            
                            displaySearchResults(data.results, counts);
                        } else {
                            console.error('Search failed:', data.error);
                            searchResultsContent.innerHTML = '<div class="p-4 text-sm text-red-500 dark:text-red-400 text-center">' + (data.error || 'Search failed. Please try again.') + '</div>';
                            searchDropdown.classList.remove('hidden');
                        }
                    }
                })
                .catch(error => {
                    console.error('Search error:', error);
                    if (currentSearchQuery === query) {
                        searchResultsContent.innerHTML = '<div class="p-4 text-sm text-red-500 dark:text-red-400 text-center">Error performing search. Please check your connection and try again.<br><small>' + error.message + '</small></div>';
                        searchDropdown.classList.remove('hidden');
                    }
                });
        }
        
        // Display search results
        function displaySearchResults(results, counts) {
            // Handle both array and object formats
            let resultsArray = [];
            if (Array.isArray(results)) {
                resultsArray = results;
            } else if (results && typeof results === 'object') {
                // If results is an object with categories, flatten it
                Object.keys(results).forEach(category => {
                    if (Array.isArray(results[category])) {
                        resultsArray = resultsArray.concat(results[category]);
                    }
                });
            }
            
            if (!resultsArray || resultsArray.length === 0) {
                searchResultsContent.innerHTML = '<div class="p-4 text-sm text-slate-500 dark:text-slate-400 text-center">No results found</div>';
                searchDropdown.classList.remove('hidden');
                return;
            }
            
            let html = '';
            
            // Group results by type
            const grouped = {
                awards: [],
                events: [],
                documents: [],
                mous: []
            };
            
            resultsArray.forEach(result => {
                if (result.type === 'award') {
                    grouped.awards.push(result);
                } else if (result.type === 'event') {
                    grouped.events.push(result);
                } else if (result.type === 'document') {
                    grouped.documents.push(result);
                } else if (result.type === 'mou') {
                    grouped.mous.push(result);
                }
            });
            
            // Awards section
            if (grouped.awards.length > 0) {
                html += '<div class="px-3 py-2 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase">Awards (' + counts.awards + ')</div>';
                grouped.awards.forEach(item => {
                    html += createResultItem(item, 'award');
                });
            }
            
            // Events section
            if (grouped.events.length > 0) {
                html += '<div class="px-3 py-2 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase border-t border-slate-200 dark:border-slate-700 mt-2">Events (' + counts.events + ')</div>';
                grouped.events.forEach(item => {
                    html += createResultItem(item, 'event');
                });
            }
            
            // Documents section
            if (grouped.documents.length > 0) {
                html += '<div class="px-3 py-2 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase border-t border-slate-200 dark:border-slate-700 mt-2">Documents (' + counts.documents + ')</div>';
                grouped.documents.forEach(item => {
                    html += createResultItem(item, 'document');
                });
            }
            
            // MOUs section
            if (grouped.mous.length > 0) {
                html += '<div class="px-3 py-2 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase border-t border-slate-200 dark:border-slate-700 mt-2">MOUs/MOAs (' + counts.mous + ')</div>';
                grouped.mous.forEach(item => {
                    html += createResultItem(item, 'mou');
                });
            }
            
            searchResultsContent.innerHTML = html;
            searchDropdown.classList.remove('hidden');
        }
        
        // Create result item HTML
        function createResultItem(item, type) {
            const icons = {
                award: 'emoji_events',
                event: 'event',
                document: 'description',
                mou: 'handshake'
            };
            
            const colors = {
                award: 'text-yellow-600 dark:text-yellow-400',
                event: 'text-blue-600 dark:text-blue-400',
                document: 'text-green-600 dark:text-green-400',
                mou: 'text-purple-600 dark:text-purple-400'
            };
            
            const date = new Date(item.created_at);
            const formattedDate = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            
            return `
                <a href="${item.url}" class="flex items-center gap-3 p-3 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition-colors group">
                    <span class="material-symbols-outlined ${colors[type]}">${icons[type]}</span>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-slate-900 dark:text-slate-100 truncate group-hover:text-primary">${escapeHtml(item.title)}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">${formattedDate}</div>
                    </div>
                    <span class="material-symbols-outlined text-slate-400 text-sm opacity-0 group-hover:opacity-100 transition-opacity">arrow_forward</span>
                </a>
            `;
        }
        
        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Event listeners
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            
            clearTimeout(searchTimeout);
            
            if (query.length < 2) {
                searchDropdown.classList.add('hidden');
                return;
            }
            
            searchTimeout = setTimeout(() => {
                performSearch(query);
            }, 300); // 300ms debounce
        });
        
        searchInput.addEventListener('focus', () => {
            if (searchInput.value.trim().length >= 2 && searchResultsContent.innerHTML) {
                searchDropdown.classList.remove('hidden');
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !searchDropdown.contains(e.target)) {
                searchDropdown.classList.add('hidden');
            }
        });
        
        // Handle Enter key to navigate to first result
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const firstResult = searchResultsContent.querySelector('a');
                if (firstResult) {
                    e.preventDefault();
                    window.location.href = firstResult.href;
                }
            }
        });
    })();
</script>

<!-- MOU Details Modal -->
<div id="mouDetailsModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex min-h-screen items-start justify-center p-4 pt-12">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeMouDetailsModal()"></div>
        
        <!-- Modal Content -->
        <div class="relative w-full max-w-7xl bg-white dark:bg-background-dark rounded-xl shadow-2xl">
            <div class="p-8 overflow-y-auto max-h-[90vh]">
                <!-- Breadcrumbs -->
                <div class="flex flex-wrap gap-2 items-center mb-6">
                    <a href="#" class="text-slate-500 dark:text-slate-400 text-sm font-medium hover:text-primary" onclick="closeMouDetailsModal(); return false;">Dashboard</a>
                    <span class="material-symbols-outlined text-slate-400 dark:text-slate-500 text-base">chevron_right</span>
                    <span class="text-slate-800 dark:text-slate-200 text-sm font-medium" id="modalBreadcrumbTitle">MOU Details</span>
                </div>
                
                <!-- Header -->
                <div class="flex flex-wrap justify-between items-start gap-4 mb-8">
                    <div class="flex flex-col gap-2">
                        <h1 class="text-slate-900 dark:text-slate-50 text-4xl font-black leading-tight tracking-tight" id="modalTitle">MOU Title</h1>
                        <div class="flex items-center gap-2">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                            </span>
                            <p class="text-green-600 dark:text-green-400 text-sm font-medium" id="modalStatus">Active</p>
                        </div>
                    </div>
                    <div class="flex flex-1 sm:flex-none gap-3 flex-wrap justify-start sm:justify-end">
                        <button onclick="editMouFromModal()" class="flex min-w-[84px] cursor-pointer items-center justify-center rounded-lg h-10 px-4 bg-slate-200 dark:bg-slate-800 text-slate-900 dark:text-slate-50 text-sm font-bold hover:bg-slate-300 dark:hover:bg-slate-700 transition-colors">
                            <span>Edit</span>
                        </button>
                        <button onclick="renewMouFromModal()" class="flex min-w-[84px] cursor-pointer items-center justify-center rounded-lg h-10 px-4 bg-primary text-white text-sm font-bold hover:bg-primary/90 transition-colors">
                            <span>Renew</span>
                        </button>
                        <button onclick="closeMouDetailsModal()" class="flex size-10 items-center justify-center rounded-lg bg-slate-200 dark:bg-slate-800 text-slate-900 dark:text-slate-50 hover:bg-slate-300 dark:hover:bg-slate-700 transition-colors">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>
                </div>
                
                <!-- Content Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Left Column -->
                    <div class="lg:col-span-2 flex flex-col gap-8">
                        <!-- Key Details -->
                        <div class="bg-white dark:bg-slate-900/50 p-6 rounded-xl border border-slate-200 dark:border-slate-800">
                            <h2 class="text-slate-900 dark:text-slate-50 text-lg font-bold mb-6">Key Details</h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                                <div class="flex flex-col gap-1">
                                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Parties Involved</p>
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200" id="modalParties">-</p>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Type</p>
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200" id="modalType">-</p>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Effective Date</p>
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200" id="modalSignDate">-</p>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Expiration / Renewal Date</p>
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200" id="modalEndDate">-</p>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Location</p>
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200" id="modalLocation">-</p>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Contact Person</p>
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200" id="modalContact">-</p>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Term</p>
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-200" id="modalTerm">-</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Terms & Conditions -->
                        <div class="bg-white dark:bg-slate-900/50 p-6 rounded-xl border border-slate-200 dark:border-slate-800">
                            <h2 class="text-slate-900 dark:text-slate-50 text-lg font-bold mb-4">Terms & Conditions</h2>
                            <div class="prose prose-sm dark:prose-invert max-h-60 overflow-y-auto pr-3 text-slate-600 dark:text-slate-300" id="modalDescription">
                                <p>No description available.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column -->
                    <div class="flex flex-col gap-8">
                        <!-- Associated Documents -->
                        <div class="bg-white dark:bg-slate-900/50 p-6 rounded-xl border border-slate-200 dark:border-slate-800">
                            <h2 class="text-slate-900 dark:text-slate-50 text-lg font-bold mb-4">Associated Documents</h2>
                            <ul class="space-y-3" id="modalDocuments">
                                <li class="text-sm text-slate-500 dark:text-slate-400">No documents attached.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // MOU Details Modal Functions
    let currentMouData = null;
    
    function showMouDetailsModal(data) {
        currentMouData = data;
        const modal = document.getElementById('mouDetailsModal');
        
        // Set title
        const title = data.title || data.institution || 'Untitled MOU/MOA';
        document.getElementById('modalTitle').textContent = title;
        document.getElementById('modalBreadcrumbTitle').textContent = title.length > 40 ? title.substring(0, 40) + '...' : title;
        
        // Set status
        const status = data.status || 'Active';
        const statusEl = document.getElementById('modalStatus');
        statusEl.textContent = status;
        if (status === 'Expired') {
            statusEl.className = 'text-red-600 dark:text-red-400 text-sm font-medium';
        } else if (status === 'Expires Soon') {
            statusEl.className = 'text-yellow-600 dark:text-yellow-400 text-sm font-medium';
        } else {
            statusEl.className = 'text-green-600 dark:text-green-400 text-sm font-medium';
        }
        
        // Set key details
        document.getElementById('modalParties').textContent = data.partner || data.institution || '-';
        document.getElementById('modalType').textContent = data.type || data.category || '-';
        document.getElementById('modalSignDate').textContent = data.sign_date ? formatModalDate(data.sign_date) : '-';
        document.getElementById('modalEndDate').textContent = data.end_date ? formatModalDate(data.end_date) : '-';
        document.getElementById('modalLocation').textContent = data.location || '-';
        document.getElementById('modalContact').textContent = data.contact_email || '-';
        document.getElementById('modalTerm').textContent = data.term || '-';
        
        // Set description
        const descEl = document.getElementById('modalDescription');
        if (data.description && data.description.trim()) {
            descEl.innerHTML = '<p>' + escapeHtml(data.description).replace(/\n/g, '</p><p>') + '</p>';
        } else {
            descEl.innerHTML = '<p>No description available.</p>';
        }
        
        // Set documents
        const docsEl = document.getElementById('modalDocuments');
        if (data.file_name && data.file_path) {
            const fileName = data.file_name || 'Document';
            const fileSize = ''; // File size not stored in DB
            docsEl.innerHTML = `
                <li class="flex items-center justify-between gap-3 p-3 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800/50">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center size-10 bg-primary/10 dark:bg-primary/20 rounded-lg">
                            <span class="material-symbols-outlined text-primary">picture_as_pdf</span>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">${escapeHtml(fileName)}</p>
                            ${fileSize ? '<p class="text-xs text-slate-500 dark:text-slate-400">' + fileSize + '</p>' : ''}
                        </div>
                    </div>
                    <a href="api/mou-moa.php?action=download&id=${data.id}" class="text-slate-500 dark:text-slate-400 hover:text-primary dark:hover:text-primary">
                        <span class="material-symbols-outlined">download</span>
                    </a>
                </li>
            `;
        } else {
            docsEl.innerHTML = '<li class="text-sm text-slate-500 dark:text-slate-400">No documents attached.</li>';
        }
        
        // Show modal
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    
    function closeMouDetailsModal() {
        const modal = document.getElementById('mouDetailsModal');
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        currentMouData = null;
    }
    
    function formatModalDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
    }
    
    function editMouFromModal() {
        if (currentMouData) {
            window.location.href = `mou-moa.php?id=${currentMouData.id}`;
        }
    }
    
    function renewMouFromModal() {
        if (currentMouData) {
            showRenewalModal(currentMouData.id);
            // Close details modal when renewal modal opens
            setTimeout(() => {
                closeMouDetailsModal();
            }, 100);
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('mouDetailsModal');
            if (modal && !modal.classList.contains('hidden')) {
                closeMouDetailsModal();
            }
        }
    });
</script>

<script>
    // XAMPP/Apache version - no port redirect needed
</script>
<!-- Renewal Confirmation Modal -->
<div id="renewalConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center hidden">
    <div class="bg-black/50 backdrop-blur-sm fixed inset-0" onclick="document.getElementById('renewalConfirmModal').classList.add('hidden')"></div>
    <div class="w-full max-w-md bg-white dark:bg-card-dark rounded-xl shadow-2xl m-4 transform transition-all relative z-10">
        <!-- Modal Header -->
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-indigo-100 dark:bg-indigo-900/20 rounded-full flex items-center justify-center">
                    <span class="material-symbols-outlined text-indigo-600 dark:text-indigo-400 text-xl">check_circle</span>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Confirm Renewal</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">MOU/MOA Renewal</p>
                </div>
            </div>
        </div>
        
        <!-- Modal Body -->
        <div class="p-6">
            <p class="text-gray-700 dark:text-gray-300">
                Have you already renewed this MOU/MOA?
            </p>
        </div>
        
        <!-- Modal Footer -->
        <div class="p-6 bg-gray-50 dark:bg-slate-900/50 rounded-b-xl flex justify-end gap-3">
            <button id="cancelRenewalBtn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-slate-800 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-slate-700 transition-colors">
                Cancel
            </button>
            <button id="confirmRenewalBtn" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors">
                Confirm
            </button>
        </div>
    </div>
</div>

</body></html>



