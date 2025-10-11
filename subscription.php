<?php
define('LMS_ACCESS', true);

// Load configuration
require_once 'includes/EnvLoader.php';
EnvLoader::load();
include 'config/config.php';
require_once 'includes/SubscriptionManager.php';
require_once 'includes/PaystackService.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

// Redirect supervisors to their subscription page
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'supervisor') {
    header('Location: supervisor/subscription.php');
    exit;
}

$subscriptionManager = new SubscriptionManager();
$paystackService = new PaystackService();
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? '';

// Get user's library information
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT 
            u.library_id,
            u.email,
            u.first_name,
            u.last_name,
            l.library_name
        FROM users u
        LEFT JOIN libraries l ON u.library_id = l.id
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user || !$user['library_id']) {
        die('No library associated with your account.');
    }
    
    $libraryId = $user['library_id'];
    $subscriptionDetails = $subscriptionManager->getSubscriptionDetails($libraryId);
    
} catch (Exception $e) {
    die('Error loading subscription information: ' . $e->getMessage());
}

// Handle payment initialization
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subscribe'])) {
    $plan = sanitize_input($_POST['plan']);
    $planDetails = PaystackService::getPlanDetails($plan);
    
    if (!$planDetails) {
        $error = "Invalid subscription plan selected.";
    } else {
        try {
            $response = $paystackService->initializePayment(
                $user['email'],
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
                $error = "Failed to initialize payment: " . $response['message'];
            }
        } catch (Exception $e) {
            $error = "Payment initialization error: " . $e->getMessage();
        }
    }
}

