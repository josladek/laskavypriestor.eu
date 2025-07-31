<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Require login
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Musíte sa prihlásiť.']);
    exit;
}

$user = getCurrentUser();
$classId = $_GET['id'] ?? $_POST['class_id'] ?? null;
$useCredit = ($_POST['use_credit'] ?? 'true') === 'true';

if (!$classId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Neplatné ID lekcie.']);
    exit;
}

try {
    // Auto-close expired classes first
    autoCloseClasses();
    
    // Check if class is closed for registration (after end time)
    if (isClassClosed($classId)) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Registrácia na túto lekciu je už uzavretá. Môžete sa registrovať iba do času skončenia lekcie.'
        ]);
        exit;
    }
    
    // Get class details - allow registration until end of class day
    $class = db()->fetch("
        SELECT yc.*, u.name as lektor_name 
        FROM yoga_classes yc 
        LEFT JOIN users u ON yc.instructor_id = u.id 
        WHERE yc.id = ? AND yc.status = 'active'
    ", [$classId]);
    
    if (!$class) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Lekcia nebola nájdená.']);
        exit;
    }
    
    // Check if class is full
    if (isClassFull($classId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Lekcia je obsadená.']);
        exit;
    }
    
    // Check if user is already registered
    $existing = db()->fetch("
        SELECT id FROM registrations 
        WHERE user_id = ? AND class_id = ? AND status = 'confirmed'
    ", [$user['id'], $classId]);
    
    if ($existing) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Už ste prihlásený na túto lekciu.']);
        exit;
    }
    
    // Determine payment method and price
    $price = $useCredit ? $class['price_with_credit'] : $class['price'];
    $paidWithCredit = $useCredit;
    
    // Check credit balance if using credit
    if ($useCredit && $user['eur_balance'] < $price) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Nedostatok kreditu. Potrebujete ' . formatPrice($price) . ', máte ' . formatPrice($user['eur_balance']) . '.'
        ]);
        exit;
    }
    
    // Start transaction
    db()->getConnection()->beginTransaction();
    
    try {
        // Create registration
        $sql = "INSERT INTO registrations (user_id, class_id, status, paid_with_credit) VALUES (?, ?, 'confirmed', ?)";
        db()->query($sql, [$user['id'], $classId, $paidWithCredit]);
        $registrationId = db()->lastInsertId();
        
        // Deduct credit if used
        if ($useCredit) {
            deductCredit($user['id'], $price, 'class_payment', $registrationId);
        }
        
        // Commit transaction
        db()->getConnection()->commit();
        
        // Success response
        $message = $useCredit 
            ? "Úspešne ste sa prihlásili na lekciu. Bolo odpočítaných " . formatPrice($price) . " kreditu."
            : "Úspešne ste sa prihlásili na lekciu. Platba " . formatPrice($price) . " sa uskutoční na mieste.";
            
        echo json_encode([
            'success' => true,
            'message' => $message,
            'registration_id' => $registrationId,
            'paid_with_credit' => $paidWithCredit,
            'amount' => $price
        ]);
        
    } catch (Exception $e) {
        db()->getConnection()->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Nastala chyba pri registrácii.']);
}
?>