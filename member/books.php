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

// Handle search, category filter, and sorting
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'title'; // Default to alphabetical by title
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12; // Books per page
$offset = ($page - 1) * $limit;

try {
    $db = Database::getInstance()->getConnection();
    
    // Get categories for the filter dropdown
    $stmt = $db->prepare("SELECT id, name FROM categories WHERE library_id = ? AND status = 'active' ORDER BY name");
    $stmt->execute([$_SESSION['library_id']]);
    $categories = $stmt->fetchAll();
    
    // Get total books count for pagination
    $sql = "SELECT COUNT(*) as total FROM books b WHERE b.library_id = ? AND b.status = 'active'";
    $params = [$_SESSION['library_id']];
    
    if (!empty($search)) {
        $sql .= " AND (b.title LIKE ? OR b.isbn LIKE ? OR b.author_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($category)) {
        $sql .= " AND b.category_id = ?";
        $params[] = $category;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $totalBooks = $stmt->fetch()['total'];
    $totalPages = ceil($totalBooks / $limit);
    
    // Get books for current page
    $sql = "SELECT b.*, c.name as category_name FROM books b LEFT JOIN categories c ON b.category_id = c.id WHERE b.library_id = ? AND b.status = 'active'";
    $params = [$_SESSION['library_id']];
    
    if (!empty($search)) {
        $sql .= " AND (b.title LIKE ? OR b.isbn LIKE ? OR b.author_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($category)) {
        $sql .= " AND b.category_id = ?";
        $params[] = $category;
    }
    
    // Add sorting
    switch ($sort) {
        case 'title':
            $sql .= " ORDER BY b.title ASC";
            break;
        case 'author':
            $sql .= " ORDER BY b.author_name ASC";
            break;
        case 'newest':
            $sql .= " ORDER BY b.created_at DESC";
            break;
        case 'oldest':
            $sql .= " ORDER BY b.created_at ASC";
            break;
        default:
            $sql .= " ORDER BY b.title ASC";
    }
    
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $books = $stmt->fetchAll();
    
} catch (Exception $e) {
    die('Error loading books: ' . $e->getMessage());
}

$pageTitle = 'Books';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - LMS</title>
    
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
        
        .page-header p {
            color: #6c757d;
            font-size: 1.1rem;
        }
        
        .search-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            max-width: 600px;
            margin: 0 auto 2rem auto;
        }
        
        .search-input {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .search-btn {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        
        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(52, 152, 219, 0.4);
        }
        
        .filters-section {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .filters-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 1rem;
        }
        
        .filters-form {
            display: inline-flex;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: center;
            max-width: 600px;
            background: transparent;
            padding: 0;
            border: none;
            box-shadow: none;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 150px;
        }
        
        .filter-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
            text-align: left;
            height: 1.2rem; /* Match height of select labels */
        }
        
        .filter-select {
            padding: 0.6rem;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 0.9rem;
            height: 2.5rem; /* Match button height */
        }
        
        .apply-btn {
            padding: 0.6rem 1rem;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
            height: 2.5rem; /* Match select height */
            align-self: flex-end;
        }
        
        .apply-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(52, 152, 219, 0.4);
        }
        
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .book-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #e9ecef;
            display: flex;
            flex-direction: column;
        }
        
        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .book-cover {
            height: 200px;
            background: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
        }
        
        .book-details {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .book-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #495057;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .book-author {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .book-category {
            display: inline-block;
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-bottom: 1rem;
            width: fit-content;
        }
        
        .book-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: auto;
        }
        
        .btn {
            flex: 1;
            padding: 0.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-view {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        
        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(52, 152, 219, 0.4);
        }
        
        .btn-reserve {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(155, 89, 182, 0.3);
        }
        
        .btn-reserve:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(155, 89, 182, 0.4);
        }
        
        .btn-reserve:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        
        .page-link {
            padding: 0.5rem 1rem;
            background: #ffffff;
            border: 1px solid #dee2e6;
            color: #495057;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .page-link:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }
        
        .page-link.active {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border-color: #3498db;
        }
        
        .no-books {
            text-align: center;
            padding: 3rem;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }
        
        .no-books i {
            font-size: 3rem;
            color: #ced4da;
            margin-bottom: 1rem;
        }
        
        .no-books h3 {
            color: #6c757d;
            margin-bottom: 1rem;
        }
        
        .stats-bar {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .books-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 1rem;
            }
            
            .search-bar {
                flex-direction: column;
            }
            
            .filters-form {
                flex-direction: column;
                align-items: center;
            }
            
            .filter-group {
                width: 100%;
                max-width: 300px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/member_navbar.php'; ?>
    
    <!-- Toast Container -->
    <div id="toast-container"></div>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-book"></i> Library Books</h1>
            <p>Browse and reserve books from your library collection</p>
        </div>
        
        <div class="stats-bar">
            <div>
                Showing <?php echo min($offset + 1, $totalBooks); ?> to <?php echo min($offset + $limit, $totalBooks); ?> of <?php echo $totalBooks; ?> books
            </div>
            <?php if (!empty($search) || !empty($category) || $sort !== 'title'): ?>
                <div>
                    <?php if (!empty($search)): ?>
                        Search: "<?php echo htmlspecialchars($search); ?>"
                    <?php endif; ?>
                    <?php if (!empty($category)): ?>
                        Category: "<?php echo htmlspecialchars(array_column($categories, 'name', 'id')[$category] ?? ''); ?>"
                    <?php endif; ?>
                    <?php if ($sort !== 'title'): ?>
                        Sort: "<?php echo htmlspecialchars(ucfirst($sort)); ?>"
                    <?php endif; ?>
                    <a href="books.php" style="margin-left: 10px; color: #3498db;">Clear All</a>
                </div>
            <?php endif; ?>
        </div>
        
        <form method="GET" class="search-bar">
            <input 
                type="text" 
                name="search" 
                class="search-input" 
                placeholder="Search by title, author, or ISBN..." 
                value="<?php echo htmlspecialchars($search); ?>"
            >
            <button type="submit" class="search-btn">
                <i class="fas fa-search"></i> Search
            </button>
        </form>
        
        <div class="filters-section">
            <div class="filters-title">Filter & Sort Books</div>
            <form method="GET" class="filters-form">
                <?php if (!empty($search)): ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <?php endif; ?>
                
                <div class="filter-group">
                    <label class="filter-label">Category</label>
                    <select name="category" class="filter-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo ($category == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Sort By</label>
                    <select name="sort" class="filter-select">
                        <option value="title" <?php echo ($sort == 'title') ? 'selected' : ''; ?>>Title (A-Z)</option>
                        <option value="author" <?php echo ($sort == 'author') ? 'selected' : ''; ?>>Author (A-Z)</option>
                        <option value="newest" <?php echo ($sort == 'newest') ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo ($sort == 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">&nbsp;</label>
                    <button type="submit" class="apply-btn">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                </div>
            </form>
        </div>
        
        <?php if (empty($books)): ?>
            <div class="no-books">
                <i class="fas fa-book-open"></i>
                <h3>No Books Found</h3>
                <p>
                    <?php 
                    if (!empty($search) || !empty($category)) {
                        echo 'No books match your search criteria. Try different filters.';
                    } else {
                        echo 'There are currently no books available in your library.';
                    }
                    ?>
                </p>
            </div>
        <?php else: ?>
            <div class="books-grid">
                <?php foreach ($books as $book): ?>
                    <div class="book-card">
                        <div class="book-cover">
                            <?php if (!empty($book['cover_image']) && file_exists('../uploads/books/' . $book['cover_image'])): ?>
                                <img src="../uploads/books/<?php echo htmlspecialchars($book['cover_image']); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <i class="fas fa-book"></i>
                            <?php endif; ?>
                        </div>
                        <div class="book-details">
                            <h3 class="book-title"><?php echo htmlspecialchars($book['title']); ?><?php if (!empty($book['subtitle'])): ?> - <?php echo htmlspecialchars($book['subtitle']); ?><?php endif; ?></h3>
                            <div class="book-author">by <?php echo htmlspecialchars($book['author_name'] ?? 'Unknown Author'); ?></div>
                            <?php if (!empty($book['category_name'])): ?>
                                <span class="book-category"><?php echo htmlspecialchars($book['category_name']); ?></span>
                            <?php endif; ?>
                            <div class="book-actions">
                                <a href="view_book.php?id=<?php echo urlencode($book['book_id']); ?>" class="btn btn-view">
                                    <i class="fas fa-file-alt"></i> View
                                </a>
                                <button 
                                    class="btn btn-reserve" 
                                    onclick="reserveBook('<?php echo htmlspecialchars($book['book_id']); ?>', '<?php echo htmlspecialchars($book['title']); ?>')"
                                    <?php echo ($book['available_copies'] <= 0) ? 'disabled' : ''; ?>
                                >
                                    <i class="fas fa-calendar-plus"></i> 
                                    <?php echo ($book['available_copies'] > 0) ? 'Reserve' : 'No Copies'; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($category) ? '&category=' . urlencode($category) : ''; ?><?php echo !empty($sort) ? '&sort=' . urlencode($sort) : ''; ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($category) ? '&category=' . urlencode($category) : ''; ?><?php echo !empty($sort) ? '&sort=' . urlencode($sort) : ''; ?>" 
                           class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($category) ? '&category=' . urlencode($category) : ''; ?><?php echo !empty($sort) ? '&sort=' . urlencode($sort) : ''; ?>" class="page-link">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
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