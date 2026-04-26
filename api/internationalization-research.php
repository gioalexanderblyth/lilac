<?php
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

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login.']);
    exit();
}

$userId = (int) $_SESSION['user_id'];

function parseNullableYear($value) {
    if ($value === null) return null;
    $raw = trim((string) $value);
    if ($raw === '') return null;
    if (!preg_match('/^\d{4}$/', $raw)) return null;
    $year = (int) $raw;
    if ($year < 1900 || $year > 2100) return null;
    return $year;
}

function normalizeCategory($value) {
    $v = strtolower(trim((string) $value));
    if (in_array($v, ['collaborative', 'sole', 'published', 'citations'], true)) return $v;
    return 'collaborative';
}

function normalizeStatus($value) {
    return strtolower(trim((string) $value)) === 'completed' ? 'completed' : 'ongoing';
}

function normalizePublishedStatus($value) {
    return strtolower(trim((string) $value)) === 'published' ? 'published' : 'not-published';
}

try {
    $pdo = getDatabaseConnection();
    if ($pdo instanceof FileBasedDatabase) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'MySQL database required for this endpoint']);
        exit();
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS internationalization_research_records (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            category VARCHAR(30) NOT NULL DEFAULT 'collaborative',
            faculty_name VARCHAR(255) NOT NULL,
            fiscal_year INT(11) DEFAULT NULL,
            research_title TEXT NOT NULL,
            partner_agency VARCHAR(255) DEFAULT NULL,
            partner_country VARCHAR(120) DEFAULT NULL,
            project_status VARCHAR(20) NOT NULL DEFAULT 'ongoing',
            published_status VARCHAR(30) NOT NULL DEFAULT 'not-published',
            sdg_focus VARCHAR(255) DEFAULT NULL,
            deleted_at DATETIME NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_deleted_at (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';
        if ($action === 'restore') {
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            if ($id <= 0) throw new Exception('ID is required');
            $stmt = $pdo->prepare("UPDATE internationalization_research_records SET deleted_at = NULL WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Record restored']);
            } else {
                throw new Exception('Record not found');
            }
            exit();
        }

        $stmt = $pdo->query("
            SELECT id, category, faculty_name, fiscal_year, research_title, partner_agency, partner_country, project_status, published_status, sdg_focus, created_at, updated_at
            FROM internationalization_research_records
            WHERE deleted_at IS NULL
            ORDER BY created_at DESC
        ");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit();
    }

    if ($method === 'POST') {
        $action = $_GET['action'] ?? '';

        $category = normalizeCategory($_POST['category'] ?? 'collaborative');
        $facultyName = trim((string) ($_POST['faculty_name'] ?? ''));
        $fiscalYear = parseNullableYear($_POST['fiscal_year'] ?? null);
        $researchTitle = trim((string) ($_POST['research_title'] ?? ''));
        $partnerAgency = trim((string) ($_POST['partner_agency'] ?? ''));
        $partnerCountry = trim((string) ($_POST['partner_country'] ?? ''));
        $projectStatus = normalizeStatus($_POST['project_status'] ?? 'ongoing');
        $publishedStatus = normalizePublishedStatus($_POST['published_status'] ?? 'not-published');
        $sdgFocus = trim((string) ($_POST['sdg_focus'] ?? ''));

        if ($facultyName === '' || $researchTitle === '') {
            throw new Exception('Faculty name and research title are required');
        }
        if ($fiscalYear === null && trim((string) ($_POST['fiscal_year'] ?? '')) !== '') {
            throw new Exception('Invalid fiscal year. Use YYYY between 1900 and 2100');
        }

        if ($action === 'update') {
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            if ($id <= 0) throw new Exception('ID is required for update');
            $stmt = $pdo->prepare("
                UPDATE internationalization_research_records
                SET category = ?, faculty_name = ?, fiscal_year = ?, research_title = ?, partner_agency = ?, partner_country = ?, project_status = ?, published_status = ?, sdg_focus = ?, updated_at = NOW()
                WHERE id = ? LIMIT 1
            ");
            $stmt->execute([$category, $facultyName, $fiscalYear, $researchTitle, $partnerAgency !== '' ? $partnerAgency : null, $partnerCountry !== '' ? $partnerCountry : null, $projectStatus, $publishedStatus, $sdgFocus !== '' ? $sdgFocus : null, $id]);
            if ($stmt->rowCount() === 0) {
                $check = $pdo->prepare("SELECT id FROM internationalization_research_records WHERE id = ? LIMIT 1");
                $check->execute([$id]);
                if (!$check->fetchColumn()) throw new Exception('Record not found');
            }
            echo json_encode(['success' => true, 'message' => 'Research record updated successfully']);
            exit();
        }

        $stmt = $pdo->prepare("
            INSERT INTO internationalization_research_records
            (user_id, category, faculty_name, fiscal_year, research_title, partner_agency, partner_country, project_status, published_status, sdg_focus)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $category, $facultyName, $fiscalYear, $researchTitle, $partnerAgency !== '' ? $partnerAgency : null, $partnerCountry !== '' ? $partnerCountry : null, $projectStatus, $publishedStatus, $sdgFocus !== '' ? $sdgFocus : null]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Research record added successfully']);
        exit();
    }

    if ($method === 'DELETE') {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) throw new Exception('ID is required');
        $permanent = isset($_GET['permanent']) && $_GET['permanent'] === 'true';

        if ($permanent) {
            $stmt = $pdo->prepare("DELETE FROM internationalization_research_records WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Record permanently deleted']);
            exit();
        }

        $stmt = $pdo->prepare("UPDATE internationalization_research_records SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Record moved to trash']);
        } else {
            throw new Exception('Record not found');
        }
        exit();
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

