<?php
// Course registration confirmation page
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$currentUser = getCurrentUser();

if (!$currentUser) {
    $_SESSION['flash_message'] = 'Pre registráciu na kurz sa musíte prihlásiť.';
    $_SESSION['flash_type'] = 'error';
    header('Location: ' . url('pages/login.php'));
    exit;
}

$courseId = (int)($_GET['id'] ?? 0);

if (!$courseId) {
    $_SESSION['flash_message'] = 'Neplatné ID kurzu.';
    $_SESSION['flash_type'] = 'error';
    header('Location: ' . url('pages/courses.php'));
    exit;
}

try {
    // Get course details
    $course = db()->fetch("
        SELECT c.*, 
               COUNT(cr.id) as registered_count,
               u.name as instructor_name,
               DATEDIFF(c.end_date, c.start_date) / 7 as weeks
        FROM courses c
        LEFT JOIN course_registrations cr ON c.id = cr.course_id AND cr.status = 'confirmed'
        LEFT JOIN users u ON c.instructor_id = u.id
        WHERE c.id = ?
        GROUP BY c.id, u.name
    ", [$courseId]);
    
    if (!$course) {
        throw new Exception('Kurz nenájdený.');
    }
    
    // Check if user is already registered
    $existing = db()->fetch("
        SELECT id FROM course_registrations 
        WHERE course_id = ? AND user_id = ? AND status = 'confirmed'
    ", [$courseId, $currentUser['id']]);
    
    if ($existing) {
        throw new Exception('Na tento kurz ste už prihlásený.');
    }
    
    // Check if course is full
    if ($course['registered_count'] >= $course['capacity']) {
        throw new Exception('Kurz je plný.');
    }
    
    // Calculate price - courses have single price, no credit option
    $price = $course['price'];
    
} catch (Exception $e) {
    $_SESSION['flash_message'] = $e->getMessage();
    $_SESSION['flash_type'] = 'error';
    header('Location: ' . url('pages/courses.php'));
    exit;
}

$pageTitle = 'Potvrdenie registrácie - ' . $course['name'];
$currentPage = 'courses';
?>

<?php include '../includes/header.php'; ?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Back button -->
            <div class="mb-4">
                <a href="<?= url('pages/courses.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>Späť na kurzy
                </a>
            </div>
            
            <!-- Course details card -->
            <div class="card mb-4">
                <?php if ($course['image_url']): ?>
                    <img src="<?= url('uploads/courses/' . $course['image_url']) ?>" class="card-img-top" alt="<?= e($course['name']) ?>" style="height: 250px; object-fit: cover;">
                <?php endif; ?>
                
                <div class="card-body">
                    <div class="text-center mb-4">
                        <h1 class="h3 mb-2">Potvrdenie registrácie na kurz</h1>
                        <p class="text-muted">Skontrolujte detaily kurzu pred potvrdením registrácie</p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <h4 class="card-title fw-bold mb-3"><?= e($course['name']) ?></h4>
                            
                            <div class="course-details mb-4">
                                <div class="row mb-2">
                                    <div class="col-4"><strong>Začiatok:</strong></div>
                                    <div class="col-8"><?= formatDate($course['start_date']) ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-4"><strong>Koniec:</strong></div>
                                    <div class="col-8"><?= formatDate($course['end_date']) ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-4"><strong>Trvanie:</strong></div>
                                    <div class="col-8"><?= max(1, (int)$course['weeks']) ?> týždňov</div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-4"><strong>Kapacita:</strong></div>
                                    <div class="col-8"><?= (int)$course['registered_count'] ?>/<?= (int)$course['capacity'] ?> prihlásených</div>
                                </div>
                                <?php if (!empty($course['level'])): ?>
                                <div class="row mb-2">
                                    <div class="col-4"><strong>Úroveň:</strong></div>
                                    <div class="col-8"><span class="badge bg-sage"><?= e($course['level']) ?></span></div>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($course['instructor_name'])): ?>
                                <div class="row mb-2">
                                    <div class="col-4"><strong>Lektor:</strong></div>
                                    <div class="col-8"><?= e($course['instructor_name']) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($course['description']): ?>
                                <div class="mb-4">
                                    <h6 class="fw-bold">Popis kurzu:</h6>
                                    <p class="text-muted"><?= e($course['description']) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4">
                            <!-- Payment info -->
                            <div class="bg-light p-3 rounded mb-3">
                                <h6 class="fw-bold mb-3">Platobné informácie</h6>
                                
                                <div class="alert alert-info mb-3">
                                    <i class="fas fa-university me-1"></i>
                                    <strong>Bankový prevod</strong>
                                </div>
                                <div class="text-center">
                                    <div class="text-muted small">Cena kurzu</div>
                                    <div class="h4 text-primary"><?= formatPrice($price) ?></div>
                                    <div class="text-muted small">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Platba bankovým prevodom alebo v hotovosti
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Registration form -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Dokončenie registrácie</h5>
                    
                    <form method="POST" action="<?= url('pages/register-course.php') ?>">
                        <input type="hidden" name="course_id" value="<?= $courseId ?>">
                        <input type="hidden" name="confirmed" value="1">
                        
                        <div class="mb-4">
                            <label for="notes" class="form-label">Poznámka (nepovinné)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Máte nejaké špecifické požiadavky alebo poznámky ku kurzu?"></textarea>
                            <div class="form-text">Vaša poznámka bude viditeľná administrátorovi.</div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Informácia o platbe:</strong> Po potvrdení registrácie budete presmerovaný na stránku s platobými údajmi a QR kódom. Platobné pokyny budú odoslané aj na váš email.
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="<?= url('pages/courses.php') ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Zrušiť
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-check me-1"></i>Potvrdiť registráciu za <?= formatPrice($price) ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>