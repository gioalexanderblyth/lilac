<?php
/**
 * Notifications API
 * Handles notification creation, retrieval, and management
 * for MOU/MOA expiration and upcoming events
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/schema-helpers.php';

session_start();

// Helper function to require authentication
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    return $_SESSION['user_id'];
}

// Helper function to get database connection
function db() {
    return getDatabaseConnection();
}

// Helper function to get JSON input
function jsonInput() {
    return json_decode(file_get_contents('php://input'), true);
}

/**
 * Get user's MOU/MOA notification period preference (in days)
 * Default is 6 months (180 days)
 */
function getUserMouNotificationPeriod($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT preference_value FROM user_preferences WHERE user_id = ? AND preference_key = 'mou_notification_period_days'");
        $stmt->execute([$userId]);
        $pref = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($pref && is_numeric($pref['preference_value']) && $pref['preference_value'] > 0) {
            return (int)$pref['preference_value'];
        }
        
        // Default: 6 months (180 days)
        return 180;
    } catch (Exception $e) {
        error_log('Error getting user MOU notification period: ' . $e->getMessage());
        return 180; // Default fallback
    }
}

/**
 * Get minimum notification period across all users (for system-wide notifications)
 * This ensures all users get notified in time based on their preferences
 */
function getMinimumNotificationPeriod($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT MIN(CAST(preference_value AS UNSIGNED)) as min_period 
            FROM user_preferences 
            WHERE preference_key = 'mou_notification_period_days' 
            AND preference_value REGEXP '^[0-9]+$'
            AND CAST(preference_value AS UNSIGNED) > 0
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && isset($result['min_period']) && $result['min_period'] > 0) {
            return (int)$result['min_period'];
        }
        
        // Default: 6 months (180 days)
        return 180;
    } catch (Exception $e) {
        error_log('Error getting minimum notification period: ' . $e->getMessage());
        return 180; // Default fallback
    }
}

/**
 * Get maximum notification period across all users (for creating notifications)
 * This ensures notifications are created for all MOUs that any user wants to be notified about
 */
function getMaximumNotificationPeriod($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT MAX(CAST(preference_value AS UNSIGNED)) as max_period 
            FROM user_preferences 
            WHERE preference_key = 'mou_notification_period_days' 
            AND preference_value REGEXP '^[0-9]+$'
            AND CAST(preference_value AS UNSIGNED) > 0
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && isset($result['max_period']) && $result['max_period'] > 0) {
            return (int)$result['max_period'];
        }
        
        // Default: 6 months (180 days)
        return 180;
    } catch (Exception $e) {
        error_log('Error getting maximum notification period: ' . $e->getMessage());
        return 180; // Default fallback
    }
}

/**
 * Get maximum days before expiry preference across all users
 * This is the second reminder notification (closer to expiration)
 */
function getMaximumDaysBeforeExpiry($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT MAX(CAST(preference_value AS UNSIGNED)) as max_days 
            FROM user_preferences 
            WHERE preference_key = 'days_before_expiry' 
            AND preference_value REGEXP '^[0-9]+$'
            AND CAST(preference_value AS UNSIGNED) > 0
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && isset($result['max_days']) && $result['max_days'] > 0) {
            return (int)$result['max_days'];
        }
        
        // Default: 30 days before expiry (if no preference set)
        return 30;
    } catch (Exception $e) {
        error_log('Error getting maximum days before expiry: ' . $e->getMessage());
        return 30; // Default fallback
    }
}

/**
 * Clean up duplicate notifications
 * Removes duplicate notifications keeping only the most recent one
 */
