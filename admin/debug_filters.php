<?php
// Debug script to test filter functionality
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

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Libraries per page
$offset = ($page - 1) * $limit;

echo "<h1>Debug Filter Values</h1>";
echo "<p>Status Filter: '" . htmlspecialchars($statusFilter) . "'</p>";
echo "<p>Search: '" . htmlspecialchars($search) . "'</p>";
echo "<p>Page: " . $page . "</p>";

try {
    $db = Database::getInstance()->getConnection();
    
    // Get total libraries count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM libraries WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $countQuery .= " AND (library_name LIKE ? OR library_code LIKE ? OR address LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($statusFilter)) {
        if ($statusFilter === 'deleted') {
            $countQuery .= " AND deleted_at IS NOT NULL";
        } else {
            $countQuery .= " AND status = ? AND deleted_at IS NULL";
            $params[] = $statusFilter;
        }
    }
    
    echo "<h2>Count Query:</h2>";
    echo "<pre>" . htmlspecialchars($countQuery) . "</pre>";
    echo "<p>Count Params: " . print_r($params, true) . "</p>";
    
    $stmt = $db->prepare($countQuery);
    $stmt->execute($params);
    $totalLibraries = $stmt->fetch()['total'];
    $totalPages = ceil($totalLibraries / $limit);
    
    echo "<p>Total Libraries: " . $totalLibraries . "</p>";
    echo "<p>Total Pages: " . $totalPages . "</p>";
    
    // Get libraries for current page
    $query = "SELECT l.*, u.first_name, u.last_name FROM libraries l LEFT JOIN users u ON l.supervisor_id = u.id WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $query .= " AND (l.library_name LIKE ? OR l.library_code LIKE ? OR l.address LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($statusFilter)) {
        if ($statusFilter === 'deleted') {
            $query .= " AND l.deleted_at IS NOT NULL";
        } else {
            $query .= " AND l.status = ? AND l.deleted_at IS NULL";
            $params[] = $statusFilter;
        }
    }
    
    $query .= " ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    echo "<h2>Data Query:</h2>";
    echo "<pre>" . htmlspecialchars($query) . "</pre>";
    echo "<p>Data Params: " . print_r($params, true) . "</p>";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $libraries = $stmt->fetchAll();
    
    echo "<h2>Libraries Found: " . count($libraries) . "</h2>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Name</th><th>Status</th><th>Deleted At</th></tr>";
    foreach ($libraries as $library) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($library['id']) . "</td>";
        echo "<td>" . htmlspecialchars($library['library_name']) . "</td>";
        echo "<td>" . htmlspecialchars($library['status']) . "</td>";
        echo "<td>" . (!empty($library['deleted_at']) ? htmlspecialchars($library['deleted_at']) : 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='libraries.php'>Back to Libraries</a></p>";
echo "<p><a href='debug_filters.php'>Test All Statuses</a></p>";
echo "<p><a href='debug_filters.php?status=active'>Test Active</a></p>";
echo "<p><a href='debug_filters.php?status=inactive'>Test Inactive</a></p>";
echo "<p><a href='debug_filters.php?status=deleted'>Test Deleted</a></p>";