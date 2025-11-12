<?php
/**
 * Awards Router - Redirects to appropriate awards page based on user role
 */
session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user = $_SESSION['user'];
$isAdmin = $user['role'] === 'admin';

// Redirect based on role
if ($isAdmin) {
    header('Location: admin-awards.php');
} else {
    header('Location: user-awards.php');
}
exit();
