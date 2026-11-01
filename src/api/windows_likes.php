<?php
require_once __DIR__ . '/../config/session_init.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Content-Type: application/json');
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

$method = $_SERVER['REQUEST_METHOD'];
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    if ($method === 'GET') {
        echo json_encode(['tracks' => [], 'albums' => []]);
        exit;
    }
    http_response_code(401);
    echo json_encode(['error' => 'Требуется вход', 'tracks' => [], 'albums' => []]);
    exit;
}

require_once __DIR__ . '/../config/db.php';

try {
    $db = get_db_connection();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['tracks' => [], 'albums' => [], 'error' => 'db_unavailable']);
    exit;
}

/**
 * Создаёт album_likes не чаще одного раза за запрос PHP (раньше CREATE выполнялся на каждый GET).
 */
function muzic2_ensure_album_likes_table(PDO $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $db->exec("CREATE TABLE IF NOT EXISTS album_likes (
        user_id INT,
        album_title VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, album_title),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
}

if ($method === 'GET') {
    $lite = isset($_GET['lite']) && (string)$_GET['lite'] === '1';

    if ($lite) {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 2000;
        if ($limit < 1) $limit = 1;
        if ($limit > 5000) $limit = 5000;

        $stmt = $db->prepare('SELECT track_id FROM likes WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . $limit);
        $stmt->execute([$user_id]);
        $trackIds = array_values(array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'track_id')));

        muzic2_ensure_album_likes_table($db);
        $stmt = $db->prepare('SELECT album_title FROM album_likes WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . $limit);
        $stmt->execute([$user_id]);
        $albums = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'lite' => true,
            'limit' => $limit,
            'track_ids' => $trackIds,
            'albums' => $albums,
        ]);
        exit;
    }

    $sqlWithFeats = '
        SELECT t.id, t.title, t.artist, t.album, t.cover, t.duration, t.file_path, t.video_url, t.explicit,
        l.created_at AS added_at,
        (SELECT GROUP_CONCAT(ta.artist ORDER BY ta.artist SEPARATOR ", ")
         FROM track_artists ta WHERE ta.track_id = t.id AND ta.role = "featured") AS feats
        FROM likes l
        INNER JOIN tracks t ON l.track_id = t.id
        WHERE l.user_id = ?
        ORDER BY l.created_at DESC';
    $sqlSimple = '
        SELECT t.id, t.title, t.artist, t.album, t.cover, t.duration, t.file_path, t.video_url, t.explicit, l.created_at AS added_at, NULL AS feats
        FROM likes l
        INNER JOIN tracks t ON l.track_id = t.id
        WHERE l.user_id = ?
        ORDER BY l.created_at DESC';
    $tracks = [];
    try {
        $stmt = $db->prepare($sqlWithFeats);
        $stmt->execute([$user_id]);
        $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $stmt = $db->prepare($sqlSimple);
        $stmt->execute([$user_id]);
        $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    muzic2_ensure_album_likes_table($db);

    $stmt = $db->prepare('SELECT al.album_title,
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
    $stmt->execute([$user_id]);
    $albums = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['tracks' => $tracks, 'albums' => $albums]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST') {
    $track_id = $data['track_id'] ?? null;
    $album_title = $data['album_title'] ?? null;
    
    if ($track_id) {
        $stmt = $db->prepare('INSERT IGNORE INTO likes (user_id, track_id) VALUES (?, ?)');
        $stmt->execute([$user_id, $track_id]);
        
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
        
        echo json_encode(['success' => true, 'liked' => true]);
        
    } elseif ($album_title) {
        
        muzic2_ensure_album_likes_table($db);

        $stmt = $db->prepare('INSERT IGNORE INTO album_likes (user_id, album_title) VALUES (?, ?)');
        $stmt->execute([$user_id, $album_title]);
        
        echo json_encode(['success' => true, 'liked' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'track_id или album_title обязателен']);
    }
    exit;
}

if ($method === 'DELETE') {
    $track_id = $data['track_id'] ?? null;
    $album_title = $data['album_title'] ?? null;
    
    if ($track_id) {
        // Unlike track
        $stmt = $db->prepare('DELETE FROM likes WHERE user_id = ? AND track_id = ?');
        $stmt->execute([$user_id, $track_id]);
        
        // Remove from favorites playlist
        $pl = $db->prepare('SELECT id FROM playlists WHERE user_id = ? AND name = ? LIMIT 1');
        $pl->execute([$user_id, 'Любимые треки']);
        if ($row = $pl->fetch()) {
            $delPt = $db->prepare('DELETE FROM playlist_tracks WHERE playlist_id = ? AND track_id = ?');
            $delPt->execute([$row['id'], $track_id]);
        }
        
        echo json_encode(['success' => true, 'liked' => false]);
        
    } elseif ($album_title) {
        // Unlike album
        $stmt = $db->prepare('DELETE FROM album_likes WHERE user_id = ? AND album_title = ?');
        $stmt->execute([$user_id, $album_title]);
        
        echo json_encode(['success' => true, 'liked' => false]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'track_id или album_title обязателен']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Метод не поддерживается']);
?>
