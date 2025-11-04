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

// Check if user is logged in and has admin role
if (!is_logged_in() || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Get library ID from URL parameter
$libraryId = isset($_GET['library_id']) ? (int)$_GET['library_id'] : 0;

if ($libraryId <= 0) {
    header('Location: book_reports.php');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Get library information with address and email (removed library_code)
    $stmt = $db->prepare("SELECT library_name, address, email FROM libraries WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$libraryId]);
    $library = $stmt->fetch();
    
    if (!$library) {
        header('Location: book_reports.php');
        exit;
    }
    
    // Get book categories summary for this library
    // Fixed: Changed c.category_name to c.name to match actual database structure
    $stmt = $db->prepare("
        SELECT 
            c.name as category_name,
            COUNT(b.id) as book_count,
            COUNT(DISTINCT b.author_name) as author_count
        FROM books b
        LEFT JOIN categories c ON b.category_id = c.id
        WHERE b.library_id = ? AND b.status = 'active'
        GROUP BY c.id, c.name
        ORDER BY c.name
    ");
    $stmt->execute([$libraryId]);
    $categories = $stmt->fetchAll();
    
    // Get total books count for this library
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_books
        FROM books b
        WHERE b.library_id = ? AND b.status = 'active'
    ");
    $stmt->execute([$libraryId]);
    $totalBooks = $stmt->fetch()['total_books'];
    
} catch (Exception $e) {
    die('Error loading book data: ' . $e->getMessage());
}

$pageTitle = 'Library Books';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - LMS</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #0066cc; /* macOS deeper blue */
            --primary-dark: #0052a3;
            --secondary-color: #f8f9fa;
            --success-color: #1b5e20; /* Deep green for active status */
            --danger-color: #c62828; /* Deep red for error states */
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
            background: #f8f9fa;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #495057;
            padding-top: 60px; /* Space for navbar */
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            margin-bottom: 30px;
            text-align: center;
        }

        .page-header h1 {
            color: #2c3e50;
            font-size: 1.8rem;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #6c757d;
            font-size: 1.1rem;
        }

        .content-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 25px;
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
        
        .library-info {
            text-align: center;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .library-info h3 {
            margin-top: 0;
            color: var(--primary-color);
            font-size: 1.5rem;
        }
        
        .library-details {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
            min-width: 150px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .detail-value {
            font-size: 1.1rem;
            color: #212529;
        }
        
        .books-section {
            margin-top: 2rem;
        }
        
        .section-title {
            text-align: center;
            color: var(--gray-900);
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .table-container {
            overflow-x: auto;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table th,
        .report-table td {
            padding: 1rem;
            text-align: center;
            border-bottom: 1px solid #dee2e6;
        }

        .report-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #212529;
            text-align: center;
        }

        .report-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .count-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
            background-color: var(--primary-color);
            color: white;
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
            transition: var(--transition);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(0, 102, 204, 0.2);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 102, 204, 0.3);
        }
        
        .back-btn {
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
            background: var(--primary-color);
            color: white;
        }
        
        .back-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 102, 204, 0.3);
        }
        
        .print-btn {
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
            background: #28a745;
            color: white;
        }
        
        .print-btn:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);
        }
        
        .no-data {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
        
        @media (max-width: 768px) {
            .library-details {
                flex-direction: column;
                gap: 1rem;
            }
            
            .report-table th,
            .report-table td {
                padding: 0.75rem 0.5rem;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }
        
        @media print {
            body * {
                visibility: hidden;
            }
            .print-area, .print-area * {
                visibility: visible;
            }
            .print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
            
            /* Chrome-specific print styles */
            .status-badge {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            /* Make library details display horizontally in print */
            .library-details {
                display: table !important;
                width: 100% !important;
                margin-top: 1rem !important;
                page-break-inside: avoid !important;
            }
            
            .detail-item {
                display: table-cell !important;
                padding: 0 1rem !important;
                text-align: center !important;
                vertical-align: top !important;
            }
            
            .detail-label {
                display: block !important;
                font-weight: 600 !important;
                color: #6c757d !important;
                font-size: 0.9rem !important;
                margin-bottom: 0.25rem !important;
            }
            
            .detail-value {
                display: block !important;
                font-size: 1.1rem !important;
                color: #212529 !important;
            }
            
            .library-info {
                text-align: center !important;
                background: #f8f9fa !important;
                border-radius: 8px !important;
                padding: 1.5rem !important;
                margin-bottom: 1.5rem !important;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/admin_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-book"></i> <?php echo $pageTitle; ?></h1>
            <p>Books in <?php echo htmlspecialchars($library['library_name']); ?></p>
        </div>

        <div class="content-card print-area">
            <div class="card-header">
                <h2><i class="fas fa-info-circle"></i> Library Information</h2>
                <div class="btn-group no-print">
                    <a href="book_reports.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Book Reports
                    </a>
                    <button class="print-btn" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>
            
            <div class="library-info">
                <h3><?php echo htmlspecialchars($library['library_name']); ?></h3>
                <div class="library-details">
                    <?php if (!empty($library['address'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Address</span>
                            <span class="detail-value"><?php echo htmlspecialchars($library['address']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($library['email'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Email</span>
                            <span class="detail-value"><?php echo htmlspecialchars($library['email']); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="detail-item">
                        <span class="detail-label">Total Books</span>
                        <span class="detail-value"><?php echo $totalBooks; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="books-section">
                <h3 class="section-title">Book Categories Summary</h3>
                
                <?php if (empty($categories)): ?>
                    <div class="no-data">
                        <i class="fas fa-book fa-3x" style="margin-bottom: 1rem; color: #ced4da;"></i>
                        <h3>No Books Found</h3>
                        <p>This library currently has no books.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Book Count</th>
                                    <th>Author Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category['category_name'] ?? 'Uncategorized'); ?></td>
                                        <td>
                                            <span class="count-badge">
                                                <?php echo $category['book_count']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="count-badge">
                                                <?php echo $category['author_count']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>