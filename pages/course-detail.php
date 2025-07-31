<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Detail kurzu';
$currentPage = 'courses';

// Get course ID from URL
$courseId = (int)($_GET['id'] ?? 0);

if (!$courseId) {
    header('Location: ' . url('pages/courses.php'));
    exit;
}

// Get course details
$course = db()->fetch("
    SELECT c.*, 
           u.name as lektor_name, 
           u.email as lektor_email,
           COUNT(cr.id) as registered_count,
           (SELECT COUNT(*) FROM yoga_classes yc WHERE yc.course_id = c.id) as total_lessons,
           (SELECT COUNT(*) FROM yoga_classes yc WHERE yc.course_id = c.id AND yc.date < NOW()) as completed_lessons
    FROM courses c
    LEFT JOIN users u ON c.instructor_id = u.id
    LEFT JOIN course_registrations cr ON c.id = cr.course_id AND cr.status = 'confirmed'
    WHERE c.id = ?
    GROUP BY c.id, u.name, u.email
", [$courseId]);

if (!$course) {
    header('Location: ' . url('pages/courses.php'));
    exit;
}

// Check if user is registered for this course
$isRegistered = false;
$userRegistration = null;
$registrationStatus = null;
if (isLoggedIn()) {
    $currentUser = getCurrentUser();
    $userRegistration = db()->fetch("
        SELECT * FROM course_registrations 
        WHERE course_id = ? AND user_id = ?
    ", [$courseId, $currentUser['id']]);
    
    if ($userRegistration) {
        $isRegistered = ($userRegistration['status'] === 'confirmed');
        $registrationStatus = $userRegistration['status'];
    }
}

// Check if course is closed for registration
$isClosed = (strtotime($course['start_date']) < time());
$isFull = isCourseFull($courseId);

// Get course lessons
$lessons = db()->fetchAll("
    SELECT yc.*, 
           COUNT(r.id) as registered_count,
           (CASE WHEN yc.date < NOW() THEN 1 ELSE 0 END) as is_completed
    FROM yoga_classes yc
    LEFT JOIN registrations r ON yc.id = r.class_id AND r.status = 'confirmed'
    WHERE yc.course_id = ?
    GROUP BY yc.id
    ORDER BY yc.date ASC
", [$courseId]);

// Calculate course progress
$progressPercentage = 0;
if ($course['total_lessons'] > 0) {
    $progressPercentage = ($course['completed_lessons'] / $course['total_lessons']) * 100;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <!-- Course Details -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <?php if ($course['image_url']): ?>
                    <img src="<?= url('uploads/courses/' . $course['image_url']) ?>" class="card-img-top" alt="<?= e($course['name']) ?>" style="height: 300px; object-fit: cover;">
                <?php else: ?>
                    <div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height: 300px;">
                        <svg width="120" height="120" viewBox="0 0 100 100">
                            <defs>
                                <linearGradient id="course-detail-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" style="stop-color:var(--sage);stop-opacity:0.3" />
                                    <stop offset="100%" style="stop-color:var(--sage-dark);stop-opacity:0.6" />
                                </linearGradient>
                            </defs>
                            <circle cx="50" cy="50" r="35" fill="none" stroke="url(#course-detail-gradient)" stroke-width="3"/>
                            <path d="M30 40 Q50 20 70 40" fill="none" stroke="url(#course-detail-gradient)" stroke-width="2"/>
                            <path d="M30 60 Q50 80 70 60" fill="none" stroke="url(#course-detail-gradient)" stroke-width="2"/>
                            <circle cx="50" cy="50" r="6" fill="var(--sage)"/>
                        </svg>
                    </div>
                <?php endif; ?>
                
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h1 class="card-title h3 fw-bold mb-0"><?= e($course['name']) ?></h1>
                        <span class="badge bg-success fs-6"><?= (int)$course['total_lessons'] ?> lekcií</span>
                    </div>
                    
                    <p class="card-text lead"><?= e($course['description']) ?></p>
                    
                    <!-- Course Info -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-user text-primary me-2"></i>
                                <div>
                                    <small class="text-muted">Lektor</small>
                                    <div class="fw-bold"><?= e($course['lektor_name']) ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-calendar text-primary me-2"></i>
                                <div>
                                    <small class="text-muted">Obdobie</small>
                                    <div class="fw-bold">
                                        <?= formatDate($course['start_date']) ?> - <?= formatDate($course['end_date']) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-users text-primary me-2"></i>
                                <div>
                                    <small class="text-muted">Kapacita</small>
                                    <div class="fw-bold"><?= (int)$course['registered_count'] ?>/<?= (int)$course['capacity'] ?> prihlásených</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-chart-line text-primary me-2"></i>
                                <div>
                                    <small class="text-muted">Pokrok</small>
                                    <div class="fw-bold"><?= number_format($progressPercentage, 0) ?>% dokončené</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <small class="text-muted">Pokrok kurzu</small>
                            <small class="text-muted"><?= (int)$course['completed_lessons'] ?>/<?= (int)$course['total_lessons'] ?> lekcií</small>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" style="width: <?= $progressPercentage ?>%"></div>
                        </div>
                    </div>
                    
                    <!-- Registration Status -->
                    <?php if ($userRegistration): ?>
                        <?php if ($registrationStatus === 'confirmed'): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Ste prihlásený na tento kurz!</strong>
                                <br><small>Platba potvrdená • Registrácia aktívna</small>
                            </div>
                        <?php elseif ($registrationStatus === 'pending'): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-clock me-2"></i>
                                <strong>Čakáte na potvrdenie platby</strong>
                                <br><small>Registrácia bude potvrdená po uhradení kurzu</small>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Stav registrácie: <?= ucfirst($registrationStatus) ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Course Lessons -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Lekcie kurzu</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($lessons)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($lessons as $index => $lesson): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <?php if ($lesson['is_completed']): ?>
                                                <i class="fas fa-check-circle text-success"></i>
                                            <?php else: ?>
                                                <i class="far fa-circle text-muted"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-1"><?= e($lesson['name']) ?></h6>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?= formatDate($lesson['date']) ?> o <?= formatTime($lesson['time_start']) ?>
                                            </small>
                                            <?php if ($lesson['description']): ?>
                                                <p class="mb-0 mt-1 text-muted small"><?= e($lesson['description']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted"><?= (int)$lesson['registered_count'] ?> prihlásených</small>
                                        <?php if ($lesson['is_completed']): ?>
                                            <br><small class="badge bg-success">Dokončené</small>
                                        <?php else: ?>
                                            <br><small class="badge bg-secondary">Nadchádzajúce</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times text-muted fs-1 mb-3"></i>
                            <h5 class="text-muted">Žiadne lekcie</h5>
                            <p class="text-muted">Pre tento kurz zatiaľ neboli naplánované žiadne lekcie.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Price Card -->
            <div class="card mb-4">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <div class="h2 text-primary mb-0"><?= formatPrice($course['price']) ?></div>
                        <small class="text-muted">Cena kurzu</small>
                        <br><small class="text-info">
                            <i class="fas fa-university me-1"></i>
                            Platba bankovým prevodom alebo v hotovosti
                        </small>
                    </div>
                    
                    <?php if (isLoggedIn()): ?>
                        <?php if ($isRegistered): ?>
                            <div class="d-grid">
                                <button class="btn btn-success" disabled>
                                    <i class="fas fa-check me-2"></i>Prihlásený
                                </button>
                            </div>
                        <?php elseif ($isClosed): ?>
                            <div class="d-grid">
                                <button class="btn btn-outline-secondary" disabled>
                                    Registrácia uzavretá
                                </button>
                            </div>
                        <?php elseif ($isFull): ?>
                            <div class="d-grid">
                                <button class="btn btn-outline-secondary" disabled>
                                    Kurz je plný
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="d-grid">
                                <a href="<?= url('pages/course-registration-confirm.php?id=' . $courseId) ?>" class="btn btn-primary w-100">
                                    <i class="fas fa-credit-card me-2"></i>Prihlásiť sa na kurz
                                </a>
                                
                                <small class="text-info mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Okamžitá registrácia - platba v štúdiu alebo prevodom
                                </small>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="d-grid">
                            <a href="<?= url('pages/login.php') ?>" class="btn btn-outline-primary">
                                Prihláste sa pre registráciu
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Course Info -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Informácie o kurze</h6>
                </div>
                <div class="card-body">
                    <?php if (isset($course['category']) && !empty($course['category'])): ?>
                    <div class="mb-3">
                        <small class="text-muted d-block">Kategória</small>
                        <span class="badge bg-sage"><?= e($course['category']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($course['level']) && !empty($course['level'])): ?>
                    <div class="mb-3">
                        <small class="text-muted d-block">Úroveň</small>
                        <span class="badge bg-secondary"><?= e($course['level']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <small class="text-muted d-block">Kapacita</small>
                        <div class="d-flex justify-content-between">
                            <span><?= (int)$course['registered_count'] ?>/<?= (int)$course['capacity'] ?> ľudí</span>
                            <small class="text-muted">
                                <?= $course['capacity'] > 0 ? number_format(($course['registered_count'] / $course['capacity']) * 100, 0) : 0 ?>% obsadené
                            </small>
                        </div>
                        <div class="progress mt-1" style="height: 4px;">
                            <div class="progress-bar bg-success" style="width: <?= $course['capacity'] > 0 ? (($course['registered_count'] / $course['capacity']) * 100) : 0 ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted d-block">Trvanie</small>
                        <span><?= (int)$course['total_lessons'] ?> lekcií</span>
                    </div>
                    
                    <div class="mb-0">
                        <small class="text-muted d-block">Pokrok</small>
                        <span><?= number_format($progressPercentage, 0) ?>% dokončené</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Back Button -->
    <div class="row mt-4">
        <div class="col-12">
            <a href="<?= url('pages/courses.php') ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Späť na kurzy
            </a>
        </div>
    </div>
</div>

<!-- CSS moved to laskavypriestor.css -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>