<?php
// Simple test script to verify delete functionality
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
    header('Location: ../login.php');
    exit;
}

echo "<h1>Library Delete Functionality Test</h1>";
echo "<p>This page tests the library delete functionality.</p>";

// Show a simple form to test deletion
echo "<form method='post'>";
echo "<label for='library_id'>Library ID to test:</label>";
echo "<input type='number' name='library_id' id='library_id' required>";
echo "<button type='submit' name='delete'>Test Delete</button>";
echo "<button type='submit' name='undelete'>Test Undelete</button>";
echo "</form>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance()->getConnection();
        $libraryId = (int)$_POST['library_id'];
        
        if (isset($_POST['delete'])) {
            // Test delete
            $stmt = $db->prepare("UPDATE libraries SET deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$libraryId]);
            
            $stmt = $db->prepare("UPDATE users SET status = 'inactive' WHERE library_id = ?");
            $stmt->execute([$libraryId]);
            
            echo "<p style='color: green;'>Library ID $libraryId marked as deleted and users deactivated.</p>";
        } elseif (isset($_POST['undelete'])) {
            // Test undelete
            $stmt = $db->prepare("UPDATE libraries SET deleted_at = NULL WHERE id = ?");
            $stmt->execute([$libraryId]);
            
            $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE library_id = ? AND status = 'inactive'");
            $stmt->execute([$libraryId]);
            
            echo "<p style='color: green;'>Library ID $libraryId undeleted and users reactivated.</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
}

echo "<p><a href='libraries.php'>Back to Libraries</a></p>";