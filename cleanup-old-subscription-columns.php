<?php
define('LMS_ACCESS', true);

// Load configuration
require_once 'includes/EnvLoader.php';
EnvLoader::load();
include 'config/config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Starting cleanup of old subscription columns...\n";
    
    // Check if the old columns exist
    $stmt = $db->prepare("DESCRIBE libraries");
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    $existingColumns = [];
    foreach ($columns as $column) {
        $existingColumns[] = $column['Field'];
    }
    
    // Columns to remove
    $columnsToRemove = ['subscription_plan', 'subscription_status', 'subscription_expires'];
    
    foreach ($columnsToRemove as $column) {
        if (in_array($column, $existingColumns)) {
            echo "Removing column: $column...\n";
            $stmt = $db->prepare("ALTER TABLE libraries DROP COLUMN $column");
            if ($stmt->execute()) {
                echo "Successfully removed column: $column\n";
            } else {
                echo "Failed to remove column: $column\n";
            }
        } else {
            echo "Column $column does not exist, skipping...\n";
        }
    }
    
    echo "Cleanup of old subscription columns completed!\n";
    
} catch (Exception $e) {
    echo "Error during cleanup: " . $e->getMessage() . "\n";
}
?>