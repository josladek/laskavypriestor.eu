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

$event_type = $_GET['type'] ?? '';
$event_id = (int)($_GET['id'] ?? 0);

if (!in_array($event_type, ['class', 'workshop']) || $event_id <= 0) {
    header('Location: attendance.php');
    exit;
}



// Handle combined attendance and payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['attendance']) || isset($_POST['payment_status']))) {
    error_log("POST request received. Attendance data: " . json_encode($_POST['attendance'] ?? []) . ", Payment data: " . json_encode($_POST['payment_status'] ?? []));
    // Process attendance changes - get all registered users
    $all_registered = [];
    if ($event_type === 'class') {
        $all_registered = db()->fetchAll("SELECT user_id FROM registrations WHERE class_id = ? AND status IN ('confirmed', 'pending')", [$event_id]);
    } else {
        $all_registered = db()->fetchAll("SELECT user_id FROM workshop_registrations WHERE workshop_id = ? AND status IN ('confirmed', 'pending')", [$event_id]);
    }
    
    // Process attendance for all registered users
    foreach ($all_registered as $user_record) {
        $user_id = $user_record['user_id'];
        $notes = $_POST['notes'][$user_id] ?? '';
        
        // Checkbox checked = 1 (present), not checked = 0 (absent)
        $attended_bool = isset($_POST['attendance'][$user_id]) ? 1 : 0;
        error_log("Processing attendance for user $user_id: attended = $attended_bool");
        
        if ($event_type === 'class') {
                // Check if attendance record exists
                $existing = db()->fetch("SELECT id FROM attendance WHERE user_id = ? AND class_id = ?", [$user_id, $event_id]);
                
                if ($existing) {
                    error_log("Updating attendance for user $user_id, class $event_id: attended = $attended_bool");
                    db()->query("UPDATE attendance SET attended = ?, notes = ?, marked_by = ?, marked_at = CURRENT_TIMESTAMP WHERE user_id = ? AND class_id = ?", 
                        [$attended_bool, $notes, $user['id'], $user_id, $event_id]);
                } else {
                    error_log("Inserting new attendance for user $user_id, class $event_id: attended = $attended_bool");
                    db()->query("INSERT INTO attendance (user_id, class_id, attended, notes, marked_by) VALUES (?, ?, ?, ?, ?)", 
                        [$user_id, $event_id, $attended_bool, $notes, $user['id']]);
                }
            } else {
                // Check if attendance record exists for workshop
                $existing = db()->fetch("SELECT id FROM attendance WHERE user_id = ? AND workshop_id = ?", [$user_id, $event_id]);
                
                if ($existing) {
                    db()->query("UPDATE attendance SET attended = ?, notes = ?, marked_by = ?, marked_at = CURRENT_TIMESTAMP WHERE user_id = ? AND workshop_id = ?", 
                        [$attended_bool, $notes, $user['id'], $user_id, $event_id]);
                } else {
                    db()->query("INSERT INTO attendance (user_id, workshop_id, attended, notes, marked_by) VALUES (?, ?, ?, ?, ?)", 
                        [$user_id, $event_id, $attended_bool, $notes, $user['id']]);
                }
            }
    }
    
    // Process payment status changes
    if (isset($_POST['payment_status'])) {
        foreach ($_POST['payment_status'] as $user_id => $status) {
            if ($status === 'paid') {
                // Update registration to confirmed and mark as paid
                if ($event_type === 'class') {
                    db()->query("UPDATE registrations SET status = 'confirmed' WHERE user_id = ? AND class_id = ? AND status = 'pending'", 
                        [$user_id, $event_id]);
                } else {
                    db()->query("UPDATE workshop_registrations SET status = 'confirmed' WHERE user_id = ? AND workshop_id = ? AND status = 'pending'", 
                        [$user_id, $event_id]);
                }
            }
        }
    }
    
    setFlashMessage('Dochádzka a platby boli úspešne uložené.', 'success');
    header("Location: attendance-detail.php?type=$event_type&id=$event_id");
    exit;
}

