<!-- Header -->
<header class="header">
    <nav class="nav">
        <div class="logo">
            <a href="index.php" style="color: inherit; text-decoration: none; display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-book-open"></i>
                <span>LMS</span>
            </a>
        </div>
        <ul class="nav-links">
            <li><a href="features.php" <?php echo (isset($currentPage) && $currentPage == 'features.php') ? 'class="active"' : (basename($_SERVER['PHP_SELF']) == 'features.php' ? 'class="active"' : ''); ?>><i class="fas fa-info-circle"></i> Features</a></li>
            <li><a href="pricing.php" <?php echo (isset($currentPage) && $currentPage == 'pricing.php') ? 'class="active"' : (basename($_SERVER['PHP_SELF']) == 'pricing.php' ? 'class="active"' : ''); ?>><i class="fas fa-tag"></i> Pricing</a></li>
            <li><a href="support.php" <?php echo (isset($currentPage) && $currentPage == 'support.php') ? 'class="active"' : (basename($_SERVER['PHP_SELF']) == 'support.php' ? 'class="active"' : ''); ?>><i class="fas fa-headset"></i> Support</a></li>
            <li><a href="login.php" <?php echo (isset($currentPage) && $currentPage == 'login.php') ? 'class="active"' : (basename($_SERVER['PHP_SELF']) == 'login.php' ? 'class="active"' : ''); ?>><i class="fas fa-sign-in-alt"></i> Login</a></li>
            <li><a href="register.php" <?php echo (isset($currentPage) && $currentPage == 'register.php') ? 'class="active"' : (basename($_SERVER['PHP_SELF']) == 'register.php' ? 'class="active"' : ''); ?>><i class="fas fa-user-plus"></i> Sign Up</a></li>
        </ul>
    </nav>
</header>