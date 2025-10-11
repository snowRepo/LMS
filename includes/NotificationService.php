<?php
/**
 * Notification Service for LMS
 * Handles creating and managing user notifications
 */

class NotificationService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Get the integer user ID from string user ID
     * 
     * @param string $userStringId The string user ID
     * @return int|null The integer user ID or null if not found
     */
    private function getUserIdFromString($userStringId) {
        try {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE user_id = ?");
            $stmt->execute([$userStringId]);
            $result = $stmt->fetch();
            return $result ? $result['id'] : null;
        } catch (Exception $e) {
            error_log("Error getting user ID from string: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create a new notification for a user
     * 
     * @param int|string $userId The user ID to send the notification to (can be integer ID or string user_id)
     * @param string $title The notification title
     * @param string $message The notification message
     * @param string $type The notification type (info, warning, success, error)
     * @param string|null $actionUrl Optional URL for action button
     * @return bool True if notification was created successfully
     */
    public function createNotification($userId, $title, $message, $type = 'info', $actionUrl = null) {
        try {
            // Check if userId is already an integer (users.id) or needs conversion from string (users.user_id)
            $integerUserId = is_numeric($userId) ? (int)$userId : $this->getUserIdFromString($userId);
            
            if (!$integerUserId) {
                throw new Exception("Invalid user ID: " . $userId);
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO notifications 
                (user_id, title, message, type, action_url, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([$integerUserId, $title, $message, $type, $actionUrl]);
            
            return $result;
        } catch (Exception $e) {
            error_log("Error creating notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create notifications for multiple users
     * 
     * @param array $userIds Array of user IDs to send the notification to
     * @param string $title The notification title
     * @param string $message The notification message
     * @param string $type The notification type (info, warning, success, error)
     * @param string|null $actionUrl Optional URL for action button
     * @return int Number of notifications created successfully
     */
    public function createNotifications($userIds, $title, $message, $type = 'info', $actionUrl = null) {
        $successCount = 0;
        
        foreach ($userIds as $userId) {
            if ($this->createNotification($userId, $title, $message, $type, $actionUrl)) {
                $successCount++;
            }
        }
        
        return $successCount;
    }
    
    /**
     * Mark a notification as read
     * 
     * @param int $notificationId The notification ID
     * @param int|string $userId The user ID (for security check)
     * @return bool True if notification was marked as read successfully
     */
    public function markAsRead($notificationId, $userId) {
        try {
            // Check if userId is already an integer (users.id) or needs conversion from string (users.user_id)
            $integerUserId = is_numeric($userId) ? (int)$userId : $this->getUserIdFromString($userId);
            
            if (!$integerUserId) {
                throw new Exception("Invalid user ID: " . $userId);
            }
            
            $stmt = $this->db->prepare("UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ?");
            $result = $stmt->execute([$notificationId, $integerUserId]);
            
            return $result && $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark all notifications as read for a user
     * 
     * @param int|string $userId The user ID
     * @return bool True if notifications were marked as read successfully
     */
    public function markAllAsRead($userId) {
        try {
            // Check if userId is already an integer (users.id) or needs conversion from string (users.user_id)
            $integerUserId = is_numeric($userId) ? (int)$userId : $this->getUserIdFromString($userId);
            
            if (!$integerUserId) {
                throw new Exception("Invalid user ID: " . $userId);
            }
            
            $stmt = $this->db->prepare("UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL");
            $result = $stmt->execute([$integerUserId]);
            
            return $result;
        } catch (Exception $e) {
            error_log("Error marking all notifications as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get notifications for a user
     * 
     * @param int|string $userId The user ID
     * @param int $limit Maximum number of notifications to retrieve
     * @param string|null $type Filter by notification type
     * @param bool $unreadOnly Whether to only retrieve unread notifications
     * @return array Array of notifications
     */
    public function getNotifications($userId, $limit = 100, $type = null, $unreadOnly = false) {
        try {
            // Check if userId is already an integer (users.id) or needs conversion from string (users.user_id)
            $integerUserId = is_numeric($userId) ? (int)$userId : $this->getUserIdFromString($userId);
            
            if (!$integerUserId) {
                throw new Exception("Invalid user ID: " . $userId);
            }
            
            $sql = "SELECT id, title, message, type, action_url, read_at, created_at 
                    FROM notifications 
                    WHERE user_id = ?";
            
            $params = [$integerUserId];
            
            if ($type) {
                $sql .= " AND type = ?";
                $params[] = $type;
            }
            
            if ($unreadOnly) {
                $sql .= " AND read_at IS NULL";
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error retrieving notifications: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get notification count for a user
     * 
     * @param int|string $userId The user ID
     * @param string|null $type Filter by notification type
     * @param bool $unreadOnly Whether to only count unread notifications
     * @return int Number of notifications
     */
    public function getNotificationCount($userId, $type = null, $unreadOnly = false) {
        try {
            // Check if userId is already an integer (users.id) or needs conversion from string (users.user_id)
            $integerUserId = is_numeric($userId) ? (int)$userId : $this->getUserIdFromString($userId);
            
            if (!$integerUserId) {
                throw new Exception("Invalid user ID: " . $userId);
            }
            
            $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ?";
            $params = [$integerUserId];
            
            if ($type) {
                $sql .= " AND type = ?";
                $params[] = $type;
            }
            
            if ($unreadOnly) {
                $sql .= " AND read_at IS NULL";
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetch()['count'];
        } catch (Exception $e) {
            error_log("Error retrieving notification count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get unread notification count for a user
     * 
     * @param int|string $userId The user ID
     * @return int Number of unread notifications
     */
    public function getUnreadCount($userId) {
        try {
            // Check if userId is already an integer (users.id) or needs conversion from string (users.user_id)
            $integerUserId = is_numeric($userId) ? (int)$userId : $this->getUserIdFromString($userId);
            
            if (!$integerUserId) {
                throw new Exception("Invalid user ID: " . $userId);
            }
            
            $stmt = $this->db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND read_at IS NULL");
            $stmt->execute([$integerUserId]);
            
            return $stmt->fetch()['unread_count'];
        } catch (Exception $e) {
            error_log("Error retrieving unread notification count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Create a system notification for all users in a library
     * 
     * @param int $libraryId The library ID
     * @param string $title The notification title
     * @param string $message The notification message
     * @param string $type The notification type (info, warning, success, error)
     * @param string|null $actionUrl Optional URL for action button
     * @return int Number of notifications created successfully
     */
    public function createLibraryNotification($libraryId, $title, $message, $type = 'info', $actionUrl = null) {
        try {
            // Get all active users in the library
            $stmt = $this->db->prepare("SELECT user_id FROM users WHERE library_id = ? AND status = 'active'");
            $stmt->execute([$libraryId]);
            $users = $stmt->fetchAll();
            
            $userIds = array_column($users, 'user_id');
            
            return $this->createNotifications($userIds, $title, $message, $type, $actionUrl);
        } catch (Exception $e) {
            error_log("Error creating library notification: " . $e->getMessage());
            return 0;
        }
    }
}
?>