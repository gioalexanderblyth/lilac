<?php
require_once 'api/config.php';

try {
    $pdo = getDatabaseConnection();
    
    echo "--- other_documents Table ---\n";
    $stmt = $pdo->query("DESCRIBE other_documents");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    print_r($columns);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
