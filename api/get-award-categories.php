<?php
/**
 * Get Award Categories API
 * Returns list of available award categories for selection
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

    // Get award categories from award_categories table
    $stmt = $pdo->query("
        SELECT 
            id,
            name,
            description
        FROM award_categories
        ORDER BY name ASC
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Also get unique category names from award_criteria
    $stmt2 = $pdo->query("
        SELECT DISTINCT 
            category_name as name,
            description
        FROM award_criteria
        WHERE status = 'active'
        ORDER BY category_name ASC
    ");
    $criteriaCategories = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // Merge and deduplicate
    $allCategories = [];
    $seenNames = [];
    
    foreach ($categories as $cat) {
        if (!in_array($cat['name'], $seenNames)) {
            $allCategories[] = $cat;
            $seenNames[] = $cat['name'];
        }
    }
    
    foreach ($criteriaCategories as $cat) {
        if (!in_array($cat['name'], $seenNames)) {
            $allCategories[] = [
                'id' => null,
                'name' => $cat['name'],
                'description' => $cat['description']
            ];
            $seenNames[] = $cat['name'];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $allCategories
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>

