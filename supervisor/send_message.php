<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Get the sender's user string ID
    $stmt = $db->prepare("SELECT user_id, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $sender = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sender) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    $senderId = $sender['user_id'];
    $senderRole = $sender['role'];
    
    // Get form data
    $recipientType = $_POST['recipient_type'] ?? '';
    $individualRecipient = $_POST['individual_recipient'] ?? '';
    $subject = trim($_POST['subject'] ?? '');
    $messageBody = trim($_POST['message_body'] ?? '');
    
    // Validate required fields
    if (empty($recipientType) || empty($subject) || empty($messageBody)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'All fields are required']);
        exit;
    }
    
    // Validate recipient type based on user role
    $isValid = true;
    $errorMessage = '';
    
    if ($senderRole === 'supervisor') {
        // Supervisors can only message librarians or all librarians (not members)
        if ($recipientType === 'all_members') {
            $isValid = false;
            $errorMessage = 'Supervisors cannot send messages to all members.';
        }
    } else if ($senderRole === 'member') {
        // Members can only message librarians
        if ($recipientType === 'supervisor' || $recipientType === 'all_members') {
            $isValid = false;
            $errorMessage = 'Members can only send messages to librarians.';
        }
    }
    
    if (!$isValid) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $errorMessage]);
        exit;
    }
    
    // Process based on recipient type
    if ($recipientType === 'all_librarians') {
        // Send to all librarians in the same library
        $stmt = $db->prepare("
            SELECT user_id FROM users 
            WHERE library_id = ? AND role = 'librarian' AND status = 'active' AND user_id != ?
        ");
        $stmt->execute([$_SESSION['library_id'], $senderId]);
        $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($recipients)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No librarians found in your library']);
            exit;
        }
        
        // Insert message for each librarian
        foreach ($recipients as $recipientId) {
            $stmt = $db->prepare("
                INSERT INTO messages (sender_id, recipient_id, subject, message, library_id) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$senderId, $recipientId, $subject, $messageBody, $_SESSION['library_id']]);
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Message sent to all librarians successfully']);
        
    } else if ($recipientType === 'all_members') {
        // Send to all members in the same library (only for librarians)
        $stmt = $db->prepare("
            SELECT user_id FROM users 
            WHERE library_id = ? AND role = 'member' AND status = 'active' AND user_id != ?
        ");
        $stmt->execute([$_SESSION['library_id'], $senderId]);
        $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($recipients)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No members found in your library']);
            exit;
        }
        
        // Insert message for each member
        foreach ($recipients as $recipientId) {
            $stmt = $db->prepare("
                INSERT INTO messages (sender_id, recipient_id, subject, message, library_id) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$senderId, $recipientId, $subject, $messageBody, $_SESSION['library_id']]);
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Message sent to all members successfully']);
        
    } else if ($recipientType === 'supervisor') {
        // Send to supervisor (only for librarians)
        $stmt = $db->prepare("
            SELECT user_id FROM users 
            WHERE library_id = ? AND role = 'supervisor' AND status = 'active' AND user_id != ?
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['library_id'], $senderId]);
        $recipientId = $stmt->fetchColumn();
        
        if (!$recipientId) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No supervisor found in your library']);
            exit;
        }
        
        // Insert message
        $stmt = $db->prepare("
            INSERT INTO messages (sender_id, recipient_id, subject, message, library_id) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$senderId, $recipientId, $subject, $messageBody, $_SESSION['library_id']]);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Message sent to supervisor successfully']);
        
    } else if ($recipientType === 'librarian') {
        // Send to librarian (only for members)
        $stmt = $db->prepare("
            SELECT user_id FROM users 
            WHERE library_id = ? AND role = 'librarian' AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['library_id']]);
        $recipientId = $stmt->fetchColumn();
        
        if (!$recipientId) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No librarian found in your library']);
            exit;
        }
        
        // Insert message
        $stmt = $db->prepare("
            INSERT INTO messages (sender_id, recipient_id, subject, message, library_id) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$senderId, $recipientId, $subject, $messageBody, $_SESSION['library_id']]);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Message sent to librarian successfully']);
        
    } else if (in_array($recipientType, ['individual_librarian', 'individual_member'])) {
        // Send to individual recipient
        if (empty($individualRecipient)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Please select a recipient']);
            exit;
        }
        
        // Get the sender's numeric ID
        $stmt = $db->prepare("SELECT id FROM users WHERE user_id = ?");
        $stmt->execute([$senderId]);
        $senderNumericId = $stmt->fetchColumn();
        
        // Check if sender is trying to message themselves
        if ($individualRecipient == $senderNumericId) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'You cannot send a message to yourself']);
            exit;
        }
        
        // Validate that the recipient exists and is in the same library
        $stmt = $db->prepare("
            SELECT user_id, role FROM users 
            WHERE id = ? AND library_id = ? AND status = 'active'
        ");
        $stmt->execute([$individualRecipient, $_SESSION['library_id']]);
        $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$recipient) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Recipient not found']);
            exit;
        }
        
        // Verify the recipient role matches the expected type
        $expectedRole = ($recipientType === 'individual_librarian') ? 'librarian' : 'member';
        if ($recipient['role'] !== $expectedRole) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid recipient type']);
            exit;
        }
        
        // Insert message
        $stmt = $db->prepare("
            INSERT INTO messages (sender_id, recipient_id, subject, message, library_id) 
            VALUES (?, (SELECT user_id FROM users WHERE id = ?), ?, ?, ?)
        ");
        $stmt->execute([$senderId, $individualRecipient, $subject, $messageBody, $_SESSION['library_id']]);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
        
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid recipient type']);
        exit;
    }
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error sending message: ' . $e->getMessage()]);
}
?>