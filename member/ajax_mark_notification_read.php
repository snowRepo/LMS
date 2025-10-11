<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';
require_once '../includes/NotificationService.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has member role
if (!is_logged_in() || $_SESSION['user_role'] !== 'member') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Get notification ID from POST data
$notificationId = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (empty($notificationId)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing notification ID']);
    exit;
}

try {
    $notificationService = new NotificationService();
    
    // Mark notification as read
    $result = $notificationService->markAsRead($notificationId, $_SESSION['user_id']);
    
    if ($result) {
        // Get updated unread count
        $unreadCount = $notificationService->getUnreadCount($_SESSION['user_id']);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'unread_count' => $unreadCount]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to mark notification as read']);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Error marking notification as read: ' . $e->getMessage()]);
}
?>