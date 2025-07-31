<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

if (!isset($_GET['id'])) {
    header('Location: clients.php');
    exit;
}

$userId = (int)$_GET['id'];

// Get user details
$user = db()->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
if (!$user) {
    header('Location: clients.php?error=' . urlencode('Klient nebol nájdený'));
    exit;
}

// Get user's registrations
$registrations = db()->fetchAll("
    SELECT r.*, yc.name as class_name, yc.date, yc.time_start, yc.time_end
    FROM registrations r 
    JOIN yoga_classes yc ON r.class_id = yc.id
    WHERE r.user_id = ?
    ORDER BY yc.date DESC, yc.time_start DESC
", [$userId]);

// Get credit transactions if user is client
$creditTransactions = [];
if ($user['role'] === 'klient' || $user['role'] === 'student') {
    $creditTransactions = db()->fetchAll("
        SELECT * FROM credit_transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ", [$userId]);
}

$pageTitle = 'Detail používateľa - ' . $user['name'];
?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Admin Panel</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../pages/logout.php">Odhlásiť</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><?= $pageTitle ?></h1>
            <div>
                <a href="edit-user.php?id=<?= $user['id'] ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Upraviť
                </a>
                <a href="clients.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Späť na používateľov
                </a>
            </div>
        </div>

        <div class="row">
            <!-- Základné informácie -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-user"></i> Základné informácie</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Meno:</strong></td>
                                <td><?= htmlspecialchars($user['name']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Telefón:</strong></td>
                                <td><?= htmlspecialchars($user['phone']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Rola:</strong></td>
                                <td>
                                    <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'lektor' ? 'warning' : 'primary') ?>">
                                        <?= $user['role'] === 'student' ? 'Klient' : ($user['role'] === 'lektor' ? 'Lektor' : 'Admin') ?>
                                    </span>
                                </td>
                            </tr>
                            <?php if ($user['role'] === 'student'): ?>
                            <tr>
                                <td><strong>Kredit:</strong></td>
                                <td><?= formatPrice($user['eur_balance']) ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td><strong>Registrovaný:</strong></td>
                                <td><?= formatDate($user['created_at']) ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Štatistiky -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar"></i> Štatistiky</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <h4 class="text-primary"><?= count($registrations) ?></h4>
                                <p>Celkové registrácie</p>
                            </div>
                            <div class="col-6 mb-3">
                                <h4 class="text-success"><?= count(array_filter($registrations, fn($r) => $r['status'] === 'confirmed')) ?></h4>
                                <p>Potvrdené registrácie</p>
                            </div>
                            <?php if ($user['role'] === 'student'): ?>
                            <div class="col-6 mb-3">
                                <h4 class="text-info"><?= count($creditTransactions) ?></h4>
                                <p>Credit transakcie</p>
                            </div>
                            <div class="col-6 mb-3">
                                <h4 class="text-warning">
                                    <?= count(array_filter($registrations, fn($r) => strtotime($r['date']) >= time())) ?>
                                </h4>
                                <p>Nadchádzajúce lekcie</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Registrácie -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-clipboard-list"></i> Registrácie na lekcie</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($registrations)): ?>
                        <p class="text-muted">Žiadne registrácie na lekcie.</p>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Lekcia</th>
                                        <th>Dátum</th>
                                        <th>Čas</th>
                                        <th>Status</th>
                                        <th>Registrované</th>
                                        <th>Platba</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($registrations as $reg): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($reg['class_name']) ?></td>
                                        <td><?= formatDate($reg['date']) ?></td>
                                        <td><?= formatTime($reg['time_start']) ?> - <?= formatTime($reg['time_end']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $reg['status'] === 'confirmed' ? 'success' : ($reg['status'] === 'cancelled' ? 'danger' : 'warning') ?>">
                                                <?= ucfirst($reg['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= formatDate($reg['registered_on']) ?></td>
                                        <td>
                                            <?php if ($reg['paid_with_credit']): ?>
                                                <span class="badge bg-success">Kreditom</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary">Na mieste</span>
                                            <?php endif; ?>
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

        <?php if ($user['role'] === 'student' && !empty($creditTransactions)): ?>
        <!-- Credit transakcie -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-coins"></i> História kreditov</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Dátum</th>
                                        <th>Typ</th>
                                        <th>Suma</th>
                                        <th>Reference</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($creditTransactions as $trans): ?>
                                    <tr>
                                        <td><?= formatDate($trans['created_at']) ?></td>
                                        <td>
                                            <?php 
                                            $typeLabels = [
                                                'manual_payment_approved' => 'Platba za kredity',
                                                'purchase' => 'Nákup kreditov', 
                                                'class_payment' => 'Platba za lekciu',
                                                'course_payment' => 'Platba za kurz',
                                                'refund' => 'Vrátenie kreditu',
                                                'admin_add' => 'Pridanie adminom',
                                                'admin_deduct' => 'Odpočítanie adminom'
                                            ];
                                            echo htmlspecialchars($typeLabels[$trans['transaction_type']] ?? $trans['transaction_type']);
                                            ?>
                                        </td>
                                        <td>
                                            <span class="<?= $trans['amount'] > 0 ? 'text-success' : 'text-danger' ?>">
                                                <?= $trans['amount'] > 0 ? '+' : '' ?><?= formatPrice($trans['amount']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($trans['reference_id'] ?? '-') ?></td>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>