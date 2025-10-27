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

// Handle image removal
if (isset($_GET['remove_image']) && $_GET['remove_image'] == '1') {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Get current image filename
        $stmt = $db->prepare("SELECT cover_image FROM books WHERE book_id = ? AND library_id = ?");
        $stmt->execute([$bookId, $libraryId]);
        $book = $stmt->fetch();
        
        if ($book && !empty($book['cover_image'])) {
            // Delete image file from server
            $imagePath = '../uploads/books/' . $book['cover_image'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
            
            // Update database to remove image reference
            $stmt = $db->prepare("UPDATE books SET cover_image = NULL WHERE book_id = ? AND library_id = ?");
            $stmt->execute([$bookId, $libraryId]);
            
            // Redirect with success message
            header('Location: edit_book.php?id=' . urlencode($bookId) . '&success=' . urlencode('Cover image removed successfully'));
            exit;
        }
    } catch (Exception $e) {
        header('Location: edit_book.php?id=' . urlencode($bookId) . '&error=' . urlencode('Error removing cover image: ' . $e->getMessage()));
        exit;
    }
}

// Fetch book details
try {
    $db = Database::getInstance()->getConnection();
    
    // Get book details with category name
    $stmt = $db->prepare("
        SELECT b.*, c.name as category_name, c.id as category_id
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
    
    // Get categories for the form
    $stmt = $db->prepare("SELECT id, name FROM categories WHERE library_id = ? AND status = 'active' ORDER BY name");
    $stmt->execute([$libraryId]);
    $categories = $stmt->fetchAll();
    
    $pageTitle = 'Edit Book - ' . $book['title'];
} catch (Exception $e) {
    header('Location: books.php?error=Error fetching book details');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $title = trim($_POST['title'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $isbn = trim($_POST['isbn'] ?? '');
        // Convert empty ISBN to NULL to avoid unique constraint violations
        $isbn = ($isbn === '') ? null : $isbn;
        $authorName = trim($_POST['author_name'] ?? '');
        $categoryId = $_POST['category_id'] ?? '';
        $publisherName = trim($_POST['publisher_name'] ?? '');
        $edition = trim($_POST['edition'] ?? '');
        $publicationYear = trim($_POST['publication_year'] ?? '');
        // Convert empty publication year to NULL
        $publicationYear = ($publicationYear === '') ? null : (int)$publicationYear;
        $pages = trim($_POST['pages'] ?? '');
        $language = trim($_POST['language'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $totalCopies = (int)($_POST['total_copies'] ?? 0);
        $availableCopies = (int)($_POST['available_copies'] ?? 0);
        $location = trim($_POST['location'] ?? '');
        $price = trim($_POST['price'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        // Handle file upload
        $coverImage = $book['cover_image']; // Keep existing image by default
        
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/books/';
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maxFileSize = 5 * 1024 * 1024; // 5MB
            
            // Validate file
            $fileType = mime_content_type($_FILES['cover_image']['tmp_name']);
            $fileSize = $_FILES['cover_image']['size'];
            
            if (!in_array($fileType, $allowedTypes)) {
                $error = "Invalid file type. Only JPG, PNG, and GIF files are allowed.";
            } elseif ($fileSize > $maxFileSize) {
                $error = "File size exceeds 5MB limit.";
            } else {
                // Generate unique filename
                $fileExtension = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
                $newFilename = uniqid() . '_' . $bookId . '.' . $fileExtension;
                $uploadPath = $uploadDir . $newFilename;
                
                // Move uploaded file
                if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $uploadPath)) {
                    $coverImage = $newFilename;
                    
                    // Delete old image if it exists and is different
                    if (!empty($book['cover_image']) && $book['cover_image'] !== $newFilename) {
                        $oldImagePath = $uploadDir . $book['cover_image'];
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }
                } else {
                    $error = "Failed to upload image.";
                }
            }
        }
        
        // Validate required fields
        if (empty($title) || empty($authorName) || empty($categoryId)) {
            $error = "Please fill in all required fields.";
        } else {
            // Update book in database
            $stmt = $db->prepare("
                UPDATE books 
                SET title = ?, subtitle = ?, isbn = ?, author_name = ?, publisher_name = ?, category_id = ?, 
                    edition = ?, publication_year = ?, pages = ?, language = ?, description = ?, 
                    total_copies = ?, available_copies = ?, location = ?, price = ?, status = ?, cover_image = ?
                WHERE book_id = ? AND library_id = ?
            ");
            
            $stmt->execute([
                $title, $subtitle, $isbn, $authorName, $publisherName, $categoryId,
                $edition, $publicationYear, $pages, $language, $description,
                $totalCopies, $availableCopies, $location, $price, $status, $coverImage,
                $bookId, $libraryId
            ]);
            
            header('Location: view_book.php?id=' . urlencode($bookId) . '&success=' . urlencode('Book updated successfully'));
            exit;
        }
    } catch (Exception $e) {
        $error = "Error updating book: " . $e->getMessage();
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
            color: #495057;
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

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }

        .btn-sm + .btn-sm {
            margin-left: 0.5rem;
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

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
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
            transition: border-color 0.3s, box-shadow 0.3s;
            box-sizing: border-box;
            /* Add padding to the right for dropdown icons */
            padding-right: 2.5rem;
        }
        
        /* Remove padding-right for non-select elements */
        input.form-control,
        textarea.form-control,
        input[type="file"].form-control {
            padding-right: 0.75rem;
        }

        .form-control:focus {
            border-color: #3498DB;
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        /* Specific styling for select elements */
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

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        input[type="file"].form-control {
            padding: 0.5rem;
            background-color: #f8f9fa;
        }

        input[type="file"].form-control::-webkit-file-upload-button {
            background: #3498DB;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }

        input[type="file"].form-control::-webkit-file-upload-button:hover {
            background: #2980B9;
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

        .section-content {
            padding: 1.5rem 0;
        }

        .section-content h3 {
            color: #495057;
            margin-top: 0;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e9ecef;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }

        .help-text {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }

        @media (max-width: 768px) {
            .form-row {
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
            <h1><i class="fas fa-edit"></i> Edit Book</h1>
            <p>Update information for "<?php echo htmlspecialchars($book['title']); ?>"</p>
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
                <h2>Book Information</h2>
                <a href="books.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Books
                </a>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <!-- Basic Information Section -->
                <div class="section-content">
                    <h3>Basic Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="title">Book Title *</label>
                            <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($book['title']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="subtitle">Subtitle</label>
                            <input type="text" id="subtitle" name="subtitle" class="form-control" value="<?php echo htmlspecialchars($book['subtitle'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="author_name">Author *</label>
                            <input type="text" id="author_name" name="author_name" class="form-control" value="<?php echo htmlspecialchars($book['author_name'] ?? ''); ?>" placeholder="Enter author name(s), separated by commas" required>
                            <div class="help-text">Enter author names separated by commas (e.g., John Doe, Jane Smith)</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="publisher_name">Publisher</label>
                            <input type="text" id="publisher_name" name="publisher_name" class="form-control" value="<?php echo htmlspecialchars($book['publisher_name'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="isbn">ISBN</label>
                            <input type="text" id="isbn" name="isbn" class="form-control" value="<?php echo !empty($book['isbn']) ? htmlspecialchars($book['isbn']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="book_id">Book ID</label>
                            <input type="text" id="book_id" name="book_id" class="form-control" value="<?php echo htmlspecialchars($book['book_id']); ?>" readonly>
                            <div class="help-text">Book ID cannot be changed</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                            <label for="category_id">Category *</label>
                            <select id="category_id" name="category_id" class="form-control" required>
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo (($book['category_id'] ?? '') == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                </div>
                
                <!-- Book Details Section -->
                <div class="section-content">
                    <h3>Book Details</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edition">Edition</label>
                            <input type="text" id="edition" name="edition" class="form-control" value="<?php echo !empty($book['edition']) ? htmlspecialchars($book['edition']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="publication_year">Publication Year</label>
                            <select id="publication_year" name="publication_year" class="form-control">
                                <option value="">Select Year</option>
                                <?php
                                $currentYear = date('Y');
                                for ($year = $currentYear; $year >= 1900; $year--) {
                                    $selected = (!empty($book['publication_year']) && $book['publication_year'] == $year) ? 'selected' : '';
                                    echo "<option value=\"{$year}\" {$selected}>{$year}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="pages">Number of Pages</label>
                            <input type="number" id="pages" name="pages" class="form-control" min="1" value="<?php echo !empty($book['pages']) ? htmlspecialchars($book['pages']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="language">Language</label>
                            <input type="text" id="language" name="language" class="form-control" value="<?php echo !empty($book['language']) ? htmlspecialchars($book['language']) : 'English'; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control"><?php echo !empty($book['description']) ? htmlspecialchars($book['description']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="cover_image">Book Cover Image</label>
                            <input type="file" id="cover_image" name="cover_image" class="form-control" accept="image/*">
                            <div class="help-text">
                                Supported formats: JPG, PNG, GIF (Max 5MB)
                            </div>
                            <?php if (!empty($book['cover_image']) && file_exists('../uploads/books/' . $book['cover_image'])): ?>
                                <div class="help-text">
                                    Current image: 
                                    <a href="../uploads/books/<?php echo htmlspecialchars($book['cover_image']); ?>" target="_blank" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="?id=<?php echo urlencode($bookId); ?>&remove_image=1" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to remove the current cover image?');">
                                        <i class="fas fa-trash-alt"></i> Remove
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Inventory Section -->
                <div class="section-content">
                    <h3>Inventory</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="total_copies">Total Copies *</label>
                            <input type="number" id="total_copies" name="total_copies" class="form-control" min="1" value="<?php echo !empty($book['total_copies']) ? htmlspecialchars($book['total_copies']) : '1'; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="available_copies">Available Copies</label>
                            <input type="number" id="available_copies" name="available_copies" class="form-control" min="0" value="<?php echo !empty($book['available_copies']) ? htmlspecialchars($book['available_copies']) : '1'; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="location">Location/Shelf</label>
                            <input type="text" id="location" name="location" class="form-control" value="<?php echo !empty($book['location']) ? htmlspecialchars($book['location']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="price">Price (GHS)</label>
                            <input type="number" id="price" name="price" class="form-control" step="0.01" min="0" value="<?php echo !empty($book['price']) ? htmlspecialchars($book['price']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="active" <?php echo (($book['status'] ?? '') === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo (($book['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            <option value="damaged" <?php echo (($book['status'] ?? '') === 'damaged') ? 'selected' : ''; ?>>Damaged</option>
                            <option value="lost" <?php echo (($book['status'] ?? '') === 'lost') ? 'selected' : ''; ?>>Lost</option>
                        </select>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="books.php" class="btn btn-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
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
            const urlParams = new URLSearchParams(window.location.search);
            const success = urlParams.get('success');
            const error = urlParams.get('error');
            
            if (success) {
                showToast(success, 'success');
                // Remove the success parameter from URL without reloading
                urlParams.delete('success');
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({}, document.title, newUrl);
            }
            
            if (error) {
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