# LMS Upload System Documentation

## Overview
The LMS upload system provides a secure, organized way to handle file uploads for library logos, user profile images, and book cover images.

## Directory Structure
```
uploads/
├── .htaccess              # Security configuration
├── index.php             # Prevents directory browsing
├── logos/                # Library logos
│   └── index.php
├── profiles/             # User profile images
│   └── index.php
├── books/                # Book cover images
│   └── index.php
└── temp/                 # Temporary files
    └── index.php
```

## Key Features

### File Replacement System
- **Automatic Replacement**: When uploading a new file for the same entity (user, library, book), the old file is automatically deleted
- **Manual Replacement**: You can specify exactly which file to replace
- **Auto-Discovery**: The system can find existing files by identifier and replace them
- **Clean Directory**: No accumulation of old, unused files

### Security Features
- **File Type Validation**: Only allows image files (JPEG, PNG, GIF, WebP)
- **File Size Limits**: Configurable limits per file type
- **Script Execution Prevention**: .htaccess prevents execution of any scripts
- **Directory Browsing Protection**: index.php files prevent directory listing
- **MIME Type Validation**: Server-side validation of actual file content

## File Size Limits
- **Library Logos**: 2MB maximum
- **Profile Images**: 1MB maximum  
- **Book Covers**: 5MB maximum
- **General Default**: 2MB maximum

## Usage

### Using FileUploadHandler Class
```php
require_once 'includes/FileUploadHandler.php';

$uploader = new FileUploadHandler();

// NEW UPLOADS (First time)
// Upload library logo
$result = $uploader->uploadLibraryLogo($_FILES['logo'], $libraryId);

// Upload profile image
$result = $uploader->uploadProfileImage($_FILES['profile'], $userId);

// Upload book cover
$result = $uploader->uploadBookImage($_FILES['book_cover'], $bookId);

// REPLACING EXISTING FILES
// Option 1: Auto-replace (automatically finds and deletes old file)
$result = $uploader->uploadLibraryLogoWithAutoReplace($_FILES['logo'], $libraryId);
$result = $uploader->uploadProfileImageWithAutoReplace($_FILES['profile'], $userId);
$result = $uploader->uploadBookImageWithAutoReplace($_FILES['book_cover'], $bookId);

// Option 2: Manual replacement (when you know the old file path)
$oldLogoPath = 'uploads/logos/logo_123_1634567890_5678.jpg';
$result = $uploader->uploadLibraryLogo($_FILES['logo'], $libraryId, $oldLogoPath);

if ($result['success']) {
    echo "File uploaded: " . $result['path'];
} else {
    echo "Upload failed: " . $result['error'];
}
```

### Upload Result Format
```php
// Success
[
    'success' => true,
    'filename' => 'logo_123456789_1234.jpg',
    'path' => 'uploads/logos/logo_123456789_1234.jpg',
    'full_path' => '/full/system/path/to/file.jpg'
]

// Error
[
    'success' => false,
    'error' => 'Error message here'
]
```

## Configuration
Upload settings are defined in `config/upload_config.php`:

- **UPLOAD_BASE_DIR**: Base upload directory
- **MAX_LOGO_SIZE**: Maximum logo file size
- **MAX_PROFILE_SIZE**: Maximum profile image size  
- **MAX_BOOK_SIZE**: Maximum book cover size
- **ALLOWED_IMAGE_TYPES**: Allowed MIME types and extensions

## Maintenance

### Automatic Cleanup
Run the cleanup script regularly via cron:
```bash
# Run daily at 2 AM
0 2 * * * /usr/bin/php /path/to/LMS/includes/cleanup.php
```

### Manual Cleanup
```bash
cd /path/to/LMS
php includes/cleanup.php
```

The cleanup script:
- Removes temporary files older than 24 hours
- **Removes orphaned files** (files that exist on disk but not in database)
- Checks directory permissions
- Reports directory sizes
- Creates missing security files

### Directory Permissions
- **Upload directories**: 755 (rwxr-xr-x)
- **Upload files**: 644 (rw-r--r--)

## Security Best Practices

1. **Never trust client-side validation** - Always validate server-side
2. **Check file content** - Use `getimagesize()` and `finfo` to verify files
3. **Generate unique filenames** - Prevent file overwrites and conflicts
4. **Store files outside document root** - Consider moving uploads outside web-accessible directory
5. **Regular cleanup** - Remove orphaned and temporary files
6. **Monitor disk usage** - Implement disk space monitoring

## Troubleshooting

### Common Issues
1. **Upload fails with "Permission denied"**
   - Check directory permissions (755 for directories, 644 for files)
   - Ensure web server has write access

2. **File size errors**
   - Check PHP settings: `upload_max_filesize`, `post_max_size`, `memory_limit`
   - Verify file size against defined limits

3. **Invalid file type errors**
   - File may be corrupted or have wrong extension
   - Check if file is actually an image using image validation

### PHP Configuration
Recommended php.ini settings:
```ini
upload_max_filesize = 10M
post_max_size = 12M
max_execution_time = 60
memory_limit = 128M
```

## File Naming Convention
Files are automatically renamed using the pattern:
`{prefix}_{identifier}_{timestamp}_{random}.{extension}`

Examples:
- `logo_123_1634567890_5678.jpg`
- `profile_456_1634567890_9012.png`
- `book_789_1634567890_3456.gif`

This ensures:
- No file overwrites
- Easy identification of file purpose
- Timestamp for cleanup/auditing
- Random component for security