<?php
/**
 * MOU/MOA Management API
 * Handles CRUD operations for MOU/MOA records with file uploads
 * Admin-only for create/update/delete, read access for all authenticated users
 */

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, RESTORE, OPTIONS');
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
        echo json_encode(['success' => false, 'error' => 'MySQL database required for MOU/MOA management']);
        exit();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Verify admin role from database to ensure accuracy
$isAdmin = false;
try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($dbUser) {
        $isAdmin = ($dbUser['role'] === 'admin');
        // Update session if role changed
        if (isset($_SESSION['role']) && $_SESSION['role'] !== $dbUser['role']) {
            $_SESSION['role'] = $dbUser['role'];
            $_SESSION['user']['role'] = $dbUser['role'];
        }
    }
} catch (Exception $e) {
    // Fallback to session role on error
    $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Calculate status based on end date
function calculateStatus($endDate) {
    if (empty($endDate)) {
        return 'Pending';
    }

    $today = new DateTime();
    $end = new DateTime($endDate);
    $today->setTime(0, 0, 0);
    $end->setTime(0, 0, 0);

    $diff = $today->diff($end);
    $daysUntilExpiry = (int)$diff->format('%r%a'); // Positive if future, negative if past

    if ($daysUntilExpiry < 0) {
        return 'Expired';
    } elseif ($daysUntilExpiry <= 30) {
        return 'Expires Soon';
    } else {
        return 'Active';
    }
}

// Handle file upload
function handleFileUpload($file) {
    $uploadDir = __DIR__ . '/../uploads/mou/';

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

    // Validate file type (PDF only for MOUs/MOAs)
    $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Invalid file type. Only PDF and Word documents are allowed.');
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = uniqid('mou_') . '_' . time() . '.' . $extension;
    $filePath = $uploadDir . $fileName;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Failed to save uploaded file');
    }

    return [
        'file_name' => $file['name'],
        'file_path' => 'uploads/mou/' . $fileName
    ];
}

// Delete file from server
function deleteFile($filePath) {
    if (!empty($filePath)) {
        $fullPath = __DIR__ . '/../' . $filePath;
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }
}

// Get all MOU/MOA entries
function getAllEntries($pdo, $userId, $isAdmin) {
    try {
        // Ensure deleted_at column exists
        try {
            $pdo->exec("ALTER TABLE mou_moa ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
        } catch (PDOException $e) {
            // Column might already exist, ignore
        }
        
        // Filter out soft-deleted entries (where deleted_at IS NULL)
        $sql = "SELECT * FROM mou_moa WHERE deleted_at IS NULL ORDER BY created_at DESC";
        $stmt = $pdo->query($sql);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Update status based on current date and map type to category
        // Allow all authenticated users to edit and delete (same as admin)
        foreach ($entries as &$entry) {
            $entry['status'] = calculateStatus($entry['end_date']);
            $entry['can_edit'] = true;  // All authenticated users can edit
            $entry['can_delete'] = true; // All authenticated users can delete
            // Map 'type' from database to 'category' for frontend
            if (isset($entry['type'])) {
                $entry['category'] = $entry['type'];
            }
        }

        return $entries;
    } catch (PDOException $e) {
        throw new Exception('Failed to fetch entries: ' . $e->getMessage());
    }
}

// Get single entry
function getEntry($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM mou_moa WHERE id = ?");
        $stmt->execute([$id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$entry) {
            throw new Exception('Entry not found');
        }

        $entry['status'] = calculateStatus($entry['end_date']);
        // Map 'type' from database to 'category' for frontend
        if (isset($entry['type'])) {
            $entry['category'] = $entry['type'];
        }
        return $entry;
    } catch (PDOException $e) {
        throw new Exception('Failed to fetch entry: ' . $e->getMessage());
    }
}

// Add new MOU/MOA entry
function addEntry($pdo, $data, $userId) {
    try {
        // Validate required fields (contact_email is optional)
        $required = ['institution', 'location', 'term', 'sign_date', 'end_date'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required");
            }
        }

        // Validate email format only if contact_email is provided
        if (!empty($data['contact_email']) && !filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }

        // Calculate status
        $status = calculateStatus($data['end_date']);

        $stmt = $pdo->prepare("
            INSERT INTO mou_moa (
                user_id, institution, location, contact_email, term,
                sign_date, end_date, status, file_name, file_path,
                title, partner, type, description, created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $userId,
            $data['institution'],
            $data['location'],
            $data['contact_email'] ?? null, // Allow null for optional contact email
            $data['term'],
            $data['sign_date'],
            $data['end_date'],
            $status,
            $data['file_name'] ?? null,
            $data['file_path'] ?? null,
            $data['title'] ?? $data['institution'], // Use institution as title if not provided
            $data['partner'] ?? null,
            $data['type'] ?? null,
            $data['description'] ?? null
        ]);

        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        throw new Exception('Failed to add entry: ' . $e->getMessage());
    }
}

