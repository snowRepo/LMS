<?php
/**
 * Email Service Class for LMS
 * Handles all email functionality using PHPMailer
 */

// Prevent direct access
if (!defined('LMS_ACCESS')) {
    die('Direct access not allowed');
}

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mailer;
    private $isConfigured = false;
    
    public function __construct() {
        // Check if PHPMailer is installed
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            throw new Exception('PHPMailer is not installed. Please install it via Composer.');
        }
        
        $this->mailer = new PHPMailer(true);
        $this->configure();
    }
    
    /**
     * Configure PHPMailer with settings from config
     */
    private function configure() {
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = SMTP_HOST;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = SMTP_USERNAME;
            $this->mailer->Password = SMTP_PASSWORD;
            $this->mailer->SMTPSecure = SMTP_ENCRYPTION;
            $this->mailer->Port = SMTP_PORT;
            
            // Default sender
            if (!empty(FROM_EMAIL)) {
                $this->mailer->setFrom(FROM_EMAIL, FROM_NAME);
                $this->mailer->addReplyTo(REPLY_TO_EMAIL ?: FROM_EMAIL, FROM_NAME);
                $this->isConfigured = true;
            }
            
            // Additional settings
            $this->mailer->isHTML(true);
            $this->mailer->CharSet = 'UTF-8';
            
        } catch (Exception $e) {
            error_log("Email configuration error: " . $e->getMessage());
            $this->isConfigured = false;
        }
    }
    
    /**
     * Check if email service is properly configured
     */
    public function isConfigured() {
        return $this->isConfigured && !empty(SMTP_USERNAME) && !empty(SMTP_PASSWORD);
    }
    
    /**
     * Send a basic email
     */
    public function sendEmail($to, $subject, $body, $altBody = '') {
        if (!$this->isConfigured()) {
            throw new Exception('Email service is not properly configured');
        }
        
        try {
            // Clear any previous recipients
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            
            // Recipients
            if (is_array($to)) {
                foreach ($to as $email => $name) {
                    if (is_numeric($email)) {
                        $this->mailer->addAddress($name);
                    } else {
                        $this->mailer->addAddress($email, $name);
                    }
                }
            } else {
                $this->mailer->addAddress($to);
            }
            
            // Content
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->AltBody = $altBody ?: strip_tags($body);
            
            $result = $this->mailer->send();
            
            if ($result) {
                $this->logEmail($to, $subject, 'sent');
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logEmail($to, $subject, 'failed', $e->getMessage());
            throw new Exception("Email could not be sent. Error: {$e->getMessage()}");
        }
    }
    
    /**
     * Send welcome email to new members
     */
    public function sendWelcomeEmail($userEmail, $userName, $temporaryPassword = null) {
        if (!SEND_WELCOME_EMAIL) {
            return false;
        }
        
        $subject = "Welcome to " . APP_NAME;
        
        $body = $this->loadTemplate('welcome', [
            'user_name' => $userName,
            'app_name' => APP_NAME,
            'app_url' => APP_URL,
            'temporary_password' => $temporaryPassword,
            'login_url' => APP_URL . '/login.php'
        ]);
        
        return $this->sendEmail($userEmail, $subject, $body);
    }
    
    /**
     * Send overdue book notification
     */
    public function sendOverdueNotification($userEmail, $userName, $overdueBooks) {
        if (!SEND_OVERDUE_NOTIFICATIONS) {
            return false;
        }
        
        $subject = "Overdue Books - " . APP_NAME;
        
        $body = $this->loadTemplate('overdue', [
            'user_name' => $userName,
            'app_name' => APP_NAME,
            'overdue_books' => $overdueBooks,
            'app_url' => APP_URL
        ]);
        
        return $this->sendEmail($userEmail, $subject, $body);
    }
    
    /**
     * Send book reservation notification
     */
    public function sendReservationNotification($userEmail, $userName, $bookTitle, $availableDate = null) {
        if (!SEND_RESERVATION_ALERTS) {
            return false;
        }
        
        $subject = "Book Reservation Update - " . APP_NAME;
        
        $body = $this->loadTemplate('reservation', [
            'user_name' => $userName,
            'book_title' => $bookTitle,
            'available_date' => $availableDate,
            'app_name' => APP_NAME,
            'app_url' => APP_URL
        ]);
        
        return $this->sendEmail($userEmail, $subject, $body);
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($userEmail, $userName, $resetToken) {
        $subject = "Password Reset - " . APP_NAME;
        
        $resetUrl = APP_URL . "/reset-password.php?token=" . $resetToken;
        
        $body = $this->loadTemplate('password-reset', [
            'user_name' => $userName,
            'reset_url' => $resetUrl,
            'app_name' => APP_NAME,
            'app_url' => APP_URL
        ]);
        
        return $this->sendEmail($userEmail, $subject, $body);
    }
    
    /**
     * Send email verification email
     */
    public function sendEmailVerification($userEmail, $userName, $verificationToken) {
        $subject = "Email Verification - " . APP_NAME;
        
        $verificationUrl = APP_URL . "/verify-email.php?token=" . $verificationToken;
        
        $body = $this->loadTemplate('email-verification', [
            'user_name' => $userName,
            'verification_url' => $verificationUrl,
            'app_name' => APP_NAME,
            'app_url' => APP_URL
        ]);
        
        return $this->sendEmail($userEmail, $subject, $body);
    }
    
    /**
     * Alias for sendEmailVerification (for backward compatibility)
     */
    public function sendVerificationEmail($userEmail, $userName, $verificationToken) {
        return $this->sendEmailVerification($userEmail, $userName, $verificationToken);
    }
    
    /**
     * Send subscription confirmation email
     */
    public function sendSubscriptionConfirmation($userEmail, $emailData) {
        $subject = "Subscription Confirmation - " . APP_NAME;
        
        $body = $this->loadTemplate('subscription-confirmation', $emailData);
        
        return $this->sendEmail($userEmail, $subject, $body);
    }
    
    /**
     * Send member setup email with temporary password
     */
    public function sendMemberSetupEmail($userEmail, $emailData) {
        $subject = "Member Account Setup - " . APP_NAME;
        
        // Extract data from the associative array
        $firstName = $emailData['first_name'] ?? '';
        $libraryName = $emailData['library_name'] ?? 'Your Library';
        $verificationLink = $emailData['verification_link'] ?? '';
        
        $body = $this->loadTemplate('member-setup', [
            'first_name' => $firstName,
            'library_name' => $libraryName,
            'verification_link' => $verificationLink,
            'app_name' => APP_NAME,
            'app_url' => APP_URL
        ]);
        
        return $this->sendEmail($userEmail, $subject, $body);
    }
    
    /**
     * Send member deleted email
     */
    public function sendMemberDeletedEmail($userEmail, $emailData) {
        $subject = "Member Account Removed - " . APP_NAME;
        
        // Extract data from the associative array
        $firstName = $emailData['first_name'] ?? '';
        $libraryName = $emailData['library_name'] ?? 'Your Library';
        
        $body = $this->loadTemplate('member-deleted', [
            'first_name' => $firstName,
            'library_name' => $libraryName,
            'app_name' => APP_NAME,
            'app_url' => APP_URL
        ]);
        
        return $this->sendEmail($userEmail, $subject, $body);
    }
    
    /**
     * Send librarian setup email
     */
    public function sendLibrarianSetupEmail($userEmail, $emailData) {
        $subject = "Librarian Account Setup - " . APP_NAME;
        
        // Extract data from the associative array
        $firstName = $emailData['first_name'] ?? '';
        $libraryName = $emailData['library_name'] ?? 'Your Library';
        $verificationLink = $emailData['verification_link'] ?? '';
        
        $body = $this->loadTemplate('librarian-setup', [
            'first_name' => $firstName,
            'library_name' => $libraryName,
            'verification_link' => $verificationLink,
            'app_name' => APP_NAME,
            'app_url' => APP_URL
        ]);
        
        return $this->sendEmail($userEmail, $subject, $body);
    }
    
    /**
     * Send librarian deleted email
     */
    public function sendLibrarianDeletedEmail($userEmail, $emailData) {
        $subject = "Librarian Account Removed - " . APP_NAME;
        
        // Extract data from the associative array
        $firstName = $emailData['first_name'] ?? '';
        $libraryName = $emailData['library_name'] ?? 'Your Library';
        
        $body = $this->loadTemplate('librarian-deleted', [
            'first_name' => $firstName,
            'library_name' => $libraryName,
            'app_name' => APP_NAME,
            'app_url' => APP_URL
        ]);
        
        return $this->sendEmail($userEmail, $subject, $body);
    }
    
    /**
     * Load email template
     */
    private function loadTemplate($templateName, $variables = []) {
        $templatePath = EMAIL_TEMPLATES_PATH . $templateName . '.php';
        
        if (file_exists($templatePath)) {
            // Extract variables for template
            extract($variables);
            
            // Capture template output
            ob_start();
            include $templatePath;
            $content = ob_get_clean();
            
            return $content;
        }
        
        // Fallback to basic template
        return $this->getBasicTemplate($templateName, $variables);
    }
    
    /**
     * Get basic email template when template file doesn't exist
     */
    private function getBasicTemplate($type, $variables) {
        $baseStyle = "
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #3498DB; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .footer { padding: 15px; text-align: center; color: #666; font-size: 12px; }
                .btn { display: inline-block; padding: 10px 20px; background: #3498DB; color: white; text-decoration: none; border-radius: 5px; }
                .details-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .details-table td { padding: 10px; border-bottom: 1px solid #ddd; }
                .details-table tr:last-child td { border-bottom: none; }
                .label { font-weight: bold; width: 30%; }
            </style>
        ";
        
        switch ($type) {
            case 'subscription-confirmation':
                return $baseStyle . "
                    <div class='container'>
                        <div class='header'>
                            <h1>Subscription Confirmation</h1>
                        </div>
                        <div class='content'>
                            <p>Hello " . ($variables['user_name'] ?? 'Valued Customer') . ",</p>
                            <p>Thank you for subscribing to " . APP_NAME . "! Your payment has been successfully processed.</p>
                            
                            <h3>Subscription Details</h3>
                            <table class='details-table'>
                                <tr>
                                    <td class='label'>Library:</td>
                                    <td>" . ($variables['library_name'] ?? 'N/A') . "</td>
                                </tr>
                                <tr>
                                    <td class='label'>Plan:</td>
                                    <td>" . ($variables['plan_name'] ?? 'N/A') . "</td>
                                </tr>
                                <tr>
                                    <td class='label'>Amount Paid:</td>
                                    <td>" . ($variables['amount'] ?? 'N/A') . "</td>
                                </tr>
                                <tr>
                                    <td class='label'>Payment Reference:</td>
                                    <td>" . ($variables['reference'] ?? 'N/A') . "</td>
                                </tr>
                                <tr>
                                    <td class='label'>Expires On:</td>
                                    <td>" . ($variables['expires_date'] ?? 'N/A') . "</td>
                                </tr>
                            </table>
                            
                            <p>Your subscription is now active and you can continue using all features of our library management system.</p>
                            <p><a href='" . ($variables['app_url'] ?? APP_URL) . "' class='btn'>Go to Dashboard</a></p>
                        </div>
                        <div class='footer'>
                            <p>&copy; " . date('Y') . " LMS. All rights reserved.</p>
                        </div>
                    </div>
                ";
                
            case 'welcome':
                return $baseStyle . "
                    <div class='container'>
                        <div class='header'>
                            <h1>Welcome to " . ($variables['app_name'] ?? APP_NAME) . "</h1>
                        </div>
                        <div class='content'>
                            <p>Hello " . ($variables['user_name'] ?? 'User') . ",</p>
                            <p>Welcome to our Library Management System! Your account has been created successfully.</p>
                            " . (isset($variables['temporary_password']) ? "<p><strong>Temporary Password:</strong> " . $variables['temporary_password'] . "</p>" : "") . "
                            <p><a href='" . ($variables['login_url'] ?? APP_URL . '/login.php') . "' class='btn'>Login Now</a></p>
                        </div>
                        <div class='footer'>
                            <p>&copy; " . date('Y') . " LMS. All rights reserved.</p>
                        </div>
                    </div>
                ";
                
            case 'overdue':
                return $baseStyle . "
                    <div class='container'>
                        <div class='header'>
                            <h1>Overdue Books Notice</h1>
                        </div>
                        <div class='content'>
                            <p>Hello " . ($variables['user_name'] ?? 'User') . ",</p>
                            <p>You have overdue books that need to be returned. Please return them as soon as possible to avoid late fees.</p>
                            <p><a href='" . ($variables['app_url'] ?? APP_URL) . "' class='btn'>View My Books</a></p>
                        </div>
                        <div class='footer'>
                            <p>&copy; " . date('Y') . " LMS. All rights reserved.</p>
                        </div>
                    </div>
                ";
                
            case 'password-reset':
                return $baseStyle . "
                    <div class='container'>
                        <div class='header'>
                            <h1>Password Reset Request</h1>
                        </div>
                        <div class='content'>
                            <p>Hello " . ($variables['user_name'] ?? 'User') . ",</p>
                            <p>We received a request to reset your password for your account at " . APP_NAME . ".</p>
                            <p>If you made this request, please click the button below to reset your password:</p>
                            <p><a href='" . ($variables['reset_url'] ?? '#') . "' class='btn'>Reset Password</a></p>
                            <p>If you didn't request a password reset, you can safely ignore this email. Your password will not be changed.</p>
                            <p><strong>Note:</strong> This password reset link will expire in 24 hours for security reasons.</p>
                        </div>
                        <div class='footer'>
                            <p>&copy; " . date('Y') . " LMS. All rights reserved.</p>
                        </div>
                    </div>
                ";
                
            case 'email-verification':
                return $baseStyle . "
                    <div class='container'>
                        <div class='header'>
                            <h1>Verify Your Email Address</h1>
                        </div>
                        <div class='content'>
                            <p>Hello " . ($variables['user_name'] ?? 'User') . ",</p>
                            <p>Thank you for registering with " . APP_NAME . "! To complete your registration, please verify your email address by clicking the button below:</p>
                            <p><a href='" . ($variables['verification_url'] ?? '#') . "' class='btn'>Verify Email Address</a></p>
                            <p>This verification link will expire in 24 hours. If you did not create an account, please ignore this email.</p>
                            <p>Once verified, you'll be automatically logged in and can start using our library system.</p>
                        </div>
                        <div class='footer'>
                            <p>&copy; " . date('Y') . " LMS. All rights reserved.</p>
                        </div>
                    </div>
                ";
                
            default:
                return $baseStyle . "
                    <div class='container'>
                        <div class='header'>
                            <h1>" . APP_NAME . "</h1>
                        </div>
                        <div class='content'>
                            <p>Hello " . ($variables['user_name'] ?? 'User') . ",</p>
                            <p>You have received this notification from " . APP_NAME . ".</p>
                        </div>
                        <div class='footer'>
                            <p>&copy; " . date('Y') . " LMS. All rights reserved.</p>
                        </div>
                    </div>
                ";
        }
    }
    
    /**
     * Test email configuration
     */
    public function testConnection() {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Email service is not configured. Please check your email settings.'
            ];
        }
        
        try {
            $this->mailer->smtpConnect();
            $this->mailer->smtpClose();
            
            return [
                'success' => true,
                'message' => 'SMTP connection successful!'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'SMTP connection failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send test email
     */
    public function sendTestEmail($toEmail) {
        $subject = "Test Email from " . APP_NAME;
        $body = "
            <h2>Test Email</h2>
            <p>This is a test email from your Library Management System.</p>
            <p>If you received this email, your email configuration is working correctly!</p>
            <p><strong>Sent at:</strong> " . date('Y-m-d H:i:s') . "</p>
        ";
        
        return $this->sendEmail($toEmail, $subject, $body);
    }
    
    /**
     * Log email activity
     */
    private function logEmail($to, $subject, $status, $error = null) {
        // Check if logging is enabled
        if (!defined('ENABLE_EMAIL_LOGGING') || !ENABLE_EMAIL_LOGGING) {
            return;
        }
        
        try {
            $logDir = __DIR__ . '/../logs';
            $logFile = $logDir . '/email.log';
            
            // Create logs directory if it doesn't exist
            if (!is_dir($logDir)) {
                if (!mkdir($logDir, 0755, true)) {
                    // If we can't create the directory, skip logging silently
                    return;
                }
            }
            
            // Check if log file is writable
            if (file_exists($logFile) && !is_writable($logFile)) {
                // If file exists but isn't writable, skip logging silently
                return;
            }
            
            // If directory exists but file doesn't, try to create it
            if (!file_exists($logFile)) {
                if (!touch($logFile)) {
                    // If we can't create the file, skip logging silently
                    return;
                }
                chmod($logFile, 0666);
            }
            
            $logData = [
                'timestamp' => date('Y-m-d H:i:s'),
                'to' => is_array($to) ? implode(', ', array_keys($to)) : $to,
                'subject' => $subject,
                'status' => $status,
                'error' => $error
            ];
            
            $logEntry = json_encode($logData) . "\n";
            
            // Use file_put_contents with error suppression as fallback
            @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
            
        } catch (Exception $e) {
            // Silently ignore logging errors - don't break email functionality
            // Optionally log to PHP error log instead
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log("EmailService logging error: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Get email statistics
     */
    public function getEmailStats() {
        $logFile = __DIR__ . '/../logs/email.log';
        
        if (!file_exists($logFile) || !is_readable($logFile)) {
            return [
                'total_sent' => 0,
                'total_failed' => 0,
                'last_sent' => null,
                'log_available' => false
            ];
        }
        
        try {
            $logs = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            if (!$logs) {
                return [
                    'total_sent' => 0,
                    'total_failed' => 0,
                    'last_sent' => null,
                    'log_available' => true
                ];
            }
            
            $totalSent = 0;
            $totalFailed = 0;
            $lastSent = null;
            
            foreach ($logs as $log) {
                $data = json_decode($log, true);
                if ($data && isset($data['status'])) {
                    if ($data['status'] === 'sent') {
                        $totalSent++;
                        $lastSent = $data['timestamp'];
                    } elseif ($data['status'] === 'failed') {
                        $totalFailed++;
                    }
                }
            }
            
            return [
                'total_sent' => $totalSent,
                'total_failed' => $totalFailed,
                'last_sent' => $lastSent,
                'log_available' => true
            ];
            
        } catch (Exception $e) {
            return [
                'total_sent' => 0,
                'total_failed' => 0,
                'last_sent' => null,
                'log_available' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
?>