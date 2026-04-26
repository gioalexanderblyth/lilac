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
    $userId = (int)$_SESSION['user_id'];
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

    $tableColumnsCache = [];

    $getTableColumns = function (string $table) use ($pdo, &$tableColumnsCache): array {
        if (isset($tableColumnsCache[$table])) {
            return $tableColumnsCache[$table];
        }
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            $cols = [];
            foreach ($rows as $row) {
                if (!empty($row['Field'])) {
                    $cols[] = (string)$row['Field'];
                }
            }
            $tableColumnsCache[$table] = $cols;
            return $cols;
        } catch (Exception $e) {
            $tableColumnsCache[$table] = [];
            return [];
        }
    };

    $buildWhereClause = function (array $cols, array $extraConditions = []) use ($userId): array {
        $where = [];
        $params = [];
        if (in_array('deleted_at', $cols, true)) {
            $where[] = "deleted_at IS NULL";
        }
        if (in_array('user_id', $cols, true)) {
            $where[] = "user_id = :user_id";
            $params[':user_id'] = $userId;
        }
        foreach ($extraConditions as $cond => $paramValue) {
            $where[] = $cond;
            if (is_array($paramValue)) {
                foreach ($paramValue as $k => $v) {
                    $params[$k] = $v;
                }
            }
        }
        if (empty($where)) {
            $where[] = "1=1";
        }
        return ['sql' => implode(' AND ', $where), 'params' => $params];
    };

    $countRows = function (string $table, array $extraConditions = []) use ($pdo, $getTableColumns, $buildWhereClause): int {
        $cols = $getTableColumns($table);
        if (empty($cols)) return 0;
        $where = $buildWhereClause($cols, $extraConditions);
        $sql = "SELECT COUNT(*) AS total FROM `{$table}` WHERE {$where['sql']}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($where['params']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return isset($row['total']) ? max(0, (int)$row['total']) : 0;
    };

    $sumRows = function (string $table, string $column, array $extraConditions = []) use ($pdo, $getTableColumns, $buildWhereClause): int {
        $cols = $getTableColumns($table);
        if (empty($cols) || !in_array($column, $cols, true)) return 0;
        $where = $buildWhereClause($cols, $extraConditions);
        $sql = "SELECT COALESCE(SUM(`{$column}`), 0) AS total FROM `{$table}` WHERE {$where['sql']}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($where['params']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return isset($row['total']) ? max(0, (int)$row['total']) : 0;
    };

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

    // Live computed metrics from mobility records (per logged-in user when user_id exists in table)
    $result['international_awards'] = $countRows('mobility_international_awards_items');
    $result['active_partnerships'] = $countRows('mou_moa');
    $result['outgoing_exchange_students'] = $countRows('student_mobility_records', [
        "LOWER(COALESCE(scope, '')) = 'outbound'" => []
    ]);
    $result['incoming_exchange_students'] = $countRows('student_mobility_records', [
        "LOWER(COALESCE(scope, '')) = 'inbound'" => []
    ]);
    $result['international_faculty_experts'] = $countRows('full_time_foreign_faculty');
    $result['joint_degree_programs'] = $countRows('transnational_education_programs');
    $result['transnational_centers'] = $countRows('international_sustainability_centers');
    $result['international_internships'] = $countRows('student_mobility_records', [
        "(LOWER(COALESCE(program_name, '')) LIKE '%intern%' OR LOWER(COALESCE(activity_modality, '')) LIKE '%intern%')" => []
    ]);
    $result['research_grants'] = $countRows('internationalization_research_records');
    $result['scholarship_slots'] =
        $sumRows('international_scholarships', 'faculty_staff_count') +
        $sumRows('studyph_program_records', 'beneficiaries_qty');
    $result['asean_event_attendees'] =
        $sumRows('inhouse_asean_events', 'participants') +
        $sumRows('collaborative_events_activities', 'participants');

    // Compute target met % from StudyPH KPI values (e.g. "85%")
    try {
        $studyphCols = $getTableColumns('studyph_program_records');
        if (!empty($studyphCols) && in_array('kpi_value', $studyphCols, true)) {
            $where = $buildWhereClause($studyphCols, [
                "COALESCE(kpi_value, '') <> ''" => []
            ]);
            $stmt = $pdo->prepare("SELECT kpi_value FROM `studyph_program_records` WHERE {$where['sql']}");
            $stmt->execute($where['params']);
            $values = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $sum = 0.0;
            $count = 0;
            foreach ($values as $raw) {
                if (!is_string($raw) && !is_numeric($raw)) continue;
                $text = trim((string)$raw);
                if ($text === '') continue;
                if (preg_match('/-?\d+(?:\.\d+)?/', $text, $m)) {
                    $n = (float)$m[0];
                    if ($n < 0) $n = 0;
                    if ($n > 100) $n = 100;
                    $sum += $n;
                    $count++;
                }
            }
            if ($count > 0) {
                $result['internationalization_target_met_percent'] = (int)round($sum / $count);
            }
        }
    } catch (Exception $e) {
        // Keep fallback value from counters table if dynamic computation fails.
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

