<?php
/**
 * Image Update Examples
 * This file shows how to use the FileUploadHandler for replacing existing images
 */

require_once 'includes/FileUploadHandler.php';
require_once 'config/config.php';

/**
 * Update library logo
 * @param array $file - $_FILES['logo'] data
 * @param int $libraryId - Library ID
 * @param string $currentLogoPath - Current logo path from database (optional)
 * @return array - Upload result
 */
function updateLibraryLogo($file, $libraryId, $currentLogoPath = null) {
    $uploader = new FileUploadHandler();
    
    if ($currentLogoPath) {
        // Replace existing logo
        return $uploader->uploadLibraryLogo($file, $libraryId, $currentLogoPath);
    } else {
        // Auto-find and replace existing logo
        return $uploader->uploadLibraryLogoWithAutoReplace($file, $libraryId);
    }
}

/**
 * Update user profile image
 * @param array $file - $_FILES['profile_image'] data
 * @param int $userId - User ID
 * @param string $currentImagePath - Current image path from database (optional)
 * @return array - Upload result
 */
function updateProfileImage($file, $userId, $currentImagePath = null) {
    $uploader = new FileUploadHandler();
    
    if ($currentImagePath) {
        // Replace existing image
        return $uploader->uploadProfileImage($file, $userId, $currentImagePath);
    } else {
        // Auto-find and replace existing image
        return $uploader->uploadProfileImageWithAutoReplace($file, $userId);
    }
}

/**
 * Update book cover image
 * @param array $file - $_FILES['book_cover'] data
 * @param int $bookId - Book ID
 * @param string $currentCoverPath - Current cover path from database (optional)
 * @return array - Upload result
 */
function updateBookCover($file, $bookId, $currentCoverPath = null) {
    $uploader = new FileUploadHandler();
    
    if ($currentCoverPath) {
        // Replace existing cover
        return $uploader->uploadBookImage($file, $bookId, $currentCoverPath);
    } else {
        // Auto-find and replace existing cover
        return $uploader->uploadBookImageWithAutoReplace($file, $bookId);
    }
}

/*
EXAMPLE USAGE:

// In a profile update form:
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    $userId = $_SESSION['user_id'];
    
    // Get current image path from database
    $stmt = $db->prepare(\"SELECT profile_image FROM users WHERE id = ?\");
    $stmt->execute([$userId]);
    $currentImage = $stmt->fetchColumn();
    
    // Update profile image
    $result = updateProfileImage($_FILES['profile_image'], $userId, $currentImage);
    
    if ($result['success']) {
        // Update database with new path
        $stmt = $db->prepare(\"UPDATE users SET profile_image = ? WHERE id = ?\");
        $stmt->execute([$result['path'], $userId]);
        
        echo \"Profile image updated successfully!\";
    } else {
        echo \"Upload failed: \" . $result['error'];
    }
}

// In a library settings form:
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['library_logo'])) {
    $libraryId = $_SESSION['library_id'];
    
    // Option 1: Auto-replace (finds existing file automatically)
    $result = updateLibraryLogo($_FILES['library_logo'], $libraryId);
    
    // Option 2: Manual replacement (if you have the current path)
    // $currentLogo = getCurrentLibraryLogo($libraryId);
    // $result = updateLibraryLogo($_FILES['library_logo'], $libraryId, $currentLogo);
    
    if ($result['success']) {
        // Update database
        $stmt = $db->prepare(\"UPDATE libraries SET logo_path = ? WHERE id = ?\");
        $stmt->execute([$result['path'], $libraryId]);
        
        echo \"Library logo updated successfully!\";
    } else {
        echo \"Upload failed: \" . $result['error'];
    }
}

// In a book management form:
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['book_cover'])) {
    $bookId = $_POST['book_id'];
    
    $result = updateBookCover($_FILES['book_cover'], $bookId);
    
    if ($result['success']) {
        // Update database
        $stmt = $db->prepare(\"UPDATE books SET cover_image = ? WHERE id = ?\");
        $stmt->execute([$result['path'], $bookId]);
        
        echo \"Book cover updated successfully!\";
    } else {
        echo \"Upload failed: \" . $result['error'];
    }
}
*/
?>"