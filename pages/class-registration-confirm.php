<?php
// Class registration confirmation page
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$currentUser = getCurrentUser();

if (!$currentUser) {
    $_SESSION['flash_message'] = 'Pre registráciu na lekciu sa musíte prihlásiť.';
    $_SESSION['flash_type'] = 'error';
    header('Location: ' . url('pages/login.php'));
    exit;
}

$classId = (int)($_GET['id'] ?? 0);

if (!$classId) {
    $_SESSION['flash_message'] = 'Neplatné ID lekcie.';
    $_SESSION['flash_type'] = 'error';
    header('Location: ' . url('pages/classes.php'));
    exit;
}

try {
    // Get class details with lektor information
    $class = db()->fetch("
        SELECT yc.*, u.name as lektor_name, COUNT(r.id) as registered_count
        FROM yoga_classes yc
        LEFT JOIN users u ON yc.instructor_id = u.id
        LEFT JOIN registrations r ON yc.id = r.class_id AND r.status = 'confirmed'
        WHERE yc.id = ?
        GROUP BY yc.id
    ", [$classId]);
    
    if (!$class) {
        throw new Exception('Lekcia nenájdená.');
    }
    
    // Check if registration is still allowed
    if (!canRegisterForClass($classId)) {
        throw new Exception('Registrácia na túto lekciu je už uzavretá.');
    }
    
    // Check if class is full
    if ($class['registered_count'] >= $class['capacity']) {
        throw new Exception('Lekcia je plná.');
    }
    
    // Check if user is already registered
    $existing = db()->fetch("
        SELECT id FROM registrations 
        WHERE class_id = ? AND user_id = ? AND status = 'confirmed'
    ", [$classId, $currentUser['id']]);
    
    if ($existing) {
        throw new Exception('Na túto lekciu ste už prihlásený.');
    }
    
    // Check if this is a standalone class
    if ($class['course_id']) {
        throw new Exception('Nemôžete sa prihlásiť na lekciu z kurzu samostatne.');
    }
    
    // Calculate payment method and price
    $price = $class['price'];
    $useCredit = false;
    $paymentMethod = 'bank_transfer';
    
    // For clients, check if they have enough credit
    if ($currentUser['role'] === 'klient') {
        $freshUser = db()->fetch("SELECT eur_balance FROM users WHERE id = ?", [$currentUser['id']]);
        $eurBalance = $freshUser ? (float)$freshUser['eur_balance'] : 0;
        $creditPrice = $class['price_with_credit'];
        
        if ($eurBalance >= $creditPrice) {
            $price = $creditPrice;
            $useCredit = true;
            $paymentMethod = 'credit';
        }
    }
    
} catch (Exception $e) {
    $_SESSION['flash_message'] = $e->getMessage();
    $_SESSION['flash_type'] = 'error';
    header('Location: ' . url('pages/classes.php'));
    exit;
}

$pageTitle = 'Potvrdenie registrácie - ' . $class['name'];
$currentPage = 'classes';
?>

<?php include '../includes/header.php'; ?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Back button -->
            <div class="mb-4">
                <a href="<?= url('pages/classes.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>Späť na lekcie
                </a>
            </div>
            
            <!-- Class details card -->
            <div class="card mb-4">
                <?php if ($class['image_url']): ?>
                    <img src="<?= url('uploads/classes/' . $class['image_url']) ?>" class="card-img-top" alt="<?= e($class['name']) ?>" style="height: 250px; object-fit: cover;">
                <?php endif; ?>
                
                <div class="card-body">
                    <div class="text-center mb-4">
                        <h1 class="h3 mb-2">Potvrdenie registrácie</h1>
                        <p class="text-muted">Skontrolujte detaily lekcie pred potvrdením registrácie</p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <h4 class="card-title fw-bold mb-3"><?= e($class['name']) ?></h4>
                            
                            <div class="class-details mb-4">
                                <div class="row mb-2">
                                    <div class="col-4"><strong>Dátum:</strong></div>
                                    <div class="col-8"><?= formatDate($class['date']) ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-4"><strong>Čas:</strong></div>
                                    <div class="col-8"><?= formatTime($class['time_start']) ?> - <?= formatTime($class['time_end']) ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-4"><strong>Lektor:</strong></div>
                                    <div class="col-8"><?= e($class['lektor_name'] ?? $class['lektor']) ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-4"><strong>Typ:</strong></div>
                                    <div class="col-8"><?= e($class['type']) ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-4"><strong>Úroveň:</strong></div>
                                    <div class="col-8"><span class="badge bg-sage"><?= e($class['level']) ?></span></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-4"><strong>Obsadenosť:</strong></div>
                                    <div class="col-8"><?= (int)$class['registered_count'] ?>/<?= (int)$class['capacity'] ?> prihlásených</div>
                                </div>
                            </div>
                            
                            <?php if ($class['description']): ?>
                                <div class="mb-4">
                                    <h6 class="fw-bold">Popis lekcie:</h6>
                                    <p class="text-muted"><?= e($class['description']) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4">
                            <!-- Payment info -->
                            <div class="bg-light p-3 rounded mb-3">
                                <h6 class="fw-bold mb-3">Platobné informácie</h6>
                                
                                <?php if ($useCredit): ?>
                                    <div class="alert alert-success mb-3">
                                        <i class="fas fa-credit-card me-1"></i>
                                        <strong>Platba kreditom</strong>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-muted small">Cena s kreditom</div>
                                        <div class="h4 text-success"><?= formatPrice($price) ?></div>
                                        <div class="text-muted small">Úspora: <?= formatPrice($class['price'] - $price) ?></div>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info mb-3">
                                        <i class="fas fa-university me-1"></i>
                                        <strong>Bankový prevod</strong>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-muted small">Cena na mieste</div>
                                        <div class="h4 text-primary"><?= formatPrice($price) ?></div>
                                        <?php if ($currentUser['role'] === 'klient'): ?>
                                            <div class="text-muted small">
                                                S kreditom: <?= formatPrice($class['price_with_credit']) ?>
                                                <br><small>(nedostatočný zostatok)</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Registration form -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Dokončenie registrácie</h5>
                    
                    <form method="POST" action="<?= url('pages/register-class.php') ?>">
                        <input type="hidden" name="class_id" value="<?= $classId ?>">
                        <input type="hidden" name="confirmed" value="1">
                        
                        <div class="mb-4">
                            <label for="notes" class="form-label">Poznámka (nepovinné)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Máte nejaké špecifické požiadavky alebo poznámky k lekcii?"></textarea>
                            <div class="form-text">Vaša poznámka bude viditeľná lektorovi.</div>
                        </div>
                        
                        <?php if (!$useCredit): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Informácia o platbe:</strong> Po potvrdení registrácie budete presmerovaný na stránku s platobými údajmi a QR kódom. Platobné pokyny budú odoslané aj na váš email.
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between">
                            <a href="<?= url('pages/classes.php') ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Zrušiť
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-check me-1"></i>Potvrdiť registráciu
                                <?php if ($useCredit): ?>
                                    za <?= formatPrice($price) ?>
                                <?php endif; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>