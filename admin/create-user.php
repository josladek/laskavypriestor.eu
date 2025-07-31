<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$message = '';
$error = '';

// Handle form submission
if ($_POST) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'klient';
    $eur_balance = (float)($_POST['eur_balance'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    
    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Meno, email a heslo sú povinné polia.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Neplatný email formát.';
    } elseif (strlen($password) < 6) {
        $error = 'Heslo musí mať aspoň 6 znakov.';
    } else {
        try {
            // Check if email already exists
            $existingUser = db()->fetch("SELECT id FROM users WHERE email = ?", [$email]);
            if ($existingUser) {
                $error = 'Používateľ s týmto emailom už existuje.';
            } else {
                // Hash password
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user
                $sql = "INSERT INTO users (name, email, phone, password_hash, role, eur_balance, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW())";
                
                $result = db()->query($sql, [
                    $name,
                    $email,
                    $phone,
                    $passwordHash,
                    $role,
                    $eur_balance
                ]);
                
                if ($result) {
                    $userId = db()->lastInsertId();
                    
                    // If role is lektor and description provided, handle additional data
                    if ($role === 'lektor' && !empty($description)) {
                        // Check if instructor_profiles table exists and add description
                        try {
                            $tables = db()->fetchAll("SHOW TABLES LIKE 'instructor_profiles'");
                            if (!empty($tables)) {
                                db()->query("INSERT INTO instructor_profiles (user_id, description, created_at) VALUES (?, ?, NOW())", 
                                          [$userId, $description]);
                            } else {
                                // Add description to users table if instructor_profiles doesn't exist
                                $columns = db()->fetchAll("SHOW COLUMNS FROM users LIKE 'description'");
                                if (empty($columns)) {
                                    db()->query("ALTER TABLE users ADD COLUMN description TEXT");
                                }
                                db()->query("UPDATE users SET description = ? WHERE id = ?", [$description, $userId]);
                            }
                        } catch (Exception $e) {
                            // Ignore description error, user was created successfully
                            error_log("Description save error: " . $e->getMessage());
                        }
                    }
                    
                    $message = "Používateľ bol úspešne vytvorený.";
                    
                    // Clear form data on success
                    $name = $email = $phone = $description = '';
                    $eur_balance = 0;
                    $role = 'klient';
                } else {
                    $error = 'Chyba pri vytváraní používateľa.';
                }
            }
        } catch (Exception $e) {
            $error = 'Chyba databázy: ' . $e->getMessage();
        }
    }
}

$currentPage = 'admin_users';
$pageTitle = 'Vytvoriť používateľa';
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container-fluid my-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-charcoal">Vytvoriť nového používateľa</h1>
                    <p class="text-muted">Pridajte nového používateľa do systému</p>
                </div>
                <a href="users.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Späť na používateľov
                </a>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= h($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= h($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <!-- Základné informácie -->
                            <div class="col-md-6">
                                <h5 class="card-title mb-3">
                                    <i class="fas fa-user text-primary me-2"></i>
                                    Základné informácie
                                </h5>
                                
                                <div class="mb-3">
                                    <label for="name" class="form-label">Meno *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?= h($name ?? '') ?>" required>
                                    <div class="invalid-feedback">
                                        Meno je povinné pole.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= h($email ?? '') ?>" required>
                                    <div class="invalid-feedback">
                                        Zadajte platný email.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="phone" class="form-label">Telefón</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?= h($phone ?? '') ?>">
                                    <small class="form-text text-muted">Formát: +421 9xx xxx xxx</small>
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label">Heslo *</label>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           minlength="6" required>
                                    <div class="invalid-feedback">
                                        Heslo musí mať aspoň 6 znakov.
                                    </div>
                                </div>
                            </div>

                            <!-- Role a nastavenia -->
                            <div class="col-md-6">
                                <h5 class="card-title mb-3">
                                    <i class="fas fa-cogs text-warning me-2"></i>
                                    Role a nastavenia
                                </h5>

                                <div class="mb-3">
                                    <label for="role" class="form-label">Rola používateľa *</label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="">Vyberte rolu...</option>
                                        <option value="klient" <?= ($role ?? '') === 'klient' ? 'selected' : '' ?>>
                                            Klient
                                        </option>
                                        <option value="lektor" <?= ($role ?? '') === 'lektor' ? 'selected' : '' ?>>
                                            Lektor
                                        </option>
                                        <option value="admin" <?= ($role ?? '') === 'admin' ? 'selected' : '' ?>>
                                            Administrátor
                                        </option>
                                    </select>
                                    <div class="invalid-feedback">
                                        Vyberte rolu používateľa.
                                    </div>
                                </div>

                                <!-- Kredit pre klientov -->
                                <div class="mb-3" id="credit-field">
                                    <label for="eur_balance" class="form-label">Počiatočný kredit (€)</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="eur_balance" name="eur_balance" 
                                               value="<?= h($eur_balance ?? 0) ?>" min="0" step="0.01">
                                        <span class="input-group-text">€</span>
                                    </div>
                                    <small class="form-text text-muted">
                                        Počiatočný kredit pre nového klienta
                                    </small>
                                </div>

                                <!-- Popis pre lektorov -->
                                <div class="mb-3" id="description-field" style="display: none;">
                                    <label for="description" class="form-label">Popis lektora</label>
                                    <textarea class="form-control" id="description" name="description" rows="4"
                                              placeholder="Krátky popis lektora, jeho skúsenosti a špecializácia..."><?= h($description ?? '') ?></textarea>
                                    <small class="form-text text-muted">
                                        Popis sa zobrazí v profile lektora
                                    </small>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between">
                            <a href="users.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Zrušiť
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-user-plus me-1"></i> Vytvoriť používateľa
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Show/hide fields based on role selection
document.getElementById('role').addEventListener('change', function() {
    const role = this.value;
    const creditField = document.getElementById('credit-field');
    const descriptionField = document.getElementById('description-field');
    
    if (role === 'klient') {
        creditField.style.display = 'block';
        descriptionField.style.display = 'none';
    } else if (role === 'lektor') {
        creditField.style.display = 'none';
        descriptionField.style.display = 'block';
    } else {
        creditField.style.display = 'none';
        descriptionField.style.display = 'none';
    }
});

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

// Initialize role-based fields on page load
document.addEventListener('DOMContentLoaded', function() {
    const role = document.getElementById('role').value;
    if (role) {
        document.getElementById('role').dispatchEvent(new Event('change'));
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>