<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../includes/image_upload.php';

    if (!isLoggedIn() || !isAdmin()) {
        header('Location: ' . dirname($_SERVER['SCRIPT_NAME']) . '/../pages/login.php');
        exit;
    }
} catch (Exception $e) {
    die("Chyba načítania: " . htmlspecialchars($e->getMessage()) . "<br><a href='../debug-error.php'>Debug</a>");
}

$currentUser = getCurrentUser();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $instructor_id = (int)$_POST['instructor_id'];
        $type_id = !empty($_POST['type_id']) ? (int)$_POST['type_id'] : null;
        $level_id = !empty($_POST['level_id']) ? (int)$_POST['level_id'] : null;
        $total_lessons = (int)$_POST['total_lessons'];
        $lesson_duration_minutes = (int)$_POST['lesson_duration_minutes'];
        $price = (float)$_POST['price'];

        $start_date = $_POST['start_date'];
        $day_of_week = (int)$_POST['day_of_week'];
        $time_start = $_POST['time_start'];
        $time_end = $_POST['time_end'];
        $capacity = (int)$_POST['capacity'];

        
        $imageUrl = null;
        
        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = uploadImage($_FILES['image'], 'uploads/courses/');
            if ($uploadResult['success']) {
                $imageUrl = $uploadResult['filename'];
            } else {
                throw new Exception($uploadResult['error']);
            }
        }
        
        if (empty($name) || empty($instructor_id) || empty($start_date) || $total_lessons < 1) {
            throw new Exception('Všetky povinné polia musia byť vyplnené.');
        }
        
        // Calculate end date
        $startDateTime = new DateTime($start_date);
        $endDateTime = clone $startDateTime;
        $endDateTime->add(new DateInterval('P' . (($total_lessons - 1) * 7) . 'D'));
        $end_date = $endDateTime->format('Y-m-d');
        
        // Get type and level names for lessons
        $typeName = null;
        $levelName = null;
        if ($type_id) {
            $typeData = db()->fetch("SELECT name FROM lesson_types WHERE id = ?", [$type_id]);
            $typeName = $typeData ? $typeData['name'] : null;
        }
        if ($level_id) {
            $levelData = db()->fetch("SELECT name FROM levels WHERE id = ?", [$level_id]);
            $levelName = $levelData ? $levelData['name'] : null;
        }

        // Insert new course
        $courseId = db()->query("
            INSERT INTO courses (name, description, instructor_id, type_id, level_id, 
                               total_lessons, lesson_duration_minutes, price, price_with_credit, start_date, end_date, 
                               day_of_week, time_start, time_end, capacity, image_url, 
                               status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
        ", [$name, $description, $instructor_id, $type_id, $level_id, $total_lessons, 
            $lesson_duration_minutes, $price, $price, $start_date, $end_date, $day_of_week, 
            $time_start, $time_end, $capacity, $imageUrl]);
        
        // Generate lessons for the course
        if ($courseId) {
            $lessonDateTime = new DateTime($start_date);
            
            for ($i = 1; $i <= $total_lessons; $i++) {
                $lessonDate = $lessonDateTime->format('Y-m-d');
                
                $pricePerLesson = $price / $total_lessons;
                
                db()->query("
                    INSERT INTO yoga_classes (name, description, instructor, instructor_id, 
                                            type, level, type_id, level_id,
                                            date, time_start, time_end, capacity, price, price_with_credit,
                                            course_id, lesson_number, status, created_at, updated_at) 
                    VALUES (?, ?, (SELECT name FROM users WHERE id = ?), ?, ?, ?, ?, ?, 
                            ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
                ", [
                    $name . " - Lekcia $i",
                    $description,
                    $instructor_id,
                    $instructor_id,
                    $typeName ?: 'Kurz',
                    $levelName ?: 'Všetky úrovne', 
                    $type_id,
                    $level_id,
                    $lessonDate,
                    $time_start,
                    $time_end,
                    $capacity,
                    $pricePerLesson,
                    $pricePerLesson, // Same price for both regular and credit
                    $courseId,
                    $i
                ]);
                
                // Add 7 days for next lesson
                $lessonDateTime->add(new DateInterval('P7D'));
            }
        }
        
        setFlashMessage('Kurz bol úspešne vytvorený s ' . $total_lessons . ' lekciami.', 'success');
        header('Location: courses.php');
        exit;
        
    } catch (Exception $e) {
        setFlashMessage('Chyba pri vytváraní kurzu: ' . $e->getMessage(), 'danger');
    }
}

// Get instructors and dictionary data
$instructors = db()->fetchAll("SELECT id, name FROM users WHERE role = 'lektor' ORDER BY name");
$lessonTypes = getLessonTypes();
$levels = getLevels();

