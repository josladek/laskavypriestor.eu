<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/image_upload.php';

requireRole('admin');

// Function to create recurring lessons
function createRecurringLessons($lessonData, $startDate, $endDate, $recurringDays) {
    $created_count = 0;
    $recurring_series_id = uniqid(); // Unique ID for this series of recurring lessons
    
    // Days mapping
    $days_map = [
        'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4,
        'friday' => 5, 'saturday' => 6, 'sunday' => 0
    ];
    
    $current_date = new DateTime($startDate);
    $end_date = new DateTime($endDate);
    
    while ($current_date <= $end_date) {
        $current_day = (int)$current_date->format('w'); // 0 = Sunday, 1 = Monday, etc.
        
        // Check if this day should have a lesson
        foreach ($recurringDays as $day) {
            if (isset($days_map[$day]) && $days_map[$day] == $current_day) {
                try {
                    db()->query("
                        INSERT INTO yoga_classes 
                        (name, description, instructor, instructor_id, type, level, type_id, level_id, date, time_start, time_end, 
                         capacity, price, price_with_credit, notes, image_url, status, recurring_series_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)
                    ", [
                        $lessonData['name'], $lessonData['description'], $lessonData['instructor'], $lessonData['instructor_id'],
                        $lessonData['type'], $lessonData['level'], $lessonData['type_id'], $lessonData['level_id'],
                        $current_date->format('Y-m-d'), $lessonData['time_start'], $lessonData['time_end'],
                        $lessonData['capacity'], $lessonData['price'], $lessonData['price_with_credit'],
                        $lessonData['notes'], $lessonData['image_url'], $recurring_series_id
                    ]);
                    $created_count++;
                } catch (Exception $e) {
                    // Log error but continue creating other lessons
                    error_log("Error creating recurring lesson: " . $e->getMessage());
                }
                break; // Only create one lesson per day
            }
        }
        
        $current_date->add(new DateInterval('P1D')); // Add one day
    }
    
    return $created_count;
}

// Get all instructors, lesson types and levels
$instructors = db()->fetchAll("SELECT id, name FROM users WHERE role = 'lektor' ORDER BY name");
$lessonTypes = getLessonTypes();
$levels = getLevels();

