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

$pageTitle = 'Notifications';
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

        .controls-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .filter-options {
            display: flex;
            gap: 1rem;
        }

        .filter-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #ced4da;
            background: #ffffff;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-btn.active,
        .filter-btn:hover {
            background: #3498DB;
            color: white;
            border-color: #3498DB;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
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
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #3498DB;
            color: #3498DB;
        }

        .btn-outline:hover {
            background: #f1f8ff;
        }

        .notifications-container {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 0;
            overflow: hidden;
        }

        .notification-item {
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            gap: 1rem;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item.unread {
            background: #f1f8ff;
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9f4ff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .notification-icon.info {
            background: #e9f4ff;
            color: #3498DB;
        }

        .notification-icon.warning {
            background: #fff8e9;
            color: #f39c12;
        }

        .notification-icon.success {
            background: #e9ffe9;
            color: #27ae60;
        }

        .notification-icon.error {
            background: #ffe9e9;
            color: #e74c3c;
        }

        .notification-content {
            flex: 1;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .notification-title {
            font-weight: 600;
            color: #495057;
            margin: 0;
        }

        .notification-time {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .notification-message {
            color: #6c757d;
            margin: 0 0 0.5rem 0;
            line-height: 1.5;
        }

        .notification-actions {
            display: flex;
            gap: 0.5rem;
        }

        .notification-action {
            padding: 0.25rem 0.75rem;
            border: 1px solid #ced4da;
            background: #ffffff;
            border-radius: 4px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .notification-action:hover {
            background: #f8f9fa;
        }

        .notification-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
        }

        .notification-count {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .pagination {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .page-btn {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #ced4da;
            background: #ffffff;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .page-btn.active,
        .page-btn:hover:not(.disabled) {
            background: #3498DB;
            color: white;
            border-color: #3498DB;
        }

        .page-btn.disabled {
            background: #f8f9fa;
            color: #6c757d;
            cursor: not-allowed;
            border-color: #e9ecef;
        }

        .page-ellipsis {
            padding: 0 0.5rem;
            color: #6c757d;
        }

        .no-notifications {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .no-notifications i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #ced4da;
        }
        
        /* Enhanced Modal Styles */
        .modal {
            display: none; /* Hide modal */
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow: auto;
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
        }

        .modal-content {
            background-color: #ffffff;
            margin: 5% auto;
            padding: 0;
            border-radius: 16px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 650px;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border: none;
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .close {
            color: white;
            font-size: 2rem;
            font-weight: bold;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close:hover {
            background-color: rgba(255,255,255,0.2);
        }

        .modal-body {
            padding: 1.5rem;
            flex: 1;
            overflow-y: auto;
            background-color: #f8f9fa;
        }

        .settings-section {
            display: none; /* Hide settings section */
        }

        .form-group {
            display: none; /* Hide form groups */
        }

        .checkbox-label {
            display: none; /* Hide checkbox labels */
        }

        .form-control {
            display: none; /* Hide form controls */
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            padding: 1rem 1.5rem;
            border-top: 1px solid #e9ecef;
            background: #ffffff;
        }
        
        /* Notification Details Modal Styles */
        .notification-details {
            text-align: center;
        }
        
        .notification-icon-large {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #e9f4ff;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            color: #3498DB;
        }
        
        .notification-icon-large.info {
            background: #e9f4ff;
            color: #3498DB;
        }
        
        .notification-icon-large.warning {
            background: #fff8e9;
            color: #f39c12;
        }
        
        .notification-icon-large.success {
            background: #e9ffe9;
            color: #27ae60;
        }
        
        .notification-icon-large.error {
            background: #ffe9e9;
            color: #e74c3c;
        }
        
        .notification-title {
            color: #495057;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .notification-meta {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .notification-type {
            background: #e9ecef;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .notification-type.info {
            background: #e9f4ff;
            color: #3498DB;
        }
        
        .notification-type.warning {
            background: #fff8e9;
            color: #f39c12;
        }
        
        .notification-type.success {
            background: #e9ffe9;
            color: #27ae60;
        }
        
        .notification-type.error {
            background: #ffe9e9;
            color: #e74c3c;
        }
        
        .notification-message-full {
            text-align: left;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #3498DB;
        }
        
        .notification-message-full p {
            margin: 0;
            line-height: 1.6;
            color: #495057;
        }
        
        .notification-action-url {
            margin-bottom: 1rem;
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .controls-bar {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .filter-options {
                flex-wrap: wrap;
            }
            
            .notification-header {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .notification-item {
                flex-direction: column;
            }
            
            .notification-footer {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .pagination {
                justify-content: center;
            }
            
            .modal-content {
                width: 95%;
                margin: 2% auto;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/supervisor_navbar.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-bell"></i> Notifications</h1>
            <p>Stay updated with important alerts and messages</p>
        </div>

        <div class="controls-bar">
            <div class="filter-options" id="filterOptions">
                <button class="filter-btn active" data-filter="all">All</button>
                <button class="filter-btn" data-filter="unread">Unread</button>
                <button class="filter-btn" data-filter="info">Info</button>
                <button class="filter-btn" data-filter="warning">Warning</button>
                <button class="filter-btn" data-filter="success">Success</button>
                <button class="filter-btn" data-filter="error">Error</button>
            </div>
            
            <div class="action-buttons">
                <button class="btn btn-outline" id="markAllRead">
                    <i class="fas fa-check-circle"></i>
                    Mark All as Read
                </button>
            </div>
        </div>

        <div class="notifications-container">
            <div id="notifications-list">
                <!-- Notifications will be loaded here via JavaScript -->
            </div>
            
            <div class="notification-footer">
                <div class="notification-count">Loading notifications...</div>
                <div class="pagination" id="pagination-controls">
                    <!-- Pagination will be dynamically generated -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Notification Details Modal -->
    <div id="notificationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-bell"></i> Notification Details</h2>
                <button class="close" id="closeNotificationModal">&times;</button>
            </div>
            
            <div class="modal-body">
                <div class="notification-details">
                    <div class="notification-icon-large">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <h3 class="notification-title">Notification Title</h3>
                    <div class="notification-meta">
                        <span class="notification-time">Just now</span>
                        <span class="notification-type">Info</span>
                    </div>
                    <div class="notification-message-full">
                        <p>Notification message content goes here...</p>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer" style="display: flex; justify-content: flex-end; padding: 1rem 1.5rem; border-top: 1px solid #e9ecef; background: #ffffff;">
                <button class="btn btn-outline" id="closeNotificationDetails">Close</button>
            </div>
        </div>
    </div>
    
    <script>
        // Global variables for pagination
        let currentPage = 1;
        let notificationsPerPage = 10; // Default value
        let allNotifications = [];
        let unreadCount = 0;
        
        // Load notifications when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize notification data store
            window.notificationDataStore = {};
            
            loadNotifications();
            
            // Mark all as read button
            document.getElementById('markAllRead').addEventListener('click', markAllAsRead);
            
            // Filter buttons
            document.querySelectorAll('.filter-btn').forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    document.querySelectorAll('.filter-btn').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    // Filter notifications based on the selected filter
                    filterNotifications(this.getAttribute('data-filter'));
                });
            });
            
            // Notification modal close buttons
            document.getElementById('closeNotificationModal').addEventListener('click', closeNotificationModal);
            document.getElementById('closeNotificationDetails').addEventListener('click', closeNotificationModal);
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                const modal = document.getElementById('notificationModal');
                if (event.target === modal) {
                    closeNotificationModal();
                }
            });
        });
        
        // Show notification details in modal
        function showNotificationDetails(notificationDataId) {
            // Retrieve notification data from global store
            const notification = window.notificationDataStore[notificationDataId];
            if (!notification) {
                console.error('Notification data not found for ID:', notificationDataId);
                return;
            }
            
            // If notification is unread, mark it as read
            if (notification.read_at === null) {
                markAsRead(notification.id);
            }
            
            const modal = document.getElementById('notificationModal');
            const iconElement = modal.querySelector('.notification-icon-large');
            const titleElement = modal.querySelector('.notification-title');
            const timeElement = modal.querySelector('.notification-time');
            const typeElement = modal.querySelector('.notification-type');
            const messageElement = modal.querySelector('.notification-message-full p');
            
            // Update modal content
            iconElement.className = 'notification-icon-large ' + getIconColorClass(notification.type);
            iconElement.innerHTML = '<i class="' + getIconClass(notification.type) + '"></i>';
            titleElement.textContent = notification.title;
            timeElement.textContent = getTimeAgo(notification.created_at);
            typeElement.textContent = notification.type.charAt(0).toUpperCase() + notification.type.slice(1);
            typeElement.className = 'notification-type ' + notification.type;
            messageElement.textContent = notification.message;
            
            // Show modal
            modal.style.display = 'block';
        }
        
        // Close notification details modal
        function closeNotificationModal() {
            document.getElementById('notificationModal').style.display = 'none';
        }
        
        // Filter notifications based on type
        function filterNotifications(filterType) {
            let filteredNotifications = [];
            
            if (filterType === 'all') {
                filteredNotifications = allNotifications;
            } else if (filterType === 'unread') {
                filteredNotifications = allNotifications.filter(notification => notification.read_at === null);
            } else {
                filteredNotifications = allNotifications.filter(notification => notification.type === filterType);
            }
            
            // Display filtered notifications
            displayFilteredNotifications(filteredNotifications);
        }
        
        // Display filtered notifications
        function displayFilteredNotifications(filteredNotifications) {
            const container = document.getElementById('notifications-list');
            const countElement = document.querySelector('.notification-count');
            const paginationContainer = document.getElementById('pagination-controls');
            
            if (filteredNotifications.length === 0) {
                container.innerHTML = '<div class="no-notifications"><i class="fas fa-bell-slash"></i><p>No notifications found</p></div>';
                countElement.textContent = 'No notifications';
                paginationContainer.innerHTML = '';
                return;
            }
            
            // For simplicity, show all filtered notifications on one page
            // In a real implementation, you would implement pagination for filtered results
            let html = '';
            filteredNotifications.forEach((notification, index) => {
                const isUnread = notification.read_at === null ? 'unread' : '';
                const iconClass = getIconClass(notification.type);
                const iconColorClass = getIconColorClass(notification.type);
                const timeAgo = getTimeAgo(notification.created_at);
                
                // Create a unique ID for this notification data
                const notificationDataId = `filteredNotificationData${index}`;
                
                // Store notification data in a global object
                if (typeof window.notificationDataStore === 'undefined') {
                    window.notificationDataStore = {};
                }
                window.notificationDataStore[notificationDataId] = notification;
                
                html += `
                <div class="notification-item ${isUnread}">
                    <div class="notification-icon ${iconColorClass}">
                        <i class="${iconClass}"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-header">
                            <h3 class="notification-title">${escapeHtml(notification.title)}</h3>
                            <span class="notification-time">${timeAgo}</span>
                        </div>
                        <p class="notification-message">${escapeHtml(notification.message)}</p>
                        <div class="notification-actions">
                            <button class="notification-action" onclick="showNotificationDetails('${notificationDataId}')">Show Details</button>
                            ${isUnread ? `<button class="notification-action" onclick="markAsRead(${notification.id})">Mark as Read</button>` : ''}
                        </div>
                    </div>
                </div>
                `;
            });
            
            container.innerHTML = html;
            countElement.textContent = `Showing ${filteredNotifications.length} of ${filteredNotifications.length} notifications`;
            paginationContainer.innerHTML = ''; // No pagination for filtered results in this simple implementation
        }
        
        // Load notifications via AJAX
        function loadNotifications(page = 1) {
            currentPage = page;
            
            // Clear notification data store
            window.notificationDataStore = {};
            
            fetch('ajax_notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error('Error loading notifications:', data.error);
                        document.getElementById('notifications-list').innerHTML = '<div class="no-notifications"><i class="fas fa-exclamation-circle"></i><p>Error loading notifications</p></div>';
                        return;
                    }
                    
                    allNotifications = data.notifications;
                    unreadCount = data.unread_count;
                    
                    // Reset filter to 'all' when loading new notifications
                    document.querySelectorAll('.filter-btn').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    document.querySelector('.filter-btn[data-filter="all"]').classList.add('active');
                    
                    displayNotifications();
                })
                .catch(error => {
                    console.error('Error loading notifications:', error);
                    document.getElementById('notifications-list').innerHTML = '<div class="no-notifications"><i class="fas fa-exclamation-circle"></i><p>Error loading notifications</p></div>';
                });
        }
        
        // Display notifications in the UI with pagination
        function displayNotifications() {
            const container = document.getElementById('notifications-list');
            const countElement = document.querySelector('.notification-count');
            const paginationContainer = document.getElementById('pagination-controls');
            
            if (allNotifications.length === 0) {
                container.innerHTML = '<div class="no-notifications"><i class="fas fa-bell-slash"></i><p>No notifications found</p></div>';
                countElement.textContent = 'No notifications';
                paginationContainer.innerHTML = '';
                return;
            }
            
            // Check if any filter is active (other than 'all')
            const activeFilter = document.querySelector('.filter-btn.active');
            if (activeFilter && activeFilter.getAttribute('data-filter') !== 'all') {
                // If a filter is active, show filtered results
                filterNotifications(activeFilter.getAttribute('data-filter'));
                return;
            }
            
            // Calculate pagination
            const totalPages = Math.ceil(allNotifications.length / notificationsPerPage);
            const startIndex = (currentPage - 1) * notificationsPerPage;
            const endIndex = Math.min(startIndex + notificationsPerPage, allNotifications.length);
            const notificationsToShow = allNotifications.slice(startIndex, endIndex);
            
            // Display notifications
            let html = '';
            notificationsToShow.forEach((notification, index) => {
                const isUnread = notification.read_at === null ? 'unread' : '';
                const iconClass = getIconClass(notification.type);
                const iconColorClass = getIconColorClass(notification.type);
                const timeAgo = getTimeAgo(notification.created_at);
                
                // Create a unique ID for this notification data
                const notificationDataId = `notificationData${index}`;
                
                // Store notification data in a global object
                if (typeof window.notificationDataStore === 'undefined') {
                    window.notificationDataStore = {};
                }
                window.notificationDataStore[notificationDataId] = notification;
                
                html += `
                <div class="notification-item ${isUnread}">
                    <div class="notification-icon ${iconColorClass}">
                        <i class="${iconClass}"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-header">
                            <h3 class="notification-title">${escapeHtml(notification.title)}</h3>
                            <span class="notification-time">${timeAgo}</span>
                        </div>
                        <p class="notification-message">${escapeHtml(notification.message)}</p>
                        <div class="notification-actions">
                            <button class="notification-action" onclick="showNotificationDetails('${notificationDataId}')">Show Details</button>
                            ${isUnread ? `<button class="notification-action" onclick="markAsRead(${notification.id})">Mark as Read</button>` : ''}
                        </div>
                    </div>
                </div>
                `;
            });
            
            container.innerHTML = html;
            countElement.textContent = `Showing ${startIndex + 1}-${endIndex} of ${allNotifications.length} notifications`;
            
            // Generate pagination controls
            generatePaginationControls(totalPages);
        }
        
        // Generate pagination controls
        function generatePaginationControls(totalPages) {
            const paginationContainer = document.getElementById('pagination-controls');
            
            // Always show pagination controls, even for a single page
            let paginationHtml = '';
            
            // Previous button
            if (currentPage > 1) {
                paginationHtml += `<button class="page-btn" onclick="loadNotifications(${currentPage - 1})"><i class="fas fa-chevron-left"></i></button>`;
            } else {
                paginationHtml += `<button class="page-btn disabled"><i class="fas fa-chevron-left"></i></button>`;
            }
            
            // Page numbers
            const maxVisiblePages = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
            let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
            
            // Adjust start page if needed
            if (endPage - startPage + 1 < maxVisiblePages) {
                startPage = Math.max(1, endPage - maxVisiblePages + 1);
            }
            
            // Show first page and ellipsis if needed
            if (startPage > 1) {
                paginationHtml += `<button class="page-btn" onclick="loadNotifications(1)">1</button>`;
                if (startPage > 2) {
                    paginationHtml += `<span class="page-ellipsis">...</span>`;
                }
            }
            
            for (let i = startPage; i <= endPage; i++) {
                if (i === currentPage) {
                    paginationHtml += `<button class="page-btn active">${i}</button>`;
                } else {
                    paginationHtml += `<button class="page-btn" onclick="loadNotifications(${i})">${i}</button>`;
                }
            }
            
            // Show last page and ellipsis if needed
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    paginationHtml += `<span class="page-ellipsis">...</span>`;
                }
                if (endPage < totalPages) {
                    paginationHtml += `<button class="page-btn" onclick="loadNotifications(${totalPages})">${totalPages}</button>`;
                }
            }
            
            // Next button
            if (currentPage < totalPages) {
                paginationHtml += `<button class="page-btn" onclick="loadNotifications(${currentPage + 1})"><i class="fas fa-chevron-right"></i></button>`;
            } else {
                paginationHtml += `<button class="page-btn disabled"><i class="fas fa-chevron-right"></i></button>`;
            }
            
            paginationContainer.innerHTML = paginationHtml;
        }
        
        // Helper function to get icon class based on notification type
        function getIconClass(type) {
            switch(type) {
                case 'warning': return 'fas fa-exclamation-triangle';
                case 'success': return 'fas fa-check-circle';
                case 'error': return 'fas fa-exclamation-circle';
                default: return 'fas fa-info-circle';
            }
        }
        
        // Helper function to get icon color class based on notification type
        function getIconColorClass(type) {
            switch(type) {
                case 'warning': return 'warning';
                case 'success': return 'success';
                case 'error': return 'error';
                default: return 'info';
            }
        }
        
        // Helper function to calculate time ago
        function getTimeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffInSeconds = Math.floor((now - date) / 1000);
            
            if (diffInSeconds < 60) {
                return 'Just now';
            } else if (diffInSeconds < 3600) {
                const minutes = Math.floor(diffInSeconds / 60);
                return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
            } else if (diffInSeconds < 86400) {
                const hours = Math.floor(diffInSeconds / 3600);
                return `${hours} hour${hours > 1 ? 's' : ''} ago`;
            } else {
                const days = Math.floor(diffInSeconds / 86400);
                return `${days} day${days > 1 ? 's' : ''} ago`;
            }
        }
        
        // Helper function to escape HTML
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
        
        // Mark all notifications as read
        function markAllAsRead() {
            fetch('ajax_mark_all_notifications_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload notifications to reflect changes
                    loadNotifications(currentPage);
                } else {
                    console.error('Error marking all as read:', data.error);
                }
            })
            .catch(error => {
                console.error('Error marking all as read:', error);
            });
        }
        
        // Mark a specific notification as read
        function markAsRead(notificationId) {
            fetch('ajax_mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'notification_id=' + encodeURIComponent(notificationId)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload notifications to reflect changes
                    loadNotifications(currentPage);
                } else {
                    console.error('Error marking as read:', data.error);
                }
            })
            .catch(error => {
                console.error('Error marking as read:', error);
            });
        }
    </script>
</body>
</html>