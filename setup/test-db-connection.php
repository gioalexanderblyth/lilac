<?php
/**
 * Database Connection Test
 * Run this file to check if database connection is working
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../api/config.php';

echo "<h2>Database Connection Test</h2>";

try {
    $pdo = getDatabaseConnection();
    
    if ($pdo instanceof FileBasedDatabase) {
        echo "<p style='color: orange;'>⚠️ Using File-Based Database Fallback (MySQL/SQLite not available)</p>";
    } else {
        echo "<p style='color: green;'>✅ Database connection successful!</p>";
        echo "<p>Database Type: " . get_class($pdo) . "</p>";
        
        // Try a simple query
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        if ($result) {
            echo "<p style='color: green;'>✅ Test query successful!</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database connection failed!</p>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Check:</p>";
    echo "<ul>";
    echo "<li>Is MySQL running in XAMPP?</li>";
    echo "<li>Does the database 'lilac_awards' exist?</li>";
    echo "<li>Are the credentials in api/config.php correct?</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<h3>Configuration:</h3>";
echo "<p>DB_HOST: " . DB_HOST . "</p>";
echo "<p>DB_NAME: " . DB_NAME . "</p>";
echo "<p>DB_USER: " . DB_USER . "</p>";
echo "<p>DB_PASS: " . (DB_PASS ? '***' : '(empty)') . "</p>";

