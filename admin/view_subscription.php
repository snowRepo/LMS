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

// Get library ID from URL parameter
$libraryId = isset($_GET['library_id']) ? (int)$_GET['library_id'] : 0;

if ($libraryId <= 0) {
    header('Location: subscriptions.php');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $subscriptionManager = new SubscriptionManager();
    
    // Get library details
    $stmt = $db->prepare("
        SELECT l.*, u.first_name, u.last_name, u.email as supervisor_email 
        FROM libraries l 
        LEFT JOIN users u ON l.supervisor_id = u.id 
        WHERE l.id = ?
    ");
    $stmt->execute([$libraryId]);
    $library = $stmt->fetch();
    
    if (!$library) {
        header('Location: subscriptions.php');
        exit;
    }
    
    // Get library subscription information
    $subscriptionDetails = $subscriptionManager->getSubscriptionDetails($libraryId);
    
    // Get subscription record directly from database for additional details
    $stmt = $db->prepare("
        SELECT *
        FROM subscriptions
        WHERE library_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$libraryId]);
    $librarySubscription = $stmt->fetch();
    
    // Get library statistics
    // Get total librarians
    $stmt = $db->prepare("SELECT COUNT(*) as total_librarians FROM users WHERE library_id = ? AND role = 'librarian' AND status = 'active'");
    $stmt->execute([$libraryId]);
    $librarianCount = $stmt->fetch()['total_librarians'];
    
    // Get total members
    $stmt = $db->prepare("SELECT COUNT(*) as total_members FROM users WHERE library_id = ? AND role = 'member' AND status = 'active'");
    $stmt->execute([$libraryId]);
    $memberCount = $stmt->fetch()['total_members'];
    
    // Get total books
    $stmt = $db->prepare("SELECT COUNT(*) as total_books FROM books WHERE library_id = ? AND status = 'active'");
    $stmt->execute([$libraryId]);
    $bookCount = $stmt->fetch()['total_books'];
    
} catch (Exception $e) {
    die('Error loading subscription details: ' . $e->getMessage());
}

