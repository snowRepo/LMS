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

// Check if user is logged in and has librarian role
if (!is_logged_in() || $_SESSION['user_role'] !== 'librarian') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Get notification ID from POST data
$notificationId = $_POST['id'] ?? null;

if (!$notificationId) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Notification ID is required']);
    exit;
}

try {
    $notificationService = new NotificationService();
    $result = $notificationService->markAsRead($notificationId, $_SESSION['user_id']);
    
    if ($result) {
        // Get updated unread count
        $unreadCount = $notificationService->getUnreadCount($_SESSION['user_id']);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'unread_count' => $unreadCount
        ]);
    } else {
        throw new Exception('Failed to mark notification as read');
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>