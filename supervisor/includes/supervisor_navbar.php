<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has supervisor role
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'supervisor') {
    header('Location: ../login.php');
    exit;
}

// Get user information
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Supervisor';
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'supervisor';

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

<!-- Supervisor Navbar -->
<nav class="supervisor-navbar">
    <div class="navbar-brand">
        <a href="dashboard.php">
            <i class="fas fa-book-open"></i>
            <span>LMS</span>
        </a>
    </div>
    
    <div class="navbar-menu">
        <!-- Navigation links will be added here -->
        <a href="dashboard.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="librarians.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'librarians.php') ? 'active' : ''; ?>">
            <i class="fas fa-user-tie"></i>
            <span>Librarians</span>
        </a>
        <a href="messages.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'messages.php') ? 'active' : ''; ?>">
            <i class="fas fa-envelope"></i>
            <span>Messages</span>
            <?php if ($unreadMessageCount > 0): ?>
                <span class="notification-badge"><?php echo $unreadMessageCount; ?></span>
            <?php endif; ?>
        </a>
        <a href="notifications.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'notifications.php') ? 'active' : ''; ?>">
            <i class="fas fa-bell"></i>
            <span>Notifications</span>
            <?php if ($unreadNotificationCount > 0): ?>
                <span class="notification-badge"><?php echo $unreadNotificationCount; ?></span>
            <?php endif; ?>
        </a>
        <a href="reports.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'reports.php') ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Reports</span>
        </a>
        <a href="subscription.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'subscription.php') ? 'active' : ''; ?>">
            <i class="fas fa-credit-card"></i>
            <span>Subscription</span>
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
                <span class="user-role">Supervisor</span>
            </div>
            <i class="fas fa-chevron-down"></i>
        </div>
        
        <div class="dropdown-menu" id="userDropdownMenu">
            <a href="profile.php" class="dropdown-item">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a href="settings.php" class="dropdown-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
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
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function() {
        dropdownMenu.classList.remove('show');
    });
    
    // Update notification badge periodically (every 30 seconds)
    function updateNotificationBadges() {
        // Only update if we're on a supervisor page
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

<style>
/* Additional CSS to make nav links slightly bigger without changing design */
.supervisor-navbar .nav-link {
    padding: 12px 15px !important; /* Slightly increased padding */
}

.supervisor-navbar .nav-link i {
    font-size: 1.1em !important; /* Slightly larger icons */
}
</style>