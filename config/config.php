<?php
// Hlavná konfigurácia aplikácie

// Bezpečnostné nastavenia
define('SECRET_KEY', 'your-secret-key-change-this-in-production');
define('SESSION_NAME', 'laskavypriestor_session');

// API kľúče
define('STRIPE_SECRET_KEY', 'sk_test_your_stripe_secret_key_here');
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_your_stripe_publishable_key_here');
define('SENDGRID_API_KEY', 'SG.your_sendgrid_api_key_here');

// Email nastavenia
define('FROM_EMAIL', 'info@laskavypriestor.eu');
define('FROM_NAME', 'Láskavý Priestor');

// Upload nastavenia
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// URL nastavenia
define('BASE_URL', 'https://www.laskavypriestor.eu');
define('SITE_NAME', 'Láskavý Priestor');

// Timezone
date_default_timezone_set('Europe/Bratislava');

// Error reporting pre development  
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Session nastavenia (len ak session nie je aktívna)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 1); // HTTPS je aktívne na produkčnom serveri
    
    // Spustenie session
    session_name(SESSION_NAME);
    session_start();
}

// Načítanie databázového pripojenia a funkcií
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';
// Auth.php sa načítava v stránkach ktoré ho potrebujú
?>