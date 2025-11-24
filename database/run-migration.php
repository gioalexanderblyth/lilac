<?php
/**
 * Database Migration Runner
 * Run this script to apply the unified file upload system migration
 * 
 * Usage: Navigate to http://localhost/lilac/database/run-migration.php in your browser
 * Or run: php database/run-migration.php from command line
 */

session_start();

// Only allow authenticated users (or you can remove this check for initial setup)
if (!isset($_SESSION['user_id'])) {
    // For initial setup, you might want to allow this without auth
    // Uncomment the line below if you want to require authentication
    // die('Please login first');
}

require_once __DIR__ . '/../api/config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration - Unified File Upload System</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #137fec;
            margin-bottom: 10px;
        }
        .status {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            border: 1px solid #dee2e6;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #137fec;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #0f66bc;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Database Migration Runner</h1>
        <p>Unified File Upload System Migration</p>
        
        <?php
        try {
            $pdo = getDatabaseConnection();
            
            if ($pdo instanceof FileBasedDatabase) {
                echo '<div class="status error">';
                echo '<strong>Error:</strong> MySQL database connection required. This migration cannot run with file-based fallback.';
                echo '</div>';
                exit;
            }
            
            // Read migration file
            $migrationFile = __DIR__ . '/migration_unified_upload.sql';
            
            if (!file_exists($migrationFile)) {
                throw new Exception('Migration file not found: ' . $migrationFile);
            }
            
            $sql = file_get_contents($migrationFile);
            
            // Remove comments and split by semicolons
            $sql = preg_replace('/--.*$/m', '', $sql);
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                function($stmt) {
                    return !empty($stmt) && !preg_match('/^(SET|START|COMMIT|\/\*)/i', $stmt);
                }
            );
            
            echo '<div class="status info">';
            echo '<strong>Starting migration...</strong><br>';
            echo 'Database: ' . $pdo->query('SELECT DATABASE()')->fetchColumn() . '<br>';
            echo 'Statements to execute: ' . count($statements);
            echo '</div>';
            
            // Note: DDL statements (ALTER TABLE, etc.) auto-commit in MySQL
            // So we don't need transactions, but we'll track errors
            
            $executed = 0;
            $errors = [];
            
            foreach ($statements as $index => $statement) {
                if (empty(trim($statement))) continue;
                
                try {
                    $pdo->exec($statement);
                    $executed++;
                    echo '<div class="status success">';
                    echo '✓ Statement ' . ($index + 1) . ' executed successfully';
                    echo '</div>';
                } catch (PDOException $e) {
                    // Check if error is because column/index already exists
                    $errorCode = $e->getCode();
                    $errorMsg = $e->getMessage();
                    
                    if (strpos($errorMsg, 'Duplicate column name') !== false || 
                        strpos($errorMsg, 'Duplicate key name') !== false ||
                        strpos($errorMsg, 'already exists') !== false ||
                        strpos($errorMsg, 'Duplicate foreign key') !== false) {
                        echo '<div class="status info">';
                        echo '⚠ Statement ' . ($index + 1) . ' skipped (already exists): ' . substr($errorMsg, 0, 100);
                        echo '</div>';
                    } else {
                        $errors[] = [
                            'statement' => $index + 1,
                            'error' => $errorMsg
                        ];
                        echo '<div class="status error">';
                        echo '✗ Statement ' . ($index + 1) . ' failed: ' . htmlspecialchars($errorMsg);
                        echo '</div>';
                    }
                }
            }
            
            if (empty($errors)) {
                echo '<div class="status success">';
                echo '<strong>Migration completed successfully!</strong><br>';
                echo 'Executed: ' . $executed . ' statements<br>';
                echo '</div>';
                
                // Verify migration
                echo '<h2>Verification</h2>';
                
                $checks = [
                    'other_documents' => ['award_id', 'source_page'],
                    'events' => ['document_id'],
                    'award_analysis' => ['source_page', 'document_id']
                ];
                
                foreach ($checks as $table => $columns) {
                    try {
                        $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
                        $existingColumns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
                        
                        foreach ($columns as $column) {
                            if (in_array($column, $existingColumns)) {
                                echo '<div class="status success">';
                                echo "✓ Column `$table`.`$column` exists";
                                echo '</div>';
                            } else {
                                echo '<div class="status error">';
                                echo "✗ Column `$table`.`$column` NOT found";
                                echo '</div>';
                            }
                        }
                    } catch (PDOException $e) {
                        echo '<div class="status error">';
                        echo "✗ Could not verify table `$table`: " . $e->getMessage();
                        echo '</div>';
                    }
                }
                
            } else {
                echo '<div class="status error">';
                echo '<strong>Migration completed with errors!</strong><br>';
                echo 'Errors: ' . count($errors) . '<br>';
                echo 'Some statements may have failed, but others may have succeeded.';
                echo '</div>';
            }
            
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo '<div class="status error">';
            echo '<strong>Fatal Error:</strong> ' . htmlspecialchars($e->getMessage());
            echo '</div>';
        }
        ?>
        
        <a href="../dashboard.php" class="btn">Return to Dashboard</a>
    </div>
</body>
</html>

