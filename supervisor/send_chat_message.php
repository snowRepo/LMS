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

// Check if user is logged in and has supervisor role
if (!is_logged_in() || $_SESSION['user_role'] !== 'supervisor') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get the raw POST data
$data = json_decode(file_get_contents('php://input'), true);

// If JSON parsing failed, try form data
if (json_last_error() !== JSON_ERROR_NONE) {
    $data = $_POST;
}

// Validate required fields
$requiredFields = ['message', 'recipient_id', 'recipient_type'];
foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
        exit;
    }
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Get current user's string ID
    $stmt = $db->prepare("SELECT user_id, first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    $currentUserId = $user['user_id'];
    $recipientId = $data['recipient_id'];
    $message = trim($data['message']);
    $recipientType = $data['recipient_type'];
    $libraryId = $_SESSION['library_id']; // Get library ID from session
    
    // Validate recipient
    $stmt = $db->prepare("SELECT user_id FROM users WHERE user_id = ?");
    $stmt->execute([$recipientId]);
    if (!$stmt->fetch()) {
        throw new Exception('Recipient not found');
    }
    
    // Insert the message
    $stmt = $db->prepare("
        INSERT INTO messages (
            sender_id, 
            recipient_id, 
            message, 
            subject, 
            library_id,
            is_read, 
            is_starred, 
            is_deleted, 
            created_at, 
            updated_at
        ) VALUES (
            :sender_id, 
            :recipient_id, 
            :message, 
            :subject, 
            :library_id,
            :is_read, 
            :is_starred, 
            :is_deleted, 
            NOW(), 
            NOW()
        )
    ");
    
    $result = $stmt->execute([
        ':sender_id' => $currentUserId,
        ':recipient_id' => $recipientId,
        ':message' => $message,
        ':subject' => 'New message',
        ':library_id' => $libraryId,
        ':is_read' => 0,
        ':is_starred' => 0,
        ':is_deleted' => 0
    ]);
    
    if (!$result) {
        throw new Exception('Failed to send message');
    }
    
    $messageId = $db->lastInsertId();
    
    // Get the full message details to return to the client
    $stmt = $db->prepare("
        SELECT 
            m.*,
            CONCAT(u.first_name, ' ', u.last_name) as sender_name,
            u.role as sender_role
        FROM messages m
        JOIN users u ON m.sender_id = u.user_id
        WHERE m.id = ?
    ");
    $stmt->execute([$messageId]);
    $sentMessage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $sentMessage
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error sending message: ' . $e->getMessage()
    ]);
}