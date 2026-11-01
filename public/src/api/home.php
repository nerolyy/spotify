<?php
require_once __DIR__ . '/../../../src/config/db.php';
header('Content-Type: application/json');

$db = get_db_connection();

// Read optional limits (with safe bounds)
$limitTracks = max(1, min(50, (int)($_GET['limit_tracks'] ?? 12)));
$limitAlbums = max(1, min(24, (int)($_GET['limit_albums'] ?? 6)));
$limitArtists = max(1, min(24, (int)($_GET['limit_artists'] ?? 6)));
$limitFavorites = max(1, min(24, (int)($_GET['limit_favorites'] ?? 6)));
$limitMixes = max(1, min(24, (int)($_GET['limit_mixes'] ?? 6)));

// Random tracks selection with feats
$tracksStmt = $db->prepare('SELECT t.id, t.title, t.artist, t.album, t.album_type, t.duration, t.file_path, t.cover, t.video_url, t.explicit,
       (SELECT GROUP_CONCAT(ta.artist ORDER BY ta.artist SEPARATOR ", ") FROM track_artists ta WHERE ta.track_id=t.id AND ta.role="featured") AS feats
  FROM tracks t
 ORDER BY RAND() LIMIT :lim');
$tracksStmt->bindValue(':lim', $limitTracks, PDO::PARAM_INT);
$tracksStmt->execute();
$tracks = $tracksStmt->fetchAll(PDO::FETCH_ASSOC);

// Albums: random selection
$albumsStmt = $db->prepare('SELECT album,
       MIN(artist) AS artist,
       MIN(album_type) AS album_type,
       MIN(cover) AS cover,
       MAX(id) AS last_track_id
  FROM tracks
 GROUP BY album
 ORDER BY RAND()
 LIMIT :lim');
$albumsStmt->bindValue(':lim', $limitAlbums, PDO::PARAM_INT);
$albumsStmt->execute();
$albums = $albumsStmt->fetchAll(PDO::FETCH_ASSOC);

// Artists: random selection
try {
    $artistsStmt = $db->prepare('SELECT a.name AS artist,
       COALESCE(a.cover, MIN(t.cover)) AS cover,
       MAX(t.id) AS last_track_id,
       COUNT(t.id) AS track_count
      FROM artists a
 LEFT JOIN tracks t ON TRIM(LOWER(a.name)) = TRIM(LOWER(t.artist))
  GROUP BY a.name, a.cover
  ORDER BY RAND()
  LIMIT :lim');
    // Fallback to tracks-based random selection
    $artistsSql = 'SELECT a.name AS artist,
       COALESCE(a.cover, MIN(t.cover)) AS cover,
       MAX(t.id) AS last_track_id,
       COUNT(t.id) AS track_count
      FROM artists a
 LEFT JOIN tracks t ON TRIM(LOWER(a.name)) = TRIM(LOWER(t.artist))
  GROUP BY a.name, a.cover
  ORDER BY RAND()
  LIMIT :lim';
    $artistsStmt = $db->prepare($artistsSql);
    $artistsStmt->bindValue(':lim', $limitArtists, PDO::PARAM_INT);
    $artistsStmt->execute();
    $artists = $artistsStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$artists || count($artists) === 0) {
        $fallbackStmt = $db->prepare('SELECT artist,
           MIN(cover) AS cover,
           MAX(id) AS last_track_id
          FROM tracks
         GROUP BY artist
         ORDER BY RAND()
         LIMIT :lim');
        $fallbackStmt->bindValue(':lim', $limitArtists, PDO::PARAM_INT);
        $fallbackStmt->execute();
        $artists = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $fallbackStmt = $db->prepare('SELECT artist,
       MIN(cover) AS cover,
       MAX(id) AS last_track_id
      FROM tracks
     GROUP BY artist
     ORDER BY RAND()
     LIMIT :lim');
    $fallbackStmt->bindValue(':lim', $limitArtists, PDO::PARAM_INT);
    $fallbackStmt->execute();
    $artists = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
}

$favoritesStmt = $db->prepare('SELECT t.id, t.title, t.artist, t.album, t.album_type, t.duration, t.file_path, t.cover, t.video_url, t.explicit,
       (SELECT GROUP_CONCAT(ta.artist ORDER BY ta.artist SEPARATOR ", ") FROM track_artists ta WHERE ta.track_id=t.id AND ta.role="featured") AS feats
  FROM tracks t ORDER BY RAND() LIMIT :lim');
$favoritesStmt->bindValue(':lim', $limitFavorites, PDO::PARAM_INT);
$favoritesStmt->execute();
$favorites = $favoritesStmt->fetchAll(PDO::FETCH_ASSOC);

$mixesStmt = $db->prepare('SELECT t.id, t.title, t.artist, t.album, t.album_type, t.duration, t.file_path, t.cover, t.video_url, t.explicit,
       (SELECT GROUP_CONCAT(ta.artist ORDER BY ta.artist SEPARATOR ", ") FROM track_artists ta WHERE ta.track_id=t.id AND ta.role="featured") AS feats
  FROM tracks t ORDER BY RAND() LIMIT :lim');
$mixesStmt->bindValue(':lim', $limitMixes, PDO::PARAM_INT);
$mixesStmt->execute();
$mixes = $mixesStmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'tracks' => $tracks,
    'albums' => $albums,
    'artists' => $artists,
    'favorites' => $favorites,
    'mixes' => $mixes
]);
?>