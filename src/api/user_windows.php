<?php
require_once __DIR__ . '/../config/session_init.php';

header('Content-Type: application/json');
// Dynamic CORS to allow credentials reliably
if (!empty($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Vary: Origin');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if (isset($_SESSION['user_id'])) {
    echo json_encode([
        'authenticated' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? 'user',
            'email' => $_SESSION['email'] ?? ''
        ]
    ]);
} else {
    echo json_encode([
        'authenticated' => false,
        'user' => null
    ]);
}
?>
