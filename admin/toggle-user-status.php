<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Require admin authentication
requireRole('admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['user_id']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$userId = (int)$input['user_id'];
$status = $input['status'];

// Validate status
if (!in_array($status, ['active', 'blocked'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// Prevent admin from blocking themselves
if ($userId == $_SESSION['user_id']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nemôžete zmeniť vlastný status']);
    exit;
}

try {
    // Check if user exists
    $user = db()->fetch("SELECT id, name, role FROM users WHERE id = ?", [$userId]);
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Používateľ nebol nájdený']);
        exit;
    }

    // Check if status column exists, if not create it
    $columns = db()->fetchAll("SHOW COLUMNS FROM users LIKE 'status'");
    if (empty($columns)) {
        db()->query("ALTER TABLE users ADD COLUMN status ENUM('active', 'blocked') DEFAULT 'active'");
    }

    // Update user status
    $result = db()->query("UPDATE users SET status = ? WHERE id = ?", [$status, $userId]);
    
    if ($result) {
        // Log the action
        $actionText = $status === 'blocked' ? 'zablokovaný' : 'odblokovaný';
        error_log("Admin {$_SESSION['user_name']} ({$_SESSION['user_id']}) {$actionText} používateľa {$user['name']} ({$userId})");
        
        echo json_encode([
            'success' => true, 
            'message' => "Status používateľa bol úspešne zmenený na {$status}"
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Nepodarilo sa zmeniť status používateľa']);
    }

} catch (Exception $e) {
    error_log("Error toggling user status: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Došlo k chybe pri zmene statusu používateľa']);
}
?>