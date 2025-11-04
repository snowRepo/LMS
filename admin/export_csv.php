<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has admin role
if (!is_logged_in() || $_SESSION['user_role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Get report data - same as in reports.php
    // Get total libraries count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM libraries WHERE deleted_at IS NULL");
    $stmt->execute();
    $totalLibraries = $stmt->fetch()['total'];
    
    // Get total members count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'member'");
    $stmt->execute();
    $totalMembers = $stmt->fetch()['total'];
    
    // Get total librarians count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'librarian'");
    $stmt->execute();
    $totalLibrarians = $stmt->fetch()['total'];
    
    // Get total supervisors count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'supervisor'");
    $stmt->execute();
    $totalSupervisors = $stmt->fetch()['total'];
    
    // Get total books count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM books");
    $stmt->execute();
    $totalBooks = $stmt->fetch()['total'];
    
    // Get active subscriptions count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM subscriptions WHERE status = 'active'");
    $stmt->execute();
    $activeSubscriptions = $stmt->fetch()['total'];
    
    // Get all libraries with their details for report
    $stmt = $db->prepare("
        SELECT 
            l.id,
            l.library_name,
            l.email,
            l.phone,
            l.address,
            (SELECT COUNT(*) FROM users WHERE library_id = l.id AND role = 'supervisor') as supervisor_count,
            (SELECT COUNT(*) FROM users WHERE library_id = l.id AND role = 'librarian') as librarian_count,
            (SELECT COUNT(*) FROM users WHERE library_id = l.id AND role = 'member') as member_count,
            (SELECT COUNT(*) FROM books WHERE library_id = l.id) as book_count,
            (SELECT COUNT(*) FROM borrowings br JOIN users u ON br.member_id = u.id WHERE u.library_id = l.id AND br.status IN ('active', 'overdue')) as borrowed_books_count
        FROM libraries l
        WHERE l.deleted_at IS NULL
        ORDER BY l.library_name
    ");
    $stmt->execute();
    $allLibraries = $stmt->fetchAll();
    
    // Get subscription details for each library
    $stmt = $db->prepare("
        SELECT 
            l.library_name,
            s.plan_type,
            s.status,
            s.start_date,
            s.end_date,
            s.auto_renew
        FROM subscriptions s
        JOIN libraries l ON s.library_id = l.id
        WHERE l.deleted_at IS NULL
        ORDER BY l.library_name
    ");
    $stmt->execute();
    $librarySubscriptions = $stmt->fetchAll();
    
    // Get attendance data for each library
    $stmt = $db->prepare("
        SELECT 
            l.library_name,
            COUNT(a.id) as attendance_count
        FROM attendance a
        JOIN libraries l ON a.library_id = l.id
        WHERE l.deleted_at IS NULL
        GROUP BY l.id, l.library_name
        ORDER BY l.library_name
    ");
    $stmt->execute();
    $libraryAttendance = $stmt->fetchAll();
    
    // Create CSV content
    $csvContent = "Library Management System - Admin Report Export\n";
    $csvContent .= "Generated on: " . date('Y-m-d H:i:s T') . "\n\n";
    
    // System Overview
    $csvContent .= "System Overview\n";
    $csvContent .= "Metric,Value\n";
    $csvContent .= "Total Libraries," . $totalLibraries . "\n";
    $csvContent .= "Total Members," . $totalMembers . "\n";
    $csvContent .= "Total Librarians," . $totalLibrarians . "\n";
    $csvContent .= "Total Supervisors," . $totalSupervisors . "\n";
    $csvContent .= "Total Books," . $totalBooks . "\n";
    $csvContent .= "Active Subscriptions," . $activeSubscriptions . "\n\n";
    
    // Library Details
    $csvContent .= "Library Details\n";
    $csvContent .= "Library Name,Email,Phone,Address,Supervisors,Librarians,Members,Books,Borrowed Books\n";
    foreach ($allLibraries as $library) {
        $csvContent .= "\"" . str_replace('"', '""', $library['library_name']) . "\",";
        $csvContent .= "\"" . str_replace('"', '""', $library['email']) . "\",";
        $csvContent .= "\"" . str_replace('"', '""', $library['phone']) . "\",";
        $csvContent .= "\"" . str_replace('"', '""', $library['address']) . "\",";
        $csvContent .= $library['supervisor_count'] . ",";
        $csvContent .= $library['librarian_count'] . ",";
        $csvContent .= $library['member_count'] . ",";
        $csvContent .= $library['book_count'] . ",";
        $csvContent .= $library['borrowed_books_count'] . "\n";
    }
    $csvContent .= "\n";
    
    // Subscription Details
    $csvContent .= "Subscription Details\n";
    $csvContent .= "Library,Plan,Status,Start Date,End Date,Auto Renew\n";
    foreach ($librarySubscriptions as $subscription) {
        $csvContent .= "\"" . str_replace('"', '""', $subscription['library_name']) . "\",";
        $csvContent .= "\"" . str_replace('"', '""', ucfirst($subscription['plan_type'])) . "\",";
        $csvContent .= "\"" . str_replace('"', '""', ucfirst($subscription['status'])) . "\",";
        $csvContent .= "\"" . str_replace('"', '""', date('M j, Y', strtotime($subscription['start_date']))) . "\",";
        $csvContent .= "\"" . str_replace('"', '""', date('M j, Y', strtotime($subscription['end_date']))) . "\",";
        $csvContent .= "\"" . str_replace('"', '""', $subscription['auto_renew'] ? 'Yes' : 'No') . "\"\n";
    }
    $csvContent .= "\n";
    
    // Attendance Summary
    $csvContent .= "Attendance Summary\n";
    $csvContent .= "Library,Attendance Count\n";
    foreach ($libraryAttendance as $attendance) {
        $csvContent .= "\"" . str_replace('"', '""', $attendance['library_name']) . "\",";
        $csvContent .= $attendance['attendance_count'] . "\n";
    }
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="lms_admin_report_' . date('Y-m-d') . '.csv"');
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