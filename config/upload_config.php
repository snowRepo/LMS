<?php
/**
 * Upload Configuration File
 * Defines upload settings and directory structure
 */

// Upload directory structure
define('UPLOAD_BASE_DIR', dirname(__DIR__) . '/uploads/');
define('LOGO_UPLOAD_DIR', UPLOAD_BASE_DIR . 'logos/');
define('PROFILE_UPLOAD_DIR', UPLOAD_BASE_DIR . 'profiles/');
define('BOOK_UPLOAD_DIR', UPLOAD_BASE_DIR . 'books/');
define('TEMP_UPLOAD_DIR', UPLOAD_BASE_DIR . 'temp/');

// File size limits (in bytes)
define('MAX_LOGO_SIZE', 2 * 1024 * 1024);      // 2MB for logos
define('MAX_PROFILE_SIZE', 1 * 1024 * 1024);   // 1MB for profile images
define('MAX_BOOK_SIZE', 5 * 1024 * 1024);      // 5MB for book covers
define('MAX_GENERAL_SIZE', 2 * 1024 * 1024);   // 2MB general limit

// Allowed file types
define('ALLOWED_IMAGE_TYPES', [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp'
]);

// Directory permissions
define('UPLOAD_DIR_PERMISSIONS', 0775);

/**
 * Initialize upload directories
 */
function initializeUploadDirectories() {
    $directories = [
        LOGO_UPLOAD_DIR,
        PROFILE_UPLOAD_DIR,
        BOOK_UPLOAD_DIR,
        TEMP_UPLOAD_DIR
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            if (!mkdir($dir, UPLOAD_DIR_PERMISSIONS, true)) {
                error_log("Failed to create upload directory: $dir");
                return false;
            }
        }
        
        // Create index.php file for security
        $indexFile = $dir . 'index.php';
        if (!file_exists($indexFile)) {
            file_put_contents($indexFile, "<?php\n// Prevent directory browsing\nhttp_response_code(403);\nexit('Access denied');\n?>");
        }
    }
    
    // Create main .htaccess if it doesn't exist
    $htaccessFile = UPLOAD_BASE_DIR . '.htaccess';
    if (!file_exists($htaccessFile)) {
        $htaccessContent = <<<HTACCESS
# Security settings for uploads directory
# Prevent execution of PHP files
<Files "*.php">
    Order Deny,Allow
    Deny from all
</Files>

<Files "*.phtml">
    Order Deny,Allow
    Deny from all
</Files>

<Files "*.php3">
    Order Deny,Allow
    Deny from all
</Files>

<Files "*.php4">
    Order Deny,Allow
    Deny from all
</Files>

<Files "*.php5">
    Order Deny,Allow
    Deny from all
</Files>

<Files "*.pl">
    Order Deny,Allow
    Deny from all
</Files>

<Files "*.py">
    Order Deny,Allow
    Deny from all
</Files>

<Files "*.jsp">
    Order Deny,Allow
    Deny from all
</Files>

<Files "*.asp">
    Order Deny,Allow
    Deny from all
</Files>

<Files "*.sh">
    Order Deny,Allow
    Deny from all
</Files>

<Files "*.cgi">
    Order Deny,Allow
    Deny from all
</Files>

# Only allow image files
<FilesMatch "\.(jpg|jpeg|png|gif|webp|svg)$">
    Order Allow,Deny
    Allow from all
</FilesMatch>

# Set proper MIME types
<IfModule mod_mime.c>
    AddType image/jpeg .jpg .jpeg
    AddType image/png .png
    AddType image/gif .gif
    AddType image/webp .webp
    AddType image/svg+xml .svg
</IfModule>
HTACCESS;
        
        file_put_contents($htaccessFile, $htaccessContent);
    }
    
    return true;
}

/**
 * Get human readable file size
 */
function formatFileSize($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Clean up old temporary files
 */
function cleanupTempFiles($maxAge = 86400) { // 24 hours default
    $tempDir = TEMP_UPLOAD_DIR;
    $files = glob($tempDir . '*');
    $deleted = 0;
    
    foreach ($files as $file) {
        if (is_file($file) && (time() - filemtime($file)) > $maxAge) {
            if (unlink($file)) {
                $deleted++;
            }
        }
    }
    
    return $deleted;
}

// Initialize directories when this file is included
initializeUploadDirectories();
?>