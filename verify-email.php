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
$autoLogin = false;

// Handle email verification
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = sanitize_input($_GET['token']);
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Find user with this verification token
        $stmt = $db->prepare("
            SELECT id, user_id, username, email, first_name, last_name, role, library_id, email_verification_token 
            FROM users 
            WHERE email_verification_token = ? 
            AND email_verified = FALSE 
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
                $message = 'Verification link has expired. Please contact support to resend verification email.';
                $messageType = 'error';
            } else {
                // Verify the user
                $stmt = $db->prepare("
                    UPDATE users 
                    SET email_verified = TRUE, 
                        email_verification_token = NULL, 
                        status = 'active',
                        updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$user['id']]);
                
                if ($stmt->rowCount() > 0) {
                    // Log the verification activity
                    $stmt = $db->prepare("
                        INSERT INTO activity_logs 
                        (user_id, library_id, action, entity_type, entity_id, description, ip_address, user_agent, created_at)
                        VALUES (?, ?, 'email_verified', 'user', ?, 'Email address verified successfully', ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $user['id'],
                        $user['library_id'],
                        $user['id'],
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                    
                    $message = 'Email verified successfully! You can now login to your account.';
                    $messageType = 'success';
                    $autoLogin = true;
                    
                    // Set up auto-login session data
                    $_SESSION['auto_login_user'] = [
                        'id' => $user['id'],
                        'user_id' => $user['user_id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'first_name' => $user['first_name'],
                        'last_name' => $user['last_name'],
                        'role' => $user['role'],
                        'library_id' => $user['library_id']
                    ];
                    
                } else {
                    $message = 'Failed to verify email. Please try again or contact support.';
                    $messageType = 'error';
                }
            }
        } else {
            $message = 'Invalid or expired verification link. Please check the link or contact support.';
            $messageType = 'error';
        }
        
    } catch (Exception $e) {
        $message = 'An error occurred during verification. Please try again later.';
        $messageType = 'error';
        
        if (DEBUG_MODE) {
            $message .= ' Error: ' . $e->getMessage();
        }
    }
} else {
    $message = 'No verification token provided.';
    $messageType = 'error';
}

$pageTitle = 'Email Verification';
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

        .verification-container {
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .verification-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
            text-align: center;
        }

        .verification-header {
            margin-bottom: 2rem;
        }

        .verification-header .logo {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 1.5rem;
            font-weight: bold;
            color: #3498DB;
            margin-bottom: 1rem;
        }

        .verification-header .logo i {
            font-size: 1.8rem;
            color: #2980B9;
        }

        .verification-header h1 {
            font-size: 1.5rem;
            color: #495057;
            font-weight: 300;
            margin-bottom: 0.5rem;
        }

        .message-container {
            margin: 2rem 0;
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

        .auto-login-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .countdown {
            font-weight: bold;
            color: #e67e22;
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
            margin: 0.5rem;
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

        .action-buttons {
            margin-top: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
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
            .verification-container {
                padding: 0 1rem;
            }
            
            .verification-card {
                padding: 1.5rem;
            }
        }

        /* Loading animation for auto-login */
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
    <div class="verification-container">
        <div class="verification-card">
            <!-- Header -->
            <div class="verification-header">
                <div class="logo">
                    <i class="fas fa-book-open"></i>
                    LMS
                </div>
                <h1>Email Verification</h1>
            </div>

            <!-- Message -->
            <div class="message-container message-<?php echo $messageType; ?>">
                <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle success-icon' : 'fa-exclamation-circle error-icon'; ?>"></i>
                <div><?php echo htmlspecialchars($message); ?></div>
            </div>

            <?php if ($autoLogin): ?>
                <!-- Auto-login notice -->
                <div class="auto-login-notice">
                    <i class="fas fa-info-circle"></i>
                    <span>Redirecting to login in <span class="countdown" id="countdown">5</span> seconds...</span>
                </div>
                
                <div class="action-buttons">
                    <a href="login.php" class="btn btn-primary">
                        <span class="loading-spinner"></span>
                        Login Now
                    </a>
                </div>
            <?php else: ?>
                <!-- Action buttons for failed verification -->
                <div class="action-buttons">
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i>
                        Go to Login
                    </a>
                    <a href="register.php" class="btn btn-outline">
                        <i class="fas fa-user-plus"></i>
                        Register New Account
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

    <?php if ($autoLogin): ?>
    <script>
        // Countdown and auto-redirect
        let countdown = 5;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(timer);
                window.location.href = 'login.php';
            }
        }, 1000);
        
        // Allow immediate login if user clicks the button
        document.querySelector('.btn-primary').addEventListener('click', function() {
            clearInterval(timer);
        });
    </script>
    <?php endif; ?>
</body>
</html>