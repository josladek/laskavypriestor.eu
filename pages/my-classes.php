<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once '../config/config.php';
    require_once '../config/database.php';
    require_once '../includes/auth.php';
    require_once '../includes/functions.php';

    requireLogin();
} catch (Exception $e) {
    die("Chyba načítania: " . htmlspecialchars($e->getMessage()) . "<br><a href='../debug-error.php'>Debug</a>");
}

$currentUser = getCurrentUser();

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_registration') {
    $registrationId = (int)$_POST['registration_id'];
    
    try {
        // Get class ID for time checking
        $registration = db()->fetch("
            SELECT r.*, yc.id as class_id 
            FROM registrations r 
            JOIN yoga_classes yc ON r.class_id = yc.id 
            WHERE r.id = ? AND r.user_id = ?
        ", [$registrationId, $currentUser['id']]);
        
        if (!$registration) {
            throw new Exception('Registrácia nenájdená.');
        }
        
        // Check if cancellation is still allowed
        if (!canCancelClassRegistration($registration['class_id'])) {
            throw new Exception('Zrušenie registrácie nie je možné. Odhlásiť sa môžete najneskôr 1 hodinu pred začiatkom lekcie.');
        }
        
        cancelRegistration($registrationId, $currentUser['id']);
        $_SESSION['flash_message'] = 'Registrácia bola úspešne zrušená.';
        $_SESSION['flash_type'] = 'success';
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Chyba pri zrušení registrácie: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }
    
    header('Location: my-classes.php');
    exit;
}

// Get user's registrations
$registrations = getUserRegistrations($currentUser['id']);

$pageTitle = 'Moje lekcie';
$currentPage = 'my-classes';
?>

<?php include '../includes/header.php'; ?>

