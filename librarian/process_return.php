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
    header('Location: ../login.php');
    exit;
}

// Get borrowing ID from GET or POST data
$borrowingId = 0;
if (isset($_GET['borrowing_id']) && is_numeric($_GET['borrowing_id'])) {
    $borrowingId = (int)$_GET['borrowing_id'];
} elseif (isset($_POST['borrowing_id']) && is_numeric($_POST['borrowing_id'])) {
    $borrowingId = (int)$_POST['borrowing_id'];
}

if (empty($borrowingId)) {
    header('Location: borrowing.php?error=' . urlencode('Invalid borrowing ID'));
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();
    
    // Get borrowing details
    $stmt = $db->prepare("
        SELECT b.id as borrowing_id, b.book_id, b.member_id, b.status,
               bk.title as book_title,
               CONCAT(u.first_name, ' ', u.last_name) as member_name
        FROM borrowings b
        JOIN books bk ON b.book_id = bk.id
        JOIN users u ON b.member_id = u.id
        WHERE b.id = ?
    ");
    $stmt->execute([$borrowingId]);
    $borrowing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$borrowing) {
        throw new Exception('Borrowing record not found');
    }
    
    // Check if borrowing is in a state that can be returned
    if ($borrowing['status'] !== 'active') {
        throw new Exception('Only active borrowings can be marked as returned');
    }
    
    // Update borrowing status to returned
    $stmt = $db->prepare("
        UPDATE borrowings 
        SET status = 'returned',
            return_date = CURDATE(),
            returned_by = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $borrowingId]);
    
    // Check if there was a reservation for this book/member combination
    $reservationStmt = $db->prepare("
        SELECT id, status
        FROM reservations 
        WHERE member_id = ? AND book_id = ? AND status IN ('approved', 'borrowed')
        FOR UPDATE
    ");
    $reservationStmt->execute([$borrowing['member_id'], $borrowing['book_id']]);
    $existingReservation = $reservationStmt->fetch(PDO::FETCH_ASSOC);
    
    // If there was a reservation, update its status to 'returned'
    if ($existingReservation) {
        $updateReservationStmt = $db->prepare("
            UPDATE reservations 
            SET status = 'returned',
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateReservationStmt->execute([$existingReservation['id']]);
    }
    
    // Increment available copies when book is returned
    // Only increment if there wasn't an approved reservation that was converted to borrowing
    // (In that case, the count was already decremented when the reservation was made)
    $stmt = $db->prepare("
        UPDATE books 
        SET available_copies = available_copies + 1 
        WHERE id = ?
    ");
    $stmt->execute([$borrowing['book_id']]);
    
    // Log the book return activity
    try {
        $activityStmt = $db->prepare("
            INSERT INTO activity_logs 
            (user_id, library_id, action, description, created_at)
            VALUES (?, ?, 'return_book', ?, NOW())
        ");
        $activityStmt->execute([
            $_SESSION['user_id'],
            $_SESSION['library_id'],
            'Returned book: ' . $borrowing['book_title']
        ]);
    } catch (Exception $e) {
        // Log the error but don't fail the return process
        error_log('Failed to log activity: ' . $e->getMessage());
    }
    
    // Send notification to member about the return
    try {
        // Get member details for notification
        $memberStmt = $db->prepare("
            SELECT u.user_id, CONCAT(u.first_name, ' ', u.last_name) as member_name, bk.title as book_title
            FROM users u
            JOIN borrowings b ON b.member_id = u.id
            JOIN books bk ON bk.id = b.book_id
            WHERE b.id = ?
        ");
        $memberStmt->execute([$borrowingId]);
        $memberInfo = $memberStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($memberInfo) {
            // Create notification service instance
            $notificationService = new NotificationService();
            
            // Prepare notification message
            $title = 'Book Returned';
            $message = "You have successfully returned '{$memberInfo['book_title']}'. Thank you for returning the book on time.";
            $actionUrl = "../member/borrowing.php";
            
            // Create notification for member
            $notificationService->createNotification(
                $memberInfo['user_id'],
                $title,
                $message,
                'success',
                $actionUrl
            );
        }
    } catch (Exception $e) {
        // Log error but don't stop the process
        error_log("Error sending return notification: " . $e->getMessage());
    }
    
    $db->commit();
    
    // Redirect with success message
    header('Location: borrowing.php?success=' . urlencode('Book returned successfully'));
    exit;
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error returning book: " . $e->getMessage());
    header('Location: borrowing.php?error=' . urlencode($e->getMessage()));
    exit;
}