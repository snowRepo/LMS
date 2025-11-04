<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';
require_once '../includes/SubscriptionCheck.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();

}

// Check if user is logged in and has member role
if (!is_logged_in() || $_SESSION['user_role'] !== 'member') {
    header('Location: ../login.php');
    exit;
}

// Check subscription status - redirect to expired page if subscription is not active
requireActiveSubscription();

// Initialize database connection
$db = Database::getInstance()->getConnection();

// Fetch active borrowings for this member
$activeBorrowings = [];
try {
    $stmt = $db->prepare("
        SELECT b.*, bk.title, bk.isbn, bk.author_name,
               DATEDIFF(b.due_date, CURDATE()) as days_remaining,
               u.first_name as issued_by_first_name, u.last_name as issued_by_last_name
        FROM borrowings b
        JOIN books bk ON b.book_id = bk.id
        JOIN users u ON b.issued_by = u.id
        WHERE b.member_id = ? AND b.return_date IS NULL
        ORDER BY b.due_date ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $activeBorrowings = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching active borrowings: " . $e->getMessage());
}

// Fetch books due in 3 days
$dueIn3Days = [];
try {
    $stmt = $db->prepare("
        SELECT b.*, bk.title, bk.isbn, bk.author_name,
               DATEDIFF(b.due_date, CURDATE()) as days_remaining,
               u.first_name as issued_by_first_name, u.last_name as issued_by_last_name
        FROM borrowings b
        JOIN books bk ON b.book_id = bk.id
        JOIN users u ON b.issued_by = u.id
        WHERE b.member_id = ? AND b.return_date IS NULL AND DATEDIFF(b.due_date, CURDATE()) = 3
        ORDER BY b.due_date ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $dueIn3Days = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching books due in 3 days: " . $e->getMessage());
}

// Fetch borrowing history for this member
$borrowingHistory = [];
try {
    $stmt = $db->prepare("
        SELECT b.*, bk.title, bk.isbn, bk.author_name,
               u.first_name as issued_by_first_name, u.last_name as issued_by_last_name,
               ur.first_name as returned_by_first_name, ur.last_name as returned_by_last_name
        FROM borrowings b
        JOIN books bk ON b.book_id = bk.id
        JOIN users u ON b.issued_by = u.id
        LEFT JOIN users ur ON b.returned_by = ur.id
        WHERE b.member_id = ? AND b.return_date IS NOT NULL
        ORDER BY b.return_date DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $borrowingHistory = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching borrowing history: " . $e->getMessage());
}

$pageTitle = 'My Borrowings';
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
            color: #212529;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .page-header p {
            color: #6c757d;
            font-size: 1.1rem;
        }
        
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            padding: 1.5rem;
            border: 1px solid #e9ecef;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }
        
        .card-icon.active {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        }
        
        .card-icon.history {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
        }
        
        .card-icon.due-soon {
            background: linear-gradient(135deg, #f39c12 0%, #d35400 100%);
        }
        
        .card-title {
            font-size: 1rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        
        .card-value {
            font-size: 2rem;
            font-weight: 700;
            color: #495057;
            margin-bottom: 0.25rem;
        }
        
        .card-footer {
            font-size: 0.9rem;
            color: #adb5bd;
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
        }
        
        .section-title {
            margin: 0;
            font-size: 1.3rem;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        
        .status-overdue {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }
        
        .status-due-soon {
            background: rgba(241, 196, 15, 0.1);
            color: #f39c12;
        }
        
        .status-normal {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }
        
        .status-returned {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
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
            font-size: 1.2rem; /* Match books page icon size */
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
            
            .dashboard-cards {
                grid-template-columns: 1fr;
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
    <?php include 'includes/member_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-exchange-alt"></i> My Borrowings</h1>
            <p>View your current borrowings and history</p>
        </div>
        
        <div class="dashboard-cards">
            <div class="card">
                <div class="card-icon active">
                    <i class="fas fa-book"></i>
                </div>
                <div class="card-title">Active Borrowings</div>
                <div class="card-value"><?php echo count($activeBorrowings); ?></div>
                <div class="card-footer">Books currently borrowed</div>
            </div>
            
            <div class="card">
                <div class="card-icon due-soon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="card-title">Due in 3 Days</div>
                <div class="card-value"><?php echo count($dueIn3Days); ?></div>
                <div class="card-footer">Books due soon</div>
            </div>
            
            <div class="card">
                <div class="card-icon history">
                    <i class="fas fa-history"></i>
                </div>
                <div class="card-title">Borrowing History</div>
                <div class="card-value"><?php echo count($borrowingHistory); ?></div>
                <div class="card-footer">Books returned</div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-book"></i> Active Borrowings</h2>
                <a href="borrowing_history.php" class="btn btn-primary">
                    <i class="fas fa-history"></i> View History
                </a>
            </div>
            
            <?php if (empty($activeBorrowings)): ?>
                <div class="no-data">
                    <i class="fas fa-book-open"></i>
                    <h3>No Active Borrowings</h3>
                    <p>You don't have any books currently borrowed</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Book Title</th>
                                <th>Author</th>
                                <th>Borrowed Date</th>
                                <th>Due Date</th>
                                <th>Days Remaining</th>
                                <th>Issued By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeBorrowings as $borrowing): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($borrowing['title']); ?></td>
                                    <td><?php echo htmlspecialchars($borrowing['author_name'] ?? 'Unknown'); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($borrowing['issue_date'])); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($borrowing['due_date'])); ?></td>
                                    <td>
                                        <?php if ($borrowing['days_remaining'] < 0): ?>
                                            <span class="status-badge status-overdue">
                                                <?php echo abs($borrowing['days_remaining']); ?> days overdue
                                            </span>
                                        <?php elseif ($borrowing['days_remaining'] <= 3): ?>
                                            <span class="status-badge status-due-soon">
                                                <?php echo $borrowing['days_remaining']; ?> days remaining
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-normal">
                                                <?php echo $borrowing['days_remaining']; ?> days remaining
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($borrowing['issued_by_first_name'] . ' ' . $borrowing['issued_by_last_name']); ?>
                                    </td>
                                    <td>
                                        <a href="view_borrowing.php?id=<?php echo $borrowing['id']; ?>" class="action-btn" title="View Borrowing Details">
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