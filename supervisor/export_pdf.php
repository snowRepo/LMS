<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';
require_once '../includes/SubscriptionManager.php';

// Load TCPDF
require_once '../vendor/autoload.php';
require_once '../vendor/tecnickcom/tcpdf/tcpdf.php';

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
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Library Management System');
    $pdf->SetTitle('LMS Report');
    $pdf->SetSubject('Library Report');
    $pdf->SetKeywords('LMS, Library, Report, Statistics');
    
    // Set default header data
    $pdf->SetHeaderData('', 0, 'Library Management System', 'Report Generated on ' . date('Y-m-d H:i:s T'), array(0,64,255), array(0,64,128));
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
    $pdf->Cell(0, 15, 'Library Management System Report', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Generated on: ' . date('Y-m-d H:i:s T'), 0, 1, 'C');
    $pdf->Cell(0, 10, 'Library ID: ' . $libraryId, 0, 1, 'C');
    $pdf->Ln(10);
    
    // Summary Statistics
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Summary Statistics', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 12);
    
    // Create table for summary statistics
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(80, 10, 'Metric', 1, 0, 'C');
    $pdf->Cell(80, 10, 'Value', 1, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(80, 10, 'Total Members', 1, 0, 'L');
    $pdf->Cell(80, 10, $totalMembers, 1, 1, 'C');
    
    $pdf->Cell(80, 10, 'Total Books', 1, 0, 'L');
    $pdf->Cell(80, 10, $totalBooks, 1, 1, 'C');
    
    $pdf->Cell(80, 10, 'Total Librarians', 1, 0, 'L');
    $pdf->Cell(80, 10, $totalLibrarians, 1, 1, 'C');
    
    $pdf->Cell(80, 10, 'Active Members Today', 1, 0, 'L');
    $pdf->Cell(80, 10, $activeMembersToday, 1, 1, 'C');
    
    $pdf->Cell(80, 10, 'Borrowed Books', 1, 0, 'L');
    $pdf->Cell(80, 10, $borrowedBooks, 1, 1, 'C');
    
    $pdf->Cell(80, 10, 'Overdue Books', 1, 0, 'L');
    $pdf->Cell(80, 10, $overdueBooks, 1, 1, 'C');
    
    $pdf->Ln(10);
    
    // Most Popular Books
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Most Popular Books', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 12);
    
    if (count($popularBooks) > 0) {
        // Create table for popular books
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(90, 10, 'Title', 1, 0, 'C');
        $pdf->Cell(40, 10, 'ISBN', 1, 0, 'C');
        $pdf->Cell(30, 10, 'Borrows', 1, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 10);
        foreach ($popularBooks as $book) {
            $pdf->Cell(90, 10, substr($book['title'], 0, 40) . (strlen($book['title']) > 40 ? '...' : ''), 1, 0, 'L');
            $pdf->Cell(40, 10, $book['isbn'], 1, 0, 'C');
            $pdf->Cell(30, 10, $book['borrow_count'], 1, 1, 'C');
        }
    } else {
        $pdf->Cell(0, 10, 'No borrowing data available', 0, 1, 'C');
    }
    
    $pdf->Ln(10);
    
    // Weekly Attendance Trend
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Weekly Attendance Trend', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 12);
    
    if (count($attendanceData) > 0) {
        // Create table for attendance data
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(90, 10, 'Date', 1, 0, 'C');
        $pdf->Cell(70, 10, 'Attendance Count', 1, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 10);
        foreach ($attendanceData as $data) {
            $pdf->Cell(90, 10, $data['date'], 1, 0, 'C');
            $pdf->Cell(70, 10, $data['attendance_count'], 1, 1, 'C');
        }
    } else {
        $pdf->Cell(0, 10, 'No attendance data available', 0, 1, 'C');
    }
    
    // Close and output PDF document
    $pdf->Output('lms_report_' . date('Y-m-d') . '.pdf', 'D');
    exit;
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error generating PDF: ' . $e->getMessage()]);
    exit;
}
?>