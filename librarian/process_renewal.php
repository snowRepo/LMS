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
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get borrowing ID from POST data
$borrowingId = isset($_POST['borrowing_id']) ? (int)$_POST['borrowing_id'] : 0;

if (empty($borrowingId)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid borrowing ID']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Get borrowing details
    $stmt = $db->prepare("
        SELECT b.*, bk.title as book_title
        FROM borrowings b
        JOIN books bk ON b.book_id = bk.id
        WHERE b.id = ? AND b.status = 'active'
    ");
    $stmt->execute([$borrowingId]);
    $borrowing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$borrowing) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Borrowing record not found or not active']);
        exit;
    }
    
    // Calculate new due date (extend by 14 days)
    $currentDueDate = new DateTime($borrowing['due_date']);
    $newDueDate = $currentDueDate->modify('+14 days');
    
    // Update borrowing record with new due date
    $updateStmt = $db->prepare("
        UPDATE borrowings 
        SET due_date = ?,
            renewal_count = renewal_count + 1,
            updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$newDueDate->format('Y-m-d'), $borrowingId]);
    
    // Send notification to member about the renewal
    $notificationMessage = "Your borrowing for '{$borrowing['book_title']}' has been renewed. New due date is " . $newDueDate->format('M j, Y') . ".";
    
    // Create notification using the NotificationService
    require_once '../includes/NotificationService.php';
    $notificationService = new NotificationService();
    $notificationService->createNotification(
        $borrowing['member_id'],
        'Borrowing Renewed',
        $notificationMessage,
        'info',
        '/member/borrowing.php'
    );
    
    // Return success response with new due date
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Borrowing renewed successfully!',
        'new_due_date' => $newDueDate->format('M j, Y')
    ]);
    
} catch (Exception $e) {
    error_log("Error processing renewal: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing the renewal. Please try again.']);
}