<?php
header('Content-Type: application/json');
session_start();

// Authentication check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No image uploaded or upload error']);
    exit();
}

$file = $_FILES['image'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

// Check MIME type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

if (!in_array($mimeType, $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Only images allowed.']);
    exit();
}

// Validate size (e.g. 5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'File too large. Max 5MB.']);
    exit();
}

// Create directory
$uploadDir = __DIR__ . '/../assets/uploads/event-images/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Generate unique name
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid('evt_') . '_' . time() . '.' . $extension;
$targetPath = $uploadDir . $filename;
$relativePath = 'assets/uploads/event-images/' . $filename;

if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    echo json_encode(['success' => true, 'image_path' => $relativePath]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to save file']);
}
?>


