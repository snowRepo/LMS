<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';
require_once '../includes/SubscriptionCheck.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has member role
if (!is_logged_in() || $_SESSION['user_role'] !== 'member') {
    header('Location: ../login.php');
    exit;
}

// Check subscription status - redirect to expired page if subscription is not active
requireActiveSubscription();

// Initialize database connection
$db = Database::getInstance()->getConnection();

// Fetch member statistics
$borrowedBooks = 0;
$reservedBooks = 0;
$dueBooks = 0;
$booksDueIn24Hours = 0; // New stat for books due in 24 hours

try {
    // Borrowed books count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM borrowings WHERE member_id = ? AND status = 'active'");
    $stmt->execute([$_SESSION['user_id']]);
    $borrowedBooks = $stmt->fetch()['total'];
    
    // Reserved books count - Fixed to check for 'approved' status instead of 'active'
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM reservations WHERE member_id = ? AND status = 'approved'");
    $stmt->execute([$_SESSION['user_id']]);
    $reservedBooks = $stmt->fetch()['total'];
    
    // Due books count (overdue)
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM borrowings WHERE member_id = ? AND status = 'active' AND due_date < CURDATE()");
    $stmt->execute([$_SESSION['user_id']]);
    $dueBooks = $stmt->fetch()['total'];
    
    // Books due in 24 hours
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM borrowings WHERE member_id = ? AND status = 'active' AND due_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)");
    $stmt->execute([$_SESSION['user_id']]);
    $booksDueIn24Hours = $stmt->fetch()['total'];
    
} catch (Exception $e) {
    // Handle error silently
    $borrowedBooks = 0;
    $reservedBooks = 0;
    $dueBooks = 0;
    $booksDueIn24Hours = 0;
}

