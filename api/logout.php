<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all session variables
$_SESSION = array();

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Start new session for flash message  
session_start();
$_SESSION['flash_message'] = 'Boli ste úspešne odhlásený.';
$_SESSION['flash_type'] = 'success';

// Redirect to home page
header('Location: ../index.php');
exit;
?>