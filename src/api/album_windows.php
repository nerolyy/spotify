<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Требуется авторизация']);
    exit;
}

// Ультра-быстрая версия для Windows
$album = $_GET['album'] ?? '';

if (empty($album)) {
    echo json_encode(['error' => 'Album name required']);
    exit;
}

try {
    $pdo = get_db_connection();
    
    // Информация об альбоме
    $stmt = $pdo->prepare("SELECT DISTINCT album, artist, album_type, cover FROM tracks WHERE album = ? LIMIT 1");
    $stmt->execute([$album]);
    $albumInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$albumInfo) {
        echo json_encode(['error' => 'Album not found']);
        exit;
    }
    
    // Треки альбома (без таблицы track_artists)
    $stmt = $pdo->prepare("SELECT id, title, artist, album, album_type, duration, file_path, cover, video_url, explicit FROM tracks WHERE album = ?");
    $stmt->execute([$album]);
    $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Не изменяем пути к обложкам - они должны быть как в оригинальном API
    
    // Формируем ответ в том же формате, что и оригинальный API
    $response = [
        'title' => $albumInfo['album'],
        'artist' => $albumInfo['artist'],
        'cover' => $albumInfo['cover'],
        'total_duration' => array_sum(array_column($tracks, 'duration')),
        'tracks' => $tracks
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error']);
}
?>
