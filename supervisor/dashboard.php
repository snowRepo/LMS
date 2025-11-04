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

// Check if user is logged in and has supervisor role
if (!is_logged_in() || $_SESSION['user_role'] !== 'supervisor') {
    header('Location: ../login.php');
    exit;
}

// Check subscription status - redirect to expired page if subscription is not active
requireActiveSubscription();

// Get subscription details for display
$subscriptionManager = new SubscriptionManager();
$libraryId = $_SESSION['library_id'];
$subscriptionDetails = $subscriptionManager->getSubscriptionDetails($libraryId);

// Get statistics and library information
try {
    $db = Database::getInstance()->getConnection();
    
    // Get library information
    $stmt = $db->prepare("SELECT * FROM libraries WHERE id = ?");
    $stmt->execute([$libraryId]);
    $libraryInfo = $stmt->fetch();
    
    // Get total books
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM books WHERE library_id = ?");
    $stmt->execute([$libraryId]);
    $totalBooks = $stmt->fetch()['total'];
    
    // Get borrowed books count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM borrowings br JOIN users u ON br.member_id = u.id WHERE u.library_id = ? AND br.status = 'borrowed'");
    $stmt->execute([$libraryId]);
    $borrowedBooks = $stmt->fetch()['total'];
    
    // Get total librarians
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE library_id = ? AND role = 'librarian' AND status = 'active'");
    $stmt->execute([$libraryId]);
    $totalLibrarians = $stmt->fetch()['total'];
    
    // Get total members
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE library_id = ? AND role = 'member' AND status = 'active'");
    $stmt->execute([$libraryId]);
    $totalMembers = $stmt->fetch()['total'];
    
    // Get total categories
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM categories WHERE library_id = ? AND status = 'active'");
    $stmt->execute([$libraryId]);
    $totalCategories = $stmt->fetch()['total'];
    
    // Get recent activities (last 5)
    $stmt = $db->prepare("
        SELECT al.*, u.first_name, u.last_name 
        FROM activity_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        WHERE al.library_id = ? 
        ORDER BY al.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$libraryId]);
    $recentActivities = $stmt->fetchAll();
    
    // Get top 5 most punctual members (highest attendance count)
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.username, 
               COUNT(a.id) as attendance_count
        FROM users u
        LEFT JOIN attendance a ON u.id = a.user_id
        WHERE u.library_id = ? AND u.role = 'member' AND u.status = 'active'
        GROUP BY u.id, u.first_name, u.last_name, u.username
        ORDER BY attendance_count DESC
        LIMIT 5
    ");
    $stmt->execute([$libraryId]);
    $topMembers = $stmt->fetchAll();
    
} catch (Exception $e) {
    die('Error loading dashboard data: ' . $e->getMessage());
}

