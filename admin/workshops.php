<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Only admins can access this page
requireRole('admin');

// Handle workshop deletion
if (isset($_GET['delete']) && isset($_GET['id'])) {
    try {
        $workshopId = (int)$_GET['id'];
        
        // Get workshop details for confirmation
        $workshop = db()->fetch("SELECT title FROM workshops WHERE id = ?", [$workshopId]);
        
        if ($workshop) {
            // Delete workshop registrations first
            db()->query("DELETE FROM workshop_registrations WHERE workshop_id = ?", [$workshopId]);
            
            // Delete the workshop
            db()->query("DELETE FROM workshops WHERE id = ?", [$workshopId]);
            
            setFlashMessage('Workshop "' . $workshop['title'] . '" bol úspešne zmazaný.', 'success');
        }
    } catch (Exception $e) {
        setFlashMessage('Chyba pri mazaní workshopu: ' . $e->getMessage(), 'danger');
    }
    
    header('Location: workshops.php');
    exit;
}

// Get all workshops with instructor info and registration counts - prioritize custom_instructor_name
$workshops = db()->fetchAll("
    SELECT w.*, 
           COALESCE(NULLIF(w.custom_instructor_name, ''), u.name) as lektor_name,
           COUNT(wr.id) as registered_count,
           COUNT(CASE WHEN wr.status = 'waitlisted' THEN 1 END) as waitlist_count
    FROM workshops w
    LEFT JOIN users u ON w.instructor_id = u.id
    LEFT JOIN workshop_registrations wr ON w.id = wr.workshop_id AND wr.status IN ('confirmed', 'waitlisted')
    GROUP BY w.id
    ORDER BY w.date DESC
");

$currentPage = 'admin_workshops';
$pageTitle = 'Správa workshopov';
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container-fluid my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-charcoal">
            <i class="fas fa-chalkboard-teacher me-2"></i>Správa workshopov
        </h2>
        <a href="create-workshop.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Nový workshop
        </a>
    </div>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?= $_SESSION['flash_type'] ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php 
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        ?>
    <?php endif; ?>

    <?php if (!empty($workshops)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Zoznam workshopov</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Workshop</th>
                            <th>Lektor</th>
                            <th>Dátum</th>
                            <th>Čas</th>
                            <th>Trvanie</th>
                            <th>Kapacita</th>
                            <th>Cena</th>
                            <th>Kategória</th>
                            <th>Stav</th>
                            <th>Akcie</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($workshops as $workshop): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($workshop['title']) ?></strong>
                                <?php if ($workshop['description']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars(substr($workshop['description'], 0, 60)) ?>...</small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($workshop['lektor_name'] ?? 'Neurčený') ?></td>
                            <td>
                                <?= formatDate($workshop['date']) ?>
                                <?php if (strtotime($workshop['date']) < time()): ?>
                                <br><small class="text-muted">Minulý</small>
                                <?php elseif (strtotime($workshop['date']) == strtotime(date('Y-m-d'))): ?>
                                <br><small class="text-warning">Dnes</small>
                                <?php else: ?>
                                <br><small class="text-success">Nadchádzajúci</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= formatTime($workshop['time_start']) ?> - <?= formatTime($workshop['time_end']) ?>
                            </td>
                            <td><?= $workshop['duration_hours'] ?>h</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <span class="me-2"><?= $workshop['registered_count'] ?>/<?= $workshop['capacity'] ?></span>
                                    <?php 
                                    $fillPercentage = $workshop['capacity'] > 0 ? ($workshop['registered_count'] / $workshop['capacity']) * 100 : 0;
                                    ?>
                                    <div class="progress" style="width: 60px; height: 8px;">
                                        <div class="progress-bar <?= $fillPercentage >= 100 ? 'bg-danger' : ($fillPercentage >= 80 ? 'bg-warning' : 'bg-success') ?>" 
                                             style="width: <?= min(100, $fillPercentage) ?>%"></div>
                                    </div>
                                </div>
                                <?php if ($workshop['waitlist_count'] > 0): ?>
                                <br><small class="text-warning">Čakajú: <?= $workshop['waitlist_count'] ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= formatPrice($workshop['price']) ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?= htmlspecialchars($workshop['category']) ?></span>
                                <br><small class="text-muted"><?= htmlspecialchars($workshop['level']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-<?= $workshop['status'] === 'active' ? 'success' : ($workshop['status'] === 'cancelled' ? 'danger' : 'secondary') ?>">
                                    <?= $workshop['status'] === 'active' ? 'Naplánovaný' : ($workshop['status'] === 'cancelled' ? 'Zrušený' : 'Dokončený') ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="edit-workshop.php?id=<?= $workshop['id'] ?>" class="btn btn-outline-primary" title="Upraviť">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="workshop-detail.php?id=<?= $workshop['id'] ?>" class="btn btn-outline-info" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="?delete=1&id=<?= $workshop['id'] ?>" 
                                       class="btn btn-outline-danger"
                                       title="Zmazať"
                                       onclick="return confirm('Naozaj chcete zmazať tento workshop? Zrušia sa aj všetky registrácie!')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Štatistiky -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="text-success">
                            <?= count(array_filter($workshops, fn($w) => strtotime($w['date']) >= time() && $w['status'] === 'active')) ?>
                        </h5>
                        <p class="card-text">Nadchádzajúce workshopy</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="text-primary">
                            <?= array_sum(array_column($workshops, 'registered_count')) ?>
                        </h5>
                        <p class="card-text">Celkové registrácie</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="text-warning">
                            <?= count(array_filter($workshops, fn($w) => $w['registered_count'] >= $w['capacity'] && $w['status'] === 'active')) ?>
                        </h5>
                        <p class="card-text">Plné workshopy</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="text-info">
                            <?= array_sum(array_column($workshops, 'waitlist_count')) ?>
                        </h5>
                        <p class="card-text">Na čakacej listine</p>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-chalkboard-teacher fa-3x text-muted mb-3"></i>
            <h4 class="text-muted">Žiadne workshopy</h4>
            <p class="text-muted">Zatiaľ neboli vytvorené žiadne workshopy.</p>
            <a href="create-workshop.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Vytvoriť prvý workshop
            </a>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>