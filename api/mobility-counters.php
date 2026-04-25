<?php
/**
 * Mobility Programs counters API
 * - Ensures the counters table exists
 * - Seeds all known counter keys to 0
 * - Returns all counters for UI KPI cards
 */

if (!ob_get_level()) {
    ob_start();
}

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    $pdo = getDatabaseConnection();
    if ($pdo instanceof FileBasedDatabase) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'MySQL database required for mobility counters']);
        exit();
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mobility_program_counters (
            id INT(11) NOT NULL AUTO_INCREMENT,
            metric_key VARCHAR(100) NOT NULL,
            metric_value INT(11) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_metric_key (metric_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $knownMetrics = [
        'international_awards',
        'active_partnerships',
        'outgoing_exchange_students',
        'incoming_exchange_students',
        'international_faculty_experts',
        'internationalization_target_met_percent',
        'joint_degree_programs',
        'transnational_centers',
        'international_internships',
        'research_grants',
        'scholarship_slots',
        'asean_event_attendees',
        'trend_inbound_students_percent',
        'trend_faculty_mobility_percent',
        'trend_global_awards_percent'
    ];

    $insertStmt = $pdo->prepare("
        INSERT INTO mobility_program_counters (metric_key, metric_value)
        VALUES (?, 0)
        ON DUPLICATE KEY UPDATE metric_key = VALUES(metric_key)
    ");

    foreach ($knownMetrics as $metricKey) {
        $insertStmt->execute([$metricKey]);
    }

    $result = array_fill_keys($knownMetrics, 0);
    $rowsStmt = $pdo->query("SELECT metric_key, metric_value FROM mobility_program_counters");
    $rows = $rowsStmt ? $rowsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

    foreach ($rows as $row) {
        $key = (string)($row['metric_key'] ?? '');
        if ($key !== '' && array_key_exists($key, $result)) {
            $value = isset($row['metric_value']) ? (int)$row['metric_value'] : 0;
            $result[$key] = max(0, $value);
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
    exit();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load mobility counters: ' . $e->getMessage()
    ]);
    exit();
}

