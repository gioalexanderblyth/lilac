<?php
/**
 * Admin Logo Upload Page
 * Simple interface for admins to upload/update the CPU logo
 */
session_start();

// Check authentication and admin role
if (!isset($_SESSION['user_id']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/api/config.php';

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logo'])) {
    try {
        $pdo = getDatabaseConnection();
        
        if ($pdo instanceof FileBasedDatabase) {
            $message = 'Database not available. Please configure database connection.';
            $messageType = 'error';
        } else {
            // Validate file
            $file = $_FILES['logo'];
            
            if ($file['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/svg+xml'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                
                if (in_array($mimeType, $allowedTypes) && $file['size'] <= 5 * 1024 * 1024) {
                    $fileContent = file_get_contents($file['tmp_name']);
                    $fileName = $file['name'];
                    
                    // Check if logo exists
                    $stmt = $pdo->prepare('SELECT id FROM site_settings WHERE setting_key = ?');
                    $stmt->execute(['cpu_logo']);
                    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existing) {
                        $stmt = $pdo->prepare('UPDATE site_settings SET setting_value = ?, mime_type = ?, file_name = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?');
                        $stmt->execute([$fileContent, $mimeType, $fileName, 'cpu_logo']);
                    } else {
                        $stmt = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value, mime_type, file_name, description) VALUES (?, ?, ?, ?, ?)');
                        $stmt->execute(['cpu_logo', $fileContent, $mimeType, $fileName, 'CPU LILAC Logo']);
                    }
                    
                    logActivity('Logo ' . ($existing ? 'updated' : 'uploaded') . ' by admin', 'INFO');
                    $message = 'Logo uploaded successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Invalid file type or file too large. Only images (PNG, JPEG, GIF, SVG) up to 5MB are allowed.';
                    $messageType = 'error';
                }
            } else {
                $message = 'File upload error occurred.';
                $messageType = 'error';
            }
        }
    } catch (Exception $e) {
        error_log('Error uploading logo: ' . $e->getMessage());
        $message = 'Failed to upload logo: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get current logo info
$currentLogo = null;
try {
    $pdo = getDatabaseConnection();
    if (!($pdo instanceof FileBasedDatabase)) {
        $stmt = $pdo->prepare('SELECT file_name, mime_type, updated_at FROM site_settings WHERE setting_key = ?');
        $stmt->execute(['cpu_logo']);
        $currentLogo = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Ignore errors
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Logo - LILAC Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white shadow-lg rounded-lg p-8">
                <div class="flex items-center justify-between mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Upload CPU Logo</h1>
                    <a href="dashboard.php" class="text-blue-600 hover:text-blue-800 flex items-center gap-2">
                        <span class="material-symbols-outlined">arrow_back</span>
                        Back to Dashboard
                    </a>
                </div>
                
                <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($currentLogo): ?>
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <p class="text-sm text-gray-600 mb-2">Current Logo:</p>
                    <p class="text-sm font-medium"><?php echo htmlspecialchars($currentLogo['file_name']); ?></p>
                    <p class="text-xs text-gray-500">Last updated: <?php echo date('Y-m-d H:i:s', strtotime($currentLogo['updated_at'])); ?></p>
                    <div class="mt-4">
                        <img src="api/get-logo.php" alt="Current Logo" class="max-w-xs h-auto border border-gray-300 rounded">
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <div>
                        <label for="logo" class="block text-sm font-medium text-gray-700 mb-2">
                            Select Logo Image
                        </label>
                        <input type="file" 
                               id="logo" 
                               name="logo" 
                               accept="image/png,image/jpeg,image/jpg,image/gif,image/svg+xml"
                               required
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="mt-2 text-xs text-gray-500">
                            Supported formats: PNG, JPEG, GIF, SVG. Maximum file size: 5MB
                        </p>
                    </div>
                    
                    <div class="flex gap-4">
                        <button type="submit" 
                                class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                            Upload Logo
                        </button>
                        <a href="dashboard.php" 
                           class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors font-medium text-gray-700">
                            Cancel
                        </a>
                    </div>
                </form>
                
                <div class="mt-8 p-4 bg-blue-50 rounded-lg">
                    <h3 class="text-sm font-semibold text-blue-900 mb-2">Instructions:</h3>
                    <ul class="text-sm text-blue-800 space-y-1 list-disc list-inside">
                        <li>Upload a high-quality logo image (recommended: PNG with transparent background)</li>
                        <li>The logo will be stored in the database and served across all pages</li>
                        <li>If no logo is uploaded, the system will fallback to the file-based logo</li>
                        <li>After uploading, the logo will appear on all pages immediately</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

