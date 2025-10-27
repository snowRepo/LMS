<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';
require_once '../includes/SubscriptionManager.php';
require_once '../includes/EmailService.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has supervisor role
if (!is_logged_in() || $_SESSION['user_role'] !== 'supervisor') {
    header('Location: ../login.php');
    exit;
}

// Check subscription status
$subscriptionManager = new SubscriptionManager();
$libraryId = $_SESSION['library_id'];
$hasActiveSubscription = $subscriptionManager->hasActiveSubscription($libraryId);

if (!$hasActiveSubscription) {
    header('Location: ../subscription.php');
    exit;
}

// Get library information
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM libraries WHERE id = ?");
    $stmt->execute([$libraryId]);
    $libraryInfo = $stmt->fetch();
} catch (Exception $e) {
    $libraryInfo = null;
}

// Handle adding a new librarian
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_librarian') {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Get form data
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $username = trim($_POST['username']);
        $address = trim($_POST['address']);
        
        // Validate required fields
        if (empty($firstName) || empty($lastName) || empty($email) || empty($username)) {
            $error = "Please fill in all required fields.";
            header('Location: librarians.php?error=' . urlencode($error));
            exit;
        } else {
            // Check if email or username already exists
            $stmt = $db->prepare("SELECT id, role FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);
            $existingUser = $stmt->fetch();
            
            if ($existingUser) {
                if ($existingUser['email'] === $email) {
                    $error = "A user with this email address already exists. Please use a different email address.";
                } elseif ($existingUser['username'] === $username) {
                    $error = "A user with this username already exists. Please choose a different username.";
                } else {
                    $error = "A user with this email or username already exists. Please use different credentials.";
                }
                
                // Add specific information about the existing user
                if ($existingUser['role'] !== 'librarian') {
                    $error .= " Note: The email address is already used by a " . $existingUser['role'] . " account.";
                }
                
                header('Location: librarians.php?error=' . urlencode($error));
                exit;
            }
            
            // Generate a temporary password (will be replaced when librarian sets their own)
            $tempPassword = bin2hex(random_bytes(16));
            $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
            
            // Generate email verification token
            $verificationToken = bin2hex(random_bytes(32));
            
            // Insert librarian with pending status
            $stmt = $db->prepare("
                INSERT INTO users 
                (user_id, username, email, password_hash, first_name, last_name, phone, address, role, library_id, created_by, status, email_verification_token)
                VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, 'librarian', ?, ?, 'pending', ?)
            ");
            
            $userId = 'LIB' . time() . rand(100, 999);
            
            // Get the integer ID of the current user for created_by field
            $createdBy = $_SESSION['user_id'];
            
            if (!$createdBy) {
                header('Location: librarians.php?error=' . urlencode("Unable to identify the current user. Please try again."));
                exit;
            }
            
            $result = $stmt->execute([
                $userId, 
                $username, 
                $email, 
                $passwordHash, 
                $firstName, 
                $lastName, 
                $phone, 
                $address, 
                $libraryId, 
                $createdBy, 
                $verificationToken
            ]);
            
            if ($result) {
                // Send email to librarian
                $emailService = new EmailService();
                
                // Check if email service is configured
                if (!$emailService->isConfigured()) {
                    header('Location: librarians.php?success=' . urlencode("Librarian added successfully, but email service is not configured. Please contact the librarian directly with their setup link."));
                    exit;
                }
                
                $verificationLink = APP_URL . "/librarian-setup.php?token=" . $verificationToken;
                
                $emailData = [
                    'first_name' => $firstName,
                    'library_name' => $libraryInfo['library_name'] ?? 'Your Library',
                    'verification_link' => $verificationLink
                ];
                
                $emailSent = $emailService->sendLibrarianSetupEmail($email, $emailData);
                
                if ($emailSent) {
                    header('Location: librarians.php?success=' . urlencode("Librarian added successfully. An email has been sent to {$email} for them to set up their account."));
                    exit;
                } else {
                    header('Location: librarians.php?success=' . urlencode("Librarian added successfully, but there was an issue sending the email. Please contact the librarian directly."));
                    exit;
                }
            } else {
                // Get the error info for debugging
                $errorInfo = $stmt->errorInfo();
                $errorMessage = "Failed to add librarian. Database error: " . ($errorInfo[2] ?? 'Unknown error');
                header('Location: librarians.php?error=' . urlencode($errorMessage));
                exit;
            }
        }
    } catch (Exception $e) {
        header('Location: librarians.php?error=' . urlencode("An error occurred: " . $e->getMessage()));
        exit;
    }
}

// Handle search and pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Librarians per page
$offset = ($page - 1) * $limit;

