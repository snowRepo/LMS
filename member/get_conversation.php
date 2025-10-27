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
    
    // Get the current user's string ID
    $stmt = $db->prepare("SELECT user_id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    $current_user_id = $user['user_id'];
    
    // Get sender ID from query parameter
    $sender_id = $_GET['sender_id'] ?? '';
    
    if (empty($sender_id)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Sender ID is required']);
        exit;
    }
    
    // Fetch messages between current user and sender
    $stmt = $db->prepare("
        SELECT 
            id,
            sender_id,
            recipient_id,
            message,
            created_at as timestamp
        FROM messages 
        WHERE 
            (sender_id = ? AND recipient_id = ?) OR 
            (sender_id = ? AND recipient_id = ?)
        ORDER BY created_at ASC
    ");
    
    $stmt->execute([
        $sender_id, $current_user_id,
        $current_user_id, $sender_id
    ]);
    
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add is_sender field manually
    foreach ($messages as &$message) {
        $message['is_sender'] = ($message['sender_id'] === $current_user_id);
    }
    
    // Mark messages as read
    $stmt = $db->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE sender_id = ? AND recipient_id = ?
    ");
    $stmt->execute([$sender_id, $current_user_id]);
    
    header('Content-Type: application/json');
    echo json_encode(['messages' => $messages]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error fetching messages: ' . $e->getMessage()]);
}
?>