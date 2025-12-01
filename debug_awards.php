<?php
require 'api/config.php';
$pdo = getDatabaseConnection();
if (!$pdo) { echo "no db"; exit; }
if ($pdo instanceof FileBasedDatabase) { echo "FileBased"; exit; }
$rows = $pdo->query('SELECT id, title, user_id, status, deleted_at FROM awards')->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT);
?>
