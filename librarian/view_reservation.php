<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';
require_once '../includes/ReservationService.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has librarian role
if (!is_logged_in() || $_SESSION['user_role'] !== 'librarian') {
    header('Location: ../login.php');
    exit;
}

// Get reservation ID from URL parameter
$reservationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (empty($reservationId)) {
    header('Location: reservations.php');
    exit;
}

// Initialize services
$reservationService = new ReservationService();
$error = '';
$success = '';

// Handle form submission for updating reservation status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $notes = filter_input(INPUT_POST, 'librarian_notes', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    
    try {
        if ($action === 'approve') {
            $reservationService->approve($reservationId, $_SESSION['user_id'], $notes);
            $toastMessage = 'Reservation approved successfully!';
            $toastType = 'success';
        } elseif ($action === 'reject') {
            // For rejection, we'll save the notes to rejection_reason column
            $reservationService->reject($reservationId, $_SESSION['user_id'], $notes);
            $toastMessage = 'Reservation rejected successfully!';
            $toastType = 'success';
        } else {
            throw new Exception('Invalid action');
        }
        
    } catch (Exception $e) {
        $toastMessage = 'Error: ' . $e->getMessage();
        $toastType = 'error';
    }
}

// Fetch reservation details
try {
    $reservation = $reservationService->getReservation($reservationId);
    
    if (!$reservation) {
        header('Location: reservations.php?error=Reservation not found');
        exit;
    }
    
    $pageTitle = 'Reservation Details - ' . $reservation['book_title'];
    
} catch (Exception $e) {
    header('Location: reservations.php?error=' . urlencode($e->getMessage()));
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
        
        .status-fulfilled { 
            background: #e3f2fd; 
            color: #1565c0; 
        }
        
        .status-cancelled { 
            background: #FFEBEE; 
            color: #c62828; 
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
        
        .rejection-reason {
            margin-top: 1rem;
            display: none;
        }
        
        .rejection-reason.show {
            display: block;
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
        <a href="reservations.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Reservations
        </a>
        
        <div class="content-card">
            <div class="reservation-details-container">
                <div class="book-cover">
                    <?php if (!empty($reservation['cover_image']) && file_exists('../uploads/books/' . $reservation['cover_image'])): ?>
                        <img src="../uploads/books/<?php echo htmlspecialchars($reservation['cover_image']); ?>" alt="<?php echo htmlspecialchars($reservation['book_title']); ?>" class="book-cover-img">
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
                            <span class="meta-value"><?php echo !empty($reservation['isbn']) ? htmlspecialchars($reservation['isbn']) : 'N/A'; ?></span>
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
                                    case 'expired':
                                        $statusClass = 'status-expired';
                                        $statusText = 'Expired';
                                        break;
                                    case 'cancelled':
                                        $statusClass = 'status-cancelled';
                                        $statusText = 'Cancelled';
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
                        
                        <div class="info-row">
                            <div class="info-label">Member:</div>
                            <div class="info-value"><?php echo htmlspecialchars($reservation['member_name']); ?></div>
                        </div>
                        
                        <?php if (!empty($reservation['member_email'])): ?>
                            <div class="info-row">
                                <div class="info-label">Email:</div>
                                <div class="info-value">
                                    <?php echo htmlspecialchars($reservation['member_email']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($reservation['member_phone'])): ?>
                            <div class="info-row">
                                <div class="info-label">Phone:</div>
                                <div class="info-value"><?php echo htmlspecialchars($reservation['member_phone']); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($reservation['notes'])): ?>
                            <div class="notes-section" style="border-left-color: #3498db;">
                                <div class="notes-header">
                                    <i class="fas fa-sticky-note"></i> Member Notes
                                </div>
                                <div class="notes-content">
                                    <?php echo nl2br(htmlspecialchars($reservation['notes'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($reservation['librarian_notes'])): ?>
                            <div class="notes-section">
                                <div class="notes-header">
                                    <i class="fas fa-comment"></i> Librarian Notes
                                </div>
                                <div class="notes-content">
                                    <?php echo nl2br(htmlspecialchars($reservation['librarian_notes'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($reservation['rejection_reason'])): ?>
                            <div class="notes-section" style="border-left-color: #e74c3c;">
                                <div class="notes-header">
                                    <i class="fas fa-exclamation-circle"></i> Rejection Reason
                                </div>
                                <div class="notes-content">
                                    <?php echo nl2br(htmlspecialchars($reservation['rejection_reason'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <?php if ($reservation['status'] === 'pending'): ?>
                            <form method="post" class="d-inline" style="width: 100%;">
                                <input type="hidden" name="action" value="approve">
                                
                                <div class="form-group">
                                    <label for="librarian_notes" class="form-label">Notes (Optional):</label>
                                    <textarea name="librarian_notes" id="librarian_notes" class="form-control" rows="2"
                                        placeholder="Add any notes for the member..."></textarea>
                                </div>
                                
                                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check"></i> Approve Reservation
                                    </button>
                                    
                                    <button type="submit" name="action" value="reject" class="btn btn-danger">
                                        <i class="fas fa-times"></i> Reject Reservation
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                        
                        <?php if ($reservation['status'] === 'approved'): ?>
                            <!-- Removed "Mark as Fulfilled" button as fulfillment is handled automatically during borrowing process -->
                        <?php endif; ?>
                        
                        <a href="reservations.php" class="btn btn-primary">
                            <i class="fas fa-list"></i> Back to List
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Toast notification function
        function showToast(message, type = 'info') {
            // Create toast container if it doesn't exist
            let toastContainer = document.getElementById('toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toast-container';
                document.body.appendChild(toastContainer);
                
                // Add styles for toast container
                toastContainer.style.position = 'fixed';
                toastContainer.style.top = '20px';
                toastContainer.style.right = '20px';
                toastContainer.style.zIndex = '9999';
                toastContainer.style.width = '350px';
                toastContainer.style.maxWidth = 'calc(100% - 40px)';
            }
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            // Add icon based on type
            let icon = 'info-circle';
            let iconClass = 'toast-icon';
            if (type === 'success') icon = 'check-circle';
            if (type === 'error') icon = 'exclamation-circle';
            if (type === 'warning') icon = 'exclamation-triangle';
            
            toast.innerHTML = `
                <div class="toast-content">
                    <i class="fas fa-${icon} ${iconClass}"></i>
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
            toastContainer.appendChild(toast);
            
            // Trigger animation
            setTimeout(() => {
                toast.classList.add('show');
            }, 10);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.classList.add('hide');
                    setTimeout(() => {
                        if (toast.parentNode) {
                            toast.parentNode.removeChild(toast);
                        }
                    }, 300);
                }
            }, 5000);
        }
        
        $(document).ready(function() {
            // Show toast message if there is one from server-side processing
            <?php if (!empty($toastMessage)): ?>
                showToast('<?php echo addslashes($toastMessage); ?>', '<?php echo $toastType; ?>');
            <?php endif; ?>
            
            // Show/hide rejection form
            $('#showRejectForm').on('click', function() {
                $('#rejectForm').addClass('show');
                $(this).hide();
            });
            
            $('#cancelReject').on('click', function() {
                $('#rejectForm').removeClass('show');
                $('#showRejectForm').show();
            });
        });
        
        function markAsFulfilled(reservationId) {
            // Removed as fulfillment is handled automatically during borrowing process
        }
    </script>
</body>
</html>