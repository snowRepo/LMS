<?php
define('LMS_ACCESS', true);

// Load configuration
require_once 'includes/EnvLoader.php';
EnvLoader::load();
include 'config/config.php';
require_once 'includes/NotificationService.php';

// Test notification creation
try {
    $notificationService = new NotificationService();
    
    // Try to create a test notification
    // Use a known user ID (you'll need to replace this with an actual user ID from your database)
    $testUserId = 1; // Replace with actual user ID
    
    $result = $notificationService->createNotification(
        $testUserId,
        'Test Notification',
        'This is a test notification to check if the system is working correctly.',
        'info',
        '/member/reservations.php'
    );
    
    if ($result) {
        echo "Notification created successfully!\n";
    } else {
        echo "Failed to create notification.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>