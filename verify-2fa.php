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

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

// Redirect if no pending 2FA
if (!isset($_SESSION['pending_2fa_user_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';
$authService = new AuthService();

// Handle 2FA verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_code'])) {
        $code = sanitize_input($_POST['verification_code']);
        
        if (empty($code)) {
            $error = 'Please enter the verification code.';
        } elseif (!preg_match('/^\d{6}$/', $code)) {
            $error = 'Verification code must be 6 digits.';
        } else {
            $result = $authService->verify2FACode($_SESSION['pending_2fa_user_id'], $code);
            
            if ($result['success']) {
                // Get user data from session
                $userData = $_SESSION['pending_2fa_user_data'];
                
                // Complete login process
                $_SESSION['user_id'] = $userData['id'];
                $_SESSION['username'] = $userData['username'];
                $_SESSION['user_role'] = $userData['role'];
                $_SESSION['library_id'] = $userData['library_id'];
                $_SESSION['user_name'] = $userData['first_name'] . ' ' . $userData['last_name'];
                $_SESSION['email'] = $userData['email'];
                $_SESSION['profile_image'] = $userData['profile_image'] ?? '';
                $_SESSION['phone'] = $userData['phone'] ?? '';
                $_SESSION['address'] = $userData['address'] ?? '';
                $_SESSION['date_of_birth'] = $userData['date_of_birth'] ?? '';
                
                // Reset login attempts
                $authService->resetLoginAttempts($userData['id']);
                
                // Set remember me cookie if requested
                if (isset($_SESSION['remember_me']) && $_SESSION['remember_me']) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + (86400 * 30), '/'); // 30 days
                    unset($_SESSION['remember_me']);
                }
                
                // Log activity
                $authService->logActivity(
                    $userData['id'], 
                    $userData['library_id'], 
                    'user_login', 
                    'User logged in with 2FA', 
                    $_SERVER['REMOTE_ADDR'] ?? ''
                );
                
                // Clear 2FA session data
                unset($_SESSION['pending_2fa_user_id']);
                unset($_SESSION['pending_2fa_user_data']);
                
                // Redirect based on role
                $redirectUrl = $authService->getRedirectUrl($userData['role'], $userData['library_id']);
                header('Location: ' . $redirectUrl);
                exit;
            } else {
                $error = $result['message'];
            }
        }
    } elseif (isset($_POST['resend_code'])) {
        // Resend verification code
        $userData = $_SESSION['pending_2fa_user_data'];
        
        if ($authService->generateAndSend2FACode(
            $userData['id'], 
            $userData['email'], 
            $userData['first_name'] . ' ' . $userData['last_name']
        )) {
            $success = 'A new verification code has been sent to your email.';
        } else {
            $error = 'Failed to send verification code. Please try again.';
        }
    }
}

