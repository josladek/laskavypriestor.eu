<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/qr_generator.php';
require_once '../config/payment_config.php';

requireLogin();
$currentUser = getCurrentUser();

$variableSymbol = $_GET['vs'] ?? '';

if (empty($variableSymbol)) {
    $_SESSION['flash_message'] = 'Neplatný variabilný symbol.';
    $_SESSION['flash_type'] = 'error';
    header('Location: /pages/courses.php');
    exit;
}

// Get course registration details by variable symbol
$courseRegistration = db()->fetch("
    SELECT cr.*, c.name as course_name, c.price, u.name as user_name
    FROM course_registrations cr
    JOIN courses c ON cr.course_id = c.id
    JOIN users u ON cr.user_id = u.id
    WHERE cr.variable_symbol = ? AND cr.user_id = ?
", [$variableSymbol, $currentUser['id']]);

if (!$courseRegistration) {
    $_SESSION['flash_message'] = 'Registrácia na kurz nebola nájdená.';
    $_SESSION['flash_type'] = 'error';
    header('Location: /pages/courses.php');
    exit;
}

// Generate QR code data for course payment
$qrMessage = "Kurz " . $courseRegistration['course_name'] . " - " . $courseRegistration['user_name'];
$qrCodeData = generateQRPaymentString($courseRegistration['price'], $variableSymbol, $qrMessage);

// Get company settings
$settings = getCompanySettings();

include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-qrcode me-2"></i>QR kód pre úhradu kurzu</h4>
                </div>
                <div class="card-body">
                    <!-- Course Info -->
                    <div class="alert alert-info">
                        <h5 class="alert-heading"><i class="fas fa-book me-2"></i><?= htmlspecialchars($courseRegistration['course_name']) ?></h5>
                        <p class="mb-1"><strong>Suma na úhradu:</strong> <?= number_format($courseRegistration['price'], 2) ?> €</p>
                        <p class="mb-0"><strong>Variabilný symbol:</strong> <?= htmlspecialchars($variableSymbol) ?></p>
                    </div>

                    <div class="row">
                        <!-- Payment Details -->
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-credit-card me-2"></i>Platobné údaje</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-sm table-borderless">
                                        <tr>
                                            <td><strong>IBAN:</strong></td>
                                            <td><?= htmlspecialchars($settings['company_iban'] ?? 'SK7311000000002612938533') ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Suma:</strong></td>
                                            <td><?= number_format($courseRegistration['price'], 2) ?> EUR</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Variabilný symbol:</strong></td>
                                            <td><?= htmlspecialchars($variableSymbol) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Správa:</strong></td>
                                            <td>Kurz: <?= htmlspecialchars($courseRegistration['course_name']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Príjemca:</strong></td>
                                            <td><?= htmlspecialchars($settings['company_name'] ?? 'Láskavý Priestor') ?></td>
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

                    <!-- Important Notice -->
                    <div class="alert alert-warning border-warning">
                        <h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Dôležité upozornenie</h6>
                        <p class="mb-2"><strong>Bez správneho variabilného symbolu <?= htmlspecialchars($variableSymbol) ?> nebude možné platbu spárovať!</strong></p>
                        <p class="mb-0">Platba bude spracovaná do 24 hodín a po potvrdení budete automaticky registrovaní na všetky lekcie kurzu.</p>
                    </div>

                    <!-- Instructions -->
                    <div class="card mt-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-list-ol me-2"></i>Pokyny na úhradu</h6>
                        </div>
                        <div class="card-body">
                            <ol class="mb-0">
                                <li>Naskenujte QR kód alebo použite platobné údaje vyššie</li>
                                <li>Overte správnosť sumy a variabilného symbolu</li>
                                <li>Potvrdite platbu v mobilnej aplikácii banky</li>
                                <li>Platba bude spracovaná do 24 hodín</li>
                                <li>Po potvrdení dostanete email s potvrdením registrácie</li>
                            </ol>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="text-center mt-4">
                        <a href="<?= url('pages/courses.php') ?>" class="btn btn-outline-primary me-2">
                            <i class="fas fa-arrow-left me-2"></i>Späť na kurzy
                        </a>
                        <a href="<?= url('pages/my-courses.php') ?>" class="btn btn-primary">
                            <i class="fas fa-book me-2"></i>Moje kurzy
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>