<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';
require_once '../includes/SubscriptionManager.php';
require_once '../includes/PaystackService.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has supervisor role
if (!is_logged_in() || $_SESSION['user_role'] !== 'supervisor') {
    header('Location: ../login.php');
    exit;
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: subscription.php');
    exit;
}

$libraryId = $_SESSION['library_id'];
$plan = isset($_POST['plan']) ? sanitize_input($_POST['plan']) : '';
$paymentMethod = isset($_POST['payment_method']) ? sanitize_input($_POST['payment_method']) : 'card';

// Validate plan
$planDetails = PaystackService::getPlanDetails($plan);
if (!$planDetails) {
    header('Location: subscription.php?error=Invalid plan selected');
    exit;
}

// Get library information
try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("SELECT * FROM libraries WHERE id = ?");
    $stmt->execute([$libraryId]);
    $libraryInfo = $stmt->fetch();
    
    if (!$libraryInfo) {
        header('Location: subscription.php?error=Library information not found');
        exit;
    }
} catch (Exception $e) {
    header('Location: subscription.php?error=Error loading library information');
    exit;
}

// Initialize Paystack payment
try {
    $paystackService = new PaystackService();
    
    $response = $paystackService->initializePayment(
        $libraryInfo['email'],
        $planDetails['price'],
        $plan,
        $libraryId,
        APP_URL . '/payment-callback.php'
    );
    
    if ($response['status']) {
        // Redirect to Paystack payment page
        header('Location: ' . $response['data']['authorization_url']);
        exit;
    } else {
        header('Location: subscription.php?error=Failed to initialize payment: ' . $response['message']);
        exit;
    }
} catch (Exception $e) {
    header('Location: subscription.php?error=Payment initialization error: ' . $e->getMessage());
    exit;
}