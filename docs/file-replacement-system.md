# File Replacement System

## Overview
The LMS now implements an intelligent file replacement system that automatically manages uploaded images by replacing old files instead of accumulating them. This keeps the uploads directory clean and saves disk space.

## How It Works

### Automatic File Replacement
When a user uploads a new image (profile picture, library logo, or book cover), the system:

1. **Validates the new file** - Ensures it meets security and size requirements
2. **Finds the old file** - Automatically locates any existing file for that entity
3. **Deletes the old file** - Removes the previous image from disk
4. **Uploads the new file** - Saves the new image with a unique filename
5. **Updates the database** - Records the new file path

### File Identification System
Files are identified using a specific naming pattern:
```
{type}_{identifier}_{timestamp}_{random}.{extension}
```

Examples:
- `logo_123_1634567890_5678.jpg` (Library ID 123)
- `profile_456_1634567890_9012.png` (User ID 456)
- `book_789_1634567890_3456.gif` (Book ID 789)

This allows the system to:
- Find existing files by searching for the pattern `{type}_{identifier}_*`
- Maintain unique filenames to prevent conflicts
- Track file creation times for cleanup

## Usage Examples

### Library Logo Update
```php
// Automatic replacement (recommended)
$uploader = new FileUploadHandler();
$result = $uploader->uploadLibraryLogoWithAutoReplace($_FILES['logo'], $libraryId);

if ($result['success']) {
    // Update database with new path
    $stmt = $db->prepare("UPDATE libraries SET logo_path = ? WHERE id = ?");
    $stmt->execute([$result['path'], $libraryId]);
}
```

### Profile Image Update
```php
// With known old file path
$currentImage = getCurrentUserProfileImage($userId);
$result = $uploader->uploadProfileImage($_FILES['profile'], $userId, $currentImage);

// Or automatic discovery
$result = $uploader->uploadProfileImageWithAutoReplace($_FILES['profile'], $userId);
```

### Book Cover Update
```php
// Simple automatic replacement
$result = $uploader->uploadBookImageWithAutoReplace($_FILES['cover'], $bookId);

if ($result['success']) {
    $stmt = $db->prepare("UPDATE books SET cover_image = ? WHERE id = ?");
    $stmt->execute([$result['path'], $bookId]);
}
```

## Methods Available

### Primary Upload Methods
- `uploadLibraryLogo($file, $libraryId, $oldFilePath = null)`
- `uploadProfileImage($file, $userId, $oldFilePath = null)`
- `uploadBookImage($file, $bookId, $oldFilePath = null)`

### Auto-Replace Methods (Recommended)
- `uploadLibraryLogoWithAutoReplace($file, $libraryId)`
- `uploadProfileImageWithAutoReplace($file, $userId)`
- `uploadBookImageWithAutoReplace($file, $bookId)`

### Utility Methods
- `findExistingFile($subDirectory, $prefix, $identifier)` - Find existing files
- `deleteFile($filePath)` - Delete a specific file
- `getFileInfo($filePath)` - Get file information

## Benefits

### Disk Space Management
- **No file accumulation** - Only one file per entity at any time
- **Automatic cleanup** - Old files are immediately deleted
- **Orphan removal** - Cleanup script removes files not in database

### Security
- **Validated replacements** - New files must pass all security checks before old files are deleted
- **Safe deletion** - Only deletes files that belong to the system
- **Protected directories** - All security measures remain in place

### Performance
- **Faster directory scanning** - Fewer files means faster operations
- **Reduced backup size** - Less data to backup
- **Better maintenance** - Easier to manage and monitor

## Maintenance

### Automatic Cleanup
The cleanup script (`includes/cleanup.php`) now includes:

```bash
# Run daily at 2 AM
0 2 * * * /usr/bin/php /path/to/LMS/includes/cleanup.php
```

Features:
- **Orphaned file detection** - Finds files on disk not referenced in database
- **Age-based cleanup** - Removes orphaned files older than 24 hours
- **Database verification** - Checks against libraries, users, and books tables
- **Detailed logging** - Reports what was cleaned up

### Manual File Management
```php
// Find all files for a specific entity
$uploader = new FileUploadHandler();
$existingLogo = $uploader->findExistingFile('logos', 'logo', $libraryId);

// Delete a specific file
$uploader->deleteFile('uploads/logos/old_logo.jpg');

// Get file information
$info = $uploader->getFileInfo('uploads/profiles/profile_123.jpg');
```

## Error Handling

The system handles various error scenarios:

### Upload Failures
- If validation fails, no files are deleted
- Original file remains intact if new upload fails
- Detailed error messages for debugging

### Missing Files
- Gracefully handles requests to delete non-existent files
- Auto-replace works even if no old file exists
- Database updates only occur after successful uploads

### Database Issues
- Transactional updates prevent inconsistent states
- Rollback support for complex operations
- Error logging for troubleshooting

## Migration Considerations

### Existing Files
- System works with existing files immediately
- No migration required for current uploads
- Gradual replacement as users update their images

### Backward Compatibility
- All existing file paths continue to work
- Database references remain valid
- No breaking changes to existing functionality

## Best Practices

1. **Always use auto-replace methods** for user-initiated updates
2. **Check upload results** before updating database
3. **Use transactions** for database updates
4. **Run cleanup script regularly** to maintain disk space
5. **Monitor disk usage** and file counts
6. **Test file operations** in development environment first

This file replacement system ensures your LMS maintains a clean, efficient file structure while providing robust image management capabilities.