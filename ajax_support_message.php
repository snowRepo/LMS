<?php
define('LMS_ACCESS', true);

// Load configuration
require_once 'includes/EnvLoader.php';
EnvLoader::load();
require_once 'config/config.php';

header('Content-Type: application/json');

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid data received']);
    exit;
}

$name = sanitize_input($input['name'] ?? '');
$email = sanitize_input($input['email'] ?? '');
$subject = sanitize_input($input['subject'] ?? '');
$message = sanitize_input($input['message'] ?? '');

// Validate required fields
if (empty($name) || empty($email) || empty($subject) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Insert support message into database
    $stmt = $conn->prepare("INSERT INTO support_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $email, $subject, $message]);
    
    // Get the inserted ID
    $messageId = $conn->lastInsertId();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Your message has been sent successfully. We will get back to you soon.',
        'message_id' => $messageId
    ]);
    
} catch (Exception $e) {
    error_log("Support message error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to send message. Please try again later.']);
}
?>