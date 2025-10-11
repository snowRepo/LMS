<?php
/**
 * Script to add user_settings table to existing LMS database
 */

// Define access constant to prevent direct access error
define('LMS_ACCESS', true);

// Load configuration
require_once '../config/config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Create user_settings table
    $sql = "
    CREATE TABLE IF NOT EXISTS user_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        setting_key VARCHAR(100) NOT NULL,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        
        UNIQUE KEY unique_user_setting (user_id, setting_key),
        INDEX idx_user_id (user_id),
        INDEX idx_setting_key (setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $db->exec($sql);
    
    echo "User settings table created successfully!\n";
    echo "Table structure:\n";
    echo "- id: Primary key\n";
    echo "- user_id: Reference to the user\n";
    echo "- setting_key: The setting identifier\n";
    echo "- setting_value: The setting value (can be JSON encoded)\n";
    echo "- created_at: When the setting was created\n";
    echo "- updated_at: When the setting was last updated\n";
    
} catch (Exception $e) {
    echo "Error creating user_settings table: " . $e->getMessage() . "\n";
    exit(1);
}
?>