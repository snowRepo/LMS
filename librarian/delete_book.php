<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';
require_once '../includes/SubscriptionManager.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has librarian role
if (!is_logged_in() || $_SESSION['user_role'] !== 'librarian') {
    header('Location: ../login.php');
    exit;
}

// Check subscription status
$subscriptionManager = new SubscriptionManager();
$libraryId = $_SESSION['library_id'];
$hasActiveSubscription = $subscriptionManager->hasActiveSubscription($libraryId);

if (!$hasActiveSubscription) {
    header('Location: ../subscription.php');
    exit;
}

// Get book ID from URL parameter
$bookId = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($bookId)) {
    header('Location: books.php');
    exit;
}

// Fetch book details
try {
    $db = Database::getInstance()->getConnection();
    
    // Get book details with category name
    $stmt = $db->prepare("
        SELECT b.*, c.name as category_name
        FROM books b
        LEFT JOIN categories c ON b.category_id = c.id
        WHERE b.library_id = ? AND b.book_id = ?
    ");
    $stmt->execute([$libraryId, $bookId]);
    $book = $stmt->fetch();
    
    if (!$book) {
        header('Location: books.php?error=Book not found');
        exit;
    }
    
    $pageTitle = 'Delete Book - ' . $book['title'];
} catch (Exception $e) {
    header('Location: books.php?error=Error fetching book details');
    exit;
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // First, get the book's cover image filename before deleting the record
        $stmt = $db->prepare("SELECT cover_image FROM books WHERE book_id = ? AND library_id = ?");
        $stmt->execute([$bookId, $libraryId]);
        $bookData = $stmt->fetch();
        
        // Delete book from database
        $stmt = $db->prepare("DELETE FROM books WHERE book_id = ? AND library_id = ?");
        $stmt->execute([$bookId, $libraryId]);
        
        // Check if any rows were affected
        if ($stmt->rowCount() > 0) {
            // If the book had a cover image, delete the file from the filesystem
            if (!empty($bookData['cover_image']) && file_exists('../uploads/books/' . $bookData['cover_image'])) {
                unlink('../uploads/books/' . $bookData['cover_image']);
            }
            
            // Log the book deletion activity
            try {
                $activityStmt = $db->prepare("
                    INSERT INTO activity_logs 
                    (user_id, library_id, action, description, created_at)
                    VALUES (?, ?, 'delete_book', ?, NOW())
                ");
                $activityStmt->execute([
                    $_SESSION['user_id'],
                    $libraryId,
                    'Deleted book: ' . $book['title']
                ]);
            } catch (Exception $e) {
                // Log the error but don't fail the book deletion
                error_log('Failed to log activity: ' . $e->getMessage());
            }
            
            // Update the current_book_count in the libraries table
            try {
                // Get the current book count for this library
                $countStmt = $db->prepare("SELECT COUNT(*) as book_count FROM books WHERE library_id = ?");
                $countStmt->execute([$libraryId]);
                $bookCount = $countStmt->fetch()['book_count'];
                
                // Update the current_book_count in the libraries table
                $updateStmt = $db->prepare("UPDATE libraries SET current_book_count = ? WHERE id = ?");
                $updateStmt->execute([$bookCount, $libraryId]);
            } catch (Exception $e) {
                // Log the error but don't fail the book deletion
                error_log('Failed to update current_book_count after deletion: ' . $e->getMessage());
            }
            
            // Show success message and redirect to books page
            header('Location: books.php?success=Book deleted successfully');
            exit;
        } else {
            $error = "Failed to delete book. Please try again.";
        }
    } catch (Exception $e) {
        $error = "Error deleting book: " . $e->getMessage();
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
    <!-- Toast CSS -->
    <!-- <link rel="stylesheet" href="css/toast.css"> -->
    
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 0;
        }
        
        html, body {
            margin: 0;
            padding: 0;
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

        .content-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .card-header h2 {
            color: #495057;
            margin: 0;
        }

        .btn {
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

        .btn-primary {
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .btn:active {
            transform: translateY(0);
        }

        .book-details-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
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
        }

        .book-cover-placeholder {
            width: 100%;
            max-width: 300px;
            height: 400px;
            background-color: #f0f0f0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .book-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .info-group {
            margin-bottom: 1.5rem;
        }

        .info-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .info-value {
            color: #6c757d;
            font-size: 1rem;
        }

        .info-value strong {
            color: #495057;
        }

        .description {
            grid-column: 1 / -1;
        }

        .description .info-value {
            line-height: 1.6;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-align: center;
        }

        .status-active {
            background-color: #006400;
            color: white;
        }

        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-damaged {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-lost {
            background-color: #f8d7da;
            color: #721c24;
        }

        .warning-message {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .warning-message h3 {
            margin-top: 0;
            color: #856404;
        }

        .warning-message p {
            margin-bottom: 0;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }

        @media (max-width: 768px) {
            .book-details-container {
                grid-template-columns: 1fr;
            }
            
            .book-info {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
        
        /* Toast notification styles */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        .toast {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            margin-bottom: 12px;
            min-width: 300px;
            max-width: 400px;
            animation: toastSlideIn 0.3s ease-out forwards;
            opacity: 0;
            transform: translateX(100%);
        }

        .toast.show {
            opacity: 1;
            transform: translateX(0);
        }

        .toast.hide {
            animation: toastSlideOut 0.3s ease-in forwards;
        }

        @keyframes toastSlideIn {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes toastSlideOut {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }

        /* Updated to use dark shades of green and red without additional design */
        .toast-success {
            background-color: #2e7d32; /* Dark green */
            color: white;
        }

        .toast-error {
            background-color: #c62828; /* Dark red */
            color: white;
        }

        .toast-info {
            background-color: #1565c0; /* Dark blue */
            color: white;
        }

        .toast-warning {
            background-color: #ef6c00; /* Dark orange */
            color: white;
        }

        .toast-icon {
            font-size: 1.2rem;
            font-weight: bold;
        }

        .toast-content {
            flex: 1;
            font-size: 0.95rem;
        }

        .toast-close {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
            transition: opacity 0.2s;
        }

        .toast-close:hover {
            opacity: 1;
        }
    </style>
</head>
<body>
    <?php include 'includes/librarian_navbar.php'; ?>
    
    <!-- Toast Notification Container -->
    <div id="toast-container" class="toast-container"></div>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-exclamation-triangle"></i> Delete Book</h1>
            <p>Confirm deletion of "<?php echo htmlspecialchars($book['title']); ?>"</p>
        </div>
        
        <!-- Toast Container -->
        <div id="toast-container"></div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="content-card">
            <div class="card-header">
                <h2>Confirm Deletion</h2>
                <a href="view_book.php?id=<?php echo urlencode($bookId); ?>" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Book
                </a>
            </div>
            
            <div class="warning-message">
                <h3><i class="fas fa-exclamation-triangle"></i> Warning: Irreversible Action</h3>
                <p>You are about to permanently delete the book "<strong><?php echo htmlspecialchars($book['title']); ?></strong>" from your library. This action cannot be undone.</p>
                <p>All associated data including borrowing records will be lost. Please confirm that you want to proceed with this deletion.</p>
            </div>
            
            <div class="book-details-container">
                <div class="book-cover">
                    <?php if (!empty($book['cover_image']) && file_exists('../uploads/books/' . $book['cover_image'])): ?>
                        <img src="../uploads/books/<?php echo htmlspecialchars($book['cover_image']); ?>" 
                             alt="Book cover" 
                             class="book-cover-img">
                    <?php else: ?>
                        <div class="book-cover-placeholder">
                            <i class="fas fa-book" style="font-size: 4rem; color: #6c757d;"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="book-info">
                    <div class="info-group">
                        <div class="info-label">Title</div>
                        <div class="info-value"><?php echo htmlspecialchars($book['title']); ?></div>
                    </div>
                    
                    <?php if (!empty($book['subtitle'])): ?>
                    <div class="info-group">
                        <div class="info-label">Subtitle</div>
                        <div class="info-value"><?php echo htmlspecialchars($book['subtitle']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-group">
                        <div class="info-label">Book ID</div>
                        <div class="info-value"><?php echo htmlspecialchars($book['book_id']); ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Author</div>
                        <div class="info-value"><?php echo !empty($book['author_name']) ? htmlspecialchars($book['author_name']) : 'N/A'; ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Publisher</div>
                        <div class="info-value"><?php echo !empty($book['publisher_name']) ? htmlspecialchars($book['publisher_name']) : 'N/A'; ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Category</div>
                        <div class="info-value"><?php echo htmlspecialchars($book['category_name'] ?? 'N/A'); ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Total Copies</div>
                        <div class="info-value"><?php echo htmlspecialchars($book['total_copies']); ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Available Copies</div>
                        <div class="info-value"><?php echo htmlspecialchars($book['available_copies']); ?></div>
                    </div>
                    
                    <div class="info-group">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <span class="status-badge status-<?php echo $book['status']; ?>">
                                <?php echo ucfirst($book['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <form method="POST">
                <div class="action-buttons">
                    <button type="submit" name="confirm_delete" class="btn btn-danger">
                        <i class="fas fa-trash-alt"></i> Confirm Deletion
                    </button>
                    <a href="view_book.php?id=<?php echo urlencode($bookId); ?>" class="btn btn-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Check for URL parameters and show toast notifications
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, checking for URL parameters');
            const urlParams = new URLSearchParams(window.location.search);
            const success = urlParams.get('success');
            const error = urlParams.get('error');
            
            console.log('Success param:', success);
            console.log('Error param:', error);
            
            if (success) {
                console.log('Showing success toast');
                showToast(success, 'success');
                // Remove the success parameter from URL without reloading
                urlParams.delete('success');
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({}, document.title, newUrl);
            }
            
            if (error) {
                console.log('Showing error toast');
                showToast(error, 'error');
                // Remove the error parameter from URL without reloading
                urlParams.delete('error');
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({}, document.title, newUrl);
            }
        });
        
        // Global toast notification function
        function showToast(message, type = 'info', duration = 5000) {
            const container = document.getElementById('toast-container');
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            toast.innerHTML = `
                <div class="toast-content">${message}</div>
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
    </script>
</body>
</html>