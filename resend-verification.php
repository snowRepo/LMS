<?php
define('LMS_ACCESS', true);

// Load configuration
require_once 'includes/EnvLoader.php';
EnvLoader::load();
include 'config/config.php';
require_once 'includes/EmailService.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$message = '';
$messageType = '';

// Handle resend verification email
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email']);
    
    if (empty($email)) {
        $message = 'Please enter your email address.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    } else {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Find user with this email who is not verified
            $stmt = $db->prepare("
                SELECT id, username, email, first_name, last_name, email_verified, email_verification_token, created_at
                FROM users 
                WHERE email = ? AND email_verified = FALSE AND status = 'active'
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Check if user was created within the last 7 days (to prevent spam)
                $createdTime = strtotime($user['created_at']);
                $currentTime = time();
                $daysSinceCreation = ($currentTime - $createdTime) / 86400;
                
                if ($daysSinceCreation > 7) {
                    $message = 'Verification request expired. Please contact support or register a new account.';
                    $messageType = 'error';
                } else {
                    // Generate new verification token
                    $newToken = bin2hex(random_bytes(32));
                    
                    // Update user with new token
                    $stmt = $db->prepare("
                        UPDATE users 
                        SET email_verification_token = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$newToken, $user['id']]);
                    
                    // Send verification email
                    try {
                        $emailService = new EmailService();
                        $userName = $user['first_name'] . ' ' . $user['last_name'];
                        $emailSent = $emailService->sendVerificationEmail($email, $userName, $newToken);
                        
                        if ($emailSent) {
                            $message = 'Verification email sent successfully! Please check your inbox and spam folder.';
                            $messageType = 'success';
                            
                            // Log the resend activity
                            $stmt = $db->prepare("
                                INSERT INTO activity_logs 
                                (user_id, library_id, action, entity_type, entity_id, description, ip_address, user_agent, created_at)
                                VALUES (?, ?, 'verification_resent', 'user', ?, 'Verification email resent', ?, ?, NOW())
                            ");
                            $stmt->execute([
                                $user['id'],
                                null, // User may not have library_id yet if not verified
                                $user['id'],
                                $_SERVER['REMOTE_ADDR'] ?? '',
                                $_SERVER['HTTP_USER_AGENT'] ?? ''
                            ]);
                        } else {
                            $message = 'Failed to send verification email. Please try again later or contact support.';
                            $messageType = 'error';
                        }
                    } catch (Exception $e) {
                        $message = 'Failed to send verification email. Please contact support.';
                        $messageType = 'error';
                        
                        if (DEBUG_MODE) {
                            $message .= ' Error: ' . $e->getMessage();
                        }
                    }
                }
            } else {
                // Don't reveal if email exists or not for security
                $message = 'If an unverified account with this email exists, a verification email has been sent.';
                $messageType = 'info';
            }
            
        } catch (Exception $e) {
            $message = 'An error occurred. Please try again later.';
            $messageType = 'error';
            
            if (DEBUG_MODE) {
                $message .= ' Error: ' . $e->getMessage();
            }
        }
    }
} elseif (isset($_SESSION['pending_verification_email'])) {
    // Pre-fill email from session if available
    $prefilledEmail = $_SESSION['pending_verification_email'];
    unset($_SESSION['pending_verification_email']);
}

