<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';
require_once '../includes/NotificationService.php';
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

// Get book ID from URL parameter
$bookId = isset($_GET['book_id']) ? trim($_GET['book_id']) : '';

if (empty($bookId)) {
    header('Location: books.php');
    exit;
}

// Initialize database connection
$db = Database::getInstance()->getConnection();

// Fetch book details
try {
    $stmt = $db->prepare("
        SELECT b.*, c.name as category_name
        FROM books b
        LEFT JOIN categories c ON b.category_id = c.id
        WHERE b.book_id = ? AND b.status = 'active' AND b.available_copies > 0
    ");
    $stmt->execute([$bookId]);
    $book = $stmt->fetch();
    
    if (!$book) {
        header('Location: books.php?error=Book not available for reservation');
        exit;
    }
    
    $pageTitle = 'Reserve Book - ' . $book['title'];
} catch (Exception $e) {
    header('Location: books.php?error=Error fetching book details');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    
    try {
        $db->beginTransaction();
        
        // Check if member already has a pending reservation for this book
        $stmt = $db->prepare("
            SELECT id FROM reservations 
            WHERE member_id = ? AND book_id = ? AND status = 'pending'
        ");
        $stmt->execute([$_SESSION['user_id'], $book['id']]);
        
        if ($stmt->fetch()) {
            throw new Exception('You already have a pending reservation for this book.');
        }
        
        // Check book availability again
        $stmt = $db->prepare("
            SELECT id, available_copies, title 
            FROM books 
            WHERE id = ? AND available_copies > 0
            FOR UPDATE
        ");
        $stmt->execute([$book['id']]);
        $bookCheck = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bookCheck) {
            throw new Exception('Selected book is no longer available for reservation');
        }
        
        // Create reservation
        $expiryDate = date('Y-m-d', strtotime('+7 days'));
        // Generate a unique reservation ID
        $reservationId = 'RES-' . strtoupper(substr(md5(uniqid()), 0, 8)) . '-' . date('Ymd');
        $stmt = $db->prepare("
            INSERT INTO reservations (
                reservation_id, book_id, member_id, reservation_date, expiry_date,
                status, notes
            ) VALUES (?, ?, ?, NOW(), ?, 'pending', ?)
        ");
        $stmt->execute([
            $reservationId, $book['id'], $_SESSION['user_id'], $expiryDate, 
            $notes
        ]);
        
        // Decrement available copies when reservation is made
        $stmt = $db->prepare("
            UPDATE books 
            SET available_copies = available_copies - 1 
            WHERE id = ?
        ");
        $stmt->execute([$book['id']]);
        
        // Get member details for notification
        $stmt = $db->prepare("SELECT id, first_name, last_name FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
        $memberName = $member ? $member['first_name'] . ' ' . $member['last_name'] : 'A member';
        
        // Get all active librarians to notify - using the numeric ID field
        $stmt = $db->query("SELECT id FROM users WHERE role = 'librarian' AND status = 'active'");
        $librarians = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($librarians)) {
            $notificationService = new NotificationService();
            $title = 'New Book Reservation';
            $message = "$memberName has reserved '{$book['title']}' (ID: $reservationId)";
            $actionUrl = "../librarian/view_reservation.php?id=" . $reservationId;
            
            // Create notification for each librarian
            foreach ($librarians as $librarianId) {
                $notificationService->createNotification(
                    (int)$librarianId,  // Cast to integer to ensure correct type
                    $title,
                    $message,
                    'info',
                    $actionUrl
                );
            }
        }
        
        // NOTE: We no longer decrement available copies here
        // Available copies will be decremented only when reservation is approved
        
        $db->commit();
        
        // Redirect with success message as URL parameter
        $successMessage = 'Your reservation for "' . htmlspecialchars($book['title']) . '" has been submitted successfully!';
        header('Location: reservations.php?success=' . urlencode($successMessage));
        exit;
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = 'Error creating reservation: ' . $e->getMessage();
    }
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
    <!-- Toast CSS -->
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
            max-width: 800px;
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
        
        .book-details-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }
        
        @media (max-width: 768px) {
            .book-details-container {
                grid-template-columns: 1fr;
            }
        }
        
        .book-cover {
            text-align: center;
        }
        
        .book-cover-img {
            width: 100%;
            max-width: 200px;
            height: 280px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin: 0 auto;
        }
        
        .book-cover-placeholder {
            width: 100%;
            max-width: 200px;
            height: 280px;
            background: #3498db;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            margin: 0 auto;
        }
        
        .book-info {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .book-title {
            font-size: 1.5rem;
            color: #212529;
            margin-bottom: 0.25rem;
        }
        
        .book-author {
            font-size: 1.1rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        
        .book-meta {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
        }
        
        .meta-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 0.1rem;
        }
        
        .meta-value {
            font-weight: 500;
            color: #495057;
        }
        
        .reservation-form {
            margin-top: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #495057;
        }
        
        textarea {
            width: 100%;
            min-height: 120px;
            padding: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-family: inherit;
            font-size: 1rem;
            resize: vertical;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        textarea:focus {
            outline: 0;
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
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
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
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
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .btn-secondary {
            background: #f8f9fa; /* Light gray/off-white color */
            color: #333;
            border: 1px solid #ced4da;
            text-decoration: none; /* Remove underline */
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
            transform: translateY(-2px);
            text-decoration: none; /* Ensure no underline on hover */
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
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/member_navbar.php'; ?>
    
    <!-- Toast Container -->
    <div id="toast-container"></div>
    
    <div class="container">
        <a href="view_book.php?id=<?php echo htmlspecialchars($bookId); ?>" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Book
        </a>
        
        <div class="page-header">
            <h1><i class="fas fa-calendar-plus"></i> Reserve Book</h1>
            <p>Confirm your reservation details and add any special notes</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="content-card">
            <div class="book-details-container">
                <div class="book-cover">
                    <?php if (!empty($book['cover_image']) && file_exists('../uploads/books/' . $book['cover_image'])): ?>
                        <img src="../uploads/books/<?php echo htmlspecialchars($book['cover_image']); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" class="book-cover-img">
                    <?php else: ?>
                        <div class="book-cover-placeholder">
                            <i class="fas fa-book"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="book-info">
                    <div>
                        <h1 class="book-title"><?php echo htmlspecialchars($book['title']); ?><?php if (!empty($book['subtitle'])): ?> - <?php echo htmlspecialchars($book['subtitle']); ?><?php endif; ?></h1>
                        <div class="book-author">by <?php echo htmlspecialchars($book['author_name'] ?? 'Unknown Author'); ?></div>
                        
                        <?php if (!empty($book['category_name'])): ?>
                            <span style="background: rgba(52, 152, 219, 0.1); color: #3498db; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.9rem;">
                                <?php echo htmlspecialchars($book['category_name']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="book-meta">
                        <div class="meta-item">
                            <span class="meta-label">ISBN</span>
                            <span class="meta-value"><?php echo !empty($book['isbn']) ? htmlspecialchars($book['isbn']) : 'N/A'; ?></span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">Available Copies</span>
                            <span class="meta-value"><?php echo htmlspecialchars($book['available_copies']); ?></span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">Reservation Expiry</span>
                            <span class="meta-value"><?php echo date('M j, Y', strtotime('+7 days')); ?></span>
                        </div>
                    </div>
                    
                    <div style="background: rgba(52, 152, 219, 0.1); color: #3498db; padding: 1rem; border-radius: 8px; margin-top: 1rem;">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Reservation Notice:</strong> Your reservation will expire in 7 days. You'll be notified when the book is ready for pickup.
                    </div>
                </div>
            </div>
            
            <?php if (!isset($successMessage)): ?>
                <form method="POST" class="reservation-form">
                    <div class="form-group">
                        <label for="notes">
                            <i class="fas fa-sticky-note"></i> Additional Notes (Optional)
                        </label>
                        <textarea 
                            id="notes" 
                            name="notes" 
                            placeholder="Add any special instructions or notes for the librarian (e.g., preferred pickup time, specific edition needed, etc.)..."
                        ><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-calendar-check"></i> Confirm Reservation
                        </button>
                        <a href="view_book.php?id=<?php echo htmlspecialchars($bookId); ?>" class="btn btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Toast notification function
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
        
        // Check for success message and show toast notification
        document.addEventListener('DOMContentLoaded', function() {
            const successElement = document.getElementById('success-message');
            if (successElement && successElement.dataset.message) {
                showToast(successElement.dataset.message, 'success');
                
                // Redirect to reservations page after a short delay to show the toast
                setTimeout(function() {
                    window.location.href = 'reservations.php';
                }, 2000);
            }
        });
    </script>
</body>
</html>