<?php
require_once 'db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse("error", "Method not allowed. Use POST.", [], 405);
}

// Get JSON input
$input = getJsonInput();

$name = isset($input['name']) ? trim($input['name']) : '';
$email = isset($input['email']) ? trim($input['email']) : '';
$password = isset($input['password']) ? trim($input['password']) : '';

// Validation
if (empty($name) || empty($email) || empty($password)) {
    sendResponse("error", "Please fill in all required fields (name, email, password).", [], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse("error", "Invalid email format.", [], 400);
}

if (strlen($password) < 6) {
    sendResponse("error", "Password must be at least 6 characters long.", [], 400);
}

try {
    // Check if email already exists
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $checkStmt->execute(['email' => $email]);
    if ($checkStmt->fetch()) {
        sendResponse("error", "Email is already registered.", [], 409);
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
    // Insert user
    $insertStmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (:name, :email, :password)");
    $insertStmt->execute([
        'name' => $name,
        'email' => $email,
        'password' => $hashedPassword
    ]);
    
    sendResponse("success", "Registration successful! You can now log in.", [], 201);
    
} catch (PDOException $e) {
    sendResponse("error", "Server error during registration: " . $e->getMessage(), [], 500);
}
?>
