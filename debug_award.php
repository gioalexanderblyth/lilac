<?php
require_once 'api/config.php';
header('Content-Type: text/plain');

try {
    $pdo = getDatabaseConnection();
    $id = 128; // From your log
    
    echo "Checking Award ID: $id\n\n";
    
    // 1. Check Award
    $stmt = $pdo->prepare("SELECT * FROM awards WHERE id = ?");
    $stmt->execute([$id]);
    $award = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($award);
    
    // 2. Check Analysis
    echo "\nAnalysis:\n";
    $stmt = $pdo->prepare("SELECT * FROM award_analysis WHERE award_id = ?");
    $stmt->execute([$id]);
    $analysis = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($analysis);
    
    // 3. Check specific columns
    echo "\nColumns in award_analysis:\n";
    try {
        $cols = $pdo->query("PRAGMA table_info(award_analysis)")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $col) echo $col['name'] . "\n";
    } catch (Exception $e) {
        $cols = $pdo->query("SHOW COLUMNS FROM award_analysis")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $col) echo $col['Field'] . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
