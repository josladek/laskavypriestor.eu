<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is admin
requireRole('admin');

// Get search filters
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
// Remove registration filter as is_public_registration doesn't exist in this database

// Build query
$where_conditions = [];
$params = [];

// Show all users by default, filter by role if needed
// Remove is_public_registration filter as it doesn't exist in this database

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($role_filter)) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get users (simplified query for existing database structure)
$sql = "SELECT u.*, 
               COUNT(r.id) as total_registrations,
               COUNT(CASE WHEN r.status = 'confirmed' THEN 1 END) as confirmed_registrations,
               MAX(r.registered_on) as last_registration,
               COALESCE(u.status, 'active') as user_status
        FROM users u
        LEFT JOIN registrations r ON u.id = r.user_id
        $where_clause
        GROUP BY u.id, u.name, u.email, u.phone, u.password_hash, u.eur_balance, u.role, u.created_at
        ORDER BY u.created_at DESC";

$users = db()->fetchAll($sql, $params);

// Get statistics (simplified for existing database)
$stats = [
    'total_clients' => db()->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'klient'")['count'],
    'total_instructors' => db()->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'instructor'")['count'],
    'total_lecturers' => db()->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'lektor'")['count'],
    'total_admins' => db()->fetch("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")['count'],
];

$pageTitle = 'Správa klientov';
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container-fluid my-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-charcoal">Správa klientov</h1>
                    <p class="text-muted">Prehľad a správa všetkých klientov</p>
                </div>
                <a href="create-user.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Vytvoriť používateľa
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-users fa-2x text-primary mb-2"></i>
                    <h5 class="card-title"><?= $stats['total_clients'] ?></h5>
                    <p class="card-text text-muted">Klienti</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-chalkboard-teacher fa-2x text-success mb-2"></i>
                    <h5 class="card-title"><?= $stats['total_lecturers'] ?></h5>
                    <p class="card-text text-muted">Lektori</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-user-shield fa-2x text-warning mb-2"></i>
                    <h5 class="card-title"><?= $stats['total_admins'] ?></h5>
                    <p class="card-text text-muted">Administrátori</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-user-cog fa-2x text-info mb-2"></i>
                    <h5 class="card-title"><?= $stats['total_instructors'] ?></h5>
                    <p class="card-text text-muted">Inštruktori (legacy)</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Hľadať</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?= e($search) ?>" placeholder="Meno, email alebo telefón">
                </div>
                <div class="col-md-3">
                    <label for="role" class="form-label">Rola</label>
                    <select class="form-select" id="role" name="role">
                        <option value="">Všetky roly</option>
                        <option value="klient" <?= $role_filter === 'klient' ? 'selected' : '' ?>>Klient</option>
                        <option value="instructor" <?= $role_filter === 'instructor' ? 'selected' : '' ?>>Lektor</option>
                        <option value="lektor" <?= $role_filter === 'lektor' ? 'selected' : '' ?>>Lektor (Slovak)</option>
                        <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Administrátor</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Filtrovať</button>
                        <a href="clients.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Všetci používatelia (<?= count($users) ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($users)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Žiadni používatelia</h5>
                    <p class="text-muted">Nie sú nájdení žiadni používatelia podľa zadaných kritérií.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Meno</th>
                                <th>Email</th>
                                <th>Telefón</th>
                                <th>Rola</th>
                                <th>Status</th>
                                <th>Kredit</th>
                                <th>Registrácie</th>
                                <th>Vytvorené</th>
                                <th>Akcie</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($user['role'] === 'lektor' && $user['lecturer_photo']): ?>
                                                <img src="<?= url('uploads/lecturers/' . $user['lecturer_photo']) ?>" 
                                                     alt="<?= e($user['name']) ?>" 
                                                     class="rounded-circle me-2" 
                                                     style="width: 32px; height: 32px; object-fit: cover;">
                                            <?php endif; ?>
                                            <div>
                                                <strong><?= e($user['name']) ?></strong>
                                                <?php if ($user['role'] === 'lektor' && $user['lecturer_description']): ?>
                                                    <br><small class="text-muted">Má podrobný profil</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= e($user['email']) ?></td>
                                    <td><?= e($user['phone']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'lektor' ? 'warning' : 'primary') ?>">
                                            <?php 
                                            $role_display = [
                                                'klient' => 'Klient',
                                                'klient' => 'Klient',
                                                'instructor' => 'Lektor',
                                                'lektor' => 'Lektor',
                                                'admin' => 'Administrátor'
                                            ];
                                            echo $role_display[$user['role']] ?? ucfirst($user['role']);
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $status = $user['user_status'] ?? 'active';
                                        if ($status === 'blocked'): ?>
                                            <span class="badge bg-danger">Blokovaný</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Aktívny</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['role'] === 'klient'): ?>
                                            <span class="badge bg-success"><?= number_format($user['eur_balance'], 2) ?> €</span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <small>
                                            Celkom: <?= $user['total_registrations'] ?><br>
                                            Potvrdené: <?= $user['confirmed_registrations'] ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small><?= date('d.m.Y H:i', strtotime($user['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="edit-user.php?id=<?= $user['id'] ?>" 
                                               class="btn btn-outline-primary" title="Upraviť">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($user['role'] === 'klient'): ?>
                                                <a href="client-detail.php?id=<?= $user['id'] ?>" 
                                                   class="btn btn-outline-info" title="Detail">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <?php 
                                                $status = $user['user_status'] ?? 'active';
                                                if ($status === 'blocked'): ?>
                                                    <button type="button" class="btn btn-outline-success" 
                                                            onclick="toggleUserStatus(<?= $user['id'] ?>, 'active', '<?= e($user['name']) ?>')" 
                                                            title="Odblokovať">
                                                        <i class="fas fa-unlock"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-outline-warning" 
                                                            onclick="toggleUserStatus(<?= $user['id'] ?>, 'blocked', '<?= e($user['name']) ?>')" 
                                                            title="Zablokovať">
                                                        <i class="fas fa-lock"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-outline-danger" 
                                                        onclick="deleteUser(<?= $user['id'] ?>, '<?= e($user['name']) ?>')" 
                                                        title="Zmazať">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
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
</div>

<script>
function deleteUser(userId, userName) {
    if (confirm('Naozaj chcete zmazať používateľa "' + userName + '"?\nTáto akcia sa nedá vrátiť späť.')) {
        fetch('delete-user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_id: userId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Používateľ bol úspešne zmazaný.');
                location.reload();
            } else {
                alert('Chyba pri mazaní používateľa: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Došlo k chybe pri mazaní používateľa.');
        });
    }
}

function toggleUserStatus(userId, newStatus, userName) {
    const action = newStatus === 'blocked' ? 'zablokovať' : 'odblokovať';
    const confirmMessage = newStatus === 'blocked' 
        ? `Naozaj chcete zablokovať používateľa "${userName}"?\nBlokovaný používateľ sa nebude môcť prihlásiť do systému.`
        : `Naozaj chcete odblokovať používateľa "${userName}"?`;
    
    if (confirm(confirmMessage)) {
        fetch('toggle-user-status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_id: userId,
                status: newStatus
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Používateľ bol úspešne ${newStatus === 'blocked' ? 'zablokovaný' : 'odblokovaný'}.`);
                location.reload();
            } else {
                alert('Chyba pri zmene statusu používateľa: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Došlo k chybe pri zmene statusu používateľa.');
        });
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>