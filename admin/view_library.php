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
$libraryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($libraryId <= 0) {
    header('Location: libraries.php');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Get library details
    $stmt = $db->prepare("
        SELECT l.*, u.first_name, u.last_name, u.email as supervisor_email 
        FROM libraries l 
        LEFT JOIN users u ON l.supervisor_id = u.id 
        WHERE l.id = ?
    ");
    $stmt->execute([$libraryId]);
    $library = $stmt->fetch();
    
    if (!$library) {
        header('Location: libraries.php');
        exit;
    }
    
    // Get library subscription information
    $stmt = $db->prepare("
        SELECT *
        FROM subscriptions
        WHERE library_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$libraryId]);
    $librarySubscription = $stmt->fetch();
    
    // Get library statistics
    // Get total librarians
    $stmt = $db->prepare("SELECT COUNT(*) as total_librarians FROM users WHERE library_id = ? AND role = 'librarian' AND status = 'active'");
    $stmt->execute([$libraryId]);
    $librarianCount = $stmt->fetch()['total_librarians'];
    
    // Get total members
    $stmt = $db->prepare("SELECT COUNT(*) as total_members FROM users WHERE library_id = ? AND role = 'member' AND status = 'active'");
    $stmt->execute([$libraryId]);
    $memberCount = $stmt->fetch()['total_members'];
    
    // Get total books
    $stmt = $db->prepare("SELECT COUNT(*) as total_books FROM books WHERE library_id = ? AND status = 'active'");
    $stmt->execute([$libraryId]);
    $bookCount = $stmt->fetch()['total_books'];
    
} catch (Exception $e) {
    die('Error loading library: ' . $e->getMessage());
}

