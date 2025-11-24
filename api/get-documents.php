<?php
/**
 * Get Documents for Selection API
 * Returns documents that can be used for award analysis
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

require_once __DIR__ . '/config.php';

try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'MySQL database required']);
        exit();
    }

    $userId = $_SESSION['user_id'];
    $source = $_GET['source'] ?? 'all'; // 'documents', 'events', 'all'

    // Get documents from other_documents table
    $documents = [];
    
    if ($source === 'all' || $source === 'documents') {
        $stmt = $pdo->prepare("
            SELECT 
                od.id,
                od.title,
                od.description,
                od.file_name,
                od.file_path,
                od.category,
                od.source_page,
                od.created_at,
                'document' as type
            FROM other_documents od
            WHERE od.user_id = ?
            ORDER BY od.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$userId]);
        $documents = array_merge($documents, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // Get documents attached to events
    if ($source === 'all' || $source === 'events') {
        $stmt = $pdo->prepare("
            SELECT 
                od.id,
                od.title,
                od.description,
                od.file_name,
                od.file_path,
                od.category,
                'events' as source_page,
                e.title as event_title,
                e.id as event_id,
                od.created_at,
                'event_attachment' as type
            FROM other_documents od
            INNER JOIN events e ON e.document_id = od.id
            WHERE od.user_id = ?
            ORDER BY od.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$userId]);
        $eventDocs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $documents = array_merge($documents, $eventDocs);
    }

    // Remove duplicates by id
    $uniqueDocs = [];
    $seenIds = [];
    foreach ($documents as $doc) {
        if (!in_array($doc['id'], $seenIds)) {
            $uniqueDocs[] = $doc;
            $seenIds[] = $doc['id'];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $uniqueDocs
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>

