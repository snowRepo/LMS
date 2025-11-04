<?php
define('LMS_ACCESS', true);

// Load configuration
require_once 'includes/EnvLoader.php';
EnvLoader::load();
include 'config/config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Checking member with ID 12...\n";
    
    // Get member with ID 12
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([12]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($member) {
        echo "Member found:\n";
        echo "ID: " . $member['id'] . "\n";
        echo "User ID: " . $member['user_id'] . "\n";
        echo "Name: " . $member['first_name'] . " " . $member['last_name'] . "\n";
        echo "Email: " . $member['email'] . "\n";
        echo "Role: " . $member['role'] . "\n";
    } else {
        echo "Member with ID 12 not found.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>