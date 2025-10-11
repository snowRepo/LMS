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

// Check if user is logged in and has librarian role
if (!is_logged_in() || $_SESSION['user_role'] !== 'librarian') {
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

header('Content-Type: application/json');

try {
    $db = Database::getInstance()->getConnection();
    
    // Get the user string ID from the integer session ID
    $stmt = $db->prepare("SELECT user_id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['error' => 'User not found']);
        exit();
    }
    
    $current_user_id = $user['user_id'];
    $recipient_id = isset($_POST['recipient_id']) ? $_POST['recipient_id'] : null;
    $message_text = isset($_POST['message']) ? trim($_POST['message']) : null;
    
    if (!$recipient_id || !$message_text) {
        echo json_encode(['error' => 'Recipient and message are required']);
        exit;
    }
    
    // Insert the new message
    $insertStmt = $db->prepare("
        INSERT INTO messages (sender_id, recipient_id, subject, message, created_at, is_read, is_starred, is_deleted, library_id)
        VALUES (?, ?, ?, ?, NOW(), 0, 0, 0, ?)
    ");
    
    // Use a generic subject for chat messages
    $subject = "Chat Message";
    
    $insertStmt->execute([$current_user_id, $recipient_id, $subject, $message_text, $libraryId]);
    
    // Get the inserted message with sender info
    $messageId = $db->lastInsertId();
    $stmt = $db->prepare("
        SELECT m.*, 
               CONCAT(u.first_name, ' ', u.last_name) as sender_name,
               u.user_id as sender_id
        FROM messages m
        JOIN users u ON m.sender_id = u.user_id
        WHERE m.id = ?
    ");
    $stmt->execute([$messageId]);
    $message = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>