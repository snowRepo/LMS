<?php

require_once dirname(__DIR__) . '/config/upload_config.php';

class FileUploadHandler {
    
    private $allowedTypes;
    private $maxFileSize;
    private $uploadBasePath;
    
    public function __construct() {
        $this->allowedTypes = ALLOWED_IMAGE_TYPES;
        $this->maxFileSize = MAX_GENERAL_SIZE;
        $this->uploadBasePath = UPLOAD_BASE_DIR;
        
        // Ensure directories exist
        initializeUploadDirectories();
    }
    
    /**
     * Upload library logo
     */
    public function uploadLibraryLogo($file, $libraryId = null, $oldFilePath = null) {
        $this->setMaxFileSize(MAX_LOGO_SIZE);
        return $this->uploadFileWithReplacement($file, 'logos', 'logo', $libraryId, $oldFilePath);
    }
    
    /**
     * Upload user profile image
     */
    public function uploadProfileImage($file, $userId = null, $oldFilePath = null) {
        $this->setMaxFileSize(MAX_PROFILE_SIZE);
        return $this->uploadFileWithReplacement($file, 'profiles', 'profile', $userId, $oldFilePath);
    }
    
    /**
     * Upload book cover image
     */
    public function uploadBookImage($file, $bookId = null, $oldFilePath = null) {
        $this->setMaxFileSize(MAX_BOOK_SIZE);
        return $this->uploadFileWithReplacement($file, 'books', 'book', $bookId, $oldFilePath);
    }
    
    /**
     * Upload file with replacement of old file
     */
    private function uploadFileWithReplacement($file, $subDirectory, $prefix, $identifier = null, $oldFilePath = null) {
        // First validate the new file
        $validation = $this->validateFile($file);
        if (!$validation['success']) {
            return $validation;
        }
        
        // Delete old file if it exists
        if ($oldFilePath && $this->deleteFile($oldFilePath)) {
            // Old file deleted successfully
        }
        
        // Proceed with upload
        return $this->uploadFile($file, $subDirectory, $prefix, $identifier);
    }
    
    /**
     * Generic file upload method
     */
    private function uploadFile($file, $subDirectory, $prefix, $identifier = null) {
        // Validate file upload
        $validation = $this->validateFile($file);
        if (!$validation['success']) {
            return $validation;
        }
        
        // Create upload directory if it doesn't exist
        $uploadDir = $this->uploadBasePath . $subDirectory . '/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0775, true)) {
                return [
                    'success' => false,
                    'error' => 'Failed to create upload directory. Please check permissions.'
                ];
            }
        }
        
        // Check if directory is writable
        if (!is_writable($uploadDir)) {
            return [
                'success' => false,
                'error' => 'Upload directory is not writable. Please check permissions.'
            ];
        }
        
        // Generate unique filename
        $extension = $this->getFileExtension($file['type']);
        $filename = $this->generateFilename($prefix, $extension, $identifier);
        $targetPath = $uploadDir . $filename;
        $relativePath = 'uploads/' . $subDirectory . '/' . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // Set proper permissions on the uploaded file
            chmod($targetPath, 0664);
            
            return [
                'success' => true,
                'filename' => $filename,
                'path' => $relativePath,
                'full_path' => $targetPath
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Failed to move uploaded file. Check directory permissions and disk space.'
            ];
        }
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'error' => $this->getUploadErrorMessage($file['error'])
            ];
        }
        
        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            return [
                'success' => false,
                'error' => 'File size exceeds maximum limit of ' . formatFileSize($this->maxFileSize)
            ];
        }
        
        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!array_key_exists($mimeType, $this->allowedTypes)) {
            return [
                'success' => false,
                'error' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed'
            ];
        }
        
        // Additional security check - verify it's actually an image
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return [
                'success' => false,
                'error' => 'Invalid image file'
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Generate unique filename
     */
    private function generateFilename($prefix, $extension, $identifier = null) {
        $timestamp = time();
        $random = rand(1000, 9999);
        
        if ($identifier) {
            return $prefix . '_' . $identifier . '_' . $timestamp . '_' . $random . '.' . $extension;
        } else {
            return $prefix . '_' . $timestamp . '_' . $random . '.' . $extension;
        }
    }
    
    /**
     * Get file extension from MIME type
     */
    private function getFileExtension($mimeType) {
        return $this->allowedTypes[$mimeType] ?? 'jpg';
    }
    
    /**
     * Get upload error message
     */
    private function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'File exceeds upload_max_filesize limit';
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds MAX_FILE_SIZE limit';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }
    
    /**
     * Delete uploaded file
     */
    public function deleteFile($filePath) {
        if (empty($filePath)) {
            return true; // Nothing to delete
        }
        
        // Handle both relative and absolute paths
        if (strpos($filePath, $this->uploadBasePath) === 0) {
            // Already absolute path
            $fullPath = $filePath;
        } else {
            // Convert relative path to absolute
            $fullPath = $this->uploadBasePath . str_replace('uploads/', '', $filePath);
        }
        
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        
        return true; // File doesn't exist, consider it deleted
    }
    
    /**
     * Find existing file by identifier and type
     */
    public function findExistingFile($subDirectory, $prefix, $identifier) {
        $searchDir = $this->uploadBasePath . $subDirectory . '/';
        if (!is_dir($searchDir)) {
            return null;
        }
        
        $pattern = $searchDir . $prefix . '_' . $identifier . '_*';
        $files = glob($pattern);
        
        if (!empty($files)) {
            // Return the most recent file if multiple exist
            usort($files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
            return str_replace($this->uploadBasePath, 'uploads/', $files[0]);
        }
        
        return null;
    }
    
    /**
     * Upload library logo with automatic replacement
     */
    public function uploadLibraryLogoWithAutoReplace($file, $libraryId) {
        $oldFile = $this->findExistingFile('logos', 'logo', $libraryId);
        return $this->uploadLibraryLogo($file, $libraryId, $oldFile);
    }
    
    /**
     * Upload profile image with automatic replacement
     */
    public function uploadProfileImageWithAutoReplace($file, $userId) {
        $oldFile = $this->findExistingFile('profiles', 'profile', $userId);
        return $this->uploadProfileImage($file, $userId, $oldFile);
    }
    
    /**
     * Upload book image with automatic replacement
     */
    public function uploadBookImageWithAutoReplace($file, $bookId) {
        $oldFile = $this->findExistingFile('books', 'book', $bookId);
        return $this->uploadBookImage($file, $bookId, $oldFile);
    }
    
    /**
     * Set maximum file size
     */
    public function setMaxFileSize($bytes) {
        $this->maxFileSize = $bytes;
    }
    
    /**
     * Add allowed file type
     */
    public function addAllowedType($mimeType, $extension) {
        $this->allowedTypes[$mimeType] = $extension;
    }
    
    /**
     * Get file info
     */
    public function getFileInfo($filePath) {
        $fullPath = $this->uploadBasePath . str_replace('uploads/', '', $filePath);
        
        if (!file_exists($fullPath)) {
            return null;
        }
        
        return [
            'size' => filesize($fullPath),
            'type' => mime_content_type($fullPath),
            'modified' => filemtime($fullPath),
            'exists' => true
        ];
    }
    
    /**
     * Clean up temporary files (older than 24 hours)
     */
    public function cleanupTempFiles() {
        return cleanupTempFiles();
    }
}