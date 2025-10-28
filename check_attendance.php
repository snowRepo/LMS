<?php
define('LMS_ACCESS', true);

// Load configuration
require_once 'includes/EnvLoader.php';
EnvLoader::load();
include 'config/config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Checking attendance records...\n";
    
    // Get recent attendance records
    $stmt = $db->query("SELECT * FROM attendance ORDER BY id DESC LIMIT 10");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($records) . " attendance records:\n";
    
    foreach ($records as $record) {
        echo "ID: " . $record['id'] . "\n";
        echo "User ID: " . $record['user_id'] . "\n";
        echo "Library ID: " . $record['library_id'] . "\n";
        echo "Attendance Date: " . $record['attendance_date'] . "\n";
        echo "Arrival Time: " . ($record['arrival_time'] ?? 'NULL') . "\n";
        echo "Departure Time: " . ($record['departure_time'] ?? 'NULL') . "\n";
        echo "Created At: " . $record['created_at'] . "\n";
        echo "------------------------\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>