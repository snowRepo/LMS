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
    $message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : null;
    $action = isset($_POST['action']) ? $_POST['action'] : null; // 'star' or 'read'
    
    if (!$message_id || !$action) {
        echo json_encode(['error' => 'Message ID and action are required']);
        exit;
    }
    
    // Verify the message belongs to the current user
    $stmt = $db->prepare("
        SELECT id FROM messages 
        WHERE id = ? AND (recipient_id = ? OR sender_id = ?)
    ");
    $stmt->execute([$message_id, $current_user_id, $current_user_id]);
    $message = $stmt->fetch();
    
    if (!$message) {
        echo json_encode(['error' => 'Message not found or access denied']);
        exit;
    }
    
    // Update the message based on the action
    if ($action === 'star') {
        // Toggle the starred status
        $stmt = $db->prepare("
            UPDATE messages 
            SET is_starred = CASE WHEN is_starred = 1 THEN 0 ELSE 1 END 
            WHERE id = ?
        ");
        $stmt->execute([$message_id]);
        
        // Get the new starred status
        $stmt = $db->prepare("SELECT is_starred FROM messages WHERE id = ?");
        $stmt->execute([$message_id]);
        $result = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'action' => 'star',
            'is_starred' => (bool)$result['is_starred']
        ]);
    } elseif ($action === 'read') {
        // Mark as read
        $stmt = $db->prepare("
            UPDATE messages 
            SET is_read = 1 
            WHERE id = ?
        ");
        $stmt->execute([$message_id]);
        
        echo json_encode([
            'success' => true,
            'action' => 'read'
        ]);
    } else {
        echo json_encode(['error' => 'Invalid action']);
        exit;
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>