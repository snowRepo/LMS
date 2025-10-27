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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/toast.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
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
            border: none;
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
            font-size: 1.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border: none;
            padding: 0.6rem 1.2rem;
            font-weight: 600;
            border-radius: 8px;
            transition: var(--transition);
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(52, 152, 219, 0.4);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }

        .conversation-item {
            padding: 1.25rem;
            border-bottom: 1px solid var(--gray-200);
            transition: var(--transition);
            text-decoration: none;
            color: inherit;
            display: block;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .conversation-item:hover {
            background: var(--gray-100);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }

        .conversation-item.unread {
            background: rgba(142, 68, 173, 0.05);
            border-left: 3px solid var(--primary-color);
        }

        .conversation-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        
        .conversation-sender {
            font-weight: 600;
            color: var(--gray-900);
            font-size: 1.1rem;
        }

        .conversation-time {
            font-size: 0.85rem;
            color: var(--gray-600);
        }

        .conversation-preview {
            color: var(--gray-600);
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
            background-color: var(--primary-color);
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
            color: var(--gray-600);
        }

        .no-conversations i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--gray-400);
        }
        
        /* Modal Styles */
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: var(--box-shadow-lg);
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            border: none;
        }
        
        .modal-title {
            font-weight: 600;
        }
        
        .btn-close {
            filter: invert(1);
        }
        
        .form-label {
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .form-control, .form-select {
            border: 2px solid var(--gray-300);
            border-radius: 8px;
            padding: 0.75rem;
            transition: var(--transition);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.25);
        }
        
        .modal-footer {
            background-color: var(--gray-100);
            border-radius: 0 0 12px 12px !important;
            border: none;
        }
        
        .btn-secondary {
            background-color: var(--gray-300);
            border: none;
            color: var(--gray-700);
            font-weight: 500;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            transition: var(--transition);
        }
        
        .btn-secondary:hover {
            background-color: var(--gray-400);
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
        }
    </style>
