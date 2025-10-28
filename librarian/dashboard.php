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

// Check if user is logged in and has librarian role
if (!is_logged_in() || $_SESSION['user_role'] !== 'librarian') {
    header('Location: ../login.php');
    exit;
}

// Initialize database connection
$db = Database::getInstance()->getConnection();

// Fetch statistics data
$totalBooks = 0;
$totalMembers = 0;
$borrowedBooks = 0;
$attendanceToday = 0;

try {
    // Total books
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM books WHERE library_id = (SELECT library_id FROM users WHERE id = ?)");
    $stmt->execute([$_SESSION['user_id']]);
    $totalBooks = $stmt->fetch()['total'];
    
    // Total members
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'member' AND library_id = (SELECT library_id FROM users WHERE id = ?)");
    $stmt->execute([$_SESSION['user_id']]);
    $totalMembers = $stmt->fetch()['total'];
    
    // Borrowed books
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM borrowings b JOIN books bk ON b.book_id = bk.id WHERE bk.library_id = (SELECT library_id FROM users WHERE id = ?) AND b.status = 'active'");
    $stmt->execute([$_SESSION['user_id']]);
    $borrowedBooks = $stmt->fetch()['total'];
    
    // Attendance today
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM attendance WHERE library_id = (SELECT library_id FROM users WHERE id = ?) AND attendance_date = CURDATE()");
    $stmt->execute([$_SESSION['user_id']]);
    $attendanceToday = $stmt->fetch()['total'];
    
} catch (Exception $e) {
    // Handle error silently
    $totalBooks = 0;
    $totalMembers = 0;
    $borrowedBooks = 0;
    $attendanceToday = 0;
}

