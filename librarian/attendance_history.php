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

// Check if user is logged in and has librarian role
if (!is_logged_in() || $_SESSION['user_role'] !== 'librarian') {
    header('Location: ../login.php');
    exit;
}

// Check subscription status
$subscriptionManager = new SubscriptionManager();
$libraryId = $_SESSION['library_id'];
$hasActiveSubscription = $subscriptionManager->hasActiveSubscription($libraryId);

if (!$hasActiveSubscription) {
    header('Location: ../subscription.php');
    exit;
}

// Get user ID from URL parameter
$userId = isset($_GET['user_id']) ? trim($_GET['user_id']) : '';

if (empty($userId)) {
    header('Location: members.php?error=Invalid user ID');
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
        header('Location: members.php?error=Member not found');
        exit;
    }
    
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
    
    $pageTitle = 'Attendance History';
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
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
            color: #495057;
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
            color: #495057;
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

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .form-row {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .member-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background-color: #f8f9fa;
            border-radius: 8px;
        }

        .member-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .member-details {
            flex: 1;
        }

        .member-details h3 {
            margin: 0 0 0.25rem 0;
            color: #495057;
            font-size: 1.1rem;
        }

        .member-details p {
            margin: 0;
            color: #6c757d;
            font-size: 0.9rem;
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

        .attendance-calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .attendance-day {
            text-align: center;
            padding: 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
        }

        .attendance-day.present {
            background-color: #d4edda;
            color: #155724;
        }

        .attendance-day.absent {
            background-color: #f8d7da;
            color: #721c24;
        }

        .attendance-day.empty {
            background-color: #e9ecef;
            color: #6c757d;
        }

        .attendance-day-header {
            font-weight: bold;
            text-align: center;
            padding: 0.5rem;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        
        .attendance-summary {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .attendance-summary-item {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            flex: 1;
        }
        
        .attendance-summary-item .number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #3498DB;
        }
        
        .attendance-summary-item .label {
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php include 'includes/librarian_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-calendar-alt"></i> Attendance History</h1>
            <p>View attendance records for library member</p>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h2>Attendance Records</h2>
                <div>
                    <a href="attendance_report.php?user_id=<?php echo urlencode($member['user_id']); ?>" class="btn btn-primary">
                        <i class="fas fa-file-alt"></i>
                        Detailed Report
                    </a>
                    <a href="mark_attendance.php?user_id=<?php echo urlencode($member['user_id']); ?>" class="btn btn-primary">
                        <i class="fas fa-calendar-check"></i>
                        Mark Attendance
                    </a>
                    <a href="members.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Members
                    </a>
                </div>
            </div>
            
            <div class="member-info">
                <div class="member-avatar">
                    <?php 
                    $initials = substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1);
                    echo strtoupper($initials);
                    ?>
                </div>
                <div class="member-details">
                    <h3><?php echo htmlspecialchars($member['full_name']); ?></h3>
                    <p>
                        <span class="status-badge status-<?php echo $member['status']; ?>">
                            <?php echo ucfirst($member['status']); ?>
                        </span>
                    </p>
                </div>
            </div>
            
            <div class="attendance-summary">
                <div class="attendance-summary-item">
                    <div class="number"><?php echo count($attendanceHistory); ?></div>
                    <div class="label">Days Present</div>
                </div>
                <div class="attendance-summary-item">
                    <div class="number"><?php echo 30 - count($attendanceHistory); ?></div>
                    <div class="label">Days Absent</div>
                </div>
                <div class="attendance-summary-item">
                    <div class="number"><?php echo $attendanceHistory ? round((count($attendanceHistory) / 30) * 100) : 0; ?>%</div>
                    <div class="label">Attendance Rate</div>
                </div>
            </div>
            
            <div class="section">
                <h3>Recent Attendance (Last 30 Days)</h3>
                <div class="attendance-calendar">
                    <div class="attendance-day-header">Sun</div>
                    <div class="attendance-day-header">Mon</div>
                    <div class="attendance-day-header">Tue</div>
                    <div class="attendance-day-header">Wed</div>
                    <div class="attendance-day-header">Thu</div>
                    <div class="attendance-day-header">Fri</div>
                    <div class="attendance-day-header">Sat</div>
                    
                    <?php
                    // Generate attendance calendar for the last 30 days
                    $today = new DateTime();
                    $startDate = clone $today;
                    $startDate->sub(new DateInterval('P30D'));
                    
                    // Adjust start date to the beginning of the week (Sunday)
                    $startDate->sub(new DateInterval('P' . $startDate->format('w') . 'D'));
                    
                    // Adjust end date to the end of the week (Saturday)
                    $endDate = clone $today;
                    $endDate->add(new DateInterval('P' . (6 - $endDate->format('w')) . 'D'));
                    
                    $currentDate = clone $startDate;
                    while ($currentDate <= $endDate) {
                        $dateStr = $currentDate->format('Y-m-d');
                        $isPresent = in_array($dateStr, $attendanceHistory);
                        $isFuture = $currentDate > $today;
                        
                        if ($isFuture) {
                            echo '<div class="attendance-day empty">-</div>';
                        } else {
                            $class = $isPresent ? 'present' : 'absent';
                            echo '<div class="attendance-day ' . $class . '">' . $currentDate->format('j') . '</div>';
                        }
                        
                        $currentDate->add(new DateInterval('P1D'));
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>