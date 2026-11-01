<?php
require_once __DIR__ . '/../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');
$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

if (!$email || !$username || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Заполните все поля']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Некорректный email']);
    exit;
}
if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'Пароль слишком короткий']);
    exit;
}

$db = get_db_connection();

$stmt = $db->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
$stmt->execute([$email, $username]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Email или логин уже заняты']);
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);

// In schema, column is named `password`. Store hash into that column.
$stmt = $db->prepare('INSERT INTO users (email, username, password) VALUES (?, ?, ?)');
try {
    $stmt->execute([$email, $username, $hash]);
    // Create default empty playlist "Любимые треки" for the new user
    $newUserId = $db->lastInsertId();
    if ($newUserId) {
        try {
            $pl = $db->prepare('INSERT INTO playlists (user_id, name, is_public) VALUES (?, ?, 0)');
            $pl->execute([$newUserId, 'Любимые треки']);
        } catch (PDOException $e2) {
            // ignore creation failure silently
        }
    }
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка при регистрации']);
} 