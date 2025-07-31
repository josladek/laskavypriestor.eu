<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('klient');

$currentUser = getCurrentUser();
$userId = $currentUser['id'];

// Get user's attendance statistics
$attendedResult = db()->fetch("
    SELECT COUNT(*) as count FROM registrations 
    WHERE user_id = ? AND attendance_marked = 1 AND status = 'confirmed'
", [$userId]);
$totalAttended = $attendedResult ? $attendedResult['count'] : 0;

$registeredResult = db()->fetch("
    SELECT COUNT(*) as count FROM registrations 
    WHERE user_id = ? AND status = 'confirmed'
", [$userId]);
$totalRegistered = $registeredResult ? $registeredResult['count'] : 0;

$attendanceRate = $totalRegistered > 0 ? round(($totalAttended / $totalRegistered) * 100, 1) : 0;

// Get favorite class types
$favoriteTypes = db()->fetchAll("
    SELECT yc.type, COUNT(*) as count
    FROM registrations cr
    JOIN yoga_classes yc ON cr.class_id = yc.id
    WHERE cr.user_id = ? AND cr.status = 'confirmed'
    GROUP BY yc.type
    ORDER BY count DESC
    LIMIT 5
", [$userId]);

// Get monthly attendance for the last 6 months
$monthlyStats = db()->fetchAll("
    SELECT 
        DATE_FORMAT(yc.date, '%Y-%m') as month,
        COUNT(*) as attended
    FROM registrations cr
    JOIN yoga_classes yc ON cr.class_id = yc.id
    WHERE cr.user_id = ? AND cr.attendance_marked = 1 AND cr.status = 'confirmed'
        AND yc.date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(yc.date, '%Y-%m')
    ORDER BY month DESC
", [$userId]);

// Get current credit balance
$creditResult = db()->fetch("SELECT eur_balance FROM users WHERE id = ?", [$userId]);
$creditBalance = $creditResult ? $creditResult['eur_balance'] : 0;

// Get total money spent
$spentResult = db()->fetch("
    SELECT COALESCE(SUM(
        CASE 
            WHEN cr.paid_with_credit = 1 THEN yc.price_with_credit
            ELSE yc.price
        END
    ), 0) as total
    FROM registrations cr
    JOIN yoga_classes yc ON cr.class_id = yc.id
    WHERE cr.user_id = ? AND cr.status = 'confirmed'
", [$userId]);
$totalSpent = $spentResult ? $spentResult['total'] : 0;

// Get recent classes (last 10)
$recentClasses = db()->fetchAll("
    SELECT 
        yc.name,
        yc.date,
        yc.time_start,
        yc.type,
        cr.attendance_marked,
        cr.paid_with_credit,
        CASE 
            WHEN cr.paid_with_credit = 1 THEN yc.price_with_credit
            ELSE yc.price
        END as paid_amount
    FROM registrations cr
    JOIN yoga_classes yc ON cr.class_id = yc.id
    WHERE cr.user_id = ? AND cr.status = 'confirmed'
    ORDER BY yc.date DESC, yc.time_start DESC
    LIMIT 10
", [$userId]);

$pageTitle = 'Moje štatistiky';
$currentPage = 'my_statistics';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title">📊 Moje štatistiky</h1>
        <a href="profile.php" class="btn btn-secondary">
            <i class="fas fa-user me-2"></i>Späť na profil
        </a>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-gradient" style="background: linear-gradient(135deg, #e8f5e8, #d4e9d4);">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="fas fa-check-circle fa-2x" style="color: #4a7c59;"></i>
                    </div>
                    <h3 class="mb-1" style="color: #2d5016;"><?= $totalAttended ?></h3>
                    <p class="mb-0 small" style="color: #5a6c57;">Absolvované lekcie</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-gradient" style="background: linear-gradient(135deg, #fff2e8, #f7e6d3);">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="fas fa-calendar-check fa-2x" style="color: #b8860b;"></i>
                    </div>
                    <h3 class="mb-1" style="color: #8b4513;"><?= $attendanceRate ?>%</h3>
                    <p class="mb-0 small" style="color: #a0522d;">Miera dochádzky</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-gradient" style="background: linear-gradient(135deg, #e8f4fd, #d3e9f7);">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="fas fa-coins fa-2x" style="color: #2c5aa0;"></i>
                    </div>
                    <h3 class="mb-1" style="color: #1e3a8a;"><?= number_format($creditBalance, 2) ?>€</h3>
                    <p class="mb-0 small" style="color: #3b5998;">Zostatok kreditov</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-gradient" style="background: linear-gradient(135deg, #fce8f3, #f7d6e9);">
                <div class="card-body text-center">
                    <div class="d-flex align-items-center justify-content-center mb-2">
                        <i class="fas fa-euro-sign fa-2x" style="color: #a855f7;"></i>
                    </div>
                    <h3 class="mb-1" style="color: #7c2d92;"><?= number_format($totalSpent, 2) ?>€</h3>
                    <p class="mb-0 small" style="color: #8b5a95;">Celkovo minené</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Favorite Class Types -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-heart text-danger me-2"></i>Obľúbené typy lekcií</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($favoriteTypes)): ?>
                        <p class="text-muted text-center">Zatiaľ nemáte žiadne absolvované lekcie.</p>
                    <?php else: ?>
                        <?php foreach ($favoriteTypes as $type): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><?= htmlspecialchars($type['type']) ?></span>
                                <span class="badge bg-sage"><?= $type['count'] ?>x</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Monthly Attendance Chart -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-line text-primary me-2"></i>Mesačná dochádzka</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($monthlyStats)): ?>
                        <p class="text-muted text-center">Žiadne dáta za posledných 6 mesiacov.</p>
                    <?php else: ?>
                        <canvas id="monthlyChart" height="150"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Classes -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-history text-info me-2"></i>Posledné lekcie</h5>
        </div>
        <div class="card-body">
            <?php if (empty($recentClasses)): ?>
                <p class="text-muted text-center">Zatiaľ nemáte žiadne absolvované lekcie.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Lekcia</th>
                                <th>Dátum</th>
                                <th>Čas</th>
                                <th>Typ</th>
                                <th>Dochádzka</th>
                                <th>Platba</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentClasses as $class): ?>
                                <tr>
                                    <td><?= htmlspecialchars($class['name']) ?></td>
                                    <td><?= formatDate($class['date']) ?></td>
                                    <td><?= formatTime($class['time_start']) ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($class['type']) ?></span></td>
                                    <td>
                                        <?php if ($class['attendance_marked']): ?>
                                            <i class="fas fa-check text-success"></i> Prítomný
                                        <?php else: ?>
                                            <i class="fas fa-times text-muted"></i> Neprítomný
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $class['paid_with_credit'] ? 'bg-info' : 'bg-success' ?>">
                                            <?= number_format($class['paid_amount'], 2) ?>€
                                            <?= $class['paid_with_credit'] ? '(kredit)' : '' ?>
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

<?php if (!empty($monthlyStats)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('monthlyChart').getContext('2d');
    
    const monthlyData = <?= json_encode(array_reverse($monthlyStats)) ?>;
    const labels = monthlyData.map(item => {
        const [year, month] = item.month.split('-');
        const date = new Date(year, month - 1);
        return date.toLocaleDateString('sk-SK', { year: 'numeric', month: 'short' });
    });
    const data = monthlyData.map(item => item.attended);
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Absolvované lekcie',
                data: data,
                borderColor: '#4a7c59',
                backgroundColor: 'rgba(74, 124, 89, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>