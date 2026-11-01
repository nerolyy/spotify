<?php
// Windows Database Optimizer - общий скрипт для подтягивания всех БД
session_start();
require_once __DIR__ . '/src/config/db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

$db = get_db_connection();
$user_id = $_SESSION['user_id'] ?? null;

// Функция для быстрого получения данных
function getQuickData($db, $user_id = null) {
    $data = [];
    
    // 1. Быстрые треки (минимальные данные)
    $tracksStmt = $db->prepare('SELECT id, title, artist, album, album_type, duration, file_path, cover, video_url, explicit FROM tracks ORDER BY RAND() LIMIT 20');
    $tracksStmt->execute();
    $data['tracks'] = $tracksStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 2. Быстрые альбомы (без GROUP BY)
    $albumsStmt = $db->prepare('SELECT DISTINCT album as title, artist, album_type, cover FROM tracks WHERE album IS NOT NULL ORDER BY RAND() LIMIT 15');
    $albumsStmt->execute();
    $data['albums'] = $albumsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Быстрые артисты (без JOIN)
    $artistsStmt = $db->prepare('SELECT DISTINCT artist as name, MIN(cover) as cover FROM tracks WHERE artist IS NOT NULL GROUP BY artist ORDER BY RAND() LIMIT 15');
    $artistsStmt->execute();
    $data['artists'] = $artistsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 4. Пользователь (если авторизован)
    if ($user_id) {
        $userStmt = $db->prepare('SELECT id, username FROM users WHERE id = ? LIMIT 1');
        $userStmt->execute([$user_id]);
        $data['user'] = $userStmt->fetch(PDO::FETCH_ASSOC);
        $data['authenticated'] = true;
        
        // 5. Лайки треков (минимальные данные)
        $likesStmt = $db->prepare('SELECT t.id, t.title, t.artist, t.cover FROM likes l JOIN tracks t ON l.track_id = t.id WHERE l.user_id = ? LIMIT 20');
        $likesStmt->execute([$user_id]);
        $data['liked_tracks'] = $likesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 6. Лайки альбомов
        try {
            $albumLikesStmt = $db->prepare('SELECT album_title FROM album_likes WHERE user_id = ? LIMIT 20');
            $albumLikesStmt->execute([$user_id]);
            $data['liked_albums'] = $albumLikesStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $data['liked_albums'] = [];
        }
        
        // 7. Плейлисты (минимальные данные)
        $playlistsStmt = $db->prepare('SELECT id, name, created_at FROM playlists WHERE user_id = ? ORDER BY created_at DESC LIMIT 10');
        $playlistsStmt->execute([$user_id]);
        $data['playlists'] = $playlistsStmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        $data['user'] = null;
        $data['authenticated'] = false;
        $data['liked_tracks'] = [];
        $data['liked_albums'] = [];
        $data['playlists'] = [];
    }
    
    // 8. Статистика
    $statsStmt = $db->query('SELECT COUNT(*) as total_tracks FROM tracks');
    $data['stats'] = [
        'total_tracks' => $statsStmt->fetch()['total_tracks'],
        'total_albums' => count($data['albums']),
        'total_artists' => count($data['artists'])
    ];
    
    return $data;
}

// Получаем данные
$startTime = microtime(true);
$data = getQuickData($db, $user_id);
$loadTime = round((microtime(true) - $startTime) * 1000, 2);

// Добавляем время загрузки
$data['load_time_ms'] = $loadTime;
$data['timestamp'] = date('Y-m-d H:i:s');

echo json_encode($data, JSON_UNESCAPED_UNICODE);
?>
