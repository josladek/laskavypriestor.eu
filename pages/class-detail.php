<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$pageTitle = 'Detail lekcie';
$currentPage = 'classes';

// Get class ID from URL
$classId = (int)($_GET['id'] ?? 0);

if (!$classId) {
    header('Location: ' . url('pages/classes.php'));
    exit;
}

// Get class details
$class = db()->fetch("
    SELECT yc.*, u.name as lektor_name, u.email as lektor_email,
           COUNT(r.id) as registered_count
    FROM yoga_classes yc
    LEFT JOIN users u ON yc.instructor_id = u.id
    LEFT JOIN registrations r ON yc.id = r.class_id AND r.status = 'confirmed'
    WHERE yc.id = ?
    GROUP BY yc.id
", [$classId]);

if (!$class) {
    header('Location: ' . url('pages/classes.php'));
    exit;
}

// Check if user is registered for this class
$isRegistered = false;
$userRegistration = null;
if (isLoggedIn()) {
    $currentUser = getCurrentUser();
    $userRegistration = db()->fetch("
        SELECT * FROM registrations 
        WHERE class_id = ? AND user_id = ? AND status = 'confirmed'
    ", [$classId, $currentUser['id']]);
    $isRegistered = (bool)$userRegistration;
}

// Check if class is closed for registration
$isClosed = isClassClosed($classId);
$isFull = isClassFull($classId);

// Get other classes by same instructor
$otherClasses = db()->fetchAll("
    SELECT yc.*, COUNT(r.id) as registered_count
    FROM yoga_classes yc
    LEFT JOIN registrations r ON yc.id = r.class_id AND r.status = 'confirmed'
    WHERE yc.instructor_id = ? AND yc.id != ? AND yc.status = 'active' AND yc.date >= CURDATE()
    GROUP BY yc.id
    ORDER BY yc.date ASC
    LIMIT 3
", [$class['instructor_id'], $classId]);

require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <!-- Class Details -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <?php if ($class['image_url']): ?>
                    <img src="<?= url('uploads/classes/' . $class['image_url']) ?>" class="card-img-top" alt="<?= e($class['name']) ?>" style="height: 300px; object-fit: cover;">
                <?php else: ?>
                    <div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height: 300px;">
                        <svg width="120" height="120" viewBox="0 0 100 100">
                            <defs>
                                <linearGradient id="class-detail-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" style="stop-color:var(--sage);stop-opacity:0.3" />
                                    <stop offset="100%" style="stop-color:var(--sage-dark);stop-opacity:0.6" />
                                </linearGradient>
                            </defs>
                            <path d="M50 20 C40 30, 40 40, 50 45 C60 40, 60 30, 50 20" fill="url(#class-detail-gradient)"/>
                            <path d="M65 35 C55 25, 45 25, 50 35 C55 45, 65 45, 65 35" fill="url(#class-detail-gradient)"/>
                            <path d="M50 80 C60 70, 60 60, 50 55 C40 60, 40 70, 50 80" fill="url(#class-detail-gradient)"/>
                            <path d="M35 35 C45 25, 55 25, 50 35 C45 45, 35 45, 35 35" fill="url(#class-detail-gradient)"/>
                            <circle cx="50" cy="50" r="8" fill="var(--sage)"/>
                        </svg>
                    </div>
                <?php endif; ?>
                
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h1 class="card-title h3 fw-bold mb-0"><?= e($class['name']) ?></h1>
                        <span class="badge bg-sage fs-6"><?= e($class['level']) ?></span>
                    </div>
                    
                    <p class="card-text lead"><?= e($class['description']) ?></p>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="fw-bold text-muted mb-2">Detaily lekcie</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-calendar text-sage me-2"></i> <?= formatDate($class['date']) ?></li>
                                <li><i class="fas fa-clock text-sage me-2"></i> <?= formatTime($class['time_start']) ?> - <?= formatTime($class['time_end']) ?></li>
                                <li><i class="fas fa-map-marker-alt text-sage me-2"></i> <?= e($class['location']) ?></li>
                                <li><i class="fas fa-tag text-sage me-2"></i> <?= e($class['type']) ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold text-muted mb-2">Lektor</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-user text-sage me-2"></i> <?= e($class['lektor_name']) ?></li>
                                <li><i class="fas fa-users text-sage me-2"></i> <?= (int)$class['registered_count'] ?>/<?= (int)$class['capacity'] ?> prihlásených</li>
                            </ul>
                        </div>
                    </div>
                    
                    <?php if ($class['notes']): ?>
                        <div class="alert alert-info">
                            <h6 class="fw-bold mb-2">Dôležité informácie</h6>
                            <p class="mb-0"><?= nl2br(e($class['notes'])) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($otherClasses)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Ďalšie lekcie s <?= e($class['lektor_name']) ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($otherClasses as $otherClass): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title"><?= e($otherClass['name']) ?></h6>
                                            <p class="card-text small text-muted">
                                                <?= formatDate($otherClass['date']) ?> • <?= formatTime($otherClass['time_start']) ?>
                                            </p>
                                            <a href="<?= url('pages/class-detail.php?id=' . $otherClass['id']) ?>" class="btn btn-outline-primary btn-sm">
                                                Detail
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Registration Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Registrácia</h5>
                </div>
                <div class="card-body">
                    <div class="pricing-info bg-light p-3 rounded mb-3">
                        <div class="row">
                            <div class="col-6 text-center">
                                <div class="text-muted small">Cena</div>
                                <div class="fw-bold"><?= formatPrice($class['price']) ?></div>
                            </div>
                            <div class="col-6 text-center">
                                <div class="text-muted small">S kreditom</div>
                                <div class="fw-bold text-sage"><?= formatPrice($class['price_with_credit']) ?></div>
                                <div class="savings small text-success">Úspora <?= formatPrice($class['price'] - $class['price_with_credit']) ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($isRegistered): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            Úspešne ste prihlásený na túto lekciu!
                        </div>
                        <p class="text-muted small">
                            Registrovaný: <?= formatDate($userRegistration['registration_date']) ?>
                        </p>
                    <?php elseif (!isLoggedIn()): ?>
                        <a href="<?= url('pages/login.php') ?>" class="btn btn-primary w-100">
                            <i class="fas fa-sign-in-alt me-2"></i>
                            Prihlásiť sa na účet
                        </a>
                        <p class="text-muted small mt-2">Pre registráciu na lekciu sa musíte prihlásiť</p>
                    <?php elseif ($isClosed): ?>
                        <button class="btn btn-outline-secondary w-100" disabled>
                            <i class="fas fa-clock me-2"></i>
                            Registrácia uzavretá
                        </button>
                        <p class="text-muted small mt-2">Registrácia je možná iba do času skončenia lekcie</p>
                    <?php elseif ($isFull): ?>
                        <button class="btn btn-outline-secondary w-100" disabled>
                            <i class="fas fa-users me-2"></i>
                            Lekcia je obsadená
                        </button>
                        <p class="text-muted small mt-2">Kapacita lekcie je naplnená</p>
                    <?php else: ?>
                        <!-- Registration form -->
                        <form method="POST" action="<?= url('pages/register-class.php') ?>" class="mb-3">
                            <input type="hidden" name="class_id" value="<?= $class['id'] ?>">
                            
                            <?php if ($currentUser && $currentUser['role'] === 'klient'): ?>
                                <!-- Automatic payment method display for clients -->
                                <div class="mb-3">
                                    <?php if ($currentUser['eur_balance'] >= $class['price_with_credit']): ?>
                                        <div class="alert alert-success">
                                            <i class="fas fa-credit-card me-2"></i>
                                            <strong>Automaticky sa použije kredit</strong><br>
                                            Cena: <?= formatPrice($class['price_with_credit']) ?> (úspora <?= formatPrice($class['price'] - $class['price_with_credit']) ?>)
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-money-bill-wave me-2"></i>
                                            <strong>Platba na mieste</strong><br>
                                            Cena: <?= formatPrice($class['price']) ?> (hotovosť)
                                        </div>
                                        <small class="text-muted">
                                            Váš kredit: <?= formatPrice($currentUser['eur_balance']) ?> (potrebujete <?= formatPrice($class['price_with_credit']) ?> na kreditovú platbu)
                                        </small>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <!-- Cash payment only for non-clients -->
                                <div class="alert alert-info">
                                    <i class="fas fa-money-bill-wave me-2"></i>
                                    <strong>Platba na mieste</strong><br>
                                    Cena: <?= formatPrice($class['price']) ?> (hotovosť)
                                </div>
                            <?php endif; ?>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-plus me-2"></i>
                                Prihlásiť sa na lekciu
                            </button>
                        </form>
                        
                        <p class="text-muted small">
                            Dostupné miesta: <?= (int)$class['capacity'] - (int)$class['registered_count'] ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Class Info -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Informácie o lekcii</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <strong>Trvanie:</strong> 
                            <?php 
                            // Vypočítaj trvanie z time_start a time_end
                            $start = new DateTime($class['time_start']);
                            $end = new DateTime($class['time_end']);
                            $duration = $end->diff($start);
                            echo $duration->h * 60 + $duration->i; 
                            ?> minút
                        </li>
                        <li class="mb-2">
                            <strong>Úroveň:</strong> 
                            <?= e($class['level']) ?>
                        </li>
                        <li class="mb-2">
                            <strong>Typ:</strong> 
                            <?= e($class['type']) ?>
                        </li>
                        <li class="mb-2">
                            <strong>Status:</strong> 
                            <span class="badge bg-<?= $class['status'] === 'active' ? 'success' : 'secondary' ?>">
                                <?= ucfirst($class['status']) ?>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>