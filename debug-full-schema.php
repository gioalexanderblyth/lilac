<?php
require_once 'api/config.php';

try {
    $pdo = getDatabaseConnection();
    
    echo "--- awards Table ---\n";
    $stmt = $pdo->query("DESCRIBE awards");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    print_r($columns);

    echo "\n--- award_analysis Table ---\n";
    $stmt = $pdo->query("DESCRIBE award_analysis");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    print_r($columns);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
