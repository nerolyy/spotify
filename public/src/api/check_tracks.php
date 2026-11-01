<?php
require_once __DIR__ . '/../../../src/config/db.php';
header('Content-Type: application/json');

$db = get_db_connection();
$tracks = $db->query('SELECT id, title, artist, album, file_path, cover FROM tracks')->fetchAll();

$missing_audio = [];
$missing_cover = [];
$duplicates = [];
$seen = [];

foreach ($tracks as $track) {
    $audio_path = __DIR__ . '/../../../' . $track['file_path'];
    $cover_path = __DIR__ . '/../../../' . $track['cover'];
    if (!file_exists($audio_path)) {
        $missing_audio[] = [
            'id' => $track['id'],
            'title' => $track['title'],
            'artist' => $track['artist'],
            'file_path' => $track['file_path']
        ];
    }
    if (!file_exists($cover_path)) {
        $missing_cover[] = [
            'id' => $track['id'],
            'title' => $track['title'],
            'artist' => $track['artist'],
            'cover' => $track['cover']
        ];
    }
    $key = $track['title'] . '|' . $track['artist'] . '|' . $track['album'];
    if (isset($seen[$key])) {
        $duplicates[] = [
            'id' => $track['id'],
            'title' => $track['title'],
            'artist' => $track['artist'],
            'album' => $track['album']
        ];
    } else {
        $seen[$key] = true;
    }
}

if ((isset($_GET['delete'])) || (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'delete')) {
    $stmt = $db->prepare("DELETE FROM tracks WHERE file_path = ?");
    $stmt->execute(['tracks/music/Kai Angel-AMC2.mp3']);
    echo json_encode(['deleted' => $stmt->rowCount()]);
    exit;
}

echo json_encode([
    'missing_audio' => $missing_audio,
    'missing_cover' => $missing_cover,
    'duplicates' => $duplicates,
    'total_tracks' => count($tracks)
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); 