<?php
/**
 * Check Database Constraints and Indexes
 * Verifies all foreign keys and indexes are in place
 */

session_start();
require_once __DIR__ . '/../api/config.php';

header('Content-Type: application/json');

try {
    $pdo = getDatabaseConnection();
    
    if ($pdo instanceof FileBasedDatabase) {
        echo json_encode(['success' => false, 'error' => 'MySQL database required']);
        exit;
    }
    
    $results = [
        'success' => true,
        'foreign_keys' => [],
        'indexes' => []
    ];
    
    // Check foreign keys
    $stmt = $pdo->query("
        SELECT 
            CONSTRAINT_NAME,
            TABLE_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
        AND REFERENCED_TABLE_NAME IS NOT NULL
        AND (
            (TABLE_NAME = 'other_documents' AND COLUMN_NAME = 'award_id') OR
            (TABLE_NAME = 'events' AND COLUMN_NAME = 'document_id') OR
            (TABLE_NAME = 'award_analysis' AND COLUMN_NAME = 'document_id')
        )
    ");
    $results['foreign_keys'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check indexes
    $stmt = $pdo->query("
        SELECT 
            TABLE_NAME,
            INDEX_NAME,
            COLUMN_NAME
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
        AND (
            (TABLE_NAME = 'other_documents' AND INDEX_NAME IN ('idx_award_id', 'idx_source_page')) OR
            (TABLE_NAME = 'events' AND INDEX_NAME = 'idx_document_id') OR
            (TABLE_NAME = 'award_analysis' AND INDEX_NAME IN ('idx_document_id', 'idx_source_page'))
        )
    ");
    $results['indexes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Verify expected constraints
    $expectedFKs = [
        'fk_other_documents_award_id',
        'fk_events_document_id',
        'fk_award_analysis_document_id'
    ];
    
    $foundFKs = array_column($results['foreign_keys'], 'CONSTRAINT_NAME');
    $missingFKs = array_diff($expectedFKs, $foundFKs);
    
    if (!empty($missingFKs)) {
        $results['success'] = false;
        $results['missing_foreign_keys'] = array_values($missingFKs);
    }
    
    // Verify expected indexes
    $expectedIndexes = [
        ['other_documents', 'idx_award_id'],
        ['other_documents', 'idx_source_page'],
        ['events', 'idx_document_id'],
        ['award_analysis', 'idx_document_id'],
        ['award_analysis', 'idx_source_page']
    ];
    
    $foundIndexes = [];
    foreach ($results['indexes'] as $idx) {
        $foundIndexes[] = [$idx['TABLE_NAME'], $idx['INDEX_NAME']];
    }
    
    $missingIndexes = [];
    foreach ($expectedIndexes as $expIdx) {
        $found = false;
        foreach ($foundIndexes as $fIdx) {
            if ($fIdx[0] === $expIdx[0] && $fIdx[1] === $expIdx[1]) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $missingIndexes[] = $expIdx;
        }
    }
    
    if (!empty($missingIndexes)) {
        $results['success'] = false;
        $results['missing_indexes'] = $missingIndexes;
    }
    
    echo json_encode($results, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

