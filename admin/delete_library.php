<?php
define('LMS_ACCESS', true);

// Load configuration
require_once '../includes/EnvLoader.php';
EnvLoader::load();
include '../config/config.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has admin role
if (!is_logged_in() || $_SESSION['user_role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Get the library ID from the request
    $input = json_decode(file_get_contents('php://input'), true);
    $libraryId = isset($input['library_id']) ? (int)$input['library_id'] : 0;
    
    if ($libraryId <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid library ID']);
        exit;
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // 1. Set the library as deleted with current timestamp
    $stmt = $db->prepare("UPDATE libraries SET deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$libraryId]);
    
    // 2. Deactivate all associated users (supervisors, librarians, members)
    $stmt = $db->prepare("UPDATE users SET status = 'inactive' WHERE library_id = ?");
    $stmt->execute([$libraryId]);
    
    // 3. Set library status to deleted
    $stmt = $db->prepare("UPDATE libraries SET status = 'inactive' WHERE id = ?");
    $stmt->execute([$libraryId]);
    
    // 4. Log the action
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, library_id, action, description) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'], 
        $libraryId, 
        'delete_library', 
        'Library deleted by admin user ' . $_SESSION['username']
    ]);
    
    // Commit transaction
    $db->commit();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Library deleted successfully']);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollback();
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error deleting library: ' . $e->getMessage()]);
}