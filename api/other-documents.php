<?php
/**
 * Other Documents Management API
 * Handles CRUD operations for other documents with file uploads
 * All authenticated users have full access
 */

// Start output buffering to catch any unexpected output
if (!ob_get_level()) {
    ob_start();
}

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, RESTORE, OPTIONS');
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

// Get database connection
try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'MySQL database required for documents management']);
        exit();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Allow all authenticated users to access documents API
// Removed admin-only restriction - all authenticated users have full CRUD access

// Handle file upload
function handleFileUpload($file) {
    $uploadDir = __DIR__ . '/../uploads/other_documents/';

    // Create upload directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error: ' . $file['error']);
    }

    // Validate file size (max 10MB)
    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        throw new Exception('File size exceeds maximum limit of 10MB');
    }

    // Validate file type
    $allowedTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/png',
        'text/plain'
    ];
    
    // Add audio types
    $allowedTypes = array_merge($allowedTypes, ALLOWED_AUDIO_TYPES);
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    // Check if it's an audio file (use larger size limit)
    $isAudio = in_array($mimeType, ALLOWED_AUDIO_TYPES);
    if ($isAudio) {
        $maxSize = MAX_AUDIO_SIZE; // Use larger limit for audio files
        if ($file['size'] > $maxSize) {
            throw new Exception('Audio file size exceeds maximum limit of ' . ($maxSize / 1024 / 1024) . 'MB');
        }
    }

    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Invalid file type. Allowed types: PDF, Word, Excel, Images, Text, Audio (MP3, WAV, OGG, M4A, AAC, FLAC)');
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('doc_') . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to move uploaded file');
    }

    return [
        'filename' => $filename,
        'filepath' => 'uploads/other_documents/' . $filename,
        'original_name' => $file['name']
    ];
}

// Handle different HTTP methods
$method = $_SERVER['REQUEST_METHOD'];

