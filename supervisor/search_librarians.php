<?php
// Set headers for JSON response
header('Content-Type: application/json');

// Database connection (adjust these settings according to your configuration)
$host = 'localhost';
$dbname = 'lms_db';
$username = 'root';
$password = '';

// Get search term from request
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10; // Number of results per page
$offset = ($page - 1) * $perPage;

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Build the base query - now including the numeric 'id' field
    $query = "SELECT id, user_id, first_name, last_name, email 
              FROM users 
              WHERE role = 'librarian' 
                AND (first_name LIKE :search 
                     OR last_name LIKE :search 
                     OR email LIKE :search 
                     OR CONCAT(first_name, ' ', last_name) LIKE :search)
              ORDER BY first_name, last_name";
    
    // Count total results (for pagination)
    $countStmt = $pdo->prepare(str_replace('id, user_id, first_name, last_name, email', 'COUNT(*) as total', $query));
    $countStmt->bindValue(':search', "%$searchTerm%", PDO::PARAM_STR);
    $countStmt->execute();
    $totalCount = $countStmt->fetchColumn();
    
    // Add pagination to the main query
    $query .= " LIMIT :offset, :perPage";
    $stmt = $pdo->prepare($query);
    
    // Bind parameters
    $stmt->bindValue(':search', "%$searchTerm%", PDO::PARAM_STR);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
    
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format results for Select2 - using numeric ID as 'id' and including user_id for reference
    $formattedResults = [];
    foreach ($results as $row) {
        $formattedResults[] = [
            'id' => $row['id'],  // Using numeric ID as the primary identifier
            'user_id' => $row['user_id'],  // Including user_id for reference if needed
            'text' => $row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['email'] . ')',
            'email' => $row['email']
        ];
    }
    
    // Return JSON response
    echo json_encode([
        'results' => $formattedResults,
        'pagination' => [
            'more' => ($offset + count($results)) < $totalCount
        ]
    ]);
    
} catch (PDOException $e) {
    // Log error and return empty result on error
    error_log('Database error in search_librarians.php: ' . $e->getMessage());
    echo json_encode([
        'results' => [],
        'pagination' => ['more' => false],
        'error' => 'An error occurred while searching for librarians.'
    ]);
}