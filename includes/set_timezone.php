<?php
/**
 * Set user timezone endpoint
 * Receives timezone from client-side and sets it in the session
 */

// Define access constant
define('LMS_ACCESS', true);

// Load configuration
require_once 'EnvLoader.php';
EnvLoader::load();
require_once '../config/config.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

try {
    // Get JSON data from request
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['timezone'])) {
        echo json_encode(['success' => false, 'message' => 'No timezone provided']);
        exit;
    }
    
    $timezone = $input['timezone'];
    
    // Validate timezone
    if (!in_array($timezone, timezone_identifiers_list())) {
        echo json_encode(['success' => false, 'message' => 'Invalid timezone']);
        exit;
    }
    
    // Use the config function to set timezone
    if (setUserTimezone($timezone)) {
        echo json_encode(['success' => true, 'message' => 'Timezone set successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to set timezone']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error setting timezone: ' . $e->getMessage()]);
}
?>