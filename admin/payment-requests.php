<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/payment_config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin');

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $adminNotes = trim($_POST['admin_notes'] ?? '');
    
    if ($requestId && in_array($action, ['approve', 'reject'])) {
        try {
            db()->beginTransaction();
            
            $request = db()->fetch("SELECT * FROM payment_requests WHERE id = ?", [$requestId]);
            if (!$request || $request['status'] !== 'pending') {
                throw new Exception('Neplatná požiadavka alebo už bola spracovaná.');
            }
            
            $newStatus = $action === 'approve' ? 'approved' : 'rejected';
            
            // Update request status
            db()->query("
                UPDATE payment_requests 
                SET status = ?, approved_by = ?, approved_at = NOW(), admin_notes = ?, updated_at = NOW()
                WHERE id = ?
            ", [$newStatus, getCurrentUser()['id'], $adminNotes, $requestId]);
            
            if ($action === 'approve') {
                // Add credit to user account with detailed logging
                error_log("DEBUG: Adding credit to user {$request['user_id']}, amount {$request['eur_amount']}");
                
                try {
                    addCredit(
                        $request['user_id'], 
                        $request['eur_amount'], 
                        'manual_payment_approved', 
                        $requestId
                    );
                    error_log("DEBUG: addCredit SUCCESS");
                } catch (Exception $e) {
                    error_log("DEBUG: addCredit FAILED: " . $e->getMessage());
                    throw new Exception('Chyba pri pripísaní kreditu: ' . $e->getMessage());
                }
                
                // Verify credit was actually added
                $userAfter = db()->fetch("SELECT eur_balance FROM users WHERE id = ?", [$request['user_id']]);
                error_log("DEBUG: User balance after addCredit: " . $userAfter['eur_balance']);
                
                // Email notification would be sent here (function not implemented yet)
                error_log("DEBUG: Payment approved for user {$request['user_id']}, amount {$request['eur_amount']} EUR");
                
                // Force refresh user session data for ALL users to ensure cache is cleared
                if (isset($_SESSION['user_id'])) {
                    error_log("DEBUG: Refreshing session for user " . $_SESSION['user_id']);
                    $_SESSION['user'] = getCurrentUser(true); // Force refresh
                    error_log("DEBUG: Session user balance after refresh: " . $_SESSION['user']['eur_balance']);
                }
                
                $_SESSION['flash_message'] = 'Požiadavka bola schválená a kredit bol pripísaný.';
                $_SESSION['flash_type'] = 'success';
            } else {
                $_SESSION['flash_message'] = 'Požiadavka bola zamietnutá.';
                $_SESSION['flash_type'] = 'warning';
            }
            
            db()->commit();
            
        } catch (Exception $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $_SESSION['flash_message'] = 'Chyba: ' . $e->getMessage();
            $_SESSION['flash_type'] = 'error';
        }
    }
}

