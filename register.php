<?php
define('LMS_ACCESS', true);

// Load configuration
require_once 'includes/EnvLoader.php';
EnvLoader::load();
include 'config/config.php';
require_once 'includes/SubscriptionManager.php';
require_once 'includes/FileUploadHandler.php';
require_once 'includes/EmailService.php';

/**
 * Generate a unique library code based on library name
 * @param string $libraryName
 * @return string
 */
function generateLibraryCode($libraryName) {
    // Extract meaningful parts from library name
    $words = preg_split('/[\s\-_]+/', trim($libraryName));
    $code = '';
    
    // Take first 2-3 letters from each word (up to 4 words)
    $wordCount = 0;
    foreach ($words as $word) {
        if ($wordCount >= 4) break;
        
        $word = preg_replace('/[^a-zA-Z]/', '', $word); // Remove non-letters
        if (strlen($word) > 0) {
            if (strlen($word) >= 3) {
                $code .= substr($word, 0, 3);
            } else {
                $code .= $word;
            }
            $wordCount++;
        }
    }
    
    // If code is too short, pad with library name initials
    if (strlen($code) < 4) {
        $initials = '';
        foreach ($words as $word) {
            $word = preg_replace('/[^a-zA-Z]/', '', $word);
            if (strlen($word) > 0) {
                $initials .= strtoupper($word[0]);
            }
        }
        $code = $initials . $code;
    }
    
    // Ensure minimum length of 4 characters
    if (strlen($code) < 4) {
        $code = 'LIB' . $code;
    }
    
    // Limit to 6 characters maximum
    $code = substr(strtoupper($code), 0, 6);
    
    // Ensure uniqueness by checking database
    try {
        $db = Database::getInstance()->getConnection();
        $originalCode = $code;
        $counter = 1;
        
        while (true) {
            $stmt = $db->prepare("SELECT id FROM libraries WHERE library_code = ?");
            $stmt->execute([$code]);
            
            if (!$stmt->fetch()) {
                // Code is unique
                break;
            }
            
            // Code exists, try with number suffix
            $code = $originalCode . $counter;
            $counter++;
            
            // Prevent infinite loop
            if ($counter > 999) {
                $code = $originalCode . rand(1000, 9999);
                break;
            }
        }
    } catch (Exception $e) {
        // If database check fails, add random number
        $code .= rand(10, 99);
    }
    
    return $code;
}

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is coming from outside the registration flow
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$isFromRegistration = strpos($referer, 'register.php') !== false;
$isPostRequest = $_SERVER['REQUEST_METHOD'] === 'POST';

if (!$isPostRequest && !$isFromRegistration && !isset($_GET['step'])) {
    // User is starting fresh (not from POST, not from register.php, no step parameter)
    unset($_SESSION['registration_data']);
    unset($_SESSION['library_data']);
    unset($_SESSION['in_registration_flow']);
}

// Mark that user is now in registration flow
$_SESSION['in_registration_flow'] = true;

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
$step = 1; // Registration steps: 1 = User Info, 2 = Library Info, 3 = Plan Selection

