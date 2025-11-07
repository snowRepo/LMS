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

// Get user ID from URL parameter
$userId = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($userId)) {
    header('Location: member_reports.php?error=Invalid user ID');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Get member details
    $stmt = $db->prepare("
        SELECT u.*, CONCAT(u.first_name, ' ', u.last_name) as full_name
        FROM users u
        WHERE u.user_id = ? AND u.library_id = ? AND u.role = 'member'
    ");
    $stmt->execute([$userId, $libraryId]);
    $member = $stmt->fetch();
    
    if (!$member) {
        header('Location: member_reports.php?error=Member not found');
        exit;
    }
    
    // Get library name
    $stmt = $db->prepare("SELECT library_name FROM libraries WHERE id = ?");
    $stmt->execute([$libraryId]);
    $library = $stmt->fetch();
    $member['library_name'] = $library ? $library['library_name'] : 'Unknown Library';
    
    // Get attendance history for the member (last 30 days)
    $stmt = $db->prepare("
        SELECT attendance_date 
        FROM attendance 
        WHERE user_id = ? AND library_id = ? 
        AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY attendance_date DESC
    ");
    $stmt->execute([$userId, $libraryId]);
    $attendanceHistory = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get borrowing history for the member
    $stmt = $db->prepare("
        SELECT b.title, b.isbn, br.issue_date, br.return_date, br.due_date, 
               CASE 
                   WHEN br.return_date IS NOT NULL THEN 'returned'
                   WHEN br.due_date < CURDATE() THEN 'overdue'
                   ELSE 'active'
               END as status
        FROM borrowings br
        JOIN books b ON br.book_id = b.id
        WHERE br.member_id = ?
        ORDER BY br.issue_date DESC
    ");
    $stmt->execute([$member['id']]);
    $borrowingHistory = $stmt->fetchAll();
    
    // Calculate attendance statistics
    $totalAttendanceDays = count($attendanceHistory);
    $presentLast7Days = 0;
    
    // Count present days in the last 7 days
    $today = new DateTime();
    for ($i = 0; $i < 7; $i++) {
        $date = clone $today;
        $date->sub(new DateInterval('P' . $i . 'D'));
        $dateStr = $date->format('Y-m-d');
        if (in_array($dateStr, $attendanceHistory)) {
            $presentLast7Days++;
        }
        }
    
    $pageTitle = 'Detailed Member Report - ' . $member['first_name'] . ' ' . $member['last_name'];
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
            min-width: 180px;
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

        .status-returned {
            background-color: #d4edda;
            color: #155724;
        }

        .status-overdue {
            background-color: #f8d7da;
            color: #721c24;
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
                font-size: 9pt; /* Reduced font size */
                line-height: 1.2; /* Reduced line height */
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
                margin-bottom: 0.5rem; /* Reduced margin */
                border-bottom: 1px solid #3498DB;
                padding-bottom: 0.25rem; /* Reduced padding */
                page-break-after: avoid;
            }
            
            .page-header h1 {
                color: #2c3e50;
                font-size: 1.2rem; /* Reduced font size */
                margin-bottom: 0.1rem; /* Reduced margin */
            }
            
            .page-header p {
                font-size: 0.7rem; /* Reduced font size */
                margin: 0.05rem 0; /* Reduced margin */
            }
            
            .report-content {
                background: #ffffff;
                padding: 0;
                box-shadow: none;
                margin: 0;
                padding: 0.2in; /* Reduced padding */
            }
            
            .card-header {
                display: none !important;
            }
            
            .report-section {
                page-break-inside: avoid;
                margin-bottom: 0.5rem; /* Reduced margin */
                padding: 0.25rem 0; /* Reduced padding */
            }
            
            .report-section h3 {
                color: #2c3e50;
                border-bottom: 1px solid #bdc3c7;
                padding-bottom: 0.1rem; /* Reduced padding */
                margin-bottom: 0.25rem; /* Reduced margin */
                font-size: 0.9rem; /* Reduced font size */
            }
            
            .info-sheet {
                background: #ffffff;
                border: none;
                box-shadow: none;
                padding: 0;
            }
            
            .info-row {
                border-bottom: 1px solid #ecf0f1;
                margin-bottom: 0.1rem; /* Reduced margin */
                padding: 0.1rem 0; /* Reduced padding */
            }
            
            .info-label {
                min-width: 120px; /* Reduced width */
                font-size: 0.75rem; /* Reduced font size */
                padding-right: 0.5rem; /* Reduced padding */
            }
            
            .info-value {
                font-size: 0.75rem; /* Reduced font size */
            }
            
            .detail-item {
                border-bottom: 1px solid #ecf0f1;
                padding: 0.1rem 0; /* Reduced padding */
                margin-bottom: 0.1rem; /* Reduced margin */
            }
            
            .detail-label {
                font-size: 0.75rem; /* Reduced font size */
            }
            
            .detail-value {
                font-size: 0.75rem; /* Reduced font size */
            }
            
            .status-badge {
                border: 1px solid #bdc3c7;
                background: transparent !important;
                color: #333 !important;
                padding: 0.05rem 0.3rem; /* Reduced padding */
                font-size: 0.6rem; /* Reduced font size */
            }
            
            /* Print-friendly status badges that preserve color information */
            .status-badge.status-active {
                background-color: #006400 !important;
                color: white !important;
                border-color: #006400 !important;
            }
            
            .status-badge.status-inactive {
                background-color: #f8d7da !important;
                color: #721c24 !important;
                border-color: #f5c6cb !important;
            }
            
            .status-badge.status-pending {
                background-color: #fff3cd !important;
                color: #856404 !important;
                border-color: #ffeaa7 !important;
            }
            
            .status-badge.status-returned {
                background-color: #d4edda !important;
                color: #155724 !important;
                border-color: #c3e6cb !important;
            }
            
            .status-badge.status-overdue {
                background-color: #f8d7da !important;
                color: #721c24 !important;
                border-color: #f5c6cb !important;
            }
            
            .status-badge.status-active,
            .status-badge.status-inactive,
            .status-badge.status-pending,
            .status-badge.status-returned,
            .status-badge.status-overdue {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            a[href]:after {
                content: none !important;
            }
            
            @page {
                margin: 0.3in; /* Reduced margin */
                size: A4;
            }
            
            /* Reduce font sizes and spacing for better fit */
            .stat-card .number {
                font-size: 1rem; /* Reduced font size */
            }
            
            .stat-card .label {
                font-size: 0.6rem; /* Reduced font size */
            }
            
            /* Ensure content fits on one page */
            .report-content {
                font-size: 0.8em; /* Reduced font size */
                transform: scale(0.95); /* Slightly scale down content */
                transform-origin: top left;
            }
            
            /* Adjust specific sections to fit better */
            .detail-value span {
                font-size: 0.65rem; /* Reduced font size */
                padding: 1px 3px; /* Reduced padding */
            }
            
            /* Limit the number of items displayed in lists to ensure everything fits */
            .detail-item:nth-child(n+8) {
                display: none; /* Hide items beyond the 7th to save space */
            }
            
            /* Make borrowing history items more compact */
            .detail-item > div > div[style*="border-bottom"] {
                padding: 0.25rem 0 !important; /* Reduced padding */
                margin: 0.1rem 0 !important; /* Reduced margin */
            }
            
            .detail-item > div > div[style*="display: flex"] {
                gap: 0.5rem !important; /* Reduced gap */
                font-size: 0.7rem !important; /* Reduced font size */
            }
            
            /* Footer note */
            .report-content > div[style*="text-align: center"] {
                font-size: 0.6rem !important; /* Reduced font size */
                margin-top: 0.1rem !important; /* Reduced margin */
                padding-top: 0.1rem !important; /* Reduced padding */
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/supervisor_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-file-alt"></i> Detailed Member Report</h1>
            <p>Comprehensive report for <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></p>
            <p style="font-size: 0.8rem; color: #7f8c8d;">Report ID: <?php echo 'RPT-' . date('Ymd') . '-' . strtoupper(substr($member['user_id'], 0, 6)); ?></p>
            <p style="font-size: 0.8rem; color: #7f8c8d;">Generated on <?php echo gmdate('M j, Y \a\t g:i A') . ' UTC'; ?></p>

        </div>
        
        <!-- Toast Container -->
        <div id="toast-container"></div>
        
        <div class="report-content">
            <div class="card-header">
                <h2>Detailed Member Report</h2>
                <div>
                    <a href="member_reports.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Member Reports
                    </a>
                    <button onclick="window.print()" class="btn btn-secondary" style="margin-left: 10px;">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </div>
            </div>
            
            <div class="report-section">
                <h3><i class="fas fa-user"></i> Member Information</h3>
                <div class="info-sheet">
                    <div class="info-row">
                        <div class="info-label">Member ID:</div>
                        <div class="info-value"><?php echo htmlspecialchars($member['user_id']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Full Name:</div>
                        <div class="info-value"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Email Address:</div>
                        <div class="info-value"><?php echo htmlspecialchars($member['email']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Phone Number:</div>
                        <div class="info-value"><?php echo !empty($member['phone']) ? htmlspecialchars($member['phone']) : 'N/A'; ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Date Joined:</div>
                        <div class="info-value"><?php echo date('F j, Y', strtotime($member['created_at'])); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Status:</div>
                        <div class="info-value">
                            <span class="status-badge status-<?php echo $member['status']; ?>">
                                <?php echo ucfirst($member['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="report-section">
                <h3><i class="fas fa-calendar-check"></i> Recent Attendance</h3>
                <div class="info-sheet">
                    <ul class="detail-list">
                        <li class="detail-item">
                            <div class="detail-label">Total Present (30 days):</div>
                            <div class="detail-value"><?php echo $totalAttendanceDays; ?> days</div>
                        </li>
                        
                        <li class="detail-item">
                            <div class="detail-label">Present Last Week:</div>
                            <div class="detail-value"><?php echo $presentLast7Days; ?> out of 7 days</div>
                        </li>
                        
                        <li class="detail-item">
                            <div class="detail-label">Recent Attendance Dates:</div>
                            <div class="detail-value">
                                <?php if (count($attendanceHistory) > 0): ?>
                                    <?php 
                                    // Sort attendance dates
                                    rsort($attendanceHistory);
                                    foreach (array_slice($attendanceHistory, 0, 10) as $date): ?>
                                        <span style="display: inline-block; margin-right: 10px; margin-bottom: 5px; background: #d4edda; color: #155724; padding: 2px 8px; border-radius: 12px; font-size: 0.85rem;">
                                            <?php echo date('M j, Y', strtotime($date)); ?>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if (count($attendanceHistory) > 10): ?>
                                        <div style="margin-top: 0.5rem; color: #6c757d; font-style: italic;">
                                            ... and <?php echo count($attendanceHistory) - 10; ?> more dates
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    No attendance records found
                                <?php endif; ?>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="report-section">
                <h3><i class="fas fa-book"></i> Borrowing History</h3>
                <div class="info-sheet">
                    <ul class="detail-list">
                        <li class="detail-item">
                            <div class="detail-label">Total Books Borrowed:</div>
                            <div class="detail-value"><?php echo count($borrowingHistory); ?> books</div>
                        </li>
                        
                        <li class="detail-item">
                            <div class="detail-label">Active Borrows:</div>
                            <div class="detail-value">
                                <?php 
                                $activeBorrows = array_filter($borrowingHistory, function($borrow) {
                                    return $borrow['status'] === 'active';
                                });
                                echo count($activeBorrows);
                                ?> books
                            </div>
                        </li>
                        
                        <li class="detail-item">
                            <div class="detail-label">Overdue Books:</div>
                            <div class="detail-value">
                                <?php 
                                $overdueBorrows = array_filter($borrowingHistory, function($borrow) {
                                    return $borrow['status'] === 'overdue';
                                });
                                echo count($overdueBorrows);
                                ?> books
                            </div>
                        </li>
                        
                        <li class="detail-item">
                            <div class="detail-label">Recent Borrowing Records:</div>
                            <div class="detail-value">
                                <?php if (count($borrowingHistory) > 0): ?>
                                    <?php 
                                    // Sort by issue date, newest first
                                    usort($borrowingHistory, function($a, $b) {
                                        return strtotime($b['issue_date']) - strtotime($a['issue_date']);
                                    });
                                    foreach (array_slice($borrowingHistory, 0, 5) as $borrow): ?>
                                        <div style="border-bottom: 1px solid #ecf0f1; padding: 0.75rem 0;">
                                            <div><strong><?php echo htmlspecialchars($borrow['title']); ?></strong></div>
                                            <div style="font-size: 0.9rem; color: #7f8c8d; margin: 0.25rem 0;">
                                                ISBN: <?php echo htmlspecialchars($borrow['isbn']); ?>
                                            </div>
                                            <div style="display: flex; flex-wrap: wrap; gap: 1rem; font-size: 0.9rem;">
                                                <span>Borrowed: <?php echo date('M j, Y', strtotime($borrow['issue_date'])); ?></span>
                                                <span>Due: <?php echo date('M j, Y', strtotime($borrow['due_date'])); ?></span>
                                                <span>
                                                    Returned: 
                                                    <?php 
                                                    if ($borrow['return_date']) {
                                                        echo date('M j, Y', strtotime($borrow['return_date']));
                                                    } else {
                                                        echo 'Not returned';
                                                    }
                                                    ?>
                                                </span>
                                                <span>
                                                    <span class="status-badge status-<?php echo $borrow['status']; ?>">
                                                        <?php echo ucfirst($borrow['status']); ?>
                                                    </span>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($borrowingHistory) > 5): ?>
                                        <div style="margin-top: 0.5rem; color: #7f8c8d; font-style: italic;">
                                            ... and <?php echo count($borrowingHistory) - 5; ?> more borrowing records
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    No borrowing history found for this member.
                                <?php endif; ?>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid #bdc3c7; text-align: center; color: #7f8c8d; font-size: 0.75rem; page-break-inside: avoid;">
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
                const newUrl = window.location.pathname + (urlParams.toString() ? ? '?' + urlParams.toString() : '');
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