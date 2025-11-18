<?php
/**
 * Global Search API
 * Searches across awards, events, documents, and MOUs
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

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
        echo json_encode(['success' => false, 'error' => 'Database required']);
        exit();
    }
    
    $query = $_GET['q'] ?? '';
    $limit = min((int)($_GET['limit'] ?? 10), 20); // Max 20 results per category
    
    if (empty($query) || strlen($query) < 2) {
        echo json_encode(['success' => true, 'results' => []]);
        exit();
    }
    
    // Use case-insensitive search (MySQL default, but explicit for clarity)
    $searchTerm = '%' . $query . '%';
    $results = [
        'awards' => [],
        'events' => [],
        'documents' => [],
        'mous' => []
    ];
    
    // Search Awards (handle NULL descriptions and empty titles)
    try {
        $stmt = $pdo->prepare("
            SELECT 
                a.id,
                COALESCE(NULLIF(a.title, ''), 'Untitled Award') as title,
                a.created_at,
                'award' as type,
                CONCAT('user-awards.php?id=', a.id) as url
            FROM awards a
            WHERE (
                (a.title IS NOT NULL AND a.title != '' AND a.title LIKE ?)
                OR (a.description IS NOT NULL AND a.description LIKE ?)
                OR (a.file_name IS NOT NULL AND a.file_name LIKE ?)
            )
            ORDER BY a.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit]);
        $results['awards'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Search Awards Error: " . $e->getMessage());
        $results['awards'] = [];
    }
    
    // Search Events (handle NULL descriptions and locations)
    try {
        $stmt = $pdo->prepare("
            SELECT 
                e.id,
                e.title,
                e.created_at,
                'event' as type,
                CONCAT('events-activities.php?event=', e.id) as url
            FROM events e
            WHERE (e.title LIKE ? OR COALESCE(e.description, '') LIKE ? OR COALESCE(e.location, '') LIKE ?)
            AND e.title IS NOT NULL AND e.title != ''
            ORDER BY COALESCE(e.event_date, e.created_at) DESC, e.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit]);
        $results['events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Search Events Error: " . $e->getMessage());
        $results['events'] = [];
    }
    
    // Search Documents (handle NULL values)
    try {
        $stmt = $pdo->prepare("
            SELECT 
                d.id,
                d.title,
                d.created_at,
                'document' as type,
                CONCAT('documents.php?doc=', d.id) as url
            FROM other_documents d
            WHERE (d.title LIKE ? OR COALESCE(d.description, '') LIKE ? OR COALESCE(d.category, '') LIKE ?)
            AND d.title IS NOT NULL AND d.title != ''
            ORDER BY d.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit]);
        $results['documents'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Search Documents Error: " . $e->getMessage());
        $results['documents'] = [];
    }
    
    // Search MOUs/MOAs (handle NULL values and search institution field)
    try {
        $stmt = $pdo->prepare("
            SELECT 
                m.id,
                COALESCE(
                    NULLIF(m.title, ''),
                    NULLIF(m.institution, ''),
                    'Untitled MOU/MOA'
                ) as title,
                m.created_at,
                'mou' as type,
                CONCAT('mou-moa.php?id=', m.id) as url
            FROM mou_moa m
            WHERE (
                (m.title IS NOT NULL AND m.title != '' AND m.title LIKE ?)
                OR (m.institution IS NOT NULL AND m.institution != '' AND m.institution LIKE ?)
                OR (m.partner IS NOT NULL AND m.partner != '' AND m.partner LIKE ?)
                OR (m.description IS NOT NULL AND m.description != '' AND m.description LIKE ?)
                OR (m.location IS NOT NULL AND m.location != '' AND m.location LIKE ?)
            )
            ORDER BY m.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit]);
        $results['mous'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Search MOUs Error: " . $e->getMessage());
        $results['mous'] = [];
    }
    
    // Combine and format results
    $allResults = [];
    foreach ($results as $category => $items) {
        foreach ($items as $item) {
            $allResults[] = $item;
        }
    }
    
    // Sort by relevance (could be improved with full-text search)
    usort($allResults, function($a, $b) use ($query) {
        $aTitle = strtolower($a['title']);
        $bTitle = strtolower($b['title']);
        $queryLower = strtolower($query);
        
        // Exact match gets priority
        $aExact = strpos($aTitle, $queryLower) === 0;
        $bExact = strpos($bTitle, $queryLower) === 0;
        
        if ($aExact && !$bExact) return -1;
        if (!$aExact && $bExact) return 1;
        
        // Then by date (newer first)
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Debug logging (remove in production)
    error_log("Search Query: " . $query);
    error_log("Awards found: " . count($results['awards']));
    error_log("Events found: " . count($results['events']));
    error_log("Documents found: " . count($results['documents']));
    error_log("MOUs found: " . count($results['mous']));
    
    echo json_encode([
        'success' => true,
        'results' => array_slice($allResults, 0, $limit * 2), // Return top results
        'counts' => [
            'awards' => count($results['awards']),
            'events' => count($results['events']),
            'documents' => count($results['documents']),
            'mous' => count($results['mous'])
        ],
        'debug' => [
            'query' => $query,
            'searchTerm' => $searchTerm
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

