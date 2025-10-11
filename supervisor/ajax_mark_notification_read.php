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

// Check if user is logged in and has supervisor role
if (!is_logged_in() || $_SESSION['user_role'] !== 'supervisor') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Check if notification ID is provided
if (!isset($_POST['notification_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Notification ID is required']);
    exit;
}

try {
    $notificationService = new NotificationService();
    $notificationId = (int)$_POST['notification_id'];
    
    // Mark notification as read
    $result = $notificationService->markAsRead($notificationId, $_SESSION['user_id']);
    
    if ($result) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Failed to mark notification as read']);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to mark notification as read: ' . $e->getMessage()]);
}
?>