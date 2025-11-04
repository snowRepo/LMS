<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';
require_once '../includes/SubscriptionManager.php';
require_once '../includes/PaystackService.php';

header('Content-Type: application/json');

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has admin role
if (!is_logged_in() || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get library ID and plan from POST data
$libraryId = isset($_POST['library_id']) ? (int)$_POST['library_id'] : 0;
$selectedPlan = isset($_POST['plan']) ? $_POST['plan'] : 'basic';

// Validate plan
$validPlans = ['basic', 'standard', 'premium'];
if (!in_array($selectedPlan, $validPlans)) {
    $selectedPlan = 'basic';
}

if ($libraryId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid library ID']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $subscriptionManager = new SubscriptionManager();
    
    // Check if library exists
    $stmt = $db->prepare("SELECT id, library_name FROM libraries WHERE id = ?");
    $stmt->execute([$libraryId]);
    $library = $stmt->fetch();
    
    if (!$library) {
        echo json_encode(['success' => false, 'message' => 'Library not found']);
        exit;
    }
    
    // Get plan details for pricing
    $planDetails = PaystackService::getPlanDetails($selectedPlan);
    $amount = $planDetails ? $planDetails['price'] : 0;
    
    // Check if library has an existing subscription record
    $stmt = $db->prepare("SELECT * FROM subscriptions WHERE library_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$libraryId]);
    $existingSubscription = $stmt->fetch();
    
    // Activate subscription (convert trial to active or create new active subscription)
    if ($existingSubscription) {
        // Update existing subscription
        $stmt = $db->prepare("
            UPDATE subscriptions 
            SET plan_type = ?,
                status = 'active', 
                start_date = ?, 
                end_date = DATE_ADD(?, INTERVAL 1 YEAR),
                updated_at = NOW()
            WHERE library_id = ?
        ");
        $startDate = date('Y-m-d');
        $result = $stmt->execute([$selectedPlan, $startDate, $startDate, $libraryId]);
    } else {
        // Create new subscription
        $stmt = $db->prepare("
            INSERT INTO subscriptions 
            (library_id, plan_type, status, start_date, end_date)
            VALUES (?, ?, 'active', ?, DATE_ADD(?, INTERVAL 1 YEAR))
        ");
        $startDate = date('Y-m-d');
        $result = $stmt->execute([$libraryId, $selectedPlan, $startDate, $startDate]);
    }
    
    if ($result) {
        // Get the subscription ID for payment history
        $stmt = $db->prepare("SELECT id FROM subscriptions WHERE library_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$libraryId]);
        $subscription = $stmt->fetch();
        
        if ($subscription) {
            $subscriptionId = $subscription['id'];
            
            // Generate admin transaction reference
            $transactionReference = 'ADMIN_' . strtoupper(uniqid()) . '_' . time();
            
            // Insert payment history record for admin-created subscription
            $stmt = $db->prepare("
                INSERT INTO payment_history 
                (subscription_id, amount, currency, payment_method, transaction_reference, status, created_by, payment_type)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'admin')
            ");
            $stmt->execute([
                $subscriptionId,
                $amount,
                'GHS',
                'admin_created',
                $transactionReference,
                'completed',
                $_SESSION['user_id'] // Set created_by to the admin's user ID
            ]);
        }
        
        // Log the activity
        $subscriptionManager->logSubscriptionActivity($libraryId, 'subscription_activated', 
            "Subscription activated for library: " . $library['library_name'] . " with " . ucfirst($selectedPlan) . " plan (Admin created)");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Subscription activated successfully for ' . $library['library_name']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to activate subscription']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error activating subscription: ' . $e->getMessage()]);
}
?>