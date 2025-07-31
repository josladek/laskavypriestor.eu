<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Get all active courses
$courses = getCourses(true);

$currentPage = 'courses';
$pageTitle = 'Kurzy';
?>

<?php include '../includes/header.php'; ?>

<div class="container my-5">
    <!-- Page Header -->
    <div class="text-center mb-5">
        <h1 class="display-4 fw-bold mb-3">Kurzy</h1>
        <p class="lead text-muted">Dlhodobé kurzy pre systematický rozvoj vašej praxe</p>
    </div>

    <!-- Course Benefits -->
    <div class="row justify-content-center mb-5">
        <div class="col-lg-8">
            <div class="card bg-light border-0">
                <div class="card-body p-4">
                    <h4 class="card-title text-center mb-4">Prečo si vybrať kurz?</h4>
                    <div class="row g-3">
                        <div class="col-md-4 text-center">
                            <i class="fas fa-chart-line fa-2x text-primary mb-2"></i>
                            <h6>Systematický pokrok</h6>
                            <small class="text-muted">Postupné budovanie techniky a sily</small>
                        </div>
                        <div class="col-md-4 text-center">
                            <i class="fas fa-users fa-2x text-primary mb-2"></i>
                            <h6>Stabilná skupina</h6>
                            <small class="text-muted">Vytvorenie komunity a priateľstiev</small>
                        </div>
                        <div class="col-md-4 text-center">
                            <i class="fas fa-euro-sign fa-2x text-primary mb-2"></i>
                            <h6>Výhodnejšie ceny</h6>
                            <small class="text-muted">Ušetríte oproti jednotlivým lekciám</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Courses Grid -->
    <?php if (!empty($courses)): ?>
        <div class="row g-4">
            <?php foreach ($courses as $course): ?>
                <div class="col-lg-6 col-xl-4">
                    <div class="card h-100 course-card">
                        <?php if ($course['image_url']): ?>
                            <img src="<?= url('uploads/courses/' . $course['image_url']) ?>" class="card-img-top" alt="<?= e($course['name']) ?>" style="height: 200px; object-fit: cover;">
                        <?php else: ?>
                            <div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height: 200px;">
                                <svg width="80" height="80" viewBox="0 0 100 100">
                                    <defs>
                                        <linearGradient id="course-gradient-<?= $course['id'] ?>" x1="0%" y1="0%" x2="100%" y2="100%">
                                            <stop offset="0%" style="stop-color:var(--sage);stop-opacity:0.3" />
                                            <stop offset="100%" style="stop-color:var(--sage-dark);stop-opacity:0.6" />
                                        </linearGradient>
                                    </defs>
                                    <circle cx="50" cy="50" r="35" fill="none" stroke="url(#course-gradient-<?= $course['id'] ?>)" stroke-width="2"/>
                                    <path d="M30 40 Q50 20 70 40" fill="none" stroke="url(#course-gradient-<?= $course['id'] ?>)" stroke-width="2"/>
                                    <path d="M30 60 Q50 80 70 60" fill="none" stroke="url(#course-gradient-<?= $course['id'] ?>)" stroke-width="2"/>
                                    <circle cx="50" cy="50" r="5" fill="var(--sage)"/>
                                </svg>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title fw-bold mb-0"><?= e($course['name']) ?></h5>
                                <span class="badge bg-success"><?= (int)$course['total_lessons'] ?> lekcií</span>
                            </div>
                            
                            <p class="card-text text-muted"><?= e($course['description']) ?></p>
                            
                            <div class="course-details mb-3">
                                <div class="row text-sm">
                                    <div class="col-6">
                                        <i class="fas fa-user text-primary me-1"></i>
                                        <small class="fw-bold"><?= e($course['lektor_name']) ?></small>
                                    </div>
                                    <div class="col-6">
                                        <i class="fas fa-clock text-primary me-1"></i>
                                        <small class="fw-bold"><?= (int)$course['lesson_duration_minutes'] ?> min</small>
                                    </div>
                                    <div class="col-6 mt-1">
                                        <i class="fas fa-calendar text-primary me-1"></i>
                                        <small class="fw-bold"><?= formatDate($course['start_date']) ?></small>
                                    </div>
                                    <div class="col-6 mt-1">
                                        <i class="fas fa-calendar-day text-primary me-1"></i>
                                        <small class="fw-bold"><?= $course['day_name']() ?> <?= formatTime($course['time_start']) ?></small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="course-period mb-3 p-2 bg-light rounded">
                                <div class="text-center">
                                    <small class="text-muted">Obdobie kurzu</small>
                                    <div class="fw-bold"><?= formatDate($course['start_date']) ?> - <?= formatDate($course['end_date']) ?></div>
                                </div>
                            </div>
                            
                            <div class="pricing-info bg-light p-3 rounded mb-3">
                                <div class="text-center">
                                    <div class="text-muted small">Cena kurzu</div>
                                    <div class="fw-bold text-primary fs-5"><?= formatPrice($course['price']) ?></div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <small class="text-muted">
                                    <i class="fas fa-users me-1"></i>
                                    <?= (int)$course['registered_count'] ?>/<?= (int)$course['capacity'] ?> prihlásených
                                </small>
                                
                                <div class="progress" style="width: 100px; height: 6px;">
                                    <div class="progress-bar bg-success" style="width: <?= ($course['capacity'] > 0) ? (($course['registered_count'] / $course['capacity']) * 100) : 0 ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <?php if (isLoggedIn()): ?>
                                    <?php if (isCourseFull($course['id'])): ?>
                                        <button class="btn btn-outline-secondary" disabled>
                                            Kurz je plný
                                        </button>
                                    <?php else: ?>
                                        <a href="<?= url('pages/course-detail.php?id=' . $course['id']) ?>" class="btn btn-primary">
                                            Zobraziť detail
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="<?= url('pages/login.php') ?>" class="btn btn-outline-primary">
                                        Prihlásiť sa na účet
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
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
            <p class="text-muted">V súčasnosti nemáme žiadne aktívne kurzy.</p>
            <a href="<?= url('pages/classes.php') ?>" class="btn btn-primary">
                Prehliadnuť jednotlivé lekcie
            </a>
        </div>
    <?php endif; ?>

    <!-- FAQ Section -->
    <div class="row justify-content-center mt-5">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-center mb-4">Často kladené otázky o kurzoch</h5>
                    
                    <div class="accordion" id="courseFaqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#courseFaq1">
                                    Môžem sa pripojiť k už prebiehajúcemu kurzu?
                                </button>
                            </h2>
                            <div id="courseFaq1" class="accordion-collapse collapse" data-bs-parent="#courseFaqAccordion">
                                <div class="accordion-body">
                                    Pripojenie je možné do 2. lekcie kurzu, ak sú ešte voľné miesta. Kontaktujte nás na info@laskavypriestor.eu.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#courseFaq2">
                                    Čo ak zmešká lekciu?
                                </button>
                            </h2>
                            <div id="courseFaq2" class="accordion-collapse collapse" data-bs-parent="#courseFaqAccordion">
                                <div class="accordion-body">
                                    Každý kurz umožňuje 1-2 náhradné lekcie. Môžete sa zúčastniť na parallelnom kurze alebo individuálnej hodine.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#courseFaq3">
                                    Môžem kurz zrušiť?
                                </button>
                            </h2>
                            <div id="courseFaq3" class="accordion-collapse collapse" data-bs-parent="#courseFaqAccordion">
                                <div class="accordion-body">
                                    Kurz môžete zrušiť do 48 hodín pred prvou lekciou s plným vrátením kreditu. Po začatí kurzu sa kredit nevracia.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CSS moved to laskavypriestor.css -->

<?php include '../includes/footer.php'; ?>