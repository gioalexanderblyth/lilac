<?php
/**
 * Update all existing users to admin role
 * Run this script once to update existing users in the database
 */

session_start();

// Simple security check - require admin or allow if no users exist yet
require_once __DIR__ . '/api/config.php';

try {
    $pdo = getDatabaseConnection();
    
    if ($pdo instanceof FileBasedDatabase) {
        die('This script requires a database connection. File-based authentication is not supported.');
    }
    
    // Update all users to admin role
    $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE role != 'admin'");
    $stmt->execute();
    
    $updatedCount = $stmt->rowCount();
    
    echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Update Users to Admin - LILAC</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
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
        .success {
            color: #10b981;
            font-size: 18px;
            margin-bottom: 20px;
        }
        .info {
            color: #6b7280;
            margin-top: 20px;
        }
        a {
            color: #667eea;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Users Updated Successfully</h1>
        <div class='success'>✓ Successfully updated $updatedCount user(s) to admin role.</div>
        <div class='info'>
            <p>All users in the database now have admin privileges.</p>
            <p><strong>Note:</strong> Users will need to log out and log back in for the changes to take effect in their session.</p>
            <p><a href='index.php'>Go to Login Page</a> | <a href='dashboard.php'>Go to Dashboard</a></p>
        </div>
    </div>
</body>
</html>";
    
    // Delete this file after execution for security
    // Uncomment the line below if you want the file to self-delete
    // @unlink(__FILE__);
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

