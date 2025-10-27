// Initialize all event listeners
function initMessagesPage() {
    // Add modal styles to ensure consistency with other modals in the system
    if (!document.getElementById('modal-styles')) {
        const style = document.createElement('style');
        style.id = 'modal-styles';
        style.textContent = `
            .modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.5);
                overflow: auto;
            }
            
            .modal-content {
                background-color: #fefefe;
                margin: 2% auto;
                padding: 0;
                border: none;
                border-radius: 12px;
                width: 90%;
                max-width: 800px;
                max-height: 90vh;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                display: flex;
                flex-direction: column;
            }
            
            .modal-header {
                padding: 1.5rem;
                background: linear-gradient(135deg, #3498DB, #2980B9);
                color: white;
                border-radius: 12px 12px 0 0;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .modal-header h2 {
                margin: 0;
                font-size: 1.5rem;
                color: white;
            }
            
            .close {
                color: white;
                font-size: 2rem;
                font-weight: bold;
                cursor: pointer;
                background: none;
                border: none;
            }
            
            .close:hover {
                opacity: 0.8;
            }
            
            .modal-body {
                padding: 1.5rem;
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
                color: #495057;
            }
            
            .form-group label.required::after {
                content: " *";
                color: #e74c3c;
            }
            
            .form-control {
                width: 100%;
                padding: 0.75rem;
                border: 1px solid #ced4da;
                border-radius: 8px;
                font-size: 1rem;
                transition: border-color 0.3s, box-shadow 0.3s;
                box-sizing: border-box;
                /* Add padding to the right for dropdown icons */
                padding-right: 2.5rem;
            }
            
            /* Remove padding-right for non-select elements */
            input.form-control,
            textarea.form-control {
                padding-right: 0.75rem;
            }
            
            .form-control:focus {
                border-color: #3498DB;
                outline: none;
                box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            }
            
            /* Specific styling for select elements */
            select.form-control {
                padding-right: 2.5rem;
                background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
                background-repeat: no-repeat;
                background-position: right 1rem center;
                background-size: 1rem;
                appearance: none;
                -webkit-appearance: none;
                -moz-appearance: none;
            }
            
            textarea.form-control {
                min-height: 120px;
                resize: vertical;
            }
            
            .modal-footer {
                padding: 1.5rem;
                background-color: #f8f9fa;
                border-radius: 0 0 12px 12px;
                display: flex;
                justify-content: flex-end;
                gap: 1rem;
            }
            
            .btn {
                padding: 0.75rem 1.5rem;
                border: none;
                border-radius: 8px;
                font-size: 1rem;
                font-weight: 500;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                transition: all 0.3s ease;
            }
            
            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }
            
            .btn:active {
                transform: translateY(0);
            }
            
            .btn-secondary {
                background: #6c757d;
                color: white;
            }
            
            .btn-secondary:hover {
                background: #5a6268;
            }
            
            .btn-primary {
                background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
                color: white;
            }
            
            .btn-primary:hover {
                background: linear-gradient(135deg, #2980B9 0%, #2573A7 100%);
            }
            
            /* Override any hover effects on the compose button */
            .compose-link {
                transition: transform 0.3s ease !important;
            }
            
            .compose-link:hover {
                background: var(--primary-color) !important;
                color: white !important;
                transform: translateY(-2px) !important;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
                opacity: 1 !important;
            }
            
            /* Chat modal styles */
            .chat-modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.5);
                overflow: auto;
            }
            
            .chat-modal-content {
                background-color: #fefefe;
                margin: 2% auto;
                padding: 0;
                border: none;
                border-radius: 12px;
                width: 90%;
                max-width: 800px;
                height: 80vh;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                display: flex;
                flex-direction: column;
            }
            
            .chat-header {
                padding: 1.5rem;
                background: linear-gradient(135deg, #3498DB, #2980B9);
                color: white;
                border-radius: 12px 12px 0 0;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .chat-header h2 {
                margin: 0;
                font-size: 1.5rem;
                color: white;
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
                word-wrap: break-word;
            }
            
            .chat-message.received {
                align-self: flex-start;
                background-color: #f1f1f1;
                color: #333;
            }
            
            .chat-message.sent {
                align-self: flex-end;
                background-color: #3498DB;
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
                border-top: 1px solid #eee;
                gap: 0.5rem;
                background-color: #f8f9fa;
                border-radius: 0 0 12px 12px;
            }
            
            .chat-input {
                flex: 1;
                padding: 0.75rem;
                border: 1px solid #ced4da;
                border-radius: 8px;
                font-size: 1rem;
                transition: border-color 0.3s, box-shadow 0.3s;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                box-sizing: border-box;
            }
            
            .chat-input:focus {
                border-color: #3498DB;
                outline: none;
                box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            }
            
            .chat-send-btn {
                background-color: #3498DB;
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
                background-color: #2980B9;
            }
            
            .loading, .error, .no-messages {
                text-align: center;
                padding: 1rem;
                color: #666;
                align-self: center;
            }
            
            .error {
                color: #e74c3c;
            }
            
            .error-message {
                font-size: 0.8rem;
                margin-top: 0.5rem;
                color: #e74c3c;
            }
            
            @media (max-width: 768px) {
                .modal-content {
                    width: 95%;
                    margin: 5% auto;
                    max-height: 95vh;
                }
                
                .chat-modal-content {
                    width: 95%;
                    height: 90vh;
                }
                
                .chat-message {
                    max-width: 90%;
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    // Attach message click listeners
    attachMessageClickListeners();
    
    // Message view switching
    document.querySelectorAll('[data-view]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Update active link
            document.querySelectorAll('.sidebar-menu a').forEach(a => a.classList.remove('active'));
            this.classList.add('active');
            
            // Hide all views and show the selected one
            document.querySelectorAll('.message-view').forEach(view => view.style.display = 'none');
            const viewName = this.getAttribute('data-view');
            const targetView = document.getElementById(viewName + '-view');
            if (targetView) {
                targetView.style.display = 'block';
            }
            
            // Update header
            const header = document.querySelector('.card-header h2');
            if (header) {
                if (viewName === 'inbox') {
                    header.textContent = 'Inbox';
                } else if (viewName === 'sent') {
                    header.textContent = 'Sent Messages';
                } else if (viewName === 'starred') {
                    header.textContent = 'Starred Messages';
                }
            }
            
            // Re-attach message click listeners after view change
            setTimeout(attachMessageClickListeners, 100);
        });
    });
    
    // Compose button
    const composeBtn = document.getElementById('composeBtn');
    if (composeBtn) {
        // Add subtle hover effect without color fading
        composeBtn.addEventListener('mouseenter', function(e) {
            e.preventDefault();
            // Add subtle transform effect without color changes
            this.style.cssText = 'background: var(--primary-color) !important; color: white !important; transform: translateY(-2px) !important; box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important; transition: transform 0.3s ease, box-shadow 0.3s ease !important;';
        });
        
        composeBtn.addEventListener('mouseleave', function(e) {
            e.preventDefault();
            // Return to original state
            this.style.cssText = 'background: var(--primary-color) !important; color: white !important; transform: none !important; box-shadow: none !important; transition: transform 0.3s ease, box-shadow 0.3s ease !important;';
        });
        
        composeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            // Store reference to the compose button
            window.composeButton = this;
            // Add active state to button
            this.classList.add('active');
            
            // Create compose modal if it doesn't exist
            let composeModal = document.getElementById('composeModal');
            if (!composeModal) {
                composeModal = document.createElement('div');
                composeModal.id = 'composeModal';
                composeModal.className = 'modal';
                composeModal.innerHTML = `
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2><i class="fas fa-envelope"></i> Compose Message</h2>
                            <button class="close" id="closeCompose">&times;</button>
                        </div>
                        <div class="modal-body">
                            <form id="composeForm">
                                <div class="form-group">
                                    <label for="recipient_type" class="required">Recipient Type</label>
                                    <select name="recipient_type" id="recipient_type" class="form-control" required>
                                        <option value="">Select recipient type</option>
                                    </select>
                                </div>
                                
                                <div class="form-group" id="individual_recipient_group" style="display: none;">
                                    <label for="individual_recipient" class="required">Select Recipient</label>
                                    <select name="individual_recipient" id="individual_recipient" class="form-control">
                                        <option value="">Select a recipient</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="subject" class="required">Subject</label>
                                    <input type="text" name="subject" id="subject" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="message_body" class="required">Message</label>
                                    <textarea name="message_body" id="message_body" class="form-control" rows="6" required></textarea>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" id="cancelCompose">Cancel</button>
                            <button class="btn btn-primary" id="sendCompose">Send Message</button>
                        </div>
                    </div>
                `;
                document.body.appendChild(composeModal);
                
                // Add event listeners
                document.getElementById('closeCompose').addEventListener('click', closeCompose);
                document.getElementById('cancelCompose').addEventListener('click', closeCompose);
                document.getElementById('sendCompose').addEventListener('click', sendComposeMessage);
                
                // Add change listener to recipient type
                document.getElementById('recipient_type').addEventListener('change', function() {
                    const individualRecipientGroup = document.getElementById('individual_recipient_group');
                    if (this.value.includes('individual')) {
                        individualRecipientGroup.style.display = 'block';
                        // Ensure the select is hidden and search container is shown
                        const select = document.getElementById('individual_recipient');
                        const searchContainer = document.getElementById('memberSearchContainer');
                        
                        if (select) select.style.display = 'none';
                        if (searchContainer) searchContainer.style.display = 'block';
                        
                        // Load recipients based on type
                        loadRecipients(this.value);
                    } else {
                        individualRecipientGroup.style.display = 'none';
                    }
                });
                
                // Close modal when clicking outside
                composeModal.addEventListener('click', function(e) {
                    if (e.target === composeModal) {
                        closeCompose();
                    }
                });
            }
            
            // Show modal
            composeModal.style.display = 'block';
            
            // Reset form and show search container if needed
            const form = document.getElementById('composeForm');
            if (form) {
                form.reset();
                
                // Initialize recipient type dropdown
                updateRecipientOptions();
                
                // If individual recipient group is shown, ensure search container is visible
                const individualRecipientGroup = document.getElementById('individual_recipient_group');
                const recipientType = document.getElementById('recipient_type');
                
                if (recipientType && recipientType.value.includes('individual')) {
                    individualRecipientGroup.style.display = 'block';
                    const select = document.getElementById('individual_recipient');
                    const searchContainer = document.getElementById('memberSearchContainer');
                    
                    if (select) select.style.display = 'none';
                    if (!searchContainer) {
                        // Force load recipients to initialize search
                        loadRecipients(recipientType.value);
                    } else {
                        searchContainer.style.display = 'block';
                    }
                } else {
                    individualRecipientGroup.style.display = 'none';
                }
            }
            
            // Update recipient options based on user role
            updateRecipientOptions();
        });
    }
    
    // Load messages via AJAX to ensure they're up to date
    loadMessagesViaAjax();
}

// Function to load messages via AJAX
function loadMessagesViaAjax() {
    // Helper function to handle fetch requests
    const fetchMessages = (view) => {
        const url = `fetch_messages.php?view=${view}`;
        console.log(`Fetching messages from: ${url}`);
        
        return fetch(url, {
            credentials: 'same-origin' // Include cookies for session
        })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    console.error(`Error response for ${view}:`, text);
                    throw new Error(`HTTP error! status: ${response.status}`);
                });
            }
            return response.text().then(text => {
                // Extract JSON part before any HTML
                const jsonEnd = text.indexOf('<!');
                const jsonText = jsonEnd > 0 ? text.substring(0, jsonEnd).trim() : text.trim();
                
                try {
                    if (!jsonText) {
                        throw new Error('Empty response');
                    }
                    const data = JSON.parse(jsonText);
                    return data;
                } catch (e) {
                    console.error(`Error parsing JSON for ${view}:`, e);
                    console.error('JSON text that failed to parse:', jsonText);
                    throw new Error(`Invalid JSON response for ${view}`);
                }
            });
        })
        .then(data => {
            if (!data) {
                console.error(`Empty data for ${view}`);
                return;
            }
            if (data.error) {
                console.error(`Error loading ${view} messages:`, data.error);
                return;
            }
            updateMessageView(view, data.messages || []);
        })
        .catch(error => {
            console.error(`Error in fetchMessages(${view}):`, error);
        });
    };

    // Load all message types in parallel
    Promise.all([
        fetchMessages('inbox'),
        fetchMessages('sent'),
        fetchMessages('starred')
    ]).catch(error => {
        console.error('Error in Promise.all:', error);
    });
}

// Function to update message view with AJAX data
function updateMessageView(viewName, messages) {
    const view = document.getElementById(viewName + '-view');
    if (!view) return;
    
    const messageList = view.querySelector('.message-list');
    if (!messageList) return;
    
    // Clear existing messages
    messageList.innerHTML = '';
    
    if (messages && messages.length > 0) {
        messages.forEach(message => {
            const messageItem = document.createElement('li');
            messageItem.className = 'message-item ' + (message.is_read ? '' : 'unread');
            messageItem.dataset.messageId = message.id;
            
            if (viewName === 'inbox' || viewName === 'starred') {
                messageItem.dataset.senderId = message.sender_id;
                messageItem.dataset.senderName = message.sender_name;
            } else if (viewName === 'sent') {
                messageItem.dataset.recipientId = message.recipient_id;
                messageItem.dataset.recipientName = message.recipient_name;
            }
            
            let messageHtml = `
                <div class="message-header">
                    <div class="message-sender">${viewName === 'sent' ? 'To: ' + message.recipient_name : message.sender_name}</div>
                    <div class="message-time">${formatMessageTime(message.message_time)}</div>
                </div>
                <div class="message-subject">${message.subject}</div>
                <div class="message-preview">${message.message.substring(0, 100)}${message.message.length > 100 ? '...' : ''}</div>
                <div class="message-actions">
            `;
            
            if (viewName === 'starred' || (viewName === 'inbox' && message.is_starred)) {
                messageHtml += `
                    <button class="message-action-btn starred" 
                            data-action="star" 
                            title="Unstar message">
                        <i class="fas fa-star"></i>
                    </button>
                `;
            } else if (viewName === 'inbox') {
                messageHtml += `
                    <button class="message-action-btn ${message.is_starred ? 'starred' : ''}" 
                            data-action="star" 
                            title="${message.is_starred ? 'Unstar message' : 'Star message'}">
                        <i class="fas fa-star"></i>
                    </button>
                    
                    ${!message.is_read ? `
                    <button class="message-action-btn" 
                            data-action="read" 
                            title="Mark as read">
                        <i class="fas fa-envelope-open"></i>
                    </button>` : ''}
                `;
                
            } else if (viewName === 'sent') {
                messageHtml += `
                    <button class="message-action-btn ${message.is_starred ? 'starred' : ''}" 
                            data-action="star" 
                            title="${message.is_starred ? 'Unstar message' : 'Star message'}">
                        <i class="fas fa-star"></i>
                    </button>
                `;
            }
            
            messageHtml += `
                </div>
            `;
            
            messageItem.innerHTML = messageHtml;
            messageList.appendChild(messageItem);
        });
        
        // Attach event listeners to the new buttons
        const newButtons = messageList.querySelectorAll('.message-action-btn');
        newButtons.forEach(button => {
            button.addEventListener('click', handleMessageAction);
        });
    } else {
        // Show no messages message
        const noMessages = view.querySelector('.no-messages');
        if (noMessages) {
            noMessages.style.display = 'block';
        }
    }
    
    // Re-attach message click listeners
    attachMessageClickListeners();
}

// Helper function to format message time
function formatMessageTime(timeString) {
    const date = new Date(timeString);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Function to update recipient options based on user role
function updateRecipientOptions() {
    const recipientTypeSelect = document.getElementById('recipient_type');
    if (!recipientTypeSelect) return;
    
    // Clear existing options except the first one
    while (recipientTypeSelect.options.length > 0) {
        recipientTypeSelect.remove(0);
    }
    
    // Add default option
    const defaultOption = document.createElement('option');
    defaultOption.value = '';
    defaultOption.textContent = 'Select recipient type';
    defaultOption.disabled = true;
    defaultOption.selected = true;
    recipientTypeSelect.appendChild(defaultOption);
    
    // Get user role from global variable
    const userRole = window.currentUserRole || 'librarian'; // Default to librarian if not set
    
    // Add options based on user role
    if (userRole === 'librarian') {
        // Librarians can message supervisors or members
        const option1 = document.createElement('option');
        option1.value = 'supervisor';
        option1.textContent = 'Supervisor';
        recipientTypeSelect.appendChild(option1);
        
        const option2 = document.createElement('option');
        option2.value = 'all_members';
        option2.textContent = 'All Members';
        recipientTypeSelect.appendChild(option2);
        
        const option3 = document.createElement('option');
        option3.value = 'individual_member';
        option3.textContent = 'Individual Member';
        recipientTypeSelect.appendChild(option3);
    } else if (userRole === 'supervisor') {
        // Supervisors can message librarians (all or individual) but NOT members
        const option1 = document.createElement('option');
        option1.value = 'all_librarians';
        option1.textContent = 'All Librarians';
        recipientTypeSelect.appendChild(option1);
        
        const option2 = document.createElement('option');
        option2.value = 'individual_librarian';
        option2.textContent = 'Individual Librarian';
        recipientTypeSelect.appendChild(option2);
    } else if (userRole === 'member') {
        // Members can message librarians
        const option1 = document.createElement('option');
        option1.value = 'librarian';
        option1.textContent = 'Librarian';
        recipientTypeSelect.appendChild(option1);
        
        const option2 = document.createElement('option');
        option2.value = 'individual_librarian';
        option2.textContent = 'Individual Librarian';
        recipientTypeSelect.appendChild(option2);
    }
    
    // Trigger change event to load recipients if needed
    $(recipientTypeSelect).trigger('change');
}

// Function to load recipients based on type
function loadRecipients(recipientType) {
    const $individualRecipientGroup = $('#individual_recipient_group');
    const $individualRecipientSelect = $('#individual_recipient');
    
    if (!$individualRecipientSelect.length) return;
    
    // For individual member selection, replace with search input
    // For supervisors selecting individual librarians, use search input as well
    if ((window.currentUserRole === 'librarian' && recipientType === 'individual_member') || 
        (window.currentUserRole === 'member' && recipientType === 'individual_librarian') ||
        (window.currentUserRole === 'supervisor' && recipientType === 'individual_librarian')) {
        
        // Show the individual recipient group
        $individualRecipientGroup.show();
        
        // Always hide the select dropdown
        $individualRecipientSelect.hide();
        
        // Check if search container already exists
        let $searchContainer = $('#memberSearchContainer');
        
        if ($searchContainer.length === 0) {
            // Create search container
            $searchContainer = $('<div id="memberSearchContainer" class="form-group"></div>');
            
            // Create hidden input to store the selected member ID
            const hiddenInput = $('<input type="hidden" name="recipient_id" id="selectedMemberId">');
            
            // Create search input
            const searchInput = $('<input type="text" class="form-control" id="memberSearchInput" placeholder="Type to search for a recipient...">');
            
            // Create results container
            const resultsContainer = $('<div id="memberSearchResults" class="mt-2" style="display:none;"></div>');
            
            // Append elements
            $searchContainer.append(hiddenInput, searchInput, resultsContainer);
            
            // Insert the search container after the select element
            $individualRecipientSelect.after($searchContainer);
            
            // Initialize search functionality
            let searchTimeout;
            
            searchInput.on('input', function() {
                const searchTerm = $(this).val().trim();
                
                // Clear previous timeout
                clearTimeout(searchTimeout);
                
                if (searchTerm.length < 2) {
                    resultsContainer.hide().empty();
                    return;
                }
                
                // Show loading
                resultsContainer.html('<div class="text-muted p-2">Searching for recipients...</div>').show();
                
                // Debounce the search
                searchTimeout = setTimeout(() => {
                    // Determine search endpoint based on user role and recipient type
                    let searchEndpoint = '';
                    
                    if (window.currentUserRole === 'librarian' && recipientType === 'individual_member') {
                        searchEndpoint = 'search_members.php';
                    } else if ((window.currentUserRole === 'member' || window.currentUserRole === 'supervisor') && recipientType === 'individual_librarian') {
                        searchEndpoint = '../supervisor/search_librarians.php';
                    }
                    
                    if (!searchEndpoint) {
                        resultsContainer.html('<div class="text-danger p-2">Invalid search configuration</div>');
                        return;
                    }
                    
                    // Show loading indicator
                    resultsContainer.html('<div class="text-muted p-2">Searching for recipients...</div>').show();
                    
                    // Make AJAX request to search for recipients
                    $.ajax({
                        url: searchEndpoint,
                        dataType: 'json',
                        data: { 
                            search: searchTerm,  // The PHP script expects 'search' parameter
                            _: new Date().getTime() // Cache buster
                        },
                        success: function(response) {
                            console.log('Search response:', response); // Debug log
                            
                            // Clear previous results
                            resultsContainer.empty();
                            
                            if (!response) {
                                resultsContainer.html('<div class="text-muted p-2">No results found</div>');
                                return;
                            }
                            
                            // Handle response format
                            let recipients = [];
                            if (response.results && Array.isArray(response.results)) {
                                recipients = response.results;
                            } else if (Array.isArray(response)) {
                                recipients = response;
                            } else if (response.librarians && Array.isArray(response.librarians)) {
                                recipients = response.librarians;
                            } else if (response.success && response.librarians) {
                                recipients = response.librarians;
                            }
                            
                            if (!recipients || !recipients.length) {
                                resultsContainer.html('<div class="text-muted p-2">No recipients found matching your search</div>');
                                return;
                            }
                            
                            // Add results
                            recipients.forEach(recipient => {
                                const displayText = recipient.text || 
                                    `${recipient.first_name || ''} ${recipient.last_name || ''} (${recipient.email || ''})`.trim();
                                const userId = recipient.id || recipient.user_id || '';
                                
                                if (!userId) return; // Skip if no user ID
                                
                                const resultItem = $('<div class="search-result-item p-2 border-bottom"></div>')
                                    .html(`
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>${displayText}</strong><br>
                                                <small class="text-muted">${recipient.email || ''}</small>
                                            </div>
                                            <button class="btn btn-sm btn-primary select-recipient">
                                                Select
                                            </button>
                                        </div>
                                    `)
                                    .on('click', function(e) {
                                        // Only trigger on the button click, not the entire row
                                        if (!$(e.target).closest('.select-recipient').length) return;
                                        
                                        // Update hidden input with selected recipient's numeric ID
                                        hiddenInput.val(userId);
                                        
                                        // Update search input with selected recipient
                                        searchInput.val(displayText);
                                        
                                        // Hide results
                                        resultsContainer.hide();
                                        
                                        console.log('Selected recipient - ID:', userId, 'Text:', displayText);
                                    })
                                    .css({
                                        'cursor': 'pointer',
                                        'transition': 'all 0.2s ease',
                                        'border-radius': '4px',
                                        'margin': '2px 0',
                                        'padding': '8px 12px'
                                    })
                                    .hover(
                                        function() { 
                                            $(this).css({
                                                'background-color': '#f0f7ff',
                                                'box-shadow': '0 2px 4px rgba(0,0,0,0.1)',
                                                'transform': 'translateX(2px)'
                                            }); 
                                        },
                                        function() { 
                                            $(this).css({
                                                'background-color': 'transparent',
                                                'box-shadow': 'none',
                                                'transform': 'translateX(0)'
                                            }); 
                                        }
                                    );
                                
                                resultsContainer.append(resultItem);
                            });
                            
                            resultsContainer.show();
                        },
                        error: function(xhr, status, error) {
                            console.error('Search error:', status, error);
                            resultsContainer.html('<div class="text-danger p-2">Error searching for recipients. Please try again.</div>');
                        }
                    });
                }, 300); // 300ms debounce
            });
            
            // Hide results when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#memberSearchContainer').length) {
                    resultsContainer.hide();
                }
            });
        }
        
        return;
    }
    
    // For non-searchable selects, use the existing logic
    // First, clean up any search UI if it exists
    $('#memberSearchContainer').remove();
    $individualRecipientSelect.show();
    
    // Rest of the existing code for non-searchable selects
    $individualRecipientSelect.html('<option value="">Loading...</option>');
    
    // Determine endpoint based on recipient type and user role
    let endpoint = '';
    
    if (window.currentUserRole === 'librarian') {
        if (recipientType === 'individual_member') {
            endpoint = 'get_members.php';
        } else if (recipientType === 'supervisor') {
            endpoint = 'get_supervisors.php';
        } else if (recipientType === 'all_members') {
            $individualRecipientSelect.html('<option value="all">All Members</option>');
            return;
        }
    } else if (window.currentUserRole === 'supervisor') {
        if (recipientType === 'individual_librarian') {
            endpoint = 'get_librarians.php';
        } else if (recipientType === 'all_librarians') {
            $individualRecipientSelect.html('<option value="all">All Librarians</option>');
            return;
        }
    } else if (window.currentUserRole === 'member') {
        if (recipientType === 'individual_librarian') {
            endpoint = '../supervisor/get_librarians.php';
        } else if (recipientType === 'librarian') {
            $individualRecipientSelect.html('<option value="all">Any Available Librarian</option>');
            return;
        }
    }
    
    if (!endpoint) {
        console.error('No endpoint defined for recipient type:', recipientType, 'and role:', window.currentUserRole);
        $individualRecipientSelect.html('<option value="">Error: Invalid recipient type</option>');
        return;
    }
    
    // Make AJAX request to get recipients
    fetch(endpoint)
        .then(response => response.json())
        .then(data => {
            if (!data) {
                throw new Error('No data received');
            }
            
            let options = '<option value="">Select a recipient</option>';
            let recipients = [];
            
            // Check which data field exists in the response
            if (data.recipients && Array.isArray(data.recipients)) {
                recipients = data.recipients;
            } else if (data.librarians && Array.isArray(data.librarians)) {
                recipients = data.librarians;
            } else if (data.members && Array.isArray(data.members)) {
                recipients = data.members;
            } else if (data.supervisors && Array.isArray(data.supervisors)) {
                recipients = data.supervisors;
            } else if (Array.isArray(data)) {
                recipients = data;
            } else if (data.success && data.librarians && Array.isArray(data.librarians)) {
                recipients = data.librarians;
            } else if (data.success && data.members && Array.isArray(data.members)) {
                recipients = data.members;
            }
            
            if (recipients.length > 0) {
                recipients.forEach(recipient => {
                    const name = (recipient.first_name || '') + ' ' + (recipient.last_name || '').trim();
                    const email = recipient.email || '';
                    const displayText = name.trim() ? `${name} (${email})` : email;
                    const userId = recipient.user_id || recipient.id || '';
                    
                    options += `<option value="${userId}">${displayText}</option>`;
                });
            } else {
                options = '<option value="">No recipients found</option>';
            }
            
            $individualRecipientSelect.html(options);
        })
        .catch(error => {
            console.error('Error loading recipients:', error);
            $individualRecipientSelect.html('<option value="">Error loading recipients</option>');
        });
}

// Function to attach click listeners to messages
function attachMessageClickListeners() {
    // Make messages clickable to open chat
    document.querySelectorAll('.message-item').forEach(item => {
        item.addEventListener('click', function(e) {
            // Don't trigger if clicking on action buttons
            if (e.target.closest('.message-action-btn') || e.target.closest('form') || e.target.closest('.message-actions')) {
                return;
            }
            
            const senderId = this.dataset.senderId;
            const senderName = this.dataset.senderName;
            const recipientId = this.dataset.recipientId;
            const recipientName = this.dataset.recipientName;
            
            // For messages in inbox, we want to chat with the sender
            // For messages in sent, we want to chat with the recipient
            if (senderId || recipientId) {
                const chatUserId = senderId || recipientId;
                const chatUserName = senderName || recipientName;
                openChat(chatUserId, chatUserName);
            }
        });
    });
    
    // Add event listeners for message action buttons
    document.querySelectorAll('.message-action-btn').forEach(button => {
        // Remove any existing event listeners to prevent duplicates
        button.removeEventListener('click', handleMessageAction);
        button.addEventListener('click', handleMessageAction);
    });
}

// Handle message action buttons (star, read)
function handleMessageAction(e) {
    e.stopPropagation();
    const messageItem = this.closest('.message-item');
    const messageId = messageItem.dataset.messageId;
    const action = this.dataset.action;
    
    // Send AJAX request to update message status
    fetch('update_message_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `message_id=${encodeURIComponent(messageId)}&action=${encodeURIComponent(action)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            console.error('Error updating message status:', data.error);
            showToast('Error updating message: ' + data.error, 'error');
            return;
        }
        
        if (data.action === 'star') {
            // Toggle starred state
            this.classList.toggle('starred', data.is_starred);
            this.title = data.is_starred ? 'Unstar message' : 'Star message';
            
            // Update all instances of this message across different views
            updateAllMessageInstances(messageId, data.is_starred);
            
            // Show toast notification
            if (data.is_starred) {
                showToast('Message starred successfully!', 'success');
            } else {
                showToast('Message unstarred successfully!', 'success');
            }
        } else if (data.action === 'read') {
            // Remove unread class and read button
            messageItem.classList.remove('unread');
            const readButton = messageItem.querySelector('[data-action="read"]');
            if (readButton) {
                readButton.remove();
            }
            
            // Update the inbox count in real-time
            if (typeof updateInboxCount === 'function') {
                updateInboxCount();
            }
            
            // Check if we should hide the "Mark All as Read" button
            checkAndToggleMarkAllReadButton();
            
            // Show toast notification
            showToast('Message marked as read!', 'success');
        }
    })
    .catch(error => {
        console.error('Error updating message status:', error);
        showToast('Error updating message status: ' + error.message, 'error');
    });
}

