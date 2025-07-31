<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect();
}

$error = '';

// Check for error messages from URL parameters
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Prosím vyplňte všetky polia.';
    } elseif (!validateEmail($email)) {
        $error = 'Neplatná emailová adresa.';
    } else {
        try {
            // Get user from database
            $user = db()->fetch("SELECT *, COALESCE(status, 'active') as status FROM users WHERE email = ?", [$email]);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Check if user is disabled
                if ($user['status'] === 'disabled') {
                    $error = 'Váš účet bol zneplatnený. Kontaktujte administrátora.';
                } elseif ($user['status'] === 'pending' && !$user['email_verified']) {
                    $error = 'Váš účet nie je aktivovaný. Skontrolujte svoj email a kliknite na overovací odkaz. <a href="' . url('pages/resend-verification.php') . '" class="text-decoration-none fw-bold">Znovu odoslať overovací email</a>';
                } else {
                    // Login successful - set session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_role'] = $user['role'];
                    
                    // Redirect based on role
                    if ($user['role'] === 'admin') {
                        header('Location: ' . url('admin/dashboard.php'));
                    } elseif ($user['role'] === 'lektor') {
                        header('Location: ' . url('lektor/index.php'));
                    } else {
                        header('Location: ' . url('index.php'));
                    }
                    exit;
                }
            } else {
                $error = 'Nesprávny email alebo heslo.';
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'Chyba pri prihlasovaní. Skúste to znovu.';
        }
    }
}

$currentPage = 'login';
$pageTitle = 'Prihlásenie';
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-header text-center">
                    <h2 class="mb-0 fw-bold">Prihlásenie</h2>
                </div>
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <svg width="60" height="60" viewBox="0 0 100 100" class="mb-3">
                            <defs>
                                <linearGradient id="login-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" style="stop-color:var(--sage);stop-opacity:0.8" />
                                    <stop offset="100%" style="stop-color:var(--sage-dark);stop-opacity:1" />
                                </linearGradient>
                            </defs>
                            <path d="M50 20 C40 30, 40 40, 50 45 C60 40, 60 30, 50 20" fill="url(#login-gradient)" opacity="0.9"/>
                            <path d="M65 35 C55 25, 45 25, 50 35 C55 45, 65 45, 65 35" fill="url(#login-gradient)" opacity="0.8"/>
                            <path d="M50 80 C60 70, 60 60, 50 55 C40 60, 40 70, 50 80" fill="url(#login-gradient)" opacity="0.9"/>
                            <path d="M35 35 C45 25, 55 25, 50 35 C45 45, 35 45, 35 35" fill="url(#login-gradient)" opacity="0.8"/>
                            <circle cx="50" cy="50" r="8" fill="var(--sage)"/>
                        </svg>
                        <p class="text-muted">Prihláste sa do svojho účtu</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="email" class="form-label">Emailová adresa</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope text-sage"></i>
                                </span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= e($_POST['email'] ?? '') ?>" placeholder="vasa@email.sk" required>
                            </div>
                            <div class="invalid-feedback">
                                Prosím zadajte platnú emailovú adresu.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Heslo</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock text-sage"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Vaše heslo" required>
                            </div>
                            <div class="invalid-feedback">
                                Prosím zadajte heslo.
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary py-2">
                                <i class="fas fa-sign-in-alt me-2"></i> Prihlásiť sa
                            </button>
                        </div>
                    </form>
                    
                    <div class="mt-4 text-center">
                        <p class="mb-0">Nemáte účet? <a href="register.php" class="text-sage text-decoration-none fw-bold">Registrujte sa</a></p>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <p class="small text-muted">
                    <i class="fas fa-shield-alt me-1"></i> Vaše údaje sú chránené SSL šifrovaním
                </p>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>