$pageTitle = 'Subscription Management';
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
            padding: 2rem 0;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .subscription-header {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            text-align: center;
        }

        .current-plan {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }

        .plan-status {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-trial {
            background: #fff3cd;
            color: #856404;
        }

        .status-expired {
            background: #f8d7da;
            color: #721c24;
        }

        .plan-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .detail-item {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .detail-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #3498DB;
            display: block;
        }

        .detail-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .plans-section {
            margin-top: 2rem;
        }

        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .plan-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
            position: relative;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .plan-card.popular {
            border: 2px solid #3498DB;
            transform: scale(1.05);
        }

        .plan-card.popular::before {
            content: "Most Popular";
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: #3498DB;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .plan-name {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 1rem;
        }

        .plan-price {
            font-size: 2.5rem;
            font-weight: bold;
            color: #3498DB;
            margin-bottom: 0.5rem;
        }

        .plan-period {
            color: #6c757d;
            margin-bottom: 2rem;
        }

        .plan-features {
            list-style: none;
            padding: 0;
            margin: 2rem 0;
            text-align: left;
        }

        .plan-features li {
            padding: 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .plan-features i {
            color: #28a745;
            width: 16px;
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
            text-align: center;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
        }

        .btn-outline {
            background: transparent;
            color: #3498DB;
            border: 2px solid #3498DB;
        }

        .btn-outline:hover {
            background: #3498DB;
            color: white;
        }

        .btn-disabled {
            background: #6c757d;
            color: white;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
        }

        .alert-danger {
            background: #f8d7da;
            border: 1px solid #dc3545;
            color: #721c24;
        }

        .usage-bar {
            background: #e9ecef;
            height: 10px;
            border-radius: 5px;
            overflow: hidden;
            margin: 0.5rem 0;
        }

        .usage-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            transition: width 0.3s ease;
        }

        .usage-fill.warning {
            background: linear-gradient(90deg, #ffc107, #ffca2c);
        }

        .usage-fill.danger {
            background: linear-gradient(90deg, #dc3545, #e74c3c);
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container">
        <!-- Header -->
        <div class="subscription-header">
            <h1><i class="fas fa-crown"></i> Subscription Management</h1>
            <p>Manage your library's subscription and billing</p>
        </div>

        <!-- Error Display -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Current Plan Status -->
        <?php if ($subscriptionDetails): ?>
        <div class="current-plan">
            <h2>Current Subscription</h2>
            <div class="plan-status">
                <h3><?php echo htmlspecialchars($user['library_name']); ?></h3>
                <span class="status-badge status-<?php echo $subscriptionDetails['is_expired'] ? 'expired' : ($subscriptionDetails['is_trial'] ? 'trial' : 'active'); ?>">
                    <?php 
                    if ($subscriptionDetails['is_expired']) {
                        echo 'Expired';
                    } elseif ($subscriptionDetails['is_trial']) {
                        echo 'Trial Period';
                    } else {
                        echo 'Active';
                    }
                    ?>
                </span>
            </div>

            <div class="plan-details">
                <div class="detail-item">
                    <span class="detail-value"><?php echo ucfirst($subscriptionDetails['plan']); ?></span>
                    <div class="detail-label">Current Plan</div>
                </div>
                <div class="detail-item">
                    <span class="detail-value"><?php echo $subscriptionDetails['days_remaining']; ?></span>
                    <div class="detail-label">Days Remaining</div>
                </div>
                <div class="detail-item">
                    <span class="detail-value"><?php echo number_format($subscriptionDetails['current_book_count']); ?></span>
                    <div class="detail-label">Books Added</div>
                </div>
                <div class="detail-item">
                    <span class="detail-value">
                        <?php echo $subscriptionDetails['book_limit'] == -1 ? 'Unlimited' : number_format($subscriptionDetails['book_limit']); ?>
                    </span>
                    <div class="detail-label">Book Limit</div>
                </div>
            </div>

            <!-- Usage Bar -->
            <?php if ($subscriptionDetails['book_limit'] != -1): ?>
                <?php 
                $usagePercent = ($subscriptionDetails['current_book_count'] / $subscriptionDetails['book_limit']) * 100;
                $usageClass = $usagePercent >= 90 ? 'danger' : ($usagePercent >= 75 ? 'warning' : '');
                ?>
                <div style="margin-top: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <span>Book Usage</span>
                        <span><?php echo round($usagePercent, 1); ?>%</span>
                    </div>
                    <div class="usage-bar">
                        <div class="usage-fill <?php echo $usageClass; ?>" style="width: <?php echo min($usagePercent, 100); ?>%"></div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Warnings -->
            <?php if ($subscriptionDetails['is_trial'] && $subscriptionDetails['days_remaining'] <= 3): ?>
                <div class="alert alert-warning" style="margin-top: 1rem;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Trial Ending Soon!</strong> Your trial expires in <?php echo $subscriptionDetails['days_remaining']; ?> day(s). 
                    Subscribe now to continue accessing your library.
                </div>
            <?php endif; ?>

            <?php if ($subscriptionDetails['is_expired']): ?>
                <div class="alert alert-danger" style="margin-top: 1rem;">
                    <i class="fas fa-exclamation-circle"></i>
                    <strong>Subscription Expired!</strong> Your subscription expired on <?php echo date('M d, Y', strtotime($subscriptionDetails['expires'])); ?>. 
                    Please renew to continue accessing your library.
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Available Plans -->
        <div class="plans-section">
            <h2 style="text-align: center; margin-bottom: 2rem;">Available Plans</h2>
            
            <div class="plans-grid">
                <!-- Basic Plan -->
                <?php $basicPlan = PaystackService::getPlanDetails('basic'); ?>
                <div class="plan-card">
                    <div class="plan-name">Basic</div>
                    <div class="plan-price"><?php echo PaystackService::formatPrice($basicPlan['price']); ?></div>
                    <div class="plan-period">per year</div>
                    
                    <ul class="plan-features">
                        <?php foreach ($basicPlan['features'] as $feature): ?>
                            <li><i class="fas fa-check"></i> <?php echo $feature; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="plan" value="basic">
                        <button type="submit" name="subscribe" class="btn btn-outline" 
                                <?php echo ($subscriptionDetails['plan'] === 'basic' && $subscriptionDetails['is_active']) ? 'disabled' : ''; ?>>
                            <?php echo ($subscriptionDetails['plan'] === 'basic' && $subscriptionDetails['is_active']) ? 'Current Plan' : 'Choose Basic'; ?>
                        </button>
                    </form>
                </div>

                <!-- Standard Plan -->
                <?php $standardPlan = PaystackService::getPlanDetails('standard'); ?>
                <div class="plan-card popular">
                    <div class="plan-name">Standard</div>
                    <div class="plan-price"><?php echo PaystackService::formatPrice($standardPlan['price']); ?></div>
                    <div class="plan-period">per year</div>
                    
                    <ul class="plan-features">
                        <?php foreach ($standardPlan['features'] as $feature): ?>
                            <li><i class="fas fa-check"></i> <?php echo $feature; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="plan" value="standard">
                        <button type="submit" name="subscribe" class="btn btn-primary"
                                <?php echo ($subscriptionDetails['plan'] === 'standard' && $subscriptionDetails['is_active']) ? 'disabled' : ''; ?>>
                            <?php echo ($subscriptionDetails['plan'] === 'standard' && $subscriptionDetails['is_active']) ? 'Current Plan' : 'Choose Standard'; ?>
                        </button>
                    </form>
                </div>

                <!-- Premium Plan -->
                <?php $premiumPlan = PaystackService::getPlanDetails('premium'); ?>
                <div class="plan-card">
                    <div class="plan-name">Premium</div>
                    <div class="plan-price"><?php echo PaystackService::formatPrice($premiumPlan['price']); ?></div>
                    <div class="plan-period">per year</div>
                    
                    <ul class="plan-features">
                        <?php foreach ($premiumPlan['features'] as $feature): ?>
                            <li><i class="fas fa-check"></i> <?php echo $feature; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="plan" value="premium">
                        <button type="submit" name="subscribe" class="btn btn-outline"
                                <?php echo ($subscriptionDetails['plan'] === 'premium' && $subscriptionDetails['is_active']) ? 'disabled' : ''; ?>>
                            <?php echo ($subscriptionDetails['plan'] === 'premium' && $subscriptionDetails['is_active']) ? 'Current Plan' : 'Choose Premium'; ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Back to Dashboard -->
        <div style="text-align: center; margin-top: 3rem;">
            <a href="dashboard.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>