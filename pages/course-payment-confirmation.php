<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/qr_generator.php';
require_once __DIR__ . '/../includes/email_functions.php';

// Must be logged in
if (!isLoggedIn()) {
    header('Location: ' . url('pages/login.php'));
    exit;
}

$currentUser = getCurrentUser();

// Get payment request from URL parameter (like lessons do)
$requestId = (int)($_GET['request_id'] ?? 0);

if (!$requestId) {
    $_SESSION['flash_message'] = 'Platobná požiadavka nenájdená.';
    $_SESSION['flash_type'] = 'error';
    header('Location: ' . url('pages/courses.php'));
    exit;
}

// Get payment request details
$paymentRequest = db()->fetch("
    SELECT pr.*, c.name as course_name, u.name as user_name, u.email as user_email
    FROM payment_requests pr
    LEFT JOIN courses c ON pr.course_id = c.id  
    LEFT JOIN users u ON pr.user_id = u.id
    WHERE pr.id = ? AND pr.user_id = ?
", [$requestId, $currentUser['id']]);

if (!$paymentRequest) {
    $_SESSION['flash_message'] = 'Platobná požiadavka nenájdená alebo nemáte oprávnenie na jej zobrazenie.';
    $_SESSION['flash_type'] = 'error';
    header('Location: ' . url('pages/courses.php'));
    exit;
}

// Convert to the format expected by the rest of the code
$paymentData = [
    'type' => 'course',
    'course_id' => $paymentRequest['course_id'],
    'course_name' => $paymentRequest['course_name'],
    'registration_id' => $requestId, // Use request ID  
    'payment_request_id' => $requestId,
    'amount' => $paymentRequest['amount'],
    'variable_symbol' => $paymentRequest['variable_symbol'],
    'user_name' => $paymentRequest['user_name'],
    'user_email' => $paymentRequest['user_email']
];

// Get studio info for QR code
$studioInfo = getStudioInfo();

try {
    // Generate QR code data string for payment
    $qrDataString = generateQRPaymentString(
        $paymentData['amount'],
        $paymentData['variable_symbol'],
        'Kurz: ' . $paymentData['course_name']
    );
    
    // Update payment request with QR data
    db()->query("
        UPDATE payment_requests 
        SET qr_code_data = ?, updated_at = NOW() 
        WHERE id = ?
    ", [$qrDataString, $paymentData['payment_request_id']]);
    
    // Generate QR code HTML for display
    $qrData = displayPaymentQRCode($qrDataString, 'img-fluid');
    
    // Debug: Log QR generation for troubleshooting
    error_log("QR Data String: " . $qrDataString);
    error_log("QR HTML length: " . strlen($qrData));
    
    // Generate QR code base64 for email
    $qrBase64 = generatePaymentQRCodeDataUrl($qrDataString);
    if ($qrBase64) {
        // Extract base64 part from data URL
        $qrBase64 = str_replace('data:image/png;base64,', '', $qrBase64);
    }
    
    // Send email notification
    $emailSent = sendCoursePaymentEmail([
        'user_email' => $paymentData['user_email'],
        'user_name' => $paymentData['user_name'],
        'course_name' => $paymentData['course_name'],
        'amount' => $paymentData['amount'],
        'variable_symbol' => $paymentData['variable_symbol'],
        'qr_data' => $qrBase64,
        'studio_info' => $studioInfo
    ]);
    
} catch (Exception $e) {
    error_log("Course payment confirmation error: " . $e->getMessage());
    $qrData = null;
    $emailSent = false;
}

// No session data to clear since we use URL parameters

$pageTitle = 'Potvrdenie registrácie na kurz';
$currentPage = 'courses';
?>

<?php include '../includes/header.php'; ?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-sage text-white text-center">
                    <h3 class="mb-0"><i class="fas fa-check-circle me-2"></i>Registrácia úspešná!</h3>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-success mb-4">
                        <h5><i class="fas fa-graduation-cap me-2"></i><?= htmlspecialchars($paymentData['course_name']) ?></h5>
                        <p class="mb-0">Vaša registrácia na kurz bola úspešne vytvorená. Pre potvrdenie účasti je potrebné uhradiť poplatok.</p>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <h5><i class="fas fa-info-circle me-2 text-sage"></i>Detaily platby</h5>
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>Suma:</strong></td>
                                    <td><?= formatPrice($paymentData['amount']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Variabilný symbol:</strong></td>
                                    <td><code><?= htmlspecialchars($paymentData['variable_symbol']) ?></code></td>
                                </tr>
                                <tr>
                                    <td><strong>IBAN:</strong></td>
                                    <td><code><?= htmlspecialchars($studioInfo['banka'] ?? 'SK1234567890123456789012') ?></code></td>
                                </tr>
                                <tr>
                                    <td><strong>Príjemca:</strong></td>
                                    <td><?= htmlspecialchars($studioInfo['nazov'] ?? 'Láskavý Priestor') ?></td>
                                </tr>
                            </table>
                        </div>

                        <?php if ($qrData): ?>
                        <div class="col-md-6 text-center mb-4">
                            <h5><i class="fas fa-qrcode me-2 text-sage"></i>QR kód pre platbu</h5>
                            <div class="qr-container mb-3">
                                <?= $qrData ?>
                            </div>
                            <p class="small text-muted">Naskenujte QR kód v mobile bankingu</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="alert alert-info">
                        <h6><i class="fas fa-envelope me-2"></i>Email s potvrdzovacími údajmi</h6>
                        <?php if ($emailSent): ?>
                            <p class="mb-0">Na vašu emailovú adresu <strong><?= htmlspecialchars($paymentData['user_email']) ?></strong> 
                            sme odoslali podrobné pokyny na platbu vrátane QR kódu.</p>
                        <?php else: ?>
                            <p class="mb-0 text-warning">Nepodarilo sa odoslať email s pokynmi. Prosím, uložte si tieto platobné údaje.</p>
                        <?php endif; ?>
                    </div>

                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Dôležité upozornenie</h6>
                        <ul class="mb-0">
                            <li>Platbu prosím uhraďte do <strong>24 hodín</strong> od registrácie</li>
                            <li>Neuhradené registrácie budú automaticky zrušené</li>
                            <li>Po uhradení platby vám príde email s potvrdením</li>
                            <li>Pri problémoch s platbou nás kontaktujte</li>
                        </ul>
                    </div>

                    <div class="text-center mt-4">
                        <a href="<?= url('pages/courses.php') ?>" class="btn btn-sage">
                            <i class="fas fa-arrow-left me-2"></i>Späť na kurzy
                        </a>
                        <a href="<?= url('pages/dashboard.php') ?>" class="btn btn-outline-sage ms-2">
                            <i class="fas fa-tachometer-alt me-2"></i>Môj účet
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>