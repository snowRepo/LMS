<?php
define('LMS_ACCESS', true);

// Try to load config with environment
try {
    require_once 'includes/EnvLoader.php';
    EnvLoader::load();
    include 'config/config.php';
    $configLoaded = true;
    $configError = null;
} catch (Exception $e) {
    $configLoaded = false;
    $configError = $e->getMessage();
}

// Handle .env file creation
$envCreated = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_env'])) {
    $examplePath = '.env.example';
    $envPath = '.env';
    
    if (file_exists($examplePath)) {
        if (copy($examplePath, $envPath)) {
            $envCreated = true;
            // Reload the page to reflect changes
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// Check if .env exists
$envExists = file_exists('.env');
$envExampleExists = file_exists('.env.example');

// Environment validation
$requiredVars = [
    'DB_HOST', 'DB_USERNAME', 'DB_NAME',
    'APP_NAME', 'APP_URL'
];

$optionalVars = [
    'SMTP_USERNAME', 'SMTP_PASSWORD', 'FROM_EMAIL'
];

$validationResults = [];
$allValid = true;

if ($configLoaded) {
    foreach ($requiredVars as $var) {
        $value = env($var);
        $validationResults[$var] = [
            'required' => true,
            'exists' => !empty($value),
            'value' => $value,
            'status' => !empty($value) ? 'success' : 'error'
        ];
        if (empty($value)) $allValid = false;
    }
    
    foreach ($optionalVars as $var) {
        $value = env($var);
        $validationResults[$var] = [
            'required' => false,
            'exists' => !empty($value),
            'value' => $value,
            'status' => !empty($value) ? 'success' : 'warning'
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Environment Setup - LMS</title>
    
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

        .validation-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .validation-table th,
        .validation-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        .validation-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }

        .validation-table .status-icon {
            font-size: 1.1rem;
            margin-right: 0.5rem;
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

        .code-block {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            font-family: 'Courier New', monospace;
            margin: 1rem 0;
            overflow-x: auto;
        }

        .setup-steps {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 1rem;
            margin: 1rem 0;
        }

        .setup-steps h4 {
            margin-bottom: 0.5rem;
            color: #1976d2;
        }

        .setup-steps ol {
            margin-left: 1rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <h1><i class="fas fa-cog"></i> Environment Configuration Setup</h1>
        <p>Secure configuration management using environment variables.</p>

        <!-- Environment File Status -->
        <div class="status-card">
            <h3>
                <i class="fas fa-file-alt"></i>
                Environment File Status
                <?php if ($envExists): ?>
                    <span class="status-success">✓ .env file exists</span>
                <?php else: ?>
                    <span class="status-error">✗ .env file missing</span>
                <?php endif; ?>
            </h3>
            
            <?php if (!$envExists): ?>
                <div class="alert alert-warning">
                    <strong>Environment file not found!</strong> You need to create a .env file for secure configuration.
                </div>
                
                <?php if ($envExampleExists): ?>
                    <div class="setup-steps">
                        <h4>Quick Setup:</h4>
                        <p>Click the button below to create your .env file from the template:</p>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="create_env" class="btn btn-success">
                                <i class="fas fa-copy"></i>
                                Create .env from template
                            </button>
                        </form>
                    </div>
                    
                    <div class="setup-steps">
                        <h4>Manual Setup:</h4>
                        <ol>
                            <li>Copy .env.example to .env</li>
                            <li>Edit .env with your actual configuration values</li>
                            <li>Never commit .env to version control</li>
                        </ol>
                        <div class="code-block">cp .env.example .env</div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <strong>Template missing!</strong> The .env.example file is also missing. Please ensure all configuration files are present.
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-success">
                    <strong>Environment file found!</strong> Your .env file is in place and ready for configuration.
                </div>
            <?php endif; ?>
        </div>

        <!-- Configuration Status -->
        <div class="status-card">
            <h3>
                <i class="fas fa-check-circle"></i>
                Configuration Status
                <?php if ($configLoaded): ?>
                    <span class="status-success">✓ Loaded successfully</span>
                <?php else: ?>
                    <span class="status-error">✗ Failed to load</span>
                <?php endif; ?>
            </h3>
            
            <?php if (!$configLoaded): ?>
                <div class="alert alert-danger">
                    <strong>Configuration Error:</strong> <?php echo htmlspecialchars($configError); ?>
                </div>
            <?php else: ?>
                <div class="alert alert-success">
                    <strong>Configuration loaded successfully!</strong> Environment variables are available.
                </div>
            <?php endif; ?>
        </div>

        <?php if ($configLoaded): ?>
        <!-- Environment Variables Validation -->
        <div class="status-card">
            <h3>
                <i class="fas fa-list-check"></i>
                Environment Variables Validation
                <?php if ($allValid): ?>
                    <span class="status-success">✓ All required variables set</span>
                <?php else: ?>
                    <span class="status-warning">⚠ Some variables need attention</span>
                <?php endif; ?>
            </h3>
            
            <table class="validation-table">
                <thead>
                    <tr>
                        <th>Variable</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($validationResults as $var => $result): ?>
                    <tr>
                        <td><code><?php echo $var; ?></code></td>
                        <td>
                            <?php if ($result['required']): ?>
                                <span class="status-error">Required</span>
                            <?php else: ?>
                                <span class="status-warning">Optional</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($result['status'] === 'success'): ?>
                                <span class="status-success">
                                    <i class="fas fa-check-circle status-icon"></i>Set
                                </span>
                            <?php elseif ($result['status'] === 'error'): ?>
                                <span class="status-error">
                                    <i class="fas fa-times-circle status-icon"></i>Missing
                                </span>
                            <?php else: ?>
                                <span class="status-warning">
                                    <i class="fas fa-exclamation-triangle status-icon"></i>Empty
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($result['value'])): ?>
                                <?php if (in_array($var, ['DB_PASSWORD', 'SMTP_PASSWORD'])): ?>
                                    <code>***hidden***</code>
                                <?php else: ?>
                                    <code><?php echo htmlspecialchars($result['value']); ?></code>
                                <?php endif; ?>
                            <?php else: ?>
                                <em>Not set</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Security Information -->
        <div class="status-card">
            <h3>
                <i class="fas fa-shield-alt"></i>
                Security Information
            </h3>
            
            <div class="alert alert-info">
                <h4>🔒 Security Best Practices:</h4>
                <ul>
                    <li><strong>Never commit .env files</strong> to version control</li>
                    <li><strong>Use strong passwords</strong> for database and email accounts</li>
                    <li><strong>Enable 2FA</strong> on email accounts used for SMTP</li>
                    <li><strong>Use app passwords</strong> instead of account passwords for Gmail</li>
                    <li><strong>Set DEBUG_MODE=false</strong> in production</li>
                    <li><strong>Regularly rotate</strong> sensitive credentials</li>
                </ul>
            </div>
            
            <div class="setup-steps">
                <h4>🔧 Current Security Settings:</h4>
                <div class="info-grid" style="display: grid; grid-template-columns: 1fr 2fr; gap: 0.5rem; margin-top: 1rem;">
                    <div><strong>Debug Mode:</strong></div>
                    <div><?php echo DEBUG_MODE ? '<span class="status-warning">Enabled (Development)</span>' : '<span class="status-success">Disabled (Production)</span>'; ?></div>
                    
                    <div><strong>Environment:</strong></div>
                    <div><?php echo strtoupper(env('APP_ENV', 'production')); ?></div>
                    
                    <div><strong>Password Min Length:</strong></div>
                    <div><?php echo env('PASSWORD_MIN_LENGTH', 8); ?> characters</div>
                    
                    <div><strong>Max Login Attempts:</strong></div>
                    <div><?php echo env('MAX_LOGIN_ATTEMPTS', 5); ?> attempts</div>
                    
                    <div><strong>Lockout Time:</strong></div>
                    <div><?php echo env('LOCKOUT_TIME', 1800); ?> seconds</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div style="text-align: center; margin-top: 2rem;">
            <?php if ($configLoaded && $allValid): ?>
                <a href="test-db.php" class="btn btn-success">
                    <i class="fas fa-database"></i>
                    Test Database Connection
                </a>
                
                <a href="test-email.php" class="btn">
                    <i class="fas fa-envelope"></i>
                    Test Email Configuration
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