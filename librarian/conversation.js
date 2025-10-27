/**
 * Conversation Management for LMS
 * Handles loading and displaying conversations, sending messages, and UI updates
 */

document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const conversationList = document.querySelector('.list-group-flush');
    const conversationView = document.getElementById('conversation-view');
    const messagesContainer = document.getElementById('messages-container');
    const backToInboxBtn = document.getElementById('back-to-inbox');
    const conversationHeader = document.getElementById('conversation-header');
    const messageForm = document.getElementById('message-form');
    const messageInput = document.getElementById('message-input');
    const newMessageBtn = document.querySelector('[data-bs-target="#composeModal"]');
    
    // State
    let currentConversation = null;
    
    // Event Listeners
    if (conversationList) {
        conversationList.addEventListener('click', handleConversationClick);
    }
    
    if (backToInboxBtn) {
        backToInboxBtn.addEventListener('click', showInboxView);
    }
    
    if (messageForm) {
        messageForm.addEventListener('submit', handleMessageSubmit);
    }
    
    // Initialize any tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(tooltipTriggerEl => {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Event Handlers
    function handleConversationClick(e) {
        const item = e.target.closest('.conversation-item');
        if (!item) return;
        
        const senderId = item.dataset.senderId;
        const senderName = item.dataset.senderName;
        
        // Mark as read in the UI
        const unreadBadge = item.querySelector('.unread-badge');
        if (unreadBadge) {
            unreadBadge.remove();
        }
        
        loadConversation(senderId, senderName);
    }
    
    async function handleMessageSubmit(e) {
        e.preventDefault();
        
        if (!messageInput.value.trim() || !currentConversation) return;
        
        try {
            showLoading(true);
            
            const formData = new FormData();
            formData.append('recipient_id', currentConversation.senderId);
            formData.append('message_body', messageInput.value.trim());
            formData.append('subject', 'New Message');
            
            const response = await fetch('send_message.php', {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error('Failed to send message');
            }
            
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Clear input and reload conversation
            messageInput.value = '';
            await loadConversation(
                currentConversation.senderId, 
                currentConversation.senderName
            );
            
            showToast('Message sent successfully!', 'success');
            
        } catch (error) {
            console.error('Error sending message:', error);
            showToast('Error: ' + error.message, 'error');
        } finally {
            showLoading(false);
        }
    }
    
    // Core Functions
    async function loadConversation(senderId, senderName) {
        try {
            showLoading(true);
            
            const response = await fetch(`get_conversation.php?sender_id=${encodeURIComponent(senderId)}`);
            
            if (!response.ok) {
                throw new Error('Failed to load conversation');
            }
            
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Update state
            currentConversation = {
                senderId: data.sender_id,
                senderName: data.sender_name || senderName
            };
            
            // Update UI
            updateConversationView(data.messages, currentConversation.senderName);
            
        } catch (error) {
            console.error('Error loading conversation:', error);
            showToast('Error loading conversation: ' + error.message, 'error');
        } finally {
            showLoading(false);
        }
    }
    
    function updateConversationView(messages, senderName) {
        // Update header
        if (conversationHeader) {
            conversationHeader.textContent = `Conversation with ${senderName}`;
        }
        
        // Clear and rebuild messages
        if (messagesContainer) {
            messagesContainer.innerHTML = messages.map(msg => `
                <div class="message-bubble ${msg.is_sender ? 'sent' : 'received'}">
                    <div class="message-content">${msg.message}</div>
                    <div class="message-time">${msg.timestamp}</div>
                </div>
            `).join('');
            
            // Scroll to bottom
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        
        // Show conversation view
        document.querySelector('.inbox-view')?.classList.add('d-none');
        conversationView?.classList.remove('d-none');
    }
    
    function showInboxView() {
        document.querySelector('.inbox-view')?.classList.remove('d-none');
        conversationView?.classList.add('d-none');
        currentConversation = null;
    }
    
    // UI Helpers
    function showLoading(show) {
        // Toggle loading state (you might need to add a loading element to your HTML)
        const loadingElement = document.getElementById('loading-indicator');
        if (loadingElement) {
            loadingElement.style.display = show ? 'block' : 'none';
        }
    }
    
    function showToast(message, type = 'info') {
        // Simple toast notification
        const toastContainer = document.getElementById('toast-container') || createToastContainer();
        const toast = document.createElement('div');
        toast.className = `toast show align-items-center text-white bg-${type === 'error' ? 'danger' : 'success'} border-0`;
        toast.role = 'alert';
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        
        toastContainer.appendChild(toast);
        
        // Auto-remove after delay
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '1100';
        document.body.appendChild(container);
        return container;
    }
});
