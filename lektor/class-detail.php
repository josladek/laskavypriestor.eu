<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('lektor');

$currentUser = getCurrentUser();
$instructorId = $currentUser['id'];

if (!isset($_GET['id'])) {
    header('Location: classes.php');
    exit;
}

$classId = (int)$_GET['id'];

// Get class details and verify ownership
$class = db()->fetch("
    SELECT yc.*, 
           COUNT(r.id) as registered_count,
           yc.capacity - COUNT(r.id) as available_spots
    FROM yoga_classes yc
    LEFT JOIN registrations r ON yc.id = r.class_id AND r.status IN ('confirmed', 'pending')
    WHERE yc.id = ? AND yc.instructor_id = ?
    GROUP BY yc.id
", [$classId, $instructorId]);

if (!$class) {
    header('Location: classes.php?error=' . urlencode('Lekcia nebola nájdená'));
    exit;
}

// Handle attendance marking and payment status FIRST - before fetching display data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance'])) {
    // Process attendance changes - get all registered users
    $all_registered = db()->fetchAll("SELECT user_id FROM registrations WHERE class_id = ? AND status IN ('confirmed', 'pending')", [$classId]);
    
    // Process attendance for all registered users
    foreach ($all_registered as $user_record) {
        $user_id = $user_record['user_id'];
        $notes = $_POST['notes'][$user_id] ?? '';
        
        // Checkbox checked = 1 (present), not checked = 0 (absent)
        $attended_bool = isset($_POST['attendance'][$user_id]) ? 1 : 0;
        
        // Check if attendance record exists
        $existing = db()->fetch("SELECT id FROM attendance WHERE user_id = ? AND class_id = ?", [$user_id, $classId]);
        
        if ($existing) {
            db()->query("UPDATE attendance SET attended = ?, notes = ?, marked_by = ?, marked_at = CURRENT_TIMESTAMP WHERE user_id = ? AND class_id = ?", 
                [$attended_bool, $notes, $currentUser['id'], $user_id, $classId]);
        } else {
            db()->query("INSERT INTO attendance (user_id, class_id, attended, notes, marked_by) VALUES (?, ?, ?, ?, ?)", 
                [$user_id, $classId, $attended_bool, $notes, $currentUser['id']]);
        }
        
        // Process payment status changes ONLY for non-credit payments
        $current_reg = db()->fetch("SELECT status, paid_with_credit FROM registrations WHERE user_id = ? AND class_id = ?", [$user_id, $classId]);
        
        // Only process payment changes if NOT paid with credit (credit payments are always confirmed and cannot be changed)
        if ($current_reg && !$current_reg['paid_with_credit']) {
            if (isset($_POST['payment_status'][$user_id])) {
                // Checkbox checked - confirm payment
                if ($current_reg['status'] === 'pending') {
                    db()->query("UPDATE registrations SET status = 'confirmed' WHERE user_id = ? AND class_id = ?", [$user_id, $classId]);
                }
            } else {
                // Checkbox not checked - set to pending only if currently confirmed
                if ($current_reg['status'] === 'confirmed') {
                    db()->query("UPDATE registrations SET status = 'pending' WHERE user_id = ? AND class_id = ?", [$user_id, $classId]);
                }
            }
        }
        // Credit payments (paid_with_credit = 1) are never changed - they stay confirmed
    }
    
    $message = 'Dochádzka a platobné statusy boli úspešne uložené.';
    header("Location: class-detail.php?id=$classId");
    exit;
}