$pageTitle = 'View Library';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo htmlspecialchars($library['library_name']); ?> - LMS</title>
    
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
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(0, 102, 204, 0.2);
        }
        
        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 102, 204, 0.3);
        }
        
        .library-details {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }
        
        .library-logo-section {
            text-align: center;
        }
        
        .library-logo-large {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border-radius: 12px;
            background-color: var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }
        
        .library-logo-large i {
            font-size: 4rem;
            color: var(--gray-500);
        }
        
        .library-info {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .info-group {
            margin-bottom: 1rem;
        }
        
        .info-group h3 {
            color: var(--gray-800);
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
            border-bottom: 1px solid var(--gray-300);
            padding-bottom: 0.5rem;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 0.75rem;
        }
        
        .info-label {
            font-weight: 600;
            width: 150px;
            color: var(--gray-700);
        }
        
        .info-value {
            flex: 1;
            color: var(--gray-900);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-active {
            background-color: var(--success-color);
            color: white;
        }
        
        .status-inactive {
            background-color: var(--gray-300);
            color: var(--gray-700);
        }
        
        .status-warning {
            background-color: var(--warning-color);
            color: white;
        }
        
        .status-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            justify-content: center;
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
        
        .btn-danger {
            background: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background: #b71c1c;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(198, 40, 40, 0.3);
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
        
        /* Responsive */
        @media (max-width: 768px) {
            .library-details {
                grid-template-columns: 1fr;
            }
            
            .info-row {
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .info-label {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/admin_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-building"></i> <?php echo $pageTitle; ?></h1>
            <p>View detailed information about this library</p>
        </div>
        
        <div class="content-card">
            <div class="card-header">
                <h2><?php echo htmlspecialchars($library['library_name']); ?></h2>
                <a href="libraries.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Libraries
                </a>
            </div>
            
            <div class="library-details">
                <div class="library-logo-section">
                    <?php if (!empty($library['logo_path']) && file_exists('../' . $library['logo_path'])): ?>
                        <img src="../<?php echo htmlspecialchars($library['logo_path']); ?>" 
                             alt="Library logo" 
                             class="library-logo-large">
                    <?php else: ?>
                        <div class="library-logo-large">
                            <i class="fas fa-university"></i>
                        </div>
                    <?php endif; ?>
                    <h3><?php echo htmlspecialchars($library['library_name']); ?></h3>
                </div>
                
                <div class="library-info">
                    <div class="info-group">
                        <h3>Basic Information</h3>
                        <div class="info-row">
                            <div class="info-label">Library Code:</div>
                            <div class="info-value"><?php echo htmlspecialchars($library['library_code']); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Status:</div>
                            <div class="info-value">
                                <span class="status-badge status-<?php echo $library['status']; ?>">
                                    <?php echo ucfirst($library['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Created:</div>
                            <div class="info-value"><?php echo date('M j, Y', strtotime($library['created_at'])); ?></div>
                        </div>
                    </div>
                    
                    <div class="info-group">
                        <h3>Contact Information</h3>
                        <div class="info-row">
                            <div class="info-label">Address:</div>
                            <div class="info-value"><?php echo !empty($library['address']) ? htmlspecialchars($library['address']) : 'N/A'; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Phone:</div>
                            <div class="info-value"><?php echo !empty($library['phone']) ? htmlspecialchars($library['phone']) : 'N/A'; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Email:</div>
                            <div class="info-value"><?php echo !empty($library['email']) ? htmlspecialchars($library['email']) : 'N/A'; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Website:</div>
                            <div class="info-value">
                                <?php if (!empty($library['website'])): ?>
                                    <a href="<?php echo htmlspecialchars($library['website']); ?>" target="_blank">
                                        <?php echo htmlspecialchars($library['website']); ?>
                                    </a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-group">
                        <h3>Supervisor Information</h3>
                        <div class="info-row">
                            <div class="info-label">Name:</div>
                            <div class="info-value">
                                <?php 
                                if (!empty($library['first_name']) && !empty($library['last_name'])) {
                                    echo htmlspecialchars($library['first_name'] . ' ' . $library['last_name']);
                                } else {
                                    echo 'No supervisor assigned';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Email:</div>
                            <div class="info-value">
                                <?php echo !empty($library['supervisor_email']) ? htmlspecialchars($library['supervisor_email']) : 'N/A'; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-group">
                        <h3>Subscription Information</h3>
                        <?php if ($librarySubscription): ?>
                            <div class="info-row">
                                <div class="info-label">Plan Type:</div>
                                <div class="info-value"><?php echo ucfirst(htmlspecialchars($librarySubscription['plan_type'])); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Status:</div>
                                <div class="info-value">
                                    <?php if ($librarySubscription['status'] === 'trial'): ?>
                                        <span class="status-badge status-warning">
                                            <?php echo ucfirst(htmlspecialchars($librarySubscription['status'])); ?>
                                        </span>
                                    <?php elseif ($librarySubscription['status'] === 'active'): ?>
                                        <span class="status-badge status-active">
                                            <?php echo ucfirst(htmlspecialchars($librarySubscription['status'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-<?php echo $librarySubscription['status']; ?>">
                                            <?php echo ucfirst(htmlspecialchars($librarySubscription['status'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Start Date:</div>
                                <div class="info-value"><?php echo date('M j, Y', strtotime($librarySubscription['start_date'])); ?></div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">End Date:</div>
                                <div class="info-value"><?php echo date('M j, Y', strtotime($librarySubscription['end_date'])); ?></div>
                            </div>
                            <?php if ($librarySubscription['status'] === 'trial'): ?>
                                <div class="info-row">
                                    <div class="info-label">Trial Period:</div>
                                    <div class="info-value">
                                        <?php 
                                        $start = new DateTime($librarySubscription['start_date']);
                                        $end = new DateTime($librarySubscription['end_date']);
                                        $interval = $start->diff($end);
                                        echo $interval->days . ' days';
                                        ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="info-row">
                                <div class="info-label">Days Remaining:</div>
                                <div class="info-value">
                                    <?php 
                                    $today = new DateTime();
                                    $endDate = new DateTime($librarySubscription['end_date']);
                                    $remainingDays = $today->diff($endDate)->days;
                                    
                                    // Check if subscription has expired
                                    if ($endDate < $today) {
                                        echo '<span class="status-badge status-danger">Expired</span>';
                                    } else {
                                        echo $remainingDays . ' days';
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <p>No subscription information found for this library.</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="info-group">
                        <h3>Library Statistics</h3>
                        <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 150px; text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="font-size: 2rem; font-weight: bold; color: #0066cc;"><?php echo $librarianCount; ?></div>
                                <div style="color: #6c757d; margin-top: 0.5rem;">Librarians</div>
                            </div>
                            <div style="flex: 1; min-width: 150px; text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="font-size: 2rem; font-weight: bold; color: #0066cc;"><?php echo $memberCount; ?></div>
                                <div style="color: #6c757d; margin-top: 0.5rem;">Members</div>
                            </div>
                            <div style="flex: 1; min-width: 150px; text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                                <div style="font-size: 2rem; font-weight: bold; color: #0066cc;"><?php echo $bookCount; ?></div>
                                <div style="color: #6c757d; margin-top: 0.5rem;">Books</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <!-- Delete functionality removed to centralize library deletion in the libraries list page -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>