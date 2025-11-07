<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';
require_once '../includes/SubscriptionManager.php';
require_once '../includes/SubscriptionCheck.php';
require_once '../includes/FileUploadHandler.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has supervisor role
if (!is_logged_in() || $_SESSION['user_role'] !== 'supervisor') {
    header('Location: ../login.php');
    exit;
}

// Check subscription status - redirect to expired page if subscription is not active
requireActiveSubscription();

// Check subscription status (keeping original logic for backward compatibility)
$subscriptionManager = new SubscriptionManager();
$libraryId = $_SESSION['library_id'];
$hasActiveSubscription = $subscriptionManager->hasActiveSubscription($libraryId);

if (!$hasActiveSubscription) {
    header('Location: ../subscription.php');
    exit;
}

// Handle form submission
$uploadMessage = '';
$uploadMessageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Handle logo upload if a new logo was provided
        if (isset($_FILES['library_logo']) && $_FILES['library_logo']['error'] === UPLOAD_ERR_OK) {
            $uploader = new FileUploadHandler();
            $result = $uploader->uploadLibraryLogoWithAutoReplace($_FILES['library_logo'], $libraryId);
            
            if ($result['success']) {
                // Update database with new logo path
                $stmt = $db->prepare("UPDATE libraries SET logo_path = ? WHERE id = ?");
                $stmt->execute([$result['path'], $libraryId]);
                $uploadMessage = 'Logo updated successfully!';
            } else {
                $uploadMessage = 'Error uploading logo: ' . $result['error'];
                $uploadMessageType = 'error';
            }
        }
        
        // Handle other library details updates
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $website = trim($_POST['website'] ?? '');
        
        // Update library details
        $stmt = $db->prepare("UPDATE libraries SET address = ?, phone = ?, email = ?, website = ? WHERE id = ?");
        $stmt->execute([$address, $phone, $email, $website, $libraryId]);
        
        // If no logo was uploaded, still show success message for other updates
        if (empty($uploadMessage)) {
            $uploadMessage = 'Library details updated successfully!';
        }
        
        // Refresh library info after update
        $stmt = $db->prepare("SELECT * FROM libraries WHERE id = ?");
        $stmt->execute([$libraryId]);
        $libraryInfo = $stmt->fetch();
        
    } catch (Exception $e) {
        $uploadMessage = 'Error updating library details: ' . $e->getMessage();
        $uploadMessageType = 'error';
    }
}

// Get library information
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM libraries WHERE id = ?");
    $stmt->execute([$libraryId]);
    $libraryInfo = $stmt->fetch();
} catch (Exception $e) {
    $libraryInfo = null;
}