// Get filter parameters
$status = $_GET['status'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build query
$whereClause = [];
$params = [];

if ($status !== 'all') {
    $whereClause[] = "pr.status = ?";
    $params[] = $status;
}

if ($dateFrom) {
    $whereClause[] = "DATE(pr.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $whereClause[] = "DATE(pr.created_at) <= ?";
    $params[] = $dateTo;
}

$whereSQL = !empty($whereClause) ? 'WHERE ' . implode(' AND ', $whereClause) : '';

// Get payment requests
$requests = db()->fetchAll("
    SELECT 
        pr.*,
        u.name as user_name,
        u.email as user_email,
        approver.name as approved_by_name
    FROM payment_requests pr
    JOIN users u ON pr.user_id = u.id
    LEFT JOIN users approver ON pr.approved_by = approver.id
    $whereSQL
    ORDER BY pr.created_at DESC
", $params);

// Get statistics
$stats = db()->fetch("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as approved_amount
    FROM payment_requests
");

$pageTitle = 'Správa platobných požiadaviek';
$currentPage = 'payment-requests';
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid my-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Platobné požiadavky</h1>
                    <p class="text-muted">Správa manuálnych platieb za kredit</p>
                </div>
            </div>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?= $stats['total'] ?></h4>
                                    <small>Celkom požiadaviek</small>
                                </div>
                                <i class="fas fa-file-invoice fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?= $stats['pending'] ?></h4>
                                    <small>Čakajúce</small>
                                </div>
                                <i class="fas fa-clock fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?= $stats['approved'] ?></h4>
                                    <small>Schválené</small>
                                </div>
                                <i class="fas fa-check fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?= number_format($stats['approved_amount'], 2) ?>€</h4>
                                    <small>Celková suma</small>
                                </div>
                                <i class="fas fa-euro-sign fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Stav</label>
                            <select name="status" class="form-select">
                                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Všetky</option>
                                <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Čakajúce</option>
                                <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Schválené</option>
                                <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Zamietnuté</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Dátum od</label>
                            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Dátum do</label>
                            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-1"></i>Filtrovať
                            </button>
                            <a href="?" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Requests Table -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($requests)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5>Žiadne požiadavky</h5>
                            <p class="text-muted">Neboli nájdené žiadne platobné požiadavky.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Používateľ</th>
                                        <th>Typ platby</th>
                                        <th>Suma</th>
                                        <th>Kredit</th>
                                        <th>Metóda</th>
                                        <th>Stav</th>
                                        <th>Vytvorené</th>
                                        <th>Akcie</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($requests as $request): ?>
                                        <tr>
                                            <td>
                                                <strong>#<?= str_pad($request['id'], 6, '0', STR_PAD_LEFT) ?></strong><br>
                                                <small class="text-muted">VS: <span class="badge bg-primary"><?= $request['variable_symbol'] ?></span></small>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($request['user_name']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($request['user_email']) ?></small>
                                            </td>
                                            <td>
                                                <?php
                                                // Determine payment type based on class_id, course_id, and workshop_id
                                                if (!empty($request['class_id'])) {
                                                    echo '<i class="fas fa-dumbbell me-1 text-primary"></i>Lekcia';
                                                } elseif (!empty($request['course_id'])) {
                                                    echo '<i class="fas fa-graduation-cap me-1 text-info"></i>Kurz';
                                                } elseif (!empty($request['workshop_id'])) {
                                                    echo '<i class="fas fa-tools me-1 text-warning"></i>Workshop';
                                                } else {
                                                    echo '<i class="fas fa-coins me-1 text-success"></i>Kredit';
                                                }
                                                ?>
                                            </td>
                                            <td><strong><?= number_format($request['amount'], 2) ?>€</strong></td>
                                            <td><?= number_format($request['eur_amount'], 2) ?>€</td>
                                            <td>
                                                <?= $request['payment_method'] === 'bank_transfer' ? 
                                                    '<i class="fas fa-university me-1"></i>Prevod' : 
                                                    '<i class="fas fa-money-bill me-1"></i>Hotovosť' ?>
                                            </td>
                                            <td>
                                                <?php
                                                $statusBadges = [
                                                    'pending' => 'warning',
                                                    'approved' => 'success',
                                                    'rejected' => 'danger',
                                                    'cancelled' => 'secondary'
                                                ];
                                                $statusTexts = [
                                                    'pending' => 'Neuhradená',
                                                    'approved' => 'Uhradená',
                                                    'rejected' => 'Zamietnutá',
                                                    'cancelled' => 'Zrušená'
                                                ];
                                                ?>
                                                <span class="badge bg-<?= $statusBadges[$request['status']] ?>">
                                                    <?= $statusTexts[$request['status']] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= date('d.m.Y', strtotime($request['created_at'])) ?><br>
                                                <small class="text-muted"><?= date('H:i', strtotime($request['created_at'])) ?></small>
                                            </td>
                                            <td>
                                                <?php if ($request['status'] === 'pending'): ?>
                                                    <button type="button" class="btn btn-sm btn-success me-1" 
                                                            onclick="approveRequest(<?= $request['id'] ?>)">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            onclick="rejectRequest(<?= $request['id'] ?>)">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            onclick="viewDetails(<?= $request['id'] ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
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
</div>

<!-- Action Modal -->
<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="actionModalTitle">Spracovanie požiadavky</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="request_id" id="actionRequestId">
                    <input type="hidden" name="action" id="actionType">
                    
                    <div class="mb-3">
                        <label class="form-label">Poznámka administrátora</label>
                        <textarea name="admin_notes" class="form-control" rows="3" 
                                  placeholder="Voliteľná poznámka k spracovaniu požiadavky..."></textarea>
                    </div>
                    
                    <div id="approveText" class="alert alert-success d-none">
                        <strong>Schváliť požiadavku:</strong> Kredit bude pripísaný na účet používateľa a bude odoslaný potvrdzovacie email.
                    </div>
                    
                    <div id="rejectText" class="alert alert-danger d-none">
                        <strong>Zamietnuť požiadavku:</strong> Kredit nebude pripísaný a používateľ bude informovaný emailom.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušiť</button>
                    <button type="submit" class="btn" id="actionSubmitBtn">Potvrdiť</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function approveRequest(requestId) {
    document.getElementById('actionRequestId').value = requestId;
    document.getElementById('actionType').value = 'approve';
    document.getElementById('actionModalTitle').textContent = 'Schváliť požiadavku';
    document.getElementById('approveText').classList.remove('d-none');
    document.getElementById('rejectText').classList.add('d-none');
    document.getElementById('actionSubmitBtn').className = 'btn btn-success';
    document.getElementById('actionSubmitBtn').textContent = 'Schváliť';
    
    new bootstrap.Modal(document.getElementById('actionModal')).show();
}

function rejectRequest(requestId) {
    document.getElementById('actionRequestId').value = requestId;
    document.getElementById('actionType').value = 'reject';
    document.getElementById('actionModalTitle').textContent = 'Zamietnuť požiadavku';
    document.getElementById('approveText').classList.add('d-none');
    document.getElementById('rejectText').classList.remove('d-none');
    document.getElementById('actionSubmitBtn').className = 'btn btn-danger';
    document.getElementById('actionSubmitBtn').textContent = 'Zamietnuť';
    
    new bootstrap.Modal(document.getElementById('actionModal')).show();
}

function viewDetails(requestId) {
    // Implement view details modal if needed
    alert('Detail požiadavky #' + requestId);
}
</script>

<?php include '../includes/footer.php'; ?>