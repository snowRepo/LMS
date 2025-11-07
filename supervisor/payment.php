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

$libraryId = $_SESSION['library_id'];
$plan = isset($_GET['plan']) ? sanitize_input($_GET['plan']) : (isset($_POST['plan']) ? sanitize_input($_POST['plan']) : '');

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

$pageTitle = 'Payment - ' . $planDetails['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - LMS</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Supervisor Navbar CSS -->
    <link rel="stylesheet" href="css/supervisor_navbar.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 0;
        }
        
        html, body {
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .payment-header {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            text-align: center;
        }

        .payment-header h1 {
            color: #212529;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .payment-header p {
            color: #6c757d;
            font-size: 1.1rem;
            margin: 0;
        }

        .payment-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 768px) {
            .payment-content {
                grid-template-columns: 1fr;
            }
        }

        .order-summary {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .order-summary h2 {
            color: #495057;
            margin-top: 0;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .library-info {
            margin-bottom: 2rem;
        }

        .library-info h3 {
            color: #495057;
            margin-bottom: 1rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .info-label {
            color: #6c757d;
        }

        .info-value {
            color: #495057;
            font-weight: 500;
        }

        .plan-details {
            margin-bottom: 2rem;
        }

        .plan-details h3 {
            color: #495057;
            margin-bottom: 1rem;
        }

        .plan-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .plan-name {
            color: #6c757d;
        }

        .plan-price {
            color: #495057;
            font-weight: 500;
        }

        .total-section {
            border-top: 1px solid #e9ecef;
            padding-top: 1rem;
            margin-top: 1rem;
        }

        .total-item {
            display: flex;
            justify-content: space-between;
            font-size: 1.2rem;
            font-weight: bold;
        }

        .payment-methods {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .payment-methods h2 {
            color: #495057;
            margin-top: 0;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .payment-options {
            display: grid;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .payment-option {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .payment-option:hover {
            border-color: #3498DB;
            background-color: #f8f9fa;
        }

        .payment-option.selected {
            border-color: #3498DB;
            background-color: #e3f2fd;
        }

        .payment-option-header {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .payment-option-icon {
            font-size: 1.5rem;
            color: #3498DB;
        }

        .payment-option-name {
            font-weight: 500;
            color: #495057;
        }

        .payment-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
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
            transition: all 0.3s ease;
            flex: 1;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .btn:active {
            transform: translateY(0);
        }

        .secure-notice {
            text-align: center;
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 1rem;
        }

        .secure-notice i {
            color: #28a745;
        }
        
        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .modal-header h2 {
            margin: 0;
            color: #495057;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6c757d;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .payment-form {
            display: grid;
            gap: 1rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .form-group label {
            font-weight: 500;
            color: #495057;
        }
        
        .form-control {
            padding: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        /* Style for select elements to add space for dropdown arrow */
        select.form-control {
            padding-right: 2.5rem;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23495057' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1rem;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3498DB;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.25);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/supervisor_navbar.php'; ?>
    
    <div class="container">
        <div class="payment-header">
            <h1><i class="fas fa-credit-card"></i> Payment Details</h1>
            <p>Complete your subscription to <?php echo htmlspecialchars($libraryInfo['library_name']); ?></p>
        </div>

        <div class="payment-content">
            <div class="order-summary">
                <h2><i class="fas fa-receipt"></i> Order Summary</h2>
                
                <div class="library-info">
                    <h3>Library Information</h3>
                    <div class="info-item">
                        <span class="info-label">Library Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($libraryInfo['library_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Library Code:</span>
                        <span class="info-value"><?php echo htmlspecialchars($libraryInfo['library_code']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($libraryInfo['email']); ?></span>
                    </div>
                </div>
                
                <div class="plan-details">
                    <h3>Subscription Plan</h3>
                    <div class="plan-item">
                        <span class="plan-name"><?php echo htmlspecialchars($planDetails['name']); ?></span>
                        <span class="plan-price"><?php echo PaystackService::formatPrice($planDetails['price']); ?></span>
                    </div>
                    <div class="plan-item">
                        <span class="plan-name">Duration:</span>
                        <span class="plan-price">1 Year</span>
                    </div>
                </div>
                
                <div class="total-section">
                    <div class="total-item">
                        <span>Total Amount:</span>
                        <span><?php echo PaystackService::formatPrice($planDetails['price']); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="payment-methods">
                <h2><i class="fas fa-wallet"></i> Payment Method</h2>
                
                <div class="payment-options">
                    <div class="payment-option selected" data-method="card">
                        <div class="payment-option-header">
                            <div class="payment-option-icon">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="payment-option-name">
                                Credit/Debit Card
                            </div>
                        </div>
                        <div style="margin-top: 0.5rem; color: #6c757d; font-size: 0.9rem;">
                            Pay with Visa, Mastercard, or other credit/debit cards
                        </div>
                    </div>
                    
                    <div class="payment-option" data-method="mobile">
                        <div class="payment-option-header">
                            <div class="payment-option-icon">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <div class="payment-option-name">
                                Mobile Money
                            </div>
                        </div>
                        <div style="margin-top: 0.5rem; color: #6c757d; font-size: 0.9rem;">
                            Pay with MTN Mobile Money, Telecel Cash, or AirtelTigo Cash
                        </div>
                    </div>
                </div>
                
                <form id="paymentForm" method="POST" action="process_payment.php">
                    <input type="hidden" name="plan" value="<?php echo htmlspecialchars($plan); ?>">
                    <input type="hidden" name="library_id" value="<?php echo htmlspecialchars($libraryId); ?>">
                    <input type="hidden" name="payment_method" id="payment_method" value="card">
                    
                    <div class="payment-actions">
                        <a href="subscription.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-lock"></i> Proceed to Payment
                        </button>
                    </div>
                </form>
                
                <div class="secure-notice">
                    <i class="fas fa-shield-alt"></i> Secure SSL Encryption
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Payment method selection
        document.querySelectorAll('.payment-option').forEach(option => {
            option.addEventListener('click', function() {
                // Remove selected class from all options
                document.querySelectorAll('.payment-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                
                // Add selected class to clicked option
                this.classList.add('selected');
                
                // Update hidden input value
                const method = this.getAttribute('data-method');
                document.getElementById('payment_method').value = method;
            });
        });
        
        // Handle form submission with modal
        document.querySelector('form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const paymentMethod = document.getElementById('payment_method').value;
            
            // Show the appropriate modal
            if (paymentMethod === 'card') {
                showCardPaymentModal();
            } else if (paymentMethod === 'mobile') {
                showMobilePaymentModal();
            }
        });
        
        // Show card payment modal
        function showCardPaymentModal() {
            // Create modal HTML
            const modalHtml = `
                <div class="modal-overlay" id="paymentModal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2><i class="fas fa-credit-card"></i> Credit/Debit Card Payment</h2>
                            <button class="close-modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <div class="payment-form">
                                <div class="form-group">
                                    <label for="card_number">Card Number</label>
                                    <input type="text" id="card_number" class="form-control" placeholder="1234 5678 9012 3456" maxlength="19">
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="expiry_date">Expiry Date</label>
                                        <input type="text" id="expiry_date" class="form-control" placeholder="MM/YY" maxlength="5">
                                    </div>
                                    <div class="form-group">
                                        <label for="cvv">CVV</label>
                                        <input type="text" id="cvv" class="form-control" placeholder="123" maxlength="4">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="cardholder_name">Cardholder Name</label>
                                    <input type="text" id="cardholder_name" class="form-control" placeholder="John Doe">
                                </div>
                                <div class="form-group">
                                    <button type="button" class="btn btn-primary" id="processCardPayment">
                                        <i class="fas fa-lock"></i> Pay Now
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Add modal to page
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Add event listeners
            document.querySelector('.close-modal').addEventListener('click', closePaymentModal);
            document.querySelector('#processCardPayment').addEventListener('click', processCardPayment);
            
            // Format card number input
            document.getElementById('card_number').addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                let formatted = '';
                for (let i = 0; i < value.length; i++) {
                    if (i > 0 && i % 4 === 0) formatted += ' ';
                    formatted += value[i];
                }
                e.target.value = formatted;
            });
            
            // Format expiry date input
            document.getElementById('expiry_date').addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 2) {
                    value = value.substring(0, 2) + '/' + value.substring(2, 4);
                }
                e.target.value = value;
            });
        }
        
        // Show mobile payment modal
        function showMobilePaymentModal() {
            // Create modal HTML
            const modalHtml = `
                <div class="modal-overlay" id="paymentModal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2><i class="fas fa-mobile-alt"></i> Mobile Money Payment</h2>
                            <button class="close-modal">&times;</button>
                        </div>
                        <div class="modal-body">
                            <div class="payment-form">
                                <div class="form-group">
                                    <label for="mobile_network">Mobile Network</label>
                                    <select id="mobile_network" class="form-control">
                                        <option value="">Select Network</option>
                                        <option value="mtn">MTN Mobile Money</option>
                                        <option value="vodafone">Telecel Cash</option>
                                        <option value="airtel">AirtelTigo Cash</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="mobile_number">Mobile Number</label>
                                    <input type="tel" id="mobile_number" class="form-control" placeholder="XXXXXXXXXX">
                                </div>
                                <div class="form-group">
                                    <button type="button" class="btn btn-primary" id="processMobilePayment">
                                        <i class="fas fa-lock"></i> Pay Now
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Add modal to page
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Add event listeners
            document.querySelector('.close-modal').addEventListener('click', closePaymentModal);
            document.querySelector('#processMobilePayment').addEventListener('click', processMobilePayment);
        }
        
        // Process card payment with Paystack
        function processCardPayment() {
            // Close the modal first
            closePaymentModal();
            
            // Show loading indicator
            const loadingHtml = `
                <div class="modal-overlay" id="loadingModal">
                    <div class="modal-content" style="text-align: center; padding: 2rem;">
                        <div style="font-size: 2rem; margin-bottom: 1rem;">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                        <h3>Processing Payment</h3>
                        <p>Please wait while we redirect you to the payment gateway...</p>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', loadingHtml);
            
            // Submit the form to process_payment.php which will redirect to Paystack
            setTimeout(function() {
                document.querySelector('form').submit();
            }, 1000);
        }
        
        // Process mobile payment with Paystack
        function processMobilePayment() {
            // Close the modal first
            closePaymentModal();
            
            // Show loading indicator
            const loadingHtml = `
                <div class="modal-overlay" id="loadingModal">
                    <div class="modal-content" style="text-align: center; padding: 2rem;">
                        <div style="font-size: 2rem; margin-bottom: 1rem;">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                        <h3>Processing Payment</h3>
                        <p>Please wait while we redirect you to the payment gateway...</p>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', loadingHtml);
            
            // Submit the form to process_payment.php which will redirect to Paystack
            setTimeout(function() {
                document.querySelector('form').submit();
            }, 1000);
        }
        
        // Close payment modal
        function closePaymentModal() {
            const modal = document.getElementById('paymentModal');
            if (modal) {
                modal.remove();
            }
        }
    </script>
</body>
</html>