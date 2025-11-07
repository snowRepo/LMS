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

// Check if user is logged in and has supervisor role
if (!is_logged_in() || $_SESSION['user_role'] !== 'supervisor') {
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

// Get librarian ID from URL parameter
$librarianId = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($librarianId)) {
    header('Location: librarians.php');
    exit;
}

// Fetch librarian details
try {
    $db = Database::getInstance()->getConnection();
    
    // Get librarian details (simplified query to match edit_librarian.php)
    $stmt = $db->prepare("
        SELECT u.*
        FROM users u
        WHERE u.library_id = ? AND u.user_id = ? AND u.role = 'librarian'
    ");
    $stmt->execute([$libraryId, $librarianId]);
    $librarian = $stmt->fetch();
    
    if (!$librarian) {
        header('Location: librarians.php?error=Librarian not found');
        exit;
    }
    
    // Get library name separately
    $stmt = $db->prepare("SELECT library_name FROM libraries WHERE id = ?");
    $stmt->execute([$libraryId]);
    $library = $stmt->fetch();
    $librarian['library_name'] = $library ? $library['library_name'] : 'Unknown Library';
    
    $pageTitle = 'Librarian Details - ' . $librarian['first_name'] . ' ' . $librarian['last_name'];
} catch (Exception $e) {
    // For debugging, let's show the actual error message
    error_log("Error fetching librarian details: " . $e->getMessage());
    error_log("Library ID: " . $libraryId);
    error_log("Librarian ID: " . $librarianId);
    error_log("Exception trace: " . $e->getTraceAsString());
    header('Location: librarians.php?error=Error fetching librarian details: ' . urlencode($e->getMessage()));
    exit;
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
    
    <!-- Supervisor Navbar CSS -->
    <link rel="stylesheet" href="css/supervisor_navbar.css">
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

        .librarian-details-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }

        .librarian-avatar {
            text-align: center;
        }

        .librarian-avatar-img {
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

        .librarian-info {
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

        .status-suspended {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-pending {
            background-color: #cce7ff;
            color: #004085;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }

        @media (max-width: 768px) {
            .librarian-details-container {
                grid-template-columns: 1fr;
            }
            
            .librarian-info {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/supervisor_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-user-tie"></i> Librarian Details</h1>
            <p>View information for <?php echo htmlspecialchars($librarian['first_name'] . ' ' . $librarian['last_name']); ?></p>
        </div>
        
        <!-- Toast Container -->
        <div id="toast-container"></div>
        
        <div class="content-card">
            <div class="card-header">
                <h2>Profile Information</h2>
                <a href="librarians.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Librarians
                </a>
            </div>
            
            <div class="librarian-details-container">
                <div class="librarian-avatar">
                    <div class="librarian-avatar-img">
                        <?php if (!empty($librarian['profile_image']) && file_exists('../' . $librarian['profile_image'])): ?>
                            <img src="../<?php echo htmlspecialchars($librarian['profile_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($librarian['first_name'] . ' ' . $librarian['last_name']); ?>" 
                                 style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                        <?php else: ?>
                            <?php 
                            $initials = substr($librarian['first_name'], 0, 1) . substr($librarian['last_name'], 0, 1);
                            echo strtoupper($initials);
                            ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="librarian-info">
                    <div class="info-group">
                        <div class="info-label">Full Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($librarian['first_name'] . ' ' . $librarian['last_name']); ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Username</div>
                        <div class="info-value"><?php echo htmlspecialchars($librarian['username']); ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Email Address</div>
                        <div class="info-value"><?php echo htmlspecialchars($librarian['email']); ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Phone Number</div>
                        <div class="info-value"><?php echo !empty($librarian['phone']) ? htmlspecialchars($librarian['phone']) : 'N/A'; ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Library</div>
                        <div class="info-value"><?php echo htmlspecialchars($librarian['library_name'] ?? 'N/A'); ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Address</div>
                        <div class="info-value"><?php echo !empty($librarian['address']) ? nl2br(htmlspecialchars($librarian['address'])) : 'N/A'; ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Date of Birth</div>
                        <div class="info-value"><?php echo !empty($librarian['date_of_birth']) ? date('M j, Y', strtotime($librarian['date_of_birth'])) : 'N/A'; ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <span class="status-badge status-<?php echo $librarian['status']; ?>">
                                <?php echo ucfirst($librarian['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Account Created</div>
                        <div class="info-value"><?php echo date('M j, Y \a\t g:i A', strtotime($librarian['created_at'])); ?></div>
                    </div>
                    
                    <?php if (!empty($librarian['updated_at']) && $librarian['updated_at'] !== $librarian['created_at']): ?>
                    <div class="info-group">
                        <div class="info-label">Last Updated</div>
                        <div class="info-value"><?php echo date('M j, Y \a\t g:i A', strtotime($librarian['updated_at'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="action-buttons">
                <a href="edit_librarian.php?id=<?php echo urlencode($librarian['user_id']); ?>" class="btn btn-warning">
                    <i class="fas fa-edit"></i> Edit Librarian
                </a>
                <?php if ($librarian['status'] !== 'pending'): ?>
                <a href="reset_librarian_password.php?id=<?php echo urlencode($librarian['user_id']); ?>" class="btn btn-secondary">
                    <i class="fas fa-key"></i> Reset Password
                </a>
                <?php endif; ?>
                <?php if ($librarian['status'] !== 'pending'): ?>
                <a href="delete_librarian.php?id=<?php echo urlencode($librarian['user_id']); ?>" class="btn btn-danger">
                    <i class="fas fa-trash-alt"></i> Delete Librarian
                </a>
                <?php endif; ?>
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