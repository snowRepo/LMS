<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';
require_once '../includes/FileUploadHandler.php';
// Database class is already available through config.php, no need to include it separately

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has member role
if (!is_logged_in() || $_SESSION['user_role'] !== 'member') {
    header('Location: ../login.php');
    exit;
}

// Handle profile image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image']) && !empty($_FILES['profile_image']['name'])) {
    $fileUploadHandler = new FileUploadHandler();
    $uploadResult = $fileUploadHandler->uploadProfileImageWithAutoReplace($_FILES['profile_image'], $_SESSION['user_id']);
    
    if ($uploadResult['success']) {
        // Update database with new profile image path
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        try {
            $stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
            $stmt->execute([$uploadResult['path'], $_SESSION['user_id']]);
            
            // Update session variable with the relative path
            $_SESSION['profile_image'] = $uploadResult['path'];
            
            // Set success message in session for toast notification
            $_SESSION['toast_message'] = 'Profile image updated successfully!';
            $_SESSION['toast_type'] = 'success';
        } catch (PDOException $e) {
            // Set error message in session for toast notification
            $_SESSION['toast_message'] = 'Database error: ' . $e->getMessage();
            $_SESSION['toast_type'] = 'error';
        }
        
        // Redirect to avoid resubmission
        header('Location: profile.php');
        exit;
    } else {
        // Set error message in session for toast notification
        $_SESSION['toast_message'] = $uploadResult['error'];
        $_SESSION['toast_type'] = 'error';
        
        // Redirect to avoid resubmission
        header('Location: profile.php');
        exit;
    }
}

// Handle profile information update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $dateOfBirth = $_POST['date_of_birth'] ?? '';
    
    // Handle empty date of birth
    if (empty($dateOfBirth)) {
        $dateOfBirth = null;
    }
    
    // Validate email
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Set error message in session for toast notification
        $_SESSION['toast_message'] = 'Invalid email format';
        $_SESSION['toast_type'] = 'error';
        
        // Redirect to avoid resubmission
        header('Location: profile.php');
        exit;
    } else {
        // Update database
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        try {
            $stmt = $conn->prepare("UPDATE users SET email = ?, phone = ?, address = ?, date_of_birth = ? WHERE id = ?");
            $stmt->execute([$email, $phone, $address, $dateOfBirth, $_SESSION['user_id']]);
            
            // Update session variables
            $_SESSION['email'] = $email;
            $_SESSION['phone'] = $phone;
            $_SESSION['address'] = $address;
            $_SESSION['date_of_birth'] = $dateOfBirth ?? '';
            
            // Set success message in session for toast notification
            $_SESSION['toast_message'] = 'Profile updated successfully!';
            $_SESSION['toast_type'] = 'success';
        } catch (PDOException $e) {
            // Set error message in session for toast notification
            $_SESSION['toast_message'] = 'Database error: ' . $e->getMessage();
            $_SESSION['toast_type'] = 'error';
        }
        
        // Redirect to avoid resubmission
        header('Location: profile.php');
        exit;
    }
}

// Handle profile image removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_profile_image'])) {
    // Remove profile image from database
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    try {
        // Get current profile image path
        $stmt = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user && !empty($user['profile_image'])) {
            // Delete the file from the server
            $fileUploadHandler = new FileUploadHandler();
            $fileUploadHandler->deleteFile($user['profile_image']);
            
            // Update database to remove profile image path
            $stmt = $conn->prepare("UPDATE users SET profile_image = NULL WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            
            // Update session variable
            $_SESSION['profile_image'] = '';
            
            // Set success message in session for toast notification
            $_SESSION['toast_message'] = 'Profile image removed successfully!';
            $_SESSION['toast_type'] = 'success';
        } else {
            // Set info message in session for toast notification
            $_SESSION['toast_message'] = 'No profile image to remove';
            $_SESSION['toast_type'] = 'info';
        }
    } catch (PDOException $e) {
        // Set error message in session for toast notification
        $_SESSION['toast_message'] = 'Database error: ' . $e->getMessage();
        $_SESSION['toast_type'] = 'error';
    }
    
    // Redirect to avoid resubmission
    header('Location: profile.php');
    exit;
}

