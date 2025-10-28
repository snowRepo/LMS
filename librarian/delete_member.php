<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';
require_once '../includes/SubscriptionManager.php';
require_once '../includes/EmailService.php';

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
        SELECT u.*
        FROM users u
        WHERE u.user_id = ? AND u.library_id = ? AND u.role = 'member'
    ");
    $stmt->execute([$userId, $libraryId]);
    $member = $stmt->fetch();
    
    if (!$member) {
        header('Location: members.php?error=Member not found');
        exit;
    }
    
    // Prevent deletion of pending members (they should be deleted via cancellation instead)
    if ($member['status'] === 'pending') {
        header('Location: members.php?error=Cannot delete pending members. Please cancel their account instead.');
        exit;
    }
    
    $pageTitle = 'Delete Member - ' . $member['first_name'] . ' ' . $member['last_name'];
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // Delete member from database
        $stmt = $db->prepare("DELETE FROM users WHERE user_id = ? AND library_id = ? AND role = 'member'");
        $stmt->execute([$userId, $libraryId]);
        
        // Check if any rows were affected
        if ($stmt->rowCount() > 0) {
            // Send email notification to the deleted member
            try {
                $emailService = new EmailService();
                if ($emailService->isConfigured()) {
                    $emailData = [
                        'first_name' => $member['first_name'],
                        'library_name' => $_SESSION['library_name'] ?? 'Your Library'
                    ];
                    
                    $emailService->sendMemberDeletedEmail($member['email'], $emailData);
                }
            } catch (Exception $e) {
                // Log the error but don't stop the deletion process
                error_log("Failed to send member deleted email: " . $e->getMessage());
            }
            
            // Show success message and redirect to members page
            header('Location: members.php?success=Member deleted successfully');
            exit;
        } else {
            $error = "Failed to delete member. Please try again.";
        }
    } catch (Exception $e) {
        $error = "Error deleting member: " . $e->getMessage();
    }
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

        .warning-message {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .warning-message h3 {
            margin-top: 0;
            color: #856404;
        }

        .warning-message p {
            margin-bottom: 0;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }

        /* Loading animation */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #dc3545; /* Red color for delete */
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
            <h1><i class="fas fa-exclamation-triangle"></i> Delete Member</h1>
            <p>Confirm deletion of <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></p>
        </div>
        
        <!-- Toast Container -->
        <div id="toast-container"></div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="content-card">
            <div class="card-header">
                <h2>Confirm Deletion</h2>
                <a href="view_member.php?id=<?php echo urlencode($userId); ?>" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Member
                </a>
            </div>
            
            <div class="warning-message">
                <h3><i class="fas fa-exclamation-triangle"></i> Warning: Irreversible Action</h3>
                <p>You are about to permanently delete the member "<strong><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></strong>" from your library. This action cannot be undone.</p>
                <p>All associated data will be lost. Please confirm that you want to proceed with this deletion.</p>
            </div>
            
            <div class="member-details-container">
                <div class="member-avatar">
                    <?php 
                    // Display profile image if available, otherwise show initials
                    if (!empty($member['profile_image']) && file_exists('../' . $member['profile_image'])): ?>
                        <img src="../<?php echo htmlspecialchars($member['profile_image']); ?>" 
                             alt="<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>" 
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
                </div>
            </div>
            
            <form method="POST" id="deleteForm">
                <div class="action-buttons">
                    <button type="button" name="confirm_delete" class="btn btn-danger" id="deleteButton">
                        <i class="fas fa-trash-alt"></i> Confirm Deletion
                    </button>
                    <a href="view_member.php?id=<?php echo urlencode($userId); ?>" class="btn btn-secondary">
                        Cancel
                    </a>
                </div>
                <input type="hidden" name="confirm_delete" value="1">
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
        }
        
        // Add spinner to delete button when clicked
        document.addEventListener('DOMContentLoaded', function() {
            const deleteButton = document.getElementById('deleteButton');
            const form = document.getElementById('deleteForm');
            
            if (deleteButton && form) {
                deleteButton.addEventListener('click', function() {
                    // Change button to show spinner
                    this.innerHTML = '<span class="loading-spinner"></span> Deleting...';
                    this.disabled = true;
                    
                    // Submit the form
                    form.submit();
                });
            }
            
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