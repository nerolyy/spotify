<?php
require_once __DIR__ . '/../../../src/config/db.php';
header('Content-Type: application/json; charset=utf-8');

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
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        if ($limit < 1) $limit = 1;
        if ($limit > 200) $limit = 200;
        $after = isset($_GET['after']) ? (string)$_GET['after'] : '';

        $where = 'album IS NOT NULL AND album <> ""';
        $params = [];
        if ($q !== '') {
            $where .= ' AND album LIKE ?';
            $params[] = '%'.$q.'%';
        }
        if ($after !== '') {
            $where .= ' AND album > ?';
            $params[] = $after;
        }

        $st = $db->prepare("SELECT album,
                                   MIN(artist) AS artist,
                                   MIN(album_type) AS album_type,
                                   MIN(cover) AS cover,
                                   COUNT(*) AS track_count
                            FROM tracks
                            WHERE $where
                            GROUP BY album
                            ORDER BY album ASC
                            LIMIT $limit");
        $st->execute($params);
        $rows = $st->fetchAll();

        $nextAfter = '';
        if ($rows && count($rows) > 0) {
            $last = end($rows);
            $nextAfter = (string)($last['album'] ?? '');
        }

        echo json_encode(['success'=>true, 'data'=>$rows, 'next_after'=>$nextAfter], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $raw = file_get_contents('php://input');
    admin_log(['method'=>$method,'raw'=>$raw]);
    $body = json_decode($raw, true);
    if (!is_array($body)) $body = [];

    $action = $body['action'] ?? '';

    if ($action === 'create') {
        $album = trim((string)($body['album'] ?? ''));
        $artist = trim((string)($body['artist'] ?? ''));
        $cover  = trim((string)($body['cover'] ?? ''));
        $type   = in_array(($body['album_type'] ?? 'album'), ['album','ep','single']) ? $body['album_type'] : 'album';
        if ($album === '') throw new Exception('Введите название альбома');
        if ($artist === '') throw new Exception('Введите имя артиста');
        // Insert a minimal placeholder track so album appears in listings
        $st = $db->prepare('INSERT INTO tracks (title, artist, album, album_type, duration, file_path, cover) VALUES (?,?,?,?,?,?,?)');
        $st->execute(['Intro', $artist, $album, $type, 0, 'tracks/music/placeholder.mp3', $cover]);
        echo json_encode(['success'=>true]);
        exit;
    }

    if ($action === 'update') {
        $album = trim((string)($body['album'] ?? ''));
        if ($album === '') throw new Exception('Введите текущее название альбома');
        $album_new = trim((string)($body['album_new'] ?? ''));
        $artist = isset($body['artist']) ? trim((string)$body['artist']) : null;
        $cover = isset($body['cover']) ? trim((string)$body['cover']) : null;
        $type = isset($body['album_type']) && in_array($body['album_type'], ['album','ep','single']) ? $body['album_type'] : null;
        $featsRaw = isset($body['feats']) ? (string)$body['feats'] : '';
        $featsArr = array_filter(array_map('trim', explode(',', $featsRaw)), function($x){ return $x!==''; });

        $db->beginTransaction();
        // If album does not exist in tracks table yet, and only album_new provided, treat as create by inserting a placeholder track entry is not ideal; instead return a clearer error
        $existsStmt = $db->prepare('SELECT COUNT(*) AS c FROM tracks WHERE TRIM(LOWER(album))=TRIM(LOWER(?))');
        $existsStmt->execute([$album]);
        $exists = (int)($existsStmt->fetch()['c'] ?? 0) > 0;
        if (!$exists && $album_new === '' && $artist === null && $cover === null && $type === null) {
            throw new Exception('Альбом не найден. Укажите новое имя/поля или добавьте треки к альбому.');
        }
        if ($artist !== null) { $st = $db->prepare('UPDATE tracks SET artist=? WHERE TRIM(LOWER(album))=TRIM(LOWER(?))'); $st->execute([$artist, $album]); }
        if ($cover !== null)  { $st = $db->prepare('UPDATE tracks SET cover=? WHERE TRIM(LOWER(album))=TRIM(LOWER(?))');  $st->execute([$cover,  $album]); }
        if ($type  !== null)  { $st = $db->prepare('UPDATE tracks SET album_type=? WHERE TRIM(LOWER(album))=TRIM(LOWER(?))'); $st->execute([$type,   $album]); }
        if ($album_new !== ''){ $st = $db->prepare('UPDATE tracks SET album=? WHERE TRIM(LOWER(album))=TRIM(LOWER(?))');  $st->execute([$album_new, $album]); }
        // Apply feats for all tracks in this album if provided
        if (isset($body['feats'])) {
            $albumMatch = $album_new !== '' ? $album_new : $album;
            // ensure mapping table exists
            try { $db->exec("CREATE TABLE IF NOT EXISTS track_artists (id INT AUTO_INCREMENT PRIMARY KEY, track_id INT NOT NULL, artist VARCHAR(255) NOT NULL, role ENUM('primary','featured') NOT NULL DEFAULT 'featured', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uniq_track_artist_role (track_id, artist, role))"); } catch (Throwable $e) {}
            try { $db->exec("CREATE TABLE IF NOT EXISTS album_artists (id INT AUTO_INCREMENT PRIMARY KEY, album VARCHAR(255) NOT NULL, artist VARCHAR(255) NOT NULL, role ENUM('primary','featured') NOT NULL DEFAULT 'featured', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uniq_album_artist_role (album, artist, role))"); } catch (Throwable $e) {}
            $idsStmt = $db->prepare('SELECT id FROM tracks WHERE TRIM(LOWER(album))=TRIM(LOWER(?))');
            $idsStmt->execute([$albumMatch]);
            $trackIds = array_map(function($r){ return (int)$r['id']; }, $idsStmt->fetchAll());
            if (!empty($trackIds)) {
                $in = implode(',', array_fill(0, count($trackIds), '?'));
                $db->prepare("DELETE FROM track_artists WHERE role='featured' AND track_id IN ($in)")->execute($trackIds);
                if (!empty($featsArr)) {
                    $ins = $db->prepare('INSERT IGNORE INTO track_artists (track_id, artist, role) VALUES (?,?,"featured")');
                    foreach ($trackIds as $tid) {
                        foreach ($featsArr as $fa) { $ins->execute([$tid, $fa]); }
                    }
                }
            }
            // Persist album-level featured artists for discovery and new tracks propagation
            $db->prepare('DELETE FROM album_artists WHERE album=? AND role="featured"')->execute([$albumMatch]);
            if (!empty($featsArr)) {
                $ain = $db->prepare('INSERT IGNORE INTO album_artists (album, artist, role) VALUES (?,?,"featured")');
                foreach ($featsArr as $fa) { $ain->execute([$albumMatch, $fa]); }
            }
        }
        $db->commit();
        echo json_encode(['success'=>true]);
        exit;
    }

    if ($action === 'delete') {
        $album = trim((string)($body['album'] ?? ''));
        if ($album === '') { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Введите текущее название альбома']); exit; }
        // Delete all tracks with this album
        $st = $db->prepare('DELETE FROM tracks WHERE TRIM(LOWER(album))=TRIM(LOWER(?))');
        $st->execute([$album]);
        echo json_encode(['success'=>true]);
        exit;
    }

    throw new Exception('Неизвестное действие');
} catch (Throwable $e) {
    admin_log(['error'=>$e->getMessage(), 'trace'=>$e->getTraceAsString()]);
    http_response_code(400);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
