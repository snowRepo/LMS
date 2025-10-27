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

// Get action and reservation ID from POST data
$action = $_POST['action'] ?? '';
$reservationId = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
$librarianNotes = $_POST['librarian_notes'] ?? '';
$rejectionReason = $_POST['rejection_reason'] ?? '';

// Validate required fields
if (empty($action) || empty($reservationId)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Validate action
if (!in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

// Require rejection reason for reject action
if ($action === 'reject' && empty($rejectionReason)) {
    echo json_encode(['success' => false, 'error' => 'Please provide a reason for rejection']);
    exit;
}

try {
    // Initialize services
    $reservationService = new ReservationService();
    $notificationService = new NotificationService();
    
    // Get reservation details
    $reservation = $reservationService->getReservation($reservationId);
    
    if (!$reservation) {
        throw new Exception('Reservation not found');
    }
    
    // Update reservation status based on action
    if ($action === 'approve') {
        // Check if reservation can be approved
        if ($reservation['status'] !== ReservationService::STATUS_PENDING) {
            throw new Exception('Only pending reservations can be approved');
        }
        
        // Update reservation status to approved using the correct method
        $success = $reservationService->approve(
            $reservationId,
            $_SESSION['user_id'],
            $librarianNotes
        );
        
        if (!$success) {
            throw new Exception('Failed to approve reservation');
        }
        
        $message = 'Reservation approved successfully';
        
    } elseif ($action === 'reject') {
        // Check if reservation can be rejected
        if ($reservation['status'] !== ReservationService::STATUS_PENDING) {
            throw new Exception('Only pending reservations can be rejected');
        }
        
        // Update reservation status to rejected using the correct method
        $success = $reservationService->reject(
            $reservationId,
            $_SESSION['user_id'],
            $rejectionReason
        );
        
        if (!$success) {
            throw new Exception('Failed to reject reservation');
        }
        
        $message = 'Reservation rejected successfully';
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => $message,
        'status' => $action === 'approve' ? ReservationService::STATUS_APPROVED : ReservationService::STATUS_REJECTED
    ]);
    
} catch (Exception $e) {
    // Log the error
    error_log('Error updating reservation status: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>