$pageTitle = 'Supervisor Dashboard';
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
    
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 0;
        }
        
        /* Ensure no default margin on body */
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
            margin: 0;
        }
        
        /* Library Info */
        .library-info {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .library-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .library-header h2 {
            color: #212529;
            margin: 0;
        }
        
        .library-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .detail-group {
            margin-bottom: 1rem;
        }
        
        .detail-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .detail-value {
            font-size: 1.1rem;
            font-weight: 500;
            color: #495057;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        
        .stat-icon.books {
            background: linear-gradient(135deg, #3498DB, #2980B9);
            color: white;
        }
        
        .stat-icon.borrowed {
            background: linear-gradient(135deg, #E74C3C, #C0392B);
            color: white;
        }
        
        .stat-icon.librarians {
            background: linear-gradient(135deg, #9B59B6, #8E44AD);
            color: white;
        }
        
        .stat-icon.members {
            background: linear-gradient(135deg, #2ECC71, #27AE60);
            color: white;
        }
        
        .stat-icon.categories {
            background: linear-gradient(135deg, #F39C12, #E67E22);
            color: white;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 1rem;
        }
        
        /* Subscription Info */
        .subscription-info {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .subscription-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .subscription-header h2 {
            color: #212529;
            margin: 0;
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
        
        .status-trial {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .subscription-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .detail-item {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .detail-item .detail-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .detail-item .detail-value {
            font-size: 1.2rem;
            font-weight: bold;
            color: #495057;
        }
        
        /* Recent Activities and Quick Links Side by Side */
        .activities-links-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        /* Responsive layout for smaller screens */
        @media (max-width: 768px) {
            .activities-links-container {
                grid-template-columns: 1fr;
            }
        }
        
        /* Recent Activities */
        .recent-activities {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
            height: fit-content;
        }
        
        .activities-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .activities-header h2 {
            color: #212529;
            margin: 0;
        }
        
        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .activity-item {
            padding: 1rem 0;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #3498DB;
            flex-shrink: 0;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 500;
            color: #495057;
            margin-bottom: 0.25rem;
        }
        
        .activity-description {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .activity-time {
            color: #adb5bd;
            font-size: 0.8rem;
        }
        
        /* Quick Links */
        .quick-links {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
            height: fit-content;
        }
        
        .links-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .links-header h2 {
            color: #212529;
            margin: 0;
        }
        
        .links-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        
        .link-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            text-decoration: none;
            color: #495057;
            transition: all 0.3s ease;
            display: block;
        }
        
        .link-card:hover {
            background: #3498DB;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
        }
        
        .link-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            display: block;
        }
        
        .link-title {
            font-weight: 500;
            font-size: 1.1rem;
        }
        
        /* Alert for trial period */
        .trial-alert {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .trial-alert i {
            font-size: 1.5rem;
            color: #856404;
        }
        
        .trial-content {
            flex: 1;
        }
        
        .trial-content h3 {
            color: #856404;
            margin: 0 0 0.5rem 0;
        }
        
        .trial-content p {
            color: #856404;
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
        
        /* Top Punctual Members */
        .content-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .card-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .card-header h2 {
            color: #212529;
            margin: 0;
        }
        
        .members-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .member-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .member-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        
        .member-rank {
            margin-right: 1rem;
        }
        
        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            font-weight: bold;
            font-size: 0.8rem;
            background: #6c757d;
            color: white;
        }
        
        .rank-badge.gold {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: #212529;
        }
        
        .rank-badge.silver {
            background: linear-gradient(135deg, #C0C0C0, #A9A9A9);
            color: #212529;
        }
        
        .rank-badge.bronze {
            background: linear-gradient(135deg, #CD7F32, #A0522D);
            color: white;
        }
        
        .member-info {
            flex: 1;
        }
        
        .member-name {
            font-weight: 500;
            color: #495057;
            margin-bottom: 0.25rem;
        }
        
        .member-username {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .member-attendance {
            text-align: center;
        }
        
        .attendance-count {
            font-size: 1.2rem;
            font-weight: bold;
            color: #3498DB;
        }
        
        .attendance-label {
            font-size: 0.8rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php include 'includes/supervisor_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-tachometer-alt"></i> Supervisor Dashboard</h1>
            <p>Welcome back! Here's what's happening with your library today.</p>
        </div>
        
        <?php if ($subscriptionDetails && $subscriptionDetails['is_trial'] && $subscriptionDetails['days_remaining'] <= 7): ?>
        <div class="trial-alert">
            <i class="fas fa-exclamation-triangle"></i>
            <div class="trial-content">
                <h3>Trial Period Ending Soon!</h3>
                <p>Your trial period expires in <?php echo $subscriptionDetails['days_remaining']; ?> day(s). 
                Subscribe now to continue accessing all features.</p>
            </div>
            <a href="subscription.php" class="btn btn-primary">
                <i class="fas fa-crown"></i>
                Manage Subscription
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Library Information -->
        <div class="library-info">
            <div class="library-header">
                <h2>
                    <?php if (!empty($libraryInfo['logo_path'])): ?>
                        <img src="<?php echo APP_URL . '/' . htmlspecialchars(ltrim($libraryInfo['logo_path'], '/')); ?>" alt="Library Logo" style="height: 40px; width: auto; margin-right: 10px; vertical-align: middle;">
                    <?php else: ?>
                        <i class="fas fa-building"></i>
                    <?php endif; ?>
                    Library Details
                </h2>
            </div>
            
            <div class="library-details">
                <div>
                    <div class="detail-group">
                        <div class="detail-label">Library Name</div>
                        <div class="detail-value"><?php echo htmlspecialchars($libraryInfo['library_name'] ?? 'N/A'); ?></div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-label">Website</div>
                        <div class="detail-value"><?php echo !empty($libraryInfo['website']) ? htmlspecialchars($libraryInfo['website']) : 'N/A'; ?></div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-label">Email</div>
                        <div class="detail-value"><?php echo htmlspecialchars($libraryInfo['email'] ?? 'N/A'); ?></div>
                    </div>
                </div>
                
                <div>
                    <div class="detail-group">
                        <div class="detail-label">Phone</div>
                        <div class="detail-value"><?php echo htmlspecialchars($libraryInfo['phone'] ?? 'N/A'); ?></div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-label">Address</div>
                        <div class="detail-value"><?php echo htmlspecialchars($libraryInfo['address'] ?? 'N/A'); ?></div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-label">Status</div>
                        <div class="detail-value">
                            <span class="status-badge status-<?php echo $libraryInfo['status'] ?? 'inactive'; ?>">
                                <?php echo ucfirst($libraryInfo['status'] ?? 'N/A'); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon books">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-value"><?php echo number_format($totalBooks); ?></div>
                <div class="stat-label">Total Books</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon borrowed">
                    <i class="fas fa-book-open"></i>
                </div>
                <div class="stat-value"><?php echo number_format($borrowedBooks); ?></div>
                <div class="stat-label">Borrowed Books</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon librarians">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stat-value"><?php echo number_format($totalLibrarians); ?></div>
                <div class="stat-label">Librarians</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon members">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo number_format($totalMembers); ?></div>
                <div class="stat-label">Members</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon categories">
                    <i class="fas fa-tags"></i>
                </div>
                <div class="stat-value"><?php echo number_format($totalCategories); ?></div>
                <div class="stat-label">Categories</div>
            </div>
        </div>
        
        <!-- Subscription Info -->
        <div class="subscription-info">
            <div class="subscription-header">
                <h2><i class="fas fa-crown"></i> Subscription Information</h2>
                <span class="status-badge status-<?php echo $subscriptionDetails['is_trial'] ? 'trial' : 'active'; ?>">
                    <?php 
                    if ($subscriptionDetails['is_trial']) {
                        echo 'Trial Period';
                    } else {
                        echo ucfirst($subscriptionDetails['plan']) . ' Plan';
                    }
                    ?>
                </span>
            </div>
            
            <div class="subscription-details">
                <div class="detail-item">
                    <div class="detail-label">Current Plan</div>
                    <div class="detail-value">
                        <?php 
                        // Show the current plan status
                        if ($subscriptionDetails['is_trial']) {
                            if (!empty($subscriptionDetails['selected_plan'])) {
                                echo 'Trial (' . ucfirst($subscriptionDetails['selected_plan']) . ')';
                            } else {
                                echo 'Trial Period';
                            }
                        } else {
                            echo ucfirst($subscriptionDetails['plan']) . ' Plan';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Days Remaining</div>
                    <div class="detail-value"><?php echo number_format($subscriptionDetails['days_remaining']); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Books Added</div>
                    <div class="detail-value"><?php echo number_format($subscriptionDetails['current_book_count']); ?></div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Book Limit</div>
                    <div class="detail-value">
                        <?php echo $subscriptionDetails['book_limit'] == -1 ? 'Unlimited' : number_format($subscriptionDetails['book_limit']); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Top Punctual Members -->
        <div class="content-card">
            <div class="card-header">
                <h2><i class="fas fa-trophy"></i> Top Punctual Members</h2>
            </div>
            
            <?php if (count($topMembers) > 0): ?>
            <div class="members-list">
                <?php foreach ($topMembers as $index => $member): ?>
                <div class="member-item">
                    <div class="member-rank">
                        <?php 
                        if ($index === 0) {
                            echo '<span class="rank-badge gold">1st</span>';
                        } elseif ($index === 1) {
                            echo '<span class="rank-badge silver">2nd</span>';
                        } elseif ($index === 2) {
                            echo '<span class="rank-badge bronze">3rd</span>';
                        } else {
                            echo '<span class="rank-badge">' . ($index + 1) . 'th</span>';
                        }
                        ?>
                    </div>
                    <div class="member-info">
                        <div class="member-name"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></div>
                        <div class="member-username">@<?php echo htmlspecialchars($member['username']); ?></div>
                    </div>
                    <div class="member-attendance">
                        <div class="attendance-count"><?php echo number_format($member['attendance_count']); ?></div>
                        <div class="attendance-label">Days Present</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p>No attendance records found for members.</p>
            <?php endif; ?>
        </div>
        
        <!-- Recent Activities and Quick Links Side by Side -->
        <div class="activities-links-container">
            <!-- Recent Activities -->
            <div class="recent-activities">
                <div class="activities-header">
                    <h2><i class="fas fa-history"></i> Recent Activities</h2>
                </div>
                
                <?php if (count($recentActivities) > 0): ?>
                <ul class="activity-list">
                    <?php foreach ($recentActivities as $activity): ?>
                    <li class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">
                                <?php 
                                $userName = !empty($activity['first_name']) ? $activity['first_name'] . ' ' . $activity['last_name'] : 'User';
                                echo htmlspecialchars($userName);
                                ?>
                            </div>
                            <div class="activity-description">
                                <?php echo htmlspecialchars($activity['description']); ?>
                            </div>
                            <div class="activity-time">
                                <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p>No recent activities found.</p>
                <?php endif; ?>
            </div>
            
            <!-- Quick Links -->
            <div class="quick-links">
                <div class="links-header">
                    <h2><i class="fas fa-bolt"></i> Quick Links</h2>
                </div>
                
                <div class="links-grid">
                    <a href="librarians.php" class="link-card">
                        <i class="fas fa-user-tie link-icon"></i>
                        <div class="link-title">Manage Librarians</div>
                    </a>
                    
                    <a href="reports.php" class="link-card">
                        <i class="fas fa-chart-bar link-icon"></i>
                        <div class="link-title">View Reports</div>
                    </a>
                    
                    <a href="messages.php" class="link-card">
                        <i class="fas fa-envelope link-icon"></i>
                        <div class="link-title">Messages</div>
                    </a>
                    
                    <a href="subscription.php" class="link-card">
                        <i class="fas fa-crown link-icon"></i>
                        <div class="link-title">Subscription</div>
                    </a>
                    
                    <a href="profile.php" class="link-card">
                        <i class="fas fa-user link-icon"></i>
                        <div class="link-title">Profile</div>
                    </a>
                    
                    <a href="settings.php" class="link-card">
                        <i class="fas fa-cog link-icon"></i>
                        <div class="link-title">Library Settings</div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>