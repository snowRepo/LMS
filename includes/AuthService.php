<?php
/**
 * Authentication Service Class for LMS
 * Handles user authentication and two-factor authentication
 */

// Prevent direct access
if (!defined('LMS_ACCESS')) {
    die('Direct access not allowed');
}

// Load required classes
require_once __DIR__ . '/SubscriptionManager.php';
require_once __DIR__ . '/EmailService.php';

class AuthService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Generate and send 2FA code
     */
    public function generateAndSend2FACode($userId, $email, $userName) {
        try {
            // Generate 6-digit code
            $code = sprintf('%06d', mt_rand(0, 999999));
            $expiryTime = date('Y-m-d H:i:s', time() + 300); // 5 minutes
            
            // Store code in database
            $stmt = $this->db->prepare("
                INSERT INTO two_factor_codes (user_id, code, expires_at, created_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                code = VALUES(code), 
                expires_at = VALUES(expires_at), 
                attempts = 0,
                created_at = NOW()
            ");
            $stmt->execute([$userId, password_hash($code, PASSWORD_DEFAULT), $expiryTime]);
            
            // Send email with code
            if (class_exists('EmailService')) {
                $emailService = new EmailService();
                $subject = "Login Verification Code - " . APP_NAME;
                
                $emailData = [
                    'user_name' => $userName,
                    'verification_code' => $code,
                    'expiry_minutes' => 5,
                    'app_name' => APP_NAME
                ];
                
                $body = $this->load2FATemplate($emailData);
                // Try to send the email and return false if it fails
                try {
                    $result = $emailService->sendEmail($email, $subject, $body);
                    return $result;
                } catch (Exception $e) {
                    return false;
                }
            }
            
            // If EmailService doesn't exist, we can't send emails
            return false;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Verify 2FA code
     */
    public function verify2FACode($userId, $code) {
        try {
            $stmt = $this->db->prepare("
                SELECT code, expires_at, attempts 
                FROM two_factor_codes 
                WHERE user_id = ? AND used = FALSE
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $storedCode = $stmt->fetch();
            
            if (!$storedCode) {
                return ['success' => false, 'message' => 'No verification code found. Please request a new one.'];
            }
            
            // Check if code has expired
            if (strtotime($storedCode['expires_at']) < time()) {
                $this->markCodeAsUsed($userId);
                return ['success' => false, 'message' => 'Verification code has expired. Please request a new one.'];
            }
            
            // Check attempts
            if ($storedCode['attempts'] >= 3) {
                $this->markCodeAsUsed($userId);
                return ['success' => false, 'message' => 'Too many failed attempts. Please request a new code.'];
            }
            
            // Verify code
            if (password_verify($code, $storedCode['code'])) {
                // Mark code as used
                $this->markCodeAsUsed($userId);
                return ['success' => true, 'message' => 'Verification successful.'];
            } else {
                // Increment attempts
                $this->incrementCodeAttempts($userId);
                $remainingAttempts = 3 - ($storedCode['attempts'] + 1);
                return [
                    'success' => false, 
                    'message' => "Invalid verification code. $remainingAttempts attempts remaining."
                ];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Verification failed. Please try again.'];
        }
    }
    
    /**
     * Mark 2FA code as used
     */
    private function markCodeAsUsed($userId) {
        $stmt = $this->db->prepare("
            UPDATE two_factor_codes 
            SET used = TRUE, used_at = NOW() 
            WHERE user_id = ? AND used = FALSE
        ");
        $stmt->execute([$userId]);
    }
    
    /**
     * Increment code attempts
     */
    private function incrementCodeAttempts($userId) {
        $stmt = $this->db->prepare("
            UPDATE two_factor_codes 
            SET attempts = attempts + 1 
            WHERE user_id = ? AND used = FALSE
        ");
        $stmt->execute([$userId]);
    }
    
    /**
     * Get user by username or email
     */
    public function getUserByCredentials($username) {
        $stmt = $this->db->prepare("
            SELECT u.id, u.user_id, u.username, u.email, u.password_hash, u.first_name, u.last_name, 
                   u.role, u.library_id, u.status, u.login_attempts, u.locked_until, u.email_verified,
                   u.profile_image, u.phone, u.address, u.date_of_birth,
                   l.library_name
            FROM users u
            LEFT JOIN libraries l ON u.library_id = l.id
            WHERE (u.username = ? OR u.email = ?) AND u.status = 'active'
        ");
        $stmt->execute([$username, $username]);
        return $stmt->fetch();
    }
    
    /**
     * Update login attempts and lock status
     */
    public function updateLoginAttempts($userId, $attempts, $lockUntil = null) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET login_attempts = ?, locked_until = ?
            WHERE id = ?
        ");
        $stmt->execute([$attempts, $lockUntil, $userId]);
    }
    
    /**
     * Reset login attempts on successful login
     */
    public function resetLoginAttempts($userId) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET login_attempts = 0, locked_until = NULL, last_login = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
    }
    
    /**
     * Log user activity
     */
    public function logActivity($userId, $libraryId, $action, $description, $ipAddress = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs 
                (user_id, library_id, action, description, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $libraryId, $action, $description, $ipAddress]);
        } catch (Exception $e) {
            // Continue even if logging fails
        }
    }
    
    /**
     * Get redirect URL based on user role
     */
    public function getRedirectUrl($role, $libraryId = null) {
        switch ($role) {
            case 'admin':
                return 'admin/dashboard.php';
            case 'supervisor':
                // Check subscription status for supervisors
                if ($libraryId) {
                    $subscriptionManager = new SubscriptionManager();
                    if (!$subscriptionManager->hasActiveSubscription($libraryId)) {
                        return 'subscription.php'; // Redirect to subscription page if expired
                    }
                }
                return 'supervisor/dashboard.php';
            case 'librarian':
                return 'librarian/dashboard.php';
            case 'member':
                return 'member/dashboard.php';
            default:
                return 'dashboard.php'; // Fallback
        }
    }
    
    /**
     * Load 2FA email template
     */
    private function load2FATemplate($data) {
        $template = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Login Verification</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #3498DB; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
                .code-box { background: white; border: 2px solid #3498DB; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0; }
                .code { font-size: 32px; font-weight: bold; color: #3498DB; letter-spacing: 5px; }
                .warning { color: #e74c3c; font-size: 14px; margin-top: 20px; }
                .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🔐 Login Verification</h1>
                </div>
                <div class="content">
                    <h2>Hello ' . htmlspecialchars($data['user_name']) . ',</h2>
                    <p>You are attempting to sign in to your ' . htmlspecialchars($data['app_name']) . ' account. To complete your login, please enter the verification code below:</p>
                    
                    <div class="code-box">
                        <div class="code">' . htmlspecialchars($data['verification_code']) . '</div>
                    </div>
                    
                    <p><strong>Important:</strong></p>
                    <ul>
                        <li>This code will expire in ' . $data['expiry_minutes'] . ' minutes</li>
                        <li>You have 3 attempts to enter the correct code</li>
                        <li>If you did not attempt to sign in, please ignore this email</li>
                    </ul>
                    
                    <div class="warning">
                        <strong>Security Notice:</strong> Never share this code with anyone. ' . htmlspecialchars($data['app_name']) . ' will never ask for your verification code via phone or email.
                    </div>
                </div>
                <div class="footer">
                    <p>This is an automated message from ' . htmlspecialchars($data['app_name']) . '. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $template;
    }
}
?>