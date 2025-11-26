<?php
require_once __DIR__ . '/../api/config.php';

try {
    $pdo = getDatabaseConnection();
    
    // Check if column exists first
    $stmt = $pdo->query("SHOW COLUMNS FROM award_analysis LIKE 'event_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE award_analysis ADD COLUMN event_id INT DEFAULT NULL AFTER document_id");
        echo "Added event_id column to award_analysis table.\n";
    } else {
        echo "event_id column already exists.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

