<?php
/**
 * Subscription Check Helper
 * Provides functions to check subscription status and prevent access to pages when subscription is expired
 */

// Prevent direct access
if (!defined('LMS_ACCESS')) {
    die('Direct access not allowed');
}

require_once 'SubscriptionManager.php';

/**
 * Check if the current user has an active subscription
 * This function should be included at the top of pages that require an active subscription
 */
function requireActiveSubscription() {
    // Start session if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || !isset($_SESSION['library_id'])) {
        header('Location: ../login.php');
        exit;
    }
    
    // Admin users don't need subscription checks
    if ($_SESSION['user_role'] === 'admin') {
        return true;
    }
    
    // Check subscription status for all roles including supervisors
    $subscriptionManager = new SubscriptionManager();
    if (!$subscriptionManager->hasActiveSubscription($_SESSION['library_id'])) {
        // Redirect to appropriate expired page using correct relative paths
        switch ($_SESSION['user_role']) {
            case 'member':
                header('Location: ../expired_member.php');
                break;
            case 'librarian':
                header('Location: ../expired_librarian.php');
                break;
            case 'supervisor':
                header('Location: ../expired_supervisor.php');
                break;
            default:
                header('Location: ../login.php');
        }
        exit;
    }
    
    return true;
}
?>