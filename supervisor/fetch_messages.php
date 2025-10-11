<?php
// Set headers for JSON response
header('Content-Type: application/json');

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a supervisor
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
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
    // Get the current user's string ID from session
    $stmt = $db->prepare("SELECT user_id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    $current_user_id = $user['user_id'];
    
    if ($view === 'sent') {
        // Fetch sent messages for the current user
        $stmt = $db->prepare("
            SELECT 
                m.*, 
                CONCAT(u.first_name, ' ', u.last_name) as recipient_name,
                m.created_at as message_time,
                u.id as recipient_numeric_id,
                u.user_id as recipient_id
            FROM messages m
            JOIN users u ON m.recipient_id = u.user_id  -- Join on user_id instead of id
            WHERE m.sender_id = ? AND m.is_deleted = 0
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([$current_user_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else if ($view === 'starred') {
        // Fetch starred messages for the current user
        $stmt = $db->prepare("
            SELECT 
                m.*, 
                CONCAT(u.first_name, ' ', u.last_name) as sender_name,
                m.created_at as message_time,
                u.id as sender_numeric_id,
                u.user_id as sender_id
            FROM messages m
            JOIN users u ON m.sender_id = u.user_id  -- Join on user_id instead of id
            WHERE m.recipient_id = ? 
            AND m.is_starred = 1 
            AND m.is_deleted = 0
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([$current_user_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // Fetch inbox messages for the current user (default)
        $stmt = $db->prepare("
            SELECT 
                m.*, 
                CONCAT(u.first_name, ' ', u.last_name) as sender_name,
                m.created_at as message_time,
                u.id as sender_numeric_id,
                u.user_id as sender_id
            FROM messages m
            JOIN users u ON m.sender_id = u.user_id  -- Join on user_id instead of id
            WHERE m.recipient_id = ? 
            AND m.is_deleted = 0
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([$current_user_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Mark messages as read when viewing inbox
    if ($view === 'inbox') {
        $updateStmt = $db->prepare("
            UPDATE messages 
            SET is_read = 1 
            WHERE recipient_id = ? AND is_read = 0
        ");
        $updateStmt->execute([$current_user_id]);
    }
    
    // Ensure all dates are in ISO 8601 format
    foreach ($messages as &$message) {
        if (isset($message['message_time'])) {
            $message['message_time'] = date('c', strtotime($message['message_time']));
        }
        if (isset($message['created_at'])) {
            $message['created_at'] = date('c', strtotime($message['created_at']));
        }
        
        // Ensure all boolean fields are actually booleans
        $message['is_read'] = (bool)($message['is_read'] ?? false);
        $message['is_starred'] = (bool)($message['is_starred'] ?? false);
        $message['is_deleted'] = (bool)($message['is_deleted'] ?? false);
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
    
} catch (Exception $e) {
    error_log('Error in fetch_messages.php: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while fetching messages',
        'debug' => DEBUG_MODE ? $e->getMessage() : null
    ]);
}