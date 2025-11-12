<?php
/**
 * MOU/MOA Management API
 * Handles CRUD operations for MOU/MOA records with file uploads
 * Admin-only for create/update/delete, read access for all authenticated users
 */

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

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
        $sql = "SELECT * FROM mou_moa ORDER BY created_at DESC";
        $stmt = $pdo->query($sql);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Update status based on current date and map type to category
        foreach ($entries as &$entry) {
            $entry['status'] = calculateStatus($entry['end_date']);
            $entry['can_edit'] = $isAdmin;
            $entry['can_delete'] = $isAdmin;
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
        // Validate required fields
        $required = ['institution', 'location', 'contact_email', 'term', 'sign_date', 'end_date'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required");
            }
        }

        // Validate email format
        if (!filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
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
            $data['contact_email'],
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
        // Calculate status
        $status = calculateStatus($data['end_date']);

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
                $data['contact_email'],
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
                $data['contact_email'],
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
function deleteEntry($pdo, $id) {
    try {
        // Get file path before deleting
        $stmt = $pdo->prepare("SELECT file_path FROM mou_moa WHERE id = ?");
        $stmt->execute([$id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$entry) {
            throw new Exception('Entry not found');
        }

        // Delete the entry
        $stmt = $pdo->prepare("DELETE FROM mou_moa WHERE id = ?");
        $stmt->execute([$id]);

        // Delete the file if it exists
        if (!empty($entry['file_path'])) {
            deleteFile($entry['file_path']);
        }

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        throw new Exception('Failed to delete entry: ' . $e->getMessage());
    }
}

// Main request handling
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    switch ($method) {
        case 'GET':
            if ($action === 'list' || $action === '') {
                // Get all entries
                $entries = getAllEntries($pdo, $userId, $isAdmin);
                echo json_encode(['success' => true, 'data' => $entries]);
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
            // Admin only
            if (!$isAdmin) {
                throw new Exception('Admin access required');
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

            if (empty($data['institution'])) {
                throw new Exception('Invalid input data');
            }

            $id = addEntry($pdo, $data, $userId);
            echo json_encode(['success' => true, 'id' => $id, 'message' => 'Entry added successfully']);
            break;

        case 'PUT':
            // Admin only
            if (!$isAdmin) {
                throw new Exception('Admin access required');
            }

            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('ID is required for update');
            }

            // Check if it's multipart/form-data (with file upload)
            if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $data = $_POST;

                // Map 'category' from form to 'type' for database
                if (isset($data['category'])) {
                    $data['type'] = $data['category'];
                    unset($data['category']);
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

                $success = updateEntry($pdo, $id, $input);
            }

            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Entry updated successfully']);
            } else {
                throw new Exception('Entry not found or no changes made');
            }
            break;

        case 'DELETE':
            // Admin only
            if (!$isAdmin) {
                throw new Exception('Admin access required');
            }

            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('ID is required for deletion');
            }

            $success = deleteEntry($pdo, $id);
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Entry deleted successfully']);
            } else {
                throw new Exception('Entry not found');
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
