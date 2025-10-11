<?php
/**
 * LMS Configuration File
 * Database and Application Settings
 */

// Prevent direct access
if (!defined('LMS_ACCESS')) {
    die('Direct access not allowed');
}

// Load Environment Variables
require_once __DIR__ . '/../includes/EnvLoader.php';

try {
    EnvLoader::load();
} catch (Exception $e) {
    die('Environment Configuration Error: ' . $e->getMessage());
}

// Database Configuration
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', env('DB_PORT', 3306)); // Add port configuration
define('DB_USERNAME', env('DB_USERNAME', 'root'));
define('DB_PASSWORD', env('DB_PASSWORD', ''));
define('DB_NAME', env('DB_NAME', 'lms_db'));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// Application Configuration
define('APP_NAME', env('APP_NAME', 'LMS - Library Management System'));
define('APP_VERSION', env('APP_VERSION', '1.0.0'));
define('APP_URL', env('APP_URL', 'http://localhost/LMS'));
define('APP_ENV', env('APP_ENV', 'production'));

// Session Configuration
define('SESSION_TIMEOUT', env('SESSION_TIMEOUT', 3600)); // 1 hour in seconds

// Subscription Plans Configuration
define('BASIC_PLAN_BOOK_LIMIT', env('BASIC_PLAN_BOOK_LIMIT', 500));
define('STANDARD_PLAN_BOOK_LIMIT', env('STANDARD_PLAN_BOOK_LIMIT', 2000));
define('PREMIUM_PLAN_BOOK_LIMIT', env('PREMIUM_PLAN_BOOK_LIMIT', -1)); // -1 means unlimited

// Paystack Configuration
define('PAYSTACK_PUBLIC_KEY', env('PAYSTACK_PUBLIC_KEY', ''));
define('PAYSTACK_SECRET_KEY', env('PAYSTACK_SECRET_KEY', ''));
define('PAYSTACK_WEBHOOK_SECRET', env('PAYSTACK_WEBHOOK_SECRET', ''));

// Subscription Configuration
define('TRIAL_PERIOD_DAYS', env('TRIAL_PERIOD_DAYS', 14));
define('BASIC_PLAN_PRICE', env('BASIC_PLAN_PRICE', 120000)); // in pesewas (GHS 1200)
define('STANDARD_PLAN_PRICE', env('STANDARD_PLAN_PRICE', 180000)); // in pesewas (GHS 1800)
define('PREMIUM_PLAN_PRICE', env('PREMIUM_PLAN_PRICE', 240000)); // in pesewas (GHS 2400)

// File Upload Configuration
define('MAX_FILE_SIZE', env('MAX_FILE_SIZE', 5 * 1024 * 1024)); // 5MB
define('UPLOAD_PATH', env('UPLOAD_PATH', '../uploads/'));

// Email Configuration (PHPMailer)
define('SMTP_HOST', env('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', env('SMTP_PORT', 587));
define('SMTP_USERNAME', env('SMTP_USERNAME', ''));
define('SMTP_PASSWORD', env('SMTP_PASSWORD', ''));
define('SMTP_ENCRYPTION', env('SMTP_ENCRYPTION', 'tls'));
define('FROM_EMAIL', env('FROM_EMAIL', ''));
define('FROM_NAME', env('FROM_NAME', 'LMS - Library Management System'));
define('REPLY_TO_EMAIL', env('REPLY_TO_EMAIL', ''));

// Email Templates
define('EMAIL_TEMPLATES_PATH', __DIR__ . '/' . env('EMAIL_TEMPLATES_PATH', '../email-templates/'));

// Notification Settings
define('SEND_WELCOME_EMAIL', env('SEND_WELCOME_EMAIL', true));
define('SEND_OVERDUE_NOTIFICATIONS', env('SEND_OVERDUE_NOTIFICATIONS', true));
define('SEND_RESERVATION_ALERTS', env('SEND_RESERVATION_ALERTS', true));
define('OVERDUE_REMINDER_DAYS', env('OVERDUE_REMINDER_DAYS', 3));

// Email Logging
define('ENABLE_EMAIL_LOGGING', env('ENABLE_EMAIL_LOGGING', true));

// Security Settings
define('CSRF_TOKEN_EXPIRE', env('CSRF_TOKEN_EXPIRE', 3600));
define('PASSWORD_MIN_LENGTH', env('PASSWORD_MIN_LENGTH', 8));
define('MAX_LOGIN_ATTEMPTS', env('MAX_LOGIN_ATTEMPTS', 5));
define('LOCKOUT_TIME', env('LOCKOUT_TIME', 1800)); // 30 minutes

// Error Reporting
define('DEBUG_MODE', env('DEBUG_MODE', false));

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
if (env('APP_TIMEZONE')) {
    date_default_timezone_set(env('APP_TIMEZONE'));
} else {
    date_default_timezone_set('America/New_York');
}

// Database Connection Class
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            // For XAMPP on macOS, we need to use the socket path
            $socketPath = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';
            
            if (file_exists($socketPath) && DB_HOST === 'localhost') {
                // Use socket connection for XAMPP
                $this->connection = new PDO(
                    "mysql:unix_socket=" . $socketPath . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
                    DB_USERNAME,
                    DB_PASSWORD,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
            } else {
                // Standard connection
                $this->connection = new PDO(
                    "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
                    DB_USERNAME,
                    DB_PASSWORD,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
            }
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die("Database connection failed: " . $e->getMessage());
            } else {
                die("Database connection failed. Please try again later.");
            }
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Test database connection
    public function testConnection() {
        try {
            $stmt = $this->connection->query("SELECT 1");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // Get database info
    public function getDatabaseInfo() {
        try {
            $stmt = $this->connection->query("SELECT VERSION() as version");
            $result = $stmt->fetch();
            return [
                'status' => 'connected',
                'mysql_version' => $result['version'],
                'database_name' => DB_NAME,
                'host' => DB_HOST
            ];
        } catch (PDOException $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}

// Helper Functions
function sanitize_input($data) {
    if ($data === null) {
        return '';
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function get_user_role() {
    return isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
}

function check_role($required_role) {
    $user_role = get_user_role();
    $roles = ['member' => 1, 'librarian' => 2, 'supervisor' => 3, 'admin' => 4];
    
    return isset($roles[$user_role]) && isset($roles[$required_role]) && 
           $roles[$user_role] >= $roles[$required_role];
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function flash_message($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

function get_flash_message($type) {
    if (isset($_SESSION['flash'][$type])) {
        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    return null;
}

// Auto-start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>