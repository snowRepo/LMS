<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';
require_once '../includes/SubscriptionManager.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has supervisor role
if (!is_logged_in() || $_SESSION['user_role'] !== 'supervisor') {
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

try {
    $db = Database::getInstance()->getConnection();
    
    // Get report data
    // Get total members count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE library_id = ? AND role = 'member'");
    $stmt->execute([$libraryId]);
    $totalMembers = $stmt->fetch()['total'];
    
    // Get total books count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM books WHERE library_id = ?");
    $stmt->execute([$libraryId]);
    $totalBooks = $stmt->fetch()['total'];
    
    // Get total librarians count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE library_id = ? AND role = 'librarian'");
    $stmt->execute([$libraryId]);
    $totalLibrarians = $stmt->fetch()['total'];
    
    // Get active members (attended today)
    $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as total FROM attendance WHERE library_id = ? AND attendance_date = CURDATE()");
    $stmt->execute([$libraryId]);
    $activeMembersToday = $stmt->fetch()['total'];
    
    // Get borrowed books count (active status)
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM borrowings br JOIN users u ON br.member_id = u.id WHERE u.library_id = ? AND br.status = 'active'");
    $stmt->execute([$libraryId]);
    $borrowedBooks = $stmt->fetch()['total'];
    
    // Get overdue books count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM borrowings br JOIN users u ON br.member_id = u.id WHERE u.library_id = ? AND br.status = 'overdue'");
    $stmt->execute([$libraryId]);
    $overdueBooks = $stmt->fetch()['total'];
    
    // Get top 5 most borrowed books
    $stmt = $db->prepare("
        SELECT b.title, b.isbn, COUNT(br.id) as borrow_count
        FROM borrowings br
        JOIN books b ON br.book_id = b.id
        JOIN users u ON br.member_id = u.id
        WHERE u.library_id = ?
        GROUP BY br.book_id
        ORDER BY borrow_count DESC
        LIMIT 5
    ");
    $stmt->execute([$libraryId]);
    $popularBooks = $stmt->fetchAll();
    
    // Get member attendance statistics for the last 7 days
    $stmt = $db->prepare("
        SELECT 
            DATE(attendance_date) as date,
            COUNT(DISTINCT user_id) as attendance_count
        FROM attendance 
        WHERE library_id = ? 
        AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(attendance_date)
        ORDER BY DATE(attendance_date)
    ");
    $stmt->execute([$libraryId]);
    $attendanceData = $stmt->fetchAll();
    
    // Create CSV content
    $csvContent = "Library Management System - Report Export\n";
    $csvContent .= "Generated on: " . date('Y-m-d H:i:s T') . "\n";
    $csvContent .= "Library ID: " . $libraryId . "\n\n";
    
    // Summary statistics
    $csvContent .= "Summary Statistics\n";
    $csvContent .= "Metric,Value\n";
    $csvContent .= "Total Members," . $totalMembers . "\n";
    $csvContent .= "Total Books," . $totalBooks . "\n";
    $csvContent .= "Total Librarians," . $totalLibrarians . "\n";
    $csvContent .= "Active Members Today," . $activeMembersToday . "\n";
    $csvContent .= "Borrowed Books," . $borrowedBooks . "\n";
    $csvContent .= "Overdue Books," . $overdueBooks . "\n\n";
    
    // Popular books
    $csvContent .= "Most Popular Books\n";
    $csvContent .= "Title,ISBN,Borrow Count\n";
    foreach ($popularBooks as $book) {
        $csvContent .= "\"" . str_replace('"', '""', $book['title']) . "\",";
        $csvContent .= "\"" . str_replace('"', '""', $book['isbn']) . "\",";
        $csvContent .= $book['borrow_count'] . "\n";
    }
    $csvContent .= "\n";
    
    // Attendance data
    $csvContent .= "Weekly Attendance Trend\n";
    $csvContent .= "Date,Attendance Count\n";
    foreach ($attendanceData as $data) {
        $csvContent .= $data['date'] . "," . $data['attendance_count'] . "\n";
    }
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="lms_report_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output CSV content
    echo $csvContent;
    exit;
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error generating CSV: ' . $e->getMessage()]);
    exit;
}
?>