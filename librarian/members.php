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

// Check if user is logged in and has librarian role
if (!is_logged_in() || $_SESSION['user_role'] !== 'librarian') {
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

// Handle adding a new member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_member') {
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
        } else {
            // Check if email or username already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);
            if ($stmt->fetch()) {
                $error = "A member with this email or username already exists.";
            } else {
                // Generate a temporary password (will be replaced when member sets their own)
                $tempPassword = bin2hex(random_bytes(16));
                $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
                
                // Generate email verification token
                $verificationToken = bin2hex(random_bytes(32));
                
                // Insert member with pending status
                $stmt = $db->prepare("
                    INSERT INTO users 
                    (user_id, username, email, password_hash, first_name, last_name, phone, address, role, library_id, created_by, status, email_verification_token)
                    VALUES 
                    (?, ?, ?, ?, ?, ?, ?, ?, 'member', ?, ?, 'pending', ?)
                ");
                
                $userId = 'MEM' . time() . rand(100, 999);
                $createdBy = $_SESSION['user_id'];
                
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
                    // Send email to member
                    $emailService = new EmailService();
                    $verificationLink = APP_URL . "/member-setup.php?token=" . $verificationToken;
                    
                    $emailData = [
                        'first_name' => $firstName,
                        'library_name' => $_SESSION['library_name'] ?? 'Your Library',
                        'verification_link' => $verificationLink
                    ];
                    
                    $emailSent = $emailService->sendMemberSetupEmail($email, $emailData);
                    
                    if ($emailSent) {
                        header('Location: members.php?success=' . urlencode("Member added successfully. An email has been sent to {$email} for them to set up their account."));
                        exit;
                    } else {
                        header('Location: members.php?success=' . urlencode("Member added successfully, but there was an issue sending the email. Please contact the member directly."));
                        exit;
                    }
                } else {
                    header('Location: members.php?error=' . urlencode("Failed to add member. Please try again."));
                    exit;
                }
            }
        }
    } catch (Exception $e) {
        header('Location: members.php?error=' . urlencode("An error occurred: " . $e->getMessage()));
        exit;
    }
}

// Handle search and pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Members per page
$offset = ($page - 1) * $limit;

