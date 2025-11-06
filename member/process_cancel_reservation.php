<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has member role
if (!is_logged_in() || $_SESSION['user_role'] !== 'member') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get reservation ID from POST data
$reservationId = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;

if (empty($reservationId)) {
    echo json_encode(['success' => false, 'message' => 'Invalid reservation ID']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();
    
    // Get reservation details including book and library information
    $stmt = $db->prepare("
        SELECT r.*, b.book_id as book_internal_id, b.title as book_title, b.library_id
        FROM reservations r
        JOIN books b ON r.book_id = b.id
        WHERE r.id = ? AND r.member_id = ?
    ");
    $stmt->execute([$reservationId, $_SESSION['user_id']]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reservation) {
        throw new Exception('Reservation not found');
    }
    
    // Check if reservation can be cancelled
    if ($reservation['status'] !== 'pending') {
        throw new Exception('Only pending reservations can be cancelled');
    }
    
    // Update reservation status to cancelled
    $stmt = $db->prepare("
        UPDATE reservations 
        SET status = 'cancelled',
            cancelled_date = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$reservationId]);
    
    // Increment available copies when reservation is cancelled (to restore the count)
    $stmt = $db->prepare("
        UPDATE books 
        SET available_copies = available_copies + 1 
        WHERE id = ?
    ");
    $stmt->execute([$reservation['book_id']]);
    
    // Get member name for notification
    $stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    $memberName = $member ? $member['first_name'] . ' ' . $member['last_name'] : 'A member';
    
    $db->commit();
    
    // Send notification to librarians
    try {
        // Include the NotificationService
        require_once '../includes/NotificationService.php';
        
        // Get all librarians in the same library
        $stmt = $db->prepare("
            SELECT id FROM users 
            WHERE library_id = ? AND role = 'librarian' AND status = 'active'
        ");
        $stmt->execute([$reservation['library_id']]);
        $librarians = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($librarians)) {
            $notificationService = new NotificationService();
            $title = "Reservation Cancelled";
            $message = "$memberName has cancelled their reservation for the book \"{$reservation['book_title']}\".";
            
            foreach ($librarians as $librarianId) {
                $notificationService->createNotification($librarianId, $title, $message, 'info', "reservations.php");
            }
        }
    } catch (Exception $e) {
        error_log("Error sending librarian notification: " . $e->getMessage());
        // Don't fail the cancellation if notification fails
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Reservation cancelled successfully'
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error cancelling reservation: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}