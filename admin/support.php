<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';
require_once '../includes/EmailService.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has admin role
if (!is_logged_in() || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$pageTitle = 'Support';

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read_id'])) {
    $messageId = intval($_POST['mark_read_id']);
    
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("UPDATE support_messages SET status = 'in_progress' WHERE id = ?");
        $stmt->execute([$messageId]);
        
        // Set success message in session for toast notification
        $_SESSION['toast_success'] = "Message marked as read.";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $e) {
        // Set error message in session for toast notification
        $_SESSION['toast_error'] = "Failed to update message status.";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message_id'])) {
    $messageId = intval($_POST['reply_message_id']);
    $reply = sanitize_input($_POST['admin_reply']);
    
    if (!empty($reply)) {
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            // Get the original message details
            $stmt = $conn->prepare("SELECT * FROM support_messages WHERE id = ?");
            $stmt->execute([$messageId]);
            $message = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($message) {
                // Update message with admin reply
                $stmt = $conn->prepare("UPDATE support_messages SET admin_reply = ?, status = 'resolved' WHERE id = ?");
                $stmt->execute([$reply, $messageId]);
                
                // Try to send email reply
                try {
                    $emailService = new EmailService();
                    if ($emailService->isConfigured()) {
                        $emailService->sendSupportReply(
                            $message['email'],
                            $message['name'],
                            $message['subject'],
                            $message['message'],
                            $reply
                        );
                        $_SESSION['toast_success'] = "Reply sent successfully! An email has been sent to the user.";
                    } else {
                        $_SESSION['toast_success'] = "Reply saved successfully! (Email not sent - service not configured)";
                    }
                } catch (Exception $e) {
                    // Email failed but message was saved
                    $_SESSION['toast_success'] = "Reply saved successfully! (Email delivery failed: " . $e->getMessage() . ")";
                }
                
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $_SESSION['toast_error'] = "Message not found.";
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
        } catch (Exception $e) {
            $_SESSION['toast_error'] = "Failed to send reply. Please try again.";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } else {
        $_SESSION['toast_error'] = "Reply message cannot be empty.";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Handle mark as read when viewing message (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['view_message_id'])) {
    $messageId = intval($_POST['view_message_id']);
    
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Only update status if it's still 'open'
        $stmt = $conn->prepare("UPDATE support_messages SET status = 'in_progress' WHERE id = ? AND status = 'open'");
        $stmt->execute([$messageId]);
        
        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false]);
        exit;
    }
}

