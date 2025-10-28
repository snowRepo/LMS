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
$userId = isset($_GET['id']) ? trim($_GET['id']) : '';

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
    
    // Get the integer ID of the member for attendance table
    $memberIntegerId = $member['id'];
    
    // Get library name
    $stmt = $db->prepare("SELECT library_name FROM libraries WHERE id = ?");
    $stmt->execute([$libraryId]);
    $library = $stmt->fetch();
    $member['library_name'] = $library ? $library['library_name'] : 'Unknown Library';
    
    // Get attendance history for the member (last 30 days) with time information
    $stmt = $db->prepare("
        SELECT attendance_date, arrival_time, departure_time
        FROM attendance 
        WHERE user_id = ? AND library_id = ? 
        AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY attendance_date DESC
    ");
    $stmt->execute([$memberIntegerId, $libraryId]);
    $attendanceHistory = $stmt->fetchAll();
    
    // Create associative array for quick lookup by date
    $attendanceLookup = [];
    foreach ($attendanceHistory as $record) {
        $attendanceLookup[$record['attendance_date']] = $record;
    }
    
    $pageTitle = 'Member Details - ' . $member['first_name'] . ' ' . $member['last_name'];
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

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .btn:active {
            transform: translateY(0);
        }

        .member-details-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }

        .member-avatar {
            text-align: center;
        }

        .member-avatar-img {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4rem;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.2);
            margin: 0 auto;
            border: 5px solid white;
        }

        .member-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .info-group {
            margin-bottom: 1.5rem;
        }

        .info-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .info-value {
            color: #6c757d;
            font-size: 1rem;
        }

        .info-value strong {
            color: #495057;
        }

        .description {
            grid-column: 1 / -1;
        }

        .description .info-value {
            line-height: 1.6;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-align: center;
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

        .section {
            margin-bottom: 2rem;
        }

        .section h3 {
            color: #495057;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
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

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }

        @media (max-width: 768px) {
            .member-details-container {
                grid-template-columns: 1fr;
            }
            
            .member-info {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/librarian_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-user"></i> Member Details</h1>
            <p>View information for <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></p>
        </div>
        
        <!-- Toast Container -->
        <div id="toast-container"></div>
        
        <div class="content-card">
            <div class="card-header">
                <h2>Profile Information</h2>
                <a href="members.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Members
                </a>
            </div>
            
            <div class="member-details-container">
                <div class="member-avatar">
                    <?php 
                    // Display profile image if available, otherwise show initials
                    if (!empty($member['profile_image']) && file_exists('../' . $member['profile_image'])): ?>
                        <img src="../<?php echo htmlspecialchars($member['profile_image']); ?>" 
                             alt="<?php echo htmlspecialchars($member['full_name']); ?>" 
                             style="width: 200px; height: 200px; border-radius: 50%; object-fit: cover; box-shadow: 0 4px 12px rgba(52, 152, 219, 0.2); margin: 0 auto; border: 5px solid white;">
                    <?php else: 
                        $initials = substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1); ?>
                        <div class="member-avatar-img">
                            <?php echo strtoupper($initials); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="member-info">
                    <div class="info-group">
                        <div class="info-label">Full Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Username</div>
                        <div class="info-value"><?php echo htmlspecialchars($member['username']); ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Email Address</div>
                        <div class="info-value"><?php echo htmlspecialchars($member['email']); ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Phone Number</div>
                        <div class="info-value"><?php echo !empty($member['phone']) ? htmlspecialchars($member['phone']) : 'N/A'; ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Date of Birth</div>
                        <div class="info-value"><?php echo !empty($member['date_of_birth']) ? date('M j, Y', strtotime($member['date_of_birth'])) : 'N/A'; ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Library</div>
                        <div class="info-value"><?php echo htmlspecialchars($member['library_name'] ?? 'N/A'); ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Address</div>
                        <div class="info-value"><?php echo !empty($member['address']) ? nl2br(htmlspecialchars($member['address'])) : 'N/A'; ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <span class="status-badge status-<?php echo $member['status']; ?>">
                                <?php echo ucfirst($member['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Account Created</div>
                        <div class="info-value"><?php echo date('M j, Y \a\t g:i A', strtotime($member['created_at'])); ?></div>
                    </div>
                    
                    <?php if (!empty($member['updated_at']) && $member['updated_at'] !== $member['created_at']): ?>
                    <div class="info-group">
                        <div class="info-label">Last Updated</div>
                        <div class="info-value"><?php echo date('M j, Y \a\t g:i A', strtotime($member['updated_at'])); ?></div>
                    </div>
                    <?php endif; ?>
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
                        $isFuture = $currentDate > $today;
                        
                        if ($isFuture) {
                            echo '<div class="attendance-day empty">-</div>';
                        } else {
                            // Check if there's an attendance record for this date
                            $attendanceRecord = isset($attendanceLookup[$dateStr]) ? $attendanceLookup[$dateStr] : null;
                            $isPresent = $attendanceRecord !== null;
                            
                            if ($isPresent) {
                                // Handle backward compatibility: if there's a record but no times, consider it present
                                $hasArrivalTime = !empty($attendanceRecord['arrival_time']) || 
                                                (!$attendanceRecord['arrival_time'] && !$attendanceRecord['departure_time']);
                                $hasDepartureTime = !empty($attendanceRecord['departure_time']);
                                
                                if ($hasArrivalTime && $hasDepartureTime) {
                                    // Complete attendance with both arrival and departure
                                    echo '<div class="attendance-day present" title="Arrival: ' . date('g:i A', strtotime($attendanceRecord['arrival_time'])) . ', Departure: ' . date('g:i A', strtotime($attendanceRecord['departure_time'])) . '">' . $currentDate->format('j') . '</div>';
                                } else if ($hasArrivalTime) {
                                    // Check-in only
                                    if (!empty($attendanceRecord['arrival_time'])) {
                                        echo '<div class="attendance-day present" title="Arrival: ' . date('g:i A', strtotime($attendanceRecord['arrival_time'])) . '">●' . $currentDate->format('j') . '</div>';
                                    } else {
                                        // Old record without time
                                        echo '<div class="attendance-day present" title="Present (time not recorded)">' . $currentDate->format('j') . '</div>';
                                    }
                                } else {
                                    // Only departure time (shouldn't happen but handle gracefully)
                                    echo '<div class="attendance-day present" title="Departure: ' . date('g:i A', strtotime($attendanceRecord['departure_time'])) . '">' . $currentDate->format('j') . '</div>';
                                }
                            } else {
                                // Absent
                                echo '<div class="attendance-day absent">' . $currentDate->format('j') . '</div>';
                            }
                        }
                        
                        $currentDate->add(new DateInterval('P1D'));
                    }
                    ?>
                </div>
            </div>
            
            <div class="action-buttons">
                <a href="edit_member.php?id=<?php echo urlencode($member['user_id']); ?>" class="btn btn-warning">
                    <i class="fas fa-edit"></i> Edit Member
                </a>
                <a href="delete_member.php?id=<?php echo urlencode($member['user_id']); ?>" class="btn btn-danger">
                    <i class="fas fa-trash-alt"></i> Delete Member
                </a>
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