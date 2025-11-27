<?php
require_once __DIR__ . '/../api/config.php';

try {
    $pdo = getDatabaseConnection();
    echo "Connected to database type: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n";
    
    // Check columns in award_analysis
    $stmt = $pdo->query("DESCRIBE award_analysis");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Columns in award_analysis: " . implode(", ", $columns) . "\n";
    
    if (!in_array('event_id', $columns)) {
        echo "event_id column is MISSING. Adding it now...\n";
        $pdo->exec("ALTER TABLE award_analysis ADD COLUMN event_id INT DEFAULT NULL AFTER document_id");
        echo "event_id column added successfully.\n";
    } else {
        echo "event_id column exists.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

