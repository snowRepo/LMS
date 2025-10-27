<?php
/**
 * Simple Environment Variable Loader for LMS
 * Loads environment variables from .env file
 */

// Prevent direct access
if (!defined('LMS_ACCESS')) {
    die('Direct access not allowed');
}

class EnvLoader {
    private static $loaded = false;
    private static $env = [];
    
    /**
     * Load environment variables from .env file
     */
    public static function load($envPath = null) {
        if (self::$loaded) {
            return;
        }
        
        if ($envPath === null) {
            $envPath = __DIR__ . '/../.env';
        }
        
        if (!file_exists($envPath)) {
            // Try .env.example as fallback (for first time setup)
            $examplePath = __DIR__ . '/../.env.example';
            if (file_exists($examplePath)) {
                throw new Exception(
                    ".env file not found. Please copy .env.example to .env and configure your settings.\n" .
                    "Command: cp .env.example .env"
                );
            } else {
                throw new Exception(".env file not found and no .env.example template available");
            }
        }
        
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse KEY=VALUE pairs
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                
                $name = trim($name);
                $value = trim($value);
                
                // Remove quotes if present
                if (preg_match('/^"(.*)"$/', $value) || preg_match("/^'(.*)'$/", $value)) {
                    $value = substr($value, 1, -1);
                }
                
                // Convert string booleans to actual booleans
                if (strtolower($value) === 'true') {
                    $value = true;
                } elseif (strtolower($value) === 'false') {
                    $value = false;
                } elseif (is_numeric($value)) {
                    // Convert numeric strings to numbers
                    $value = is_float($value + 0) ? (float)$value : (int)$value;
                }
                
                // Store in our env array and set as environment variable
                self::$env[$name] = $value;
                $_ENV[$name] = $value;
                putenv("$name=$value");
            }
        }
        
        self::$loaded = true;
    }
    
    /**
     * Get environment variable with optional default
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }
        
        // Try our internal array first
        if (array_key_exists($key, self::$env)) {
            return self::$env[$key];
        }
        
        // Try $_ENV
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }
        
        // Try getenv()
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        
        return $default;
    }
    
    /**
     * Check if environment variable exists
     */
    public static function has($key) {
        if (!self::$loaded) {
            self::load();
        }
        
        return array_key_exists($key, self::$env) || 
               array_key_exists($key, $_ENV) || 
               getenv($key) !== false;
    }
    
    /**
     * Get all environment variables
     */
    public static function all() {
        if (!self::$loaded) {
            self::load();
        }
        
        return self::$env;
    }
    
    /**
     * Validate required environment variables
     */
    public static function validateRequired($required = []) {
        $missing = [];
        
        foreach ($required as $key) {
            if (!self::has($key) || empty(self::get($key))) {
                $missing[] = $key;
            }
        }
        
        if (!empty($missing)) {
            throw new Exception(
                "Missing required environment variables: " . implode(', ', $missing) . "\n" .
                "Please check your .env file configuration."
            );
        }
        
        return true;
    }
    
    /**
     * Get database configuration array
     */
    public static function getDatabaseConfig() {
        return [
            'host' => self::get('DB_HOST', 'localhost'),
            'username' => self::get('DB_USERNAME', 'root'),
            'password' => self::get('DB_PASSWORD', ''),
            'database' => self::get('DB_NAME', 'lms_db'),
            'charset' => self::get('DB_CHARSET', 'utf8mb4')
        ];
    }
    
    /**
     * Get email configuration array
     */
    public static function getEmailConfig() {
        return [
            'host' => self::get('SMTP_HOST', 'smtp.gmail.com'),
            'port' => self::get('SMTP_PORT', 587),
            'username' => self::get('SMTP_USERNAME', ''),
            'password' => self::get('SMTP_PASSWORD', ''),
            'encryption' => self::get('SMTP_ENCRYPTION', 'tls'),
            'from_email' => self::get('FROM_EMAIL', ''),
            'from_name' => self::get('FROM_NAME', 'LMS'),
            'reply_to' => self::get('REPLY_TO_EMAIL', '')
        ];
    }
    
    /**
     * Check if running in development mode
     */
    public static function isDevelopment() {
        return self::get('APP_ENV', 'production') === 'development';
    }
    
    /**
     * Check if debug mode is enabled
     */
    public static function isDebugMode() {
        return self::get('DEBUG_MODE', false) === true;
    }
}

/**
 * Helper function to get environment variable
 */
function env($key, $default = null) {
    return EnvLoader::get($key, $default);
}
?>