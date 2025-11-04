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

// Handle delete/undelete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['user_id'])) {
    $action = $_POST['action'];
    $userId = (int)$_POST['user_id'];
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Get user details to check role and current status
        $stmt = $db->prepare("SELECT role, status FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userDetails = $stmt->fetch();
        
        if (!$userDetails) {
            // Redirect with error message
            header('Location: users.php?error=' . urlencode('User not found.'));
            exit;
        } else {
            // Admin users cannot be deleted from the interface
            if ($userDetails['role'] === 'admin') {
                header('Location: users.php?error=' . urlencode('Admin users cannot be deleted from the interface.'));
                exit;
            } else {
                if ($action === 'delete') {
                    // Soft delete by setting status to inactive
                    $stmt = $db->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
                    $stmt->execute([$userId]);
                    header('Location: users.php?success=' . urlencode('User has been deactivated successfully.'));
                    exit;
                } elseif ($action === 'undelete' && $userDetails['status'] === 'inactive') {
                    // Undelete by setting status back to active
                    $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ?");
                    $stmt->execute([$userId]);
                    header('Location: users.php?success=' . urlencode('User has been reactivated successfully.'));
                    exit;
                } else {
                    header('Location: users.php?error=' . urlencode('Invalid action.'));
                    exit;
                }
            }
        }
    } catch (Exception $e) {
        header('Location: users.php?error=' . urlencode('Error processing request: ' . $e->getMessage()));
        exit;
    }
}