try {
    $db = Database::getInstance()->getConnection();
    
    // Get total members count for pagination
    if (!empty($search)) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM users u 
            WHERE u.library_id = ? 
            AND u.role = 'member'
            AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)
        ");
        $searchTerm = "%$search%";
        $stmt->execute([$libraryId, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE library_id = ? AND role = 'member'");
        $stmt->execute([$libraryId]);
    }
    $totalMembers = $stmt->fetch()['total'];
    $totalPages = ceil($totalMembers / $limit);
    
    // Get members for current page
    if (!empty($search)) {
        $stmt = $db->prepare("
            SELECT u.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as full_name
            FROM users u
            WHERE u.library_id = ? 
            AND u.role = 'member'
            AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)
            ORDER BY u.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $searchTerm = "%$search%";
        $stmt->execute([$libraryId, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit, $offset]);
    } else {
        $stmt = $db->prepare("
            SELECT u.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as full_name
            FROM users u
            WHERE u.library_id = ? 
            AND u.role = 'member'
            ORDER BY u.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$libraryId, $limit, $offset]);
    }
    $members = $stmt->fetchAll();
    
    // Get attendance data for today for all members
    $attendanceStmt = $db->prepare("
        SELECT user_id 
        FROM attendance 
        WHERE library_id = ? AND attendance_date = CURDATE()
    ");
    $attendanceStmt->execute([$libraryId]);
    $attendanceData = $attendanceStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Function to get attendance history for a member
    function getAttendanceHistory($db, $userId, $days = 7) {
        $stmt = $db->prepare("
            SELECT attendance_date 
            FROM attendance 
            WHERE user_id = ? 
            AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            ORDER BY attendance_date DESC
        ");
        $stmt->execute([$userId, $days]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
} catch (Exception $e) {
    die('Error loading members: ' . $e->getMessage());
}

$pageTitle = 'Members Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - LMS</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Toast CSS -->
    <link rel="stylesheet" href="css/toast.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 0;
        }
        
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
            margin: 0 auto 1.5rem auto;
            justify-content: center;
        }

        .search-input {
            flex: 1;
            padding: 0.75rem;
            border: 1.5px solid #95A5A6;
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
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            color: white;
            padding: 0.75rem 1rem;
        }

        .search-btn:hover {
            background: linear-gradient(135deg, #2980B9 0%, #2573A7 100%);
        }

        .search-btn-text {
            display: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.3);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.3);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
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

        .table-container {
            overflow-x: auto;
        }

        .members-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            font-size: 0.85rem;
        }

        .members-table th,
        .members-table td {
            padding: 0.75rem;
            text-align: center;
            border-bottom: 1px solid #dee2e6;
        }

        .members-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }

        .members-table tr:hover {
            background-color: #f8f9fa;
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

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-active {
            background-color: #006400;
            color: white;
        }

        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        /* Action button size adjustment */
        .action-btn {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        .member-avatar-small {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.9rem;
            margin: 0 auto;
        }

        /* Attendance icon styling */
        .attendance-present {
            color: #28a745; /* Green color for present */
        }

        .attendance-absent {
            color: #6c757d; /* Gray color for absent */
        }

        /* Prevent background scrolling when modal is open */
        body.modal-open {
            overflow: hidden;
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
            overflow: hidden;
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
            transition: border-color 0.3s, box-shadow 0.3s;
            box-sizing: border-box;
            /* Add padding to the right for dropdown icons */
            padding-right: 2.5rem;
        }
        
        /* Remove padding-right for non-select elements */
        input.form-control,
        textarea.form-control,
        input[type="file"].form-control {
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

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        input[type="file"].form-control {
            padding: 0.5rem;
            background-color: #f8f9fa;
        }

        input[type="file"].form-control::-webkit-file-upload-button {
            background: #3498DB;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }

        input[type="file"].form-control::-webkit-file-upload-button:hover {
            background: #2980B9;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-row .form-group:last-child {
            margin-bottom: 0;
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
                max-height: 95vh;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        /* Books page style action buttons */
        .action-btn {
            padding: 0.5rem 1rem;
            font-size: 1.2rem; /* Match books page icon size */
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            margin: 0 0.25rem;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }
        
        /* Color styling for action buttons */
        .btn-view {
            background: #3498DB;
            color: white;
        }
        
        .btn-edit {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-attendance {
            background: #f0f0f0; /* Light gray background */
            color: #28a745; /* Green checkmark */
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .btn-resend {
            background: #28a745;
            color: white;
        }
        
        /* Hover effects */
        .btn-view:hover {
            background: #2980B9;
        }
        
        .btn-edit:hover {
            background: #e0a800;
        }
        
        .btn-attendance:hover {
            background: #e0e0e0; /* Slightly darker gray on hover */
        }
        
        .btn-delete:hover {
            background: #c82333;
        }
        
        .btn-resend:hover {
            background: #218838;
        }
        
        /* Image placeholder styling */
        .image-placeholder {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <?php include 'includes/librarian_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-users"></i> Members Management</h1>
            <p>Manage library members and their accounts</p>
        </div>

        <!-- Toast Container -->
        <div id="toast-container"></div>

        <div class="content-card">
            <div class="card-header">
                <h2>Member List</h2>
                <button class="btn btn-primary" id="addMemberBtn">
                    <i class="fas fa-plus"></i>
                    Add New Member
                </button>
            </div>
            
            <!-- Search Bar -->
            <div class="search-bar">
                <form method="GET" style="display: flex; width: 100%; max-width: 500px; gap: 1rem;">
                    <input type="text" 
                           name="search" 
                           class="search-input" 
                           placeholder="Search members..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                        <span class="search-btn-text">Search</span>
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="members.php" class="btn btn-secondary" style="padding: 0.75rem 1rem;">
                            <i class="fas fa-times"></i>
                            <span class="search-btn-text">Clear</span>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="table-container">
                <table class="members-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Member</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($members) > 0): ?>
                            <?php foreach ($members as $member): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($member['profile_image']) && file_exists('../' . $member['profile_image'])): ?>
                                            <img src="../<?php echo htmlspecialchars($member['profile_image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($member['full_name']); ?>" 
                                                 style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; margin: 0 auto; display: block;">
                                        <?php else: ?>
                                            <div class="image-placeholder">
                                                <?php 
                                                $initials = substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1);
                                                echo strtoupper($initials);
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($member['email']); ?></td>
                                    <td><?php echo htmlspecialchars($member['phone'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $member['status']; ?>">
                                            <?php echo ucfirst($member['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($member['created_at'])); ?></td>
                                    <td>
                                        <?php $isPresentToday = in_array($member['user_id'], $attendanceData); ?>
                                        <a href="mark_attendance.php?user_id=<?php echo urlencode($member['user_id']); ?>" class="btn btn-attendance action-btn" title="<?php echo $isPresentToday ? 'Mark as Absent' : 'Mark as Present'; ?>">
                                            <i class="fas fa-check-circle <?php echo $isPresentToday ? 'attendance-present' : 'attendance-absent'; ?>"></i>
                                        </a>
                                        <?php if ($member['status'] === 'pending'): ?>
                                            <a href="resend_setup.php?user_id=<?php echo urlencode($member['user_id']); ?>" class="btn btn-resend action-btn" title="Resend Setup Email">
                                                <i class="fas fa-paper-plane"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="edit_member.php?id=<?php echo urlencode($member['user_id']); ?>" class="btn btn-edit action-btn" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="view_member.php?id=<?php echo urlencode($member['user_id']); ?>" class="btn btn-view action-btn" title="View Details">
                                            <i class="fas fa-file-alt"></i>
                                        </a>
                                        <?php if ($member['status'] !== 'pending'): ?>
                                            <a href="delete_member.php?id=<?php echo urlencode($member['user_id']); ?>" class="btn btn-delete action-btn" title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">
                                    <?php echo !empty($search) ? 'No members found matching your search.' : 'No members in the library yet.'; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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
    
    <!-- Add Member Modal -->
    <div id="addMemberModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user"></i> Add New Member</h2>
                <button class="close">&times;</button>
            </div>
            
            <div class="modal-body">
                <form id="addMemberForm" method="POST">
                    <input type="hidden" name="action" value="add_member">
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
                <button class="btn btn-primary" id="saveMemberBtn">Add Member</button>
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
        const modal = document.getElementById('addMemberModal');
        const addMemberBtn = document.getElementById('addMemberBtn');
        const closeBtn = document.querySelector('.close');
        const cancelBtn = document.getElementById('cancelBtn');
        
        // Open modal
        addMemberBtn.addEventListener('click', function() {
            modal.style.display = 'block';
            document.body.classList.add('modal-open');
        });
        
        // Close modal
        function closeModal() {
            modal.style.display = 'none';
            document.body.classList.remove('modal-open');
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
        document.getElementById('saveMemberBtn').addEventListener('click', function() {
            // Validate form
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const email = document.getElementById('email').value.trim();
            const username = document.getElementById('username').value.trim();
            
            if (!firstName || !lastName || !email || !username) {
                alert('Please fill in all required fields.');
                return;
            }
            
            // Add spinner and disable button
            this.innerHTML = '<span class="loading-spinner"></span> Adding...';
            this.disabled = true;
            
            // Submit form
            document.getElementById('addMemberForm').submit();
        });
        
        // Remove the AJAX attendance functionality since we're using separate pages
    </script>
</body>
</html>