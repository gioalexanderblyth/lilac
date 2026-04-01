<?php
/**
 * User Preferences API
 * Handles saving and loading user dashboard preferences
 */

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/schema-helpers.php';

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login.']);
    exit();
}

$userId = $_SESSION['user_id'];

// Get database connection
try {
    $pdo = getDatabaseConnection();

    // File-based fallback: no DB — return defaults so the UI does not 500 (notifications.js treats null as enabled)
    if ($pdo instanceof FileBasedDatabase) {
        http_response_code(200);
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $key = $_GET['key'] ?? null;
            if ($key) {
                echo json_encode(['success' => true, 'value' => null]);
            } else {
                echo json_encode(['success' => true, 'preferences' => []]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Saving preferences requires MySQL/SQLite.']);
        }
        exit();
    }

    ensureUserPreferencesTable($pdo);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Handle different HTTP methods
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Get user preferences
            $preferenceKey = $_GET['key'] ?? null;
            
            if ($preferenceKey) {
                // Get specific preference
                $stmt = $pdo->prepare('SELECT preference_value FROM user_preferences WHERE user_id = ? AND preference_key = ?');
                $stmt->execute([$userId, $preferenceKey]);
                $pref = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($pref) {
                    echo json_encode([
                        'success' => true,
                        'value' => $pref['preference_value']
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'value' => null
                    ]);
                }
            } else {
                // Get all preferences for user
                $stmt = $pdo->prepare('SELECT preference_key, preference_value FROM user_preferences WHERE user_id = ?');
                $stmt->execute([$userId]);
                $prefs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $preferences = [];
                foreach ($prefs as $pref) {
                    $preferences[$pref['preference_key']] = $pref['preference_value'];
                }
                
                echo json_encode([
                    'success' => true,
                    'preferences' => $preferences
                ]);
            }
            break;

        case 'POST':
        case 'PUT':
            // Save user preference
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['key']) || !isset($input['value'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing key or value']);
                exit();
            }
            
            $preferenceKey = $input['key'];
            $preferenceValue = $input['value'];
            
            // Insert or update preference (MySQL vs SQLite upsert)
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $stmt = $pdo->prepare('
                    INSERT INTO user_preferences (user_id, preference_key, preference_value)
                    VALUES (?, ?, ?)
                    ON CONFLICT(user_id, preference_key) DO UPDATE SET
                        preference_value = excluded.preference_value,
                        updated_at = CURRENT_TIMESTAMP
                ');
            } else {
                $stmt = $pdo->prepare('
                    INSERT INTO user_preferences (user_id, preference_key, preference_value)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE preference_value = VALUES(preference_value), updated_at = CURRENT_TIMESTAMP
                ');
            }
            $stmt->execute([$userId, $preferenceKey, $preferenceValue]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Preference saved successfully'
            ]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

