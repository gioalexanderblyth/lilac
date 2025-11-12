<?php
/**
 * Migration Script: Update mou_moa table structure
 * Adds required columns for MOU/MOA management system
 */

require_once 'api/config.php';

echo "=== Updating mou_moa Table Structure ===\n\n";

try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        die("Error: MySQL database required for this operation\n");
    }

    // Check current columns
    echo "Checking current table structure...\n";
    $stmt = $pdo->query("DESCRIBE mou_moa");
    $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Current columns: " . implode(', ', $existingColumns) . "\n\n";

    // Add missing columns
    $columnsToAdd = [
        'institution' => "ADD COLUMN institution VARCHAR(255) AFTER user_id",
        'location' => "ADD COLUMN location VARCHAR(255) AFTER institution",
        'contact_email' => "ADD COLUMN contact_email VARCHAR(255) AFTER location",
        'term' => "ADD COLUMN term VARCHAR(100) AFTER contact_email",
        'sign_date' => "ADD COLUMN sign_date DATE AFTER term",
        'end_date' => "ADD COLUMN end_date DATE AFTER sign_date",
        'status' => "ADD COLUMN status ENUM('Active', 'Expired', 'Expires Soon', 'Pending') DEFAULT 'Active' AFTER end_date",
        'file_name' => "ADD COLUMN file_name VARCHAR(255) AFTER status",
        'file_path' => "ADD COLUMN file_path VARCHAR(500) AFTER file_name",
        'updated_at' => "ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at"
    ];

    foreach ($columnsToAdd as $column => $sql) {
        if (!in_array($column, $existingColumns)) {
            echo "Adding column: $column... ";
            $pdo->exec("ALTER TABLE mou_moa $sql");
            echo "✓ Done\n";
        } else {
            echo "Column $column already exists, skipping\n";
        }
    }

    echo "\n✅ Table structure updated successfully!\n\n";

    // Show final structure
    echo "Final table structure:\n";
    $stmt = $pdo->query("DESCRIBE mou_moa");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
