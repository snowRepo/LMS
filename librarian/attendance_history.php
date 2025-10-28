<?php
define('LMS_ACCESS', true);

// Set the default timezone to match your system
date_default_timezone_set('Africa/Accra'); // Change this to your actual timezone

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
    
    // Get the integer ID of the member for attendance table
    $memberIntegerId = $member['id'];
    
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

        .member-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background: #f8f9fa;
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
            font-size: 1.5rem;
        }

        .member-details h3 {
            margin: 0 0 0.25rem 0;
            color: #495057;
        }

        .member-details p {
            margin: 0;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .attendance-table th,
        .attendance-table td {
            padding: 0.75rem;
            text-align: center;
            border-bottom: 1px solid #dee2e6;
        }

        .attendance-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }

        .attendance-table tr:hover {
            background-color: #f8f9fa;
        }

        .time-present {
            color: #28a745;
            font-weight: 500;
        }

        .time-missing {
            color: #dc3545;
        }

        .no-records {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 2rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .content-card {
                padding: 1rem;
            }
            
            .attendance-table {
                font-size: 0.9rem;
            }
            
            .attendance-table th,
            .attendance-table td {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/librarian_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-history"></i> Attendance History</h1>
            <p>View <?php echo htmlspecialchars($member['full_name']); ?>'s attendance records</p>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h2>Attendance Records</h2>
                <a href="mark_attendance.php?user_id=<?php echo urlencode($userId); ?>" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Mark Attendance
                </a>
            </div>
            
            <div class="member-info">
                <div class="member-avatar">
                    <?php 
                    // Display profile image if available, otherwise show initials
                    if (!empty($member['profile_image']) && file_exists('../' . $member['profile_image'])): ?>
                        <img src="../<?php echo htmlspecialchars($member['profile_image']); ?>" 
                             alt="<?php echo htmlspecialchars($member['full_name']); ?>" 
                             style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover;">
                    <?php else: 
                        $initials = substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1); ?>
                        <div style="width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 1.5rem;">
                            <?php echo strtoupper($initials); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="member-details">
                    <h3><?php echo htmlspecialchars($member['full_name']); ?></h3>
                    <p>Email: <?php echo htmlspecialchars($member['email']); ?></p>
                    <p>Phone: <?php echo htmlspecialchars($member['phone'] ?? 'N/A'); ?></p>
                </div>
            </div>
            
            <?php if (count($attendanceHistory) > 0): ?>
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Arrival Time</th>
                            <th>Departure Time</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendanceHistory as $record): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($record['attendance_date'])); ?></td>
                                <td>
                                    <?php 
                                    // Handle backward compatibility: if there's a record but no arrival time,
                                    // it's an old record that should be considered as "present"
                                    $hasArrivalTime = !empty($record['arrival_time']);
                                    if ($hasArrivalTime): ?>
                                        <span class="time-present"><?php echo date('g:i A', strtotime($record['arrival_time'])); ?></span>
                                    <?php elseif (!$hasArrivalTime && empty($record['departure_time'])): ?>
                                        <span class="time-present">Recorded (time not specified)</span>
                                    <?php else: ?>
                                        <span class="time-missing">Not recorded</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    // Handle backward compatibility: if there's a record but no departure time,
                                    // it's either an old record or a check-in without check-out
                                    $hasDepartureTime = !empty($record['departure_time']);
                                    if ($hasDepartureTime): ?>
                                        <span class="time-present"><?php echo date('g:i A', strtotime($record['departure_time'])); ?></span>
                                    <?php elseif (!$hasDepartureTime && empty($record['arrival_time'])): ?>
                                        <span class="time-present">Recorded (time not specified)</span>
                                    <?php else: ?>
                                        <span class="time-missing">Not recorded</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    // Handle backward compatibility for records without time information
                                    // If there's a record but no times, it's an old record that should be considered as "present"
                                    $hasArrivalTime = !empty($record['arrival_time']);
                                    $hasDepartureTime = !empty($record['departure_time']);
                                    
                                    if ($hasArrivalTime && $hasDepartureTime) {
                                        $arrival = new DateTime($record['arrival_time']);
                                        $departure = new DateTime($record['departure_time']);
                                        $interval = $arrival->diff($departure);
                                        echo $interval->format('%h hrs %i mins');
                                    } else if ($hasArrivalTime || (!$hasArrivalTime && !$hasDepartureTime)) {
                                        // Either has arrival time or is an old record (no times at all)
                                        echo 'In Progress';
                                    } else {
                                        echo 'Incomplete';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-records">
                    <i class="fas fa-info-circle"></i> No attendance records found for the last 30 days.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        // Detect user timezone and send to server
        document.addEventListener('DOMContentLoaded', function() {
            // Get user's timezone
            const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
            
            // Check if we've already set this timezone to avoid infinite loops
            const timezoneKey = 'lms_user_timezone';
            const storedTimezone = localStorage.getItem(timezoneKey);
            
            if (storedTimezone !== userTimezone) {
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
                        // Only reload if this is the first time setting the timezone
                        if (!storedTimezone) {
                            window.location.reload();
                        }
                    }
                })
                .catch(error => {
                    console.error('Error setting timezone:', error);
                });
            }
        });
    </script>
</body>
</html>