<?php
/**
 * Upload/Update CPU Logo in Database
 * This script allows admins to upload and update the logo stored in the database
 */
session_start();

// Check authentication and admin role
if (!isset($_SESSION['user_id']) || (($_SESSION['user'] ?? [])['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized. Admin access required.']);
    exit;
}

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    
    // Check if we're using file-based fallback
    if ($pdo instanceof FileBasedDatabase) {
        http_response_code(503);
        echo json_encode(['error' => 'Database not available. Please configure database connection.']);
        exit;
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'No file uploaded or upload error occurred']);
        exit;
    }
    
    $file = $_FILES['logo'];
    
    // Validate file type
    $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/svg+xml'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file type. Only images (PNG, JPEG, GIF, SVG) are allowed.']);
        exit;
    }
    
    // Validate file size (max 5MB)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['error' => 'File size exceeds maximum allowed size of 5MB']);
        exit;
    }
    
    // Read file content
    $fileContent = file_get_contents($file['tmp_name']);
    $fileName = $file['name'];
    
    // Check if logo already exists
    $stmt = $pdo->prepare('SELECT id FROM site_settings WHERE setting_key = ?');
    $stmt->execute(['cpu_logo']);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Update existing logo
        $stmt = $pdo->prepare('UPDATE site_settings SET setting_value = ?, mime_type = ?, file_name = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?');
        $stmt->execute([$fileContent, $mimeType, $fileName, 'cpu_logo']);
        $message = 'Logo updated successfully';
    } else {
        // Insert new logo
        $stmt = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value, mime_type, file_name, description) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute(['cpu_logo', $fileContent, $mimeType, $fileName, 'CPU LILAC Logo']);
        $message = 'Logo uploaded successfully';
    }
    
    // Log activity
    logActivity('Logo ' . ($existing ? 'updated' : 'uploaded') . ' by admin', 'INFO');
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'file_name' => $fileName,
        'mime_type' => $mimeType,
        'size' => $file['size']
    ]);
    
} catch (Exception $e) {
    error_log('Error uploading logo: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to upload logo: ' . $e->getMessage()]);
}
?>

