<?php
// Globálny error handler pre PHP aplikáciu

// Zapnutie error reportingu iba pre debug
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Vlastný error handler
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $errorTypes = [
        E_ERROR => 'Fatal Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Strict Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];
    
    $errorType = isset($errorTypes[$errno]) ? $errorTypes[$errno] : 'Unknown Error';
    
    // Log error
    $logMessage = "[$errorType] $errstr in $errfile on line $errline";
    error_log($logMessage);
    
    // Ak je debug mode, zobraz chybu
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        echo "<div style='background: #ffebee; border: 1px solid #f44336; padding: 10px; margin: 10px; border-radius: 4px;'>";
        echo "<strong>$errorType:</strong> $errstr<br>";
        echo "<strong>File:</strong> $errfile<br>";
        echo "<strong>Line:</strong> $errline";
        echo "</div>";
    }
    
    // Pre fatálne chyby, ukončenie
    if ($errno == E_ERROR || $errno == E_CORE_ERROR || $errno == E_COMPILE_ERROR) {
        if (!defined('DEBUG_MODE') || !DEBUG_MODE) {
            // Production friendly error page
            include __DIR__ . '/../pages/error.php';
        }
        exit;
    }
    
    return true;
}

// Exception handler
function customExceptionHandler($exception) {
    $message = "Uncaught exception: " . $exception->getMessage();
    $file = $exception->getFile();
    $line = $exception->getLine();
    
    error_log("$message in $file on line $line");
    
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        echo "<div style='background: #ffebee; border: 1px solid #f44336; padding: 15px; margin: 10px; border-radius: 4px;'>";
        echo "<h3>Uncaught Exception</h3>";
        echo "<strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "<br>";
        echo "<strong>File:</strong> " . htmlspecialchars($exception->getFile()) . "<br>";
        echo "<strong>Line:</strong> " . $exception->getLine() . "<br>";
        echo "<strong>Stack trace:</strong><pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
        echo "</div>";
    } else {
        // Production friendly error page
        include __DIR__ . '/../pages/error.php';
    }
    
    exit;
}

// Shutdown function pre fatalne chyby
function shutdownFunction() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        if (!defined('DEBUG_MODE') || !DEBUG_MODE) {
            // Production friendly error page
            include __DIR__ . '/../pages/error.php';
        }
    }
}

// Registrácia error handlerov
set_error_handler('customErrorHandler');
set_exception_handler('customExceptionHandler');
register_shutdown_function('shutdownFunction');
?>