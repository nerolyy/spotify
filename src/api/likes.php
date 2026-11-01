<?php
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$db = get_db_connection();
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) { 
    // Return empty likes for non-authenticated users
    echo json_encode(['tracks' => [], 'albums' => []]); 
    exit; 
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // list liked tracks with track info + feats + like timestamp
    $stmt = $db->prepare('SELECT t.*, l.created_at AS added_at, (SELECT GROUP_CONCAT(ta.artist ORDER BY ta.artist SEPARATOR ", ") FROM track_artists ta WHERE ta.track_id=t.id AND ta.role="featured") AS feats FROM likes l JOIN tracks t ON l.track_id = t.id WHERE l.user_id = ? ORDER BY l.created_at DESC');
    $stmt->execute([$user_id]);
    $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // list liked albums (create table if not exists)
    $createTable = "CREATE TABLE IF NOT EXISTS album_likes (
        user_id INT,
        album_title VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, album_title),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $db->exec($createTable);
    
    $albumStmt = $db->prepare('SELECT al.album_title,
        (SELECT t.cover
         FROM tracks t
         WHERE t.album = al.album_title
           AND t.cover IS NOT NULL
           AND TRIM(t.cover) != ""
         ORDER BY
           CASE
             WHEN LOWER(t.cover) LIKE "%placeholder%" OR LOWER(TRIM(t.cover)) = "cover" THEN 1
             ELSE 0
           END,
           t.id DESC
         LIMIT 1) AS cover
        FROM album_likes al
        WHERE al.user_id = ?
        ORDER BY al.created_at DESC');
    $albumStmt->execute([$user_id]);
    $albums = $albumStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['tracks' => $tracks, 'albums' => $albums]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if ($method === 'POST') {
    $track_id = $data['track_id'] ?? null;
    $album_title = $data['album_title'] ?? null;
    
    if ($track_id) {
        // Handle track like
        $stmt = $db->prepare('INSERT IGNORE INTO likes (user_id, track_id) VALUES (?, ?)');
        $stmt->execute([$user_id, $track_id]);
        // Also ensure the track is in the user's default "Любимые треки" playlist
        $pl = $db->prepare('SELECT id FROM playlists WHERE user_id = ? AND name = ? LIMIT 1');
        $pl->execute([$user_id, 'Любимые треки']);
        $playlistId = ($row = $pl->fetch()) ? $row['id'] : null;
        if (!$playlistId) {
            $crt = $db->prepare('INSERT INTO playlists (user_id, name, is_public) VALUES (?, ?, 0)');
            $crt->execute([$user_id, 'Любимые треки']);
            $playlistId = $db->lastInsertId();
        }
        if ($playlistId) {
            $posStmt = $db->prepare('SELECT COALESCE(MAX(position),0)+1 AS next_pos FROM playlist_tracks WHERE playlist_id = ?');
            $posStmt->execute([$playlistId]);
            $next = ($posStmt->fetch()['next_pos']) ?? 1;
            $insPt = $db->prepare('INSERT IGNORE INTO playlist_tracks (playlist_id, track_id, position) VALUES (?, ?, ?)');
            $insPt->execute([$playlistId, $track_id, $next]);
        }
    } elseif ($album_title) {
        // Handle album like - create table if not exists
        $createTable = "CREATE TABLE IF NOT EXISTS album_likes (
            user_id INT,
            album_title VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, album_title),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        $db->exec($createTable);
        
        $stmt = $db->prepare('INSERT IGNORE INTO album_likes (user_id, album_title) VALUES (?, ?)');
        $stmt->execute([$user_id, $album_title]);
    } else {
        http_response_code(400); 
        echo json_encode(['error' => 'track_id или album_title обязателен']); 
        exit; 
    }
    
    echo json_encode(['success' => true]);
    exit;
}

if ($method === 'DELETE') {
    $track_id = $data['track_id'] ?? null;
    $album_title = $data['album_title'] ?? null;
    
    if ($track_id) {
        // Handle track unlike
        $stmt = $db->prepare('DELETE FROM likes WHERE user_id = ? AND track_id = ?');
        $stmt->execute([$user_id, $track_id]);
        // Remove from default favorites playlist as well
        $pl = $db->prepare('SELECT id FROM playlists WHERE user_id = ? AND name = ? LIMIT 1');
        $pl->execute([$user_id, 'Любимые треки']);
        if ($row = $pl->fetch()) {
            $delPt = $db->prepare('DELETE FROM playlist_tracks WHERE playlist_id = ? AND track_id = ?');
            $delPt->execute([$row['id'], $track_id]);
        }
    } elseif ($album_title) {
        // Handle album unlike
        $stmt = $db->prepare('DELETE FROM album_likes WHERE user_id = ? AND album_title = ?');
        $stmt->execute([$user_id, $album_title]);
    } else {
        http_response_code(400); 
        echo json_encode(['error' => 'track_id или album_title обязателен']); 
        exit; 
    }
    
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Метод не поддерживается']);
?>