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

// Check if user is logged in and has supervisor role
if (!is_logged_in() || $_SESSION['user_role'] !== 'supervisor') {
    header('Location: login.php');
    exit;
}

// Get user information
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Supervisor';
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';

// Get user initials for profile placeholder
$nameParts = explode(' ', $userName);
$initials = '';
foreach (array_slice($nameParts, 0, 2) as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}

$pageTitle = 'Subscription Expired';
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
        /* Custom Expired Page Styles */
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 60px;
            overflow: hidden; /* Prevent vertical scrolling */
        }

        /* Custom Navbar - Match supervisor navbar styling */
        .expired-navbar {
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            color: #f8f9fa;
            padding: 0.5rem 1rem; /* Match supervisor navbar padding */
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); /* Match supervisor navbar shadow */
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            margin: 0;
            box-sizing: border-box; /* Include padding in width calculation */
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 8px; /* Match supervisor navbar gap */
        }
        
        .navbar-brand span {
            display: flex;
            align-items: center;
            gap: 8px; /* Match supervisor navbar gap */
            font-size: 1.5rem; /* Match supervisor navbar font size */
            font-weight: bold; /* Match supervisor navbar font weight */
            color: #f8f9fa;
            text-decoration: none;
        }
        
        .navbar-brand i {
            color: #ecf0f1; /* Match supervisor navbar icon color */
            font-size: 1.8rem; /* Match supervisor navbar icon size */
        }
        
        .navbar-user {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 8px; /* Match supervisor navbar gap */
            cursor: pointer;
            padding: 0.4rem; /* Match supervisor navbar padding */
            border-radius: 6px; /* Match supervisor navbar border radius */
            transition: background-color 0.3s ease;
        }
        
        .user-info:hover {
            background-color: rgba(248, 249, 250, 0.1); /* Match supervisor navbar hover */
        }
        
        .user-avatar {
            width: 35px; /* Match supervisor navbar avatar size */
            height: 35px; /* Match supervisor navbar avatar size */
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #ffffff; /* Match supervisor navbar avatar background */
            color: #3498DB; /* Match supervisor navbar avatar color */
            font-weight: bold;
            font-size: 0.9rem; /* Match supervisor navbar avatar font size */
        }
        
        .avatar-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            background-color: #ffffff; /* Match supervisor navbar placeholder background */
            color: #3498DB; /* Match supervisor navbar placeholder color */
            font-weight: bold;
            font-size: 0.9rem; /* Match supervisor navbar placeholder font size */
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
            text-align: right;
        }
        
        .user-name {
            font-weight: 500; /* Match supervisor navbar user name weight */
            font-size: 0.85rem; /* Match supervisor navbar user name size */
            color: #f8f9fa;
        }
        
        .user-role {
            font-size: 0.7rem; /* Match supervisor navbar user role size */
            opacity: 0.8;
            color: #f8f9fa;
        }
        
        /* Main Content */
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
            max-height: calc(100vh - 120px); /* Prevent overflow */
            overflow-y: auto; /* Allow scrolling only if content exceeds */
        }
        
        .header-icon {
            font-size: 4rem;
            color: #c62828; /* Deep red for error state */
            margin-bottom: 1rem;
        }
        
        h1 {
            color: #495057;
            margin-bottom: 1rem;
        }
        
        .message {
            color: #6c757d;
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 2rem;
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
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); /* Blue for primary action */
            color: #ffffff;
            margin: 0.5rem;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #c62828 0%, #b71c1c 100%); /* Deep red for logout */
        }
        
        .btn-secondary:hover {
            box-shadow: 0 6px 20px rgba(198, 40, 40, 0.3);
        }
        
        .btn i {
            font-size: 0.9rem;
        }
        
        .warning-box {
            background: #fff8e1;
            border-left: 4px solid #ffc107;
            padding: 1rem;
            border-radius: 4px;
            margin: 1.5rem 0;
            text-align: left;
        }
        
        .warning-box h3 {
            margin-top: 0;
            color: #856404;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .warning-box p {
            margin-bottom: 0;
            color: #856404;
        }
    </style>
</head>
<body>
    <!-- Custom Navbar without navigation links -->
    <nav class="expired-navbar">
        <div class="navbar-brand">
            <span>
                <i class="fas fa-book-open"></i>
                <span>LMS</span>
            </span>
        </div>
        
        <div class="navbar-user">
            <div class="user-info">
                <div class="user-avatar">
                    <?php if (isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])): ?>
                        <?php if (file_exists($_SESSION['profile_image'])): ?>
                            <img src="<?php echo htmlspecialchars($_SESSION['profile_image']); ?>" alt="Profile">
                        <?php else: ?>
                            <div class="avatar-placeholder"><?php echo $initials; ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="avatar-placeholder"><?php echo $initials; ?></div>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
                    <span class="user-role">Supervisor</span>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="container">
        <div class="header-icon">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        
        <h1>Subscription Expired</h1>
        
        <div class="message">
            <p>Your library subscription has expired. As a supervisor, you can renew the subscription to restore access for all library users.</p>
        </div>
        
        <div class="warning-box">
            <h3><i class="fas fa-info-circle"></i> What happens next?</h3>
            <p>Until the subscription is renewed, library members and librarians will have restricted access to system features.</p>
        </div>
        
        <div style="margin: 2rem 0;">
            <a href="supervisor/subscription.php" class="btn">
                <i class="fas fa-credit-card"></i>
                Renew Subscription
            </a>
            <a href="logout.php" class="btn btn-secondary">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>
</body>
</html>