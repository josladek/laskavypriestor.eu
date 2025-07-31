<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is admin or instructor
$user = getCurrentUser();
if (!$user || !in_array($user['role'], ['admin', 'lektor'])) {
    header('Location: ../pages/login.php');
    exit;
}

// Get attendance statistics
$stats = [];

// Overall attendance stats
$stats['total_marked'] = db()->fetch("SELECT COUNT(*) as count FROM attendance")['count'];
$stats['total_attended'] = db()->fetch("SELECT COUNT(*) as count FROM attendance WHERE attended = TRUE")['count'];
$stats['total_absent'] = db()->fetch("SELECT COUNT(*) as count FROM attendance WHERE attended = FALSE")['count'];
$stats['attendance_rate'] = $stats['total_marked'] > 0 ? round(($stats['total_attended'] / $stats['total_marked']) * 100, 1) : 0;

// Monthly attendance stats
$monthly_stats = db()->fetchAll("
    SELECT 
        DATE_FORMAT(COALESCE(yc.date, w.date), '%Y-%m') as month,
        COUNT(a.id) as total_marked,
        COUNT(CASE WHEN a.attended = TRUE THEN 1 END) as total_attended,
        ROUND((COUNT(CASE WHEN a.attended = TRUE THEN 1 END) / COUNT(a.id)) * 100, 1) as attendance_rate
    FROM attendance a
    LEFT JOIN yoga_classes yc ON a.class_id = yc.id
    LEFT JOIN workshops w ON a.workshop_id = w.id
    WHERE a.marked_at >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(COALESCE(yc.date, w.date), '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");

// Top attending clients
$top_clients = db()->fetchAll("
    SELECT 
        u.name,
        u.email,
        COUNT(a.id) as total_classes,
        COUNT(CASE WHEN a.attended = TRUE THEN 1 END) as attended_classes,
        ROUND((COUNT(CASE WHEN a.attended = TRUE THEN 1 END) / COUNT(a.id)) * 100, 1) as attendance_rate
    FROM users u
    JOIN attendance a ON u.id = a.user_id
    WHERE u.role = 'klient'
    GROUP BY u.id, u.name, u.email
    HAVING COUNT(a.id) >= 3
    ORDER BY attendance_rate DESC, attended_classes DESC
    LIMIT 10
");

// Instructor attendance stats (only for admin)
$instructor_stats = [];
if ($user['role'] === 'admin') {
    $instructor_stats = db()->fetchAll("
        SELECT 
            u.name as instructor_name,
            COUNT(DISTINCT COALESCE(yc.id, w.id)) as total_events,
            COUNT(a.id) as total_marked,
            COUNT(CASE WHEN a.attended = TRUE THEN 1 END) as total_attended,
            ROUND((COUNT(CASE WHEN a.attended = TRUE THEN 1 END) / COUNT(a.id)) * 100, 1) as attendance_rate
        FROM users u
        LEFT JOIN yoga_classes yc ON u.id = yc.instructor_id
        LEFT JOIN workshops w ON u.id = w.instructor_id
        LEFT JOIN attendance a ON (a.class_id = yc.id OR a.workshop_id = w.id)
        WHERE u.role IN ('lektor', 'instructor')
        GROUP BY u.id, u.name
        HAVING COUNT(a.id) > 0
        ORDER BY attendance_rate DESC
    ");
}

// Recent attendance activity
$recent_activity = db()->fetchAll("
    SELECT 
        u.name as client_name,
        COALESCE(yc.name, w.title) as event_name,
        CASE WHEN yc.id IS NOT NULL THEN 'Lekcia' ELSE 'Workshop' END as event_type,
        a.attended,
        a.marked_at,
        marker.name as marked_by
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN yoga_classes yc ON a.class_id = yc.id
    LEFT JOIN workshops w ON a.workshop_id = w.id
    LEFT JOIN users marker ON a.marked_by = marker.id
    ORDER BY a.marked_at DESC
    LIMIT 20
");

$pageTitle = 'Štatistiky dochádzky';
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container-fluid my-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-charcoal">Štatistiky dochádzky</h1>
                    <p class="text-muted">Prehľad a analýza dochádzky klientov</p>
                </div>
                <a href="attendance.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-1"></i>Späť na evidenciu
                </a>
            </div>
        </div>
    </div>

    <!-- Overall Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= $stats['total_marked'] ?></h4>
                            <p class="mb-0">Celkom označených</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clipboard-check fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= $stats['total_attended'] ?></h4>
                            <p class="mb-0">Prítomní</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= $stats['total_absent'] ?></h4>
                            <p class="mb-0">Neprítomní</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-times fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= $stats['attendance_rate'] ?>%</h4>
                            <p class="mb-0">Miera dochádzky</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-percentage fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Monthly Trends -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Mesačné trendy</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($monthly_stats)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Žiadne údaje za posledných 6 mesiacov</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Mesiac</th>
                                        <th>Označené</th>
                                        <th>Prítomní</th>
                                        <th>Miera</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($monthly_stats as $month): ?>
                                        <tr>
                                            <td><?= date('m/Y', strtotime($month['month'] . '-01')) ?></td>
                                            <td><?= $month['total_marked'] ?></td>
                                            <td><?= $month['total_attended'] ?></td>
                                            <td>
                                                <span class="badge bg-<?= $month['attendance_rate'] >= 80 ? 'success' : ($month['attendance_rate'] >= 60 ? 'warning' : 'danger') ?>">
                                                    <?= $month['attendance_rate'] ?>%
                                                </span>
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

        <!-- Top Clients -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Najlepší klienti (dochádzka)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($top_clients)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Žiadni klienti s označenou dochádzkou</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Klient</th>
                                        <th>Lekcie</th>
                                        <th>Miera</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_clients as $client): ?>
                                        <tr>
                                            <td>
                                                <strong><?= e($client['name']) ?></strong><br>
                                                <small class="text-muted"><?= e($client['email']) ?></small>
                                            </td>
                                            <td>
                                                <?= $client['attended_classes'] ?>/<?= $client['total_classes'] ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $client['attendance_rate'] >= 90 ? 'success' : ($client['attendance_rate'] >= 70 ? 'warning' : 'danger') ?>">
                                                    <?= $client['attendance_rate'] ?>%
                                                </span>
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
    </div>

    <?php if ($user['role'] === 'admin' && !empty($instructor_stats)): ?>
    <!-- Instructor Stats (Admin only) -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Štatistiky lektorov</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Lektor</th>
                                    <th>Udalosti</th>
                                    <th>Označené</th>
                                    <th>Prítomní</th>
                                    <th>Miera dochádzky</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($instructor_stats as $instructor): ?>
                                    <tr>
                                        <td><strong><?= e($instructor['instructor_name']) ?></strong></td>
                                        <td><?= $instructor['total_events'] ?></td>
                                        <td><?= $instructor['total_marked'] ?></td>
                                        <td><?= $instructor['total_attended'] ?></td>
                                        <td>
                                            <span class="badge bg-<?= $instructor['attendance_rate'] >= 80 ? 'success' : ($instructor['attendance_rate'] >= 60 ? 'warning' : 'danger') ?>">
                                                <?= $instructor['attendance_rate'] ?>%
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
    <?php endif; ?>

    <!-- Recent Activity -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Posledná aktivita</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_activity)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Žiadna nedávna aktivita</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Klient</th>
                                        <th>Udalosť</th>
                                        <th>Typ</th>
                                        <th>Stav</th>
                                        <th>Označené</th>
                                        <th>Označil</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_activity as $activity): ?>
                                        <tr>
                                            <td><?= e($activity['client_name']) ?></td>
                                            <td><?= e($activity['event_name']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $activity['event_type'] === 'Lekcia' ? 'primary' : 'warning' ?>">
                                                    <?= $activity['event_type'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($activity['attended']): ?>
                                                    <span class="badge bg-success"><i class="fas fa-check"></i> Prítomný</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger"><i class="fas fa-times"></i> Neprítomný</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?= date('d.m.Y H:i', strtotime($activity['marked_at'])) ?></small>
                                            </td>
                                            <td>
                                                <small><?= e($activity['marked_by']) ?></small>
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
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>