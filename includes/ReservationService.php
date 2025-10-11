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
            
            // Update reservation
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
            $this->sendStatusChangeNotification(
                $reservation['member_user_id'],  // Use the string user_id
                $reservationId,
                'approved',
                "Your reservation for '{$reservation['book_title']}' has been approved. Please pick it up by $pickupDeadline."
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
            
            if (empty($reason)) {
                throw new Exception('A rejection reason is required');
            }
            
            // Update reservation
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
            
            // Send notification to member (using the string user_id, not the integer id)
            $this->sendStatusChangeNotification(
                $reservation['member_user_id'],  // Use the string user_id
                $reservationId,
                'rejected',
                "Your reservation for '{$reservation['book_title']}' has been rejected. Reason: $reason"
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
        $this->notificationService->createNotification(
            $userId,
            $title,
            $message,
            'reservation_' . $action,
            "/member/reservations.php"
        );
        
        // Optional: Send email notification
        // $this->sendReservationEmail($userId, $title, $message);
    }
    
    // Add other methods like fulfill(), cancel(), checkExpiredReservations(), etc.
}
?>