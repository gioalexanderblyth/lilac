<?php
require_once __DIR__ . '/api/config.php';

try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->query("DESCRIBE award_analysis");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns: " . implode(", ", $columns) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>


