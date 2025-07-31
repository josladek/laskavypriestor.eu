<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('lektor');

$currentUser = getCurrentUser();
$instructorId = $currentUser['id'];

if (!isset($_GET['id'])) {
    header('Location: klienti.php');
    exit;
}

$clientId = (int)$_GET['id'];

// Get client details
$client = db()->fetch("
    SELECT u.id, u.name, u.email, u.phone, u.created_at,
           COUNT(DISTINCT r.id) as total_classes,
           COUNT(DISTINCT CASE WHEN yc.date >= CURDATE() THEN r.id END) as upcoming_classes,
           MAX(yc.date) as last_class_date
    FROM users u
    LEFT JOIN registrations r ON u.id = r.user_id AND r.status = 'confirmed'
    LEFT JOIN yoga_classes yc ON r.class_id = yc.id AND yc.instructor_id = ?
    WHERE u.id = ?
    GROUP BY u.id, u.name, u.email, u.phone, u.created_at
", [$instructorId, $clientId]);

if (!$client) {
    header('Location: klienti.php?error=' . urlencode('Klient nebol nájdený'));
    exit;
}

// Get client's classes with this instructor
$classes = db()->fetchAll("
    SELECT yc.id, yc.name, yc.date, yc.time_start, yc.time_end, yc.type,
           r.registered_on, r.status, r.paid_with_credit,
           CASE WHEN yc.price_with_credit > 0 THEN yc.price_with_credit ELSE yc.price END as price,
           a.attended, a.notes as attendance_notes, a.marked_at,
           CASE 
               WHEN yc.date < CURDATE() OR (yc.date = CURDATE() AND yc.time_end < CURTIME()) THEN 'finished'
               WHEN yc.date = CURDATE() THEN 'today'
               ELSE 'upcoming'
           END as status_class
    FROM registrations r
    JOIN yoga_classes yc ON r.class_id = yc.id
    LEFT JOIN attendance a ON r.user_id = a.user_id AND r.class_id = a.class_id
    WHERE r.user_id = ? AND yc.instructor_id = ? AND r.status = 'confirmed'
    ORDER BY yc.date DESC, yc.time_start DESC
", [$clientId, $instructorId]);

$pageTitle = 'Lekcie klienta - ' . $client['name'];
$currentPage = 'lektor_klienti';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title"><?= $pageTitle ?></h1>
        <a href="klienti.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Späť na klientov
        </a>
    </div>

    <!-- Client Info -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-user"></i> Informácie o klientovi</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Meno:</strong> <?= htmlspecialchars($client['name']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($client['email']) ?></p>
                    <p><strong>Telefón:</strong> <?= htmlspecialchars($client['phone']) ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Registrovaný:</strong> <?= date('d.m.Y', strtotime($client['created_at'])) ?></p>
                    <p><strong>Celkové lekcie:</strong> <?= $client['total_classes'] ?></p>
                    <p><strong>Budúce lekcie:</strong> <?= $client['upcoming_classes'] ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Classes List -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-calendar-alt"></i> História lekcií (<?= count($classes) ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($classes)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <h6>Žiadne lekcie</h6>
                    <p class="text-muted">Tento klient sa ešte nezúčastnil žiadnej z vašich lekcií.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Lekcia</th>
                                <th>Dátum a čas</th>
                                <th>Status</th>
                                <th>Platba</th>
                                <th>Dochádzka</th>
                                <th>Poznámky</th>
                                <th>Akcie</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classes as $class): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($class['name']) ?></strong>
                                    <br><small class="text-muted"><?= htmlspecialchars($class['type']) ?></small>
                                </td>
                                <td>
                                    <?= date('d.m.Y', strtotime($class['date'])) ?>
                                    <br><small class="text-muted"><?= substr($class['time_start'], 0, 5) ?> - <?= substr($class['time_end'], 0, 5) ?></small>
                                </td>
                                <td>
                                    <?php if ($class['status_class'] === 'finished'): ?>
                                        <span class="badge bg-secondary">Ukončená</span>
                                    <?php elseif ($class['status_class'] === 'today'): ?>
                                        <span class="badge bg-primary">Dnes</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Budúca</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($class['paid_with_credit']): ?>
                                        <span class="badge bg-success">Kreditom</span>
                                    <?php elseif ($class['status'] === 'confirmed'): ?>
                                        <span class="badge bg-success">Zaplatené</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Čaká na platbu</span>
                                    <?php endif; ?>
                                    <br><small class="text-muted"><?= formatPrice($class['price']) ?></small>
                                </td>
                                <td>
                                    <?php if ($class['attended'] === '1'): ?>
                                        <span class="badge bg-success">Prítomný</span>
                                    <?php elseif ($class['attended'] === '0'): ?>
                                        <span class="badge bg-danger">Neprítomný</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Neoznačené</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($class['marked_at']): ?>
                                        <br><small class="text-muted">Označené: <?= date('d.m.Y H:i', strtotime($class['marked_at'])) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($class['attendance_notes'])): ?>
                                        <small><?= htmlspecialchars($class['attendance_notes']) ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">-</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="class-detail.php?id=<?= $class['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i> Detail
                                    </a>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>