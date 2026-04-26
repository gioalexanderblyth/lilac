<?php
/**
 * International Awards table API for Mobility Programs
 * - Ensures table exists
 * - Seeds default indicators per user (all years start at 0)
 * - Lists summary records for the logged-in user
 * - Lists detail rows (imported from excel) per indicator
 */

if (!ob_get_level()) {
    ob_start();
}

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

function ensureInternationalAwardsTable($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mobility_international_awards (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            indicator_name VARCHAR(255) NOT NULL,
            color_token VARCHAR(30) NOT NULL DEFAULT 'violet',
            y2019 INT(11) NOT NULL DEFAULT 0,
            y2020 INT(11) NOT NULL DEFAULT 0,
            y2021 INT(11) NOT NULL DEFAULT 0,
            y2022 INT(11) NOT NULL DEFAULT 0,
            y2023 INT(11) NOT NULL DEFAULT 0,
            y2024 INT(11) NOT NULL DEFAULT 0,
            y2025_proj INT(11) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_user_id_deleted (user_id, deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

function ensureInternationalAwardsDetailsTable($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mobility_international_awards_items (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            indicator_name VARCHAR(255) NOT NULL,
            entry_title VARCHAR(255) NOT NULL,
            entry_source VARCHAR(255) DEFAULT NULL,
            entry_year INT(11) DEFAULT NULL,
            entry_rank VARCHAR(255) DEFAULT NULL,
            deleted_at DATETIME NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_indicator_deleted (user_id, indicator_name, deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

function parseNullableYearValue($value) {
    if ($value === null) return null;
    $text = trim((string)$value);
    if ($text === '') return null;
    if (!preg_match('/^\d{4}$/', $text)) return null;
    $year = (int)$text;
    if ($year < 1900 || $year > 2100) return null;
    return $year;
}

function seedDefaultRowsForUser($pdo, $userId) {
    $defaults = [
        ['Global Excellence Citations', 'violet'],
        ['Sustainable Campus Awards', 'blue'],
        ['QS World Ranking Recognition', 'orange'],
        ['International Accreditation', 'emerald'],
        ['Innovation Leadership Awards', 'pink']
    ];

    $countStmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM mobility_international_awards
        WHERE user_id = ? AND deleted_at IS NULL
    ");
    $countStmt->execute([$userId]);
    $countRow = $countStmt->fetch(PDO::FETCH_ASSOC);
    $existingCount = isset($countRow['total']) ? (int)$countRow['total'] : 0;

    if ($existingCount > 0) {
        return;
    }

    $insertStmt = $pdo->prepare("
        INSERT INTO mobility_international_awards
            (user_id, indicator_name, color_token, y2019, y2020, y2021, y2022, y2023, y2024, y2025_proj)
        VALUES
            (?, ?, ?, 0, 0, 0, 0, 0, 0, 0)
    ");

    foreach ($defaults as $row) {
        $insertStmt->execute([$userId, $row[0], $row[1]]);
    }
}

try {
    $userId = (int)$_SESSION['user_id'];
    $action = isset($_GET['action']) ? trim((string)$_GET['action']) : 'list';
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'MySQL database required for international awards']);
        exit();
    }

    ensureInternationalAwardsTable($pdo);
    ensureInternationalAwardsDetailsTable($pdo);

    if ($action === 'details') {
        $indicator = isset($_GET['indicator']) ? trim((string)$_GET['indicator']) : '';
        if ($indicator === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Indicator is required']);
            exit();
        }

        $stmt = $pdo->prepare("
            SELECT id, indicator_name, entry_title, entry_source, entry_year, entry_rank
            FROM mobility_international_awards_items
            WHERE user_id = ? AND indicator_name = ? AND deleted_at IS NULL
            ORDER BY
                CASE WHEN entry_year IS NULL THEN 1 ELSE 0 END ASC,
                entry_year DESC,
                id ASC
        ");
        $stmt->execute([$userId, $indicator]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $rows]);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_detail') {
        $indicator = isset($_POST['indicator_name']) ? trim((string)$_POST['indicator_name']) : '';
        $title = isset($_POST['entry_title']) ? trim((string)$_POST['entry_title']) : '';
        $source = isset($_POST['entry_source']) ? trim((string)$_POST['entry_source']) : '';
        $rank = isset($_POST['entry_rank']) ? trim((string)$_POST['entry_rank']) : '';
        $year = parseNullableYearValue($_POST['entry_year'] ?? null);

        if ($indicator === '' || $title === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Indicator and title are required']);
            exit();
        }

        $sourceValue = $source !== '' ? $source : null;
        $rankValue = $rank !== '' ? $rank : null;
        $insert = $pdo->prepare("
            INSERT INTO mobility_international_awards_items
                (user_id, indicator_name, entry_title, entry_source, entry_year, entry_rank)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([$userId, $indicator, $title, $sourceValue, $year, $rankValue]);

        echo json_encode([
            'success' => true,
            'message' => 'Award detail record saved',
            'id' => (int)$pdo->lastInsertId()
        ]);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_detail') {
        $detailId = isset($_POST['detail_id']) ? (int)$_POST['detail_id'] : 0;
        $indicator = isset($_POST['indicator_name']) ? trim((string)$_POST['indicator_name']) : '';
        $title = isset($_POST['entry_title']) ? trim((string)$_POST['entry_title']) : '';
        $source = isset($_POST['entry_source']) ? trim((string)$_POST['entry_source']) : '';
        $rank = isset($_POST['entry_rank']) ? trim((string)$_POST['entry_rank']) : '';
        $year = parseNullableYearValue($_POST['entry_year'] ?? null);

        if ($detailId <= 0 || $indicator === '' || $title === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Detail id, indicator, and title are required']);
            exit();
        }

        $sourceValue = $source !== '' ? $source : null;
        $rankValue = $rank !== '' ? $rank : null;
        $update = $pdo->prepare("
            UPDATE mobility_international_awards_items
            SET indicator_name = ?, entry_title = ?, entry_source = ?, entry_year = ?, entry_rank = ?
            WHERE id = ? AND user_id = ? AND deleted_at IS NULL
        ");
        $update->execute([$indicator, $title, $sourceValue, $year, $rankValue, $detailId, $userId]);

        if ($update->rowCount() === 0) {
            $exists = $pdo->prepare("
                SELECT id
                FROM mobility_international_awards_items
                WHERE id = ? AND user_id = ? AND deleted_at IS NULL
                LIMIT 1
            ");
            $exists->execute([$detailId, $userId]);
            if (!$exists->fetch(PDO::FETCH_ASSOC)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Record not found']);
                exit();
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Award detail record updated'
        ]);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete_detail') {
        $detailId = isset($_POST['detail_id']) ? (int)$_POST['detail_id'] : 0;
        if ($detailId <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Detail id is required']);
            exit();
        }

        $delete = $pdo->prepare("
            UPDATE mobility_international_awards_items
            SET deleted_at = NOW()
            WHERE id = ? AND user_id = ? AND deleted_at IS NULL
        ");
        $delete->execute([$detailId, $userId]);
        if ($delete->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Record not found']);
            exit();
        }

        echo json_encode([
            'success' => true,
            'message' => 'Award detail record deleted'
        ]);
        exit();
    }

    seedDefaultRowsForUser($pdo, $userId);

    $stmt = $pdo->prepare("
        SELECT
            m.id,
            m.indicator_name,
            m.color_token,
            COALESCE(a.y2019, m.y2019, 0) AS y2019,
            COALESCE(a.y2020, m.y2020, 0) AS y2020,
            COALESCE(a.y2021, m.y2021, 0) AS y2021,
            COALESCE(a.y2022, m.y2022, 0) AS y2022,
            COALESCE(a.y2023, m.y2023, 0) AS y2023,
            COALESCE(a.y2024, m.y2024, 0) AS y2024,
            COALESCE(a.y2025_proj, m.y2025_proj, 0) AS y2025_proj
        FROM mobility_international_awards m
        LEFT JOIN (
            SELECT
                indicator_name,
                SUM(CASE WHEN entry_year = 2019 THEN 1 ELSE 0 END) AS y2019,
                SUM(CASE WHEN entry_year = 2020 THEN 1 ELSE 0 END) AS y2020,
                SUM(CASE WHEN entry_year = 2021 THEN 1 ELSE 0 END) AS y2021,
                SUM(CASE WHEN entry_year = 2022 THEN 1 ELSE 0 END) AS y2022,
                SUM(CASE WHEN entry_year = 2023 THEN 1 ELSE 0 END) AS y2023,
                SUM(CASE WHEN entry_year = 2024 THEN 1 ELSE 0 END) AS y2024,
                SUM(CASE WHEN entry_year = 2025 THEN 1 ELSE 0 END) AS y2025_proj
            FROM mobility_international_awards_items
            WHERE user_id = ? AND deleted_at IS NULL
            GROUP BY indicator_name
        ) a
            ON a.indicator_name = m.indicator_name
        WHERE m.user_id = ? AND m.deleted_at IS NULL
        ORDER BY m.id ASC
    ");
    $stmt->execute([$userId, $userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $rows
    ]);
    exit();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load international awards: ' . $e->getMessage()
    ]);
    exit();
}