// Fetch recent activity for this librarian
$recentActivities = [];
try {
    $stmt = $db->prepare("
        SELECT al.*, u.first_name, u.last_name, 
               CASE 
                   WHEN al.action = 'add_book' THEN 'New Book Added'
                   WHEN al.action = 'add_member' THEN 'New Member Registered'
                   WHEN al.action = 'borrow_book' THEN 'Book Borrowed'
                   WHEN al.action = 'return_book' THEN 'Book Returned'
                   WHEN al.action = 'update_member' THEN 'Member Profile Updated'
                   ELSE al.action
               END as activity_title,
               al.description as activity_description,
               al.created_at
        FROM activity_logs al
        JOIN users u ON al.user_id = u.id
        WHERE al.library_id = (SELECT library_id FROM users WHERE id = ?)
        AND al.user_id = ?
        ORDER BY al.created_at DESC
        LIMIT 4
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $recentActivities = $stmt->fetchAll();
} catch (Exception $e) {
    // Handle error silently
    $recentActivities = [];
}

$pageTitle = 'Librarian Dashboard';
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 0;
        }
        
        /* Dashboard Content Styles */
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            margin-top: 30px; /* Further reduced margin to decrease space between navbar and heading */
        }
        
        .dashboard-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .dashboard-header h1 {
            color: #495057;
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
        }
        
        .dashboard-header p {
            color: #6c757d;
            font-size: 1.2rem;
        }
        
        /* Stat Cards Grid */
        .stat-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr); /* Changed to 4 columns in one row */
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 6px 25px rgba(0,0,0,0.1);
            padding: 1.8rem;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #e9ecef;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
        }
        
        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.2rem;
        }
        
        .stat-card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #6c757d;
        }
        
        .stat-card-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-card-value {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #495057;
        }
        
        .stat-card-footer {
            font-size: 0.9rem;
            color: #adb5bd;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* Color variations for stat cards */
        .books-total .stat-card-icon {
            background: rgba(142, 68, 173, 0.15);
            color: #8e44ad;
        }
        
        .members-total .stat-card-icon {
            background: rgba(52, 152, 219, 0.15);
            color: #3498db;
        }
        
        .books-borrowed .stat-card-icon {
            background: rgba(241, 196, 15, 0.15);
            color: #f1c40f;
        }
        
        .attendance-today .stat-card-icon {
            background: rgba(46, 204, 113, 0.15);
            color: #2ecc71;
        }
        
        .books-due .stat-card-icon {
            background: rgba(231, 76, 60, 0.15);
            color: #e74c3c;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .stat-cards {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
            
            .stat-card-value {
                font-size: 1.8rem;
            }
        }
        
        /* Recent Activity and Quick Links */
        .dashboard-sections {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .recent-activity, .quick-links {
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 6px 25px rgba(0,0,0,0.1);
            padding: 1.8rem;
            border: 1px solid #e9ecef;
        }
        
        .section-header {
            margin-bottom: 1.5rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .section-header h2 {
            color: #495057;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-header h2 i {
            color: #8e44ad;
        }
        
        /* Recent Activity List */
        .activity-list {
            list-style: none;
        }
        
        .activity-item {
            padding: 1rem 0;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            gap: 15px;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            margin-bottom: 0.3rem;
            color: #495057;
        }
        
        .activity-description {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.3rem;
        }
        
        .activity-time {
            font-size: 0.8rem;
            color: #adb5bd;
        }
        
        /* Quick Links Grid */
        .quick-links-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        
        .quick-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.2rem 0.5rem;
            background: rgba(142, 68, 173, 0.05);
            border-radius: 12px;
            text-decoration: none;
            color: #495057;
            transition: all 0.3s ease;
            border: 1px solid rgba(142, 68, 173, 0.1);
        }
        
        .quick-link:hover {
            background: rgba(142, 68, 173, 0.15);
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .quick-link i {
            font-size: 1.5rem;
            margin-bottom: 0.8rem;
            color: #8e44ad;
        }
        
        .quick-link span {
            font-weight: 500;
            text-align: center;
        }
        
        /* Responsive adjustments for dashboard sections */
        @media (max-width: 992px) {
            .dashboard-sections {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 576px) {
            .quick-links-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Include Librarian Navbar -->
    <?php include 'includes/librarian_navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1><i class="fas fa-book"></i> Librarian Dashboard</h1>
            <p>Welcome back! Here's your library overview</p>
        </div>

        <div class="stat-cards">
            <!-- Total Books Card -->
            <div class="stat-card books-total">
                <div class="stat-card-header">
                    <div class="stat-card-title">Total Books</div>
                    <div class="stat-card-icon">
                        <i class="fas fa-book"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?php echo number_format($totalBooks); ?></div>
                <div class="stat-card-footer">
                    <i class="fas fa-arrow-up"></i>
                    <span>in your library</span>
                </div>
            </div>
            
            <!-- Total Members Card -->
            <div class="stat-card members-total">
                <div class="stat-card-header">
                    <div class="stat-card-title">Total Members</div>
                    <div class="stat-card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?php echo number_format($totalMembers); ?></div>
                <div class="stat-card-footer">
                    <i class="fas fa-arrow-up"></i>
                    <span>registered members</span>
                </div>
            </div>
            
            <!-- Borrowed Books Card -->
            <div class="stat-card books-borrowed">
                <div class="stat-card-header">
                    <div class="stat-card-title">Borrowed Books</div>
                    <div class="stat-card-icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?php echo number_format($borrowedBooks); ?></div>
                <div class="stat-card-footer">
                    <i class="fas fa-book"></i>
                    <span>currently borrowed</span>
                </div>
            </div>
            
            <!-- Attendance Today Card -->
            <div class="stat-card attendance-today">
                <div class="stat-card-header">
                    <div class="stat-card-title">Attendance Today</div>
                    <div class="stat-card-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?php echo number_format($attendanceToday); ?></div>
                <div class="stat-card-footer">
                    <i class="fas fa-calendar-day"></i>
                    <span>visitors today</span>
                </div>
            </div>
        </div>
        
        <div class="dashboard-sections">
            <!-- Recent Activity -->
            <div class="recent-activity">
                <div class="section-header">
                    <h2><i class="fas fa-history"></i> Recent Activity</h2>
                </div>
                
                <ul class="activity-list">
                    <?php if (!empty($recentActivities)): ?>
                        <?php foreach ($recentActivities as $activity): ?>
                        <li class="activity-item">
                            <div class="activity-icon">
                                <?php 
                                switch ($activity['action']) {
                                    case 'add_book':
                                        echo '<i class="fas fa-book"></i>';
                                        break;
                                    case 'add_member':
                                        echo '<i class="fas fa-user-plus"></i>';
                                        break;
                                    case 'borrow_book':
                                        echo '<i class="fas fa-exchange-alt"></i>';
                                        break;
                                    case 'return_book':
                                        echo '<i class="fas fa-book-reader"></i>';
                                        break;
                                    case 'update_member':
                                        echo '<i class="fas fa-user-edit"></i>';
                                        break;
                                    default:
                                        echo '<i class="fas fa-history"></i>';
                                }
                                ?>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title"><?php echo htmlspecialchars($activity['activity_title']); ?></div>
                                <div class="activity-description"><?php echo htmlspecialchars($activity['activity_description'] ?? 'Activity performed'); ?></div>
                                <div class="activity-time"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="activity-item">
                            <div class="activity-content">
                                <div class="activity-title">No Recent Activity</div>
                                <div class="activity-description">You haven't performed any activities yet</div>
                            </div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- Quick Links -->
            <div class="quick-links">
                <div class="section-header">
                    <h2><i class="fas fa-bolt"></i> Quick Links</h2>
                </div>
                
                <div class="quick-links-grid">
                    <a href="books.php" class="quick-link">
                        <i class="fas fa-plus-circle"></i>
                        <span>Add Book</span>
                    </a>
                    
                    <a href="members.php" class="quick-link">
                        <i class="fas fa-user-plus"></i>
                        <span>Add Member</span>
                    </a>
                    
                    <a href="process_borrowing.php" class="quick-link">
                        <i class="fas fa-exchange-alt"></i>
                        <span>Borrow Book</span>
                    </a>
                    
                    <a href="borrowing.php" class="quick-link">
                        <i class="fas fa-tasks"></i>
                        <span>Manage Borrowing</span>
                    </a>
                    
                    <a href="profile.php" class="quick-link">
                        <i class="fas fa-user-cog"></i>
                        <span>Profile Settings</span>
                    </a>
                    
                    <a href="reservations.php" class="quick-link">
                        <i class="fas fa-calendar-check"></i>
                        <span>Reservations</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>