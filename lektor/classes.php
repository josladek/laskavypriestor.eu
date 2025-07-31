<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('lektor');

$currentUser = getCurrentUser();
$instructorId = $currentUser['id'];

// Handle class deletion
if ($_POST && isset($_POST['delete_class'])) {
    $classId = (int)$_POST['class_id'];
    
    try {
        // Check if class belongs to instructor
        $class = db()->fetch("SELECT id FROM yoga_classes WHERE id = ? AND instructor_id = ?", [$classId, $instructorId]);
        
        if ($class) {
            // Delete registrations first
            db()->query("DELETE FROM registrations WHERE class_id = ?", [$classId]);
            // Delete class
            db()->query("DELETE FROM yoga_classes WHERE id = ?", [$classId]);
            
            $message = "Lekcia bola úspešne zmazaná.";
        } else {
            $error = "Lekcia nebola nájdená alebo nemáte oprávnenie na jej zmazanie.";
        }
    } catch (Exception $e) {
        $error = "Chyba pri mazaní lekcie: " . $e->getMessage();
    }
}

// Get filters with defaults: active status and upcoming dates
$statusFilter = $_GET['status'] ?? 'active';
$typeFilter = $_GET['type'] ?? 'all';
$dateFilter = $_GET['date'] ?? 'upcoming';

// Build where conditions (exclude course lessons)
$whereConditions = ["yc.instructor_id = ?", "yc.course_id IS NULL"];
$params = [$instructorId];

if ($statusFilter !== 'all') {
    $whereConditions[] = "yc.status = ?";
    $params[] = $statusFilter;
}

if ($typeFilter !== 'all') {
    $whereConditions[] = "yc.type = ?";
    $params[] = $typeFilter;
}

if ($dateFilter === 'today') {
    $whereConditions[] = "yc.date = CURDATE()";
} elseif ($dateFilter === 'upcoming') {
    $whereConditions[] = "yc.date >= CURDATE()";
} elseif ($dateFilter === 'past') {
    $whereConditions[] = "yc.date < CURDATE()";
}

$whereClause = implode(' AND ', $whereConditions);

// Get instructor's classes with registration counts
$classes = db()->fetchAll("
    SELECT yc.*, 
           COUNT(r.id) as registered_count,
           yc.capacity - COUNT(r.id) as available_spots
    FROM yoga_classes yc
    LEFT JOIN registrations r ON yc.id = r.class_id AND r.status = 'confirmed'
    WHERE $whereClause
    GROUP BY yc.id
    ORDER BY yc.date ASC, yc.time_start ASC
", $params);

// Get unique types for filter (exclude course lessons)
$types = db()->fetchAll("
    SELECT DISTINCT type 
    FROM yoga_classes 
    WHERE instructor_id = ? AND course_id IS NULL
    ORDER BY type
", [$instructorId]);

$pageTitle = 'Moje lekcie';
$currentPage = 'lektor_classes';
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
            <h1 class="page-title"><?= $pageTitle ?></h1>
            <div>
                <span class="badge bg-primary me-2">Celkom: <?= count($classes) ?></span>
                <a href="create-class.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nová lekcia
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Všetky</option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Aktívne</option>
                            <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Zrušené</option>
                            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Dokončené</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="type" class="form-label">Typ jogy</label>
                        <select name="type" id="type" class="form-select">
                            <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>Všetky typy</option>
                            <?php foreach ($types as $type): ?>
                            <option value="<?= htmlspecialchars($type['type']) ?>" <?= $typeFilter === $type['type'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['type']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="date" class="form-label">Dátum</label>
                        <select name="date" id="date" class="form-select">
                            <option value="all" <?= $dateFilter === 'all' ? 'selected' : '' ?>>Všetky</option>
                            <option value="today" <?= $dateFilter === 'today' ? 'selected' : '' ?>>Dnes</option>
                            <option value="upcoming" <?= $dateFilter === 'upcoming' ? 'selected' : '' ?>>Nadchádzajúce</option>
                            <option value="past" <?= $dateFilter === 'past' ? 'selected' : '' ?>>Minulé</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">Filtrovať</button>
                            <a href="classes.php" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Classes Table -->
        <div class="card">
            <div class="card-body">
                <?php if (empty($classes)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <h5>Žiadne lekcie</h5>
                    <p class="text-muted">Zatiaľ nemáte vytvorené žiadne lekcie.</p>
                    <a href="create-class.php" class="btn btn-primary">Vytvoriť prvú lekciu</a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Lekcia</th>
                                <th>Typ / Úroveň</th>
                                <th>Dátum / Čas</th>
                                <th>Obsadenosť</th>
                                <th>Cena</th>
                                <th>Status</th>
                                <th>Akcie</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classes as $class): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($class['name']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($class['location']) ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($class['type']) ?></span>
                                        <br><small class="text-muted"><?= htmlspecialchars($class['level']) ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= formatDate($class['date']) ?></strong>
                                        <br><small><?= formatTime($class['time_start']) ?> - <?= formatTime($class['time_end']) ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $class['registered_count'] >= $class['capacity'] ? 'danger' : ($class['registered_count'] > 0 ? 'warning' : 'success') ?>">
                                        <?= $class['registered_count'] ?>/<?= $class['capacity'] ?>
                                    </span>
                                    <?php if ($class['available_spots'] > 0): ?>
                                    <br><small class="text-success"><?= $class['available_spots'] ?> voľných</small>
                                    <?php else: ?>
                                    <br><small class="text-danger">Plné</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= formatPrice($class['price_with_credit']) ?></strong>
                                        <br><small class="text-muted"><?= formatPrice($class['price']) ?> na mieste</small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $class['status'] === 'active' ? 'success' : ($class['status'] === 'cancelled' ? 'danger' : 'secondary') ?>">
                                        <?= ucfirst($class['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="class-detail.php?id=<?= $class['id'] ?>" class="btn btn-outline-primary" title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit-class.php?id=<?= $class['id'] ?>" class="btn btn-outline-secondary" title="Editovať">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" onclick="deleteClass(<?= $class['id'] ?>, '<?= htmlspecialchars($class['name']) ?>')" title="Zmazať">
                                            <i class="fas fa-trash"></i>
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
                    <p>Naozaj chcete zmazať lekciu <strong id="className"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Táto akcia zmaže aj všetky registrácie na túto lekciu!
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušiť</button>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="class_id" id="deleteClassId">
                        <input type="hidden" name="delete_class" value="1">
                        <button type="submit" class="btn btn-danger">Zmazať</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteClass(classId, className) {
            document.getElementById('deleteClassId').value = classId;
            document.getElementById('className').textContent = className;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>