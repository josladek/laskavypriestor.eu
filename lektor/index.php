<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('lektor');

$currentUser = getCurrentUser();
$instructorId = $currentUser['id'];

// Get instructor's classes statistics
$totalClasses = db()->fetch("
    SELECT COUNT(*) as count
    FROM yoga_classes 
    WHERE instructor_id = ?
", [$instructorId])['count'];

$upcomingClasses = db()->fetch("
    SELECT COUNT(*) as count
    FROM yoga_classes 
    WHERE instructor_id = ? AND date >= CURDATE() AND status = 'active'
", [$instructorId])['count'];

$totalClients = db()->fetch("
    SELECT COUNT(DISTINCT r.user_id) as count
    FROM registrations r 
    JOIN yoga_classes yc ON r.class_id = yc.id 
    WHERE yc.instructor_id = ? AND r.status = 'confirmed'
", [$instructorId])['count'];

$thisMonthRevenue = db()->fetch("
    SELECT COALESCE(SUM(yc.price_with_credit), 0) as revenue
    FROM registrations r 
    JOIN yoga_classes yc ON r.class_id = yc.id 
    WHERE yc.instructor_id = ? 
    AND r.status = 'confirmed' 
    AND MONTH(r.registered_on) = MONTH(CURDATE()) 
    AND YEAR(r.registered_on) = YEAR(CURDATE())
", [$instructorId])['revenue'];

// Get recent classes
$recentClasses = db()->fetchAll("
    SELECT yc.*, 
           COUNT(r.id) as registered_count,
           yc.capacity - COUNT(r.id) as available_spots
    FROM yoga_classes yc
    LEFT JOIN registrations r ON yc.id = r.class_id AND r.status = 'confirmed'
    WHERE yc.instructor_id = ?
    GROUP BY yc.id
    ORDER BY yc.date DESC, yc.time_start DESC
    LIMIT 8
", [$instructorId]);

// Get recent registrations
$recentRegistrations = db()->fetchAll("
    SELECT r.*, u.name as client_name, u.email, yc.name as class_name, yc.date, yc.time_start
    FROM registrations r
    JOIN users u ON r.user_id = u.id
    JOIN yoga_classes yc ON r.class_id = yc.id
    WHERE yc.instructor_id = ? AND r.status = 'confirmed'
    ORDER BY r.registered_on DESC
    LIMIT 6
", [$instructorId]);

$pageTitle = 'Lektor Dashboard';
$currentPage = 'lektor_dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">Dashboard</h1>
            <div>
                <a href="create-class.php" class="btn btn-sage">
                    <i class="fas fa-plus me-2"></i>Nová lekcia
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <div class="stats-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3 class="stats-number"><?= $totalClasses ?></h3>
                        <p class="stats-label">Celkové lekcie</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <div class="stats-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="stats-number"><?= $upcomingClasses ?></h3>
                        <p class="stats-label">Nadchádzajúce</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <div class="stats-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="stats-number"><?= $totalClients ?></h3>
                        <p class="stats-label">Celkovo klientov</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <div class="stats-icon">
                            <i class="fas fa-euro-sign"></i>
                        </div>
                        <h3 class="stats-number"><?= formatPrice($thisMonthRevenue) ?></h3>
                        <p class="stats-label">Tento mesiac</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-bolt"></i> Rýchle akcie</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <a href="create-class.php" class="btn btn-outline-sage w-100">
                                    <i class="fas fa-plus me-2"></i>Nová lekcia
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="classes.php" class="btn btn-outline-sage w-100">
                                    <i class="fas fa-list me-2"></i>Moje lekcie
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="klienti.php" class="btn btn-outline-sage w-100">
                                    <i class="fas fa-users me-2"></i>Moji klienti
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="attendance.php" class="btn btn-outline-sage w-100">
                                    <i class="fas fa-chart-bar me-2"></i>Dochádzka
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Classes -->
            <div class="col-md-8 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-calendar"></i> Nedávne lekcie</h5>
                        <a href="classes.php" class="btn btn-sm btn-outline-sage">Zobraziť všetky</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentClasses)): ?>
                        <p class="text-muted">Žiadne lekcie zatiaľ.</p>
                        <a href="create-class.php" class="btn btn-sage">Vytvoriť prvú lekciu</a>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Lekcia</th>
                                        <th>Dátum</th>
                                        <th>Čas</th>
                                        <th>Obsadenosť</th>
                                        <th>Status</th>
                                        <th>Akcie</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentClasses as $class): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($class['name']) ?></strong>
                                            <br><small class="text-muted"><?= htmlspecialchars($class['type']) ?></small>
                                        </td>
                                        <td><?= formatDate($class['date']) ?></td>
                                        <td><?= formatTime($class['time_start']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $class['registered_count'] >= $class['capacity'] ? 'danger' : 'success' ?>">
                                                <?= $class['registered_count'] ?>/<?= $class['capacity'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $class['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                <?= ucfirst($class['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="class-detail.php?id=<?= $class['id'] ?>" class="btn btn-outline-sage btn-sm" title="Detail">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit-class.php?id=<?= $class['id'] ?>" class="btn btn-outline-secondary btn-sm" title="Editovať">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Registrations -->
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-user-plus"></i> Nedávne registrácie</h5>
                        <a href="klienti.php" class="btn btn-sm btn-outline-sage">Všetci</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentRegistrations)): ?>
                        <p class="text-muted">Žiadne registrácie zatiaľ.</p>
                        <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentRegistrations as $reg): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?= htmlspecialchars($reg['client_name']) ?></h6>
                                        <p class="mb-1 small text-muted"><?= htmlspecialchars($reg['class_name']) ?></p>
                                        <small class="text-muted"><?= formatDate($reg['date']) ?> o <?= formatTime($reg['time_start']) ?></small>
                                    </div>
                                    <small class="text-muted"><?= formatDate($reg['registered_on']) ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>