// Handle search and pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$roleFilter = isset($_GET['role']) ? $_GET['role'] : '';
$libraryFilter = isset($_GET['library']) ? (int)$_GET['library'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$limit = 10; // Users per page
$offset = ($page - 1) * $limit;

try {
    $db = Database::getInstance()->getConnection();
    
    // Get libraries for filter dropdown
    $librariesStmt = $db->prepare("SELECT id, library_name FROM libraries WHERE deleted_at IS NULL ORDER BY library_name");
    $librariesStmt->execute();
    $libraries = $librariesStmt->fetchAll();
    
    // Get total users count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM users u WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $countQuery .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // Apply role filter
    if (!empty($roleFilter)) {
        $countQuery .= " AND u.role = ?";
        $params[] = $roleFilter;
    }
    
    // Apply library filter
    if (!empty($libraryFilter)) {
        $countQuery .= " AND u.library_id = ?";
        $params[] = $libraryFilter;
    }
    
    // Apply status filter
    if (!empty($statusFilter)) {
        $countQuery .= " AND u.status = ?";
        $params[] = $statusFilter;
    }
    
    $stmt = $db->prepare($countQuery);
    $stmt->execute($params);
    $totalUsers = $stmt->fetch()['total'];
    $totalPages = ceil($totalUsers / $limit);
    
    // Get users for current page
    $query = "SELECT u.*, l.library_name FROM users u LEFT JOIN libraries l ON u.library_id = l.id WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // Apply role filter
    if (!empty($roleFilter)) {
        $query .= " AND u.role = ?";
        $params[] = $roleFilter;
    }
    
    // Apply library filter
    if (!empty($libraryFilter)) {
        $query .= " AND u.library_id = ?";
        $params[] = $libraryFilter;
    }
    
    // Apply status filter
    if (!empty($statusFilter)) {
        $query .= " AND u.status = ?";
        $params[] = $statusFilter;
    }
    
    $query .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
} catch (Exception $e) {
    die('Error loading users: ' . $e->getMessage());
}

$pageTitle = 'Users';
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
        :root {
            --primary-color: #0066cc; /* macOS deeper blue */
            --primary-dark: #0052a3;
            --secondary-color: #f8f9fa;
            --success-color: #1b5e20; /* Deep green for active status */
            --danger-color: #c62828; /* Deep red for error states */
            --warning-color: #F39C12;
            --info-color: #495057;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --gray-900: #212529;
            --border-radius: 12px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --box-shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s ease;
        }
        
        body {
            background: #f8f9fa;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #495057;
            padding-top: 60px; /* Space for navbar */
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            margin-bottom: 30px;
            text-align: center;
        }

        .page-header h1 {
            color: #2c3e50;
            font-size: 1.8rem;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #6c757d;
            font-size: 1.1rem;
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
            border-bottom: 1px solid var(--gray-200);
        }

        .card-header h2 {
            color: var(--gray-900);
            margin: 0;
        }
        
        /* Search and Filter Bar */
        .search-filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: end;
        }
        
        .filter-container {
            flex: 1;
            display: flex;
            gap: 1rem;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            width: fit-content;
        }
        
        .filter-group label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--gray-700);
        }
        
        .filter-select {
            padding: 0.75rem;
            border: 1.5px solid #95A5A6;
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
            width: fit-content;
            height: calc(2.5rem + 3px);
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23495057' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1rem;
            padding-right: 2.5rem;
        }
        
        .filter-select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.15);
        }
        
        .search-container {
            display: flex;
            gap: 0.5rem;
            width: 400px;
            margin-left: auto;
        }
        
        .search-group {
            display: flex;
            gap: 0.5rem;
            width: 100%;
        }
        
        .search-input {
            flex: 1;
            padding: 0.75rem;
            border: 1.5px solid #95A5A6;
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .search-input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.15);
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
            transition: var(--transition);
        }
        
        .search-btn {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 0.75rem 1rem;
            box-shadow: 0 4px 6px rgba(0, 102, 204, 0.2);
        }
        
        .search-btn:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, #004d99 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 102, 204, 0.3);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(0, 102, 204, 0.2);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 102, 204, 0.3);
        }
        
        .btn-danger {
            background: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background: #b71c1c;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(198, 40, 40, 0.3);
        }
        
        .btn-success {
            background: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background: #2e7d32;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(29, 94, 32, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.3);
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 193, 7, 0.3);
        }
        
        .filter-actions {
            display: flex;
            gap: 0.5rem;
            align-items: end;
        }
        
        /* Table Styles */
        .table-container {
            overflow-x: auto;
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        
        .users-table th,
        .users-table td {
            padding: 1rem;
            text-align: center;
            border-bottom: 1px solid var(--gray-300);
        }
        
        .users-table th {
            background-color: var(--gray-100);
            font-weight: 600;
            color: var(--gray-700);
            position: sticky;
            top: 0;
        }
        
        .users-table tr:hover {
            background-color: var(--gray-100);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            background-color: var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }
        
        .user-avatar i {
            font-size: 1rem;
            color: var(--gray-500);
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
        
        .status-badge.status-active {
            background-color: var(--success-color);
            color: white;
        }
        
        .status-badge.status-inactive {
            background-color: var(--gray-300);
            color: var(--gray-700);
        }
        
        .status-badge.status-pending {
            background-color: var(--warning-color);
            color: white;
        }
        
        .status-badge.status-suspended {
            background-color: var(--danger-color);
            color: white;
        }
        
        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .role-badge.role-admin {
            background-color: #6f42c1;
            color: white;
        }
        
        .role-badge.role-supervisor {
            background-color: #17a2b8;
            color: white;
        }
        
        .role-badge.role-librarian {
            background-color: #28a745;
            color: white;
        }
        
        .role-badge.role-member {
            background-color: #007bff;
            color: white;
        }
        
        .action-btn {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        /* Alert Styles - REMOVED as we're using toast notifications */
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        
        .pagination a,
        .pagination span {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-300);
            text-decoration: none;
            color: var(--gray-700);
            border-radius: 4px;
            transition: var(--transition);
        }
        
        .pagination a:hover {
            background-color: var(--gray-200);
        }
        
        .pagination .current {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .search-filter-bar {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .search-group {
                width: 100%;
            }
            
            .filter-actions {
                width: 100%;
                justify-content: center;
            }
            
            .users-table {
                font-size: 0.8rem;
            }
            
            .users-table th,
            .users-table td {
                padding: 0.5rem;
            }
            
            .user-avatar {
                width: 30px;
                height: 30px;
            }
        }
    </style>
    
    <script>
        function confirmDelete(userId, userName) {
            if (confirm('Are you sure you want to deactivate this user: ' + userName + '?\n\nThis will be a soft delete. The user will be set to inactive status and will not be able to log in.\n\nThey can be reactivated later using the undelete function.')) {
                // Submit the delete form
                document.getElementById('delete-form-' + userId).submit();
            }
        }
        
        function confirmUndelete(userId, userName) {
            if (confirm('Are you sure you want to reactivate this user: ' + userName + '?\n\nThis will restore the user to active status and they will be able to log in again.')) {
                // Submit the undelete form
                document.getElementById('undelete-form-' + userId).submit();
            }
        }
    </script>
</head>

<body>
    <?php include 'includes/admin_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-users"></i> <?php echo $pageTitle; ?></h1>
            <p>Manage all users in the system</p>
        </div>
        
        <!-- Toast notifications will be displayed here via JavaScript -->
        
        <div class="content-card">
            <div class="card-header">
                <h2>All Users</h2>
            </div>
            
            <!-- Search and Filter Bar -->
            <div class="search-filter-bar">
                <form method="GET" style="display: contents;">
                    <input type="hidden" name="_" value="<?php echo time(); ?>">
                    <div class="filter-container">
                        <div class="filter-group">
                            <label for="role">Filter by Role</label>
                            <select name="role" id="role" class="filter-select">
                                <option value="">All Roles</option>
                                <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="supervisor" <?php echo $roleFilter === 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                                <option value="librarian" <?php echo $roleFilter === 'librarian' ? 'selected' : ''; ?>>Librarian</option>
                                <option value="member" <?php echo $roleFilter === 'member' ? 'selected' : ''; ?>>Member</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="library">Filter by Library</label>
                            <select name="library" id="library" class="filter-select">
                                <option value="">All Libraries</option>
                                <?php foreach ($libraries as $library): ?>
                                    <option value="<?php echo $library['id']; ?>" <?php echo $libraryFilter == $library['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($library['library_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="status">Filter by Status</label>
                            <select name="status" id="status" class="filter-select">
                                <option value="">All Statuses</option>
                                <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="suspended" <?php echo $statusFilter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            </select>
                        </div>
                        
                        <div class="filter-actions">
                            <input type="submit" value="Filter" class="btn btn-primary">
                        </div>
                    </div>
                    
                    <div class="search-container">
                        <div class="search-group">
                            <input type="text" 
                                   name="search" 
                                   id="search"
                                   class="search-input" 
                                   placeholder="Search users..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="search-btn">
                                <i class="fas fa-search"></i>
                            </button>
                            <?php if (!empty($search) || !empty($roleFilter) || !empty($libraryFilter) || !empty($statusFilter)): ?>
                                <a href="users.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Users Table -->
            <div class="table-container">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Profile Image</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Library</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="user-avatar">
                                            <?php if (!empty($user['profile_image']) && file_exists('../' . $user['profile_image'])): ?>
                                                <img src="../<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                                     alt="Profile" 
                                                     style="width: 100%; height: 100%; object-fit: cover;">
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
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo !empty($user['library_name']) ? htmlspecialchars($user['library_name']) : 'N/A'; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $user['status']; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view_user.php?id=<?php echo $user['id']; ?>" class="btn btn-primary action-btn" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($user['role'] !== 'admin'): ?>
                                            <?php if ($user['status'] !== 'inactive'): ?>
                                                <!-- Delete form -->
                                                <form id="delete-form-<?php echo $user['id']; ?>" method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="button" class="btn btn-danger action-btn" title="Deactivate" onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['first_name'] . ' ' . $user['last_name'])); ?>')">
                                                        <i class="fas fa-user-slash"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <!-- Undelete form -->
                                                <form id="undelete-form-<?php echo $user['id']; ?>" method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="undelete">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="button" class="btn btn-success action-btn" title="Reactivate" onclick="confirmUndelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['first_name'] . ' ' . $user['last_name'])); ?>')">
                                                        <i class="fas fa-user-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">
                                    <?php 
                                    if (!empty($search) || !empty($roleFilter) || !empty($libraryFilter) || !empty($statusFilter)) {
                                        echo 'No users found matching your search criteria.';
                                    } else {
                                        echo 'No users in the system yet.';
                                    }
                                    ?>
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
                        <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($roleFilter) ? '&role=' . urlencode($roleFilter) : ''; ?><?php echo !empty($libraryFilter) ? '&library=' . urlencode($libraryFilter) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?>">&laquo; First</a>
                        <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($roleFilter) ? '&role=' . urlencode($roleFilter) : ''; ?><?php echo !empty($libraryFilter) ? '&library=' . urlencode($libraryFilter) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?>">&lsaquo; Prev</a>
                    <?php endif; ?>
                    
                    <?php 
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($roleFilter) ? '&role=' . urlencode($roleFilter) : ''; ?><?php echo !empty($libraryFilter) ? '&library=' . urlencode($libraryFilter) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($roleFilter) ? '&role=' . urlencode($roleFilter) : ''; ?><?php echo !empty($libraryFilter) ? '&library=' . urlencode($libraryFilter) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?>">Next &rsaquo;</a>
                        <a href="?page=<?php echo $totalPages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($roleFilter) ? '&role=' . urlencode($roleFilter) : ''; ?><?php echo !empty($libraryFilter) ? '&library=' . urlencode($libraryFilter) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?>">Last &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Toast JavaScript -->
    <script src="js/toast.js"></script>
</body>
</html>