// Check if we should show or hide the "Mark All as Read" button
function checkAndToggleMarkAllReadButton() {
    // Only show the button if we're in the inbox view and there are unread messages
    const inboxView = document.getElementById('inbox-view');
    const markAllReadBtn = document.getElementById('markAllRead');
    
    if (inboxView && markAllReadBtn) {
        // Check if the inbox view is currently displayed
        if (inboxView.style.display !== 'none') {
            // Check if there are any unread messages
            const unreadMessages = document.querySelectorAll('.message-item.unread');
            if (unreadMessages.length > 0) {
                markAllReadBtn.style.display = 'inline-block';
            } else {
                markAllReadBtn.style.display = 'none';
            }
        }
    }
}

// Update all instances of a message across different views
function updateAllMessageInstances(messageId, isStarred) {
    // Find all message items with this message ID
    const allMessageItems = document.querySelectorAll(`[data-message-id="${messageId}"]`);
    
    // Update star button in each instance
    allMessageItems.forEach(item => {
        const starButton = item.querySelector('[data-action="star"]');
        if (starButton) {
            starButton.classList.toggle('starred', isStarred);
            starButton.title = isStarred ? 'Unstar message' : 'Star message';
        }
    });
    
    // Update the starred messages list in real-time
    // Use the first message item that has the necessary data attributes
    let messageItemToClone = allMessageItems[0];
    // Prefer an item that has sender or recipient data
    for (let i = 0; i < allMessageItems.length; i++) {
        if (allMessageItems[i].dataset.senderId || allMessageItems[i].dataset.recipientId) {
            messageItemToClone = allMessageItems[i];
            break;
        }
    }
    
    updateStarredMessagesList(messageId, isStarred, messageItemToClone);
}

