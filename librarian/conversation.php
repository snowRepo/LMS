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

// Check if user is logged in and has librarian role
if (!is_logged_in() || $_SESSION['user_role'] !== 'librarian') {
    header('Location: ../login.php');
    exit();
}

// Check subscription status
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
$senderName = '';  // Initialize senderName variable

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
    <link rel="stylesheet" href="css/toast.css">
    <style>
        :root {
            --primary-color: #3498db;
            --primary-dark: #2980b9;
            --secondary-color: #f0f0f0;
            --success-color: #27ae60;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --info-color: #3498db;
            --light-color: #f8f9fa;
            --dark-color: #2c3e50;
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
            background: linear-gradient(135deg, var(--gray-100) 0%, #e9ecef 100%);
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 0;
            overflow: hidden; /* Prevent body scrolling */
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            height: 100vh; /* Full viewport height */
            display: flex;
            flex-direction: column;
        }

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
            flex-shrink: 0; /* Prevent header from shrinking */
        }

        .page-header h1 {
            color: var(--gray-900);
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: var(--gray-600);
            font-size: 1.1rem;
        }

        .content-card {
            background: #ffffff;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 2rem;
            margin-bottom: 2rem;
            border: none;
        }

        .conversation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .conversation-header h2 {
            color: var(--gray-900);
            margin: 0;
            font-size: 1.5rem;
        }
        
        .message-bubble {
            max-width: 80%;
            padding: 16px 20px;
            border-radius: 18px;
            margin-bottom: 16px;
            position: relative;
            word-wrap: break-word;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            transition: var(--transition);
        }
        
        .message-bubble:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        
        .message-bubble.sent {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 4px;
        }
        
        .message-bubble.received {
            background-color: var(--gray-200);
            color: var(--gray-800);
            margin-right: auto;
            border-bottom-left-radius: 4px;
        }
        
        .message-content {
            font-size: 1rem;
            line-height: 1.5;
        }
        
        .message-time {
            font-size: 0.75rem;
            opacity: 0.9;
            text-align: right;
            margin-top: 8px;
            font-weight: 500;
        }
        
        #messages-container {
            display: flex;
            flex-direction: column;
            gap: 16px;
            padding: 24px;
            height: calc(100vh - 350px); /* Adjusted height */
            overflow-y: auto;
            background-color: #f8f9fa;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid var(--gray-200);
            flex-grow: 1; /* Allow messages container to grow */
        }
        
        .message-input-container {
            position: relative;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--box-shadow);
            border: 1px solid var(--gray-200);
            flex-shrink: 0; /* Prevent input area from shrinking */
        }
        
        .back-button {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(52, 152, 219, 0.4);
            color: white;
        }
        
        .back-button:active {
            transform: translateY(0);
        }
        
        .form-control {
            border: 2px solid var(--gray-300);
            border-radius: 8px;
            padding: 12px 16px;
            transition: var(--transition);
            font-size: 1rem;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border: none;
            padding: 12px 20px;
            font-weight: 600;
            border-radius: 8px;
            transition: var(--transition);
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(52, 152, 219, 0.4);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .loading-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            color: var(--gray-600);
        }
        
        .spinner-border {
            width: 3rem;
            height: 3rem;
            border-width: 0.25em;
        }
        
        #loading-indicator {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
        }
        
        .alert {
            border-radius: 8px;
            box-shadow: var(--box-shadow);
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .content-card {
                padding: 1.5rem;
            }
            
            #messages-container {
                height: calc(100vh - 300px);
                padding: 16px;
            }
            
            .message-bubble {
                max-width: 90%;
                padding: 12px 16px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/librarian_navbar.php'; ?>
    
    <!-- Toast Notification Container -->
    <div id="toast-container"></div>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-comments me-2"></i> Conversation</h1>
            <p>Chat with your colleagues</p>
        </div>
        
        <div class="content-card">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <a href="messages.php" class="back-button">
                    <i class="fas fa-arrow-left"></i> Back to Messages
                </a>
            <?php else: ?>
                <div class="conversation-header">
                    <h2>Conversation with <?php echo htmlspecialchars($senderName); ?></h2>
                    <a href="messages.php" class="back-button">
                        <i class="fas fa-arrow-left"></i> Back to Messages
                    </a>
                </div>
                
                <div id="messages-container">
                    <!-- Messages will be loaded here via JavaScript -->
                    <div class="loading-container" id="loading-messages">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3">Loading messages...</p>
                    </div>
                </div>
                
                <div class="message-input-container">
                    <form id="message-form" class="d-flex gap-2">
                        <input type="hidden" id="sender-id" value="<?php echo htmlspecialchars($senderId); ?>">
                        <input type="hidden" id="sender-role" value="<?php echo htmlspecialchars($senderRole); ?>">
                        <input type="text" id="message-input" class="form-control flex-grow-1" 
                               placeholder="Type your message..." required autofocus>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send
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

    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toast Notification Functions
        function showToast(message, type = 'info') {
            // Create toast container if it doesn't exist
            let toastContainer = document.getElementById('toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toast-container';
                document.body.appendChild(toastContainer);
            }
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            // Set icon based on type
            let iconClass = 'fa-info-circle';
            if (type === 'success') iconClass = 'fa-check-circle';
            else if (type === 'error') iconClass = 'fa-exclamation-circle';
            else if (type === 'warning') iconClass = 'fa-exclamation-triangle';
            
            toast.innerHTML = `
                <div class="toast-content">
                    <div class="toast-icon">
                        <i class="fas ${iconClass}"></i>
                    </div>
                    <div class="toast-message">${message}</div>
                    <button class="toast-close">&times;</button>
                </div>
            `;
            
            // Add toast to container
            toastContainer.appendChild(toast);
            
            // Show toast with animation
            setTimeout(() => {
                toast.classList.add('show');
            }, 10);
            
            // Add close button event listener
            const closeBtn = toast.querySelector('.toast-close');
            closeBtn.addEventListener('click', () => {
                toast.classList.remove('show');
                toast.classList.add('hide');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            });
            
            // Auto hide toast after 5 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.classList.remove('show');
                    toast.classList.add('hide');
                    setTimeout(() => {
                        if (toast.parentNode) {
                            toast.parentNode.removeChild(toast);
                        }
                    }, 300);
                }
            }, 5000);
        }
    </script>
    
    <!-- Conversation JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const messagesContainer = document.getElementById('messages-container');
        const messageForm = document.getElementById('message-form');
        const messageInput = document.getElementById('message-input');
        const loadingIndicator = document.getElementById('loading-indicator');
        const loadingMessages = document.getElementById('loading-messages');
        const senderId = document.getElementById('sender-id')?.value;
        
        if (!senderId) {
            return;
        }

        // Load messages
        async function loadMessages() {
            try {
                showLoading(true);
                const response = await fetch(`get_conversation.php?sender_id=${encodeURIComponent(senderId)}`);
                
                if (!response.ok) {
                    throw new Error('Failed to load messages');
                }
                
                const data = await response.json();
                
                if (data.error) {
                    throw new Error(data.error);
                }
                
                displayMessages(data.messages || []);
                
            } catch (error) {
                console.error('Error loading messages:', error);
                showToast('Error loading messages: ' + error.message, 'error');
            } finally {
                showLoading(false);
                if (loadingMessages) loadingMessages.remove();
            }
        }
        
        // Display messages in the container
        function displayMessages(messages) {
            messagesContainer.innerHTML = '';
            
            if (messages.length === 0) {
                const noMessagesDiv = document.createElement('div');
                noMessagesDiv.className = 'text-center py-5 text-muted';
                noMessagesDiv.innerHTML = `
                    <i class="fas fa-comments fa-3x mb-3"></i>
                    <h4>No messages yet</h4>
                    <p class="mb-0">Start the conversation by sending a message</p>
                `;
                messagesContainer.appendChild(noMessagesDiv);
                return;
            }
            
            messages.forEach(msg => {
                const messageDiv = document.createElement('div');
                const isSent = msg.is_sender;
                const time = new Date(msg.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                
                messageDiv.className = `message-bubble ${isSent ? 'sent' : 'received'}`;
                messageDiv.innerHTML = `
                    <div class="message-content">${msg.message}</div>
                    <div class="message-time">${time}</div>
                `;
                
                messagesContainer.appendChild(messageDiv);
            });
            
            // Scroll to bottom
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        
        // Handle message form submission
        if (messageForm) {
            messageForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const message = messageInput.value.trim();
                if (!message) return;
                
                try {
                    showLoading(true);
                    const formData = new FormData();
                    
                    // Use the recipient's role from the hidden input
                    const recipientRole = document.getElementById('sender-role').value;
                    
                    // Set the correct recipient type based on the role
                    const recipientType = recipientRole === 'member' ? 'individual_member' : 
                                        recipientRole === 'librarian' ? 'individual_librarian' : 
                                        recipientRole === 'supervisor' ? 'supervisor' : recipientRole;
                    formData.append('recipient_type', recipientType);
                    formData.append('individual_recipient', senderId);
                    formData.append('subject', 'New message');
                    formData.append('message_body', message);
                    
                    const response = await fetch('send_message.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (!response.ok) {
                        const errorData = await response.json();
                        throw new Error(errorData.error || 'Failed to send message');
                    }
                    
                    const data = await response.json();
                    
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    
                    // Clear input and reload messages
                    messageInput.value = '';
                    await loadMessages();
                    
                    showToast('Message sent!', 'success');
                    
                } catch (error) {
                    console.error('Error sending message:', error);
                    showToast('Error: ' + error.message, 'error');
                } finally {
                    showLoading(false);
                    messageInput.focus();
                }
            });
        }
        
        // Show loading indicator
        function showLoading(show) {
            if (loadingIndicator) {
                loadingIndicator.style.display = show ? 'block' : 'none';
            }
        }
        
        // Load initial messages
        loadMessages();
    });
    </script>
</body>
</html>