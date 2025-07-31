<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Get all instructors with their profiles
$instructors = db()->fetchAll("
    SELECT u.*, ip.bio, ip.certifications, ip.specializations, ip.experience_years, 
           ip.hourly_rate, ip.photo_url,
           (SELECT COUNT(*) FROM yoga_classes yc WHERE yc.instructor_id = u.id AND yc.status = 'active' AND yc.date >= CURDATE()) as upcoming_classes_count,
           (SELECT COUNT(*) FROM yoga_classes yc WHERE yc.instructor_id = u.id AND yc.status = 'completed') as total_classes_count
    FROM users u 
    LEFT JOIN instructor_profiles ip ON u.id = ip.user_id 
    WHERE u.role = 'lektor' 
    ORDER BY u.name ASC
");

$currentPage = 'instructors';
$pageTitle = 'Lektori';
?>

<?php include '../includes/header.php'; ?>

<div class="container my-5">
    <!-- Page Header -->
    <div class="text-center mb-5">
        <h1 class="display-4 fw-bold mb-3">Naši lektori</h1>
        <p class="lead text-muted">Spoznajte našich skúsených a certifikovaných inštruktorov</p>
    </div>

    <!-- Introduction -->
    <!-- TODO
    <div class="row justify-content-center mb-5">
        <div class="col-lg-8">
            <div class="card bg-light border-0">
                <div class="card-body p-4 text-center">
                    <h4 class="card-title mb-3">Prečo sú naši lektori výnimoční?</h4>
                    <p class="card-text">
                        Každý z našich lektorov prešiel dôkladným výberovým procesom a má bohaté skúsenosti 
                        s vyučovaním jogy. Všetci sú certifikovaní a neustále sa vzdelávajú, aby vám mohli 
                        poskytovať ten najkvalitnejší zážitok z jogy.
                    </p>
                </div>
            </div>
        </div>
    </div> -->

    <!-- Instructors Grid -->
    <?php if (!empty($instructors)): ?>
        <div class="row g-4">
            <?php foreach ($instructors as $instructor): ?>
                <div class="col-lg-6 col-xl-4">
                    <div class="card h-100 instructor-card">
                        <div class="position-relative">
                            <?php if ($instructor['photo_url']): ?>
                                <img src="<?= url('uploads/' . $instructor['photo_url']) ?>" class="card-img-top instructor-photo" alt="<?= e($instructor['name']) ?>">
                            <?php else: ?>
                                <div class="card-img-top instructor-placeholder d-flex align-items-center justify-content-center">
                                    <svg width="80" height="80" viewBox="0 0 100 100">
                                        <defs>
                                            <linearGradient id="instructor-gradient-<?= $instructor['id'] ?>" x1="0%" y1="0%" x2="100%" y2="100%">
                                                <stop offset="0%" style="stop-color:var(--sage);stop-opacity:0.3" />
                                                <stop offset="100%" style="stop-color:var(--sage-dark);stop-opacity:0.6" />
                                            </linearGradient>
                                        </defs>
                                        <circle cx="50" cy="35" r="15" fill="url(#instructor-gradient-<?= $instructor['id'] ?>)"/>
                                        <path d="M20 80 Q50 60 80 80" fill="url(#instructor-gradient-<?= $instructor['id'] ?>)"/>
                                    </svg>
                                </div>
                            <?php endif; ?>
                            
                            <div class="instructor-overlay">
                                <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#instructorModal<?= $instructor['id'] ?>">
                                    <i class="fas fa-info-circle me-1"></i> Detail
                                </button>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <h5 class="card-title fw-bold mb-2"><?= e($instructor['name']) ?></h5>
                            
                            <?php if ($instructor['experience_years']): ?>
                                <div class="mb-2">
                                    <span class="badge bg-primary">
                                        <?= (int)$instructor['experience_years'] ?> rokov praxe
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($instructor['bio']): ?>
                                <p class="card-text text-muted small">
                                    <?= e(strlen($instructor['bio']) > 120 ? substr($instructor['bio'], 0, 120) . '...' : $instructor['bio']) ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if ($instructor['specializations']): ?>
                                <div class="mb-3">
                                    <div class="small text-muted mb-1">Špecializácie:</div>
                                    <?php 
                                    $specializations = explode(',', $instructor['specializations']);
                                    foreach ($specializations as $spec): 
                                        $spec = trim($spec);
                                        if (!empty($spec)):
                                    ?>
                                        <span class="badge bg-light text-dark me-1 mb-1"><?= e($spec) ?></span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="instructor-stats">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="fw-bold text-primary"><?= (int)$instructor['upcoming_classes_count'] ?></div>
                                        <div class="small text-muted">Nadchádzajúce lekcie</div>
                                    </div>
                                    <div class="col-6">
                                        <div class="fw-bold text-success"><?= (int)$instructor['total_classes_count'] ?></div>
                                        <div class="small text-muted">Dokončené lekcie</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-footer bg-transparent border-top-0">
                            <div class="d-grid">
                                <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#instructorModal<?= $instructor['id'] ?>">
                                    <i class="fas fa-user me-1"></i> Zobraziť profil
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Instructor Modal -->
                <div class="modal fade" id="instructorModal<?= $instructor['id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><?= e($instructor['name']) ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-4 text-center mb-3">
                                        <?php if ($instructor['photo_url']): ?>
                                            <img src="<?= url('uploads/' . $instructor['photo_url']) ?>" class="img-fluid rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;" alt="<?= e($instructor['name']) ?>">
                                        <?php else: ?>
                                            <div class="bg-light rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 150px; height: 150px;">
                                                <i class="fas fa-user fa-4x text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($instructor['experience_years']): ?>
                                            <div class="badge bg-primary mb-2">
                                                <?= (int)$instructor['experience_years'] ?> rokov praxe
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-8">
                                        <?php if ($instructor['bio']): ?>
                                            <h6>O lektorovi</h6>
                                            <p class="text-muted"><?= nl2br(e($instructor['bio'])) ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if ($instructor['certifications']): ?>
                                            <h6>Certifikácie</h6>
                                            <p class="text-muted"><?= nl2br(e($instructor['certifications'])) ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if ($instructor['specializations']): ?>
                                            <h6>Špecializácie</h6>
                                            <div class="mb-3">
                                                <?php 
                                                $specializations = explode(',', $instructor['specializations']);
                                                foreach ($specializations as $spec): 
                                                    $spec = trim($spec);
                                                    if (!empty($spec)):
                                                ?>
                                                    <span class="badge bg-light text-dark me-1 mb-1"><?= e($spec) ?></span>
                                                <?php 
                                                    endif;
                                                endforeach; 
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="row">
                                            <div class="col-6">
                                                <h6>Štatistiky</h6>
                                                <ul class="list-unstyled small">
                                                    <li><strong>Nadchádzajúce lekcie:</strong> <?= (int)$instructor['upcoming_classes_count'] ?></li>
                                                    <li><strong>Dokončené lekcie:</strong> <?= (int)$instructor['total_classes_count'] ?></li>
                                                </ul>
                                            </div>
                                            
                                            <div class="col-6">
                                                <h6>Kontakt</h6>
                                                <ul class="list-unstyled small">
                                                    <li><i class="fas fa-envelope me-2"></i> <?= e($instructor['email']) ?></li>
                                                    <?php if ($instructor['phone']): ?>
                                                        <li><i class="fas fa-phone me-2"></i> <?= e($instructor['phone']) ?></li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <a href="<?= url('pages/classes.php?instructor=' . $instructor['id']) ?>" class="btn btn-primary">
                                    <i class="fas fa-calendar me-1"></i> Zobraziť lekcie
                                </a>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zavrieť</button>
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
                        <linearGradient id="empty-instructors-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:var(--sage);stop-opacity:0.2" />
                            <stop offset="100%" style="stop-color:var(--sage-dark);stop-opacity:0.4" />
                        </linearGradient>
                    </defs>
                    <circle cx="50" cy="35" r="15" fill="url(#empty-instructors-gradient)"/>
                    <path d="M20 80 Q50 60 80 80" fill="url(#empty-instructors-gradient)"/>
                </svg>
            </div>
            <h3 class="text-muted">Žiadni lektori</h3>
            <p class="text-muted">V súčasnosti nemáme žiadnych aktívnych lektorov.</p>
        </div>
    <?php endif; ?>

    <!-- Join Team Section -->
    <!-- TODO
    <div class="row justify-content-center mt-5">
        <div class="col-lg-8">
            <div class="card bg-sage text-white">
                <div class="card-body p-4 text-center">
                    <h4 class="card-title text-white mb-3">Chcete sa pridať k nášmu tímu?</h4>
                    <p class="card-text">
                        Hľadáme certifikovaných lektorov jogy, ktorí chcú zdieľať svoju lásku k joge 
                        v pokojnom a podporujúcom prostredí. Ponúkame flexibilné rozvrhy a spravodlivé odmeny.
                    </p>
                    <a href="mailto:info@laskavypriestor.eu?subject=Záujem o pozíciu lektora" class="btn btn-light">
                        <i class="fas fa-envelope me-2"></i> Kontaktujte nás
                    </a>
                </div>
            </div>
        </div>
    </div> -->
</div>

<!-- CSS moved to laskavypriestor.css -->


<?php include '../includes/footer.php'; ?>