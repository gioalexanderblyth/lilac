<?php
require_once 'api/config.php';

header('Content-Type: text/plain');

try {
    $pdo = getDatabaseConnection();
    echo "Database connection successful.\n";
    
    if ($pdo instanceof FileBasedDatabase) {
        echo "Using FileBasedDatabase (fallback).\n";
    } else {
        echo "Using PDO (SQLite/MySQL).\n";
        
        // Check awards table schema
        echo "\nSchema for 'awards':\n";
        try {
            $cols = $pdo->query("PRAGMA table_info(awards)")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $col) {
                echo "- " . $col['name'] . " (" . $col['type'] . ")\n";
            }
        } catch (Exception $e) {
            echo "Error getting schema: " . $e->getMessage() . "\n";
        }
        
        // Check raw count
        $count = $pdo->query("SELECT COUNT(*) FROM awards")->fetchColumn();
        echo "\nTotal rows in 'awards' table: $count\n";
        
        // Check rows
        $stmt = $pdo->query("SELECT id, title, file_name, user_id, created_at FROM awards LIMIT 5");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "\nFirst 5 rows:\n";
        print_r($rows);
        
        // Check award_analysis table
        echo "\nTotal rows in 'award_analysis' table: " . $pdo->query("SELECT COUNT(*) FROM award_analysis")->fetchColumn() . "\n";
        
        $stmt = $pdo->query("SELECT award_id, predicted_category, match_percentage FROM award_analysis LIMIT 5");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "\nFirst 5 analysis rows:\n";
        print_r($rows);
        
        // Check join query used in api/awards.php
        echo "\nTesting API Query:\n";
        try {
            // Simplified version of the query we added
            $sql = "
                SELECT a.id, a.title, aa.match_percentage 
                FROM awards a 
                LEFT JOIN award_analysis aa ON a.id = aa.award_id 
                ORDER BY a.created_at DESC
                LIMIT 5
            ";
            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "Query successful. Rows returned: " . count($rows) . "\n";
            print_r($rows);
        } catch (Exception $e) {
            echo "Query failed: " . $e->getMessage() . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

