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
    
    // Get librarian details
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
    
    $pageTitle = 'Edit Librarian - ' . $librarian['first_name'] . ' ' . $librarian['last_name'];
} catch (Exception $e) {
    header('Location: librarians.php?error=Error fetching librarian details');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $username = trim($_POST['username']);
        $address = trim($_POST['address']);
        $status = $_POST['status'];
        
        // Validate required fields
        if (empty($firstName) || empty($lastName) || empty($email) || empty($username)) {
            $error = "Please fill in all required fields.";
        } else {
            // Update librarian in database
            $stmt = $db->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, email = ?, phone = ?, username = ?, address = ?, status = ?, updated_at = NOW()
                WHERE user_id = ? AND library_id = ?
            ");
            
            $result = $stmt->execute([
                $firstName, $lastName, $email, $phone, $username, $address, $status, $librarianId, $libraryId
            ]);
            
            if ($result) {
                header('Location: view_librarian.php?id=' . urlencode($librarianId) . '&success=' . urlencode('Librarian updated successfully'));
                exit;
            } else {
                header('Location: edit_librarian.php?id=' . urlencode($librarianId) . '&error=' . urlencode('Failed to update librarian. Please try again.'));
                exit;
            }
        }
    } catch (Exception $e) {
        header('Location: edit_librarian.php?id=' . urlencode($librarianId) . '&error=' . urlencode('Error updating librarian: ' . $e->getMessage()));
        exit;
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
            /* Add padding to the right for dropdown icons */
            padding-right: 2.5rem;
        }
        
        /* Remove padding-right for non-select elements */
        input.form-control,
        textarea.form-control {
            padding-right: 0.75rem;
        }

        .form-control:focus {
            border-color: #3498DB;
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        /* Specific styling for select elements */
        select.form-control {
            padding-right: 2.5rem;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1rem;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-row .form-group:last-child {
            margin-bottom: 0;
        }

        .section-content {
            padding: 1.5rem 0;
        }

        .section-content h3 {
            color: #495057;
            margin-top: 0;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e9ecef;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }

        @media (max-width: 768px) {
            .form-row {
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
            <h1><i class="fas fa-user-tie"></i> Edit Librarian</h1>
            <p>Update information for <?php echo htmlspecialchars($librarian['first_name'] . ' ' . $librarian['last_name']); ?></p>
        </div>
        
        <!-- Toast Container -->
        <div id="toast-container"></div>
        
        <div class="content-card">
            <div class="card-header">
                <h2>Librarian Information</h2>
                <a href="view_librarian.php?id=<?php echo urlencode($librarianId); ?>" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Librarian
                </a>
            </div>
            
            <form method="POST">
                <div class="section-content">
                    <h3>Personal Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo htmlspecialchars($librarian['first_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo htmlspecialchars($librarian['last_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($librarian['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="text" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($librarian['phone'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Username *</label>
                            <input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars($librarian['username']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="form-control">
                                <option value="active" <?php echo ($librarian['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($librarian['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                <option value="suspended" <?php echo ($librarian['status'] === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                                <?php if ($librarian['status'] === 'pending'): ?>
                                <option value="pending" selected>Pending</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" class="form-control" rows="3"><?php echo htmlspecialchars($librarian['address'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="view_librarian.php?id=<?php echo urlencode($librarianId); ?>" class="btn btn-secondary">
                        Cancel
                    </a>
                </div>
            </form>
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
    </div>
</body>
</html>