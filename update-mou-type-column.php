<?php
/**
 * Migration Script: Update mou_moa type column to VARCHAR
 * Changes ENUM('MOU','MOA') to VARCHAR(255) to store full descriptions
 */

require_once 'api/config.php';

echo "=== Updating mou_moa Type Column ===\n\n";

try {
    $pdo = getDatabaseConnection();

    if ($pdo instanceof FileBasedDatabase) {
        die("Error: MySQL database required for this operation\n");
    }

    // Check current column type
    echo "Checking current type column structure...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM mou_moa WHERE Field = 'type'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($column) {
        echo "Current type: " . $column['Type'] . "\n\n";
    }

    // Update existing values first
    echo "Updating existing values...\n";
    $pdo->exec("UPDATE mou_moa SET type = 'MOU (Memorandum of Understanding)' WHERE type = 'MOU'");
    $affectedMOU = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();
    echo "Updated $affectedMOU MOU entries\n";

    $pdo->exec("UPDATE mou_moa SET type = 'MOA (Memorandum of Agreement)' WHERE type = 'MOA'");
    $affectedMOA = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();
    echo "Updated $affectedMOA MOA entries\n\n";

    // Modify column to VARCHAR
    echo "Modifying column type to VARCHAR(255)...\n";
    $pdo->exec("ALTER TABLE mou_moa MODIFY COLUMN type VARCHAR(255) NULL");
    echo "✓ Column modified successfully\n\n";

    // Verify the change
    echo "Verifying change...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM mou_moa WHERE Field = 'type'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "New type: " . $column['Type'] . "\n\n";

    // Show sample data
    echo "Sample data:\n";
    $stmt = $pdo->query("SELECT id, institution, type FROM mou_moa LIMIT 5");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  ID {$row['id']}: {$row['institution']} - {$row['type']}\n";
    }

    echo "\n✅ Migration completed successfully!\n\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
