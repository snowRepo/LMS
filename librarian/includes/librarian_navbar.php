<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has librarian role
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'librarian') {
    header('Location: ../login.php');
    exit;
}

// Get user information
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Librarian';
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'librarian';

// Get user initials for profile placeholder
$nameParts = explode(' ', $userName);
$initials = '';
foreach (array_slice($nameParts, 0, 2) as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}

// Count unread messages for the current user
$unreadMessageCount = 0;
$unreadNotificationCount = 0;

if (isset($_SESSION['user_id'])) {
    try {
        // Include database connection
        require_once '../config/config.php';
        
        $db = Database::getInstance()->getConnection();
        
        // Get the user string ID from the integer session ID
        $stmt = $db->prepare("SELECT user_id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $user_string_id = $user['user_id'];
            
            // Count unread messages using string user_id
            $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM messages WHERE recipient_id = ? AND is_read = 0 AND is_deleted = 0");
            $stmt->execute([$user_string_id]);
            $unreadMessageCount = $stmt->fetch()['unread_count'];
        }
        
        // Count unread notifications
        $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND read_at IS NULL");
        $stmt->execute([$user['user_id'] ?? $_SESSION['user_id']]);
        $unreadNotificationCount = $stmt->fetch()['unread_count'];
    } catch (Exception $e) {
        // Handle error silently
        $unreadMessageCount = 0;
        $unreadNotificationCount = 0;
    }
}
?>

