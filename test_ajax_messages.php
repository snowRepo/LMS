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

// Check if user is logged in and has librarian role
if (!is_logged_in() || $_SESSION['user_role'] !== 'librarian') {
    echo "You must be logged in as a librarian to test this.";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>AJAX Messages Test</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <h1>AJAX Messages Test</h1>
    
    <button id="testInbox">Test Inbox Messages</button>
    <button id="testSent">Test Sent Messages</button>
    <button id="testStarred">Test Starred Messages</button>
    
    <div id="result" style="margin-top: 20px; padding: 10px; border: 1px solid #ccc;">
        <p>Click a button above to test.</p>
    </div>
    
    <script>
        $('#testInbox').click(function() {
            $.get('librarian/fetch_messages.php?view=inbox', function(data) {
                $('#result').html('<h3>Inbox Messages:</h3><pre>' + JSON.stringify(data, null, 2) + '</pre>');
            }).fail(function() {
                $('#result').html('<p style="color: red;">Error fetching inbox messages</p>');
            });
        });
        
        $('#testSent').click(function() {
            $.get('librarian/fetch_messages.php?view=sent', function(data) {
                $('#result').html('<h3>Sent Messages:</h3><pre>' + JSON.stringify(data, null, 2) + '</pre>');
            }).fail(function() {
                $('#result').html('<p style="color: red;">Error fetching sent messages</p>');
            });
        });
        
        $('#testStarred').click(function() {
            $.get('librarian/fetch_messages.php?view=starred', function(data) {
                $('#result').html('<h3>Starred Messages:</h3><pre>' + JSON.stringify(data, null, 2) + '</pre>');
            }).fail(function() {
                $('#result').html('<p style="color: red;">Error fetching starred messages</p>');
            });
        });
    </script>
    
    <br><br>
    <a href="librarian/messages.php">← Back to Messages</a>
</body>
</html>