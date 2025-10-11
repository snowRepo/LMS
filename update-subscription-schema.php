<?php
define('LMS_ACCESS', true);

// Load configuration
require_once 'includes/EnvLoader.php';
EnvLoader::load();
include 'config/config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Update the libraries table schema
    echo "Updating libraries table schema...\n";
    
    // Modify subscription_plan column
    $stmt = $db->prepare("
        ALTER TABLE libraries 
        MODIFY COLUMN subscription_plan ENUM('basic', 'standard', 'premium') DEFAULT NULL,
        MODIFY COLUMN subscription_status ENUM('trial', 'active', 'inactive', 'suspended') DEFAULT 'trial'
    ");
    
    if ($stmt->execute()) {
        echo "Successfully updated libraries table schema.\n";
    } else {
        echo "Failed to update libraries table schema.\n";
    }
    
    // Check if there are any libraries with 'trial' in subscription_plan that need to be fixed
    $stmt = $db->prepare("
        SELECT id, subscription_plan, subscription_status 
        FROM libraries 
        WHERE subscription_plan = 'trial'
    ");
    $stmt->execute();
    $libraries = $stmt->fetchAll();
    
    if (count($libraries) > 0) {
        echo "Found " . count($libraries) . " libraries with incorrect subscription_plan values. Fixing...\n";
        
        foreach ($libraries as $library) {
            // Set subscription_plan to NULL and ensure subscription_status is 'trial'
            $updateStmt = $db->prepare("
                UPDATE libraries 
                SET subscription_plan = NULL, subscription_status = 'trial'
                WHERE id = ?
            ");
            $updateStmt->execute([$library['id']]);
            echo "Fixed library ID: " . $library['id'] . "\n";
        }
    } else {
        echo "No libraries with incorrect subscription_plan values found.\n";
    }
    
    echo "Schema update completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error updating schema: " . $e->getMessage() . "\n";
}
?>