try {
    $db = Database::getInstance()->getConnection();
    
    // Get total librarians count for pagination
    if (!empty($search)) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM users u 
            WHERE u.library_id = ? 
            AND u.role = 'librarian'
            AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ? OR u.phone LIKE ?)
        ");
        $searchTerm = "%$search%";
        $stmt->execute([$libraryId, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE library_id = ? AND role = 'librarian'");
        $stmt->execute([$libraryId]);
    }
    $totalLibrarians = $stmt->fetch()['total'];
    $totalPages = ceil($totalLibrarians / $limit);
    
    // Get librarians for current page
    if (!empty($search)) {
        $stmt = $db->prepare("
            SELECT u.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as full_name,
                   u.profile_image
            FROM users u
            WHERE u.library_id = ? 
            AND u.role = 'librarian'
            AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ? OR u.phone LIKE ?)
            ORDER BY u.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $searchTerm = "%$search%";
        $stmt->execute([$libraryId, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit, $offset]);
    } else {
        $stmt = $db->prepare("
            SELECT u.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as full_name,
                   u.profile_image
            FROM users u
            WHERE u.library_id = ? 
            AND u.role = 'librarian'
            ORDER BY u.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$libraryId, $limit, $offset]);
    }
    $librarians = $stmt->fetchAll();
    
} catch (Exception $e) {
    die('Error loading librarians: ' . $e->getMessage());
}