if ($_POST) {
    // Debug - log POST data
    error_log("CREATE CLASS POST: " . print_r($_POST, true));
    
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $instructor_id = (int)$_POST['instructor_id'];
    $type_id = (int)$_POST['type_id'];
    $level_id = (int)$_POST['level_id'];
    $date = $_POST['date'];
    $time_start = $_POST['time_start'];
    $time_end = $_POST['time_end'];
    $capacity = (int)$_POST['capacity'];
    $price = (float)$_POST['price'];
    $price_with_credit = (float)$_POST['price_with_credit'];

    $notes = trim($_POST['notes']);
    
    // Recurring lesson options
    $is_recurring = isset($_POST['is_recurring']) && $_POST['is_recurring'] == '1';
    $recurring_days = [];
    $recurring_end_date = '';
    
    if ($is_recurring) {
        $recurring_days = $_POST['recurring_days'] ?? [];
        $recurring_end_date = $_POST['recurring_end_date'] ?? '';
    }
    
    $errors = [];
    $imageUrl = null;
    
    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadResult = uploadImage($_FILES['image'], 'uploads/classes/');
        if ($uploadResult['success']) {
            $imageUrl = $uploadResult['filename'];
        } else {
            $errors[] = $uploadResult['error'];
        }
    }
    
    if (empty($name)) $errors[] = "Názov lekcie je povinný.";
    if (empty($description)) $errors[] = "Popis lekcie je povinný.";
    if ($instructor_id <= 0) $errors[] = "Vyberte lektora.";
    if ($type_id <= 0) $errors[] = "Druh lekcie je povinný.";
    if ($level_id <= 0) $errors[] = "Úroveň je povinná.";
    if (empty($date)) $errors[] = "Dátum je povinný.";
    if (empty($time_start)) $errors[] = "Čas začiatku je povinný.";
    if (empty($time_end)) $errors[] = "Čas konca je povinný.";
    if ($capacity <= 0) $errors[] = "Kapacita musí byť väčšia ako 0.";
    if ($price <= 0) $errors[] = "Cena musí byť väčšia ako 0.";
    if ($price_with_credit <= 0) $errors[] = "Cena s kreditom musí byť väčšia ako 0.";
    
    if (strtotime($date) < strtotime(date('Y-m-d'))) {
        $errors[] = "Dátum nemôže byť v minulosti.";
    }
    
    if (strtotime($time_end) <= strtotime($time_start)) {
        $errors[] = "Čas konca musí byť po čase začiatku.";
    }
    
    // Validate recurring options
    if ($is_recurring) {
        if (empty($recurring_days)) {
            $errors[] = "Pre opakovanú lekciu musíte vybrať aspoň jeden deň v týždni.";
        }
        if (empty($recurring_end_date)) {
            $errors[] = "Pre opakovanú lekciu musíte zadať dátum ukončenia.";
        } elseif (strtotime($recurring_end_date) <= strtotime($date)) {
            $errors[] = "Dátum ukončenia opakovaných lekcií musí byť neskôr ako prvá lekcia.";
        }
    }
    
    if (empty($errors)) {
        try {
            error_log("Creating lesson - no errors found");
            // Get instructor name, lesson type and level
            $instructor = db()->fetch("SELECT name FROM users WHERE id = ?", [$instructor_id]);
            $lessonType = db()->fetch("SELECT name FROM lesson_types WHERE id = ?", [$type_id]);
            $level = db()->fetch("SELECT name FROM levels WHERE id = ?", [$level_id]);
            
            if ($is_recurring) {
                // Create recurring lessons
                $created_count = createRecurringLessons([
                    'name' => $name,
                    'description' => $description,
                    'instructor' => $instructor['name'],
                    'instructor_id' => $instructor_id,
                    'type' => $lessonType['name'],
                    'level' => $level['name'],
                    'type_id' => $type_id,
                    'level_id' => $level_id,
                    'time_start' => $time_start,
                    'time_end' => $time_end,
                    'capacity' => $capacity,
                    'price' => $price,
                    'price_with_credit' => $price_with_credit,

                    'notes' => $notes,
                    'image_url' => $imageUrl
                ], $date, $recurring_end_date, $recurring_days);
                
                header('Location: classes.php?created=' . $created_count . '&recurring=1');
                exit;
            } else {
                // Create single lesson
                $lesson_id = db()->query("
                    INSERT INTO yoga_classes 
                    (name, description, instructor, instructor_id, type, level, type_id, level_id, date, time_start, time_end, 
                     capacity, price, price_with_credit, notes, image_url, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                ", [
                    $name, $description, $instructor['name'], $instructor_id, $lessonType['name'], $level['name'], 
                    $type_id, $level_id, $date, $time_start, $time_end, $capacity, $price, $price_with_credit, $notes, $imageUrl
                ]);
                
                header('Location: classes.php?created=1');
                exit;
            }
        } catch (Exception $e) {
            $errors[] = "Chyba pri vytváraní lekcie: " . $e->getMessage();
            error_log("CREATE LESSON ERROR: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
        }
    } else {
        error_log("CREATE LESSON ERRORS: " . print_r($errors, true));
    }
}

