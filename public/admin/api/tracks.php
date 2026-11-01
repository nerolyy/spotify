<?php
require_once __DIR__ . '/../../../src/config/db.php';
header('Content-Type: application/json; charset=utf-8');

function admin_parse_feats($raw): array
{
    if (is_array($raw)) {
        $parts = $raw;
    } else {
        $parts = preg_split('/[,;]+/u', (string) $raw);
    }
    $out = [];
    $seen = [];
    foreach ($parts as $p) {
        $name = trim((string) $p);
        if ($name === '') {
            continue;
        }
        $key = mb_strtolower($name, 'UTF-8');
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $name;
    }

    return $out;
}

function admin_ensure_track_artists_table(PDO $db): void
{
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS track_artists (
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

function admin_sync_track_artists(PDO $db, int $trackId, string $primaryArtist, array $featsArr): void
{
    admin_ensure_track_artists_table($db);
    $db->prepare('DELETE FROM track_artists WHERE track_id=?')->execute([$trackId]);

    $primaryArtist = trim($primaryArtist);
    if ($primaryArtist !== '') {
        $st = $db->prepare('INSERT IGNORE INTO track_artists (track_id, artist, role) VALUES (?, ?, "primary")');
        $st->execute([$trackId, $primaryArtist]);
    }

    $seen = [];
    if ($primaryArtist !== '') {
        $seen[mb_strtolower($primaryArtist, 'UTF-8')] = true;
    }

    $insFeat = $db->prepare('INSERT IGNORE INTO track_artists (track_id, artist, role) VALUES (?, ?, "featured")');
    $insArtist = $db->prepare('INSERT IGNORE INTO artists (name) VALUES (?)');
    foreach ($featsArr as $fa) {
        $name = trim((string) $fa);
        if ($name === '') {
            continue;
        }
        $key = mb_strtolower($name, 'UTF-8');
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $insFeat->execute([$trackId, $name]);
        try {
            $insArtist->execute([$name]);
        } catch (Throwable $e) {
            // ignore
        }
    }
}

function admin_log($message){
    try {
        $file = __DIR__ . '/../admin_api.log';
        $prefix = '['.date('Y-m-d H:i:s').'] ' . ($_SERVER['REQUEST_URI'] ?? '') . ' ';
        if (is_array($message) || is_object($message)) { $message = json_encode($message, JSON_UNESCAPED_UNICODE); }
        @file_put_contents($file, $prefix . $message . "\n", FILE_APPEND);
    } catch (Throwable $e) {}
}

try {
    $db = get_db_connection();
    try { $db->exec("ALTER TABLE tracks ADD COLUMN video_url VARCHAR(500) NULL"); } catch (Throwable $e) { /* ignore if exists */ }
    try { $db->exec("ALTER TABLE tracks ADD COLUMN explicit TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $e) { /* ignore if exists */ }
    try { $db->exec("ALTER TABLE tracks ADD COLUMN genre VARCHAR(120) NULL"); } catch (Throwable $e) { /* ignore if exists */ }
    admin_ensure_track_artists_table($db);
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
        $albumExact = isset($_GET['album']) ? trim((string)$_GET['album']) : '';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        if ($limit < 1) $limit = 1;
        if ($limit > 200) $limit = 200;
        $afterId = isset($_GET['after_id']) ? (int)$_GET['after_id'] : 0;

        // Select only fields used by admin UI
        $cols = 'id, title, artist, album, album_type, duration, file_path, cover, COALESCE(video_url,"") AS video_url, explicit, COALESCE(genre,"") AS genre';

        if ($albumExact !== '') {
            // Album track listing (used inside album modal)
            $st = $db->prepare("SELECT $cols FROM tracks WHERE TRIM(LOWER(album))=TRIM(LOWER(?)) AND (?=0 OR id > ?) ORDER BY id ASC LIMIT $limit");
            $st->execute([$albumExact, $afterId, $afterId]);
            $rows = $st->fetchAll();
        } elseif ($q !== '') {
            $like = '%'.$q.'%';
            $st = $db->prepare("SELECT $cols FROM tracks WHERE (title LIKE ? OR artist LIKE ? OR album LIKE ?) AND (?=0 OR id < ?) ORDER BY id DESC LIMIT $limit");
            $st->execute([$like, $like, $like, $afterId, $afterId]);
            $rows = $st->fetchAll();
        } else {
            $st = $db->prepare("SELECT $cols FROM tracks WHERE (?=0 OR id < ?) ORDER BY id DESC LIMIT $limit");
            $st->execute([$afterId, $afterId]);
            $rows = $st->fetchAll();
        }
        // Attach feats
        try {
            if ($rows && count($rows)>0) {
                $ids = array_map(function($r){ return (int)$r['id']; }, $rows);
                $in = implode(',', array_fill(0, count($ids), '?'));
                $qs = $db->prepare("SELECT track_id, GROUP_CONCAT(artist ORDER BY artist SEPARATOR ', ') AS feats FROM track_artists WHERE role='featured' AND track_id IN ($in) GROUP BY track_id");
                $qs->execute($ids);
                $m = [];
                foreach ($qs as $rr) { $m[(int)$rr['track_id']] = (string)$rr['feats']; }
                foreach ($rows as &$r) { $r['feats'] = $m[(int)$r['id']] ?? ''; }
            }
        } catch (Throwable $e) {}
        $nextAfter = 0;
        if ($rows && count($rows) > 0) {
            // For DESC lists, caller should pass after_id=<last_seen_id> to fetch older (id < after_id)
            // For ASC album lists, caller should pass after_id=<last_seen_id> to fetch newer (id > after_id)
            $last = end($rows);
            $nextAfter = (int)($last['id'] ?? 0);
        }
        echo json_encode(['success'=>true, 'data'=>$rows, 'next_after_id'=>$nextAfter], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $raw = file_get_contents('php://input');
    admin_log(['method'=>$method,'raw'=>$raw]);
    $body = json_decode($raw, true);
    if (!is_array($body)) $body = [];

    $action = $body['action'] ?? '';

    if ($action === 'create') {
        $title = trim((string)($body['title'] ?? ''));
        $artist = trim((string)($body['artist'] ?? ''));
        $album = trim((string)($body['album'] ?? ''));
        $file = trim((string)($body['file_path'] ?? ''));
        $cover = trim((string)($body['cover'] ?? ''));
        $video_url = trim((string)($body['video_url'] ?? ''));
        $explicit = !empty($body['explicit']) ? 1 : 0;
        $genre = trim((string)($body['genre'] ?? ''));
        $type = in_array(($body['album_type'] ?? 'album'), ['album','ep','single']) ? $body['album_type'] : 'album';
        $dur = (int)($body['duration'] ?? 0);
        if ($title==='' || $artist==='' || $album==='' || $file==='') throw new Exception('Заполните обязательные поля');
        
        function normalizeFilePath($path) {
            if (empty($path)) return '';
            $path = str_replace('\\', '/', $path);
            
            $path = preg_replace('#^/+muzic2/+#', '', $path);
            $path = ltrim($path, '/');
            
            if (strpos($path, 'tracks/') === 0) {
                return $path;
            }
            
            $root = realpath(__DIR__ . '/../../../../');
            if ($root && (strpos($path, '/') === 0 || strpos($path, $root) === 0)) {
                $fullPath = realpath($path);
                if ($fullPath && strpos($fullPath, $root) === 0) {
                    $path = substr($fullPath, strlen($root) + 1);
                    
                    if (strpos($path, 'tracks/') === 0) {
                        return $path;
                    }
                }
            }

            $idx = strpos($path, 'tracks/');
            if ($idx !== false) {
                return substr($path, $idx);
            }
            
            return 'tracks/music/' . basename($path);
        }
        
        $file = normalizeFilePath($file);
        
        $root = realpath(__DIR__ . '/../../../../');
        $targetPath = $root . '/' . $file;
        $targetDir = dirname($targetPath);
        
        if (strpos($file, 'tracks/music/') === 0) {
            if (!file_exists($targetPath)) {
                $originalPath = trim((string)($body['file_path'] ?? ''));
                if ($originalPath && file_exists($originalPath)) {
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }
                    if (!copy($originalPath, $targetPath)) {
                        throw new Exception('Не удалось скопировать файл');
                    }
                }
            }
        }
        
        $st = $db->prepare('INSERT INTO tracks (title, artist, album, album_type, duration, file_path, cover, video_url, explicit, genre) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $st->execute([$title,$artist,$album,$type,$dur,$file,$cover,$video_url,$explicit,$genre]);
        $newId = (int)$db->lastInsertId();
        admin_sync_track_artists($db, $newId, $artist, admin_parse_feats($body['feats'] ?? ''));
        echo json_encode(['success'=>true, 'id'=>$newId]);
        exit;
    }

    if ($action === 'update') {
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) throw new Exception('Неверный ID');
        $fields = ['title','artist','album','album_type','duration','file_path','cover','video_url','explicit','genre'];
        $set=[]; $params=[':id'=>$id];
        foreach ($fields as $f) {
            if (array_key_exists($f, $body)) {
                $set[] = "$f = :$f";
                if ($f==='duration') { $params[":$f"] = (int)$body[$f]; }
                elseif ($f==='explicit') { $params[":$f"] = !empty($body[$f]) ? 1 : 0; }
                else { $params[":$f"] = trim((string)$body[$f]); }
            }
        }
        if (!$set) throw new Exception('Нечего сохранять');
        $st = $db->prepare('UPDATE tracks SET '.implode(',', $set).' WHERE id=:id');
        $st->execute($params);
        if (array_key_exists('feats', $body) || array_key_exists('artist', $body)) {
            $primary = array_key_exists('artist', $body) ? trim((string) $body['artist']) : '';
            if ($primary === '') {
                $st = $db->prepare('SELECT artist FROM tracks WHERE id=? LIMIT 1');
                $st->execute([$id]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                $primary = trim((string) ($row['artist'] ?? ''));
            }
            $feats = array_key_exists('feats', $body) ? admin_parse_feats($body['feats']) : [];
            if (!array_key_exists('feats', $body)) {
                $qs = $db->prepare("SELECT GROUP_CONCAT(artist ORDER BY artist SEPARATOR ', ') AS feats FROM track_artists WHERE track_id=? AND role='featured' GROUP BY track_id");
                $qs->execute([$id]);
                $fr = $qs->fetch(PDO::FETCH_ASSOC);
                $feats = admin_parse_feats($fr['feats'] ?? '');
            }
            admin_sync_track_artists($db, $id, $primary, $feats);
        }
        echo json_encode(['success'=>true]);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) throw new Exception('Неверный ID');
        try { $db->prepare('DELETE FROM track_artists WHERE track_id=?')->execute([$id]); } catch (Throwable $e) {}
        $st = $db->prepare('DELETE FROM tracks WHERE id=?');
        $st->execute([$id]);
        echo json_encode(['success'=>true]);
        exit;
    }

    throw new Exception('Неизвестное действие');
} catch (Throwable $e) {
    admin_log(['error'=>$e->getMessage(), 'trace'=>$e->getTraceAsString()]);
    http_response_code(400);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
