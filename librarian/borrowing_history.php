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

// Check if user is logged in and has librarian role
if (!is_logged_in() || $_SESSION['user_role'] !== 'librarian') {
    header('Location: ../login.php');
    exit;
}

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Initialize database connection
$db = Database::getInstance()->getConnection();

// Fetch all returned borrowings with search filter
$borrowingHistory = [];
try {
    if (!empty($search)) {
        $stmt = $db->prepare("
            SELECT b.*, bk.title, bk.isbn, bk.author_name,
                   CONCAT(m.first_name, ' ', m.last_name) as member_name,
                   m.user_id as member_id,
                   u.first_name as issued_by_first_name, u.last_name as issued_by_last_name,
                   ur.first_name as returned_by_first_name, ur.last_name as returned_by_last_name
            FROM borrowings b
            JOIN books bk ON b.book_id = bk.id
            JOIN users m ON b.member_id = m.id
            JOIN users u ON b.issued_by = u.id
            LEFT JOIN users ur ON b.returned_by = ur.id
            WHERE b.return_date IS NOT NULL
            AND (bk.title LIKE ? OR bk.isbn LIKE ? OR b.transaction_id LIKE ? OR m.first_name LIKE ? OR m.last_name LIKE ? OR m.user_id LIKE ?)
            ORDER BY b.return_date DESC
            LIMIT 50
        ");
        $searchParam = "%$search%";
        $stmt->execute([$searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
    } else {
        $stmt = $db->prepare("
            SELECT b.*, bk.title, bk.isbn, bk.author_name,
                   CONCAT(m.first_name, ' ', m.last_name) as member_name,
                   m.user_id as member_id,
                   u.first_name as issued_by_first_name, u.last_name as issued_by_last_name,
                   ur.first_name as returned_by_first_name, ur.last_name as returned_by_last_name
            FROM borrowings b
            JOIN books bk ON b.book_id = bk.id
            JOIN users m ON b.member_id = m.id
            JOIN users u ON b.issued_by = u.id
            LEFT JOIN users ur ON b.returned_by = ur.id
            WHERE b.return_date IS NOT NULL
            ORDER BY b.return_date DESC
            LIMIT 50
        ");
        $stmt->execute();
    }
    $borrowingHistory = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching borrowing history: " . $e->getMessage());
}

$pageTitle = 'Borrowing History';
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 0;
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
            color: #495057;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .page-header p {
            color: #6c757d;
            font-size: 1.1rem;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #3498db;
            text-decoration: none;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .section {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            overflow: hidden;
            border: 1px solid #e9ecef;
        }
        
        .section-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .section-title {
            margin: 0;
            font-size: 1.3rem;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .search-bar {
            display: flex;
            gap: 0.5rem;
            max-width: 400px;
            width: 100%;
        }
        
        .search-input {
            flex: 1;
            padding: 0.5rem;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .search-btn {
            padding: 0.5rem 1rem;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 1rem;
            text-align: center;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
        }
        
        .table tr:last-child td {
            border-bottom: none;
        }
        
        .table tr:hover {
            background-color: #f8f9fa;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-returned {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }
        
        .status-overdue {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }
        
        .no-data {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
        
        .no-data i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #ced4da;
        }
        
        .action-btn {
            padding: 0.5rem 1rem;
            font-size: 1.2rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            margin: 0 0.25rem;
            border: none;
            cursor: pointer;
            background: #3498DB;
            color: white;
        }
        
        .action-btn:hover {
            background: #2980B9;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .section-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-bar {
                max-width: 100%;
            }
            
            .table th,
            .table td {
                padding: 0.75rem 0.5rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/librarian_navbar.php'; ?>
    
    <div class="container">
        <a href="borrowing.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Borrowings
        </a>
        
        <div class="page-header">
            <h1><i class="fas fa-history"></i> Borrowing History</h1>
            <p>View all past book borrowings</p>
        </div>
        
        <div class="section">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-list"></i> Borrowing History</h2>
                <form method="GET" class="search-bar">
                    <input type="text" name="search" class="search-input" placeholder="Search by title, ISBN, member name, or transaction ID..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="borrowing_history.php" class="btn btn-primary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <?php if (empty($borrowingHistory)): ?>
                <div class="no-data">
                    <i class="fas fa-inbox"></i>
                    <h3>No Borrowing History Found</h3>
                    <p>
                        <?php if (!empty($search)): ?>
                            No borrowing history matches your search.
                        <?php else: ?>
                            No books have been returned yet.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Book Title</th>
                                <th>Author</th>
                                <th>Member</th>
                                <th>Borrowed Date</th>
                                <th>Due Date</th>
                                <th>Return Date</th>
                                <th>Transaction ID</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($borrowingHistory as $borrowing): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($borrowing['title']); ?></td>
                                    <td><?php echo htmlspecialchars($borrowing['author_name'] ?? 'Unknown'); ?></td>
                                    <td><?php echo htmlspecialchars($borrowing['member_name']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($borrowing['issue_date'])); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($borrowing['due_date'])); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($borrowing['return_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($borrowing['transaction_id']); ?></td>
                                    <td>
                                        <a href="view_borrowing.php?id=<?php echo $borrowing['id']; ?>" class="action-btn" title="View Details">
                                            <i class="fas fa-file-alt"></i>
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