<?php
/**
 * Check Logo Status - Verify if logo is stored in database
 * This script helps verify if the logo system is working correctly
 */
session_start();

// Allow access even if not logged in for diagnostic purposes
require_once __DIR__ . '/../api/config.php';

$status = [
    'database_connected' => false,
    'table_exists' => false,
    'logo_in_database' => false,
    'file_fallback_exists' => false,
    'logo_info' => null,
    'errors' => []
];

try {
    $pdo = getDatabaseConnection();
    
    if ($pdo instanceof FileBasedDatabase) {
        $status['errors'][] = 'Using file-based database fallback - MySQL not available';
    } else {
        $status['database_connected'] = true;
        
        // Check if table exists
        try {
            $stmt = $pdo->query('SHOW TABLES LIKE "site_settings"');
            $tableExists = $stmt->rowCount() > 0;
            $status['table_exists'] = $tableExists;
            
            if ($tableExists) {
                // Check if logo exists in database
                $stmt = $pdo->prepare('SELECT file_name, mime_type, LENGTH(setting_value) as file_size, updated_at FROM site_settings WHERE setting_key = ?');
                $stmt->execute(['cpu_logo']);
                $logo = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($logo) {
                    $status['logo_in_database'] = true;
                    $status['logo_info'] = [
                        'file_name' => $logo['file_name'],
                        'mime_type' => $logo['mime_type'],
                        'file_size' => $logo['file_size'],
                        'updated_at' => $logo['updated_at']
                    ];
                }
            } else {
                $status['errors'][] = 'site_settings table does not exist. Please run the SQL migration.';
            }
        } catch (PDOException $e) {
            $status['errors'][] = 'Error checking table: ' . $e->getMessage();
        }
    }
} catch (Exception $e) {
    $status['errors'][] = 'Database connection error: ' . $e->getMessage();
}

// Check file fallback
$logoPath = __DIR__ . '/../assets/images/cpu-logo.png';
$status['file_fallback_exists'] = file_exists($logoPath);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logo Status Check - LILAC</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl mx-auto">
            <div class="bg-white shadow-lg rounded-lg p-8">
                <h1 class="text-2xl font-bold text-gray-900 mb-6">CPU Logo Database Status</h1>
                
                <div class="space-y-4">
                    <!-- Database Connection -->
                    <div class="p-4 rounded-lg <?php echo $status['database_connected'] ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'; ?>">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">Database Connection:</span>
                            <span class="<?php echo $status['database_connected'] ? 'text-green-700' : 'text-red-700'; ?> font-bold">
                                <?php echo $status['database_connected'] ? '✓ Connected' : '✗ Not Connected'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Table Exists -->
                    <div class="p-4 rounded-lg <?php echo $status['table_exists'] ? 'bg-green-50 border border-green-200' : 'bg-yellow-50 border border-yellow-200'; ?>">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">site_settings Table:</span>
                            <span class="<?php echo $status['table_exists'] ? 'text-green-700' : 'text-yellow-700'; ?> font-bold">
                                <?php echo $status['table_exists'] ? '✓ Exists' : '✗ Not Found'; ?>
                            </span>
                        </div>
                        <?php if (!$status['table_exists']): ?>
                        <p class="text-sm text-yellow-800 mt-2">
                            Run the SQL migration: <code class="bg-yellow-100 px-2 py-1 rounded">database/logo.sql</code> or import <code>database/lilac_awards.sql</code>
                        </p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Logo in Database -->
                    <div class="p-4 rounded-lg <?php echo $status['logo_in_database'] ? 'bg-green-50 border border-green-200' : 'bg-yellow-50 border border-yellow-200'; ?>">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">Logo in Database:</span>
                            <span class="<?php echo $status['logo_in_database'] ? 'text-green-700' : 'text-yellow-700'; ?> font-bold">
                                <?php echo $status['logo_in_database'] ? '✓ Stored' : '✗ Not Stored'; ?>
                            </span>
                        </div>
                        <?php if ($status['logo_in_database'] && $status['logo_info']): ?>
                        <div class="mt-3 text-sm">
                            <p><strong>File Name:</strong> <?php echo htmlspecialchars($status['logo_info']['file_name']); ?></p>
                            <p><strong>MIME Type:</strong> <?php echo htmlspecialchars($status['logo_info']['mime_type']); ?></p>
                            <p><strong>File Size:</strong> <?php echo number_format($status['logo_info']['file_size'] / 1024, 2); ?> KB</p>
                            <p><strong>Last Updated:</strong> <?php echo htmlspecialchars($status['logo_info']['updated_at']); ?></p>
                        </div>
                        <?php elseif (!$status['logo_in_database'] && $status['table_exists']): ?>
                        <p class="text-sm text-yellow-800 mt-2">
                            No logo uploaded yet. <a href="admin-logo-upload.php" class="text-blue-600 hover:underline">Upload logo here</a>
                        </p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- File Fallback -->
                    <div class="p-4 rounded-lg <?php echo $status['file_fallback_exists'] ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'; ?>">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">File Fallback (cpu-logo.png):</span>
                            <span class="<?php echo $status['file_fallback_exists'] ? 'text-green-700' : 'text-red-700'; ?> font-bold">
                                <?php echo $status['file_fallback_exists'] ? '✓ Exists' : '✗ Not Found'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Logo Preview -->
                    <div class="p-4 rounded-lg bg-gray-50 border border-gray-200">
                        <h3 class="font-medium mb-3">Logo Preview:</h3>
                        <div class="flex items-center gap-4">
                            <img src="../api/get-logo.php" alt="CPU Logo" class="h-20 w-20 object-contain border border-gray-300 rounded" 
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                            <div style="display: none;" class="text-red-600 text-sm">Logo failed to load</div>
                        </div>
                        <p class="text-xs text-gray-600 mt-2">
                            Current source: <code>./api/get-logo.php</code>
                        </p>
                    </div>
                    
                    <!-- Errors -->
                    <?php if (!empty($status['errors'])): ?>
                    <div class="p-4 rounded-lg bg-red-50 border border-red-200">
                        <h3 class="font-medium text-red-900 mb-2">Errors:</h3>
                        <ul class="list-disc list-inside text-sm text-red-800">
                            <?php foreach ($status['errors'] as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Actions -->
                    <div class="flex gap-4 mt-6">
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user']['role'] === 'admin'): ?>
                        <a href="../admin-logo-upload.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Upload/Update Logo
                        </a>
                        <?php endif; ?>
                        <a href="../dashboard.php" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

