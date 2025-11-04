<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';

// Load TCPDF
require_once '../vendor/autoload.php';
require_once '../vendor/tecnickcom/tcpdf/tcpdf.php';

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
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Library Management System');
    $pdf->SetTitle('LMS Admin Report');
    $pdf->SetSubject('Admin Report');
    $pdf->SetKeywords('LMS, Library, Admin, Report, Statistics');
    
    // Set default header data
    $pdf->SetHeaderData('', 0, 'Library Management System - Admin Report', 'Generated on ' . date('Y-m-d H:i:s T'), array(0,64,255), array(0,64,128));
    $pdf->setFooterData(array(0,64,0), array(0,64,128));
    
    // Set header and footer fonts
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    
    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    
    // Set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    
    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    
    // Set font
    $pdf->SetFont('helvetica', 'B', 20);
    
    // Add a page
    $pdf->AddPage();
    
    // Title
    $pdf->Cell(0, 15, 'Library Management System - Admin Report', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Generated on: ' . date('Y-m-d H:i:s T'), 0, 1, 'C');
    $pdf->Ln(10);
    
    // System Overview
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'System Overview', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 12);
    
    // Create table for system overview
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(100, 10, 'Metric', 1, 0, 'C');
    $pdf->Cell(60, 10, 'Value', 1, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(100, 10, 'Total Libraries', 1, 0, 'L');
    $pdf->Cell(60, 10, $totalLibraries, 1, 1, 'C');
    
    $pdf->Cell(100, 10, 'Total Members', 1, 0, 'L');
    $pdf->Cell(60, 10, $totalMembers, 1, 1, 'C');
    
    $pdf->Cell(100, 10, 'Total Librarians', 1, 0, 'L');
    $pdf->Cell(60, 10, $totalLibrarians, 1, 1, 'C');
    
    $pdf->Cell(100, 10, 'Total Supervisors', 1, 0, 'L');
    $pdf->Cell(60, 10, $totalSupervisors, 1, 1, 'C');
    
    $pdf->Cell(100, 10, 'Total Books', 1, 0, 'L');
    $pdf->Cell(60, 10, $totalBooks, 1, 1, 'C');
    
    $pdf->Cell(100, 10, 'Active Subscriptions', 1, 0, 'L');
    $pdf->Cell(60, 10, $activeSubscriptions, 1, 1, 'C');
    
    $pdf->Ln(10);
    
    // Library Details
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Library Details', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 12);
    
    foreach ($allLibraries as $library) {
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, $library['library_name'], 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 8, 'Email: ' . $library['email'], 0, 1, 'L');
        $pdf->Cell(0, 8, 'Phone: ' . $library['phone'], 0, 1, 'L');
        $pdf->Cell(0, 8, 'Address: ' . $library['address'], 0, 1, 'L');
        
        // Library statistics table
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(30, 8, 'Supervisors', 1, 0, 'C');
        $pdf->Cell(30, 8, 'Librarians', 1, 0, 'C');
        $pdf->Cell(30, 8, 'Members', 1, 0, 'C');
        $pdf->Cell(30, 8, 'Books', 1, 0, 'C');
        $pdf->Cell(30, 8, 'Borrowed', 1, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(30, 8, $library['supervisor_count'], 1, 0, 'C');
        $pdf->Cell(30, 8, $library['librarian_count'], 1, 0, 'C');
        $pdf->Cell(30, 8, $library['member_count'], 1, 0, 'C');
        $pdf->Cell(30, 8, $library['book_count'], 1, 0, 'C');
        $pdf->Cell(30, 8, $library['borrowed_books_count'], 1, 1, 'C');
        
        $pdf->Ln(5);
    }
    
    // Subscription Details
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Subscription Details', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 12);
    
    if (count($librarySubscriptions) > 0) {
        // Create table for subscription details
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(40, 10, 'Library', 1, 0, 'C');
        $pdf->Cell(25, 10, 'Plan', 1, 0, 'C');
        $pdf->Cell(25, 10, 'Status', 1, 0, 'C');
        $pdf->Cell(30, 10, 'Start Date', 1, 0, 'C');
        $pdf->Cell(30, 10, 'End Date', 1, 0, 'C');
        $pdf->Cell(25, 10, 'Auto Renew', 1, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 8);
        foreach ($librarySubscriptions as $subscription) {
            $pdf->Cell(40, 8, substr($subscription['library_name'], 0, 20) . (strlen($subscription['library_name']) > 20 ? '...' : ''), 1, 0, 'L');
            $pdf->Cell(25, 8, ucfirst($subscription['plan_type']), 1, 0, 'C');
            $pdf->Cell(25, 8, ucfirst($subscription['status']), 1, 0, 'C');
            $pdf->Cell(30, 8, date('M j, Y', strtotime($subscription['start_date'])), 1, 0, 'C');
            $pdf->Cell(30, 8, date('M j, Y', strtotime($subscription['end_date'])), 1, 0, 'C');
            $pdf->Cell(25, 8, $subscription['auto_renew'] ? 'Yes' : 'No', 1, 1, 'C');
        }
    } else {
        $pdf->Cell(0, 10, 'No subscription data available', 0, 1, 'C');
    }
    
    $pdf->Ln(10);
    
    // Attendance Summary
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Attendance Summary', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 12);
    
    if (count($libraryAttendance) > 0) {
        // Create table for attendance data
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(100, 10, 'Library', 1, 0, 'C');
        $pdf->Cell(60, 10, 'Attendance Count', 1, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 10);
        foreach ($libraryAttendance as $attendance) {
            $pdf->Cell(100, 8, $attendance['library_name'], 1, 0, 'L');
            $pdf->Cell(60, 8, $attendance['attendance_count'], 1, 1, 'C');
        }
    } else {
        $pdf->Cell(0, 10, 'No attendance data available', 0, 1, 'C');
    }
    
    // Close and output PDF document
    $pdf->Output('lms_admin_report_' . date('Y-m-d') . '.pdf', 'D');
    exit;
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error generating PDF: ' . $e->getMessage()]);
    exit;
}
?>