<div class="container my-5">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Moje lekcie</h1>
                    <p class="text-muted">Prehľad vašich lekcie</p>
                </div>
                <a href="classes.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Registrovať na lekciu
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($registrations)): ?>
        <div class="row g-4">
            <?php foreach ($registrations as $registration): ?>
                <div class="col-lg-6 col-xl-4">
                    <div class="card class-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="card-title fw-bold mb-0"><?= e($registration['class_name']) ?></h5>
                                <?php if ($registration['status'] === 'confirmed'): ?>
                                    <span class="badge bg-success">Potvrdené</span>
                                <?php elseif ($registration['status'] === 'waitlisted'): ?>
                                    <span class="badge bg-warning">Čakacia lista</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= e($registration['status']) ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="class-details mb-3">
                                <div class="row text-sm">
                                    <div class="col-12 mb-2">
                                        <i class="fas fa-calendar text-primary me-2"></i>
                                        <strong><?= formatDate($registration['date']) ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <i class="fas fa-clock text-primary me-1"></i>
                                        <small><?= formatTime($registration['time_start']) ?> - <?= formatTime($registration['time_end']) ?></small>
                                    </div>
                                    <div class="col-6">
                                        <i class="fas fa-map-marker-alt text-primary me-1"></i>
                                        <small><?= e($registration['location']) ?></small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="payment-info bg-light p-2 rounded mb-3">
                                <?php if ($registration['paid_with_credit']): ?>
                                    <div class="text-success small">
                                        <i class="fas fa-credit-card me-1"></i>
                                        Uhradené kreditom: <?= formatPrice($registration['price_with_credit']) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-muted small">
                                        <i class="fas fa-money-bill me-1"></i>
                                        Platba na bankový prevodom alebo v hotovosti na mieste
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($registration['status'] === 'waitlisted' && isset($registration['waitlist_position'])): ?>
                                <div class="alert alert-warning small py-2">
                                    <i class="fas fa-clock me-1"></i>
                                    Pozícia na čakacej liste: <?= (int)$registration['waitlist_position'] ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="registration-date text-muted small mb-3">
                                Registrované: <?= formatDate($registration['registered_on']) ?>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <?php 
                                $classDateTime = new DateTime($registration['date'] . ' ' . $registration['time_start']);
                                $now = new DateTime();
                                $cancellationDeadline = (clone $classDateTime)->modify('-1 hour');
                                $canCancel = $now < $cancellationDeadline;
                                $hasClassPassed = $now > $classDateTime;
                                ?>
                                
                                <?php if ($canCancel && !$hasClassPassed): ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="cancel_registration">
                                        <input type="hidden" name="registration_id" value="<?= $registration['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm"
                                                onclick="return confirmCancellation('<?= e($registration['class_name']) ?>', <?= $registration['paid_with_credit'] ? $registration['price_with_credit'] : 0 ?>)">
                                            <i class="fas fa-times me-1"></i> Zrušiť registráciu
                                        </button>
                                    </form>
                                <?php elseif (!$hasClassPassed): ?>
                                    <button class="btn btn-outline-secondary btn-sm" disabled>
                                        <i class="fas fa-clock me-1"></i> Nemožno zrušiť (menej ako 1 hodina)
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-outline-secondary btn-sm" disabled>
                                        <i class="fas fa-check me-1"></i> Lekcia prebehla
                                    </button>
                                <?php endif; ?>
                                
                                <a href="export-calendar.php?registration=<?= $registration['id'] ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-calendar-plus me-1"></i> Pridať do kalendára
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Statistics -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Štatistiky</h5>
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="h4 text-primary"><?= count($registrations) ?></div>
                                <div class="text-muted">Celkom registrácií</div>
                            </div>
                            <div class="col-md-3">
                                <?php $confirmed = array_filter($registrations, function($r) { return $r['status'] === 'confirmed'; }); ?>
                                <div class="h4 text-success"><?= count($confirmed) ?></div>
                                <div class="text-muted">Potvrdené</div>
                            </div>
                            <div class="col-md-3">
                                <?php $waitlisted = array_filter($registrations, function($r) { return $r['status'] === 'waitlisted'; }); ?>
                                <div class="h4 text-warning"><?= count($waitlisted) ?></div>
                                <div class="text-muted">Čakacia lista</div>
                            </div>
                            <div class="col-md-3">
                                <?php $paidWithCredit = array_filter($registrations, function($r) { return $r['paid_with_credit']; }); ?>
                                <div class="h4 text-info"><?= count($paidWithCredit) ?></div>
                                <div class="text-muted">Platené kreditom</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <div class="text-center py-5">
            <div class="mb-4">
                <svg width="120" height="120" viewBox="0 0 100 100">
                    <defs>
                        <linearGradient id="empty-classes-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:var(--sage);stop-opacity:0.2" />
                            <stop offset="100%" style="stop-color:var(--sage-dark);stop-opacity:0.4" />
                        </linearGradient>
                    </defs>
                    <circle cx="50" cy="50" r="40" fill="none" stroke="url(#empty-classes-gradient)" stroke-width="2"/>
                    <path d="M30 45 Q50 25 70 45" fill="none" stroke="url(#empty-classes-gradient)" stroke-width="2"/>
                    <path d="M30 55 Q50 75 70 55" fill="none" stroke="url(#empty-classes-gradient)" stroke-width="2"/>
                </svg>
            </div>
            <h3 class="text-muted">Žiadne lekcie</h3>
            <p class="text-muted">Ešte ste sa neregistrovali na žiadne lekcie.</p>
            <div class="d-flex gap-2 justify-content-center">
                <a href="classes.php" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i> Prehliadnuť lekcie
                </a>
                <a href="courses.php" class="btn btn-outline-primary">
                    <i class="fas fa-graduation-cap me-1"></i> Pozrieť kurzy
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- CSS moved to laskavypriestor.css -->

<script>
function confirmCancellation(className, refundAmount) {
    let message = `Naozaj chcete zrušiť registráciu na lekciu "${className}"?`;
    if (refundAmount > 0) {
        message += `\n\nBude vám vrátený kredit vo výške ${refundAmount.toFixed(2)}€.`;
    }
    message += '\n\nTáto akcia sa nedá vrátiť späť.';
    
    return confirm(message);
}
</script>

<?php include '../includes/footer.php'; ?>