$pageTitle = 'Resend Verification Email';
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

        .resend-container {
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
            padding: 0 1rem;
            box-sizing: border-box;
        }

        .resend-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
            text-align: center;
            overflow: hidden;
            position: relative;
            box-sizing: border-box;
        }

        form {
            margin-bottom: 1.5rem;
        }

        .resend-header {
            margin-bottom: 2rem;
        }

        .resend-header .logo {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 1.5rem;
            font-weight: bold;
            color: #3498DB;
            margin-bottom: 1rem;
        }

        .resend-header .logo i {
            font-size: 1.8rem;
            color: #2980B9;
        }

        .resend-header h1 {
            font-size: 1.5rem;
            color: #495057;
            font-weight: 300;
            margin-bottom: 0.5rem;
        }

        .resend-header p {
            color: #6c757d;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
            clear: both;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #495057;
            font-size: 0.95rem;
        }

        .form-control {
            width: 100%;
            padding: 0.85rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498DB;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .input-group {
            position: relative;
            width: 100%;
            box-sizing: border-box;
        }

        .input-group i {
            position: absolute;
            left: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 0.9rem;
        }

        .input-group .form-control {
            padding-left: 2.75rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .alert i {
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0.85rem 1.5rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-bottom: 1rem;
            box-sizing: border-box;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            color: #ffffff;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
        }

        .btn-outline {
            background: #ffffff;
            color: #3498DB;
            border: 2px solid #3498DB;
        }

        .btn-outline:hover {
            background: #3498DB;
            color: #ffffff;
            transform: translateY(-2px);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .action-links {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-top: 1.5rem;
            padding: 0;
            width: 100%;
            box-sizing: border-box;
        }

        .back-home {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e9ecef;
            clear: both;
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

        .help-text {
            font-size: 0.85rem;
            color: #6c757d;
            text-align: left;
            margin-top: 0.5rem;
        }

        /* Clearfix */
        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }

        /* Loading Spinner */
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 0.8s ease-in-out infinite;
            margin-right: 0.5rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading-text {
            opacity: 0.9;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .resend-container {
                padding: 0 1rem;
            }
            
            .resend-card {
                padding: 1.5rem;
            }
        }
    </style>
    
    <script>
        // Handle resend verification form submission with loading state
        document.addEventListener('DOMContentLoaded', function() {
            const resendBtn = document.getElementById('resendButton');
            
            if (resendBtn) {
                const form = resendBtn.closest('form');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        // Don't prevent default - let the form submit
                        const buttonContent = resendBtn.querySelector('.button-content');
                        const loadingContent = resendBtn.querySelector('.loading-content');
                        
                        // Show loading state
                        if (buttonContent) buttonContent.style.display = 'none';
                        if (loadingContent) {
                            loadingContent.style.display = 'flex';
                            loadingContent.style.alignItems = 'center';
                            loadingContent.style.justifyContent = 'center';
                        }
                        
                        // Disable button to prevent double submission
                        resendBtn.disabled = true;
                    });
                }
            }
        });
    </script>
</head>
<body>
    <div class="resend-container">
        <div class="resend-card clearfix">
            <!-- Header -->
            <div class="resend-header">
                <div class="logo">
                    <i class="fas fa-envelope-open"></i>
                    LMS
                </div>
                <h1>Resend Verification Email</h1>
                <p>Enter your email address to receive a new verification link.</p>
            </div>

            <!-- Message -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas <?php 
                        echo $messageType === 'success' ? 'fa-check-circle' : 
                             ($messageType === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'); 
                    ?>"></i>
                    <div><?php echo htmlspecialchars($message); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($messageType !== 'success'): ?>
                <!-- Resend Form -->
                <form method="POST" action="resend-verification.php">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-group">
                            <i class="fas fa-envelope"></i>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   class="form-control" 
                                   placeholder="Enter your email address"
                                   value="<?php echo htmlspecialchars($prefilledEmail ?? $_POST['email'] ?? ''); ?>"
                                   required>
                        </div>
                        <div class="help-text">
                            We'll send a new verification link to this email address if an unverified account exists.
                        </div>
                    </div>

                    <button type="submit" id="resendButton" class="btn btn-primary">
                        <span class="button-content">
                            <i class="fas fa-paper-plane"></i>
                            Send Verification Email
                        </span>
                        <span class="loading-content" style="display: none;">
                            <span class="spinner"></span>
                            <span class="loading-text">Sending Email...</span>
                        </span>
                    </button>
                </form>
            <?php endif; ?>

            <!-- Action Links -->
            <div class="action-links">
                <?php if ($messageType === 'success'): ?>
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i>
                        Go to Login
                    </a>
                <?php endif; ?>
                
                <a href="register.php" class="btn btn-outline">
                    <i class="fas fa-user-plus"></i>
                    Register New Account
                </a>
            </div>

            <!-- Back to Home -->
            <div class="back-home">
                <a href="index.php">
                    <i class="fas fa-arrow-left"></i>
                    Back to Home
                </a>
            </div>
        </div>
    </div>
</body>
</html>