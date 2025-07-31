<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/email_functions.php';

requireRole('admin');

// Initialize error variable
$error = null;

// Handle delete confirmation step
if (isset($_GET['confirm_delete'])) {
    $classId = (int)$_GET['id'];
    $deleteType = $_GET['type'] ?? 'single';
    
    // Get class info and check for registrations
    $classInfo = db()->fetch("SELECT * FROM yoga_classes WHERE id = ?", [$classId]);
    
    if (!$classInfo) {
        $error = "Lekcia nebola nájdená.";
    } else {
        $hasRegistrations = false;
        $registeredClients = [];
        $totalLessons = 1;
        
        if ($deleteType === 'recurring') {
            // Check for recurring series registrations
            if ($classInfo['recurring_series_id']) {
                $currentDate = date('Y-m-d');
                $registrations = getRecurringSeriesWithClients($classInfo['recurring_series_id'], $currentDate);
                
                if (!empty($registrations)) {
                    $hasRegistrations = true;
                    $totalLessons = count($registrations);
                    
                    // Group by unique clients
                    $uniqueClients = [];
                    foreach ($registrations as $reg) {
                        $clientKey = $reg['user_id'];
                        if (!isset($uniqueClients[$clientKey])) {
                            $uniqueClients[$clientKey] = [
                                'id' => $reg['user_id'],
                                'name' => $reg['user_name'],
                                'email' => $reg['user_email']
                            ];
                        }
                    }
                    $registeredClients = array_values($uniqueClients);
                }
            }
        } else {
            // Check single lesson registrations
            $registeredClients = getRegisteredClients($classId);
            $hasRegistrations = !empty($registeredClients);
            
            // Debug: Log what we found
            error_log("DEBUG: Class ID: $classId, Found clients: " . count($registeredClients));
            if (!empty($registeredClients)) {
                foreach ($registeredClients as $client) {
                    error_log("DEBUG: Client: " . $client['name'] . " (" . $client['email'] . ")");
                }
            }
        }
    }
}