function cleanupDuplicateNotifications($pdo) {
    try {
        // Remove duplicate expired notifications (keep the most recent one)
        $pdo->exec("
            DELETE n1 FROM notifications n1
            INNER JOIN notifications n2 
            WHERE n1.id < n2.id
            AND n1.related_id = n2.related_id
            AND n1.related_type = n2.related_type
            AND n1.type = n2.type
            AND n1.user_id IS NULL
            AND n2.user_id IS NULL
            AND n1.related_type = 'mou_moa'
            AND n1.type = 'mou_expired'
        ");
        
        // Remove duplicate expiring notifications (keep the most recent one)
        $pdo->exec("
            DELETE n1 FROM notifications n1
            INNER JOIN notifications n2 
            WHERE n1.id < n2.id
            AND n1.related_id = n2.related_id
            AND n1.related_type = n2.related_type
            AND n1.type = n2.type
            AND n1.user_id IS NULL
            AND n2.user_id IS NULL
            AND n1.related_type = 'mou_moa'
            AND (n1.type = 'mou_expiring' OR n1.type = 'mou_expiring_soon')
        ");
        
        return true;
    } catch (Exception $e) {
        error_log('Error cleaning up duplicate notifications: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check for MOU/MOA expiring soon and create notifications
 * Creates two types of notifications:
 * 1. Initial notification based on "MOU/MOA Expiration Notifications" (default: 180 days)
 * 2. Second reminder based on "Days Before Expiry Date" (default: 30 days)
 */
function checkMouExpiration($pdo, $userId = null) {
    try {
        // Clean up any existing duplicate notifications first
        cleanupDuplicateNotifications($pdo);
        
        $today = date('Y-m-d');
        $todayDate = new DateTime();
        $todayDate->setTime(0, 0, 0);
        
        // Get both notification periods
        // 1. Initial notification period (default: 180 days / 6 months)
        $initialNotificationPeriodDays = getMaximumNotificationPeriod($pdo);
        $initialNotificationDate = date('Y-m-d', strtotime("+{$initialNotificationPeriodDays} days"));
        
        // 2. Days before expiry reminder (default: 30 days)
        $daysBeforeExpiry = getMaximumDaysBeforeExpiry($pdo);
        $reminderNotificationDate = date('Y-m-d', strtotime("+{$daysBeforeExpiry} days"));
        
        // Get all MOU/MOA entries that are:
        // 1. Expired (end_date < today) - check ALL expired MOUs, not just recent ones
        // 2. Expiring within either notification period (initial or reminder)
        // 3. Invalid/zero dates (0000-00-00) should be treated as expired
        // 4. CRITICAL: Always include MOUs expiring in 3 days or less, regardless of settings
        // Use the maximum of both periods to get all relevant MOUs, but ensure we catch urgent ones
        $maxPeriod = max($initialNotificationPeriodDays, $daysBeforeExpiry, 3); // At least 3 days to catch urgent expirations
        $maxNotificationDate = date('Y-m-d', strtotime("+{$maxPeriod} days"));
        $urgentDate = date('Y-m-d', strtotime("+3 days")); // Always check 3 days ahead for urgent expirations
        
        $sql = "SELECT id, title, institution, partner, end_date, user_id, status 
                FROM mou_moa 
                WHERE end_date IS NOT NULL 
                AND deleted_at IS NULL
                AND (
                    end_date = '0000-00-00' OR
                    end_date = '' OR
                    end_date < ? OR 
                    (end_date >= ? AND end_date <= ?) OR
                    -- CRITICAL: Always catch MOUs expiring in 3 days or less
                    (end_date >= ? AND end_date <= ?)
                )";
        
        $params = [$today, $today, $maxNotificationDate, $today, $urgentDate];
        
        // If user_id is provided, filter by user
        if ($userId) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $mous = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $notificationsCreated = 0;
        
        foreach ($mous as $mou) {
            // Handle invalid/zero dates - treat as expired
            if (empty($mou['end_date']) || $mou['end_date'] == '0000-00-00' || $mou['end_date'] == '') {
                // Treat zero/invalid dates as expired
                $daysUntilExpiry = -1;
                $type = 'mou_expired';
            } else {
                // Calculate days until expiration (signed: negative = expired, positive = days remaining)
                try {
                    $endDate = new DateTime($mou['end_date']);
                    $endDate->setTime(0, 0, 0);
                    $diff = $todayDate->diff($endDate);
                    $daysUntilExpiry = (int)$diff->format('%r%a'); // Signed integer: positive = future, negative = past
                } catch (Exception $e) {
                    // Invalid date format - treat as expired
                    error_log('Invalid end_date for MOU ' . $mou['id'] . ': ' . $mou['end_date']);
                    $daysUntilExpiry = -1; // Treat as expired
                }
            }
            
            $mouName = $mou['title'] ?: $mou['institution'] ?: $mou['partner'] ?: 'Untitled';
            
            // Handle expired MOUs first
            if ($daysUntilExpiry < 0 || $daysUntilExpiry == 0) {
                // Check if expired notification already exists (use COUNT to be more robust)
                $checkStmt = $pdo->prepare("
                    SELECT COUNT(*) as count FROM notifications 
                    WHERE related_id = ? 
                    AND related_type = 'mou_moa' 
                    AND type = 'mou_expired'
                    AND user_id IS NULL
                ");
                $checkStmt->execute([$mou['id']]);
                $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
                $existingCount = (int)($result['count'] ?? 0);
                
                if ($existingCount == 0) {
                    $title = "MOU/MOA Expired: " . $mouName;
                    // Handle invalid dates
                    if (empty($mou['end_date']) || $mou['end_date'] == '0000-00-00' || $mou['end_date'] == '') {
                        $message = "The MOU/MOA with " . ($mou['institution'] ?: $mou['partner'] ?: 'partner') . " has an invalid expiration date.";
                    } else {
                        $dateTimestamp = strtotime($mou['end_date']);
                        if ($dateTimestamp === false) {
                            $message = "The MOU/MOA with " . ($mou['institution'] ?: $mou['partner'] ?: 'partner') . " has an invalid expiration date.";
                        } else {
                            $message = "The MOU/MOA with " . ($mou['institution'] ?: $mou['partner'] ?: 'partner') . " expired on " . date('F j, Y', $dateTimestamp) . ".";
                        }
                    }
                    
                    $insertStmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, type, title, message, related_id, related_type, created_at)
                        VALUES (NULL, ?, ?, ?, ?, 'mou_moa', NOW())
                    ");
                    $insertStmt->execute(['mou_expired', $title, $message, $mou['id']]);
                    $notificationsCreated++;
                }
                continue; // Skip to next MOU
            }
            
            // For expiring MOUs, create two separate notifications:
            // 1. Initial notification (mou_expiring) - when within initial notification period
            // 2. Reminder notification (mou_expiring_soon) - when within days before expiry period
            
            // CRITICAL: Always notify for MOUs expiring in 3 days or less, regardless of settings
            // This ensures urgent expirations are never missed
            $isUrgentExpiration = $daysUntilExpiry <= 3 && $daysUntilExpiry > 0;
            
            // Check and create initial notification (first notification)
            if ($daysUntilExpiry <= $initialNotificationPeriodDays && $daysUntilExpiry > $daysBeforeExpiry) {
                // Within initial notification period but not yet in reminder period
                // Check if notification already exists (regardless of age to prevent duplicates)
                $checkStmt = $pdo->prepare("
                    SELECT id FROM notifications 
                    WHERE related_id = ? 
                    AND related_type = 'mou_moa' 
                    AND type = 'mou_expiring'
                    AND user_id IS NULL
                ");
                $checkStmt->execute([$mou['id']]);
                $existing = $checkStmt->fetch();
                
                if (!$existing) {
                    $title = "MOU/MOA Expiring Soon: " . $mouName;
                    $dateTimestamp = strtotime($mou['end_date']);
                    $message = "The MOU/MOA with " . ($mou['institution'] ?: $mou['partner'] ?: 'partner') . " will expire in " . $daysUntilExpiry . " day(s) on " . date('F j, Y', $dateTimestamp) . ".";
                    
                    $insertStmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, type, title, message, related_id, related_type, created_at)
                        VALUES (NULL, ?, ?, ?, ?, 'mou_moa', NOW())
                    ");
                    $insertStmt->execute(['mou_expiring', $title, $message, $mou['id']]);
                    $notificationsCreated++;
                }
            }
            
            // Check and create reminder notification (second notification - days before expiry)
            // Also include urgent expirations (3 days or less) even if outside normal reminder period
            if ($daysUntilExpiry <= $daysBeforeExpiry || $isUrgentExpiration) {
                // Within reminder notification period
                // IMPORTANT: Delete any existing mou_expiring notification for this MOU
                // since mou_expiring_soon is more urgent and should replace it
                $deleteExpiringStmt = $pdo->prepare("
                    DELETE FROM notifications 
                    WHERE related_id = ? 
                    AND related_type = 'mou_moa' 
                    AND type = 'mou_expiring'
                    AND user_id IS NULL
                ");
                $deleteExpiringStmt->execute([$mou['id']]);
                
                // For critical expirations (3 days or less), always create/update notification
                // For others, check if notification already exists (regardless of age to prevent duplicates)
                $isCritical = $daysUntilExpiry <= 3;
                
                if ($isCritical) {
                    // For critical expirations, delete old mou_expiring_soon notification and create fresh one
                    // This ensures users always see the updated countdown
                    $deleteStmt = $pdo->prepare("
                        DELETE FROM notifications 
                        WHERE related_id = ? 
                        AND related_type = 'mou_moa' 
                        AND type = 'mou_expiring_soon'
                        AND user_id IS NULL
                    ");
                    $deleteStmt->execute([$mou['id']]);
                } else {
                    // For non-critical, check if notification already exists (regardless of age to prevent duplicates)
                    $checkStmt = $pdo->prepare("
                        SELECT id FROM notifications 
                        WHERE related_id = ? 
                        AND related_type = 'mou_moa' 
                        AND type = 'mou_expiring_soon'
                        AND user_id IS NULL
                    ");
                    $checkStmt->execute([$mou['id']]);
                    $existing = $checkStmt->fetch();
                    
                    if ($existing) {
                        continue; // Skip creating duplicate notification
                    }
                }
                
                // Create the notification
                $title = "MOU/MOA Expiring Soon - Reminder: " . $mouName;
                $dateTimestamp = strtotime($mou['end_date']);
                $message = "REMINDER: The MOU/MOA with " . ($mou['institution'] ?: $mou['partner'] ?: 'partner') . " will expire in " . $daysUntilExpiry . " day(s) on " . date('F j, Y', $dateTimestamp) . ". Please renew soon.";
                
                try {
                    $insertStmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, type, title, message, related_id, related_type, created_at)
                        VALUES (NULL, ?, ?, ?, ?, 'mou_moa', NOW())
                    ");
                    $insertStmt->execute(['mou_expiring_soon', $title, $message, $mou['id']]);
                    $notificationsCreated++;
                    error_log("Created MOU expiring_soon notification for MOU ID: " . $mou['id'] . ", days until expiry: " . $daysUntilExpiry);
                } catch (Exception $e) {
                    error_log('Error creating MOU expiring_soon notification for MOU ' . $mou['id'] . ': ' . $e->getMessage());
                }
            }
        }
        
        return $notificationsCreated;
    } catch (Exception $e) {
        error_log('Error checking MOU expiration: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Check for upcoming events and create notifications
 */
function checkUpcomingEvents($pdo, $userId = null) {
    try {
        // Get events happening within 7 days
        $sevenDaysFromNow = date('Y-m-d', strtotime('+7 days'));
        $today = date('Y-m-d');
        
        $sql = "SELECT id, title, event_date, start_time, location, user_id, status 
                FROM events 
                WHERE event_date IS NOT NULL 
                AND event_date >= ? 
                AND event_date <= ? 
                AND status IN ('planned', 'ongoing')
                AND deleted_at IS NULL";
        
        $params = [$today, $sevenDaysFromNow];
        
        // If user_id is provided, filter by user
        if ($userId) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $upcomingEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $notificationsCreated = 0;
        
        foreach ($upcomingEvents as $event) {
            // Calculate days until event (signed: negative = past, positive = days remaining)
            $eventDate = new DateTime($event['event_date']);
            $eventDate->setTime(0, 0, 0); // Set to start of day
            $todayDate = new DateTime();
            $todayDate->setTime(0, 0, 0); // Set to start of day
            $diff = $todayDate->diff($eventDate);
            $daysUntilEvent = (int)$diff->format('%r%a'); // Signed integer: positive = future, negative = past
            
            // Determine notification type
            $type = 'event_upcoming';
            if ($daysUntilEvent == 0) {
                $type = 'event_today';
            }
            
            // Check if notification already exists for this event (global notification with user_id = NULL)
            // Check within last 7 days to allow updates if event status changes
            $checkStmt = $pdo->prepare("
                SELECT id FROM notifications 
                WHERE related_id = ? 
                AND related_type = 'event' 
                AND type = ? 
                AND user_id IS NULL
                AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $checkStmt->execute([$event['id'], $type]);
            $existing = $checkStmt->fetch();
            
            if (!$existing) {
                // Create notification
                $title = $daysUntilEvent == 0
                    ? "Event Today: " . $event['title']
                    : "Upcoming Event: " . $event['title'];
                
                $timeStr = $event['start_time'] ? " at " . date('g:i A', strtotime($event['start_time'])) : "";
                $locationStr = $event['location'] ? " at " . $event['location'] : "";
                
                $message = $daysUntilEvent == 0
                    ? "The event '" . $event['title'] . "' is happening today" . $timeStr . $locationStr . "."
                    : "The event '" . $event['title'] . "' is happening in " . $daysUntilEvent . " day(s) on " . date('F j, Y', strtotime($event['event_date'])) . $timeStr . $locationStr . ".";
                
                // Create notification with user_id = NULL so it's visible to all users
                // This makes event notifications visible on all pages
                $insertStmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, title, message, related_id, related_type, created_at)
                    VALUES (NULL, ?, ?, ?, ?, 'event', NOW())
                ");
                $insertStmt->execute([
                    $type,
                    $title,
                    $message,
                    $event['id']
                ]);
                $notificationsCreated++;
            }
        }
        
        return $notificationsCreated;
    } catch (Exception $e) {
        error_log('Error checking upcoming events: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Check for upcoming schedules and create notifications
 */
function checkUpcomingSchedules($pdo, $userId = null) {
    try {
        // Get schedules happening within 30 days (extended from 7 to catch more schedules)
        // This ensures notifications are created for schedules further in the future
        $thirtyDaysFromNow = date('Y-m-d', strtotime('+30 days'));
        $today = date('Y-m-d');
        
        $sql = "SELECT id, title, scheduled_date, scheduled_time, user_id, status 
                FROM schedules 
                WHERE scheduled_date IS NOT NULL 
                AND scheduled_date >= ? 
                AND scheduled_date <= ? 
                AND status = 'scheduled'";
        
        $params = [$today, $thirtyDaysFromNow];
        
        // If user_id is provided, filter by user
        if ($userId) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $upcomingSchedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log('Schedule notification check: Found ' . count($upcomingSchedules) . ' upcoming schedule(s)');
        
        $notificationsCreated = 0;
        
        foreach ($upcomingSchedules as $schedule) {
            // Calculate days until schedule (signed: negative = past, positive = days remaining)
            $scheduleDate = new DateTime($schedule['scheduled_date']);
            $scheduleDate->setTime(0, 0, 0); // Set to start of day
            $todayDate = new DateTime();
            $todayDate->setTime(0, 0, 0); // Set to start of day
            $diff = $todayDate->diff($scheduleDate);
            $daysUntilSchedule = (int)$diff->format('%r%a'); // Signed integer: positive = future, negative = past
            
            // Determine notification type (reuse event types for consistency)
            $type = 'event_upcoming';
            if ($daysUntilSchedule == 0) {
                $type = 'event_today';
            }
            
            // Check if notification already exists for this schedule (global notification with user_id = NULL)
            // Don't check date restriction - if schedule exists and is upcoming, create/update notification
            $checkStmt = $pdo->prepare("
                SELECT id FROM notifications 
                WHERE related_id = ? 
                AND related_type = 'schedule' 
                AND type = ? 
                AND user_id IS NULL
            ");
            $checkStmt->execute([$schedule['id'], $type]);
            $existing = $checkStmt->fetch();
            
            if (!$existing) {
                // No notification exists - create one
                // Create notification
                $title = $daysUntilSchedule == 0
                    ? "Schedule Today: " . $schedule['title']
                    : "Upcoming Schedule: " . $schedule['title'];
                
                $timeStr = $schedule['scheduled_time'] ? " at " . date('g:i A', strtotime($schedule['scheduled_time'])) : "";
                
                $message = $daysUntilSchedule == 0
                    ? "The schedule '" . $schedule['title'] . "' is happening today" . $timeStr . "."
                    : "The schedule '" . $schedule['title'] . "' is happening in " . $daysUntilSchedule . " day(s) on " . date('F j, Y', strtotime($schedule['scheduled_date'])) . $timeStr . ".";
                
                // Create notification with user_id = NULL so it's visible to all users
                // This makes schedule notifications visible on all pages
                try {
                    $insertStmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, type, title, message, related_id, related_type, created_at)
                        VALUES (NULL, ?, ?, ?, ?, 'schedule', NOW())
                    ");
                    $insertStmt->execute([
                        $type,
                        $title,
                        $message,
                        $schedule['id']
                    ]);
                    $notificationsCreated++;
                    error_log("Created schedule notification for schedule ID: " . $schedule['id'] . ", title: " . $schedule['title'] . ", days until: " . $daysUntilSchedule);
                } catch (Exception $e) {
                    error_log('Error creating schedule notification for schedule ' . $schedule['id'] . ': ' . $e->getMessage());
                }
            } else {
                error_log("Schedule notification already exists for schedule ID: " . $schedule['id'] . ", type: " . $type);
            }
        }
        
        return $notificationsCreated;
    } catch (Exception $e) {
        error_log('Error checking upcoming schedules: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Get notifications for current user
 */
function getNotifications($pdo, $userId, $unreadOnly = false) {
    try {
        // Get user's notification preference
        $userNotificationPeriod = getUserMouNotificationPeriod($pdo, $userId);
        
        // For global notifications (user_id IS NULL), check notification_reads table
        // For user-specific notifications, use the is_read field
        // Exclude MOU notifications that have been confirmed by this user
        $sql = "SELECT n.*, 
                       CASE 
                           WHEN n.related_type = 'mou_moa' THEN m.title
                           WHEN n.related_type = 'event' THEN e.title
                           WHEN n.related_type = 'schedule' THEN s.title
                           ELSE NULL
                       END as related_title,
                       CASE 
                           WHEN n.user_id IS NULL THEN 
                               CASE WHEN nr.id IS NOT NULL THEN 1 ELSE 0 END
                           ELSE n.is_read
                       END as is_read_for_user,
                       nc.renewal_status as mou_renewal_status,
                       CASE WHEN nc.id IS NOT NULL THEN 1 ELSE 0 END as is_confirmed,
                       m.end_date as mou_end_date,
                       m.status as mou_status,
                       m.institution as mou_institution,
                       m.partner as mou_partner
                FROM notifications n
                LEFT JOIN mou_moa m ON n.related_type = 'mou_moa' AND n.related_id = m.id
                LEFT JOIN events e ON n.related_type = 'event' AND n.related_id = e.id
                LEFT JOIN schedules s ON n.related_type = 'schedule' AND n.related_id = s.id
                LEFT JOIN notification_reads nr ON n.id = nr.notification_id AND nr.user_id = ? AND n.user_id IS NULL
                LEFT JOIN notification_confirmations nc ON n.id = nc.notification_id AND nc.user_id = ? AND n.related_type = 'mou_moa'
                WHERE (n.user_id = ? OR n.user_id IS NULL)
                AND (
                    n.related_type IS NULL
                    OR (n.related_type = 'mou_moa' AND m.id IS NOT NULL AND (m.deleted_at IS NULL OR m.deleted_at = ''))
                    OR (n.related_type = 'event' AND e.id IS NOT NULL)
                    OR (n.related_type = 'schedule' AND s.id IS NOT NULL)
                )
                AND (
                    n.related_type != 'mou_moa' 
                    OR n.related_type IS NULL
                    OR (
                        -- For expired MOUs: always show (they're already expired)
                        (n.type = 'mou_expired' AND (nc.id IS NULL OR nc.renewal_status != 'renewed'))
                        OR
                        -- For expiring MOUs: only show if within user's notification period and not confirmed as renewed
                        (n.type = 'mou_expiring' 
                         AND (nc.id IS NULL OR nc.renewal_status = 'not_renewed')
                         AND m.end_date IS NOT NULL 
                         AND m.end_date != '' 
                         AND m.end_date != '0000-00-00'
                         AND DATEDIFF(m.end_date, CURDATE()) <= ?)
                        OR
                        -- For reminder notifications (mou_expiring_soon): always show if not confirmed as renewed
                        (n.type = 'mou_expiring_soon' 
                         AND (nc.id IS NULL OR nc.renewal_status = 'not_renewed')
                         AND m.end_date IS NOT NULL 
                         AND m.end_date != '' 
                         AND m.end_date != '0000-00-00')
                    )
                )";
        
        $params = [$userId, $userId, $userId, $userNotificationPeriod];
        
        if ($unreadOnly) {
            $sql .= " AND (
                (n.user_id IS NULL AND nr.id IS NULL) OR 
                (n.user_id IS NOT NULL AND n.is_read = 0)
            )";
        }
        
        $sql .= " GROUP BY n.id ORDER BY n.created_at DESC LIMIT 50";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Additional deduplication: Remove any duplicates by notification ID
        $seenIds = [];
        $deduplicatedNotifications = [];
        foreach ($notifications as $notif) {
            if (!in_array($notif['id'], $seenIds)) {
                $seenIds[] = $notif['id'];
                $deduplicatedNotifications[] = $notif;
            }
        }
        $notifications = $deduplicatedNotifications;
        
        // Format dates and use is_read_for_user
        // Also filter MOU expiring notifications based on user preference (additional check for edge cases)
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        
        foreach ($notifications as &$notif) {
            $notif['created_at'] = date('Y-m-d H:i:s', strtotime($notif['created_at']));
            $notif['read_at'] = $notif['read_at'] ? date('Y-m-d H:i:s', strtotime($notif['read_at'])) : null;
            $notif['is_read'] = (bool)($notif['is_read_for_user'] ?? $notif['is_read']);
            $notif['is_confirmed'] = (bool)($notif['is_confirmed'] ?? false);
            $notif['mou_renewal_status'] = $notif['mou_renewal_status'] ?? null;
            // Calculate criticality for MOU notifications
            if ($notif['related_type'] === 'mou_moa' && $notif['mou_end_date']) {
                $endDate = new DateTime($notif['mou_end_date']);
                $endDate->setTime(0, 0, 0);
                $daysDiff = (int)$today->diff($endDate)->format('%r%a');
                $notif['mou_days_until_expiry'] = $daysDiff;
                $notif['mou_is_expired'] = $daysDiff < 0;
            }
            unset($notif['is_read_for_user']); // Remove helper field
        }
        
        // Additional filtering: Remove expiring MOU notifications outside user's preference period
        $filteredNotifications = [];
        foreach ($notifications as $notif) {
            if ($notif['related_type'] === 'mou_moa' && $notif['type'] === 'mou_expiring') {
                // Check if this expiring notification is within user's preference period
                if (isset($notif['mou_days_until_expiry']) && $notif['mou_days_until_expiry'] > $userNotificationPeriod) {
                    // Skip this notification - it's outside the user's preference period
                    continue;
                }
            }
            $filteredNotifications[] = $notif;
        }
        
        return $filteredNotifications;
    } catch (Exception $e) {
        error_log('Error getting notifications: ' . $e->getMessage());
        return [];
    }
}

/**
 * Mark notification as read
 */
function markAsRead($pdo, $notificationId, $userId) {
    try {
        // First check if this is a global notification (user_id IS NULL)
        $checkStmt = $pdo->prepare("SELECT user_id FROM notifications WHERE id = ?");
        $checkStmt->execute([$notificationId]);
        $notification = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$notification) {
            return false;
        }
        
        if ($notification['user_id'] === null) {
            // Global notification - use notification_reads table for per-user tracking
            $insertStmt = $pdo->prepare("
                INSERT INTO notification_reads (notification_id, user_id, read_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE read_at = NOW()
            ");
            $insertStmt->execute([$notificationId, $userId]);
            return $insertStmt->rowCount() > 0;
        } else {
            // User-specific notification - update is_read field
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = 1, read_at = NOW() 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$notificationId, $userId]);
            return $stmt->rowCount() > 0;
        }
    } catch (Exception $e) {
        error_log('Error marking notification as read: ' . $e->getMessage());
        return false;
    }
}

/**
 * Mark all notifications as read
 */
function markAllAsRead($pdo, $userId) {
    try {
        // Mark user-specific notifications as read
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = NOW() 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$userId]);
        $userSpecificCount = $stmt->rowCount();
        
        // Mark all global notifications as read for this user (using notification_reads)
        $globalStmt = $pdo->prepare("
            INSERT INTO notification_reads (notification_id, user_id, read_at)
            SELECT n.id, ?, NOW()
            FROM notifications n
            WHERE n.user_id IS NULL
            AND n.id NOT IN (SELECT notification_id FROM notification_reads WHERE user_id = ?)
            ON DUPLICATE KEY UPDATE read_at = NOW()
        ");
        $globalStmt->execute([$userId, $userId]);
        $globalCount = $globalStmt->rowCount();
        
        return $userSpecificCount + $globalCount;
    } catch (Exception $e) {
        error_log('Error marking all notifications as read: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Delete notification
 */
function deleteNotification($pdo, $notificationId, $userId) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM notifications 
            WHERE id = ? AND (user_id = ? OR user_id IS NULL)
        ");
        $stmt->execute([$notificationId, $userId]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log('Error deleting notification: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get unread notification count
 */
function getUnreadCount($pdo, $userId) {
    try {
        // Get actual unread notifications using the same function that displays them
        // This ensures the count exactly matches what's shown in the modal
        // getNotifications with unreadOnly=true already filters to only unread notifications
        $notifications = getNotifications($pdo, $userId, true);
        
        // Return the count - getNotifications with unreadOnly=true already filters out read ones
        return count($notifications);
    } catch (Exception $e) {
        error_log('Error getting unread count: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Confirm MOU renewal status
 */
function confirmMouRenewal($pdo, $notificationId, $userId, $renewalStatus) {
    try {
        // Verify notification exists and is an MOU notification
        $checkStmt = $pdo->prepare("
            SELECT id, related_type 
            FROM notifications 
            WHERE id = ? AND related_type = 'mou_moa'
        ");
        $checkStmt->execute([$notificationId]);
        $notification = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$notification) {
            return false;
        }
        
        // Insert or update confirmation
        $stmt = $pdo->prepare("
            INSERT INTO notification_confirmations (notification_id, user_id, renewal_status, confirmed_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                renewal_status = VALUES(renewal_status),
                confirmed_at = NOW()
        ");
        $stmt->execute([$notificationId, $userId, $renewalStatus]);
        
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log('Error confirming MOU renewal: ' . $e->getMessage());
        return false;
    }
}

// Main request handler
try {
    $pdo = db();

    // SQLite / file fallback: full notification logic uses MySQL-only SQL (e.g. DATEDIFF). Avoid 500s.
    if ($pdo instanceof FileBasedDatabase || ($pdo instanceof PDO && isPdoSqlite($pdo))) {
        requireAuth();
        $methodEarly = $_SERVER['REQUEST_METHOD'];
        $actionEarly = $_GET['action'] ?? '';
        sendNotificationsDegradedResponse($methodEarly, $actionEarly);
    }
    
    // Create notifications table if it doesn't exist (MySQL)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) DEFAULT NULL,
            type ENUM('mou_expiring','mou_expiring_soon','mou_expired','event_upcoming','event_today','system') DEFAULT 'system',
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            related_id INT(11) DEFAULT NULL,
            related_type VARCHAR(50) DEFAULT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            read_at TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_is_read (is_read),
            KEY idx_created_at (created_at),
            KEY idx_type (type),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Create notification_reads table for per-user read tracking of global notifications
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS notification_reads (
                id INT(11) NOT NULL AUTO_INCREMENT,
                notification_id INT(11) NOT NULL,
                user_id INT(11) NOT NULL,
                read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY unique_user_notification (notification_id, user_id),
                KEY idx_notification_id (notification_id),
                KEY idx_user_id (user_id),
                FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Exception $e) {
        // Table might already exist with different structure, try to alter it
        error_log('Error creating notification_reads table: ' . $e->getMessage());
    }
    
    // Create notification_confirmations table for MOU renewal confirmations
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS notification_confirmations (
                id INT(11) NOT NULL AUTO_INCREMENT,
                notification_id INT(11) NOT NULL,
                user_id INT(11) NOT NULL,
                renewal_status ENUM('renewed', 'not_renewed') NOT NULL,
                confirmed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY unique_user_notification_confirmation (notification_id, user_id),
                KEY idx_notification_id (notification_id),
                KEY idx_user_id (user_id),
                FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Exception $e) {
        error_log('Error creating notification_confirmations table: ' . $e->getMessage());
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    $userId = requireAuth();
    
    switch ($method) {
        case 'GET':
            if ($action === 'check') {
                // Check and create notifications for MOU expiration and upcoming events
                // Pass null to check ALL MOU/MOA entries (not filtered by user)
                // This ensures notifications are visible on all pages
                
                try {
                    // Also migrate any existing user-specific MOU/Event/Schedule notifications to global (user_id = NULL)
                    // This ensures old notifications become visible to all users
                    try {
                        $migrateStmt = $pdo->prepare("
                            UPDATE notifications 
                            SET user_id = NULL 
                            WHERE related_type IN ('mou_moa', 'event', 'schedule') 
                            AND user_id IS NOT NULL
                            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        ");
                        $migrateStmt->execute();
                    } catch (Exception $e) {
                        error_log('Error migrating notifications: ' . $e->getMessage());
                    }
                    
                    $mouCount = 0;
                    $eventCount = 0;
                    $scheduleCount = 0;
                    
                    try {
                        $mouCount = checkMouExpiration($pdo, null);
                    } catch (Exception $e) {
                        error_log('Error checking MOU expiration: ' . $e->getMessage());
                    }
                    
                    try {
                        $eventCount = checkUpcomingEvents($pdo, null);
                    } catch (Exception $e) {
                        error_log('Error checking upcoming events: ' . $e->getMessage());
                    }
                    
                    try {
                        $scheduleCount = checkUpcomingSchedules($pdo, null);
                    } catch (Exception $e) {
                        error_log('Error checking upcoming schedules: ' . $e->getMessage());
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'mou_notifications_created' => $mouCount,
                        'event_notifications_created' => $eventCount,
                        'schedule_notifications_created' => $scheduleCount,
                        'total_created' => $mouCount + $eventCount + $scheduleCount
                    ]);
                } catch (Exception $e) {
                    error_log('Error in notification check: ' . $e->getMessage());
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Failed to check notifications: ' . $e->getMessage()
                    ]);
                }
            } elseif ($action === 'count') {
                // Clean up duplicates before getting count
                cleanupDuplicateNotifications($pdo);
                
                // Get unread notification count
                $count = getUnreadCount($pdo, $userId);
                echo json_encode(['count' => $count]);
            } else {
                // Clean up duplicates before retrieving notifications
                cleanupDuplicateNotifications($pdo);
                
                // Get notifications
                $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
                $notifications = getNotifications($pdo, $userId, $unreadOnly);
                echo json_encode(['notifications' => $notifications, 'count' => count($notifications)]);
            }
            break;
            
        case 'PUT':
            $data = jsonInput();
            $notificationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            
            if ($notificationId > 0) {
                // Mark single notification as read
                $success = markAsRead($pdo, $notificationId, $userId);
                echo json_encode(['success' => $success]);
            } elseif (isset($data['action']) && $data['action'] === 'mark_all_read') {
                // Mark all as read
                $count = markAllAsRead($pdo, $userId);
                echo json_encode(['success' => true, 'count' => $count]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid request']);
            }
            break;
            
        case 'POST':
            $data = jsonInput();
            
            if (isset($data['action']) && $data['action'] === 'confirm_mou_renewal') {
                $notificationId = isset($data['notification_id']) ? (int)$data['notification_id'] : 0;
                $renewalStatus = isset($data['renewal_status']) ? $data['renewal_status'] : '';
                
                if ($notificationId > 0 && in_array($renewalStatus, ['renewed', 'not_renewed'])) {
                    $success = confirmMouRenewal($pdo, $notificationId, $userId, $renewalStatus);
                    echo json_encode(['success' => $success]);
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid request parameters']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
            }
            break;
            
        case 'DELETE':
            $notificationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($notificationId > 0) {
                $success = deleteNotification($pdo, $notificationId, $userId);
                echo json_encode(['success' => $success]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Notification ID required']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

