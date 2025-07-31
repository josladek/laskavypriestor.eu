<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$message = '';
$error = '';



// Handle user status toggle
if ($_POST && isset($_POST['toggle_status'])) {
    $userId = (int)$_POST['user_id'];
    $currentUser = getCurrentUser();
    
    // Security check: admin can't disable themselves
    if ($userId == $currentUser['id']) {
        $error = 'Nemôžete zneplatniť sám seba.';
    } else {
        try {
            $user = db()->fetch("SELECT status FROM users WHERE id = ?", [$userId]);
            $newStatus = $user['status'] === 'active' ? 'disabled' : 'active';
            
            db()->query("UPDATE users SET status = ? WHERE id = ?", [$newStatus, $userId]);
            $message = $newStatus === 'active' ? 'Používateľ bol aktivovaný.' : 'Používateľ bol zneplatnený.';
        } catch (Exception $e) {
            $error = 'Chyba pri zmene statusu: ' . $e->getMessage();
        }
    }
}

// Handle user deletion
if ($_POST && isset($_POST['delete_user'])) {
    $userId = (int)$_POST['user_id'];
    $currentUser = getCurrentUser();
    
    // Security check: admin can't delete themselves
    if ($userId == $currentUser['id']) {
        $error = 'Nemôžete zmazať sám seba.';
    } else {
        try {
            // Delete user and related data
            db()->query("DELETE FROM credit_transactions WHERE user_id = ?", [$userId]);
            db()->query("DELETE FROM registrations WHERE user_id = ?", [$userId]);
            db()->query("DELETE FROM client_profiles WHERE user_id = ?", [$userId]);
            db()->query("DELETE FROM instructor_profiles WHERE user_id = ?", [$userId]);
            db()->query("DELETE FROM users WHERE id = ?", [$userId]);
            
            $message = 'Používateľ bol úspešne zmazaný.';
        } catch (Exception $e) {
            $error = 'Chyba pri mazaní používateľa: ' . $e->getMessage();
        }
    }
}

// Get all users with status field handling
$users = db()->fetchAll("
    SELECT u.*, 
           COUNT(r.id) as total_registrations,
           u.eur_balance,
           COALESCE(u.status, 'active') as status
    FROM users u
    LEFT JOIN registrations r ON u.id = r.user_id AND r.status = 'confirmed'
    GROUP BY u.id
    ORDER BY u.created_at DESC
");

$currentPage = 'admin_users';
$pageTitle = 'Správa používateľov';
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container-fluid my-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="text-charcoal"><?= $pageTitle ?></h1>
                <div class="btn-group">
                    <a href="create-user.php" class="btn btn-success">
                        <i class="fas fa-user-plus"></i> Nový používateľ
                    </a>
                    <a href="dashboard.php" class="btn btn-secondary">Späť na dashboard</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
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



        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Meno</th>
                        <th>Email</th>
                        <th>Telefón</th>
                        <th>Rola</th>
                        <th>Status</th>
                        <th>Kredit</th>
                        <th>Registrácie</th>
                        <th>Registrovaný</th>
                        <th>Akcie</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($user['name']) ?></strong>
                        </td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['phone']) ?></td>
                        <td>
                            <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'lektor' ? 'warning' : 'primary') ?>">
                                <?= $user['role'] === 'klient' || $user['role'] === 'student' ? 'Klient' : ($user['role'] === 'lektor' ? 'Lektor' : 'Admin') ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-<?= ($user['status'] ?? 'active') === 'active' ? 'success' : 'danger' ?>">
                                <?= ($user['status'] ?? 'active') === 'active' ? 'Aktívny' : 'Zneplatnený' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($user['role'] === 'klient' || $user['role'] === 'student'): ?>
                                <?= formatPrice($user['eur_balance']) ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-info"><?= $user['total_registrations'] ?></span>
                        </td>
                        <td><?= formatDate($user['created_at']) ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="client-detail.php?id=<?= $user['id'] ?>" class="btn btn-outline-primary" title="Detail">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit-user.php?id=<?= $user['id'] ?>" class="btn btn-outline-secondary" title="Upraviť">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button" class="btn btn-outline-warning" onclick="toggleUserStatus(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>', '<?= $user['status'] ?? 'active' ?>')" title="<?= ($user['status'] ?? 'active') === 'active' ? 'Zneplatniť' : 'Aktivovať' ?>">
                                    <i class="fas fa-<?= ($user['status'] ?? 'active') === 'active' ? 'ban' : 'check' ?>"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger" onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name']) ?>')" title="Zmazať">
                                    <i class="fas fa-trash"></i>
                                </button>
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
                        <h5 class="text-danger"><?= count(array_filter($users, fn($u) => $u['role'] === 'admin')) ?></h5>
                        <p class="card-text">Admini</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="text-warning"><?= count(array_filter($users, fn($u) => $u['role'] === 'lektor')) ?></h5>
                        <p class="card-text">Lektori</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="text-primary"><?= count(array_filter($users, fn($u) => $u['role'] === 'klient' || $u['role'] === 'student')) ?></h5>
                        <p class="card-text">Klienti</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="text-success">
                            <?= formatPrice(array_sum(array_column(array_filter($users, fn($u) => $u['role'] === 'klient' || $u['role'] === 'student'), 'eur_balance'))) ?>
                        </h5>
                        <p class="card-text">Celkový kredit</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Toggle Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Zmeniť status používateľa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Naozaj chcete zmeniť status používateľa <strong id="statusUserName"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span id="statusAction"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušiť</button>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="user_id" id="statusUserId">
                        <input type="hidden" name="toggle_status" value="1">
                        <button type="submit" class="btn btn-warning" id="statusConfirmBtn">Potvrdiť</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Potvrdiť zmazanie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Naozaj chcete zmazať používateľa <strong id="deleteUserName"></strong>?</p>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Upozornenie:</strong> Táto akcia je nevratná a zmaže všetky údaje používateľa vrátane registrácií a transakcií!
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušiť</button>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="user_id" id="deleteUserId">
                        <input type="hidden" name="delete_user" value="1">
                        <button type="submit" class="btn btn-danger">Zmazať definitívne</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleUserStatus(userId, userName, currentStatus) {
            document.getElementById('statusUserId').value = userId;
            document.getElementById('statusUserName').textContent = userName;
            
            const isActive = currentStatus === 'active';
            const action = isActive ? 'zneplatniť' : 'aktivovať';
            const description = isActive ? 
                'Zneplatnený používateľ sa nebude môcť prihlásiť do systému.' : 
                'Aktivovaný používateľ bude môcť opäť používať systém.';
            
            document.getElementById('statusAction').textContent = description;
            document.getElementById('statusConfirmBtn').textContent = isActive ? 'Zneplatniť' : 'Aktivovať';
            document.getElementById('statusConfirmBtn').className = `btn ${isActive ? 'btn-warning' : 'btn-success'}`;
            
            new bootstrap.Modal(document.getElementById('statusModal')).show();
        }

        function deleteUser(userId, userName) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUserName').textContent = userName;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>

<?php include __DIR__ . '/../includes/footer.php'; ?>