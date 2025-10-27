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

// Get borrowing ID from URL parameter
$borrowingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (empty($borrowingId)) {
    header('Location: borrowing.php');
    exit;
}

$error = '';
$success = '';

// Fetch borrowing details
try {
    $db = Database::getInstance()->getConnection();
    
    // Get borrowing details with book and member information
    $stmt = $db->prepare("
        SELECT b.*, 
               bk.title as book_title, 
               bk.isbn, 
               bk.author_name as book_author,
               bk.cover_image,
               bk.category_id,
               c.name as category_name,
               CONCAT(m.first_name, ' ', m.last_name) as member_name,
               m.email as member_email,
               m.phone as member_phone,
               m.user_id as member_id
        FROM borrowings b
        JOIN books bk ON b.book_id = bk.id
        JOIN users m ON b.member_id = m.id
        LEFT JOIN categories c ON bk.category_id = c.id
        WHERE b.id = ?
    ");
    $stmt->execute([$borrowingId]);
    $borrowing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$borrowing) {
        header('Location: borrowing.php?error=Borrowing record not found');
        exit;
    }
    
    $pageTitle = 'Borrowing Details - ' . $borrowing['book_title'];
    
} catch (Exception $e) {
    header('Location: borrowing.php?error=' . urlencode($e->getMessage()));
    exit;
}

