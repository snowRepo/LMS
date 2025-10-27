<?php
/**
 * Script to add messages table to existing LMS database
 */

// Load configuration
require_once '../config/config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Create messages table
    $sql = "
    CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id VARCHAR(50) NOT NULL,
        recipient_id VARCHAR(50) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        library_id INT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        is_starred TINYINT(1) DEFAULT 0,
        is_deleted TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_sender (sender_id),
        INDEX idx_recipient (recipient_id),
        INDEX idx_library (library_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $db->exec($sql);
    
    echo "Messages table created successfully!\n";
    echo "Table structure:\n";
    echo "- id: Primary key\n";
    echo "- sender_id: ID of the user who sent the message\n";
    echo "- recipient_id: ID of the user who received the message\n";
    echo "- subject: Message subject line\n";
    echo "- message: The message content\n";
    echo "- library_id: The library this message belongs to\n";
    echo "- is_read: Whether the message has been read (0 = unread, 1 = read)\n";
    echo "- is_starred: Whether the message is starred (0 = not starred, 1 = starred)\n";
    echo "- is_deleted: Whether the message is deleted (0 = not deleted, 1 = deleted)\n";
    echo "- created_at: When the message was created\n";
    echo "- updated_at: When the message was last updated\n";
    
} catch (Exception $e) {
    echo "Error creating messages table: " . $e->getMessage() . "\n";
    exit(1);
}
?>