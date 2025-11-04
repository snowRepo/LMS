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

try {
    $db = Database::getInstance()->getConnection();
    
    // Get total books count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM books WHERE library_id = ?");
    $stmt->execute([$libraryId]);
    $totalBooks = $stmt->fetch()['total'];
    
    // Get available books count (not borrowed)
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM books b 
        LEFT JOIN borrowings br ON b.id = br.book_id AND br.status = 'active'
        WHERE b.library_id = ? AND br.id IS NULL
    ");
    $stmt->execute([$libraryId]);
    $availableBooks = $stmt->fetch()['total'];
    
    // Get borrowed books count
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM borrowings br 
        JOIN books b ON br.book_id = b.id 
        WHERE b.library_id = ? AND br.status = 'active'
    ");
    $stmt->execute([$libraryId]);
    $borrowedBooks = $stmt->fetch()['total'];
    
    // Get overdue books count
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM borrowings br 
        JOIN books b ON br.book_id = b.id 
        WHERE b.library_id = ? AND br.status = 'overdue'
    ");
    $stmt->execute([$libraryId]);
    $overdueBooks = $stmt->fetch()['total'];
    
    // Get lost books count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM books WHERE library_id = ? AND status = 'lost'");
    $stmt->execute([$libraryId]);
    $lostBooks = $stmt->fetch()['total'];
    
    // Get damaged books count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM books WHERE library_id = ? AND status = 'damaged'");
    $stmt->execute([$libraryId]);
    $damagedBooks = $stmt->fetch()['total'];
    
    // Get books by category
    $stmt = $db->prepare("
        SELECT c.name as category, COUNT(b.id) as count
        FROM categories c
        LEFT JOIN books b ON c.id = b.category_id AND b.library_id = ?
        WHERE c.library_id = ?
        GROUP BY c.id, c.name
        ORDER BY count DESC
    ");
    $stmt->execute([$libraryId, $libraryId]);
    $booksByCategory = $stmt->fetchAll();
    
    // Get most borrowed books
    $stmt = $db->prepare("
        SELECT b.title, b.isbn, COUNT(br.id) as borrow_count
        FROM books b
        JOIN borrowings br ON b.id = br.book_id
        WHERE b.library_id = ?
        GROUP BY b.id, b.title, b.isbn
        ORDER BY borrow_count DESC
        LIMIT 10
    ");
    $stmt->execute([$libraryId]);
    $popularBooks = $stmt->fetchAll();
    

    
    $pageTitle = 'Book Reports';
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - LMS</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Supervisor Navbar CSS -->
    <link rel="stylesheet" href="css/supervisor_navbar.css">
    <!-- Toast CSS -->
    <link rel="stylesheet" href="css/toast.css">
    
    <style>
        body {
            background: #ffffff;
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 0;
            color: #333;
        }
        
        html, body {
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 100%;
            margin: 0 auto;
            padding: 1rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #3498DB;
            padding-bottom: 1rem;
        }

        .page-header h1 {
            color: #2c3e50;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #7f8c8d;
            font-size: 1rem;
        }

        .report-content {
            background: #ffffff;
            padding: 1.5rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #bdc3c7;
        }

        .card-header h2 {
            color: #2c3e50;
            margin: 0;
            font-size: 1.4rem;
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
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .btn:active {
            transform: translateY(0);
        }

        .report-section {
            margin-bottom: 2rem;
            padding: 1rem 0;
        }
        
        .report-section h3 {
            color: #2c3e50;
            border-bottom: 1px solid #bdc3c7;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }
        
        .info-sheet {
            background: #ffffff;
            padding: 1rem 0;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 0.75rem;
            align-items: flex-start;
            padding: 0.5rem 0;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .info-row:last-child {
            margin-bottom: 0;
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #2c3e50;
            min-width: 200px;
            padding-right: 1rem;
        }
        
        .info-value {
            flex: 1;
            color: #7f8c8d;
        }

        .section-title {
            color: #3498DB;
            font-size: 1.1rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #3498DB;
        }
        
        .detail-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        
        .detail-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        
        .detail-value {
            color: #7f8c8d;
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

        .status-available {
            background-color: #006400;
            color: white;
        }

        .status-borrowed {
            background-color: #d4edda;
            color: #155724;
        }

        .status-overdue {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-lost {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-damaged {
            background-color: #fff3cd;
            color: #856404;
        }

        /* Print styles */
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            body {
                background: #ffffff;
                padding: 0;
                margin: 0;
                font-size: 8pt; /* Reduced font size */
                line-height: 1.1; /* Reduced line height */
            }
            
            /* Hide navbar and all buttons in print view */
            .navbar, .btn, .toast-container, #toast-container {
                display: none !important;
            }
            
            /* Also hide any navbar elements specifically */
            nav, .navigation, .nav, .navbar-header, .navbar-collapse, .navbar-brand, .navbar-nav, .navbar-toggle {
                display: none !important;
            }
            
            .container {
                max-width: 100%;
                padding: 0;
                margin: 0;
            }
            
            .page-header {
                text-align: center;
                margin-bottom: 0.3rem; /* Reduced margin */
                border-bottom: 1px solid #3498DB;
                padding-bottom: 0.15rem; /* Reduced padding */
                page-break-after: avoid;
            }
            
            .page-header h1 {
                color: #2c3e50;
                font-size: 1.1rem; /* Reduced font size */
                margin-bottom: 0.05rem; /* Reduced margin */
            }
            
            .page-header p {
                font-size: 0.6rem; /* Reduced font size */
                margin: 0.03rem 0; /* Reduced margin */
            }
            
            .report-content {
                background: #ffffff;
                padding: 0;
                box-shadow: none;
                margin: 0;
                padding: 0.15in; /* Reduced padding */
            }
            
            .card-header {
                display: none !important;
            }
            
            .report-section {
                page-break-inside: avoid;
                margin-bottom: 0.3rem; /* Reduced margin */
                padding: 0.15rem 0; /* Reduced padding */
            }
            
            .report-section h3 {
                color: #2c3e50;
                border-bottom: 1px solid #bdc3c7;
                padding-bottom: 0.1rem; /* Reduced padding */
                margin-bottom: 0.15rem; /* Reduced margin */
                font-size: 0.8rem; /* Reduced font size */
            }
            
            .info-sheet {
                background: #ffffff;
                border: none;
                box-shadow: none;
                padding: 0;
            }
            
            .info-row {
                border-bottom: 1px solid #ecf0f1;
                margin-bottom: 0.05rem; /* Reduced margin */
                padding: 0.05rem 0; /* Reduced padding */
            }
            
            .info-label {
                min-width: 100px; /* Reduced width */
                font-size: 0.7rem; /* Reduced font size */
                padding-right: 0.3rem; /* Reduced padding */
            }
            
            .info-value {
                font-size: 0.7rem; /* Reduced font size */
            }
            
            .detail-item {
                border-bottom: 1px solid #ecf0f1;
                padding: 0.05rem 0; /* Reduced padding */
                margin-bottom: 0.05rem; /* Reduced margin */
            }
            
            .detail-label {
                font-size: 0.7rem; /* Reduced font size */
            }
            
            .detail-value {
                font-size: 0.7rem; /* Reduced font size */
            }
            
            .status-badge {
                border: 1px solid #bdc3c7;
                background: transparent !important;
                color: #333 !important;
                padding: 0.03rem 0.2rem; /* Reduced padding */
                font-size: 0.55rem; /* Reduced font size */
            }
            
            /* Print-friendly status badges that preserve color information */
            .status-badge.status-available {
                background-color: #006400 !important;
                color: white !important;
                border-color: #006400 !important;
            }
            
            .status-badge.status-borrowed {
                background-color: #d4edda !important;
                color: #155724 !important;
                border-color: #c3e6cb !important;
            }
            
            .status-badge.status-overdue {
                background-color: #f8d7da !important;
                color: #721c24 !important;
                border-color: #f5c6cb !important;
            }
            
            .status-badge.status-lost {
                background-color: #f8d7da !important;
                color: #721c24 !important;
                border-color: #f5c6cb !important;
            }
            
            .status-badge.status-damaged {
                background-color: #fff3cd !important;
                color: #856404 !important;
                border-color: #ffeaa7 !important;
            }
            
            .status-badge.status-available,
            .status-badge.status-borrowed,
            .status-badge.status-overdue,
            .status-badge.status-lost,
            .status-badge.status-damaged {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            a[href]:after {
                content: none !important;
            }
            
            @page {
                margin: 0.2in; /* Reduced margin */
                size: A4;
            }
            
            /* Reduce font sizes and spacing for better fit */
            .stat-card .number {
                font-size: 0.9rem; /* Reduced font size */
            }
            
            .stat-card .label {
                font-size: 0.55rem; /* Reduced font size */
            }
            
            /* Ensure content fits on one page */
            .report-content {
                font-size: 0.75em; /* Reduced font size */
                transform: scale(0.92); /* Slightly scale down content */
                transform-origin: top left;
            }
            
            /* Adjust specific sections to fit better */
            .detail-value span {
                font-size: 0.6rem; /* Reduced font size */
                padding: 1px 2px; /* Reduced padding */
            }
            
            /* Limit the number of items displayed in lists to ensure everything fits */
            .detail-item:nth-child(n+12) {
                display: none; /* Hide items beyond the 11th to save space */
            }
            
            /* Footer note */
            .report-content > div[style*="text-align: center"] {
                font-size: 0.55rem !important; /* Reduced font size */
                margin-top: 0.05rem !important; /* Reduced margin */
                padding-top: 0.05rem !important; /* Reduced padding */
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/supervisor_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-book"></i> Book Reports</h1>
            <p>Comprehensive report on library book inventory and borrowing statistics</p>
            <p style="font-size: 0.8rem; color: #7f8c8d;">Report ID: <?php echo 'RPT-' . date('Ymd') . '-' . strtoupper(uniqid()); ?></p>
            <p style="font-size: 0.8rem; color: #7f8c8d;">Generated on <?php echo gmdate('M j, Y \a\t g:i A') . ' UTC'; ?></p>
        </div>
        
        <!-- Toast Container -->
        <div id="toast-container"></div>
        
        <div class="report-content">
            <div class="card-header">
                <h2>Book Inventory Report</h2>
                <div>
                    <a href="reports.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Reports
                    </a>
                    <button onclick="window.print()" class="btn btn-secondary" style="margin-left: 10px;">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </div>
            </div>
            
            <div class="report-section">
                <h3><i class="fas fa-chart-bar"></i> Book Inventory Summary</h3>
                <div class="info-sheet">
                    <div class="info-row">
                        <div class="info-label">Total Books in Library:</div>
                        <div class="info-value"><?php echo $totalBooks; ?> books</div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Available Books:</div>
                        <div class="info-value">
                            <?php echo $availableBooks; ?> books
                            <span class="status-badge status-available">
                                <?php echo $totalBooks > 0 ? round(($availableBooks / $totalBooks) * 100, 1) : 0; ?>%
                            </span>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Borrowed Books:</div>
                        <div class="info-value">
                            <?php echo $borrowedBooks; ?> books
                            <span class="status-badge status-borrowed">
                                <?php echo $totalBooks > 0 ? round(($borrowedBooks / $totalBooks) * 100, 1) : 0; ?>%
                            </span>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Overdue Books:</div>
                        <div class="info-value">
                            <?php echo $overdueBooks; ?> books
                            <span class="status-badge status-overdue">
                                <?php echo $totalBooks > 0 ? round(($overdueBooks / $totalBooks) * 100, 1) : 0; ?>%
                            </span>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Lost Books:</div>
                        <div class="info-value">
                            <?php echo $lostBooks; ?> books
                            <span class="status-badge status-lost">
                                <?php echo $totalBooks > 0 ? round(($lostBooks / $totalBooks) * 100, 1) : 0; ?>%
                            </span>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Damaged Books:</div>
                        <div class="info-value">
                            <?php echo $damagedBooks; ?> books
                            <span class="status-badge status-damaged">
                                <?php echo $totalBooks > 0 ? round(($damagedBooks / $totalBooks) * 100, 1) : 0; ?>%
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="report-section">
                <h3><i class="fas fa-layer-group"></i> Books by Category</h3>
                <div class="info-sheet">
                    <ul class="detail-list">
                        <?php if (count($booksByCategory) > 0): ?>
                            <?php foreach ($booksByCategory as $category): ?>
                                <li class="detail-item">
                                    <div class="detail-label"><?php echo htmlspecialchars($category['category']); ?>:</div>
                                    <div class="detail-value"><?php echo $category['count']; ?> books</div>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="detail-item">
                                <div class="detail-value">No categories found</div>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            
            <div class="report-section">
                <h3><i class="fas fa-fire"></i> Most Popular Books</h3>
                <div class="info-sheet">
                    <ul class="detail-list">
                        <?php if (count($popularBooks) > 0): ?>
                            <?php foreach ($popularBooks as $book): ?>
                                <li class="detail-item">
                                    <div class="detail-label"><?php echo htmlspecialchars($book['title']); ?></div>
                                    <div class="detail-value">
                                        ISBN: <?php echo htmlspecialchars($book['isbn']); ?> | 
                                        Borrowed: <?php echo $book['borrow_count']; ?> times
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="detail-item">
                                <div class="detail-value">No borrowing data available</div>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            

            
            <div style="margin-top: 0; padding-top: 0.25rem; border-top: 1px solid #bdc3c7; text-align: center; color: #7f8c8d; font-size: 0.75rem; page-break-inside: avoid;">
                <p>This report contains confidential information and should be handled accordingly.</p>
            </div>
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