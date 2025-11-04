<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';
require_once '../includes/SubscriptionCheck.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has member role
if (!is_logged_in() || $_SESSION['user_role'] !== 'member') {
    header('Location: ../login.php');
    exit();
}

// Check subscription status - redirect to expired page if subscription is not active
requireActiveSubscription();

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
    
    // First, get the latest message ID for each conversation
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
    
} catch (Exception $e) {
    $error = 'Error loading messages: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - LMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/toast.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 0; /* Remove padding to ensure navbar is at the very top */
        }
        
        /* Ensure no default margin on body */
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
            color: #212529;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #6c757d;
            font-size: 1.1rem;
        }

        .content-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            padding: 2rem;
            margin-bottom: 2rem;
            border: none;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .card-header h2 {
            color: #212529;
            margin: 0;
            font-size: 1.5rem;
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
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(52, 152, 219, 0.2);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9 0%, #2573A7 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(52, 152, 219, 0.3);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }

        .conversation-item {
            padding: 1.25rem;
            border-bottom: 1px solid #e9ecef;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .conversation-item:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }

        .conversation-item.unread {
            background: rgba(52, 152, 219, 0.05);
            border-left: 3px solid #3498db;
        }

        .conversation-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        
        .conversation-sender {
            font-weight: 600;
            color: #212529;
            font-size: 1.1rem;
        }

        .conversation-time {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .conversation-preview {
            color: #6c757d;
            font-size: 0.95rem;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .unread-badge {
            font-size: 0.7rem;
            vertical-align: middle;
            background-color: #3498db;
            color: white;
            border-radius: 50%;
            padding: 0;
            min-width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }
        
        .last-message {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }
        
        .message-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .no-conversations {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .no-conversations i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #ced4da;
        }
        
        /* Modal Styles */
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
            border-radius: 12px;
            width: 90%;
            max-width: 700px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
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
            border-bottom: 1px solid #e9ecef;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border-radius: 12px 12px 0 0;
        }

        .modal-header h2 {
            margin: 0;
            color: white;
            font-size: 1.25rem;
        }

        .close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: white;
            transition: color 0.3s;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close:hover {
            background-color: rgba(255, 255, 255, 0.2);
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
            color: #495057;
            font-size: 0.9rem;
        }

        .form-group label.required::after {
            content: " *";
            color: #e74c3c;
        }

        .form-control {
            width: 100%;
            padding: 0.5rem;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: border-color 0.3s;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            box-sizing: border-box;
        }

        .form-control:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.25);
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            padding: 1rem;
            border-top: 1px solid #e9ecef;
            background-color: #f8f9fa;
            border-radius: 0 0 12px 12px;
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
            background-color: #e9ecef;
            color: #495057;
        }

        .btn-secondary:hover {
            background-color: #dee2e6;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-secondary:active {
            transform: translateY(0);
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .content-card {
                padding: 1.5rem;
            }
            
            .modal-content {
                max-width: 95%;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/member_navbar.php'; ?>
    
    <!-- Toast Notification Container -->
    <div id="toast-container"></div>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-envelope"></i> Messages</h1>
            <p>Manage your communications</p>
        </div>
        
        <div class="content-card">
            <div class="card-header">
                <h2>Conversations</h2>
                <button class="btn btn-primary" id="composeBtn">
                    <i class="fas fa-plus"></i> New Message
                </button>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($conversations)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($conversations as $conv): ?>
                        <a href="conversation.php?id=<?php echo urlencode($conv['message_id']); ?>&sender_id=<?php echo urlencode($conv['other_user_id']); ?>" 
                           class="list-group-item list-group-item-action conversation-item <?php echo $conv['unread_count'] > 0 ? 'unread' : ''; ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="me-3">
                                    <div class="conversation-header">
                                        <span class="conversation-sender">
                                            <?php echo htmlspecialchars($conv['first_name'] . ' ' . $conv['last_name']); ?>
                                        </span>
                                        <?php if ($conv['unread_count'] > 0): ?>
                                            <span class="badge unread-badge">
                                                <?php echo $conv['unread_count']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="conversation-preview" title="<?php echo htmlspecialchars($conv['last_message']); ?>">
                                        <strong><?php echo htmlspecialchars($conv['subject']); ?></strong>: 
                                        <?php 
                                        $preview = htmlspecialchars(substr($conv['last_message'], 0, 80));
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
                <div class="no-conversations">
                    <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                    <h3>No Conversations</h3>
                    <p>You don't have any conversations yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Compose Modal -->
    <div class="modal" id="composeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus"></i> Compose New Message</h2>
                <button class="close" id="closeModal">&times;</button>
            </div>
            <form id="composeForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="recipient_type" class="required">Recipient:</label>
                        <select class="form-control" id="recipient_type" name="recipient_type" required>
                            <option value="all_librarians">All Librarians</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="subject" class="required">Subject:</label>
                        <input type="text" class="form-control" id="subject" name="subject" required>
                    </div>

                    <div class="form-group">
                        <label for="message_body" class="required">Message:</label>
                        <textarea class="form-control" id="message_body" name="message_body" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancelBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Message</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const composeBtn = document.getElementById('composeBtn');
            const closeModal = document.getElementById('closeModal');
            const cancelBtn = document.getElementById('cancelBtn');
            const composeModal = document.getElementById('composeModal');
            const composeForm = document.getElementById('composeForm');
            
            // Open modal
            composeBtn.addEventListener('click', function() {
                console.log('Opening modal');
                composeModal.style.display = 'block';
            });
            
            // Close modal
            function closeComposeModal() {
                console.log('Closing modal');
                composeModal.style.display = 'none';
                composeForm.reset();
                console.log('Modal closed and form reset');
            }
            
            closeModal.addEventListener('click', function() {
                console.log('Close button clicked');
                closeComposeModal();
            });
            
            cancelBtn.addEventListener('click', function() {
                console.log('Cancel button clicked');
                closeComposeModal();
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === composeModal) {
                    console.log('Clicked outside modal');
                    closeComposeModal();
                }
            });
            
            // Handle form submission
            composeForm.addEventListener('submit', function(e) {
                e.preventDefault();
                console.log('Form submitted');
                sendComposeMessage();
            });
        });
        
        function sendComposeMessage() {
            const form = document.getElementById('composeForm');
            const formData = new FormData(form);
            
            // Basic validation
            if (!formData.get('subject') || !formData.get('message_body')) {
                showToast('Please fill in all required fields', 'warning');
                return;
            }
            
            // Show loading indicator
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending...';
            submitBtn.disabled = true;
            
            // Make AJAX request to send the message
            fetch('../librarian/send_message.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Server response:', data);
                if (data.error) {
                    showToast('Error: ' + data.error, 'error');
                } else if (data.success) {
                    showToast(data.message || 'Message sent successfully', 'success');
                    // Close modal and redirect
                    document.getElementById('composeModal').style.display = 'none';
                    form.reset();
                    setTimeout(() => {
                        window.location.href = 'messages.php';
                    }, 1500);
                } else {
                    showToast('Unexpected response from server', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Network error: ' + error.message, 'error');
                // Force close modal on network error
                document.getElementById('composeModal').style.display = 'none';
                form.reset();
            })
            .finally(() => {
                // Always restore button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }
        
        // Toast notification function
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
</body>
</html>