// Update the starred messages list in real-time
function updateStarredMessagesList(messageId, isStarred, originalMessageItem) {
    // Get the starred messages view
    const starredView = document.getElementById('starred-view');
    if (!starredView) return;
    
    if (isStarred) {
        // If message is now starred, add it to the starred list
        // Instead of cloning, let's recreate the message item from the original data
        const messageData = {
            messageId: originalMessageItem.dataset.messageId,
            senderId: originalMessageItem.dataset.senderId,
            senderName: originalMessageItem.dataset.senderName,
            recipientId: originalMessageItem.dataset.recipientId,
            recipientName: originalMessageItem.dataset.recipientName,
            subject: originalMessageItem.querySelector('.message-subject') ? originalMessageItem.querySelector('.message-subject').textContent : '',
            preview: originalMessageItem.querySelector('.message-preview') ? originalMessageItem.querySelector('.message-preview').textContent : '',
            time: originalMessageItem.querySelector('.message-time') ? originalMessageItem.querySelector('.message-time').textContent : '',
            isRead: !originalMessageItem.classList.contains('unread')
        };
        
        // Create new message item HTML
        const messageHtml = `
            <li class="message-item ${messageData.isRead ? '' : 'unread'}" 
                data-message-id="${messageData.messageId}"
                ${messageData.senderId ? `data-sender-id="${messageData.senderId}"` : ''}
                ${messageData.senderName ? `data-sender-name="${messageData.senderName}"` : ''}
                ${messageData.recipientId ? `data-recipient-id="${messageData.recipientId}"` : ''}
                ${messageData.recipientName ? `data-recipient-name="${messageData.recipientName}"` : ''}>
                <div class="message-header">
                    <div class="message-sender">${messageData.senderId ? messageData.senderName : 'To: ' + messageData.recipientName}</div>
                    <div class="message-time">${messageData.time}</div>
                </div>
                <div class="message-subject">${messageData.subject}</div>
                <div class="message-preview">${messageData.preview}</div>
                <div class="message-actions">
                    <button class="message-action-btn starred" 
                            data-action="star" 
                            title="Unstar message">
                        <i class="fas fa-star"></i>
                    </button>
                    ${messageData.isRead ? '' : `
                    <button class="message-action-btn" 
                            data-action="read" 
                            title="Mark as read">
                        <i class="fas fa-envelope-open"></i>
                    </button>`}
                </div>
            </li>
        `;
        
        // Get or create the message list
        let messageList = starredView.querySelector('.message-list');
        if (!messageList) {
            // Create message list if it doesn't exist
            messageList = document.createElement('ul');
            messageList.className = 'message-list';
            starredView.appendChild(messageList);
        }
        
        // Check if message already exists in starred list
        const existingMessage = messageList.querySelector(`[data-message-id="${messageId}"]`);
        if (!existingMessage) {
            // Insert at the beginning
            messageList.insertAdjacentHTML('afterbegin', messageHtml);
            
            // Attach event listeners to the new buttons
            const newMessageItem = messageList.firstElementChild;
            const newButtons = newMessageItem.querySelectorAll('.message-action-btn');
            newButtons.forEach(button => {
                button.addEventListener('click', handleMessageAction);
            });
        }
        
        // Hide the "no messages" message if it exists
        const noMessages = starredView.querySelector('.no-messages');
        if (noMessages) {
            noMessages.style.display = 'none';
        }
    } else {
        // If message is now unstarred, remove it from the starred list
        const starredMessage = starredView.querySelector(`[data-message-id="${messageId}"]`);
        if (starredMessage) {
            starredMessage.remove();
            
            // Check if starred list is now empty
            const messageList = starredView.querySelector('.message-list');
            if (messageList && messageList.children.length === 0) {
                // Show no messages message
                const noMessages = starredView.querySelector('.no-messages');
                if (noMessages) {
                    noMessages.style.display = 'block';
                }
            }
        }
    }
    
    // Re-attach message click listeners
    attachMessageClickListeners();
}

