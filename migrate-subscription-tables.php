<?php
define('LMS_ACCESS', true);

// Load configuration
require_once 'includes/EnvLoader.php';
EnvLoader::load();
include 'config/config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Starting subscription tables migration...\n";
    
    // Create subscriptions table
    echo "Creating subscriptions table...\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            library_id INT NOT NULL,
            plan_type ENUM('basic', 'standard', 'premium') NOT NULL,
            status ENUM('trial', 'active', 'inactive', 'suspended', 'cancelled') DEFAULT 'trial',
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            auto_renew BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (library_id) REFERENCES libraries(id) ON DELETE CASCADE,
            
            INDEX idx_library_id (library_id),
            INDEX idx_status (status),
            INDEX idx_end_date (end_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    
    if ($stmt->execute()) {
        echo "Subscriptions table created successfully.\n";
    } else {
        echo "Failed to create subscriptions table.\n";
    }
    
    // Create payment_history table
    echo "Creating payment_history table...\n";
    $stmt = $db->prepare("
        CREATE TABLE IF NOT EXISTS payment_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subscription_id INT NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            currency VARCHAR(3) DEFAULT 'GHS',
            payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            payment_method ENUM('credit_card', 'debit_card', 'bank_transfer', 'mobile_money', 'paypal') NOT NULL,
            transaction_reference VARCHAR(255),
            status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
            
            INDEX idx_subscription_id (subscription_id),
            INDEX idx_payment_date (payment_date),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    
    if ($stmt->execute()) {
        echo "Payment history table created successfully.\n";
    } else {
        echo "Failed to create payment history table.\n";
    }
    
    // Migrate existing subscription data from libraries table to subscriptions table
    echo "Migrating existing subscription data...\n";
    $stmt = $db->prepare("
        SELECT id, subscription_plan, subscription_status, subscription_expires, created_at
        FROM libraries 
        WHERE subscription_plan IS NOT NULL OR subscription_status IS NOT NULL
    ");
    $stmt->execute();
    $libraries = $stmt->fetchAll();
    
    $migratedCount = 0;
    foreach ($libraries as $library) {
        // Determine plan type - if subscription_plan is NULL, we'll need to handle this specially
        $planType = $library['subscription_plan'];
        if (empty($planType)) {
            // For libraries without a plan set, we might need to determine from other data
            // For now, we'll skip libraries without a valid plan
            continue;
        }
        
        // Determine status
        $status = $library['subscription_status'] ?? 'trial';
        
        // Determine dates
        $startDate = date('Y-m-d', strtotime($library['created_at']));
        $endDate = $library['subscription_expires'] ?? date('Y-m-d', strtotime('+14 days', strtotime($startDate)));
        
        // Insert into subscriptions table
        $insertStmt = $db->prepare("
            INSERT INTO subscriptions 
            (library_id, plan_type, status, start_date, end_date)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        if ($insertStmt->execute([
            $library['id'], 
            $planType, 
            $status, 
            $startDate, 
            $endDate
        ])) {
            $migratedCount++;
        } else {
            echo "Failed to migrate subscription data for library ID: " . $library['id'] . "\n";
        }
    }
    
    echo "Migrated $migratedCount library subscription records.\n";
    
    echo "Subscription tables migration completed successfully!\n";
    echo "Note: The old subscription columns in the libraries table have not been removed yet.\n";
    echo "Please verify the data migration before removing the old columns.\n";
    
} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
}
?>