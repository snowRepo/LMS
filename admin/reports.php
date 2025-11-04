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

// Check if user is logged in and has admin role
if (!is_logged_in() || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Get report data
try {
    $db = Database::getInstance()->getConnection();
    
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
    
    // Debug: Get supervisor details to check for inconsistencies
    /*
    $stmt = $db->prepare("SELECT u.*, l.library_name FROM users u LEFT JOIN libraries l ON u.library_id = l.id WHERE u.role = 'supervisor' ORDER BY u.library_id");
    $stmt->execute();
    $supervisorDetails = $stmt->fetchAll();
    echo "<!-- Supervisor Details: ";
    print_r($supervisorDetails);
    echo " -->";
    */
    
    // Get total books count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM books");
    $stmt->execute();
    $totalBooks = $stmt->fetch()['total'];
    
    // Get active subscriptions count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM subscriptions WHERE status = 'active'");
    $stmt->execute();
    $activeSubscriptions = $stmt->fetch()['total'];
    
    // Get trial subscriptions count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM subscriptions WHERE status = 'trial'");
    $stmt->execute();
    $trialSubscriptions = $stmt->fetch()['total'];
    
    // Get all libraries with their details for print report
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
    
    // Debug: Check if any library has more than 1 supervisor
    /*
    foreach ($allLibraries as $library) {
        if ($library['supervisor_count'] > 1) {
            echo "<!-- Library {$library['library_name']} has {$library['supervisor_count']} supervisors -->";
        }
    }
    */
    
    // Get top 5 libraries by book count
    $stmt = $db->prepare("
        SELECT l.library_name, COUNT(b.id) as book_count
        FROM libraries l
        LEFT JOIN books b ON l.id = b.library_id
        WHERE l.deleted_at IS NULL
        GROUP BY l.id
        ORDER BY book_count DESC
        LIMIT 5
    ");
    $stmt->execute();
    $topLibraries = $stmt->fetchAll();
    
    // Get subscription statistics
    $stmt = $db->prepare("
        SELECT 
            plan_type,
            COUNT(*) as count
        FROM subscriptions 
        WHERE status IN ('active', 'trial')
        GROUP BY plan_type
        ORDER BY count DESC
    ");
    $stmt->execute();
    $subscriptionStats = $stmt->fetchAll();
    
    // Get recent payments (last 10)
    $stmt = $db->prepare("
        SELECT 
            ph.amount,
            ph.currency,
            ph.payment_method,
            ph.transaction_reference,
            ph.payment_date,
            l.library_name,
            s.plan_type
        FROM payment_history ph
        JOIN subscriptions s ON ph.subscription_id = s.id
        JOIN libraries l ON s.library_id = l.id
        WHERE ph.status = 'completed'
        ORDER BY ph.payment_date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recentPayments = $stmt->fetchAll();
    
    // Get libraries by subscription status
    $stmt = $db->prepare("
        SELECT 
            status,
            COUNT(*) as count
        FROM subscriptions 
        GROUP BY status
        ORDER BY count DESC
    ");
    $stmt->execute();
    $subscriptionStatusStats = $stmt->fetchAll();
    
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
    
} catch (Exception $e) {
    die('Error loading report data: ' . $e->getMessage());
}

$pageTitle = 'Reports';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - LMS</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
            color: #2c3e50; /* Changed from var(--primary-color) to match other admin pages */
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
        
        .btn-outline {
            background: transparent;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }
        
        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-success {
            background: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background: #2e7d32;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(29, 94, 32, 0.3);
        }
        
        .btn-danger {
            background: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background: #b71c1c;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(198, 40, 40, 0.3);
        }
        
        .print-button {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .print-button:hover {
            background: #218838;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.1);
        }

        .stat-card .icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .stat-card .number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
        }

        .stat-card .label {
            font-size: 1rem;
            color: #6c757d;
        }

        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .chart-header h3 {
            color: #212529;
            margin: 0;
        }

        .report-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-header h3 {
            color: #212529;
            margin: 0;
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
            padding: 0.75rem;
            text-align: center;
            border-bottom: 1px solid #dee2e6;
        }

        .report-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #212529;
        }

        .report-table tr:hover {
            background-color: #f8f9fa;
        }

        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .report-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .report-card h4 {
            color: #212529;
            margin-top: 0;
        }

        .report-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .action-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            text-align: center;
            transition: transform 0.3s ease;
            flex: 1;
            min-width: 200px;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.1);
        }

        .action-card .icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .action-card h4 {
            color: #212529;
            margin: 0 0 1rem 0;
        }

        .action-card .btn {
            width: 100%;
            justify-content: center;
        }
        
        .no-print {
            display: block;
        }
        
        .print-only {
            display: none;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            .print-only {
                display: block !important;
            }
            
            /* Hide main content during print */
            .page-header,
            .stats-container,
            .chart-container,
            .report-section {
                display: none !important;
            }
            
            body {
                padding-top: 0;
                background: white;
            }
            
            .container {
                max-width: 100%;
                padding: 0;
            }
            
            .content-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            .btn {
                display: none;
            }
            
            .stat-card:hover {
                transform: none;
                box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            }
            
            .action-card:hover {
                transform: none;
                box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            }
            
            /* Hide navbar during print */
            .admin-navbar-container {
                display: none !important;
            }
            
            /* Print report specific styles */
            .print-header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #0066cc;
                padding-bottom: 20px;
            }
            
            .print-header h1 {
                color: #0066cc;
                margin: 0 0 10px 0;
            }
            
            .print-header p {
                color: #6c757d;
                margin: 0;
            }
            
            .print-section {
                margin-bottom: 30px;
                page-break-inside: avoid;
            }
            
            .print-section-header {
                background-color: #0066cc;
                color: white;
                padding: 10px 15px;
                border-radius: 5px;
                margin-bottom: 15px;
            }
            
            .print-library-details {
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 1px solid #eee;
            }
            
            .print-library-name {
                font-weight: bold;
                font-size: 1.2em;
                color: #0066cc;
                margin-bottom: 5px;
            }
            
            .print-library-stats {
                display: grid;
                grid-template-columns: repeat(5, 1fr);
                gap: 10px;
                margin: 10px 0;
            }
            
            .print-stat {
                background: #f8f9fa;
                padding: 8px;
                border-radius: 4px;
                text-align: center;
            }
            
            .print-stat-label {
                font-size: 0.8em;
                color: #6c757d;
            }
            
            .print-stat-value {
                font-weight: bold;
                font-size: 1.1em;
                color: #0066cc;
            }
            
            .print-table {
                width: 100%;
                border-collapse: collapse;
                margin: 15px 0;
            }
            
            .print-table th,
            .print-table td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: center;
            }
            
            .print-table th {
                background-color: #0066cc;
                color: white;
            }
            
            .print-footer {
                margin-top: 30px;
                text-align: center;
                font-size: 0.9em;
                color: #6c757d;
                border-top: 1px solid #eee;
                padding-top: 15px;
            }
        }

        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .report-grid {
                grid-template-columns: 1fr;
            }
            
            .report-actions {
                flex-direction: column;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }

        @media (max-width: 480px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/admin_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-chart-bar"></i> <?php echo $pageTitle; ?></h1>
            <p>System-wide analytics and reports</p>
        </div>

        <!-- Print Button -->
        <div class="no-print" style="text-align: right; margin-bottom: 20px;">
            <button class="print-button" onclick="window.print()">
                <i class="fas fa-print"></i> Print Report
            </button>
        </div>
        
        <!-- Print Report Content (Hidden on screen, visible only during print) -->
        <div class="print-only" style="display: none;">
            <div class="print-header">
                <h1>Library Management System - Comprehensive Report</h1>
                <p>Generated on <?php echo date('F j, Y'); ?></p>
            </div>
            
            <!-- Library Count Summary -->
            <div class="print-section">
                <div class="print-section-header">
                    <h2><i class="fas fa-building"></i> System Overview</h2>
                </div>
                <div class="print-library-stats">
                    <div class="print-stat">
                        <div class="print-stat-label">Total Libraries</div>
                        <div class="print-stat-value"><?php echo $totalLibraries; ?></div>
                    </div>
                    <div class="print-stat">
                        <div class="print-stat-label">Total Members</div>
                        <div class="print-stat-value"><?php echo $totalMembers; ?></div>
                    </div>
                    <div class="print-stat">
                        <div class="print-stat-label">Total Librarians</div>
                        <div class="print-stat-value"><?php echo $totalLibrarians; ?></div>
                    </div>
                    <div class="print-stat">
                        <div class="print-stat-label">Total Supervisors</div>
                        <div class="print-stat-value"><?php echo $totalSupervisors; ?></div>
                    </div>
                    <div class="print-stat">
                        <div class="print-stat-label">Total Books</div>
                        <div class="print-stat-value"><?php echo $totalBooks; ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Library Details -->
            <div class="print-section">
                <div class="print-section-header">
                    <h2><i class="fas fa-university"></i> Library Details</h2>
                </div>
                <?php foreach ($allLibraries as $library): ?>
                <div class="print-library-details">
                    <div class="print-library-name"><?php echo htmlspecialchars($library['library_name']); ?></div>
                    <div><strong>Email:</strong> <?php echo htmlspecialchars($library['email']); ?></div>
                    <div><strong>Phone:</strong> <?php echo htmlspecialchars($library['phone']); ?></div>
                    <div><strong>Address:</strong> <?php echo htmlspecialchars($library['address']); ?></div>
                    <div class="print-library-stats">
                        <div class="print-stat">
                            <div class="print-stat-label">Supervisors</div>
                            <div class="print-stat-value"><?php echo $library['supervisor_count']; ?></div>
                        </div>
                        <div class="print-stat">
                            <div class="print-stat-label">Librarians</div>
                            <div class="print-stat-value"><?php echo $library['librarian_count']; ?></div>
                        </div>
                        <div class="print-stat">
                            <div class="print-stat-label">Members</div>
                            <div class="print-stat-value"><?php echo $library['member_count']; ?></div>
                        </div>
                        <div class="print-stat">
                            <div class="print-stat-label">Books</div>
                            <div class="print-stat-value"><?php echo $library['book_count']; ?></div>
                        </div>
                        <div class="print-stat">
                            <div class="print-stat-label">Borrowed Books</div>
                            <div class="print-stat-value"><?php echo $library['borrowed_books_count']; ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Subscription Details -->
            <div class="print-section">
                <div class="print-section-header">
                    <h2><i class="fas fa-credit-card"></i> Subscription Details</h2>
                </div>
                <table class="print-table">
                    <thead>
                        <tr>
                            <th>Library</th>
                            <th>Plan</th>
                            <th>Status</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Auto Renew</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($librarySubscriptions as $subscription): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($subscription['library_name']); ?></td>
                            <td><?php echo ucfirst($subscription['plan_type']); ?></td>
                            <td><?php echo ucfirst($subscription['status']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($subscription['start_date'])); ?></td>
                            <td><?php echo date('M j, Y', strtotime($subscription['end_date'])); ?></td>
                            <td><?php echo $subscription['auto_renew'] ? 'Yes' : 'No'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Attendance Details -->
            <div class="print-section">
                <div class="print-section-header">
                    <h2><i class="fas fa-calendar-check"></i> Attendance Summary</h2>
                </div>
                <table class="print-table">
                    <thead>
                        <tr>
                            <th>Library</th>
                            <th>Attendance Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($libraryAttendance as $attendance): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($attendance['library_name']); ?></td>
                            <td><?php echo $attendance['attendance_count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="print-footer">
                Report generated by LMS Admin on <?php echo date('F j, Y g:i A'); ?>
            </div>
        </div>

        <!-- Key Statistics -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-building"></i>
                </div>
                <div class="number"><?php echo $totalLibraries; ?></div>
                <div class="label">Total Libraries</div>
            </div>
            
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="number"><?php echo $totalMembers; ?></div>
                <div class="label">Total Members</div>
            </div>
            
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="number"><?php echo $totalLibrarians; ?></div>
                <div class="label">Librarians</div>
            </div>
            
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="number"><?php echo $totalSupervisors; ?></div>
                <div class="label">Supervisors</div>
            </div>
            
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="number"><?php echo $totalBooks; ?></div>
                <div class="label">Total Books</div>
            </div>
            
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="number"><?php echo $activeSubscriptions; ?></div>
                <div class="label">Active Subscriptions</div>
            </div>
        </div>

        <!-- Subscription Statistics Chart -->
        <div class="chart-container">
            <div class="chart-header">
                <h3><i class="fas fa-chart-pie"></i> Subscription Distribution</h3>
            </div>
            <div class="chart-wrapper">
                <canvas id="subscriptionChart"></canvas>
            </div>
        </div>

        <!-- Subscription Status Chart -->
        <div class="chart-container">
            <div class="chart-header">
                <h3><i class="fas fa-chart-bar"></i> Subscription Status Overview</h3>
            </div>
            <div class="chart-wrapper">
                <canvas id="subscriptionStatusChart"></canvas>
            </div>
        </div>

        <!-- Top Libraries by Book Count -->
        <div class="report-section">
            <div class="section-header">
                <h3><i class="fas fa-fire"></i> Top Libraries by Book Count</h3>
            </div>
            <div class="table-container">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Library Name</th>
                            <th>Book Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topLibraries)): ?>
                            <tr>
                                <td colspan="2" style="text-align: center;">No library data available</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($topLibraries as $library): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($library['library_name']); ?></td>
                                    <td><?php echo $library['book_count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Payments -->
        <div class="report-section">
            <div class="section-header">
                <h3><i class="fas fa-receipt"></i> Recent Payments</h3>
            </div>
            <div class="table-container">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Library</th>
                            <th>Plan</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Date</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentPayments)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No payment data available</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentPayments as $payment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['library_name']); ?></td>
                                    <td><?php echo ucfirst($payment['plan_type']); ?></td>
                                    <td><?php echo $payment['currency'] . ' ' . number_format($payment['amount'], 2); ?></td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($payment['transaction_reference']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Report Categories -->
        <div class="report-section">
            <div class="section-header">
                <h3><i class="fas fa-file-alt"></i> Detailed Reports</h3>
            </div>
            <div class="report-grid">
                <div class="report-card">
                    <h4><i class="fas fa-user-graduate"></i> Member Reports</h4>
                    <p>Generate detailed reports on member information across all libraries.</p>
                    <button class="btn btn-primary" onclick="window.location.href='member_reports.php'">
                        <i class="fas fa-file-alt"></i>
                        Generate Report
                    </button>
                </div>
                
                <div class="report-card">
                    <h4><i class="fas fa-book-open"></i> Book Reports</h4>
                    <p>Analyze book inventory and borrowing patterns across all libraries.</p>
                    <button class="btn btn-primary" onclick="window.location.href='book_reports.php'">
                        <i class="fas fa-file-alt"></i>
                        Generate Report
                    </button>
                </div>
                
                <div class="report-card">
                    <h4><i class="fas fa-exchange-alt"></i> Borrowing Reports</h4>
                    <p>Track borrowed books, due dates, and overdue items system-wide.</p>
                    <button class="btn btn-primary" onclick="window.location.href='borrowing_reports.php'">
                        <i class="fas fa-file-alt"></i>
                        Generate Report
                    </button>
                </div>
            </div>
        </div>

        <!-- Export Actions -->
        <div class="report-section">
            <div class="section-header">
                <h3><i class="fas fa-download"></i> Export Options</h3>
            </div>
            <div class="report-actions">
                <div class="action-card">
                    <div class="icon">
                        <i class="fas fa-file-pdf"></i>
                    </div>
                    <h4>Export to PDF</h4>
                    <p>Download comprehensive reports in PDF format for sharing and printing.</p>
                    <button class="btn btn-danger" onclick="exportReport('pdf')">
                        <i class="fas fa-file-pdf"></i>
                        Export PDF
                    </button>
                </div>
                
                <div class="action-card">
                    <div class="icon">
                        <i class="fas fa-file-excel"></i>
                    </div>
                    <h4>Export to CSV</h4>
                    <p>Export raw data in CSV format for further analysis in spreadsheet applications.</p>
                    <button class="btn btn-success" onclick="exportReport('csv')">
                        <i class="fas fa-table"></i>
                        Export CSV
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Subscription Distribution Chart
            const subscriptionCtx = document.getElementById('subscriptionChart').getContext('2d');
            const subscriptionChart = new Chart(subscriptionCtx, {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_column($subscriptionStats, 'plan_type')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($subscriptionStats, 'count')); ?>,
                        backgroundColor: [
                            '#0066cc',
                            '#17a2b8',
                            '#6f42c1',
                            '#28a745',
                            '#ffc107'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.raw;
                                }
                            }
                        }
                    }
                }
            });
            
            // Subscription Status Chart
            const statusLabels = <?php echo json_encode(array_column($subscriptionStatusStats, 'status')); ?>;
            const statusData = <?php echo json_encode(array_column($subscriptionStatusStats, 'count')); ?>;
            
            const statusCtx = document.getElementById('subscriptionStatusChart').getContext('2d');
            const statusChart = new Chart(statusCtx, {
                type: 'bar',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        label: 'Subscription Count',
                        data: statusData,
                        backgroundColor: [
                            '#28a745',
                            '#ffc107',
                            '#17a2b8',
                            '#dc3545',
                            '#6c757d'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        });
        
        // Report generation functions
        function generateReport(type) {
            alert('Report generation for ' + type + ' will be implemented in a future update.');
        }
        
        function exportReport(format) {
            if (format === 'pdf') {
                window.location.href = 'export_pdf.php';
            } else if (format === 'csv') {
                window.location.href = 'export_csv.php';
            }
        }
    </script>
</body>
</html>