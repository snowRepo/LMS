<?php
define('LMS_ACCESS', true);

// Load configuration
require_once 'includes/EnvLoader.php';
EnvLoader::load();
include 'config/config.php';
require_once 'includes/AuthService.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Clear any existing auto-login session data to prevent confusion
unset($_SESSION['auto_login_user']);
unset($_SESSION['pending_2fa_user_id']);
unset($_SESSION['pending_2fa_user_data']);

$message = '';
$messageType = '';
$showPasswordForm = false;
$userData = null;

// Handle librarian setup
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = sanitize_input($_GET['token']);
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Find librarian with this verification token and pending status
        $stmt = $db->prepare("
            SELECT id, user_id, username, email, first_name, last_name, role, library_id, email_verification_token, status 
            FROM users 
            WHERE email_verification_token = ? 
            AND role = 'librarian'
            AND status = 'pending'
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Check if token has expired (24 hours)
            $stmt = $db->prepare("SELECT created_at FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $userCreated = $stmt->fetch();
            
            $createdTime = strtotime($userCreated['created_at']);
            $currentTime = time();
            $tokenAge = $currentTime - $createdTime;
            
            // Token expires after 24 hours (86400 seconds)
            if ($tokenAge > 86400) {
                $message = 'Setup link has expired. Please contact your supervisor to resend the setup email.';
                $messageType = 'error';
            } else {
                // Show password setup form
                $showPasswordForm = true;
                $userData = $user;
            }
        } else {
            $message = 'Invalid or expired setup link. Please check the link or contact your supervisor.';
            $messageType = 'error';
        }
        
    } catch (Exception $e) {
        $message = 'An error occurred during setup. Please try again later.';
        $messageType = 'error';
        
        if (DEBUG_MODE) {
            $message .= ' Error: ' . $e->getMessage();
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'setup_password') {
    // Handle password setup
    // Debug: Log that we've reached this point
    error_log("Password setup form submitted");
    error_log("POST data: " . print_r($_POST, true));
    
    try {
        $db = Database::getInstance()->getConnection();
        
        $userId = sanitize_input($_POST['user_id']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        $csrfToken = $_POST['csrf_token'];
        
        // Debug: Log the received data
        error_log("User ID: " . $userId);
        error_log("Password length: " . strlen($password));
        error_log("Confirm password length: " . strlen($confirmPassword));
        
        // Validate CSRF token
        if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            $message = 'Invalid request. Please try again.';
            $messageType = 'error';
            error_log("Invalid CSRF token");
            return;
        }
        
        // Validate passwords
        if (empty($password) || empty($confirmPassword)) {
            $message = 'Please enter both password and confirmation.';
            $messageType = 'error';
            error_log("Validation failed: Empty password fields");
        } elseif ($password !== $confirmPassword) {
            $message = 'Passwords do not match.';
            $messageType = 'error';
            error_log("Validation failed: Passwords don't match");
        } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
            $message = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
            $messageType = 'error';
            error_log("Validation failed: Password too short");
        } else {
            // Hash password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            // Update user: set password, change status to active, clear verification token, and mark email as verified
            $stmt = $db->prepare("
                UPDATE users 
                SET password_hash = ?, 
                    email_verification_token = NULL,
                    email_verified = TRUE,
                    status = 'active',
                    updated_at = NOW() 
                WHERE id = ? 
                AND role = 'librarian'
                AND status = 'pending'
            ");
            
            // Get the numeric ID for the user first
            $stmtId = $db->prepare("SELECT id FROM users WHERE user_id = ?");
            $stmtId->execute([$userId]);
            $user = $stmtId->fetch();
            
            if (!$user || !isset($user['id'])) {
                throw new Exception("Could not find user with user_id: " . $userId);
            }
            
            $numericId = $user['id'];
            $result = $stmt->execute([$passwordHash, $numericId]);
            
            // Debug: Log the result of the update
            error_log("Update result: " . ($result ? 'true' : 'false'));
            error_log("Rows affected: " . $stmt->rowCount());
            
            if ($result && $stmt->rowCount() > 0) {
                // Get library ID for activity log
                $stmt = $db->prepare("SELECT library_id FROM users WHERE id = ?");
                $stmt->execute([$numericId]);
                $libraryData = $stmt->fetch();
                
                if (!$libraryData) {
                    throw new Exception("Could not find library ID for user: " . $userId);
                }
                
                $libraryId = $libraryData['library_id'];
                
                // Log the setup activity
                $stmt = $db->prepare("
                    INSERT INTO activity_logs 
                    (user_id, library_id, action, entity_type, entity_id, description, ip_address, user_agent, created_at)
                    VALUES (?, ?, 'librarian_setup', 'user', ?, 'Librarian account setup completed successfully', ?, ?, NOW())
                ");
                $stmt->execute([
                    $numericId,
                    $libraryId,
                    $numericId,
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
                
                $message = 'Account setup completed successfully! You can now login to your account.';
                $messageType = 'success';
                
                // Ensure no auto-login session data exists
                unset($_SESSION['auto_login_user']);
                
                // Remove the auto-login session variable so user must manually login
                // $_SESSION['auto_login_user'] = [
                //     'id' => $numericId,
                //     'user_id' => $userId,
                //     'username' => $userData['username'],
                //     'email' => $userData['email'],
                //     'first_name' => $userData['first_name'],
                //     'last_name' => $userData['last_name'],
                //     'role' => 'librarian',
                //     'library_id' => $userData['library_id']
                // ];
                error_log("Password setup successful for user: " . $userId);
            } else {
                $message = 'Failed to complete setup. Please try again or contact support.';
                $messageType = 'error';
                error_log("Database update failed for user ID: " . $userId);
            }
        }
    } catch (Exception $e) {
        $message = 'An error occurred during setup. Please try again later.';
        $messageType = 'error';
        
        if (DEBUG_MODE) {
            $message .= ' Error: ' . $e->getMessage();
        }
        error_log("Exception in password setup: " . $e->getMessage());
    }
} else {
    $message = 'No setup token provided.';
    $messageType = 'error';
}

$pageTitle = 'Librarian Account Setup';
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
            padding: 2rem 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .setup-container {
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .setup-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
        }

        .setup-header {
            margin-bottom: 2rem;
            text-align: center;
        }

        .setup-header .logo {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 1.5rem;
            font-weight: bold;
            color: #3498DB;
            margin-bottom: 1rem;
        }

        .setup-header .logo i {
            font-size: 1.8rem;
            color: #2980B9;
        }

        .setup-header h1 {
            font-size: 1.5rem;
            color: #495057;
            font-weight: 300;
            margin-bottom: 0.5rem;
        }

        .message-container {
            margin: 1rem 0;
            padding: 1.5rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1rem;
            line-height: 1.5;
        }

        .message-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .message-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .message-container i {
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .success-icon {
            color: #28a745;
        }

        .error-icon {
            color: #dc3545;
        }

        .user-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }

        .user-info h3 {
            margin-top: 0;
            color: #495057;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 0.5rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }

        .info-label {
            color: #6c757d;
            font-weight: 500;
        }

        .info-value {
            color: #495057;
            font-weight: 500;
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
            transition: border-color 0.3s;
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
            justify-content: center;
            gap: 8px;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin: 0.5rem 0;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            color: #ffffff;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: #ffffff;
        }

        .btn-secondary:hover {
            background: #495057;
            transform: translateY(-2px);
        }

        .password-requirements {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
            font-size: 0.9rem;
        }

        .back-home {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e9ecef;
        }

        .back-home a {
            color: #6c757d;
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: color 0.3s ease;
        }

        .back-home a:hover {
            color: #495057;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .setup-container {
                padding: 0 1rem;
            }
            
            .setup-card {
                padding: 1.5rem;
            }
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
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-card">
            <!-- Header -->
            <div class="setup-header">
                <div class="logo">
                    <i class="fas fa-book-open"></i>
                    LMS
                </div>
                <h1>Librarian Account Setup</h1>
            </div>

            <?php if (!empty($message) && $messageType !== 'success'): ?>
                <!-- Message -->
                <div class="message-container message-<?php echo $messageType; ?>">
                    <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle success-icon' : 'fa-exclamation-circle error-icon'; ?>"></i>
                    <div><?php echo htmlspecialchars($message); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($showPasswordForm && $userData): ?>
                <!-- User Info -->
                <div class="user-info">
                    <h3>Account Details</h3>
                    <div class="info-item">
                        <span class="info-label">Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($userData['first_name'] . ' ' . $userData['last_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Username:</span>
                        <span class="info-value"><?php echo htmlspecialchars($userData['username']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($userData['email']); ?></span>
                    </div>
                </div>

                <!-- Password Setup Form -->
                <form method="POST" id="passwordForm" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <input type="hidden" name="action" value="setup_password">
                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($userData['user_id']); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); ?>">
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required 
                               minlength="<?php echo PASSWORD_MIN_LENGTH; ?>"
                               pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{" . PASSWORD_MIN_LENGTH . ",}$"
                               title="Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters long and include at least one uppercase letter, one lowercase letter, one number, and one special character.">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        <div id="password-match-error" style="color: #dc3545; display: none; margin-top: 0.5rem;">
                            Passwords do not match.
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" id="setupBtn">
                        <span id="buttonText">Complete Setup</span>
                        <span id="buttonSpinner" class="loading-spinner" style="display: none; margin-left: 8px;"></span>
                    </button>
                </form>
            <?php elseif ($messageType === 'success'): ?>
                <!-- Success message with login button -->
                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; margin: 2rem 0; width: 100%;">
                    <div class="message-container message-success" style="width: 100%; max-width: 100%; box-sizing: border-box;">
                        <i class="fas fa-check-circle success-icon"></i>
                        <div><?php echo htmlspecialchars($message); ?></div>
                    </div>
                    <a href="login.php" class="btn btn-primary" style="margin-top: 1.5rem; width: 100%; max-width: 300px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <i class="fas fa-sign-in-alt"></i>
                        Go to Login
                    </a>
                </div>
            <?php elseif ($messageType !== 'success'): ?>
                <!-- Action buttons for failed verification -->
                <div style="text-align: center; margin-top: 2rem;">
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i>
                        Go to Login
                    </a>
                </div>
            <?php endif; ?>

            <!-- Back to Home -->
            <div class="back-home">
                <a href="index.php">
                    <i class="fas fa-arrow-left"></i>
                    Back to Home
                </a>
            </div>
        </div>
    </div>

    <?php if ($messageType === 'success'): ?>
    <!-- Removed auto-redirect to allow user to see success message and click login button -->
    <?php endif; ?>
    
    <?php if ($showPasswordForm && $userData): ?>
    <script>
    document.getElementById('passwordForm').addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const passwordMatchError = document.getElementById('password-match-error');
        const submitBtn = document.getElementById('setupBtn');
        const buttonText = document.getElementById('buttonText');
        const buttonSpinner = document.getElementById('buttonSpinner');
        
        // Reset error state
        passwordMatchError.style.display = 'none';
        
        // Check if passwords match
        if (password !== confirmPassword) {
            e.preventDefault();
            passwordMatchError.style.display = 'block';
            return false;
        }
        
        // Show loading state
        submitBtn.disabled = true;
        buttonText.textContent = 'Setting up your account...';
        buttonSpinner.style.display = 'inline-block';
        
        return true;
    });
    </script>
    <?php endif; ?>
</body>
</html>