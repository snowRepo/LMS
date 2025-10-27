<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';
require_once '../includes/NotificationService.php';

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

$pageTitle = 'Process New Borrowing';

// Handle AJAX requests for member and book search
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] === 'search_members' && !empty($_GET['term'])) {
        try {
            $stmt = $db->prepare("
                SELECT 
                    id, 
                    user_id,
                    CONCAT(first_name, ' ', last_name) as name, 
                    email,
                    role,
                    status,
                    profile_image
                FROM users 
                WHERE 
                    (first_name LIKE ? OR 
                    last_name LIKE ? OR 
                    email LIKE ? OR 
                    user_id LIKE ?)
                    AND role = 'member'
                LIMIT 10
            
            ");
            $searchParam = "%{$_GET['term']}%";
            $stmt->execute([$searchParam, $searchParam, $searchParam, $searchParam]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Log the query and results for debugging
            error_log("Search term: {$_GET['term']}");
            error_log("Found " . count($results) . " members");
            if (count($results) > 0) {
                error_log("First result: " . print_r($results[0], true));
            }
            
            echo json_encode($results);
        } catch (PDOException $e) {
            error_log("Database error in search_members: " . $e->getMessage());
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($_GET['action'] === 'search_books' && !empty($_GET['term'])) {
        try {
            // Get member ID if provided
            $memberId = $_GET['member_id'] ?? null;
            
            $stmt = $db->prepare("
                SELECT 
                    b.id,
                    b.title,
                    b.author_name as author,
                    b.isbn,
                    b.description,
                    c.name as category_name,
                    b.publication_year,
                    b.total_copies as quantity,
                    b.available_copies as available,
                    b.cover_image
                FROM books b
                LEFT JOIN categories c ON b.category_id = c.id
                WHERE 
                    b.title LIKE ? OR 
                    b.author_name LIKE ? OR 
                    b.isbn LIKE ?
                ORDER BY b.title
                LIMIT 10
            
            ");
            $searchParam = "%{$_GET['term']}%";
            $stmt->execute([$searchParam, $searchParam, $searchParam]);
            $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Enhance books with reservation information if member is selected
            if ($memberId) {
                foreach ($books as &$book) {
                    // Check if there's an approved reservation for this member and book
                    $reservationStmt = $db->prepare("
                        SELECT id, status
                        FROM reservations 
                        WHERE member_id = ? AND book_id = ? AND status IN ('approved', 'borrowed')
                    ");
                    $reservationStmt->execute([$memberId, $book['id']]);
                    $reservation = $reservationStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($reservation) {
                        $book['has_reservation'] = true;
                        $book['reservation_status'] = $reservation['status'];
                    } else {
                        $book['has_reservation'] = false;
                        $book['reservation_status'] = null;
                    }
                }
            }
            
            // Log for debugging
            error_log("Book search term: {$_GET['term']}");
            error_log("Found " . count($books) . " books");
            if (count($books) > 0) {
                error_log("First book: " . print_r($books[0], true));
            }
            
            echo json_encode($books);
        } catch (PDOException $e) {
            error_log("Database error in search_books: " . $e->getMessage());
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($_GET['action'] === 'get_member' && !empty($_GET['id'])) {
        try {
            $stmt = $db->prepare("
                SELECT 
                    id, 
                    user_id,
                    CONCAT(first_name, ' ', last_name) as name, 
                    email,
                    role,
                    status,
                    profile_image
                FROM users 
                WHERE id = ? AND role = 'member'
            ");
            $stmt->execute([$_GET['id']]);
            $member = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($member ?: ['error' => 'Member not found']);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error']);
        }
        exit;
    }
    
    if ($_GET['action'] === 'get_book' && !empty($_GET['id'])) {
        try {
            $stmt = $db->prepare("
                SELECT 
                    b.id,
                    b.book_id,
                    b.isbn,
                    b.title,
                    b.author_name as author,
                    b.description,
                    b.edition,
                    b.publication_year,
                    b.pages,
                    b.language,
                    b.cover_image,
                    b.category_id,
                    b.library_id,
                    b.total_copies as quantity,
                    b.available_copies,
                    b.location,
                    b.price,
                    b.added_by,
                    b.created_at,
                    b.updated_at,
                    b.status,
                    c.name as category_name,
                    (SELECT COUNT(*) FROM borrowings WHERE book_id = b.id AND return_date IS NULL) as borrowed_count
                FROM books b
                LEFT JOIN categories c ON b.category_id = c.id
                WHERE b.id = ?
            ");
            $stmt->execute([$_GET['id']]);
            $book = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($book) {
                $book['available'] = $book['available_copies'];
                unset($book['borrowed_count']);
            }
            
            echo json_encode($book ?: ['error' => 'Book not found']);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Database error']);
        }
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    
    // Validate required fields
    $errors = [];
    $memberId = $_POST['member_id'] ?? '';
    $bookId = $_POST['book_id'] ?? '';
    $dueDate = $_POST['due_date'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if (empty($memberId)) $errors[] = 'Member is required';
    if (empty($bookId)) $errors[] = 'Book is required';
    if (empty($dueDate)) $errors[] = 'Due date is required';
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Check if there's an approved reservation for this member and book
            // Also check for any reservation that might indicate the book was already accounted for
            $reservationStmt = $db->prepare("
                SELECT id, reservation_id, status
                FROM reservations 
                WHERE member_id = ? AND book_id = ? AND status IN ('approved', 'borrowed')
                FOR UPDATE
            ");
            $reservationStmt->execute([$memberId, $bookId]);
            $existingReservation = $reservationStmt->fetch(PDO::FETCH_ASSOC);
            
            $fromReservation = !empty($existingReservation);
            
            if ($fromReservation) {
                // If coming from reservation, check that the book is available
                // (it should be available since it was reserved)
                $stmt = $db->prepare("
                    SELECT 
                        b.available_copies
                    FROM books b
                    WHERE b.id = ?
                    FOR UPDATE
                ");
                $stmt->execute([$bookId]);
                $book = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$book) {
                    throw new Exception('Book not found');
                }
                
                // No need to decrement available copies - already done when reservation was made
                // However, if there are no available copies, verify there's a valid reservation
                if ($book['available_copies'] < 1) {
                    // We already know there's a reservation ($fromReservation is true), so this is valid
                    // The reservation guarantees that borrowing is allowed even with 0 available copies
                }
            } else {
                // Direct borrowing - check and decrement available copies
                $stmt = $db->prepare("
                    SELECT 
                        b.available_copies
                    FROM books b
                    WHERE b.id = ?
                    FOR UPDATE
                ");
                $stmt->execute([$bookId]);
                $book = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$book) {
                    throw new Exception('Book not found');
                }
                
                if ($book['available_copies'] <= 0) {
                    throw new Exception('No available copies of this book');
                }
                
                // Decrement available copies for direct borrowing
                $stmt = $db->prepare("
                    UPDATE books 
                    SET available_copies = available_copies - 1 
                    WHERE id = ?
                ");
                $stmt->execute([$bookId]);
            }
            
            // Create borrowing record
            $stmt = $db->prepare("
                INSERT INTO borrowings (
                    transaction_id,
                    member_id, 
                    book_id, 
                    issued_by,
                    issue_date, 
                    due_date, 
                    notes,
                    status
                ) VALUES (?, ?, ?, ?, CURDATE(), ?, ?, 'active')
            
            ");
            
            // Generate transaction ID
            $transactionId = 'TXN' . time() . rand(1000, 9999);
            
            $success = $stmt->execute([
                $transactionId,
                $memberId,
                $bookId,
                $_SESSION['user_id'],
                $dueDate,
                $notes
            ]);
            
            if ($success) {
                // If this borrowing came from a reservation, update the reservation status to 'borrowed'
                if ($fromReservation) {
                    $updateReservationStmt = $db->prepare("
                        UPDATE reservations 
                        SET status = 'borrowed',
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $updateReservationStmt->execute([$existingReservation['id']]);
                }
                
                // Send notification to member about the borrowing
                try {
                    // Get member details for notification
                    $memberStmt = $db->prepare("
                        SELECT u.user_id, CONCAT(u.first_name, ' ', u.last_name) as member_name, b.title as book_title
                        FROM users u
                        JOIN books b ON b.id = ?
                        WHERE u.id = ?
                    ");
                    $memberStmt->execute([$bookId, $memberId]);
                    $memberInfo = $memberStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($memberInfo) {
                        // Create notification service instance
                        $notificationService = new NotificationService();
                        
                        // Prepare notification message
                        $title = 'Book Borrowed';
                        $message = "You have successfully borrowed '{$memberInfo['book_title']}'. The due date is " . date('M j, Y', strtotime($dueDate)) . ".";
                        $actionUrl = "../member/borrowing.php";
                        
                        // Create notification for member
                        $notificationService->createNotification(
                            $memberInfo['user_id'],
                            $title,
                            $message,
                            'success',
                            $actionUrl
                        );
                    }
                } catch (Exception $e) {
                    // Log error but don't stop the process
                    error_log("Error sending borrowing notification: " . $e->getMessage());
                }
                
                $db->commit();
                // Redirect with success message for toast notification
                header('Location: borrowing.php?success=' . urlencode('Book borrowed successfully'));
                exit;
            } else {
                throw new Exception('Failed to process borrowing');
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
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
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <style>
        :root {
            --primary-color: #3498DB;
            --primary-dark: #2980B9;
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            color: #333;
            min-height: 100vh;
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
            margin: 0 0 0.5rem;
            font-size: 2rem;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
        }
        
        .page-header p {
            color: var(--gray-600);
            margin: 0;
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        #borrowingForm {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
        }
        
        .form-group {
            width: 100%;
            max-width: 500px;
            margin: 0 auto 1.75rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--gray-700);
            font-size: 0.95rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.85rem 1rem;
            border: 1.5px solid #95A5A6;
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
            box-sizing: border-box;
            background-color: #fff;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15);
            background-color: #fff;
        }
        
        .search-container {
            position: relative;
            width: 100%;
        }
        
        .search-results {
            position: absolute;
            width: 100%;
            max-height: 300px;
            overflow-y: auto;
            background: white;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            box-shadow: var(--box-shadow);
            z-index: 1000;
            display: none;
            margin-top: 0.5rem;
        }
        
        .search-result-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            transition: background 0.2s;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .search-result-item:last-child {
            border-bottom: none;
        }
        
        .search-result-item:hover {
            background: var(--gray-100);
        }
        
        .search-result-item h4 {
            margin: 0 0 0.25rem;
            color: var(--gray-900);
            font-size: 0.95rem;
        }
        
        .search-result-item p {
            margin: 0;
            font-size: 0.85rem;
            color: var(--gray-600);
        }
        
        .book-details, #memberInfo {
            background: var(--gray-100);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin: 1rem auto 0;
            width: 100%;
            max-width: 500px;
            box-sizing: border-box;
            border: 1px solid var(--gray-200);
        }
        
        .book-cover {
            width: 100px;
            height: 140px;
            background: var(--gray-200);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-600);
            margin-right: 1.5rem;
            flex-shrink: 0;
        }
        
        .book-info h3, #memberName {
            margin: 0 0 0.75rem;
            color: var(--gray-900);
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .book-meta {
            color: var(--gray-600);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .book-availability {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-active { 
            background: #e8f5e9; 
            color: #2e7d32;
        }
        
        .status-inactive { 
            background: #ffebee; 
            color: #c62828;
        }
        
        .form-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2.5rem;
            padding-top: 1.75rem;
            border-top: 1px solid var(--gray-200);
            width: 100%;
            max-width: 500px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.85rem 1.75rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: var(--transition);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(52, 152, 219, 0.2);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, #2573A7 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(52, 152, 219, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            padding: 0.75rem 1.5rem;
            box-shadow: none;
        }
        
        .btn-outline:hover {
            background: rgba(52, 152, 219, 0.1);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(52, 152, 219, 0.1);
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .content-card {
                padding: 1.5rem;
            }
            
            .card-header h2 {
                font-size: 1.5rem;
            }
            
            .form-group {
                margin-bottom: 1.5rem;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .btn, .btn-outline {
                width: 100%;
                padding: 0.85rem;
            }
        }
        
        .text-success {
            color: var(--success-color);
        }
        
        .text-danger {
            color: var(--danger-color);
        }
        
        .text-warning {
            color: var(--warning-color);
        }
        
        .text-muted {
            color: var(--gray-600);
        }
    </style>
</head>
<body>
    <?php include 'includes/librarian_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-book"></i> Process New Borrowing</h1>
            <p>Issue books to library members and manage due dates</p>
        </div>
        
        <div class="content-card">
            <div class="card-header">
                <h2><i class="fas fa-exchange-alt"></i> Borrowing Form</h2>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div style="background: #ffebee; border: 1px solid #ffcdd2; color: #c62828; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <strong>Error:</strong>
                    <ul style="margin: 0.5rem 0 0 1rem;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form id="borrowingForm" method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="form-group">
                    <label for="memberSearch" class="form-label">Search Member</label>
                    <div class="search-container">
                        <input type="text" 
                               id="memberSearch" 
                               class="form-control" 
                               placeholder="Search by name, email, or member ID..."
                               autocomplete="off">
                        <input type="hidden" id="memberId" name="member_id">
                        <div class="search-results" id="memberResults"></div>
                    </div>
                </div>
                
                <div id="memberInfo" class="book-details">
                    <div style="display: flex; align-items: center;">
                        <div class="book-cover" id="memberAvatar">
                            <i class="fas fa-user" style="font-size: 2rem;"></i>
                        </div>
                        <div class="book-info">
                            <h3 id="memberName">Member Name</h3>
                            <div class="book-meta">
                                <span id="memberIdDisplay">ID: -</span> | 
                                <span id="memberEmail">email@example.com</span>
                            </div>
                            <div class="book-meta">
                                <span id="memberType">Member Type</span> | 
                                <span id="memberStatus" class="status-active">Active</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="bookSearchSection" class="form-group" style="margin-top: 2rem;">
                    <label for="bookSearch" class="form-label">Search Book</label>
                    <div class="search-container">
                        <input type="text" 
                               id="bookSearch" 
                               class="form-control" 
                               placeholder="Search by title, author, or ISBN..."
                               autocomplete="off">
                        <input type="hidden" id="bookId" name="book_id">
                        <div class="search-results" id="bookResults"></div>
                    </div>
                </div>
                
                <div id="bookInfo" class="book-details">
                    <div style="display: flex;">
                        <div class="book-cover" id="bookCover">
                            <i class="fas fa-book" style="font-size: 2rem;"></i>
                        </div>
                        <div class="book-info">
                            <h3 id="bookTitle">Book Title</h3>
                            <div class="book-meta">
                                <span id="bookAuthor">Author Name</span> | 
                                <span id="bookIsbn">ISBN: -</span>
                            </div>
                            <div class="book-meta">
                                <span id="bookCategory">Category</span> | 
                                <span id="bookYear">Publishing Year</span>
                            </div>
                            <div id="bookAvailability" class="book-availability status-active">
                                Available (X copies)
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="dueDateSection" class="form-group" style="margin-top: 2rem;">
                    <label for="dueDate" class="form-label">Due Date</label>
                    <input type="date" 
                           id="dueDate" 
                           name="due_date" 
                           class="form-control" 
                           min="<?php echo date('Y-m-d'); ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="notes" class="form-label">Notes (Optional)</label>
                    <textarea id="notes" 
                              name="notes" 
                              class="form-control" 
                              rows="3" 
                              placeholder="Add any additional notes..."></textarea>
                </div>
                
                <div class="form-actions">
                    <a href="borrowing.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Borrowings
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Process Borrowing
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Set default due date to 14 days from now
            const today = new Date();
            const dueDate = new Date();
            dueDate.setDate(today.getDate() + 14);
            const formattedDueDate = dueDate.toISOString().split('T')[0];
            $('#dueDate').val(formattedDueDate);
            
            // Store member status when member is selected
            let currentMemberStatus = '';
            
            // Member search functionality
            let memberSearchTimeout;
            $('#memberSearch').on('input', function() {
                clearTimeout(memberSearchTimeout);
                const searchTerm = $(this).val().trim();
                
                if (searchTerm.length < 2) {
                    $('#memberResults').hide().empty();
                    return;
                }
                
                memberSearchTimeout = setTimeout(() => {
                    $.ajax({
                        type: 'GET',
                        url: 'process_borrowing.php',
                        data: {
                            action: 'search_members',
                            term: searchTerm
                        },
                        dataType: 'json',
                        success: function(response) {
                            console.log('Search response:', response);
                            const $results = $('#memberResults');
                            $results.empty();
                            
                            if (response && response.length > 0) {
                                response.forEach(member => {
                                    $results.append(`
                                        <div class="search-result-item" 
                                             data-id="${member.id}"
                                             data-name="${member.name}"
                                             data-email="${member.email}"
                                             data-member-id="${member.user_id}"
                                             data-type="${member.role}"
                                             data-status="${member.status}"
                                             data-profile-image="${member.profile_image || ''}">
                                            <h4>${member.name}</h4>
                                            <p>${member.email} | ${member.user_id} | ${member.role}</p>
                                        </div>
                                    `);
                                });
                                $results.show();
                            } else {
                                $results.append('<div class="search-result-item">No members found</div>');
                                $results.show();
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Search error:', status, error);
                            const $results = $('#memberResults');
                            $results.html('<div class="search-result-item">Error loading members</div>').show();
                        }
                    });
                }, 300); // 300ms debounce
            });

            // Handle member selection
            $(document).on('click', '#memberResults .search-result-item[data-id]', function() {
                const $item = $(this);
                const memberId = $item.data('id');
                const profileImage = $item.data('profile-image');
                
                // Store the member status
                currentMemberStatus = $item.data('status') || '';
                
                // Update hidden input
                $('#memberId').val(memberId);
                
                // Update search field
                $('#memberSearch').val($item.data('name'));
                
                // Hide results
                $('#memberResults').hide();
                
                // Update member info display
                $('#memberName').text($item.data('name'));
                $('#memberIdDisplay').text('ID: ' + $item.data('member-id'));
                $('#memberEmail').text($item.data('email'));
                $('#memberType').text($item.data('type'));
                
                const $statusEl = $('#memberStatus');
                $statusEl.removeClass('status-inactive status-active');
                
                // Set status color - green for active, red for inactive
                if (currentMemberStatus && currentMemberStatus.toString().toLowerCase() === 'active') {
                    $statusEl.addClass('status-active');
                    $statusEl.html(`<i class="fas fa-check-circle"></i> Active`);
                } else {
                    $statusEl.addClass('status-inactive');
                    $statusEl.html(`<i class="fas fa-times-circle"></i> Inactive`);
                }
                
                // Update member avatar
                const $avatar = $('#memberAvatar');
                if (profileImage && profileImage !== '') {
                    // Check if the image file exists
                    $.get(`../${profileImage}`)
                        .done(function() {
                            $avatar.html(`<img src="../${profileImage}" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">`);
                        })
                        .fail(function() {
                            // If image doesn't exist, show initials
                            const nameParts = $item.data('name').split(' ');
                            let initials = '';
                            for (let i = 0; i < Math.min(nameParts.length, 2); i++) {
                                initials += nameParts[i].charAt(0).toUpperCase();
                            }
                            $avatar.html(`<div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; background: #3498db; color: white; font-weight: bold; font-size: 1.2rem;">${initials}</div>`);
                        });
                } else {
                    // Show initials fallback
                    const nameParts = $item.data('name').split(' ');
                    let initials = '';
                    for (let i = 0; i < Math.min(nameParts.length, 2); i++) {
                        initials += nameParts[i].charAt(0).toUpperCase();
                    }
                    $avatar.html(`<div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; background: #3498db; color: white; font-weight: bold; font-size: 1.2rem;">${initials}</div>`);
                }
                
                // Show the member info section
                $('#memberInfo').fadeIn();
                
                // Enable book search
                $('#bookSearch').prop('disabled', false);
                
                // Trigger book search if there's already text in the book search field
                const bookSearchTerm = $('#bookSearch').val().trim();
                if (bookSearchTerm.length >= 2) {
                    $('#bookSearch').trigger('input');
                }
            });
            
            // Book search functionality
            let bookSearchTimeout;
            $('#bookSearch').on('input', function() {
                clearTimeout(bookSearchTimeout);
                const searchTerm = $(this).val().trim();
                
                if (searchTerm.length < 2) {
                    $('#bookResults').hide().empty();
                    return;
                }
                
                bookSearchTimeout = setTimeout(() => {
                    // Get current member ID
                    const memberId = $('#memberId').val();
                    
                    const requestData = {
                        action: 'search_books',
                        term: searchTerm
                    };
                    
                    // Add member ID if available
                    if (memberId) {
                        requestData.member_id = memberId;
                    }
                    
                    $.ajax({
                        type: 'GET',
                        url: 'process_borrowing.php',
                        data: requestData,
                        dataType: 'json',
                        success: function(response) {
                            console.log('Book search response:', response);
                            const $results = $('#bookResults');
                            $results.empty();
                            
                            if (response && response.length > 0) {
                                response.forEach(book => {
                                    // Determine availability text
                                    let availableText;
                                    if (book.has_reservation && book.reservation_status === 'approved') {
                                        availableText = `<span style="color: #155724;">Reserved for this member</span>`;
                                    } else if (book.available > 0) {
                                        availableText = `<span style="color: #155724;">Available (${book.available} of ${book.quantity})</span>`;
                                    } else {
                                        availableText = '<span style="color: #721c24;">Not Available</span>';
                                    }
                                        
                                    $results.append(`
                                        <div class="search-result-item" 
                                             data-id="${book.id}"
                                             data-title="${book.title}"
                                             data-author="${book.author}"
                                             data-isbn="${book.isbn}"
                                             data-category="${book.category_name || 'N/A'}"
                                             data-year="${book.publication_year || ''}"
                                             data-available="${book.available}"
                                             data-total="${book.quantity}"
                                             data-has-reservation="${book.has_reservation || false}"
                                             data-reservation-status="${book.reservation_status || ''}"
                                             data-cover-image="${book.cover_image || ''}">
                                            <h4>${book.title}</h4>
                                            <p>${book.author} | ${book.isbn} | ${availableText}</p>
                                        </div>
                                    `);
                                });
                                $results.show();
                            } else {
                                $results.html('<div class="search-result-item">No books found</div>');
                                $results.show();
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Book search error:', status, error);
                            const $results = $('#bookResults');
                            $results.html('<div class="search-result-item">Error loading books</div>').show();
                        }
                    });
                }, 300); // 300ms debounce
            });
            
            // Handle book selection
            $(document).on('click', '#bookResults .search-result-item[data-id]', function() {
                const $item = $(this);
                const bookId = $item.data('id');
                const coverImage = $item.data('cover-image');
                const hasReservation = $item.data('has-reservation');
                const reservationStatus = $item.data('reservation-status');
                
                // Update hidden input
                $('#bookId').val(bookId);
                
                // Update search field
                $('#bookSearch').val($item.data('title'));
                
                // Hide results
                $('#bookResults').hide();
                
                // Update book info display
                $('#bookTitle').text($item.data('title'));
                $('#bookAuthor').text('by ' + $item.data('author'));
                $('#bookIsbn').text('ISBN: ' + $item.data('isbn'));
                $('#bookCategory').text('Category: ' + $item.data('category'));
                $('#bookYear').text('Year: ' + ($item.data('year') || 'N/A'));
                
                // Update availability
                const available = parseInt($item.data('available'));
                const total = parseInt($item.data('total'));
                const $availability = $('#bookAvailability');
                
                $availability.removeClass('status-active status-inactive');
                if (available > 0 || (hasReservation && reservationStatus === 'approved')) {
                    if (hasReservation && reservationStatus === 'approved') {
                        $availability.html(`<i class="fas fa-check-circle"></i> Reserved for this member`);
                        $availability.addClass('status-active');
                    } else {
                        $availability.html(`<i class="fas fa-check-circle"></i> Available (${available} of ${total} copies)`);
                        $availability.addClass('status-active');
                    }
                    $('#submitBorrow').prop('disabled', false);
                } else {
                    $availability.html('<i class="fas fa-times-circle"></i> Not available for borrowing');
                    $availability.addClass('status-inactive');
                    $('#submitBorrow').prop('disabled', true);
                }
                
                // Update book cover
                const $cover = $('#bookCover');
                if (coverImage && coverImage !== '') {
                    // Check if the image file exists
                    $.get(`../uploads/books/${coverImage}`)
                        .done(function() {
                            $cover.html(`<img src="../uploads/books/${coverImage}" alt="Cover" style="width: 100%; height: 100%; object-fit: cover;">`);
                        })
                        .fail(function() {
                            // If image doesn't exist, show book icon
                            $cover.html(`<i class="fas fa-book" style="font-size: 2rem;"></i>`);
                        });
                } else {
                    // Show book icon fallback
                    $cover.html(`<i class="fas fa-book" style="font-size: 2rem;"></i>`);
                }
                
                // Show the book info section
                $('#bookInfo').fadeIn();
            });
            
            // Hide search results when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.search-container').length) {
                    $('.search-results').hide();
                }
            });
            
            // Form submission
            $('#borrowingForm').on('submit', function(e) {
                e.preventDefault();
                
                // Add form validation here
                if (!$('#memberId').val()) {
                    alert('Please select a member');
                    return;
                }
                
                if (!$('#bookId').val()) {
                    alert('Please select a book');
                    return;
                }
                
                // Get the current book information
                const $selectedBookItem = $('#bookResults .search-result-item[data-id="' + $('#bookId').val() + '"]');
                const available = parseInt($('#bookAvailability').text().match(/Available \((\d+)/)?.[1] || '0');
                const hasReservation = $('#bookAvailability').text().includes('Reserved for this member');
                
                if (available <= 0 && !hasReservation) {
                    alert('This book is not available for borrowing');
                    return;
                }
                
                // Submit the form
                this.submit();
            });
        });
    </script>
</body>
</html>