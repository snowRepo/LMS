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

// Get search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get libraries data with borrowing counts and search functionality
try {
    $db = Database::getInstance()->getConnection();
    
    // Get all libraries with borrowing counts, with search functionality
    if (!empty($search)) {
        $stmt = $db->prepare("
            SELECT 
                l.id,
                l.library_name,
                l.library_code,
                COUNT(b.id) as borrowing_count
            FROM libraries l
            LEFT JOIN users u ON l.id = u.library_id
            LEFT JOIN borrowings b ON u.id = b.member_id
            WHERE l.deleted_at IS NULL
            AND (l.library_name LIKE ? OR l.library_code LIKE ?)
            GROUP BY l.id
            ORDER BY l.library_name
        ");
        $searchTerm = '%' . $search . '%';
        $stmt->execute([$searchTerm, $searchTerm]);
    } else {
        $stmt = $db->prepare("
            SELECT 
                l.id,
                l.library_name,
                l.library_code,
                COUNT(b.id) as borrowing_count
            FROM libraries l
            LEFT JOIN users u ON l.id = u.library_id
            LEFT JOIN borrowings b ON u.id = b.member_id
            WHERE l.deleted_at IS NULL
            GROUP BY l.id
            ORDER BY l.library_name
        ");
        $stmt->execute();
    }
    $libraries = $stmt->fetchAll();
    
} catch (Exception $e) {
    die('Error loading libraries data: ' . $e->getMessage());
}

$pageTitle = 'Borrowing Reports';
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
            justify-content: center; /* Center the search bar */
        }
        
        .search-container {
            display: flex;
            gap: 0.5rem;
            width: 400px;
            margin: 0 auto; /* Center the search container */
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
            background: var(--primary-color);
            color: white;
        }
        
        .search-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 102, 204, 0.3);
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
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.3);
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
            cursor: pointer;
            transition: var(--transition);
            background: var(--primary-color);
            color: white;
        }
        
        .back-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 102, 204, 0.3);
        }
        
        /* Table Styles */
        .table-container {
            overflow-x: auto;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table th,
        .report-table td {
            padding: 1rem;
            text-align: center;
            border-bottom: 1px solid #dee2e6;
        }

        .report-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #212529;
            text-align: center;
        }

        .report-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .count-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
            background-color: var(--primary-color);
            color: white;
        }
        
        .action-cell {
            display: flex;
            justify-content: center;
        }
        
        .view-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }
        
        .view-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 102, 204, 0.3);
        }
        
        /* Action button styles to match member and book reports */
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: auto; /* Changed from fixed width */
            height: auto; /* Changed from fixed height */
            border-radius: 8px; /* Changed from 50% to match users page */
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            padding: 0.5rem 1rem; /* Added padding */
        }
        
        .action-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px); /* Added back the hover effect */
            box-shadow: 0 6px 20px rgba(0, 102, 204, 0.3); /* Added shadow for depth */
        }
        
        .action-btn i {
            margin-right: 0.5rem; /* Added space between icon and text */
        }
        
        .no-data {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
        
        @media (max-width: 768px) {
            .search-container {
                width: 100%;
            }
            
            .report-table th,
            .report-table td {
                padding: 0.75rem 0.5rem;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/admin_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-book-reader"></i> <?php echo $pageTitle; ?></h1>
            <p>View borrowing statistics by library</p>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h2><i class="fas fa-chart-bar"></i> Library Borrowing Statistics</h2>
                <a href="reports.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Reports
                </a>
            </div>
            
            <!-- Search Bar -->
            <div class="search-filter-bar">
                <div class="search-container">
                    <form method="GET" class="search-group">
                        <input 
                            type="text" 
                            name="search" 
                            class="search-input" 
                            placeholder="Search libraries by name or code..." 
                            value="<?php echo htmlspecialchars($search); ?>"
                        >
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
            </div>
            
            <?php if (empty($libraries)): ?>
                <div class="no-data">
                    <i class="fas fa-book-reader fa-3x" style="margin-bottom: 1rem; color: #ced4da;"></i>
                    <h3>No Libraries Found</h3>
                    <p><?php echo !empty($search) ? 'No libraries match your search criteria.' : 'There are currently no libraries in the system.'; ?></p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Library Name</th>
                                <th>Library Code</th>
                                <th>Borrowing Count</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($libraries as $library): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($library['library_name']); ?></td>
                                    <td><?php echo htmlspecialchars($library['library_code']); ?></td>
                                    <td>
                                        <span class="count-badge">
                                            <?php echo $library['borrowing_count']; ?>
                                        </span>
                                    </td>
                                    <td class="action-cell">
                                        <a href="view_library_borrowings.php?library_id=<?php echo $library['id']; ?>" 
                                           class="action-btn" 
                                           title="View Borrowings">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>