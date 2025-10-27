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
    
    // Mark all unread messages as read for the current user
    $stmt = $db->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE recipient_id = ? AND is_read = 0
    ");
    $stmt->execute([$current_user_id]);
    
    echo json_encode(['success' => true, 'message' => 'All messages marked as read']);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>