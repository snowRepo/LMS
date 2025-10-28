<?php
define('LMS_ACCESS', true);

// Load configuration
require_once 'includes/EnvLoader.php';
EnvLoader::load();
include 'config/config.php';

try {
    // Test notification service
    require_once 'includes/NotificationService.php';
    $notificationService = new NotificationService();
    
    // Create a test notification
    $result = $notificationService->createNotification(
        'MEM1761278591792',  // Test user ID
        'Test Notification',
        'This is a test notification to verify the notification system is working correctly.',
        'info',
        '../member/dashboard.php'
    );
    
    if ($result) {
        echo "Notification created successfully!";
    } else {
        echo "Failed to create notification.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>