// Get registered clients with attendance data AFTER POST processing
$klienti = db()->fetchAll("
    SELECT u.id as user_id, u.name, u.email, u.phone, r.registered_on, r.status, r.paid_with_credit,
           a.attended, a.notes, a.marked_at, marker.name as marked_by_name
    FROM registrations r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN attendance a ON u.id = a.user_id AND a.class_id = ?
    LEFT JOIN users marker ON a.marked_by = marker.id
    WHERE r.class_id = ? AND r.status IN ('confirmed', 'pending')
    ORDER BY u.name
", [$classId, $classId]);

// Keep backwards compatibility for some variables
$clients = $klienti;
$students = $klienti;

$pageTitle = 'Detail lekcie - ' . $class['name'];
$currentPage = 'lektor_class_detail';
require_once __DIR__ . '/../includes/header.php';
?>

    <div class="container mt-4">
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

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title"><?= htmlspecialchars($class['name']) ?></h1>
            <div>
                <a href="edit-class.php?id=<?= $class['id'] ?>" class="btn btn-outline-sage">
                    <i class="fas fa-edit me-2"></i>Upraviť
                </a>
                <a href="classes.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Späť na lekcie
                </a>
            </div>
        </div>

        <div class="row">
            <!-- Class Information -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle"></i> Informácie o lekcii</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Typ:</strong></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($class['type']) ?></span></td>
                            </tr>
                            <tr>
                                <td><strong>Úroveň:</strong></td>
                                <td><?= htmlspecialchars($class['level']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Dátum:</strong></td>
                                <td><?= formatDate($class['date']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Čas:</strong></td>
                                <td><?= formatTime($class['time_start']) ?> - <?= formatTime($class['time_end']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Miesto:</strong></td>
                                <td><?= htmlspecialchars($class['location']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Kapacita:</strong></td>
                                <td>
                                    <span class="badge bg-<?= $class['registered_count'] >= $class['capacity'] ? 'danger' : 'success' ?>">
                                        <?= $class['registered_count'] ?>/<?= $class['capacity'] ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td>
                                    <span class="badge bg-<?= $class['status'] === 'active' ? 'success' : ($class['status'] === 'cancelled' ? 'danger' : 'secondary') ?>">
                                        <?= ucfirst($class['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pricing & Statistics -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar"></i> Štatistiky a ceny</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center mb-3">
                            <div class="col-4">
                                <h4 class="text-primary"><?= $class['registered_count'] ?></h4>
                                <p class="small mb-0">Registrovaní</p>
                            </div>
                            <div class="col-4">
                                <h4 class="text-success"><?= $class['available_spots'] ?></h4>
                                <p class="small mb-0">Voľné miesta</p>
                            </div>
                            <div class="col-4">
                                <h4 class="text-info"><?= round(($class['registered_count'] / $class['capacity']) * 100) ?>%</h4>
                                <p class="small mb-0">Obsadenosť</p>
                            </div>
                        </div>

                        <hr>

                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Cena s kreditom:</strong></td>
                                <td><strong><?= formatPrice($class['price_with_credit']) ?></strong></td>
                            </tr>
                            <tr>
                                <td><strong>Cena na mieste:</strong></td>
                                <td><?= formatPrice($class['price']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Úspora s kreditom:</strong></td>
                                <td>
                                    <span class="text-success">
                                        <?= formatPrice($class['price'] - $class['price_with_credit']) ?>
                                        (<?= round((($class['price'] - $class['price_with_credit']) / $class['price']) * 100) ?>%)
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Description -->
        <?php if (!empty($class['description'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-align-left"></i> Popis lekcie</h5>
            </div>
            <div class="card-body">
                <p><?= nl2br(htmlspecialchars($class['description'])) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Notes -->
        <?php if (!empty($class['notes'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-sticky-note"></i> Poznámky</h5>
            </div>
            <div class="card-body">
                <p><?= nl2br(htmlspecialchars($class['notes'])) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Registered Students Overview -->
        <div class="card" id="studentsOverview">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-users"></i> Registrovaní klienti (<?= count($klienti) ?>)</h5>
                <?php if (!empty($klienti)): ?>
                <button type="button" class="btn btn-sm btn-primary" onclick="showAttendanceForm()">
                    <i class="fas fa-check"></i> Označiť dochádzku
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($klienti)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                    <h6>Žiadni registrovaní klienti</h6>
                    <p class="text-muted">Na túto lekciu sa ešte nikto nezaregistroval.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Klient</th>
                                <th>Kontakt</th>
                                <th>Registrovaný</th>
                                <th>Platba</th>
                                <th>Status</th>
                                <th>Akcie</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($klienti as $student): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($student['name']) ?></strong>
                                </td>
                                <td>
                                    <div>
                                        <i class="fas fa-envelope text-muted me-1"></i><?= htmlspecialchars($student['email']) ?>
                                    </div>
                                    <div>
                                        <i class="fas fa-phone text-muted me-1"></i><?= htmlspecialchars($student['phone']) ?>
                                    </div>
                                </td>
                                <td>
                                    <?= date('d.m.Y', strtotime($student['registered_on'])) ?>
                                    <br><small class="text-muted"><?= date('H:i', strtotime($student['registered_on'])) ?></small>
                                </td>
                                <td>
                                    <?php if ($student['paid_with_credit']): ?>
                                        <span class="badge bg-success">Kreditom</span>
                                        <br><small class="text-muted"><?= formatPrice($class['price_with_credit']) ?></small>
                                    <?php else: ?>
                                        <span class="badge bg-primary">Na mieste</span>
                                        <br><small class="text-muted"><?= formatPrice($class['price']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $student['status'] === 'confirmed' ? 'success' : ($student['status'] === 'cancelled' ? 'danger' : 'warning') ?>">
                                        <?= ucfirst($student['status']) ?>
                                    </span>
                                    <?php if ($student['attended'] !== null): ?>
                                        <br><small class="badge bg-<?= $student['attended'] ? 'success' : 'secondary' ?> mt-1">
                                            <?= $student['attended'] ? 'Prítomný' : 'Neprítomný' ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="mailto:<?= htmlspecialchars($student['email']) ?>" class="btn btn-sm btn-outline-primary" title="Napísať email">
                                        <i class="fas fa-envelope"></i>
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

        <!-- Attendance Marking Form (hidden by default, same structure as admin) -->
        <div class="card" id="attendanceForm" style="display: none;">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Označenie dochádzky</h5>
                <button type="button" class="btn btn-sm btn-secondary" onclick="showStudentsOverview()">
                    <i class="fas fa-arrow-left me-2"></i>Späť na prehľad
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($klienti)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Žiadne registrácie</h5>
                        <p class="text-muted">Na túto lekciu nie sú žiadni registrovaní klienti.</p>
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Klient</th>
                                        <th>Kontakt</th>
                                        <th>Status a platba</th>
                                        <th>Prítomný</th>
                                        <th>Poznámky</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($klienti as $registration): ?>
                                        <tr>
                                            <td>
                                                <strong><?= e($registration['name']) ?></strong>
                                            </td>
                                            <td>
                                                <small>
                                                    <?= e($registration['email']) ?><br>
                                                    <?= e($registration['phone']) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <!-- Status badge -->
                                                <?php if ($registration['status'] === 'pending'): ?>
                                                    <span class="badge bg-warning text-dark">Čaká na platbu</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Potvrdené</span>
                                                <?php endif; ?>
                                                
                                                <!-- Payment checkbox - same as admin but for lektor role -->
                                                <div class="mt-2">
                                                    <?php 
                                                    $is_credit_payment = ($registration['paid_with_credit'] == 1);
                                                    $is_confirmed = ($registration['status'] === 'confirmed');
                                                    $checkbox_disabled = $is_credit_payment; // Only credit payments are disabled
                                                    $checkbox_checked = $is_confirmed; // Checked if confirmed
                                                    ?>
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input" 
                                                               name="payment_status[<?= $registration['user_id'] ?>]" 
                                                               value="paid" 
                                                               id="paid_<?= $registration['user_id'] ?>"
                                                               <?= $checkbox_checked ? 'checked' : '' ?>
                                                               <?= $checkbox_disabled ? 'disabled' : '' ?>>
                                                        <label class="form-check-label small" for="paid_<?= $registration['user_id'] ?>">
                                                            <strong>Platba uhradená</strong>
                                                            <?php if ($is_credit_payment): ?>
                                                                <br><small class="text-muted">(kredit)</small>
                                                            <?php endif; ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="attendance[<?= $registration['user_id'] ?>]" 
                                                           id="present_<?= $registration['user_id'] ?>" 
                                                           value="1" 
                                                           <?= $registration['attended'] == '1' ? 'checked' : '' ?>>
                                                    <label class="form-check-label text-success" for="present_<?= $registration['user_id'] ?>">
                                                        <!--<i class="fas fa-check"></i> <strong>Prítomný</strong>-->
                                                    </label>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm" 
                                                       name="notes[<?= $registration['user_id'] ?>]" 
                                                       value="<?= e($registration['notes'] ?? '') ?>" 
                                                       placeholder="Poznámky...">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Uložiť dochádzku
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

<script>
function showAttendanceForm() {
    document.getElementById('studentsOverview').style.display = 'none';
    document.getElementById('attendanceForm').style.display = 'block';
}

function showStudentsOverview() {
    document.getElementById('attendanceForm').style.display = 'none';
    document.getElementById('studentsOverview').style.display = 'block';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>