// Update the starred messages count in the sidebar
function updateStarredCount() {
    // Get the current starred count from the server
    fetch('get_starred_count.php')
        .then(response => response.json())
        .then(data => {
            if (!data.error) {
                // Update the starred count in the left pane
                const starredLink = document.querySelector('.sidebar-menu a[data-view="starred"]');
                if (starredLink) {
                    // For now, we're just ensuring the UI updates properly
                    // In a more complete implementation, we might show the actual count
                }
            }
        })
        .catch(error => {
            console.error('Error updating starred count:', error);
        });
}

// Update the inbox count in real-time
function updateInboxCount() {
    // Fetch the new unread count
    fetch('ajax_message_count.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data && typeof data.unread_count !== 'undefined') {
                // Update the inbox count in the left pane
                const inboxLink = document.querySelector('.sidebar-menu a[data-view="inbox"]');
                if (inboxLink) {
                    let badge = inboxLink.querySelector('.message-count');
                    
                    if (parseInt(data.unread_count) > 0) {
                        if (badge) {
                            badge.textContent = data.unread_count;
                        } else {
                            // Create badge if it doesn't exist
                            badge = document.createElement('span');
                            badge.className = 'message-count';
                            badge.textContent = data.unread_count;
                            inboxLink.appendChild(badge);
                        }
                    } else {
                        // Remove badge if count is 0
                        if (badge) {
                            badge.remove();
                        }
                    }
                }
                
                // Also update the navbar badge (in case it's not already being updated)
                const messagesLink = document.querySelector('.nav-link[href="messages.php"]');
                if (messagesLink) {
                    let badge = messagesLink.querySelector('.notification-badge');
                    
                    if (parseInt(data.unread_count) > 0) {
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
                
                // Check if we should hide the "Mark All as Read" button
                checkAndToggleMarkAllReadButton();
            } else {
                console.error('Invalid response format from server:', data);
            }
        })
        .catch(error => {
            console.error('Error updating inbox count:', error);
        });
}

