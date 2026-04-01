<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}
/**
 * CLI: php database/check_login_env.php
 * Shows which DB backend is active and whether lesley can authenticate with Central@2025.
 */
require_once __DIR__ . '/../api/config.php';

$pdo = getDatabaseConnection();
echo "Backend: " . get_class($pdo) . "\n";

if ($pdo instanceof FileBasedDatabase) {
    echo "WARNING: File-based mode is active. MySQL/SQLite failed. Only admin/admin123 and user/user123 work.\n";
    echo "Fix: Start MySQL in XAMPP and ensure database 'lilac' exists (import database/lilac.sql).\n";
    exit(1);
}

if (!($pdo instanceof PDO)) {
    echo "Unknown connection type.\n";
    exit(1);
}

$stmt = $pdo->query("SELECT id, username, email, status, LEFT(password_hash, 30) as hash_prefix FROM users WHERE username IN ('lesley', 'admin')");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Users (lesley, admin):\n";
print_r($rows);

$stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
$stmt->execute(['lesley']);
$lesley = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$lesley) {
    echo "User 'lesley' not found. Import database/lilac.sql or create the user.\n";
    exit(1);
}

$ok = password_verify('Central@2025', $lesley['password_hash']);
echo "password_verify('Central@2025', lesley): " . ($ok ? "MATCH\n" : "NO MATCH — run database/fix_lesley_password_central2025.sql\n");
