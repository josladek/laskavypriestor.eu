<?php
// Hlavná stránka aplikácie
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../includes/functions.php';
} catch (Exception $e) {
    die("Chyba načítania: " . htmlspecialchars($e->getMessage()));
}

$currentUser = null;
if (isLoggedIn()) {
    $currentUser = getCurrentUser();
}

// Get upcoming classes for homepage
$upcomingClasses = db()->fetchAll("
    SELECT yc.*, u.name as lektor_name,
           (SELECT COUNT(*) FROM registrations WHERE class_id = yc.id AND status = 'confirmed') as registered_count
    FROM yoga_classes yc
    LEFT JOIN users u ON yc.instructor_id = u.id
    WHERE yc.date >= CURDATE() AND yc.status = 'active'
    ORDER BY yc.date, yc.time_start
    LIMIT 6
");

// Get instructors for homepage (simplified query without GROUP BY)
$featuredInstructors = db()->fetchAll("
    SELECT u.id, u.name, u.email, u.role, u.created_at, ip.bio, ip.photo_url
    FROM users u
    LEFT JOIN instructor_profiles ip ON u.id = ip.user_id
    WHERE u.role = 'lektor'
    ORDER BY u.created_at DESC
    LIMIT 3
");

// Add class counts for each instructor
foreach ($featuredInstructors as &$instructor) {
    $classStats = db()->fetch("
        SELECT 
            COUNT(*) as total_classes,
            COUNT(CASE WHEN date >= CURDATE() THEN 1 END) as upcoming_classes
        FROM yoga_classes 
        WHERE instructor_id = ? AND status = 'active'
    ", [$instructor['id']]);
    
    $instructor['total_classes'] = $classStats['total_classes'] ?? 0;
    $instructor['upcoming_classes'] = $classStats['upcoming_classes'] ?? 0;
}

$pageTitle = 'Láskavý Priestor - Jogové štúdio';
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center min-vh-100 py-5">
            <div class="col-lg-6">
                <div class="hero-content">
                    <h1 class="hero-title">Vitajte v Láskavom Priestore</h1>
                    <p class="hero-subtitle">Miesto pokoja, rovnováhy a vnútorného rastu. Objavte silu jogy v našom krásnom štúdiu s profesionálnymi lektormi.</p>
                    
                    <div class="hero-features mb-4">
                        <div class="feature-item">
                            <i class="fas fa-heart text-sage"></i>
                            <span>Láskavý prístup</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-leaf text-sage"></i>
                            <span>Prirodzené prostredie</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-users text-sage"></i>
                            <span>Skúsení lektori</span>
                        </div>
                    </div>
                    
                    <div class="hero-actions">
                        <?php if ($currentUser): ?>
                            <a href="classes.php" class="btn btn-sage btn-lg me-3">Prehliadnuť lekcie</a>
                            <a href="my-classes.php" class="btn btn-outline-sage btn-lg">Moje registrácie</a>
                        <?php else: ?>
                            <a href="register.php" class="btn btn-sage btn-lg me-3">Zaregistrovať sa</a>
                            <a href="classes.php" class="btn btn-outline-sage btn-lg">Prehliadnuť lekcie</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="hero-image text-center">
                    <img src="../assets/images/logo.jpg" alt="Láskavý Priestor Logo" class="img-fluid rounded-3 shadow-lg" style="max-width: 400px; height: auto;">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Pricing Section -->
<section class="pricing-section py-5 bg-light">
    <div class="container">
        <div class="row">
            <div class="col-12 text-center mb-5">
                <h2 class="section-title">Naše ceny</h2>
                <p class="section-subtitle">Transparentný cenník s možnosťou úspory cez kreditný systém</p>
            </div>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="pricing-card">
                    <div class="pricing-header">
                        <h3>Platba na mieste</h3>
                        <div class="price">12€</div>
                        <p>za lekciu</p>
                    </div>
                    <ul class="pricing-features">
                        <li><i class="fas fa-check text-sage"></i> Platba v hotovosti</li>
                        <li><i class="fas fa-check text-sage"></i> Bez registrácie vopred</li>
                        <li><i class="fas fa-check text-sage"></i> Flexibilita</li>
                    </ul>
                </div>
            </div>
            
            <div class="col-md-5">
                <div class="pricing-card featured">
                    <div class="pricing-header">
                        <h3>S kreditom</h3>
                        <div class="price">10€</div>
                        <p>za lekciu</p>
                        <div class="savings">Ušetríte 17%</div>
                    </div>
                    <ul class="pricing-features">
                        <li><i class="fas fa-check text-white"></i> Online registrácia</li>
                        <li><i class="fas fa-check text-white"></i> Automatické rezervácie</li>
                        <li><i class="fas fa-check text-white"></i> História návštev</li>
                        <li><i class="fas fa-check text-white"></i> Email potvrdenia</li>
                    </ul>
                    <?php if (!$currentUser): ?>
                        <a href="register.php" class="btn btn-light btn-block mt-3">Začať ušetrovať</a>
                    <?php else: ?>
                        <a href="buy-credits.php" class="btn btn-light btn-block mt-3">Kúpiť kredity</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Upcoming Classes -->
<?php if (!empty($upcomingClasses)): ?>
<section class="upcoming-classes py-5">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="section-title">Nadchádzajúce lekcie</h2>
                        <p class="section-subtitle">Pripojte sa k našim najbližším lekciam</p>
                    </div>
                    <a href="classes.php" class="btn btn-outline-sage">Všetky lekcie</a>
                </div>
            </div>
        </div>
        
        <div class="row">
            <?php foreach ($upcomingClasses as $class): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="class-card">
                        <div class="class-header">
                            <h5 class="class-name"><?= e($class['name']) ?></h5>
                            <div class="class-meta">
                                <span class="class-type"><?= e($class['type']) ?></span>
                                <span class="class-level"><?= e($class['level']) ?></span>
                            </div>
                        </div>
                        
                        <div class="class-details">
                            <div class="class-info">
                                <i class="fas fa-calendar text-sage"></i>
                                <span><?= formatDate($class['date']) ?></span>
                            </div>
                            <div class="class-info">
                                <i class="fas fa-clock text-sage"></i>
                                <span><?= $class['time_start'] ?> - <?= $class['time_end'] ?></span>
                            </div>
                            <div class="class-info">
                                <i class="fas fa-user text-sage"></i>
                                <span><?= e($class['lektor_name']) ?></span>
                            </div>
                            <div class="class-info">
                                <i class="fas fa-users text-sage"></i>
                                <span><?= $class['registered_count'] ?>/<?= $class['capacity'] ?> miest</span>
                            </div>
                        </div>
                        
                        <div class="class-footer">
                            <div class="class-pricing">
                                <span class="price-credit"><?= formatPrice($class['price_with_credit']) ?></span>
                                <small>s kreditom</small>
                                <span class="price-cash"><?= formatPrice($class['price']) ?> na mieste</span>
                            </div>
                            
                            <?php if ($currentUser): ?>
                                <a href="classes.php#class-<?= $class['id'] ?>" class="btn btn-sage btn-sm">Registrovať</a>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-sage btn-sm">Prihlásiť sa</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Featured Instructors -->
<?php if (!empty($featuredInstructors)): ?>
<section class="instructors-section py-5 bg-light">
    <div class="container">
        <div class="row">
            <div class="col-12 text-center mb-5">
                <h2 class="section-title">Naši lektori</h2>
                <p class="section-subtitle">Skúsení profesionáli s láskou k joge</p>
            </div>
        </div>
        
        <div class="row">
            <?php foreach ($featuredInstructors as $instructor): ?>
                <div class="col-md-4 mb-4">
                    <div class="instructor-card text-center">
                        <div class="instructor-photo">
                            <?php if ($instructor['photo_url']): ?>
                                <img src="<?= e($instructor['photo_url']) ?>" alt="<?= e($instructor['name']) ?>" class="rounded-circle">
                            <?php else: ?>
                                <div class="instructor-placeholder">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <h5 class="instructor-name"><?= e($instructor['name']) ?></h5>
                        
                        <?php if ($instructor['bio']): ?>
                            <p class="instructor-bio"><?= e(substr($instructor['bio'], 0, 100)) ?><?= strlen($instructor['bio']) > 100 ? '...' : '' ?></p>
                        <?php endif; ?>
                        
                        <div class="instructor-stats">
                            <div class="stat">
                                <div class="stat-number"><?= $instructor['total_classes'] ?></div>
                                <div class="stat-label">lekcií</div>
                            </div>
                            <div class="stat">
                                <div class="stat-number"><?= $instructor['upcoming_classes'] ?></div>
                                <div class="stat-label">nadchádzajúcich</div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="row">
            <div class="col-12 text-center">
                <a href="instructors.php" class="btn btn-outline-sage">Všetci lektori</a>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- About Section -->
<section class="about-section py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h2 class="section-title">O Láskavom Priestore</h2>
                <p class="lead">Naše štúdio je miestom, kde sa stretáva tradičná jogová múdrosť s moderným prístupom k wellness-u.</p>
                
                <div class="about-features">
                    <div class="feature">
                        <div class="feature-icon">
                            <i class="fas fa-heart text-sage"></i>
                        </div>
                        <div class="feature-content">
                            <h5>Láskavý prístup</h5>
                            <p>Každý je vítaný bez ohľadu na úroveň skúseností. Vytvárame bezpečný priestor pre rast.</p>
                        </div>
                    </div>
                    
                    <div class="feature">
                        <div class="feature-icon">
                            <i class="fas fa-leaf text-sage"></i>
                        </div>
                        <div class="feature-content">
                            <h5>Prirodzené prostredie</h5>
                            <p>Naše štúdio je navrhnuté s dôrazom na prirodzené materiály a pokojnú atmosféru.</p>
                        </div>
                    </div>
                    
                    <div class="feature">
                        <div class="feature-icon">
                            <i class="fas fa-users text-sage"></i>
                        </div>
                        <div class="feature-content">
                            <h5>Komunita</h5>
                            <p>Budujeme komunitu ľudí, ktorí sa navzájom podporujú na ceste k rovnováhe.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="about-image">
                    <svg viewBox="0 0 400 300" class="w-100">
                        <!-- Studio illustration -->
                        <defs>
                            <linearGradient id="studioGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#faf8f5;stop-opacity:1" />
                                <stop offset="100%" style="stop-color:#e8dcc0;stop-opacity:1" />
                            </linearGradient>
                        </defs>
                        
                        <!-- Studio room -->
                        <rect x="50" y="100" width="300" height="150" fill="url(#studioGradient)" stroke="#8db3a0" stroke-width="2" />
                        
                        <!-- Yoga mats -->
                        <rect x="80" y="180" width="60" height="20" rx="10" fill="#8db3a0" opacity="0.7" />
                        <rect x="160" y="180" width="60" height="20" rx="10" fill="#8db3a0" opacity="0.7" />
                        <rect x="240" y="180" width="60" height="20" rx="10" fill="#8db3a0" opacity="0.7" />
                        
                        <!-- Plants -->
                        <g transform="translate(70,120)">
                            <rect x="0" y="15" width="8" height="8" fill="#8db3a0" />
                            <circle cx="4" cy="10" r="8" fill="#a8c4a2" opacity="0.8" />
                        </g>
                        <g transform="translate(330,130)">
                            <rect x="0" y="10" width="6" height="6" fill="#8db3a0" />
                            <circle cx="3" cy="8" r="6" fill="#a8c4a2" opacity="0.8" />
                        </g>
                        
                        <!-- Window -->
                        <rect x="280" y="110" width="60" height="40" fill="#e8f0e5" stroke="#8db3a0" stroke-width="1" />
                        <line x1="310" y1="110" x2="310" y2="150" stroke="#8db3a0" stroke-width="1" />
                        <line x1="280" y1="130" x2="340" y2="130" stroke="#8db3a0" stroke-width="1" />
                    </svg>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>