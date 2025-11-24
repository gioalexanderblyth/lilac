<?php
/**
 * Verify Migration - Unified File Upload System
 * Checks if all required columns and indexes exist
 */

session_start();
require_once __DIR__ . '/../api/config.php';

header('Content-Type: application/json');

try {
    $pdo = getDatabaseConnection();
    
    if ($pdo instanceof FileBasedDatabase) {
        echo json_encode([
            'success' => false,
            'error' => 'MySQL database required'
        ]);
        exit;
    }
    
    $results = [
        'success' => true,
        'checks' => []
    ];
    
    // Check other_documents table
    $stmt = $pdo->query("SHOW COLUMNS FROM `other_documents`");
    $columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    
    $results['checks']['other_documents'] = [
        'award_id' => in_array('award_id', $columns),
        'source_page' => in_array('source_page', $columns)
    ];
    
    // Check events table
    $stmt = $pdo->query("SHOW COLUMNS FROM `events`");
    $columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    
    $results['checks']['events'] = [
        'document_id' => in_array('document_id', $columns)
    ];
    
    // Check award_analysis table
    $stmt = $pdo->query("SHOW COLUMNS FROM `award_analysis`");
    $columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    
    $results['checks']['award_analysis'] = [
        'source_page' => in_array('source_page', $columns),
        'document_id' => in_array('document_id', $columns)
    ];
    
    // Check if all required columns exist
    $allPassed = true;
    foreach ($results['checks'] as $table => $cols) {
        foreach ($cols as $col => $exists) {
            if (!$exists) {
                $allPassed = false;
                $results['success'] = false;
            }
        }
    }
    
    $results['all_passed'] = $allPassed;
    
    echo json_encode($results, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

