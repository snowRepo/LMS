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
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $libraryId = $_SESSION['library_id'];
    
    // Handle different actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_book':
                // Check subscription limits
                $subscriptionManager = new SubscriptionManager();
                $subscriptionDetails = $subscriptionManager->getSubscriptionDetails($libraryId);
                
                if (!$subscriptionDetails || !$subscriptionDetails['can_add_books']) {
                    $limit = $subscriptionDetails['book_limit'];
                    if ($limit == -1) {
                        $message = 'You have reached your book limit. Please upgrade your subscription to add more books.';
                    } else {
                        $message = "You have reached your book limit of {$limit} books. Please upgrade your subscription to add more books.";
                    }
                    header('Content-Type: application/json');
                    echo json_encode(['error' => $message]);
                    exit;
                }
                
                // Get form data with proper null checks
                $title = isset($_POST['title']) ? trim($_POST['title']) : '';
                $subtitle = isset($_POST['subtitle']) ? trim($_POST['subtitle']) : '';
                $isbn = isset($_POST['isbn']) ? trim($_POST['isbn']) : '';
                // Convert empty ISBN to NULL to avoid unique constraint violations
                $isbn = ($isbn === '') ? null : $isbn;
                $authorName = isset($_POST['author_name']) ? trim($_POST['author_name']) : '';
                $categoryId = isset($_POST['category_id']) ? $_POST['category_id'] : '';
                $publisherName = isset($_POST['publisher_name']) ? trim($_POST['publisher_name']) : '';
                $edition = isset($_POST['edition']) ? trim($_POST['edition']) : '';
                $publicationYear = isset($_POST['publication_year']) ? trim($_POST['publication_year']) : '';
                // Convert empty publication year to NULL
                $publicationYear = ($publicationYear === '') ? null : (int)$publicationYear;
                $pages = isset($_POST['pages']) ? trim($_POST['pages']) : '';
                $language = isset($_POST['language']) ? trim($_POST['language']) : '';
                $description = isset($_POST['description']) ? trim($_POST['description']) : '';
                $totalCopies = isset($_POST['total_copies']) ? (int)$_POST['total_copies'] : 0;
                $availableCopies = isset($_POST['available_copies']) ? (int)$_POST['available_copies'] : 0;
                $location = isset($_POST['location']) ? trim($_POST['location']) : '';
                $price = isset($_POST['price']) ? trim($_POST['price']) : '';
                $status = isset($_POST['status']) ? $_POST['status'] : 'available';
                $bookId = isset($_POST['book_id']) ? trim($_POST['book_id']) : '';
                
                // Validate required fields
                if (empty($title) || empty($authorName) || empty($categoryId) || empty($bookId)) {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Please fill in all required fields.']);
                    exit;
                }
                
                // Check if book ID already exists
                $stmt = $db->prepare("SELECT id FROM books WHERE book_id = ? AND library_id = ?");
                $stmt->execute([$bookId, $libraryId]);
                $existingBook = $stmt->fetch();
                
                if ($existingBook) {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'A book with this ID already exists.']);
                    exit;
                }
                
                // Handle file upload if provided
                $coverImage = null;
                if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['cover_image'];
                    
                    // Validate file type
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                    $fileType = mime_content_type($file['tmp_name']);
                    
                    if (!in_array($fileType, $allowedTypes)) {
                        header('Content-Type: application/json');
                        echo json_encode(['error' => 'Invalid file type. Only JPG, PNG, and GIF files are allowed.']);
                        exit;
                    }
                    
                    // Validate file size
                    if ($file['size'] > MAX_FILE_SIZE) {
                        header('Content-Type: application/json');
                        echo json_encode(['error' => 'File size exceeds the maximum limit of ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB.']);
                        exit;
                    }
                    
                    // Generate unique filename
                    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $uniqueFilename = uniqid('book_cover_') . '_' . time() . '.' . $fileExtension;
                    $uploadPath = UPLOAD_PATH . 'books/' . $uniqueFilename;
                    
                    // Move uploaded file
                    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        header('Content-Type: application/json');
                        echo json_encode(['error' => 'Failed to upload cover image.']);
                        exit;
                    }
                    
                    $coverImage = $uniqueFilename;
                }
                
                // Get the correct user ID from the database
                // Always use the session user_id to query the database for the integer id
                $sessionUserId = $_SESSION['user_id'];
                
                // Query the database to get the integer user ID
                $userStmt = $db->prepare("SELECT id FROM users WHERE (id = ? OR user_id = ?) AND library_id = ?");
                $userStmt->execute([$sessionUserId, $sessionUserId, $libraryId]);
                $user = $userStmt->fetch();
                
                if (!$user) {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'User not found. Please log in again.']);
                    exit;
                }
                
                $userId = $user['id'];
                
                // Insert new book
                $stmt = $db->prepare("
                    INSERT INTO books (
                        book_id, title, subtitle, isbn, author_name, publisher_name, category_id,
                        edition, publication_year, pages, language, description,
                        total_copies, available_copies, location, price, status, library_id, added_by, cover_image
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $result = $stmt->execute([
                    $bookId, $title, $subtitle, $isbn, $authorName, $publisherName, $categoryId,
                    $edition, $publicationYear, $pages, $language, $description,
                    $totalCopies, $availableCopies, $location, $price, $status, $libraryId, $userId, $coverImage
                ]);
                
                if ($result) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Book added successfully!'
                    ]);
                } else {
                    // Delete uploaded file if database insert fails
                    if ($coverImage && file_exists(UPLOAD_PATH . 'books/' . $coverImage)) {
                        unlink(UPLOAD_PATH . 'books/' . $coverImage);
                    }
                    
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Failed to add book.']);
                }
                break;
                
            default:
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Invalid action']);
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid request']);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>