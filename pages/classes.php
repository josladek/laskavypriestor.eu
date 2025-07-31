<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Auto-close expired classes
autoCloseClasses();

// Get filter parameters
$selectedType = $_GET['type'] ?? 'all';

// Get available types with counts
function getTypeStats() {
    $sql = "
        SELECT yc.type, COUNT(*) as count
        FROM yoga_classes yc 
        WHERE (yc.date > CURDATE() OR (yc.date = CURDATE() AND yc.time_end >= CURTIME()))
        AND yc.status = 'active'
        AND yc.course_id IS NULL
        GROUP BY yc.type
        ORDER BY yc.type ASC
    ";
    
    $typeStats = [];
    $results = db()->fetchAll($sql);
    
    foreach ($results as $result) {
        $typeStats[$result['type']] = $result['count'];
    }
    
    return $typeStats;
}

// Get type statistics and all types
$typeStats = getTypeStats();
$types = getLessonTypes();

// Calculate total classes
$totalClasses = array_sum($typeStats);

// Get classes with filters
$filters = ['type' => $selectedType];
$classes = getClasses($filters);

$currentPage = 'classes';
$pageTitle = 'Lekcie';
?>

<?php include '../includes/header.php'; ?>

<div class="container my-5">
    <!-- Page Header -->
    <div class="text-center mb-5">
        <h1 class="display-4 fw-bold mb-3">Lekcie</h1>
        <p class="lead text-muted">Kliknite na dlaždicu a vyberte si typ lekcie</p>
    </div>

    <!-- Type Filter Tiles -->
    <div class="row g-3 mb-5">
        <?php 
        // Define pastel colors for each tile
        $colors = [
            'all' => ['bg' => '#E8F5E8', 'border' => '#A8D8A8', 'icon' => 'fas fa-th-large'],
            0 => ['bg' => '#FFE8E8', 'border' => '#FFB3B3', 'icon' => 'fas fa-heart'],
            1 => ['bg' => '#E8F0FF', 'border' => '#B3D1FF', 'icon' => 'fas fa-leaf'],
            2 => ['bg' => '#FFF8E8', 'border' => '#FFE8B3', 'icon' => 'fas fa-sun'],
            3 => ['bg' => '#F0E8FF', 'border' => '#D1B3FF', 'icon' => 'fas fa-moon'],
            4 => ['bg' => '#E8FFFF', 'border' => '#B3FFFF', 'icon' => 'fas fa-water'],
            5 => ['bg' => '#FFE8F0', 'border' => '#FFB3D1', 'icon' => 'fas fa-flower'],
        ];
        $colorIndex = 0;
        ?>
        
        <!-- All Classes Tile -->
        <div class="col-lg-3 col-md-4 col-sm-6">
            <a href="?type=all" class="text-decoration-none">
                <div class="card h-100 filter-tile <?= $selectedType === 'all' ? 'active' : '' ?>" 
                     style="background-color: <?= $colors['all']['bg'] ?>; border: 2px solid <?= $colors['all']['border'] ?>;">
                    <div class="card-body text-center py-4">
                        <div class="mb-3">
                            <i class="<?= $colors['all']['icon'] ?> fa-2x" style="color: <?= $colors['all']['border'] ?>;"></i>
                        </div>
                        <h5 class="card-title fw-bold mb-2">Všetky lekcie</h5>
                        <p class="card-text">
                            <span class="badge rounded-pill" style="background-color: <?= $colors['all']['border'] ?>; color: white;">
                                <?= $totalClasses ?> lekcií
                            </span>
                        </p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Type Specific Tiles -->
        <?php foreach ($types as $type): 
            $count = $typeStats[$type['name']] ?? 0;
            if ($count === 0) continue; // Skip types with no classes
            
            $colorIndex++;
            $colorKey = $colorIndex % count($colors);
            $color = $colors[$colorKey] ?? $colors[0];
        ?>
            <div class="col-lg-3 col-md-4 col-sm-6">
                <a href="?type=<?= urlencode($type['name']) ?>" class="text-decoration-none">
                    <div class="card h-100 filter-tile <?= $selectedType === $type['name'] ? 'active' : '' ?>" 
                         style="background-color: <?= $color['bg'] ?>; border: 2px solid <?= $color['border'] ?>;">
                        <div class="card-body text-center py-4">
                            <div class="mb-3">
                                <i class="<?= $color['icon'] ?> fa-2x" style="color: <?= $color['border'] ?>;"></i>
                            </div>
                            <h5 class="card-title fw-bold mb-2"><?= e($type['name']) ?></h5>
                            <p class="card-text">
                                <span class="badge rounded-pill" style="background-color: <?= $color['border'] ?>; color: white;">
                                    <?= $count ?> <?= $count === 1 ? 'lekcia' : ($count < 5 ? 'lekcie' : 'lekcií') ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Results Header -->
    <?php if ($selectedType !== 'all'): ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0">
                Lekcie typu: <span class="text-sage"><?= e($selectedType) ?></span>
                <small class="text-muted">(<?= count($classes) ?> <?= count($classes) === 1 ? 'lekcia' : (count($classes) < 5 ? 'lekcie' : 'lekcií') ?>)</small>
            </h3>
            <a href="?" class="btn btn-outline-sage btn-sm">
                <i class="fas fa-times me-1"></i> Zrušiť filter
            </a>
        </div>
    <?php endif; ?>

    <!-- Classes Grid -->
    <?php if (!empty($classes)): ?>
        <div class="row g-4">
            <?php foreach ($classes as $class): ?>
                <div class="col-lg-6 col-xl-4">
                    <div class="card h-100 class-card position-relative">
                        <!-- Registration Status Badge -->
                        <?php if (isLoggedIn() && (int)$class['user_registered'] > 0): ?>
                            <div class="position-absolute top-0 end-0 m-2" style="z-index: 10;">
                                <span class="badge bg-success rounded-pill">
                                    <i class="fas fa-check"></i> Prihlásený
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($class['image_url']): ?>
                            <img src="<?= url('uploads/classes/' . $class['image_url']) ?>" class="card-img-top" alt="<?= e($class['name']) ?>" style="height: 200px; object-fit: cover;">
                        <?php else: ?>
                            <div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height: 200px;">
                                <svg width="80" height="80" viewBox="0 0 100 100">
                                    <defs>
                                        <linearGradient id="placeholder-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                            <stop offset="0%" style="stop-color:var(--sage);stop-opacity:0.3" />
                                            <stop offset="100%" style="stop-color:var(--sage-dark);stop-opacity:0.6" />
                                        </linearGradient>
                                    </defs>
                                    <path d="M50 20 C40 30, 40 40, 50 45 C60 40, 60 30, 50 20" fill="url(#placeholder-gradient)"/>
                                    <path d="M65 35 C55 25, 45 25, 50 35 C55 45, 65 45, 65 35" fill="url(#placeholder-gradient)"/>
                                    <path d="M50 80 C60 70, 60 60, 50 55 C40 60, 40 70, 50 80" fill="url(#placeholder-gradient)"/>
                                    <path d="M35 35 C45 25, 55 25, 50 35 C45 45, 35 45, 35 35" fill="url(#placeholder-gradient)"/>
                                    <circle cx="50" cy="50" r="8" fill="var(--sage)"/>
                                </svg>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title fw-bold mb-0"><?= e($class['name']) ?></h5>
                                <span class="badge bg-sage"><?= e($class['level']) ?></span>
                            </div>
                            
                            <p class="card-text text-muted"><?= e($class['description']) ?></p>
                            
                            <div class="class-details mb-3">
                                <div class="row text-sm">
                                    <div class="col-6">
                                        <i class="fas fa-user text-sage me-1"></i>
                                        <small class="fw-bold"><?= e($class['lektor_name'] ?? $class['lektor']) ?></small>
                                    </div>
                                    <div class="col-6">
                                        <i class="fas fa-tag text-sage me-1"></i>
                                        <small class="fw-bold"><?= e($class['type']) ?></small>
                                    </div>
                                    <div class="col-6 mt-1">
                                        <i class="fas fa-calendar text-sage me-1"></i>
                                        <small class="fw-bold"><?= formatDate($class['date']) ?></small>
                                    </div>
                                    <div class="col-6 mt-1">
                                        <i class="fas fa-clock text-sage me-1"></i>
                                        <small class="fw-bold"><?= formatTime($class['time_start']) ?> - <?= formatTime($class['time_end']) ?></small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="pricing-info bg-light p-3 rounded mb-3">
                                <div class="row">
                                    <div class="col-6 text-center">
                                        <div class="text-muted small">Na mieste</div>
                                        <div class="fw-bold"><?= formatPrice($class['price']) ?></div>
                                    </div>
                                    <div class="col-6 text-center">
                                        <div class="text-muted small">S kreditom</div>
                                        <div class="fw-bold text-sage"><?= formatPrice($class['price_with_credit']) ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-users me-1"></i>
                                    <?= (int)$class['registered_count'] ?>/<?= (int)$class['capacity'] ?> prihlásených
                                </small>
                                
                                <?php if (isLoggedIn()): ?>
                                    <?php if ((int)$class['user_registered'] > 0): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check me-1"></i>Prihlásený
                                        </span>
                                    <?php elseif (isClassClosed($class['id'])): ?>
                                        <button class="btn btn-outline-secondary btn-sm" disabled>
                                            <i class="fas fa-clock me-1"></i>Uzavreté
                                        </button>
                                    <?php elseif (isClassFull($class['id'])): ?>
                                        <button class="btn btn-outline-secondary btn-sm" disabled>
                                            <i class="fas fa-users me-1"></i>Obsadené
                                        </button>
                                    <?php else: ?>
                                        <a href="<?= url('pages/class-registration-confirm.php?id=' . $class['id']) ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-plus me-1"></i>Prihlásiť sa
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="<?= url('pages/login.php') ?>" class="btn btn-outline-primary btn-sm">
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
                        <linearGradient id="empty-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:var(--sage);stop-opacity:0.2" />
                            <stop offset="100%" style="stop-color:var(--sage-dark);stop-opacity:0.4" />
                        </linearGradient>
                    </defs>
                    <circle cx="50" cy="50" r="40" fill="none" stroke="url(#empty-gradient)" stroke-width="2"/>
                    <path d="M35 45 Q50 35 65 45" fill="none" stroke="url(#empty-gradient)" stroke-width="2"/>
                    <circle cx="40" cy="42" r="2" fill="var(--sage)"/>
                    <circle cx="60" cy="42" r="2" fill="var(--sage)"/>
                </svg>
            </div>
            <h3 class="text-muted">Žiadne lekcie</h3>
            <p class="text-muted">Pre zadané kritériá sme nenašli žiadne lekcie.</p>
            <a href="<?= url('pages/classes.php') ?>" class="btn btn-primary">
                Zobraziť všetky lekcie
            </a>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>