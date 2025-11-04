<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';
require_once '../includes/SubscriptionManager.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has admin role
if (!is_logged_in() || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Handle search and pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$planFilter = isset($_GET['plan']) ? $_GET['plan'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Libraries per page
$offset = ($page - 1) * $limit;

try {
    $db = Database::getInstance()->getConnection();
    $subscriptionManager = new SubscriptionManager();
    
    // Get total libraries count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM libraries l LEFT JOIN subscriptions s ON l.id = s.library_id WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $countQuery .= " AND (l.library_name LIKE ? OR l.library_code LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // Apply plan filter
    if (!empty($planFilter)) {
        $countQuery .= " AND s.plan_type = ?";
        $params[] = $planFilter;
    }
    
    // Apply status filter
    if (!empty($statusFilter)) {
        $countQuery .= " AND s.status = ?";
        $params[] = $statusFilter;
    }
    
    $stmt = $db->prepare($countQuery);
    $stmt->execute($params);
    $totalLibraries = $stmt->fetch()['total'];
    $totalPages = ceil($totalLibraries / $limit);
    
    // Get libraries with subscription details
    $query = "
        SELECT 
            l.id,
            l.library_name,
            l.library_code,
            l.status as library_status,
            l.deleted_at,
            s.plan_type,
            s.status as subscription_status,
            s.start_date,
            s.end_date
        FROM libraries l
        LEFT JOIN subscriptions s ON l.id = s.library_id
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (l.library_name LIKE ? OR l.library_code LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // Apply plan filter
    if (!empty($planFilter)) {
        $query .= " AND s.plan_type = ?";
        $params[] = $planFilter;
    }
    
    // Apply status filter
    if (!empty($statusFilter)) {
        $query .= " AND s.status = ?";
        $params[] = $statusFilter;
    }
    
    $query .= " ORDER BY l.library_name ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $libraries = $stmt->fetchAll();
    
} catch (Exception $e) {
    die('Error loading subscription data: ' . $e->getMessage());
}

$pageTitle = 'Subscriptions';
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
        
        /* Search Bar */
        .search-container {
            display: flex;
            gap: 0.5rem;
            width: 400px;
            margin-left: auto;
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
        
        /* Table Styles */
        .table-container {
            overflow-x: auto;
        }
        
        .subscriptions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        
        .subscriptions-table th,
        .subscriptions-table td {
            padding: 1rem;
            text-align: center;
            border-bottom: 1px solid var(--gray-300);
        }
        
        .subscriptions-table th {
            background-color: var(--gray-100);
            font-weight: 600;
            color: var(--gray-700);
            position: sticky;
            top: 0;
        }
        
        .subscriptions-table tr:hover {
            background-color: var(--gray-100);
        }
        
        .library-logo {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            background-color: var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }
        
        .library-logo i {
            font-size: 1.5rem;
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
        
        .status-active {
            background-color: var(--success-color);
            color: white;
        }
        
        .status-inactive {
            background-color: var(--gray-300);
            color: var(--gray-700);
        }
        
        .status-trial {
            background-color: var(--warning-color);
            color: white;
        }
        
        .status-expired {
            background-color: var(--danger-color);
            color: white;
        }
        
        .plan-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .plan-basic {
            background-color: #6c757d;
            color: white;
        }
        
        .plan-standard {
            background-color: #17a2b8;
            color: white;
        }
        
        .plan-premium {
            background-color: #6f42c1;
            color: white;
        }
        
        .action-btn {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Icon with slash styling */
        .icon-with-slash {
            position: relative;
            display: inline-block;
            width: 1em;
            height: 1em;
            line-height: 1;
        }
        
        .icon-with-slash::after {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 1.5em;
            height: 2px;
            background-color: currentColor;
            transform: translate(-50%, -50%) rotate(-45deg);
            transform-origin: center;
        }
        
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
            
            .search-container {
                width: 100%;
            }
            
            .subscriptions-table {
                font-size: 0.8rem;
            }
            
            .subscriptions-table th,
            .subscriptions-table td {
                padding: 0.5rem;
            }
            
            .library-logo {
                width: 40px;
                height: 40px;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/admin_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-credit-card"></i> <?php echo $pageTitle; ?></h1>
            <p>Manage library subscriptions and payment plans</p>
        </div>
        
        <div class="content-card">
            <div class="card-header">
                <h2>Library Subscriptions</h2>
            </div>
            
            <!-- Search and Filter Bar -->
            <div class="search-filter-bar">
                <form method="GET" style="display: contents;">
                    <input type="hidden" name="_" value="<?php echo time(); ?>">
                    <div class="filter-container">
                        <div class="filter-group">
                            <label for="plan">Filter by Plan</label>
                            <select name="plan" id="plan" class="filter-select">
                                <option value="">All Plans</option>
                                <option value="basic" <?php echo $planFilter === 'basic' ? 'selected' : ''; ?>>Basic</option>
                                <option value="standard" <?php echo $planFilter === 'standard' ? 'selected' : ''; ?>>Standard</option>
                                <option value="premium" <?php echo $planFilter === 'premium' ? 'selected' : ''; ?>>Premium</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="status">Filter by Status</label>
                            <select name="status" id="status" class="filter-select">
                                <option value="">All Statuses</option>
                                <option value="trial" <?php echo $statusFilter === 'trial' ? 'selected' : ''; ?>>Trial</option>
                                <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="expired" <?php echo $statusFilter === 'expired' ? 'selected' : ''; ?>>Expired</option>
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
                                   placeholder="Search libraries..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="search-btn">
                                <i class="fas fa-search"></i>
                            </button>
                            <?php if (!empty($search) || !empty($planFilter) || !empty($statusFilter)): ?>
                                <a href="subscriptions.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Subscriptions Table -->
            <div class="table-container">
                <table class="subscriptions-table">
                    <thead>
                        <tr>
                            <th>Library</th>
                            <th>Code</th>
                            <th>Package</th>
                            <th>Status</th>
                            <th>Days Remaining</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($libraries) > 0): ?>
                            <?php foreach ($libraries as $library): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($library['library_name']); ?></strong>
                                        <?php if (!empty($library['deleted_at'])): ?>
                                            <br><span class="status-badge status-inactive">DELETED</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($library['library_code']); ?></td>
                                    <td>
                                        <?php if (!empty($library['plan_type'])): ?>
                                            <span class="plan-badge plan-<?php echo $library['plan_type']; ?>">
                                                <?php echo ucfirst($library['plan_type']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="plan-badge plan-basic">No Plan</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($library['subscription_status'])): ?>
                                            <?php 
                                            $statusClass = 'status-' . $library['subscription_status'];
                                            if ($library['subscription_status'] === 'trial' && !empty($library['end_date']) && strtotime($library['end_date']) < time()) {
                                                $statusClass = 'status-expired';
                                            }
                                            ?>
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php 
                                                if ($library['subscription_status'] === 'trial' && !empty($library['end_date']) && strtotime($library['end_date']) < time()) {
                                                    echo 'Expired';
                                                } else {
                                                    echo ucfirst($library['subscription_status']);
                                                }
                                                ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-inactive">No Subscription</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if (!empty($library['end_date'])) {
                                            // Use the same calculation method as in view_library.php for consistency
                                            $today = new DateTime();
                                            $endDate = new DateTime($library['end_date']);
                                            
                                            // Check if subscription has expired
                                            if ($endDate < $today) {
                                                echo 'Expired';
                                            } else {
                                                $remainingDays = $today->diff($endDate)->days;
                                                echo $remainingDays . ' day' . ($remainingDays != 1 ? 's' : '');
                                            }
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="view_subscription.php?library_id=<?php echo $library['id']; ?>" class="btn btn-primary action-btn" title="View Subscription">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="activate_subscription.php?library_id=<?php echo $library['id']; ?>" class="btn btn-success action-btn" title="Activate">
                                            <i class="fas fa-toggle-on"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">
                                    <?php 
                                    if (!empty($search)) {
                                        echo 'No libraries found matching your search criteria.';
                                    } else {
                                        echo 'No libraries in the system yet.';
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
                    <?php 
                    // Build query string for pagination links
                    $queryString = '';
                    if (!empty($search)) {
                        $queryString .= '&search=' . urlencode($search);
                    }
                    if (!empty($planFilter)) {
                        $queryString .= '&plan=' . urlencode($planFilter);
                    }
                    if (!empty($statusFilter)) {
                        $queryString .= '&status=' . urlencode($statusFilter);
                    }
                    ?>
                    
                    <?php if ($page > 1): ?>
                        <a href="?page=1<?php echo $queryString; ?>">&laquo; First</a>
                        <a href="?page=<?php echo $page - 1; ?><?php echo $queryString; ?>">&lsaquo; Prev</a>
                    <?php endif; ?>
                    
                    <?php 
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?><?php echo $queryString; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $queryString; ?>">Next &rsaquo;</a>
                        <a href="?page=<?php echo $totalPages; ?><?php echo $queryString; ?>">Last &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Toast JavaScript -->
    <script src="js/toast.js"></script>
    
    <!-- Toast JavaScript -->
    <script src="js/toast.js"></script>
</body>
</html>