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
    
    // Get the user_id of the member for attendance table (foreign key reference)
    $memberUserId = $member['user_id'];
    
    // Check if member has attendance record for today
    $stmt = $db->prepare("
        SELECT id, arrival_time, departure_time 
        FROM attendance 
        WHERE user_id = ? AND library_id = ? AND attendance_date = CURDATE()
    ");
    $stmt->execute([$memberUserId, $libraryId]);
    $attendanceRecord = $stmt->fetch();
    
    $isPresentToday = $attendanceRecord ? true : false;
    // Handle backward compatibility: if there's a record but no times, consider it checked in
    $hasArrivalTime = $attendanceRecord && (!empty($attendanceRecord['arrival_time']) || 
                      (!$attendanceRecord['arrival_time'] && !$attendanceRecord['departure_time']));
    $hasDepartureTime = $attendanceRecord && !empty($attendanceRecord['departure_time']);
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = isset($_POST['action']) ? trim($_POST['action']) : '';
        
        if ($action === 'check_in') {
            // Mark member arrival time
            if (!$isPresentToday) {
                // Create new attendance record with arrival time
                $stmt = $db->prepare("
                    INSERT INTO attendance (user_id, library_id, attendance_date, arrival_time) 
                    VALUES (?, ?, ?, ?)
                ");
                // Use DateTime for consistency
                $now = new DateTime();
                $currentTime = $now->format('H:i:s');
                $currentDate = $now->format('Y-m-d');
                $result = $stmt->execute([$memberUserId, $libraryId, $currentDate, $currentTime]);
            } else if (!$hasArrivalTime) {
                // Update existing record with arrival time
                $stmt = $db->prepare("
                    UPDATE attendance 
                    SET arrival_time = ? 
                    WHERE id = ?
                ");
                // Use DateTime for consistency
                $now = new DateTime();
                $currentTime = $now->format('H:i:s');
                $result = $stmt->execute([$currentTime, $attendanceRecord['id']]);
            } else {
                $error = 'Member already checked in today';
                $result = false;
            }
            
            if ($result) {
                // Send notification to member about check-in
                require_once '../includes/NotificationService.php';
                $notificationService = new NotificationService();
                
                $notificationTitle = "Check-in Recorded";
                // Use DateTime for consistency
                $now = new DateTime();
                $currentTimeDisplay = $now->format('g:i A');
                $currentDateDisplay = $now->format('F j, Y');
                $notificationMessage = "Your arrival time has been recorded as " . $currentTimeDisplay . " today (" . $currentDateDisplay . ") by the librarian.";
                
                $notificationService->createNotification(
                    $userId,  // user_id string for notification service
                    $notificationTitle,
                    $notificationMessage,
                    'info',  // type
                    '../member/dashboard.php'  // action URL
                );
                
                // Redirect with success message
                header('Location: mark_attendance.php?user_id=' . urlencode($userId) . '&success=' . urlencode('Check-in time recorded successfully'));
                exit;
            } else if (!isset($error)) {
                // Redirect with error message
                header('Location: mark_attendance.php?user_id=' . urlencode($userId) . '&error=' . urlencode('Failed to record check-in time'));
                exit;
            }
        } else if ($action === 'check_out') {
            // Mark member departure time
            if ($isPresentToday && $hasArrivalTime && !$hasDepartureTime) {
                // Update existing record with departure time
                $stmt = $db->prepare("
                    UPDATE attendance 
                    SET departure_time = ? 
                    WHERE id = ?
                ");
                // Use DateTime for consistency
                $now = new DateTime();
                $currentTime = $now->format('H:i:s');
                $result = $stmt->execute([$currentTime, $attendanceRecord['id']]);
                
                if ($result) {
                    // Send notification to member about check-out
                    require_once '../includes/NotificationService.php';
                    $notificationService = new NotificationService();
                    
                    $notificationTitle = "Check-out Recorded";
                    // Use DateTime for consistency
                    $now = new DateTime();
                    $currentTimeDisplay = $now->format('g:i A');
                    $currentDateDisplay = $now->format('F j, Y');
                    $notificationMessage = "Your departure time has been recorded as " . $currentTimeDisplay . " today (" . $currentDateDisplay . ") by the librarian.";
                    
                    $notificationService->createNotification(
                        $userId,  // user_id string for notification service
                        $notificationTitle,
                        $notificationMessage,
                        'info',  // type
                        '../member/dashboard.php'  // action URL
                    );
                    
                    // Redirect with success message
                    header('Location: mark_attendance.php?user_id=' . urlencode($userId) . '&success=' . urlencode('Check-out time recorded successfully'));
                    exit;
                } else {
                    // Redirect with error message
                    header('Location: mark_attendance.php?user_id=' . urlencode($userId) . '&error=' . urlencode('Failed to record check-out time'));
                    exit;
                }
            } else if (!$isPresentToday || !$hasArrivalTime) {
                // Redirect with error message
                header('Location: mark_attendance.php?user_id=' . urlencode($userId) . '&error=' . urlencode('Member must check in before checking out'));
                exit;
            } else if ($hasDepartureTime) {
                // Redirect with error message
                header('Location: mark_attendance.php?user_id=' . urlencode($userId) . '&error=' . urlencode('Member already checked out today'));
                exit;
            }
        } else if ($action === 'reset') {
            // Reset attendance record for the day
            if ($isPresentToday) {
                $stmt = $db->prepare("
                    DELETE FROM attendance 
                    WHERE id = ?
                ");
                $result = $stmt->execute([$attendanceRecord['id']]);
                
                if ($result) {
                    // Send notification to member about reset
                    require_once '../includes/NotificationService.php';
                    $notificationService = new NotificationService();
                    
                    $notificationTitle = "Attendance Record Reset";
                    $currentDateDisplay = date('F j, Y');
                    $notificationMessage = "Your attendance record for today (" . $currentDateDisplay . ") has been reset by the librarian.";
                    
                    $notificationService->createNotification(
                        $userId,  // user_id string for notification service
                        $notificationTitle,
                        $notificationMessage,
                        'info',  // type
                        '../member/dashboard.php'  // action URL
                    );
                    
                    // Redirect with success message
                    header('Location: mark_attendance.php?user_id=' . urlencode($userId) . '&success=' . urlencode('Attendance record reset successfully'));
                    exit;
                } else {
                    // Redirect with error message
                    header('Location: mark_attendance.php?user_id=' . urlencode($userId) . '&error=' . urlencode('Failed to reset attendance record'));
                    exit;
                }
            } else {
                // Redirect with error message
                header('Location: mark_attendance.php?user_id=' . urlencode($userId) . '&error=' . urlencode('No attendance record found for today'));
                exit;
            }
        }
    }
    
    $pageTitle = 'Mark Attendance';
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
    
    <!-- Toast CSS -->
    <link rel="stylesheet" href="css/toast.css">
    
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
            color: #495057;
            margin: 0;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #495057;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
            box-sizing: border-box;
        }

        .form-control:focus {
            border-color: #3498DB;
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
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
            background: linear-gradient(135deg, #2980B9 0%, #2573A7 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.3);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.3);
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 193, 7, 0.3);
        }

        .form-row {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .member-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 12px;
        }
        
        .member-avatar-img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 2rem;
        }
        
        .member-details {
            flex: 1;
        }
        
        .member-name {
            font-size: 1.5rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.25rem;
        }
        
        .member-email {
            color: #6c757d;
            margin-bottom: 0.25rem;
        }
        
        .member-phone {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .attendance-status {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 12px;
        }
        
        .attendance-status h3 {
            margin-top: 0;
            color: #495057;
            margin-bottom: 1.5rem;
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        
        .status-item {
            text-align: center;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .status-label {
            font-weight: 600;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        
        .status-value {
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        .status-present {
            color: #28a745;
        }
        
        .status-absent {
            color: #dc3545;
        }
        
        .attendance-actions {
            margin-bottom: 2rem;
        }
        
        .attendance-actions h3 {
            color: #495057;
            margin-bottom: 1.5rem;
        }
        
        .attendance-actions form {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Loading animation */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498DB;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 8px;
            vertical-align: middle;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .member-info {
                flex-direction: column;
                text-align: center;
            }
            
            .status-grid {
                grid-template-columns: 1fr;
            }
            
            .attendance-actions form {
                flex-direction: column;
            }
            
            .attendance-actions form .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/librarian_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-user-clock"></i> Mark Attendance</h1>
            <p>Record member's arrival and departure times</p>
        </div>

        <!-- Toast Container -->
        <div id="toast-container"></div>

        <div class="content-card">
            <div class="card-header">
                <h2>Attendance for <?php echo htmlspecialchars($member['full_name']); ?></h2>
                <div>
                    <a href="attendance_history.php?user_id=<?php echo urlencode($userId); ?>" class="btn btn-primary" style="margin-right: 10px;">
                        <i class="fas fa-history"></i> Attendance History
                    </a>
                    <a href="attendance_report.php?user_id=<?php echo urlencode($userId); ?>" class="btn btn-primary" style="margin-right: 10px;">
                        <i class="fas fa-chart-bar"></i> Attendance Report
                    </a>
                    <a href="members.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Members
                    </a>
                </div>
            </div>
            
            <!-- Member Info -->
            <div class="member-info">
                <div class="member-avatar">
                    <?php 
                    // Display profile image if available, otherwise show initials
                    if (!empty($member['profile_image']) && file_exists('../' . $member['profile_image'])): ?>
                        <img src="../<?php echo htmlspecialchars($member['profile_image']); ?>" 
                             alt="<?php echo htmlspecialchars($member['full_name']); ?>" 
                             style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover;">
                    <?php else: 
                        $initials = substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1); ?>
                        <div class="member-avatar-img">
                            <?php echo strtoupper($initials); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="member-details">
                    <div class="member-name"><?php echo htmlspecialchars($member['full_name']); ?></div>
                    <div class="member-email"><?php echo htmlspecialchars($member['email']); ?></div>
                    <div class="member-phone">Phone: <?php echo htmlspecialchars($member['phone'] ?? 'N/A'); ?></div>
                </div>
            </div>
            
            <!-- Today's Attendance Status -->
            <div class="attendance-status">
                <h3>Today's Attendance Status</h3>
                <div class="status-grid">
                    <div class="status-item">
                        <div class="status-label">Date</div>
                        <div class="status-value"><?php echo date('F j, Y'); ?></div>
                    </div>
                    <div class="status-item">
                        <div class="status-label">Checked In</div>
                        <div class="status-value">
                            <?php if ($hasArrivalTime): ?>
                                <span class="status-present">
                                    <i class="fas fa-check-circle"></i> 
                                    <?php 
                                    if (!empty($attendanceRecord['arrival_time'])) {
                                        // For stored times, we assume they were stored in the user's timezone at the time of recording
                                        // So we just format them as is
                                        $arrivalTime = date('g:i A', strtotime($attendanceRecord['arrival_time']));
                                        echo $arrivalTime;
                                    } else {
                                        echo 'Recorded (time not specified)';
                                    }
                                    ?>
                                </span>
                            <?php else: ?>
                                <span class="status-absent">
                                    <i class="fas fa-times-circle"></i> Not yet
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="status-item">
                        <div class="status-label">Checked Out</div>
                        <div class="status-value">
                            <?php if ($hasDepartureTime): ?>
                                <span class="status-present">
                                    <i class="fas fa-check-circle"></i> 
                                    <?php 
                                    // For stored times, we assume they were stored in the user's timezone at the time of recording
                                    // So we just format them as is
                                    $departureTime = date('g:i A', strtotime($attendanceRecord['departure_time']));
                                    echo $departureTime;
                                    ?>
                                </span>
                            <?php else: ?>
                                <span class="status-absent">
                                    <i class="fas fa-times-circle"></i> Not yet
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Attendance Actions -->
            <div class="attendance-actions">
                <h3>Attendance Actions</h3>
                <form method="POST" id="attendanceForm">
                    <?php if (!$hasArrivalTime): ?>
                        <button type="submit" name="action" value="check_in" class="btn btn-success">
                            <i class="fas fa-sign-in-alt"></i> Check In (Record Arrival)
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($hasArrivalTime && !$hasDepartureTime): ?>
                        <button type="submit" name="action" value="check_out" class="btn btn-warning">
                            <i class="fas fa-sign-out-alt"></i> Check Out (Record Departure)
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($isPresentToday): ?>
                        <button type="submit" name="action" value="reset" class="btn btn-danger" onclick="return confirm('Are you sure you want to reset today\'s attendance record?')">
                            <i class="fas fa-undo"></i> Reset Attendance
                        </button>
                    <?php endif; ?>
                </form>
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
            
            return toast;
        }
        
        // Re-enable timezone detection to use user's local time
        document.addEventListener('DOMContentLoaded', function() {
            // Get user's timezone
            const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
            
            // Check if we've already set this timezone to avoid infinite loops
            const timezoneKey = 'lms_user_timezone';
            const storedTimezone = localStorage.getItem(timezoneKey);
            
            // Always send timezone to server to ensure it's up to date
            // Send timezone to server via AJAX
            fetch('../includes/set_timezone.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({timezone: userTimezone})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Timezone set to: ' + userTimezone);
                    // Store the timezone in localStorage to prevent future reloads
                    localStorage.setItem(timezoneKey, userTimezone);
                    // Always reload to ensure the timezone is applied
                    if (storedTimezone !== userTimezone) {
                        window.location.reload();
                    }
                } else {
                    console.error('Failed to set timezone:', data.message);
                }
            })
            .catch(error => {
                console.error('Error setting timezone:', error);
            });
            
            // Check for URL parameters and show toast notifications
            const urlParams = new URLSearchParams(window.location.search);
            const success = urlParams.get('success');
            const error = urlParams.get('error');
            
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
        });
    </script>
</body>
</html>