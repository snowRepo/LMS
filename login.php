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

$error = '';
$success = '';
$authService = new AuthService();

// Handle logout success message
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success = 'You have been successfully logged out.';
}

// Handle auto-login after email verification or account setup
if (isset($_SESSION['auto_login_user'])) {
    $autoLoginUser = $_SESSION['auto_login_user'];
    unset($_SESSION['auto_login_user']);
    
    // Set up user session for auto-login - ALWAYS use the integer id field
    $_SESSION['user_id'] = $autoLoginUser['id'];
    $_SESSION['username'] = $autoLoginUser['username'];
    $_SESSION['user_role'] = $autoLoginUser['role'];
    $_SESSION['library_id'] = $autoLoginUser['library_id'];
    $_SESSION['user_name'] = $autoLoginUser['first_name'] . ' ' . $autoLoginUser['last_name'];
    $_SESSION['email'] = $autoLoginUser['email'];
    $_SESSION['profile_image'] = $autoLoginUser['profile_image'] ?? '';
    $_SESSION['phone'] = $autoLoginUser['phone'] ?? '';
    $_SESSION['address'] = $autoLoginUser['address'] ?? '';
    $_SESSION['date_of_birth'] = $autoLoginUser['date_of_birth'] ?? '';
    
    // Update last login
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        
        // Log the auto-login activity
        $stmt = $db->prepare("
            INSERT INTO activity_logs 
            (user_id, library_id, action, entity_type, entity_id, description, ip_address, user_agent, created_at)
            VALUES (?, ?, 'auto_login', 'user', ?, 'User auto-logged in after account setup', ?, ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $autoLoginUser['library_id'],
            $_SESSION['user_id'],
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        // Continue even if logging fails
    }
    
    // Determine redirect URL based on role
    $redirectUrl = $authService->getRedirectUrl($autoLoginUser['role'], $autoLoginUser['library_id']);
    header('Location: ' . $redirectUrl);
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            // Get user by username or email
            $user = $authService->getUserByCredentials($username);
            
            if (!$user) {
                $error = 'Invalid username or password.';
            } elseif ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                $error = 'Account is temporarily locked. Please try again later.';
            } elseif (!$user['email_verified']) {
                $error = 'Please verify your email address before logging in. Check your inbox for the verification link.';
            } elseif (password_verify($password, $user['password_hash'])) {
                // Valid credentials - check if admin or regular user
                
                if ($user['role'] === 'admin') {
                    // Admin users bypass 2FA - direct login
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_string_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['library_id'] = $user['library_id'];
                    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['profile_image'] = $user['profile_image'] ?? '';
                    $_SESSION['phone'] = $user['phone'] ?? '';
                    $_SESSION['address'] = $user['address'] ?? '';
                    $_SESSION['date_of_birth'] = $user['date_of_birth'] ?? '';
                    
                    // Reset login attempts
                    $authService->resetLoginAttempts($user['id']);
                    
                    // Update last login
                    try {
                        $db = Database::getInstance()->getConnection();
                        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                        $stmt->execute([$user['id']]);
                        
                        // Log admin login
                        $stmt = $db->prepare("
                            INSERT INTO activity_logs 
                            (user_id, library_id, action, entity_type, entity_id, description, ip_address, user_agent, created_at)
                            VALUES (?, ?, 'admin_login', 'user', ?, 'Admin user logged in directly', ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $user['id'],
                            $user['library_id'],
                            $user['id'],
                            $_SERVER['REMOTE_ADDR'] ?? '',
                            $_SERVER['HTTP_USER_AGENT'] ?? ''
                        ]);
                    } catch (Exception $e) {
                        // Continue even if logging fails
                    }
                    
                    // Set remember me cookie if requested
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        $expires = time() + (30 * 24 * 60 * 60); // 30 days
                        setcookie('remember_token', $token, $expires, '/', '', false, true);
                        
                        // Store token in database (you may want to create a remember_tokens table)
                    }
                    
                    // Redirect admin to dashboard
                    $redirectUrl = $authService->getRedirectUrl($user['role'], $user['library_id']);
                    header('Location: ' . $redirectUrl);
                    exit;
                } else {
                    // Regular users go through 2FA process
                    $userName = $user['first_name'] . ' ' . $user['last_name'];
                    
                    $result = $authService->generateAndSend2FACode($user['id'], $user['email'], $userName);
                    
                    if ($result) {
                        // Store user data in session for 2FA verification
                        $_SESSION['pending_2fa_user_id'] = $user['id'];
                        $_SESSION['pending_2fa_user_data'] = $user;
                        
                        // Store remember me preference
                        if ($remember) {
                            $_SESSION['remember_me'] = true;
                        }
                        
                        // Add a small delay to allow spinner to be visible
                        usleep(500000); // 0.5 seconds
                        
                        // Redirect to 2FA verification page
                        header('Location: verify-2fa.php');
                        exit;
                    } else {
                        $error = 'Failed to send verification code. Please try again.';
                    }
                }
            } else {
                // Increment login attempts
                $attempts = $user['login_attempts'] + 1;
                $lockUntil = null;
                
                if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                    $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_TIME);
                }
                
                $authService->updateLoginAttempts($user['id'], $attempts, $lockUntil);
                
                if ($lockUntil) {
                    $error = 'Too many failed attempts. Account locked for ' . (LOCKOUT_TIME / 60) . ' minutes.';
                } else {
                    $remaining = MAX_LOGIN_ATTEMPTS - $attempts;
                    $error = "Invalid username or password. $remaining attempts remaining.";
                }
            }
        } catch (Exception $e) {
            $error = 'Login failed. Please try again.';
            if (DEBUG_MODE) {
                $error .= ' Error: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Login';
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

        .login-container {
            max-width: 600px;
            width: 100%;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .login-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
            text-align: center;
        }

        .login-header {
            margin-bottom: 1rem;
        }

        .login-header .logo {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 1.5rem;
            font-weight: bold;
            color: #3498DB;
            margin-bottom: 0.75rem;
        }

        .login-header .logo i {
            font-size: 1.75rem;
            color: #2980B9;
        }

        .login-header h1 {
            font-size: 1.5rem;
            color: #495057;
            font-weight: 300;
            margin-bottom: 0.25rem;
        }

        .login-header p {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 1rem;
            text-align: left;
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
        }

        .form-control:focus {
            outline: none;
            border-color: #3498DB;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .input-group {
            position: relative;
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
            padding-right: 2.75rem;
        }

        .password-toggle {
            position: absolute;
            right: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            cursor: pointer;
            font-size: 0.9rem;
            transition: color 0.3s ease;
            z-index: 2;
        }

        .password-toggle:hover {
            color: #3498DB;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #3498DB;
        }

        .checkbox-group label {
            margin: 0;
            font-size: 0.95rem;
            color: #6c757d;
            cursor: pointer;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
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
            color: #3498DB;
            border: 2px solid #3498DB;
        }

        .btn-secondary:hover {
            background: #3498DB;
            color: #ffffff;
            transform: translateY(-2px);
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

        .divider {
            display: flex;
            align-items: center;
            margin: 1rem 0;
            color: #6c757d;
            font-size: 0.85rem;
        }

        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #e9ecef;
        }

        .divider span {
            padding: 0 1rem;
        }

        .auth-links {
            margin-top: 1rem;
            text-align: center;
        }

        .auth-links a {
            color: #3498DB;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .auth-links a:hover {
            color: #2980B9;
            text-decoration: underline;
        }

        .back-home {
            text-align: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
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

        /* Spinner Styles */
        .spinner {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin: 1rem 0;
        }

        .spinner .spinner-border {
            width: 1.5rem;
            height: 1.5rem;
            border: 2px solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: spinner-border 0.75s linear infinite;
        }

        @keyframes spinner-border {
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .login-container {
                max-width: 100%;
                padding: 0 1rem;
            }
            
            .login-card {
                padding: 1.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 0 0.5rem;
            }
            
            .login-card {
                padding: 1.25rem;
            }
        }
    </style>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordToggle = document.getElementById('passwordToggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordToggle.classList.remove('fa-eye');
                passwordToggle.classList.add('fa-eye-slash');
                passwordToggle.title = 'Hide Password';
            } else {
                passwordInput.type = 'password';
                passwordToggle.classList.remove('fa-eye-slash');
                passwordToggle.classList.add('fa-eye');
                passwordToggle.title = 'Show Password';
            }
        }

        // Show spinner when form is submitted
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            if (loginForm) {
                loginForm.addEventListener('submit', function() {
                    // Show spinner
                    const spinner = document.getElementById('loginSpinner');
                    if (spinner) {
                        spinner.style.display = 'flex';
                    }
                    
                    // Disable submit button
                    const loginButton = document.getElementById('loginButton');
                    if (loginButton) {
                        loginButton.disabled = true;
                        loginButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    }
                });
            }
            
            // Hide spinner on page load if there are errors
            const errorElement = document.querySelector('.alert-danger');
            const spinner = document.getElementById('loginSpinner');
            const loginButton = document.getElementById('loginButton');
            
            if (errorElement && spinner && loginButton) {
                spinner.style.display = 'none';
                loginButton.disabled = false;
                loginButton.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
            }
        });
    </script>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Header -->
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-book-open"></i>
                    LMS
                </div>
                <h1>Welcome Back</h1>
                <p>Sign in to your library account</p>
            </div>

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

            <!-- Login Form -->
            <form method="POST" action="login.php" id="loginForm">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               class="form-control" 
                               placeholder="Enter your username or email"
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                               required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-control" 
                               placeholder="Enter your password"
                               required>
                        <span class="fas fa-eye password-toggle" 
                              onclick="togglePassword()"
                              id="passwordToggle"
                              title="Show/Hide Password"></span>
                    </div>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Remember me</label>
                </div>

                <button type="submit" class="btn btn-primary" id="loginButton">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                </button>

                <!-- Spinner -->
                <div class="spinner" id="loginSpinner">
                    <div class="spinner-border text-primary" role="status"></div>
                    <span>Sending 2FA code...</span>
                </div>
            </form>

            <div class="divider">
                <span>or</span>
            </div>

            <!-- Auth Links -->
            <div class="auth-links">
                <p>Don't have an account? <a href="register.php">Create one now</a></p>
                <p><a href="forgot-password.php">Forgot your password?</a></p>
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