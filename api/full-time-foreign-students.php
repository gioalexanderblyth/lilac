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

function normalizeLevel($value) {
    return strtolower(trim((string) $value)) === 'graduate' ? 'graduate' : 'undergraduate';
}

try {
    $pdo = getDatabaseConnection();
    if ($pdo instanceof FileBasedDatabase) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'MySQL database required for this endpoint']);
        exit();
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS full_time_foreign_students (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            country_origin VARCHAR(120) NOT NULL,
            students_count INT(11) NOT NULL DEFAULT 1,
            programs TEXT NOT NULL,
            modality VARCHAR(50) NOT NULL DEFAULT 'on-site',
            study_level VARCHAR(30) NOT NULL DEFAULT 'undergraduate',
            start_year INT(11) DEFAULT NULL,
            end_year INT(11) DEFAULT NULL,
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
            $stmt = $pdo->prepare("UPDATE full_time_foreign_students SET deleted_at = NULL WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Record restored']);
            } else {
                throw new Exception('Record not found');
            }
            exit();
        }

        $stmt = $pdo->query("
            SELECT id, country_origin, students_count, programs, modality, study_level, start_year, end_year, created_at, updated_at
            FROM full_time_foreign_students
            WHERE deleted_at IS NULL
            ORDER BY created_at DESC
        ");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit();
    }

    if ($method === 'POST') {
        $action = $_GET['action'] ?? '';

        $country = trim((string) ($_POST['country_origin'] ?? ''));
        $count = isset($_POST['students_count']) ? (int) $_POST['students_count'] : 0;
        $programs = trim((string) ($_POST['programs'] ?? ''));
        $modality = trim((string) ($_POST['modality'] ?? 'on-site'));
        $level = normalizeLevel($_POST['study_level'] ?? 'undergraduate');
        $startYear = parseNullableYear($_POST['start_year'] ?? null);
        $endYear = parseNullableYear($_POST['end_year'] ?? null);

        if ($country === '' || $programs === '') throw new Exception('Country and programs are required');
        if ($count < 1) throw new Exception('Student count must be at least 1');
        if (($startYear === null && trim((string) ($_POST['start_year'] ?? '')) !== '') ||
            ($endYear === null && trim((string) ($_POST['end_year'] ?? '')) !== '')) {
            throw new Exception('Invalid year value. Use YYYY between 1900 and 2100');
        }
        if ($startYear !== null && $endYear !== null && $endYear < $startYear) {
            throw new Exception('End year must be greater than or equal to start year');
        }

        if ($action === 'update') {
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            if ($id <= 0) throw new Exception('ID is required for update');
            $stmt = $pdo->prepare("
                UPDATE full_time_foreign_students
                SET country_origin = ?, students_count = ?, programs = ?, modality = ?, study_level = ?, start_year = ?, end_year = ?, updated_at = NOW()
                WHERE id = ? LIMIT 1
            ");
            $stmt->execute([$country, $count, $programs, $modality, $level, $startYear, $endYear, $id]);
            if ($stmt->rowCount() === 0) {
                $check = $pdo->prepare("SELECT id FROM full_time_foreign_students WHERE id = ? LIMIT 1");
                $check->execute([$id]);
                if (!$check->fetchColumn()) throw new Exception('Record not found');
            }
            echo json_encode(['success' => true, 'message' => 'Record updated successfully']);
            exit();
        }

        $stmt = $pdo->prepare("
            INSERT INTO full_time_foreign_students
            (user_id, country_origin, students_count, programs, modality, study_level, start_year, end_year)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $country, $count, $programs, $modality, $level, $startYear, $endYear]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Record added successfully']);
        exit();
    }

    if ($method === 'DELETE') {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) throw new Exception('ID is required');
        $permanent = isset($_GET['permanent']) && $_GET['permanent'] === 'true';

        if ($permanent) {
            $stmt = $pdo->prepare("DELETE FROM full_time_foreign_students WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Record permanently deleted']);
            exit();
        }

        $stmt = $pdo->prepare("UPDATE full_time_foreign_students SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL LIMIT 1");
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

