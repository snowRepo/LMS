<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has librarian role
if (!is_logged_in() || $_SESSION['user_role'] !== 'librarian') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance()->getConnection();
        
        $userId = trim($_POST['user_id']);
        $attendanceDate = trim($_POST['attendance_date']);
        $present = isset($_POST['present']) ? (int)$_POST['present'] : 0;
        
        if (empty($userId) || empty($attendanceDate)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Missing required parameters']);
            exit;
        }
        
        if ($present) {
            // Mark member as present
            $stmt = $db->prepare("
                INSERT INTO attendance (user_id, library_id, attendance_date) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE attendance_date = VALUES(attendance_date)
            ");
            $result = $stmt->execute([$userId, $libraryId, $attendanceDate]);
            
            if ($result) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Attendance marked successfully']);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Failed to mark attendance']);
            }
        } else {
            // Remove attendance record
            $stmt = $db->prepare("
                DELETE FROM attendance 
                WHERE user_id = ? AND library_id = ? AND attendance_date = ?
            ");
            $result = $stmt->execute([$userId, $libraryId, $attendanceDate]);
            
            if ($result) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Attendance record removed']);
            } else {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Failed to remove attendance record']);
            }
        }
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'An error occurred: ' . $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid request method']);
}
?>