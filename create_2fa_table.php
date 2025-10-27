<?php
define('LMS_ACCESS', true);

// Load configuration
require_once 'includes/EnvLoader.php';
EnvLoader::load();
include 'config/config.php';

try {
    echo "<h2>Creating two_factor_codes table</h2>";
    
    // Test database connection
    $db = Database::getInstance()->getConnection();
    
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Check if table already exists
    $stmt = $db->prepare("SHOW TABLES LIKE 'two_factor_codes'");
    $stmt->execute();
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "<p style='color: green;'>✓ two_factor_codes table already exists</p>";
    } else {
        // Create the two_factor_codes table
        $sql = "CREATE TABLE two_factor_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            code VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            attempts INT DEFAULT 0,
            used TINYINT(1) DEFAULT 0,
            used_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_expires_at (expires_at),
            INDEX idx_used (used)
        )";
        
        $stmt = $db->prepare($sql);
        $stmt->execute();
        
        echo "<p style='color: green;'>✓ two_factor_codes table created successfully</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>