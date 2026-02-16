<?php
/**
 * List Audio Files API
 * Returns a list of audio files available in the assets/audio directory
 */

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $audioDir = __DIR__ . '/../assets/audio/';
    $audioFiles = [];
    
    // Check if directory exists
    if (!is_dir($audioDir)) {
        echo json_encode([
            'success' => true,
            'files' => []
        ]);
        exit();
    }
    
    // Get all audio files from the directory
    $allowedExtensions = ['wav', 'mp3', 'ogg', 'webm', 'm4a', 'aac', 'flac'];
    $files = scandir($audioDir);
    
    foreach ($files as $file) {
        // Skip directories and hidden files
        if ($file === '.' || $file === '..' || is_dir($audioDir . $file)) {
            continue;
        }
        
        // Check if file has valid audio extension
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($extension, $allowedExtensions)) {
            $audioFiles[] = [
                'filename' => $file,
                'path' => 'assets/audio/' . $file,
                'extension' => $extension
            ];
        }
    }
    
    // Sort files alphabetically by filename
    usort($audioFiles, function($a, $b) {
        return strcmp($a['filename'], $b['filename']);
    });
    
    echo json_encode([
        'success' => true,
        'files' => $audioFiles
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to list audio files: ' . $e->getMessage()
    ]);
}
?>
