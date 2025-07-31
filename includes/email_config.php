<?php
/**
 * Email configuration for the yoga studio application
 * Configure your email settings here
 */

// SMTP Configuration (if using SMTP instead of mail())
define('USE_SMTP', false); // Set to true to use SMTP

// If USE_SMTP is true, configure these:
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'

// Email settings
define('MAIL_FROM_EMAIL', 'info@laskavypriestor.eu');
define('MAIL_FROM_NAME', 'Láskavý Priestor');

/**
 * Validate email address
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Test email sending functionality
 */
function testEmailSending($testEmail = 'test@example.com') {
    $subject = 'Test Email - Láskavý Priestor';
    $body = '<h1>Test Email</h1><p>Ak vidíte tento email, email systém funguje správne.</p>';
    
    return sendEmail($testEmail, $subject, $body);
}

/**
 * Log email sending attempts for debugging
 */
function logEmailAttempt($to, $subject, $success) {
    $status = $success ? 'SUCCESS' : 'FAILED';
    $logMessage = "[EMAIL $status] To: $to, Subject: $subject, Time: " . date('Y-m-d H:i:s');
    error_log($logMessage);
}
?>