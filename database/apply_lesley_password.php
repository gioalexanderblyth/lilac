<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}
/**
 * One-time: set lesley's password to Central@2025 (matches docs/README.md).
 * CLI: php database/apply_lesley_password.php
 */
require_once __DIR__ . '/../api/config.php';

$pdo = getDatabaseConnection();
if ($pdo instanceof FileBasedDatabase) {
    fwrite(STDERR, "MySQL required. Start XAMPP MySQL and configure api/config.php.\n");
    exit(1);
}

$password = 'Central@2025';
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE username = ?');
$stmt->execute([$hash, 'lesley']);
echo 'Updated rows: ' . $stmt->rowCount() . "\n";
if ($stmt->rowCount() === 0) {
    echo "No user named 'lesley'. Create one or import database/lilac.sql\n";
    exit(1);
}

$stmt = $pdo->prepare('SELECT username FROM users WHERE username = ?');
$stmt->execute(['lesley']);
$u = $stmt->fetch(PDO::FETCH_ASSOC);
echo "OK: lesley password is now Central@2025 (verify login at index.php)\n";