$pageTitle = 'Subscription Details';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo htmlspecialchars($library['library_name']); ?> - LMS</title>
    
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
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(0, 102, 204, 0.2);
        }
        
        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 102, 204, 0.3);
        }
        
        .library-details {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }
        
        .library-logo-section {
            text-align: center;
        }
        
        .library-logo-large {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border-radius: 12px;
            background-color: var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }
        
        .library-logo-large i {
            font-size: 4rem;
            color: var(--gray-500);
        }
        
        .library-info {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .info-group {
            margin-bottom: 1rem;
        }
        
        .info-group h3 {
            color: var(--gray-800);
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
            border-bottom: 1px solid var(--gray-300);
            padding-bottom: 0.5rem;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 0.75rem;
        }
        
        .info-label {
            font-weight: 600;
            width: 180px;
            color: var(--gray-700);
        }
        
        .info-value {
            flex: 1;
            color: var(--gray-900);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-active {
            background-color: var(--success-color);
            color: white;
        }
        
        .status-inactive {
            background-color: var(--gray-300);
            color: var(--gray-700);
        }
        
        .status-trial {
            background-color: var(--warning-color);
            color: white;
        }
        
        .status-expired {
            background-color: var(--danger-color);
            color: white;
        }
        
        .plan-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .plan-basic {
            background-color: #6c757d;
            color: white;
        }
        
        .plan-standard {
            background-color: #17a2b8;
            color: white;
        }
        
        .plan-premium {
            background-color: #6f42c1;
            color: white;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .stat-card {
            background: var(--gray-100);
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0.5rem 0;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--gray-600);
        }
        
        .subscription-benefits {
            background: var(--gray-100);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .benefits-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        
        .benefits-list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--gray-300);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .benefits-list li:last-child {
            border-bottom: none;
        }
        
        .benefits-list li i {
            color: var(--success-color);
        }
        
        @media (max-width: 768px) {
            .library-details {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/admin_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-file-invoice-dollar"></i> <?php echo $pageTitle; ?></h1>
            <p>Subscription details for <?php echo htmlspecialchars($library['library_name']); ?></p>
        </div>
        
        <div class="content-card">
            <div class="card-header">
                <h2>Subscription Information</h2>
                <a href="subscriptions.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Subscriptions
                </a>
            </div>
            
            <div class="library-details">
                <div class="library-logo-section">
                    <div class="library-logo-large">
                        <?php if (!empty($library['logo_path']) && file_exists('../' . $library['logo_path'])): ?>
                            <img src="../<?php echo htmlspecialchars($library['logo_path']); ?>" alt="Library Logo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 12px;">
                        <?php else: ?>
                            <i class="fas fa-book"></i>
                        <?php endif; ?>
                    </div>
                    <h3><?php echo htmlspecialchars($library['library_name']); ?></h3>
                    <p><?php echo htmlspecialchars($library['library_code']); ?></p>
                </div>
                
                <div class="library-info">
                    <?php if ($subscriptionDetails): ?>
                        <div class="info-group">
                            <h3><i class="fas fa-info-circle"></i> Subscription Details</h3>
                            
                            <div class="info-row">
                                <div class="info-label">Package:</div>
                                <div class="info-value">
                                    <span class="plan-badge plan-<?php echo $subscriptionDetails['plan']; ?>">
                                        <?php echo ucfirst($subscriptionDetails['plan']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="info-row">
                                <div class="info-label">Status:</div>
                                <div class="info-value">
                                    <?php 
                                    $statusClass = 'status-' . $librarySubscription['status'];
                                    if ($librarySubscription['status'] === 'trial' && !empty($librarySubscription['end_date']) && strtotime($librarySubscription['end_date']) < time()) {
                                        $statusClass = 'status-expired';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php 
                                        if ($librarySubscription['status'] === 'trial' && !empty($librarySubscription['end_date']) && strtotime($librarySubscription['end_date']) < time()) {
                                            echo 'Expired';
                                        } else {
                                            echo ucfirst($librarySubscription['status']);
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="info-row">
                                <div class="info-label">Start Date:</div>
                                <div class="info-value"><?php echo date('M j, Y', strtotime($librarySubscription['start_date'])); ?></div>
                            </div>
                            
                            <div class="info-row">
                                <div class="info-label">End Date:</div>
                                <div class="info-value"><?php echo date('M j, Y', strtotime($librarySubscription['end_date'])); ?></div>
                            </div>
                            
                            <div class="info-row">
                                <div class="info-label">Days Remaining:</div>
                                <div class="info-value">
                                    <?php 
                                    if (!empty($librarySubscription['end_date'])) {
                                        $today = new DateTime();
                                        $endDate = new DateTime($librarySubscription['end_date']);
                                        
                                        if ($endDate < $today) {
                                            echo '<span class="status-badge status-expired">Expired</span>';
                                        } else {
                                            $remainingDays = $today->diff($endDate)->days;
                                            echo $remainingDays . ' day' . ($remainingDays != 1 ? 's' : '');
                                        }
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="info-group">
                            <h3><i class="fas fa-star"></i> Plan Benefits</h3>
                            <div class="subscription-benefits">
                                <ul class="benefits-list">
                                    <?php 
                                    $bookLimit = 0;
                                    switch ($subscriptionDetails['plan']) {
                                        case 'basic':
                                            $bookLimit = BASIC_PLAN_BOOK_LIMIT;
                                            echo '<li><i class="fas fa-check-circle"></i> Up to ' . ($bookLimit == -1 ? 'Unlimited' : $bookLimit) . ' books</li>';
                                            echo '<li><i class="fas fa-check-circle"></i> Basic reporting features</li>';
                                            echo '<li><i class="fas fa-check-circle"></i> Email support</li>';
                                            break;
                                        case 'standard':
                                            $bookLimit = STANDARD_PLAN_BOOK_LIMIT;
                                            echo '<li><i class="fas fa-check-circle"></i> Up to ' . ($bookLimit == -1 ? 'Unlimited' : $bookLimit) . ' books</li>';
                                            echo '<li><i class="fas fa-check-circle"></i> Advanced reporting features</li>';
                                            echo '<li><i class="fas fa-check-circle"></i> Priority email support</li>';
                                            echo '<li><i class="fas fa-check-circle"></i> Member attendance tracking</li>';
                                            break;
                                        case 'premium':
                                            $bookLimit = PREMIUM_PLAN_BOOK_LIMIT;
                                            echo '<li><i class="fas fa-check-circle"></i> ' . ($bookLimit == -1 ? 'Unlimited' : $bookLimit) . ' books</li>';
                                            echo '<li><i class="fas fa-check-circle"></i> All reporting features</li>';
                                            echo '<li><i class="fas fa-check-circle"></i> 24/7 priority support</li>';
                                            echo '<li><i class="fas fa-check-circle"></i> Advanced analytics</li>';
                                            echo '<li><i class="fas fa-check-circle"></i> Custom branding options</li>';
                                            break;
                                        default:
                                            echo '<li><i class="fas fa-check-circle"></i> Basic library management features</li>';
                                    }
                                    ?>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="info-group">
                            <h3><i class="fas fa-chart-bar"></i> Library Statistics</h3>
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $bookCount; ?></div>
                                    <div class="stat-label">Books</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $librarianCount; ?></div>
                                    <div class="stat-label">Librarians</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-value"><?php echo $memberCount; ?></div>
                                    <div class="stat-label">Members</div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="info-group">
                            <h3><i class="fas fa-exclamation-circle"></i> No Active Subscription</h3>
                            <p>This library does not have an active subscription.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>