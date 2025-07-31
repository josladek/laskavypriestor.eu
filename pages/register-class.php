<?php
// Class registration handler in pages/ directory (moved from api/)
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/email_functions.php';
require_once __DIR__ . '/../includes/qr_generator.php';
require_once __DIR__ . '/../config/payment_config.php';

// Session je už spustená v config.php a auth.php
$currentUser = getCurrentUser();

if (!$currentUser) {
    $_SESSION['flash_message'] = 'Pre registráciu na lekciu sa musíte prihlásiť.';
    $_SESSION['flash_type'] = 'error';
    header('Location: ' . url('pages/login.php'));
    exit;
}

// Handle POST requests only - GET should go through confirmation page
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('pages/classes.php'));
    exit;
}

$classId = (int)($_POST['class_id'] ?? 0);
$confirmed = isset($_POST['confirmed']) && $_POST['confirmed'] === '1';
$notes = trim($_POST['notes'] ?? '');

if (!$confirmed) {
    $_SESSION['flash_message'] = 'Registrácia nebola potvrdená.';
    $_SESSION['flash_type'] = 'error';
    header('Location: ' . url('pages/classes.php'));
    exit;
}

if (!$classId) {
    $_SESSION['flash_message'] = 'Neplatné ID lekcie.';
    $_SESSION['flash_type'] = 'error';
    header('Location: ' . url('pages/classes.php'));
    exit;
}

try {
    // Get class details
    $class = db()->fetch("
        SELECT yc.*, COUNT(r.id) as registered_count
        FROM yoga_classes yc
        LEFT JOIN registrations r ON yc.id = r.class_id AND r.status IN ('confirmed', 'pending')
        WHERE yc.id = ?
        GROUP BY yc.id
    ", [$classId]);
    
    if (!$class) {
        throw new Exception('Lekcia nenájdená.');
    }
    
    // Check if registration is still allowed
    if (!canRegisterForClass($classId)) {
        throw new Exception('Registrácia na túto lekciu je už uzavretá. Registrovať sa môžete len do času konania lekcie.');
    }
    
    // Check if class is full
    if ($class['registered_count'] >= $class['capacity']) {
        throw new Exception('Lekcia je plná.');
    }
    
    // Check if user is already registered
    $existing = db()->fetch("
        SELECT id FROM registrations 
        WHERE class_id = ? AND user_id = ? AND status IN ('confirmed', 'pending')
    ", [$classId, $currentUser['id']]);
    if ($existing) {
        throw new Exception('Na túto lekciu ste už prihlásený.');
    }
    
    // Check if this is a standalone class (not part of course)
    if ($class['course_id']) {
        throw new Exception('Nemôžete sa prihlásiť na lekciu z kurzu samostatne. Prihláste sa na celý kurz.');
    }
    
    // Automatic payment method selection
    $price = $class['price'];
    $useCredit = false;
    $paymentMethod = 'cash';
    $registrationStatus = 'confirmed';
    
    // For clients, automatically use credit if they have enough balance
    if ($currentUser['role'] === 'klient') {
        $freshUser = db()->fetch("SELECT eur_balance FROM users WHERE id = ?", [$currentUser['id']]);
        $eurBalance = $freshUser ? (float)$freshUser['eur_balance'] : 0;
        $creditPrice = $class['price_with_credit'];
        
        if ($eurBalance >= $creditPrice) {
            // Use credit automatically
            $price = $creditPrice;
            $useCredit = true;
            $paymentMethod = 'credit';
        } else {
            // Insufficient credit - set as pending and create payment request
            $registrationStatus = 'pending';
        }
    }
    
    // Start transaction
    db()->beginTransaction();
    
    $variableSymbol = null;
    $paymentRequestId = null;
    
    if ($useCredit) {
        // Deduct credit for credit payments
        $success = deductCredit($currentUser['id'], $price, 'class_payment', $classId);
        if (!$success) {
            throw new Exception('Chyba pri odpočítaní kreditu.');
        }
    } else {
        // For cash payments, create payment request like credit purchase
        $variableSymbol = generateVariableSymbol($currentUser['id'], 1);
        
        // Generate QR code data
        $qrMessage = "Platba za lekciu " . $class['name'] . " - " . $currentUser['name'];
        $qrCodeData = generateQRPaymentString($price, $variableSymbol, $qrMessage);
        
        // Create payment request
        $paymentRequestId = db()->query("
            INSERT INTO payment_requests (user_id, package_id, amount, eur_amount, variable_symbol, payment_method, qr_code_data, notes, class_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $currentUser['id'],
            'class_payment',
            $price,
            $price,
            $variableSymbol,
            'bank_transfer',
            $qrCodeData,
            "Platba za lekciu " . $class['name'],
            $classId
        ]);
    }
    
    // Create class registration with notes
    $registrationId = db()->query("
        INSERT INTO registrations (class_id, user_id, status, paid_with_credit, variable_symbol, notes)
        VALUES (?, ?, ?, ?, ?, ?)
    ", [$classId, $currentUser['id'], $registrationStatus, $useCredit ? 1 : 0, $variableSymbol, $notes]);
    
    db()->commit();
    
    // Handle different payment methods
    if ($useCredit) {
        $savings = $class['price'] - $price;
        $_SESSION['flash_message'] = "Úspešne ste sa prihlásili na lekciu \"{$class['name']}\" za " . formatPrice($price) . " z kreditu. Váš zostatok kreditu je " . formatPrice($eurBalance - $price);
            
        $_SESSION['flash_type'] = 'success';
        header('Location: ' . url('pages/classes.php'));
        exit;
    } else {
        // For cash payments, send email and redirect to payment confirmation like credit purchase
        if ($currentUser['email'] && $paymentRequestId) {
            // Create package data for email template
            $packageData = [
                'name' => 'Platba za lekciu: ' . $class['name'],
                'price' => $price,
                'amount' => $price
            ];
            
            try {
                sendPaymentInstructionEmail($currentUser, $packageData, $variableSymbol, $qrCodeData);
            } catch (Exception $e) {
                error_log("Failed to send class payment email: " . $e->getMessage());
            }
        }
        
        $_SESSION['flash_message'] = 'Úspešne ste sa prihlásili na lekciu. Informácie o úhrade boli odoslané na Váš email.' ;
        $_SESSION['flash_type'] = 'success';
        
        // Redirect to payment confirmation page like credit purchase
        header('Location: /pages/class-payment-confirmation.php?request_id=' . $paymentRequestId);
        exit;
    }
    exit;
    
} catch (Exception $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    
    $_SESSION['flash_message'] = $e->getMessage();
    $_SESSION['flash_type'] = 'error';
    
    // Redirect back to classes list
    header('Location: ' . url('pages/classes.php'));
    exit;
}
?>