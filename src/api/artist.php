<?php
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Требуется авторизация']);
    exit;
}

$db = get_db_connection();
$artist = isset($_GET['artist']) ? $_GET['artist'] : null;

if (!$artist) {
    echo json_encode(['error' => 'No artist name provided']);
    exit;
}

// Получаем информацию об артисте и его треках
$artistQuery = 'SELECT 
    artist,
    COUNT(*) as total_tracks,
    COUNT(DISTINCT album) as total_albums,
    SUM(duration) as total_duration,
    MIN(cover) as cover
FROM tracks 
WHERE TRIM(LOWER(artist)) = TRIM(LOWER(?)) 
GROUP BY artist';

$artistStmt = $db->prepare($artistQuery);
$artistStmt->execute([$artist]);
$artistInfo = $artistStmt->fetch();

if (!$artistInfo) {
    echo json_encode(['error' => 'Artist not found', 'debug_artist' => $artist]);
    exit;
}

// Получаем топ-треки артиста (наиболее популярные)
$topTracksQuery = 'SELECT 
    id, title, artist, album, duration, file_path, cover
FROM tracks 
WHERE TRIM(LOWER(artist)) = TRIM(LOWER(?)) 
ORDER BY id ASC 
LIMIT 10';

$topTracksStmt = $db->prepare($topTracksQuery);
$topTracksStmt->execute([$artist]);
$topTracks = [];

foreach ($topTracksStmt as $track) {
    $topTracks[] = [
        'id' => $track['id'],
        'title' => $track['title'],
        'artist' => $track['artist'],
        'album' => $track['album'],
        'duration' => (int)$track['duration'],
        'src' => $track['file_path'],
        'cover' => $track['cover']
    ];
}

// Получаем альбомы артиста
$albumsQuery = 'SELECT 
    album,
    album_type,
    COUNT(*) as track_count,
    MIN(cover) as cover,
    SUM(duration) as total_duration
FROM tracks 
WHERE TRIM(LOWER(artist)) = TRIM(LOWER(?)) 
GROUP BY album, album_type
ORDER BY album ASC';

$albumsStmt = $db->prepare($albumsQuery);
$albumsStmt->execute([$artist]);
$albums = [];

foreach ($albumsStmt as $album) {
    // Убеждаемся, что album_type не null и правильно обработан
    $albumType = $album['album_type'] ? trim(strtolower($album['album_type'])) : 'album';
    $albums[] = [
        'title' => $album['album'],
        'type' => $albumType,
        'album_type' => $albumType, // Дублируем для совместимости
        'track_count' => (int)$album['track_count'],
        'cover' => $album['cover'],
        'total_duration' => (int)$album['total_duration']
    ];
}

// Получаем все треки для подсчета прослушиваний (пока заглушка)
$monthlyListeners = rand(100000, 10000000); // Временная заглушка

// Исправляем пути к обложкам
$cover = $artistInfo['cover'] ? '/muzic2/' . $artistInfo['cover'] : null;
error_log("Original cover: " . $artistInfo['cover']);
error_log("Fixed cover: " . $cover);

foreach ($topTracks as &$track) {
    if ($track['cover']) {
        $track['cover'] = '/muzic2/' . $track['cover'];
    }
}

foreach ($albums as &$album) {
    if ($album['cover']) {
        $album['cover'] = '/muzic2/' . $album['cover'];
    }
}

$response = [
    'name' => $artistInfo['artist'],
    'verified' => true, // Пока всех делаем верифицированными
    'monthly_listeners' => $monthlyListeners,
    'cover' => $cover,
    'total_tracks' => (int)$artistInfo['total_tracks'],
    'total_albums' => (int)$artistInfo['total_albums'],
    'total_duration' => (int)$artistInfo['total_duration'],
    'top_tracks' => $topTracks,
    'albums' => $albums
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>


