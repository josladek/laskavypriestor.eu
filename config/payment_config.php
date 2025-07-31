<?php
// Payment configuration for manual payment system

// Bank account details for payments
define('BANK_ACCOUNT_NUMBER', '1234567890/0800');
define('BANK_NAME', 'Slovenská sporiteľňa');
define('BANK_SWIFT', 'GIBASKBX');
define('BANK_IBAN', 'SK73 1100 0000 0026 1293 8533');

// Payment details
define('COMPANY_NAME', 'Láskavý Priestor s.r.o.');
define('COMPANY_ADDRESS', 'Yogová ulica 123, 811 02 Bratislava');
define('COMPANY_ICO', '12345678');
define('COMPANY_DIC', '2023456789');

// Email settings for payment notifications
define('PAYMENT_EMAIL_FROM', 'info@laskavypriestor.eu');
define('PAYMENT_EMAIL_SUBJECT', 'Pokyny na úhradu kreditu - Láskavý Priestor');

// SMTP settings
define('SMTP_HOST', 'smtp.forpsi.com');
define('SMTP_PORT', 465);
define('SMTP_USERNAME', 'info@laskavypriestor.eu');
define('SMTP_PASSWORD', 'aeoW&z9sqk');
define('SMTP_SECURE', 'ssl');

// Payment statuses
define('PAYMENT_STATUS_PENDING', 'pending');
define('PAYMENT_STATUS_APPROVED', 'approved');
define('PAYMENT_STATUS_REJECTED', 'rejected');
define('PAYMENT_STATUS_CANCELLED', 'cancelled');

// QR code payment string template (Slovak QR payment standard)
// Note: generateQRPaymentString() function is defined in includes/qr_generator.php

// Generate unique variable symbol with timestamp and sequence
function generateVariableSymbol($userId, $packageId) {
    // First, check if a variable symbol already exists and ensure uniqueness
    $baseSymbol = generateBaseVariableSymbol($userId, $packageId);
    
    // Check database for existing variable symbol
    try {
        $db = Database::getInstance();
        $existing = $db->fetch("SELECT id FROM payment_requests WHERE variable_symbol = ?", [$baseSymbol]);
        
        if ($existing) {
            // If exists, add microseconds to make it unique
            $microtime = substr(microtime(true) * 1000000, -6); // Last 6 digits of microseconds
            $uniqueSymbol = $baseSymbol . substr($microtime, -2); // Add last 2 digits
            
            // Double check uniqueness (highly unlikely to collide)
            $counter = 1;
            while ($db->fetch("SELECT id FROM payment_requests WHERE variable_symbol = ?", [$uniqueSymbol])) {
                $uniqueSymbol = $baseSymbol . str_pad($counter, 2, '0', STR_PAD_LEFT);
                $counter++;
                if ($counter > 99) break; // Safety break
            }
            
            return $uniqueSymbol;
        }
    } catch (Exception $e) {
        // If database error, just return base symbol
        error_log("Error checking variable symbol uniqueness: " . $e->getMessage());
    }
    
    return $baseSymbol;
}

// Generate base variable symbol from user ID and timestamp
function generateBaseVariableSymbol($userId, $packageId) {
    // Create exactly 10-digit numeric variable symbol: USERID(4) + MMDD(4) + PACKAGE(1) + RANDOM(1)
    $month = date('m');
    $day = date('d');
    $paddedUserId = str_pad($userId, 4, '0', STR_PAD_LEFT); // Exactly 4 digits for user ID
    $paddedPackageId = str_pad($packageId, 1, '0', STR_PAD_LEFT); // 1 digit for package
    $randomDigit = rand(0, 9); // Random digit for uniqueness
    
    $baseSymbol = $paddedUserId . $month . $day . $paddedPackageId . $randomDigit;
    
    // Ensure exactly 10 digits
    if (strlen($baseSymbol) > 10) {
        $baseSymbol = substr($baseSymbol, 0, 10);
    } elseif (strlen($baseSymbol) < 10) {
        $baseSymbol = str_pad($baseSymbol, 10, '0', STR_PAD_RIGHT);
    }
    
    return $baseSymbol;
}
?>