// Fetch library information
$libraryInfo = [];
try {
    $stmt = $db->prepare("
        SELECT l.library_name, l.address, l.phone, l.email, l.website, l.logo_path
        FROM libraries l
        JOIN users u ON l.id = u.library_id
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $libraryInfo = $stmt->fetch();
} catch (Exception $e) {
    // Handle error silently
    $libraryInfo = [];
}

// Fetch recent activity for this member (borrowings, reservations, and profile updates)
$recentActivity = [];
try {
    // First, try to get activities with profile updates (if updated_at column exists)
    try {
        $stmt = $db->prepare("
            SELECT 
                'borrowing' as activity_type,
                b.id as activity_id,
                b.status,
                b.created_at,
                bk.title,
                bk.author_name as author,
                CASE 
                    WHEN b.status = 'active' THEN 'Book Borrowed'
                    WHEN b.status = 'returned' THEN 'Book Returned'
                    ELSE 'Activity'
                END as activity_title
            FROM borrowings b
            JOIN books bk ON b.book_id = bk.id
            WHERE b.member_id = ?
            
            UNION ALL
            
            SELECT 
                'reservation' as activity_type,
                r.id as activity_id,
                r.status,
                r.created_at,
                bk.title,
                bk.author_name as author,
                CASE 
                    WHEN r.status = 'pending' THEN 'Reservation Made'
                    WHEN r.status = 'approved' THEN 'Reservation Approved'
                    WHEN r.status = 'rejected' THEN 'Reservation Rejected'
                    ELSE CONCAT('Reservation ', r.status)
                END as activity_title
            FROM reservations r
            JOIN books bk ON r.book_id = bk.id
            WHERE r.member_id = ?
            
            UNION ALL
            
            SELECT 
                'profile' as activity_type,
                u.id as activity_id,
                'updated' as status,
                u.updated_at as created_at,
                'Profile' as title,
                '' as author,
                'Profile Updated' as activity_title
            FROM users u
            WHERE u.id = ? AND u.updated_at IS NOT NULL AND u.updated_at > '2000-01-01'
            
            ORDER BY created_at DESC
            LIMIT 4
        ");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
        $recentActivity = $stmt->fetchAll();
    } catch (Exception $e) {
        // If the above query fails due to missing updated_at column, fall back to borrowings and reservations only
        $stmt = $db->prepare("
            SELECT 
                'borrowing' as activity_type,
                b.id as activity_id,
                b.status,
                b.created_at,
                bk.title,
                bk.author_name as author,
                CASE 
                    WHEN b.status = 'active' THEN 'Book Borrowed'
                    WHEN b.status = 'returned' THEN 'Book Returned'
                    ELSE 'Activity'
                END as activity_title
            FROM borrowings b
            JOIN books bk ON b.book_id = bk.id
            WHERE b.member_id = ?
            
            UNION ALL
            
            SELECT 
                'reservation' as activity_type,
                r.id as activity_id,
                r.status,
                r.created_at,
                bk.title,
                bk.author_name as author,
                CASE 
                    WHEN r.status = 'pending' THEN 'Reservation Made'
                    WHEN r.status = 'approved' THEN 'Reservation Approved'
                    WHEN r.status = 'rejected' THEN 'Reservation Rejected'
                    ELSE CONCAT('Reservation ', r.status)
                END as activity_title
            FROM reservations r
            JOIN books bk ON r.book_id = bk.id
            WHERE r.member_id = ?
            
            ORDER BY created_at DESC
            LIMIT 4
        ");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
        $recentActivity = $stmt->fetchAll();
    }
} catch (Exception $e) {
    // Handle error silently
    $recentActivity = [];
}

$pageTitle = 'Member Dashboard';
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
            margin-top: 20px; /* Increased margin to push header down slightly for observation */
        }
        
        .dashboard-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .dashboard-header h1 {
            color: #212529;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .dashboard-header p {
            color: #6c757d;
            font-size: 1.1rem;
        }
        
        /* Library Info Card */
        .library-info {
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 6px 25px rgba(0,0,0,0.1);
            padding: 1.8rem;
            border: 1px solid #e9ecef;
            margin-bottom: 2rem;
        }
        
        .library-header {
            margin-bottom: 1.5rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .library-header h2 {
            color: #495057;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .library-header h2 i {
            color: #3498db;
        }
        
        .library-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .library-detail-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .library-detail-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.3rem;
        }
        
        .library-detail-value {
            font-weight: 500;
            color: #495057;
        }
        
        /* Stat Cards Grid */
        .stat-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
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
        .books-borrowed .stat-card-icon {
            background: rgba(52, 152, 219, 0.15);
            color: #3498db;
        }
        
        .books-reserved .stat-card-icon {
            background: rgba(155, 89, 182, 0.15);
            color: #9b59b6;
        }
        
        .books-due .stat-card-icon {
            background: rgba(231, 76, 60, 0.15);
            color: #e74c3c;
        }
        
        .fines-total .stat-card-icon {
            background: rgba(241, 196, 15, 0.15);
            color: #f1c40f;
        }
        
        /* Dashboard Sections Grid */
        .dashboard-sections {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        /* Recent Activity */
        .recent-activity {
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
            color: #3498db;
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
        
        .activity-icon {
            font-size: 1.2rem;
            color: #3498db;
            padding-top: 3px;
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
        
        /* Quick Links */
        .quick-links {
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 6px 25px rgba(0,0,0,0.1);
            padding: 1.8rem;
            border: 1px solid #e9ecef;
        }
        
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
            background: rgba(52, 152, 219, 0.05);
            border-radius: 12px;
            text-decoration: none;
            color: #495057;
            transition: all 0.3s ease;
            border: 1px solid rgba(52, 152, 219, 0.1);
        }
        
        .quick-link:hover {
            background: rgba(52, 152, 219, 0.15);
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .quick-link i {
            font-size: 1.5rem;
            margin-bottom: 0.8rem;
            color: #3498db;
        }
        
        .quick-link span {
            font-weight: 500;
            text-align: center;
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .stat-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .dashboard-sections {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .stat-cards {
                grid-template-columns: 1fr;
            }
            
            .stat-card-value {
                font-size: 1.8rem;
            }
            
            .quick-links-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 576px) {
            .quick-links-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/member_navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1><i class="fas fa-book-reader"></i> Member Dashboard</h1>
            <p>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Member'); ?>! Here's your library overview</p>
        </div>

        <!-- Library Information -->
        <?php if (!empty($libraryInfo)): ?>
        <div class="library-info">
            <div class="library-header">
                <?php if (!empty($libraryInfo['logo_path']) && file_exists('../' . $libraryInfo['logo_path'])): ?>
                    <h2><img src="../<?php echo htmlspecialchars($libraryInfo['logo_path']); ?>" alt="Library Logo" style="height: 40px; width: auto; margin-right: 12px; vertical-align: middle;"> <?php echo htmlspecialchars($libraryInfo['library_name']); ?></h2>
                <?php else: ?>
                    <h2><i class="fas fa-university"></i> <?php echo htmlspecialchars($libraryInfo['library_name']); ?></h2>
                <?php endif; ?>
            </div>
            
            <div class="library-details">
                <div class="library-detail-item">
                    <span class="library-detail-label">Library Website</span>
                    <span class="library-detail-value"><?php echo htmlspecialchars($libraryInfo['website']); ?></span>
                </div>
                
                <?php if (!empty($libraryInfo['address'])): ?>
                <div class="library-detail-item">
                    <span class="library-detail-label">Address</span>
                    <span class="library-detail-value"><?php echo htmlspecialchars($libraryInfo['address']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($libraryInfo['phone'])): ?>
                <div class="library-detail-item">
                    <span class="library-detail-label">Phone</span>
                    <span class="library-detail-value"><?php echo htmlspecialchars($libraryInfo['phone']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($libraryInfo['email'])): ?>
                <div class="library-detail-item">
                    <span class="library-detail-label">Email</span>
                    <span class="library-detail-value"><?php echo htmlspecialchars($libraryInfo['email']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="stat-cards">
            <!-- Borrowed Books Card -->
            <div class="stat-card books-borrowed">
                <div class="stat-card-header">
                    <div class="stat-card-title">Borrowed Books</div>
                    <div class="stat-card-icon">
                        <i class="fas fa-book"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?php echo number_format($borrowedBooks); ?></div>
                <div class="stat-card-footer">
                    <i class="fas fa-book"></i>
                    <span>currently borrowed</span>
                </div>
            </div>
            
            <!-- Reserved Books Card -->
            <div class="stat-card books-reserved">
                <div class="stat-card-header">
                    <div class="stat-card-title">Reserved Books</div>
                    <div class="stat-card-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?php echo number_format($reservedBooks); ?></div>
                <div class="stat-card-footer">
                    <i class="fas fa-clock"></i>
                    <span>waiting for pickup</span>
                </div>
            </div>
            
            <!-- Due Books Card -->
            <div class="stat-card books-due">
                <div class="stat-card-header">
                    <div class="stat-card-title">Due Books</div>
                    <div class="stat-card-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?php echo number_format($dueBooks); ?></div>
                <div class="stat-card-footer">
                    <i class="fas fa-calendar-times"></i>
                    <span>overdue</span>
                </div>
            </div>
            
            <!-- Books Due Tomorrow Card -->
            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-card-title">Due Tomorrow</div>
                    <div class="stat-card-icon" style="background: rgba(243, 156, 18, 0.15); color: #f39c12;">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?php echo number_format($booksDueIn24Hours); ?></div>
                <div class="stat-card-footer">
                    <i class="fas fa-info-circle"></i>
                    <span>due in 24 hours</span>
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
                    <?php if (!empty($recentActivity)): ?>
                        <?php foreach ($recentActivity as $activity): ?>
                        <li class="activity-item">
                            <div class="activity-icon">
                                <?php 
                                // Determine icon based on activity type
                                if (isset($activity['activity_type']) && $activity['activity_type'] == 'borrowing'): 
                                    if ($activity['status'] == 'active'): ?>
                                        <i class="fas fa-book"></i>
                                    <?php else: ?>
                                        <i class="fas fa-book-reader"></i>
                                    <?php endif; ?>
                                <?php elseif (isset($activity['activity_type']) && $activity['activity_type'] == 'reservation'): ?>
                                    <?php if ($activity['status'] == 'pending'): ?>
                                        <i class="fas fa-calendar-plus"></i>
                                    <?php elseif ($activity['status'] == 'approved'): ?>
                                        <i class="fas fa-calendar-check"></i>
                                    <?php elseif ($activity['status'] == 'rejected'): ?>
                                        <i class="fas fa-calendar-times"></i>
                                    <?php else: ?>
                                        <i class="fas fa-calendar"></i>
                                    <?php endif; ?>
                                <?php elseif (isset($activity['activity_type']) && $activity['activity_type'] == 'profile'): ?>
                                    <i class="fas fa-user"></i>
                                <?php else: ?>
                                    <?php if ($activity['status'] == 'active'): ?>
                                        <i class="fas fa-book"></i>
                                    <?php else: ?>
                                        <i class="fas fa-book-reader"></i>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title"><?php echo htmlspecialchars($activity['activity_title']); ?></div>
                                <div class="activity-description">
                                    <?php 
                                    // Show appropriate description based on activity type
                                    if (isset($activity['activity_type']) && $activity['activity_type'] == 'profile'): ?>
                                        Your profile information was updated
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($activity['title']); ?> 
                                        <?php if (!empty($activity['author'])): ?>
                                            by <?php echo htmlspecialchars($activity['author']); ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="activity-time">
                                    <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                </div>
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
                        <i class="fas fa-book"></i>
                        <span>Browse Books</span>
                    </a>
                    
                    <a href="borrowing.php" class="quick-link">
                        <i class="fas fa-exchange-alt"></i>
                        <span>My Borrowings</span>
                    </a>
                    
                    <a href="reservations.php" class="quick-link">
                        <i class="fas fa-calendar-check"></i>
                        <span>My Reservations</span>
                    </a>
                    
                    <a href="messages.php" class="quick-link">
                        <i class="fas fa-envelope"></i>
                        <span>Messages</span>
                    </a>
                    
                    <a href="notifications.php" class="quick-link">
                        <i class="fas fa-bell"></i>
                        <span>Notifications</span>
                    </a>
                    
                    <a href="profile.php" class="quick-link">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>