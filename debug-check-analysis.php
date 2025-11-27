<?php
require_once __DIR__ . '/api/config.php';

try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->query("SELECT id, event_id, detected_text, created_at FROM award_analysis ORDER BY id DESC LIMIT 5");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Recent Analysis Records:\n";
    foreach ($rows as $row) {
        echo "ID: " . $row['id'] . " | Event ID: " . ($row['event_id'] ?? 'NULL') . " | Date: " . $row['created_at'] . "\n";
        echo "Text snippet: " . substr($row['detected_text'], 0, 50) . "...\n\n";
    }
    
    if (empty($rows)) {
        echo "No analysis records found.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

