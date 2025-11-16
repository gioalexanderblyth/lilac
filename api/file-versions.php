<?php
/**
 * File Versioning API
 * Handles file version management
 */

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/activity-history.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['user_id'];

// Get database connection
try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database required for file versioning']);
        exit();
    }

    // Ensure file_versions table exists
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS file_versions (
                id INT(11) NOT NULL AUTO_INCREMENT,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INT(11) NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_size INT(11) DEFAULT NULL,
                version_number INT(11) NOT NULL DEFAULT 1,
                uploaded_by INT(11) NOT NULL,
                version_notes TEXT DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_entity (entity_type, entity_id),
                KEY idx_uploaded_by (uploaded_by),
                FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Exception $e) {
        // Table might already exist, continue
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    if ($method === 'GET') {
        // Get file versions
        $entityType = $_GET['entity_type'] ?? '';
        $entityId = (int)($_GET['entity_id'] ?? 0);

        if (empty($entityType) || $entityId === 0) {
            throw new Exception('Entity type and ID are required');
        }

        $stmt = $pdo->prepare("
            SELECT fv.*, u.username, u.full_name, u.email
            FROM file_versions fv
            LEFT JOIN users u ON fv.uploaded_by = u.id
            WHERE fv.entity_type = ? AND fv.entity_id = ?
            ORDER BY fv.version_number DESC, fv.created_at DESC
        ");
        $stmt->execute([$entityType, $entityId]);
        $versions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $versions
        ]);

    } elseif ($method === 'POST') {
        // Create new file version
        $entityType = $_POST['entity_type'] ?? '';
        $entityId = (int)($_POST['entity_id'] ?? 0);
        $versionNotes = $_POST['version_notes'] ?? null;

        if (empty($entityType) || $entityId === 0) {
            throw new Exception('Entity type and ID are required');
        }

        // Handle file upload
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload required');
        }

        $file = $_FILES['file'];
        $fileName = $file['name'];
        $fileSize = $file['size'];

        // Get next version number
        $stmt = $pdo->prepare("SELECT MAX(version_number) as max_version FROM file_versions WHERE entity_type = ? AND entity_id = ?");
        $stmt->execute([$entityType, $entityId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextVersion = ($result['max_version'] ?? 0) + 1;

        // Determine upload directory based on entity type
        $uploadDir = __DIR__ . '/../uploads/versions/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $uniqueFileName = $entityType . '_' . $entityId . '_v' . $nextVersion . '_' . time() . '.' . $extension;
        $filePath = $uploadDir . $uniqueFileName;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Failed to save uploaded file');
        }

        // Save version record
        $relativePath = 'uploads/versions/' . $uniqueFileName;
        $stmt = $pdo->prepare("
            INSERT INTO file_versions (entity_type, entity_id, file_name, file_path, file_size, version_number, uploaded_by, version_notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$entityType, $entityId, $fileName, $relativePath, $fileSize, $nextVersion, $userId, $versionNotes]);
        
        $versionId = $pdo->lastInsertId();

        // Get created version with user info
        $stmt = $pdo->prepare("
            SELECT fv.*, u.username, u.full_name, u.email
            FROM file_versions fv
            LEFT JOIN users u ON fv.uploaded_by = u.id
            WHERE fv.id = ?
        ");
        $stmt->execute([$versionId]);
        $version = $stmt->fetch(PDO::FETCH_ASSOC);

        // Log activity
        logActivityHistory($pdo, $userId, 'create', 'file_version', $versionId, "Uploaded version $nextVersion for $entityType #$entityId");

        echo json_encode([
            'success' => true,
            'data' => $version,
            'message' => 'File version uploaded successfully'
        ]);

    } elseif ($method === 'DELETE') {
        // Delete file version
        $id = (int)($_GET['id'] ?? 0);

        if ($id === 0) {
            throw new Exception('Version ID is required');
        }

        // Get version info
        $stmt = $pdo->prepare("SELECT file_path FROM file_versions WHERE id = ?");
        $stmt->execute([$id]);
        $version = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$version) {
            throw new Exception('Version not found');
        }

        // Delete physical file
        $filePath = __DIR__ . '/../' . $version['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Delete version record
        $stmt = $pdo->prepare("DELETE FROM file_versions WHERE id = ?");
        $stmt->execute([$id]);

        // Log activity
        logActivityHistory($pdo, $userId, 'delete', 'file_version', $id, 'Deleted file version');

        echo json_encode([
            'success' => true,
            'message' => 'File version deleted successfully'
        ]);

    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

