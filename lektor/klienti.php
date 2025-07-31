<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('lektor');

$currentUser = getCurrentUser();
$instructorId = $currentUser['id'];

// Get instructor's clients with statistics
$klienti = db()->fetchAll("
    SELECT u.id, u.name, u.email, u.phone, u.created_at,
           COUNT(DISTINCT r.id) as total_classes,
           COUNT(DISTINCT CASE WHEN yc.date >= CURDATE() THEN r.id END) as upcoming_classes,
           MAX(yc.date) as last_class_date,
           SUM(CASE WHEN r.paid_with_credit THEN yc.price_with_credit ELSE yc.price END) as total_revenue
    FROM users u
    JOIN registrations r ON u.id = r.user_id AND r.status = 'confirmed'
    JOIN yoga_classes yc ON r.class_id = yc.id
    WHERE yc.instructor_id = ?
    GROUP BY u.id, u.name, u.email, u.phone, u.created_at
    ORDER BY total_classes DESC, u.name ASC
", [$instructorId]);

// Get class statistics for filter
$classStats = db()->fetchAll("
    SELECT yc.name, yc.id, COUNT(r.id) as client_count
    FROM yoga_classes yc
    LEFT JOIN registrations r ON yc.id = r.class_id AND r.status = 'confirmed'
    WHERE yc.instructor_id = ?
    GROUP BY yc.id, yc.name
    ORDER BY yc.date DESC
", [$instructorId]);

$pageTitle = 'Moji klienti';
$currentPage = 'lektor_klienti';
require_once __DIR__ . '/../includes/header.php';
?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title"><?= $pageTitle ?></h1>
            <div>
                <span class="badge bg-primary me-2">Celkom: <?= count($klienti) ?></span>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>

        <!-- Statistics Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <div class="stats-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="stats-number"><?= count($klienti) ?></h3>
                        <p class="stats-label">Celkovo klientov</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <div class="stats-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3 class="stats-number"><?= array_sum(array_column($klienti, 'total_classes')) ?></h3>
                        <p class="stats-label">Celkové registrácie</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <div class="stats-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="stats-number"><?= array_sum(array_column($klienti, 'upcoming_classes')) ?></h3>
                        <p class="stats-label">Nadchádzajúce</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <div class="stats-icon">
                            <i class="fas fa-euro-sign"></i>
                        </div>
                        <h3 class="stats-number"><?= formatPrice(array_sum(array_column($klienti, 'total_revenue'))) ?></h3>
                        <p class="stats-label">Celkový príjem</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Klienti Table -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-users"></i> Zoznam klientov</h5>
            </div>
            <div class="card-body">
                <?php if (empty($klienti)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5>Žiadni klienti</h5>
                    <p class="text-muted">Zatiaľ nemáte žiadnych klientov registrovaných na vaše lekcie.</p>
                    <a href="create-class.php" class="btn btn-primary">Vytvoriť lekciu</a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Klient</th>
                                <th>Kontakt</th>
                                <th>Štatistiky</th>
                                <th>Posledná lekcia</th>
                                <th>Celkový príjem</th>
                                <th>Registrovaný</th>
                                <th>Akcie</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($klienti as $klient): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($klient['name']) ?></strong>
                                        <br><small class="text-muted">ID: <?= $klient['id'] ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <div><i class="fas fa-envelope text-muted me-1"></i><?= htmlspecialchars($klient['email']) ?></div>
                                        <div><i class="fas fa-phone text-muted me-1"></i><?= htmlspecialchars($klient['phone']) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <span class="badge bg-primary"><?= $klient['total_classes'] ?> lekcií</span>
                                        <?php if ($klient['upcoming_classes'] > 0): ?>
                                        <br><span class="badge bg-success mt-1"><?= $klient['upcoming_classes'] ?> nadchádzajúcich</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($klient['last_class_date']): ?>
                                    <div>
                                        <?= formatDate($klient['last_class_date']) ?>
                                        <br><small class="text-muted">
                                            <?php
                                            $days = (strtotime($klient['last_class_date']) - strtotime('today')) / (60*60*24);
                                            if ($days < 0) {
                                                echo 'pred ' . abs($days) . ' dňami';
                                            } elseif ($days == 0) {
                                                echo 'dnes';
                                            } else {
                                                echo 'za ' . $days . ' dní';
                                            }
                                            ?>
                                        </small>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= formatPrice($klient['total_revenue']) ?></strong>
                                    <?php if ($klient['total_classes'] > 0): ?>
                                    <br><small class="text-muted">
                                        Ø <?= formatPrice($klient['total_revenue'] / $klient['total_classes']) ?>/lekcia
                                    </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= formatDate($klient['created_at']) ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" onclick="showClientDetail(<?= $klient['id'] ?>)" title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-success" onclick="showClientClasses(<?= $klient['id'] ?>)" title="Lekcie">
                                            <i class="fas fa-calendar"></i>
                                        </button>
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

        <!-- Class Statistics -->
        <?php if (!empty($classStats)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5><i class="fas fa-chart-bar"></i> Štatistiky podľa lekcií</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($classStats as $index => $classStat): ?>
                    <?php if ($index >= 6) break; // Show only first 6 ?>
                    <div class="col-md-4 mb-3">
                        <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                            <div>
                                <strong><?= htmlspecialchars($classStat['name']) ?></strong>
                                <br><small class="text-muted"><?= $classStat['client_count'] ?> klientov</small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-primary"><?= $classStat['client_count'] ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($classStats) > 6): ?>
                <div class="text-center">
                    <small class="text-muted">A ďalších <?= count($classStats) - 6 ?> lekcií...</small>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Client Detail Modal -->
    <div class="modal fade" id="clientDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail klienta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="clientDetailContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Načítava...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showClientDetail(clientId) {
            const modal = new bootstrap.Modal(document.getElementById('clientDetailModal'));
            const content = document.getElementById('clientDetailContent');
            
            // Show loading
            content.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Načítava...</span>
                    </div>
                </div>
            `;
            
            modal.show();
            
            // Load client details via AJAX
            fetch(`client-detail.php?id=${clientId}`)
                .then(response => response.text())
                .then(data => {
                    content.innerHTML = data;
                })
                .catch(error => {
                    content.innerHTML = '<div class="alert alert-danger">Chyba pri načítavaní údajov.</div>';
                });
        }

        function showClientClasses(clientId) {
            // Redirect to client classes view
            window.location.href = `client-classes.php?id=${clientId}`;
        }
        
        // Keep backwards compatibility
        function showStudentDetail(clientId) {
            showClientDetail(clientId);
        }
        
        function showStudentClasses(clientId) {
            showClientClasses(clientId);
        }
    </script>
</body>
</html>