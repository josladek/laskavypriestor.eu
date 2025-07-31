<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Session is already started in auth.php

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $response['message'] = 'Prosím vyplňte všetky polia';
    } else {
        try {
            // Get user from database
            $user = db()->fetch("SELECT *, COALESCE(status, 'active') as status FROM users WHERE email = ?", [$email]);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Check if user is disabled
                if ($user['status'] === 'disabled') {
                    $response['message'] = 'Váš účet bol deaktivovaný. Kontaktujte administrátora.';
                } else {
                    // Login successful
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_role'] = $user['role'];
                    
                    $response['success'] = true;
                    $response['message'] = 'Prihlásenie úspešné';
                    
                    // Redirect based on role
                    if ($user['role'] === 'admin') {
                        $response['redirect'] = url('admin/dashboard.php');
                    } elseif ($user['role'] === 'lektor') {
                        $response['redirect'] = url('lektor/index.php');
                    } else {
                        $response['redirect'] = url('index.php');
                    }
                }
            } else {
                $response['message'] = 'Nesprávny email alebo heslo';
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $response['message'] = 'Chyba pri prihlasovaní. Skúste to znovu.';
        }
    }
}

// Return JSON response or redirect
if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
    header('Content-Type: application/json');
    echo json_encode($response);
} else {
    if ($response['success']) {
        header('Location: ' . $response['redirect']);
    } else {
        header('Location: ' . url('pages/login.php?error=' . urlencode($response['message'])));
    }
    exit;
}
?>