$pageTitle = 'Two-Factor Authentication';
$userData = $_SESSION['pending_2fa_user_data'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - LMS</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Navbar CSS -->
    <link rel="stylesheet" href="css/navbar.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            margin: 0;
        }

        .verify-container {
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .verify-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
            text-align: center;
        }

        .verify-header {
            margin-bottom: 1.5rem;
        }

        .verify-header .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 2rem;
        }

        .verify-header h1 {
            font-size: 1.5rem;
            color: #495057;
            font-weight: 300;
            margin-bottom: 0.5rem;
        }

        .verify-header p {
            color: #6c757d;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .user-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
            border-left: 4px solid #3498DB;
        }

        .user-info strong {
            color: #495057;
        }

        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #495057;
            font-size: 0.95rem;
        }

        .verification-input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1.5rem;
            text-align: center;
            letter-spacing: 0.5rem;
            font-weight: bold;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .verification-input:focus {
            outline: none;
            border-color: #3498DB;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-bottom: 0.75rem;
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
            background: #ffffff;
            color: #6c757d;
            border: 2px solid #e9ecef;
        }

        .btn-secondary:hover {
            background: #f8f9fa;
            border-color: #3498DB;
            color: #3498DB;
        }

        .alert {
            padding: 0.85rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .help-text {
            color: #6c757d;
            font-size: 0.85rem;
            margin-top: 0.5rem;
            line-height: 1.4;
        }

        .timer {
            color: #e74c3c;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .back-login {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }

        .back-login a {
            color: #6c757d;
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: color 0.3s ease;
        }

        .back-login a:hover {
            color: #495057;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .verify-container {
                max-width: 100%;
                padding: 0 1rem;
            }
            
            .verify-card {
                padding: 1.5rem;
            }

            .verification-input {
                font-size: 1.25rem;
                letter-spacing: 0.3rem;
            }
        }
    </style>

    <script>
        // Auto-focus on verification input
        window.addEventListener('load', function() {
            const input = document.getElementById('verification_code');
            if (input) {
                input.focus();
            }
        });

        // Only allow numbers in verification input
        function enforceNumeric(event) {
            const input = event.target;
            input.value = input.value.replace(/[^0-9]/g, '');
            
            // Limit to 6 digits
            if (input.value.length > 6) {
                input.value = input.value.slice(0, 6);
            }
        }

        // Countdown timer for resend (optional enhancement)
        let countdownTimer = 60;
        function startCountdown() {
            const resendBtn = document.querySelector('button[name="resend_code"]');
            const timer = document.getElementById('resend-timer');
            
            if (resendBtn && timer) {
                resendBtn.disabled = true;
                resendBtn.innerHTML = '<i class="fas fa-clock"></i> Resend in <span id="countdown">' + countdownTimer + '</span>s';
                
                const interval = setInterval(function() {
                    countdownTimer--;
                    document.getElementById('countdown').textContent = countdownTimer;
                    
                    if (countdownTimer <= 0) {
                        clearInterval(interval);
                        resendBtn.disabled = false;
                        resendBtn.innerHTML = '<i class="fas fa-redo"></i> Resend Code';
                        countdownTimer = 60;
                    }
                }, 1000);
            }
        }
    </script>
</head>
<body>
    <div class="verify-container">
        <div class="verify-card">
            <!-- Header -->
            <div class="verify-header">
                <div class="icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1>Two-Factor Authentication</h1>
                <p>We've sent a 6-digit verification code to your email address for security.</p>
            </div>

            <!-- User Info -->
            <?php if ($userData): ?>
            <div class="user-info">
                <strong>Signing in as:</strong> <?php echo htmlspecialchars($userData['first_name'] . ' ' . $userData['last_name']); ?><br>
                <strong>Email:</strong> <?php echo htmlspecialchars(substr($userData['email'], 0, 2) . '***@' . substr($userData['email'], strpos($userData['email'], '@') + 1)); ?>
            </div>
            <?php endif; ?>

            <!-- Error/Success Messages -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Verification Form -->
            <form method="POST" action="verify-2fa.php" id="verify-form">
                <div class="form-group">
                    <label for="verification_code">Enter Verification Code</label>
                    <input type="text" 
                           id="verification_code" 
                           name="verification_code" 
                           class="verification-input"
                           placeholder="000000"
                           maxlength="6"
                           oninput="enforceNumeric(event)"
                           required>
                    <div class="help-text">
                        <i class="fas fa-info-circle"></i>
                        Enter the 6-digit code sent to your email. Code expires in 5 minutes.
                    </div>
                </div>

                <button type="submit" name="verify_code" class="btn btn-primary">
                    <i class="fas fa-check"></i>
                    Verify & Sign In
                </button>
            </form>

            <!-- Resend Code -->
            <form method="POST" action="verify-2fa.php" style="margin-top: 1rem;">
                <button type="submit" name="resend_code" class="btn btn-secondary" onclick="startCountdown()">
                    <i class="fas fa-redo"></i>
                    Resend Code
                </button>
                <div id="resend-timer"></div>
            </form>

            <!-- Security Notice -->
            <div class="help-text" style="margin-top: 1rem; text-align: center;">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Security Notice:</strong> Never share this code with anyone. If you didn't attempt to sign in, please secure your account immediately.
            </div>

            <!-- Back to Login -->
            <div class="back-login">
                <a href="login.php">
                    <i class="fas fa-arrow-left"></i>
                    Back to Login
                </a>
            </div>
        </div>
    </div>
</body>
</html>