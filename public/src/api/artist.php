<?php
require_once __DIR__ . '/../../../src/config/db.php';
header('Content-Type: application/json');

$db = get_db_connection();
$artist = isset($_GET['artist']) ? (string)$_GET['artist'] : null;

if (!$artist) {
    echo json_encode(['error' => 'No artist name provided']);
    exit;
}

// Prefer explicit artists table first, so artists without tracks still resolve
$explicitCover = null; $bio = null; $explicitName = null; $promoVideo = null; $promoTrackId = null;
try {
    // Добавляем поля если их нет
    try {
        $db->exec("ALTER TABLE artists ADD COLUMN IF NOT EXISTS promo_video VARCHAR(500) DEFAULT NULL");
    } catch (Throwable $e) {}
    try {
        $db->exec("ALTER TABLE artists ADD COLUMN IF NOT EXISTS promo_track_id INT DEFAULT NULL");
    } catch (Throwable $e) {}
    
    $row = $db->prepare('SELECT name, cover, bio, promo_video, promo_track_id FROM artists WHERE LOWER(name)=LOWER(?) LIMIT 1');
    $row->execute([$artist]);
    $ar = $row->fetch();
    if ($ar) { 
        $explicitName = $ar['name']; 
        $explicitCover = $ar['cover']; 
        $bio = $ar['bio']; 
        $promoVideo = isset($ar['promo_video']) && $ar['promo_video'] !== '' && $ar['promo_video'] !== null ? trim((string)$ar['promo_video']) : null;
        $promoTrackId = isset($ar['promo_track_id']) && $ar['promo_track_id'] !== null && $ar['promo_track_id'] !== '' ? (int)$ar['promo_track_id'] : null;
    }
} catch (Throwable $e) {}

// Try to find exact-match (case-insensitive) artist rows in tracks (for stats)
$artistQuery = 'SELECT 
    artist,
    COUNT(*) as total_tracks,
    COUNT(DISTINCT album) as total_albums,
    SUM(duration) as total_duration,
    MIN(cover) as cover
FROM tracks 
WHERE LOWER(artist) = LOWER(?) 
GROUP BY artist';

$artistStmt = $db->prepare($artistQuery);
$artistStmt->execute([$artist]);
$artistInfo = $artistStmt->fetch();

// If no tracks entry but explicit artist exists, synthesize minimal info
if (!$artistInfo && $explicitName) {
    $artistInfo = [
        'artist' => $explicitName,
        'total_tracks' => 0,
        'total_albums' => 0,
        'total_duration' => 0,
        'cover' => $explicitCover
    ];
}

if (!$artistInfo) {
    echo json_encode(['error' => 'Artist not found', 'debug_artist' => $artist]);
    exit;
}

// Top tracks for this artist: include primary artist OR featured appearances
$topTracks = [];
try {
    // Ensure mapping table exists (defensive)
    try { $db->exec("CREATE TABLE IF NOT EXISTS track_artists (id INT AUTO_INCREMENT PRIMARY KEY, track_id INT NOT NULL, artist VARCHAR(255) NOT NULL, role ENUM('primary','featured') NOT NULL DEFAULT 'featured', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uniq_track_artist_role (track_id, artist, role))"); } catch (Throwable $e2) {}
    $sql = "SELECT DISTINCT t.id, t.title, t.artist, t.album, t.duration, t.file_path, t.cover, t.video_url, t.explicit,
                   (SELECT GROUP_CONCAT(ta2.artist ORDER BY ta2.artist SEPARATOR ', ') FROM track_artists ta2 WHERE ta2.track_id=t.id AND ta2.role='featured') AS feats
            FROM tracks t
            LEFT JOIN track_artists ta ON ta.track_id = t.id AND ta.role='featured'
            WHERE LOWER(t.artist)=LOWER(?) OR LOWER(ta.artist)=LOWER(?)
            ORDER BY t.id DESC LIMIT 50";
    $stmt = $db->prepare($sql);
    $stmt->execute([$artist,$artist]);
    foreach ($stmt as $track) {
        $topTracks[] = [
            'id' => $track['id'],
            'title' => $track['title'],
            'artist' => $track['artist'],
            'feats' => $track['feats'] ?? null,
            'album' => $track['album'],
            'duration' => (int)$track['duration'],
            'file_path' => $track['file_path'],
            'cover' => $track['cover'],
            'video_url' => $track['video_url'],
            'explicit' => (int)$track['explicit']
        ];
    }
} catch (Throwable $e) {}

