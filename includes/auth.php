<?php
// Autentifikačné funkcie

/**
 * Spustenie session - už sa spúšťa v config.php
 */
function startSession() {
    // Session sa spúšťa v config.php, len kontrola
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Kontrola či je používateľ prihlásený
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Získanie aktuálneho používateľa
 */
function getCurrentUser($forceRefresh = false) {
    if (!isLoggedIn()) {
        return null;
    }
    
    static $currentUser = null;
    
    if ($currentUser === null || $forceRefresh) {
        try {
            $result = db()->fetch("SELECT *, COALESCE(status, 'active') as status FROM users WHERE id = ?", [$_SESSION['user_id']]);
            
            // Check if user is blocked
            if ($result && $result['status'] === 'blocked') {
                session_destroy();
                $currentUser = false;
            } else {
                $currentUser = $result ? $result : false;
            }
        } catch (Exception $e) {
            error_log("getCurrentUser error: " . $e->getMessage());
            $currentUser = false;
        }
    }
    
    return $currentUser === false ? null : $currentUser;
}

/**
 * Vyčistenie cache používateľských údajov - zavolá sa po zmene kreditu
 */
function refreshCurrentUser() {
    return getCurrentUser(true);
}

/**
 * Kontrola či je používateľ admin
 */
function isAdmin() {
    $user = getCurrentUser();
    return $user && $user['role'] === 'admin';
}

/**
 * Kontrola či je používateľ lektor
 */
function isInstructor() {
    $user = getCurrentUser();
    return $user && $user['role'] === 'lektor';
}

/**
 * Kontrola či je používateľ študent
 */
function isStudent() {
    $user = getCurrentUser();
    return $user && $user['role'] === 'klient';
}

/**
 * Alias pre isStudent() - kompatibilita
 */
function isClient() {
    return isStudent();
}

/**
 * Kontrola či má používateľ konkrétnu rolu
 */
function hasRole($role) {
    $user = getCurrentUser();
    return $user && $user['role'] === $role;
}

/**
 * Vyžadovanie konkrétnej role
 */
function requireRole($role) {
    if (!isLoggedIn()) {
        header('Location: ' . url('pages/login.php'));
        exit;
    }
    
    $user = getCurrentUser();
    if (!$user || $user['role'] !== $role) {
        // If user has wrong role, redirect to appropriate dashboard instead of login
        if ($user) {
            switch ($user['role']) {
                case 'admin':
                    header('Location: ' . url('admin/dashboard.php'));
                    break;
                case 'lektor':
                    header('Location: ' . url('lektor/dashboard.php'));
                    break;
                case 'klient':
                    header('Location: ' . url('pages/index.php'));
                    break;
                default:
                    header('Location: ' . url('pages/login.php'));
            }
        } else {
            header('Location: ' . url('pages/login.php'));
        }
        exit;
    }
}

/**
 * Vyžadovanie prihlásenia
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . url('pages/login.php'));
        exit;
    }
}

/**
 * Prihlásenie používateľa
 */
function loginUser($email, $password) {
    $user = db()->fetch("SELECT *, COALESCE(status, 'active') as status FROM users WHERE email = ?", [$email]);
    
    // Check if user exists and password is correct
    if ($user && password_verify($password, $user['password_hash'])) {
        // Check if user is blocked
        if ($user['status'] === 'blocked') {
            return 'blocked'; // Return special status for blocked users
        }
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];
        
        return true;
    }
    
    return false;
}

/**
 * Odhlásenie používateľa
 */
function logoutUser() {
    session_destroy();
    session_start();
}

/**
 * Registrácia nového používateľa
 */
function registerUser($name, $email, $phone, $password) {
    // Kontrola či email už existuje
    $existing = db()->fetch("SELECT id FROM users WHERE email = ?", [$email]);
    if ($existing) {
        return false;
    }
    
    // Vytvorenie hash hesla
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Vloženie do databázy - všetky verejné registrácie sú klienti
    $userId = db()->query("INSERT INTO users (name, email, phone, password_hash, role, eur_balance, is_public_registration, created_at) VALUES (?, ?, ?, ?, 'klient', 0.0, 1, NOW())", 
        [$name, $email, $phone, $passwordHash]);
    
    if ($userId) {
        // Automatické prihlásenie
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = 'klient';
        $_SESSION['user_name'] = $name;
        
        return true;
    }
    
    return false;
}

/**
 * Validácia hesla
 */
function validatePassword($password) {
    return strlen($password) >= 6;
}

// validateEmail() function moved to functions.php for better validation

/**
 * Flash správy
 */
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Získanie flash správy
 */
function getFlashMessage() {
    $message = $_SESSION['flash_message'] ?? null;
    $type = $_SESSION['flash_type'] ?? 'info';
    
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
    
    return $message ? ['message' => $message, 'type' => $type] : null;
}

/**
 * CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validácia CSRF tokenu
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Kontrola povolení pre zobrazenie obsahu
 */
function canViewContent($contentType) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $user = getCurrentUser();
    
    switch ($contentType) {
        case 'admin':
            return $user['role'] === 'admin';
        case 'lektor':
            return in_array($user['role'], ['admin', 'lektor']);
        case 'klient':
            return in_array($user['role'], ['admin', 'lektor', 'klient']);
        default:
            return false;
    }
}
?>