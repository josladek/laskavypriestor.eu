<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' : '' ?>Láskavý Priestor</title>
    
    <!-- Google Fonts - Roboto -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,300;0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS (Local) -->
    <link rel="stylesheet" href="<?= url('assets/css/bootstrap.min.css') ?>">
    <!-- Font Awesome (Local with fonts) -->
    <link rel="stylesheet" href="<?= url('assets/css/fontawesome-local.min.css') ?>">
    
    <!-- Custom Roboto Font CSS -->
    <link rel="stylesheet" href="<?= url('assets/css/roboto-fonts.css') ?>">
    
    <!-- Láskavý Priestor Complete CSS Framework -->
    <link rel="stylesheet" href="<?= url('assets/css/laskavypriestor.css') ?>">



</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm">
        <div class="container">
            <?php if (isLoggedIn() && getCurrentUser()['role'] === 'admin'): ?>
                <a class="navbar-brand d-flex align-items-center" href="/admin/dashboard.php">
            <?php elseif (isLoggedIn() && getCurrentUser()['role'] === 'lektor'): ?>
                <a class="navbar-brand d-flex align-items-center" href="/lektor/index.php">
            <?php else: ?>
                <a class="navbar-brand d-flex align-items-center" href="/index.php">
            <?php endif; ?>
                <!-- Logo SVG -->
                <svg width="40" height="40" viewBox="0 0 100 100" class="me-2">
                    <defs>
                        <linearGradient id="logo-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:#a8b5a0;stop-opacity:1" />
                            <stop offset="100%" style="stop-color:#8db3a0;stop-opacity:1" />
                        </linearGradient>
                    </defs>
                    <circle cx="50" cy="50" r="45" fill="none" stroke="url(#logo-gradient)" stroke-width="3"/>
                    <path d="M30 40 Q50 20 70 40" fill="none" stroke="url(#logo-gradient)" stroke-width="2"/>
                    <path d="M30 60 Q50 80 70 60" fill="none" stroke="url(#logo-gradient)" stroke-width="2"/>
                    <circle cx="50" cy="50" r="6" fill="var(--sage)"/>
                </svg>
                <span class="fw-bold text-charcoal">Láskavý Priestor</span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <?php if (isLoggedIn() && getCurrentUser()['role'] === 'admin'): ?>
                            <a class="nav-link <?= (isset($currentPage) && $currentPage === 'admin_dashboard') ? 'active' : '' ?>" 
                               href="/admin/dashboard.php">Domov</a>
                        <?php elseif (isLoggedIn() && getCurrentUser()['role'] === 'lektor'): ?>
                            <a class="nav-link <?= (isset($currentPage) && $currentPage === 'lektor_dashboard') ? 'active' : '' ?>" 
                               href="/lektor/index.php">Domov</a>
                        <?php else: ?>
                            <a class="nav-link <?= (isset($currentPage) && $currentPage === 'home') ? 'active' : '' ?>" 
                               href="/index.php">Domov</a>
                        <?php endif; ?>
                    </li>
                    <!-- Universal menu for all users -->
                    <li class="nav-item">
                        <a class="nav-link <?= (isset($currentPage) && $currentPage === 'classes') ? 'active' : '' ?>" 
                           href="<?= url('pages/classes.php') ?>">Lekcie</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= (isset($currentPage) && $currentPage === 'courses') ? 'active' : '' ?>" 
                           href="<?= url('pages/courses.php') ?>">Kurzy</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= (isset($currentPage) && $currentPage === 'workshops') ? 'active' : '' ?>" 
                           href="<?= url('pages/workshops.php') ?>">Workshopy</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= (isset($currentPage) && $currentPage === 'instructors') ? 'active' : '' ?>" 
                           href="<?= url('pages/instructors.php') ?>">Lektori</a>
                    </li>
                </ul>

                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <?php $currentUser = getCurrentUser(); ?>
                        
                        <!-- EUR Balance (len pre klientov) -->
                        <?php if ($currentUser && $currentUser['role'] === 'klient'): ?>
                            <?php 
                            // Reload fresh user data to get current EUR balance
                            $freshUser = db()->fetch("SELECT eur_balance FROM users WHERE id = ?", [$currentUser['id']]);
                            $eurBalance = $freshUser ? (float)$freshUser['eur_balance'] : 0;
                            ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= url('pages/buy-credits-manual.php') ?>">
                                    <span class="badge bg-sage rounded-pill">
                                        <?= number_format($eurBalance, 2) ?>€ kredit
                                    </span>
                                </a>
                            </li>
                        <?php endif; ?>

                        <!-- User Menu -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <?= htmlspecialchars($currentUser ? $currentUser['name'] : 'Používateľ') ?>
                            </a>
                            <ul class="dropdown-menu">
                                <?php if ($currentUser && $currentUser['role'] === 'klient'): ?>
                                    <li><a class="dropdown-item" href="<?= url('pages/my-classes.php') ?>"><i class="fas fa-dumbbell me-2"></i>Moje lekcie</a></li>
                                    <li><a class="dropdown-item" href="<?= url('pages/my-courses.php') ?>"><i class="fas fa-graduation-cap me-2"></i>Moje kurzy</a></li>
                                    <li><a class="dropdown-item" href="<?= url('pages/my-workshops.php') ?>"><i class="fas fa-tools me-2"></i>Moje workshopy</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?= url('pages/online-calendar-outlook.php') ?>"><i class="fas fa-calendar-alt me-2"></i>Online kalendár</a></li>
                                    <li><a class="dropdown-item" href="<?= url('pages/my-statistics.php') ?>"><i class="fas fa-chart-bar me-2"></i>Moje štatistiky</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?= url('pages/buy-credits-manual.php') ?>"><i class="fas fa-credit-card me-2"></i>Dobiť kredit</a></li>
                                    <li><a class="dropdown-item" href="<?= url('pages/credit-history.php') ?>"><i class="fas fa-history me-2"></i>História kreditov</a></li>
                                <?php elseif ($currentUser && $currentUser['role'] === 'lektor'): ?>
                                    <li><a class="dropdown-item" href="/lektor/index.php"><i class="fas fa-tachometer-alt me-2"></i>Lektor dashboard</a></li>
                                    <li><a class="dropdown-item" href="/lektor/classes.php"><i class="fas fa-dumbbell me-2"></i>Moje lekcie</a></li>
                                    <li><a class="dropdown-item" href="/lektor/klienti.php"><i class="fas fa-users me-2"></i>Moji klienti</a></li>
                                    <li><a class="dropdown-item" href="/lektor/analytics.php"><i class="fas fa-chart-line me-2"></i>Analýzy</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?= url('pages/reports.php') ?>"><i class="fas fa-chart-bar me-2"></i>Reporty</a></li>
                                    <li><a class="dropdown-item" href="<?= url('pages/communication.php') ?>"><i class="fas fa-envelope me-2"></i>Komunikácia</a></li>
                                <?php elseif ($currentUser && $currentUser['role'] === 'admin'): ?>
                                    <li><a class="dropdown-item" href="<?= url('admin/dashboard.php') ?>"><i class="fas fa-tachometer-alt me-2"></i>Administrácia</a></li>
                                    <li><a class="dropdown-item" href="<?= url('admin/clients.php') ?>"><i class="fas fa-users me-2"></i>Používatelia</a></li>
                                    <li><a class="dropdown-item" href="<?= url('admin/lecturers.php') ?>"><i class="fas fa-user-tie me-2"></i>Lektori</a></li>
                                    <li><a class="dropdown-item" href="<?= url('admin/classes.php') ?>"><i class="fas fa-dumbbell me-2"></i>Lekcie</a></li>
                                    <li><a class="dropdown-item" href="<?= url('admin/courses.php') ?>"><i class="fas fa-graduation-cap me-2"></i>Kurzy</a></li>
                                    <li><a class="dropdown-item" href="<?= url('admin/workshops.php') ?>"><i class="fas fa-tools me-2"></i>Workshopy</a></li>
                                    <li><a class="dropdown-item" href="<?= url('admin/payment-requests.php') ?>"><i class="fas fa-credit-card me-2"></i>Platobné požiadavky</a></li>
                                    <li><a class="dropdown-item" href="<?= url('admin/attendance.php') ?>"><i class="fas fa-clipboard-check me-2"></i>Evidencia dochádzky</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?= url('pages/reports.php') ?>"><i class="fas fa-chart-bar me-2"></i>Reporty</a></li>
                                    <li><a class="dropdown-item" href="<?= url('pages/communication.php') ?>"><i class="fas fa-envelope me-2"></i>Komunikácia</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?= url('admin/settings.php') ?>"><i class="fas fa-cog me-2"></i>Nastavenia</a></li>
                                    <li><a class="dropdown-item" href="<?= url('admin/dictionaries.php') ?>"><i class="fas fa-list me-2"></i>Číselníky</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= url('pages/profile.php') ?>">Môj profil</a></li>
                                <li><a class="dropdown-item" href="<?= url('pages/logout.php') ?>">Odhlásiť sa</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= url('pages/login.php') ?>">Prihlásenie</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= url('pages/register.php') ?>">Registrácia</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="container mt-3">
            <div class="alert alert-<?= $_SESSION['flash_type'] ?? 'info' ?> alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['flash_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
        <?php 
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        ?>
    <?php endif; ?>