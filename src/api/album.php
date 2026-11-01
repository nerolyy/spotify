<?php
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Требуется авторизация']);
    exit;
}

$db = get_db_connection();
$album = isset($_GET['album']) ? $_GET['album'] : null;
if (!$album) {
    echo json_encode(['error' => 'No album name']);
    exit;
}

$sql = 'SELECT album, MIN(artist) as artist, MIN(album_type) as album_type, MIN(cover) as cover FROM tracks WHERE TRIM(LOWER(album)) = TRIM(LOWER(?)) GROUP BY album';
$albumRow = $db->prepare($sql);
$albumRow->execute([$album]);
$albumRow = $albumRow->fetch();
if (!$albumRow) {
    echo json_encode(['error' => 'Album not found', 'debug_album' => $album]);
    exit;
}

$artist = $albumRow['artist'];
$cover = $albumRow['cover'];

$tracks = $db->prepare('SELECT t.id, t.title, t.artist, t.duration, t.file_path, t.cover, t.video_url, t.explicit,
  (SELECT GROUP_CONCAT(ta.artist ORDER BY ta.artist SEPARATOR ", ") FROM track_artists ta WHERE ta.track_id=t.id AND ta.role="featured") AS feats
  FROM tracks t WHERE TRIM(LOWER(t.album)) = TRIM(LOWER(?)) ORDER BY t.id ASC');
$tracks->execute([$album]);
$trackList = [];
$totalDuration = 0;
foreach ($tracks as $t) {
    $trackList[] = [
        'id' => $t['id'],
        'title' => $t['title'],
        'artist' => $t['artist'],
        'feats' => $t['feats'],
        'duration' => (int)$t['duration'],
        'src' => $t['file_path'],
        'cover' => $t['cover'],
        'video_url' => $t['video_url'],
        'explicit' => (int)$t['explicit']
    ];
    $totalDuration += (int)$t['duration'];
}

$res = [
    'title' => $albumRow['album'],
    'artist' => $artist,
    'cover' => $cover,
    'total_duration' => $totalDuration,
    'tracks' => $trackList
];
echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); 

