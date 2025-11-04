<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';
require_once '../includes/SubscriptionManager.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has admin role
if (!is_logged_in() || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Get library ID from URL parameter
$libraryId = isset($_GET['library_id']) ? (int)$_GET['library_id'] : 0;

if ($libraryId <= 0) {
    header('Location: subscriptions.php');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $subscriptionManager = new SubscriptionManager();
    
    // Get library details
    $stmt = $db->prepare("
        SELECT l.*, u.first_name, u.last_name, u.email as supervisor_email 
        FROM libraries l 
        LEFT JOIN users u ON l.supervisor_id = u.id 
        WHERE l.id = ?
    ");
    $stmt->execute([$libraryId]);
    $library = $stmt->fetch();
    
    if (!$library) {
        header('Location: subscriptions.php');
        exit;
    }
    
    // Check if library has any subscription (trial or active)
    $stmt = $db->prepare("SELECT * FROM subscriptions WHERE library_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$libraryId]);
    $existingSubscription = $stmt->fetch();
    
    // Libraries can always be activated regardless of current status
    $canBeActivated = true;
    
    // Check for session message from redirect
    $activationMessage = '';
    $activationSuccess = false;
    
    if (isset($_SESSION['activation_message'])) {
        $activationMessage = $_SESSION['activation_message'];
        $activationSuccess = isset($_SESSION['activation_success']) && $_SESSION['activation_success'];
        unset($_SESSION['activation_message']);
        unset($_SESSION['activation_success']);
    }
    
    // Handle form submission
    $selectedPlan = 'basic';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_subscription'])) {
        $selectedPlan = isset($_POST['plan']) ? $_POST['plan'] : 'basic';
        
        // Validate plan
        $validPlans = ['basic', 'standard', 'premium'];
        if (!in_array($selectedPlan, $validPlans)) {
            $selectedPlan = 'basic';
        }
        
        // Libraries can always be activated regardless of current status
        if ($canBeActivated) {
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
                
                $activationSuccess = true;
                $activationMessage = 'Subscription activated successfully for ' . $library['library_name'];
                
                // Set a session variable to show the message after redirect
                $_SESSION['activation_message'] = $activationMessage;
                $_SESSION['activation_success'] = true;
                
                // Redirect to avoid form resubmission and to show toast
                header('Location: activate_subscription.php?library_id=' . $libraryId);
                exit;
            } else {
                $activationMessage = 'Failed to activate subscription.';
            }
        }
    }
    
    // Get current subscription details if any
    $stmt = $db->prepare("
        SELECT *
        FROM subscriptions
        WHERE library_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$libraryId]);
    $librarySubscription = $stmt->fetch();
    
} catch (Exception $e) {
    die('Error loading subscription activation page: ' . $e->getMessage());
}

