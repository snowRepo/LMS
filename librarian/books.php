<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';
require_once '../includes/SubscriptionManager.php';
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

// Get library ID for use in queries
$libraryId = $_SESSION['library_id'];

// Handle search and pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'title';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Books per page
$offset = ($page - 1) * $limit;

try {
    $db = Database::getInstance()->getConnection();
    
    // Get total books count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM books b WHERE b.library_id = ?";
    $params = [$libraryId];
    
    if (!empty($search)) {
        $countQuery .= " AND (b.title LIKE ? OR b.isbn LIKE ? OR b.book_id LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($categoryFilter)) {
        $countQuery .= " AND b.category_id = ?";
        $params[] = $categoryFilter;
    }
    
    $stmt = $db->prepare($countQuery);
    $stmt->execute($params);
    $totalBooks = $stmt->fetch()['total'];
    $totalPages = ceil($totalBooks / $limit);
    
    // Get books for current page
    $query = "SELECT b.*, c.name as category_name FROM books b LEFT JOIN categories c ON b.category_id = c.id WHERE b.library_id = ?";
    $params = [$libraryId];
    
    if (!empty($search)) {
        $query .= " AND (b.title LIKE ? OR b.isbn LIKE ? OR b.book_id LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($categoryFilter)) {
        $query .= " AND b.category_id = ?";
        $params[] = $categoryFilter;
    }
    
    // Add sorting
    $validSorts = ['title', 'author_name'];
    
    if (!in_array($sort, $validSorts)) {
        $sort = 'title';
    }
    
    $query .= " ORDER BY b.$sort ASC";
    
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $books = $stmt->fetchAll();
    
    // Get categories for the form and filters
    $stmt = $db->prepare("SELECT id, name FROM categories WHERE library_id = ? AND status = 'active' ORDER BY name");
    $stmt->execute([$libraryId]);
    $categories = $stmt->fetchAll();
    
} catch (Exception $e) {
    die('Error loading books: ' . $e->getMessage());
}

