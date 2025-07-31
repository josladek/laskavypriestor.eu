<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in
requireLogin();

$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        // Validation
        if (empty($name) || empty($email) || empty($phone)) {
            $error = 'Všetky polia musia byť vyplnené.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Neplatná emailová adresa.';
        } else {
            // Check if email is unique (excluding current user)
            $existingUser = db()->fetch("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $user['id']]);
            if ($existingUser) {
                $error = 'Táto emailová adresa je už používaná iným používateľom.';
            } else {
                try {
                    // Update user profile
                    db()->query("
                        UPDATE users 
                        SET name = ?, email = ?, phone = ?
                        WHERE id = ?
                    ", [$name, $email, $phone, $user['id']]);
                    
                    // Update session data
                    $_SESSION['user_name'] = $name;
                    
                    $success = 'Profil bol úspešne aktualizovaný.';
                    
                    // Refresh user data
                    $user = getCurrentUser();
                    
                } catch (Exception $e) {
                    $error = 'Chyba pri aktualizácii profilu: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Všetky polia pre zmenu hesla musia byť vyplnené.';
        } elseif (strlen($new_password) < 6) {
            $error = 'Nové heslo musí mať minimálne 6 znakov.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Nové heslá sa nezhodujú.';
        } elseif (!password_verify($current_password, $user['password_hash'])) {
            $error = 'Súčasné heslo nie je správne.';
        } else {
            try {
                // Update password
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                db()->query("
                    UPDATE users 
                    SET password_hash = ?
                    WHERE id = ?
                ", [$new_password_hash, $user['id']]);
                
                $success = 'Heslo bolo úspešne zmenené.';
                
            } catch (Exception $e) {
                $error = 'Chyba pri zmene hesla: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Môj profil';
$currentPage = 'profile';
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container my-5">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4 text-charcoal">Môj profil</h1>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success" role="alert">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Profile Information -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Základné údaje</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Meno a priezvisko</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?= e($user['name']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= e($user['email']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Telefón</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?= e($user['phone']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Rola</label>
                            <input type="text" class="form-control" value="<?= ucfirst($user['role']) ?>" readonly>
                            <div class="form-text">Rola sa nedá zmeniť</div>
                        </div>
                        
                        <?php if ($user['role'] === 'klient'): ?>
                        <div class="mb-3">
                            <label class="form-label">Kredit</label>
                            <input type="text" class="form-control" 
                                   value="<?= number_format($user['eur_balance'], 2) ?> €" readonly>
                            <div class="form-text">Váš aktuálny zostatok kreditu</div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Registrovaný</label>
                            <input type="text" class="form-control" 
                                   value="<?= date('d.m.Y H:i', strtotime($user['created_at'])) ?>" readonly>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Uložiť zmeny</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Change Password -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Zmena hesla</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Súčasné heslo</label>
                            <input type="password" class="form-control" id="current_password" 
                                   name="current_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Nové heslo</label>
                            <input type="password" class="form-control" id="new_password" 
                                   name="new_password" required minlength="6">
                            <div class="form-text">Minimálne 6 znakov</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Potvrdiť nové heslo</label>
                            <input type="password" class="form-control" id="confirm_password" 
                                   name="confirm_password" required minlength="6">
                        </div>
                        
                        <button type="submit" class="btn btn-warning">Zmeniť heslo</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Info for Lecturers -->
    <?php if ($user['role'] === 'lektor'): ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Profil lektora</h5>
                </div>
                <div class="card-body">
                    <?php if ($user['lecturer_photo']): ?>
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <img src="<?= url('uploads/lecturers/' . $user['lecturer_photo']) ?>" 
                                 alt="<?= e($user['name']) ?>" 
                                 class="img-fluid rounded">
                        </div>
                        <div class="col-md-9">
                            <h6>Popis lektora:</h6>
                            <div class="lecturer-description">
                                <?= $user['lecturer_description'] ?: '<em>Žiadny popis</em>' ?>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="text-muted">
                        <p>Váš profil lektora zatiaľ nie je kompletne vyplnený.</p>
                        <p>Pre úpravu profilu kontaktujte administrátora.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Password confirmation validation
document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    function validatePassword() {
        if (newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Heslá sa nezhodujú');
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
    
    newPassword.addEventListener('input', validatePassword);
    confirmPassword.addEventListener('input', validatePassword);
});
</script>

<!-- CSS moved to laskavypriestor.css -->

<?php include __DIR__ . '/../includes/footer.php'; ?>