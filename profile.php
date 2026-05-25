<?php
require_once 'db.php';

// Authenticate user via token
$user = authenticateUser($conn);
$userId = $user['id'];

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Return user details
    sendResponse("success", "Profile retrieved.", [
        'user' => $user
    ]);
} elseif ($method === 'POST' || $method === 'PUT') {
    // Handle Profile Update
    $input = getJsonInput();
    
    $name = isset($input['name']) ? trim($input['name']) : '';
    $phone = isset($input['phone']) ? trim($input['phone']) : null;
    $bio = isset($input['bio']) ? trim($input['bio']) : null;
    
    // Validation
    if (empty($name)) {
        sendResponse("error", "Name cannot be empty.", [], 400);
    }
    
    try {
        // Update user fields
        $stmt = $conn->prepare("UPDATE users SET name = :name, phone = :phone, bio = :bio WHERE id = :id");
        $stmt->execute([
            'name' => $name,
            'phone' => $phone,
            'bio' => $bio,
            'id' => $userId
        ]);
        
        // Fetch updated user data
        $fetchStmt = $conn->prepare("SELECT id, name, email, phone, bio FROM users WHERE id = :id LIMIT 1");
        $fetchStmt->execute(['id' => $userId]);
        $updatedUser = $fetchStmt->fetch();
        
        sendResponse("success", "Profile updated successfully!", [
            'user' => $updatedUser
        ]);
        
    } catch (PDOException $e) {
        sendResponse("error", "Server error during profile update: " . $e->getMessage(), [], 500);
    }
} else {
    sendResponse("error", "Method not allowed.", [], 405);
}
?>
