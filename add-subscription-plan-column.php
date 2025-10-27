<?php
define('LMS_ACCESS', true);

// Load configuration
require_once 'includes/EnvLoader.php';
EnvLoader::load();
include 'config/config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Adding subscription_plan column back to libraries table...\n";
    
    // Add the subscription_plan column back to the libraries table
    $stmt = $db->prepare("
        ALTER TABLE libraries 
        ADD COLUMN subscription_plan ENUM('basic', 'standard', 'premium') DEFAULT NULL
    ");
    
    if ($stmt->execute()) {
        echo "Successfully added subscription_plan column to libraries table.\n";
    } else {
        echo "Failed to add subscription_plan column to libraries table.\n";
    }
    
    echo "Column addition completed!\n";
    
} catch (Exception $e) {
    echo "Error adding column: " . $e->getMessage() . "\n";
}
?>