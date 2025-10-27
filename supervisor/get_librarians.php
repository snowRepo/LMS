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

// Check if user is logged in and has appropriate role
if (!is_logged_in() || !in_array($_SESSION['user_role'], ['supervisor', 'librarian', 'member'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Get librarians from the same library
    $stmt = $db->prepare("
        SELECT user_id, first_name, last_name, email
        FROM users 
        WHERE library_id = ? AND role = 'librarian' AND status = 'active'
        ORDER BY first_name, last_name
    ");
    $stmt->execute([$_SESSION['library_id']]);
    $librarians = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'librarians' => $librarians]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error fetching librarians: ' . $e->getMessage()]);
}
?>