$pageTitle = 'Librarians';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - LMS</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Supervisor Navbar CSS -->
    <link rel="stylesheet" href="css/supervisor_navbar.css">
    <!-- Toast CSS -->
    <link rel="stylesheet" href="css/toast.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 0; /* Remove padding to ensure navbar is at the very top */
        }
        
        /* Ensure no default margin on body */
        html, body {
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-header h1 {
            color: #212529;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #6c757d;
            font-size: 1.1rem;
        }

        .content-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
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

        .search-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            max-width: 600px;
            margin: 0 auto 1.5rem auto; /* Center the search bar */
            justify-content: center; /* Center content */
        }

        .search-input {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 1rem;
            max-width: 400px;
        }

        .search-btn, .btn {
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
        }

        .search-btn {
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%); /* Primary color */
            color: white;
            padding: 0.75rem 1rem; /* Adjust padding for icon only */
        }

        .search-btn:hover {
            background: linear-gradient(135deg, #2980B9 0%, #2573A7 100%); /* Darker primary color on hover */
        }

        .search-btn-text {
            display: none; /* Hide text to show only icon */
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a,
        .pagination span {
            padding: 0.5rem 0.75rem;
            border: 1px solid #dee2e6;
            text-decoration: none;
            color: #495057;
            border-radius: 4px;
        }

        .pagination a:hover {
            background-color: #e9ecef;
        }

        .pagination .current {
            background-color: #3498DB;
            color: white;
            border-color: #3498DB;
        }

        .librarians-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
        }

        .librarian-card {
            background: #ffffff;
            border-radius: 15px;
            padding: 1.75rem;
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 1px solid #e0e0e0;
            position: relative;
            overflow: hidden;
        }

        .librarian-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
            border-color: #3498DB;
        }

        .librarian-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3498DB, #2980B9);
        }

        .librarian-header {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
            padding-bottom: 1.25rem;
            border-bottom: 1px solid #e9ecef;
        }

        .librarian-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
            box-shadow: 0 6px 15px rgba(52, 152, 219, 0.3);
            border: 3px solid white;
        }

        .librarian-info h3 {
            margin: 0 0 0.3rem 0;
            color: #212529;
            font-size: 1.4rem;
            font-weight: 600;
        }

        .librarian-info p {
            margin: 0;
            color: #7f8c8d;
            font-size: 1rem;
            font-weight: 500;
        }

        .librarian-details {
            margin: 1.25rem 0;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.85rem;
            padding-bottom: 0.85rem;
            border-bottom: 1px solid #f1f3f5;
        }

        .detail-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .detail-label {
            color: #7f8c8d;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .detail-value {
            color: #2c3e50;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-align: center;
        }

        .status-active {
            background-color: #006400;
            color: white;
        }

        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-suspended {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-pending {
            background-color: #cce7ff;
            color: #004085;
        }

        .librarian-actions {
            display: flex;
            gap: 0.85rem;
            margin-top: 1.25rem;
            padding-top: 1.25rem;
            border-top: 1px solid #e9ecef;
        }

        .action-btn {
            flex: 1;
            padding: 0.7rem;
            border: none;
            border-radius: 8px;
            background: #f8f9fa;
            color: #495057;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            font-size: 0.9rem;
            font-weight: 500;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .action-btn:hover {
            background: #3498DB;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.2);
        }

        .action-btn.resend {
            background: #d4edda;
            color: #155724;
        }

        .action-btn.resend:hover {
            background: #28a745;
            color: white;
        }

        .action-btn.delete-btn {
            background: #f5c6cb;
            color: #721c24;
        }

        .action-btn.delete-btn:hover {
            background: #dc3545;
            color: white;
        }

        .action-btn.edit-btn {
            background: #fff3cd;
            color: #856404;
        }

        .action-btn.edit-btn:hover {
            background: #ffc107;
            color: #212529;
        }

        .action-btn.view-btn {
            background: #b8daff;
            color: #004085;
        }

        .action-btn.view-btn:hover {
            background: #3498DB;
            color: white;
        }

        .action-btn.reset-password {
            background: #bee5eb;
            color: #0c5460;
        }

        .action-btn.reset-password:hover {
            background: #17a2b8;
            color: white;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow: auto;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 2% auto;
            padding: 0;
            border: none;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, #3498DB, #2980B9);
            color: white;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
        }

        .close {
            color: white;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
        }

        .close:hover {
            opacity: 0.8;
        }

        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            flex: 1;
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
            /* Add padding to the right for dropdown icons */
            padding-right: 2.5rem;
        }
        
        /* Remove padding-right for non-select elements */
        input.form-control,
        textarea.form-control {
            padding-right: 0.75rem;
        }

        
        .form-control:focus {
            border-color: #3498DB;
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        /* Specific styling for select elements */
        select.form-control {
            padding-right: 2.5rem;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1rem;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            background-color: #f8f9fa;
            border-radius: 0 0 12px 12px;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        /* Alert styles */
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

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 5% auto;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .librarians-grid {
                grid-template-columns: 1fr;
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
            margin-right: 8px;
            vertical-align: middle;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <?php include 'includes/supervisor_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-user-tie"></i> Librarians</h1>
            <p>Manage library staff members</p>
        </div>

        <!-- Toast Container -->
        <div id="toast-container"></div>



        <div class="content-card">
            <div class="card-header">
                <h2>Librarian Team</h2>
                <button class="btn btn-primary" id="addLibrarianBtn">
                    <i class="fas fa-plus"></i>
                    Add New Librarian
                </button>
            </div>
            
            <!-- Search Bar -->
            <div class="search-bar">
                <form method="GET" style="display: flex; width: 100%; max-width: 500px; gap: 1rem;">
                    <input type="text" 
                           name="search" 
                           class="search-input" 
                           placeholder="Search by name, email, username, or phone..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                        <span class="search-btn-text">Search</span>
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="librarians.php" class="btn btn-secondary" style="padding: 0.75rem 1rem;">
                            <i class="fas fa-times"></i>
                            <span class="search-btn-text">Clear</span>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="librarians-grid">
                <?php if (count($librarians) > 0): ?>
                    <?php foreach ($librarians as $librarian): ?>
                        <div class="librarian-card" data-user-id="<?php echo htmlspecialchars($librarian['user_id']); ?>">
                            <div class="librarian-header">
                                <div class="librarian-avatar">
                                    <?php if (!empty($librarian['profile_image']) && file_exists('../' . $librarian['profile_image'])): ?>
                                        <img src="../<?php echo htmlspecialchars($librarian['profile_image']); ?>" 
                                             alt="<?php echo htmlspecialchars($librarian['full_name']); ?>" 
                                             style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                                    <?php else: ?>
                                        <?php 
                                        $initials = substr($librarian['first_name'], 0, 1) . substr($librarian['last_name'], 0, 1);
                                        echo strtoupper($initials);
                                        ?>
                                    <?php endif; ?>
                                </div>
                                <div class="librarian-info">
                                    <h3><?php echo htmlspecialchars($librarian['full_name']); ?></h3>
                                    <p><?php echo htmlspecialchars($librarian['username']); ?></p>
                                </div>
                            </div>
                            <div class="librarian-details">
                                <div class="detail-item">
                                    <span class="detail-label">Email:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($librarian['email']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Phone:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($librarian['phone'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Status:</span>
                                    <span class="detail-value">
                                        <span class="status-badge status-<?php echo $librarian['status']; ?>">
                                            <?php echo ucfirst($librarian['status']); ?>
                                        </span>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Joined:</span>
                                    <span class="detail-value"><?php echo date('M j, Y', strtotime($librarian['created_at'])); ?></span>
                                </div>
                            </div>
                            <div class="librarian-actions">
                                <?php if ($librarian['status'] === 'pending'): ?>
                                    <a href="resend_librarian_setup.php?id=<?php echo urlencode($librarian['user_id']); ?>" class="action-btn resend" title="Resend Setup Email">
                                        <i class="fas fa-paper-plane"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="edit_librarian.php?id=<?php echo urlencode($librarian['user_id']); ?>" class="action-btn edit-btn" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="view_librarian.php?id=<?php echo urlencode($librarian['user_id']); ?>" class="action-btn view-btn" title="View Details">
                                    <i class="fas fa-file-alt"></i>
                                </a>
                                <?php if ($librarian['status'] !== 'pending'): ?>
                                    <a href="reset_librarian_password.php?id=<?php echo urlencode($librarian['user_id']); ?>" class="action-btn reset-password" title="Reset Password">
                                        <i class="fas fa-key"></i>
                                    </a>
                                    <a href="delete_librarian.php?id=<?php echo urlencode($librarian['user_id']); ?>" class="action-btn delete-btn" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 2rem;">
                        <?php echo !empty($search) ? 'No librarians found matching your search.' : 'No librarians in the library yet.'; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">&laquo; First</a>
                        <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">&lsaquo; Prev</a>
                    <?php endif; ?>
                    
                    <?php 
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Next &rsaquo;</a>
                        <a href="?page=<?php echo $totalPages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Last &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add Librarian Modal -->
    <div id="addLibrarianModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-tie"></i> Add New Librarian</h2>
                <button class="close">&times;</button>
            </div>
            
            <div class="modal-body">
                <form id="addLibrarianForm" method="POST">
                    <input type="hidden" name="action" value="add_librarian">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="text" id="phone" name="phone" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="username">Username *</label>
                            <input type="text" id="username" name="username" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelBtn">Cancel</button>
                <button class="btn btn-primary" id="saveLibrarianBtn">Add Librarian</button>
            </div>
        </div>
    </div>
    
    <script>
        // Toast notification function
        function showToast(message, type = 'info', duration = 5000) {
            const container = document.getElementById('toast-container');
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            // Add icon based on type
            let icon = '';
            switch(type) {
                case 'success':
                    icon = '<i class="fas fa-check-circle"></i>';
                    break;
                case 'error':
                    icon = '<i class="fas fa-exclamation-circle"></i>';
                    break;
                case 'warning':
                    icon = '<i class="fas fa-exclamation-triangle"></i>';
                    break;
                case 'info':
                    icon = '<i class="fas fa-info-circle"></i>';
                    break;
            }
            
            toast.innerHTML = `
                <div class="toast-content">
                    <span class="toast-icon">${icon}</span>
                    <span class="toast-message">${message}</span>
                </div>
                <button class="toast-close">&times;</button>
            `;
            
            // Add close button event
            const closeBtn = toast.querySelector('.toast-close');
            closeBtn.addEventListener('click', function() {
                toast.classList.add('hide');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            });
            
            // Add toast to container
            container.appendChild(toast);
            
            // Trigger animation
            setTimeout(() => {
                toast.classList.add('show');
            }, 10);
            
            // Auto remove after duration
            if (duration > 0) {
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.classList.add('hide');
                        setTimeout(() => {
                            if (toast.parentNode) {
                                toast.parentNode.removeChild(toast);
                            }
                        }, 300);
                    }
                }, duration);
            }
        }
        
        // Check for URL parameters and show toast notifications
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const success = urlParams.get('success');
            const error = urlParams.get('error');
            
            if (success) {
                showToast(success, 'success');
                // Remove the success parameter from URL without reloading
                urlParams.delete('success');
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({}, document.title, newUrl);
            }
            
            if (error) {
                showToast(error, 'error');
                // Remove the error parameter from URL without reloading
                urlParams.delete('error');
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({}, document.title, newUrl);
            }
        });
        
        // Modal functionality
        const modal = document.getElementById('addLibrarianModal');
        const addLibrarianBtn = document.getElementById('addLibrarianBtn');
        const closeBtn = document.querySelector('.close');
        const cancelBtn = document.getElementById('cancelBtn');
        
        // Open modal
        addLibrarianBtn.addEventListener('click', function() {
            modal.style.display = 'block';
        });
        
        // Close modal
        function closeModal() {
            modal.style.display = 'none';
        }
        
        closeBtn.addEventListener('click', closeModal);
        cancelBtn.addEventListener('click', closeModal);
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeModal();
            }
        });
        
        // Form submission
        document.getElementById('addLibrarianBtn').addEventListener('click', function() {
            document.getElementById('addLibrarianForm').reset();
        });
        
        // Add spinner to save button when form is submitted
        document.getElementById('saveLibrarianBtn').addEventListener('click', function() {
            const form = document.getElementById('addLibrarianForm');
            if (form.checkValidity()) {
                this.innerHTML = '<span class="loading-spinner"></span> Adding...';
                this.disabled = true;
                form.submit();
            }
        });
</script>
</body>
</html>