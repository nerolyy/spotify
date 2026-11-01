<?php
require_once __DIR__ . '/src/config/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }

    $name = isset($data['artist_name']) ? trim($data['artist_name']) : '';
    $cover = isset($data['cover']) ? trim($data['cover']) : '';
    $bio = isset($data['bio']) ? trim($data['bio']) : '';

    if ($name === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Поле artist_name обязательно']);
        exit;
    }

    $db = get_db_connection();

    // Ensure artists table exists (idempotent)
    $db->exec('CREATE TABLE IF NOT EXISTS artists (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE,
        cover VARCHAR(255),
        bio TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )');

    // Upsert artist record
    $sql = 'INSERT INTO artists (name, cover, bio) VALUES (:name, :cover, :bio)
            ON DUPLICATE KEY UPDATE cover = VALUES(cover), bio = VALUES(bio)';
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':name' => $name,
        ':cover' => $cover,
        ':bio' => $bio,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Артист сохранён',
        'data' => [
            'artist_name' => $name,
            'cover' => $cover,
            'bio' => $bio,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
