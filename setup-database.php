<?php
define('LMS_ACCESS', true);

// Load environment and config
try {
    require_once 'includes/EnvLoader.php';
    EnvLoader::load();
    include 'config/config.php';
    $configLoaded = true;
} catch (Exception $e) {
    $configLoaded = false;
    $configError = $e->getMessage();
}

// Initialize variables
$setupResult = null;
$dbExists = false;
$tablesExist = false;
$tableStatus = [];

// Check database existence
if ($configLoaded) {
    try {
        // Connect without selecting database to check if it exists
        $tempConn = new PDO(
            "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET,
            DB_USERNAME,
            DB_PASSWORD,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Check if database exists
        $stmt = $tempConn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
        $dbExists = $stmt->rowCount() > 0;
        
        if ($dbExists) {
            // Connect to the specific database
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            // Check which tables exist
            $expectedTables = [
                'libraries', 'users', 'categories', 'authors', 'publishers', 
                'books', 'book_authors', 'borrowings', 'reservations', 
                'fines', 'activity_logs', 'settings', 'notifications'
            ];
            
            foreach ($expectedTables as $table) {
                try {
                    $stmt = $conn->query("SELECT 1 FROM $table LIMIT 1");
                    $tableStatus[$table] = 'exists';
                } catch (PDOException $e) {
                    $tableStatus[$table] = 'missing';
                }
            }
            
            $tablesExist = !in_array('missing', $tableStatus);
        }
        
    } catch (PDOException $e) {
        $setupResult = [
            'success' => false,
            'message' => 'Database connection error: ' . $e->getMessage()
        ];
    }
}

// Handle setup actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $configLoaded) {
    try {
        if (isset($_POST['create_database'])) {
            // Create database
            $tempConn = new PDO(
                "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET,
                DB_USERNAME,
                DB_PASSWORD,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            $tempConn->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
            
            $setupResult = [
                'success' => true,
                'message' => 'Database "' . DB_NAME . '" created successfully!'
            ];
            
            // Refresh page to update status
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        
        if (isset($_POST['setup_tables'])) {
            // Setup tables from schema
            $schemaFile = __DIR__ . '/database/schema.sql';
            
            if (!file_exists($schemaFile)) {
                throw new Exception('Schema file not found: ' . $schemaFile);
            }
            
            $schema = file_get_contents($schemaFile);
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            // Execute schema
            $conn->exec($schema);
            
            $setupResult = [
                'success' => true,
                'message' => 'Database tables created successfully! Default admin user created with username: admin, password: admin123'
            ];
            
            // Refresh page to update status
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        
        if (isset($_POST['reset_database'])) {
            // Reset database (drop and recreate)
            $tempConn = new PDO(
                "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET,
                DB_USERNAME,
                DB_PASSWORD,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            $tempConn->exec("DROP DATABASE IF EXISTS `" . DB_NAME . "`");
            $tempConn->exec("CREATE DATABASE `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
            
            $setupResult = [
                'success' => true,
                'message' => 'Database reset successfully! Please setup tables again.'
            ];
            
            // Refresh page to update status
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        
    } catch (Exception $e) {
        $setupResult = [
            'success' => false,
            'message' => 'Setup error: ' . $e->getMessage()
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - LMS</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Navbar CSS -->
    <link rel="stylesheet" href="css/navbar.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }

        .container {
            max-width: 1000px;
            margin: 2rem auto;
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .status-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .status-card h3 {
            color: #495057;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-success {
            color: #28a745;
        }

        .status-error {
            color: #dc3545;
        }

        .status-warning {
            color: #ffc107;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .table th,
        .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.75rem 1.5rem;
            background: #3498DB;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .btn:hover {
            background: #2980B9;
            transform: translateY(-1px);
        }

        .btn-success {
            background: #28a745;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .btn-danger {
            background: #dc3545;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #495057;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin: 1rem 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            transition: width 0.3s ease;
        }

        .schema-info {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 1rem;
            margin: 1rem 0;
        }

        .schema-info h4 {
            margin-bottom: 0.5rem;
            color: #1976d2;
        }

        .confirm-dialog {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <h1><i class="fas fa-database"></i> Database Setup</h1>
        <p>Set up the database structure for your Library Management System.</p>

        <?php if (!$configLoaded): ?>
            <div class="alert alert-danger">
                <strong>Configuration Error:</strong> <?php echo htmlspecialchars($configError); ?>
                <br><a href="setup-env.php">Please configure your environment first</a>
            </div>
        <?php else: ?>

        <!-- Setup Result -->
        <?php if ($setupResult): ?>
            <div class="alert alert-<?php echo $setupResult['success'] ? 'success' : 'danger'; ?>">
                <?php echo htmlspecialchars($setupResult['message']); ?>
            </div>
        <?php endif; ?>

        <!-- Database Status -->
        <div class="status-card">
            <h3>
                <i class="fas fa-database"></i>
                Database Status
                <?php if ($dbExists): ?>
                    <span class="status-success">✓ Database exists</span>
                <?php else: ?>
                    <span class="status-error">✗ Database not found</span>
                <?php endif; ?>
            </h3>
            
            <p><strong>Database Name:</strong> <?php echo DB_NAME; ?></p>
            <p><strong>Host:</strong> <?php echo DB_HOST; ?></p>
            <p><strong>Username:</strong> <?php echo DB_USERNAME; ?></p>
            
            <?php if (!$dbExists): ?>
                <div class="alert alert-info">
                    <strong>Database Not Found:</strong> The database "<?php echo DB_NAME; ?>" doesn't exist yet.
                </div>
                
                <form method="POST">
                    <button type="submit" name="create_database" class="btn btn-success">
                        <i class="fas fa-plus"></i>
                        Create Database
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($dbExists): ?>
        <!-- Tables Status -->
        <div class="status-card">
            <h3>
                <i class="fas fa-table"></i>
                Tables Status
                <?php if ($tablesExist): ?>
                    <span class="status-success">✓ All tables exist</span>
                <?php else: ?>
                    <span class="status-warning">⚠ Some tables missing</span>
                <?php endif; ?>
            </h3>
            
            <?php if (!empty($tableStatus)): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Table Name</th>
                            <th>Status</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $tableDescriptions = [
                            'libraries' => 'Library information and subscription details',
                            'users' => 'System users (admin, supervisors, librarians, members)',
                            'categories' => 'Book categories and classifications',
                            'authors' => 'Author information',
                            'publishers' => 'Publisher details',
                            'books' => 'Book catalog and inventory',
                            'book_authors' => 'Book-author relationships (many-to-many)',
                            'borrowings' => 'Book borrowing transactions',
                            'reservations' => 'Book reservations',
                            'fines' => 'Fine management and payments',
                            'activity_logs' => 'System activity tracking',
                            'settings' => 'System and library settings',
                            'notifications' => 'User notifications'
                        ];
                        
                        foreach ($tableStatus as $table => $status): ?>
                        <tr>
                            <td><code><?php echo $table; ?></code></td>
                            <td>
                                <?php if ($status === 'exists'): ?>
                                    <span class="status-success">✓ Exists</span>
                                <?php else: ?>
                                    <span class="status-error">✗ Missing</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $tableDescriptions[$table] ?? 'System table'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Progress Bar -->
                <?php 
                $existingTables = array_count_values($tableStatus)['exists'] ?? 0;
                $totalTables = count($tableStatus);
                $progressPercent = ($existingTables / $totalTables) * 100;
                ?>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $progressPercent; ?>%"></div>
                </div>
                <p><strong>Progress:</strong> <?php echo $existingTables; ?> of <?php echo $totalTables; ?> tables (<?php echo round($progressPercent); ?>%)</p>
            <?php endif; ?>
            
            <?php if (!$tablesExist): ?>
                <div class="schema-info">
                    <h4>📋 Database Schema Information</h4>
                    <ul>
                        <li><strong>13 Tables</strong> will be created</li>
                        <li><strong>Role-based system</strong> with 4 user types</li>
                        <li><strong>Multi-library support</strong> with subscription management</li>
                        <li><strong>Complete borrowing system</strong> with fines and reservations</li>
                        <li><strong>Activity logging</strong> and notifications</li>
                        <li><strong>Default admin user</strong> will be created (username: admin, password: admin123)</li>
                    </ul>
                </div>
                
                <form method="POST">
                    <button type="submit" name="setup_tables" class="btn btn-success">
                        <i class="fas fa-cogs"></i>
                        Setup All Tables
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Admin Actions -->
        <?php if ($dbExists): ?>
        <div class="status-card">
            <h3>
                <i class="fas fa-tools"></i>
                Admin Actions
            </h3>
            
            <div class="confirm-dialog">
                <h4>⚠️ Danger Zone</h4>
                <p>The following action will permanently delete all data in the database.</p>
                
                <form method="POST" onsubmit="return confirm('Are you sure you want to reset the database? This will delete ALL data!')">
                    <button type="submit" name="reset_database" class="btn btn-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        Reset Database
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Actions -->
        <div style="text-align: center; margin-top: 2rem;">
            <?php if ($tablesExist): ?>
                <a href="test-db.php" class="btn btn-success">
                    <i class="fas fa-check-circle"></i>
                    Test Database Connection
                </a>
                
                <a href="login.php" class="btn">
                    <i class="fas fa-sign-in-alt"></i>
                    Go to Login Page
                </a>
            <?php endif; ?>
            
            <a href="setup-env.php" class="btn btn-secondary">
                <i class="fas fa-cog"></i>
                Environment Setup
            </a>
            
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-home"></i>
                Back to Home
            </a>
        </div>
    </div>
</body>
</html>