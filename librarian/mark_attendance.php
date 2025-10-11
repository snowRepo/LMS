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
    
    // Check if member is already marked present today
    $stmt = $db->prepare("
        SELECT id 
        FROM attendance 
        WHERE user_id = ? AND library_id = ? AND attendance_date = CURDATE()
    ");
    $stmt->execute([$userId, $libraryId]);
    $isPresentToday = $stmt->fetch();
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $present = isset($_POST['present']) ? 1 : 0;
        
        if ($present) {
            // Mark member as present
            $stmt = $db->prepare("
                INSERT INTO attendance (user_id, library_id, attendance_date) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE attendance_date = VALUES(attendance_date)
            ");
            $result = $stmt->execute([$userId, $libraryId, date('Y-m-d')]);
            
            if ($result) {
                $success = 'Attendance marked successfully';
            } else {
                $error = 'Failed to mark attendance';
            }
        } else {
            // Remove attendance record
            $stmt = $db->prepare("
                DELETE FROM attendance 
                WHERE user_id = ? AND library_id = ? AND attendance_date = ?
            ");
            $result = $stmt->execute([$userId, $libraryId, date('Y-m-d')]);
            
            if ($result) {
                $success = 'Attendance record removed';
            } else {
                $error = 'Failed to remove attendance record';
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

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
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

        .attendance-section {
            margin-bottom: 1.5rem;
            padding: 1rem;
            background-color: #f8f9fa;
            border-radius: 8px;
        }

        .attendance-heading {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .attendance-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-absent {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .badge-present {
            background-color: #d4edda;
            color: #155724;
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

        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 8px;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <?php include 'includes/librarian_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-calendar-check"></i> Mark Attendance</h1>
            <p>Mark attendance for library member</p>
        </div>

        <!-- Toast Container -->
        <div id="toast-container"></div>

        <?php if (isset($error)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showToast('<?php echo addslashes($error); ?>', 'error');
                });
            </script>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const toast = showToast('<?php echo addslashes($success); ?>', 'success');
                    // Redirect to members page after showing toast
                    setTimeout(function() {
                        window.location.href = 'members.php';
                    }, 2000);
                });
            </script>
        <?php endif; ?>

        <div class="content-card">
            <div class="card-header">
                <h2>Member Attendance</h2>
                <div>
                    <a href="attendance_history.php?user_id=<?php echo urlencode($member['user_id']); ?>" class="btn btn-primary">
                        <i class="fas fa-history"></i>
                        Attendance History
                    </a>
                    <a href="attendance_report.php?user_id=<?php echo urlencode($member['user_id']); ?>" class="btn btn-primary">
                        <i class="fas fa-file-alt"></i>
                        Detailed Report
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
            
            <div class="attendance-section">
                <div class="attendance-heading">Attendance Today</div>
                <span class="attendance-badge <?php echo $isPresentToday ? 'badge-present' : 'badge-absent'; ?>">
                    <i class="fas fa-<?php echo $isPresentToday ? 'check' : 'times'; ?>"></i>
                    <?php echo $isPresentToday ? 'Present' : 'Absent'; ?>
                </span>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="attendance_date">Attendance Date</label>
                    <input type="text" id="attendance_date" class="form-control" value="<?php echo date('F j, Y'); ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="present" value="1" <?php echo $isPresentToday ? 'checked' : ''; ?>>
                        Mark as Present Today
                    </label>
                </div>
                
                <div class="form-row">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i>
                        Save Attendance
                    </button>
                    <a href="members.php" class="btn btn-secondary">
                        Cancel
                    </a>
                </div>
            </form>
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