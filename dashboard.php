<?php
define('LMS_ACCESS', true);

// Load configuration
require_once 'includes/EnvLoader.php';
EnvLoader::load();
include 'config/config.php';
require_once 'includes/SubscriptionManager.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

// Get user role and redirect to appropriate dashboard
$userRole = $_SESSION['user_role'] ?? 'member';

switch ($userRole) {
    case 'admin':
        header('Location: admin/dashboard.php');
        break;
    case 'supervisor':
        // Check subscription status for supervisors
        if (isset($_SESSION['library_id'])) {
            $subscriptionManager = new SubscriptionManager();
            if (!$subscriptionManager->hasActiveSubscription($_SESSION['library_id'])) {
                header('Location: subscription.php');
                exit;
            }
        }
        header('Location: supervisor/dashboard.php');
        break;
    case 'librarian':
        header('Location: librarian/dashboard.php');
        break;
    case 'member':
        header('Location: member/dashboard.php');
        break;
    default:
        // Fallback for unknown roles
        header('Location: login.php');
        break;
}
exit;
?>