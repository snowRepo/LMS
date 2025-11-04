<?php
define('LMS_ACCESS', true);

// Load configuration
require_once 'includes/EnvLoader.php';
EnvLoader::load();
include 'config/config.php';
require_once 'includes/SubscriptionManager.php';
require_once 'includes/PaystackService.php';
require_once 'includes/EmailService.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$success = false;
$message = '';
$subscriptionDetails = null;

try {
    // Get payment reference from URL
    $reference = $_GET['reference'] ?? '';
    
    if (empty($reference)) {
        throw new Exception('No payment reference provided');
    }
    
    // Verify payment with Paystack
    $paystackService = new PaystackService();
    $verification = $paystackService->verifyPayment($reference);
    
    if (!$verification['status']) {
        throw new Exception('Payment verification failed');
    }
    
    $paymentData = $verification['data'];
    
    // Check if payment was successful
    if ($paymentData['status'] !== 'success') {
        throw new Exception('Payment was not successful: ' . $paymentData['status']);
    }
    
    // Extract metadata
    $metadata = $paymentData['metadata'];
    $plan = $metadata['plan'] ?? '';
    $libraryId = $metadata['library_id'] ?? '';
    
    if (empty($plan) || empty($libraryId)) {
        throw new Exception('Invalid payment metadata');
    }
    
    // Verify the library belongs to current user
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT u.library_id, l.library_name
        FROM users u
        JOIN libraries l ON u.library_id = l.id
        WHERE u.id = ? AND u.library_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $libraryId]);
    $userLibrary = $stmt->fetch();
    
    if (!$userLibrary) {
        throw new Exception('Unauthorized: Library does not belong to current user');
    }
    
    // Check if payment has already been processed
    $stmt = $db->prepare("
        SELECT id FROM activity_logs 
        WHERE entity_type = 'payment' 
        AND description LIKE ? 
        LIMIT 1
    ");
    $stmt->execute(['%' . $reference . '%']);
    $existingPayment = $stmt->fetch();
    
    if ($existingPayment) {
        // Payment already processed, redirect to subscription page
        header('Location: subscription.php?status=already_processed');
        exit;
    }
    
    // Update subscription
    $subscriptionManager = new SubscriptionManager();
    $updateResult = $subscriptionManager->updateSubscription($libraryId, $plan, $reference);
    
    if (!$updateResult) {
        throw new Exception('Failed to update subscription in database');
    }
    
    // Get the subscription ID for payment history
    $stmt = $db->prepare("SELECT id FROM subscriptions WHERE library_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$libraryId]);
    $subscription = $stmt->fetch();
    
    if (!$subscription) {
        throw new Exception('Failed to retrieve subscription ID');
    }
    
    $subscriptionId = $subscription['id'];
    
    // Insert payment history record
    $planDetails = PaystackService::getPlanDetails($plan);
    $amount = $paymentData['amount'] / 100; // Convert from pesewas to cedis
    
    $stmt = $db->prepare("
        INSERT INTO payment_history 
        (subscription_id, amount, currency, payment_method, transaction_reference, status, created_by, payment_type)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'gateway')
    ");
    $stmt->execute([
        $subscriptionId,
        $amount,
        'GHS',
        $paymentData['channel'] ?? 'credit_card', // Use payment channel from Paystack or default to credit_card
        $reference, // Use the transaction reference from Paystack
        'completed',
        $_SESSION['user_id'] // Set created_by to the supervisor's user ID
    ]);
    
    // Update book limit based on plan
    $planDetails = PaystackService::getPlanDetails($plan);
    $bookLimit = $planDetails['book_limit'];
    
    $stmt = $db->prepare("
        UPDATE libraries 
        SET book_limit = ?
        WHERE id = ?
    ");
    $stmt->execute([$bookLimit, $libraryId]);
    
    // Log the payment
    $stmt = $db->prepare("
        INSERT INTO activity_logs 
        (library_id, user_id, action, entity_type, description, created_at)
        VALUES (?, ?, 'subscription_payment', 'payment', ?, NOW())
    ");
    $stmt->execute([
        $libraryId,
        $_SESSION['user_id'],
        "Payment successful for $plan plan. Reference: $reference. Amount: " . ($paymentData['amount'] / 100)
    ]);
    
    // Send confirmation email (optional)
    try {
        $emailService = new EmailService();
        $stmt = $db->prepare("SELECT email, first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        // Also get the library email
        $stmt = $db->prepare("SELECT email FROM libraries WHERE id = ?");
        $stmt->execute([$libraryId]);
        $library = $stmt->fetch();
        
        if ($user) {
            $emailData = [
                'user_name' => $user['first_name'] . ' ' . $user['last_name'],
                'library_name' => $userLibrary['library_name'],
                'plan_name' => ucfirst($plan),
                'amount' => PaystackService::formatPrice($paymentData['amount'] / 100),
                'reference' => $reference,
                'expires_date' => date('M d, Y', strtotime('+12 months')),
                'app_name' => APP_NAME,
                'app_url' => APP_URL
            ];
            
            // Send to supervisor email
            $emailService->sendSubscriptionConfirmation($user['email'], $emailData);
            
            // Also send to library email if it's different from supervisor email
            if ($library && $library['email'] !== $user['email']) {
                $emailService->sendSubscriptionConfirmation($library['email'], $emailData);
            }
        }
    } catch (Exception $e) {
        // Don't fail the payment process if email fails
        error_log("Failed to send subscription confirmation email: " . $e->getMessage());
    }
    
    $success = true;
    $message = 'Payment successful! Your subscription has been updated.';
    $subscriptionDetails = $subscriptionManager->getSubscriptionDetails($libraryId);
    
} catch (Exception $e) {
    $success = false;
    $message = $e->getMessage();
    error_log("Payment callback error: " . $e->getMessage());
}

$pageTitle = $success ? 'Payment Successful' : 'Payment Failed';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - LMS</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Navbar CSS -->
    <link rel="stylesheet" href="css/navbar.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .result-container {
            max-width: 600px;
            background: white;
            padding: 3rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
        }

        .result-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
        }

        .success-icon {
            color: #28a745;
        }

        .error-icon {
            color: #dc3545;
        }

        .result-title {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }

        .success-title {
            color: #28a745;
        }

        .error-title {
            color: #dc3545;
        }

        .result-message {
            font-size: 1.1rem;
            color: #6c757d;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .subscription-summary {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 2rem 0;
            text-align: left;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }

        .summary-item:last-child {
            border-bottom: none;
            font-weight: bold;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 0 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #495057;
        }

        .actions {
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <div class="result-container">
        <?php if ($success): ?>
            <!-- Success State -->
            <div class="result-icon success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1 class="result-title success-title">Payment Successful!</h1>
            <p class="result-message">
                <?php echo htmlspecialchars($message); ?>
            </p>

            <?php if ($subscriptionDetails): ?>
            <div class="subscription-summary">
                <h3 style="margin-bottom: 1rem; color: #2c3e50;">Subscription Details</h3>
                <div class="summary-item">
                    <span>Library:</span>
                    <span><?php echo htmlspecialchars($subscriptionDetails['library_name']); ?></span>
                </div>
                <div class="summary-item">
                    <span>Plan:</span>
                    <span><?php echo ucfirst($subscriptionDetails['plan']); ?></span>
                </div>
                <div class="summary-item">
                    <span>Status:</span>
                    <span style="color: #28a745;">Active</span>
                </div>
                <div class="summary-item">
                    <span>Book Limit:</span>
                    <span>
                        <?php echo $subscriptionDetails['book_limit'] == -1 ? 'Unlimited' : number_format($subscriptionDetails['book_limit']); ?>
                    </span>
                </div>
                <div class="summary-item">
                    <span>Expires:</span>
                    <span><?php echo date('M d, Y', strtotime($subscriptionDetails['expires'])); ?></span>
                </div>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Error State -->
            <div class="result-icon error-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <h1 class="result-title error-title">Payment Failed</h1>
            <p class="result-message">
                <?php echo htmlspecialchars($message); ?>
            </p>
        <?php endif; ?>

        <div class="actions">
            <?php if ($success): ?>
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="fas fa-tachometer-alt"></i>
                    Go to Dashboard
                </a>
                <a href="subscription.php" class="btn btn-secondary">
                    <i class="fas fa-crown"></i>
                    View Subscription
                </a>
            <?php else: ?>
                <a href="subscription.php" class="btn btn-primary">
                    <i class="fas fa-redo"></i>
                    Try Again
                </a>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i>
                    Back to Dashboard
                </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>