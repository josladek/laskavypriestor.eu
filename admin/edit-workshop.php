<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$currentUser = getCurrentUser();
$workshopId = (int)($_GET['id'] ?? 0);

if (!$workshopId) {
    header('Location: workshops.php');
    exit;
}

// Get workshop details
$workshop = db()->fetch("SELECT * FROM workshops WHERE id = ?", [$workshopId]);
if (!$workshop) {
    header('Location: workshops.php');
    exit;
}

// Get instructors
$instructors = db()->fetchAll("SELECT id, name FROM users WHERE role = 'lektor' ORDER BY name");

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $instructor_id = !empty($_POST['instructor_id']) ? (int)$_POST['instructor_id'] : null;
    $instructor_name = trim($_POST['instructor_name'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $timeStart = trim($_POST['time_start'] ?? '');
    $timeEnd = trim($_POST['time_end'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $is_free = isset($_POST['is_free']) && $_POST['is_free'] === '1';

    $notes = trim($_POST['notes'] ?? '');
    
    // Handle image upload
    $imageUrl = $workshop['image_url']; // Keep existing image by default
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/workshops/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = time() . '_' . $_FILES['image']['name'];
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
            // Delete old image if exists
            if ($workshop['image_url'] && file_exists($uploadDir . $workshop['image_url'])) {
                unlink($uploadDir . $workshop['image_url']);
            }
            $imageUrl = $fileName;
        }
    }
    
    // Validation
    if (empty($title) || empty($description) || empty($date) || 
        empty($timeStart) || empty($timeEnd) || $capacity <= 0) {
        $error = 'Prosím vyplňte všetky povinné polia.';
    } elseif ($instructor_id === null) {
        $error = 'Lektor zo systému je povinný - potrebný pre evidenciu dochádzky workshopu.';
    } elseif (!$is_free && $price <= 0) {
        $error = 'Pre platený workshop musíte zadať cenu vyššiu ako 0.';
    } elseif (strtotime($timeEnd) <= strtotime($timeStart)) {
        $error = 'Čas ukončenia musí byť neskôr ako čas začiatku.';
    } else {
        try {
            // Set price to 0 if free
            if ($is_free) {
                $price = 0;
            }
            
            // Calculate duration
            $duration = (strtotime($timeEnd) - strtotime($timeStart)) / 3600;
            
            // Update workshop
            $result = db()->query("
                UPDATE workshops SET 
                    title = ?, description = ?, instructor_id = ?, custom_instructor_name = ?, 
                    date = ?, time_start = ?, time_end = ?, duration_hours = ?, capacity = ?, 
                    price = ?, is_free = ?, notes = ?, image_url = ?, updated_at = NOW()
                WHERE id = ?
            ", [
                $title, $description, $instructor_id, $instructor_name,
                $date, $timeStart, $timeEnd, $duration, $capacity, $price, ($is_free ? 1 : 0),
                $notes, $imageUrl, $workshopId
            ]);
            
            if ($result !== false) {
                $success = 'Workshop bol úspešne aktualizovaný.';
                // Refresh workshop data
                $workshop = db()->fetch("SELECT * FROM workshops WHERE id = ?", [$workshopId]);
            } else {
                $error = 'Chyba pri aktualizácii workshopu.';
            }
        } catch (Exception $e) {
            $error = 'Chyba pri aktualizácii workshopu: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Upraviť workshop';
include __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Upraviť workshop: <?= h($workshop['title']) ?></h3>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= h($error) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= h($success) ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="title" class="form-label">Názov workshopu *</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?= h($_POST['title'] ?? $workshop['title']) ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="image" class="form-label">Obrázok workshopu</label>
                                <?php if ($workshop['image_url']): ?>
                                    <div class="mb-2">
                                        <img src="<?= url('uploads/workshops/' . $workshop['image_url']) ?>" 
                                             alt="Aktuálny obrázok" class="img-thumbnail" style="max-height: 80px;">
                                        <div class="form-text">Aktuálny obrázok</div>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                <div class="form-text">Voliteľný obrázok pre workshop (JPG, PNG)</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Popis workshopu * 
                                <small class="text-muted">(podporuje HTML formátovanie)</small>
                            </label>
                            <textarea class="form-control" id="description" name="description" rows="6" required><?= $_POST['description'] ?? $workshop['description'] ?></textarea>
                            <div class="form-text">Môžete použiť HTML tagy pre formátovanie: &lt;strong&gt;, &lt;em&gt;, &lt;br&gt;, &lt;p&gt;, &lt;ul&gt;, &lt;li&gt;</div>
                        </div>
                        
                        <!-- Lektor section -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0">Vedenie workshopu</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="instructor_id" class="form-label">Lektor zo systému *</label>
                                        <select class="form-select" id="instructor_id" name="instructor_id" required>
                                            <option value="">-- Vyberte lektora --</option>
                                            <?php foreach ($instructors as $instructor): ?>
                                                <option value="<?= $instructor['id'] ?>" 
                                                        <?= (($_POST['instructor_id'] ?? $workshop['instructor_id']) == $instructor['id']) ? 'selected' : '' ?>>
                                                    <?= h($instructor['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text text-danger">Povinné - zodpovedný za evidenciu dochádzky</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="instructor_name" class="form-label">Alebo meno vedúceho (voliteľné)</label>
                                        <input type="text" class="form-control" id="instructor_name" name="instructor_name" 
                                               value="<?= h($_POST['instructor_name'] ?? $workshop['custom_instructor_name']) ?>" 
                                               placeholder="Meno zobrazené klientom">
                                        <div class="form-text">Ak je zadané, toto meno sa zobrazí klientom namiesto mena lektora zo systému</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="date" class="form-label">Dátum *</label>
                                <input type="date" class="form-control" id="date" name="date" 
                                       value="<?= h($_POST['date'] ?? $workshop['date']) ?>" required>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="time_start" class="form-label">Čas začiatku *</label>
                                <input type="time" class="form-control" id="time_start" name="time_start" 
                                       value="<?= h($_POST['time_start'] ?? $workshop['time_start']) ?>" required>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="time_end" class="form-label">Čas ukončenia *</label>
                                <input type="time" class="form-control" id="time_end" name="time_end" 
                                       value="<?= h($_POST['time_end'] ?? $workshop['time_end']) ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="capacity" class="form-label">Kapacita *</label>
                                <input type="number" class="form-control" id="capacity" name="capacity" 
                                       value="<?= h($_POST['capacity'] ?? $workshop['capacity']) ?>" min="1" required>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="is_free" name="is_free" value="1" 
                                           <?= (isset($_POST['is_free']) ? ($_POST['is_free'] === '1') : $workshop['is_free']) ? 'checked' : '' ?>
                                           onchange="togglePrice()">
                                    <label class="form-check-label" for="is_free">
                                        Bezplatný workshop
                                    </label>
                                </div>
                                <label for="price" class="form-label">Cena (€)</label>
                                <input type="number" class="form-control" id="price" name="price" 
                                       value="<?= h($_POST['price'] ?? $workshop['price']) ?>" step="0.01" min="0">
                            </div>
                            

                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Poznámky</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?= h($_POST['notes'] ?? $workshop['notes']) ?></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="workshops.php" class="btn btn-secondary">Späť na zoznam</a>
                            <button type="submit" class="btn btn-primary">Aktualizovať workshop</button>
                        </div>
                    </form>
                    
                    <script>
                    function togglePrice() {
                        const isFree = document.getElementById('is_free');
                        const priceField = document.getElementById('price');
                        
                        if (isFree.checked) {
                            priceField.value = '0';
                            priceField.disabled = true;
                        } else {
                            priceField.disabled = false;
                            if (priceField.value === '0') {
                                priceField.value = '';
                            }
                        }
                    }
                    
                    // Initialize on page load
                    document.addEventListener('DOMContentLoaded', function() {
                        togglePrice();
                    });
                    </script>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>