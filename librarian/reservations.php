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

// Fetch pending reservations with search filter
$pendingReservations = [];
try {
    if (!empty($search)) {
        $stmt = $db->prepare("
            SELECT r.*, 
                   bk.title, 
                   bk.author_name,
                   CONCAT(m.first_name, ' ', m.last_name) as member_name,
                   DATEDIFF(r.expiry_date, CURDATE()) as days_remaining
            FROM reservations r
            JOIN books bk ON r.book_id = bk.id
            JOIN users m ON r.member_id = m.id
            WHERE r.status = 'pending'
            AND (bk.title LIKE ? OR bk.author_name LIKE ? OR m.first_name LIKE ? OR m.last_name LIKE ? OR m.user_id LIKE ?)
            ORDER BY r.reservation_date ASC
            LIMIT 10
        ");
        $searchParam = "%$search%";
        $stmt->execute([$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
    } else {
        $stmt = $db->query("
            SELECT r.*, 
                   bk.title, 
                   bk.author_name,
                   CONCAT(m.first_name, ' ', m.last_name) as member_name,
                   DATEDIFF(r.expiry_date, CURDATE()) as days_remaining
            FROM reservations r
            JOIN books bk ON r.book_id = bk.id
            JOIN users m ON r.member_id = m.id
            WHERE r.status = 'pending'
            ORDER BY r.reservation_date ASC
            LIMIT 10
        ");
    }
    $pendingReservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching pending reservations: " . $e->getMessage());
}

$pageTitle = 'Reservations';
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
            --primary-color: #3498DB; /* Blue theme to match borrowing page */
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
            --modal-bg: rgba(0, 0, 0, 0.5);
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
            background: rgba(155, 89, 182, 0.1);
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
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);
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
            text-align: center;
        }
        
        .table tr:last-child td {
            border-bottom: none;
        }
        
        .table thead tr:last-child th {
            border-bottom: 1px solid var(--gray-200);
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-pending { background: #fff3e0; color: #ef6c00; }
        .status-fulfilled { background: #e8f5e9; color: #2e7d32; }
        .status-cancelled { background: #ffebee; color: #c62828; }
        
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
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: var(--modal-bg);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: none;
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-header h5 {
            margin: 0;
            font-size: 1.25rem;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
        }
        
        .modal-body {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
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
        }
    </style>
</head>
<body>
    <?php include 'includes/librarian_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-calendar-check"></i> Reservations</h1>
            <p>Manage book reservations and fulfill requests</p>
        </div>
        
        <!-- Pending Reservations Section -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-clock"></i> Pending Reservations</h2>
                <a href="reservation_history.php" class="btn btn-primary">
                    <i class="fas fa-history"></i> View History
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
                        <a href="reservations.php" class="btn btn-outline" style="padding: 0.75rem 1rem;">
                            <i class="fas fa-times"></i>
                            <span class="search-btn-text">Clear</span>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="section-body">
                <?php if (!empty($pendingReservations)): ?>
                    <div style="overflow-x: auto;">
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
                                <?php foreach ($pendingReservations as $reservation): 
                                    $reservationDate = new DateTime($reservation['reservation_date']);
                                    $expiryDate = new DateTime($reservation['expiry_date']);
                                    $isExpired = $reservation['days_remaining'] < 0;
                                ?>
                                    <tr>
                                        <td class="book-info">
                                            <div><strong><?php echo htmlspecialchars($reservation['title']); ?></strong></div>
                                            <div class="text-muted"><?php echo htmlspecialchars($reservation['author_name'] ?? 'Unknown Author'); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($reservation['member_name']); ?></td>
                                        <td><?php echo $reservationDate->format('M j, Y'); ?></td>
                                        <td class="<?php echo $isExpired ? 'text-danger' : ''; ?>">
                                            <?php echo $expiryDate->format('M j, Y'); ?>
                                            <?php if ($isExpired): ?>
                                                <div class="small text-danger">(Expired)</div>
                                            <?php else: ?>
                                                <div class="small text-muted">(<?php echo $reservation['days_remaining']; ?> days left)</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-pending">
                                                <?php echo ucfirst($reservation['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-success" title="Approve" onclick="approveReservation(<?php echo $reservation['id']; ?>)">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-danger" title="Reject" onclick="rejectReservation(<?php echo $reservation['id']; ?>)">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <button class="btn btn-primary" title="View Details" onclick="viewReservation(<?php echo $reservation['id']; ?>)">
                                                    <i class="fas fa-file-alt"></i>
                                                </button>
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
                        <p style="margin: 0;">No pending reservations found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Function to view reservation details
        function viewReservation(reservationId) {
            // Redirect to the view reservation page
            window.location.href = 'view_reservation.php?id=' + reservationId;
        }
        
        // Function to show approval modal
        function approveReservation(reservationId) {
            // Set the reservation ID in the modal form
            document.getElementById('approveReservationId').value = reservationId;
            // Clear previous notes
            document.getElementById('approveNotes').value = '';
            // Show the modal
            document.getElementById('approveModal').style.display = 'block';
        }
        
        // Function to show rejection modal
        function rejectReservation(reservationId) {
            // Set the reservation ID in the modal form
            document.getElementById('rejectReservationId').value = reservationId;
            // Clear previous reason and notes
            document.getElementById('rejectReason').value = '';
            // Show the modal
            document.getElementById('rejectModal').style.display = 'block';
        }
        
        // Function to close modals
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Function to show toast notifications
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
        
        // Function to submit approval
        function submitApproval() {
            const reservationId = document.getElementById('approveReservationId').value;
            const notes = document.getElementById('approveNotes').value;
            
            // Basic validation
            if (!reservationId) {
                showToast('Invalid reservation ID', 'error');
                return;
            }
            
            // Disable submit button to prevent double submission
            const submitBtn = document.getElementById('approveSubmitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';
            
            // Make AJAX request
            $.post('ajax_update_reservation_status.php', {
                action: 'approve',
                reservation_id: reservationId,
                librarian_notes: notes,
                csrf_token: '<?php echo $_SESSION['csrf_token'] ?? ''; ?>'
            })
            .done(function(response) {
                if (response.success) {
                    // Close modal
                    closeModal('approveModal');
                    // Show success message
                    showToast('Reservation approved successfully!', 'success');
                    // Reload the page to reflect changes
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showToast('Error: ' + (response.error || 'Unknown error occurred'), 'error');
                }
            })
            .fail(function() {
                showToast('Error: Failed to process approval', 'error');
            })
            .always(function() {
                // Re-enable submit button
                submitBtn.disabled = false;
                submitBtn.textContent = 'Approve Reservation';
            });
        }
        
        // Function to submit rejection
        function submitRejection() {
            const reservationId = document.getElementById('rejectReservationId').value;
            const reason = document.getElementById('rejectReason').value;
            
            // Basic validation
            if (!reservationId) {
                showToast('Invalid reservation ID', 'error');
                return;
            }
            
            if (!reason.trim()) {
                showToast('Please provide a reason for rejection', 'error');
                return;
            }
            
            // Disable submit button to prevent double submission
            const submitBtn = document.getElementById('rejectSubmitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';
            
            // Make AJAX request
            $.post('ajax_update_reservation_status.php', {
                action: 'reject',
                reservation_id: reservationId,
                rejection_reason: reason,
                csrf_token: '<?php echo $_SESSION['csrf_token'] ?? ''; ?>'
            })
            .done(function(response) {
                if (response.success) {
                    // Close modal
                    closeModal('rejectModal');
                    // Show success message
                    showToast('Reservation rejected successfully!', 'success');
                    // Reload the page to reflect changes
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showToast('Error: ' + (response.error || 'Unknown error occurred'), 'error');
                }
            })
            .fail(function() {
                showToast('Error: Failed to process rejection', 'error');
            })
            .always(function() {
                // Re-enable submit button
                submitBtn.disabled = false;
                submitBtn.textContent = 'Reject Reservation';
            });
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Initialize any necessary event listeners when the document is ready
        $(document).ready(function() {
            // Add any additional initialization code here
        });
    </script>
    
    <!-- Approval Modal -->
    <div class="modal" id="approveModal">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Approve Reservation</h5>
                <span class="close" onclick="closeModal('approveModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to approve this reservation?</p>
                <input type="hidden" id="approveReservationId">
                <div class="mb-3">
                    <label for="approveNotes" class="form-label">Notes (Optional):</label>
                    <textarea class="form-control" id="approveNotes" rows="3" placeholder="Add any notes for the member..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('approveModal')">Cancel</button>
                <button type="button" class="btn btn-success" id="approveSubmitBtn" onclick="submitApproval()">Approve Reservation</button>
            </div>
        </div>
    </div>
    
    <!-- Rejection Modal -->
    <div class="modal" id="rejectModal">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Reject Reservation</h5>
                <span class="close" onclick="closeModal('rejectModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to reject this reservation?</p>
                <input type="hidden" id="rejectReservationId">
                <div class="mb-3">
                    <label for="rejectReason" class="form-label">Reason for Rejection <span class="text-danger">*</span>:</label>
                    <textarea class="form-control" id="rejectReason" rows="3" placeholder="Please provide a reason for rejecting this reservation..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
                <button type="button" class="btn btn-danger" id="rejectSubmitBtn" onclick="submitRejection()">Reject Reservation</button>
            </div>
        </div>
    </div>
</body>
</html>
