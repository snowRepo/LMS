<?php
/**
 * File Cleanup Script
 * Run this script via cron to clean up temporary files and maintain upload directories
 * 
 * Usage: php cleanup.php
 * Recommended cron: 0 2 * * * /usr/bin/php /path/to/LMS/includes/cleanup.php
 */

// Security check
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from command line');
}

// Load required files
require_once dirname(__DIR__) . '/config/upload_config.php';
require_once __DIR__ . '/FileUploadHandler.php';

function log_message($message) {
    echo "[" . date('Y-m-d H:i:s') . "] $message\n";
}

function cleanup_temp_files() {
    log_message("Starting temporary file cleanup...");
    
    $deleted = cleanupTempFiles();
    log_message("Deleted $deleted temporary files");
    
    return $deleted;
}

function cleanup_orphaned_files() {
    log_message("Starting orphaned file cleanup...");
    
    try {
        // Define LMS_ACCESS to allow config file access
        if (!defined('LMS_ACCESS')) {
            define('LMS_ACCESS', true);
        }
        
        // Load database connection
        require_once dirname(__DIR__) . '/config/config.php';
        $db = Database::getInstance()->getConnection();
        
        $orphaned = 0;
        
        // Check logos directory
        $logoFiles = glob(LOGO_UPLOAD_DIR . '*');
        foreach ($logoFiles as $file) {
            if (is_file($file) && basename($file) !== 'index.php') {
                $relativePath = str_replace(dirname(__DIR__) . '/', '', $file);
                
                // Check if this logo exists in libraries table
                $stmt = $db->prepare("SELECT COUNT(*) FROM libraries WHERE logo_path = ?");
                $stmt->execute([$relativePath]);
                
                if ($stmt->fetchColumn() == 0) {
                    // File not found in database, delete if older than 1 day
                    if ((time() - filemtime($file)) > 86400) {
                        if (unlink($file)) {
                            log_message("Deleted orphaned logo: $relativePath");
                            $orphaned++;
                        }
                    }
                }
            }
        }
        
        // Check profiles directory
        $profileFiles = glob(PROFILE_UPLOAD_DIR . '*');
        foreach ($profileFiles as $file) {
            if (is_file($file) && basename($file) !== 'index.php') {
                $relativePath = str_replace(dirname(__DIR__) . '/', '', $file);
                
                // Check if this profile image exists in users table
                $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE profile_image = ?");
                $stmt->execute([$relativePath]);
                
                if ($stmt->fetchColumn() == 0) {
                    // File not found in database, delete if older than 1 day
                    if ((time() - filemtime($file)) > 86400) {
                        if (unlink($file)) {
                            log_message("Deleted orphaned profile image: $relativePath");
                            $orphaned++;
                        }
                    }
                }
            }
        }
        
        // Check books directory
        $bookFiles = glob(BOOK_UPLOAD_DIR . '*');
        foreach ($bookFiles as $file) {
            if (is_file($file) && basename($file) !== 'index.php') {
                $relativePath = str_replace(dirname(__DIR__) . '/', '', $file);
                
                // Check if this book cover exists in books table
                $stmt = $db->prepare("SELECT COUNT(*) FROM books WHERE cover_image = ?");
                $stmt->execute([$relativePath]);
                
                if ($stmt->fetchColumn() == 0) {
                    // File not found in database, delete if older than 1 day
                    if ((time() - filemtime($file)) > 86400) {
                        if (unlink($file)) {
                            log_message("Deleted orphaned book cover: $relativePath");
                            $orphaned++;
                        }
                    }
                }
            }
        }
        
        log_message("Found and deleted $orphaned orphaned files");
        
        return $orphaned;
    } catch (Exception $e) {
        log_message("Error during orphaned file cleanup: " . $e->getMessage());
        return 0;
    }
}

function check_directory_permissions() {
    log_message("Checking directory permissions...");
    
    $directories = [
        UPLOAD_BASE_DIR,
        LOGO_UPLOAD_DIR,
        PROFILE_UPLOAD_DIR,
        BOOK_UPLOAD_DIR,
        TEMP_UPLOAD_DIR
    ];
    
    $issues = 0;
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            log_message("WARNING: Directory does not exist: $dir");
            if (mkdir($dir, UPLOAD_DIR_PERMISSIONS, true)) {
                log_message("Created directory: $dir");
                // Set proper group ownership if possible
                if (function_exists('chgrp')) {
                    @chgrp($dir, 'daemon');
                }
            } else {
                log_message("ERROR: Failed to create directory: $dir");
                $issues++;
            }
        } elseif (!is_writable($dir)) {
            log_message("WARNING: Directory is not writable: $dir");
            if (chmod($dir, UPLOAD_DIR_PERMISSIONS)) {
                log_message("Fixed permissions for: $dir");
                // Set proper group ownership if possible
                if (function_exists('chgrp')) {
                    @chgrp($dir, 'daemon');
                }
            } else {
                log_message("ERROR: Failed to fix permissions for: $dir");
                $issues++;
            }
        }
        
        // Check for index.php files
        $indexFile = $dir . 'index.php';
        if (!file_exists($indexFile)) {
            log_message("Creating security index.php in: $dir");
            file_put_contents($indexFile, "<?php\n// Prevent directory browsing\nhttp_response_code(403);\nexit('Access denied');\n?>");
        }
    }
    
    return $issues;
}

function get_directory_sizes() {
    log_message("Calculating directory sizes...");
    
    $directories = [
        'logos' => LOGO_UPLOAD_DIR,
        'profiles' => PROFILE_UPLOAD_DIR,
        'books' => BOOK_UPLOAD_DIR,
        'temp' => TEMP_UPLOAD_DIR
    ];
    
    foreach ($directories as $name => $dir) {
        if (is_dir($dir)) {
            $size = 0;
            $files = 0;
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                    $files++;
                }
            }
            
            log_message("$name directory: $files files, " . formatFileSize($size));
        }
    }
}

// Main cleanup execution
log_message("=== LMS File Cleanup Started ===");

$tempDeleted = cleanup_temp_files();
$orphanedDeleted = cleanup_orphaned_files();
$permissionIssues = check_directory_permissions();
get_directory_sizes();

log_message("=== Cleanup Summary ===");
log_message("Temporary files deleted: $tempDeleted");
log_message("Orphaned files deleted: $orphanedDeleted");
log_message("Permission issues found: $permissionIssues");

if ($permissionIssues > 0) {
    log_message("WARNING: There were permission issues that need attention");
    exit(1);
}

log_message("=== LMS File Cleanup Completed Successfully ===");
exit(0);
?>