<?php
// Simple test script to check library counts
session_start();

// Simple database connection without all the security checks
$dbhost = 'localhost';
$dbuser = 'root';
$dbpass = '';
$dbname = 'lms_db';

try {
    $pdo = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>Library Count Tests</h1>";
    
    // Test 1: Count all libraries
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM libraries');
    $stmt->execute();
    $total = $stmt->fetch()['total'];
    echo "<p>Total libraries: " . $total . "</p>";
    
    // Test 2: Count with no filter (should be same as total)
    $countQuery = 'SELECT COUNT(*) as total FROM libraries WHERE 1=1';
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute();
    $totalNoFilter = $stmt->fetch()['total'];
    echo "<p>Total with no filter: " . $totalNoFilter . "</p>";
    
    // Test 3: Count deleted libraries
    $countQuery = 'SELECT COUNT(*) as total FROM libraries WHERE deleted_at IS NOT NULL';
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute();
    $totalDeleted = $stmt->fetch()['total'];
    echo "<p>Total deleted libraries: " . $totalDeleted . "</p>";
    
    // Test 4: Count active non-deleted libraries
    $countQuery = 'SELECT COUNT(*) as total FROM libraries WHERE status = ? AND deleted_at IS NULL';
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute(['active']);
    $totalActive = $stmt->fetch()['total'];
    echo "<p>Total active non-deleted libraries: " . $totalActive . "</p>";
    
    // Test 5: Count inactive non-deleted libraries
    $countQuery = 'SELECT COUNT(*) as total FROM libraries WHERE status = ? AND deleted_at IS NULL';
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute(['inactive']);
    $totalInactive = $stmt->fetch()['total'];
    echo "<p>Total inactive non-deleted libraries: " . $totalInactive . "</p>";
    
    echo "<p><a href='libraries.php'>Back to Libraries</a></p>";
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>