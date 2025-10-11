<?php
define('LMS_ACCESS', true);

// Load configuration
require_once 'includes/EnvLoader.php';
EnvLoader::load();
include 'config/config.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get token from URL
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

// Validate token
if (empty($token)) {
    $error = "Invalid password reset link.";
} else {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if token exists and is not expired
        $stmt = $db->prepare("
            SELECT user_id, first_name, email 
            FROM users 
            WHERE password_reset_token = ? 
            AND password_reset_expires > NOW()
            AND status != 'pending'
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error = "Invalid or expired password reset link.";
        }
    } catch (Exception $e) {
        $error = "An error occurred. Please try again.";
    }
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    try {
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Validate passwords
        if (empty($password) || empty($confirmPassword)) {
            $error = "Please fill in all fields.";
        } elseif ($password !== $confirmPassword) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
            $error = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long.";
        } else {
            // Hash password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            // Update user password and clear reset token
            $stmt = $db->prepare("
                UPDATE users 
                SET password_hash = ?, 
                    password_reset_token = NULL, 
                    password_reset_expires = NULL,
                    updated_at = NOW()
                WHERE password_reset_token = ?
            ");
            $result = $stmt->execute([$passwordHash, $token]);
            
            if ($result) {
                $success = "Password reset successfully. You can now login with your new password.";
                // Clear token so it can't be used again
                $token = '';
            } else {
                $error = "Failed to reset password. Please try again.";
            }
        }
    } catch (Exception $e) {
        $error = "An error occurred. Please try again.";
    }
}

$pageTitle = 'Reset Password';
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
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .reset-container {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
            width: 100%;
            max-width: 500px;
        }
        
        .reset-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .reset-header h1 {
            color: #495057;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .reset-header p {
            color: #6c757d;
            font-size: 1.1rem;
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
            gap: 8px;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            justify-content: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 8px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .login-link a {
            color: #3498DB;
            text-decoration: none;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <h1><i class="fas fa-key"></i> Reset Password</h1>
            <p>Enter your new password below</p>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
            <div class="login-link">
                <a href="login.php">Go to Login</a>
            </div>
        <?php elseif (isset($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <div class="login-link">
                <a href="login.php">Return to Login</a>
            </div>
        <?php elseif ($user): ?>
            <form method="POST">
                <input type="hidden" name="reset_password" value="1">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Reset Password
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>