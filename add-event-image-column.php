<?php
require_once __DIR__ . '/api/config.php';

try {
    $pdo = getDatabaseConnection();
    $pdo->exec("ALTER TABLE events ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER location");
    echo "Added image_path column to events table.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

