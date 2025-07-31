<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('lektor');

$currentUser = getCurrentUser();
$instructorId = $currentUser['id'];

$error = '';
$success = '';

// Get or create instructor profile
$profile = db()->fetch("SELECT * FROM instructor_profiles WHERE user_id = ?", [$instructorId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bio = trim($_POST['bio'] ?? '');
    $certifications = trim($_POST['certifications'] ?? '');
    $specializations = trim($_POST['specializations'] ?? '');
    $experienceYears = (int)($_POST['experience_years'] ?? 0);
    $hourlyRate = (float)($_POST['hourly_rate'] ?? 0);
    
    // Update user info
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    if (empty($name) || empty($email) || empty($phone)) {
        $error = 'Prosím vyplňte všetky základné údaje.';
    } else {
        try {
            // Update user table
            db()->query("
                UPDATE users 
                SET name = ?, email = ?, phone = ? 
                WHERE id = ?
            ", [$name, $email, $phone, $instructorId]);
            
            // Update or insert instructor profile
            if ($profile) {
                db()->query("
                    UPDATE instructor_profiles 
                    SET bio = ?, certifications = ?, specializations = ?, 
                        experience_years = ?, hourly_rate = ?, updated_at = NOW()
                    WHERE user_id = ?
                ", [$bio, $certifications, $specializations, $experienceYears, $hourlyRate, $instructorId]);
            } else {
                db()->query("
                    INSERT INTO instructor_profiles 
                    (user_id, bio, certifications, specializations, experience_years, hourly_rate, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ", [$instructorId, $bio, $certifications, $specializations, $experienceYears, $hourlyRate]);
            }
            
            $success = 'Profil bol úspešne aktualizovaný.';
            
            // Refresh data
            $currentUser = getCurrentUser();
            $profile = db()->fetch("SELECT * FROM instructor_profiles WHERE user_id = ?", [$instructorId]);
            
        } catch (Exception $e) {
            $error = 'Chyba pri aktualizácii profilu: ' . $e->getMessage();
        }
    }
}

// Get instructor statistics
$stats = db()->fetch("
    SELECT 
        COUNT(DISTINCT yc.id) as total_classes,
        COUNT(r.id) as total_clients,
        SUM(CASE WHEN r.paid_with_credit THEN yc.price_with_credit ELSE yc.price END) as total_revenue,
        AVG(yc.capacity) as avg_capacity
    FROM yoga_classes yc
    LEFT JOIN registrations r ON yc.id = r.class_id AND r.status = 'confirmed'
    WHERE yc.instructor_id = ?
", [$instructorId]);

$pageTitle = 'Môj profil';
$currentPage = 'lektor_profile';
require_once __DIR__ . '/../includes/header.php';
?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title"><?= $pageTitle ?></h1>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Dashboard
            </a>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-user-edit"></i> Upraviť profil</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="needs-validation" novalidate>
                            <h6 class="mb-3 text-muted">Základné údaje</h6>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Meno a priezvisko *</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?= htmlspecialchars($currentUser['name']) ?>" required>
                                        <div class="invalid-feedback">Prosím zadajte meno.</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?= htmlspecialchars($currentUser['email']) ?>" required>
                                        <div class="invalid-feedback">Prosím zadajte platný email.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label">Telefón *</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?= htmlspecialchars($currentUser['phone']) ?>" required>
                                <div class="invalid-feedback">Prosím zadajte telefón.</div>
                            </div>

                            <hr>
                            
                            <h6 class="mb-3 text-muted">Profesionálne informácie</h6>

                            <div class="mb-3">
                                <label for="bio" class="form-label">Biografia</label>
                                <textarea class="form-control" id="bio" name="bio" rows="4"><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
                                <div class="form-text">Opíšte svoju jogovú cestu a filozofiu</div>
                            </div>

                            <div class="mb-3">
                                <label for="certifications" class="form-label">Certifikácie</label>
                                <textarea class="form-control" id="certifications" name="certifications" rows="3"><?= htmlspecialchars($profile['certifications'] ?? '') ?></textarea>
                                <div class="form-text">Zoznam vašich certifikácií a kvalifikácií</div>
                            </div>

                            <div class="mb-3">
                                <label for="specializations" class="form-label">Špecializácie</label>
                                <textarea class="form-control" id="specializations" name="specializations" rows="2"><?= htmlspecialchars($profile['specializations'] ?? '') ?></textarea>
                                <div class="form-text">Typy jogy v ktorých sa špecializujete</div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="experience_years" class="form-label">Roky skúseností</label>
                                        <input type="number" class="form-control" id="experience_years" name="experience_years" 
                                               value="<?= htmlspecialchars($profile['experience_years'] ?? '') ?>" min="0" max="50">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="hourly_rate" class="form-label">Hodinová sadzba (€)</label>
                                        <input type="number" class="form-control" id="hourly_rate" name="hourly_rate" 
                                               value="<?= htmlspecialchars($profile['hourly_rate'] ?? '') ?>" step="0.01" min="0">
                                        <div class="form-text">Pre súkromné lekcie</div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Uložiť zmeny
                                </button>
                                <a href="index.php" class="btn btn-secondary">Zrušiť</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Statistics Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6><i class="fas fa-chart-bar"></i> Moje štatistiky</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <h4 class="text-primary"><?= $stats['total_classes'] ?: 0 ?></h4>
                                <p class="small mb-0">Celkové lekcie</p>
                            </div>
                            <div class="col-6 mb-3">
                                <h4 class="text-success"><?= $stats['total_clients'] ?: 0 ?></h4>
                                <p class="small mb-0">Klienti celkom</p>
                            </div>
                            <div class="col-6 mb-3">
                                <h4 class="text-info"><?= formatPrice($stats['total_revenue'] ?: 0) ?></h4>
                                <p class="small mb-0">Celkový príjem</p>
                            </div>
                            <div class="col-6 mb-3">
                                <h4 class="text-warning"><?= round($stats['avg_capacity'] ?: 0) ?></h4>
                                <p class="small mb-0">Priem. kapacita</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Tips -->
                <div class="card">
                    <div class="card-header">
                        <h6><i class="fas fa-lightbulb"></i> Tipy pre profil</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled small">
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <strong>Biografia:</strong> Buďte autentickí a osobní
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <strong>Certifikácie:</strong> Uveďte relevantné kvalifikácie
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <strong>Špecializácie:</strong> Zdôraznite svoje silné stránky
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <strong>Kontakt:</strong> Udržujte aktuálne údaje
                            </li>
                        </ul>
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
    </script>
</body>
</html>