// Check for success message from redirect
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - LMS</title>
    
    <!-- Include CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/toast.css">
    
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
        
        .content-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid #e9ecef;
        }
        
        .borrowing-details-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }
        
        @media (max-width: 768px) {
            .borrowing-details-container {
                grid-template-columns: 1fr;
            }
        }
        
        .book-cover {
            text-align: center;
        }
        
        .book-cover-img {
            width: 100%;
            max-width: 300px;
            height: 400px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin: 0 auto;
        }
        
        .book-cover-placeholder {
            width: 100%;
            max-width: 300px;
            height: 400px;
            background: #3498db;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4rem;
            margin: 0 auto;
        }
        
        .book-info {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .book-title {
            font-size: 1.8rem;
            color: #212529;
            margin-bottom: 0.5rem;
        }
        
        .book-author {
            font-size: 1.2rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }
        
        .book-meta {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
        }
        
        .meta-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.25rem;
        }
        
        .meta-value {
            font-weight: 500;
            color: #495057;
        }
        
        .borrowing-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin: 1rem 0;
            border: 1px solid #e9ecef;
        }
        
        .borrowing-info h3 {
            color: #495057;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 0.75rem;
        }
        
        .info-label {
            font-weight: 600;
            width: 180px;
            color: #495057;
        }
        
        .info-value {
            flex: 1;
            color: #6c757d;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .status-active { 
            background: #1b5e20; 
            color: white; 
        }
        
        .status-overdue { 
            background: #ffebee; 
            color: #c62828; 
        }
        
        .status-due { 
            background: #fff3e0; 
            color: #ef6c00; 
        }
        
        .status-returned { 
            background: #c8e6c9; 
            color: #2e7d32; 
        }
        
        .notes-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            border-left: 3px solid #3498db;
        }
        
        .notes-header {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .notes-content {
            color: #6c757d;
            line-height: 1.5;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(52, 152, 219, 0.3);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9 0%, #2573A7 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(52, 152, 219, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(40, 167, 69, 0.3);
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(40, 167, 69, 0.4);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(231, 76, 60, 0.3);
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #c0392b 0%, #a5281b 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(231, 76, 60, 0.4);
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
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
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .form-control:focus {
            border-color: #3498db;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
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
        
        <div class="content-card">
            <div class="borrowing-details-container">
                <div class="book-cover">
                    <?php if (!empty($borrowing['cover_image']) && file_exists('../uploads/books/' . $borrowing['cover_image'])): ?>
                        <img src="../uploads/books/<?php echo htmlspecialchars($borrowing['cover_image']); ?>" alt="<?php echo htmlspecialchars($borrowing['book_title']); ?>" class="book-cover-img">
                    <?php else: ?>
                        <div class="book-cover-placeholder">
                            <i class="fas fa-book"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="book-info">
                    <div>
                        <h1 class="book-title"><?php echo htmlspecialchars($borrowing['book_title']); ?></h1>
                        <div class="book-author">by <?php echo htmlspecialchars($borrowing['book_author'] ?? 'Unknown Author'); ?></div>
                        
                        <?php if (!empty($borrowing['category_name'])): ?>
                            <span style="background: rgba(52, 152, 219, 0.1); color: #3498db; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.9rem;">
                                <?php echo htmlspecialchars($borrowing['category_name']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="book-meta">
                        <div class="meta-item">
                            <span class="meta-label">ISBN</span>
                            <span class="meta-value"><?php echo !empty($borrowing['isbn']) ? htmlspecialchars($borrowing['isbn']) : 'N/A'; ?></span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">Borrowed Date</span>
                            <span class="meta-value"><?php echo date('M j, Y', strtotime($borrowing['issue_date'])); ?></span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">Due Date</span>
                            <span class="meta-value"><?php echo date('M j, Y', strtotime($borrowing['due_date'])); ?></span>
                        </div>
                        
                        <?php if (!empty($borrowing['return_date'])): ?>
                        <div class="meta-item">
                            <span class="meta-label">Return Date</span>
                            <span class="meta-value"><?php echo date('M j, Y', strtotime($borrowing['return_date'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="borrowing-info">
                        <h3><i class="fas fa-exchange-alt"></i> Borrowing Information</h3>
                        
                        <div class="info-row">
                            <div class="info-label">Status:</div>
                            <div class="info-value">
                                <?php 
                                $statusClass = '';
                                $statusText = '';
                                switch ($borrowing['status']) {
                                    case 'active':
                                        $statusClass = 'status-active';
                                        $statusText = 'Active';
                                        break;
                                    case 'returned':
                                        $statusClass = 'status-returned';
                                        $statusText = 'Returned';
                                        break;
                                    default:
                                        $statusClass = 'status-active';
                                        $statusText = ucfirst($borrowing['status']);
                                }
                                
                                // Check if overdue
                                if ($borrowing['status'] === 'active') {
                                    $dueDate = new DateTime($borrowing['due_date']);
                                    $today = new DateTime();
                                    if ($dueDate < $today) {
                                        $statusClass = 'status-overdue';
                                        $statusText = 'Overdue';
                                    } else if ($dueDate == $today) {
                                        $statusClass = 'status-due';
                                        $statusText = 'Due Today';
                                    } else if ($dueDate->diff($today)->days <= 2) {
                                        $statusClass = 'status-due';
                                        $statusText = 'Due Soon';
                                    }
                                }
                                ?>
                                <span class="status-badge <?php echo $statusClass; ?>">
                                    <?php echo htmlspecialchars($statusText); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="info-row">
                            <div class="info-label">Member:</div>
                            <div class="info-value"><?php echo htmlspecialchars($borrowing['member_name']); ?></div>
                        </div>
                        
                        <?php if (!empty($borrowing['member_email'])): ?>
                            <div class="info-row">
                                <div class="info-label">Email:</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars($borrowing['member_email']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($borrowing['member_phone'])): ?>
                            <div class="info-row">
                                <div class="info-label">Phone:</div>
                                <div class="info-value"><?php echo htmlspecialchars($borrowing['member_phone']); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="info-row">
                            <div class="info-label">Member ID:</div>
                            <div class="info-value"><?php echo htmlspecialchars($borrowing['member_id']); ?></div>
                        </div>
                    </div>
                    
                    <?php if (!empty($borrowing['notes'])): ?>
                    <div class="notes-section">
                        <div class="notes-header">
                            <i class="fas fa-sticky-note"></i> Borrowing Notes
                        </div>
                        <div class="notes-content">
                            <?php echo nl2br(htmlspecialchars($borrowing['notes'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <?php if ($borrowing['status'] === 'active'): ?>
                            <button class="btn btn-success" data-id="<?php echo $borrowingId; ?>">
                                <i class="fas fa-undo"></i> Mark as Returned
                            </button>
                            
                            <button class="btn btn-primary" onclick="renewBook(<?php echo $borrowingId; ?>)">
                                <i class="fas fa-sync-alt"></i> Renew Borrowing
                            </button>
                        <?php endif; ?>
                        
                        <a href="borrowing.php" class="btn btn-primary">
                            <i class="fas fa-list"></i> Back to List
                        </a>
                    </div>

                </div>
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
        
        // Initialize when document is ready
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
            
            // Add click event to the Mark as Returned button
            $('button.btn-success').click(function() {
                const borrowingId = <?php echo $borrowingId; ?>;
                markAsReturned(borrowingId);
            });
            

        });
    </script>
</body>
</html>