// Update MOU/MOA entry
function updateEntry($pdo, $id, $data) {
    try {
        // Validate required fields (contact_email is optional)
        $required = ['institution', 'location', 'term', 'sign_date', 'end_date'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw new Exception("Field '$field' is required");
            }
        }

        // Validate email format only if contact_email is provided
        if (!empty($data['contact_email']) && !filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }

        // Calculate status
        $status = calculateStatus($data['end_date'] ?? '');

        // Check if we're updating the file
        $updateFile = isset($data['file_name']) && isset($data['file_path']);

        if ($updateFile) {
            $stmt = $pdo->prepare("
                UPDATE mou_moa
                SET institution = ?, location = ?, contact_email = ?, term = ?,
                    sign_date = ?, end_date = ?, status = ?, file_name = ?, file_path = ?,
                    title = ?, partner = ?, type = ?, description = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $data['institution'],
                $data['location'],
                !empty($data['contact_email']) ? $data['contact_email'] : null, // Allow null for optional contact email
                $data['term'],
                $data['sign_date'],
                $data['end_date'],
                $status,
                $data['file_name'],
                $data['file_path'],
                $data['title'] ?? $data['institution'], // Use institution as title if not provided
                $data['partner'] ?? null,
                $data['type'] ?? null,
                $data['description'] ?? null,
                $id
            ]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE mou_moa
                SET institution = ?, location = ?, contact_email = ?, term = ?,
                    sign_date = ?, end_date = ?, status = ?,
                    title = ?, partner = ?, type = ?, description = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $data['institution'],
                $data['location'],
                !empty($data['contact_email']) ? $data['contact_email'] : null, // Allow null for optional contact email
                $data['term'],
                $data['sign_date'],
                $data['end_date'],
                $status,
                $data['title'] ?? $data['institution'], // Use institution as title if not provided
                $data['partner'] ?? null,
                $data['type'] ?? null,
                $data['description'] ?? null,
                $id
            ]);
        }

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        throw new Exception('Failed to update entry: ' . $e->getMessage());
    }
}

