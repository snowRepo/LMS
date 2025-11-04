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
        WHERE b.library_id = ? AND b.book_id = ? AND b.status = 'active'
    ");
    $stmt->execute([$_SESSION['library_id'], $bookId]);
    $book = $stmt->fetch();
    
    if (!$book) {
        header('Location: books.php?error=Book not found');
        exit;
    }
    
    $pageTitle = 'Book Details - ' . $book['title'];
} catch (Exception $e) {
    header('Location: books.php?error=Error fetching book details');
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
        
        .book-description {
            margin: 1rem 0;
        }
        
        .book-description h3 {
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .book-description p {
            color: #6c757d;
            line-height: 1.6;
        }
        
        .availability-status {
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            text-align: center;
            font-weight: 500;
        }
        
        .available {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
            border: 1px solid rgba(46, 204, 113, 0.3);
        }
        
        .unavailable {
            background: rgba(231, 76, 60, 0.1);
            color: #c0392b;
            border: 1px solid rgba(231, 76, 60, 0.3);
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .btn {
            flex: 1;
            padding: 0.75rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(52, 152, 219, 0.4);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn-reserve {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            color: white;
        }
        
        .btn-reserve:hover {
            box-shadow: 0 6px 15px rgba(155, 89, 182, 0.4);
        }
        
        .btn-reserve:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
    </style>
</head>
<body>
    <?php include 'includes/member_navbar.php'; ?>
    
    <!-- Toast Container -->
    <div id="toast-container"></div>
    
    <div class="container">
        <a href="books.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Books
        </a>
        
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
                            <span class="meta-label">Publication Year</span>
                            <span class="meta-value"><?php echo !empty($book['publication_year']) ? htmlspecialchars($book['publication_year']) : 'N/A'; ?></span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">Pages</span>
                            <span class="meta-value"><?php echo !empty($book['pages']) ? htmlspecialchars($book['pages']) : 'N/A'; ?></span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">Language</span>
                            <span class="meta-value"><?php echo htmlspecialchars($book['language'] ?? 'English'); ?></span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">Total Copies</span>
                            <span class="meta-value"><?php echo htmlspecialchars($book['total_copies']); ?></span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">Available Copies</span>
                            <span class="meta-value"><?php echo htmlspecialchars($book['available_copies']); ?></span>
                        </div>
                    </div>
                    
                    <div class="availability-status <?php echo ($book['available_copies'] > 0) ? 'available' : 'unavailable'; ?>">
                        <?php if ($book['available_copies'] > 0): ?>
                            <i class="fas fa-check-circle"></i> Available for reservation
                        <?php else: ?>
                            <i class="fas fa-times-circle"></i> Currently unavailable
                        <?php endif; ?>
                    </div>
                    
                    <div class="book-description">
                        <h3>Description</h3>
                        <p><?php echo !empty($book['description']) ? htmlspecialchars($book['description']) : 'No description available for this book.'; ?></p>
                    </div>
                    
                    <div class="action-buttons">
                        <button 
                            class="btn btn-reserve" 
                            onclick="reserveBook('<?php echo htmlspecialchars($book['book_id']); ?>', '<?php echo htmlspecialchars($book['title']); ?>')"
                            <?php echo ($book['available_copies'] <= 0) ? 'disabled' : ''; ?>
                        >
                            <i class="fas fa-calendar-plus"></i> 
                            <?php echo ($book['available_copies'] > 0) ? 'Reserve Book' : 'No Copies Available'; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function reserveBook(bookId, bookTitle) {
            // Redirect to reservation confirmation page without prompt
            window.location.href = `reserve_book.php?book_id=${bookId}`;
        }
        
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
        
        // Check for URL parameters and show toast notifications
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const success = urlParams.get('success');
            const error = urlParams.get('error');
            
            if (success) {
                console.log('Showing success toast');
                showToast(success, 'success');
                // Remove the parameter from URL without reloading the page
                urlParams.delete('success');
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({}, document.title, newUrl);
            }
            
            if (error) {
                console.log('Showing error toast');
                showToast(error, 'error');
                // Remove the parameter from URL without reloading the page
                urlParams.delete('error');
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({}, document.title, newUrl);
            }
        });
    </script>
</body>
</html>