$pageTitle = 'Activate Subscription';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo htmlspecialchars($library['library_name']); ?> - LMS</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Toast CSS -->
    <link rel="stylesheet" href="css/toast.css">
    
    <style>
        :root {
            --primary-color: #0066cc; /* macOS deeper blue */
            --primary-dark: #0052a3;
            --secondary-color: #f8f9fa;
            --success-color: #1b5e20; /* Deep green for active status */
            --danger-color: #c62828; /* Deep red for error states */
            --warning-color: #F39C12;
            --info-color: #495057;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --gray-900: #212529;
            --border-radius: 12px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --box-shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s ease;
        }
        
        body {
            background: #f8f9fa;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #495057;
            padding-top: 60px; /* Space for navbar */
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            margin-bottom: 30px;
            text-align: center;
        }

        .page-header h1 {
            color: #2c3e50;
            font-size: 1.8rem;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #6c757d;
            font-size: 1.1rem;
        }

        .content-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 25px;
            margin-bottom: 2rem;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .card-header h2 {
            color: var(--gray-900);
            margin: 0;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(0, 102, 204, 0.2);
        }
        
        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 102, 204, 0.3);
        }
        
        .library-details {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }
        
        .library-logo-section {
            text-align: center;
        }
        
        .library-logo-large {
            width: 200px;
            height: 200px;
            object-fit: cover;
            border-radius: 12px;
            background-color: var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }
        
        .library-logo-large i {
            font-size: 4rem;
            color: var(--gray-500);
        }
        
        .library-info {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .info-group {
            margin-bottom: 1rem;
        }
        
        .info-group h3 {
            color: var(--gray-800);
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
            border-bottom: 1px solid var(--gray-300);
            padding-bottom: 0.5rem;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 0.75rem;
        }
        
        .info-label {
            font-weight: 600;
            width: 180px;
            color: var(--gray-700);
        }
        
        .info-value {
            flex: 1;
            color: var(--gray-900);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-active {
            background-color: var(--success-color);
            color: white;
        }
        
        .status-inactive {
            background-color: var(--gray-300);
            color: var(--gray-700);
        }
        
        .status-trial {
            background-color: var(--warning-color);
            color: white;
        }
        
        .status-expired {
            background-color: var(--danger-color);
            color: white;
        }
        
        .plan-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .plan-basic {
            background-color: #6c757d;
            color: white;
        }
        
        .plan-standard {
            background-color: #17a2b8;
            color: white;
        }
        
        .plan-premium {
            background-color: #6f42c1;
            color: white;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .plan-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .plan-option {
            border: 2px solid var(--gray-300);
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .plan-option:hover {
            border-color: var(--primary-color);
        }
        
        .plan-option.selected {
            border-color: var(--primary-color);
            background-color: rgba(0, 102, 204, 0.05);
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
        }
        
        .plan-option input[type="radio"] {
            display: none;
        }
        
        .plan-name {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .plan-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .plan-features {
            text-align: left;
            margin-top: 1rem;
        }
        
        .plan-features ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        
        .plan-features li {
            padding: 0.25rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .plan-features li i {
            color: var(--success-color);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(0, 102, 204, 0.2);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 102, 204, 0.3);
        }
        
        .btn-success {
            background: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background: #2e7d32;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(29, 94, 32, 0.3);
        }
        
        .btn-danger {
            background: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background: #b71c1c;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(198, 40, 40, 0.3);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background-color: rgba(27, 94, 32, 0.1);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }
        
        .alert-error {
            background-color: rgba(198, 40, 40, 0.1);
            border: 1px solid var(--danger-color);
            color: var(--danger-color);
        }
        
        .alert-info {
            background-color: rgba(23, 162, 184, 0.1);
            border: 1px solid #17a2b8;
            color: #17a2b8;
        }
        
        @media (max-width: 768px) {
            .library-details {
                grid-template-columns: 1fr;
            }
            
            .plan-options {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/admin_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-toggle-on"></i> <?php echo $pageTitle; ?></h1>
            <p>Activate subscription for <?php echo htmlspecialchars($library['library_name']); ?></p>
        </div>
        
        <div class="content-card">
            <div class="card-header">
                <h2>Subscription Activation</h2>
                <a href="subscriptions.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Subscriptions
                </a>
            </div>
            
            <div class="library-details">
                <div class="library-logo-section">
                    <div class="library-logo-large">
                        <?php if (!empty($library['logo_path']) && file_exists('../' . $library['logo_path'])): ?>
                            <img src="../<?php echo htmlspecialchars($library['logo_path']); ?>" alt="Library Logo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 12px;">
                        <?php else: ?>
                            <i class="fas fa-book"></i>
                        <?php endif; ?>
                    </div>
                    <h3><?php echo htmlspecialchars($library['library_name']); ?></h3>
                    <p><?php echo htmlspecialchars($library['library_code']); ?></p>
                </div>
                
                <div class="library-info">
                    <?php if (!empty($activationMessage)): ?>
                        <div class="alert <?php echo $activationSuccess ? 'alert-success' : 'alert-error'; ?>">
                            <?php echo htmlspecialchars($activationMessage); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($existingSubscription): ?>
                        <div class="info-group">
                            <h3><i class="fas fa-info-circle"></i> Current Subscription</h3>
                            <div class="info-row">
                                <div class="info-label">Package:</div>
                                <div class="info-value">
                                    <span class="plan-badge plan-<?php echo $existingSubscription['plan_type']; ?>">
                                        <?php echo ucfirst($existingSubscription['plan_type']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="info-row">
                                <div class="info-label">Status:</div>
                                <div class="info-value">
                                    <?php 
                                    $statusClass = 'status-' . $existingSubscription['status'];
                                    if ($existingSubscription['status'] === 'trial' && !empty($existingSubscription['end_date']) && strtotime($existingSubscription['end_date']) < time()) {
                                        $statusClass = 'status-expired';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php 
                                        if ($existingSubscription['status'] === 'trial' && !empty($existingSubscription['end_date']) && strtotime($existingSubscription['end_date']) < time()) {
                                            echo 'Expired';
                                        } else {
                                            echo ucfirst($existingSubscription['status']);
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="info-row">
                                <div class="info-label">Start Date:</div>
                                <div class="info-value"><?php echo date('M j, Y', strtotime($existingSubscription['start_date'])); ?></div>
                            </div>
                            
                            <div class="info-row">
                                <div class="info-label">End Date:</div>
                                <div class="info-value"><?php echo date('M j, Y', strtotime($existingSubscription['end_date'])); ?></div>
                            </div>
                            
                            <?php if (!empty($existingSubscription['end_date'])): ?>
                                <div class="info-row">
                                    <div class="info-label">Days Remaining:</div>
                                    <div class="info-value">
                                        <?php 
                                        $today = new DateTime();
                                        $endDate = new DateTime($existingSubscription['end_date']);
                                        
                                        if ($endDate < $today) {
                                            echo '<span class="status-badge status-expired">Expired</span>';
                                        } else {
                                            $remainingDays = $today->diff($endDate)->days;
                                            echo $remainingDays . ' day' . ($remainingDays != 1 ? 's' : '');
                                        }
                                        ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($existingSubscription['status'] === 'trial'): ?>
                            <div class="info-group">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> This library is currently in trial mode. You can activate a full subscription below.
                                </div>
                            </div>
                        <?php elseif ($existingSubscription['status'] === 'active'): ?>
                            <div class="info-group">
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i> This library already has an active subscription. You can change the plan below.
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="info-group">
                            <h3><i class="fas fa-star"></i> <?php echo $existingSubscription ? 'Change' : 'Select'; ?> Subscription Plan</h3>
                            <p>Choose a subscription plan for <?php echo htmlspecialchars($library['library_name']); ?>:</p>
                            
                            <div class="plan-options">
                                <div class="plan-option <?php echo $selectedPlan === 'basic' ? 'selected' : ''; ?>">
                                    <input type="radio" id="plan_basic" name="plan" value="basic" <?php echo $selectedPlan === 'basic' ? 'checked' : ''; ?>>
                                    <div class="plan-name">Basic</div>
                                    <div class="plan-price">₵<?php echo number_format(BASIC_PLAN_PRICE / 100, 2); ?></div>
                                    <div class="plan-features">
                                        <ul>
                                            <li><i class="fas fa-check-circle"></i> Up to <?php echo BASIC_PLAN_BOOK_LIMIT == -1 ? 'Unlimited' : BASIC_PLAN_BOOK_LIMIT; ?> books</li>
                                            <li><i class="fas fa-check-circle"></i> Basic reporting features</li>
                                            <li><i class="fas fa-check-circle"></i> Email support</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="plan-option <?php echo $selectedPlan === 'standard' ? 'selected' : ''; ?>">
                                    <input type="radio" id="plan_standard" name="plan" value="standard" <?php echo $selectedPlan === 'standard' ? 'checked' : ''; ?>>
                                    <div class="plan-name">Standard</div>
                                    <div class="plan-price">₵<?php echo number_format(STANDARD_PLAN_PRICE / 100, 2); ?></div>
                                    <div class="plan-features">
                                        <ul>
                                            <li><i class="fas fa-check-circle"></i> Up to <?php echo STANDARD_PLAN_BOOK_LIMIT == -1 ? 'Unlimited' : STANDARD_PLAN_BOOK_LIMIT; ?> books</li>
                                            <li><i class="fas fa-check-circle"></i> Advanced reporting features</li>
                                            <li><i class="fas fa-check-circle"></i> Priority email support</li>
                                            <li><i class="fas fa-check-circle"></i> Member attendance tracking</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="plan-option <?php echo $selectedPlan === 'premium' ? 'selected' : ''; ?>">
                                    <input type="radio" id="plan_premium" name="plan" value="premium" <?php echo $selectedPlan === 'premium' ? 'checked' : ''; ?>>
                                    <div class="plan-name">Premium</div>
                                    <div class="plan-price">₵<?php echo number_format(PREMIUM_PLAN_PRICE / 100, 2); ?></div>
                                    <div class="plan-features">
                                        <ul>
                                            <li><i class="fas fa-check-circle"></i> <?php echo PREMIUM_PLAN_BOOK_LIMIT == -1 ? 'Unlimited' : PREMIUM_PLAN_BOOK_LIMIT; ?> books</li>
                                            <li><i class="fas fa-check-circle"></i> All reporting features</li>
                                            <li><i class="fas fa-check-circle"></i> 24/7 priority support</li>
                                            <li><i class="fas fa-check-circle"></i> Advanced analytics</li>
                                            <li><i class="fas fa-check-circle"></i> Custom branding options</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="activate_subscription" class="btn btn-success" id="activateBtn">
                                <i class="fas fa-toggle-on"></i> <?php echo $existingSubscription ? 'Change Plan' : 'Activate Subscription'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Toast CSS -->
    <link rel="stylesheet" href="css/toast.css">
    <!-- Toast JavaScript -->
    <script src="js/toast.js"></script>
    
    <?php if (!empty($activationMessage)): ?>
        <script>
            window.addEventListener('DOMContentLoaded', function() {
                showToast('<?php echo addslashes($activationMessage); ?>', '<?php echo $activationSuccess ? 'success' : 'error'; ?>');
            });
        </script>
    <?php endif; ?>
    
    <script>
        // Plan selection functionality
        document.querySelectorAll('.plan-option').forEach(option => {
            option.addEventListener('click', function() {
                // Remove selected class from all options
                document.querySelectorAll('.plan-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                
                // Add selected class to clicked option
                this.classList.add('selected');
                
                // Check the radio button
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
            });
        });
        
        // Add form submission confirmation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault(); // Prevent default form submission
                    
                    const selectedPlan = document.querySelector('input[name="plan"]:checked');
                    if (selectedPlan) {
                        const planName = selectedPlan.value.charAt(0).toUpperCase() + selectedPlan.value.slice(1);
                        const libraryName = "<?php echo addslashes($library['library_name']); ?>";
                        
                        if (confirm("Are you sure you want to activate the " + planName + " plan for " + libraryName + "?")) {
                            // Disable the submit button to prevent double submission
                            const submitBtn = document.getElementById('activateBtn');
                            const originalText = submitBtn.innerHTML;
                            submitBtn.disabled = true;
                            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Activating...';
                            
                            // Send AJAX request
                            const formData = new FormData();
                            formData.append('library_id', <?php echo $libraryId; ?>);
                            formData.append('plan', selectedPlan.value);
                            
                            fetch('ajax_activate_subscription.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    showToast(data.message, 'success');
                                    // Reload the page after a short delay to show updated information
                                    setTimeout(() => {
                                        location.reload();
                                    }, 2000);
                                } else {
                                    showToast(data.message, 'error');
                                    // Re-enable the submit button
                                    submitBtn.disabled = false;
                                    submitBtn.innerHTML = originalText;
                                }
                            })
                            .catch(error => {
                                showToast('Error activating subscription: ' + error.message, 'error');
                                // Re-enable the submit button
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalText;
                            });
                        }
                    } else {
                        alert("Please select a subscription plan.");
                    }
                    
                    return false;
                });
            }
        });
    </script>
</body>
</html>