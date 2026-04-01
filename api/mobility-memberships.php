<?php
/**
 * Institutional Memberships API for Mobility Programs
 * - Create
 * - List (for the logged-in user)
 * - Delete (soft delete)
 */

if (!ob_get_level()) {
    ob_start();
}

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config.php';

// Auth
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login.']);
    exit();
}

$userId = (int)$_SESSION['user_id'];

// DB
try {
    $pdo = getDatabaseConnection();
    if ($pdo instanceof FileBasedDatabase) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'MySQL database required for memberships']);
        exit();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Ensure table exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS institutional_memberships (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            org_name VARCHAR(255) NOT NULL,
            membership_type ENUM('International','Local') NOT NULL,
            membership_status ENUM('Annual','Lifetime') NOT NULL,
            membership_year INT(11) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
            updated_at TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            deleted_at DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_deleted_at (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed creating memberships table: ' . $e->getMessage()]);
    exit();
}

function jsonSuccess($data = []) {
    return array_merge(['success' => true], $data);
}

function normalizeMembershipType($v) {
    $v = trim((string)$v);
    if ($v === 'International') return 'International';
    if ($v === 'Local') return 'Local';
    return '';
}

function normalizeMembershipStatus($v) {
    $v = trim((string)$v);
    if ($v === 'Annual') return 'Annual';
    if ($v === 'Lifetime') return 'Lifetime';
    return '';
}

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        // List
        $stmt = $pdo->prepare("
            SELECT id, org_name, membership_type, membership_status, membership_year, created_at
            FROM institutional_memberships
            WHERE user_id = ? AND deleted_at IS NULL
            ORDER BY created_at DESC, id DESC
        ");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(jsonSuccess(['data' => $rows]));
        exit();
    }

    if ($method === 'POST') {
        $orgName = isset($_POST['org_name']) ? trim((string)$_POST['org_name']) : '';
        $type = isset($_POST['membership_type']) ? normalizeMembershipType($_POST['membership_type']) : '';
        $status = isset($_POST['membership_status']) ? normalizeMembershipStatus($_POST['membership_status']) : '';
        $year = isset($_POST['membership_year']) ? (int)$_POST['membership_year'] : 0;

        if ($orgName === '') {
            throw new Exception('Organization name is required');
        }
        if ($type === '') {
            throw new Exception('Membership type is required');
        }
        if ($status === '') {
            throw new Exception('Membership status is required');
        }
        if ($year <= 0) {
            throw new Exception('Membership year is required');
        }

        $stmt = $pdo->prepare("
            INSERT INTO institutional_memberships
                (user_id, org_name, membership_type, membership_status, membership_year, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$userId, $orgName, $type, $status, $year]);
        $newId = (int)$pdo->lastInsertId();

        echo json_encode(jsonSuccess(['id' => $newId, 'message' => 'Membership saved successfully']));
        exit();
    }

    if ($method === 'DELETE') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            throw new Exception('Membership id is required');
        }

        $stmt = $pdo->prepare("
            UPDATE institutional_memberships
            SET deleted_at = CURRENT_TIMESTAMP
            WHERE id = ? AND user_id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$id, $userId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(jsonSuccess(['message' => 'Membership deleted successfully']));
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Membership not found']);
        }
        exit();
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit();
}

