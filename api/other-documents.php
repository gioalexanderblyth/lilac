<?php
/**
 * Other Documents Management API
 * Handles CRUD operations for other documents with file uploads
 * Admin-only access
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

// Verify admin role from database
try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'MySQL database required for documents management']);
        exit();
    }

    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $isAdmin = ($user && $user['role'] === 'admin');
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Admin-only access
if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit();
}

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
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Invalid file type. Allowed types: PDF, Word, Excel, Images, Text');
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
    switch ($method) {
        case 'GET':
            // Get all other documents
            if (isset($_GET['id'])) {
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
                // Get all documents
                $stmt = $pdo->query("
                    SELECT od.*, u.username as uploaded_by
                    FROM other_documents od
                    LEFT JOIN users u ON od.user_id = u.id
                    ORDER BY od.created_at DESC
                ");
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
            parse_str(file_get_contents("php://input"), $_PUT);

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

            // Update fields
            $title = $_PUT['title'] ?? $existing['title'];
            $description = $_PUT['description'] ?? $existing['description'];
            $category = $_PUT['category'] ?? $existing['category'];

            $stmt = $pdo->prepare("
                UPDATE other_documents
                SET title = ?, description = ?, category = ?, updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([$title, $description, $category, $id]);

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
                'data' => $updated
            ]);
            break;

        case 'DELETE':
            // Delete document
            if (!isset($_GET['id'])) {
                throw new Exception('Document ID required');
            }

            $id = $_GET['id'];

            // Get document file path
            $stmt = $pdo->prepare("SELECT file_path FROM other_documents WHERE id = ?");
            $stmt->execute([$id]);
            $document = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$document) {
                http_response_code(404);
                throw new Exception('Document not found');
            }

            // Delete file
            $fullPath = __DIR__ . '/../' . $document['file_path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            // Delete database record
            $stmt = $pdo->prepare("DELETE FROM other_documents WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode([
                'success' => true,
                'message' => 'Document deleted successfully'
            ]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
