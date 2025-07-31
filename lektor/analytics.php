<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('lektor');

$currentUser = getCurrentUser();
$instructorId = $currentUser['id'];

// Get monthly statistics (last 6 months)
$monthlyStats = db()->fetchAll("
    SELECT 
        DATE_FORMAT(yc.date, '%Y-%m') as month,
        COUNT(DISTINCT yc.id) as classes_count,
        COUNT(r.id) as registrations_count,
        SUM(CASE WHEN r.paid_with_credit THEN yc.price_with_credit ELSE yc.price END) as revenue
    FROM yoga_classes yc
    LEFT JOIN registrations r ON yc.id = r.class_id AND r.status = 'confirmed'
    WHERE yc.instructor_id = ? 
    AND yc.date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(yc.date, '%Y-%m')
    ORDER BY month DESC
", [$instructorId]);

// Get class type statistics
$typeStats = db()->fetchAll("
    SELECT 
        yc.type,
        COUNT(DISTINCT yc.id) as classes_count,
        COUNT(r.id) as registrations_count,
        AVG(COUNT(r.id)) OVER (PARTITION BY yc.type) as avg_attendance,
        SUM(CASE WHEN r.paid_with_credit THEN yc.price_with_credit ELSE yc.price END) as revenue
    FROM yoga_classes yc
    LEFT JOIN registrations r ON yc.id = r.class_id AND r.status = 'confirmed'
    WHERE yc.instructor_id = ?
    GROUP BY yc.type
    ORDER BY registrations_count DESC
", [$instructorId]);

// Get top performing classes
$topClasses = db()->fetchAll("
    SELECT 
        yc.name,
        yc.type,
        yc.date,
        COUNT(r.id) as registrations_count,
        yc.capacity,
        ROUND((COUNT(r.id) / yc.capacity) * 100, 1) as occupancy_rate,
        SUM(CASE WHEN r.paid_with_credit THEN yc.price_with_credit ELSE yc.price END) as revenue
    FROM yoga_classes yc
    LEFT JOIN registrations r ON yc.id = r.class_id AND r.status = 'confirmed'
    WHERE yc.instructor_id = ?
    GROUP BY yc.id, yc.name, yc.type, yc.date, yc.capacity
    ORDER BY registrations_count DESC, occupancy_rate DESC
    LIMIT 10
", [$instructorId]);

// Get time slot analysis
$timeSlotStats = db()->fetchAll("
    SELECT 
        CASE 
            WHEN TIME(yc.time_start) < '12:00:00' THEN 'Ráno (do 12:00)'
            WHEN TIME(yc.time_start) < '17:00:00' THEN 'Popoludnie (12:00-17:00)'
            ELSE 'Večer (od 17:00)'
        END as time_slot,
        COUNT(DISTINCT yc.id) as classes_count,
        COUNT(r.id) as registrations_count,
        AVG(COUNT(r.id)) OVER (PARTITION BY 
            CASE 
                WHEN TIME(yc.time_start) < '12:00:00' THEN 'Ráno'
                WHEN TIME(yc.time_start) < '17:00:00' THEN 'Popoludnie'
                ELSE 'Večer'
            END
        ) as avg_attendance
    FROM yoga_classes yc
    LEFT JOIN registrations r ON yc.id = r.class_id AND r.status = 'confirmed'
    WHERE yc.instructor_id = ?
    GROUP BY time_slot
    ORDER BY registrations_count DESC
", [$instructorId]);

// Get revenue summary
$revenueStats = db()->fetch("
    SELECT 
        COUNT(DISTINCT yc.id) as total_classes,
        COUNT(r.id) as total_registrations,
        SUM(CASE WHEN r.paid_with_credit THEN yc.price_with_credit ELSE yc.price END) as total_revenue,
        AVG(CASE WHEN r.paid_with_credit THEN yc.price_with_credit ELSE yc.price END) as avg_price_per_class,
        SUM(CASE WHEN r.paid_with_credit THEN yc.price_with_credit ELSE 0 END) as credit_revenue,
        SUM(CASE WHEN NOT r.paid_with_credit THEN yc.price ELSE 0 END) as cash_revenue
    FROM yoga_classes yc
    LEFT JOIN registrations r ON yc.id = r.class_id AND r.status = 'confirmed'
    WHERE yc.instructor_id = ?
", [$instructorId]);

$pageTitle = 'Analýzy a štatistiky';
$currentPage = 'lektor_analytics';
require_once __DIR__ . '/../includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title"><?= $pageTitle ?></h1>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Dashboard
            </a>
        </div>

        <!-- Revenue Summary -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <div class="stats-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3 class="stats-number"><?= $revenueStats['total_classes'] ?: 0 ?></h3>
                        <p class="stats-label">Celkové lekcie</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <div class="stats-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="stats-number"><?= $revenueStats['total_registrations'] ?: 0 ?></h3>
                        <p class="stats-label">Registrácie</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <div class="stats-icon">
                            <i class="fas fa-euro-sign"></i>
                        </div>
                        <h3 class="stats-number"><?= formatPrice($revenueStats['total_revenue'] ?: 0) ?></h3>
                        <p class="stats-label">Celkový príjem</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <div class="stats-icon">
                            <i class="fas fa-coins"></i>
                        </div>
                        <h3 class="stats-number"><?= formatPrice($revenueStats['credit_revenue'] ?: 0) ?></h3>
                        <p class="stats-label">Kreditový príjem</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <div class="stats-icon">
                            <i class="fas fa-money-bill"></i>
                        </div>
                        <h3 class="stats-number"><?= formatPrice($revenueStats['cash_revenue'] ?: 0) ?></h3>
                        <p class="stats-label">Hotovostný príjem</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <div class="stats-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3 class="stats-number"><?= formatPrice($revenueStats['avg_price_per_class'] ?: 0) ?></h3>
                        <p class="stats-label">Priemerná cena</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Monthly Revenue Chart -->
            <div class="col-md-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line"></i> Mesačné štatistiky (posledných 6 mesiacov)</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="monthlyChart" height="300"></canvas>
                    </div>
                </div>
            </div>

            <!-- Class Types Chart -->
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie"></i> Typy lekcií</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="typeChart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Top Performing Classes -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-trophy"></i> Najúspešnejšie lekcie</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($topClasses)): ?>
                        <p class="text-muted">Žiadne lekcie zatiaľ.</p>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Lekcia</th>
                                        <th>Typ</th>
                                        <th>Účasť</th>
                                        <th>Obsadenosť</th>
                                        <th>Príjem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topClasses as $index => $class): ?>
                                    <tr>
                                        <td>
                                            <?php if ($index < 3): ?>
                                            <i class="fas fa-medal text-warning me-1"></i>
                                            <?php endif; ?>
                                            <strong><?= htmlspecialchars($class['name']) ?></strong>
                                            <br><small class="text-muted"><?= formatDate($class['date']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($class['type']) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?= $class['registrations_count'] ?>/<?= $class['capacity'] ?></span>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-<?= $class['occupancy_rate'] >= 80 ? 'success' : ($class['occupancy_rate'] >= 50 ? 'warning' : 'danger') ?>" 
                                                     style="width: <?= $class['occupancy_rate'] ?>%">
                                                    <?= $class['occupancy_rate'] ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?= formatPrice($class['revenue']) ?></strong>
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

            <!-- Time Slot Analysis -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-clock"></i> Analýza časových pásiem</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($timeSlotStats)): ?>
                        <p class="text-muted">Žiadne údaje o časových pásmach.</p>
                        <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($timeSlotStats as $slot): ?>
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded mb-3">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($slot['time_slot']) ?></h6>
                                    <small class="text-muted"><?= $slot['classes_count'] ?> lekcií</small>
                                </div>
                                <div class="text-end">
                                    <div>
                                        <span class="badge bg-primary"><?= $slot['registrations_count'] ?> registrácií</span>
                                    </div>
                                    <small class="text-muted">
                                        Ø <?= round($slot['avg_attendance'], 1) ?> na lekciu
                                    </small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Class Type Statistics -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list-alt"></i> Štatistiky podľa typov jogy</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($typeStats)): ?>
                        <p class="text-muted">Žiadne údaje o typoch lekcií.</p>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Typ jogy</th>
                                        <th>Počet lekcií</th>
                                        <th>Celkové registrácie</th>
                                        <th>Priemerná účasť</th>
                                        <th>Celkový príjem</th>
                                        <th>Príjem na lekciu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($typeStats as $type): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($type['type']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= $type['classes_count'] ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?= $type['registrations_count'] ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?= $type['classes_count'] > 0 ? round($type['registrations_count'] / $type['classes_count'], 1) : 0 ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?= formatPrice($type['revenue']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="text-success">
                                                <?= $type['classes_count'] > 0 ? formatPrice($type['revenue'] / $type['classes_count']) : formatPrice(0) ?>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Monthly statistics chart
        const monthlyData = <?= json_encode(array_reverse($monthlyStats)) ?>;
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: monthlyData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('sk-SK', { year: 'numeric', month: 'long' });
                }),
                datasets: [{
                    label: 'Príjem (€)',
                    data: monthlyData.map(item => parseFloat(item.revenue || 0)),
                    backgroundColor: 'rgba(142, 179, 160, 0.6)',
                    borderColor: 'rgba(142, 179, 160, 1)',
                    borderWidth: 1,
                    yAxisID: 'y'
                }, {
                    label: 'Registrácie',
                    data: monthlyData.map(item => parseInt(item.registrations_count || 0)),
                    backgroundColor: 'rgba(232, 220, 192, 0.6)',
                    borderColor: 'rgba(232, 220, 192, 1)',
                    borderWidth: 1,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Príjem (€)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Počet registrácií'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });

        // Class types pie chart
        const typeData = <?= json_encode($typeStats) ?>;
        const typeCtx = document.getElementById('typeChart').getContext('2d');
        
        const colors = [
            'rgba(142, 179, 160, 0.8)',
            'rgba(232, 220, 192, 0.8)',
            'rgba(248, 246, 240, 0.8)',
            'rgba(74, 74, 74, 0.8)',
            'rgba(184, 134, 11, 0.8)',
            'rgba(220, 38, 127, 0.8)'
        ];

        new Chart(typeCtx, {
            type: 'doughnut',
            data: {
                labels: typeData.map(item => item.type),
                datasets: [{
                    data: typeData.map(item => parseInt(item.registrations_count || 0)),
                    backgroundColor: colors.slice(0, typeData.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>