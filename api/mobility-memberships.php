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
            membership_status VARCHAR(100) NULL DEFAULT NULL,
            membership_year INT(11) NULL DEFAULT NULL,
            membership_year_end INT(11) NULL DEFAULT NULL,
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

// Ensure legacy tables can also store custom statuses.
try {
    $pdo->exec("ALTER TABLE institutional_memberships MODIFY COLUMN membership_status VARCHAR(100) NULL DEFAULT NULL");
} catch (Exception $_) {
    // Best effort only; keep API operational even if ALTER is not needed or denied.
}
try {
    $pdo->exec("ALTER TABLE institutional_memberships MODIFY COLUMN membership_year INT(11) NULL DEFAULT NULL");
} catch (Exception $_) {
    // Best effort only; keep API operational even if ALTER is not needed or denied.
}
try {
    $pdo->exec("ALTER TABLE institutional_memberships ADD COLUMN membership_year_end INT(11) NULL DEFAULT NULL AFTER membership_year");
} catch (Exception $_) {
    // Best effort only; keep API operational even if ALTER is not needed or denied.
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
    if ($v === '') return null;
    $lower = strtolower($v);
    if ($lower === 'annual') return 'Annual';
    if ($lower === 'lifetime') return 'Lifetime';
    if ($lower === 'autonomous' || $lower === 'autonomous status') return 'Autonomous';
    if (strlen($v) > 100) {
        throw new Exception('Status is too long (max 100 characters)');
    }
    return $v;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        if (isset($_GET['action']) && $_GET['action'] === 'restore') {
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id <= 0) {
                throw new Exception('Membership id is required');
            }

            $stmt = $pdo->prepare("
                UPDATE institutional_memberships
                SET deleted_at = NULL, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND user_id = ? AND deleted_at IS NOT NULL
            ");
            $stmt->execute([$id, $userId]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(jsonSuccess(['message' => 'Membership restored successfully']));
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Membership not found in trash']);
            }
            exit();
        }

        // List
        $stmt = $pdo->prepare("
            SELECT id, org_name, membership_type, membership_status, membership_year, membership_year_end, created_at
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
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $orgName = isset($_POST['org_name']) ? trim((string)$_POST['org_name']) : '';
        $type = isset($_POST['membership_type']) ? normalizeMembershipType($_POST['membership_type']) : '';
        $status = isset($_POST['membership_status']) ? normalizeMembershipStatus($_POST['membership_status']) : null;
        $yearRaw = isset($_POST['membership_year']) ? trim((string)$_POST['membership_year']) : '';
        $year = ($yearRaw === '') ? null : (int)$yearRaw;
        $yearEndRaw = isset($_POST['membership_year_end']) ? trim((string)$_POST['membership_year_end']) : '';
        $yearEnd = ($yearEndRaw === '') ? null : (int)$yearEndRaw;

        if ($orgName === '') {
            throw new Exception('Organization name is required');
        }
        if ($type === '') {
            throw new Exception('Membership type is required');
        }
        if ($year !== null && $year <= 0) {
            throw new Exception('Membership year must be a valid year');
        }
        if ($yearEnd !== null) {
            if ($yearEnd <= 0) {
                throw new Exception('Membership end year must be a valid year');
            }
            if ($year === null) {
                throw new Exception('Membership start year is required when end year is provided');
            }
            if ($yearEnd < $year) {
                throw new Exception('Membership end year cannot be earlier than start year');
            }
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE institutional_memberships
                SET org_name = ?, membership_type = ?, membership_status = ?, membership_year = ?, membership_year_end = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND user_id = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$orgName, $type, $status, $year, $yearEnd, $id, $userId]);
            if ($stmt->rowCount() <= 0) {
                // MySQL can return 0 affected rows when values are unchanged.
                $existsStmt = $pdo->prepare("
                    SELECT id FROM institutional_memberships
                    WHERE id = ? AND user_id = ? AND deleted_at IS NULL
                    LIMIT 1
                ");
                $existsStmt->execute([$id, $userId]);
                $exists = $existsStmt->fetch(PDO::FETCH_ASSOC);
                if (!$exists) {
                    throw new Exception('Membership not found');
                }
            }
            echo json_encode(jsonSuccess(['id' => $id, 'message' => 'Membership updated successfully']));
            exit();
        }

        $stmt = $pdo->prepare("
            INSERT INTO institutional_memberships
                (user_id, org_name, membership_type, membership_status, membership_year, membership_year_end, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$userId, $orgName, $type, $status, $year, $yearEnd]);
        $newId = (int)$pdo->lastInsertId();

        echo json_encode(jsonSuccess(['id' => $newId, 'message' => 'Membership saved successfully']));
        exit();
    }

    if ($method === 'DELETE') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $permanent = isset($_GET['permanent']) && $_GET['permanent'] === 'true';
        if ($id <= 0) {
            throw new Exception('Membership id is required');
        }

        if ($permanent) {
            $stmt = $pdo->prepare("
                DELETE FROM institutional_memberships
                WHERE id = ? AND user_id = ? AND deleted_at IS NOT NULL
            ");
            $stmt->execute([$id, $userId]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE institutional_memberships
                SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND user_id = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$id, $userId]);
        }

        if ($stmt->rowCount() > 0) {
            echo json_encode(jsonSuccess([
                'message' => $permanent ? 'Membership permanently deleted' : 'Membership moved to trash'
            ]));
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

