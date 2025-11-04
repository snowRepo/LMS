<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';
require_once '../includes/SubscriptionManager.php';
require_once '../includes/SubscriptionCheck.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has supervisor role
if (!is_logged_in() || $_SESSION['user_role'] !== 'supervisor') {
    header('Location: ../login.php');
    exit;
}

// Check subscription status - redirect to expired page if subscription is not active
requireActiveSubscription();

// Check subscription status (keeping original logic for backward compatibility)
$subscriptionManager = new SubscriptionManager();
$libraryId = $_SESSION['library_id'];
$hasActiveSubscription = $subscriptionManager->hasActiveSubscription($libraryId);

if (!$hasActiveSubscription) {
    header('Location: ../subscription.php');
    exit;
}

// Handle export requests
if (isset($_GET['export'])) {
    $exportType = $_GET['export'];
    
    // Check if we're filtering by search or individual member
    $searchFilter = isset($_GET['search']) ? trim($_GET['search']) : '';
    $individualMemberId = isset($_GET['id']) ? trim($_GET['id']) : '';
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Get members for this library (filtered, individual, or all)
        if (!empty($individualMemberId)) {
            // Get specific member for individual export
            $stmt = $db->prepare("
                SELECT u.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as full_name,
                       l.library_name
                FROM users u
                LEFT JOIN libraries l ON u.library_id = l.id
                WHERE u.user_id = ? AND u.library_id = ? AND u.role = 'member'
            ");
            $stmt->execute([$individualMemberId, $libraryId]);
        } else if (!empty($searchFilter)) {
            // Get filtered members
            $stmt = $db->prepare("
                SELECT u.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as full_name,
                       l.library_name
                FROM users u
                LEFT JOIN libraries l ON u.library_id = l.id
                WHERE u.library_id = ? AND u.role = 'member'
                AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ? OR u.user_id LIKE ?)
                ORDER BY u.created_at DESC
            ");
            $searchTerm = "%$searchFilter%";
            $stmt->execute([$libraryId, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        } else {
            // Get all members
            $stmt = $db->prepare("
                SELECT u.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as full_name,
                       l.library_name
                FROM users u
                LEFT JOIN libraries l ON u.library_id = l.id
                WHERE u.library_id = ? AND u.role = 'member'
                ORDER BY u.created_at DESC
            ");
            $stmt->execute([$libraryId]);
        }
        $members = $stmt->fetchAll();
        
        if ($exportType === 'csv') {
            // Export as CSV
            $filenamePrefix = 'member_report';
            if (!empty($individualMemberId)) {
                // Get member name for filename
                $stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
                $stmt->execute([$individualMemberId]);
                $member = $stmt->fetch();
                if ($member) {
                    $filenamePrefix = strtolower(str_replace(' ', '_', $member['first_name'] . '_' . $member['last_name'] . '_report'));
                }
            } else if (!empty($searchFilter)) {
                $filenamePrefix = 'member_report_search_' . strtolower(str_replace(' ', '_', $searchFilter));
            }
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filenamePrefix . '_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            // Add CSV headers
            fputcsv($output, ['Member ID', 'Name', 'Username', 'Email', 'Phone', 'Address', 'Library', 'Status', 'Created At']);
            
            // Add data rows
            foreach ($members as $member) {
                fputcsv($output, [
                    $member['user_id'],
                    $member['full_name'],
                    $member['username'],
                    $member['email'],
                    $member['phone'] ?? 'N/A',
                    $member['address'] ?? 'N/A',
                    $member['library_name'] ?? 'N/A',
                    ucfirst($member['status']),
                    date('M j, Y', strtotime($member['created_at']))
                ]);
            }
            
            fclose($output);
            exit;
        } elseif ($exportType === 'pdf') {
            // Export as PDF
            require_once '../vendor/autoload.php';
            
            // Create new PDF document
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Set document information
            $pdfTitle = 'Member Report';
            if (!empty($individualMemberId)) {
                // Get member name for title
                $stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
                $stmt->execute([$individualMemberId]);
                $member = $stmt->fetch();
                if ($member) {
                    $pdfTitle = $member['first_name'] . ' ' . $member['last_name'] . ' Report';
                }
            } else if (!empty($searchFilter)) {
                $pdfTitle = 'Member Report - Search Results for: ' . $searchFilter;
            }
            
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetTitle($pdfTitle);
            
            // Remove default header/footer
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            
            // Set margins
            $pdf->SetMargins(15, 15, 15);
            
            // Set auto page breaks
            $pdf->SetAutoPageBreak(TRUE, 15);
            
            // Set font
            $pdf->SetFont('helvetica', '', 12);
            
            // Add a page
            $pdf->AddPage();
            
            // Set color for background
            $pdf->SetFillColor(240, 240, 240);
            
            // Title
            $pdf->SetFont('helvetica', 'B', 16);
            $pdfTitle = 'Member Report';
            if (!empty($individualMemberId)) {
                // Get member name for title
                $stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
                $stmt->execute([$individualMemberId]);
                $member = $stmt->fetch();
                if ($member) {
                    $pdfTitle = $member['first_name'] . ' ' . $member['last_name'] . ' Report';
                }
            } else if (!empty($searchFilter)) {
                $pdfTitle = 'Member Report - Search Results for: ' . $searchFilter;
            }
            $pdf->Cell(0, 10, $pdfTitle, 0, 1, 'C');
            $pdf->Ln(5);
            
            // Date
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 10, 'Generated on: ' . date('F j, Y'), 0, 1, 'R');
            $pdf->Ln(5);
            
            // Table header
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(30, 10, 'Member ID', 1, 0, 'C', 1);
            $pdf->Cell(40, 10, 'Name', 1, 0, 'C', 1);
            $pdf->Cell(30, 10, 'Username', 1, 0, 'C', 1);
            $pdf->Cell(40, 10, 'Email', 1, 0, 'C', 1);
            $pdf->Cell(25, 10, 'Phone', 1, 0, 'C', 1);
            $pdf->Cell(25, 10, 'Status', 1, 1, 'C', 1);
            
            // Table data
            $pdf->SetFont('helvetica', '', 9);
            foreach ($members as $member) {
                $pdf->Cell(30, 8, $member['user_id'], 1, 0, 'L');
                $pdf->Cell(40, 8, $member['full_name'], 1, 0, 'L');
                $pdf->Cell(30, 8, $member['username'], 1, 0, 'L');
                $pdf->Cell(40, 8, $member['email'], 1, 0, 'L');
                $pdf->Cell(25, 8, $member['phone'] ?? 'N/A', 1, 0, 'L');
                $pdf->Cell(25, 8, ucfirst($member['status']), 1, 1, 'C');
            }
            
            // Close and output PDF document
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="member_report_' . date('Y-m-d') . '.pdf"');
            $pdf->Output('member_report_' . date('Y-m-d') . '.pdf', 'D');
            exit;
        }
    } catch (Exception $e) {
        header('Location: member_reports.php?error=Error exporting data: ' . $e->getMessage());
        exit;
    }
}

// Get members for display
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Members per page
$offset = ($page - 1) * $limit;

// Check if we're viewing an individual member report
$individualMemberId = isset($_GET['id']) ? trim($_GET['id']) : '';

try {
    $db = Database::getInstance()->getConnection();
    
    // Get total members count for pagination
    if (!empty($search)) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM users u 
            WHERE u.library_id = ? 
            AND u.role = 'member'
            AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ? OR u.user_id LIKE ?)
        ");
        $searchTerm = "%$search%";
        $stmt->execute([$libraryId, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE library_id = ? AND role = 'member'");
        $stmt->execute([$libraryId]);
    }
    $totalMembers = $stmt->fetch()['total'];
    $totalPages = ceil($totalMembers / $limit);
    
    // Get members for current page
    if (!empty($search)) {
        $stmt = $db->prepare("
            SELECT u.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as full_name,
                   l.library_name
            FROM users u
            LEFT JOIN libraries l ON u.library_id = l.id
            WHERE u.library_id = ? 
            AND u.role = 'member'
            AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ? OR u.user_id LIKE ?)
            ORDER BY u.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $searchTerm = "%$search%";
        $stmt->execute([$libraryId, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit, $offset]);
    } else {
        $stmt = $db->prepare("
            SELECT u.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as full_name,
                   l.library_name
            FROM users u
            LEFT JOIN libraries l ON u.library_id = l.id
            WHERE u.library_id = ? 
            AND u.role = 'member'
            ORDER BY u.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$libraryId, $limit, $offset]);
    }
    $members = $stmt->fetchAll();
    
} catch (Exception $e) {
    die('Error loading members: ' . $e->getMessage());
}

$pageTitle = 'Member Reports';
if (!empty($individualMemberId)) {
    // Get member name for page title
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
        $stmt->execute([$individualMemberId]);
        $member = $stmt->fetch();
        if ($member) {
            $pageTitle = $member['first_name'] . ' ' . $member['last_name'] . ' Report';
        }
    } catch (Exception $e) {
        // Handle error silently
    }
} else if (!empty($search)) {
    $pageTitle = 'Member Reports - Search Results for: ' . $search;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - LMS</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Supervisor Navbar CSS -->
    <link rel="stylesheet" href="css/supervisor_navbar.css">
    <!-- Toast CSS -->
    <link rel="stylesheet" href="css/toast.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 0;
        }
        
        html, body {
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-header h1 {
            color: #212529;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #6c757d;
            font-size: 1.1rem;
        }

        .content-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .card-header h2 {
            color: #212529;
            margin: 0;
        }

        .filter-section {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
            justify-content: center;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 200px;
        }

        .filter-group label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 0.5rem;
        }

        .filter-input {
            padding: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 1rem;
        }

        .search-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            max-width: 600px;
            margin: 0 auto 1.5rem auto;
            justify-content: center;
        }

        .search-input {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 1rem;
            max-width: 400px;
        }

        .search-btn, .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .search-btn {
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            color: white;
            padding: 0.75rem 1rem;
        }

        .search-btn:hover {
            background: linear-gradient(135deg, #2980B9 0%, #2573A7 100%);
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }

        .export-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .table-container {
            overflow-x: auto;
        }

        .members-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            font-size: 0.85rem;
        }

        .members-table th,
        .members-table td {
            padding: 0.75rem;
            text-align: center;
            border-bottom: 1px solid #dee2e6;
        }

        .members-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
            text-align: center;
        }

        .members-table tr:hover {
            background-color: #f8f9fa;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a,
        .pagination span {
            padding: 0.5rem 0.75rem;
            border: 1px solid #dee2e6;
            text-decoration: none;
            color: #495057;
            border-radius: 4px;
        }

        .pagination a:hover {
            background-color: #e9ecef;
        }

        .pagination .current {
            background-color: #3498DB;
            color: white;
            border-color: #3498DB;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-active {
            background-color: #006400;
            color: white;
        }

        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .member-avatar-small {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.9rem;
            margin: 0 auto;
        }

        @media (max-width: 768px) {
            .export-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .filter-section {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                min-width: unset;
            }
            
            .members-table th,
            .members-table td {
                padding: 0.5rem;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/supervisor_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-file-alt"></i> Member Reports</h1>
            <p>View and export detailed member information</p>
        </div>

        <!-- Toast Container -->
        <div id="toast-container"></div>

        <div class="content-card">
            <div class="card-header">
                <h2>Member Details Report</h2>
                <a href="reports.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Reports
                </a>
            </div>
            
            <!-- Search Bar -->
            <div class="search-bar">
                <form method="GET" style="display: flex; width: 100%; max-width: 500px; gap: 1rem;">
                    <input type="text" 
                           name="search" 
                           class="search-input" 
                           placeholder="Search members..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                        <span class="search-btn-text">Search</span>
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="member_reports.php" class="btn btn-primary" style="padding: 0.75rem 1rem;">
                            <i class="fas fa-times"></i>
                            <span class="search-btn-text">Clear</span>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            

            
            <div class="table-container">
                <table class="members-table">
                    <thead>
                        <tr>
                            <th>Member ID</th>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Library</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($members) > 0): ?>
                            <?php foreach ($members as $member): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($member['user_id']); ?></td>
                                    <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($member['username']); ?></td>
                                    <td><?php echo htmlspecialchars($member['email']); ?></td>
                                    <td><?php echo htmlspecialchars($member['phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($member['library_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $member['status']; ?>">
                                            <?php echo ucfirst($member['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($member['created_at'])); ?></td>
                                    <td>
                                        <a href="detailed_member_report.php?id=<?php echo urlencode($member['user_id']); ?>" 
                                           class="btn btn-primary" 
                                           title="View Detailed Report">
                                            <i class="fas fa-file-alt"></i> View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">
                                    <?php echo !empty($search) ? 'No members found matching your search.' : 'No members in the library yet.'; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">&laquo; First</a>
                        <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">&lsaquo; Prev</a>
                    <?php endif; ?>
                    
                    <?php 
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Next &rsaquo;</a>
                        <a href="?page=<?php echo $totalPages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Last &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Toast notification function
        function showToast(message, type = 'info', duration = 5000) {
            const container = document.getElementById('toast-container');
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            // Add icon based on type
            let icon = '';
            switch(type) {
                case 'success':
                    icon = '<i class="fas fa-check-circle"></i>';
                    break;
                case 'error':
                    icon = '<i class="fas fa-exclamation-circle"></i>';
                    break;
                case 'warning':
                    icon = '<i class="fas fa-exclamation-triangle"></i>';
                    break;
                case 'info':
                    icon = '<i class="fas fa-info-circle"></i>';
                    break;
            }
            
            toast.innerHTML = `
                <div class="toast-content">
                    <span class="toast-icon">${icon}</span>
                    <span class="toast-message">${message}</span>
                </div>
                <button class="toast-close">&times;</button>
            `;
            
            // Add close button event
            const closeBtn = toast.querySelector('.toast-close');
            closeBtn.addEventListener('click', function() {
                toast.classList.add('hide');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            });
            
            // Add toast to container
            container.appendChild(toast);
            
            // Trigger animation
            setTimeout(() => {
                toast.classList.add('show');
            }, 10);
            
            // Auto remove after duration
            if (duration > 0) {
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.classList.add('hide');
                        setTimeout(() => {
                            if (toast.parentNode) {
                                toast.parentNode.removeChild(toast);
                            }
                        }, 300);
                    }
                }, duration);
            }
        }
        
        // Check for URL parameters and show toast notifications
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const success = urlParams.get('success');
            const error = urlParams.get('error');
            const message = urlParams.get('message');
            const messageType = urlParams.get('message_type') || 'info';
            
            if (success) {
                showToast(success, 'success');
                // Remove the success parameter from URL without reloading
                urlParams.delete('success');
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({}, document.title, newUrl);
            }
            
            if (error) {
                showToast(error, 'error');
                // Remove the error parameter from URL without reloading
                urlParams.delete('error');
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({}, document.title, newUrl);
            }
            
            if (message) {
                showToast(message, messageType);
                // Remove the message parameter from URL without reloading
                urlParams.delete('message');
                urlParams.delete('message_type');
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({}, document.title, newUrl);
            }
        });
    </script>
</body>
</html>