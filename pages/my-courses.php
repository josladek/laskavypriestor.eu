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

// Handle POST requests for course cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_course_registration') {
    $registrationId = (int)($_POST['registration_id'] ?? 0);
    
    if ($registrationId) {
        try {
            // Get registration details first
            $registration = db()->fetch("
                SELECT cr.*, c.name as course_name, c.price_with_credit, c.price 
                FROM course_registrations cr 
                JOIN courses c ON cr.course_id = c.id 
                WHERE cr.id = ? AND cr.user_id = ? AND cr.status = 'confirmed'
            ", [$registrationId, $currentUser['id']]);
            
            if (!$registration) {
                throw new Exception('Registrácia nebola nájdená alebo už bola zrušená.');
            }
            
            db()->beginTransaction();
            
            // Update registration status to cancelled
            db()->query("UPDATE course_registrations SET status = 'cancelled' WHERE id = ?", [$registrationId]);
            
            // Cancel all lesson registrations for this course
            $courseLessons = db()->fetchAll("SELECT id FROM yoga_classes WHERE course_id = ?", [$registration['course_id']]);
            foreach ($courseLessons as $lesson) {
                // Cancel registrations for this user on course lessons
                db()->query("UPDATE registrations SET status = 'cancelled' WHERE class_id = ? AND user_id = ?", 
                           [$lesson['id'], $currentUser['id']]);
            }
            
            // Refund credit if paid with credit
            if ($registration['paid_with_credit']) {
                $refundAmount = $registration['payment_amount'];
                
                // Add EUR credit back to user account
                db()->query("UPDATE users SET eur_balance = eur_balance + ? WHERE id = ?", [$refundAmount, $currentUser['id']]);
                
                // Create refund transaction record
                db()->query("
                    INSERT INTO credit_transactions (user_id, amount, transaction_type, reference_id, description) 
                    VALUES (?, ?, 'course_refund', ?, ?)
                ", [$currentUser['id'], $refundAmount, $registrationId, "Vrátený kredit za zrušenie kurzu: " . $registration['course_name']]);
            }
            
            db()->commit();
            
            $_SESSION['flash_message'] = "Registrácia na kurz \"{$registration['course_name']}\" bola úspešne zrušená." . 
                ($registration['paid_with_credit'] ? " Kredit vo výške " . formatPrice($registration['payment_amount']) . " bol vrátený na váš účet." : "");
            $_SESSION['flash_type'] = 'success';
            
        } catch (Exception $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $_SESSION['flash_message'] = $e->getMessage();
            $_SESSION['flash_type'] = 'error';
        }
    }
    
    // Redirect to prevent form resubmission
    header('Location: ' . url('pages/my-courses.php'));
    exit;
}

// Get user's course registrations
$courseRegistrations = getUserCourseRegistrations($currentUser['id']);

$pageTitle = 'Moje kurzy';
$currentPage = 'my-courses';
?>

<?php include '../includes/header.php'; ?>

<div class="container my-5">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Moje kurzy</h1>
                    <p class="text-muted">Prehľad vašich registrácií na jogové kurzy</p>
                </div>
                <a href="courses.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Registrovať na kurz
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($courseRegistrations)): ?>
        <div class="row g-4">
            <?php foreach ($courseRegistrations as $registration): ?>
                <div class="col-lg-6">
                    <div class="card course-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="card-title fw-bold mb-0"><?= e($registration['course_name']) ?></h5>
                                <?php if ($registration['status'] === 'confirmed'): ?>
                                    <span class="badge bg-success">Potvrdené</span>
                                <?php elseif ($registration['status'] === 'cancelled'): ?>
                                    <span class="badge bg-danger">Zrušené</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= e($registration['status']) ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="course-details mb-3">
                                <div class="row text-sm">
                                    <div class="col-6">
                                        <i class="fas fa-calendar text-primary me-1"></i>
                                        <small class="fw-bold">Začiatok</small>
                                        <br><small><?= formatDate($registration['start_date']) ?></small>
                                    </div>
                                    <div class="col-6">
                                        <i class="fas fa-calendar-check text-primary me-1"></i>
                                        <small class="fw-bold">Koniec</small>
                                        <br><small><?= formatDate($registration['end_date']) ?></small>
                                    </div>
                                    <div class="col-6 mt-2">
                                        <i class="fas fa-user text-primary me-1"></i>
                                        <small class="fw-bold"><?= e($registration['lektor_name']) ?></small>
                                    </div>
                                    <div class="col-6 mt-2">
                                        <i class="fas fa-list text-primary me-1"></i>
                                        <small class="fw-bold"><?= (int)$registration['total_lessons'] ?> lekcií</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="payment-info bg-light p-3 rounded mb-3">
                                <div class="row">
                                    <div class="col-6">
                                        <div class="text-muted small">Zaplatené</div>
                                        <div class="fw-bold"><?= formatPrice($registration['payment_amount']) ?></div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-muted small">Spôsob platby</div>
                                        <div class="fw-bold">
                                            <?php if ($registration['paid_with_credit']): ?>
                                                <span class="text-success">
                                                    <i class="fas fa-credit-card me-1"></i> Kredit
                                                </span>
                                            <?php else: ?>
                                                <span class="text-info">
                                                    <i class="fas fa-money-bill me-1"></i> Bankový prevod
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($registration['notes'])): ?>
                                <div class="notes mb-3">
                                    <div class="text-muted small">Poznámky:</div>
                                    <div class="small"><?= nl2br(e($registration['notes'])) ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="registration-date text-muted small mb-3">
                                Registrované: <?= formatDate($registration['registered_on']) ?>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <?php 
                                $startDate = new DateTime($registration['start_date']);
                                $now = new DateTime();
                                $daysUntilStart = ($startDate->getTimestamp() - $now->getTimestamp()) / (24 * 3600);
                                ?>
                                
                                <?php if ($registration['status'] === 'confirmed'): ?>
                                    <?php if ($daysUntilStart > 2): ?>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="action" value="cancel_course_registration">
                                            <input type="hidden" name="registration_id" value="<?= $registration['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm"
                                                    onclick="return confirmCourseCancellation('<?= e($registration['course_name']) ?>', <?= $registration['paid_with_credit'] ? $registration['payment_amount'] : 0 ?>)">
                                                <i class="fas fa-times me-1"></i> Zrušiť registráciu
                                            </button>
                                        </form>
                                    <?php elseif ($daysUntilStart > 0): ?>
                                        <button class="btn btn-outline-secondary btn-sm" disabled>
                                            <i class="fas fa-clock me-1"></i> Nemožno zrušiť (menej ako 48h)
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-success btn-sm" disabled>
                                            <i class="fas fa-play me-1"></i> Kurz prebieha
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <a href="../pages/course-detail.php?id=<?= $registration['course_id'] ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye me-1"></i> Detail kurzu
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
                        <h5 class="card-title">Štatistiky kurzov</h5>
                        <div class="row text-center">
                            <div class="col-md-4">
                                <div class="h4 text-primary"><?= count($courseRegistrations) ?></div>
                                <div class="text-muted">Celkom kurzov</div>
                            </div>
                            <div class="col-md-4">
                                <?php $confirmed = array_filter($courseRegistrations, function($r) { return $r['status'] === 'confirmed'; }); ?>
                                <div class="h4 text-success"><?= count($confirmed) ?></div>
                                <div class="text-muted">Aktívne</div>
                            </div>
                            <div class="col-md-4">
                                <?php $totalAmount = array_sum(array_column($courseRegistrations, 'payment_amount')); ?>
                                <div class="h4 text-info"><?= formatPrice($totalAmount) ?></div>
                                <div class="text-muted">Celková investícia</div>
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
                        <linearGradient id="empty-courses-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:var(--sage);stop-opacity:0.2" />
                            <stop offset="100%" style="stop-color:var(--sage-dark);stop-opacity:0.4" />
                        </linearGradient>
                    </defs>
                    <circle cx="50" cy="50" r="40" fill="none" stroke="url(#empty-courses-gradient)" stroke-width="2"/>
                    <path d="M30 45 Q50 25 70 45" fill="none" stroke="url(#empty-courses-gradient)" stroke-width="2"/>
                    <path d="M30 55 Q50 75 70 55" fill="none" stroke="url(#empty-courses-gradient)" stroke-width="2"/>
                    <circle cx="40" cy="42" r="2" fill="var(--sage)"/>
                    <circle cx="60" cy="42" r="2" fill="var(--sage)"/>
                </svg>
            </div>
            <h3 class="text-muted">Žiadne kurzy</h3>
            <p class="text-muted">Ešte ste sa neregistrovali na žiadne kurzy.</p>
            <div class="d-flex gap-2 justify-content-center">
                <a href="courses.php" class="btn btn-primary">
                    <i class="fas fa-graduation-cap me-1"></i> Prehliadnuť kurzy
                </a>
                <a href="classes.php" class="btn btn-outline-primary">
                    <i class="fas fa-dumbbell me-1"></i> Jednotlivé lekcie
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- CSS moved to laskavypriestor.css -->

<script>
function confirmCourseCancellation(courseName, refundAmount) {
    let message = `Naozaj chcete zrušiť registráciu na kurz "${courseName}"?`;
    if (refundAmount > 0) {
        message += `\n\nBude vám vrátený kredit vo výške ${refundAmount.toFixed(2)}€.`;
    }
    message += '\n\nTáto akcia sa nedá vrátiť späť.';
    
    return confirm(message);
}
</script>

<?php include '../includes/footer.php'; ?>