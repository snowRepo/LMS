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
    header('Location: ../login.php');
    exit;
}

// Get library ID from URL parameter
$libraryId = isset($_GET['library_id']) ? (int)$_GET['library_id'] : 0;

if ($libraryId <= 0) {
    header('Location: borrowing_reports.php');
    exit;
}

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Items per page
$offset = ($page - 1) * $limit;

try {
    $db = Database::getInstance()->getConnection();
    
    // Get library information
    $stmt = $db->prepare("SELECT library_name, library_code, address, email FROM libraries WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$libraryId]);
    $library = $stmt->fetch();
    
    if (!$library) {
        header('Location: borrowing_reports.php');
        exit;
    }
    
    // Get borrowings for this library with pagination
    $stmt = $db->prepare("
        SELECT 
            b.transaction_id,
            bk.title as book_title,
            CONCAT(u.first_name, ' ', u.last_name) as member_name,
            u.email as member_email,
            b.issue_date,
            b.due_date,
            b.return_date,
            b.status,
            b.fine_amount
        FROM borrowings b
        JOIN books bk ON b.book_id = bk.id
        JOIN users u ON b.member_id = u.id
        JOIN users lu ON b.issued_by = lu.id
        WHERE u.library_id = ? 
        ORDER BY b.issue_date DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$libraryId, $limit, $offset]);
    $borrowings = $stmt->fetchAll();
    
    // Get total borrowings count for this library
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_borrowings
        FROM borrowings b
        JOIN users u ON b.member_id = u.id
        WHERE u.library_id = ?
    ");
    $stmt->execute([$libraryId]);
    $totalBorrowings = $stmt->fetch()['total_borrowings'];
    
    // Calculate total pages
    $totalPages = ceil($totalBorrowings / $limit);
    
} catch (Exception $e) {
    die('Error loading borrowing data: ' . $e->getMessage());
}