$pageTitle = 'Nová lekcia';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-10 mx-auto">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><?= $pageTitle ?></h1>
            <a href="classes.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Späť na lekcie
            </a>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <h6>Chyby vo formulári:</h6>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Informácie o lekcii</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="name" class="form-label">Názov lekcie *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="instructor_id" class="form-label">Lektor *</label>
                                    <select class="form-select" id="instructor_id" name="instructor_id" required>
                                        <option value="">Vyberte lektora</option>
                                        <?php foreach ($instructors as $instructor): ?>
                                        <option value="<?= $instructor['id'] ?>" 
                                                <?= (isset($_POST['instructor_id']) && $_POST['instructor_id'] == $instructor['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($instructor['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Popis lekcie *</label>
                                <textarea class="form-control" id="description" name="description" rows="3" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
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
                                </div>
                                <div class="col-md-6 mb-3">
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
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="date" class="form-label">Dátum *</label>
                                    <input type="date" class="form-control" id="date" name="date" 
                                           value="<?= htmlspecialchars($_POST['date'] ?? '') ?>" 
                                           min="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="time_start" class="form-label">Čas začiatku *</label>
                                    <input type="time" class="form-control" id="time_start" name="time_start" 
                                           value="<?= htmlspecialchars($_POST['time_start'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="time_end" class="form-label">Čas konca *</label>
                                    <input type="time" class="form-control" id="time_end" name="time_end" 
                                           value="<?= htmlspecialchars($_POST['time_end'] ?? '') ?>" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="capacity" class="form-label">Kapacita *</label>
                                    <input type="number" class="form-control" id="capacity" name="capacity" 
                                           value="<?= htmlspecialchars($_POST['capacity'] ?? '12') ?>" min="1" max="50" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="price" class="form-label">Cena na mieste (€) *</label>
                                    <input type="number" step="0.01" class="form-control" id="price" name="price" 
                                           value="<?= htmlspecialchars($_POST['price'] ?? '12.00') ?>" min="0.01" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="price_with_credit" class="form-label">Cena s kreditom (€) *</label>
                                    <input type="number" step="0.01" class="form-control" id="price_with_credit" name="price_with_credit" 
                                           value="<?= htmlspecialchars($_POST['price_with_credit'] ?? '10.00') ?>" min="0.01" required>
                                </div>
                            </div>



                            <div class="mb-3">
                                <label for="image" class="form-label">Obrázok lekcie</label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                <div class="form-text">Podporované formáty: JPG, PNG, GIF, WEBP. Maximálna veľkosť: 5MB.</div>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">Poznámky</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                            </div>

                            <!-- Recurring Lessons Section -->
                            <div class="card mt-4">
                                <div class="card-header">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_recurring" name="is_recurring" value="1"
                                               <?= (isset($_POST['is_recurring']) && $_POST['is_recurring'] == '1') ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="is_recurring">
                                            <strong>Opakovaná lekcia</strong>
                                        </label>
                                    </div>
                                </div>
                                <div class="card-body" id="recurring_options" style="display: <?= (isset($_POST['is_recurring']) && $_POST['is_recurring'] == '1') ? 'block' : 'none' ?>;">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Dni v týždni *</label>
                                            <?php 
                                            $days = [
                                                'monday' => 'Pondelok',
                                                'tuesday' => 'Utorok', 
                                                'wednesday' => 'Streda',
                                                'thursday' => 'Štvrtok',
                                                'friday' => 'Piatok',
                                                'saturday' => 'Sobota',
                                                'sunday' => 'Nedeľa'
                                            ];
                                            foreach ($days as $value => $label): 
                                            ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="recurring_days[]" 
                                                           value="<?= $value ?>" id="day_<?= $value ?>"
                                                           <?= (isset($_POST['recurring_days']) && in_array($value, $_POST['recurring_days'])) ? 'checked' : '' ?>>
                                                    <label class="form-check-label" for="day_<?= $value ?>">
                                                        <?= $label ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="recurring_end_date" class="form-label">Ukončiť opakovanie dňa *</label>
                                            <input type="date" class="form-control" id="recurring_end_date" name="recurring_end_date" 
                                                   value="<?= htmlspecialchars($_POST['recurring_end_date'] ?? '') ?>" 
                                                   min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                                            <div class="form-text">Lekcie sa budú vytvárať až do tohto dátumu</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="classes.php" class="btn btn-secondary">Zrušiť</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <span id="submit_text">Vytvoriť lekciu</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const recurringCheckbox = document.getElementById('is_recurring');
    const recurringOptions = document.getElementById('recurring_options');
    const submitText = document.getElementById('submit_text');
    
    recurringCheckbox.addEventListener('change', function() {
        if (this.checked) {
            recurringOptions.style.display = 'block';
            submitText.textContent = 'Vytvoriť opakované lekcie';
        } else {
            recurringOptions.style.display = 'none';
            submitText.textContent = 'Vytvoriť lekciu';
        }
    });
    
    // Set initial state
    if (recurringCheckbox.checked) {
        submitText.textContent = 'Vytvoriť opakované lekcie';
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>