// Send compose message
function sendComposeMessage() {
    console.log('sendComposeMessage called');
    
    const form = document.getElementById('composeForm');
    const recipientType = document.getElementById('recipient_type').value;
    
    // Debug: Log the recipient type
    console.log('Recipient Type:', recipientType);
    
    // Check for both individual_recipient select and selectedMemberId hidden input
    const individualRecipientSelect = document.getElementById('individual_recipient');
    const selectedMemberId = document.getElementById('selectedMemberId');
    
    console.log('individualRecipientSelect:', individualRecipientSelect);
    console.log('selectedMemberId:', selectedMemberId);
    
    let individualRecipient = '';
    if (individualRecipientSelect && individualRecipientSelect.value) {
        individualRecipient = individualRecipientSelect.value;
        console.log('Using individualRecipientSelect value:', individualRecipient);
    } else if (selectedMemberId && selectedMemberId.value) {
        individualRecipient = selectedMemberId.value;
        console.log('Using selectedMemberId value:', individualRecipient);
    }
    
    const subject = document.getElementById('subject').value.trim();
    const messageBody = document.getElementById('message_body').value.trim();
    
    // Debug logs
    console.log('Form validation - Recipient Type:', recipientType);
    console.log('Form validation - Individual Recipient:', individualRecipient);
    console.log('Form validation - Subject:', subject);
    console.log('Form validation - Message Body:', messageBody);
    
    // Validate form
    if (!recipientType || !subject || !messageBody) {
        console.log('Validation failed - missing required fields');
        showToast('Please fill in all required fields.', 'warning');
        return;
    }
    
    // If individual recipient is selected, validate that one is chosen
    if (recipientType.includes('individual') && !individualRecipient) {
        console.log('Validation failed - no recipient selected');
        console.log('recipientType:', recipientType);
        console.log('individualRecipient:', individualRecipient);
        console.log('individualRecipientSelect value:', individualRecipientSelect ? individualRecipientSelect.value : 'N/A');
        console.log('selectedMemberId value:', selectedMemberId ? selectedMemberId.value : 'N/A');
        showToast('Please select a recipient.', 'warning');
        return;
    }
    
    // Get user role
    const userRole = window.currentUserRole || 'librarian'; // Default to librarian if not set
    
    // Validate recipient type based on user role
    let isValid = true;
    let errorMessage = '';
    
    if (userRole === 'librarian') {
        // Librarians can message supervisors or members
        if (recipientType !== 'supervisor' && recipientType !== 'all_members' && recipientType !== 'individual_member') {
            isValid = false;
            errorMessage = 'Librarians can only send messages to supervisors or members.';
        }
    } else if (userRole === 'member') {
        // Members can only message librarians
        if (recipientType !== 'librarian' && recipientType !== 'individual_librarian') {
            isValid = false;
            errorMessage = 'Members can only send messages to librarians.';
        }
    } else if (userRole === 'supervisor') {
        // Supervisors can message all librarians or individual librarians
        if (recipientType !== 'all_librarians' && recipientType !== 'individual_librarian') {
            isValid = false;
            errorMessage = 'Supervisors can only send messages to librarians.';
        }
    }
    
    if (!isValid) {
        console.log('Validation failed - invalid recipient type for user role');
        console.log('User Role:', userRole);
        console.log('Recipient Type:', recipientType);
        showToast(errorMessage, 'error');
        return;
    }
    
    console.log('Sending message with data:', {
        recipient_type: recipientType,
        individual_recipient: individualRecipient,
        subject: subject,
        message_body: messageBody
    });
    
    // Send message via AJAX
    const formData = new FormData();
    formData.append('recipient_type', recipientType);
    formData.append('individual_recipient', individualRecipient);
    formData.append('subject', subject);
    formData.append('message_body', messageBody);
    
    fetch('send_message.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Server response:', data);
        if (data.success) {
            showToast('Message sent successfully!', 'success');
            closeCompose();
            form.reset();
            
            // Hide individual recipient group
            const individualRecipientGroup = document.getElementById('individual_recipient_group');
            if (individualRecipientGroup) {
                individualRecipientGroup.style.display = 'none';
            }
            
            // Clear the selected member ID
            if (selectedMemberId) {
                selectedMemberId.value = '';
            }
            
            // Refresh sent messages list
            refreshSentMessages();
        } else {
            console.error('Error from server:', data.error);
            showToast('Error sending message: ' + (data.error || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error sending message:', error);
        showToast('Error sending message: ' + error.message, 'error');
    });
}

// Close compose modal
function closeCompose() {
    const composeModal = document.getElementById('composeModal');
    if (composeModal) {
        composeModal.style.display = 'none';
        // Remove active state from compose button to maintain consistent UI feedback
        if (window.composeButton) {
            window.composeButton.classList.remove('active');
        }
    }
}

// Function to refresh sent messages list
function refreshSentMessages() {
    console.log('Refreshing sent messages...');
    
    // Show loading state
    const sentView = document.getElementById('sent-view');
    if (sentView) {
        sentView.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading messages...</p>
            </div>
        `;
    }
    
    // Fetch updated sent messages
    fetch('fetch_messages.php?view=sent&_=' + new Date().getTime())
        .then(async response => {
            const contentType = response.headers.get('content-type');
            
            // Check if response is JSON
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Expected JSON but got:', contentType, text.substring(0, 200));
                throw new Error(`Invalid response type: ${contentType}`);
            }
            
            if (!response.ok) {
                const error = await response.json().catch(() => ({}));
                throw new Error(error.error || `HTTP error! status: ${response.status}`);
            }
            
            return response.json();
        })
        .then(data => {
            console.log('Received messages data:', data);
            
            if (!data) {
                throw new Error('No data received from server');
            }
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            if (!data.success) {
                throw new Error(data.error || 'Failed to load messages');
            }
            
            // Get the sent messages view
            const sentView = document.getElementById('sent-view');
            if (!sentView) {
                throw new Error('Sent view element not found');
            }
            
            // Update the sent messages list
            if (data.messages && data.messages.length > 0) {
                let messagesHtml = '<ul class="message-list">';
                
                data.messages.forEach(message => {
                    if (!message || typeof message !== 'object') {
                        console.warn('Skipping invalid message format:', message);
                        return;
                    }
                    
                    try {
                        // Ensure required fields exist
                        const messageId = message.id || '';
                        const recipientId = message.recipient_id || '';
                        const recipientName = message.recipient_name || 'Unknown Recipient';
                        const subject = message.subject || '(No subject)';
                        const messageText = message.message || '';
                        const isStarred = Boolean(message.is_starred);
                        
                        // Safely format the time
                        let formattedTime = 'Unknown time';
                        try {
                            formattedTime = formatMessageTime(message.message_time || message.created_at || new Date().toISOString());
                        } catch (e) {
                            console.warn('Error formatting date:', e);
                        }
                        
                        // Create message preview (first 100 chars)
                        const preview = messageText.length > 100 
                            ? messageText.substring(0, 100) + '...' 
                            : messageText;
                        
                        messagesHtml += `
                            <li class="message-item" 
                                data-recipient-id="${escapeHtml(recipientId)}" 
                                data-recipient-name="${escapeHtml(recipientName)}"
                                data-message-id="${escapeHtml(messageId)}">
                                <div class="message-header">
                                    <div class="message-sender">To: ${escapeHtml(recipientName)}</div>
                                    <div class="message-time">${formattedTime}</div>
                                </div>
                                <div class="message-subject">${escapeHtml(subject)}</div>
                                <div class="message-preview">${escapeHtml(preview)}</div>
                                <div class="message-actions">
                                    <button class="message-action-btn ${isStarred ? 'starred' : ''}" 
                                            data-action="star" 
                                            data-message-id="${escapeHtml(messageId)}"
                                            title="${isStarred ? 'Unstar message' : 'Star message'}">
                                        <i class="fas fa-star"></i>
                                    </button>
                                </div>
                            </li>
                        `;
                    } catch (e) {
                        console.error('Error rendering message:', e, message);
                    }
                });
                
                messagesHtml += '</ul>';
                
                // Update the sent view content
                sentView.innerHTML = messagesHtml;
                
                // Attach event listeners to the new buttons
                const newButtons = sentView.querySelectorAll('.message-action-btn');
                newButtons.forEach(button => {
                    button.addEventListener('click', handleMessageAction);
                });
            } else {
                // Show no messages message
                sentView.innerHTML = `
                    <div class="no-messages">
                        <i class="fas fa-paper-plane"></i>
                        <h3>No Sent Messages</h3>
                        <p>You haven't sent any messages yet.</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error in refreshSentMessages:', error);
            const sentView = document.getElementById('sent-view');
            if (sentView) {
                sentView.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h4>Error Loading Messages</h4>
                        <p>${escapeHtml(error.message || 'There was a problem loading your sent messages.')}</p>
                        <button class="btn btn-sm btn-outline-secondary mt-2" onclick="refreshSentMessages()">
                            <i class="fas fa-sync-alt"></i> Retry
                        </button>
                        <div class="mt-2 small text-muted">
                            <a href="#" onclick="event.preventDefault(); console.error('Full error:', ${JSON.stringify(error.toString())});">
                                Show technical details
                            </a>
                        </div>
                    </div>
                `;
            }
        });
}

// Helper function to escape HTML
function escapeHtml(unsafe) {
    if (typeof unsafe !== 'string') return unsafe;
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Helper function to format message time
function formatMessageTime(timeString) {
    try {
        if (!timeString) return 'Unknown time';
        
        // If it's already a Date object, use it directly
        const date = typeof timeString === 'string' ? new Date(timeString) : timeString;
        
        // Check if the date is valid
        if (isNaN(date.getTime())) {
            console.warn('Invalid date string:', timeString);
            return 'Invalid date';
        }
        
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
    } catch (e) {
        console.error('Error formatting date:', e, 'Input was:', timeString);
        return 'Invalid date';
    }
}

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

// Open chat function
function openChat(userId, userName) {
    // Create chat modal if it doesn't exist
    let chatModal = document.getElementById('chatModal');
    if (!chatModal) {
        chatModal = document.createElement('div');
        chatModal.id = 'chatModal';
        chatModal.className = 'chat-modal';
        chatModal.innerHTML = `
            <div class="chat-modal-content">
                <div class="chat-header">
                    <h2><i class="fas fa-comments"></i> Chat with <span id="chatUserName"></span></h2>
                    <button class="close" id="closeChat">&times;</button>
                </div>
                <div class="chat-body">
                    <div class="chat-messages" id="chatMessages">
                        <div class="loading">Loading messages...</div>
                    </div>
                </div>
                <div class="chat-footer">
                    <input type="text" class="chat-input" id="chatInput" placeholder="Type your message...">
                    <button class="chat-send-btn" id="sendChat"><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>
        `;
        document.body.appendChild(chatModal);
        
        // Add event listeners
        document.getElementById('closeChat').addEventListener('click', closeChat);
        document.getElementById('sendChat').addEventListener('click', sendMessage);
        document.getElementById('chatInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
        
        // Close modal when clicking outside
        chatModal.addEventListener('click', function(e) {
            if (e.target === chatModal) {
                closeChat();
            }
        });
    }
    
    // Set user info
    document.getElementById('chatUserName').textContent = userName;
    chatModal.dataset.userId = userId;
    
    // Show modal
    chatModal.style.display = 'block';
    
    // Prevent background scrolling
    document.body.style.overflow = 'hidden';
    
    // Load messages
    loadChatMessages(userId);
    
    // Focus on input
    document.getElementById('chatInput').focus();
}

// Close chat function
function closeChat() {
    const chatModal = document.getElementById('chatModal');
    if (chatModal) {
        chatModal.style.display = 'none';
    }
    
    // Restore background scrolling
    document.body.style.overflow = '';
}

// Load chat messages function
function loadChatMessages(userId) {
    const chatMessages = document.getElementById('chatMessages');
    chatMessages.innerHTML = '<div class="loading">Loading messages...</div>';
    
    // Make AJAX request to get chat messages
    fetch(`get_chat_messages.php?user_id=${encodeURIComponent(userId)}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                chatMessages.innerHTML = `<div class="error">Error loading messages: ${data.error}</div>`;
                return;
            }
            
            if (data.success && data.messages.length > 0) {
                let messagesHtml = '';
                
                data.messages.forEach(message => {
                    // Determine if the message is sent or received based on the sender_id
                    const isSent = message.sender_id === window.currentUserId;
                    const messageClass = isSent ? 'sent' : 'received';
                    const time = new Date(message.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    
                    messagesHtml += `
                        <div class="chat-message ${messageClass}">
                            <div class="message-content">${message.message}</div>
                            <div class="message-time">${time}</div>
                        </div>
                    `;
                });
                
                chatMessages.innerHTML = messagesHtml;
                
                // Update the inbox count since messages have been marked as read
                if (typeof updateInboxCount === 'function') {
                    updateInboxCount();
                }
            } else {
                chatMessages.innerHTML = '<div class="no-messages">No messages yet. Start the conversation!</div>';
            }
            
            // Scroll to bottom
            chatMessages.scrollTop = chatMessages.scrollHeight;
        })
        .catch(error => {
            chatMessages.innerHTML = `<div class="error">Error loading messages: ${error.message}</div>`;
        });
}

// Send message function
function sendMessage() {
    const chatInput = document.getElementById('chatInput');
    const message = chatInput.value.trim();
    const chatModal = document.getElementById('chatModal');
    
    if (message && chatModal) {
        const userId = chatModal.dataset.userId;
        
        // Add message to UI immediately
        const chatMessages = document.getElementById('chatMessages');
        const messageElement = document.createElement('div');
        messageElement.className = 'chat-message sent';
        messageElement.innerHTML = `
            <div class="message-content">${message}</div>
            <div class="message-time">${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
        `;
        chatMessages.appendChild(messageElement);
        
        // Clear input and scroll to bottom
        chatInput.value = '';
        chatMessages.scrollTop = chatMessages.scrollHeight;
        
        // Send message to server
        fetch('send_chat_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `recipient_id=${encodeURIComponent(userId)}&message=${encodeURIComponent(message)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                // Show error
                messageElement.classList.add('error');
                messageElement.innerHTML += `<div class="error-message">Failed to send: ${data.error}</div>`;
            }
            // If successful, the message is already in the UI
        })
        .catch(error => {
            // Show error
            messageElement.classList.add('error');
            messageElement.innerHTML += `<div class="error-message">Failed to send: ${error.message}</div>`;
        });
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', initMessagesPage);
