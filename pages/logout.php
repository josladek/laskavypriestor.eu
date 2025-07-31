<?php
// Logout handler - clean session and redirect
require_once '../config/config.php';

// Session je už spustená v config.php
// Clear all session data completely
$_SESSION = array();

// Remove session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy session
session_destroy();

// Start fresh session for flash message
session_start();
session_regenerate_id(true);
$_SESSION['flash_message'] = 'Boli ste úspešne odhlásený.';
$_SESSION['flash_type'] = 'success';

// Redirect to main page  
header('Location: ../index.php');
exit;