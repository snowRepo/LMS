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
    
    // Get total supervisors count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE library_id = ? AND role = 'supervisor'");
    $stmt->execute([$libraryId]);
    $totalSupervisors = $stmt->fetch()['total'];
    
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
    
    // Get borrowing statistics
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM borrowings br JOIN users u ON br.member_id = u.id WHERE u.library_id = ?");
    $stmt->execute([$libraryId]);
    $totalBorrowings = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM borrowings br JOIN users u ON br.member_id = u.id WHERE u.library_id = ? AND br.status = 'active'");
    $stmt->execute([$libraryId]);
    $activeBorrowings = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM borrowings br JOIN users u ON br.member_id = u.id WHERE u.library_id = ? AND br.status = 'overdue'");
    $stmt->execute([$libraryId]);
    $dueBooks = $stmt->fetch()['total'];
    
    // Get top 3 most borrowed books
    $stmt = $db->prepare("
        SELECT b.title, b.isbn, COUNT(br.id) as borrow_count
        FROM borrowings br
        JOIN books b ON br.book_id = b.id
        JOIN users u ON br.member_id = u.id
        WHERE u.library_id = ?
        GROUP BY br.book_id
        ORDER BY borrow_count DESC
        LIMIT 3
    ");
    $stmt->execute([$libraryId]);
    $popularBooks = $stmt->fetchAll();
    
    // Get member attendance data with count
    $stmt = $db->prepare("
        SELECT 
            u.user_id,
            u.first_name,
            u.last_name,
            COUNT(a.user_id) as attendance_count
        FROM users u
        LEFT JOIN attendance a ON u.id = a.user_id AND a.library_id = ?
        WHERE u.library_id = ? AND u.role = 'member'
        GROUP BY u.id, u.user_id, u.first_name, u.last_name
        ORDER BY attendance_count DESC
    ");
    $stmt->execute([$libraryId, $libraryId]);
    $memberAttendance = $stmt->fetchAll();
    
    // Create CSV content
    $csvContent = "Library Management System - Report Export\n";
    $csvContent .= "Generated on: " . date('Y-m-d H:i:s T') . "\n";
    $csvContent .= "Library ID: " . $libraryId . "\n\n";
    
    // Overall Library Data
    $csvContent .= "Overall Library Data\n";
    $csvContent .= "Metric,Value\n";
    $csvContent .= "Total Supervisors," . $totalSupervisors . "\n";
    $csvContent .= "Total Librarians," . $totalLibrarians . "\n";
    $csvContent .= "Total Members," . $totalMembers . "\n";
    $csvContent .= "Total Books," . $totalBooks . "\n\n";
    
    // Borrowing Section
    $csvContent .= "Borrowing Statistics\n";
    $csvContent .= "Metric,Value\n";
    $csvContent .= "Total Borrowings," . $totalBorrowings . "\n";
    $csvContent .= "Active Borrowings," . $activeBorrowings . "\n";
    $csvContent .= "Due Books," . $dueBooks . "\n\n";
    
    // Attendance Section
    $csvContent .= "Member Attendance\n";
    $csvContent .= "Name,Attendance Count\n";
    foreach ($memberAttendance as $member) {
        $csvContent .= "\"" . str_replace('"', '""', $member['first_name'] . ' ' . $member['last_name']) . "\",";
        $csvContent .= $member['attendance_count'] . "\n";
    }
    $csvContent .= "\n";
    
    // Popular Books
    $csvContent .= "Most Popular Books (Top 3)\n";
    $csvContent .= "Title,ISBN,Borrow Count\n";
    foreach ($popularBooks as $book) {
        $csvContent .= "\"" . str_replace('"', '""', $book['title']) . "\",";
        $csvContent .= "\"" . str_replace('"', '""', $book['isbn']) . "\",";
        $csvContent .= $book['borrow_count'] . "\n";
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