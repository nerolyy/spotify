<?php
// Windows-optimized authentication API
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
// Dynamic CORS for credentials: reflect exact Origin instead of '*'
if (!empty($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Vary: Origin');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$db = get_db_connection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        echo json_encode(['authenticated' => false]);
        exit;
    }
    $stmt = $db->prepare('SELECT id, email, username FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo json_encode(['authenticated' => true, 'user' => $user]);
    } else {
        echo json_encode(['authenticated' => false]);
    }
    exit;
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = is_string($raw) && strlen($raw) ? json_decode($raw, true) : null;
    if (!is_array($data)) { $data = $_POST ?: []; }
    if (!is_array($data) || !count($data)) { $data = $_GET ?: []; }
    $action = $data['action'] ?? 'login';
    
    if ($action === 'login') {
        $login = trim((string)($data['login'] ?? ''));
        $password = (string)($data['password'] ?? '');
        
        if (!$login || !$password) {
            http_response_code(400);
            echo json_encode(['error' => 'Заполните все поля']);
            exit;
        }
        
        
        $stmt = $db->prepare('SELECT id, email, username, password FROM users WHERE email = ? OR username = ?');
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $valid = false;
        if ($user) {
           
            if (password_verify($password, $user['password'])) {
                $valid = true;
            } else {
                
                if (hash_equals((string)$user['password'], (string)$password)) {
                    $valid = true;
                }
            }
        }
        if (!$valid) {
            http_response_code(401);
            echo json_encode(['error' => 'Неверный логин или пароль']);
            exit;
        }
        
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'username' => $user['username']
            ]
        ]);
        exit;
    }
    
    if ($action === 'register') {
        $email = trim($data['email'] ?? '');
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        
        if (!$email || !$username || !$password) {
            http_response_code(400);
            echo json_encode(['error' => 'Заполните все поля']);
            exit;
        }
        
        // Check if user exists
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Пользователь уже существует']);
            exit;
        }
        
        // Create user
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare('INSERT INTO users (email, username, password) VALUES (?, ?, ?)');
        $stmt->execute([$email, $username, $hashedPassword]);
        
        $user_id = $db->lastInsertId();
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user_id,
                'email' => $email,
                'username' => $username
            ]
        ]);
        exit;
    }
}

if ($method === 'DELETE') {
    // Logout
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Метод не поддерживается']);
?>
