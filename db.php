<?php
// CORS headers to allow requests from any origin (essential for mobile & GitHub Pages web frontend)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Simple helper to load .env file manually
function loadEnv($dir) {
    $filePath = rtrim($dir, '/') . '/.env';
    if (!file_exists($filePath)) {
        return; // File doesn't exist, will rely on system environment variables (e.g. in cloud hosting)
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Split name and value
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $name = trim($parts[0]);
            $value = trim($parts[1]);
            
            // Remove quotes if present
            $value = trim($value, "\"'");

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// Load env file in the current directory
loadEnv(__DIR__);

// Helper to get environment variable with fallback
function env($key, $default = '') {
    $val = getenv($key);
    return $val !== false ? $val : (isset($_ENV[$key]) ? $_ENV[$key] : $default);
}

// Database Credentials from Environment Variables
// Supports custom variables (DB_HOST) or Clever Cloud default variables (MYSQL_ADDON_HOST)
$host     = env('DB_HOST', env('MYSQL_ADDON_HOST', 'localhost'));
$db_name  = env('DB_NAME', env('MYSQL_ADDON_DB', 'react_native_db'));
$username = env('DB_USER', env('MYSQL_ADDON_USER', 'root'));
$password = env('DB_PASS', env('MYSQL_ADDON_PASSWORD', ''));
$conn     = null;

try {
    $conn = new PDO("mysql:host=" . $host . ";dbname=" . $db_name . ";charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $exception) {
    // Return connection error
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed. Check your environment configuration."
    ]);
    exit();
}

// Helper function to send JSON responses
function sendResponse($status, $message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        "status" => $status,
        "message" => $message,
        "data" => $data
    ]);
    exit();
}

// Helper function to parse JSON input
function getJsonInput() {
    $raw = file_get_contents("php://input");
    return json_decode($raw, true) ?: [];
}

// Helper function to authenticate user via Token header
function authenticateUser($conn) {
    $headers = getallheaders();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
    // Check if Authorization header is in format "Bearer <token>"
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
        
        // Find user by token
        $stmt = $conn->prepare("SELECT id, name, email, phone, bio FROM users WHERE token = :token LIMIT 1");
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch();
        
        if ($user) {
            return $user;
        }
    }
    
    sendResponse("error", "Unauthorized access. Invalid or missing token.", [], 401);
}
?>
