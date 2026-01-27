<?php
/**
 * Audio File Upload API
 * Handles audio file uploads for the system
 */

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config.php';

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login.']);
    exit();
}

$userId = $_SESSION['user_id'];

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check if file was uploaded
        if (!isset($_FILES['audio_file']) || $_FILES['audio_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No file uploaded or upload error occurred');
        }

        $file = $_FILES['audio_file'];

        // Validate file type (audio files)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        // Also check by extension as fallback
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['mp3', 'wav', 'ogg', 'webm', 'm4a', 'aac', 'flac'];
        
        $isValidMime = in_array($mimeType, ALLOWED_AUDIO_TYPES);
        $isValidExt = in_array($extension, $allowedExtensions);

        if (!$isValidMime && !$isValidExt) {
            throw new Exception('Invalid file type. Allowed audio formats: MP3, WAV, OGG, WebM, M4A, AAC, FLAC');
        }

        // Validate file size (max 50MB for audio)
        if ($file['size'] > MAX_AUDIO_SIZE) {
            throw new Exception('File size exceeds maximum limit of ' . (MAX_AUDIO_SIZE / 1024 / 1024) . 'MB');
        }

        // Create upload directory if it doesn't exist
        $uploadDir = __DIR__ . '/../uploads/audio/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $fileName = 'audio_' . $userId . '_' . time() . '_' . uniqid() . '.' . $extension;
        $filePath = $uploadDir . $fileName;
        $relativePath = 'uploads/audio/' . $fileName;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Failed to save uploaded file');
        }

        // Log successful upload
        if (function_exists('logActivity')) {
            logActivity("Audio file uploaded successfully by user ID: {$userId} - {$fileName}", 'INFO');
        }

        echo json_encode([
            'success' => true,
            'message' => 'Audio file uploaded successfully',
            'file_name' => $fileName,
            'file_path' => $relativePath,
            'file_size' => $file['size'],
            'mime_type' => $mimeType
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        
        // Log the error for debugging
        error_log('Audio upload error for user ' . $userId . ': ' . $e->getMessage());
        if (function_exists('logActivity')) {
            logActivity("Audio upload failed for user ID: {$userId} - " . $e->getMessage(), 'ERROR');
        }
        
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>

