<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/payment_config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/email_functions.php';
require_once '../includes/qr_generator.php';

requireLogin();
$currentUser = getCurrentUser();

if ($currentUser['role'] !== 'klient') {
    $_SESSION['flash_message'] = 'Kredit môžu nakupovať iba klienti.';
    $_SESSION['flash_type'] = 'error';
    header('Location: /index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $packageId = $_POST['package_id'] ?? '';
    $paymentMethod = 'bank_transfer'; // Always bank transfer
    
    // Package definitions
    $packages = [
        'basic' => ['amount' => 50, 'price' => 50.00, 'name' => 'Základný balíček'],
        'standard' => ['amount' => 75, 'price' => 75.00, 'name' => 'Štandardný balíček'],
        'premium' => ['amount' => 100, 'price' => 100.00, 'name' => 'Prémiový balíček']
    ];
    
    if (!isset($packages[$packageId])) {
        $_SESSION['flash_message'] = 'Neplatný balíček kreditu.';
        $_SESSION['flash_type'] = 'error';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    $package = $packages[$packageId];
    
    try {
        db()->beginTransaction();
        
        // Generate unique numeric variable symbol
        $packageNumber = array_search($packageId, array_keys($packages)) + 1;
        $variableSymbol = generateVariableSymbol($currentUser['id'], $packageNumber);
        
        // Generate QR code data
        $qrMessage = "Kredit " . $package['name'] . " - " . $currentUser['name'];
        $qrCodeData = generateQRPaymentString($package['price'], $variableSymbol, $qrMessage);
        
        // Create payment request
        $requestId = db()->query("
            INSERT INTO payment_requests (user_id, package_id, amount, eur_amount, variable_symbol, payment_method, qr_code_data, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $currentUser['id'],
            $packageId,
            $package['price'],
            $package['amount'],
            $variableSymbol,
            $paymentMethod,
            $qrCodeData,
            "Požiadavka na " . $package['name'] . " (" . $package['amount'] . "€ kredit)"
        ]);
        
        if (!$requestId) {
            throw new Exception('Chyba pri vytváraní platobnej požiadavky.');
        }
        
        db()->commit();
        
        // Send email with payment instructions
        if ($currentUser['email']) {
            sendPaymentInstructionEmail($currentUser, $package, $variableSymbol, $qrCodeData);
        }
        
        $_SESSION['flash_message'] = 'Požiadavka na kredit bola úspešne vytvorená. Platobné pokyny boli odoslané na váš email.';
        $_SESSION['flash_type'] = 'success';
        
        // Redirect to payment confirmation page
        header('Location: /pages/payment-confirmation.php?request_id=' . $requestId);
        exit;
        
    } catch (Exception $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        
        $_SESSION['flash_message'] = 'Chyba: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Get current balance
$freshUser = db()->fetch("SELECT eur_balance FROM users WHERE id = ?", [$currentUser['id']]);
$currentBalance = $freshUser ? (float)$freshUser['eur_balance'] : 0;

// Get pending payment requests
$pendingRequests = db()->fetchAll("
    SELECT * FROM payment_requests 
    WHERE user_id = ? AND status = 'pending' 
    ORDER BY created_at DESC
", [$currentUser['id']]);

$pageTitle = 'Kúpa kreditu';
$currentPage = 'buy-credits';
?>

<?php include '../includes/header.php'; ?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="text-center mb-5">
                <h1 class="h3 mb-3">Kúpa kreditu</h1>
                <p class="text-muted">Vyberte si balíček kreditu pre jogové lekcie</p>
                <div class="alert alert-info">
                    <strong>Aktuálny zostatok:</strong> <?= number_format($currentBalance, 2) ?>€
                </div>
            </div>

            <?php if (!empty($pendingRequests)): ?>
                <div class="alert alert-warning mb-4">
                    <h5><i class="fas fa-clock me-2"></i>Čakajúce požiadavky</h5>
                    <p class="mb-2">Máte <?= count($pendingRequests) ?> nevybavené požiadavky na kredit:</p>
                    <?php foreach ($pendingRequests as $request): ?>
                        <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-2">
                            <span><?= htmlspecialchars($request['notes']) ?></span>
                            <span class="badge bg-warning">
                                <?= number_format($request['amount'], 2) ?>€ - <?= date('d.m.Y H:i', strtotime($request['created_at'])) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="mb-4" id="packageForm">
                <input type="hidden" name="package_id" id="selectedPackage" required>
                
                <div class="row g-4 mb-4">
                    <!-- Basic Package -->
                    <div class="col-md-4">
                        <div class="card h-100 package-card" data-package="basic" style="cursor: pointer;">
                            <div class="card-header package-header bg-light text-center">
                                <h5 class="card-title mb-0 text-dark">Základný balíček</h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="display-6 fw-bold mb-2">50€</div>
                                <p class="text-muted">50€ kredit</p>
                                <ul class="list-unstyled mb-4">
                                    <li>✓ 5 lekcií s kreditom</li>
                                    <li>✓ Platnosť 6 mesiacov</li>
                                    <li>✓ Flexibilné zrušenie</li>
                                </ul>
                                <div class="selection-indicator">
                                    <i class="fas fa-check-circle text-success" style="display: none;"></i>
                                    <span class="text-muted">Kliknite pre výber</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Standard Package -->
                    <div class="col-md-4">
                        <div class="card h-100 package-card" data-package="standard" style="cursor: pointer;">
                            <div class="card-header package-header bg-light text-center">
                                <small class="popular-badge d-block text-warning">NAJOBĽÚBENEJŠÍ</small>
                                <h5 class="card-title mb-0 text-dark">Štandardný balíček</h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="display-6 fw-bold mb-2">75€</div>
                                <p class="text-muted">75€ kredit</p>
                                <ul class="list-unstyled mb-4">
                                    <li>✓ 7-8 lekcií s kreditom</li>
                                    <li>✓ Platnosť 8 mesiacov</li>
                                    <li>✓ Flexibilné zrušenie</li>
                                    <li>✓ Bonus workshop</li>
                                </ul>
                                <div class="selection-indicator">
                                    <i class="fas fa-check-circle text-success" style="display: none;"></i>
                                    <span class="text-muted">Kliknite pre výber</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Premium Package -->
                    <div class="col-md-4">
                        <div class="card h-100 package-card" data-package="premium" style="cursor: pointer;">
                            <div class="card-header package-header bg-light text-center">
                                <h5 class="card-title mb-0 text-dark">Prémiový balíček</h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="display-6 fw-bold mb-2">100€</div>
                                <p class="text-muted">100€ kredit</p>
                                <ul class="list-unstyled mb-4">
                                    <li>✓ 10 lekcií s kreditom</li>
                                    <li>✓ Platnosť 12 mesiacov</li>
                                    <li>✓ Flexibilné zrušenie</li>
                                    <li>✓ 2 bonus workshopy</li>
                                    <li>✓ Osobné konzultácie</li>
                                </ul>
                                <div class="selection-indicator">
                                    <i class="fas fa-check-circle text-success" style="display: none;"></i>
                                    <span class="text-muted">Kliknite pre výber</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title mb-3">Potvrdiť nákup kreditu</h5>
                        <p class="text-muted mb-4">Po potvrdení dostanete platobné údaje a QR kód na email</p>
                        
                        <div id="selectedPackageInfo" class="alert alert-info" style="display: none;">
                            <strong>Vybraný balíček:</strong> <span id="selectedPackageName"></span><br>
                            <strong>Suma:</strong> <span id="selectedPackagePrice"></span>€
                        </div>
                        
                        <button type="submit" class="btn btn-sage btn-lg w-100" id="submitBtn" disabled>
                            <i class="fas fa-credit-card me-2"></i>Najprv vyberte balíček
                        </button>
                    </div>
                </div>
            </form>

            <div class="alert alert-light">
                <h6><i class="fas fa-info-circle me-2"></i>Informácie o platbe</h6>
                <ul class="mb-0 small">
                    <li>Kredit má platnosť podľa zvoleného balíčka</li>
                    <li>Pri zrušení lekcie sa kredit vráti na váš účet</li>
                    <li>Nevyčerpaný kredit sa neprepadá do expirácie</li>
                    <li>Platobné pokyny a QR kód dostanete na email</li>
                    <li><strong>Kredit môžete uhradiť aj v hotovosti v štúdiu pred lekciou</strong></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- CSS moved to laskavypriestor.css -->



<script>
document.addEventListener('DOMContentLoaded', function() {
    const packageCards = document.querySelectorAll('.package-card');
    const selectedPackageInput = document.getElementById('selectedPackage');
    const submitBtn = document.getElementById('submitBtn');
    const selectedPackageInfo = document.getElementById('selectedPackageInfo');
    const selectedPackageName = document.getElementById('selectedPackageName');
    const selectedPackagePrice = document.getElementById('selectedPackagePrice');
    
    const packageData = {
        'basic': { name: 'Základný balíček', price: 50 },
        'standard': { name: 'Štandardný balíček', price: 75 },
        'premium': { name: 'Prémiový balíček', price: 100 }
    };
    
    packageCards.forEach(card => {
        card.addEventListener('click', function() {
            // Remove selection from all cards
            packageCards.forEach(c => c.classList.remove('selected'));
            
            // Add selection to clicked card
            this.classList.add('selected');
            
            // Update hidden input
            const packageId = this.dataset.package;
            selectedPackageInput.value = packageId;
            
            // Update package info
            const packageInfo = packageData[packageId];
            selectedPackageName.textContent = packageInfo.name;
            selectedPackagePrice.textContent = packageInfo.price;
            selectedPackageInfo.style.display = 'block';
            
            // Enable submit button
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-credit-card me-2"></i>Potvrdiť nákup kreditu';
        });
    });
    
    // Form validation
    document.getElementById('packageForm').addEventListener('submit', function(e) {
        if (!selectedPackageInput.value) {
            e.preventDefault();
            alert('Prosím vyberte balíček kreditu.');
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>