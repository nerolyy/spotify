<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../src/config/session_init.php';
require_once __DIR__ . '/../../../src/config/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = (string)($_GET['action'] ?? '');
if ($action === '') $action = (string)($_POST['action'] ?? '');

function json_out(array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = get_db_connection();
} catch (Throwable $e) {
    json_out(['success' => false, 'error' => 'db_unavailable'], 500);
}

function table_has_column(PDO $db, string $table, string $column): bool {
    try {
        $st = $db->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1');
        $st->execute([$table, $column]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

// Best-effort schema compatibility (older DBs)
try { $db->exec("ALTER TABLE playlists ADD COLUMN cover VARCHAR(500) NULL"); } catch (Throwable $e) {}
try { $db->exec("ALTER TABLE playlist_tracks ADD COLUMN added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); } catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'export') {
    // Export core content and user playlists (no media files)
    $trackCols = ['id','title','artist','album','album_type','duration','file_path','cover','video_url','explicit'];
    if (table_has_column($db, 'tracks', 'feats')) $trackCols[] = 'feats';
    if (table_has_column($db, 'tracks', 'created_at')) $trackCols[] = 'created_at';
    if (table_has_column($db, 'tracks', 'updated_at')) $trackCols[] = 'updated_at';
    $tracks = $db->query("SELECT " . implode(', ', $trackCols) . " FROM tracks ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

    $artists = [];
    $artistCols = ['id','name','cover','bio'];
    if (table_has_column($db, 'artists', 'promo_video')) $artistCols[] = 'promo_video';
    if (table_has_column($db, 'artists', 'promo_track_id')) $artistCols[] = 'promo_track_id';
    if (table_has_column($db, 'artists', 'created_at')) $artistCols[] = 'created_at';
    if (table_has_column($db, 'artists', 'updated_at')) $artistCols[] = 'updated_at';
    try { $artists = $db->query("SELECT " . implode(', ', $artistCols) . " FROM artists ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { $artists = []; }
    $lyrics = [];
    try {
        $lyricCols = ['track_id','content'];
        if (table_has_column($db, 'lyrics', 'created_at')) $lyricCols[] = 'created_at';
        if (table_has_column($db, 'lyrics', 'updated_at')) $lyricCols[] = 'updated_at';
        $lyrics = $db->query("SELECT " . implode(', ', $lyricCols) . " FROM lyrics ORDER BY track_id ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}

    $playlists = [];
    $playlistTracks = [];
    try {
        // Export all playlists when auth is disabled for export
        $playlists = $db->query("SELECT id, user_id, name, is_public, created_at, cover FROM playlists ORDER BY user_id ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
        $playlistTracks = $db->query("SELECT playlist_id, track_id, position, added_at FROM playlist_tracks ORDER BY playlist_id ASC, position ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $playlists = [];
        $playlistTracks = [];
    }

    json_out([
        'success' => true,
        'format' => 'muzic2-db-transfer-v1',
        'exported_at' => date('c'),
        'data' => [
            'tracks' => $tracks,
            'artists' => $artists,
            'lyrics' => $lyrics,
            'playlists' => $playlists,
            'playlist_tracks' => $playlistTracks,
        ],
        'counts' => [
            'tracks' => count($tracks),
            'artists' => count($artists),
            'lyrics' => count($lyrics),
            'playlists' => count($playlists),
            'playlist_tracks' => count($playlistTracks),
        ]
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'import') {
    if (!isset($_SESSION['user_id'])) {
        json_out(['success' => false, 'error' => 'auth_required'], 401);
    }
    $raw = '';
    if (!empty($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $raw = (string)file_get_contents($_FILES['file']['tmp_name']);
    } else {
        $raw = (string)file_get_contents('php://input');
    }
    $payload = json_decode($raw, true);
    if (!is_array($payload) || empty($payload['data']) || !is_array($payload['data'])) {
        json_out(['success' => false, 'error' => 'bad_format'], 400);
    }

    $d = $payload['data'];
    $db->beginTransaction();
    $imported = ['tracks' => 0, 'artists' => 0, 'lyrics' => 0, 'playlists' => 0, 'playlist_tracks' => 0];

    // Tracks upsert
    if (!empty($d['tracks']) && is_array($d['tracks'])) {
        $stmt = $db->prepare("INSERT INTO tracks (id, title, artist, album, album_type, duration, file_path, cover, video_url, explicit, created_at, updated_at)
            VALUES (:id,:title,:artist,:album,:album_type,:duration,:file_path,:cover,:video_url,:explicit,:created_at,:updated_at)
            ON DUPLICATE KEY UPDATE
              title=VALUES(title), artist=VALUES(artist), album=VALUES(album), album_type=VALUES(album_type),
              duration=VALUES(duration), file_path=VALUES(file_path), cover=VALUES(cover), video_url=VALUES(video_url),
              explicit=VALUES(explicit), updated_at=VALUES(updated_at)");
        foreach ($d['tracks'] as $t) {
            if (!is_array($t) || empty($t['id'])) continue;
            $stmt->execute([
                ':id' => (int)$t['id'],
                ':title' => (string)($t['title'] ?? ''),
                ':artist' => (string)($t['artist'] ?? ''),
                ':album' => (string)($t['album'] ?? ''),
                ':album_type' => (string)($t['album_type'] ?? 'album'),
                ':duration' => (int)($t['duration'] ?? 0),
                ':file_path' => (string)($t['file_path'] ?? ''),
                ':cover' => (string)($t['cover'] ?? ''),
                ':video_url' => (string)($t['video_url'] ?? ''),
                ':explicit' => (int)($t['explicit'] ?? 0),
                ':created_at' => (string)($t['created_at'] ?? date('Y-m-d H:i:s')),
                ':updated_at' => (string)($t['updated_at'] ?? date('Y-m-d H:i:s')),
            ]);
            $imported['tracks'] += 1;
        }
    }

    // Artists upsert (best effort)
    if (!empty($d['artists']) && is_array($d['artists'])) {
        $hasPromo = true;
        try { $db->query("SELECT promo_video, promo_track_id FROM artists LIMIT 1"); } catch (Throwable $e) { $hasPromo = false; }
        $sql = $hasPromo
            ? "INSERT INTO artists (id, name, cover, bio, promo_video, promo_track_id, created_at, updated_at)
               VALUES (?,?,?,?,?,?,?,?)
               ON DUPLICATE KEY UPDATE name=VALUES(name), cover=VALUES(cover), bio=VALUES(bio), promo_video=VALUES(promo_video), promo_track_id=VALUES(promo_track_id), updated_at=VALUES(updated_at)"
            : "INSERT INTO artists (id, name, cover, bio, created_at, updated_at)
               VALUES (?,?,?,?,?,?)
               ON DUPLICATE KEY UPDATE name=VALUES(name), cover=VALUES(cover), bio=VALUES(bio), updated_at=VALUES(updated_at)";
        $stmt = $db->prepare($sql);
        foreach ($d['artists'] as $a) {
            if (!is_array($a) || empty($a['id'])) continue;
            if ($hasPromo) {
                $stmt->execute([(int)$a['id'], (string)($a['name'] ?? ''), (string)($a['cover'] ?? ''), (string)($a['bio'] ?? ''), (string)($a['promo_video'] ?? ''), $a['promo_track_id'] ?? null, (string)($a['created_at'] ?? date('Y-m-d H:i:s')), (string)($a['updated_at'] ?? date('Y-m-d H:i:s'))]);
            } else {
                $stmt->execute([(int)$a['id'], (string)($a['name'] ?? ''), (string)($a['cover'] ?? ''), (string)($a['bio'] ?? ''), (string)($a['created_at'] ?? date('Y-m-d H:i:s')), (string)($a['updated_at'] ?? date('Y-m-d H:i:s'))]);
            }
            $imported['artists'] += 1;
        }
    }

    // Lyrics upsert
    if (!empty($d['lyrics']) && is_array($d['lyrics'])) {
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS lyrics (track_id INT PRIMARY KEY, content MEDIUMTEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
            $stmt = $db->prepare("INSERT INTO lyrics (track_id, content, created_at, updated_at)
                VALUES (?,?,?,?)
                ON DUPLICATE KEY UPDATE content=VALUES(content), updated_at=VALUES(updated_at)");
            foreach ($d['lyrics'] as $l) {
                if (!is_array($l) || empty($l['track_id'])) continue;
                $stmt->execute([(int)$l['track_id'], (string)($l['content'] ?? ''), (string)($l['created_at'] ?? date('Y-m-d H:i:s')), (string)($l['updated_at'] ?? date('Y-m-d H:i:s'))]);
                $imported['lyrics'] += 1;
            }
        } catch (Throwable $e) {}
    }

    // User playlists import (into current user)
    $uid = (int)$_SESSION['user_id'];
    if (!empty($d['playlists']) && is_array($d['playlists'])) {
        $stmt = null;
        $hasCover = true;
        try { $db->query("SELECT cover FROM playlists LIMIT 1"); } catch (Throwable $e) { $hasCover = false; }
        $stmt = $hasCover
            ? $db->prepare("INSERT INTO playlists (user_id, name, is_public, cover) VALUES (?,?,?,?)")
            : $db->prepare("INSERT INTO playlists (user_id, name, is_public) VALUES (?,?,?)");

        $mapOldToNew = [];
        foreach ($d['playlists'] as $p) {
            if (!is_array($p) || empty($p['name'])) continue;
            $name = (string)$p['name'];
            $isPublic = !empty($p['is_public']) ? 1 : 0;
            try {
                if ($hasCover) $stmt->execute([$uid, $name, $isPublic, (string)($p['cover'] ?? '')]);
                else $stmt->execute([$uid, $name, $isPublic]);
                $newId = (int)$db->lastInsertId();
                if (!empty($p['id'])) $mapOldToNew[(int)$p['id']] = $newId;
                $imported['playlists'] += 1;
            } catch (Throwable $e) {
                // ignore duplicates by name etc.
            }
        }

        // playlist tracks
        if (!empty($d['playlist_tracks']) && is_array($d['playlist_tracks'])) {
            $hasAddedAt = true;
            try { $db->query("SELECT added_at FROM playlist_tracks LIMIT 1"); } catch (Throwable $e) { $hasAddedAt = false; }
            $stmtPt = $hasAddedAt
                ? $db->prepare("INSERT IGNORE INTO playlist_tracks (playlist_id, track_id, position, added_at) VALUES (?,?,?,?)")
                : $db->prepare("INSERT IGNORE INTO playlist_tracks (playlist_id, track_id, position) VALUES (?,?,?)");
            foreach ($d['playlist_tracks'] as $pt) {
                if (!is_array($pt)) continue;
                $oldPl = (int)($pt['playlist_id'] ?? 0);
                $newPl = $mapOldToNew[$oldPl] ?? 0;
                if ($newPl <= 0) continue;
                $tid = (int)($pt['track_id'] ?? 0);
                if ($tid <= 0) continue;
                $pos = (int)($pt['position'] ?? 0);
                if ($pos <= 0) $pos = 1;
                if ($hasAddedAt) $stmtPt->execute([$newPl, $tid, $pos, (string)($pt['added_at'] ?? date('Y-m-d H:i:s'))]);
                else $stmtPt->execute([$newPl, $tid, $pos]);
                $imported['playlist_tracks'] += 1;
            }
        }
    }

    $db->commit();
    json_out(['success' => true, 'imported' => $imported]);
}

json_out(['success' => false, 'error' => 'bad_action'], 400);

