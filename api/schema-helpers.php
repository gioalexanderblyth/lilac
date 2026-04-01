<?php
/**
 * Portable DDL for user_preferences / notifications when MySQL is not used.
 */

function isPdoSqlite(PDO $pdo): bool {
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
}

/**
 * When SQLite or file-based DB is active, notification APIs cannot run full MySQL queries (DATEDIFF, etc.).
 * Return 200 + empty payloads so the bell UI does not spam 500 in the console.
 */
function sendNotificationsDegradedResponse(string $method, string $action): void {
    header('Content-Type: application/json');
    http_response_code(200);

    if ($method === 'GET') {
        if ($action === 'count') {
            echo json_encode(['count' => 0]);
            exit;
        }
        if ($action === 'check') {
            echo json_encode([
                'success' => true,
                'mou_notifications_created' => 0,
                'event_notifications_created' => 0,
                'schedule_notifications_created' => 0,
                'total_created' => 0,
            ]);
            exit;
        }
        echo json_encode(['notifications' => [], 'count' => 0]);
        exit;
    }

    if ($method === 'PUT') {
        echo json_encode(['success' => true]);
        exit;
    }

    if ($method === 'POST' || $method === 'DELETE') {
        echo json_encode(['success' => false, 'error' => 'Notifications require a full MySQL database']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

/**
 * Create user_preferences without MySQL-only FK (SQLite has no users table in fallback DB).
 */
function ensureUserPreferencesTable(PDO $pdo): void {
    if (isPdoSqlite($pdo)) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_preferences (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                preference_key TEXT NOT NULL,
                preference_value TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, preference_key)
            )
        ");
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_preferences (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            preference_key VARCHAR(100) NOT NULL,
            preference_value TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_preference (user_id, preference_key),
            KEY idx_user_id (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}
