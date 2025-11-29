<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow DELETE requests
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

session_start();

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login.']);
    exit();
}

require_once 'config.php';

try {
    // Get the award ID from query parameters
    $awardId = $_GET['id'] ?? '';
    
    if (empty($awardId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Award ID is required']);
        exit();
    }
    
    $pdo = getDatabaseConnection();
    
    // Check if using database or file-based storage
    if (!($pdo instanceof FileBasedDatabase)) {
        // Database-based deletion
        $awardIdInt = (int)$awardId;
        
        // Check if permanent delete is requested
        $permanent = isset($_GET['permanent']) && $_GET['permanent'] === 'true';
        
        // First, get the award details
        $stmt = $pdo->prepare('SELECT file_path, file_name, created_at FROM awards WHERE id = ?');
        $stmt->execute([$awardIdInt]);
        $award = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$award) {
            http_response_code(404);
            echo json_encode(['error' => 'Award not found']);
            exit();
        }
        
        if ($permanent) {
            // Permanent delete - remove files and database records
            $deletedFiles = [];

            // 1. Find and delete linked OTHER DOCUMENTS (Documents Page)
            $stmtDocs = $pdo->prepare("SELECT id, title, file_path, created_at FROM other_documents WHERE award_id = ?");
            $stmtDocs->execute([$awardIdInt]);
            $linkedDocs = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

            foreach ($linkedDocs as $doc) {
                // Delete physical file from Documents folder
                if ($doc['file_path'] && file_exists(__DIR__ . '/../' . $doc['file_path'])) {
                    if (unlink(__DIR__ . '/../' . $doc['file_path'])) {
                        $deletedFiles[] = basename($doc['file_path']);
                    }
                }

                // Delete the record
                $stmtDel = $pdo->prepare("DELETE FROM other_documents WHERE id = ?");
                $stmtDel->execute([$doc['id']]);

                // CASCADE: Delete linked MOU/MOA entries if they match (auto-copied ones)
                if ($doc['title']) {
                    // Find potential MOU/MOA duplicate created around the same time
                    $stmtMou = $pdo->prepare("
                        SELECT id, file_path FROM mou_moa 
                        WHERE title = ? AND ABS(UNIX_TIMESTAMP(created_at) - UNIX_TIMESTAMP(?)) < 60
                    ");
                    $stmtMou->execute([$doc['title'], $doc['created_at']]);
                    $linkedMou = $stmtMou->fetch(PDO::FETCH_ASSOC);

                    if ($linkedMou) {
                        // Delete MOU file
                        if ($linkedMou['file_path'] && file_exists(__DIR__ . '/../' . $linkedMou['file_path'])) {
                            unlink(__DIR__ . '/../' . $linkedMou['file_path']);
                        }
                        // Delete MOU record
                        $pdo->prepare("DELETE FROM mou_moa WHERE id = ?")->execute([$linkedMou['id']]);
                    }
                }
            }
            
            // Delete associated analysis records explicitly
            $stmt = $pdo->prepare('DELETE FROM award_analysis WHERE award_id = ?');
            $stmt->execute([$awardIdInt]);
            
            // Delete the uploaded file if it exists
            if ($award['file_path']) {
                $fullPath = __DIR__ . '/../' . $award['file_path'];
                if (file_exists($fullPath)) {
                    if (unlink($fullPath)) {
                        $deletedFiles[] = basename($award['file_path']);
                    }
                }
            }
            
            // Delete from database
            $stmt = $pdo->prepare('DELETE FROM awards WHERE id = ?');
            $stmt->execute([$awardIdInt]);
            
            if ($stmt->rowCount() > 0) {
                logActivity('Award permanently deleted (ID: ' . $awardIdInt . ')', 'INFO');
                echo json_encode([
                    'success' => true,
                    'message' => 'Award permanently deleted',
                    'deleted_files' => $deletedFiles
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Award not found']);
            }
        } else {
            // Soft delete - move to trash (set deleted_at)
            try {
                // Ensure deleted_at column exists
                $pdo->exec("ALTER TABLE awards ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
            } catch (PDOException $e) {
                // Column might already exist, ignore
            }
            
            $stmt = $pdo->prepare('UPDATE awards SET deleted_at = NOW() WHERE id = ?');
            $stmt->execute([$awardIdInt]);
            
            if ($stmt->rowCount() > 0) {
                logActivity('Award moved to trash (ID: ' . $awardIdInt . ')', 'INFO');
                echo json_encode([
                    'success' => true,
                    'message' => 'Award moved to trash'
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Award not found']);
            }
        }
        return;
    }
    
    // File-based storage deletion (original logic)
    // Handle mock awards (ID = 1) - track deleted mock awards
    if ($awardId === '1' || $awardId === 1) {
        // Store deleted mock awards in a simple file
        $deletedMockFile = __DIR__ . '/../data/deleted_mock_awards.json';
        $deletedMocks = [];
        
        if (file_exists($deletedMockFile)) {
            $content = file_get_contents($deletedMockFile);
            if ($content) {
                $deletedMocks = json_decode($content, true) ?: [];
            }
        }
        
        // Add this mock award to the deleted list
        $deletedMocks[] = [
            'id' => $awardId,
            'deleted_at' => date('Y-m-d H:i:s')
        ];
        
        // Ensure data directory exists
        $dataDir = __DIR__ . '/../data/';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        // Save the updated deleted list
        file_put_contents($deletedMockFile, json_encode($deletedMocks));
        
        echo json_encode([
            'success' => true,
            'message' => 'Mock award deleted successfully',
            'deleted_files' => ['mock_award']
        ]);
        return;
    }
    
    // For file-based storage, delete the analysis file and associated files
    $dataDir = __DIR__ . '/../data/';
    $uploadsDir = __DIR__ . '/../uploads/awards/';
    
    $deleted = false;
    $deletedFiles = [];
    
    // Find and delete the analysis file
    if (is_dir($dataDir)) {
        $files = glob($dataDir . '*.json'); // Include both analysis_* and upload_analysis_* files
        foreach ($files as $file) {
            if (strpos(basename($file, '.json'), $awardId) !== false) {
                // Read the file to get the associated upload file
                $content = file_get_contents($file);
                if ($content) {
                    $data = json_decode($content, true);
                    if ($data && isset($data['file_path'])) {
                        // Delete the uploaded file
                        $uploadFile = $data['file_path'];
                        if (file_exists($uploadFile)) {
                            if (unlink($uploadFile)) {
                                $deletedFiles[] = basename($uploadFile);
                            }
                        }
                    }
                }
                
                // Delete the analysis file
                if (unlink($file)) {
                    $deletedFiles[] = basename($file);
                    $deleted = true;
                }
                break;
            }
        }
    }
    
    if ($deleted) {
        logActivity('Award deleted successfully: ' . implode(', ', $deletedFiles), 'INFO');
        echo json_encode([
            'success' => true,
            'message' => 'Award deleted successfully',
            'deleted_files' => $deletedFiles
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Award not found']);
    }
    
} catch (Exception $e) {
    logActivity('Delete award failed: ' . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete award: ' . $e->getMessage()]);
}
?>
