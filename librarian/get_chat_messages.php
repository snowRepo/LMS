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
    $other_user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
    
    if (!$other_user_id) {
        echo json_encode(['error' => 'User ID is required']);
        exit();
    }
    
    // Get messages between the two users
    $stmt = $db->prepare("
        SELECT m.*, 
               CONCAT(u.first_name, ' ', u.last_name) as sender_name,
               u.user_id as sender_id
        FROM messages m
        JOIN users u ON m.sender_id = u.user_id
        WHERE (m.sender_id = ? AND m.recipient_id = ?) 
           OR (m.sender_id = ? AND m.recipient_id = ?)
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$current_user_id, $other_user_id, $other_user_id, $current_user_id]);
    $messages = $stmt->fetchAll();
    
    // Mark messages as read if they are from the other user to current user
    $updateStmt = $db->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE recipient_id = ? AND sender_id = ? AND is_read = 0
    ");
    $updateStmt->execute([$current_user_id, $other_user_id]);
    
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>