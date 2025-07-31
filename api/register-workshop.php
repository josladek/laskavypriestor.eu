<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Must be logged in
if (!isLoggedIn()) {
    header('Location: ../pages/login.php');
    exit;
}

$currentUser = getCurrentUser();

// Get workshop ID and confirmation from POST (confirmation form) or GET (direct access)  
$workshopId = (int)($_POST['workshop_id'] ?? $_GET['id'] ?? 0);
$confirmed = isset($_POST['confirmed']) && $_POST['confirmed'] === '1';
$notes = trim($_POST['notes'] ?? '');

// If coming from confirmation form, require confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$confirmed) {
    $_SESSION['flash_message'] = 'Registrácia nebola potvrdená.';
    $_SESSION['flash_type'] = 'error';
    header('Location: ' . url('pages/workshops.php'));
    exit;
}

if (!$workshopId) {
    setFlashMessage('Nevalidný workshop.', 'danger');
    header('Location: ../pages/workshops.php');
    exit;
}

try {
    // Get workshop details with proper instructor name handling
    $workshop = db()->fetch("
        SELECT w.*, 
               COALESCE(NULLIF(w.custom_instructor_name, ''), u.name) as lektor_name,
               COUNT(wr.id) as registered_count
        FROM workshops w
        LEFT JOIN users u ON w.instructor_id = u.id
        LEFT JOIN workshop_registrations wr ON w.id = wr.workshop_id AND wr.status IN ('confirmed', 'waitlisted')
        WHERE w.id = ? AND w.status = 'active'
        GROUP BY w.id
    ", [$workshopId]);

    if (!$workshop) {
        setFlashMessage('Workshop nebol nájdený alebo nie je dostupný na registráciu.', 'danger');
        header('Location: ../pages/workshops.php');
        exit;
    }

    // Check if workshop is in the past
    if (strtotime($workshop['date']) < strtotime(date('Y-m-d'))) {
        setFlashMessage('Nemôžete sa registrovať na workshop z minulosti.', 'danger');
        header('Location: ../pages/workshops.php');
        exit;
    }

    // Check if user is already registered (only active registrations)
    $existingRegistration = db()->fetch("
        SELECT id, status FROM workshop_registrations 
        WHERE user_id = ? AND workshop_id = ? AND status IN ('confirmed', 'waitlisted', 'pending')
    ", [$currentUser['id'], $workshopId]);

    if ($existingRegistration) {
        if ($existingRegistration['status'] === 'confirmed') {
            setFlashMessage('Už ste registrovaný na tento workshop.', 'warning');
        } else {
            setFlashMessage('Už ste na čakacej listine pre tento workshop.', 'warning');
        }
        header('Location: ../pages/workshops.php');
        exit;
    }
    
    // Clean up any cancelled registrations before creating new one
    db()->query("
        DELETE FROM workshop_registrations 
        WHERE workshop_id = ? AND user_id = ? AND status = 'cancelled'
    ", [$workshopId, $currentUser['id']]);

    // Determine registration status
    $status = 'confirmed';
    $waitlistPosition = null;
    
    if ($workshop['registered_count'] >= $workshop['capacity']) {
        $status = 'waitlisted';
        $waitlistPosition = db()->fetchColumn("
            SELECT COUNT(*) + 1 FROM workshop_registrations 
            WHERE workshop_id = ? AND status = 'waitlisted'
        ", [$workshopId]);
    }

    // Handle payment based on workshop price
    $isFree = ($workshop['is_free'] == 1) || ($workshop['price'] == 0);
    $paymentMethod = $isFree ? 'none' : 'cash';
    $paymentStatus = $isFree ? 'confirmed' : 'pending';

    // Create registration
    db()->query("
        INSERT INTO workshop_registrations (user_id, workshop_id, status, paid_with_credit, 
                                          payment_amount, payment_method, payment_status, waitlist_position, registered_on, notes) 
        VALUES (?, ?, ?, 0, ?, ?, ?, ?, NOW(), ?)
    ", [$currentUser['id'], $workshopId, $status, $workshop['price'], $paymentMethod, $paymentStatus, $waitlistPosition, $notes]);

    $registrationId = db()->lastInsertId();

    if ($status === 'confirmed') {
        if ($isFree) {
            // Free workshop - registration is complete
            setFlashMessage('Úspešne ste sa zaregistrovali na bezplatný workshop "' . $workshop['title'] . '".', 'success');
            header('Location: ' . url('pages/workshops.php'));
            exit;
        } else {
            // Paid workshop - create payment request
            $variableSymbol = generateVariableSymbol($currentUser['id'], $workshopId);
            
            db()->query("
                INSERT INTO payment_requests (user_id, package_id, workshop_id, amount, eur_amount, variable_symbol, 
                                            status, payment_method, notes, created_at, updated_at) 
                VALUES (?, 'WORKSHOP', ?, ?, ?, ?, 'pending', 'bank_transfer', ?, NOW(), NOW())
            ", [
                $currentUser['id'], 
                $workshopId,
                $workshop['price'], 
                $workshop['price'], 
                $variableSymbol,
                'Registrácia na workshop: ' . $workshop['title'] . ($notes ? '; Poznámka: ' . $notes : '')
            ]);
            
            $paymentRequestId = db()->lastInsertId();
            
            // Redirect to payment confirmation page
            header('Location: ' . url('pages/workshop-payment-confirmation.php?request_id=' . $paymentRequestId));
            exit;
        }
    } else {
        setFlashMessage('Workshop je plný. Pridali sme vás na čakaciu listinu (pozícia: ' . $waitlistPosition . ').', 'info');
    }

} catch (Exception $e) {
    error_log('Workshop registration error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    setFlashMessage('Chyba pri registrácii: ' . $e->getMessage(), 'danger');
}

header('Location: ../pages/workshops.php');
exit;