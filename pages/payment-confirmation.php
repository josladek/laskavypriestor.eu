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
    $_SESSION['flash_message'] = 'Neplatná požiadavka na kredit.';
    $_SESSION['flash_type'] = 'error';
    header('Location: /pages/buy-credits-manual.php');
    exit;
}

// Get payment request details
$paymentRequest = db()->fetch("
    SELECT * FROM payment_requests 
    WHERE id = ? AND user_id = ?
", [$requestId, $currentUser['id']]);

if (!$paymentRequest) {
    $_SESSION['flash_message'] = 'Požiadavka na kredit nebola nájdená.';
    $_SESSION['flash_type'] = 'error';
    header('Location: /pages/buy-credits-manual.php');
    exit;
}

// Package definitions
$packages = [
    'basic' => ['amount' => 50, 'price' => 50.00, 'name' => 'Základný balíček'],
    'standard' => ['amount' => 75, 'price' => 75.00, 'name' => 'Štandardný balíček'],
    'premium' => ['amount' => 100, 'price' => 100.00, 'name' => 'Prémiový balíček']
];

$package = $packages[$paymentRequest['package_id']] ?? null;

if (!$package) {
    $_SESSION['flash_message'] = 'Neplatný balíček kreditu.';
    $_SESSION['flash_type'] = 'error';
    header('Location: /pages/buy-credits-manual.php');
    exit;
}

// Simple QR code fallback
$qrMessage = "Kredit " . $package['name'] . " - " . $currentUser['name'];
$qrCodeHtml = '<div class="alert alert-info">QR kód sa pripravuje...</div>';

// Get studio info from settings
$studioInfo = getStudioInfo();

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-sage shadow">
                <div class="card-header bg-sage text-white text-center">
                    <h4 class="mb-0"><i class="fas fa-credit-card me-2"></i>Pokyny na úhradu kreditu</h4>
                </div>
                <div class="card-body">
                    <!-- Package Info -->
                    <div class="alert alert-light border-sage mb-4">
                        <h5 class="text-sage mb-3"><i class="fas fa-box me-2"></i>Detail objednávky</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Balíček:</strong> <?= htmlspecialchars($package['name']) ?></p>
                                <p><strong>Kredit:</strong> <?= number_format($package['amount'], 0) ?>€</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Suma na úhradu:</strong> <span class="text-sage fs-4"><?= number_format($package['price'], 2) ?>€</span></p>
                                <p><strong>Stav:</strong> <span class="badge bg-warning">Čaká na úhradu</span></p>
                            </div>
                        </div>
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
                                            <td><strong><?= number_format($package['price'], 2) ?> EUR</strong></td>
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
                                    <?= $qrCodeHtml ?>
                                    <p class="text-muted small mt-2">Naskenujte kód mobilnou bankovnou aplikáciou</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Important Notice -->
                    <div class="alert alert-warning border-warning">
                        <h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Dôležité upozornenie</h6>
                        <p class="mb-2"><strong>Bez správneho variabilného symbolu <?= htmlspecialchars($paymentRequest['variable_symbol']) ?> nebude možné platbu spárovať!</strong></p>
                        <p class="mb-0">Platba bude spracovaná do 24 hodín a kredit bude automaticky pripísaný na váš účet.</p>
                    </div>

                    <!-- Alternative Payment -->
                    <div class="alert alert-info border-info">
                        <h6 class="alert-heading"><i class="fas fa-hand-holding-usd me-2"></i>Alternatívna platba</h6>
                        <p class="mb-0"><strong>Kredit môžete uhradiť aj v hotovosti v štúdiu pred lekciou.</strong></p>
                        <p class="mb-0">Jednoducho sa prihláste na lekciu a dobitie kreditu uveďte pred začiatkom.</p>
                    </div>

                    <!-- Instructions -->
                    <div class="card">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-list me-2"></i>Ako uskutočniť platbu</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-sage">Mobilná aplikácia:</h6>
                                    <ol class="small">
                                        <li>Otvorte bankovú aplikáciu</li>
                                        <li>Zvoľte "Skenovanie QR kódu"</li>
                                        <li>Naskenujte QR kód vyššie</li>
                                        <li>Skontrolujte údaje a potvrďte</li>
                                    </ol>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-sage">Internetbanking:</h6>
                                    <ol class="small">
                                        <li>Prihláste sa do internetbankingu</li>
                                        <li>Zvoľte "Nový prevod"</li>
                                        <li>Vyplňte údaje z tabuľky vyššie</li>
                                        <li>Nezabudnite na variabilný symbol!</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <a href="/pages/buy-credits-manual.php" class="btn btn-secondary me-2">
                            <i class="fas fa-arrow-left me-2"></i>Späť na nákup kreditu
                        </a>
                        <a href="/index.php" class="btn btn-sage">
                            <i class="fas fa-home me-2"></i>Domov
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>