$pageTitle = 'Books Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - LMS</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Toast CSS -->
    <link rel="stylesheet" href="css/toast.css">
    
    <style>
        :root {
            --primary-color: #3498DB;
            --primary-dark: #2980B9;
            --secondary-color: #f8f9fa;
            --success-color: #2ECC71;
            --danger-color: #e74c3c;
            --warning-color: #F39C12;
            --info-color: #495057;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --gray-900: #212529;
            --border-radius: 12px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --box-shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s ease;
        }
        
        body {
            background: linear-gradient(135deg, var(--gray-100) 0%, #e9ecef 100%);
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
            color: var(--gray-900);
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: var(--gray-600);
            font-size: 1.1rem;
        }

        .content-card {
            background: #ffffff;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .card-header h2 {
            color: var(--gray-900);
            margin: 0;
        }
        
        /* Search and Filter Bar */
        .search-filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: end;
        }
        
        .filter-container {
            flex: 1;
            display: flex;
            gap: 1rem;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            width: fit-content;
        }
        
        .filter-group label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--gray-700);
        }
        
        .filter-select {
            padding: 0.75rem;
            border: 1.5px solid #95A5A6;
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
            width: fit-content;
            height: calc(2.5rem + 3px);
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23495057' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1rem;
            padding-right: 2.5rem;
        }
        
        .filter-select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15);
        }
        
        .search-container {
            display: flex;
            gap: 0.5rem;
            width: 400px;
            margin-left: auto;
        }
        
        .search-group {
            display: flex;
            gap: 0.5rem;
            width: 100%;
        }
        
        .search-input {
            flex: 1;
            padding: 0.75rem;
            border: 1.5px solid #95A5A6;
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .search-input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15);
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
            transition: var(--transition);
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
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(52, 152, 219, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.3);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.3);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 193, 7, 0.3);
        }
        
        .filter-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .table-container {
            overflow-x: auto;
        }

        .books-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            font-size: 0.85rem;
        }

        .books-table th,
        .books-table td {
            padding: 0.75rem;
            text-align: center;
            border-bottom: 1px solid var(--gray-300);
        }

        .books-table th {
            background-color: var(--gray-100);
            font-weight: 600;
            color: var(--gray-700);
        }

        .books-table tr:hover {
            background-color: var(--gray-100);
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a,
        .pagination span {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-300);
            text-decoration: none;
            color: var(--gray-700);
            border-radius: 4px;
            transition: var(--transition);
        }

        .pagination a:hover {
            background-color: var(--gray-200);
        }

        .pagination .current {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
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

        .action-btn {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        /* Add this CSS rule for author column truncation */
        .author-column {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        /* Prevent background scrolling when modal is open */
        body.modal-open {
            overflow: hidden;
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
            background-color: rgba(0,0,0,0.5);
            overflow: hidden;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 2% auto;
            padding: 0;
            border: none;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 1000px;
            max-height: 90vh;
            box-shadow: var(--box-shadow-lg);
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
        }

        .close {
            color: white;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
            transition: var(--transition);
        }

        .close:hover {
            opacity: 0.8;
        }

        .modal-body {
            padding: 0;
            overflow-y: auto;
            flex: 1;
        }

        .modal-sections {
            display: flex;
            border-bottom: 1px solid var(--gray-200);
            background-color: var(--gray-100);
        }

        .section-tab {
            padding: 1rem 1.5rem;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            font-weight: 500;
            color: var(--gray-600);
            transition: var(--transition);
        }

        .section-tab:hover {
            color: var(--gray-900);
            background-color: var(--gray-200);
        }

        .section-tab.active {
            color: var(--primary-color);
            border-bottom: 3px solid var(--primary-color);
            background-color: #ffffff;
        }

        .section-content {
            display: none;
            padding: 1.5rem;
            box-sizing: border-box;
        }

        .section-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--gray-700);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
            box-sizing: border-box;
            padding-right: 2.5rem;
        }
        
        input.form-control,
        textarea.form-control,
        input[type="file"].form-control {
            padding-right: 0.75rem;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        input[type="file"].form-control {
            padding: 0.5rem;
            background-color: var(--gray-100);
        }

        input[type="file"].form-control::-webkit-file-upload-button {
            background: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }

        input[type="file"].form-control::-webkit-file-upload-button:hover {
            background: var(--primary-dark);
        }

        select.form-control {
            padding-right: 2.5rem;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1rem;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }

        .category-selection-container {
            margin-bottom: 1rem;
        }

        .help-text {
            font-size: 0.8rem;
            color: var(--gray-600);
            margin-top: 0.5rem;
        }

        #category-message {
            padding: 0.75rem;
            border-radius: 4px;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .message-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-row .form-group:last-child {
            margin-bottom: 0;
        }

        .single-field {
            grid-column: 1 / -1;
        }

        .modal-footer {
            padding: 1.5rem;
            background-color: var(--gray-100);
            border-radius: 0 0 var(--border-radius) var(--border-radius);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .search-filter-bar {
                flex-direction: column;
            }
            
            .filter-container {
                flex-direction: column;
                width: 100%;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .search-container {
                width: 100%;
                margin-left: 0;
            }
            
            .search-group {
                width: 100%;
            }
            
            .filter-actions {
                width: 100%;
                justify-content: center;
            }
            
            .books-table {
                font-size: 0.8rem;
            }
            
            .books-table th,
            .books-table td {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/librarian_navbar.php'; ?>
    
    <!-- Toast Notification Container -->
    <div id="toast-container" class="toast-container"></div>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-book"></i> Books Management</h1>
            <p>Manage your library's book collection</p>
        </div>
        
        <!-- Toast Container -->
        <div id="toast-container"></div>
        
        <div class="content-card">
            <div class="card-header">
                <h2>Book Collection</h2>
                <button class="btn btn-primary" id="addBookBtn">
                    <i class="fas fa-plus"></i>
                    Add New Book
                </button>
            </div>
            
            <!-- Search and Filter Bar -->
            <div class="search-filter-bar">
                <form method="GET" style="display: contents;">
                    <input type="hidden" name="_" value="<?php echo time(); ?>">
                    <div class="filter-container">
                        <div class="filter-group">
                            <label for="category">Filter by Category</label>
                            <select name="category" id="category" class="filter-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo $categoryFilter == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="sort">Sort by</label>
                            <select name="sort" id="sort" class="filter-select">
                                <option value="title" <?php echo $sort === 'title' ? 'selected' : ''; ?>>Title (A-Z)</option>
                                <option value="author_name" <?php echo $sort === 'author_name' ? 'selected' : ''; ?>>Author (A-Z)</option>
                            </select>
                        </div>
                        
                        <div class="filter-actions">
                            <input type="submit" value="Filter" class="btn btn-primary">
                        </div>
                    </div>
                    
                    <div class="search-container">
                        <div class="search-group">
                            <input type="text" 
                                   name="search" 
                                   id="search"
                                   class="search-input" 
                                   placeholder="Search by title, ISBN, or Book ID..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="search-btn">
                                <i class="fas fa-search"></i>
                            </button>
                            <?php if (!empty($search) || !empty($categoryFilter)): ?>
                                <a href="books.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Books Table -->
            <div class="table-container">
                <table class="books-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Book ID</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Category</th>
                            <th>Total Copies</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($books) > 0): ?>
                            <?php foreach ($books as $book): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($book['cover_image']) && file_exists('../uploads/books/' . $book['cover_image'])): ?>
                                            <img src="../uploads/books/<?php echo htmlspecialchars($book['cover_image']); ?>" 
                                                 alt="Book cover" 
                                                 style="width: 50px; height: 70px; object-fit: cover; border-radius: 4px;">
                                        <?php else: ?>
                                            <div style="width: 50px; height: 70px; background-color: #f0f0f0; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-book" style="color: #6c757d;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($book['book_id']); ?></td>
                                    <td><?php echo htmlspecialchars($book['title']); ?><?php if (!empty($book['subtitle'])): ?> - <?php echo htmlspecialchars($book['subtitle']); ?><?php endif; ?></td>
                                    <td style="max-width: 150px; white-space: normal; word-wrap: break-word; line-height: 1.8;" title="<?php echo !empty($book['author_name']) ? htmlspecialchars($book['author_name']) : 'N/A'; ?>">
                                        <?php 
                                        $authorName = !empty($book['author_name']) ? htmlspecialchars($book['author_name']) : 'N/A';
                                        // Always display author names vertically (top to bottom)
                                        if (strpos($authorName, ',') !== false) {
                                            // Multiple authors - split and display each on separate line
                                            $authors = explode(',', $authorName);
                                            foreach ($authors as $author) {
                                                echo trim($author) . '<br>';
                                            }
                                        } else {
                                            // Single author - still display on its own line
                                            echo $authorName;
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($book['category_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($book['total_copies'] ?? 1); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $book['status']; ?>">
                                            <?php echo ucfirst($book['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view_book.php?id=<?php echo urlencode($book['book_id']); ?>" class="btn btn-primary action-btn" title="View">
                                            <i class="fas fa-file-alt"></i>
                                        </a>
                                        <a href="edit_book.php?id=<?php echo urlencode($book['book_id']); ?>" class="btn btn-warning action-btn" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="delete_book.php?id=<?php echo urlencode($book['book_id']); ?>" class="btn btn-danger action-btn" title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">
                                    <?php echo !empty($search) ? 'No books found matching your search.' : 'No books in the library yet.'; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php 
                    // Build query string for pagination links
                    $queryString = '';
                    $params = [];
                    
                    if (!empty($search)) {
                        $params['search'] = $search;
                    }
                    
                    if (!empty($categoryFilter)) {
                        $params['category'] = $categoryFilter;
                    }
                    
                    if (!empty($sort) && $sort !== 'title') {
                        $params['sort'] = $sort;
                    }
                    
                    $queryString = !empty($params) ? '&' . http_build_query($params) : '';
                    ?>
                    
                    <?php if ($page > 1): ?>
                        <a href="?page=1<?php echo $queryString; ?>">&laquo; First</a>
                        <a href="?page=<?php echo $page - 1; ?><?php echo $queryString; ?>">&lsaquo; Prev</a>
                    <?php endif; ?>
                    
                    <?php 
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?><?php echo $queryString; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $queryString; ?>">Next &rsaquo;</a>
                        <a href="?page=<?php echo $totalPages; ?><?php echo $queryString; ?>">Last &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add Book Modal -->
    <div id="addBookModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-book"></i> Add New Book</h2>
                <button class="close">&times;</button>
            </div>
            
            <div class="modal-body">
                <!-- Section Tabs -->
                <div class="modal-sections">
                    <div class="section-tab active" data-section="basic">Basic Information</div>
                    <div class="section-tab" data-section="details">Book Details</div>
                    <div class="section-tab" data-section="inventory">Inventory</div>
                </div>
                
                <!-- Form Sections -->
                <form id="addBookForm">
                    <!-- Basic Information Section -->
                    <div class="section-content active" id="basic-section">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="title">Book Title *</label>
                                <input type="text" id="title" name="title" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="subtitle">Subtitle</label>
                                <input type="text" id="subtitle" name="subtitle" class="form-control">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="author_name">Author *</label>
                                <input type="text" id="author_name" name="author_name" class="form-control" placeholder="Enter author name" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="isbn">ISBN</label>
                                <input type="text" id="isbn" name="isbn" class="form-control">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="book_id">Book ID *</label>
                                <input type="text" id="book_id" name="book_id" class="form-control" readonly>
                                <div class="help-text">Automatically generated from title and author</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="publisher_name">Publisher</label>
                                <input type="text" id="publisher_name" name="publisher_name" class="form-control" placeholder="Enter publisher name">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group single-field">
                                <label for="category_selection">Category *</label>
                                <div class="category-selection-container">
                                    <select id="category_selection" name="category_selection" class="form-control" required>
                                        <option value="">Select Category</option>
                                        <option value="existing">Select from existing categories</option>
                                        <option value="new">Add new category</option>
                                    </select>
                                </div>
                                <div id="existing-category-container" style="display: none; margin-top: 1rem;">
                                    <select id="category_id" name="category_id" class="form-control">
                                        <option value="">Choose a category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div id="new-category-container" style="display: none; margin-top: 1rem;">
                                    <input type="text" id="new_category_name" name="new_category_name" class="form-control" placeholder="Enter new category name">
                                    <button type="button" id="add-category-btn" class="btn btn-primary" style="margin-top: 0.5rem;">
                                        <i class="fas fa-plus"></i> Add Category
                                    </button>
                                    <div id="category-message" style="margin-top: 0.5rem; display: none;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Book Details Section -->
                    <div class="section-content" id="details-section">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edition">Edition</label>
                                <input type="text" id="edition" name="edition" class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label for="publication_year">Publication Year</label>
                                <select id="publication_year" name="publication_year" class="form-control">
                                    <option value="">Select Year</option>
                                    <?php
                                    $currentYear = date('Y');
                                    for ($year = $currentYear; $year >= 1900; $year--) {
                                        echo "<option value=\"{$year}\">{$year}</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="pages">Number of Pages</label>
                                <input type="number" id="pages" name="pages" class="form-control" min="1">
                            </div>
                            
                            <div class="form-group">
                                <label for="language">Language</label>
                                <input type="text" id="language" name="language" class="form-control" value="English">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" class="form-control" rows="4" style="min-height: 100px;"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="cover_image">Book Cover Image</label>
                                <input type="file" id="cover_image" name="cover_image" class="form-control" accept="image/*">
                                <div class="help-text">
                                    Supported formats: JPG, PNG, GIF (Max 5MB)
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Inventory Section -->
                    <div class="section-content" id="inventory-section">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="total_copies">Total Copies *</label>
                                <input type="number" id="total_copies" name="total_copies" class="form-control" min="1" value="1" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="available_copies">Available Copies</label>
                                <input type="number" id="available_copies" name="available_copies" class="form-control" min="0" value="1">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="location">Location/Shelf</label>
                                <input type="text" id="location" name="location" class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label for="price">Price (GHS)</label>
                                <input type="number" id="price" name="price" class="form-control" step="0.01" min="0">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="damaged">Damaged</option>
                                <option value="lost">Lost</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelBtn">Cancel</button>
                <button class="btn btn-primary" id="saveBookBtn">Save Book</button>
            </div>
        </div>
    </div>
    
    <script>
        // Modal functionality
        const modal = document.getElementById('addBookModal');
        const addBookBtn = document.getElementById('addBookBtn');
        const closeBtn = document.querySelector('.close');
        const cancelBtn = document.getElementById('cancelBtn');
        const sectionTabs = document.querySelectorAll('.section-tab');
        const sectionContents = document.querySelectorAll('.section-content');
        
        // Category selection elements
        const categorySelection = document.getElementById('category_selection');
        const existingCategoryContainer = document.getElementById('existing-category-container');
        const newCategoryContainer = document.getElementById('new-category-container');
        const categoryIdSelect = document.getElementById('category_id');
        const newCategoryName = document.getElementById('new_category_name');
        const addCategoryBtn = document.getElementById('add-category-btn');
        const categoryMessage = document.getElementById('category-message');
        
        // Handle category selection change
        categorySelection.addEventListener('change', function() {
            const value = this.value;
            
            // Hide both containers by default
            existingCategoryContainer.style.display = 'none';
            newCategoryContainer.style.display = 'none';
            
            if (value === 'existing') {
                existingCategoryContainer.style.display = 'block';
            } else if (value === 'new') {
                newCategoryContainer.style.display = 'block';
                newCategoryName.value = '';
                categoryMessage.style.display = 'none';
            }
        });
        
        // Handle adding a new category
        addCategoryBtn.addEventListener('click', function() {
            const categoryName = newCategoryName.value.trim();
            
            if (!categoryName) {
                showCategoryMessage('Please enter a category name', 'error');
                return;
            }
            
            // Send AJAX request to add category
            const formData = new FormData();
            formData.append('action', 'add_category');
            formData.append('category_name', categoryName);
            
            fetch('ajax_categories.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    showCategoryMessage(data.error, 'error');
                } else if (data.success) {
                    // Add the new category to the existing categories dropdown
                    const option = document.createElement('option');
                    option.value = data.category_id;
                    option.textContent = data.category_name;
                    categoryIdSelect.appendChild(option);
                    
                    // Select the new category
                    categoryIdSelect.value = data.category_id;
                    
                    // Switch to existing category selection
                    categorySelection.value = 'existing';
                    existingCategoryContainer.style.display = 'block';
                    newCategoryContainer.style.display = 'none';
                    
                    showCategoryMessage('Category added successfully!', 'success');
                }
            })
            .catch(error => {
                showCategoryMessage('Error adding category: ' + error.message, 'error');
            });
        });
        
        // Helper function to show category messages
        function showCategoryMessage(message, type) {
            categoryMessage.textContent = message;
            categoryMessage.className = 'message-' + type;
            categoryMessage.style.display = 'block';
            
            // Hide message after 3 seconds
            setTimeout(() => {
                categoryMessage.style.display = 'none';
            }, 3000);
        }
        
        // Open modal
        addBookBtn.addEventListener('click', function() {
            // Reset form
            document.getElementById('addBookForm').reset();
            
            // Reset category selection
            categorySelection.value = '';
            existingCategoryContainer.style.display = 'none';
            newCategoryContainer.style.display = 'none';
            
            // Reset section tabs to show basic info first
            sectionTabs.forEach(tab => tab.classList.remove('active'));
            sectionContents.forEach(content => content.classList.remove('active'));
            document.querySelector('.section-tab[data-section="basic"]').classList.add('active');
            document.getElementById('basic-section').classList.add('active');
            
            // Clear book ID field
            document.getElementById('book_id').value = '';
            
            // Refresh categories list
            refreshCategories();
            
            modal.style.display = 'block';
            document.body.classList.add('modal-open');
        });
        
        // Refresh categories dropdown
        function refreshCategories() {
            fetch('ajax_categories.php?action=get_categories')
            .then(response => response.json())
            .then(data => {
                if (data.categories) {
                    // Clear existing options except the first one
                    while (categoryIdSelect.options.length > 1) {
                        categoryIdSelect.remove(1);
                    }
                    
                    // Add updated categories
                    data.categories.forEach(category => {
                        const option = document.createElement('option');
                        option.value = category.id;
                        option.textContent = category.name;
                        categoryIdSelect.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Error refreshing categories:', error);
            });
        }
        
        // Close modal
        function closeModal() {
            modal.style.display = 'none';
            document.body.classList.remove('modal-open');
        }
        
        closeBtn.addEventListener('click', closeModal);
        cancelBtn.addEventListener('click', closeModal);
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeModal();
            }
        });
        
        // Section switching
        sectionTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const sectionId = this.getAttribute('data-section');
                
                // Update active tab
                sectionTabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Show active section
                sectionContents.forEach(content => {
                    content.classList.remove('active');
                    if (content.id === sectionId + '-section') {
                        content.classList.add('active');
                    }
                });
            });
        });
        
        // Form submission
        document.getElementById('saveBookBtn').addEventListener('click', function() {
            // Get form elements
            const title = document.getElementById('title').value.trim();
            const author = document.getElementById('author_name').value.trim();
            const bookId = document.getElementById('book_id').value.trim();
            const publisherName = document.getElementById('publisher_name').value.trim();
            const totalCopies = parseInt(document.getElementById('total_copies').value);
            const categorySelectionValue = document.getElementById('category_selection').value;
            
            // Basic validation
            if (!title) {
                alert('Please enter a book title');
                switchToSection('basic');
                document.getElementById('title').focus();
                return;
            }
            
            if (!author) {
                alert('Please enter an author name');
                switchToSection('basic');
                document.getElementById('author_name').focus();
                return;
            }
            
            if (!bookId) {
                alert('Book ID could not be generated. Please enter both title and author.');
                switchToSection('basic');
                return;
            }
            
            if (isNaN(totalCopies) || totalCopies < 1) {
                alert('Please enter a valid number of total copies (minimum 1)');
                switchToSection('inventory');
                document.getElementById('total_copies').focus();
                return;
            }
            
            // Category validation
            let categoryId = null;
            
            if (categorySelectionValue === 'existing') {
                categoryId = document.getElementById('category_id').value;
                if (!categoryId) {
                    alert('Please select a category');
                    switchToSection('basic');
                    return;
                }
            } else if (categorySelectionValue === 'new') {
                // Check if there's a new category name
                const newCategoryNameValue = document.getElementById('new_category_name').value.trim();
                if (!newCategoryNameValue) {
                    alert('Please enter a new category name or select an existing category');
                    switchToSection('basic');
                    document.getElementById('new_category_name').focus();
                    return;
                }
                // In a real implementation, we would add the new category first
                alert('Please add the new category first before saving the book');
                switchToSection('basic');
                document.getElementById('new_category_name').focus();
                return;
            } else {
                alert('Please select a category option');
                switchToSection('basic');
                return;
            }
            
            // Prepare form data for AJAX submission
            const formData = new FormData();
            formData.append('action', 'add_book');
            formData.append('title', title);
            formData.append('author_name', author);
            formData.append('book_id', bookId);
            formData.append('publisher_name', publisherName);
            formData.append('category_id', categoryId);
            formData.append('total_copies', totalCopies);
            
            // Add other form fields
            const subtitle = document.getElementById('subtitle').value.trim();
            const isbn = document.getElementById('isbn').value.trim();
            const edition = document.getElementById('edition').value.trim();
            const publicationYear = document.getElementById('publication_year').value.trim();
            const pages = document.getElementById('pages').value.trim();
            const language = document.getElementById('language').value.trim();
            const description = document.getElementById('description').value.trim();
            const availableCopies = document.getElementById('available_copies').value.trim();
            const location = document.getElementById('location').value.trim();
            const price = document.getElementById('price').value.trim();
            const status = document.getElementById('status').value;
            const coverImage = document.getElementById('cover_image').files[0]; // Get the cover image file
            
            if (subtitle) formData.append('subtitle', subtitle);
            if (isbn) formData.append('isbn', isbn);
            if (edition) formData.append('edition', edition);
            if (publicationYear) formData.append('publication_year', publicationYear);
            if (pages) formData.append('pages', pages);
            if (language) formData.append('language', language);
            if (description) formData.append('description', description);
            if (availableCopies) formData.append('available_copies', availableCopies);
            if (location) formData.append('location', location);
            if (price) formData.append('price', price);
            if (coverImage) formData.append('cover_image', coverImage); // Append the cover image file
            formData.append('status', status);
            
            // Submit via AJAX
            fetch('ajax_books.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                } else if (data.success) {
                    showToast(data.message, 'success');
                    closeModal();
                    // Reload the page to show the new book
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }
            })
            .catch(error => {
                alert('Error saving book: ' + error.message);
            });
        });
        
        // Helper function to switch to a specific section
        function switchToSection(sectionName) {
            // Update active tab
            sectionTabs.forEach(t => t.classList.remove('active'));
            document.querySelector('.section-tab[data-section="' + sectionName + '"]').classList.add('active');
            
            // Show active section
            sectionContents.forEach(content => {
                content.classList.remove('active');
                if (content.id === sectionName + '-section') {
                    content.classList.add('active');
                }
            });
        }
        
        // Generate book ID based on title and author
        function generateBookId() {
            const title = document.getElementById('title').value.trim();
            const author = document.getElementById('author_name').value.trim();
            const bookIdField = document.getElementById('book_id');
            
            if (title && author) {
                // Create a slug from title and author
                const titlePart = title.substring(0, 3).toUpperCase();
                const authorPart = author.substring(0, 3).toUpperCase();
                const timestamp = Date.now().toString().slice(-4);
                const bookId = titlePart + authorPart + timestamp;
                bookIdField.value = bookId;
            } else {
                bookIdField.value = '';
            }
        }
        
        // Add event listeners for automatic book ID generation
        document.getElementById('title').addEventListener('blur', generateBookId);
        document.getElementById('author_name').addEventListener('blur', generateBookId);
        
        // Auto-update available copies when total copies changes
        document.getElementById('total_copies').addEventListener('change', function() {
            const totalCopies = parseInt(this.value) || 0;
            const availableCopiesField = document.getElementById('available_copies');
            const currentAvailable = parseInt(availableCopiesField.value) || 0;
            
            // Only update available copies if it's greater than the new total or if it was empty
            if (currentAvailable > totalCopies || availableCopiesField.value === '') {
                availableCopiesField.value = totalCopies;
            }
        });
        
        // Real-time validation for ISBN
        document.getElementById('isbn').addEventListener('input', function() {
            const isbn = this.value.replace(/[^0-9X]/g, ''); // Remove non-numeric characters except X
            if (isbn.length > 13) {
                this.value = isbn.substring(0, 13);
            }
        });
        
        // Remove the real-time validation for publication year since it's now a select element
        // The select element will only allow valid years
        
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