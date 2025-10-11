<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';
require_once '../includes/EmailService.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has supervisor role
if (!is_logged_in() || $_SESSION['user_role'] !== 'supervisor') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $libraryId = $_SESSION['library_id'];
    
    // Handle different actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'resend_setup_email':
                // Resend setup email for librarian or member
                $userId = trim($_POST['user_id']);
                
                if (empty($userId)) {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'User ID is required']);
                    exit;
                }
                
                // Get user details
                $stmt = $db->prepare("
                    SELECT u.*, 
                           CONCAT(u.first_name, ' ', u.last_name) as full_name,
                           l.name as library_name
                    FROM users u
                    LEFT JOIN libraries l ON u.library_id = l.id
                    WHERE u.user_id = ? 
                    AND u.library_id = ?
                    AND u.status = 'pending'
                ");
                $stmt->execute([$userId, $libraryId]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'User not found or not in pending status']);
                    exit;
                }
                
                // Generate new verification token
                $verificationToken = bin2hex(random_bytes(32));
                
                // Update user with new token
                $stmt = $db->prepare("UPDATE users SET email_verification_token = ?, updated_at = NOW() WHERE user_id = ?");
                $result = $stmt->execute([$verificationToken, $userId]);
                
                if ($result) {
                    // Send email
                    $emailService = new EmailService();
                    
                    if ($user['role'] === 'librarian') {
                        $verificationLink = APP_URL . "/librarian-setup.php?token=" . $verificationToken;
                        $emailData = [
                            'first_name' => $user['first_name'],
                            'library_name' => $user['library_name'] ?? 'Your Library',
                            'verification_link' => $verificationLink
                        ];
                        $emailSent = $emailService->sendLibrarianSetupEmail($user['email'], $emailData);
                    } else if ($user['role'] === 'member') {
                        $verificationLink = APP_URL . "/member-setup.php?token=" . $verificationToken;
                        $emailData = [
                            'first_name' => $user['first_name'],
                            'library_name' => $user['library_name'] ?? 'Your Library',
                            'verification_link' => $verificationLink
                        ];
                        $emailSent = $emailService->sendMemberSetupEmail($user['email'], $emailData);
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['error' => 'Invalid user role']);
                        exit;
                    }
                    
                    if ($emailSent) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => true,
                            'message' => 'Setup email resent successfully'
                        ]);
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['error' => 'Failed to send email']);
                    }
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Failed to update user']);
                }
                break;
                
            case 'reset_password':
                // Send password reset email for librarian or member
                $userId = trim($_POST['user_id']);
                
                if (empty($userId)) {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'User ID is required']);
                    exit;
                }
                
                // Get user details
                $stmt = $db->prepare("
                    SELECT u.*, 
                           CONCAT(u.first_name, ' ', u.last_name) as full_name,
                           l.name as library_name
                    FROM users u
                    LEFT JOIN libraries l ON u.library_id = l.id
                    WHERE u.user_id = ? 
                    AND u.library_id = ?
                    AND u.status != 'pending'
                ");
                $stmt->execute([$userId, $libraryId]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'User not found or account is pending']);
                    exit;
                }
                
                // Generate password reset token
                $resetToken = bin2hex(random_bytes(32));
                
                // Update user with reset token and expiry (24 hours)
                $stmt = $db->prepare("UPDATE users SET password_reset_token = ?, password_reset_expires = DATE_ADD(NOW(), INTERVAL 24 HOUR), updated_at = NOW() WHERE user_id = ?");
                $result = $stmt->execute([$resetToken, $userId]);
                
                if ($result) {
                    // Send password reset email
                    $emailService = new EmailService();
                    
                    $emailSent = $emailService->sendPasswordResetEmail($user['email'], $user['first_name'], $resetToken);
                    
                    if ($emailSent) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => true,
                            'message' => 'Password reset email sent successfully'
                        ]);
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['error' => 'Failed to send password reset email']);
                    }
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Failed to update user']);
                }
                break;
                
            default:
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Invalid action']);
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid request']);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>