$pageTitle = 'Settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - LMS</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Supervisor Navbar CSS -->
    <link rel="stylesheet" href="css/supervisor_navbar.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 0; /* Remove padding to ensure navbar is at the very top */
        }
        
        /* Ensure no default margin on body */
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

        .settings-container {
            display: grid;
            grid-template-columns: 1fr 3fr;
            gap: 2rem;
        }

        .settings-sidebar {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 1.5rem;
        }

        .settings-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .settings-menu li {
            margin-bottom: 0.5rem;
        }

        .settings-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 1rem;
            text-decoration: none;
            color: #495057;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .settings-menu a:hover,
        .settings-menu a.active {
            background: #f8f9fa;
            color: #3498DB;
        }

        .settings-menu a i {
            width: 20px;
            text-align: center;
        }

        .content-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
        }

        .card-header {
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
            color: #495057;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 1rem;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498DB;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.25);
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1rem;
        }

        .form-check input {
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

        /* Logo upload styles */
        .logo-upload-container {
            border: 2px dashed #ced4da;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .logo-upload-container:hover {
            border-color: #3498DB;
            background: #e3f2fd;
        }

        .logo-preview-area {
            cursor: pointer;
            position: relative;
        }

        .logo-placeholder {
            padding: 1rem;
        }

        .logo-placeholder i {
            font-size: 2.5rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }

        .logo-placeholder p {
            margin: 0 0 0.5rem 0;
            font-weight: 500;
            color: #495057;
        }

        .logo-placeholder span {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .logo-preview {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
        }

        .logo-input {
            display: none;
        }

        .alert {
            padding: 0.75rem 1.25rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: 0.25rem;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        /* Toast notification styles */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        .toast {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            margin-bottom: 12px;
            min-width: 300px;
            max-width: 400px;
            animation: toastSlideIn 0.3s ease-out forwards;
            opacity: 0;
            transform: translateX(100%);
        }

        .toast.show {
            opacity: 1;
            transform: translateX(0);
        }

        .toast.hide {
            animation: toastSlideOut 0.3s ease-in forwards;
        }

        @keyframes toastSlideIn {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes toastSlideOut {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }

        .toast-success {
            background-color: #2e7d32; /* Dark green */
            color: white;
        }

        .toast-error {
            background-color: #c62828; /* Dark red */
            color: white;
        }

        .toast-info {
            background-color: #1565c0; /* Dark blue */
            color: white;
        }

        .toast-warning {
            background-color: #ef6c00; /* Dark orange */
            color: white;
        }

        .toast-icon {
            font-size: 1.2rem;
            font-weight: bold;
        }

        .toast-content {
            flex: 1;
            font-size: 0.95rem;
        }

        .toast-close {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
            transition: opacity 0.2s;
        }

        .toast-close:hover {
            opacity: 1;
        }

        @media (max-width: 768px) {
            .settings-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/supervisor_navbar.php'; ?>
    
    <!-- Toast Notification Container -->
    <div id="toast-container" class="toast-container"></div>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-cog"></i> Settings</h1>
            <p>Manage your library settings and preferences</p>
        </div>

        <div class="settings-container">
            <div class="settings-sidebar">
                <ul class="settings-menu">
                    <li><a href="#" class="active"><i class="fas fa-building"></i> Library Details</a></li>
                    <li><a href="#" onclick="showComingSoon('notifications')"><i class="fas fa-bell"></i> Notifications</a></li>
                    <li><a href="#" onclick="showComingSoon('appearance')"><i class="fas fa-palette"></i> Appearance</a></li>
                    <li><a href="#" onclick="showComingSoon('language')"><i class="fas fa-language"></i> Language</a></li>
                </ul>
            </div>
            
            <div class="content-card">
                <div class="card-header">
                    <h2>Library Details</h2>
                </div>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="libraryName">Library Name</label>
                        <input type="text" id="libraryName" class="form-control" value="<?php echo $libraryInfo ? htmlspecialchars($libraryInfo['library_name']) : ''; ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="libraryCode">Library Code</label>
                        <input type="text" id="libraryCode" class="form-control" value="<?php echo $libraryInfo ? htmlspecialchars($libraryInfo['library_code']) : ''; ?>" readonly>
                    </div>
                    
                    <!-- Library Logo Upload -->
                    <div class="form-group">
                        <label for="library_logo">Library Logo</label>
                        <div class="logo-upload-container">
                            <input type="file" 
                                   id="library_logo" 
                                   name="library_logo" 
                                   class="logo-input" 
                                   accept="image/*"
                                   onchange="previewLogo(this)">
                            <div class="logo-preview-area" onclick="document.getElementById('library_logo').click()">
                                <div class="logo-placeholder" id="logoPlaceholder">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Click to upload logo</p>
                                    <span>PNG, JPG, GIF up to 2MB</span>
                                </div>
                                <img id="logoPreview" class="logo-preview" style="display: none;">
                            </div>
                        </div>
                        <?php if ($libraryInfo && !empty($libraryInfo['logo_path'])): ?>
                            <div style="margin-top: 1rem;">
                                <p>Current logo:</p>
                                <img src="<?php echo APP_URL . '/' . htmlspecialchars(ltrim($libraryInfo['logo_path'], '/')); ?>" alt="Current Library Logo" style="max-height: 100px; border-radius: 8px;">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <input type="text" id="address" name="address" class="form-control" value="<?php echo $libraryInfo ? htmlspecialchars($libraryInfo['address'] ?? '') : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="text" id="phone" name="phone" class="form-control" value="<?php echo $libraryInfo ? htmlspecialchars($libraryInfo['phone'] ?? '') : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo $libraryInfo ? htmlspecialchars($libraryInfo['email'] ?? '') : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="website">Website</label>
                        <input type="text" id="website" name="website" class="form-control" value="<?php echo $libraryInfo ? htmlspecialchars($libraryInfo['website'] ?? '') : ''; ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Save Changes
                    </button>
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
            
            toast.innerHTML = `
                <div class="toast-content">${message}</div>
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

        function previewLogo(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const preview = document.getElementById('logoPreview');
                    const placeholder = document.getElementById('logoPlaceholder');
                    
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    placeholder.style.display = 'none';
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Show toast notification on page load if there's a message
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($uploadMessage)): ?>
            showToast(<?php echo json_encode($uploadMessage); ?>, <?php echo json_encode($uploadMessageType === 'error' ? 'error' : 'success'); ?>);
            <?php endif; ?>
        });

        // Function to show coming soon message
        function showComingSoon(feature) {
            let message = '';
            switch(feature) {
                case 'notifications':
                    message = 'Notifications settings coming soon!';
                    break;
                case 'appearance':
                    message = 'Appearance settings coming soon!';
                    break;
                case 'language':
                    message = 'Language settings coming soon!';
                    break;
                default:
                    message = 'This feature is coming soon!';
            }
            showToast(message, 'info');
        }
    </script>
</body>
</html>