$pageTitle = 'Profile';
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
            color: #495057;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #6c757d;
            font-size: 1.1rem;
        }

        .profile-container {
            display: grid;
            grid-template-columns: 1fr 3fr;
            gap: 2rem;
        }

        .profile-sidebar {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
            text-align: center;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: bold;
            position: relative;
            overflow: hidden;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info h2 {
            color: #495057;
            margin: 1rem 0 0.5rem;
        }

        .profile-info p {
            color: #6c757d;
            margin: 0.5rem 0;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498DB;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.25);
        }

        .form-control:read-only {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }
        
        /* Ensure textarea takes full width */
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
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

        .file-upload-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-upload-wrapper input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-upload-label {
            display: block;
            padding: 0.75rem;
            border: 2px dashed #ced4da;
            border-radius: 6px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-upload-label:hover {
            border-color: #3498DB;
            background-color: #f8f9fa;
        }

        .file-upload-label i {
            font-size: 2rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
            display: block;
        }

        .file-upload-info {
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: #6c757d;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-row:last-child {
            margin-bottom: 0;
        }
        
        /* Image preview container */
        .image-preview-container {
            margin-top: 1rem;
            text-align: center;
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            display: none;
            margin: 0 auto;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        /* Clear preview button */
        #clearPreview {
            display: none;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 1rem;
                margin-bottom: 1rem;
            }
            
            .form-group {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/member_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-user"></i> Profile</h1>
            <p>Manage your account information</p>
        </div>

        <div class="profile-container">
            <div class="profile-sidebar">
                <div class="profile-avatar">
                    <?php if (isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])): ?>
                        <?php if (file_exists('../' . $_SESSION['profile_image'])): ?>
                            <img src="../<?php echo htmlspecialchars($_SESSION['profile_image']); ?>" alt="Profile Image">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>
                    <p>Member</p>
                    <p><?php echo htmlspecialchars($_SESSION['username']); ?></p>
                </div>
                
                <form method="POST" enctype="multipart/form-data" style="margin-top: 1.5rem;">
                    <div class="file-upload-wrapper">
                        <input type="file" id="profile_image" name="profile_image" accept="image/*">
                        <label for="profile_image" class="file-upload-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Upload New Photo</span>
                        </label>
                    </div>
                    <div class="file-upload-info">
                        <small>Max file size: 1MB. JPG, PNG, GIF, WebP formats only.</small>
                    </div>
                    
                    <!-- Image preview container -->
                    <div class="image-preview-container">
                        <img id="imagePreview" class="image-preview" alt="Preview">
                        <button type="button" id="clearPreview" class="btn btn-secondary" style="margin-top: 10px;">
                            <i class="fas fa-times"></i> Clear Preview
                        </button>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                        <i class="fas fa-save"></i>
                        Update Photo
                    </button>
                    
                    <?php if (isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])): ?>
                        <button type="submit" name="remove_profile_image" class="btn btn-danger" style="width: 100%; margin-top: 10px;" 
                                onclick="return confirm('Are you sure you want to remove your profile image?')">
                            <i class="fas fa-trash"></i>
                            Remove Photo
                        </button>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="content-card">
                <div class="card-header">
                    <h2>Account Information</h2>
                </div>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="fullName">Full Name</label>
                        <input type="text" id="fullName" class="form-control" value="<?php echo htmlspecialchars($_SESSION['user_name']); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" class="form-control" value="<?php echo htmlspecialchars($_SESSION['username']); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Role</label>
                        <div class="form-control-static">Member</div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="text" id="phone" name="phone" class="form-control" value="<?php echo isset($_SESSION['phone']) ? htmlspecialchars($_SESSION['phone']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" value="<?php echo isset($_SESSION['date_of_birth']) ? htmlspecialchars($_SESSION['date_of_birth']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <!-- Empty div for spacing -->
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" class="form-control" rows="3" placeholder="Enter your address"><?php echo isset($_SESSION['address']) ? htmlspecialchars($_SESSION['address']) : ''; ?></textarea>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Toast Container -->
    <div id="toast-container"></div>
    
    <!-- Include Messages JS for toast functionality -->
    <script src="../js/messages.js"></script>
    
    <script>
        // Image preview functionality
        document.getElementById('profile_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('imagePreview');
            const clearButton = document.getElementById('clearPreview');
            
            if (file) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    clearButton.style.display = 'inline-flex';
                }
                
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
                clearButton.style.display = 'none';
            }
        });
        
        // Clear preview functionality
        document.getElementById('clearPreview').addEventListener('click', function() {
            const fileInput = document.getElementById('profile_image');
            const preview = document.getElementById('imagePreview');
            const clearButton = document.getElementById('clearPreview');
            
            fileInput.value = '';
            preview.style.display = 'none';
            clearButton.style.display = 'none';
        });
        
        // Show toast notification if exists
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['toast_message'])): ?>
                showToast('<?php echo addslashes($_SESSION['toast_message']); ?>', '<?php echo $_SESSION['toast_type'] ?? 'info'; ?>');
                <?php 
                // Clear the message from session
                unset($_SESSION['toast_message']);
                unset($_SESSION['toast_type']);
                ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>