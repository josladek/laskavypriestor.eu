<?php
// Session is handled by config.php

// Include required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: /pages/login.php');
    exit;
}

$sessionId = $_GET['session_id'] ?? 'demo_session';
$packageId = $_GET['package_id'] ?? '';
$isDemo = isset($_GET['demo']);

if (!$packageId) {
    $_SESSION['flash_message'] = 'Neplatné parametre pre overenie platby.';
    $_SESSION['flash_type'] = 'error';
    header('Location: /pages/buy-credits.php');
    exit;
}

// Package mapping
$packages = [
    'basic' => ['amount' => 50, 'price' => 50.00, 'name' => 'Základný balíček'],
    'standard' => ['amount' => 75, 'price' => 75.00, 'name' => 'Štandardný balíček'],
    'premium' => ['amount' => 100, 'price' => 100.00, 'name' => 'Prémiový balíček']
];

if (!isset($packages[$packageId])) {
    $_SESSION['flash_message'] = 'Neplatný balíček.';
    $_SESSION['flash_type'] = 'error';
    header('Location: /pages/buy-credits.php');
    exit;
}

$package = $packages[$packageId];

try {
    $user = getCurrentUser();
    
    // Pre demo účely - simuluj úspešnú platbu
    if ($isDemo) {
        // Add EUR amount to user's credit balance
        $success = addCredit($user['id'], $package['amount'], 'demo_payment', 'demo_' . time());
        
        if ($success) {
            // Refresh user session data to show updated balance immediately
            $_SESSION['user'] = db()->fetch("SELECT * FROM users WHERE id = ?", [$user['id']]);
            
            $_SESSION['flash_message'] = "DEMO: Úspešne ste dobili kredit o " . $package['amount'] . " € za " . number_format($package['price'], 2) . " €! (Simulovaná platba)";
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Chyba pri pridávaní kreditu do vášho účtu.';
            $_SESSION['flash_type'] = 'error';
        }
    } else {
        $_SESSION['flash_message'] = 'Neplatný typ platby.';
        $_SESSION['flash_type'] = 'error';
    }

} catch (Exception $e) {
    $_SESSION['flash_message'] = 'Chyba pri spracovaní platby: ' . $e->getMessage();
    $_SESSION['flash_type'] = 'error';
}

header('Location: /pages/buy-credits.php');
exit;
?>