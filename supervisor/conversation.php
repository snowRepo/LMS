<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';
require_once '../includes/SubscriptionManager.php';
require_once '../includes/SubscriptionCheck.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has supervisor role
if (!is_logged_in() || $_SESSION['user_role'] !== 'supervisor') {
    header('Location: ../login.php');
    exit();
}

// Check subscription status - redirect to expired page if subscription is not active
requireActiveSubscription();

// Check subscription status (keeping original logic for backward compatibility)
$subscriptionManager = new SubscriptionManager();
$libraryId = $_SESSION['library_id'];
$hasActiveSubscription = $subscriptionManager->hasActiveSubscription($libraryId);

if (!$hasActiveSubscription) {
    header('Location: ../subscription.php');
    exit;
}

// Get conversation ID and sender ID from URL
$conversationId = $_GET['id'] ?? '';
$senderId = $_GET['sender_id'] ?? '';
$error = '';
$senderName = '';
$senderRole = '';

if (empty($conversationId) || empty($senderId)) {
    $error = 'Invalid conversation parameters';
} else {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Get current user's string ID
        $stmt = $db->prepare("SELECT user_id, first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        $current_user_id = $user['user_id'];
        
        // Get sender's details
        $stmt = $db->prepare("
            SELECT user_id, CONCAT(first_name, ' ', last_name) as name, role 
            FROM users 
            WHERE user_id = ?
        ");
        $stmt->execute([$senderId]);
        $sender = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sender) {
            throw new Exception('Sender not found');
        }
        
        $senderName = $sender['name'];
        $senderRole = $sender['role'];
        
        // Mark messages as read
        $stmt = $db->prepare("
            UPDATE messages 
            SET is_read = 1 
            WHERE id = ? AND sender_id = ? AND recipient_id = ?
        ");
        $stmt->execute([$conversationId, $senderId, $current_user_id]);
        
    } catch (Exception $e) {
        $error = 'Error loading conversation: ' . $e->getMessage();
    }
}

$pageTitle = 'Conversation' . ($senderName ? ' with ' . $senderName : '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
            overflow: hidden; /* Prevent body scrolling */
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 15px;
            height: calc(100vh - 80px); /* Adjust for navbar (56px) + padding */
            display: flex;
            flex-direction: column;
        }
        
        .page-header {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .page-header h1 {
            color: #2c3e50;
            font-weight: 600;
            font-size: 1.4rem;
            margin-bottom: 0;
        }
        
        .content-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .card-header h2 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .card-body {
            padding: 0;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .message-bubble {
            max-width: 70%;
            padding: 8px 12px;
            border-radius: 1rem;
            margin-bottom: 8px;
            position: relative;
            word-wrap: break-word;
            line-height: 1.4;
            font-size: 0.9rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .message-bubble.sent {
            background: #3498db;
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 0.25rem;
        }
        
        .message-bubble.received {
            background: #f1f3f9;
            color: #2c3e50;
            margin-right: auto;
            border-bottom-left-radius: 0.25rem;
        }
        
        .message-time {
            font-size: 0.65rem;
            opacity: 0.8;
            margin-top: 0.2rem;
            display: block;
            text-align: right;
        }
        
        .message-time.sent {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .message-time.received {
            color: #6c757d;
        }
        
        .message-input-container {
            background: white;
            padding: 10px 15px;
            border-top: 1px solid #dee2e6;
        }
        
        #message-input {
            border-radius: 1.5rem;
            padding: 0.5rem 1rem;
            border: 1px solid #dee2e6;
            transition: all 0.2s;
            font-size: 0.9rem;
        }
        
        #message-input:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        
        .btn-primary {
            background: #3498db;
            border: none;
            border-radius: 1.5rem;
            padding: 0.5rem 1rem;
            font-weight: 600;
            transition: all 0.2s;
            font-size: 0.9rem;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }
        
        .back-button {
            color: #2c3e50;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            text-decoration: none; /* Remove underline */
        }
        
        .back-button:hover {
            background: #f8f9fa;
            transform: translateX(-2px);
            color: #3498db;
            text-decoration: none; /* Ensure no underline on hover */
        }
        
        #messages-container {
            padding: 20px;
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        /* Custom scrollbar */
        #messages-container::-webkit-scrollbar {
            width: 8px;
        }
        
        #messages-container::-webkit-scrollbar-track {
            background: #f1f3f9;
            border-radius: 4px;
        }
        
        #messages-container::-webkit-scrollbar-thumb {
            background: #d1d3e2;
            border-radius: 4px;
        }
        
        #messages-container::-webkit-scrollbar-thumb:hover {
            background: #b7b9cc;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .message-bubble {
                max-width: 85%;
            }
            
            .container {
                padding: 10px;
                height: calc(100vh - 70px); /* Adjust for mobile navbar */
            }
            
            body {
                padding-top: 0;
                overflow: hidden;
            }
            
            .page-header h1 {
                font-size: 1.25rem;
            }
            
            .back-button {
                width: 36px;
                height: 36px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/supervisor_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <div class="d-flex align-items-center">
                <a href="messages.php" class="back-button me-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1><i class="fas fa-comments me-2"></i> <?php echo $pageTitle; ?></h1>
                    <?php if ($senderRole): ?>
                        <p class="text-muted mb-0"><?php echo ucfirst($senderRole); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="content-card">
            <?php if ($error): ?>
                <div class="alert alert-danger m-3">
                    <?php echo htmlspecialchars($error); ?>
                    <a href="messages.php" class="btn btn-sm btn-outline-secondary ms-3">Back to Messages</a>
                </div>
            <?php else: ?>
                <div id="messages-container">
                    <div class="text-center py-5 text-muted" id="loading-messages">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading messages...</p>
                    </div>
                </div>
                
                <div class="message-input-container">
                    <form id="message-form" class="d-flex">
                        <input type="hidden" id="sender-id" value="<?php echo htmlspecialchars($senderId); ?>">
                        <input type="hidden" id="sender-role" value="<?php echo htmlspecialchars($senderRole); ?>">
                        <input type="text" id="message-input" class="form-control me-2" 
                               placeholder="Type your message..." required autofocus>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i> Send
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Loading Indicator -->
    <div id="loading-indicator" class="position-fixed top-50 start-50 translate-middle" style="display: none; z-index: 9999;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const messagesContainer = document.getElementById('messages-container');
            const messageForm = document.getElementById('message-form');
            const messageInput = document.getElementById('message-input');
            const loadingIndicator = document.getElementById('loading-indicator');
            const loadingMessages = document.getElementById('loading-messages');
            const senderId = document.getElementById('sender-id').value;
            const currentUserId = '<?php echo $current_user_id; ?>';
            
            // Load conversation
            async function loadConversation() {
                try {
                    const response = await fetch(`get_chat_messages.php?user_id=${encodeURIComponent(senderId)}`);
                    const data = await response.json();
                    
                    if (data.error) {
                        showToast(data.error, 'error');
                        return;
                    }
                    
                    // Clear loading message
                    if (loadingMessages) {
                        loadingMessages.remove();
                    }
                    
                    if (data.messages && data.messages.length > 0) {
                        messagesContainer.innerHTML = '';
                        
                        data.messages.forEach(message => {
                            const isSent = message.sender_id === currentUserId;
                            const messageTime = new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                            
                            const messageElement = document.createElement('div');
                            messageElement.className = `message-bubble ${isSent ? 'sent' : 'received'}`;
                            messageElement.innerHTML = `
                                <div class="message-content">${message.message}</div>
                                <div class="message-time ${isSent ? 'sent' : 'received'}">${messageTime}</div>
                            `;
                            
                            messagesContainer.appendChild(messageElement);
                        });
                        
                        // Scroll to bottom
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    } else {
                        messagesContainer.innerHTML = `
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-comment-slash fa-3x mb-3"></i>
                                <h3>No messages yet</h3>
                                <p>Start the conversation by sending a message!</p>
                            </div>
                        `;
                    }
                } catch (error) {
                    console.error('Error loading messages:', error);
                    if (loadingMessages) {
                        loadingMessages.innerHTML = `
                            <div class="text-center py-5 text-danger">
                                <i class="fas fa-exclamation-circle fa-3x mb-3"></i>
                                <h3>Error loading messages</h3>
                                <p>Please try again later.</p>
                            </div>
                        `;
                    }
                    showToast('Error loading messages. Please try again.', 'error');
                }
            }
            
            // Send message
            messageForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const message = messageInput.value.trim();
                if (!message) return;
                
                const formData = new FormData();
                formData.append('message', message);
                formData.append('recipient_id', senderId);
                formData.append('recipient_type', 'individual');
                
                try {
                    loadingIndicator.style.display = 'block';
                    
                    const response = await fetch('send_chat_message.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        messageInput.value = '';
                        await loadConversation();
                        showToast('Message sent successfully', 'success');
                    } else {
                        showToast(result.error || 'Failed to send message', 'error');
                    }
                } catch (error) {
                    console.error('Error sending message:', error);
                    showToast('Error sending message. Please try again.', 'error');
                } finally {
                    loadingIndicator.style.display = 'none';
                }
            });
            
            // Show toast notification
            function showToast(message, type = 'info') {
                const toast = document.createElement('div');
                // Use dark shades of green and red as per user preference
                const bgColor = type === 'error' ? '#c62828' : '#2e7d32';
                
                toast.className = 'toast show align-items-center text-white border-0 position-fixed top-0 end-0 m-3';
                toast.role = 'alert';
                toast.setAttribute('aria-live', 'assertive');
                toast.setAttribute('aria-atomic', 'true');
                toast.style.zIndex = '9999';
                toast.style.backgroundColor = bgColor; // Apply the specific color from user preference
                
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                `;
                
                document.body.appendChild(toast);
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    toast.remove();
                }, 5000);
            }
            
            // Load initial messages
            loadConversation();
            
            // Optional: Poll for new messages every 10 seconds
            setInterval(loadConversation, 10000);
        });
    </script>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
