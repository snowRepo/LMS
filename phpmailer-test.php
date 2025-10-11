<?php
// Simple PHPMailer installation test
echo "<h2>PHPMailer Installation Test</h2>";

// Check if vendor autoload exists
if (file_exists('vendor/autoload.php')) {
    echo "<p>✓ Composer vendor/autoload.php found</p>";
    require_once 'vendor/autoload.php';
} else {
    echo "<p>✗ Composer vendor/autoload.php NOT found</p>";
    die("Please run: composer install");
}

// Test PHPMailer classes
try {
    echo "<p>Testing PHPMailer classes...</p>";
    
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        echo "<p>✓ PHPMailer\\PHPMailer\\PHPMailer class loaded</p>";
    } else {
        echo "<p>✗ PHPMailer\\PHPMailer\\PHPMailer class NOT found</p>";
    }
    
    if (class_exists('PHPMailer\\PHPMailer\\SMTP')) {
        echo "<p>✓ PHPMailer\\PHPMailer\\SMTP class loaded</p>";
    } else {
        echo "<p>✗ PHPMailer\\PHPMailer\\SMTP class NOT found</p>";
    }
    
    if (class_exists('PHPMailer\\PHPMailer\\Exception')) {
        echo "<p>✓ PHPMailer\\PHPMailer\\Exception class loaded</p>";
    } else {
        echo "<p>✗ PHPMailer\\PHPMailer\\Exception class NOT found</p>";
    }
    
    // Try to create PHPMailer instance
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    echo "<p>✓ PHPMailer instance created successfully!</p>";
    echo "<p><strong>PHPMailer Version:</strong> " . $mail::VERSION . "</p>";
    
    echo "<h3>🎉 PHPMailer is properly installed and working!</h3>";
    echo "<p><a href='test-email.php'>→ Go to Email Configuration Test</a></p>";
    
} catch (Exception $e) {
    echo "<p>✗ Error: " . $e->getMessage() . "</p>";
}
?>