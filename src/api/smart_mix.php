<?php
// API для умных миксов на основе одного трека
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Требуется авторизация']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$db = get_db_connection();

try {
    $trackId = (int)($_GET['track_id'] ?? 0);
    $limit = min((int)($_GET['limit'] ?? 30), 100);
    
    if ($trackId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'track_id is required']);
        exit;
    }
    
    // Получаем информацию о треке
    $trackStmt = $db->prepare("SELECT id, title, artist, album, cover, file_path as src, COALESCE(video_url, '') AS video_url, explicit FROM tracks WHERE id = ?");
    $trackStmt->execute([$trackId]);
    $baseTrack = $trackStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$baseTrack) {
        http_response_code(404);
        echo json_encode(['error' => 'Track not found']);
        exit;
    }
    
    $similarTracks = [];
    
    // 1. Ищем треки того же артиста
    $sameArtistStmt = $db->prepare("SELECT id, title, artist, album, duration, cover, file_path as src, COALESCE(video_url, '') AS video_url, explicit 
                                    FROM tracks 
                                    WHERE artist = ? AND id != ? 
                                    ORDER BY id DESC 
                                    LIMIT 120");
    $sameArtistLimit = min(10, (int)($limit * 0.3));
    $sameArtistStmt->execute([$baseTrack['artist'], $trackId]);
    $sameArtistTracks = $sameArtistStmt->fetchAll(PDO::FETCH_ASSOC);
    shuffle($sameArtistTracks);
    $sameArtistTracks = array_slice($sameArtistTracks, 0, $sameArtistLimit);
    $similarTracks = array_merge($similarTracks, $sameArtistTracks);
    
    // 2. Ищем треки из того же альбома
    if ($baseTrack['album']) {
        $sameAlbumStmt = $db->prepare("SELECT id, title, artist, album, duration, cover, file_path as src, COALESCE(video_url, '') AS video_url, explicit 
                                       FROM tracks 
                                       WHERE album = ? AND id != ? 
                                       ORDER BY id DESC 
                                       LIMIT 80");
        $sameAlbumLimit = min(5, (int)($limit * 0.2));
        $sameAlbumStmt->execute([$baseTrack['album'], $trackId]);
        $sameAlbumTracks = $sameAlbumStmt->fetchAll(PDO::FETCH_ASSOC);
        shuffle($sameAlbumTracks);
        $sameAlbumTracks = array_slice($sameAlbumTracks, 0, $sameAlbumLimit);
        $similarTracks = array_merge($similarTracks, $sameAlbumTracks);
    }
    
    // 3. Ищем треки с похожими жанрами
    $genreStmt = $db->prepare("SELECT DISTINCT t.id, t.title, t.artist, t.album, t.duration, t.cover, t.file_path as src, COALESCE(t.video_url, '') AS video_url, t.explicit
                               FROM tracks t
                               INNER JOIN track_genres tg1 ON t.id = tg1.track_id
                               INNER JOIN track_genres tg2 ON tg1.genre_id = tg2.genre_id
                               WHERE tg2.track_id = ? AND t.id != ?
                               ORDER BY t.id DESC
                               LIMIT 160");
    $genreLimit = min(10, (int)($limit * 0.3));
    $genreStmt->execute([$trackId, $trackId]);
    $genreTracks = $genreStmt->fetchAll(PDO::FETCH_ASSOC);
    shuffle($genreTracks);
    $genreTracks = array_slice($genreTracks, 0, $genreLimit);
    $similarTracks = array_merge($similarTracks, $genreTracks);
    
    // 4. Ищем треки с похожими названиями (по ключевым словам)
    $titleWords = explode(' ', strtolower($baseTrack['title']));
    $titleWords = array_filter($titleWords, function($w) { return strlen($w) > 3; });
    
    if (!empty($titleWords)) {
        $titleConditions = [];
        $titleParams = [];
        foreach (array_slice($titleWords, 0, 3) as $word) {
            $titleConditions[] = "LOWER(t.title) LIKE ?";
            $titleParams[] = '%' . $word . '%';
        }
        
        if (!empty($titleConditions)) {
            $titleParams[] = $trackId;
            
            $titleStmt = $db->prepare("SELECT DISTINCT t.id, t.title, t.artist, t.album, t.duration, t.cover, t.file_path as src, COALESCE(t.video_url, '') AS video_url, t.explicit
                                       FROM tracks t
                                       WHERE (" . implode(' OR ', $titleConditions) . ") AND t.id != ?
                                       ORDER BY t.id DESC
                                       LIMIT 160");
            $titleStmt->execute($titleParams);
            $titleTracks = $titleStmt->fetchAll(PDO::FETCH_ASSOC);
            shuffle($titleTracks);
            $titleTracks = array_slice($titleTracks, 0, min(10, $limit));
            $similarTracks = array_merge($similarTracks, $titleTracks);
        }
    }
    
    // 5. Если недостаточно треков, добавляем случайные
    $uniqueTracks = [];
    $seenIds = [$trackId];
    foreach ($similarTracks as $track) {
        if (!in_array($track['id'], $seenIds)) {
            $uniqueTracks[] = $track;
            $seenIds[] = $track['id'];
        }
    }
    
    if (count($uniqueTracks) < $limit) {
        $needed = $limit - count($uniqueTracks);
        $inPlaceholders = str_repeat('?,', count($seenIds) - 1) . '?';
        $randomStmt = $db->prepare("SELECT id, title, artist, album, duration, cover, file_path as src, COALESCE(video_url, '') AS video_url, explicit
                                   FROM tracks
                                   WHERE id NOT IN ($inPlaceholders)
                                   ORDER BY id DESC
                                   LIMIT 200");
        $randomParams = $seenIds;
        $randomStmt->execute($randomParams);
        $randomTracks = $randomStmt->fetchAll(PDO::FETCH_ASSOC);
        shuffle($randomTracks);
        $randomTracks = array_slice($randomTracks, 0, $needed);
        $uniqueTracks = array_merge($uniqueTracks, $randomTracks);
    }
    
    // Перемешиваем результаты
    shuffle($uniqueTracks);
    
    // Добавляем базовый трек в начало
    $resultTracks = [$baseTrack];
    $resultTracks = array_merge($resultTracks, array_slice($uniqueTracks, 0, $limit - 1));
    
    echo json_encode([
        'base_track' => $baseTrack,
        'tracks' => $resultTracks,
        'total' => count($resultTracks)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка сервера: ' . $e->getMessage()]);
}
?>

