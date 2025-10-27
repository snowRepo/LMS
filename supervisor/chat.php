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

// Check if user is logged in and has supervisor role
if (!is_logged_in() || $_SESSION['user_role'] !== 'supervisor') {
    header('Location: ../login.php');
    exit;
}

// Check subscription status
$subscriptionManager = new SubscriptionManager();
$libraryId = $_SESSION['library_id'];
$hasActiveSubscription = $subscriptionManager->hasActiveSubscription($libraryId);

if (!$hasActiveSubscription) {
    header('Location: ../subscription.php');
    exit;
}

// Get message ID from URL parameter
$messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (empty($messageId)) {
    header('Location: messages.php');
    exit;
}

// Get message details
try {
    $db = Database::getInstance()->getConnection();
    
    // Get the user string ID from the integer session ID
    $stmt = $db->prepare("SELECT user_id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header('Location: ../login.php');
        exit;
    }
    
    $user_string_id = $user['user_id'];
    
    // Get the message and mark it as read
    $stmt = $db->prepare("
        SELECT m.*, 
               CONCAT(u.first_name, ' ', u.last_name) as sender_name,
               CONCAT(u2.first_name, ' ', u2.last_name) as recipient_name,
               u.user_id as sender_id,
               u2.user_id as recipient_id
        FROM messages m
        JOIN users u ON m.sender_id = u.user_id
        JOIN users u2 ON m.recipient_id = u2.user_id
        WHERE m.id = ? AND (m.recipient_id = ? OR m.sender_id = ?) AND m.is_deleted = 0
    ");
    $stmt->execute([$messageId, $user_string_id, $user_string_id]);
    $message = $stmt->fetch();
    
    if (!$message) {
        header('Location: messages.php');
        exit;
    }
    
    // Mark message as read if current user is recipient
    if ($message['recipient_id'] == $user_string_id && !$message['is_read']) {
        $stmt = $db->prepare("UPDATE messages SET is_read = 1, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$messageId]);
    }
    
    // Get conversation history (all messages between these two users)
    $stmt = $db->prepare("
        SELECT m.*, 
               CONCAT(u.first_name, ' ', u.last_name) as sender_name,
               u.user_id as sender_id
        FROM messages m
        JOIN users u ON m.sender_id = u.user_id
        WHERE ((m.sender_id = ? AND m.recipient_id = ?) OR (m.sender_id = ? AND m.recipient_id = ?))
        AND m.is_deleted = 0
        ORDER BY m.created_at ASC
    ");
    
    $otherUserId = $message['sender_id'] == $user_string_id ? $message['recipient_id'] : $message['sender_id'];
    $stmt->execute([
        $user_string_id, $otherUserId,
        $otherUserId, $user_string_id
    ]);
    $conversation = $stmt->fetchAll();
    
} catch (Exception $e) {
    die('Error loading message: ' . $e->getMessage());
}

// Handle reply action
$replySent = false;
$messageError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_reply') {
    try {
        $db = Database::getInstance()->getConnection();
        
        $recipientId = trim($_POST['recipient_id']);
        $subject = trim($_POST['subject']);
        $messageBody = trim($_POST['message']);
        
        // Validate required fields
        if (empty($recipientId) || empty($subject) || empty($messageBody)) {
            $messageError = "Recipient, subject, and message are required.";
        } else {
            // Insert reply message
            $stmt = $db->prepare("
                INSERT INTO messages 
                (sender_id, recipient_id, subject, message, library_id, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([
                $user_string_id,
                $recipientId,
                $subject,
                $messageBody,
                $libraryId
            ]);
            
            if ($result) {
                $replySent = true;
                // Refresh the conversation to include the new message
                $stmt = $db->prepare("
                    SELECT m.*, 
                           CONCAT(u.first_name, ' ', u.last_name) as sender_name,
                           u.user_id as sender_id
                    FROM messages m
                    JOIN users u ON m.sender_id = u.user_id
                    WHERE ((m.sender_id = ? AND m.recipient_id = ?) OR (m.sender_id = ? AND m.recipient_id = ?))
                    AND m.is_deleted = 0
                    ORDER BY m.created_at ASC
                ");
                
                $stmt->execute([
                    $user_string_id, $recipientId,
                    $recipientId, $user_string_id
                ]);
                $conversation = $stmt->fetchAll();
            } else {
                $messageError = "Failed to send message. Please try again.";
            }
        }
    } catch (Exception $e) {
        $messageError = "Error sending message: " . $e->getMessage();
    }
}

$pageTitle = 'Chat - ' . htmlspecialchars($message['subject']);
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
        :root {
            --primary-color: #3498DB;
            --primary-dark: #2980B9;
            --gray-200: #e5e7eb;
            --gray-500: #6b7280;
            --gray-800: #1f2937;
            --gray-900: #111827;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
        }
        
        .chat-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            height: calc(100vh - 150px);
            overflow: hidden;
        }
        
        .chat-header {
            background: var(--primary-color);
            color: white;
            padding: 1rem;
            text-align: center;
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .back-link {
            color: white;
            text-decoration: none;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.2);
            transition: background 0.2s;
        }
        
        .back-link:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .participant-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1rem;
        }
        
        .participant-name {
            font-weight: 600;
            color: var(--gray-900);
            font-size: 1.1rem;
        }
        
        .chat-messages {
            flex: 1;
            padding: 1.5rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            background-color: #f8f9fa;
        }
        
        .chat-message {
            max-width: 70%;
            padding: 0.5rem 0;
        }
        
        .chat-message.sent {
            align-self: flex-end;
            text-align: right;
        }
        
        .chat-message.received {
            align-self: flex-start;
            text-align: left;
        }
        
        .chat-message .message-content {
            display: inline-block;
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            font-size: 0.95rem;
            line-height: 1.4;
            max-width: 100%;
            word-wrap: break-word;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        
        .chat-message.sent .message-content {
            background: var(--primary-color);
            color: white;
            border-bottom-right-radius: 0.25rem;
        }
        
        .chat-message.received .message-content {
            background: white;
            color: var(--gray-900);
            border: 1px solid var(--gray-200);
            border-bottom-left-radius: 0.25rem;
        }
        
        .chat-message .message-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
        }
        
        .chat-message .message-sender {
            font-size: 0.8rem;
            color: var(--gray-500);
        }
        
        .chat-message .message-time {
            font-size: 0.7rem;
            color: var(--gray-500);
        }
        
        .chat-input {
            padding: 1rem;
            border-top: 1px solid var(--gray-200);
            background: white;
            display: flex;
            gap: 0.75rem;
        }
        
        .chat-input textarea {
            flex: 1;
            border: 1px solid var(--gray-200);
            border-radius: 1.5rem;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            resize: none;
            outline: none;
            transition: border-color 0.2s;
            min-height: 44px;
            max-height: 120px;
        }
        
        .chat-input textarea:focus {
            border-color: var(--primary-color);
        }
        
        .send-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        
        .send-btn:hover {
            background: var(--primary-dark);
        }
        
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--gray-500);
            text-align: center;
            padding: 2rem;
        }
        
        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--gray-300);
        }
        
        .empty-state h3 {
            margin-bottom: 0.5rem;
            color: var(--gray-700);
        }
        
        .empty-state p {
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .chat-container {
                height: calc(100vh - 120px);
                border-radius: 0;
            }
            
            .chat-message {
                max-width: 85%;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/supervisor_navbar.php'; ?>
    
    <div class="container">
        <div class="chat-container">
            <div class="chat-header">
                <a href="messages.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Messages
                </a>
                <h2><?php echo htmlspecialchars($message['subject']); ?></h2>
                <div></div> <!-- Empty div for flex spacing -->
            </div>
            
            <div class="participant-info">
                <div class="avatar">
                    <?php 
                    $participantName = $message['sender_id'] == $user_string_id ? $message['recipient_name'] : $message['sender_name'];
                    $nameParts = explode(' ', $participantName);
                    $initials = '';
                    foreach (array_slice($nameParts, 0, 2) as $part) {
                        $initials .= strtoupper(substr($part, 0, 1));
                    }
                    echo $initials;
                    ?>
                </div>
                <div class="participant-name">
                    <?php echo htmlspecialchars($participantName); ?>
                </div>
            </div>
            
            <div class="chat-messages" id="chatMessages">
                <?php if (count($conversation) > 0): ?>
                    <?php foreach ($conversation as $msg): 
                        $isSender = ($msg['sender_id'] == $user_string_id);
                        $messageClass = $isSender ? 'sent' : 'received';
                    ?>
                        <div class="chat-message <?php echo $messageClass; ?>">
                            <div class="message-header">
                                <div class="message-sender"><?php echo htmlspecialchars($msg['sender_name']); ?></div>
                                <div class="message-time"><?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?></div>
                            </div>
                            <div class="message-content">
                                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-comment-slash"></i>
                        <h3>No Messages Yet</h3>
                        <p>Start the conversation by sending a message below.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <form method="post" class="chat-input">
                <input type="hidden" name="action" value="send_reply">
                <input type="hidden" name="recipient_id" value="<?php echo htmlspecialchars($otherUserId); ?>">
                <input type="hidden" name="subject" value="<?php echo htmlspecialchars($message['subject']); ?>">
                <textarea name="message" placeholder="Type your message..." rows="1" required></textarea>
                <button type="submit" class="send-btn">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        </div>
    </div>
    
    <script>
        // Set current user name for JavaScript
        // Commented out as it's not currently in use and causing errors
        // window.currentUserName = <?php 
        //     $name = isset($_SESSION['user_name']) && !empty(trim($_SESSION['user_name'])) 
        //         ? trim($_SESSION['user_name']) 
        //         : 'User';
        //     echo json_encode($name, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
        // ?>;
        
        // Auto-resize textarea
        const textarea = document.querySelector('textarea');
        if (textarea) {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
        }
        
        // Scroll to bottom of messages
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Auto-focus the textarea when page loads
        document.addEventListener('DOMContentLoaded', function() {
            if (textarea) {
                textarea.focus();
            }
        });
    </script>
</body>
</html>