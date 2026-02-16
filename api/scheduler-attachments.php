<?php
/**
 * Scheduler Attachments API
 * Handles authenticated file uploads for scheduler events.
 * Allowed types: PDF, JPG/JPEG/PNG, DOC/DOCX (max 10MB per file).
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

// Authentication check (reuse standard session auth)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/config.php';

try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        http_response_code(503);
        echo json_encode(['success' => false, 'error' => 'Database connection required for attachments']);
        exit();
    }

    // Ensure attachments table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS schedule_attachments (
            id INT(11) NOT NULL AUTO_INCREMENT,
            schedule_id INT(11) NULL,
            user_id INT(11) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) DEFAULT NULL,
            file_size INT(11) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_schedule_id (schedule_id),
            KEY idx_user_id (user_id),
            CONSTRAINT fk_schedule_attachments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $method = $_SERVER['REQUEST_METHOD'];

    // Simple GET to list attachments for a schedule (optional helper)
    if ($method === 'GET') {
        $scheduleId = isset($_GET['schedule_id']) ? (int)$_GET['schedule_id'] : 0;
        if ($scheduleId <= 0) {
            echo json_encode(['success' => false, 'error' => 'schedule_id is required']);
            exit();
        }

        $stmt = $pdo->prepare("
            SELECT id, schedule_id, file_name, original_name, file_path, mime_type, file_size, created_at
            FROM schedule_attachments
            WHERE schedule_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$scheduleId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'attachments' => $rows]);
        exit();
    }

    // Only POST is supported for uploads
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit();
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
        echo json_encode(['success' => false, 'error' => 'No file uploaded']);
        exit();
    }

    $file = $_FILES['file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'Upload error: ' . $file['error']]);
        exit();
    }

    // Validate size (max 10MB)
    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'error' => 'File too large. Max 10MB.']);
        exit();
    }

    // Validate MIME type using finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $allowedTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg',
        'image/png'
    ];

    if (!in_array($mimeType, $allowedTypes, true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Only PDF, DOC/DOCX, JPG/PNG allowed.']);
        exit();
    }

    // Prepare upload directory
    $uploadDir = __DIR__ . '/../uploads/schedule_attachments/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safeExtension = preg_replace('/[^a-zA-Z0-9]/', '', $extension);
    $filename = uniqid('sched_att_') . '_' . time() . ($safeExtension ? '.' . $safeExtension : '');
    $targetPath = $uploadDir . $filename;
    $relativePath = 'uploads/schedule_attachments/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        echo json_encode(['success' => false, 'error' => 'Failed to save file']);
        exit();
    }

    $scheduleId = isset($_POST['schedule_id']) && $_POST['schedule_id'] !== ''
        ? (int)$_POST['schedule_id']
        : null;

    $userId = (int)$_SESSION['user_id'];

    // Store metadata
    $stmt = $pdo->prepare("
        INSERT INTO schedule_attachments (
            schedule_id, user_id, file_name, original_name, file_path, mime_type, file_size
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $scheduleId,
        $userId,
        $filename,
        $file['name'],
        $relativePath,
        $mimeType,
        (int)$file['size']
    ]);

    $id = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'attachment' => [
            'id' => (int)$id,
            'schedule_id' => $scheduleId,
            'file_name' => $filename,
            'original_name' => $file['name'],
            'file_path' => $relativePath,
            'mime_type' => $mimeType,
            'file_size' => (int)$file['size']
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

?>

