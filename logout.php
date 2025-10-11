<?php
define('LMS_ACCESS', true);

// Load configuration
require_once 'includes/EnvLoader.php';
EnvLoader::load();
include 'config/config.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Log the logout activity if user is logged in
if (is_logged_in() && isset($_SESSION['user_id'])) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO activity_logs 
            (user_id, library_id, action, description, ip_address, created_at)
            VALUES (?, ?, 'user_logout', 'User logged out', ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['user_id'], 
            $_SESSION['library_id'] ?? null, 
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    } catch (Exception $e) {
        // Don't fail logout if logging fails
    }
}

// Clear all session variables
$_SESSION = [];

// Destroy the session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Clear remember me cookie if it exists
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Destroy session
session_destroy();

// Redirect to login page with success message
header('Location: login.php?logout=success');
exit;
?>