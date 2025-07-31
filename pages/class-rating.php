<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('klient');

$currentUser = getCurrentUser();
$userId = $currentUser['id'];
$classId = (int)($_GET['class_id'] ?? 0);

if (!$classId) {
    header('Location: my-classes.php?error=' . urlencode('Neplatná lekcia'));
    exit;
}

// Check if user attended this class
$attendance = db()->fetch("
    SELECT cr.*, yc.name, yc.date, yc.time_start, yc.instructor
    FROM registrations cr
    JOIN yoga_classes yc ON cr.class_id = yc.id
    WHERE cr.user_id = ? AND cr.class_id = ? AND cr.status = 'confirmed' AND cr.attendance_marked = 1
", [$userId, $classId]);

if (!$attendance) {
    header('Location: my-classes.php?error=' . urlencode('Nemôžete hodnotiť lekciu, ktorú ste neabsolvovali'));
    exit;
}

// Check if already rated
$existingRating = db()->fetch("
    SELECT * FROM class_ratings 
    WHERE user_id = ? AND class_id = ?
", [$userId, $classId]);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    
    if ($rating < 1 || $rating > 5) {
        $error = 'Prosím vyberte hodnotenie od 1 do 5 hviezd.';
    } else {
        try {
            if ($existingRating) {
                // Update existing rating
                db()->query("
                    UPDATE class_ratings 
                    SET rating = ?, comment = ?, updated_at = NOW()
                    WHERE user_id = ? AND class_id = ?
                ", [$rating, $comment, $userId, $classId]);
                $success = 'Hodnotenie bolo úspešne aktualizované.';
            } else {
                // Insert new rating
                db()->query("
                    INSERT INTO class_ratings (user_id, class_id, rating, comment, created_at, updated_at)
                    VALUES (?, ?, ?, ?, NOW(), NOW())
                ", [$userId, $classId, $rating, $comment]);
                $success = 'Hodnotenie bolo úspešne pridané. Ďakujeme za váš názor!';
            }
            
            // Refresh existing rating data
            $existingRating = db()->fetch("
                SELECT * FROM class_ratings 
                WHERE user_id = ? AND class_id = ?
            ", [$userId, $classId]);
            
        } catch (Exception $e) {
            $error = 'Chyba pri ukladaní hodnotenia: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Hodnotiť lekciu - ' . $attendance['name'];
$currentPage = 'class_rating';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="page-title">⭐ Hodnotiť lekciu</h1>
                <a href="my-classes.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Späť na moje lekcie
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <!-- Class Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle me-2"></i>Detaily lekcie</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Názov:</strong> <?= htmlspecialchars($attendance['name']) ?></p>
                            <p><strong>Dátum:</strong> <?= formatDate($attendance['date']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Čas:</strong> <?= formatTime($attendance['time_start']) ?></p>
                            <p><strong>Lektor:</strong> <?= htmlspecialchars($attendance['instructor']) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rating Form -->
            <div class="card">
                <div class="card-header">
                    <h5>
                        <i class="fas fa-star me-2"></i>
                        <?= $existingRating ? 'Upraviť hodnotenie' : 'Pridať hodnotenie' ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <!-- Star Rating -->
                        <div class="mb-4">
                            <label class="form-label"><strong>Celkové hodnotenie *</strong></label>
                            <div class="star-rating mb-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <input type="radio" name="rating" value="<?= $i ?>" id="star<?= $i ?>" 
                                           <?= ($existingRating && $existingRating['rating'] == $i) ? 'checked' : '' ?> required>
                                    <label for="star<?= $i ?>" class="star">
                                        <i class="fas fa-star"></i>
                                    </label>
                                <?php endfor; ?>
                            </div>
                            <div class="form-text">
                                <span id="rating-text">
                                    <?php if ($existingRating): ?>
                                        <?= getRatingText($existingRating['rating']) ?>
                                    <?php else: ?>
                                        Kliknite na hviezdy pre hodnotenie
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <!-- Comment -->
                        <div class="mb-4">
                            <label for="comment" class="form-label"><strong>Váš komentár</strong></label>
                            <textarea class="form-control" id="comment" name="comment" rows="4" 
                                      placeholder="Napíšte nám, čo sa vám páčilo alebo čo by sme mohli zlepšiť..."><?= $existingRating ? htmlspecialchars($existingRating['comment']) : '' ?></textarea>
                            <div class="form-text">Komentár je nepovinný, ale pomáha nám zlepšovať naše služby.</div>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="my-classes.php" class="btn btn-secondary me-md-2">Zrušiť</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>
                                <?= $existingRating ? 'Aktualizovať hodnotenie' : 'Pridať hodnotenie' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($existingRating): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h6><i class="fas fa-clock me-2"></i>História hodnotenia</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-1">
                            <strong>Pridané:</strong> <?= formatDateTime($existingRating['created_at']) ?>
                        </p>
                        <?php if ($existingRating['updated_at'] != $existingRating['created_at']): ?>
                            <p class="mb-0">
                                <strong>Naposledy upravené:</strong> <?= formatDateTime($existingRating['updated_at']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- CSS moved to laskavypriestor.css -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ratingInputs = document.querySelectorAll('input[name="rating"]');
    const ratingText = document.getElementById('rating-text');
    
    const ratingTexts = {
        1: '⭐ Slabé - potrebuje zlepšenie',
        2: '⭐⭐ Podpriemerné - mohlo by byť lepšie', 
        3: '⭐⭐⭐ Priemerne - v poriadku',
        4: '⭐⭐⭐⭐ Veľmi dobré - páčilo sa mi',
        5: '⭐⭐⭐⭐⭐ Výborné - perfektná lekcia!'
    };
    
    ratingInputs.forEach(input => {
        input.addEventListener('change', function() {
            if (this.checked) {
                ratingText.textContent = ratingTexts[this.value];
            }
        });
    });
});
</script>

<?php 
function getRatingText($rating) {
    $texts = [
        1 => '⭐ Slabé - potrebuje zlepšenie',
        2 => '⭐⭐ Podpriemerné - mohlo by byť lepšie', 
        3 => '⭐⭐⭐ Priemerne - v poriadku',
        4 => '⭐⭐⭐⭐ Veľmi dobré - páčilo sa mi',
        5 => '⭐⭐⭐⭐⭐ Výborné - perfektná lekcia!'
    ];
    return $texts[$rating] ?? '';
}

require_once __DIR__ . '/../includes/footer.php'; 
?>