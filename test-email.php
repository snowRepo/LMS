<?php
define('LMS_ACCESS', true);
include 'config/config.php';

// Load Composer autoloader for PHPMailer
if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
}

// Handle form submissions
$testResult = null;
$configTest = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['test_config'])) {
        // Test SMTP configuration
        try {
            require_once 'includes/EmailService.php';
            $emailService = new EmailService();
            $configTest = $emailService->testConnection();
        } catch (Exception $e) {
            $configTest = [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    if (isset($_POST['send_test']) && !empty($_POST['test_email'])) {
        // Send test email
        try {
            require_once 'includes/EmailService.php';
            $emailService = new EmailService();
            
            if ($emailService->isConfigured()) {
                $result = $emailService->sendTestEmail($_POST['test_email']);
                $testResult = [
                    'success' => $result,
                    'message' => $result ? 'Test email sent successfully!' : 'Failed to send test email.'
                ];
            } else {
                $testResult = [
                    'success' => false,
                    'message' => 'Email service is not configured. Please update your email settings in config.php'
                ];
            }
        } catch (Exception $e) {
            $testResult = [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}

// Get email statistics
$emailStats = null;
try {
    if (class_exists('EmailService')) {
        require_once 'includes/EmailService.php';
        $emailService = new EmailService();
        $emailStats = $emailService->getEmailStats();
    }
} catch (Exception $e) {
    // Ignore if EmailService can't be loaded
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Configuration Test - LMS</title>
    
    <!-- Navbar CSS -->
    <link rel="stylesheet" href="css/navbar.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }

        .container {
            max-width: 900px;
            margin: 2rem auto;
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .config-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .config-card h3 {
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

        .form-group {
            margin-bottom: 1rem;
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
        }

        .form-control:focus {
            outline: none;
            border-color: #3498DB;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.25);
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

        .btn-success {
            background: #28a745;
        }

        .btn-success:hover {
            background: #218838;
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

        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }

        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .config-instructions {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 1rem;
            margin: 1rem 0;
        }

        .config-instructions h4 {
            margin-bottom: 0.5rem;
            color: #1976d2;
        }

        .config-instructions code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <h1><i class="fas fa-envelope"></i> Email Configuration Test</h1>
        <p>Configure and test PHPMailer email functionality for the Library Management System.</p>

        <!-- PHPMailer Installation Check -->
        <div class="config-card">
            <h3>
                <i class="fas fa-download"></i>
                PHPMailer Installation
                <?php
                if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                    echo '<span class="status-success">✓ Installed</span>';
                } else {
                    echo '<span class="status-error">✗ Not Found</span>';
                }
                ?>
            </h3>
            
            <?php if (!class_exists('PHPMailer\PHPMailer\PHPMailer')): ?>
                <div class="alert alert-warning">
                    <strong>PHPMailer not found!</strong> Please install PHPMailer using Composer.
                </div>
                
                <div class="config-instructions">
                    <h4>Installation Instructions:</h4>
                    <ol>
                        <li>Open terminal in your LMS directory</li>
                        <li>Run: <code>composer require phpmailer/phpmailer</code></li>
                        <li>Add to your PHP file: <code>require_once 'vendor/autoload.php';</code></li>
                    </ol>
                </div>
            <?php else: ?>
                <p class="status-success">PHPMailer is installed and ready to use!</p>
            <?php endif; ?>
        </div>

        <!-- Email Configuration -->
        <div class="config-card">
            <h3>
                <i class="fas fa-cog"></i>
                Email Configuration
                <?php
                $isConfigured = !empty(SMTP_USERNAME) && !empty(SMTP_PASSWORD) && !empty(FROM_EMAIL);
                echo $isConfigured ? '<span class="status-success">✓ Configured</span>' : '<span class="status-warning">⚠ Needs Setup</span>';
                ?>
            </h3>
            
            <div class="info-grid">
                <div class="info-label">SMTP Host:</div>
                <div class="info-value"><?php echo SMTP_HOST; ?></div>
                
                <div class="info-label">SMTP Port:</div>
                <div class="info-value"><?php echo SMTP_PORT; ?></div>
                
                <div class="info-label">Encryption:</div>
                <div class="info-value"><?php echo strtoupper(SMTP_ENCRYPTION); ?></div>
                
                <div class="info-label">Username:</div>
                <div class="info-value"><?php echo !empty(SMTP_USERNAME) ? '***configured***' : '<span class="status-error">Not set</span>'; ?></div>
                
                <div class="info-label">Password:</div>
                <div class="info-value"><?php echo !empty(SMTP_PASSWORD) ? '***configured***' : '<span class="status-error">Not set</span>'; ?></div>
                
                <div class="info-label">From Email:</div>
                <div class="info-value"><?php echo !empty(FROM_EMAIL) ? FROM_EMAIL : '<span class="status-error">Not set</span>'; ?></div>
                
                <div class="info-label">From Name:</div>
                <div class="info-value"><?php echo FROM_NAME; ?></div>
            </div>
            
            <?php if (!$isConfigured): ?>
                <div class="alert alert-info" style="margin-top: 1rem;">
                    <strong>Configuration Required:</strong> Please update the email settings in <code>config/config.php</code>
                </div>
                
                <div class="config-instructions">
                    <h4>Gmail Setup Instructions:</h4>
                    <ol>
                        <li>Enable 2-factor authentication on your Gmail account</li>
                        <li>Generate an App Password: Google Account → Security → App passwords</li>
                        <li>Update config.php with your email and app password</li>
                        <li>Use: Host: smtp.gmail.com, Port: 587, Encryption: tls</li>
                    </ol>
                </div>
            <?php endif; ?>
        </div>

        <!-- SMTP Connection Test -->
        <div class="config-card">
            <h3>
                <i class="fas fa-plug"></i>
                SMTP Connection Test
            </h3>
            
            <?php if ($configTest): ?>
                <div class="alert alert-<?php echo $configTest['success'] ? 'success' : 'danger'; ?>">
                    <?php echo htmlspecialchars($configTest['message']); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <button type="submit" name="test_config" class="btn">
                    <i class="fas fa-plug"></i>
                    Test SMTP Connection
                </button>
            </form>
        </div>

        <!-- Send Test Email -->
        <div class="config-card">
            <h3>
                <i class="fas fa-paper-plane"></i>
                Send Test Email
            </h3>
            
            <?php if ($testResult): ?>
                <div class="alert alert-<?php echo $testResult['success'] ? 'success' : 'danger'; ?>">
                    <?php echo htmlspecialchars($testResult['message']); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="test_email">Test Email Address:</label>
                    <input type="email" id="test_email" name="test_email" class="form-control" 
                           placeholder="Enter email address to send test email" required>
                </div>
                
                <button type="submit" name="send_test" class="btn btn-success">
                    <i class="fas fa-paper-plane"></i>
                    Send Test Email
                </button>
            </form>
        </div>

        <!-- Email Statistics -->
        <?php if ($emailStats): ?>
        <div class="config-card">
            <h3>
                <i class="fas fa-chart-bar"></i>
                Email Statistics
            </h3>
            
            <div class="info-grid">
                <div class="info-label">Total Sent:</div>
                <div class="info-value"><?php echo $emailStats['total_sent']; ?></div>
                
                <div class="info-label">Total Failed:</div>
                <div class="info-value"><?php echo $emailStats['total_failed']; ?></div>
                
                <div class="info-label">Last Sent:</div>
                <div class="info-value"><?php echo $emailStats['last_sent'] ?: 'Never'; ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div style="text-align: center; margin-top: 2rem;">
            <a href="test-db.php" class="btn btn-secondary">
                <i class="fas fa-database"></i>
                Database Test
            </a>
            
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-home"></i>
                Back to Home
            </a>
        </div>
    </div>
</body>
</html>