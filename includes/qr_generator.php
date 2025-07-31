<?php
/**
 * QR Code generator for Pay by Square payments
 */

/**
 * Generate QR code image for payment
 */
function generatePaymentQRCode($qrData, $filename) {
    // Simple QR code generation without external library
    // We'll use a basic approach with QR code API service
    
    $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
        'size' => '300x300',
        'data' => $qrData,
        'format' => 'png',
        'bgcolor' => 'ffffff',
        'color' => '000000',
        'qzone' => '2',
        'margin' => '10'
    ]);
    
    // Download QR code image
    $qrCodeImage = file_get_contents($qrCodeUrl);
    
    if ($qrCodeImage === false) {
        return false;
    }
    
    // Save to uploads directory
    $uploadsDir = '../uploads/qr-codes/';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
    
    $filePath = $uploadsDir . $filename;
    $success = file_put_contents($filePath, $qrCodeImage);
    
    return $success ? $filePath : false;
}

/**
 * Generate QR code data URL for inline display
 */
function generatePaymentQRCodeDataUrl($qrData) {
    $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
        'size' => '250x250',
        'data' => $qrData,
        'format' => 'png',
        'bgcolor' => 'ffffff',
        'color' => '000000',
        'qzone' => '2',
        'margin' => '10'
    ]);
    
    $qrCodeImage = file_get_contents($qrCodeUrl);
    
    if ($qrCodeImage === false) {
        return null;
    }
    
    return 'data:image/png;base64,' . base64_encode($qrCodeImage);
}

/**
 * Clean old QR code files (older than 24 hours)
 */
function cleanOldQRCodes() {
    $uploadsDir = '../uploads/qr-codes/';
    if (!is_dir($uploadsDir)) {
        return;
    }
    
    $files = glob($uploadsDir . '*.png');
    $now = time();
    
    foreach ($files as $file) {
        if (is_file($file)) {
            $fileTime = filemtime($file);
            // Delete files older than 24 hours
            if ($now - $fileTime > 24 * 60 * 60) {
                unlink($file);
            }
        }
    }
}

/**
 * Generate QR Payment string for Slovak Pay by Square
 * Using proper Pay by Square standard with external API for proper encoding
 */
function generateQRPaymentString($amount, $variableSymbol, $message = '') {
    // Use QRGenerator.sk API which properly implements Pay by Square standard
    $iban = 'SK7311000000002612938533';
    $amount = number_format((float)$amount, 2, '.', '');
    
    // Build query parameters for proper Pay by Square encoding
    $params = [
        'iban' => $iban,
        'amount' => $amount,
        'currency' => 'EUR',
        'vs' => $variableSymbol
    ];
    
    if (!empty($message)) {
        $params['payment_note'] = substr($message, 0, 60);
    }
    
    // Call QRGenerator.sk API to get proper Pay by Square string
    $apiUrl = 'https://api.qrgenerator.sk/by-square/pay/string?' . http_build_query($params);
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'method' => 'GET',
            'header' => 'User-Agent: LaskavyPriestor/1.0'
        ]
    ]);
    
    $response = @file_get_contents($apiUrl, false, $context);
    
    // Parse JSON response from API
    if ($response !== false) {
        $data = json_decode($response, true);
        if ($data && isset($data['data'])) {
            return $data['data'];
        }
    }
    
    // Fallback to simple format if API fails
    $qrString = 'SPD*1.0*ACC:' . $iban . '*AM:' . $amount . '*CC:EUR*VS:' . $variableSymbol;
    if (!empty($message)) {
        $cleanMessage = preg_replace('/[^a-zA-Z0-9\s\-_.,]/', '', $message);
        $qrString .= '*MSG:' . substr($cleanMessage, 0, 60);
    }
    
    return $qrString;
}

/**
 * Display QR code inline as base64 image
 */
function displayPaymentQRCode($qrData, $cssClass = 'qr-code-image') {
    try {
        $dataUrl = generatePaymentQRCodeDataUrl($qrData);
        
        if ($dataUrl) {
            return '<img src="' . $dataUrl . '" alt="QR kód pre platbu" class="' . $cssClass . '" style="max-width: 250px; height: auto; border: 1px solid #ddd; border-radius: 8px;">';
        } else {
            return '<div class="alert alert-warning">QR kód sa nepodarilo vygenerovať</div>';
        }
    } catch (Exception $e) {
        error_log("Error generating QR code: " . $e->getMessage());
        return '<div class="alert alert-warning">QR kód sa nepodarilo vygenerovať</div>';
    }
}
?>