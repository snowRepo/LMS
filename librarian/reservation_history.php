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

// Initialize database connection
$db = Database::getInstance()->getConnection();

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch all reservations with search filter
$reservations = [];
try {
    if (!empty($search)) {
        $stmt = $db->prepare("
            SELECT r.*, 
                   bk.title, 
                   bk.isbn, 
                   bk.author_name as author,
                   CONCAT(m.first_name, ' ', m.last_name) as member_name,
                   CASE 
                       WHEN r.status = 'pending' THEN 'Pending'
                       WHEN r.status = 'approved' THEN 'Approved'
                       WHEN r.status = 'rejected' THEN 'Rejected'
                       WHEN r.status = 'cancelled' THEN 'Cancelled'
                       WHEN r.status = 'fulfilled' THEN 'Fulfilled'
                       WHEN r.status = 'expired' THEN 'Expired'
                       ELSE 'Unknown'
                   END as status_text,
                   CASE 
                       WHEN r.status = 'pending' THEN 'status-pending'
                       WHEN r.status = 'approved' THEN 'status-approved'
                       WHEN r.status = 'rejected' THEN 'status-rejected'
                       WHEN r.status = 'cancelled' THEN 'status-cancelled'
                       WHEN r.status = 'fulfilled' THEN 'status-fulfilled'
                       WHEN r.status = 'expired' THEN 'status-expired'
                       ELSE 'status-unknown'
                   END as status_class
            FROM reservations r
            JOIN books bk ON r.book_id = bk.id
            JOIN users m ON r.member_id = m.id
            WHERE (bk.title LIKE ? OR bk.isbn LIKE ? OR bk.author_name LIKE ? OR m.first_name LIKE ? OR m.last_name LIKE ? OR m.user_id LIKE ?)
            ORDER BY r.reservation_date DESC
            LIMIT 50
        ");
        $searchParam = "%$search%";
        $stmt->execute([$searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
    } else {
        $stmt = $db->query("
            SELECT r.*, 
                   bk.title, 
                   bk.isbn, 
                   bk.author_name as author,
                   CONCAT(m.first_name, ' ', m.last_name) as member_name,
                   CASE 
                       WHEN r.status = 'pending' THEN 'Pending'
                       WHEN r.status = 'approved' THEN 'Approved'
                       WHEN r.status = 'rejected' THEN 'Rejected'
                       WHEN r.status = 'cancelled' THEN 'Cancelled'
                       WHEN r.status = 'fulfilled' THEN 'Fulfilled'
                       WHEN r.status = 'expired' THEN 'Expired'
                       ELSE 'Unknown'
                   END as status_text,
                   CASE 
                       WHEN r.status = 'pending' THEN 'status-pending'
                       WHEN r.status = 'approved' THEN 'status-approved'
                       WHEN r.status = 'rejected' THEN 'status-rejected'
                       WHEN r.status = 'cancelled' THEN 'status-cancelled'
                       WHEN r.status = 'fulfilled' THEN 'status-fulfilled'
                       WHEN r.status = 'expired' THEN 'status-expired'
                       ELSE 'status-unknown'
                   END as status_class
            FROM reservations r
            JOIN books bk ON r.book_id = bk.id
            JOIN users m ON r.member_id = m.id
            ORDER BY r.reservation_date DESC
            LIMIT 50
        ");
    }
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching reservations: " . $e->getMessage());
}

$pageTitle = 'Reservation History';
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
            --info-color: #3498DB;
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .page-header h1 {
            color: #2c3e50;
            margin: 0 0 0.5rem;
            font-size: 1.8rem;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
        }
        
        .page-header p {
            color: #7f8c8d;
            margin: 0;
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
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-outline:hover {
            background: rgba(52, 152, 219, 0.1);
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
        }
        
        .table th {
            font-weight: 600;
            color: var(--gray-800);
            font-size: 0.85rem;
            text-transform: uppercase;
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
            text-align: center;
        }
        
        .status-pending { 
            background: #fff3e0; 
            color: #ef6c00; 
        }
        
        .status-approved { 
            background: #1e7e34; 
            color: white; 
        }
        
        .status-rejected { 
            background: #ffebee; 
            color: #c62828; 
        }
        
        .status-cancelled { 
            background: #fafafa; 
            color: #9e9e9e; 
        }
        
        .status-fulfilled { 
            background: #e3f2fd; 
            color: #1565c0; 
        }
        
        .status-expired { 
            background: #ffecb3; 
            color: #ff8f00; 
        }
        
        .text-muted { color: var(--gray-600); }
        .text-danger { color: var(--danger-color); }
        .text-warning { color: var(--warning-color); }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }
        
        .action-btn {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
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
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 0.75rem 1rem;
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
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(52, 152, 219, 0.2);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, #2573A7 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.3);
        }
        
        .book-info {
            text-align: center;
        }
        
        .book-info .text-muted {
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .table th,
            .table td {
                padding: 0.75rem;
            }
            
            .table-responsive {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/librarian_navbar.php'; ?>
    
    <!-- Toast Container -->
    <div id="toast-container"></div>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-history"></i> Reservation History</h1>
            <p>View all reservation requests and their current status</p>
        </div>
        
        <!-- All Reservations Section -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-list"></i> All Reservations</h2>
                <a href="reservations.php" class="btn btn-primary">
                    <i class="fas fa-clock"></i> View Pending
                </a>
            </div>
            
            <!-- Search Bar -->
            <div class="search-bar">
                <form method="GET" style="display: flex; width: 100%; max-width: 500px; gap: 1rem;">
                    <input type="text" 
                           name="search" 
                           class="search-input" 
                           placeholder="Search by book title, author, member name or ID..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                        <span class="search-btn-text">Search</span>
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="reservation_history.php" class="btn btn-outline" style="padding: 0.75rem 1rem;">
                            <i class="fas fa-times"></i>
                            <span class="search-btn-text">Clear</span>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="section-body">
                <?php if (!empty($reservations)): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Book</th>
                                    <th>Requested By</th>
                                    <th>Reservation Date</th>
                                    <th>Expiry Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations as $reservation): 
                                    $reservationDate = new DateTime($reservation['reservation_date']);
                                    $expiryDate = new DateTime($reservation['expiry_date']);
                                    $isExpired = $reservation['status'] === 'pending' && $expiryDate < new DateTime();
                                ?>
                                    <tr>
                                        <td class="book-info">
                                            <div><strong><?php echo htmlspecialchars($reservation['title']); ?></strong></div>
                                            <div class="text-muted">
                                                <?php echo htmlspecialchars($reservation['author']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($reservation['member_name']); ?></td>
                                        <td><?php echo $reservationDate->format('M j, Y'); ?></td>
                                        <td class="<?php echo $isExpired ? 'text-danger' : ''; ?>">
                                            <?php echo $expiryDate->format('M j, Y'); ?>
                                            <?php if ($isExpired): ?>
                                                <div class="small text-danger">(Expired)</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $reservation['status_class']; ?>">
                                                <?php echo htmlspecialchars($reservation['status_text']); ?>
                                                <?php if ($isExpired): ?>
                                                    <br><span class="text-danger">(Expired)</span>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="view_reservation.php?id=<?php echo $reservation['id']; ?>" class="btn btn-primary action-btn" title="View Details">
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
                        <p style="margin: 0;">No reservations found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Global toast notification function
        function showToast(message, type = 'info', duration = 5000) {
            const container = document.getElementById('toast-container');
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            // Add icon based on type
            let icon = 'info-circle';
            if (type === 'success') icon = 'check-circle';
            if (type === 'error') icon = 'exclamation-circle';
            if (type === 'warning') icon = 'exclamation-triangle';
            
            toast.innerHTML = `
                <div class="toast-content">
                    <i class="fas fa-${icon} toast-icon"></i>
                    <div class="toast-message">${message}</div>
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
        
        // Function to view reservation details
        function viewReservation(reservationId) {
            // Redirect to the view reservation page
            window.location.href = 'view_reservation.php?id=' + reservationId;
        }
        
        // Initialize any necessary event listeners when the document is ready
        $(document).ready(function() {
            // Add any additional initialization code here
        });
    </script>
</body>
</html>