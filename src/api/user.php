<?php
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) { echo json_encode(['authenticated' => false]); exit; }

    $db = get_db_connection();
    $stmt = $db->prepare('SELECT id, email, username FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    echo json_encode(['authenticated' => true, 'user' => $user]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Метод не поддерживается']);
?>