// Delete MOU/MOA entry
function deleteEntry($pdo, $id, $permanent = false) {
    try {
        // Get file path before deleting
        // For permanent delete, include soft-deleted items (deleted_at IS NOT NULL)
        // For soft delete, only find active items
        if ($permanent) {
            // Find entry regardless of deleted_at status for permanent delete
            $stmt = $pdo->prepare("SELECT file_path FROM mou_moa WHERE id = ?");
        } else {
            // Only find active entries for soft delete
            $stmt = $pdo->prepare("SELECT file_path FROM mou_moa WHERE id = ? AND (deleted_at IS NULL OR deleted_at = '')");
        }
        $stmt->execute([$id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);

        // For permanent delete, if entry doesn't exist, consider it already deleted (idempotent)
        if (!$entry) {
            if ($permanent) {
                // Entry already deleted - return true (idempotent operation)
                return true;
            } else {
                throw new Exception('Entry not found');
            }
        }

        // Delete related notifications (both for permanent and soft delete)
        try {
            // Delete all notifications related to this MOU
            // This will also cascade delete related records in notification_reads and notification_confirmations
            // if foreign keys are properly set up
            $notifStmt = $pdo->prepare("DELETE FROM notifications WHERE related_type = 'mou_moa' AND related_id = ?");
            $notifStmt->execute([$id]);
        } catch (PDOException $e) {
            // Log error but don't fail the delete operation if notifications table doesn't exist
            // This allows the system to work even if notifications haven't been set up yet
            error_log('Error deleting notifications for MOU ' . $id . ': ' . $e->getMessage());
        }

        if ($permanent) {
            // Permanent delete - remove file and database record
            // Delete the file if it exists
            if (!empty($entry['file_path'])) {
                deleteFile($entry['file_path']);
            }
            
            // Delete database record
            $stmt = $pdo->prepare("DELETE FROM mou_moa WHERE id = ?");
            $stmt->execute([$id]);
            
            return $stmt->rowCount() > 0;
        } else {
            // Soft delete - move to trash (set deleted_at)
            // First, ensure deleted_at column exists
            try {
                $pdo->exec("ALTER TABLE mou_moa ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
            } catch (PDOException $e) {
                // Column might already exist, ignore
            }
            $stmt = $pdo->prepare("UPDATE mou_moa SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            
            return $stmt->rowCount() > 0;
        }
    } catch (PDOException $e) {
        error_log('PDO Error in deleteEntry for MOU ' . $id . ': ' . $e->getMessage());
        throw new Exception('Failed to delete entry: ' . $e->getMessage());
    }
}

// Get MOU/MOA entries due for renewal (60 days before expiration)
function getRenewals($pdo) {
    try {
        $today = date('Y-m-d');
        $sixtyDaysFromNow = date('Y-m-d', strtotime('+60 days'));
        
        $sql = "SELECT id, title, institution, partner, type, end_date, 
                       DATEDIFF(end_date, CURDATE()) as days_remaining
                FROM mou_moa 
                WHERE deleted_at IS NULL
                AND end_date IS NOT NULL
                AND end_date >= CURDATE()
                AND end_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
                AND (renewal_confirmed = 0 OR renewal_confirmed IS NULL)
                ORDER BY end_date ASC";
        
        $stmt = $pdo->query($sql);
        $renewals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Map 'type' from database to 'category' for frontend
        foreach ($renewals as &$renewal) {
            if (isset($renewal['type'])) {
                $renewal['category'] = $renewal['type'];
            }
            // Ensure days_remaining is an integer
            $renewal['days_remaining'] = (int)$renewal['days_remaining'];
        }
        
        return $renewals;
    } catch (PDOException $e) {
        throw new Exception('Failed to fetch renewals: ' . $e->getMessage());
    }
}

// Mark MOU/MOA as renewed
function markAsRenewed($pdo, $id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE mou_moa 
            SET renewal_confirmed = 1, 
                renewal_confirmed_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        throw new Exception('Failed to mark as renewed: ' . $e->getMessage());
    }
}

// Main request handling
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    switch ($method) {
        case 'GET':
            // Check if it's a restore action
            if ($action === 'restore') {
                $id = $_GET['id'] ?? null;
                if (!$id) {
                    throw new Exception('ID is required for restoration');
                }
                
                try {
                    // Ensure deleted_at column exists
                    try {
                        $pdo->exec("ALTER TABLE mou_moa ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
                    } catch (PDOException $e) {
                        // Column might already exist, ignore
                    }
                    
                    $stmt = $pdo->prepare("UPDATE mou_moa SET deleted_at = NULL WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    if ($stmt->rowCount() > 0) {
                        echo json_encode(['success' => true, 'message' => 'Entry restored successfully']);
                    } else {
                        throw new Exception('Entry not found');
                    }
                } catch (PDOException $e) {
                    throw new Exception('Failed to restore entry: ' . $e->getMessage());
                }
            } elseif ($action === 'list' || $action === '') {
                // Get all entries - all authenticated users have full access
                $entries = getAllEntries($pdo, $userId, true);
                echo json_encode(['success' => true, 'data' => $entries]);
            } elseif ($action === 'renewals') {
                // Get MOU/MOA entries due for renewal
                $renewals = getRenewals($pdo);
                echo json_encode(['success' => true, 'data' => $renewals]);
            } elseif ($action === 'get') {
                // Get single entry
                $id = $_GET['id'] ?? null;
                if (!$id) {
                    throw new Exception('ID is required');
                }
                $entry = getEntry($pdo, $id);
                echo json_encode(['success' => true, 'data' => $entry]);
            } elseif ($action === 'download') {
                // Download file
                $id = $_GET['id'] ?? null;
                if (!$id) {
                    throw new Exception('ID is required');
                }

                $entry = getEntry($pdo, $id);
                if (empty($entry['file_path'])) {
                    throw new Exception('No file attached to this entry');
                }

                $filePath = __DIR__ . '/../' . $entry['file_path'];
                if (!file_exists($filePath)) {
                    throw new Exception('File not found');
                }

                // Set headers for file download
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($entry['file_name']) . '"');
                header('Content-Length: ' . filesize($filePath));
                readfile($filePath);
                exit();
            } else {
                throw new Exception('Invalid action');
            }
            break;

        case 'POST':
            // Allow all authenticated users to create (same as admin)
            // No admin check needed - all authenticated users have full access

            $action = $_GET['action'] ?? '';
            
            // Handle update via POST (for FormData compatibility)
            if ($action === 'update') {
                $id = $_GET['id'] ?? null;
                if (!$id) {
                    throw new Exception('ID is required for update');
                }

                // Handle file upload if present
                $data = $_POST;
                $hasFile = isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE;
                
                if ($hasFile) {
                    // Get old file path for deletion
                    $oldEntry = getEntry($pdo, $id);
                    
                    // Upload new file
                    $fileInfo = handleFileUpload($_FILES['file']);
                    $data['file_name'] = $fileInfo['file_name'];
                    $data['file_path'] = $fileInfo['file_path'];
                }

                // Map 'category' from form to 'type' for database
                if (isset($data['category'])) {
                    $data['type'] = $data['category'];
                    unset($data['category']);
                }

                $success = updateEntry($pdo, $id, $data);

                // Delete old file if update was successful
                if ($success && $hasFile && !empty($oldEntry['file_path'])) {
                    deleteFile($oldEntry['file_path']);
                }

                if ($success) {
                    echo json_encode(['success' => true, 'message' => 'Entry updated successfully']);
                } else {
                    throw new Exception('Entry not found or no changes made');
                }
                break;
            }

            // Handle file upload if present
            $data = $_POST;
            if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $fileInfo = handleFileUpload($_FILES['file']);
                $data['file_name'] = $fileInfo['file_name'];
                $data['file_path'] = $fileInfo['file_path'];
            }

            // Map 'category' from form to 'type' for database
            if (isset($data['category'])) {
                $data['type'] = $data['category'];
                unset($data['category']);
            }
            
            // Normalize empty contact_email to null (it's optional)
            if (isset($data['contact_email']) && empty(trim($data['contact_email']))) {
                $data['contact_email'] = null;
            }

            if (empty($data['institution'])) {
                throw new Exception('Invalid input data');
            }

            $id = addEntry($pdo, $data, $userId);
            echo json_encode(['success' => true, 'id' => $id, 'message' => 'Entry added successfully']);
            break;

        case 'PUT':
            // Allow all authenticated users to update (same as admin)
            // No admin check needed - all authenticated users have full access

            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('ID is required for update');
            }

            // Check if it's multipart/form-data (FormData from frontend)
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            $isFormData = strpos($contentType, 'multipart/form-data') !== false;
            
            // For PUT requests, PHP doesn't populate $_POST automatically
            // Check if we have files (which PHP does populate for PUT)
            if ($isFormData && isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
                // File upload with FormData - PHP populates $_FILES but may not populate $_POST for PUT
                // Try $_POST first, if empty, parse from input stream
                if (!empty($_POST)) {
                    $data = $_POST;
                } else {
                    // Parse multipart/form-data manually for PUT requests
                    $data = [];
                    $input = file_get_contents('php://input');
                    $boundary = substr($contentType, strpos($contentType, 'boundary=') + 9);
                    $parts = explode('--' . $boundary, $input);
                    
                    foreach ($parts as $part) {
                        if (empty(trim($part)) || $part === '--') continue;
                        
                        if (preg_match('/name="([^"]+)"/', $part, $matches)) {
                            $name = $matches[1];
                            if ($name === 'file') continue; // Skip file, already in $_FILES
                            
                            $value = preg_split('/\r\n\r\n/', $part, 2);
                            if (isset($value[1])) {
                                $data[$name] = trim($value[1], "\r\n-");
                            }
                        }
                    }
                }

                // Map 'category' from form to 'type' for database
                if (isset($data['category'])) {
                    $data['type'] = $data['category'];
                    unset($data['category']);
                }
                
                // Normalize empty contact_email to null (it's optional)
                if (isset($data['contact_email']) && empty(trim($data['contact_email']))) {
                    $data['contact_email'] = null;
                }

                // Get old file path for deletion
                $oldEntry = getEntry($pdo, $id);

                // Upload new file
                $fileInfo = handleFileUpload($_FILES['file']);
                $data['file_name'] = $fileInfo['file_name'];
                $data['file_path'] = $fileInfo['file_path'];

                $success = updateEntry($pdo, $id, $data);

                // Delete old file if update was successful
                if ($success && !empty($oldEntry['file_path'])) {
                    deleteFile($oldEntry['file_path']);
                }
            } elseif ($isFormData) {
                // FormData without file upload - PHP doesn't populate $_POST for PUT
                // Parse multipart/form-data manually
                if (!empty($_POST)) {
                    $data = $_POST;
                } else {
                    $data = [];
                    $input = file_get_contents('php://input');
                    $boundary = substr($contentType, strpos($contentType, 'boundary=') + 9);
                    $parts = explode('--' . $boundary, $input);
                    
                    foreach ($parts as $part) {
                        if (empty(trim($part)) || $part === '--') continue;
                        
                        if (preg_match('/name="([^"]+)"/', $part, $matches)) {
                            $name = $matches[1];
                            $value = preg_split('/\r\n\r\n/', $part, 2);
                            if (isset($value[1])) {
                                $data[$name] = trim($value[1], "\r\n-");
                            }
                        }
                    }
                }

                // Map 'category' from form to 'type' for database
                if (isset($data['category'])) {
                    $data['type'] = $data['category'];
                    unset($data['category']);
                }
                
                // Normalize empty contact_email to null (it's optional)
                if (isset($data['contact_email']) && empty(trim($data['contact_email']))) {
                    $data['contact_email'] = null;
                }

                $success = updateEntry($pdo, $id, $data);
            } else {
                // JSON input (no file upload)
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input) {
                    throw new Exception('Invalid JSON input');
                }

                // Map 'category' from form to 'type' for database
                if (isset($input['category'])) {
                    $input['type'] = $input['category'];
                    unset($input['category']);
                }
                
                // Normalize empty contact_email to null (it's optional)
                if (isset($input['contact_email']) && empty(trim($input['contact_email']))) {
                    $input['contact_email'] = null;
                }

                $success = updateEntry($pdo, $id, $input);
            }

            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Entry updated successfully']);
            } else {
                throw new Exception('Entry not found or no changes made');
            }
            break;

        case 'PATCH':
            // Handle mark as renewed
            $action = $_GET['action'] ?? '';
            if ($action === 'mark-renewed') {
                $id = $_GET['id'] ?? null;
                if (!$id) {
                    throw new Exception('ID is required');
                }
                
                $success = markAsRenewed($pdo, $id);
                if ($success) {
                    echo json_encode(['success' => true, 'message' => 'Marked as renewed successfully']);
                } else {
                    throw new Exception('Entry not found or already marked as renewed');
                }
            } else {
                throw new Exception('Invalid action');
            }
            break;

        case 'DELETE':
            // Allow all authenticated users to delete (same as admin)
            // No admin check needed - all authenticated users have full access

            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('ID is required for deletion');
            }

            // Check if permanent delete is requested
            $permanent = isset($_GET['permanent']) && $_GET['permanent'] === 'true';
            
            $success = deleteEntry($pdo, $id, $permanent);
            if ($success) {
                $message = $permanent ? 'Entry permanently deleted' : 'Entry moved to trash';
                echo json_encode(['success' => true, 'message' => $message]);
            } else {
                // For permanent delete, if entry doesn't exist, it's already deleted (success)
                if ($permanent) {
                    echo json_encode(['success' => true, 'message' => 'Entry already deleted or not found']);
                } else {
                    throw new Exception('Entry not found');
                }
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
