<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Требуется авторизация']);
    exit;
}

$artist = trim((string) ($_GET['artist'] ?? ''));

if ($artist === '') {
    echo json_encode(['error' => 'Artist name required']);
    exit;
}

function artist_windows_ensure_track_artists(PDO $pdo): void
{
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS track_artists (
            id INT AUTO_INCREMENT PRIMARY KEY,
            track_id INT NOT NULL,
            artist VARCHAR(255) NOT NULL,
            role ENUM('primary','featured') NOT NULL DEFAULT 'featured',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_track_artist_role (track_id, artist, role)
        )");
    } catch (Throwable $e) {
        // ignore
    }
}

try {
    $pdo = get_db_connection();
    artist_windows_ensure_track_artists($pdo);

    $artistInfo = null;

    $stmt = $pdo->prepare('SELECT name, cover, bio FROM artists WHERE TRIM(LOWER(name)) = TRIM(LOWER(?)) LIMIT 1');
    $stmt->execute([$artist]);
    $artistInfo = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$artistInfo) {
        $stmt = $pdo->prepare('SELECT artist AS name, MIN(cover) AS cover FROM tracks WHERE TRIM(LOWER(artist)) = TRIM(LOWER(?)) GROUP BY artist LIMIT 1');
        $stmt->execute([$artist]);
        $artistInfo = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if (!$artistInfo) {
        $stmt = $pdo->prepare('SELECT ta.artist AS name, MIN(t.cover) AS cover
            FROM track_artists ta
            INNER JOIN tracks t ON t.id = ta.track_id
            WHERE TRIM(LOWER(ta.artist)) = TRIM(LOWER(?)) AND ta.role = "featured"
            GROUP BY ta.artist
            LIMIT 1');
        $stmt->execute([$artist]);
        $artistInfo = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if (!$artistInfo) {
        echo json_encode(['error' => 'Artist not found']);
        exit;
    }

    $displayName = trim((string) ($artistInfo['name'] ?? $artistInfo['artist'] ?? $artist));

    $trackWhere = 'TRIM(LOWER(t.artist)) = TRIM(LOWER(?)) OR TRIM(LOWER(ta.artist)) = TRIM(LOWER(?))';
    $featsSub = "(SELECT GROUP_CONCAT(ta2.artist ORDER BY ta2.artist SEPARATOR ', ')
        FROM track_artists ta2 WHERE ta2.track_id = t.id AND ta2.role = 'featured') AS feats";

    $stmt = $pdo->prepare("SELECT DISTINCT t.id, t.title, t.artist, t.album, t.album_type, t.duration, t.file_path, t.cover, t.video_url, t.explicit, $featsSub
        FROM tracks t
        LEFT JOIN track_artists ta ON ta.track_id = t.id AND ta.role = 'featured'
        WHERE $trackWhere
        ORDER BY t.id DESC
        LIMIT 50");
    $stmt->execute([$displayName, $displayName]);
    $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare("SELECT t.album, MIN(t.album_type) AS album_type, MIN(t.cover) AS cover, COUNT(DISTINCT t.id) AS track_count
        FROM tracks t
        LEFT JOIN track_artists ta ON ta.track_id = t.id AND ta.role = 'featured'
        WHERE ($trackWhere) AND t.album IS NOT NULL AND TRIM(t.album) <> ''
        GROUP BY t.album
        ORDER BY t.album ASC");
    $stmt->execute([$displayName, $displayName]);
    $albumsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $albums = [];
    foreach ($albumsRaw as $album) {
        $albumType = $album['album_type'] ? trim(strtolower((string) $album['album_type'])) : 'album';
        $albums[] = [
            'album' => $album['album'],
            'title' => $album['album'],
            'type' => $albumType,
            'album_type' => $albumType,
            'cover' => $album['cover'],
            'track_count' => (int) ($album['track_count'] ?? 0),
        ];
    }

    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT t.id) AS total_tracks
        FROM tracks t
        LEFT JOIN track_artists ta ON ta.track_id = t.id AND ta.role = 'featured'
        WHERE $trackWhere");
    $stmt->execute([$displayName, $displayName]);
    $totalTracks = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total_tracks'] ?? 0);

    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT t.album) AS total_albums
        FROM tracks t
        LEFT JOIN track_artists ta ON ta.track_id = t.id AND ta.role = 'featured'
        WHERE ($trackWhere) AND t.album IS NOT NULL AND TRIM(t.album) <> ''");
    $stmt->execute([$displayName, $displayName]);
    $totalAlbums = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total_albums'] ?? 0);

    $stmt = $pdo->prepare("SELECT SUM(sub.dur) AS total_duration FROM (
            SELECT DISTINCT t.id, t.duration AS dur
            FROM tracks t
            LEFT JOIN track_artists ta ON ta.track_id = t.id AND ta.role = 'featured'
            WHERE $trackWhere
        ) sub");
    $stmt->execute([$displayName, $displayName]);
    $totalDuration = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total_duration'] ?? 0);

    $promoVideo = null;
    try {
        $pv = $pdo->prepare('SELECT promo_video FROM artists WHERE TRIM(LOWER(name)) = TRIM(LOWER(?)) LIMIT 1');
        $pv->execute([$displayName]);
        $promoRow = $pv->fetch(PDO::FETCH_ASSOC);
        $promoVideo = $promoRow['promo_video'] ?? null;
    } catch (Throwable $e) {
        $promoVideo = null;
    }

    $response = [
        'name' => $displayName,
        'verified' => true,
        'monthly_listeners' => rand(100000, 10000000),
        'cover' => $artistInfo['cover'] ?? null,
        'bio' => $artistInfo['bio'] ?? null,
        'promo_video' => $promoVideo,
        'total_tracks' => $totalTracks,
        'total_albums' => $totalAlbums,
        'total_duration' => $totalDuration,
        'top_tracks' => $tracks,
        'albums' => $albums,
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error']);
}
