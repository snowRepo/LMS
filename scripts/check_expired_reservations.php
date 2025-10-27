<?php
/**
 * Script to check and update expired reservations
 * This script should be run as a cron job daily
 */

define('LMS_ACCESS', true);

// Load configuration
require_once __DIR__ . '/../includes/EnvLoader.php';
EnvLoader::load();
include __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/NotificationService.php';

try {
    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();
    
    // Find all pending reservations that have expired
    $stmt = $db->prepare("
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
    
    if (count($expiredReservations) > 0) {
        // Update each expired reservation
        foreach ($expiredReservations as $reservation) {
            // Update reservation status to expired
            $updateStmt = $db->prepare("
                UPDATE reservations 
                SET status = 'expired',
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$reservation['id']]);
            
            // Increment available copies when reservation expires (to restore the count)
            $updateStmt = $db->prepare("
                UPDATE books 
                SET available_copies = available_copies + 1 
                WHERE id = ?
            ");
            $updateStmt->execute([$reservation['book_id']]);
            
            // Send notification to member
            $notificationService = new NotificationService();
            $message = "Your reservation for '{$reservation['book_title']}' has expired as it was not actioned within the 7-day period.";
            $notificationService->createNotification(
                $reservation['member_user_id'],
                'Reservation Expired',
                $message,
                'warning',
                "/member/reservations.php"
            );
        }
        
        echo "Processed " . count($expiredReservations) . " expired reservations.\n";
    } else {
        echo "No expired reservations found.\n";
    }
    
    $db->commit();
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error checking expired reservations: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}