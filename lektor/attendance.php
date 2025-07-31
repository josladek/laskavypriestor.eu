<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is instructor
$user = getCurrentUser();
if (!$user || $user['role'] !== 'lektor') {
    header('Location: ../pages/login.php');
    exit;
}

// Redirect to admin attendance with instructor filter
header("Location: ../admin/attendance.php");
exit;
?>