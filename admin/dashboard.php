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

// Get statistics
$totalLibraries = 0;
$totalUsers = 0;
$totalActiveSubscriptions = 0;

// Get recent activity
$recentActivities = [];

try {
    $db = Database::getInstance()->getConnection();
    
    // Get total libraries
    $stmt = $db->query("SELECT COUNT(*) as count FROM libraries WHERE status = 'active'");
    $totalLibraries = $stmt->fetch()['count'];
    
    // Get total users (all roles combined)
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
    $totalUsers = $stmt->fetch()['count'];
    
    // Get total active subscriptions
    $stmt = $db->query("SELECT COUNT(*) as count FROM subscriptions WHERE status = 'active' AND end_date > CURDATE()");
    $totalActiveSubscriptions = $stmt->fetch()['count'];
    
    // Get recent activity for admin
    $stmt = $db->prepare("
        SELECT al.*, u.first_name, u.last_name, u.role,
               CASE 
                   WHEN al.action = 'add_library' THEN 'New Library Added'
                   WHEN al.action = 'add_user' THEN 'New User Registered'
                   WHEN al.action = 'update_library' THEN 'Library Updated'
                   WHEN al.action = 'update_user' THEN 'User Profile Updated'
                   WHEN al.action = 'delete_library' THEN 'Library Deleted'
                   WHEN al.action = 'delete_user' THEN 'User Deleted'
                   ELSE al.action
               END as activity_title
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recentActivities = $stmt->fetchAll();
    
} catch (Exception $e) {
    // Handle errors silently
    $recentActivities = [];
}

$pageTitle = 'Admin Dashboard';
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
        body {
            background: #f8f9fa;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #495057;
            padding-top: 60px; /* Space for navbar */
        }

        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .dashboard-header {
            margin-bottom: 30px;
            text-align: center;
        }

        .dashboard-header h1 {
            color: #2c3e50;
            font-size: 1.8rem;
            margin-bottom: 10px;
        }

        .dashboard-header p {
            color: #6c757d;
            font-size: 1.1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
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

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin: 0 auto 1rem;
        }

        .bg-primary {
            background: linear-gradient(135deg, #007AFF 0%, #0066cc 100%); /* macOS vibrant blue */
        }

        .bg-success {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%); /* Deep green */
        }

        .bg-info {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
        }

        .bg-warning {
            background: linear-gradient(135deg, #f39c12 0%, #d35400 100%);
        }

        .stat-info h3 {
            font-size: 2rem;
            margin: 0 0 5px 0;
            color: #2c3e50;
            text-align: center;
        }

        .stat-info p {
            margin: 0;
            color: #6c757d;
            font-size: 0.95rem;
            text-align: center;
        }

        /* Dashboard Sections Grid */
        .dashboard-sections {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }

        /* Recent Activity */
        .recent-activity {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 25px;
        }

        .section-header {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }

        .section-header h2 {
            color: #2c3e50;
            font-size: 1.5rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .activity-list {
            list-style: none;
        }

        .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            gap: 15px;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            font-size: 1.2rem;
            color: #0066cc;
            padding-top: 3px;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: #495057;
        }

        .activity-description {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .activity-time {
            font-size: 0.8rem;
            color: #adb5bd;
        }

        /* Quick Links */
        .quick-links {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 25px;
        }

        .quick-links-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .quick-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 15px 10px;
            background: rgba(0, 102, 204, 0.05);
            border-radius: 10px;
            text-decoration: none;
            color: #495057;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 102, 204, 0.1);
        }

        .quick-link:hover {
            background: rgba(0, 102, 204, 0.15);
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .quick-link i {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #0066cc;
        }

        .quick-link span {
            font-weight: 500;
            text-align: center;
            font-size: 0.9rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .stat-icon {
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
                margin-right: 15px;
            }
            
            .stat-info h3 {
                font-size: 1.5rem;
            }
            
            .dashboard-sections {
                grid-template-columns: 1fr;
            }
            
            .quick-links-grid {
                grid-template-columns: repeat(2, 1fr);
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
    <?php include 'includes/admin_navbar.php'; ?>
    
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1><i class="fas fa-tachometer-alt"></i> Dashboard Overview</h1>
            <p>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>. Here's what's happening today.</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon bg-primary">
                    <i class="fas fa-building"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $totalLibraries; ?></h3>
                    <p>Libraries</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon bg-success">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $totalUsers; ?></h3>
                    <p>Users</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon bg-info">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $totalActiveSubscriptions; ?></h3>
                    <p>Active Subscriptions</p>
                </div>
            </div>
        </div>

        <!-- Dashboard Sections -->
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
                                    case 'add_library':
                                        echo '<i class="fas fa-building"></i>';
                                        break;
                                    case 'add_user':
                                        echo '<i class="fas fa-user-plus"></i>';
                                        break;
                                    case 'update_library':
                                        echo '<i class="fas fa-edit"></i>';
                                        break;
                                    case 'update_user':
                                        echo '<i class="fas fa-user-edit"></i>';
                                        break;
                                    case 'delete_library':
                                        echo '<i class="fas fa-trash"></i>';
                                        break;
                                    case 'delete_user':
                                        echo '<i class="fas fa-user-times"></i>';
                                        break;
                                    default:
                                        echo '<i class="fas fa-history"></i>';
                                }
                                ?>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title"><?php echo htmlspecialchars($activity['activity_title']); ?></div>
                                <div class="activity-description">
                                    <?php 
                                    if (!empty($activity['first_name']) && !empty($activity['last_name'])) {
                                        echo 'By ' . htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']);
                                        if (!empty($activity['role'])) {
                                            echo ' (' . ucfirst($activity['role']) . ')';
                                        }
                                    } else {
                                        echo 'System activity';
                                    }
                                    ?>
                                </div>
                                <div class="activity-time"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="activity-item">
                            <div class="activity-content">
                                <div class="activity-title">No Recent Activity</div>
                                <div class="activity-description">No system activities recorded yet</div>
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
                    <a href="libraries.php" class="quick-link">
                        <i class="fas fa-building"></i>
                        <span>Manage Libraries</span>
                    </a>
                    
                    <a href="users.php" class="quick-link">
                        <i class="fas fa-users"></i>
                        <span>Manage Users</span>
                    </a>
                    
                    <a href="subscriptions.php" class="quick-link">
                        <i class="fas fa-credit-card"></i>
                        <span>Subscriptions</span>
                    </a>
                    
                    <a href="reports.php" class="quick-link">
                        <i class="fas fa-chart-bar"></i>
                        <span>View Reports</span>
                    </a>
                    
                    <a href="support.php" class="quick-link">
                        <i class="fas fa-headset"></i>
                        <span>Support Tickets</span>
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