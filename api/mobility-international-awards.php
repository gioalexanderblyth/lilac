<?php
/**
 * International Awards table API for Mobility Programs
 * - Ensures table exists
 * - Seeds default indicators per user (all years start at 0)
 * - Lists records for the logged-in user
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
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'MySQL database required for international awards']);
        exit();
    }

    ensureInternationalAwardsTable($pdo);
    seedDefaultRowsForUser($pdo, $userId);

    $stmt = $pdo->prepare("
        SELECT id, indicator_name, color_token, y2019, y2020, y2021, y2022, y2023, y2024, y2025_proj
        FROM mobility_international_awards
        WHERE user_id = ? AND deleted_at IS NULL
        ORDER BY id ASC
    ");
    $stmt->execute([$userId]);
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

