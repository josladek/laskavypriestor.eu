<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/email_functions.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    $classId = (int) $_GET['class_id'];
    $isRecurring = isset($_GET['recurring']) && $_GET['recurring'] === 'true';
    
    if (!$classId) {
        echo json_encode(['error' => 'Invalid class ID']);
        exit;
    }
    
    if ($isRecurring) {
        // Get recurring series ID
        $class = db()->fetch("SELECT recurring_series_id FROM yoga_classes WHERE id = ?", [$classId]);
        
        if (!$class || !$class['recurring_series_id']) {
            echo json_encode(['has_registrations' => false, 'clients' => []]);
            exit;
        }
        
        // Get current date for future lessons only
        $currentDate = date('Y-m-d');
        
        // Check for registrations in all future lessons of the series
        $registrations = getRecurringSeriesWithClients($class['recurring_series_id'], $currentDate);
        
        if (empty($registrations)) {
            echo json_encode(['has_registrations' => false, 'clients' => []]);
            exit;
        }
        
        // Group by unique clients
        $uniqueClients = [];
        foreach ($registrations as $reg) {
            $clientKey = $reg['user_id'];
            if (!isset($uniqueClients[$clientKey])) {
                $uniqueClients[$clientKey] = [
                    'id' => $reg['user_id'],
                    'name' => $reg['user_name'],
                    'email' => $reg['user_email']
                ];
            }
        }
        
        echo json_encode([
            'has_registrations' => true,
            'clients' => array_values($uniqueClients),
            'total_lessons' => count($registrations)
        ]);
        
    } else {
        // Check single lesson registrations
        $clients = getRegisteredClients($classId);
        
        echo json_encode([
            'has_registrations' => !empty($clients),
            'clients' => $clients
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error checking registrations: " . $e->getMessage());
    echo json_encode(['error' => 'Server error']);
}
?>