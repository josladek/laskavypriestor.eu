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

// Create attendance table if it doesn't exist
try {
    db()->query("CREATE TABLE IF NOT EXISTS attendance (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL,
        class_id INTEGER NULL,
        workshop_id INTEGER NULL,
        attended BOOLEAN NOT NULL DEFAULT FALSE,
        marked_by INTEGER NOT NULL,
        marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        notes TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES yoga_classes(id) ON DELETE CASCADE,
        FOREIGN KEY (workshop_id) REFERENCES workshops(id) ON DELETE CASCADE,
        FOREIGN KEY (marked_by) REFERENCES users(id),
        UNIQUE(user_id, class_id),
        UNIQUE(user_id, workshop_id)
    )");
} catch (Exception $e) {
    error_log("Attendance table creation error: " . $e->getMessage());
}

// Get filter parameters
$event_type = $_GET['type'] ?? 'all'; // all, class, workshop
$date_filter = $_GET['date'] ?? '';
$instructor_filter = $_GET['instructor'] ?? '';

// Build query conditions
$where_conditions = [];
$params = [];

// Role-based filtering
if ($user['role'] === 'lektor') {
    $where_conditions[] = "(yc.instructor_id = ? OR w.instructor_id = ?)";
    $params[] = $user['id'];
    $params[] = $user['id'];
}

if ($event_type === 'class') {
    $where_conditions[] = "yc.id IS NOT NULL";
} elseif ($event_type === 'workshop') {
    $where_conditions[] = "w.id IS NOT NULL";
}

if (!empty($date_filter)) {
    $where_conditions[] = "(yc.date = ? OR w.date = ?)";
    $params[] = $date_filter;
    $params[] = $date_filter;
}

