<?php
// Create admin user script
require_once __DIR__ . '/src/config/db.php';

header('Content-Type: application/json');

try {
    $db = get_db_connection();
    
    // Check if admin user exists
    $stmt = $db->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
    $stmt->execute(['admin', 'admin@test.com']);
    $existingUser = $stmt->fetch();
    
    if ($existingUser) {
        echo json_encode([
            'status' => 'EXISTS',
            'message' => 'Admin user already exists',
            'user_id' => $existingUser['id']
        ]);
        exit;
    }
    
    // Create admin user
    $username = 'admin';
    $email = 'admin@test.com';
    $password = password_hash('admin', PASSWORD_DEFAULT);
    
    $stmt = $db->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
    $stmt->execute([$username, $email, $password]);
    
    $userId = $db->lastInsertId();
    
    echo json_encode([
        'status' => 'CREATED',
        'message' => 'Admin user created successfully',
        'user_id' => $userId,
        'username' => $username,
        'email' => $email
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'ERROR',
        'message' => $e->getMessage()
    ]);
}
?>
