<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/qr_generator.php';
require_once '../config/payment_config.php';


requireLogin();
$currentUser = getCurrentUser();

$requestId = $_GET['request_id'] ?? '';

if (empty($requestId)) {
    $_SESSION['flash_message'] = 'Neplatná požiadavka na platbu za lekciu.';
    $_SESSION['flash_type'] = 'error';
    header('Location: /pages/classes.php');
    exit;
}

// Get payment request details
$paymentRequest = db()->fetch("
    SELECT pr.*, yc.name as class_name, yc.date, yc.time_start, yc.time_end, yc.type, yc.level
    FROM payment_requests pr
    LEFT JOIN yoga_classes yc ON pr.class_id = yc.id
    WHERE pr.id = ? AND pr.user_id = ?
", [$requestId, $currentUser['id']]);

if (!$paymentRequest) {
    $_SESSION['flash_message'] = 'Požiadavka na platbu za lekciu nebola nájdená.';
    $_SESSION['flash_type'] = 'error';
    header('Location: /pages/classes.php');
    exit;
}

// Generate QR code data
$qrMessage = $paymentRequest['notes'];
$qrCodeData = generateQRPaymentString($paymentRequest['amount'], $paymentRequest['variable_symbol'], $qrMessage);

// Get studio info from settings
$studioInfo = getStudioInfo();

$pageTitle = 'Pokyny na úhradu lekcie';
$currentPage = 'class-payment';

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-sage shadow">
                <div class="card-header bg-sage text-white text-center">
                    <h4 class="mb-0"><i class="fas fa-credit-card me-2"></i>Pokyny na úhradu lekcie</h4>
                </div>
                <div class="card-body">
                    <!-- Class Info -->
                    <div class="alert alert-light border-sage mb-4">
                        <h5 class="text-sage mb-3"><i class="fas fa-yoga me-2"></i>Detail lekcie</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Lekcia:</strong> <?= htmlspecialchars($paymentRequest['class_name']) ?></p>
                                <p><strong>Typ:</strong> <?= htmlspecialchars($paymentRequest['type']) ?></p>
                                <p><strong>Úroveň:</strong> <?= htmlspecialchars($paymentRequest['level']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Dátum:</strong> <?= date('d.m.Y', strtotime($paymentRequest['date'])) ?></p>
                                <p><strong>Čas:</strong> <?= date('H:i', strtotime($paymentRequest['time_start'])) ?> - <?= date('H:i', strtotime($paymentRequest['time_end'])) ?></p>
                                <p><strong>Cena za lekciu:</strong> <span class="text-sage fs-4"><?= number_format($paymentRequest['amount'], 2) ?>€</span></p>
                                <!-- <p><strong>Stav:</strong> <span class="badge bg-warning">Čaká na úhradu</span></p>-->
                            </div>
                        </div>
                    </div>

                    <!-- Instructions -->
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle me-2"></i>Pokyny na úhradu</h6>
                        <ol class="mb-0">
                            <li>Uskutočnite platbu pomocou vyššie uvedených údajov</li>
                            <li>Platba bude spracovaná do 24 hodín</li>
                        </ol>
                    </div>
                    
                    <div class="row">
                        <!-- Payment Details -->
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-university me-2"></i>Platobné údaje</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm mb-0">
                                        <tr>
                                            <td><strong>IBAN:</strong></td>
                                            <td><?= htmlspecialchars($studioInfo['iban']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Suma:</strong></td>
                                            <td><strong><?= number_format($paymentRequest['amount'], 2) ?> EUR</strong></td>
                                        </tr>
                                        <tr class="table-warning">
                                            <td><strong>Variabilný symbol:</strong></td>
                                            <td><strong class="text-danger"><?= htmlspecialchars($paymentRequest['variable_symbol']) ?></strong></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Správa:</strong></td>
                                            <td><?= htmlspecialchars($qrMessage) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Banka:</strong></td>
                                            <td><?= htmlspecialchars($studioInfo['bank_name']) ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- QR Code -->
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-qrcode me-2"></i>QR kód pre Pay by Square</h6>
                                </div>
                                <div class="card-body text-center">
                                    <?= displayPaymentQRCode($qrCodeData, 'img-fluid') ?>
                                    <p class="text-muted small mt-2">Naskenujte kód mobilnou bankovnou aplikáciou</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Alternative Payment -->
                    <div style="background: #e8f0e5; border: 2px solid #8db3a0; padding: 20px; border-radius: 8px; margin: 20px 0;">
                        <h5 style="color: #8db3a0; margin-top: 0;"><i class="fas fa-hand-holding-usd me-2"></i>Alternatíva platby</h5>
                        <p style="margin-bottom: 0;"><strong>Platbu môžete uhradiť aj v hotovosti v štúdiu pred lekciou.</strong></p>
                        <p style="margin-bottom: 0;">Jednoducho príďte 10 minút skôr a uhraďte registráciu na recepcii.</p>
                    </div>

                    <!-- Warning -->
                    <div class="alert alert-warning">
                        <strong><i class="fas fa-exclamation-triangle me-2"></i>Dôležité:</strong> 
                        Bez správneho variabilného symbolu nebude možné platbu spárovať s vaším účtom a registráciou.
                    </div>

                    <!-- Action Buttons -->
                    <div class="text-center mt-4">
                        <a href="/pages/classes.php" class="btn btn-sage me-2">
                            <i class="fas fa-arrow-left me-2"></i>Späť na lekcie
                        </a>
                        <a href="/pages/my-classes.php" class="btn btn-outline-sage">
                            <i class="fas fa-list me-2"></i>Moje lekcie
                        </a>
                    </div>

                    <!-- Contact Info -->
                    <div class="mt-4 text-center">
                        <p class="text-muted">
                            V prípade otázok nás kontaktujte na 
                            <a href="mailto:info@laskavypriestor.eu">info@laskavypriestor.eu</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>