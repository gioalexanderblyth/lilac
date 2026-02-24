<?php
/**
 * Forms Management API
 * Handles upload and listing of printable forms (PDF, DOC, DOCX)
 */

if (!ob_get_level()) ob_start();
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, RESTORE, OPTIONS');
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

try {
    $pdo = getDatabaseConnection();
    if ($pdo instanceof FileBasedDatabase) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'MySQL database required for forms management']);
        exit();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Ensure forms table exists with deleted_at
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `forms` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `title` varchar(255) NOT NULL,
        `file_name` varchar(255) NOT NULL,
        `file_path` varchar(512) NOT NULL,
        `file_size` int(11) DEFAULT NULL,
        `created_by` int(11) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `deleted_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `created_by` (`created_by`)
    )");
    try { $pdo->exec("ALTER TABLE forms ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL"); } catch (PDOException $e) {}
} catch (PDOException $e) {
    error_log('Forms table creation: ' . $e->getMessage());
}

function handleFormUpload($file) {
    $uploadDir = __DIR__ . '/../uploads/forms/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error: ' . $file['error']);
    }
    $maxSize = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $maxSize) {
        throw new Exception('File size exceeds maximum limit of 10MB');
    }
    $allowedTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip', // DOCX/XLSX are ZIP-based; often reported as this on Windows
        'image/jpeg',
        'image/jpg',
        'image/pjpeg',
        'image/png',
        'image/webp',
        'text/plain',
        'application/octet-stream' // Fallback for some Office files
    ];
    $allowedExt = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'webp', 'txt'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) finfo_close($finfo);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mimeOk = in_array($mimeType, $allowedTypes);
    $extOk = in_array($ext, $allowedExt);
    if (!$mimeOk && !$extOk) {
        throw new Exception('Invalid file type. Allowed: PDF, Word, Excel, images (JPG, PNG, WebP), text');
    }
    $filename = uniqid('form_') . '_' . time() . '.' . $ext;
    $filepath = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to save file');
    }
    return [
        'filename' => $filename,
        'filepath' => 'uploads/forms/' . $filename,
        'original_name' => $file['name'],
        'file_size' => $file['size']
    ];
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if (ob_get_level() > 0) ob_clean();

    $action = $_GET['action'] ?? '';

    switch ($method) {
        case 'GET':
            if ($action === 'restore') {
                $id = (int) ($_GET['id'] ?? 0);
                if (!$id) throw new Exception('Form ID required');
                $stmt = $pdo->prepare("UPDATE forms SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL");
                $stmt->execute([$id]);
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Form restored successfully']);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Form not found or already restored']);
                }
                break;
            }
            $stmt = $pdo->prepare("SELECT id, title, file_name, file_path, file_size, created_at FROM forms WHERE (deleted_at IS NULL OR deleted_at = '') ORDER BY created_at DESC");
            $stmt->execute();
            $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $forms]);
            break;

        case 'POST':
            if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
                throw new Exception('No file uploaded');
            }
            $info = handleFormUpload($_FILES['file']);
            $title = trim($_POST['title'] ?? '') ?: $info['original_name'];
            $stmt = $pdo->prepare("INSERT INTO forms (title, file_name, file_path, file_size, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $info['filename'], $info['filepath'], $info['file_size'], $userId]);
            $id = (int) $pdo->lastInsertId();
            echo json_encode([
                'success' => true,
                'message' => 'Form uploaded successfully',
                'data' => [
                    'id' => $id,
                    'title' => $title,
                    'file_name' => $info['filename'],
                    'file_path' => $info['filepath'],
                    'file_size' => $info['file_size'],
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ]);
            break;

        case 'DELETE':
            $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
            $permanent = isset($_GET['permanent']) && $_GET['permanent'] === 'true';
            if (!$id) throw new Exception('Form ID required');
            $stmt = $pdo->prepare("SELECT file_path FROM forms WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Form not found');
            if ($permanent) {
                $pdo->prepare("DELETE FROM forms WHERE id = ?")->execute([$id]);
                $fullPath = __DIR__ . '/../' . $row['file_path'];
                if (file_exists($fullPath)) unlink($fullPath);
                echo json_encode(['success' => true, 'message' => 'Form permanently deleted']);
            } else {
                $pdo->prepare("UPDATE forms SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Form moved to trash']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    if (ob_get_level() > 0) ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
