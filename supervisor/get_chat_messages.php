<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';
require_once '../includes/SubscriptionManager.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has supervisor role
if (!is_logged_in() || $_SESSION['user_role'] !== 'supervisor') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check subscription status
$subscriptionManager = new SubscriptionManager();
$libraryId = $_SESSION['library_id'];
$hasActiveSubscription = $subscriptionManager->hasActiveSubscription($libraryId);

if (!$hasActiveSubscription) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No active subscription']);
    exit;
}

// Get the user ID from the query string
$otherUserId = $_GET['user_id'] ?? '';

if (empty($otherUserId)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'User ID is required']);
    exit;
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
    
    // Mark messages as read
    $stmt = $db->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE (sender_id = ? AND recipient_id = ?) 
        AND is_read = 0
    ");
    $stmt->execute([$otherUserId, $currentUserId]);
    
    // Get messages between the two users
    $stmt = $db->prepare("
        SELECT 
            m.*,
            CONCAT(u.first_name, ' ', u.last_name) as sender_name,
            u.role as sender_role,
            CASE 
                WHEN m.sender_id = ? THEN 1 
                ELSE 0 
            END as is_sender
        FROM messages m
        JOIN users u ON m.sender_id = u.user_id
        WHERE (m.sender_id = ? AND m.recipient_id = ?)
           OR (m.sender_id = ? AND m.recipient_id = ?)
        ORDER BY m.created_at ASC
    ");
    
    $stmt->execute([
        $currentUserId,
        $currentUserId, $otherUserId,
        $otherUserId, $currentUserId
    ]);
    
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user details for the conversation
    $stmt = $db->prepare("
        SELECT user_id, CONCAT(first_name, ' ', last_name) as name, role 
        FROM users 
        WHERE user_id = ?
    ");
    $stmt->execute([$otherUserId]);
    $otherUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'otherUser' => $otherUser
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error fetching messages: ' . $e->getMessage()
    ]);
}
?>