try {
    // Ensure no output before JSON
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    switch ($method) {
        case 'GET':
            // Check if it's a restore action
            if (isset($_GET['action']) && $_GET['action'] === 'restore') {
                $id = $_GET['id'] ?? null;
                if (!$id) {
                    throw new Exception('Document ID required for restoration');
                }
                
                try {
                    // Ensure deleted_at column exists
                    try {
                        $pdo->exec("ALTER TABLE other_documents ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
                    } catch (PDOException $e) {
                        // Column might already exist, ignore
                    }
                    
                    $stmt = $pdo->prepare("UPDATE other_documents SET deleted_at = NULL WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    if ($stmt->rowCount() > 0) {
                        echo json_encode(['success' => true, 'message' => 'Document restored successfully']);
                    } else {
                        throw new Exception('Document not found');
                    }
                } catch (PDOException $e) {
                    throw new Exception('Failed to restore document: ' . $e->getMessage());
                }
            } elseif (isset($_GET['id'])) {
                // Get single document
                $stmt = $pdo->prepare("
                    SELECT od.*, u.username as uploaded_by
                    FROM other_documents od
                    LEFT JOIN users u ON od.user_id = u.id
                    WHERE od.id = ?
                ");
                $stmt->execute([$_GET['id']]);
                $document = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($document) {
                    echo json_encode(['success' => true, 'data' => $document]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Document not found']);
                }
            } else {
                // Get all documents (optionally filtered by user_id)
                $userIdFilter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
                
                if ($userIdFilter) {
                    $stmt = $pdo->prepare("
                        SELECT od.*, u.username as uploaded_by
                        FROM other_documents od
                        LEFT JOIN users u ON od.user_id = u.id
                        WHERE od.user_id = ?
                        ORDER BY od.created_at DESC
                    ");
                    $stmt->execute([$userIdFilter]);
                } else {
                    $stmt = $pdo->query("
                        SELECT od.*, u.username as uploaded_by
                        FROM other_documents od
                        LEFT JOIN users u ON od.user_id = u.id
                        ORDER BY od.created_at DESC
                    ");
                }
                $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'data' => $documents]);
            }
            break;

        case 'POST':
            // Create new document
            if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
                throw new Exception('No file uploaded');
            }

            $fileInfo = handleFileUpload($_FILES['file']);

            $title = $_POST['title'] ?? $fileInfo['original_name'];
            $description = $_POST['description'] ?? null;
            $category = $_POST['category'] ?? 'Other Documents';

            $stmt = $pdo->prepare("
                INSERT INTO other_documents
                (user_id, title, description, file_name, file_path, category, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            $stmt->execute([
                $userId,
                $title,
                $description,
                $fileInfo['original_name'],
                $fileInfo['filepath'],
                $category
            ]);

            $newId = $pdo->lastInsertId();

            // NOTE: Auto-copy to MOU/MOA table has been disabled to prevent false positives.
            // Documents uploaded to the Documents page will stay in the Documents page even if
            // they are classified as MOU/MOA. Users should explicitly upload MOU/MOA documents
            // through the dedicated MOU/MOA page if they want them in that system.
            
            // If you need to enable auto-copy in the future, uncomment the code below and add
            // a confidence threshold check (e.g., only copy if confidence >= 85%)
            /*
            if (stripos($category, 'MOU') !== false || stripos($category, 'MOA') !== false) {
                // Only auto-copy if explicitly enabled and confidence is very high
                // This prevents false positives from generic document classification
            }
            */

            // Get the created document
            $stmt = $pdo->prepare("
                SELECT od.*, u.username as uploaded_by
                FROM other_documents od
                LEFT JOIN users u ON od.user_id = u.id
                WHERE od.id = ?
            ");
            $stmt->execute([$newId]);
            $newDocument = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'data' => $newDocument
            ]);
            break;

        case 'PUT':
            // Update document
            if (!isset($_GET['id'])) {
                throw new Exception('Document ID required');
            }

            $id = $_GET['id'];

            // Get existing document
            $stmt = $pdo->prepare("SELECT * FROM other_documents WHERE id = ?");
            $stmt->execute([$id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                http_response_code(404);
                throw new Exception('Document not found');
            }

            // Check if file is being uploaded (multipart/form-data)
            $fileUpdated = false;
            $newFilePath = $existing['file_path'];
            $newFileName = $existing['file_name'];
            
            if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
                // Handle file upload
                $fileInfo = handleFileUpload($_FILES['file']);
                $newFilePath = $fileInfo['filepath'];
                $newFileName = $fileInfo['filename'];
                $fileUpdated = true;
                
                // Delete old file if it exists
                if (!empty($existing['file_path']) && file_exists($existing['file_path'])) {
                    @unlink($existing['file_path']);
                }
                
                // Get data from POST (multipart/form-data)
                $title = $_POST['title'] ?? $existing['title'];
                $description = $_POST['description'] ?? $existing['description'];
                $category = $_POST['category'] ?? $existing['category'];
            } else {
                // No file upload, get data from JSON
                $input = json_decode(file_get_contents("php://input"), true);
                if (!$input) {
                    // Try form data if JSON parsing fails
                    parse_str(file_get_contents("php://input"), $_PUT);
                    $input = $_PUT;
                }
                
                $title = $input['title'] ?? $existing['title'];
                $description = $input['description'] ?? $existing['description'];
                $category = $input['category'] ?? $existing['category'];
            }

            // Update fields
            if ($fileUpdated) {
                $stmt = $pdo->prepare("
                    UPDATE other_documents
                    SET title = ?, description = ?, category = ?, file_name = ?, file_path = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$title, $description, $category, $newFileName, $newFilePath, $id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE other_documents
                    SET title = ?, description = ?, category = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$title, $description, $category, $id]);
            }

            // Get updated document
            $stmt = $pdo->prepare("
                SELECT od.*, u.username as uploaded_by
                FROM other_documents od
                LEFT JOIN users u ON od.user_id = u.id
                WHERE od.id = ?
            ");
            $stmt->execute([$id]);
            $updated = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'message' => 'Document updated successfully',
                'document' => $updated
            ]);
            break;

        case 'DELETE':
            // Move document to trash (soft delete)
            if (!isset($_GET['id'])) {
                throw new Exception('Document ID required');
            }

            $id = $_GET['id'];
            $permanent = isset($_GET['permanent']) && $_GET['permanent'] === 'true';

            // Get document file path and details (include soft-deleted documents for permanent delete)
            // When permanent=true, we need to find documents even if they're in trash (deleted_at IS NOT NULL)
            if ($permanent) {
                // For permanent delete, find document regardless of deleted_at status
                $stmt = $pdo->prepare("SELECT * FROM other_documents WHERE id = ?");
            } else {
                // For soft delete, only find active documents
                $stmt = $pdo->prepare("SELECT * FROM other_documents WHERE id = ? AND (deleted_at IS NULL OR deleted_at = '')");
            }
            $stmt->execute([$id]);
            $document = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$document) {
                // For permanent delete, if document doesn't exist, consider it already deleted (idempotent)
                if ($permanent) {
                    // Document already deleted - return success (idempotent operation)
                    if (ob_get_level() > 0) {
                        ob_clean();
                    }
                    echo json_encode([
                        'success' => true,
                        'message' => 'Document already deleted or not found'
                    ]);
                    exit();
                } else {
                    // For soft delete, document must exist
                    http_response_code(404);
                    throw new Exception('Document not found');
                }
            }

            if ($permanent) {
                // Handle related records first (before deleting the main record)
                // Update related records in events and award_analysis tables (foreign keys will set to NULL)
                try {
                    // Update events that reference this document
                    $pdo->prepare("UPDATE events SET document_id = NULL WHERE document_id = ?")->execute([$id]);
                } catch (PDOException $e) {
                    // Log but continue - column might not exist
                    error_log('Note: Could not update events table: ' . $e->getMessage());
                }
                
                try {
                    // Update award_analysis that reference this document
                    $pdo->prepare("UPDATE award_analysis SET document_id = NULL WHERE document_id = ?")->execute([$id]);
                } catch (PDOException $e) {
                    // Log but continue - column might not exist
                    error_log('Note: Could not update award_analysis table: ' . $e->getMessage());
                }
                
                // Delete physical file (if file_path exists and is not empty)
                if (!empty($document['file_path'])) {
                    $fullPath = __DIR__ . '/../' . $document['file_path'];
                    if (file_exists($fullPath)) {
                        @unlink($fullPath);
                    }
                }

                // CASCADE DELETE: Check for linked MOU/MOA (created within 60s with same title)
                try {
                    $title = $document['title'];
                    $createdAt = $document['created_at'];
                    
                    // Find match in mou_moa table - use simpler date comparison
                    $stmtMou = $pdo->prepare("
                        SELECT id, file_path FROM mou_moa 
                        WHERE title = ? 
                        AND ABS(UNIX_TIMESTAMP(created_at) - UNIX_TIMESTAMP(?)) < 60
                        AND (deleted_at IS NULL OR deleted_at = '')
                    ");
                    $stmtMou->execute([$title, $createdAt]);
                    $linkedMou = $stmtMou->fetch(PDO::FETCH_ASSOC);
                    
                    if ($linkedMou) {
                        // Delete related notifications first (to avoid foreign key constraint issues)
                        try {
                            $notifStmt = $pdo->prepare("DELETE FROM notifications WHERE related_type = 'mou_moa' AND related_id = ?");
                            $notifStmt->execute([$linkedMou['id']]);
                        } catch (PDOException $e) {
                            // Log but don't fail if notifications table doesn't exist or has issues
                            error_log('Error deleting notifications for MOU ' . $linkedMou['id'] . ': ' . $e->getMessage());
                        }
                        
                        // Delete linked MOU file if different from main doc
                        if ($linkedMou['file_path'] !== $document['file_path']) {
                            $mouPath = __DIR__ . '/../' . $linkedMou['file_path'];
                            if (file_exists($mouPath)) {
                                @unlink($mouPath);
                            }
                        }
                        
                        // Permanently delete linked MOU record
                        try {
                            $deleteMouStmt = $pdo->prepare("DELETE FROM mou_moa WHERE id = ?");
                            $deleteMouStmt->execute([$linkedMou['id']]);
                            error_log("Cascade permanently deleted linked MOU/MOA ID: " . $linkedMou['id']);
                        } catch (PDOException $e) {
                            // Log error but don't fail the main delete operation
                            error_log("Failed to delete linked MOU record: " . $e->getMessage());
                        }
                    }
                } catch (Exception $e) {
                    // Log error but don't fail the main delete operation
                    error_log("Failed to cascade delete linked MOU: " . $e->getMessage());
                }

                // Delete database record
                try {
                    // Check if document still exists (might have been deleted by cascade)
                    $checkStmt = $pdo->prepare("SELECT id FROM other_documents WHERE id = ?");
                    $checkStmt->execute([$id]);
                    $stillExists = $checkStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($stillExists) {
                        $stmt = $pdo->prepare("DELETE FROM other_documents WHERE id = ?");
                        $stmt->execute([$id]);
                        
                        if ($stmt->rowCount() > 0) {
                            // Clear output buffer before sending response
                            if (ob_get_level() > 0) {
                                ob_clean();
                            }
                            echo json_encode([
                                'success' => true,
                                'message' => 'Document permanently deleted'
                            ]);
                        } else {
                            // Document might have been deleted by foreign key cascade
                            if (ob_get_level() > 0) {
                                ob_clean();
                            }
                            echo json_encode([
                                'success' => true,
                                'message' => 'Document deleted (may have been removed by cascade)'
                            ]);
                        }
                    } else {
                        // Document already deleted
                        if (ob_get_level() > 0) {
                            ob_clean();
                        }
                        echo json_encode([
                            'success' => true,
                            'message' => 'Document already deleted'
                        ]);
                    }
                } catch (PDOException $e) {
                    error_log('PDO Error deleting other_documents ID ' . $id . ': ' . $e->getMessage());
                    error_log('PDO Error Code: ' . $e->getCode());
                    error_log('PDO Error Info: ' . print_r($e->errorInfo, true));
                    throw new Exception('Failed to delete document: ' . $e->getMessage());
                }
            } else {
                // Soft delete - move to trash (set deleted_at)
                try {
                    // Ensure deleted_at column exists
                    $pdo->exec("ALTER TABLE other_documents ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
                } catch (PDOException $e) {
                    // Column might already exist, ignore
                }
                
                $stmt = $pdo->prepare("UPDATE other_documents SET deleted_at = NOW() WHERE id = ?");
                $stmt->execute([$id]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Document moved to trash'
                ]);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }
} catch (PDOException $e) {
    // Clear any unexpected output that might have been generated before the error
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json');
    error_log('PDO Error in other-documents.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    // Clear any unexpected output that might have been generated before the error
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json');
    error_log('Error in other-documents.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Error $e) {
    // Catch fatal errors (PHP 7+)
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json');
    error_log('Fatal Error in other-documents.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}

// End output buffering and send output
// Only flush if we haven't already sent output
if (ob_get_level() > 0 && !headers_sent()) {
    ob_end_flush();
}
