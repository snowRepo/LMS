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
    
    // Overall Library Data
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Overall Library Data', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 12);
    
    // Create table for overall library data
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(80, 10, 'Metric', 1, 0, 'C');
    $pdf->Cell(80, 10, 'Value', 1, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(80, 10, 'Total Supervisors', 1, 0, 'L');
    $pdf->Cell(80, 10, $totalSupervisors, 1, 1, 'C');
    
    $pdf->Cell(80, 10, 'Total Librarians', 1, 0, 'L');
    $pdf->Cell(80, 10, $totalLibrarians, 1, 1, 'C');
    
    $pdf->Cell(80, 10, 'Total Members', 1, 0, 'L');
    $pdf->Cell(80, 10, $totalMembers, 1, 1, 'C');
    
    $pdf->Cell(80, 10, 'Total Books', 1, 0, 'L');
    $pdf->Cell(80, 10, $totalBooks, 1, 1, 'C');
    
    $pdf->Ln(10);
    
    // Borrowing Section
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Borrowing Statistics', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 12);
    
    // Create table for borrowing statistics
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(80, 10, 'Metric', 1, 0, 'C');
    $pdf->Cell(80, 10, 'Value', 1, 1, 'C');
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(80, 10, 'Total Borrowings', 1, 0, 'L');
    $pdf->Cell(80, 10, $totalBorrowings, 1, 1, 'C');
    
    $pdf->Cell(80, 10, 'Active Borrowings', 1, 0, 'L');
    $pdf->Cell(80, 10, $activeBorrowings, 1, 1, 'C');
    
    $pdf->Cell(80, 10, 'Due Books', 1, 0, 'L');
    $pdf->Cell(80, 10, $dueBooks, 1, 1, 'C');
    
    $pdf->Ln(10);
    
    // Attendance Section
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Member Attendance', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 12);
    
    if (count($memberAttendance) > 0) {
        // Create table for member attendance
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(90, 10, 'Name', 1, 0, 'C');
        $pdf->Cell(70, 10, 'Attendance Count', 1, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 10);
        foreach ($memberAttendance as $member) {
            $pdf->Cell(90, 10, $member['first_name'] . ' ' . $member['last_name'], 1, 0, 'L');
            $pdf->Cell(70, 10, $member['attendance_count'], 1, 1, 'C');
        }
    } else {
        $pdf->Cell(0, 10, 'No attendance data available', 0, 1, 'C');
    }
    
    $pdf->Ln(10);
    
    // Most Popular Books (Top 3)
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Most Popular Books (Top 3)', 0, 1, 'L');
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
    
    // Close and output PDF document
    $pdf->Output('lms_report_' . date('Y-m-d') . '.pdf', 'D');
    exit;
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error generating PDF: ' . $e->getMessage()]);
    exit;
}
?>