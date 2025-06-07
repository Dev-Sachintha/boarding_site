<?php
// =========================================================================
// ERROR REPORTING (Development: On, Production: Off/Log)
// =========================================================================
ini_set('display_errors', 1); // Set to 0 in production
ini_set('display_startup_errors', 1); // Set to 0 in production
error_reporting(E_ALL);

// =========================================================================
// CORS HEADERS (Adjust as needed for production)
// =========================================================================
// Allow all origins for development. For production, restrict to your frontend's domain.
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Cache-Control");
header("Access-Control-Allow-Credentials: true"); // If you plan to use cookies/sessions across domains (requires specific Origin)

// Handle OPTIONS pre-flight request (sent by browsers for certain cross-origin requests)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204); // 204 No Content
    exit;
}

// =========================================================================
// JSON RESPONSE HELPER FUNCTION
// =========================================================================
/**
 * Sends a JSON response and exits the script.
 *
 * @param mixed $data The data to encode as JSON. Can be an array or object.
 * @param int $statusCode The HTTP status code to send.
 */
if (!function_exists('send_json_response')) {
    function send_json_response($data, $statusCode = 200)
    {
        // Check if headers have already been sent to prevent errors
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode($data);
        exit; // Terminate script execution after sending response
    }
}

// =========================================================================
// DATABASE CONFIGURATION CONSTANTS
// =========================================================================
define('DB_SERVER', 'localhost');         // Usually 'localhost' for local development
define('DB_USERNAME', 'root');            // Your MySQL username (default for XAMPP is 'root')
define('DB_PASSWORD', '');                // Your MySQL password (default for XAMPP is empty)
define('DB_NAME', 'uniboard_db');         // The name of your database

// =========================================================================
// DATABASE CONNECTION
// =========================================================================
$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn === false) {
    // Log the detailed error for server-side debugging
    error_log("Database Connection Error: " . mysqli_connect_error());

    // Send a generic error message to the client
    send_json_response(
        [
            "success" => false,
            "message" => "Database connection failed. Please try again later or contact support."
            // "debug_message" => mysqli_connect_error() // Optional: for development only
        ],
        500 // Internal Server Error
    );
}

// Set character set to utf8mb4 for full Unicode support
if (!mysqli_set_charset($conn, "utf8mb4")) {
    error_log("Error loading character set utf8mb4: " . mysqli_error($conn));
    // Continue execution, but log the error. Could also choose to send_json_response here.
}

// =========================================================================
// SESSION MANAGEMENT (Start session for API calls that might need it)
// =========================================================================
if (session_status() == PHP_SESSION_NONE) {
    // More secure session cookie parameters
    session_set_cookie_params([
        'lifetime' => 86400, // Session cookie lifetime in seconds (e.g., 1 day)
        'path' => '/',       // Available for the entire domain
        'domain' => isset($_SERVER['HTTP_HOST']) ? preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']) : '', // Set domain dynamically, remove port
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // Send cookie only over HTTPS if applicable
        'httponly' => true,  // Prevent client-side JavaScript access to the session cookie
        'samesite' => 'Lax'  // Mitigates CSRF attacks. Use 'Strict' if appropriate.
    ]);
    session_start();
}

// Optional: Global settings or functions can be placed here if needed by other scripts.
