<?php
define('LMS_ACCESS', true);

// Load configuration
require_once 'includes/EnvLoader.php';
EnvLoader::load();
include 'config/config.php';
require_once 'includes/NotificationService.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!is_logged_in()) {
    echo "User not logged in\n";
    exit;
}

echo "Session user_id: " . $_SESSION['user_id'] . "\n";
echo "Session user_role: " . $_SESSION['user_role'] . "\n";

try {
    $notificationService = new NotificationService();
    
    // Get notifications for the current user
    echo "Getting notifications for user ID: " . $_SESSION['user_id'] . "\n";
    $notifications = $notificationService->getNotifications($_SESSION['user_id']);
    
    echo "Found " . count($notifications) . " notifications\n";
    
    // Display first few notifications
    foreach (array_slice($notifications, 0, 3) as $notification) {
        echo "Notification ID: " . $notification['id'] . "\n";
        echo "Title: " . $notification['title'] . "\n";
        echo "Message: " . $notification['message'] . "\n";
        echo "Type: " . $notification['type'] . "\n";
        echo "Read at: " . ($notification['read_at'] ?? 'Not read') . "\n";
        echo "---\n";
    }
    
    // Get unread count
    $unreadCount = $notificationService->getUnreadCount($_SESSION['user_id']);
    echo "Unread count: " . $unreadCount . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>