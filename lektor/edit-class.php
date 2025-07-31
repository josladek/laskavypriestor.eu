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
    SELECT * FROM yoga_classes 
    WHERE id = ? AND instructor_id = ?
", [$classId, $instructorId]);

if (!$class) {
    header('Location: classes.php?error=' . urlencode('Lekcia nebola nájdená'));
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $level = trim($_POST['level'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $timeStart = trim($_POST['time_start'] ?? '');
    $timeEnd = trim($_POST['time_end'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $priceWithCredit = (float)($_POST['price_with_credit'] ?? 0);

    $notes = trim($_POST['notes'] ?? '');
    $status = trim($_POST['status'] ?? 'active');
    
    // Validation
    if (empty($name) || empty($description) || empty($type) || empty($level) || 
        empty($date) || empty($timeStart) || empty($timeEnd) || $capacity <= 0 || 
        $price <= 0 || $priceWithCredit <= 0) {
        $error = 'Prosím vyplňte všetky povinné polia.';
    } elseif ($priceWithCredit >= $price) {
        $error = 'Cena s kreditom musí byť nižšia ako cena na mieste.';
    } elseif (strtotime($timeEnd) <= strtotime($timeStart)) {
        $error = 'Čas ukončenia musí byť neskôr ako čas začiatku.';
    } else {
        try {
            // Update class
            db()->query("
                UPDATE yoga_classes SET
                    name = ?, description = ?, type = ?, level = ?, 
                    date = ?, time_start = ?, time_end = ?, capacity = ?, 
                    price = ?, price_with_credit = ?, notes = ?, 
                    status = ?, updated_at = NOW()
                WHERE id = ? AND instructor_id = ?
            ", [
                $name, $description, $type, $level,
                $date, $timeStart, $timeEnd, $capacity,
                $price, $priceWithCredit, $notes,
                $status, $classId, $instructorId
            ]);
            
            $success = 'Lekcia bola úspešne aktualizovaná.';
            
            // Refresh class data
            $class = db()->fetch("SELECT * FROM yoga_classes WHERE id = ?", [$classId]);
            
        } catch (Exception $e) {
            $error = 'Chyba pri aktualizácii lekcie: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Upraviť lekciu - ' . $class['name'];
$currentPage = 'lektor_edit_class';
require_once __DIR__ . '/../includes/header.php';
?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title"><?= $pageTitle ?></h1>
            <div>
                <a href="class-detail.php?id=<?= $class['id'] ?>" class="btn btn-outline-sage">
                    <i class="fas fa-eye me-2"></i>Detail
                </a>
                <a href="classes.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Späť na lekcie
                </a>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success) ?>
            <div class="mt-2">
                <a href="class-detail.php?id=<?= $class['id'] ?>" class="btn btn-sm btn-sage">Zobraziť detail</a>
                <a href="classes.php" class="btn btn-sm btn-sage">Späť na lekcie</a>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-edit"></i> Upraviť lekciu</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Názov lekcie *</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?= htmlspecialchars($class['name']) ?>" required>
                                        <div class="invalid-feedback">Prosím zadajte názov lekcie.</div>
                                    </div>
                                </div>

                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Popis lekcie *</label>
                                <textarea class="form-control" id="description" name="description" rows="3" required><?= htmlspecialchars($class['description']) ?></textarea>
                                <div class="invalid-feedback">Prosím zadajte popis lekcie.</div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="type" class="form-label">Typ jogy *</label>
                                        <select class="form-select" id="type" name="type" required>
                                            <option value="">Vyberte typ</option>
                                            <option value="Hatha" <?= $class['type'] === 'Hatha' ? 'selected' : '' ?>>Hatha</option>
                                            <option value="Vinyasa" <?= $class['type'] === 'Vinyasa' ? 'selected' : '' ?>>Vinyasa</option>
                                            <option value="Yin" <?= $class['type'] === 'Yin' ? 'selected' : '' ?>>Yin</option>
                                            <option value="Ashtanga" <?= $class['type'] === 'Ashtanga' ? 'selected' : '' ?>>Ashtanga</option>
                                            <option value="Power" <?= $class['type'] === 'Power' ? 'selected' : '' ?>>Power</option>
                                            <option value="Restorative" <?= $class['type'] === 'Restorative' ? 'selected' : '' ?>>Restorative</option>
                                            <option value="Hot" <?= $class['type'] === 'Hot' ? 'selected' : '' ?>>Hot Yoga</option>
                                        </select>
                                        <div class="invalid-feedback">Prosím vyberte typ jogy.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="level" class="form-label">Úroveň *</label>
                                        <select class="form-select" id="level" name="level" required>
                                            <option value="">Vyberte úroveň</option>
                                            <option value="Začiatočník" <?= $class['level'] === 'Začiatočník' ? 'selected' : '' ?>>Začiatočník</option>
                                            <option value="Pokročilý" <?= $class['level'] === 'Pokročilý' ? 'selected' : '' ?>>Pokročilý</option>
                                            <option value="Všetky úrovne" <?= $class['level'] === 'Všetky úrovne' ? 'selected' : '' ?>>Všetky úrovne</option>
                                        </select>
                                        <div class="invalid-feedback">Prosím vyberte úroveň.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status *</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="active" <?= $class['status'] === 'active' ? 'selected' : '' ?>>Aktívna</option>
                                            <option value="cancelled" <?= $class['status'] === 'cancelled' ? 'selected' : '' ?>>Zrušená</option>
                                            <option value="completed" <?= $class['status'] === 'completed' ? 'selected' : '' ?>>Dokončená</option>
                                        </select>
                                        <div class="invalid-feedback">Prosím vyberte status.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="date" class="form-label">Dátum *</label>
                                        <input type="date" class="form-control" id="date" name="date" 
                                               value="<?= htmlspecialchars($class['date']) ?>" required>
                                        <div class="invalid-feedback">Prosím zadajte dátum lekcie.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="time_start" class="form-label">Čas začiatku *</label>
                                        <input type="time" class="form-control" id="time_start" name="time_start" 
                                               value="<?= htmlspecialchars($class['time_start']) ?>" required>
                                        <div class="invalid-feedback">Prosím zadajte čas začiatku.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="time_end" class="form-label">Čas ukončenia *</label>
                                        <input type="time" class="form-control" id="time_end" name="time_end" 
                                               value="<?= htmlspecialchars($class['time_end']) ?>" required>
                                        <div class="invalid-feedback">Prosím zadajte čas ukončenia.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="capacity" class="form-label">Kapacita *</label>
                                        <input type="number" class="form-control" id="capacity" name="capacity" 
                                               value="<?= htmlspecialchars($class['capacity']) ?>" 
                                               min="1" max="50" required>
                                        <div class="invalid-feedback">Prosím zadajte kapacitu (1-50).</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="price" class="form-label">Cena na mieste (€) *</label>
                                        <input type="number" class="form-control" id="price" name="price" 
                                               value="<?= htmlspecialchars($class['price']) ?>" 
                                               step="0.01" min="0.01" required>
                                        <div class="invalid-feedback">Prosím zadajte cenu na mieste.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="price_with_credit" class="form-label">Cena s kreditom (€) *</label>
                                        <input type="number" class="form-control" id="price_with_credit" name="price_with_credit" 
                                               value="<?= htmlspecialchars($class['price_with_credit']) ?>" 
                                               step="0.01" min="0.01" required>
                                        <div class="invalid-feedback">Prosím zadajte cenu s kreditom.</div>
                                        <div class="form-text">Mala by byť nižšia ako cena na mieste</div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">Poznámky</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2"><?= htmlspecialchars($class['notes']) ?></textarea>
                                <div class="form-text">Ďalšie informácie pre účastníkov</div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Uložiť zmeny
                                </button>
                                <a href="class-detail.php?id=<?= $class['id'] ?>" class="btn btn-outline-info">
                                    <i class="fas fa-eye"></i> Zobraziť detail
                                </a>
                                <a href="classes.php" class="btn btn-secondary">Zrušiť</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6><i class="fas fa-info-circle"></i> Informácie o úprave</h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Upozornenie:</strong> Zmeny v lekcii sa môžu týkať už registrovaných klientov.
                        </div>
                        
                        <ul class="list-unstyled small">
                            <li class="mb-2">
                                <i class="fas fa-clock text-info me-2"></i>
                                <strong>Čas:</strong> Zmena času upozorní klientov
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-euro-sign text-success me-2"></i>
                                <strong>Cena:</strong> Zmena ovplyvní len nové registrácie
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-users text-primary me-2"></i>
                                <strong>Kapacita:</strong> Zníženie môže ovplyvniť čakačku
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-ban text-danger me-2"></i>
                                <strong>Zrušenie:</strong> Automaticky vráti kredity
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Class statistics -->
                <?php
                $registrations = db()->fetch("
                    SELECT COUNT(*) as count,
                           SUM(CASE WHEN paid_with_credit THEN 1 ELSE 0 END) as credit_payments,
                           SUM(CASE WHEN NOT paid_with_credit THEN 1 ELSE 0 END) as cash_payments
                    FROM registrations 
                    WHERE class_id = ? AND status = 'confirmed'
                ", [$classId]);
                ?>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h6><i class="fas fa-chart-bar"></i> Štatistiky lekcie</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-2">
                                <h5 class="text-primary"><?= $registrations['count'] ?></h5>
                                <p class="small mb-0">Registrácií</p>
                            </div>
                            <div class="col-6 mb-2">
                                <h5 class="text-success"><?= $registrations['credit_payments'] ?></h5>
                                <p class="small mb-0">S kreditom</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();

        // Price validation
        document.getElementById('price_with_credit').addEventListener('input', function() {
            const price = parseFloat(document.getElementById('price').value) || 0;
            const priceWithCredit = parseFloat(this.value) || 0;
            
            if (priceWithCredit >= price && price > 0) {
                this.setCustomValidity('Cena s kreditom musí byť nižšia ako cena na mieste');
            } else {
                this.setCustomValidity('');
            }
        });

        document.getElementById('price').addEventListener('input', function() {
            const priceWithCreditInput = document.getElementById('price_with_credit');
            const price = parseFloat(this.value) || 0;
            const priceWithCredit = parseFloat(priceWithCreditInput.value) || 0;
            
            if (priceWithCredit >= price && price > 0) {
                priceWithCreditInput.setCustomValidity('Cena s kreditom musí byť nižšia ako cena na mieste');
            } else {
                priceWithCreditInput.setCustomValidity('');
            }
        });
    </script>
</body>
</html>