<?php
/**
 * Subscription Manager Class
 * Handles subscription-related operations for libraries
 */

// Include required classes
require_once 'PaystackService.php';

class SubscriptionManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Start trial period for a library
     */
    public function startTrial($libraryId) {
        $startDate = date('Y-m-d');
        $trialExpires = date('Y-m-d', strtotime('+' . TRIAL_PERIOD_DAYS . ' days'));
        
        // Default to basic plan for new trials
        $selectedPlan = 'basic';
        
        $stmt = $this->db->prepare("
            INSERT INTO subscriptions 
            (library_id, plan_type, status, start_date, end_date)
            VALUES (?, ?, 'trial', ?, ?)
        ");
        
        return $stmt->execute([$libraryId, $selectedPlan, $startDate, $trialExpires]);
    }
    
    /**
     * Check if library is currently in trial period
     */
    public function isInTrial($libraryId) {
        $stmt = $this->db->prepare("
            SELECT s.plan_type, s.end_date, s.status
            FROM subscriptions s
            WHERE s.library_id = ?
            ORDER BY s.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$libraryId]);
        $subscription = $stmt->fetch();
        
        if (!$subscription) {
            return false;
        }
        
        return $subscription['status'] === 'trial' && 
               strtotime($subscription['end_date']) > time();
    }
    
    /**
     * Check if trial has expired
     */
    public function isTrialExpired($libraryId) {
        $stmt = $this->db->prepare("
            SELECT s.plan_type, s.end_date, s.status
            FROM subscriptions s
            WHERE s.library_id = ?
            ORDER BY s.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$libraryId]);
        $subscription = $stmt->fetch();
        
        if (!$subscription) {
            return true;
        }
        
        return $subscription['status'] === 'trial' && 
               strtotime($subscription['end_date']) <= time();
    }
    
    /**
     * Check if library has active subscription
     */
    public function hasActiveSubscription($libraryId) {
        $stmt = $this->db->prepare("
            SELECT s.plan_type, s.end_date, s.status
            FROM subscriptions s
            WHERE s.library_id = ?
            ORDER BY s.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$libraryId]);
        $subscription = $stmt->fetch();
        
        if (!$subscription) {
            return false;
        }
        
        // Trial is considered active if not expired
        if ($subscription['status'] === 'trial') {
            return $this->isInTrial($libraryId);
        }
        
        // Paid subscription
        return $subscription['status'] === 'active' && 
               strtotime($subscription['end_date']) > time();
    }
    
    /**
     * Update subscription after successful payment
     */
    public function updateSubscription($libraryId, $plan, $transactionRef, $months = 12) {
        $startDate = date('Y-m-d');
        $expiresDate = date('Y-m-d', strtotime('+' . $months . ' months'));
        
        // Update existing subscription or create new one
        $stmt = $this->db->prepare("
            INSERT INTO subscriptions 
            (library_id, plan_type, status, start_date, end_date)
            VALUES (?, ?, 'active', ?, ?)
            ON DUPLICATE KEY UPDATE
            plan_type = VALUES(plan_type),
            status = VALUES(status),
            start_date = VALUES(start_date),
            end_date = VALUES(end_date),
            updated_at = NOW()
        ");
        
        $result = $stmt->execute([$libraryId, $plan, $startDate, $expiresDate]);
        
        if ($result) {
            // Log the payment
            $this->logSubscriptionActivity($libraryId, 'subscription_updated', 
                "Subscription updated to $plan plan, expires: $expiresDate");
        }
        
        return $result;
    }
    
    /**
     * Get library subscription details
     */
    public function getSubscriptionDetails($libraryId) {
        $stmt = $this->db->prepare("
            SELECT 
                s.plan_type,
                s.status,
                s.end_date,
                s.start_date,
                l.book_limit,
                l.library_name
            FROM subscriptions s
            JOIN libraries l ON s.library_id = l.id
            WHERE s.library_id = ?
            ORDER BY s.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$libraryId]);
        $subscription = $stmt->fetch();
        
        if (!$subscription) {
            return null;
        }
        
        // Get actual book count from the database
        $bookCountStmt = $this->db->prepare("SELECT COUNT(*) as count FROM books WHERE library_id = ?");
        $bookCountStmt->execute([$libraryId]);
        $bookCount = $bookCountStmt->fetch()['count'];
        
        // Dynamically determine book limit based on current plan
        $planDetails = PaystackService::getPlanDetails($subscription['plan_type']);
        $dynamicBookLimit = $planDetails ? $planDetails['book_limit'] : $subscription['book_limit'];
        
        // Get the selected plan from the subscription record itself
        $details = [
            'library_name' => $subscription['library_name'],
            'plan' => $subscription['plan_type'],
            'status' => $subscription['status'],
            'expires' => $subscription['end_date'],
            'start_date' => $subscription['start_date'],
            'book_limit' => $dynamicBookLimit, // Use dynamic book limit based on plan
            'current_book_count' => $bookCount,
            'selected_plan' => $subscription['plan_type'], // Plan is now stored in subscriptions table
            'days_remaining' => $this->getDaysRemaining($subscription['end_date']),
            'is_trial' => $subscription['status'] === 'trial',
            'is_active' => $this->hasActiveSubscription($libraryId),
            'is_expired' => strtotime($subscription['end_date']) <= time(),
            'can_add_books' => $bookCount < $dynamicBookLimit || $dynamicBookLimit == -1
        ];
        
        return $details;
    }
    
    /**
     * Get days remaining until expiration
     */
    private function getDaysRemaining($expiresDate) {
        $now = time();
        $expires = strtotime($expiresDate);
        $diff = $expires - $now;
        
        if ($diff <= 0) {
            return 0;
        }
        
        return ceil($diff / (60 * 60 * 24));
    }
    
    /**
     * Log subscription activity
     */
    public function logSubscriptionActivity($libraryId, $action, $description) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs 
                (library_id, action, entity_type, description, created_at)
                VALUES (?, ?, 'subscription', ?, NOW())
            ");
            $stmt->execute([$libraryId, $action, $description]);
        } catch (Exception $e) {
            // Log error but don't fail the main operation
            error_log("Failed to log subscription activity: " . $e->getMessage());
        }
    }
    
    /**
     * Get subscription statistics for admin
     */
    public function getSubscriptionStats() {
        $stats = [];
        
        // Total libraries by plan
        $stmt = $this->db->query("
            SELECT 
                s.plan_type,
                s.status,
                COUNT(*) as count
            FROM subscriptions s
            GROUP BY s.plan_type, s.status
        ");
        $stats['by_plan'] = $stmt->fetchAll();
        
        // Expiring soon (next 7 days)
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM subscriptions s
            WHERE s.end_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)
            AND s.status IN ('active', 'trial')
        ");
        $stmt->execute();
        $stats['expiring_soon'] = $stmt->fetch()['count'];
        
        // Total revenue (this would require a payments table)
        $stats['total_revenue'] = 0; // Placeholder
        
        return $stats;
    }
}
?>