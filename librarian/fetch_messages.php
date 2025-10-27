<?php
header('Content-Type: application/json');

session_start();

// Check if user is logged in and is a librarian
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'librarian') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Database connection - using the correct method
define('LMS_ACCESS', true);
require_once '../config/config.php';
$db = Database::getInstance()->getConnection();

// Get the view parameter
$view = isset($_GET['view']) ? $_GET['view'] : 'inbox';

try {
    // Get the user ID from the session (it's the integer ID, not the user_id string)
    // We need to get the user_id string from the database
    $stmt = $db->prepare("SELECT user_id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['error' => 'User not found']);
        exit();
    }
    
    $user_string_id = $user['user_id'];
    
    if ($view === 'sent') {
        // Fetch sent messages for the current user
        $stmt = $db->prepare("
            SELECT m.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as recipient_name,
                   m.created_at as message_time,
                   u.user_id as recipient_id
            FROM messages m
            JOIN users u ON m.recipient_id = u.user_id
            WHERE m.sender_id = ? AND m.is_deleted = 0
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([$user_string_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Return the messages as JSON
        echo json_encode(['messages' => $messages]);
        exit();
        
    } else if ($view === 'starred') {
        // Fetch starred messages for the current user
        $stmt = $db->prepare("
            SELECT m.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as sender_name,
                   m.created_at as message_time,
                   u.user_id as sender_id
            FROM messages m
            JOIN users u ON m.sender_id = u.user_id
            WHERE m.recipient_id = ? AND m.is_starred = 1 AND m.is_deleted = 0
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([$user_string_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Return the messages as JSON
        echo json_encode(['messages' => $messages]);
        exit();
        
    } else {
        // Default to inbox
        $stmt = $db->prepare("
            SELECT m.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as sender_name,
                   m.created_at as message_time,
                   u.user_id as sender_id
            FROM messages m
            JOIN users u ON m.sender_id = u.user_id
            WHERE m.recipient_id = ? AND m.is_deleted = 0
            ORDER BY m.is_read ASC, m.created_at DESC
        ");
        $stmt->execute([$user_string_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Return the messages as JSON
        echo json_encode(['messages' => $messages]);
        exit();
    }
    
} catch (Exception $e) {
    // Log the error for debugging
    error_log('Error in fetch_messages.php: ' . $e->getMessage());
    
    // Return a generic error message
    echo json_encode(['error' => 'An error occurred while fetching messages']);
    exit();
}