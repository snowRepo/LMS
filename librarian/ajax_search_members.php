<?php
// Define access constant
define('LMS_ACCESS', true);

// Load configuration and database connection
require_once '../config/config.php';

// Get search term from request
$searchTerm = isset($_GET['term']) ? trim($_GET['term']) : '';

// Validate search term
if (empty($searchTerm)) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Prepare query to search members
    $query = "SELECT user_id, first_name, last_name, email 
              FROM users 
              WHERE role = 'member' 
                AND (first_name LIKE :search1 
                     OR last_name LIKE :search2 
                     OR email LIKE :search3 
                     OR CONCAT(first_name, ' ', last_name) LIKE :search4)
              ORDER BY first_name, last_name
              LIMIT 10";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':search1', "%$searchTerm%", PDO::PARAM_STR);
    $stmt->bindValue(':search2', "%$searchTerm%", PDO::PARAM_STR);
    $stmt->bindValue(':search3', "%$searchTerm%", PDO::PARAM_STR);
    $stmt->bindValue(':search4', "%$searchTerm%", PDO::PARAM_STR);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format results for the messages page
    $formattedResults = [];
    foreach ($results as $row) {
        $formattedResults[] = [
            'id' => $row['user_id'],
            'name' => $row['first_name'] . ' ' . $row['last_name'],
            'email' => $row['email']
        ];
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($formattedResults);
    
} catch (Exception $e) {
    // Log error and return empty result on error
    error_log('Database error in ajax_search_members.php: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([]);
}