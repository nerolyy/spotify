<?php
// Optimized home API - minimal queries
require_once __DIR__ . '/../../../src/config/db.php';
header('Content-Type: application/json');

$db = get_db_connection();

// Simple limits
$limitTracks = min(10, (int)($_GET['limit_tracks'] ?? 10));
$limitAlbums = min(10, (int)($_GET['limit_albums'] ?? 10));
$limitArtists = min(10, (int)($_GET['limit_artists'] ?? 10));

// Simple random tracks - minimal data
$tracksStmt = $db->prepare('SELECT id, title, artist, cover FROM tracks ORDER BY RAND() LIMIT ?');
$tracksStmt->execute([$limitTracks]);
$tracks = $tracksStmt->fetchAll(PDO::FETCH_ASSOC);

// Simple random albums - minimal data
$albumsStmt = $db->prepare('SELECT DISTINCT album as title, artist, cover FROM tracks WHERE album IS NOT NULL ORDER BY RAND() LIMIT ?');
$albumsStmt->execute([$limitAlbums]);
$albums = $albumsStmt->fetchAll(PDO::FETCH_ASSOC);

// Simple random artists - minimal data
$artistsStmt = $db->prepare('SELECT DISTINCT artist as name FROM tracks WHERE artist IS NOT NULL ORDER BY RAND() LIMIT ?');
$artistsStmt->execute([$limitArtists]);
$artists = $artistsStmt->fetchAll(PDO::FETCH_ASSOC);

// Simple favorites - minimal data
$favorites = [];
if (isset($_SESSION['user_id'])) {
    $favStmt = $db->prepare('SELECT t.id, t.title, t.artist, t.cover FROM likes l JOIN tracks t ON l.track_id = t.id WHERE l.user_id = ? LIMIT 10');
    $favStmt->execute([$_SESSION['user_id']]);
    $favorites = $favStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Simple mixes - minimal data
$mixes = [];
if (isset($_SESSION['user_id'])) {
    $mixStmt = $db->prepare('SELECT id, name FROM playlists WHERE user_id = ? LIMIT 5');
    $mixStmt->execute([$_SESSION['user_id']]);
    $mixes = $mixStmt->fetchAll(PDO::FETCH_ASSOC);
}

echo json_encode([
    'tracks' => $tracks,
    'albums' => $albums,
    'artists' => $artists,
    'favorites' => $favorites,
    'mixes' => $mixes
]);
?>
