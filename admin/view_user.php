<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has admin role
if (!is_logged_in() || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Get user ID from URL parameter
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($userId <= 0) {
    header('Location: users.php');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Get user details with library information
    $stmt = $db->prepare("
        SELECT u.*, l.library_name 
        FROM users u 
        LEFT JOIN libraries l ON u.library_id = l.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header('Location: users.php');
        exit;
    }
    
    // Get creator information if available
    $creator = null;
    if ($user['created_by']) {
        $stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$user['created_by']]);
        $creator = $stmt->fetch();
    }
    
} catch (Exception $e) {
    die('Error loading user: ' . $e->getMessage());
}

$pageTitle = 'View User';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?> - LMS</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body {
            margin: 0;
            padding-top: 60px; /* Space for navbar - this is essential */
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; /* Font for navbar */
            background: #f8f9fa;
            color: #495057;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            color: #2c3e50;
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .content-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 25px;
            margin-bottom: 2rem;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .card-header h2 {
            color: #212529;
            margin: 0;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(0, 102, 204, 0.2);
        }
        
        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 102, 204, 0.3);
            transition: all 0.3s ease;
        }
        
        .user-details-layout {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }
        
        .profile-section {
            text-align: center;
        }
        
        .profile-image-large {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border-radius: 12px;
            background: #0066cc; /* Deeper blue background like navbar */
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            font-weight: bold;
            font-size: 4rem;
        }
        
        .profile-image-large i {
            font-size: 4rem;
            color: white;
        }
        
        .user-information-container {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .information-group {
            margin-bottom: 1rem;
        }
        
        .information-group h3 {
            color: #343a40;
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 0.5rem;
        }
        
        .information-row {
            display: flex;
            margin-bottom: 0.75rem;
        }
        
        .information-label {
            font-weight: 600;
            width: 150px;
            color: #495057;
        }
        
        .information-value {
            flex: 1;
            color: #212529;
        }
        
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-active {
            background-color: #1b5e20; /* Deep green */
            color: white;
        }
        
        .status-inactive {
            background-color: #dee2e6;
            color: #495057;
        }
        
        .status-pending {
            background-color: #F39C12;
            color: white;
        }
        
        .status-suspended {
            background-color: #c62828; /* Deep red */
            color: white;
        }
        
        .role-indicator {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .role-indicator.admin {
            background-color: #6f42c1;
            color: white;
        }
        
        .role-indicator.supervisor {
            background-color: #17a2b8;
            color: white;
        }
        
        .role-indicator.librarian {
            background-color: #28a745;
            color: white;
        }
        
        .role-indicator.member {
            background-color: #007bff;
            color: white;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .user-details-layout {
                grid-template-columns: 1fr;
            }
            
            .information-row {
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .information-label {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/admin_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-user"></i> <?php echo $pageTitle; ?></h1>
        </div>
        
        <div class="content-card">
            <div class="card-header">
                <h2><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                <a href="users.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Users
                </a>
            </div>
            
            <div class="user-details-layout">
                <div class="profile-section">
                    <div class="profile-image-large">
                        <?php if (!empty($user['profile_image']) && file_exists('../' . $user['profile_image'])): ?>
                            <img src="../<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                 alt="User profile" 
                                 style="width: 100%; height: 100%; object-fit: cover; border-radius: 12px;">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <?php 
                                $initials = '';
                                $nameParts = explode(' ', $user['first_name'] . ' ' . $user['last_name']);
                                foreach (array_slice($nameParts, 0, 2) as $part) {
                                    $initials .= strtoupper(substr($part, 0, 1));
                                }
                                echo $initials;
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <h3><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                </div>
                
                <div class="user-information-container">
                    <div class="information-group">
                        <h3>Basic Information</h3>
                        <div class="information-row">
                            <div class="information-label">User ID:</div>
                            <div class="information-value"><?php echo htmlspecialchars($user['user_id']); ?></div>
                        </div>
                        <div class="information-row">
                            <div class="information-label">Username:</div>
                            <div class="information-value"><?php echo htmlspecialchars($user['username']); ?></div>
                        </div>
                        <div class="information-row">
                            <div class="information-label">Full Name:</div>
                            <div class="information-value"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        </div>
                        <div class="information-row">
                            <div class="information-label">Role:</div>
                            <div class="information-value">
                                <span class="role-indicator <?php echo $user['role']; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="information-row">
                            <div class="information-label">Status:</div>
                            <div class="information-value">
                                <span class="status-indicator status-<?php echo $user['status']; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="information-group">
                        <h3>Contact Information</h3>
                        <div class="information-row">
                            <div class="information-label">Email:</div>
                            <div class="information-value"><?php echo htmlspecialchars($user['email']); ?></div>
                        </div>
                        <div class="information-row">
                            <div class="information-label">Phone:</div>
                            <div class="information-value"><?php echo !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'N/A'; ?></div>
                        </div>
                        <div class="information-row">
                            <div class="information-label">Address:</div>
                            <div class="information-value"><?php echo !empty($user['address']) ? htmlspecialchars($user['address']) : 'N/A'; ?></div>
                        </div>
                    </div>
                    
                    <div class="information-group">
                        <h3>Additional Information</h3>
                        <div class="information-row">
                            <div class="information-label">Library:</div>
                            <div class="information-value"><?php echo !empty($user['library_name']) ? htmlspecialchars($user['library_name']) : 'N/A'; ?></div>
                        </div>
                        <div class="information-row">
                            <div class="information-label">Created:</div>
                            <div class="information-value"><?php echo date('M j, Y g:i A', strtotime($user['created_at'])); ?></div>
                        </div>
                        <div class="information-row">
                            <div class="information-label">Last Updated:</div>
                            <div class="information-value"><?php echo date('M j, Y g:i A', strtotime($user['updated_at'])); ?></div>
                        </div>
                        <div class="information-row">
                            <div class="information-label">Last Login:</div>
                            <div class="information-value"><?php echo !empty($user['last_login']) ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?></div>
                        </div>
                        <?php if ($creator): ?>
                        <div class="information-row">
                            <div class="information-label">Created By:</div>
                            <div class="information-value"><?php echo htmlspecialchars($creator['first_name'] . ' ' . $creator['last_name']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>