// Handle class deletion with client notifications
if ($_GET && isset($_GET['delete']) && isset($_GET['id'])) {
    $classId = (int)$_GET['id'];
    $deleteType = $_GET['delete_type'] ?? 'single';
    $notifyClients = $_GET['notify'] ?? 'no';
    $cancellationReason = $_GET['reason'] ?? '';
    
    try {
        $notificationResults = ['sent' => 0, 'errors' => 0];
        
        if ($deleteType === 'recurring_all') {
            // Get the recurring series ID
            $class = db()->fetch("SELECT recurring_series_id, name FROM yoga_classes WHERE id = ?", [$classId]);
            
            if ($class && $class['recurring_series_id']) {
                $currentDate = date('Y-m-d');
                
                // Send notifications before deletion if requested
                if ($notifyClients === 'send') {
                    $notificationResults = sendRecurringSeriesCancellationNotifications(
                        $class['recurring_series_id'], 
                        $currentDate, 
                        $cancellationReason, 
                        true
                    );
                }
                
                // Get all future lessons in the recurring series
                $recurringClasses = db()->fetchAll("SELECT id FROM yoga_classes WHERE recurring_series_id = ? AND date >= ?", [$class['recurring_series_id'], $currentDate]);
                
                foreach ($recurringClasses as $recurringClass) {
                    // Process refunds for registrations
                    $registrations = db()->fetchAll("SELECT * FROM class_registrations WHERE class_id = ? AND status = 'confirmed'", [$recurringClass['id']]);
                    
                    foreach ($registrations as $registration) {
                        if ($registration['payment_method'] === 'credit') {
                            // Refund credit to user account
                            db()->query("UPDATE users SET credit_balance = credit_balance + ? WHERE id = ?", 
                                [$registration['amount'], $registration['user_id']]);
                        }
                        // Mark registration as cancelled
                        db()->query("UPDATE class_registrations SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?", 
                            [$registration['id']]);
                    }
                    
                    // Delete the class
                    db()->query("DELETE FROM yoga_classes WHERE id = ?", [$recurringClass['id']]);
                }
                
                $deletedCount = count($recurringClasses);
                $message = "Zmazané {$deletedCount} opakovaných lekcií.";
                
                if ($notifyClients === 'send' && $notificationResults['sent'] > 0) {
                    $message .= " Zaslaných {$notificationResults['sent']} notifikácií klientom.";
                    if ($notificationResults['errors'] > 0) {
                        $message .= " {$notificationResults['errors']} notifikácií sa nepodarilo odoslať.";
                    }
                }
            } else {
                throw new Exception("Lekcia nie je súčasťou opakovanej série.");
            }
        } else {
            // Single lesson deletion
            
            // Send notification to registered clients if requested
            if ($notifyClients === 'send') {
                $registeredClients = getRegisteredClients($classId);
                $classInfo = db()->fetch("SELECT name, date, time_start FROM yoga_classes WHERE id = ?", [$classId]);
                
                $sent = 0;
                $errors = 0;
                
                foreach ($registeredClients as $client) {
                    $classData = [
                        'name' => $classInfo['name'] ?? 'Neznáma lekcia',
                        'date' => $classInfo['date'] ?? date('Y-m-d'),
                        'time' => $classInfo['time_start'] ?? '00:00'
                    ];
                    
                    if (sendClassCancellationEmail($client, [$classData], $cancellationReason)) {
                        $sent++;
                    } else {
                        $errors++;
                    }
                }
                
                $notificationResults = ['sent' => $sent, 'errors' => $errors];
            }
            
            // Process refunds for registrations
            $registrations = db()->fetchAll("SELECT * FROM registrations WHERE class_id = ? AND status IN ('confirmed', 'pending')", [$classId]);
            
            foreach ($registrations as $registration) {
                // Get class price for refund
                $class = db()->fetch("SELECT price_with_credit FROM yoga_classes WHERE id = ?", [$classId]);
                $refundAmount = $class['price_with_credit'] ?? 0;
                
                // Refund credit to user account if they paid with credit
                if ($refundAmount > 0) {
                    db()->query("UPDATE users SET eur_balance = eur_balance + ? WHERE id = ?", 
                        [$refundAmount, $registration['user_id']]);
                    
                    // Add refund transaction
                    db()->query("INSERT INTO credit_transactions (user_id, amount, transaction_type, reference_id, description) VALUES (?, ?, 'class_refund', ?, ?)", 
                        [$registration['user_id'], $refundAmount, $registration['id'], "Vrátený kredit za zrušenie lekcie"]);
                }
                
                // Mark registration as cancelled
                db()->query("UPDATE registrations SET status = 'cancelled' WHERE id = ?", 
                    [$registration['id']]);
            }
            
            // Delete the class
            db()->query("DELETE FROM yoga_classes WHERE id = ?", [$classId]);
            $message = "Lekcia bola úspešne zmazaná.";
            
            if ($notifyClients === 'send' && $notificationResults['sent'] > 0) {
                $message .= " Zaslaných {$notificationResults['sent']} notifikácií klientom.";
                if ($notificationResults['errors'] > 0) {
                    $message .= " {$notificationResults['errors']} notifikácií sa nepodarilo odoslať.";
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("Class deletion error: " . $e->getMessage());
        $error = "Chyba pri mazaní lekcie: " . $e->getMessage();
    }
}

// Handle filters
$status_filter = $_GET['status'] ?? 'all';
$date_filter = $_GET['date'] ?? '';
$instructor_filter = $_GET['instructor'] ?? '';
$type_filter = $_GET['type'] ?? 'all';

// Build WHERE conditions
$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    if ($status_filter === 'upcoming') {
        $where_conditions[] = "(yc.date > CURDATE() OR (yc.date = CURDATE() AND CONCAT(yc.date, ' ', yc.time_end) > NOW()))";
    } elseif ($status_filter === 'past') {
        $where_conditions[] = "(yc.date < CURDATE() OR (yc.date = CURDATE() AND CONCAT(yc.date, ' ', yc.time_end) <= NOW()))";
    } elseif ($status_filter === 'today') {
        $where_conditions[] = "(yc.date = CURDATE() AND CONCAT(yc.date, ' ', yc.time_end) > NOW())";
    }
}

if (!empty($date_filter)) {
    $where_conditions[] = "yc.date = ?";
    $params[] = $date_filter;
}

if (!empty($instructor_filter)) {
    $where_conditions[] = "yc.instructor_id = ?";
    $params[] = $instructor_filter;
}

if ($type_filter !== 'all') {
    if ($type_filter === 'regular') {
        $where_conditions[] = "(yc.course_id IS NULL OR yc.course_id = '')";
    } elseif ($type_filter === 'course') {
        $where_conditions[] = "(yc.course_id IS NOT NULL AND yc.course_id != '')";
    } elseif ($type_filter === 'recurring') {
        $where_conditions[] = "(yc.recurring_series_id IS NOT NULL AND yc.recurring_series_id != '')";
    }
}

// Build final query
$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$classes = db()->fetchAll("
    SELECT yc.*, 
           u.name as lektor_name,
           COUNT(CASE WHEN r.status IN ('confirmed', 'pending') THEN r.id END) as registrations_count,
           COUNT(CASE WHEN r.status = 'confirmed' THEN r.id END) as confirmed_count,
           COUNT(CASE WHEN r.status = 'pending' THEN r.id END) as pending_count,
           c.name as course_name,
           CASE WHEN yc.recurring_series_id IS NOT NULL AND yc.recurring_series_id != '' THEN 1 ELSE 0 END as is_recurring
    FROM yoga_classes yc 
    LEFT JOIN users u ON yc.instructor_id = u.id 
    LEFT JOIN registrations r ON yc.id = r.class_id AND r.status IN ('confirmed', 'pending')
    LEFT JOIN courses c ON yc.course_id = c.id
    $where_clause
    GROUP BY yc.id
    ORDER BY yc.date ASC, yc.time_start ASC
", $params);

// Get all instructors for filter dropdown (admin can see all)
$instructors = db()->fetchAll("
    SELECT DISTINCT u.id, u.name 
    FROM users u 
    INNER JOIN yoga_classes yc ON u.id = yc.instructor_id 
    WHERE u.role = 'lektor'
    ORDER BY u.name
");

$currentPage = 'admin_classes';
$pageTitle = 'Správa lekcií';
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<?php if (isset($_GET['confirm_delete']) && !$error): ?>
<!-- Delete Confirmation Form -->
<div class="container-fluid my-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">⚠️ Potvrdenie zmazania lekcie</h5>
                </div>
                <div class="card-body">
                    <h6><strong><?= htmlspecialchars($classInfo['name']) ?></strong></h6>
                    <p class="text-muted">
                        📅 <?= date('d.m.Y', strtotime($classInfo['date'])) ?> o <?= date('H:i', strtotime($classInfo['time_start'])) ?>
                    </p>
                    
                    <?php if ($deleteType === 'recurring'): ?>
                        <div class="alert alert-info">
                            <strong>Opakovaná lekcia:</strong> Zmaže sa celá séria budúcich lekcií (<?= $totalLessons ?> lekcií).
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($hasRegistrations): ?>
                        <div class="alert alert-warning">
                            <h6><strong>Na <?= $deleteType === 'recurring' ? 'lekcie v sérii' : 'lekciu' ?> <?= $deleteType === 'recurring' ? 'sú prihlásení' : 'je prihlásený' ?> <?= count($registeredClients) ?> 
                            <?= count($registeredClients) === 1 ? 'klient' : (count($registeredClients) < 5 ? 'klienti' : 'klientov') ?>:</strong></h6>
                            
                            <ul class="mb-0">
                                <?php foreach ($registeredClients as $client): ?>
                                    <li><?= htmlspecialchars($client['name']) ?> (<?= htmlspecialchars($client['email']) ?>)</li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        
                        <form method="get" action="">
                            <input type="hidden" name="delete" value="1">
                            <input type="hidden" name="id" value="<?= $classId ?>">
                            <input type="hidden" name="delete_type" value="<?= $deleteType === 'recurring' ? 'recurring_all' : 'single' ?>">
                            
                            <div class="mb-3">
                                <label for="reason" class="form-label">Dôvod zrušenia lekcie:</label>
                                <textarea name="reason" id="reason" class="form-control" rows="3" 
                                          placeholder="Napr. choroba lektora, technické problémy, atď."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="notify" value="send" id="sendNotify" checked>
                                    <label class="form-check-label" for="sendNotify">
                                        <strong>Potvrdiť zrušenie a zaslať informáciu klientom</strong>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="notify" value="no" id="noNotify">
                                    <label class="form-check-label" for="noNotify">
                                        Potvrdiť zrušenie bez zaslania informácie klientom
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-danger">Potvrdiť zrušenie</button>
                                <a href="?" class="btn btn-secondary">Nepotvrdiť zrušenie</a>
                            </div>
                        </form>
                    <?php else: ?>
                        <!-- No registrations, simple confirmation -->
                        <div class="alert alert-info">
                            <strong>Na <?= $deleteType === 'recurring' ? 'lekcie v sérii' : 'lekciu' ?> nie <?= $deleteType === 'recurring' ? 'sú prihlásení žiadni klienti' : 'je prihlásený žiaden klient' ?>.</strong>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <a href="?delete=1&id=<?= $classId ?>&delete_type=<?= $deleteType === 'recurring' ? 'recurring_all' : 'single' ?>&notify=no" 
                               class="btn btn-danger">Potvrdiť zmazanie</a>
                            <a href="?" class="btn btn-secondary">Zrušiť</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>

<div class="container-fluid my-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="text-charcoal"><?= $pageTitle ?></h1>
                <div class="btn-group">
                    <span class="badge bg-primary me-2">Celkom: <?= count($classes) ?></span>
                    <a href="create-class.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> Nová lekcia
                    </a>
                    <a href="dashboard.php" class="btn btn-secondary">Späť na dashboard</a>
                </div>
            </div>

            <!-- Filter Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtrovanie</h5>
                </div>
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Všetky</option>
                                <option value="upcoming" <?= $status_filter === 'upcoming' ? 'selected' : '' ?>>Budúce</option>
                                <option value="today" <?= $status_filter === 'today' ? 'selected' : '' ?>>Dnes</option>
                                <option value="past" <?= $status_filter === 'past' ? 'selected' : '' ?>>Ukončené</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="type" class="form-label">Typ</label>
                            <select class="form-select" id="type" name="type">
                                <option value="all" <?= $type_filter === 'all' ? 'selected' : '' ?>>Všetky</option>
                                <option value="regular" <?= $type_filter === 'regular' ? 'selected' : '' ?>>Jednotlivé lekcie</option>
                                <option value="course" <?= $type_filter === 'course' ? 'selected' : '' ?>>Kurzy</option>
                                <option value="recurring" <?= $type_filter === 'recurring' ? 'selected' : '' ?>>Opakované lekcie</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="date" class="form-label">Dátum</label>
                            <input type="date" class="form-control" id="date" name="date" value="<?= htmlspecialchars($date_filter) ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="instructor" class="form-label">Lektor</label>
                            <select class="form-select" id="instructor" name="instructor">
                                <option value="">Všetci lektori</option>
                                <?php foreach ($instructors as $instructor): ?>
                                    <option value="<?= $instructor['id'] ?>" <?= $instructor_filter == $instructor['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($instructor['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Filtrovať
                                </button>
                                <a href="classes.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Vymazať filtre
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (isset($message)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

        <?php if (empty($classes)): ?>
        <div class="alert alert-info">
            <?php if ($status_filter !== 'all' || !empty($date_filter) || !empty($instructor_filter) || $type_filter !== 'all'): ?>
                <h5><i class="fas fa-search me-2"></i>Žiadne výsledky</h5>
                <p>Podľa zadaných filtrov neboli nájdené žiadne lekcie. Skúste zmeniť kritériá vyhľadávania.</p>
                <a href="classes.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-times me-1"></i>Vymazať všetky filtre
                </a>
            <?php else: ?>
                <h5>Žiadne lekcie</h5>
                <p>Zatiaľ neboli vytvorené žiadne lekcie. <a href="create-class.php">Vytvorte prvú lekciu</a>.</p>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Názov</th>
                        <th>Lektor</th>
                        <th>Druh/Úroveň</th>
                        <th>Dátum</th>
                        <th>Čas</th>
                        <th>Kapacita</th>
                        <th>Cena</th>
                        <th>Status</th>
                        <th>Akcie</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classes as $class): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($class['name']) ?></strong>
                            <?php if ($class['description']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars(substr($class['description'], 0, 60)) ?>...</small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($class['lektor_name'] ?? $class['lektor']) ?></td>
                        <td>
                            <span class="badge bg-secondary"><?= htmlspecialchars(getLessonTypeName($class['type_id']) ?: $class['type']) ?></span><br>
                            <small><?= htmlspecialchars(getLevelName($class['level_id']) ?: $class['level']) ?></small>
                        </td>
                        <td>
                            <div>
                                <strong><?= formatDate($class['date']) ?></strong><br>
                                <?php 
                                $status = getEventStatus($class['date'], $class['time_end']);
                                $status_badge = '';
                                $status_text = '';
                                switch ($status) {
                                    case 'finished':
                                        $status_badge = 'bg-secondary';
                                        $status_text = 'Ukončená';
                                        break;
                                    case 'today':
                                        $status_badge = 'bg-info';
                                        $status_text = 'Dnes';
                                        break;
                                    case 'upcoming':
                                        $status_badge = 'bg-success';
                                        $status_text = 'Budúca';
                                        break;
                                }
                                ?>
                                <small><span class="badge <?= $status_badge ?>"><?= $status_text ?></span></small>
                            </div>
                        </td>
                        <td>
                            <?= formatTime($class['time_start']) ?> - <?= formatTime($class['time_end']) ?>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <span class="me-2"><?= $class['registrations_count'] ?>/<?= $class['capacity'] ?></span>
                                <?php 
                                $fillPercentage = $class['capacity'] > 0 ? ($class['registrations_count'] / $class['capacity']) * 100 : 0;
                                ?>
                                <div class="progress" style="width: 60px; height: 8px;">
                                    <div class="progress-bar <?= $fillPercentage >= 100 ? 'bg-danger' : ($fillPercentage >= 80 ? 'bg-warning' : 'bg-success') ?>" 
                                         style="width: <?= min(100, $fillPercentage) ?>%"></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <strong><?= formatPrice($class['price']) ?></strong><br>
                            <small class="text-success"><?= formatPrice($class['price_with_credit']) ?></small>
                        </td>
                        <td>
                            <span class="badge bg-<?= $class['status'] === 'active' ? 'success' : ($class['status'] === 'cancelled' ? 'danger' : 'secondary') ?>">
                                <?= ucfirst($class['status']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="edit-class.php?id=<?= $class['id'] ?>" class="btn btn-outline-primary" title="Upraviť">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="class-detail.php?id=<?= $class['id'] ?>" class="btn btn-outline-info" title="Detail">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($class['is_recurring']): ?>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-outline-danger dropdown-toggle" 
                                            data-bs-toggle="dropdown" aria-expanded="false"
                                            title="Zmazať opakovanú lekciu">
                                        <i class="fas fa-trash"></i>
                                        <i class="fas fa-repeat" style="font-size: 8px; vertical-align: super;"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="?confirm_delete=1&id=<?= $class['id'] ?>&type=single">
                                            <i class="fas fa-calendar-day me-2"></i>Zmazať len túto lekciu
                                        </a></li>
                                        <li><a class="dropdown-item" href="?confirm_delete=1&id=<?= $class['id'] ?>&type=recurring">
                                            <i class="fas fa-calendar-week me-2"></i>Zmazať celú sériu
                                        </a></li>
                                    </ul>
                                </div>
                                <?php else: ?>
                                <a href="?confirm_delete=1&id=<?= $class['id'] ?>&type=single" 
                                   class="btn btn-outline-danger"
                                   title="Zmazať lekciu">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Štatistiky -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="text-success">
                            <?= count(array_filter($classes, fn($c) => strtotime($c['date']) >= time() && $c['status'] === 'active')) ?>
                        </h5>
                        <p class="card-text">Aktívne lekcie</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="text-primary">
                            <?= array_sum(array_column($classes, 'registrations_count')) ?>
                        </h5>
                        <p class="card-text">Celkové registrácie</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="text-warning">
                            <?= count(array_filter($classes, fn($c) => $c['registrations_count'] >= $c['capacity'] && $c['status'] === 'active')) ?>
                        </h5>
                        <p class="card-text">Plné lekcie</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="text-info">
                            <?= count(array_unique(array_column($classes, 'instructor_id'))) ?>
                        </h5>
                        <p class="card-text">Aktívni lektori</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
<?php endif; // End confirmation check ?>

    <script>
    // No complex JavaScript needed - using simple server-side approach
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>