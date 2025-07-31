<?php
echo "Test started...\n";

try {
    echo "Loading config...\n";
    require_once '../config/config.php';
    echo "Config loaded\n";
    
    echo "Loading database...\n";
    require_once '../config/database.php';
    echo "Database loaded\n";
    
    echo "Loading auth...\n";
    require_once '../includes/auth.php';
    echo "Auth loaded\n";
    
    echo "Loading functions...\n";
    require_once '../includes/functions.php';
    echo "Functions loaded\n";
    
    echo "All includes successful!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>