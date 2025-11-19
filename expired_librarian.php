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

// Check if user is logged in and has librarian role
if (!is_logged_in() || $_SESSION['user_role'] !== 'librarian') {
    header('Location: login.php');
    exit;
}

// Get user information
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Librarian';
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

        /* Custom Navbar - Minimal version without nav links */
        .expired-navbar {
            background: #f0e6f5; /* Light purple background */
            color: #333;
            padding: 0.4rem 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            border-bottom: 1px solid #d0bce0;
            margin: 0;
            font-size: 0.9rem;
            box-sizing: border-box; /* Include padding in width calculation */
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .navbar-brand span {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 1.2rem;
            font-weight: 700;
            color: #8e44ad;
            text-decoration: none;
        }
        
        .navbar-brand i {
            color: #8e44ad;
            font-size: 1.3rem;
        }
        
        .navbar-user {
            position: relative;
            display: flex;
            align-items: center;
            min-width: 150px; /* Ensure enough space for user info */
            justify-content: flex-end; /* Align to the right */
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            white-space: nowrap; /* Prevent text wrapping */
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #8e44ad;
            color: white;
            font-weight: bold;
            font-size: 0.9rem;
            border: 2px solid #8e44ad;
            box-shadow: 0 1px 4px rgba(0,0,0,0.2);
        }
        
        .avatar-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
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
            font-weight: 600;
            font-size: 0.8rem;
            color: #333;
        }
        
        .user-role {
            font-size: 0.65rem;
            opacity: 0.7;
            color: #666;
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
            background: linear-gradient(135deg, #c62828 0%, #b71c1c 100%); /* Deep red */
            color: #ffffff;
        }
        
        .btn:hover {
            transform: translateY(-2px);
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
                    <span class="user-role">Librarian</span>
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
            <p>Your library subscription has expired. Access to library management features is currently restricted.</p>
            <p>Access will be restored when your library renews their subscription.</p>
        </div>
        
        <div class="warning-box">
            <h3><i class="fas fa-info-circle"></i> What happens next?</h3>
            <p>You will not be able to manage library resources, books, or user accounts until your subscription is renewed.</p>
        </div>
        
        <a href="logout.php" class="btn">
            <i class="fas fa-sign-out-alt"></i>
            Logout
        </a>
    </div>
</body>
</html>