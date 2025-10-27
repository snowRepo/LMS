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

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and has librarian role
if (!is_logged_in() || $_SESSION['user_role'] !== 'librarian') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get the sender ID from the request
$senderId = $_GET['sender_id'] ?? '';

if (empty($senderId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Sender ID is required']);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Get current user's string ID
    $stmt = $db->prepare("SELECT user_id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    $currentUserId = $user['user_id'];
    
    // Mark messages as read when viewing conversation
    $updateStmt = $db->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE sender_id = ? 
        AND recipient_id = ? 
        AND is_read = 0
    ");
    $updateStmt->execute([$senderId, $currentUserId]);
    
    // Get the conversation messages
    $query = "
        SELECT 
            m.id,
            m.sender_id,
            m.recipient_id,
            m.subject,
            m.message,
            m.created_at,
            m.is_read,
            CONCAT(u.first_name, ' ', u.last_name) as sender_name,
            u.user_id as sender_user_id
        FROM messages m
        JOIN users u ON m.sender_id = u.user_id
        WHERE 
            (m.sender_id = ? AND m.recipient_id = ?) OR
            (m.sender_id = ? AND m.recipient_id = ?)
        ORDER BY m.created_at ASC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$senderId, $currentUserId, $currentUserId, $senderId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response
    $response = [
        'success' => true,
        'messages' => array_map(function($message) use ($currentUserId) {
            return [
                'id' => $message['id'],
                'is_sender' => $message['sender_id'] === $currentUserId,
                'sender_name' => $message['sender_name'],
                'sender_id' => $message['sender_id'],
                'message' => htmlspecialchars($message['message']),
                'subject' => htmlspecialchars($message['subject']),
                'timestamp' => date('M j, g:i a', strtotime($message['created_at'])),
                'is_read' => (bool)$message['is_read']
            ];
        }, $messages),
        'sender_name' => $messages[0]['sender_name'] ?? '',
        'sender_id' => $senderId
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error loading conversation: ' . $e->getMessage()
    ]);
}