$pageTitle = 'Vytvoriť kurz';
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container my-5">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Vytvoriť nový kurz</h1>
                    <p class="text-muted">Pridať nový jogový kurz s automaticky generovanými lekciami</p>
                </div>
                <a href="courses.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-1"></i> Späť na kurzy
                </a>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form method="post" class="needs-validation" novalidate enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Názov kurzu *</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="instructor_id" class="form-label">Lektor *</label>
                                    <select class="form-select" id="instructor_id" name="instructor_id" required>
                                        <option value="">Vyberte lektora</option>
                                        <?php foreach ($instructors as $instructor): ?>
                                            <option value="<?= $instructor['id'] ?>"><?= e($instructor['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Popis kurzu</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="type_id" class="form-label">Druh lekcie</label>
                                    <select class="form-select" id="type_id" name="type_id">
                                        <option value="">Vyberte druh lekcie</option>
                                        <?php foreach ($lessonTypes as $type): ?>
                                            <option value="<?= $type['id'] ?>"><?= e($type['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="level_id" class="form-label">Úroveň</label>
                                    <select class="form-select" id="level_id" name="level_id">
                                        <option value="">Vyberte úroveň</option>
                                        <?php foreach ($levels as $level): ?>
                                            <option value="<?= $level['id'] ?>"><?= e($level['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="image" class="form-label">Obrázok kurzu</label>
                            <input type="file" class="form-control" id="image" name="image" accept="image/*">
                            <div class="form-text">Podporované formáty: JPG, PNG, GIF, WEBP. Maximálna veľkosť: 5MB.</div>
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="total_lessons" class="form-label">Počet lekcií *</label>
                                    <input type="number" class="form-control" id="total_lessons" name="total_lessons" 
                                           value="8" min="1" max="20" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="lesson_duration_minutes" class="form-label">Trvanie lekcie (min)</label>
                                    <input type="number" class="form-control" id="lesson_duration_minutes" name="lesson_duration_minutes" 
                                           value="90" min="30" max="180">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="capacity" class="form-label">Kapacita</label>
                                    <input type="number" class="form-control" id="capacity" name="capacity" 
                                           value="15" min="1" max="50">
                                </div>
                            </div>
                            <div class="col-md-3">

                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Dátum začiatku *</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="day_of_week" class="form-label">Deň v týždni *</label>
                                    <select class="form-select" id="day_of_week" name="day_of_week" required>
                                        <option value="">Vyberte deň</option>
                                        <option value="1">Pondelok</option>
                                        <option value="2">Utorok</option>
                                        <option value="3">Streda</option>
                                        <option value="4">Štvrtok</option>
                                        <option value="5">Piatok</option>
                                        <option value="6">Sobota</option>
                                        <option value="0">Nedeľa</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="time_start" class="form-label">Začiatok</label>
                                    <input type="time" class="form-control" id="time_start" name="time_start" value="18:00">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="time_end" class="form-label">Koniec</label>
                                    <input type="time" class="form-control" id="time_end" name="time_end" value="19:30">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="price" class="form-label">Cena kurzu (€) *</label>
                                    <input type="number" class="form-control" id="price" name="price" 
                                           value="96.00" step="0.01" min="0" required>
                                    <div class="form-text">Platba bankovým prevodom</div>
                                </div>
                            </div>

                        </div>

                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-1"></i> Automatické generovanie lekcií</h6>
                            <p class="mb-0">Kurz automaticky vytvorí jednotlivé lekcie každý týždeň podľa nastaveného rozvrhu. 
                            Napríklad 8-týždňový kurz každý utorok vytvorí 8 lekcií.</p>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="courses.php" class="btn btn-secondary">Zrušiť</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Vytvoriť kurz
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Set minimum date to today
document.getElementById('start_date').min = new Date().toISOString().split('T')[0];

// Auto-calculate prices based on lesson count
document.getElementById('total_lessons').addEventListener('input', function() {
    const lessons = parseInt(this.value) || 8;
    const pricePerLesson = 12;
    
    document.getElementById('price').value = (lessons * pricePerLesson).toFixed(2);
});

// Auto-set day of week based on start date
document.getElementById('start_date').addEventListener('change', function() {
    const date = new Date(this.value);
    const dayOfWeek = date.getDay();
    document.getElementById('day_of_week').value = dayOfWeek;
});

// Auto-calculate end time based on duration
document.getElementById('time_start').addEventListener('change', updateEndTime);
document.getElementById('lesson_duration_minutes').addEventListener('change', updateEndTime);

function updateEndTime() {
    const startTime = document.getElementById('time_start').value;
    const duration = parseInt(document.getElementById('lesson_duration_minutes').value) || 90;
    
    if (startTime) {
        const [hours, minutes] = startTime.split(':').map(Number);
        const endDate = new Date();
        endDate.setHours(hours, minutes + duration, 0, 0);
        const endTime = endDate.toTimeString().slice(0, 5);
        document.getElementById('time_end').value = endTime;
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>