</head>
<body>
    <?php include 'includes/librarian_navbar.php'; ?>
    
    <!-- Toast Notification Container -->
    <div id="toast-container"></div>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-envelope me-2"></i> Messages</h1>
            <p>Manage your communications</p>
        </div>
        
        <div class="content-card">
            <div class="card-header">
                <h2>Conversations</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#composeModal">
                    <i class="fas fa-plus me-1"></i> New Message
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
    <div class="modal fade" id="composeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Compose New Message</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="composeForm" onsubmit="sendComposeMessage(); return false;">
                    <div class="modal-body">
                        <!-- Recipient Type Selection -->
                        <div class="mb-4">
                            <label for="recipient_type" class="form-label fw-bold">Recipient:</label>
                            <select class="form-select" id="recipient_type" name="recipient_type" required>
                                <option value="supervisor">All Supervisors</option>
                                <option value="all_members">All Members</option>
                                <option value="individual_member">Individual Member</option>
                            </select>
                        </div>

                        <!-- Individual Member Search (initially hidden) -->
                        <div id="individualRecipientGroup" class="mb-3" style="display: none;">
                            <label for="memberSearch" class="form-label">Search Member:</label>
                            <div class="position-relative">
                                <input type="text" class="form-control" id="memberSearch" placeholder="Type to search members..." autocomplete="off">
                                <input type="hidden" id="selectedMemberId" name="individual_recipient">
                                <div id="searchResults" class="list-group position-absolute w-100 z-1" style="display: none; max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem; margin-top: 0.25rem;">
                                    <!-- Search results will be populated here -->
                                </div>
                            </div>
                        </div>

                        <!-- Subject -->
                        <div class="mb-3">
                            <label for="subject" class="form-label fw-bold">Subject:</label>
                            <input type="text" class="form-control" id="subject" name="subject" required>
                        </div>

                        <!-- Message -->
                        <div class="mb-2">
                            <label for="message_body" class="form-label fw-bold">Message:</label>
                            <textarea class="form-control" id="message_body" name="message_body" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Show/hide individual recipient search based on recipient type
        document.getElementById('recipient_type').addEventListener('change', function() {
            const individualGroup = document.getElementById('individualRecipientGroup');
            individualGroup.style.display = this.value === 'individual_member' ? 'block' : 'none';
        });

        // Initialize the visibility on page load
        document.addEventListener('DOMContentLoaded', function() {
            const recipientType = document.getElementById('recipient_type');
            const individualGroup = document.getElementById('individualRecipientGroup');
            if (recipientType.value !== 'individual_member') {
                individualGroup.style.display = 'none';
            }
        });

        // Member search functionality
        const memberSearch = document.getElementById('memberSearch');
        const searchResults = document.getElementById('searchResults');
        const selectedMemberId = document.getElementById('selectedMemberId');
        let searchTimeout;
        let selectedMember = null;

        // Handle search input
        memberSearch.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query === '') {
                selectedMemberId.value = '';
                selectedMember = null;
                searchResults.style.display = 'none';
                return;
            }
            
            if (selectedMember && query === selectedMember.name) {
                searchResults.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => searchMembers(query), 300);
        });

        // Close search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.position-relative')) {
                searchResults.style.display = 'none';
            }
        });

        // Show search results when focusing on the search input
        memberSearch.addEventListener('focus', function() {
            if (this.value && !selectedMember) {
                searchMembers(this.value);
            }
        });

        // Select all text when clicking on the search input with a selected member
        memberSearch.addEventListener('click', function() {
            if (selectedMember) {
                this.select();
            }
        });

        function searchMembers(query) {
            if (!query) {
                searchResults.style.display = 'none';
                return;
            }
            
            // Show loading state
            searchResults.innerHTML = '<div class="list-group-item">Searching...</div>';
            searchResults.style.display = 'block';
            
            // Make AJAX request to search members
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'ajax_search_members.php?term=' + encodeURIComponent(query), true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            const members = JSON.parse(xhr.responseText);
                            displaySearchResults(members);
                        } catch (e) {
                            console.error('Error parsing JSON:', e);
                            searchResults.innerHTML = '<div class="list-group-item">Error loading results</div>';
                        }
                    } else {
                        console.error('AJAX request failed with status:', xhr.status);
                        searchResults.innerHTML = '<div class="list-group-item">Error loading results</div>';
                    }
                }
            };
            xhr.onerror = function() {
                console.error('AJAX request failed');
                searchResults.innerHTML = '<div class="list-group-item">Error loading results</div>';
            };
            xhr.send();
        }
        
        function displaySearchResults(members) {
            if (members.length === 0) {
                searchResults.innerHTML = '<div class="list-group-item">No members found</div>';
                return;
            }
            
            searchResults.innerHTML = members.map(member => `
                <button type="button" class="list-group-item list-group-item-action" 
                        data-id="${member.id}" data-name="${member.name}">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">${member.name}</h6>
                    </div>
                    <small class="text-muted">${member.email}</small>
                </button>
            `).join('');
            
            // Add click handlers to result items
            searchResults.querySelectorAll('button').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');
                    
                    selectedMemberId.value = id;
                    selectedMember = { id, name };
                    memberSearch.value = name;
                    searchResults.style.display = 'none';
                });
            });
        }
        
        function sendComposeMessage() {
            const form = document.getElementById('composeForm');
            const formData = new FormData(form);
            
            // Basic validation
            if (!formData.get('subject') || !formData.get('message_body')) {
                showToast('Please fill in all required fields', 'warning');
                return;
            }
            
            if (formData.get('recipient_type') === 'individual_member' && !formData.get('individual_recipient')) {
                showToast('Please select a member to send the message to', 'warning');
                return;
            }
            
            // Show loading indicator
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending...';
            submitBtn.disabled = true;
            
            // Make AJAX request to send the message
            fetch('send_message.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    showToast(data.error, 'error');
                } else {
                    showToast(data.message, 'success');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('composeModal'));
                    modal.hide();
                    form.reset();
                    selectedMemberId.value = '';
                    selectedMember = null;
                    // Reload the page to show the new conversation
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error sending message', 'error');
            })
            .finally(() => {
                // Restore button state
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