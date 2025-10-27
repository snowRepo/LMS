<?php
define('LMS_ACCESS', true);

// Load environment and config
require_once 'includes/EnvLoader.php';
EnvLoader::load();
include 'config/config.php';

echo "<h1>Database Connection Test</h1>";

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "<p style='color: green;'>✓ Database connection successful!</p>";
    
    $info = $db->getDatabaseInfo();
    echo "<h3>Database Information:</h3>";
    echo "<ul>";
    echo "<li><strong>Status:</strong> " . $info['status'] . "</li>";
    echo "<li><strong>MySQL Version:</strong> " . $info['mysql_version'] . "</li>";
    echo "<li><strong>Database Name:</strong> " . $info['database_name'] . "</li>";
    echo "<li><strong>Host:</strong> " . $info['host'] . "</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database connection failed: " . $e->getMessage() . "</p>";
}

echo "<br><a href='setup-database.php'>← Back to Database Setup</a>";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Test - LMS</title>
    
    <!-- Navbar CSS -->
    <link rel="stylesheet" href="css/navbar.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }

        .container {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .test-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .test-card h3 {
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

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .info-label {
            font-weight: bold;
            color: #6c757d;
        }

        .info-value {
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
        }

        .btn:hover {
            background: #2980B9;
            transform: translateY(-1px);
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

        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <h1><i class="fas fa-database"></i> Database Connection Test</h1>
        <p>Testing database connectivity and configuration for the Library Management System.</p>

        <!-- Configuration Test -->
        <div class="test-card">
            <h3>
                <i class="fas fa-cog"></i>
                Configuration Test
                <span class="status-success">✓</span>
            </h3>
            <div class="info-grid">
                <div class="info-label">App Name:</div>
                <div class="info-value"><?php echo APP_NAME; ?></div>
                
                <div class="info-label">App Version:</div>
                <div class="info-value"><?php echo APP_VERSION; ?></div>
                
                <div class="info-label">Database Host:</div>
                <div class="info-value"><?php echo DB_HOST; ?></div>
                
                <div class="info-label">Database Name:</div>
                <div class="info-value"><?php echo DB_NAME; ?></div>
                
                <div class="info-label">Debug Mode:</div>
                <div class="info-value"><?php echo DEBUG_MODE ? 'Enabled' : 'Disabled'; ?></div>
                
                <div class="info-label">Timezone:</div>
                <div class="info-value"><?php echo date_default_timezone_get(); ?></div>
            </div>
        </div>

        <!-- Database Connection Test -->
        <div class="test-card">
            <h3>
                <i class="fas fa-plug"></i>
                Database Connection Test
                <?php
                try {
                    $db = Database::getInstance();
                    $info = $db->getDatabaseInfo();
                    
                    if ($info['status'] === 'connected') {
                        echo '<span class="status-success">✓ Connected</span>';
                    } else {
                        echo '<span class="status-error">✗ Failed</span>';
                    }
                } catch (Exception $e) {
                    echo '<span class="status-error">✗ Failed</span>';
                    $info = ['status' => 'error', 'message' => $e->getMessage()];
                }
                ?>
            </h3>
            
            <?php if ($info['status'] === 'connected'): ?>
                <div class="info-grid">
                    <div class="info-label">MySQL Version:</div>
                    <div class="info-value"><?php echo $info['mysql_version']; ?></div>
                    
                    <div class="info-label">Database:</div>
                    <div class="info-value"><?php echo $info['database_name']; ?></div>
                    
                    <div class="info-label">Host:</div>
                    <div class="info-value"><?php echo $info['host']; ?></div>
                    
                    <div class="info-label">Connection Status:</div>
                    <div class="info-value status-success">Active</div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <strong>Connection Failed:</strong> <?php echo htmlspecialchars($info['message']); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Database Existence Check -->
        <div class="test-card">
            <h3>
                <i class="fas fa-database"></i>
                Database Existence Check
                <?php
                $db_exists = false;
                try {
                    $db = Database::getInstance();
                    $conn = $db->getConnection();
                    $stmt = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
                    $db_exists = $stmt->rowCount() > 0;
                    
                    if ($db_exists) {
                        echo '<span class="status-success">✓ Exists</span>';
                    } else {
                        echo '<span class="status-warning">! Not Found</span>';
                    }
                } catch (Exception $e) {
                    echo '<span class="status-error">✗ Error</span>';
                }
                ?>
            </h3>
            
            <?php if (!$db_exists): ?>
                <div class="alert alert-info">
                    <strong>Database Not Found:</strong> The database "<?php echo DB_NAME; ?>" doesn't exist yet. 
                    You'll need to create it before proceeding with the application setup.
                </div>
                
                <h4>Quick Setup Instructions:</h4>
                <ol>
                    <li>Open phpMyAdmin (http://localhost/phpmyadmin)</li>
                    <li>Click "New" to create a new database</li>
                    <li>Enter database name: <strong><?php echo DB_NAME; ?></strong></li>
                    <li>Select charset: <strong>utf8mb4_general_ci</strong></li>
                    <li>Click "Create"</li>
                </ol>
            <?php else: ?>
                <p class="status-success">Database "<?php echo DB_NAME; ?>" exists and is ready for use.</p>
            <?php endif; ?>
        </div>

        <!-- Subscription Plans Test -->
        <div class="test-card">
            <h3>
                <i class="fas fa-crown"></i>
                Subscription Configuration
                <span class="status-success">✓</span>
            </h3>
            <div class="info-grid">
                <div class="info-label">Basic Plan Limit:</div>
                <div class="info-value"><?php echo number_format(BASIC_PLAN_BOOK_LIMIT); ?> books</div>
                
                <div class="info-label">Standard Plan Limit:</div>
                <div class="info-value"><?php echo number_format(STANDARD_PLAN_BOOK_LIMIT); ?> books</div>
                
                <div class="info-label">Premium Plan Limit:</div>
                <div class="info-value"><?php echo PREMIUM_PLAN_BOOK_LIMIT === -1 ? 'Unlimited' : number_format(PREMIUM_PLAN_BOOK_LIMIT); ?></div>
            </div>
        </div>

        <!-- Actions -->
        <div style="text-align: center; margin-top: 2rem;">
            <?php if ($db_exists): ?>
                <a href="setup-tables.php" class="btn">
                    <i class="fas fa-table"></i>
                    Setup Database Tables
                </a>
            <?php else: ?>
                <a href="http://localhost/phpmyadmin" target="_blank" class="btn btn-secondary">
                    <i class="fas fa-external-link-alt"></i>
                    Open phpMyAdmin
                </a>
            <?php endif; ?>
            
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-home"></i>
                Back to Home
            </a>
        </div>
    </div>
</body>
</html>