// Fetch support messages
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM support_messages ORDER BY created_at DESC");
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $errorMessage = "Failed to fetch support messages.";
    $messages = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - LMS</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Toast CSS -->
    <link rel="stylesheet" href="css/toast.css">
    
    <style>
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
            color: #2c3e50; /* Reverted to original color */
            font-size: 1.8rem;
            margin: 0 0 10px 0;
        }

        .page-header p {
            color: #6c757d;
            font-size: 1.1rem;
            margin: 0;
        }

        .content-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 30px;
            margin-bottom: 20px;
        }

        .content-card h2 {
            color: #0066cc; /* Updated to match admin deep blue color */
            margin-bottom: 20px;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            justify-content: center;
        }

        .messages-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .message-card {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            background: #ffffff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .message-info {
            flex: 1;
            min-width: 200px;
        }

        .message-sender {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .message-subject {
            font-weight: 500;
            margin-bottom: 5px;
            color: #0066cc; /* Updated to match admin deep blue color */
        }

        .message-date {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .message-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .message-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-open {
            background: #fff3cd;
            color: #856404;
        }

        .status-in_progress {
            background: #cce5ff;
            color: #004085;
        }

        .status-resolved {
            background: #d4edda;
            color: #1b5e20; /* Updated to user's preferred deep green color */
        }

        .status-closed {
            background: #f8d7da;
            color: #c62828; /* Updated to user's preferred deep red color */
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #0066cc; /* Updated to match admin deep blue color */
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #0056b3; /* Darker shade for hover effect */
        }

        .btn-outline {
            background: transparent;
            color: #0066cc; /* Updated to match admin deep blue color */
            border: 1px solid #0066cc; /* Updated to match admin deep blue color */
        }

        .btn-outline:hover {
            background: #0066cc; /* Updated to match admin deep blue color */
            color: #ffffff;
        }

        .btn-success {
            background: #28a745;
            color: #ffffff;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .no-messages {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .no-messages i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #ced4da;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow: auto;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 0;
            border: none;
            border-radius: 12px;
            width: 90%;
            max-width: 700px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            padding: 20px;
            background: #0066cc; /* Updated to match admin deep blue color */
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
        }

        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
        }

        .close:hover {
            color: #f1f1f1;
        }

        .modal-body {
            padding: 20px;
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden; /* Prevent horizontal scrolling */
            max-width: 100%; /* Ensure content doesn't exceed container */
        }

        .message-details {
            margin-bottom: 20px;
        }

        .detail-row {
            display: flex;
            margin-bottom: 10px;
            flex-wrap: wrap; /* Allow wrapping on small screens */
        }

        .detail-label {
            font-weight: 600;
            min-width: 100px; /* Fixed minimum width */
            color: #2c3e50;
        }

        .detail-value {
            flex: 1;
            color: #495057;
            word-break: break-word; /* Prevent overflow */
            min-width: 0; /* Allow shrinking */
        }

        .message-content-view {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
            border-left: 3px solid #0066cc; /* Updated to match admin deep blue color */
            overflow-x: auto; /* Prevent horizontal overflow */
            max-width: 100%; /* Ensure content doesn't exceed container */
        }

        .reply-content-view {
            background: #e3f2fd;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
            border-left: 3px solid #0066cc; /* Updated to match admin deep blue color */
            overflow-x: auto; /* Prevent horizontal overflow */
            max-width: 100%; /* Ensure content doesn't exceed container */
        }

        .modal-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .reply-form-modal {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 1rem;
            min-height: 120px;
            resize: vertical;
            box-sizing: border-box; /* Include padding in width calculation */
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #3498DB;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.25);
        }
    </style>
</head>

<body>
    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/toast_notifications.php'; ?>
    
    <!-- Display toast notifications from session -->
    <?php if (isset($_SESSION['toast_success'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                showToast('<?php echo addslashes($_SESSION['toast_success']); ?>', 'success');
            });
        </script>
        <?php unset($_SESSION['toast_success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['toast_error'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                showToast('<?php echo addslashes($_SESSION['toast_error']); ?>', 'error');
            });
        </script>
        <?php unset($_SESSION['toast_error']); ?>
    <?php endif; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-headset"></i> <?php echo $pageTitle; ?></h1>
            <p>Manage support messages from users</p>
        </div>
        
        <div class="content-card">
            <h2><i class="fas fa-inbox"></i> Support Messages</h2>
            
            <?php if (empty($messages)): ?>
                <div class="no-messages">
                    <i class="fas fa-inbox"></i>
                    <h3>No Support Messages</h3>
                    <p>There are currently no support messages from users.</p>
                </div>
            <?php else: ?>
                <div class="messages-list">
                    <?php foreach ($messages as $message): ?>
                        <div class="message-card">
                            <div class="message-info">
                                <div class="message-sender"><?php echo htmlspecialchars($message['name']); ?> 
                                    <span class="message-status status-<?php echo $message['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $message['status'])); ?>
                                    </span>
                                </div>
                                <div class="message-subject"><?php echo htmlspecialchars($message['subject']); ?></div>
                                <div class="message-date"><?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?></div>
                            </div>
                            
                            <div class="message-actions">
                                <button class="btn btn-outline btn-sm view-message" data-message-id="<?php echo $message['id']; ?>">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                
                                <?php if ($message['status'] === 'open'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="mark_read_id" value="<?php echo $message['id']; ?>">
                                        <button type="submit" class="btn btn-success btn-sm">
                                            <i class="fas fa-check"></i> Mark Read
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Message Modal -->
    <div id="messageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-envelope"></i> Support Message</h2>
                <button class="close">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Message content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline close-modal">Close</button>
            </div>
        </div>
    </div>

    <script src="js/toast.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal elements
            const modal = document.getElementById('messageModal');
            const modalBody = document.getElementById('modalBody');
            const closeButtons = document.querySelectorAll('.close, .close-modal');
            const viewButtons = document.querySelectorAll('.view-message');
            
            // Close modal
            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    modal.style.display = 'none';
                });
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
            
            // View message
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const messageId = this.getAttribute('data-message-id');
                    
                    // Find the message data
                    const messages = <?php echo json_encode($messages); ?>;
                    const message = messages.find(msg => msg.id == messageId);
                    
                    if (message) {
                        // Build modal content
                        let modalContent = `
                            <div class="message-details">
                                <div class="detail-row">
                                    <div class="detail-label">From:</div>
                                    <div class="detail-value">${message.name} &lt;${message.email}&gt;</div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Subject:</div>
                                    <div class="detail-value">${message.subject}</div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Date:</div>
                                    <div class="detail-value">${new Date(message.created_at).toLocaleString()}</div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">Status:</div>
                                    <div class="detail-value">
                                        <span class="message-status status-${message.status}">
                                            ${message.status.replace('_', ' ').charAt(0).toUpperCase() + message.status.replace('_', ' ').slice(1)}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="message-content-view">
                                <h4>Message:</h4>
                                <p>${message.message.replace(/\n/g, '<br>')}</p>
                            </div>
                        `;
                        
                        // Add reply content if exists
                        if (message.admin_reply) {
                            modalContent += `
                                <div class="reply-content-view">
                                    <h4>Admin Reply:</h4>
                                    <p>${message.admin_reply.replace(/\n/g, '<br>')}</p>
                                </div>
                            `;
                        } else {
                            // Add reply form if no reply yet
                            modalContent += `
                                <div class="reply-form-modal">
                                    <h4>Reply to Message:</h4>
                                    <form method="POST" class="reply-form">
                                        <input type="hidden" name="reply_message_id" value="${message.id}">
                                        <div class="form-group">
                                            <textarea name="admin_reply" placeholder="Type your reply here..." required></textarea>
                                        </div>
                                        <div style="display: flex; gap: 10px; justify-content: flex-end;">
                                            <button type="submit" class="btn btn-primary send-reply-btn">
                                                <i class="fas fa-paper-plane"></i> Send Reply
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            `;
                        }
                        
                        modalBody.innerHTML = modalContent;
                        modal.style.display = 'block';
                        
                        // If the message is 'open', mark it as 'in_progress' via AJAX
                        if (message.status === 'open') {
                            // Send AJAX request to update status
                            fetch('', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: 'view_message_id=' + messageId
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    // Update the status display in the main list
                                    const messageCards = document.querySelectorAll('.message-card');
                                    messageCards.forEach(card => {
                                        if (card.querySelector('.view-message').getAttribute('data-message-id') === messageId) {
                                            const statusBadge = card.querySelector('.message-status');
                                            if (statusBadge) {
                                                statusBadge.className = 'message-status status-in_progress';
                                                statusBadge.textContent = 'In Progress';
                                            }
                                            // Remove the "Mark Read" button
                                            const markReadButton = card.querySelector('form');
                                            if (markReadButton) {
                                                markReadButton.remove();
                                            }
                                        }
                                    });
                                    
                                    // Update the status in the modal
                                    const modalStatus = document.querySelector('.modal .message-status');
                                    if (modalStatus) {
                                        modalStatus.className = 'message-status status-in_progress';
                                        modalStatus.textContent = 'In Progress';
                                    }
                                }
                            });
                        }
                        
                        // Add event listener for reply form submission
                        const replyForm = modalBody.querySelector('.reply-form');
                        if (replyForm) {
                            replyForm.addEventListener('submit', function(e) {
                                e.preventDefault();
                                
                                const submitBtn = replyForm.querySelector('.send-reply-btn');
                                const originalText = submitBtn.innerHTML;
                                
                                // Disable button and show loading state
                                submitBtn.disabled = true;
                                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                                
                                // Submit form via AJAX
                                const formData = new FormData(replyForm);
                                
                                fetch('', {
                                    method: 'POST',
                                    body: new URLSearchParams(formData)
                                })
                                .then(response => response.text())
                                .then(data => {
                                    // Show success toast
                                    showToast('Reply sent successfully!', 'success');
                                    
                                    // Close modal
                                    modal.style.display = 'none';
                                    
                                    // Reload page to show updated status
                                    setTimeout(() => {
                                        location.reload();
                                    }, 1000);
                                })
                                .catch(error => {
                                    // Show error toast
                                    showToast('Failed to send reply. Please try again.', 'error');
                                    
                                    // Re-enable button
                                    submitBtn.disabled = false;
                                    submitBtn.innerHTML = originalText;
                                });
                            });
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>