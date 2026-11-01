<?php
require_once __DIR__ . '/../config/db.php';

$db = get_db_connection();

$sql = 'SELECT t.id, t.title, t.duration, t.file_path, t.cover, t.created_at,
               a.id AS artist_id, a.name AS artist_name,
               al.id AS album_id, al.title AS album_title, al.cover AS album_cover
        FROM tracks t
        LEFT JOIN artists a ON t.artist_id = a.id
        LEFT JOIN albums al ON t.album_id = al.id
        ORDER BY t.created_at DESC';
$tracks = $db->query($sql)->fetchAll();

$track_ids = array_column($tracks, 'id');
$genres_map = [];
if ($track_ids) {
    $in = str_repeat('?,', count($track_ids) - 1) . '?';
    $stmt = $db->prepare("SELECT tg.track_id, g.name FROM track_genres tg JOIN genres g ON tg.genre_id = g.id WHERE tg.track_id IN ($in)");
    $stmt->execute($track_ids);
    foreach ($stmt->fetchAll() as $row) {
        $genres_map[$row['track_id']] = $row['name'];
    }
}

foreach ($tracks as &$track) {
    $track['genres'] = $genres_map[$track['id']] ?? [];
}

unset($track);

echo json_encode(['tracks' => $tracks]); 