// Handle clearing registration flow
if (isset($_POST['clear_registration_flow'])) {
    unset($_SESSION['registration_data']);
    unset($_SESSION['library_data']);
    unset($_SESSION['in_registration_flow']);
    exit('cleared'); // Return response for JavaScript
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step']) && $_POST['step'] == '3') {
        // Step 3: Complete registration with selected plan
        
        // User and library data from session
        $userData = $_SESSION['registration_data'] ?? null;
        $libraryData = $_SESSION['library_data'] ?? null;
        
        if (!$userData || !$libraryData) {
            $error = 'Registration session expired. Please start again.';
            $step = 1;
        } else {
            // Library data
            $libraryName = $libraryData['library_name'];
            $libraryCode = $libraryData['library_code'];
            $address = $libraryData['address'];
            $phone = $libraryData['phone'];
            $libraryEmail = $libraryData['library_email'];
            $website = $libraryData['website'];
            $logoPath = $libraryData['logo_path'] ?? null;
            
            // Plan data
            $selectedPlan = $_POST['plan'] ?? '';
            
            if (empty($selectedPlan) || !in_array($selectedPlan, ['basic', 'standard', 'premium'])) {
                $error = 'Please select a valid subscription plan.';
                $step = 3;
            } else {
                try {
                    $db = Database::getInstance()->getConnection();
                    $db->beginTransaction();
                    
                    // Check if library code exists
                    $stmt = $db->prepare("SELECT id FROM libraries WHERE library_code = ?");
                    $stmt->execute([$libraryCode]);
                    if ($stmt->fetch()) {
                        throw new Exception('Library code already exists. Please choose a different code.');
                    }
                    
                    // Create library (without subscription columns)
                    $stmt = $db->prepare("
                        INSERT INTO libraries 
                        (library_name, library_code, address, phone, email, website, logo_path, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$libraryName, $libraryCode, $address, $phone, $libraryEmail, $website, $logoPath]);
                    $libraryId = $db->lastInsertId();
                    
                    // Update library with the selected plan (we'll store this temporarily and use it when creating the subscription)
                    $stmt = $db->prepare("UPDATE libraries SET subscription_plan = ? WHERE id = ?");
                    $stmt->execute([$selectedPlan, $libraryId]);
                    
                    // Create user
                    $userId = 'USR' . time() . rand(100, 999);
                    $passwordHash = password_hash($userData['password'], PASSWORD_DEFAULT);
                    
                    // Generate email verification token
                    $verificationToken = bin2hex(random_bytes(32));
                    
                    $stmt = $db->prepare("
                        INSERT INTO users 
                        (user_id, username, email, password_hash, first_name, last_name, 
                         phone, date_of_birth, role, library_id, email_verified, email_verification_token, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'supervisor', ?, FALSE, ?, NOW())
                    ");
                    $stmt->execute([
                        $userId, $userData['username'], $userData['email'], $passwordHash,
                        $userData['first_name'], $userData['last_name'], $userData['phone'], 
                        !empty($userData['date_of_birth']) ? $userData['date_of_birth'] : null,
                        $libraryId, $verificationToken
                    ]);
                    $userDbId = $db->lastInsertId();
                    
                    // Update library supervisor_id
                    $stmt = $db->prepare("UPDATE libraries SET supervisor_id = ? WHERE id = ?");
                    $stmt->execute([$userDbId, $libraryId]);
                    
                    // Start trial period in the new subscriptions table
                    $subscriptionManager = new SubscriptionManager();
                    $subscriptionManager->startTrial($libraryId);
                    
                    $db->commit();
                    
                    // Clear registration data
                    unset($_SESSION['registration_data']);
                    unset($_SESSION['library_data']);
                    unset($_SESSION['in_registration_flow']);
                    
                    // Send email verification instead of welcome email
                    try {
                        $emailService = new EmailService();
                        
                        // Check if email service is configured
                        if (!$emailService->isConfigured()) {
                            $success = 'Registration successful! However, the email service is not configured. Please contact support to verify your account manually. Your 14-day trial is ready to start.';
                        } else {
                            $emailSent = $emailService->sendVerificationEmail(
                                $userData['email'], 
                                $userData['first_name'] . ' ' . $userData['last_name'], 
                                $verificationToken
                            );
                            
                            if ($emailSent) {
                                $success = 'Registration successful! Please check your email and click the verification link to activate your account. Your 14-day free trial will start after email verification. After the trial period, you can subscribe to your selected ' . ucfirst($selectedPlan) . ' plan.';
                            } else {
                                $success = 'Registration successful! However, we could not send the verification email. Please contact support to verify your account manually. Your 14-day trial is ready to start.';
                            }
                        }
                    } catch (Exception $e) {
                        $success = 'Registration successful! However, there was an issue sending the verification email. Please contact support to verify your account.';
                        if (DEBUG_MODE) {
                            $success .= ' Email error: ' . $e->getMessage();
                        }
                        // Log the error for debugging
                        error_log('Email sending error: ' . $e->getMessage());
                    }
                    
                    // Store email for potential resend
                    $_SESSION['pending_verification_email'] = $userData['email'];
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = $e->getMessage();
                    $step = 3;
                }
            }
        }
    } elseif (isset($_POST['step']) && $_POST['step'] == '2') {
        // Step 2: Library Setup
        $libraryName = sanitize_input($_POST['library_name'] ?? '');
        $libraryCode = isset($_POST['library_code']) && !empty($_POST['library_code']) 
                      ? strtoupper(sanitize_input($_POST['library_code'])) 
                      : generateLibraryCode($libraryName);
        $address = sanitize_input($_POST['address'] ?? '');
        $phone = sanitize_input($_POST['phone'] ?? '');
        $libraryEmail = sanitize_input($_POST['library_email'] ?? '');
        $website = sanitize_input($_POST['website'] ?? '');
        
        // Handle logo upload
        $logoPath = null;
        if (isset($_FILES['library_logo']) && $_FILES['library_logo']['error'] === UPLOAD_ERR_OK) {
            $fileUploader = new FileUploadHandler();
            // For new registrations, no old file to replace, but this prepares for future updates
            $uploadResult = $fileUploader->uploadLibraryLogo($_FILES['library_logo']);
            
            if ($uploadResult['success']) {
                $logoPath = $uploadResult['path'];
            } else {
                $error = 'Logo upload failed: ' . $uploadResult['error'];
                $step = 2;
            }
        }
        
        // Validate required fields (name, address, phone, email)
        if (empty($libraryName) || empty($address) || empty($phone) || empty($libraryEmail)) {
            $error = 'Please fill in all required fields (Library Name, Address, Phone, and Email).';
            $step = 2;
        } elseif (!filter_var($libraryEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid library email address.';
            $step = 2;
        } else {
            try {
                $db = Database::getInstance()->getConnection();
                
                // Check if library code exists
                $stmt = $db->prepare("SELECT id FROM libraries WHERE library_code = ?");
                $stmt->execute([$libraryCode]);
                if ($stmt->fetch()) {
                    $error = 'Library code already exists. Please choose a different code.';
                    $step = 2;
                } else {
                    // Store library data in session and proceed to step 3
                    $_SESSION['library_data'] = [
                        'library_name' => $libraryName,
                        'library_code' => $libraryCode,
                        'address' => $address,
                        'phone' => $phone,
                        'library_email' => $libraryEmail,
                        'website' => $website,
                        'logo_path' => $logoPath
                    ];
                    $step = 3;
                }
            } catch (Exception $e) {
                $error = 'Database error. Please try again.';
                $step = 2;
            }
        }
    } elseif (!isset($_POST['step']) || $_POST['step'] == '1') {
        // Step 1: Validate user information (only if this is actually a step 1 submission)
        $firstName = sanitize_input($_POST['first_name'] ?? '');
        $lastName = sanitize_input($_POST['last_name'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $phone = sanitize_input($_POST['phone'] ?? '');
        $dateOfBirth = sanitize_input($_POST['date_of_birth'] ?? '');
        $username = sanitize_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Only validate if we have form data (not empty POST or not from error redirect)
        if (!empty($firstName) || !empty($lastName) || !empty($email) || !empty($username) || !empty($password)) {
            if (empty($firstName) || empty($lastName) || empty($email) || empty($username) || empty($password)) {
                $error = 'Please fill in all required fields.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
                $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
            } elseif ($password !== $confirmPassword) {
                $error = 'Passwords do not match.';
            } else {
                try {
                    $db = Database::getInstance()->getConnection();
                    
                    // Check if username or email exists
                    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                    $stmt->execute([$username, $email]);
                    if ($stmt->fetch()) {
                        $error = 'Username or email already exists.';
                    } else {
                        // Store user data in session and proceed to step 2
                        $_SESSION['registration_data'] = [
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'email' => $email,
                            'phone' => $phone,
                            'date_of_birth' => $dateOfBirth,
                            'username' => $username,
                            'password' => $password
                        ];
                        $step = 2;
                    }
                } catch (Exception $e) {
                    $error = 'Registration failed. Please try again.';
                    if (DEBUG_MODE) {
                        $error .= ' Error: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Check if we're in step 2 or 3 but have no session data
if ($step == 1 && isset($_GET['step'])) {
    if ($_GET['step'] == '2' && isset($_SESSION['registration_data'])) {
        $step = 2;
    } elseif ($_GET['step'] == '3' && isset($_SESSION['registration_data']) && isset($_SESSION['library_data'])) {
        $step = 3;
    }
}

$pageTitle = 'Register';
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
            padding: 1rem 0;
        }

        .register-container {
            max-width: 600px;
            width: 100%;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        /* Wider container for plan selection */
        .register-container.plan-step {
            max-width: 1000px;
        }

        .register-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 1.75rem;
            text-align: center;
        }

        .register-header {
            margin-bottom: 0.75rem;
        }

        .register-header .logo {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 1.3rem;
            font-weight: bold;
            color: #3498DB;
            margin-bottom: 0.5rem;
        }

        .register-header .logo i {
            font-size: 1.5rem;
            color: #2980B9;
        }

        .register-header h1 {
            font-size: 1.3rem;
            color: #495057;
            font-weight: 300;
            margin-bottom: 0.25rem;
        }

        .register-header p {
            color: #6c757d;
            font-size: 0.85rem;
        }

        .step-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.3rem 0.6rem;
            border-radius: 16px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .step.active {
            background: #3498DB;
            color: white;
        }

        .step.completed {
            background: #3498DB;
            color: white;
        }

        .step.inactive {
            background: #e9ecef;
            color: #6c757d;
        }

        .step-divider {
            width: 1.5rem;
            height: 2px;
            background: #e9ecef;
        }

        .step-divider.completed {
            background: #3498DB;
        }

        .form-group {
            margin-bottom: 0.75rem;
            text-align: left;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.4rem;
            font-weight: 500;
            color: #495057;
            font-size: 0.85rem;
        }

        .form-group label .required {
            color: #dc3545;
        }

        .form-control {
            width: 100%;
            padding: 0.7rem;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498DB;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .input-group {
            position: relative;
        }

        .input-group i {
            position: absolute;
            left: 0.7rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 0.85rem;
        }

        .input-group .form-control {
            padding-left: 2.5rem;
            padding-right: 2.5rem;
        }

        .password-toggle {
            position: absolute;
            right: 0.7rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            cursor: pointer;
            font-size: 0.85rem;
            transition: color 0.3s ease;
            z-index: 2;
        }

        .password-toggle:hover {
            color: #3498DB;
        }
        
        .optional {
            color: #6c757d;
            font-weight: normal;
            font-size: 0.8rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            color: #ffffff;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: #ffffff;
        }

        .btn-secondary:hover {
            background: #495057;
        }

        .btn-outline {
            background: #ffffff;
            color: #3498DB;
            border: 2px solid #3498DB;
        }

        .btn-outline:hover {
            background: #3498DB;
            color: #ffffff;
            transform: translateY(-2px);
        }

        .btn-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-top: 0.75rem;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn:disabled:hover {
            transform: none;
            box-shadow: none;
        }

        /* Loading Spinner */
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 0.8s ease-in-out infinite;
            margin-right: 0.5rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading-text {
            opacity: 0.9;
        }

        /* Success state styles */
        .success-container .register-header h1,
        .success-container .register-header p {
            display: none;
        }

        .success-container .step-indicator {
            display: none;
        }

        .success-container .auth-links {
            display: none;
        }

        .success-container .back-home {
            display: none;
        }

        .alert {
            padding: 0.7rem;
            border-radius: 6px;
            margin-bottom: 0.75rem;
            display: block;
            font-size: 0.85rem;
            text-align: center;
        }

        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            text-align: center;
        }

        .alert-success .fa-check-circle {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }

        .alert-success .success-actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 1.5rem;
            align-items: center;
            width: 100%;
        }

        .alert-success .success-actions .btn {
            width: auto;
            min-width: 200px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .alert-success .resend-section {
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .alert-success .resend-section p {
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .auth-links {
            margin-top: 1rem;
            text-align: center;
        }

        .auth-links a {
            color: #3498DB;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .auth-links a:hover {
            color: #2980B9;
            text-decoration: underline;
        }

        .back-home {
            text-align: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .back-home a {
            color: #6c757d;
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: color 0.3s ease;
        }

        .back-home a:hover {
            color: #495057;
        }

        .help-text {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 0.2rem;
        }

        /* Plan Selection Styles */
        .plan-selection {
            margin: 1.5rem 0;
            width: 100%;
            overflow: hidden;
        }

        .plans-grid {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            align-items: stretch;
            padding: 0 1rem;
            overflow-x: auto;
        }

        .plan-card {
            position: relative;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            background: #ffffff;
            flex: 1;
            max-width: 300px;
            min-width: 260px;
            flex-shrink: 0;
        }

        .plan-card.featured {
            border-color: #3498DB;
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.15);
        }

        .plan-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: linear-gradient(135deg, #3498DB, #2980B9);
            color: white;
            padding: 0.3rem 0.8rem;
            font-size: 0.7rem;
            font-weight: 600;
            border-bottom-left-radius: 8px;
            z-index: 2;
        }

        .plan-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            z-index: 1;
        }

        .plan-label {
            display: block;
            padding: 1.5rem;
            cursor: pointer;
            height: 100%;
            transition: all 0.3s ease;
        }

        .plan-card:hover {
            border-color: #3498DB;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.1);
        }

        .plan-card input[type="radio"]:checked + .plan-label {
            background: linear-gradient(135deg, #f8f9ff, #e8f4fd);
            border-color: #3498DB;
        }

        .plan-header {
            text-align: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .plan-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0 0 0.5rem 0;
        }

        .plan-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: #27ae60;
        }

        .plan-features ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .plan-features li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0;
            font-size: 0.85rem;
            color: #495057;
        }

        .plan-features li i {
            color: #27ae60;
            font-size: 0.8rem;
            width: 12px;
        }

        /* Logo Upload Styles */
        .logo-upload-container {
            position: relative;
        }

        .logo-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            z-index: 1;
        }

        .logo-preview-area {
            border: 2px dashed #bdc3c7;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8f9fa;
            position: relative;
        }

        .logo-preview-area:hover {
            border-color: #3498DB;
            background: #f0f8ff;
        }

        .logo-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }

        .logo-placeholder i {
            font-size: 2.5rem;
            color: #7f8c8d;
        }

        .logo-placeholder p {
            margin: 0;
            font-weight: 500;
            color: #2c3e50;
        }

        .logo-placeholder span {
            font-size: 0.8rem;
            color: #7f8c8d;
        }

        .logo-preview {
            max-width: 150px;
            max-height: 150px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .logo-preview-area.has-image {
            border-style: solid;
            border-color: #27ae60;
            background: #f8fff8;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .plans-grid {
                gap: 1rem;
                padding: 0 0.5rem;
            }
            
            .plan-card {
                min-width: 240px;
                max-width: 280px;
            }
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .btn-group {
                grid-template-columns: 1fr;
            }
            
            .step-indicator {
                gap: 0.5rem;
            }
            
            .plans-grid {
                flex-direction: column;
                align-items: center;
                gap: 1rem;
                padding: 0;
            }
            
            .plan-card {
                max-width: 100%;
                width: 100%;
                min-width: auto;
            }
        }

        @media (max-width: 480px) {
            .register-container {
                padding: 0 1rem;
            }
            
            .register-card {
                padding: 1.5rem 1.25rem;
            }
        }
    </style>
    
    <script>
        function togglePassword(fieldId, toggleId) {
            const passwordInput = document.getElementById(fieldId);
            const passwordToggle = document.getElementById(toggleId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordToggle.classList.remove('fa-eye');
                passwordToggle.classList.add('fa-eye-slash');
                passwordToggle.title = 'Hide Password';
            } else {
                passwordInput.type = 'password';
                passwordToggle.classList.remove('fa-eye-slash');
                passwordToggle.classList.add('fa-eye');
                passwordToggle.title = 'Show Password';
            }
        }
        
        function previewLogo(input) {
            const file = input.files[0];
            const previewArea = document.querySelector('.logo-preview-area');
            const placeholder = document.querySelector('.logo-placeholder');
            const preview = document.getElementById('logo-preview');
            
            if (file) {
                // Validate file size (2MB max)
                if (file.size > 2 * 1024 * 1024) {
                    alert('File size must be less than 2MB');
                    input.value = '';
                    return;
                }
                
                // Validate file type
                if (!file.type.startsWith('image/')) {
                    alert('Please select an image file');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    placeholder.style.display = 'none';
                    previewArea.classList.add('has-image');
                };
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
                placeholder.style.display = 'flex';
                previewArea.classList.remove('has-image');
            }
        }
        
        function generateCode() {
            const libraryName = document.getElementById('library_name').value.trim();
            const codeInput = document.getElementById('library_code');
            
            if (!libraryName) {
                alert('Please enter the library name first');
                document.getElementById('library_name').focus();
                return;
            }
            
            // Generate code from library name
            const words = libraryName.split(/[\s\-_]+/);
            let code = '';
            
            // Take first 2-3 letters from each word (up to 4 words)
            let wordCount = 0;
            for (let word of words) {
                if (wordCount >= 4) break;
                
                word = word.replace(/[^a-zA-Z]/g, ''); // Remove non-letters
                if (word.length > 0) {
                    if (word.length >= 3) {
                        code += word.substring(0, 3);
                    } else {
                        code += word;
                    }
                    wordCount++;
                }
            }
            
            // If code is too short, pad with initials
            if (code.length < 4) {
                let initials = '';
                for (let word of words) {
                    word = word.replace(/[^a-zA-Z]/g, '');
                    if (word.length > 0) {
                        initials += word[0].toUpperCase();
                    }
                }
                code = initials + code;
            }
            
            // Ensure minimum length
            if (code.length < 4) {
                code = 'LIB' + code;
            }
            
            // Limit to 6 characters and make uppercase
            code = code.substring(0, 6).toUpperCase();
            
            // Add random number if still too short
            if (code.length < 4) {
                code += Math.floor(Math.random() * 90 + 10);
            }
            
            codeInput.value = code;
            
            // Add visual feedback
            codeInput.style.background = '#e8f5e8';
            setTimeout(() => {
                codeInput.style.background = '';
            }, 1000);
        }
        
        // Auto-generate code when library name changes (if code is empty)
        document.addEventListener('DOMContentLoaded', function() {
            const libraryNameInput = document.getElementById('library_name');
            const codeInput = document.getElementById('library_code');
            
            if (libraryNameInput && codeInput) {
                // Auto-generate on library name blur
                libraryNameInput.addEventListener('blur', function() {
                    if (this.value.trim() && !codeInput.value.trim()) {
                        generateCode();
                    }
                });
                
                // Auto-generate when code input is clicked/focused (if empty)
                codeInput.addEventListener('focus', function() {
                    const libraryName = libraryNameInput.value.trim();
                    if (libraryName && !this.value.trim()) {
                        generateCode();
                    }
                });
            }
        });
        
        // Clear registration flow when navigating away from registration
        window.addEventListener('beforeunload', function() {
            // Only clear if navigating to a different domain or closing
            // This will be handled by server-side logic for internal navigation
        });
        
        // Handle navigation to other internal pages
        document.addEventListener('click', function(e) {
            const link = e.target.closest('a');
            if (link && link.href) {
                const currentHost = window.location.host;
                const linkHost = new URL(link.href).host;
                const isInternalLink = linkHost === currentHost;
                const isRegistrationPage = link.href.includes('register.php');
                
                // If clicking an internal link that's not register.php, clear the flow
                if (isInternalLink && !isRegistrationPage && !link.href.includes('#')) {
                    // Send request to clear registration flow
                    fetch('register.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'clear_registration_flow=1'
                    }).catch(() => {});
                }
            }
        });
        
        // Handle Complete Registration form submission with loading state
        document.addEventListener('DOMContentLoaded', function() {
            const completeBtn = document.getElementById('completeRegistrationBtn');
            
            if (completeBtn) {
                const form = completeBtn.closest('form');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        // Don't prevent default - let the form submit
                        const buttonContent = completeBtn.querySelector('.button-content');
                        const loadingContent = completeBtn.querySelector('.loading-content');
                        
                        // Show loading state
                        if (buttonContent) buttonContent.style.display = 'none';
                        if (loadingContent) {
                            loadingContent.style.display = 'flex';
                            loadingContent.style.alignItems = 'center';
                            loadingContent.style.justifyContent = 'center';
                        }
                        
                        // Disable button to prevent double submission
                        completeBtn.disabled = true;
                        
                        // Disable other form inputs to prevent changes
                        const formInputs = form.querySelectorAll('input:not([type="hidden"]):not([type="radio"]):not([name="plan"]), button');
                        formInputs.forEach(input => {
                            if (input !== completeBtn) {
                                input.disabled = true;
                            }
                        });
                    });
                }
            }
        });
    </script>
</head>
<body>
    <div class="register-container<?php echo $step == 3 ? ' plan-step' : ''; ?><?php echo !empty($success) ? ' success-container' : ''; ?>">
        <div class="register-card">
            <!-- Header -->
            <div class="register-header">
                <div class="logo">
                    <i class="fas fa-book-open"></i>
                    LMS
                </div>
                <h1><?php echo $step == 1 ? 'Create Account' : ($step == 2 ? 'Library Setup' : 'Choose Plan'); ?></h1>
                <p><?php echo $step == 1 ? 'Start your library management journey' : ($step == 2 ? 'Complete your library information' : 'Select your subscription plan'); ?></p>
            </div>

            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step <?php echo $step >= 1 ? 'active' : 'inactive'; ?>">
                    <i class="fas fa-user"></i>
                    <span>Personal Info</span>
                </div>
                <div class="step-divider <?php echo $step > 1 ? 'completed' : ''; ?>"></div>
                <div class="step <?php echo $step == 2 ? 'active' : ($step > 2 ? 'completed' : 'inactive'); ?>">
                    <i class="fas fa-building"></i>
                    <span>Library Setup</span>
                </div>
                <div class="step-divider <?php echo $step > 2 ? 'completed' : ''; ?>"></div>
                <div class="step <?php echo $step == 3 ? 'active' : ($step > 3 ? 'completed' : 'inactive'); ?>">
                    <i class="fas fa-crown"></i>
                    <span>Plan Selection</span>
                </div>
            </div>

            <!-- Error/Success Messages -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                    
                    <div class="success-actions">
                        <?php if (isset($_SESSION['pending_verification_email'])): ?>
                            <div class="resend-section">
                                <p>Didn't receive the email?</p>
                                <a href="resend-verification.php" class="btn btn-outline">
                                    <i class="fas fa-envelope"></i>
                                    Resend Verification Email
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <a href="login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i>
                            Go to Login
                        </a>
                    </div>
                </div>
            <?php elseif ($step == 1): ?>
                <!-- Step 1: Personal Information -->
                <form method="POST" action="register.php">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" 
                                       id="first_name" 
                                       name="first_name" 
                                       class="form-control" 
                                       placeholder="John"
                                       value="<?php echo htmlspecialchars($_POST['first_name'] ?? $_SESSION['registration_data']['first_name'] ?? ''); ?>"
                                       required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="last_name">Last Name <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" 
                                       id="last_name" 
                                       name="last_name" 
                                       class="form-control" 
                                       placeholder="Doe"
                                       value="<?php echo htmlspecialchars($_POST['last_name'] ?? $_SESSION['registration_data']['last_name'] ?? ''); ?>"
                                       required>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email Address <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-envelope"></i>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       class="form-control" 
                                       placeholder="john@example.com"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? $_SESSION['registration_data']['email'] ?? ''); ?>"
                                       required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="username">Username <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-at"></i>
                                <input type="text" 
                                       id="username" 
                                       name="username" 
                                       class="form-control" 
                                       placeholder="johndoe"
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? $_SESSION['registration_data']['username'] ?? ''); ?>"
                                       required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="help-text" style="margin-top: -0.55rem; margin-bottom: 0.75rem; text-align: left;">Username will be used to login to your account</div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <div class="input-group">
                                <i class="fas fa-calendar"></i>
                                <input type="date" 
                                       id="date_of_birth" 
                                       name="date_of_birth" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? $_SESSION['registration_data']['date_of_birth'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <div class="input-group">
                                <i class="fas fa-phone"></i>
                                <input type="tel" 
                                       id="phone" 
                                       name="phone" 
                                       class="form-control" 
                                       placeholder="+233 XX XXX XXXX"
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? $_SESSION['registration_data']['phone'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>



                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Password <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" 
                                       id="password" 
                                       name="password" 
                                       class="form-control" 
                                       placeholder="Password"
                                       required>
                                <span class="fas fa-eye password-toggle" 
                                      onclick="togglePassword('password', 'passwordToggle1')"
                                      id="passwordToggle1"
                                      title="Show/Hide Password"></span>
                            </div>
                            <div class="help-text">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       class="form-control" 
                                       placeholder="Confirm Password"
                                       required>
                                <span class="fas fa-eye password-toggle" 
                                      onclick="togglePassword('confirm_password', 'passwordToggle2')"
                                      id="passwordToggle2"
                                      title="Show/Hide Password"></span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-arrow-right"></i>
                        Continue to Library Setup
                    </button>
                </form>

            <?php elseif ($step == 2): ?>
                <!-- Step 2: Library Information -->
                <form method="POST" action="register.php" enctype="multipart/form-data">
                    <input type="hidden" name="step" value="2">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="library_name">Library Name <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-building"></i>
                                <input type="text" 
                                       id="library_name" 
                                       name="library_name" 
                                       class="form-control" 
                                       placeholder="Central Public Library"
                                       value="<?php echo htmlspecialchars($_POST['library_name'] ?? $_SESSION['library_data']['library_name'] ?? ''); ?>"
                                       required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="library_code">Library Code <span class="optional">(Auto-generated)</span></label>
                            <div class="input-group">
                                <i class="fas fa-hashtag"></i>
                                <input type="text" 
                                       id="library_code" 
                                       name="library_code" 
                                       class="form-control" 
                                       placeholder="Will be auto-generated from library name"
                                       value="<?php echo htmlspecialchars($_POST['library_code'] ?? $_SESSION['library_data']['library_code'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="help-text" style="margin-top: -0.75rem; margin-bottom: 1rem; text-align: left;">Leave blank to auto-generate from library name, or enter a custom 3-10 character code</div>

                    <!-- Library Logo Upload -->
                    <div class="form-group">
                        <label for="library_logo">Library Logo</label>
                        <div class="logo-upload-container">
                            <input type="file" 
                                   id="library_logo" 
                                   name="library_logo" 
                                   class="logo-input" 
                                   accept="image/*"
                                   onchange="previewLogo(this)">
                            <div class="logo-preview-area" onclick="document.getElementById('library_logo').click()">
                                <div class="logo-placeholder">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Click to upload logo</p>
                                    <span>PNG, JPG, GIF up to 2MB</span>
                                </div>
                                <img id="logo-preview" class="logo-preview" style="display: none;">
                            </div>
                        </div>
                        <div class="help-text">Upload your library logo (recommended size: 200x200px)</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="address">Library Address <span class="required">*</span></label>
                            <textarea id="address" 
                                      name="address" 
                                      class="form-control" 
                                      rows="2"
                                      placeholder="123 Main Street, City, Region"
                                      required><?php echo htmlspecialchars($_POST['address'] ?? $_SESSION['library_data']['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="website">Website</label>
                            <div class="input-group">
                                <i class="fas fa-globe"></i>
                                <input type="url" 
                                       id="website" 
                                       name="website" 
                                       class="form-control" 
                                       placeholder="https://www.library.com"
                                       value="<?php echo htmlspecialchars($_POST['website'] ?? $_SESSION['library_data']['website'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="library_phone">Library Phone <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-phone"></i>
                                <input type="tel" 
                                       id="library_phone" 
                                       name="phone" 
                                       class="form-control" 
                                       placeholder="+233 XX XXX XXXX"
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? $_SESSION['library_data']['phone'] ?? ''); ?>"
                                       required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="library_email">Library Email <span class="required">*</span></label>
                            <div class="input-group">
                                <i class="fas fa-envelope"></i>
                                <input type="email" 
                                       id="library_email" 
                                       name="library_email" 
                                       class="form-control" 
                                       placeholder="info@library.com"
                                       value="<?php echo htmlspecialchars($_POST['library_email'] ?? $_SESSION['library_data']['library_email'] ?? ''); ?>"
                                       required>
                            </div>
                        </div>
                    </div>

                    <div class="btn-group">
                        <a href="register.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i>
                            Back
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-arrow-right"></i>
                            Continue to Plan Selection
                        </button>
                    </div>
                </form>

            <?php elseif ($step == 3): ?>
                <!-- Step 3: Plan Selection -->
                <form method="POST" action="register.php">
                    <input type="hidden" name="step" value="3">
                    
                    <div class="plan-selection">
                        <div class="plans-grid">
                            <!-- Basic Plan -->
                            <div class="plan-card">
                                <input type="radio" id="basic" name="plan" value="basic" required>
                                <label for="basic" class="plan-label">
                                    <div class="plan-header">
                                        <h3>Basic Plan</h3>
                                        <div class="plan-price">GH₵<?php echo number_format(BASIC_PLAN_PRICE / 100); ?>/month</div>
                                    </div>
                                    <div class="plan-features">
                                        <ul>
                                            <li><i class="fas fa-check"></i> Up to <?php echo number_format(BASIC_PLAN_BOOK_LIMIT); ?> books</li>
                                            <li><i class="fas fa-check"></i> Basic member management</li>
                                            <li><i class="fas fa-check"></i> Borrowing & returns</li>
                                            <li><i class="fas fa-check"></i> Email notifications</li>
                                            <li><i class="fas fa-check"></i> Basic reports</li>
                                        </ul>
                                    </div>
                                </label>
                            </div>

                            <!-- Standard Plan -->
                            <div class="plan-card featured">
                                <div class="plan-badge">Most Popular</div>
                                <input type="radio" id="standard" name="plan" value="standard" required>
                                <label for="standard" class="plan-label">
                                    <div class="plan-header">
                                        <h3>Standard Plan</h3>
                                        <div class="plan-price">GH₵<?php echo number_format(STANDARD_PLAN_PRICE / 100); ?>/month</div>
                                    </div>
                                    <div class="plan-features">
                                        <ul>
                                            <li><i class="fas fa-check"></i> Up to <?php echo number_format(STANDARD_PLAN_BOOK_LIMIT); ?> books</li>
                                            <li><i class="fas fa-check"></i> Advanced member management</li>
                                            <li><i class="fas fa-check"></i> Reservations & fines</li>
                                            <li><i class="fas fa-check"></i> SMS & email notifications</li>
                                            <li><i class="fas fa-check"></i> Advanced reports</li>
                                            <li><i class="fas fa-check"></i> Multi-librarian support</li>
                                        </ul>
                                    </div>
                                </label>
                            </div>

                            <!-- Premium Plan -->
                            <div class="plan-card">
                                <input type="radio" id="premium" name="plan" value="premium" required>
                                <label for="premium" class="plan-label">
                                    <div class="plan-header">
                                        <h3>Premium Plan</h3>
                                        <div class="plan-price">GH₵<?php echo number_format(PREMIUM_PLAN_PRICE / 100); ?>/month</div>
                                    </div>
                                    <div class="plan-features">
                                        <ul>
                                            <li><i class="fas fa-check"></i> Unlimited books</li>
                                            <li><i class="fas fa-check"></i> Full member management</li>
                                            <li><i class="fas fa-check"></i> Advanced reservations</li>
                                            <li><i class="fas fa-check"></i> All notification types</li>
                                            <li><i class="fas fa-check"></i> Comprehensive reports</li>
                                            <li><i class="fas fa-check"></i> Multiple branches</li>
                                            <li><i class="fas fa-check"></i> API access</li>
                                            <li><i class="fas fa-check"></i> Priority support</li>
                                        </ul>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="btn-group">
                        <a href="register.php?step=2" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i>
                            Back
                        </a>
                        <button type="submit" id="completeRegistrationBtn" class="btn btn-primary">
                            <span class="button-content">
                                <i class="fas fa-check"></i>
                                Complete Registration
                            </span>
                            <span class="loading-content" style="display: none;">
                                <span class="spinner"></span>
                                <span class="loading-text">Creating Account...</span>
                            </span>
                        </button>
                    </div>
                </form>

            <?php elseif ($step == 4): ?>
                <!-- This step shouldn't exist anymore -->
            <?php endif; ?>

            <?php if (empty($success)): ?>
                <!-- Auth Links -->
                <div class="auth-links">
                    <p>Already have an account? <a href="login.php">Sign in here</a></p>
                </div>
            <?php endif; ?>
            
            <!-- Back to Home -->
            <div class="back-home">
                <a href="index.php">
                    <i class="fas fa-arrow-left"></i>
                    Back to Home
                </a>
            </div>
        </div>
    </div>
</body>
</html>