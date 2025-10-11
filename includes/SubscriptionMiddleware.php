<?php
/**
 * Subscription Middleware
 * Checks subscription status and enforces restrictions
 */

class SubscriptionMiddleware {
    
    /**
     * Check if user has access to the system
     * Returns array with access status and restrictions
     */
    public static function checkAccess($userId = null) {
        if (!$userId) {
            $userId = $_SESSION['user_id'] ?? null;
        }
        
        if (!$userId) {
            return [
                'has_access' => false,
                'reason' => 'not_logged_in',
                'message' => 'Please log in to continue',
                'redirect' => 'login.php'
            ];
        }
        
        try {
            $db = Database::getInstance()->getConnection();
            
            // Get user and library info
            $stmt = $db->prepare("
                SELECT 
                    u.role,
                    u.library_id,
                    l.library_name
                FROM users u
                LEFT JOIN libraries l ON u.library_id = l.id
                WHERE u.id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return [
                    'has_access' => false,
                    'reason' => 'user_not_found',
                    'message' => 'User not found',
                    'redirect' => 'login.php'
                ];
            }
            
            // Admin users always have access
            if ($user['role'] === 'admin') {
                return [
                    'has_access' => true,
                    'is_admin' => true,
                    'restrictions' => []
                ];
            }
            
            // Users without library (shouldn't happen for non-admin)
            if (!$user['library_id']) {
                return [
                    'has_access' => false,
                    'reason' => 'no_library',
                    'message' => 'No library assigned to your account',
                    'redirect' => 'index.php'
                ];
            }
            
            $subscriptionManager = new SubscriptionManager();
            $hasActiveSubscription = $subscriptionManager->hasActiveSubscription($user['library_id']);
            $subscriptionDetails = $subscriptionManager->getSubscriptionDetails($user['library_id']);
            
            if ($hasActiveSubscription) {
                return [
                    'has_access' => true,
                    'is_admin' => false,
                    'library_id' => $user['library_id'],
                    'subscription' => $subscriptionDetails,
                    'restrictions' => self::getAccessRestrictions($subscriptionDetails)
                ];
            } else {
                // Check if it's expired trial or subscription
                $isTrialExpired = $subscriptionManager->isTrialExpired($user['library_id']);
                
                return [
                    'has_access' => false,
                    'reason' => $isTrialExpired ? 'trial_expired' : 'subscription_expired',
                    'message' => $isTrialExpired ? 
                        'Your 14-day trial has expired. Please subscribe to continue.' :
                        'Your subscription has expired. Please renew to continue.',
                    'library_id' => $user['library_id'],
                    'subscription' => $subscriptionDetails,
                    'show_subscribe_button' => true,
                    'redirect' => 'subscription.php'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Subscription middleware error: " . $e->getMessage());
            return [
                'has_access' => false,
                'reason' => 'system_error',
                'message' => 'System error. Please try again later.',
                'redirect' => 'index.php'
            ];
        }
    }
    
    /**
     * Get access restrictions based on subscription plan
     */
    private static function getAccessRestrictions($subscriptionDetails) {
        $restrictions = [];
        
        if (!$subscriptionDetails) {
            return ['all' => true];
        }
        
        // Book limit restrictions
        if (!$subscriptionDetails['can_add_books']) {
            $restrictions['add_books'] = "You've reached your book limit of " . 
                number_format($subscriptionDetails['book_limit']) . " books. Upgrade to add more.";
        }
        
        // Trial period warnings
        if ($subscriptionDetails['is_trial'] && $subscriptionDetails['days_remaining'] <= 3) {
            $restrictions['trial_ending'] = "Your trial expires in " . 
                $subscriptionDetails['days_remaining'] . " day(s). Subscribe to continue access.";
        }
        
        // Plan-specific restrictions
        switch ($subscriptionDetails['plan']) {
            case 'trial':
            case 'basic':
                $restrictions['advanced_reports'] = "Advanced reporting is available in Standard and Premium plans.";
                $restrictions['api_access'] = "API access is available in Standard and Premium plans.";
                break;
            case 'standard':
                $restrictions['custom_integrations'] = "Custom integrations are available in Premium plan.";
                break;
        }
        
        return $restrictions;
    }
    
    /**
     * Enforce access control - redirect if no access
     */
    public static function requireAccess($redirectUrl = 'subscription.php') {
        $access = self::checkAccess();
        
        if (!$access['has_access']) {
            if (isset($access['show_subscribe_button']) && $access['show_subscribe_button']) {
                // Store the access check result in session for the subscription page
                $_SESSION['access_check'] = $access;
                header('Location: ' . $redirectUrl);
            } else {
                header('Location: ' . ($access['redirect'] ?? 'index.php'));
            }
            exit;
        }
        
        return $access;
    }
    
    /**
     * Check if specific feature is accessible
     */
    public static function canAccess($feature, $userId = null) {
        $access = self::checkAccess($userId);
        
        if (!$access['has_access']) {
            return false;
        }
        
        // Admin can access everything
        if ($access['is_admin'] ?? false) {
            return true;
        }
        
        // Check specific feature restrictions
        return !isset($access['restrictions'][$feature]);
    }
    
    /**
     * Generate restricted UI wrapper
     */
    public static function wrapRestrictedContent($content, $feature, $restriction_message = null) {
        if (self::canAccess($feature)) {
            return $content;
        }
        
        $access = self::checkAccess();
        $message = $restriction_message ?? ($access['restrictions'][$feature] ?? 'This feature is not available in your current plan.');
        
        return '
        <div class="restricted-content">
            <div class="restriction-overlay">
                <div class="restriction-message">
                    <i class="fas fa-lock"></i>
                    <p>' . htmlspecialchars($message) . '</p>
                    <a href="subscription.php" class="btn btn-primary">Upgrade Plan</a>
                </div>
            </div>
            <div class="restricted-inner" style="filter: blur(2px); pointer-events: none;">
                ' . $content . '
            </div>
        </div>';
    }
    
    /**
     * Add restriction styles to page
     */
    public static function getRestrictionStyles() {
        return '
        <style>
        .restricted-content {
            position: relative;
        }
        
        .restriction-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            border-radius: 8px;
        }
        
        .restriction-message {
            text-align: center;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border: 2px solid #e9ecef;
        }
        
        .restriction-message i {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }
        
        .restriction-message p {
            margin-bottom: 1.5rem;
            color: #495057;
            font-weight: 500;
        }
        
        .expired-interface {
            filter: grayscale(100%) brightness(0.8);
            pointer-events: none;
            position: relative;
        }
        
        .expired-interface::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(108, 117, 125, 0.1);
            z-index: 1;
        }
        
        .subscription-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .subscription-warning i {
            color: #856404;
            font-size: 1.2rem;
        }
        
        .subscription-warning .warning-text {
            flex: 1;
            color: #856404;
            font-weight: 500;
        }
        
        .subscription-warning .btn {
            white-space: nowrap;
        }
        </style>';
    }
}
?>