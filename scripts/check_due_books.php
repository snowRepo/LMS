<?php
/**
 * Script to check for books due in 24 hours and send notifications
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
    
    // Find all active borrowings that are due in exactly 24 hours
    // We check for books due tomorrow (DATE_ADD(CURDATE(), INTERVAL 1 DAY))
    $stmt = $db->prepare("
        SELECT b.id as borrowing_id, b.book_id, b.member_id, b.due_date,
               bk.title as book_title,
               CONCAT(u.first_name, ' ', u.last_name) as member_name,
               u.user_id as member_user_id,
               u.email as member_email
        FROM borrowings b
        JOIN books bk ON b.book_id = bk.id
        JOIN users u ON b.member_id = u.id
        WHERE b.status = 'active' 
        AND b.due_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
    ");
    $stmt->execute();
    $dueBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($dueBooks) > 0) {
        $notificationService = new NotificationService();
        
        // Send notification to each member with due books
        foreach ($dueBooks as $book) {
            $message = "Reminder: The book '{$book['book_title']}' is due tomorrow ({$book['due_date']}). Please return it on time to avoid late fees.";
            $notificationService->createNotification(
                $book['member_user_id'],
                'Book Due Reminder',
                $message,
                'warning',
                "/member/borrowing.php"
            );
        }
        
        echo "Sent reminders for " . count($dueBooks) . " books due tomorrow.\n";
    } else {
        echo "No books are due tomorrow.\n";
    }
    
    $db->commit();
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error checking due books: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}