<style>
    /* New Librarian Navbar Styles - Embedded CSS */
    .new-librarian-navbar {
        background: #f0f0f0; /* Light grey background */
        color: #333;
        padding: 0.6rem 1.2rem; /* Slightly reduced padding */
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        z-index: 1000;
        border-bottom: 1px solid #ddd;
        margin: 0;
    }
    
    .navbar-brand {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .navbar-brand span {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 1.4rem; /* Slightly reduced */
        font-weight: 700;
        color: #8e44ad; /* Purple color for brand text */
        text-decoration: none;
        transition: transform 0.3s ease;
    }
    
    .navbar-brand i {
        color: #8e44ad; /* Purple color for icon */
        font-size: 1.5rem; /* Slightly reduced */
    }
    
    .navbar-menu {
        display: flex;
        gap: 0.6rem; /* Reduced from 1rem */
    }
    
    .nav-link {
        color: #333;
        text-decoration: none;
        padding: 0.4rem 0.8rem; /* Slightly reduced */
        border-radius: 30px;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 5px;
        font-weight: 500;
        font-size: 0.85rem; /* Slightly reduced */
        position: relative;
        background-color: rgba(142, 68, 173, 0.05);
        border: 1px solid rgba(142, 68, 173, 0.1);
    }
    
    .nav-link i {
        font-size: 0.85rem; /* Slightly reduced */
    }
    
    .nav-link:hover {
        background-color: rgba(142, 68, 173, 0.15);
        color: #8e44ad;
        transform: translateY(-2px);
        border-color: rgba(142, 68, 173, 0.3);
    }
    
    .nav-link.active {
        background-color: rgba(142, 68, 173, 0.2);
        color: #8e44ad;
        font-weight: 600;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        border-color: rgba(142, 68, 173, 0.3);
    }
    
    .notification-badge {
        background-color: #8e44ad;
        color: white;
        border-radius: 50%;
        padding: 0;
        font-size: 0.7rem;
        font-weight: bold;
        min-width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        position: absolute;
        top: -5px;
        right: -5px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        line-height: 1;
    }
    
    .navbar-user {
        position: relative;
        display: flex;
        align-items: center;
    }
    
    .user-info {
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
        padding: 0.5rem 1rem;
        border-radius: 30px;
        transition: all 0.3s ease;
    }
    
    .user-info:hover {
        background-color: rgba(142, 68, 173, 0.1); /* Light purple on hover */
    }
    
    .user-avatar {
        width: 40px; /* Slightly reduced avatar size */
        height: 40px;
        border-radius: 50%;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #8e44ad; /* Purple background */
        color: white;
        font-weight: bold;
        font-size: 1.1rem; /* Slightly reduced font size */
        border: 2px solid #8e44ad;
        box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    }
    
    .user-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .avatar-placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
    }
    
    .user-details {
        display: flex;
        flex-direction: column;
        text-align: right;
    }
    
    .user-name {
        font-weight: 600;
        font-size: 0.95rem; /* Slightly reduced font size */
        color: #333;
    }
    
    .user-role {
        font-size: 0.75rem; /* Slightly reduced font size */
        opacity: 0.7;
        color: #666;
    }
    
    .dropdown-menu {
        position: absolute;
        top: 100%;
        right: 0;
        background: white;
        color: #495057;
        border-radius: 12px;
        box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        width: 220px;
        display: none;
        z-index: 1001;
        margin-top: 1rem;
        overflow: hidden;
        border: 1px solid #e9ecef;
    }
    
    .dropdown-menu.show {
        display: block;
        animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 1.2rem;
        text-decoration: none;
        color: #495057;
        border-bottom: 1px solid #e9ecef;
        transition: all 0.3s ease;
        font-weight: 500;
    }
    
    .dropdown-item:last-child {
        border-bottom: none;
    }
    
    .dropdown-item:hover {
        background-color: #f8f9fa;
        color: #8e44ad;
        padding-left: 1.5rem;
    }
    
    .dropdown-item i {
        width: 24px;
        text-align: center;
        font-size: 1.1rem;
    }
    
    .fas.fa-chevron-down {
        font-size: 0.8rem;
        transition: transform 0.3s ease;
    }
    
    /* Chevron rotates only when dropdown is open, not on hover */
    .dropdown-menu.show ~ .fas.fa-chevron-down,
    .dropdown-open .fas.fa-chevron-down {
        transform: rotate(180deg);
    }
    
    /* Responsive */
    @media (max-width: 992px) {
        .new-librarian-navbar {
            padding: 0.6rem 1rem; /* Adjusted padding */
        }
        
        .navbar-menu {
            gap: 0.5rem;
        }
        
        .nav-link {
            padding: 0.6rem 1rem;
            font-size: 0.9rem;
        }
    }
    
    @media (max-width: 768px) {
        .new-librarian-navbar {
            flex-direction: column;
            gap: 1rem;
            padding: 1rem;
        }
        
        .navbar-menu {
            width: 100%;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .navbar-user {
            width: 100%;
            justify-content: flex-end;
        }
    }
</style>

<!-- New Librarian Navbar -->
<nav class="new-librarian-navbar">
    <div class="navbar-brand">
        <!-- Brand is no longer clickable -->
        <span>
            <i class="fas fa-book-open"></i>
            <span>LMS</span>
        </span>
    </div>
    
    <div class="navbar-menu">
        <!-- Dashboard navlink -->
        <a href="dashboard.php" class="nav-link<?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? ' active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <!-- Books navlink -->
        <a href="books.php" class="nav-link<?php echo (basename($_SERVER['PHP_SELF']) == 'books.php') ? ' active' : ''; ?>">
            <i class="fas fa-book"></i>
            <span>Books</span>
        </a>
        <!-- Members navlink -->
        <a href="members.php" class="nav-link<?php echo (basename($_SERVER['PHP_SELF']) == 'members.php') ? ' active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Members</span>
        </a>
        <!-- Borrowing navlink -->
        <a href="borrowing.php" class="nav-link<?php echo (basename($_SERVER['PHP_SELF']) == 'borrowing.php') ? ' active' : ''; ?>">
            <i class="fas fa-exchange-alt"></i>
            <span>Borrowing</span>
        </a>
        <!-- Reservations navlink -->
        <a href="reservations.php" class="nav-link<?php echo (basename($_SERVER['PHP_SELF']) == 'reservations.php') ? ' active' : ''; ?>">
            <i class="fas fa-calendar-check"></i>
            <span>Reservations</span>
        </a>
        <!-- Messages navlink -->
        <a href="messages.php" class="nav-link<?php echo (basename($_SERVER['PHP_SELF']) == 'messages.php') ? ' active' : ''; ?>">
            <i class="fas fa-envelope"></i>
            <span>Messages</span>
            <?php if ($unreadMessageCount > 0): ?>
                <span class="notification-badge"><?php echo $unreadMessageCount; ?></span>
            <?php endif; ?>
        </a>
        <!-- Notifications navlink -->
        <a href="notifications.php" class="nav-link<?php echo (basename($_SERVER['PHP_SELF']) == 'notifications.php') ? ' active' : ''; ?>">
            <i class="fas fa-bell"></i>
            <span>Notifications</span>
            <?php if ($unreadNotificationCount > 0): ?>
                <span class="notification-badge"><?php echo $unreadNotificationCount; ?></span>
            <?php endif; ?>
        </a>
    </div>
    
    <div class="navbar-user">
        <div class="user-info" id="userDropdown">
            <div class="user-avatar">
                <?php if (isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])): ?>
                    <?php if (file_exists('../' . $_SESSION['profile_image'])): ?>
                        <img src="../<?php echo htmlspecialchars($_SESSION['profile_image']); ?>" alt="Profile">
                    <?php else: ?>
                        <div class="avatar-placeholder"><?php echo $initials; ?></div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="avatar-placeholder"><?php echo $initials; ?></div>
                <?php endif; ?>
            </div>
            <div class="user-details">
                <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
                <span class="user-role">Librarian</span>
            </div>
            <i class="fas fa-chevron-down"></i>
        </div>
        
        <div class="dropdown-menu" id="userDropdownMenu">
            <a href="profile.php" class="dropdown-item">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a href="../logout.php" class="dropdown-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const userDropdown = document.getElementById('userDropdown');
    const dropdownMenu = document.getElementById('userDropdownMenu');
    
    userDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdownMenu.classList.toggle('show');
        // Toggle class on parent to control chevron rotation
        this.classList.toggle('dropdown-open');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function() {
        dropdownMenu.classList.remove('show');
        userDropdown.classList.remove('dropdown-open');
    });
    
    // Update notification badge periodically (every 30 seconds)
    function updateNotificationBadges() {
        // Only update if we're on a librarian page
        if (typeof fetch !== 'undefined') {
            // Update message count
            fetch('ajax_message_count.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.error) {
                        // Update the message notification badge in the navbar
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
                    console.error('Error updating message badge:', error);
                });
                
            // Update notification count
            fetch('ajax_notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.error) {
                        // Update the notification badge in the navbar
                        const notificationsLink = document.querySelector('.nav-link[href="notifications.php"]');
                        if (notificationsLink) {
                            let badge = notificationsLink.querySelector('.notification-badge');
                            
                            if (data.unread_count > 0) {
                                if (badge) {
                                    badge.textContent = data.unread_count;
                                } else {
                                    // Create badge if it doesn't exist
                                    badge = document.createElement('span');
                                    badge.className = 'notification-badge';
                                    badge.textContent = data.unread_count;
                                    notificationsLink.appendChild(badge);
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
                    console.error('Error updating notification badge:', error);
                });
        }
    }
    
    // Update badges on page load and then every 30 seconds
    updateNotificationBadges();
    setInterval(updateNotificationBadges, 30000);
});
</script>