<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';
require_once '../includes/ReservationService.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and has librarian role
if (!isset($_SESSION)) {
    session_start();
}

// Check if user is logged in and is a librarian
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'librarian') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Get reservation ID from POST data
$reservationId = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;

if (empty($reservationId)) {
    echo json_encode(['success' => false, 'error' => 'Invalid reservation ID']);
    exit;
}

try {
    // Initialize services
    $reservationService = new ReservationService();
    
    // Get reservation details
    $reservation = $reservationService->getReservation($reservationId);
    
    if (!$reservation) {
        throw new Exception('Reservation not found');
    }
    
    // Check if reservation is in a state that can be fulfilled
    if ($reservation['status'] !== ReservationService::STATUS_APPROVED) {
        throw new Exception('Only approved reservations can be marked as fulfilled');
    }
    
    // Update reservation status to fulfilled
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        UPDATE reservations 
        SET status = ?, 
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([
        ReservationService::STATUS_FULFILLED,
        $reservationId
    ]);
    
    // Send notification to member (using the string user_id, not the integer id)
    $notificationService = new NotificationService();
    $notificationService->createNotification(
        $reservation['member_user_id'],  // Use the string user_id
        'Reservation Fulfilled',
        "Your reservation for '{$reservation['book_title']}' has been marked as fulfilled. Thank you for using our library!",
        'reservation_fulfilled',
        "/member/reservations.php"
    );
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Reservation marked as fulfilled successfully',
        'status' => ReservationService::STATUS_FULFILLED
    ]);
    
} catch (Exception $e) {
    // Log the error
    error_log('Error fulfilling reservation: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>