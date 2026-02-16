<?php
/**
 * One-time migration: Add image_path column to events table
 * Run via: php add-event-image-column.php (or visit in browser)
 */
require_once __DIR__ . '/../../api/config.php';

try {
    $pdo = getDatabaseConnection();
    if ($pdo instanceof FileBasedDatabase) {
        die('This migration requires MySQL. File-based database not supported.');
    }
    $pdo->exec("ALTER TABLE events ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER location");
    echo "Added image_path column to events table.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
