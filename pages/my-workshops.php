<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$currentUser = getCurrentUser();

// Get user's workshop registrations
$workshops = db()->fetchAll("
    SELECT wr.*, w.title as workshop_name, w.date, w.time_start, w.time_end, w.price,
           w.location, w.description, w.capacity,
           COALESCE(NULLIF(w.custom_instructor_name, ''), u.name) as instructor_name,
           wr.status as registration_status, wr.registered_on, wr.notes
    FROM workshop_registrations wr
    JOIN workshops w ON wr.workshop_id = w.id
    LEFT JOIN users u ON w.instructor_id = u.id
    WHERE wr.user_id = ? AND wr.status IN ('confirmed', 'waitlisted', 'pending')
    ORDER BY w.date ASC
", [$currentUser['id']]);

// Handle workshop cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_workshop'])) {
    $workshopRegistrationId = (int)$_POST['workshop_registration_id'];
    
    // Verify this registration belongs to current user
    $registration = db()->fetch("
        SELECT wr.*, w.title as workshop_name, w.price
        FROM workshop_registrations wr
        JOIN workshops w ON wr.workshop_id = w.id
        WHERE wr.id = ? AND wr.user_id = ?
    ", [$workshopRegistrationId, $currentUser['id']]);
    
    if ($registration) {
        try {
            // Update registration status to cancelled
            db()->query("
                UPDATE workshop_registrations 
                SET status = 'cancelled', updated_at = NOW() 
                WHERE id = ?
            ", [$workshopRegistrationId]);
            
            // If user paid, they should get refund (this would be manual process)
            if ($registration['payment_amount'] > 0) {
                setFlashMessage('Úspešne ste sa odhlásili z workshopu "' . $registration['workshop_name'] . '". Pre vrátenie platby nás kontaktujte.', 'success');
            } else {
                setFlashMessage('Úspešne ste sa odhlásili z workshopu "' . $registration['workshop_name'] . '".', 'success');
            }
            
        } catch (Exception $e) {
            setFlashMessage('Chyba pri odhlasovaní z workshopu: ' . $e->getMessage(), 'danger');
        }
    } else {
        setFlashMessage('Workshop registrácia nenájdená.', 'danger');
    }
    
    // Redirect to prevent form resubmission
    header('Location: ' . url('pages/my-workshops.php'));
    exit;
}

$pageTitle = 'Moje workshopy';
$currentPage = 'workshops';
include '../includes/header.php';
?>

<div class="container my-5">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-tools me-2 text-sage"></i>Moje workshopy</h2>
                <a href="<?= url('pages/workshops.php') ?>" class="btn btn-outline-sage">
                    <i class="fas fa-plus me-2"></i>Registrovať na workshop
                </a>
            </div>

            <?php if (empty($workshops)): ?>
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle me-2"></i>
                    Nemáte sa registrovaný na žiadne workshopy.
                    <br><br>
                    <a href="<?= url('pages/workshops.php') ?>" class="btn btn-sage">
                        <i class="fas fa-search me-2"></i>Prezrieť dostupné workshopy
                    </a>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($workshops as $workshop): ?>
                        <div class="col-lg-6 col-xl-4 mb-4">
                            <div class="card h-100 shadow-sm border-0">
                                <div class="card-header bg-light border-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <h5 class="card-title mb-1"><?= htmlspecialchars($workshop['workshop_name']) ?></h5>
                                        <?php
                                        $statusBadge = '';
                                        switch ($workshop['registration_status']) {
                                            case 'confirmed':
                                                $statusBadge = '<span class="badge bg-success">Potvrdené</span>';
                                                break;
                                            case 'waitlisted':
                                                $statusBadge = '<span class="badge bg-warning">Čakacia listina</span>';
                                                break;
                                            case 'pending':
                                                $statusBadge = '<span class="badge bg-info">Čaká na platbu</span>';
                                                break;
                                        }
                                        echo $statusBadge;
                                        ?>
                                    </div>
                                </div>
                                
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-calendar me-2 text-sage"></i>
                                            <strong><?= formatDate($workshop['date']) ?></strong>
                                        </div>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-clock me-2 text-sage"></i>
                                            <?= formatTime($workshop['time_start']) ?> - <?= formatTime($workshop['time_end']) ?>
                                        </div>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-user me-2 text-sage"></i>
                                            <?= htmlspecialchars($workshop['instructor_name']) ?>
                                        </div>
                                        <?php if (!empty($workshop['location'])): ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-map-marker-alt me-2 text-sage"></i>
                                            <?= htmlspecialchars($workshop['location']) ?>
                                        </div>
                                        <?php endif; ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-euro-sign me-2 text-sage"></i>
                                            <strong><?= formatPrice($workshop['price']) ?></strong>
                                        </div>
                                    </div>

                                    <?php if (!empty($workshop['description'])): ?>
                                    <div class="mb-3">
                                        <small class="text-muted">
                                            <?= nl2br(htmlspecialchars(substr($workshop['description'], 0, 150))) ?>
                                            <?= strlen($workshop['description']) > 150 ? '...' : '' ?>
                                        </small>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($workshop['notes'])): ?>
                                    <div class="mb-3">
                                        <strong>Vaša poznámka:</strong>
                                        <div class="text-muted small">
                                            <?= nl2br(htmlspecialchars($workshop['notes'])) ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <div class="mb-3">
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            Registrované: <?= formatDate($workshop['registered_on']) ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="card-footer bg-transparent border-0">
                                    <?php
                                    $workshopDate = new DateTime($workshop['date']);
                                    $today = new DateTime();
                                    $canCancel = $workshopDate > $today; // Can cancel if workshop is in future
                                    ?>
                                    
                                    <?php if ($canCancel): ?>
                                    <form method="POST" onsubmit="return confirm('Naozaj sa chcete odhlásiť z tohto workshopu?')">
                                        <input type="hidden" name="workshop_registration_id" value="<?= $workshop['id'] ?>">
                                        <button type="submit" name="cancel_workshop" class="btn btn-outline-danger btn-sm">
                                            <i class="fas fa-times me-2"></i>Odhlásiť sa
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <span class="text-muted small">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Workshop už prebehol
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>