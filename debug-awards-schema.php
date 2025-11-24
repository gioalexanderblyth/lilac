<?php
require_once 'api/config.php';

try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->query("DESCRIBE awards");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns in 'awards' table:\n";
    print_r($columns);
    
    // Check specifically for ocr_text
    if (!in_array('ocr_text', $columns)) {
        echo "\nALERT: 'ocr_text' column is MISSING!\n";
        
        // Add it
        echo "Attempting to add 'ocr_text' column...\n";
        $pdo->exec("ALTER TABLE awards ADD COLUMN ocr_text LONGTEXT NULL AFTER file_path");
        echo "Column 'ocr_text' added successfully.\n";
    } else {
        echo "\n'ocr_text' column exists.\n";
    }

    // Check for user_id
    if (!in_array('user_id', $columns)) {
        echo "\nALERT: 'user_id' column is MISSING!\n";
        // We probably need to add it, but let's see if we can infer logic
        echo "Attempting to add 'user_id' column...\n";
        $pdo->exec("ALTER TABLE awards ADD COLUMN user_id INT(11) NULL AFTER id");
        // Add FK? Maybe later.
        echo "Column 'user_id' added successfully.\n";
    } else {
        echo "\n'user_id' column exists.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
