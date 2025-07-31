<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is admin
requireRole('admin');

// Get lecturers with statistics
$lecturers = db()->fetchAll("
    SELECT u.*, 
           COUNT(DISTINCT yc.id) as total_classes,
           COUNT(DISTINCT c.id) as total_courses,
           COUNT(DISTINCT w.id) as total_workshops,
           COUNT(DISTINCT r.id) as total_registrations
    FROM users u 
    LEFT JOIN yoga_classes yc ON u.id = yc.instructor_id
    LEFT JOIN courses c ON u.id = c.instructor_id  
    LEFT JOIN workshops w ON u.id = w.instructor_id
    LEFT JOIN registrations r ON yc.id = r.class_id AND r.status = 'confirmed'
    WHERE u.role = 'lektor'
    GROUP BY u.id
    ORDER BY u.name ASC
");

$pageTitle = 'Správa lektorov';
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container-fluid my-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-charcoal">Správa lektorov</h1>
                    <p class="text-muted">Prehľad a správa všetkých lektorov</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="clients.php" class="btn btn-outline-secondary">
                        <i class="fas fa-users me-1"></i> Správa klientov
                    </a>
                    <a href="create-user.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Vytvoriť lektora
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Lecturers Grid -->
    <div class="row">
        <?php if (empty($lecturers)): ?>
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="fas fa-chalkboard-teacher fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Žiadni lektori</h5>
                    <p class="text-muted">Zatiaľ nie sú vytvorení žiadni lektori.</p>
                    <a href="create-user.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Vytvoriť prvého lektora
                    </a>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($lecturers as $lecturer): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start mb-3">
                                <div class="me-3">
                                    <?php if ($lecturer['lecturer_photo']): ?>
                                        <img src="<?= url('uploads/lecturers/' . $lecturer['lecturer_photo']) ?>" 
                                             alt="<?= e($lecturer['name']) ?>" 
                                             class="rounded-circle" 
                                             style="width: 60px; height: 60px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                             style="width: 60px; height: 60px;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="card-title mb-1"><?= e($lecturer['name']) ?></h5>
                                    <p class="text-muted mb-0"><?= e($lecturer['email']) ?></p>
                                    <small class="text-muted"><?= e($lecturer['phone']) ?></small>
                                </div>
                            </div>

                            <?php if ($lecturer['lecturer_description']): ?>
                                <div class="mb-3">
                                    <h6 class="text-muted">Popis:</h6>
                                    <div class="lecturer-description" style="max-height: 120px; overflow: hidden;">
                                        <?= $lecturer['lecturer_description'] ?>
                                    </div>
                                    <?php if (strlen($lecturer['lecturer_description']) > 200): ?>
                                        <button class="btn btn-link btn-sm p-0" onclick="toggleDescription(this)">
                                            Zobraziť viac
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="row text-center mb-3">
                                <div class="col-3">
                                    <div class="border-end">
                                        <strong class="d-block"><?= $lecturer['total_classes'] ?></strong>
                                        <small class="text-muted">Lekcie</small>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="border-end">
                                        <strong class="d-block"><?= $lecturer['total_courses'] ?></strong>
                                        <small class="text-muted">Kurzy</small>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="border-end">
                                        <strong class="d-block"><?= $lecturer['total_workshops'] ?></strong>
                                        <small class="text-muted">Workshopy</small>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <strong class="d-block"><?= $lecturer['total_registrations'] ?></strong>
                                    <small class="text-muted">Študenti</small>
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <a href="edit-user.php?id=<?= $lecturer['id'] ?>" 
                                   class="btn btn-primary btn-sm flex-fill">
                                    <i class="fas fa-edit me-1"></i> Upraviť
                                </a>
                                <a href="clients.php?search=<?= urlencode($lecturer['email']) ?>" 
                                   class="btn btn-outline-info btn-sm">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                        onclick="deleteLecturer(<?= $lecturer['id'] ?>, '<?= e($lecturer['name']) ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-footer text-muted">
                            <small>
                                Vytvorený: <?= date('d.m.Y', strtotime($lecturer['created_at'])) ?>
                            </small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleDescription(button) {
    const description = button.previousElementSibling;
    if (description.style.maxHeight === 'none') {
        description.style.maxHeight = '120px';
        button.textContent = 'Zobraziť viac';
    } else {
        description.style.maxHeight = 'none';
        button.textContent = 'Zobraziť menej';
    }
}

function deleteLecturer(lecturerId, lecturerName) {
    if (confirm('Naozaj chcete zmazať lektora "' + lecturerName + '"?\n\nPozor: Zmaže sa aj všetko čo vytvoril (lekcie, kurzy, workshopy).\nTáto akcia sa nedá vrátiť späť.')) {
        fetch('delete-user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_id: lecturerId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Lektor bol úspešne zmazaný.');
                location.reload();
            } else {
                alert('Chyba pri mazaní lektora: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Došlo k chybe pri mazaní lektora.');
        });
    }
}
</script>

<!-- CSS moved to laskavypriestor.css -->

<?php include __DIR__ . '/../includes/footer.php'; ?>