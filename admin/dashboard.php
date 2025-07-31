<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Only admins can access this page
requireRole('admin');

// Get statistics
$stats = [
    'total_users' => db()->fetch("SELECT COUNT(*) as count FROM users")['count'],
    'total_clients' => db()->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'klient'")['count'],
    'total_instructors' => db()->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'lektor'")['count'],
    'total_classes' => db()->fetch("SELECT COUNT(*) as count FROM yoga_classes WHERE status = 'active'")['count'],
    'total_registrations' => db()->fetch("SELECT COUNT(*) as count FROM registrations WHERE status = 'confirmed'")['count'],
    'total_courses' => db()->fetch("SELECT COUNT(*) as count FROM courses WHERE status = 'active'")['count'],
    'total_revenue' => db()->fetch("SELECT SUM(amount) as total FROM credit_transactions WHERE transaction_type = 'purchase'")['total'] ?? 0
];

// Recent activities
$recent_registrations = db()->fetchAll("
    SELECT r.*, u.name as user_name, yc.name as class_name, yc.date, yc.time_start
    FROM registrations r
    JOIN users u ON r.user_id = u.id
    JOIN yoga_classes yc ON r.class_id = yc.id
    WHERE r.status = 'confirmed'
    ORDER BY r.registered_on DESC
    LIMIT 10
");

$recent_users = db()->fetchAll("
    SELECT * FROM users 
    ORDER BY created_at DESC 
    LIMIT 5
");

$upcoming_classes = db()->fetchAll("
    SELECT yc.*, u.name as lektor_name,
           (SELECT COUNT(*) FROM registrations r WHERE r.class_id = yc.id AND r.status = 'confirmed') as registered_count
    FROM yoga_classes yc
    LEFT JOIN users u ON yc.instructor_id = u.id
    WHERE yc.date >= CURDATE() AND yc.status = 'active'
    ORDER BY yc.date ASC, yc.time_start ASC
    LIMIT 5
");

$currentPage = 'admin_dashboard';
$pageTitle = 'Admin Dashboard';
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container-fluid my-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="text-charcoal">Administrácia</h1>
                <div class="btn-group">
                    <a href="clients.php" class="btn btn-outline-primary">Klienti</a>
                    <a href="classes.php" class="btn btn-outline-primary">Lekcie</a>
                    <a href="courses.php" class="btn btn-outline-primary">Kurzy</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-dark shadow-sm" style="background: linear-gradient(135deg, #e8f5e8 0%, #d4e9d4 100%); border: none;">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="fw-bold"><?= $stats['total_users'] ?></h4>
                            <p class="mb-0 text-muted">Celkovo používateľov</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-users fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-dark shadow-sm" style="background: linear-gradient(135deg, #f8e8f8 0%, #f0d4f0 100%); border: none;">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="fw-bold"><?= $stats['total_clients'] ?></h4>
                            <p class="mb-0 text-muted">Klienti</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-heart fa-2x" style="color: #c768c7;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-dark shadow-sm" style="background: linear-gradient(135deg, #e8f4ff 0%, #d4e8ff 100%); border: none;">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="fw-bold"><?= $stats['total_instructors'] ?></h4>
                            <p class="mb-0 text-muted">Lektori</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-chalkboard-teacher fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-dark shadow-sm" style="background: linear-gradient(135deg, #fff8e8 0%, #fff0d4 100%); border: none;">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="fw-bold"><?= $stats['total_classes'] ?></h4>
                            <p class="mb-0 text-muted">Aktívne lekcie</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-sun fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Second row of stats -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-dark shadow-sm" style="background: linear-gradient(135deg, #e8f8f8 0%, #d4f0f0 100%); border: none;">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="fw-bold"><?= $stats['total_registrations'] ?></h4>
                            <p class="mb-0 text-muted">Celkovo registrácií</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-calendar-check fa-2x" style="color: #20b2aa;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-dark shadow-sm" style="background: linear-gradient(135deg, #f0e8ff 0%, #e8d4ff 100%); border: none;">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="fw-bold"><?= $stats['total_courses'] ?></h4>
                            <p class="mb-0 text-muted">Aktívne kurzy</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-book fa-2x" style="color: #9370db;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-dark shadow-sm" style="background: linear-gradient(135deg, #f0f8e8 0%, #e0f0d4 100%); border: none;">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="fw-bold"><?= formatPrice($stats['total_revenue']) ?></h4>
                            <p class="mb-0 text-muted">Celkové tržby</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-euro-sign fa-2x" style="color: #82b366;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Registrations -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Posledné registrácie</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Používateľ</th>
                                    <th>Lekcia</th>
                                    <th>Dátum</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_registrations as $reg): ?>
                                <tr>
                                    <td><?= e($reg['user_name']) ?></td>
                                    <td>
                                        <small><?= e($reg['class_name']) ?></small>
                                        <br><small class="text-muted"><?= formatDate($reg['date']) ?> <?= formatTime($reg['time_start']) ?></small>
                                    </td>
                                    <td><small><?= formatDate($reg['registered_on']) ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Classes -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Nadchádzajúce lekcie</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Lekcia</th>
                                    <th>Lektor</th>
                                    <th>Registrovaní</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcoming_classes as $class): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($class['name']) ?></strong>
                                        <br><small class="text-muted"><?= formatDate($class['date']) ?> <?= formatTime($class['time_start']) ?></small>
                                    </td>
                                    <td><?= e($class['lektor_name']) ?></td>
                                    <td>
                                        <span class="badge <?= $class['registered_count'] >= $class['capacity'] ? 'bg-danger' : 'bg-success' ?>">
                                            <?= $class['registered_count'] ?>/<?= $class['capacity'] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Users -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Noví používatelia</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Meno</th>
                                    <th>Email</th>
                                    <th>Rola</th>
                                    <th>Registrovaný</th>
                                    <th>Akcie</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_users as $user): ?>
                                <tr>
                                    <td><?= e($user['name']) ?></td>
                                    <td><?= e($user['email']) ?></td>
                                    <td>
                                        <span class="badge <?= $user['role'] === 'admin' ? 'bg-danger' : ($user['role'] === 'lektor' ? 'bg-warning' : 'bg-info') ?>">
                                            <?= ucfirst($user['role']) ?>
                                        </span>
                                    </td>
                                    <td><?= formatDate($user['created_at']) ?></td>
                                    <td>
                                        <a href="clients.php" class="btn btn-sm btn-outline-primary">Detail</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>