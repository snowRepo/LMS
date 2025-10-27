<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/NotificationService.php';

class ReservationService {
    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_FULFILLED = 'fulfilled';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';
    
    private $db;
    private $notificationService;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->notificationService = new NotificationService();
    }
    
    /**
     * Approve a reservation
     */
    public function approve($reservationId, $librarianId, $notes = '') {
        try {
            $this->db->beginTransaction();
            
            // Get reservation details
            $reservation = $this->getReservation($reservationId);
            
            // Validate status transition
            if ($reservation['status'] !== self::STATUS_PENDING) {
                throw new Exception('Only pending reservations can be approved');
            }
            
            // Update reservation (no change to available copies - already decremented when reservation was made)
            $pickupDeadline = date('Y-m-d', strtotime('+7 days'));
            $stmt = $this->db->prepare("
                UPDATE reservations 
                SET status = ?,
                    actioned_by = ?,
                    actioned_at = NOW(),
                    pickup_deadline = ?,
                    librarian_notes = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                self::STATUS_APPROVED,
                $librarianId,
                $pickupDeadline,
                $notes,
                $reservationId
            ]);
            
            // Send notification to member (using the string user_id, not the integer id)
            $message = "Your reservation for '{$reservation['book_title']}' has been approved. Please pick it up by $pickupDeadline.";
            if (!empty($notes)) {
                $message .= " Note from librarian: $notes";
            }
            $this->sendStatusChangeNotification(
                $reservation['member_user_id'],  // Use the string user_id
                $reservationId,
                'approved',
                $message
            );
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error approving reservation: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Reject a reservation
     */
    public function reject($reservationId, $librarianId, $reason = '') {
        try {
            $this->db->beginTransaction();
            
            // Get reservation details
            $reservation = $this->getReservation($reservationId);
            
            // Validate status transition
            if ($reservation['status'] !== self::STATUS_PENDING) {
                throw new Exception('Only pending reservations can be rejected');
            }
            
            // Reason is optional for rejection, use default message if empty
            if (empty($reason)) {
                $reason = 'Reservation rejected by librarian.';
            }
            
            // Update reservation - store reason only in rejection_reason column for rejection
            $stmt = $this->db->prepare("
                UPDATE reservations 
                SET status = ?,
                    actioned_by = ?,
                    actioned_at = NOW(),
                    rejection_reason = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                self::STATUS_REJECTED,
                $librarianId,
                $reason,
                $reservationId
            ]);
            
            // Increment available copies when reservation is rejected (to restore the count)
            $stmt = $this->db->prepare("
                UPDATE books 
                SET available_copies = available_copies + 1 
                WHERE id = ?
            ");
            $stmt->execute([$reservation['book_id']]);
            
            // Send notification to member (using the string user_id, not the integer id)
            $message = "Your reservation for '{$reservation['book_title']}' has been rejected.";
            if (!empty($reason) && $reason !== 'Reservation rejected by librarian.') {
                $message .= " Reason: $reason";
            } else {
                $message .= " Please contact the library for more information.";
            }
            $this->sendStatusChangeNotification(
                $reservation['member_user_id'],  // Use the string user_id
                $reservationId,
                'rejected',
                $message
            );
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error rejecting reservation: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get reservation details with related data
     */
    public function getReservation($reservationId) {
        $stmt = $this->db->prepare("
            SELECT r.*, 
                   b.title as book_title, 
                   b.isbn,
                   b.cover_image,
                   b.category_id,
                   b.book_id as book_internal_id,
                   c.name as category_name,
                   CONCAT(u.first_name, ' ', u.last_name) as member_name,
                   u.email as member_email,
                   u.phone as member_phone,
                   u.user_id as member_user_id  -- Get the string user_id for notifications
            FROM reservations r
            JOIN books b ON r.book_id = b.id
            JOIN users u ON r.member_id = u.id
            LEFT JOIN categories c ON b.category_id = c.id
            WHERE r.id = ?
        ");
        $stmt->execute([$reservationId]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reservation) {
            throw new Exception('Reservation not found');
        }
        
        return $reservation;
    }
    
    /**
     * Send notification for status changes
     */
    public function sendStatusChangeNotification($userId, $reservationId, $action, $message) {
        $title = "Reservation " . ucfirst($action);
        // Map reservation actions to valid notification types
        $type = 'info';
        if ($action === 'approved') {
            $type = 'success';
        } elseif ($action === 'rejected') {
            $type = 'warning';
        } elseif ($action === 'expired' || $action === 'cancelled') {
            $type = 'error';
        }
        
        $this->notificationService->createNotification(
            $userId,
            $title,
            $message,
            $type,
            "/member/reservations.php"
        );
        
        // Optional: Send email notification
        // $this->sendReservationEmail($userId, $title, $message);
    }
    
    /**
     * Check and update expired reservations
     */
    public function checkExpiredReservations() {
        try {
            $this->db->beginTransaction();
            
            // Find all pending reservations that have expired
            $stmt = $this->db->prepare("
                SELECT r.id, r.member_id, r.book_id, b.title as book_title, 
                       CONCAT(u.first_name, ' ', u.last_name) as member_name,
                       u.user_id as member_user_id
                FROM reservations r
                JOIN books b ON r.book_id = b.id
                JOIN users u ON r.member_id = u.id
                WHERE r.status = 'pending' AND r.expiry_date < CURDATE()
            ");
            $stmt->execute();
            $expiredReservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $updatedCount = 0;
            
            if (count($expiredReservations) > 0) {
                // Update each expired reservation
                foreach ($expiredReservations as $reservation) {
                    // Update reservation status to expired
                    $updateStmt = $this->db->prepare("
                        UPDATE reservations 
                        SET status = 'expired',
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$reservation['id']]);
                    
                    // Increment available copies when reservation expires (to restore the count)
                    $updateStmt = $this->db->prepare("
                        UPDATE books 
                        SET available_copies = available_copies + 1 
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$reservation['book_id']]);
                    
                    // Send notification to member
                    $message = "Your reservation for '{$reservation['book_title']}' has expired as it was not actioned within the 7-day period.";
                    $this->sendStatusChangeNotification(
                        $reservation['member_user_id'],
                        $reservation['id'],
                        'expired',
                        $message
                    );
                    
                    $updatedCount++;
                }
            }
            
            $this->db->commit();
            return $updatedCount;
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error checking expired reservations: " . $e->getMessage());
            throw $e;
        }
    }
    
    // Add other methods like fulfill(), cancel(), etc.
}
?>