<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Get the user string ID from the integer session ID
    $stmt = $db->prepare("SELECT user_id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    $user_string_id = $user['user_id'];
    
    // Count unread messages using string user_id
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM messages WHERE recipient_id = ? AND is_read = 0 AND is_deleted = 0");
    $stmt->execute([$user_string_id]);
    $unreadCount = $stmt->fetch()['unread_count'];
    
    header('Content-Type: application/json');
    echo json_encode(['unread_count' => $unreadCount]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error counting messages: ' . $e->getMessage()]);
}
?>