<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../src/config/db.php';

$db = null;
try {
    $db = get_db_connection();
} catch (Throwable $e) {
    // continue without DB
}

// Base videos directory
$baseDir = __DIR__ . '/../../../tracks/video';
$baseUrl = '/muzic2/tracks/video/';

$artist = isset($_GET['artist']) ? trim((string)$_GET['artist']) : '';
$artistLower = mb_strtolower($artist, 'UTF-8');

function normalizeVideoPath($path) {
    $path = trim((string)$path);
    if ($path === '') return '';
    // Extract f= if proxied via video.php
    if (stripos($path, 'video.php') !== false && stripos($path, 'f=') !== false) {
        $parts = parse_url($path);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $q);
            if (!empty($q['f'])) {
                $path = $q['f'];
            }
        }
    }
    $path = rawurldecode($path);
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#^https?://[^/]+/#i', '/', $path);
    if (strpos($path, '/muzic2/') === 0) {
        $path = substr($path, strlen('/muzic2/'));
    }
    $path = ltrim($path, '/');
    if (strpos($path, 'tracks/') === false && strpos($path, 'tracks') !== false) {
        $path = substr($path, strpos($path, 'tracks'));
    }
    if (strpos($path, 'tracks/') !== 0) {
        $path = 'tracks/video/' . ltrim($path, '/');
    }
    return mb_strtolower($path, 'UTF-8');
}

$tracksMap = [];
if ($db) {
    try {
        $rows = $db->query("SELECT id, title, artist, cover, duration, video_url FROM tracks WHERE video_url IS NOT NULL AND video_url <> ''")->fetchAll();
        foreach ($rows as $row) {
            $norm = normalizeVideoPath($row['video_url']);
            if ($norm !== '') {
                $tracksMap[$norm] = [
                    'id' => (int)$row['id'],
                    'title' => (string)$row['title'],
                    'artist' => (string)$row['artist'],
                    'cover' => (string)$row['cover'],
                    'duration' => (int)$row['duration']
                ];
            }
        }
    } catch (Throwable $e) {
        // ignore DB errors
    }
}

$result = [];
if (is_dir($baseDir)) {
    $files = scandir($baseDir);
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;
        $path = $baseDir . '/' . $f;
        if (!is_file($path)) continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (!in_array($ext, ['mp4','m4v','webm','mov','avi','ogg'])) continue;

        $title = pathinfo($f, PATHINFO_FILENAME);
        $titleClean = preg_replace('/[_-]+/', ' ', $title);
        $matchArtist = true;
        if ($artistLower !== '') {
            $matchArtist = (mb_strpos(mb_strtolower($title, 'UTF-8'), $artistLower) !== false);
        }
        if (!$matchArtist) continue;

        $relative = 'tracks/video/' . $f;
        $normalized = normalizeVideoPath($relative);
        $track = $tracksMap[$normalized] ?? null;

        $cover = $track && !empty($track['cover'])
            ? $track['cover']
            : null;

        $result[] = [
            'title' => $track['title'] ?? $titleClean,
            'artist' => $track['artist'] ?? $artist,
            'src' => $baseUrl . rawurlencode($f),
            'cover' => $cover,
            'duration' => $track['duration'] ?? 0,
            'track_id' => $track['id'] ?? null,
            'relative_path' => $relative,
            'original_title' => $titleClean
        ];
    }
}

echo json_encode(['success' => true, 'data' => $result], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>

















