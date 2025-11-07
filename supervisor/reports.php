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

// Get report data
try {
    $db = Database::getInstance()->getConnection();
    
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
    
    // Get top 5 most borrowed books with author information
    $stmt = $db->prepare("
        SELECT b.title, b.author_name, b.isbn, COUNT(br.id) as borrow_count
        FROM borrowings br
        JOIN books b ON br.book_id = b.id
        JOIN users u ON br.member_id = u.id
        WHERE u.library_id = ?
        GROUP BY br.book_id, b.title, b.author_name, b.isbn
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
    
    // Prepare attendance data for chart
    $attendanceChartData = [];
    $attendanceChartLabels = [];
    foreach ($attendanceData as $data) {
        $attendanceChartLabels[] = date('M j', strtotime($data['date']));
        $attendanceChartData[] = $data['attendance_count'];
    }
    
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
    
    <!-- Supervisor Navbar CSS -->
    <link rel="stylesheet" href="css/supervisor_navbar.css">
    
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

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
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
            color: #3498DB;
        }

        .stat-card .number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: #3498DB;
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
            height: 400px; /* Set a fixed height */
        }

        .chart-wrapper {
            position: relative;
            height: 300px; /* Chart height */
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
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
        }
        
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.1);
        }

        .report-card h4 {
            color: #212529;
            margin-top: 0;
            flex: 0 0 auto;
        }
        
        .report-card p {
            flex: 1 1 auto;
            margin-bottom: 1rem;
        }
        
        .report-card .btn {
            margin-top: auto;
            align-self: center;
            justify-content: center;
        }

        .report-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
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
            display: flex;
            flex-direction: column;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.1);
        }

        .action-card .icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #3498DB;
            flex: 0 0 auto;
        }

        .action-card h4 {
            color: #212529;
            margin: 0 0 1rem 0;
            flex: 0 0 auto;
        }
        
        .action-card p {
            flex: 1 1 auto;
            margin-bottom: 1rem;
        }
        
        .action-card .btn {
            margin-top: auto;
            width: 100%;
            justify-content: center;
        }

        @media (max-width: 768px) {
            .report-grid {
                grid-template-columns: 1fr;
            }
            
            .report-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/supervisor_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
            <p>Comprehensive insights into your library's performance</p>
        </div>

        <!-- Key Statistics -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="number"><?php echo $totalMembers; ?></div>
                <div class="label">Total Members</div>
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
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="number"><?php echo $totalLibrarians; ?></div>
                <div class="label">Librarians</div>
            </div>
            
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="number"><?php echo $activeMembersToday; ?></div>
                <div class="label">Active Today</div>
            </div>
            
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="number"><?php echo $borrowedBooks; ?></div>
                <div class="label">Books Borrowed</div>
            </div>
            
            <div class="stat-card">
                <div class="icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="number"><?php echo $overdueBooks; ?></div>
                <div class="label">Overdue Books</div>
            </div>
        </div>

        <!-- Attendance Chart -->
        <div class="chart-container">
            <div class="chart-header">
                <h3><i class="fas fa-chart-line"></i> Weekly Attendance Trend</h3>
            </div>
            <div class="chart-wrapper">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>

        <!-- Popular Books -->
        <div class="report-section">
            <div class="section-header">
                <h3><i class="fas fa-fire"></i> Most Popular Books</h3>
            </div>
            <div class="table-container">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Book Title</th>
                            <th>Author</th>
                            <th>ISBN</th>
                            <th>Borrow Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($popularBooks)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center;">No borrowing data available</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($popularBooks as $book): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($book['title']); ?></td>
                                    <td><?php echo htmlspecialchars($book['author_name'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($book['isbn'] ?? ''); ?></td>
                                    <td><?php echo $book['borrow_count']; ?></td>
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
                    <p>Generate detailed reports on member information with export capabilities in CSV format.</p>
                    <a href="member_reports.php" class="btn btn-primary">
                        <i class="fas fa-file-alt"></i>
                        Generate Report
                    </a>
                </div>
                
                <div class="report-card">
                    <h4><i class="fas fa-book-open"></i> Book Reports</h4>
                    <p>Analyze book inventory, borrowing patterns, and availability statistics.</p>
                    <a href="book_reports.php" class="btn btn-primary">
                        <i class="fas fa-file-alt"></i>
                        Generate Report
                    </a>
                </div>
                
                <div class="report-card">
                    <h4><i class="fas fa-exchange-alt"></i> Borrowing Reports</h4>
                    <p>Track borrowed books, due dates, and overdue items.</p>
                    <a href="borrowing_reports.php" class="btn btn-primary">
                        <i class="fas fa-file-alt"></i>
                        Generate Report
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="report-section">
            <div class="section-header">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            </div>
            <div class="report-actions">
                <div class="action-card">
                    <div class="icon">
                        <i class="fas fa-file-pdf"></i>
                    </div>
                    <h4>Export Reports</h4>
                    <p>Download reports in PDF format for sharing and printing.</p>
                    <button class="btn btn-danger" onclick="window.location.href='export_pdf.php'">
                        <i class="fas fa-download"></i>
                        Export PDF
                    </button>
                </div>
                
                <div class="action-card">
                    <div class="icon">
                        <i class="fas fa-file-excel"></i>
                    </div>
                    <h4>Export Data</h4>
                    <p>Export raw data in CSV format for further analysis.</p>
                    <button class="btn btn-success" onclick="window.location.href='export_csv.php'">
                        <i class="fas fa-table"></i>
                        Export CSV
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize attendance chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('attendanceChart').getContext('2d');
            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($attendanceChartLabels); ?>,
                    datasets: [{
                        label: 'Daily Attendance',
                        data: <?php echo json_encode($attendanceChartData); ?>,
                        backgroundColor: 'rgba(52, 152, 219, 0.7)',
                        borderColor: '#3498DB',
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
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                precision: 0
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>