// Albums for this artist: include albums where they are primary or featured (album-level or track-level)
$albums = [];
try {
    // ensure album_artists exists (defensive)
    try { $db->exec("CREATE TABLE IF NOT EXISTS album_artists (id INT AUTO_INCREMENT PRIMARY KEY, album VARCHAR(255) NOT NULL, artist VARCHAR(255) NOT NULL, role ENUM('primary','featured') NOT NULL DEFAULT 'featured', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uniq_album_artist_role (album, artist, role))"); } catch (Throwable $e2) {}
    $sqlAlbums = "SELECT t.album, t.album_type, COUNT(*) as track_count, MIN(t.cover) as cover, SUM(t.duration) as total_duration
                  FROM tracks t
                  LEFT JOIN track_artists ta ON ta.track_id = t.id AND ta.role='featured'
                  LEFT JOIN album_artists aa ON TRIM(LOWER(aa.album)) = TRIM(LOWER(t.album)) AND aa.role='featured'
                  WHERE LOWER(t.artist)=LOWER(?) OR LOWER(ta.artist)=LOWER(?) OR LOWER(aa.artist)=LOWER(?)
                  GROUP BY t.album, t.album_type
                  ORDER BY t.album ASC";
    $albumsStmt = $db->prepare($sqlAlbums);
    $albumsStmt->execute([$artist, $artist, $artist]);
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
} catch (Throwable $e) {}

// If artist has no albums via tracks yet, include album-level associations even when album has 0 tracks (placeholder logic)
try {
    if (count($albums) === 0) {
        $aa = $db->prepare('SELECT aa.album as album, MIN(t.album_type) as album_type, MIN(t.cover) as cover
                             FROM album_artists aa LEFT JOIN tracks t ON TRIM(LOWER(t.album))=TRIM(LOWER(aa.album))
                             WHERE LOWER(aa.artist)=LOWER(?) AND aa.role="featured"
                             GROUP BY aa.album');
        $aa->execute([$artist]);
        foreach ($aa as $row) {
            $albums[] = [
                'title' => $row['album'],
                'type' => $row['album_type'] ?: 'album',
                'track_count' => 0,
                'cover' => $row['cover'],
                'total_duration' => 0
            ];
        }
    }
} catch (Throwable $e) {}

$monthlyListeners = rand(100000, 10000000);

// Get promo track info if promo_track_id is set
$promoTrack = null;
if ($promoTrackId) {
    try {
        $trackStmt = $db->prepare('SELECT id, title, artist, album, cover, file_path, duration FROM tracks WHERE id = ? LIMIT 1');
        $trackStmt->execute([$promoTrackId]);
        $trackRow = $trackStmt->fetch();
        if ($trackRow) {
            $promoTrack = [
                'id' => (int)$trackRow['id'],
                'title' => $trackRow['title'],
                'artist' => $trackRow['artist'],
                'album' => $trackRow['album'],
                'cover' => $trackRow['cover'],
                'file_path' => $trackRow['file_path'],
                'duration' => (int)$trackRow['duration']
            ];
        }
    } catch (Throwable $e) {
        error_log("Failed to load promo track: " . $e->getMessage());
    }
}

$response = [
    'name' => $artistInfo['artist'],
    'verified' => true,
    'monthly_listeners' => $monthlyListeners,
    'cover' => $explicitCover ?: $artistInfo['cover'],
    'bio' => $bio ?: null,
    'promo_video' => $promoVideo ? trim((string)$promoVideo) : null,
    'promo_track_id' => $promoTrackId,
    'promo_track' => $promoTrack,
    'total_tracks' => (int)$artistInfo['total_tracks'],
    'total_albums' => (int)$artistInfo['total_albums'],
    'total_duration' => (int)$artistInfo['total_duration'],
    'top_tracks' => $topTracks,
    'albums' => $albums
];

// DEBUG: Log promo_video if exists
if ($promoVideo) {
    error_log("Artist API: promo_video for {$artistInfo['artist']}: " . $promoVideo);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
