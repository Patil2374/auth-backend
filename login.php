<?php
require_once 'db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse("error", "Method not allowed. Use POST.", [], 405);
}

// Get JSON input
$input = getJsonInput();

$email = isset($input['email']) ? trim($input['email']) : '';
$password = isset($input['password']) ? trim($input['password']) : '';

// Validation
if (empty($email) || empty($password)) {
    sendResponse("error", "Email and password are required.", [], 400);
}

try {
    // Fetch user
    $stmt = $conn->prepare("SELECT id, name, email, password, phone, bio FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password'])) {
        sendResponse("error", "Invalid email or password.", [], 401);
    }
    
    // Generate secure session token
    $token = bin2hex(random_bytes(32));
    
    // Save token to database
    $updateStmt = $conn->prepare("UPDATE users SET token = :token WHERE id = :id");
    $updateStmt->execute([
        'token' => $token,
        'id' => $user['id']
    ]);
    
    // Return token and user profile details (exclude password)
    $responseUser = [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'bio' => $user['bio']
    ];
    
    sendResponse("success", "Login successful!", [
        'token' => $token,
        'user' => $responseUser
    ]);
    
} catch (PDOException $e) {
    sendResponse("error", "Server error during login: " . $e->getMessage(), [], 500);
}
?>