// Get event details and registrations
if ($event_type === 'class') {
    $event = db()->fetch("SELECT yc.*, u.name as instructor_name FROM yoga_classes yc 
                          LEFT JOIN users u ON yc.instructor_id = u.id 
                          WHERE yc.id = ?", [$event_id]);
    
    if (!$event) {
        header('Location: attendance.php');
        exit;
    }
    
    // Check if user can access this class
    if ($user['role'] === 'lektor' && $event['instructor_id'] != $user['id']) {
        header('Location: attendance.php');
        exit;
    }
    
    // Get registrations with attendance data (including pending)
    $registrations = db()->fetchAll("
        SELECT u.id as user_id, u.name, u.email, u.phone, r.registered_on, r.status, r.paid_with_credit,
               a.attended, a.notes, a.marked_at, marker.name as marked_by_name
        FROM registrations r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN attendance a ON u.id = a.user_id AND a.class_id = ?
        LEFT JOIN users marker ON a.marked_by = marker.id
        WHERE r.class_id = ? AND r.status IN ('confirmed', 'pending')
        ORDER BY u.name", [$event_id, $event_id]);
        
    // Debug: Log query results
    error_log("Class ID: $event_id, Found registrations: " . count($registrations));
    foreach ($registrations as $reg) {
        error_log("User {$reg['user_id']}: attended = " . var_export($reg['attended'], true) . " (type: " . gettype($reg['attended']) . ")");
    }
        
} else {
    $event = db()->fetch("SELECT w.*, u.name as instructor_name FROM workshops w 
                          LEFT JOIN users u ON w.instructor_id = u.id 
                          WHERE w.id = ?", [$event_id]);
    
    if (!$event) {
        header('Location: attendance.php');
        exit;
    }
    
    // Check if user can access this workshop
    if ($user['role'] === 'lektor' && $event['instructor_id'] != $user['id']) {
        header('Location: attendance.php');
        exit;
    }
    
    // Get registrations with attendance data (including pending)
    $registrations = db()->fetchAll("
        SELECT u.id as user_id, u.name, u.email, u.phone, wr.registered_on, wr.status, wr.paid_with_credit,
               a.attended, a.notes, a.marked_at, marker.name as marked_by_name
        FROM workshop_registrations wr
        JOIN users u ON wr.user_id = u.id
        LEFT JOIN attendance a ON u.id = a.user_id AND a.workshop_id = ?
        LEFT JOIN users marker ON a.marked_by = marker.id
        WHERE wr.workshop_id = ? AND wr.status IN ('confirmed', 'pending')
        ORDER BY u.name", [$event_id, $event_id]);
        
    // Debug: Log query results  
    error_log("Workshop ID: $event_id, Found registrations: " . count($registrations));
}

$pageTitle = 'Dochádzka - ' . ($event['name'] ?? $event['title']);
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container-fluid my-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-charcoal">Dochádzka</h1>
                    <p class="text-muted">
                        <?= $event_type === 'class' ? 'Lekcia' : 'Workshop' ?>: 
                        <strong><?= e($event['name'] ?? $event['title']) ?></strong>
                    </p>
                </div>
                <a href="attendance.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Späť na prehľad
                </a>
            </div>
        </div>
    </div>

    <!-- Event Details -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>Dátum:</strong><br>
                    <?= date('d.m.Y', strtotime($event['date'])) ?>
                </div>
                <div class="col-md-3">
                    <strong>Čas:</strong><br>
                    <?= $event['time_start'] ?> - <?= $event['time_end'] ?>
                </div>
                <div class="col-md-3">
                    <strong>Lektor:</strong><br>
                    <?= e($event['instructor_name']) ?>
                </div>
                <div class="col-md-3">
                    <strong>Registrovaní:</strong><br>
                    <span class="badge bg-info fs-6"><?= count($registrations) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php $flash = getFlashMessage(); if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Attendance Form -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Označenie dochádzky</h5>
<!--            <div>
                <button type="button" class="btn btn-sm btn-success" onclick="markAllAttended()">Označiť všetkých prítomných</button>
                <button type="button" class="btn btn-sm btn-warning" onclick="clearAllAttendance()">Zrušiť všetky označenia</button>
            </div> -->
        </div>
        <div class="card-body">
            <?php if (empty($registrations)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Žiadne registrácie</h5>
                    <p class="text-muted">Na túto udalosť nie sú žiadni registrovaní klienti.</p>
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
                                <?php foreach ($registrations as $registration): ?>
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
                                            <!-- <div class="mb-1"> -->
                                            <!-- <small><?= date('d.m.Y H:i', strtotime($registration['registered_on'])) ?></small> -->
                                            <!-- </div> -->
                                            <!-- Status badge -->
                                            
                                            <?php if ($registration['status'] === 'pending'): ?>
                                                <span class="badge bg-warning text-dark">Čaká na platbu</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Potvrdené</span>
                                            <?php endif; ?>
                                            
                                            <!-- Payment checkbox -->
                                            <?php if ($user['role'] === 'admin'): ?>
                                                <div class="mt-2">
                                                    <?php 
                                                    $is_credit_payment = ($registration['paid_with_credit'] == 1);
                                                    $is_confirmed = ($registration['status'] === 'confirmed');
                                                    $checkbox_disabled = $is_credit_payment || $is_confirmed;
                                                    $checkbox_checked = $is_confirmed;
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
                                            <?php endif; ?>
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

<!--<script>
function markAllAttended() {
    document.querySelectorAll('input[type="checkbox"][name^="attendance"]').forEach(checkbox => {
        checkbox.checked = true;
    });
}

function clearAllAttendance() {
    document.querySelectorAll('input[type="checkbox"][name^="attendance"]').forEach(checkbox => {
        checkbox.checked = false;
    });
}
</script>-->

<?php include __DIR__ . '/../includes/footer.php'; ?>