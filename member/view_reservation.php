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

// Check if user is logged in and has member role
if (!is_logged_in() || $_SESSION['user_role'] !== 'member') {
    header('Location: ../login.php');
    exit;
}

// Get reservation ID from URL parameter
$reservationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (empty($reservationId)) {
    header('Location: reservations.php');
    exit;
}

// Initialize database connection
$db = Database::getInstance()->getConnection();

// Fetch reservation details
try {
    $stmt = $db->prepare("
        SELECT r.*, 
               bk.title as book_title,
               bk.subtitle as book_subtitle,
               bk.author_name as book_author,
               bk.isbn as book_isbn,
               bk.description as book_description,
               bk.cover_image as book_cover,
               c.name as category_name
        FROM reservations r
        JOIN books bk ON r.book_id = bk.id
        LEFT JOIN categories c ON bk.category_id = c.id
        WHERE r.id = ? AND r.member_id = ?
    ");
    $stmt->execute([$reservationId, $_SESSION['user_id']]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reservation) {
        header('Location: reservations.php?error=Reservation not found');
        exit;
    }
    
    $pageTitle = 'Reservation Details - ' . $reservation['book_title'];
} catch (Exception $e) {
    header('Location: reservations.php?error=Error fetching reservation details');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - LMS</title>
    
    <!-- Font Awesome -->
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
            color: #212529;
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
        
        .reservation-details-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }
        
        @media (max-width: 768px) {
            .reservation-details-container {
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
        
        .reservation-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin: 1rem 0;
            border: 1px solid #e9ecef;
        }
        
        .reservation-info h3 {
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
            background: #FFEBEE; 
            color: #c62828; 
        }
        
        .status-fulfilled { 
            background: #e3f2fd; 
            color: #1565c0; 
        }
        
        .status-borrowed { 
            background: #b2ebf2; 
            color: #00796b; 
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
    <?php include 'includes/member_navbar.php'; ?>
    
    <!-- Toast Container -->
    <div id="toast-container"></div>
    
    <div class="container">
        <a href="reservations.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Reservations
        </a>
        
        <div class="content-card">
            <div class="reservation-details-container">
                <div class="book-cover">
                    <?php if (!empty($reservation['book_cover']) && file_exists('../uploads/books/' . $reservation['book_cover'])): ?>
                        <img src="../uploads/books/<?php echo htmlspecialchars($reservation['book_cover']); ?>" alt="<?php echo htmlspecialchars($reservation['book_title']); ?>" class="book-cover-img">
                    <?php else: ?>
                        <div class="book-cover-placeholder">
                            <i class="fas fa-book"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="book-info">
                    <div>
                        <h1 class="book-title">
                            <?php echo htmlspecialchars($reservation['book_title']); ?>
                            <?php if (!empty($reservation['book_subtitle'])): ?>
                                - <?php echo htmlspecialchars($reservation['book_subtitle']); ?>
                            <?php endif; ?>
                        </h1>
                        <div class="book-author">by <?php echo htmlspecialchars($reservation['book_author'] ?? 'Unknown Author'); ?></div>
                        
                        <?php if (!empty($reservation['category_name'])): ?>
                            <span style="background: rgba(52, 152, 219, 0.1); color: #3498db; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.9rem;">
                                <?php echo htmlspecialchars($reservation['category_name']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="book-meta">
                        <div class="meta-item">
                            <span class="meta-label">ISBN</span>
                            <span class="meta-value"><?php echo !empty($reservation['book_isbn']) ? htmlspecialchars($reservation['book_isbn']) : 'N/A'; ?></span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">Reservation Date</span>
                            <span class="meta-value"><?php echo date('M j, Y', strtotime($reservation['reservation_date'])); ?></span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">Expiry Date</span>
                            <span class="meta-value"><?php echo date('M j, Y', strtotime($reservation['expiry_date'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="reservation-info">
                        <h3><i class="fas fa-calendar-check"></i> Reservation Information</h3>
                        
                        <div class="info-row">
                            <div class="info-label">Status:</div>
                            <div class="info-value">
                                <?php 
                                $statusClass = '';
                                $statusText = '';
                                switch ($reservation['status']) {
                                    case 'pending':
                                        $statusClass = 'status-pending';
                                        $statusText = 'Pending';
                                        break;
                                    case 'approved':
                                        $statusClass = 'status-approved';
                                        $statusText = 'Approved';
                                        break;
                                    case 'rejected':
                                        $statusClass = 'status-rejected';
                                        $statusText = 'Rejected';
                                        break;
                                    case 'cancelled':
                                        $statusClass = 'status-cancelled';
                                        $statusText = 'Cancelled';
                                        break;
                                    case 'fulfilled':
                                        $statusClass = 'status-fulfilled';
                                        $statusText = 'Fulfilled';
                                        break;
                                    case 'borrowed':
                                        $statusClass = 'status-borrowed';
                                        $statusText = 'Borrowed';
                                        break;
                                    case 'returned':
                                        $statusClass = 'status-returned';
                                        $statusText = 'Returned';
                                        break;
                                    default:
                                        $statusClass = 'status-pending';
                                        $statusText = ucfirst($reservation['status']);
                                }
                                ?>
                                <span class="status-badge <?php echo $statusClass; ?>">
                                    <?php echo htmlspecialchars($statusText); ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php if (!empty($reservation['notes'])): ?>
                            <div class="notes-section">
                                <div class="notes-header">
                                    <i class="fas fa-sticky-note"></i> Your Notes
                                </div>
                                <div class="notes-content">
                                    <?php echo htmlspecialchars($reservation['notes']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($reservation['librarian_notes'])): ?>
                            <div class="notes-section" style="border-left-color: #f39c12;">
                                <div class="notes-header">
                                    <i class="fas fa-comment"></i> Librarian Response
                                </div>
                                <div class="notes-content">
                                    <?php echo htmlspecialchars($reservation['librarian_notes']); ?>
                                </div>
                            </div>
                        <?php elseif (!empty($reservation['rejection_reason'])): ?>
                            <div class="notes-section" style="border-left-color: #f39c12;">
                                <div class="notes-header">
                                    <i class="fas fa-comment"></i> Librarian Response
                                </div>
                                <div class="notes-content">
                                    <?php echo htmlspecialchars($reservation['rejection_reason']); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>
                    
                    <div class="action-buttons">
                        <?php if ($reservation['status'] === 'pending'): ?>
                            <button class="btn btn-danger" onclick="cancelReservation(<?php echo $reservation['id']; ?>)">
                                <i class="fas fa-times-circle"></i> Cancel Reservation
                            </button>
                        <?php endif; ?>
                        
                        <a href="reservations.php" class="btn btn-primary">
                            <i class="fas fa-list"></i> View All Reservations
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
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
        
        function cancelReservation(reservationId) {
            if (confirm('Are you sure you want to cancel this reservation?')) {
                fetch('process_cancel_reservation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'reservation_id=' + reservationId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Redirect to reservations page with success message
                        window.location.href = 'reservations.php?success=' + encodeURIComponent('Reservation cancelled successfully!');
                    } else {
                        // Show error toast
                        showToast('Error: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('An error occurred while cancelling the reservation.', 'error');
                });
            }
        }
    </script>
</body>
</html>