<?php
/**
 * Admin Creation Tool - LOCALHOST ONLY
 * This file creates the initial admin user for the LMS system
 * Security: Only accessible from localhost and password protected
 */

// Security: Only allow localhost access
$allowedIPs = ['127.0.0.1', '::1', 'localhost'];
$clientIP = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'unknown';

if (!in_array($clientIP, $allowedIPs) && $clientIP !== gethostbyname(gethostname())) {
    http_response_code(403);
    die('Access denied. This tool is only accessible from localhost.');
}

// Master password for this tool (loaded from environment)
define('LMS_ACCESS', true);

// Load configuration
require_once 'includes/EnvLoader.php';
EnvLoader::load();
include 'config/config.php';

// Get admin creation password from environment
$adminCreationPassword = $_ENV['ADMIN_CREATION_PASSWORD'] ?? null;

if (empty($adminCreationPassword)) {
    http_response_code(500);
    die('Admin creation tool is not properly configured. Please set ADMIN_CREATION_PASSWORD in your .env file.');
}

$error = '';
$success = '';
$step = 'auth'; // Steps: auth, create, complete

// Handle authentication
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'authenticate') {
        $masterPassword = $_POST['master_password'] ?? '';
        
        if ($masterPassword === $adminCreationPassword) {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['admin_creation_authenticated'] = true;
            $_SESSION['admin_creation_time'] = time();
            $step = 'create';
        } else {
            $error = 'Invalid master password. Access denied.';
        }
    } elseif ($_POST['action'] === 'quit') {
        // Handle quit action - clear session and return to auth
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['admin_creation_authenticated']);
        unset($_SESSION['admin_creation_time']);
        $step = 'auth';
        $success = 'Session cleared. Please re-authenticate to continue.';
    } elseif ($_POST['action'] === 'create_admin') {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check authentication and session timeout (30 minutes)
        if (!isset($_SESSION['admin_creation_authenticated']) || 
            !$_SESSION['admin_creation_authenticated'] || 
            (time() - $_SESSION['admin_creation_time']) > 1800) {
            $error = 'Session expired. Please authenticate again.';
            $step = 'auth';
            unset($_SESSION['admin_creation_authenticated']);
        } else {
            // Process admin creation
            $firstName = sanitize_input($_POST['first_name']);
            $lastName = sanitize_input($_POST['last_name']);
            $email = sanitize_input($_POST['email']);
            $username = sanitize_input($_POST['username']);
            $password = $_POST['password'];
            $confirmPassword = $_POST['confirm_password'];
            
            // Validation
            if (empty($firstName) || empty($lastName) || empty($email) || empty($username) || empty($password)) {
                $error = 'Please fill in all required fields.';
                $step = 'create';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
                $step = 'create';
            } elseif (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters long.';
                $step = 'create';
            } elseif ($password !== $confirmPassword) {
                $error = 'Passwords do not match.';
                $step = 'create';
            } else {
                try {
                    $db = Database::getInstance()->getConnection();
                    
                    // Check if admin already exists
                    $stmt = $db->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
                    $stmt->execute();
                    if ($stmt->fetch()) {
                        $error = 'An admin user already exists in the system.';
                        $step = 'create';
                    } else {
                        // Check if username or email exists
                        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                        $stmt->execute([$username, $email]);
                        if ($stmt->fetch()) {
                            $error = 'Username or email already exists.';
                            $step = 'create';
                        } else {
                            // Create admin user
                            $userId = 'ADM' . time() . rand(100, 999);
                            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                            
                            $stmt = $db->prepare("
                                INSERT INTO users 
                                (user_id, username, email, password_hash, first_name, last_name, 
                                 role, email_verified, created_at, status)
                                VALUES (?, ?, ?, ?, ?, ?, 'admin', TRUE, NOW(), 'active')
                            ");
                            $stmt->execute([
                                $userId, $username, $email, $passwordHash, $firstName, $lastName
                            ]);
                            
                            if ($stmt->rowCount() > 0) {
                                $newAdminId = $db->lastInsertId();
                                
                                // Log the admin creation
                                $stmt = $db->prepare("
                                    INSERT INTO activity_logs 
                                    (user_id, action, entity_type, entity_id, description, ip_address, user_agent, created_at)
                                    VALUES (?, 'admin_created', 'user', ?, 'Admin user created via creation tool', ?, ?, NOW())
                                ");
                                $stmt->execute([
                                    $newAdminId,
                                    $newAdminId,
                                    $clientIP,
                                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                                ]);
                                
                                $success = "Admin user created successfully!";
                                $step = 'complete';
                                
                                // Clear session
                                unset($_SESSION['admin_creation_authenticated']);
                                unset($_SESSION['admin_creation_time']);
                                
                            } else {
                                $error = 'Failed to create admin user. Please try again.';
                                $step = 'create';
                            }
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Database error: ' . $e->getMessage();
                    $step = 'create';
                }
            }
        }
    }
} else {
    // Check if already authenticated
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['admin_creation_authenticated']) && 
        $_SESSION['admin_creation_authenticated'] && 
        (time() - $_SESSION['admin_creation_time']) <= 1800) {
        $step = 'create';
    }
}

// ... existing code ...

$pageTitle = 'Admin Creation Tool';
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
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            min-height: 100vh;
            padding: 2rem 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        .admin-container {
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .admin-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            padding: 2rem;
            text-align: center;
            border: 2px solid #e74c3c;
        }

        .security-warning {
            background: #e74c3c;
            color: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .security-warning i {
            font-size: 1.2rem;
        }

        .admin-header {
            margin-bottom: 2rem;
        }

        .admin-header .logo {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 1.5rem;
            font-weight: bold;
            color: #e74c3c;
            margin-bottom: 1rem;
        }

        .admin-header .logo i {
            font-size: 1.8rem;
        }

        .admin-header h1 {
            font-size: 1.5rem;
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .admin-header p {
            color: #7f8c8d;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.9rem;
        }

        .form-group label .required {
            color: #e74c3c;
        }

        .form-control {
            width: 100%;
            padding: 0.85rem;
            border: 2px solid #bdc3c7;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #ffffff;
            box-sizing: border-box;
            position: relative;
            z-index: 1;
        }

        .form-control:focus {
            outline: none;
            border-color: #e74c3c;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
        }

        .input-group {
            position: relative;
            z-index: 1;
        }

        .input-group i {
            position: absolute;
            left: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
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
            color: #7f8c8d;
            cursor: pointer;
            font-size: 0.9rem;
            transition: color 0.3s ease;
            z-index: 10;
            pointer-events: auto;
        }

        .password-toggle:hover {
            color: #e74c3c;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            line-height: 1.4;
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
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: #ffffff;
        }

        .btn-danger:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: #ffffff;
        }

        .btn-success:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(39, 174, 96, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            color: #ffffff;
        }

        .btn-secondary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(149, 165, 166, 0.4);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .info-box {
            background: #ecf0f1;
            border-left: 4px solid #3498db;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .info-box h4 {
            margin: 0 0 0.5rem 0;
            color: #2c3e50;
            font-size: 0.95rem;
        }

        .info-box p {
            margin: 0;
            color: #7f8c8d;
            font-size: 0.85rem;
            line-height: 1.4;
        }

        .help-text {
            font-size: 0.8rem;
            color: #7f8c8d;
            text-align: left;
            margin-top: 0.3rem;
        }

        .completion-info {
            background: #d4edda;
            border: 2px solid #27ae60;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .completion-info h3 {
            color: #155724;
            margin: 0 0 1rem 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .completion-info ul {
            text-align: left;
            color: #155724;
            margin: 0;
            padding-left: 1.5rem;
        }

        .completion-info li {
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .admin-container {
                padding: 0 1rem;
            }
            
            .admin-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-card">
            <!-- Security Warning -->
            <div class="security-warning">
                <i class="fas fa-shield-alt"></i>
                <div>
                    <strong>SECURE ADMIN TOOL</strong><br>
                    Localhost only • Password protected • Single use
                </div>
            </div>

            <!-- Header -->
            <div class="admin-header">
                <div class="logo">
                    <i class="fas fa-user-shield"></i>
                    Admin Creation
                </div>
                <h1>LMS Administrator Setup</h1>
                <p>Create the initial administrator account for your Library Management System</p>
            </div>

            <?php if ($step === 'auth'): ?>
                <!-- Authentication Step -->
                <div class="info-box">
                    <h4><i class="fas fa-info-circle"></i> Security Notice</h4>
                    <p>This tool is protected by a master password. Enter the password to proceed with admin creation.</p>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="create-admin.php">
                    <input type="hidden" name="action" value="authenticate">
                    
                    <div class="form-group">
                        <label for="master_password">Master Password <span class="required">*</span></label>
                        <div class="input-group">
                            <i class="fas fa-key"></i>
                            <input type="password" 
                                   id="master_password" 
                                   name="master_password" 
                                   class="form-control" 
                                   placeholder="Enter master password"
                                   required>
                            <span class="fas fa-eye password-toggle" 
                                  onclick="togglePassword('master_password', this)"
                                  title="Show/Hide Password"></span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-unlock"></i>
                        Authenticate
                    </button>
                </form>

            <?php elseif ($step === 'create'): ?>
                <!-- Admin Creation Step -->
                <div class="info-box">
                    <h4><i class="fas fa-user-plus"></i> Create Administrator</h4>
                    <p>Enter the details for the system administrator. This will be the primary admin account with full system access.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="create-admin.php">
                    <input type="hidden" name="action" value="create_admin">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" 
                                       id="first_name" 
                                       name="first_name" 
                                       class="form-control" 
                                       placeholder="John"
                                       value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                                       required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="last_name">Last Name <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" 
                                       id="last_name" 
                                       name="last_name" 
                                       class="form-control" 
                                       placeholder="Doe"
                                       value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                                       required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <div class="input-group">
                            <i class="fas fa-envelope"></i>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   class="form-control" 
                                   placeholder="admin@library.com"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   required>
                        </div>
                        <div class="help-text">This will be used for system notifications and recovery</div>
                    </div>

                    <div class="form-group">
                        <label for="username">Username <span class="required">*</span></label>
                        <div class="input-group">
                            <i class="fas fa-at"></i>
                            <input type="text" 
                                   id="username" 
                                   name="username" 
                                   class="form-control" 
                                   placeholder="admin"
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                   required>
                        </div>
                        <div class="help-text">This will be used to login to the admin panel</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Password <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" 
                                       id="password" 
                                       name="password" 
                                       class="form-control" 
                                       placeholder="Password"
                                       required>
                                <span class="fas fa-eye password-toggle" 
                                      onclick="togglePassword('password', this)"
                                      title="Show/Hide Password"></span>
                            </div>
                            <div class="help-text">Minimum 8 characters</div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       class="form-control" 
                                       placeholder="Confirm Password"
                                       required>
                                <span class="fas fa-eye password-toggle" 
                                      onclick="togglePassword('confirm_password', this)"
                                      title="Show/Hide Password"></span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-user-plus"></i>
                        Create Administrator
                    </button>
                </form>

                <!-- Quit Button -->
                <form method="POST" action="create-admin.php" style="margin-top: 1rem;">
                    <input type="hidden" name="action" value="quit">
                    <button type="submit" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Quit & Re-authenticate
                    </button>
                </form>

            <?php elseif ($step === 'complete'): ?>
                <!-- Completion Step -->
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <div class="completion-info">
                    <h3>
                        <i class="fas fa-check-circle"></i>
                        Setup Complete!
                    </h3>
                    <ul>
                        <li>Administrator account has been created successfully</li>
                        <li>The admin can now login to the system</li>
                        <li>This creation tool is now disabled</li>
                        <li>All activity has been logged for security</li>
                    </ul>
                </div>

                <div style="margin-top: 1.5rem; padding: 1rem; background: #f8f9fa; border-radius: 6px; text-align: left;">
                    <p style="margin: 0; font-size: 0.85rem; color: #6c757d;">
                        <strong>Security Note:</strong> For security reasons, consider deleting this file (<code>create-admin.php</code>) 
                        after admin creation is complete.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function togglePassword(fieldId, toggleElement) {
            const passwordInput = document.getElementById(fieldId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleElement.classList.remove('fa-eye');
                toggleElement.classList.add('fa-eye-slash');
                toggleElement.title = 'Hide Password';
            } else {
                passwordInput.type = 'password';
                toggleElement.classList.remove('fa-eye-slash');
                toggleElement.classList.add('fa-eye');
                toggleElement.title = 'Show Password';
            }
        }

        // Auto-focus on first input
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('input[type="password"], input[type="text"], input[type="email"]');
            if (firstInput) {
                firstInput.focus();
            }
        });
    </script>
</body>
</html>