if (!empty($instructor_filter) && $user['role'] === 'admin') {
    $where_conditions[] = "(yc.instructor_id = ? OR w.instructor_id = ?)";
    $params[] = $instructor_filter;
    $params[] = $instructor_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get events with registration counts
$sql = "SELECT 
            'class' as event_type,
            yc.id as event_id,
            yc.name as event_name,
            yc.date as event_date,
            yc.time_start,
            yc.time_end,
            yc.instructor_id,
            u.name as instructor_name,
            COUNT(r.id) as total_registrations,
            COUNT(CASE WHEN a.attended = TRUE THEN 1 END) as marked_attendance
        FROM yoga_classes yc
        LEFT JOIN users u ON yc.instructor_id = u.id
        LEFT JOIN registrations r ON yc.id = r.class_id AND r.status IN ('confirmed', 'pending')
        LEFT JOIN attendance a ON yc.id = a.class_id AND r.user_id = a.user_id
        $where_clause
        GROUP BY yc.id, yc.name, yc.date, yc.time_start, yc.time_end, yc.instructor_id, u.name
        
        UNION ALL
        
        SELECT 
            'workshop' as event_type,
            w.id as event_id,
            w.title as event_name,
            w.date as event_date,
            w.time_start,
            w.time_end,
            w.instructor_id,
            u.name as instructor_name,
            COUNT(wr.id) as total_registrations,
            COUNT(CASE WHEN a.attended = TRUE THEN 1 END) as marked_attendance
        FROM workshops w
        LEFT JOIN users u ON w.instructor_id = u.id
        LEFT JOIN workshop_registrations wr ON w.id = wr.workshop_id AND wr.status IN ('confirmed', 'pending')
        LEFT JOIN attendance a ON w.id = a.workshop_id AND wr.user_id = a.user_id
        $where_clause
        GROUP BY w.id, w.title, w.date, w.time_start, w.time_end, w.instructor_id, u.name
        
        ORDER BY event_date ASC, time_start ASC";

$events = db()->fetchAll($sql, array_merge($params, $params));

// Get instructors for admin filter
$instructors = [];
if ($user['role'] === 'admin') {
    $instructors = db()->fetchAll("SELECT id, name FROM users WHERE role IN ('lektor', 'instructor') ORDER BY name");
}

$pageTitle = 'Evidencia dochádzky';
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container-fluid my-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-charcoal">Evidencia dochádzky</h1>
                    <p class="text-muted">Označenie účasti klientov na lekciách a workshopoch</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="type" class="form-label">Typ udalosti</label>
                    <select class="form-select" id="type" name="type">
                        <option value="all" <?= $event_type === 'all' ? 'selected' : '' ?>>Všetky</option>
                        <option value="class" <?= $event_type === 'class' ? 'selected' : '' ?>>Lekcie</option>
                        <option value="workshop" <?= $event_type === 'workshop' ? 'selected' : '' ?>>Workshopy</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="date" class="form-label">Dátum</label>
                    <input type="date" class="form-control" id="date" name="date" value="<?= e($date_filter) ?>">
                </div>
                <?php if ($user['role'] === 'admin'): ?>
                <div class="col-md-3">
                    <label for="instructor" class="form-label">Lektor</label>
                    <select class="form-select" id="instructor" name="instructor">
                        <option value="">Všetci lektori</option>
                        <?php foreach ($instructors as $instructor): ?>
                            <option value="<?= $instructor['id'] ?>" <?= $instructor_filter == $instructor['id'] ? 'selected' : '' ?>>
                                <?= e($instructor['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Filtrovať</button>
                        <a href="attendance.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Events List -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Udalosti (<?= count($events) ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($events)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Žiadne udalosti</h5>
                    <p class="text-muted">Nie sú nájdené žiadne udalosti podľa zadaných kritérií.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Typ</th>
                                <th>Názov</th>
                                <th>Dátum a čas</th>
                                <th>Lektor</th>
                                <th>Registrácie</th>
                                <th>Dochádzka</th>
                                <th>Akcie</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $event): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-<?= $event['event_type'] === 'class' ? 'primary' : 'warning' ?>">
                                            <?= $event['event_type'] === 'class' ? 'Lekcia' : 'Workshop' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?= e($event['event_name']) ?></strong>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?= date('d.m.Y', strtotime($event['event_date'])) ?></strong><br>
                                            <small class="text-muted"><?= $event['time_start'] ?> - <?= $event['time_end'] ?></small>
                                            <?php 
                                            $status = getEventStatus($event['event_date'], $event['time_end']);
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
                                            <br><small><span class="badge <?= $status_badge ?>"><?= $status_text ?></span></small>
                                        </div>
                                    </td>
                                    <td><?= e($event['instructor_name']) ?></td>
                                    <td>
                                        <span class="badge bg-info"><?= $event['total_registrations'] ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $attendance_percentage = $event['total_registrations'] > 0 
                                            ? round(($event['marked_attendance'] / $event['total_registrations']) * 100) 
                                            : 0;
                                        $badge_class = $attendance_percentage >= 80 ? 'success' : ($attendance_percentage >= 50 ? 'warning' : 'danger');
                                        ?>
                                        <span class="badge bg-<?= $badge_class ?>">
                                            <?= $event['marked_attendance'] ?>/<?= $event['total_registrations'] ?> (<?= $attendance_percentage ?>%)
                                        </span>
                                    </td>
                                    <td>
                                        <?php $event_status = getEventStatus($event['event_date'], $event['time_end']); ?>
                                        <?php if ($event_status === 'finished'): ?>
                                            <a href="attendance-detail.php?type=<?= $event['event_type'] ?>&id=<?= $event['event_id'] ?>" 
                                               class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-eye me-1"></i>Zobraziť dochádzku
                                            </a>
                                        <?php else: ?>
                                            <a href="attendance-detail.php?type=<?= $event['event_type'] ?>&id=<?= $event['event_id'] ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-clipboard-check me-1"></i>Označiť dochádzku
                                            </a>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>