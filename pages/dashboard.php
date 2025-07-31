<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect based on user role
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();
switch ($user['role']) {
    case 'admin':
        header('Location: ../admin/');
        break;
    case 'lektor':
        header('Location: ../lektor/index.php');
        break;
    case 'klient':
    default:
        header('Location: index.php');
        break;
}
exit;
?>