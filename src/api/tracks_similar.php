<?php
require_once __DIR__ . '/../config/db.php';

$db = get_db_connection();

$data = json_decode(file_get_contents('php://input'), true);
$exclude_ids = $data['exclude_ids'] ?? [];
$track_id = $data['track_id'] ?? null;

if (!$track_id && empty($exclude_ids)) {
    http_response_code(400);
    echo json_encode(['error' => 'track_id или exclude_ids обязательны']);
    exit;
}

if ($track_id) {
    $stmt = $db->prepare('SELECT artist_id FROM tracks WHERE id = ?');
    $stmt->execute([$track_id]);
    $track = $stmt->fetch();
    if (!$track) {
        http_response_code(404);
        echo json_encode(['error' => 'Трек не найден']);
        exit;
    }
    $artist_id = $track['artist_id'];
    $stmt = $db->prepare('SELECT genre_id FROM track_genres WHERE track_id = ?');
    $stmt->execute([$track_id]);
    $genre_ids = array_column($stmt->fetchAll(), 'genre_id');
} else {
    $artist_id = null;
    $genre_ids = [];
}

$where = [];
$params = [];
if ($artist_id) {
    $where[] = 't.artist_id = ?';
    $params[] = $artist_id;
}
if ($genre_ids) {
    $in = str_repeat('?,', count($genre_ids) - 1) . '?';
    $where[] = 'tg.genre_id IN (' . $in . ')';
    $params = array_merge($params, $genre_ids);
}
if ($exclude_ids) {
    $in_ex = str_repeat('?,', count($exclude_ids) - 1) . '?';
    $where[] = 't.id NOT IN (' . $in_ex . ')';
    $params = array_merge($params, $exclude_ids);
}

$sql = 'SELECT DISTINCT t.id, t.title, t.duration, t.file_path, t.cover, t.created_at,
               a.id AS artist_id, a.name AS artist_name
        FROM tracks t
        LEFT JOIN artists a ON t.artist_id = a.id
        LEFT JOIN track_genres tg ON t.id = tg.track_id';
if ($where) {
    $sql .= ' WHERE ' . implode(' OR ', $where);
}
$sql .= ' ORDER BY t.id DESC LIMIT 80';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$tracks = $stmt->fetchAll();
shuffle($tracks);
$tracks = array_slice($tracks, 0, 10);

$track_ids = array_column($tracks, 'id');
$genres_map = [];
if ($track_ids) {
    $in = str_repeat('?,', count($track_ids) - 1) . '?';
    $stmt = $db->prepare("SELECT tg.track_id, g.name FROM track_genres tg JOIN genres g ON tg.genre_id = g.id WHERE tg.track_id IN ($in)");
    $stmt->execute($track_ids);
    foreach ($stmt->fetchAll() as $row) {
        $genres_map[$row['track_id']][] = $row['name'];
    }
}
foreach ($tracks as &$track) {
    $track['genres'] = $genres_map[$track['id']] ?? [];
}
unset($track);

echo json_encode(['tracks' => $tracks]); 