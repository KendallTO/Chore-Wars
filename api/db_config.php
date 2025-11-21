<?php
/**
 * Database Configuration File
 * 
 * IMPORTANT: Update these values with your actual database credentials
 * This file should be placed outside the public web directory for security
 * or protected with .htaccess
 */
// Prevent direct access
defined('DB_CONFIG_LOADED') or define('DB_CONFIG_LOADED', true);

// Database connection settings
// TODO: Replace these with your actual cPanel/hosting database credentials
define('DB_HOST', 'localhost');           // Usually 'localhost' for cPanel
define('DB_NAME', 'your_database_name');  // Your database name from cPanel
define('DB_USER', 'your_database_user');  // Your database username
define('DB_PASS', 'your_database_pass');  // Your database password
define('DB_CHARSET', 'utf8mb4');

/**
 * Create a database connection using mysqli
 * 
 * @return mysqli|null Returns mysqli connection or null on failure
 */
function getDBConnection() {
    try {
        // Create connection
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        // Check connection
        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
            return null;
        }
        
        // Set charset to prevent SQL injection via charset
        if (!$conn->set_charset(DB_CHARSET)) {
            error_log("Error loading character set " . DB_CHARSET . ": " . $conn->error);
            $conn->close();
            return null;
        }
        
        return $conn;
        
    } catch (Exception $e) {
        error_log("Database connection exception: " . $e->getMessage());
        return null;
    }
}

/**
 * Close database connection
 * 
 * @param mysqli $conn The connection to close
 */
function closeDBConnection($conn) {
    if ($conn && $conn instanceof mysqli) {
        $conn->close();
    }
}

/**
 * Send JSON response and exit
 * 
 * @param array $data Data to send as JSON
 * @param int $statusCode HTTP status code (default: 200)
 */
function sendJSON($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Send error response and exit
 * 
 * @param string $message Error message
 * @param int $statusCode HTTP status code (default: 400)
 */
function sendError($message, $statusCode = 400) {
    sendJSON(['error' => $message], $statusCode);
}

// Enable error reporting for development (disable in production)
// TODO: Set to 0 in production environment
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to users
ini_set('log_errors', 1);     // Log errors to file
?>
