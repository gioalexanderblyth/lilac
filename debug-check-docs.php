<?php
require_once 'api/config.php';

try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->query("SELECT count(*) FROM other_documents");
    echo "Other Documents Count: " . $stmt->fetchColumn() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

