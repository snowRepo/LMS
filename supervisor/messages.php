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

try {
    $db = Database::getInstance()->getConnection();
    
    // Get current user's string ID
    $stmt = $db->prepare("SELECT user_id, first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    $user_string_id = $user['user_id'];
    
    // First, get the latest message ID for each conversation (both as sender and recipient)
    $latestMessagesQuery = "
        SELECT MAX(id) as latest_message_id
        FROM messages
        WHERE sender_id = :user_id1 OR recipient_id = :user_id2
        GROUP BY 
            CASE 
                WHEN sender_id = :user_id3 THEN recipient_id
                ELSE sender_id
            END
    ";
    
    $stmt = $db->prepare($latestMessagesQuery);
    $stmt->execute([
        ':user_id1' => $user_string_id,
        ':user_id2' => $user_string_id,
        ':user_id3' => $user_string_id
    ]);
    $latestMessageIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'latest_message_id');
    
    if (empty($latestMessageIds)) {
        $conversations = [];
    } else {
        // Now get the full message details for these latest messages
        $placeholders = rtrim(str_repeat('?,', count($latestMessageIds)), ',');
        $query = "
            SELECT 
                m.id as message_id,
                CASE 
                    WHEN m.sender_id = ? THEN m.recipient_id
                    ELSE m.sender_id
                END as other_user_id,
                u.first_name,
                u.last_name,
                m.subject,
                m.message as last_message,
                m.created_at as last_message_time,
                m.sender_id = ? as is_sender,
                (
                    SELECT COUNT(*) 
                    FROM messages m2 
                    WHERE 
                        m2.sender_id = CASE 
                            WHEN m.sender_id = ? THEN m.recipient_id 
                            ELSE m.sender_id 
                        END 
                        AND m2.recipient_id = ?
                        AND m2.is_read = 0
                ) as unread_count
            FROM 
                messages m
                INNER JOIN users u ON u.user_id = CASE 
                    WHEN m.sender_id = ? THEN m.recipient_id
                    ELSE m.sender_id
                END
            WHERE m.id IN ($placeholders)
            ORDER BY m.created_at DESC
        ";
        
        $params = array_merge(
            [$user_string_id, $user_string_id, $user_string_id, $user_string_id, $user_string_id],
            $latestMessageIds
        );
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Count total unread messages
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM messages WHERE recipient_id = ? AND is_read = 0 AND is_deleted = 0");
    $stmt->execute([$user_string_id]);
    $unreadCount = $stmt->fetch()['unread_count'];
    
} catch (Exception $e) {
    $error = 'Error loading messages: ' . $e->getMessage();
}

$pageTitle = 'Messages';
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
    
    <!-- Toast Notifications CSS -->
    <link rel="stylesheet" href="css/toast.css">
    
    <style>
        :root {
            --primary-color: #3498DB;
            --primary-dark: #2980B9;
            --secondary-color: #f8f9fa;
            --success-color: #2ECC71;
            --danger-color: #e74c3c;
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
            background: linear-gradient(135deg, var(--gray-100) 0%, #e9ecef 100%);
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

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
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
        
        .card-header .btn {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        .message-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 2rem;
        }

        .message-sidebar {
            background: var(--gray-100);
            border-radius: var(--border-radius);
            padding: 1.5rem;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li {
            margin-bottom: 0.5rem;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.75rem 1rem;
            text-decoration: none;
            color: var(--gray-700);
            border-radius: 8px;
            transition: var(--transition);
            font-weight: 500;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: var(--gray-200);
            color: var(--gray-900);
        }

        .compose-link {
            background: var(--primary-color);
            color: white !important;
            font-weight: 600;
        }

        /* Removed hover effect that was causing fade out */
        .compose-link:hover {
            background: var(--primary-color);
            color: white !important;
        }

        .compose-link.active {
            background: var(--primary-dark);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.25);
        }

        .message-count {
            background: var(--primary-color);
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0;
            border-radius: 50%;
            min-width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            text-align: center;
        }

        /* Modal Styles - Made more compact */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .modal-header h2 {
            margin: 0;
            color: var(--gray-900);
            font-size: 1.25rem;
        }

        .close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray-500);
            transition: color 0.3s;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close:hover {
            color: var(--gray-700);
            background-color: var(--gray-200);
        }

        .modal-body {
            padding: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.3rem;
            font-weight: 500;
            color: var(--gray-700);
            font-size: 0.9rem;
        }

        .form-group label.required::after {
            content: " *";
            color: var(--danger-color);
        }

        .form-control {
            width: 100%;
            padding: 0.5rem;
            border: 2px solid var(--gray-300);
            border-radius: 6px;
            font-size: 0.9rem;
            transition: border-color 0.3s;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            box-sizing: border-box;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.25);
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            padding: 1rem;
            border-top: 1px solid var(--gray-200);
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-secondary {
            background-color: var(--gray-300);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background-color: var(--gray-400);
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        /* Chat Modal Styles */
        .chat-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .chat-modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            height: 80vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            animation: modalFadeIn 0.3s ease;
        }

        .chat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .chat-header h2 {
            margin: 0;
            color: var(--gray-900);
            font-size: 1.5rem;
        }

        .chat-body {
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .chat-messages {
            flex: 1;
            padding: 1.5rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .chat-message {
            max-width: 80%;
            padding: 1rem;
            border-radius: 12px;
            position: relative;
        }

        .chat-message.received {
            align-self: flex-start;
            background-color: var(--gray-200);
            color: var(--gray-800);
        }

        .chat-message.sent {
            align-self: flex-end;
            background-color: var(--primary-color);
            color: white;
        }

        .chat-message .message-content {
            margin-bottom: 0.5rem;
            line-height: 1.5;
        }

        .chat-message .message-time {
            font-size: 0.75rem;
            opacity: 0.8;
            text-align: right;
        }

        .chat-footer {
            display: flex;
            padding: 1rem;
            border-top: 1px solid var(--gray-200);
            gap: 0.5rem;
            background-color: #f8f9fa;
            border-radius: 0 0 12px 12px;
        }

        .chat-input {
            flex: 1;
            padding: 0.75rem;
            border: 2px solid var(--gray-300);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            box-sizing: border-box;
        }

        .chat-input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.25);
        }

        .chat-send-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .chat-send-btn:hover {
            background-color: var(--primary-dark);
        }

        .message-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .message-item {
            padding: 1.25rem;
            border-bottom: 1px solid var(--gray-200);
            transition: var(--transition);
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .message-item:hover {
            background: var(--gray-100);
        }

        .message-item.unread {
            background: rgba(52, 152, 219, 0.05);
            border-left: 3px solid var(--primary-color);
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .message-sender {
            font-weight: 600;
            color: var(--gray-900);
        }

        .message-time {
            font-size: 0.85rem;
            color: var(--gray-600);
        }

        .message-subject {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--gray-800);
        }

        .message-preview {
            color: var(--gray-600);
            font-size: 0.9rem;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .no-messages {
            text-align: center;
            padding: 3rem;
            color: var(--gray-600);
        }

        .no-messages i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--gray-400);
        }

        /* Message actions */
        .message-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .message-action-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            padding: 0.25rem;
            border-radius: 4px;
            transition: all 0.3s ease;
            color: var(--gray-500);
        }
        
        .message-action-btn:hover {
            background-color: var(--gray-200);
            color: var(--gray-700);
        }
        
        .message-action-btn.starred {
            color: #F39C12;
        }
        
        .message-action-btn.read {
            color: #2ECC71;
        }
        
        .unread-badge {
            font-size: 0.7rem;
            vertical-align: middle;
        }
        
        .last-message {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }
        
        @media (max-width: 768px) {
            .message-container {
                grid-template-columns: 1fr;
            }
            
            .message-sidebar {
                margin-bottom: 1rem;
            }
            
            .modal-content {
                max-width: 95%;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/supervisor_navbar.php'; ?>
    
    <!-- Toast Notification Container -->
    <div id="toast-container"></div>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-envelope"></i> Messages</h1>
            <p>Manage your communications</p>
        </div>
        
        <div class="content-card">
            <div class="message-container">
                <div class="message-sidebar">
                    <ul class="sidebar-menu">
                        <li>
                            <a href="#" class="compose-link" id="composeBtn">
                                <i class="fas fa-plus"></i>
                                Compose
                            </a>
                        </li>
                        <li>
                            <a href="#" class="active" data-view="conversations">
                                <i class="fas fa-comments"></i>
                                Conversations
                                <?php if ($unreadCount > 0): ?>
                                    <span class="message-count"><?php echo $unreadCount; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="message-content">
                    <div class="card-header">
                        <h2>Conversations</h2>
                    </div>
                    
                    <!-- Conversations View -->
                    <div class="message-view" id="conversations-view" style="display: block;">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($conversations)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($conversations as $conv): ?>
                                    <a href="conversation.php?id=<?php echo urlencode($conv['message_id']); ?>&sender_id=<?php echo urlencode($conv['other_user_id']); ?>" 
                                       class="list-group-item list-group-item-action message-item <?php echo $conv['unread_count'] > 0 ? 'unread' : ''; ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="me-3">
                                                <div class="fw-bold">
                                                    <?php echo htmlspecialchars($conv['first_name'] . ' ' . $conv['last_name']); ?>
                                                    <?php if ($conv['unread_count'] > 0): ?>
                                                        <span class="badge bg-primary rounded-pill unread-badge">
                                                            <?php echo $conv['unread_count']; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="last-message text-muted" title="<?php echo htmlspecialchars($conv['last_message']); ?>">
                                                    <strong><?php echo htmlspecialchars($conv['subject']); ?></strong>: 
                                                    <?php 
                                                    $preview = htmlspecialchars(substr($conv['last_message'], 0, 60));
                                                    echo $preview . (strlen($preview) < strlen($conv['last_message']) ? '...' : '');
                                                    ?>
                                                </div>
                                            </div>
                                            <div class="text-muted small text-nowrap">
                                                <?php echo date('M j, g:i a', strtotime($conv['last_message_time'])); ?>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-messages">
                                <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                <h3>No Conversations</h3>
                                <p>You don't have any conversations yet. Use the Compose button on the left to start a conversation.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Store current user ID and role for chat functionality
        window.currentUserId = <?php echo json_encode($user_string_id ?? ''); ?>;
        window.currentUserName = <?php 
            $name = (isset($_SESSION['first_name']) ? trim($_SESSION['first_name']) . ' ' : '') . 
                   (isset($_SESSION['last_name']) ? trim($_SESSION['last_name']) : '');
            echo json_encode(trim($name) ?: 'User'); 
        ?>;
        window.currentUserRole = <?php echo json_encode($_SESSION['user_role'] ?? 'user'); ?>;
        
        // Function to update the inbox count
        function updateInboxCount() {
            // Fetch the new unread count
            fetch('ajax_message_count.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.error) {
                        // Update the conversations count in the left pane
                        const conversationsLink = document.querySelector('.sidebar-menu a[data-view="conversations"]');
                        if (conversationsLink) {
                            let badge = conversationsLink.querySelector('.message-count');
                            
                            if (data.unread_count > 0) {
                                if (badge) {
                                    badge.textContent = data.unread_count;
                                } else {
                                    // Create badge if it doesn't exist
                                    badge = document.createElement('span');
                                    badge.className = 'message-count';
                                    badge.textContent = data.unread_count;
                                    conversationsLink.appendChild(badge);
                                }
                            } else {
                                // Remove badge if count is 0
                                if (badge) {
                                    badge.remove();
                                }
                            }
                        }
                        
                        // Also update the navbar badge
                        const messagesLink = document.querySelector('.nav-link[href="messages.php"]');
                        if (messagesLink) {
                            let badge = messagesLink.querySelector('.notification-badge');
                            
                            if (data.unread_count > 0) {
                                if (badge) {
                                    badge.textContent = data.unread_count;
                                } else {
                                    // Create badge if it doesn't exist
                                    badge = document.createElement('span');
                                    badge.className = 'notification-badge';
                                    badge.textContent = data.unread_count;
                                    messagesLink.appendChild(badge);
                                }
                            } else {
                                // Remove badge if count is 0
                                if (badge) {
                                    badge.remove();
                                }
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Error updating inbox count:', error);
                });
        }
        
        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listener for compose button
            document.getElementById('composeBtn').addEventListener('click', function(e) {
                e.preventDefault();
                // Trigger the compose functionality from messages.js
                if (typeof initMessagesPage !== 'undefined') {
                    // If messages.js is loaded, trigger compose
                    const composeEvent = new Event('composeClick');
                    document.dispatchEvent(composeEvent);
                }
            });
            
            // Update notification badges periodically
            setInterval(updateInboxCount, 30000);
        });
        
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
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Messages JavaScript -->
    <script src="../js/messages.js"></script>
</body>
</html>