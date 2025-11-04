<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Get user information
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Administrator';
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'admin';

// Get user initials for profile placeholder
$nameParts = explode(' ', $userName);
$initials = '';
foreach (array_slice($nameParts, 0, 2) as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
?>

<style>
    /* Admin Navbar Styles */
    .admin-navbar-container {
        background: #e9ecef; /* Deeper grey background */
        color: #495057;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        z-index: 1000;
        border-bottom: 1px solid #dee2e6;
    }
    
    .admin-navbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 20px;
        height: 60px;
    }
    
    .navbar-brand {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .navbar-brand h3 {
        margin: 0;
        font-size: 1.4rem;
        color: #495057;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .navbar-menu {
        display: flex;
        gap: 5px;
    }
    
    .navbar-menu a {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 12px;
        color: #495057;
        text-decoration: none;
        border-radius: 4px;
        transition: all 0.3s ease;
        font-size: 0.85rem;
    }
    
    .navbar-menu a:hover {
        background: #dee2e6;
        color: #0066cc; /* Deeper blue per user preference */
    }
    
    .navbar-menu a.active {
        background: #dee2e6;
        color: #0066cc; /* Deeper blue per user preference */
        font-weight: 500;
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
        background-color: #dee2e6;
    }
    
    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #0066cc; /* Deeper blue per user preference */
        color: white;
        font-weight: bold;
        font-size: 1.1rem;
        border: 2px solid #0066cc;
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
        font-size: 0.95rem;
        color: #333;
    }
    
    .user-role {
        font-size: 0.75rem;
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
        color: #0066cc; /* Deeper blue per user preference */
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
    
    .dropdown-menu.show ~ .fas.fa-chevron-down,
    .dropdown-open .fas.fa-chevron-down {
        transform: rotate(180deg);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .navbar-menu {
            display: none;
        }
        
        .navbar-brand h3 span {
            display: none;
        }
    }
</style>

<!-- Admin Navbar -->
<div class="admin-navbar-container">
    <div class="admin-navbar">
        <div class="navbar-brand">
            <h3><i class="fas fa-book-open"></i> <span>LMS</span></h3>
        </div>
        
        <div class="navbar-menu">
            <a href="dashboard.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="libraries.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'libraries.php') ? 'active' : ''; ?>">
                <i class="fas fa-building"></i>
                <span>Libraries</span>
            </a>
            <a href="users.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'users.php') ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            <a href="subscriptions.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'subscriptions.php') ? 'active' : ''; ?>">
                <i class="fas fa-credit-card"></i>
                <span>Subscriptions</span>
            </a>
            <a href="support.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'support.php') ? 'active' : ''; ?>">
                <i class="fas fa-headset"></i>
                <span>Support</span>
            </a>
            <a href="reports.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'reports.php') ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
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
                    <span class="user-role">Administrator</span>
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
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const userDropdown = document.getElementById('userDropdown');
    const dropdownMenu = document.getElementById('userDropdownMenu');
    
    userDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdownMenu.classList.toggle('show');
        this.classList.toggle('dropdown-open');
    });
    
    document.addEventListener('click', function() {
        dropdownMenu.classList.remove('show');
        userDropdown.classList.remove('dropdown-open');
    });
});
</script>