$pageTitle = 'Library Borrowings';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - LMS</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #0066cc; /* macOS deeper blue */
            --primary-dark: #0052a3;
            --secondary-color: #f8f9fa;
            --success-color: #1b5e20; /* Deep green for active status */
            --danger-color: #c62828; /* Deep red for error states */
            --warning-color: #F39C12;
            --info-color: #495057;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --gray-900: #212529;
            --border-radius: 12px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --box-shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s ease;
        }
        
        body {
            background: #f8f9fa;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #495057;
            padding-top: 60px; /* Space for navbar */
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            margin-bottom: 30px;
            text-align: center;
        }

        .page-header h1 {
            color: #2c3e50;
            font-size: 1.8rem;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #6c757d;
            font-size: 1.1rem;
        }

        .content-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 25px;
            margin-bottom: 2rem;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .card-header h2 {
            color: var(--gray-900);
            margin: 0;
        }
        
        .library-info {
            text-align: center;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .library-info h3 {
            margin-top: 0;
            color: var(--primary-color);
            font-size: 1.5rem;
        }
        
        .library-details {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
            min-width: 150px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .detail-value {
            font-size: 1.1rem;
            color: #212529;
        }
        
        .borrowings-section {
            margin-top: 2rem;
        }
        
        .section-title {
            text-align: center;
            color: var(--gray-900);
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .table-container {
            overflow-x: auto;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table th,
        .report-table td {
            padding: 1rem;
            text-align: center;
            border-bottom: 1px solid #dee2e6;
        }

        .report-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #212529;
            text-align: center;
        }

        .report-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-active {
            background-color: #1b5e20; /* Deep green */
            color: white;
        }
        
        .status-returned {
            background-color: #6c757d; /* Gray */
            color: white;
        }
        
        .status-overdue {
            background-color: #F39C12; /* Orange */
            color: white;
        }
        
        .status-lost {
            background-color: #c62828; /* Deep red */
            color: white;
        }
        
        .btn {
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
            transition: var(--transition);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(0, 102, 204, 0.2);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 102, 204, 0.3);
        }
        
        .back-btn {
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
            transition: var(--transition);
            background: var(--primary-color);
            color: white;
        }
        
        .back-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 102, 204, 0.3);
        }
        
        .print-btn {
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
            transition: var(--transition);
            background: #28a745;
            color: white;
        }
        
        .print-btn:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);
        }
        
        .no-data {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        
        .pagination a,
        .pagination span {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-300);
            text-decoration: none;
            color: var(--gray-700);
            border-radius: 4px;
            transition: var(--transition);
        }
        
        .pagination a:hover {
            background-color: var(--gray-200);
        }
        
        .pagination .current {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        @media (max-width: 768px) {
            .library-details {
                flex-direction: column;
                gap: 1rem;
            }
            
            .report-table th,
            .report-table td {
                padding: 0.75rem 0.5rem;
            }
            
            .card-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .card-header .btn-group {
                display: flex;
                gap: 0.5rem;
            }
            
            .pagination {
                flex-wrap: wrap;
            }
        }
        
        @media print {
            body * {
                visibility: hidden;
            }
            .print-area, .print-area * {
                visibility: visible;
            }
            .print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
            
            /* Make library details display horizontally in print */
            .library-details {
                display: table !important;
                width: 100% !important;
                margin-top: 1rem !important;
                page-break-inside: avoid !important;
            }
            
            .detail-item {
                display: table-cell !important;
                padding: 0 1rem !important;
                text-align: center !important;
                vertical-align: top !important;
            }
            
            .detail-label {
                display: block !important;
                font-weight: 600 !important;
                color: #6c757d !important;
                font-size: 0.9rem !important;
                margin-bottom: 0.25rem !important;
            }
            
            .detail-value {
                display: block !important;
                font-size: 1.1rem !important;
                color: #212529 !important;
            }
            
            .library-info {
                text-align: center !important;
                background: #f8f9fa !important;
                border-radius: 8px !important;
                padding: 1.5rem !important;
                margin-bottom: 1.5rem !important;
            }
            
            /* Chrome-specific print styles for status badges */
            .status-badge {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
                display: inline-block !important;
                padding: 4px 12px !important;
                border-radius: 12px !important;
                font-size: 12px !important;
                font-weight: bold !important;
                color: white !important;
                background-color: #6c757d !important; /* Default gray */
            }
            
            .status-badge.status-active {
                background-color: #1b5e20 !important; /* Deep green */
            }
            
            .status-badge.status-returned {
                background-color: #6c757d !important; /* Gray */
            }
            
            .status-badge.status-overdue {
                background-color: #F39C12 !important; /* Orange */
            }
            
            .status-badge.status-lost {
                background-color: #c62828 !important; /* Deep red */
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/admin_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-book-reader"></i> <?php echo $pageTitle; ?></h1>
            <p>Borrowings in <?php echo htmlspecialchars($library['library_name']); ?></p>
        </div>

        <div class="content-card print-area">
            <div class="card-header">
                <h2><i class="fas fa-info-circle"></i> Library Information</h2>
                <div class="btn-group no-print">
                    <a href="borrowing_reports.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Borrowing Reports
                    </a>
                    <button class="print-btn" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>
            
            <div class="library-info">
                <h3><?php echo htmlspecialchars($library['library_name']); ?></h3>
                <div class="library-details">
                    <?php if (!empty($library['address'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Address</span>
                            <span class="detail-value"><?php echo htmlspecialchars($library['address']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($library['email'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Email</span>
                            <span class="detail-value"><?php echo htmlspecialchars($library['email']); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="detail-item">
                        <span class="detail-label">Total Borrowings</span>
                        <span class="detail-value"><?php echo $totalBorrowings; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="borrowings-section">
                <h3 class="section-title">Borrowing Records</h3>
                
                <?php if (empty($borrowings)): ?>
                    <div class="no-data">
                        <i class="fas fa-book-reader fa-3x" style="margin-bottom: 1rem; color: #ced4da;"></i>
                        <h3>No Borrowings Found</h3>
                        <p>This library currently has no borrowing records.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Transaction ID</th>
                                    <th>Book Title</th>
                                    <th>Member</th>
                                    <th>Issue Date</th>
                                    <th>Due Date</th>
                                    <th>Return Date</th>
                                    <th>Status</th>
                                    <th>Fine Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($borrowings as $borrowing): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($borrowing['transaction_id']); ?></td>
                                        <td><?php echo htmlspecialchars($borrowing['book_title']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($borrowing['member_name']); ?><br>
                                            <small><?php echo htmlspecialchars($borrowing['member_email']); ?></small>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($borrowing['issue_date'])); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($borrowing['due_date'])); ?></td>
                                        <td>
                                            <?php echo !empty($borrowing['return_date']) ? date('M j, Y', strtotime($borrowing['return_date'])) : 'N/A'; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $borrowing['status']; ?>">
                                                <?php echo ucfirst($borrowing['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $borrowing['fine_amount'] > 0 ? '₵' . number_format($borrowing['fine_amount'], 2) : '₵0.00'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?library_id=<?php echo $libraryId; ?>&page=1">&laquo; First</a>
                                <a href="?library_id=<?php echo $libraryId; ?>&page=<?php echo $page - 1; ?>">&lsaquo; Prev</a>
                            <?php endif; ?>
                            
                            <?php 
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?library_id=<?php echo $libraryId; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?library_id=<?php echo $libraryId; ?>&page=<?php echo $page + 1; ?>">Next &rsaquo;</a>
                                <a href="?library_id=<?php echo $libraryId; ?>&page=<?php echo $totalPages; ?>">Last &raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>