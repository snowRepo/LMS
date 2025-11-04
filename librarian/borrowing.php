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

// Check if user is logged in and has librarian role
if (!is_logged_in() || $_SESSION['user_role'] !== 'librarian') {
    header('Location: ../login.php');
    exit;
}

// Check subscription status - redirect to expired page if subscription is not active
requireActiveSubscription();

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Initialize database connection
$db = Database::getInstance()->getConnection();

// Fetch active borrowings with search filter
$activeBorrowings = [];
try {
    if (!empty($search)) {
        $stmt = $db->prepare("
            SELECT b.*, bk.title, bk.isbn, 
                   CONCAT(m.first_name, ' ', m.last_name) as member_name,
                   DATEDIFF(b.due_date, CURDATE()) as days_remaining
            FROM borrowings b
            JOIN books bk ON b.book_id = bk.id
            JOIN users m ON b.member_id = m.id
            WHERE b.return_date IS NULL
            AND (bk.title LIKE ? OR bk.isbn LIKE ? OR m.first_name LIKE ? OR m.last_name LIKE ? OR m.user_id LIKE ?)
            ORDER BY b.due_date ASC
            LIMIT 10
        ");
        $searchParam = "%$search%";
        $stmt->execute([$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
    } else {
        $stmt = $db->query("
            SELECT b.*, bk.title, bk.isbn, 
                   CONCAT(m.first_name, ' ', m.last_name) as member_name,
                   DATEDIFF(b.due_date, CURDATE()) as days_remaining
            FROM borrowings b
            JOIN books bk ON b.book_id = bk.id
            JOIN users m ON b.member_id = m.id
            WHERE b.return_date IS NULL
            ORDER BY b.due_date ASC
            LIMIT 10
        ");
    }
    $activeBorrowings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching active borrowings: " . $e->getMessage());
}

$pageTitle = 'Borrowing';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - LMS</title>
    
    <!-- Include CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/toast.css">
    
    <style>
        :root {
            --primary-color: #3498DB;
            --primary-dark: #2980B9;
            --success-color: #2ECC71;
            --danger-color: #e74c3c;
            --warning-color: #F39C12;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-600: #6c757d;
            --gray-800: #343a40;
            --border-radius: 8px;
            --box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            color: #333;
        }
        
        .container {
            max-width: 1400px;
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
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 1.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .card-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 1rem;
            width: 100%;
        }
        
        .card-actions {
            margin-top: auto;
            width: 100%;
            display: flex;
            justify-content: center;
        }
        
        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.5rem;
            color: white;
        }
        
        .card-icon.primary { background: var(--primary-color); }
        .card-icon.warning { background: var(--warning-color); }
        .card-icon.danger { background: var(--danger-color); }
        
        .card-title {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin: 0;
        }
        
        .card-value {
            font-size: 1.8rem;
            font-weight: 600;
            margin: 0.25rem 0 0;
            color: #2c3e50;
        }
        
        .section {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .section-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-title {
            margin: 0;
            font-size: 1.2rem;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(52, 152, 219, 0.2);
        }
        
        .btn:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, #2573A7 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(52, 152, 219, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            box-shadow: none;
        }
        
        .btn-outline:hover {
            background: rgba(52, 152, 219, 0.1);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(52, 152, 219, 0.1);
        }
        
        .search-bar {
            display: flex;
            gap: 1rem;
            margin: 1.5rem auto;
            max-width: 600px;
            justify-content: center;
        }
        
        .search-input {
            flex: 1;
            padding: 0.75rem;
            border: 1.5px solid #95A5A6;
            border-radius: 8px;
            font-size: 1rem;
            max-width: 400px;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15);
        }
        
        .search-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(52, 152, 219, 0.2);
        }
        
        .search-btn:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, #2573A7 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(52, 152, 219, 0.3);
        }
        
        .search-btn-text {
            display: none;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 1rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid var(--gray-200);
            vertical-align: middle;
        }
        
        .table th {
            font-weight: 600;
            color: var(--gray-800);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .table tr:last-child td {
            border-bottom: none;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-active { background: #1b5e20; color: white; }
        .status-due { background: #fff3e0; color: #ef6c00; }
        .status-overdue { background: #ffebee; color: #d32f2f; }
        
        .text-muted { color: var(--gray-600); }
        .text-danger { color: var(--danger-color); }
        .text-warning { color: var(--warning-color); }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }
        
        .book-info {
            text-align: center;
        }
        
        .book-info .text-muted {
            text-align: center;
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
        
        .btn-renew {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-return {
            background: #28a745;
            color: white;
        }
        
        /* Hover effects */
        .btn-view:hover {
            background: #2980B9;
        }
        
        .btn-renew:hover {
            background: #e0a800;
        }
        
        .btn-return:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <?php include 'includes/librarian_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-exchange-alt"></i> Borrowing</h1>
            <p>Manage book borrowings and returns in one place</p>
        </div>
        
        <!-- Active Borrowings Section -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-book-reader"></i> Active Borrowings</h2>
                <div style="display: flex; gap: 1rem;">
                    <a href="process_borrowing.php" class="btn">
                        <i class="fas fa-plus"></i> New Borrowing
                    </a>
                    <a href="borrowing_history.php" class="btn btn-outline">
                        <i class="fas fa-history"></i> Borrowing History
                    </a>
                </div>
            </div>
            
            <!-- Search Bar -->
            <div class="search-bar">
                <form method="GET" style="display: flex; width: 100%; max-width: 500px; gap: 1rem;">
                    <input type="text" 
                           name="search" 
                           class="search-input" 
                           placeholder="Search by book title, ISBN, member name or ID..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                        <span class="search-btn-text">Search</span>
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="borrowing.php" class="btn btn-outline" style="padding: 0.75rem 1rem;">
                            <i class="fas fa-times"></i>
                            <span class="search-btn-text">Clear</span>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="section-body">
                <?php if (!empty($activeBorrowings)): ?>
                    <div style="overflow-x: auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Book</th>
                                    <th>Borrower</th>
                                    <th>Borrowed Date</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activeBorrowings as $borrowing): 
                                    $dueDate = new DateTime($borrowing['due_date']);
                                    $today = new DateTime();
                                    $daysRemaining = $borrowing['days_remaining'];
                                    
                                    $statusClass = 'status-active';
                                    $statusText = 'Active';
                                    
                                    if ($daysRemaining < 0) {
                                        $statusClass = 'status-overdue';
                                        $statusText = 'Overdue';
                                    } elseif ($daysRemaining == 0) {
                                        $statusClass = 'status-due';
                                        $statusText = 'Due Today';
                                    } elseif ($daysRemaining <= 2) {
                                        $statusClass = 'status-due';
                                        $statusText = 'Due Soon';
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <div class="book-info">
                                                <div><strong><?php echo htmlspecialchars($borrowing['title']); ?></strong></div>
                                                <div class="text-muted"><?php echo htmlspecialchars($borrowing['isbn']); ?></div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($borrowing['member_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($borrowing['issue_date'])); ?></td>
                                        <td>
                                            <?php 
                                                echo date('M d, Y', strtotime($borrowing['due_date']));
                                                if ($daysRemaining < 0) {
                                                    echo ' <span class="text-danger">(' . abs($daysRemaining) . ' days late)</span>';
                                                } elseif ($daysRemaining == 0) {
                                                    echo ' <span class="text-warning">(Today)</span>';
                                                } elseif ($daysRemaining <= 2) {
                                                    echo ' <span class="text-warning">(' . $daysRemaining . ' days left)</span>';
                                                }
                                            ?>
                                        </td>
                                        <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn btn-return" title="Mark as Returned" data-id="<?php echo $borrowing['id']; ?>">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                                <button class="action-btn btn-renew" title="Renew" data-id="<?php echo $borrowing['id']; ?>">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                                <a href="view_borrowing.php?id=<?php echo $borrowing['id']; ?>" class="action-btn btn-view" title="View Details">
                                                    <i class="fas fa-file-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="padding: 2rem; text-align: center; color: var(--gray-600);">
                        <i class="fas fa-inbox" style="font-size: 3rem; opacity: 0.5; margin-bottom: 1rem;"></i>
                        <p style="margin: 0;"><?php echo !empty($search) ? 'No active borrowings found matching your search.' : 'No active borrowings found'; ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Toast Notification Functions
        function showToast(message, type = 'info') {
            // Create toast container if it doesn't exist
            let toastContainer = document.getElementById('toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toast-container';
                document.body.appendChild(toastContainer);
            }
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            // Set icon based on type
            let iconClass = 'fa-info-circle';
            if (type === 'success') iconClass = 'fa-check-circle';
            else if (type === 'error') iconClass = 'fa-exclamation-circle';
            else if (type === 'warning') iconClass = 'fa-exclamation-triangle';
            
            toast.innerHTML = `
                <div class="toast-content">
                    <div class="toast-icon">
                        <i class="fas ${iconClass}"></i>
                    </div>
                    <div class="toast-message">${message}</div>
                    <button class="toast-close">&times;</button>
                </div>
            `;
            
            // Add toast to container
            toastContainer.appendChild(toast);
            
            // Show toast with animation
            setTimeout(() => {
                toast.classList.add('show');
            }, 10);
            
            // Add close button event listener
            const closeBtn = toast.querySelector('.toast-close');
            closeBtn.addEventListener('click', () => {
                toast.classList.remove('show');
                toast.classList.add('hide');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            });
            
            // Auto hide toast after 5 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.classList.remove('show');
                    toast.classList.add('hide');
                    setTimeout(() => {
                        if (toast.parentNode) {
                            toast.parentNode.removeChild(toast);
                        }
                    }, 300);
                }
            }, 5000);
        }
        
        
        // Function to mark book as returned
        function markAsReturned(borrowingId) {
            // Show browser confirmation dialog
            if (!confirm('Are you sure you want to mark this book as returned?')) {
                return; // User cancelled the action
            }
            
            // Redirect to process return with borrowing ID
            window.location.href = 'process_return.php?borrowing_id=' + borrowingId;
        }
        
        // Function to handle renew book action
        function renewBook(borrowingId) {
            if (confirm('Are you sure you want to renew this borrowing?')) {
                // AJAX call to process renewal
                $.post('process_renewal.php', { borrowing_id: borrowingId })
                    .done(function(response) {
                        const result = JSON.parse(response);
                        if (result.success) {
                            // Reload the page or update the UI
                            location.reload();
                        } else {
                            alert('Error: ' + result.message);
                        }
                    })
                    .fail(function() {
                        alert('Error processing renewal. Please try again.');
                    });
            }
        }
        
        // Attach event listeners when the document is ready
        $(document).ready(function() {
            // Show toast notification if success or error parameter is present
            const urlParams = new URLSearchParams(window.location.search);
            const success = urlParams.get('success');
            const error = urlParams.get('error');
            
            if (success) {
                showToast(success, 'success');
                // Remove the success parameter from URL to prevent repeated notifications
                urlParams.delete('success');
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({}, document.title, newUrl);
            }
            
            if (error) {
                showToast(error, 'error');
                // Remove the error parameter from URL to prevent repeated notifications
                urlParams.delete('error');
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({}, document.title, newUrl);
            }
            
            // Handle mark as returned button clicks
            $('.btn-return').click(function() {
                const borrowingId = $(this).data('id');
                markAsReturned(borrowingId);
            });
            
            // Handle renew book button clicks
            $('.btn-renew').click(function() {
                const borrowingId = $(this).data('id');
                renewBook(borrowingId);
            });
            

        });
    </script>
</body>
</html>