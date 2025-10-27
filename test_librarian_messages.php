<?php
define('LMS_ACCESS', true);

// Load configuration
require_once 'includes/EnvLoader.php';
EnvLoader::load();
include 'config/config.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

echo "<h1>Librarian Messages Test</h1>";

// Check if user is logged in and has librarian role
if (!is_logged_in() || $_SESSION['user_role'] !== 'librarian') {
    echo "<p>You must be logged in as a librarian to test this.</p>";
    echo "<a href='login.php'>Login</a>";
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Get the user string ID from the integer session ID
    $stmt = $db->prepare("SELECT user_id, first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "<p>Error: User not found</p>";
        exit();
    }
    
    $user_string_id = $user['user_id'];
    $full_name = $user['first_name'] . ' ' . $user['last_name'];
    
    echo "<h2>User Information</h2>";
    echo "<p><strong>User ID:</strong> " . $_SESSION['user_id'] . "</p>";
    echo "<p><strong>User String ID:</strong> " . $user_string_id . "</p>";
    echo "<p><strong>Name:</strong> " . $full_name . "</p>";
    echo "<p><strong>Role:</strong> " . $_SESSION['user_role'] . "</p>";
    echo "<p><strong>Library ID:</strong> " . $_SESSION['library_id'] . "</p>";
    
    // Get inbox messages (not deleted and not sent by current user)
    echo "<h2>Inbox Messages</h2>";
    $stmt = $db->prepare("
        SELECT m.*, 
               CONCAT(u.first_name, ' ', u.last_name) as sender_name,
               m.created_at as message_time,
               u.user_id as sender_id
        FROM messages m
        JOIN users u ON m.sender_id = u.user_id
        WHERE m.recipient_id = ? AND m.is_deleted = 0
        ORDER BY m.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_string_id]);
    $inboxMessages = $stmt->fetchAll();
    
    echo "<p><strong>Found " . count($inboxMessages) . " inbox messages</strong></p>";
    
    if (count($inboxMessages) > 0) {
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>ID</th><th>Sender</th><th>Subject</th><th>Message</th><th>Date</th><th>Read</th></tr>";
        foreach ($inboxMessages as $message) {
            echo "<tr>";
            echo "<td>" . $message['id'] . "</td>";
            echo "<td>" . htmlspecialchars($message['sender_name']) . "</td>";
            echo "<td>" . htmlspecialchars($message['subject']) . "</td>";
            echo "<td>" . htmlspecialchars(substr($message['message'], 0, 50)) . "...</td>";
            echo "<td>" . $message['message_time'] . "</td>";
            echo "<td>" . ($message['is_read'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No inbox messages found.</p>";
    }
    
    // Count unread messages
    $stmt = $db->prepare("SELECT COUNT(*) as unread_count FROM messages WHERE recipient_id = ? AND is_read = 0 AND is_deleted = 0");
    $stmt->execute([$user_string_id]);
    $unreadCount = $stmt->fetch()['unread_count'];
    
    echo "<h2>Unread Messages</h2>";
    echo "<p><strong>Unread count:</strong> " . $unreadCount . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<br><a href='librarian/messages.php'>← Back to Messages</a>";
?>