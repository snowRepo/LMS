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

// Check if user is logged in and has librarian role
if (!is_logged_in() || $_SESSION['user_role'] !== 'librarian') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $libraryId = $_SESSION['library_id'];
    
    // Handle different actions
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'get_categories':
                // Get all categories for this library
                $stmt = $db->prepare("SELECT id, name FROM categories WHERE library_id = ? AND status = 'active' ORDER BY name");
                $stmt->execute([$libraryId]);
                $categories = $stmt->fetchAll();
                header('Content-Type: application/json');
                echo json_encode(['categories' => $categories]);
                break;
                
            default:
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Invalid action']);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_category':
                // Add a new category
                $categoryName = trim($_POST['category_name']);
                
                if (empty($categoryName)) {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Category name is required']);
                    exit;
                }
                
                // Check if category already exists
                $stmt = $db->prepare("SELECT id FROM categories WHERE name = ? AND library_id = ?");
                $stmt->execute([$categoryName, $libraryId]);
                $existingCategory = $stmt->fetch();
                
                if ($existingCategory) {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Category already exists']);
                    exit;
                }
                
                // Insert new category
                $stmt = $db->prepare("INSERT INTO categories (name, library_id, created_by) VALUES (?, ?, ?)");
                $result = $stmt->execute([$categoryName, $libraryId, $_SESSION['user_id']]);
                
                if ($result) {
                    $newCategoryId = $db->lastInsertId();
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'category_id' => $newCategoryId,
                        'category_name' => $categoryName
                    ]);
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Failed to add category']);
                }
                break;
                
            default:
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Invalid action']);
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid request']);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>