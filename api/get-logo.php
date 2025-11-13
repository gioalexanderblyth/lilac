<?php
/**
 * Serve CPU Logo from Database
 * This endpoint retrieves and serves the logo image stored in the database
 */
// Suppress any output before headers
ob_start();

require_once __DIR__ . '/config.php';

// Clear any output
ob_end_clean();

try {
    $pdo = getDatabaseConnection();
    
    // Check if we're using file-based fallback
    if ($pdo instanceof FileBasedDatabase) {
        // Fallback to file system if database is not available
        $logoPath = __DIR__ . '/../assets/images/cpu-logo.png';
        if (file_exists($logoPath)) {
            header('Content-Type: image/png');
            header('Cache-Control: public, max-age=3600');
            readfile($logoPath);
            exit;
        } else {
            http_response_code(404);
            header('Content-Type: text/plain');
            echo 'Logo not found';
            exit;
        }
    }
    
    // Check if table exists
    try {
        $pdo->query('SELECT 1 FROM site_settings LIMIT 1');
    } catch (PDOException $e) {
        // Table doesn't exist, fallback to file
        $logoPath = __DIR__ . '/../assets/images/cpu-logo.png';
        if (file_exists($logoPath)) {
            header('Content-Type: image/png');
            header('Cache-Control: public, max-age=3600');
            readfile($logoPath);
            exit;
        } else {
            http_response_code(404);
            header('Content-Type: text/plain');
            echo 'Logo not found';
            exit;
        }
    }
    
    // Get logo from database
    $stmt = $pdo->prepare('SELECT setting_value, mime_type, file_name FROM site_settings WHERE setting_key = ?');
    $stmt->execute(['cpu_logo']);
    $logo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($logo && $logo['setting_value']) {
        // Set appropriate content type
        $mimeType = $logo['mime_type'] ?: 'image/png';
        header('Content-Type: ' . $mimeType);
        
        // Set cache headers
        header('Cache-Control: public, max-age=31536000'); // Cache for 1 year
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
        
        // Output the image data
        echo $logo['setting_value'];
    } else {
        // Fallback to file system if no logo in database
        $logoPath = __DIR__ . '/../assets/images/cpu-logo.png';
        if (file_exists($logoPath)) {
            header('Content-Type: image/png');
            header('Cache-Control: public, max-age=3600');
            readfile($logoPath);
        } else {
            http_response_code(404);
            header('Content-Type: text/plain');
            echo 'Logo not found';
        }
    }
} catch (Exception $e) {
    error_log('Error serving logo: ' . $e->getMessage());
    
    // Fallback to file system on error
    $logoPath = __DIR__ . '/../assets/images/cpu-logo.png';
    if (file_exists($logoPath)) {
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=3600');
        readfile($logoPath);
    } else {
        http_response_code(500);
        header('Content-Type: text/plain');
        echo 'Error loading logo';
    }
}
?>

