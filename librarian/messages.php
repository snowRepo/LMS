<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';
require_once '../includes/SubscriptionCheck.php';
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
            --modal-bg: rgba(0, 0, 0, 0.5);
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
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: 0 4px 6px rgba(52, 152, 219, 0.2);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, #2573A7 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(52, 152, 219, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.3);
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
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: var(--modal-bg);
            overflow: hidden;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 2% auto;
            padding: 0;
            border: none;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            box-shadow: var(--box-shadow-lg);
            display: flex;
            flex-direction: column;
        }
        
        /* Add space to the right of dropdown icons */
        select.form-select {
            padding-right: 2.5rem;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 16px 12px;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }
        
        /* Change cancel button color to broken white */
        #cancelMessageBtn {
            background-color: #f8f9fa;
            color: #212529;
            border: 1px solid #dee2e6;
        }
        
        #cancelMessageBtn:hover {
            background-color: #e9ecef;
        }
        
        .modal-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
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
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
            transition: var(--transition);
        }

        .close:hover {
            opacity: 0.8;
        }

        .modal-body {
            padding: 0;
            overflow-y: auto;
            flex: 1;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--gray-700);
        }

        .form-control, .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
            box-sizing: border-box;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .modal-footer {
            padding: 1.5rem;
            background-color: var(--gray-100);
            border-radius: 0 0 var(--border-radius) var(--border-radius);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        
        /* Search Results */
        .search-results {
            position: absolute;
            z-index: 1000;
            width: 100%;
            background: white;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            max-height: 200px;
            overflow-y: auto;
            margin-top: 0.25rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .search-result-item {
            padding: 0.75rem;
            border-bottom: 1px solid var(--gray-200);
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .search-result-item:hover {
            background-color: var(--gray-100);
        }
        
        .search-result-item:last-child {
            border-bottom: none;
        }
        
        .position-relative {
            position: relative;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .modal-content {
                width: 95%;
                margin: 5% auto;
                max-height: 95vh;
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
            <h1><i class="fas fa-envelope"></i> Messages</h1>
            <p>Manage your communications</p>
        </div>
        
        <div class="content-card">
            <div class="card-header">
                <h2>Conversations</h2>
                <button class="btn btn-primary" id="newMessageBtn">
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
                           class="conversation-item <?php echo $conv['unread_count'] > 0 ? 'unread' : ''; ?>">
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
    <div id="composeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-envelope"></i> Compose New Message</h2>
                <button class="close">&times;</button>
            </div>
            <div class="modal-body" style="padding: 1.5rem;">
                <form id="composeForm">
                    <!-- Recipient Type Selection -->
                    <div class="form-group">
                        <label for="recipient_type">Recipient:</label>
                        <select class="form-select" id="recipient_type" name="recipient_type" required>
                            <option value="supervisor">All Supervisors</option>
                            <option value="all_members">All Members</option>
                            <option value="individual_member">Individual Member</option>
                        </select>
                    </div>

                    <!-- Individual Member Search (initially hidden) -->
                    <div id="individualRecipientGroup" class="form-group" style="display: none;">
                        <label for="memberSearch">Search Member:</label>
                        <div class="position-relative">
                            <input type="text" class="form-control" id="memberSearch" placeholder="Type to search members..." autocomplete="off">
                            <input type="hidden" id="selectedMemberId" name="individual_recipient">
                            <div id="searchResults" class="search-results" style="display: none;">
                                <!-- Search results will be populated here -->
                            </div>
                        </div>
                    </div>

                    <!-- Subject -->
                    <div class="form-group">
                        <label for="subject">Subject:</label>
                        <input type="text" class="form-control" id="subject" name="subject" required>
                    </div>

                    <!-- Message -->
                    <div class="form-group">
                        <label for="message_body">Message:</label>
                        <textarea class="form-control" id="message_body" name="message_body" rows="4" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelMessageBtn">Cancel</button>
                <button class="btn btn-primary" id="sendMessageBtn">Send Message</button>
            </div>
        </div>
    </div>

    <script>
        // Modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('composeModal');
            const newMessageBtn = document.getElementById('newMessageBtn');
            const closeBtn = document.querySelector('.close');
            const cancelMessageBtn = document.getElementById('cancelMessageBtn');
            
            // Open modal
            newMessageBtn.addEventListener('click', function() {
                // Reset form
                document.getElementById('composeForm').reset();
                document.getElementById('selectedMemberId').value = '';
                document.getElementById('searchResults').style.display = 'none';
                document.getElementById('individualRecipientGroup').style.display = 'none';
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            });
            
            // Close modal functions
            function closeModal() {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
            
            closeBtn.addEventListener('click', closeModal);
            cancelMessageBtn.addEventListener('click', closeModal);
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeModal();
                }
            });
            
            // Show/hide individual recipient search based on recipient type
            document.getElementById('recipient_type').addEventListener('change', function() {
                const individualGroup = document.getElementById('individualRecipientGroup');
                individualGroup.style.display = this.value === 'individual_member' ? 'block' : 'none';
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
                searchResults.innerHTML = '<div class="search-result-item">Searching...</div>';
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
                                searchResults.innerHTML = '<div class="search-result-item">Error loading results</div>';
                            }
                        } else {
                            console.error('AJAX request failed with status:', xhr.status);
                            searchResults.innerHTML = '<div class="search-result-item">Error loading results</div>';
                        }
                    }
                };
                xhr.onerror = function() {
                    console.error('AJAX request failed');
                    searchResults.innerHTML = '<div class="search-result-item">Error loading results</div>';
                };
                xhr.send();
            }
            
            function displaySearchResults(members) {
                if (members.length === 0) {
                    searchResults.innerHTML = '<div class="search-result-item">No members found</div>';
                    return;
                }
                
                searchResults.innerHTML = members.map(member => `
                    <div class="search-result-item" data-id="${member.id}" data-name="${member.name}">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <h6 style="margin: 0;">${member.name}</h6>
                        </div>
                        <small style="color: var(--gray-600);">${member.email}</small>
                    </div>
                `).join('');
                
                // Add click handlers to result items
                searchResults.querySelectorAll('.search-result-item').forEach(item => {
                    item.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const name = this.getAttribute('data-name');
                        
                        selectedMemberId.value = id;
                        selectedMember = { id, name };
                        memberSearch.value = name;
                        searchResults.style.display = 'none';
                    });
                });
            }
            
            // Send message function
            document.getElementById('sendMessageBtn').addEventListener('click', function() {
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
                const submitBtn = this;
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
                        closeModal();
                        form.reset();
                        selectedMemberId.value = '';
                        selectedMember = null;
                        // Reload the page to show the new conversation
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error sending message', 'error');
                })
                .finally(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
            });
            
            // Toast notification function
            function showToast(message, type = 'info') {
                // Create toast container if it doesn't exist
                let toastContainer = document.getElementById('toast-container');
                if (!toastContainer) {
                    toastContainer = document.createElement('div');
                    toastContainer.id = 'toast-container';
                    toastContainer.style.position = 'fixed';
                    toastContainer.style.top = '20px';
                    toastContainer.style.right = '20px';
                    toastContainer.style.zIndex = '9999';
                    document.body.appendChild(toastContainer);
                }
                
                // Create toast element
                const toast = document.createElement('div');
                toast.style.display = 'flex';
                toast.style.alignItems = 'center';
                toast.style.gap = '12px';
                toast.style.padding = '16px 20px';
                toast.style.borderRadius = '8px';
                toast.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
                toast.style.marginBottom = '12px';
                toast.style.minWidth = '300px';
                toast.style.maxWidth = '400px';
                toast.style.animation = 'toastSlideIn 0.3s ease-out forwards';
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                
                // Set colors based on type
                switch(type) {
                    case 'success':
                        toast.style.backgroundColor = '#2e7d32';
                        toast.style.color = 'white';
                        break;
                    case 'error':
                        toast.style.backgroundColor = '#c62828';
                        toast.style.color = 'white';
                        break;
                    case 'warning':
                        toast.style.backgroundColor = '#ef6c00';
                        toast.style.color = 'white';
                        break;
                    default:
                        toast.style.backgroundColor = '#1565c0';
                        toast.style.color = 'white';
                }
                
                toast.innerHTML = `
                    <div style="flex: 1; font-size: 0.95rem;">${message}</div>
                    <button class="toast-close" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: inherit; opacity: 0.7; transition: opacity 0.2s;">&times;</button>
                `;
                
                // Add close button event
                const closeBtn = toast.querySelector('.toast-close');
                closeBtn.addEventListener('click', function() {
                    toast.style.animation = 'toastSlideOut 0.3s ease-in forwards';
                    setTimeout(() => {
                        if (toast.parentNode) {
                            toast.parentNode.removeChild(toast);
                        }
                    }, 300);
                });
                
                // Add toast to container
                toastContainer.appendChild(toast);
                
                // Trigger animation
                setTimeout(() => {
                    toast.style.opacity = '1';
                    toast.style.transform = 'translateX(0)';
                }, 10);
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.style.animation = 'toastSlideOut 0.3s ease-in forwards';
                        setTimeout(() => {
                            if (toast.parentNode) {
                                toast.parentNode.removeChild(toast);
                            }
                        }, 300);
                    }
                }, 5000);
            }
            
            // Add CSS for toast animations
            const style = document.createElement('style');
            style.innerHTML = `
                @keyframes toastSlideIn {
                    from { opacity: 0; transform: translateX(100%); }
                    to { opacity: 1; transform: translateX(0); }
                }
                
                @keyframes toastSlideOut {
                    from { opacity: 1; transform: translateX(0); }
                    to { opacity: 0; transform: translateX(100%); }
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>