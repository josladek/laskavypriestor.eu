<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('lektor');

$currentUser = getCurrentUser();
$instructorId = $currentUser['id'];

// Get lesson types and levels
$lessonTypes = getLessonTypes();
$levels = getLevels();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type_id = (int)($_POST['type_id'] ?? 0);
    $level_id = (int)($_POST['level_id'] ?? 0);
    $date = trim($_POST['date'] ?? '');
    $timeStart = trim($_POST['time_start'] ?? '');
    $timeEnd = trim($_POST['time_end'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $priceWithCredit = (float)($_POST['price_with_credit'] ?? 0);

    $notes = trim($_POST['notes'] ?? '');
    
    // Validation
    if (empty($name) || empty($description) || $type_id <= 0 || $level_id <= 0 || 
        empty($date) || empty($timeStart) || empty($timeEnd) || $capacity <= 0 || 
        $price <= 0 || $priceWithCredit <= 0) {
        $error = 'Prosím vyplňte všetky povinné polia.';
    } elseif ($priceWithCredit >= $price) {
        $error = 'Cena s kreditom musí byť nižšia ako cena na mieste.';
    } elseif (strtotime($date) < strtotime('today')) {
        $error = 'Dátum lekcie musí byť v budúcnosti.';
    } elseif (strtotime($timeEnd) <= strtotime($timeStart)) {
        $error = 'Čas ukončenia musí byť neskôr ako čas začiatku.';
    } else {
        try {
            // Get type and level names
            $lessonType = db()->fetch("SELECT name FROM lesson_types WHERE id = ?", [$type_id]);
            $level = db()->fetch("SELECT name FROM levels WHERE id = ?", [$level_id]);
            
            // Insert class
            $classId = db()->query("
                INSERT INTO yoga_classes (
                    name, description, instructor, instructor_id, type, level, type_id, level_id,
                    date, time_start, time_end, capacity, price, price_with_credit, 
                    notes, status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
            ", [
                $name, $description, $currentUser['name'], $instructorId, 
                $lessonType['name'], $level['name'], $type_id, $level_id,
                $date, $timeStart, $timeEnd, $capacity, $price, $priceWithCredit,
                $notes
            ]);
            
            if ($classId) {
                $success = 'Lekcia bola úspešne vytvorená.';
                // Reset form
                $_POST = [];
            } else {
                $error = 'Chyba pri vytváraní lekcie.';
            }
        } catch (Exception $e) {
            $error = 'Chyba pri vytváraní lekcie: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Vytvoriť novú lekciu';
$currentPage = 'lektor_create_class';
require_once __DIR__ . '/../includes/header.php';
?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title"><?= $pageTitle ?></h1>
            <a href="classes.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Späť na lekcie
            </a>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success) ?>
            <div class="mt-2">
                <a href="classes.php" class="btn btn-sm btn-sage">Zobraziť všetky lekcie</a>
                <a href="create-class.php" class="btn btn-sm btn-sage">Vytvoriť ďalšiu lekciu</a>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-plus"></i> Nová lekcia</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Názov lekcie *</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                                        <div class="invalid-feedback">Prosím zadajte názov lekcie.</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="location" class="form-label">Miesto</label>
                                        <input type="text" class="form-control" id="location" name="location" 
                                               value="<?= htmlspecialchars($_POST['location'] ?? 'Studio') ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Popis lekcie *</label>
                                <textarea class="form-control" id="description" name="description" rows="3" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                <div class="invalid-feedback">Prosím zadajte popis lekcie.</div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="type_id" class="form-label">Druh lekcie *</label>
                                        <select class="form-select" id="type_id" name="type_id" required>
                                            <option value="">Vyberte druh lekcie</option>
                                            <?php foreach ($lessonTypes as $type): ?>
                                            <option value="<?= $type['id'] ?>" 
                                                    <?= (isset($_POST['type_id']) && $_POST['type_id'] == $type['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($type['name']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback">Prosím vyberte druh lekcie.</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="level_id" class="form-label">Úroveň *</label>
                                        <select class="form-select" id="level_id" name="level_id" required>
                                            <option value="">Vyberte úroveň</option>
                                            <?php foreach ($levels as $level): ?>
                                            <option value="<?= $level['id'] ?>" 
                                                    <?= (isset($_POST['level_id']) && $_POST['level_id'] == $level['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($level['name']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback">Prosím vyberte úroveň.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="date" class="form-label">Dátum *</label>
                                        <input type="date" class="form-control" id="date" name="date" 
                                               value="<?= htmlspecialchars($_POST['date'] ?? '') ?>" 
                                               min="<?= date('Y-m-d') ?>" required>
                                        <div class="invalid-feedback">Prosím zadajte dátum lekcie.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="time_start" class="form-label">Čas začiatku *</label>
                                        <input type="time" class="form-control" id="time_start" name="time_start" 
                                               value="<?= htmlspecialchars($_POST['time_start'] ?? '') ?>" required>
                                        <div class="invalid-feedback">Prosím zadajte čas začiatku.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="time_end" class="form-label">Čas ukončenia *</label>
                                        <input type="time" class="form-control" id="time_end" name="time_end" 
                                               value="<?= htmlspecialchars($_POST['time_end'] ?? '') ?>" required>
                                        <div class="invalid-feedback">Prosím zadajte čas ukončenia.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="capacity" class="form-label">Kapacita *</label>
                                        <input type="number" class="form-control" id="capacity" name="capacity" 
                                               value="<?= htmlspecialchars($_POST['capacity'] ?? '') ?>" 
                                               min="1" max="50" required>
                                        <div class="invalid-feedback">Prosím zadajte kapacitu (1-50).</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="price" class="form-label">Cena na mieste (€) *</label>
                                        <input type="number" class="form-control" id="price" name="price" 
                                               value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" 
                                               step="0.01" min="0.01" required>
                                        <div class="invalid-feedback">Prosím zadajte cenu na mieste.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="price_with_credit" class="form-label">Cena s kreditom (€) *</label>
                                        <input type="number" class="form-control" id="price_with_credit" name="price_with_credit" 
                                               value="<?= htmlspecialchars($_POST['price_with_credit'] ?? '') ?>" 
                                               step="0.01" min="0.01" required>
                                        <div class="invalid-feedback">Prosím zadajte cenu s kreditom.</div>
                                        <div class="form-text">Mala by byť nižšia ako cena na mieste</div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">Poznámky</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                                <div class="form-text">Ďalšie informácie pre účastníkov</div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Vytvoriť lekciu
                                </button>
                                <a href="classes.php" class="btn btn-secondary">Zrušiť</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6><i class="fas fa-info-circle"></i> Tipy pre vytvorenie lekcie</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled small">
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <strong>Popis:</strong> Uveďte čo účastníci môžu očakávať
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <strong>Kapacita:</strong> Zvážte veľkosť priestoru
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <strong>Ceny:</strong> Kreditová cena motivuje k nákupu
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <strong>Čas:</strong> Nechajte dostatok času na prípravu
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h6><i class="fas fa-clock"></i> Odporúčané časy</h6>
                    </div>
                    <div class="card-body">
                        <div class="small">
                            <div class="mb-2">
                                <strong>Ráno:</strong> 7:00 - 9:00
                            </div>
                            <div class="mb-2">
                                <strong>Dopoludnie:</strong> 10:00 - 12:00
                            </div>
                            <div class="mb-2">
                                <strong>Popoludnie:</strong> 16:00 - 18:00
                            </div>
                            <div class="mb-2">
                                <strong>Večer:</strong> 19:00 - 21:00
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