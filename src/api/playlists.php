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
    http_response_code(401);
    echo json_encode(['error' => 'Не авторизован']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];

if ($method === 'GET') {
    // If playlist_id provided, return tracks
    $playlist_id = $_GET['playlist_id'] ?? null;
    if ($playlist_id) {
        // Return track info for playlist with feats and alias file_path as src; include optional video_url and added_at
        $sqlWithAddedAt = 'SELECT t.id, t.title, t.artist, t.album, t.duration, t.cover, t.file_path AS src, t.video_url,
            pt.added_at,
            (SELECT GROUP_CONCAT(ta.artist ORDER BY ta.artist SEPARATOR ", ") FROM track_artists ta WHERE ta.track_id=t.id AND ta.role="featured") AS feats
          FROM playlist_tracks pt JOIN tracks t ON pt.track_id = t.id WHERE pt.playlist_id = ? ORDER BY pt.position ASC';
        $sqlFallback = 'SELECT t.id, t.title, t.artist, t.album, t.duration, t.cover, t.file_path AS src, t.video_url,
            l.created_at AS added_at,
            (SELECT GROUP_CONCAT(ta.artist ORDER BY ta.artist SEPARATOR ", ") FROM track_artists ta WHERE ta.track_id=t.id AND ta.role="featured") AS feats
          FROM playlist_tracks pt
          JOIN tracks t ON pt.track_id = t.id
          LEFT JOIN playlists p ON p.id = pt.playlist_id
          LEFT JOIN likes l ON l.track_id = pt.track_id AND l.user_id = p.user_id
          WHERE pt.playlist_id = ? ORDER BY pt.position ASC';
        try {
            $stmt = $db->prepare($sqlWithAddedAt);
            $stmt->execute([$playlist_id]);
        } catch (Throwable $e) {
            $stmt = $db->prepare($sqlFallback);
            $stmt->execute([$playlist_id]);
        }
        echo json_encode(['tracks' => $stmt->fetchAll()]);
    } else {
        // Ensure default "Любимые треки" exists for this user
        $check = $db->prepare('SELECT id FROM playlists WHERE user_id = ? AND name = ? LIMIT 1');
        $check->execute([$user_id, 'Любимые треки']);
        if (!$check->fetch()) {
            try {
                $ins = $db->prepare('INSERT INTO playlists (user_id, name, is_public, cover) VALUES (?, ?, 0, ?)');
                $ins->execute([$user_id, 'Любимые треки', 'tracks/covers/favorites-playlist.png']);
            } catch (Throwable $e) {
                $ins = $db->prepare('INSERT INTO playlists (user_id, name, is_public) VALUES (?, ?, 0)');
                $ins->execute([$user_id, 'Любимые треки']);
            }
        }
        try {
            $stmt = $db->prepare('SELECT id, user_id, name, is_public, created_at, cover FROM playlists WHERE user_id = ? ORDER BY created_at DESC');
        } catch (Throwable $e) {
            $stmt = $db->prepare('SELECT * FROM playlists WHERE user_id = ? ORDER BY created_at DESC');
        }
        $stmt->execute([$user_id]);
        $playlists = $stmt->fetchAll();
        
        // Add special cover for "Любимые треки" playlist
        foreach ($playlists as &$playlist) {
            if ($playlist['name'] === 'Любимые треки') {
                $playlist['cover'] = 'tracks/covers/favorites-playlist.png';
            }
        }
        
        echo json_encode(['playlists' => $playlists]);
    }
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST' && preg_match('#/add$#', $path)) {
    $playlist_id = $data['playlist_id'] ?? null;
    $track_id = $data['track_id'] ?? null;
    if (!$playlist_id || !$track_id) {
        http_response_code(400);
        echo json_encode(['error' => 'playlist_id и track_id обязательны']);
        exit;
    }
    $stmt = $db->prepare('SELECT id FROM playlists WHERE id = ? AND user_id = ?');
    $stmt->execute([$playlist_id, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Нет доступа к плейлисту']);
        exit;
    }
    $stmt = $db->prepare('SELECT MAX(position) AS max_pos FROM playlist_tracks WHERE playlist_id = ?');
    $stmt->execute([$playlist_id]);
    $pos = ($stmt->fetch()['max_pos'] ?? 0) + 1;
    $stmt = $db->prepare('INSERT IGNORE INTO playlist_tracks (playlist_id, track_id, position) VALUES (?, ?, ?)');
    $stmt->execute([$playlist_id, $track_id, $pos]);
    echo json_encode(['success' => true]);
    exit;
}

if ($method === 'POST' && preg_match('#/remove$#', $path)) {
    $playlist_id = $data['playlist_id'] ?? null;
    $track_id = $data['track_id'] ?? null;
    if (!$playlist_id || !$track_id) {
        http_response_code(400);
        echo json_encode(['error' => 'playlist_id и track_id обязательны']);
        exit;
    }
    $stmt = $db->prepare('SELECT id FROM playlists WHERE id = ? AND user_id = ?');
    $stmt->execute([$playlist_id, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Нет доступа к плейлисту']);
        exit;
    }
    $stmt = $db->prepare('DELETE FROM playlist_tracks WHERE playlist_id = ? AND track_id = ?');
    $stmt->execute([$playlist_id, $track_id]);
    echo json_encode(['success' => true]);
    exit;
}

if ($method === 'POST') {
    // rename or delete?
    if (preg_match('#/update$#', $path)) {
        $playlist_id = $data['playlist_id'] ?? null;
        $name = isset($data['name']) ? trim((string)$data['name']) : null;
        $cover = isset($data['cover']) ? trim((string)$data['cover']) : null;
        if (!$playlist_id) { http_response_code(400); echo json_encode(['error' => 'playlist_id обязателен']); exit; }

        $stmt = $db->prepare('SELECT id, name FROM playlists WHERE id = ? AND user_id = ?');
        $stmt->execute([$playlist_id, $user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { http_response_code(403); echo json_encode(['error' => 'Нет доступа к плейлисту']); exit; }

        $sets = [];
        $params = [];
        if ($name !== null && $name !== '') { $sets[] = 'name = ?'; $params[] = $name; }
        if ($cover !== null) {
            // allow empty cover to reset to NULL
            $sets[] = 'cover = ?';
            $params[] = ($cover !== '' ? $cover : null);
        }
        if (empty($sets)) { echo json_encode(['success' => true]); exit; }

        $params[] = $playlist_id;
        $params[] = $user_id;
        try {
            $up = $db->prepare('UPDATE playlists SET ' . implode(', ', $sets) . ' WHERE id = ? AND user_id = ?');
            $up->execute($params);
        } catch (Throwable $e) {
            // In case playlists.cover column doesn't exist yet, retry without it
            if (strpos($e->getMessage(), 'cover') !== false) {
                $sets2 = [];
                $params2 = [];
                if ($name !== null && $name !== '') { $sets2[] = 'name = ?'; $params2[] = $name; }
                $params2[] = $playlist_id;
                $params2[] = $user_id;
                if (empty($sets2)) { echo json_encode(['success' => true]); exit; }
                $up2 = $db->prepare('UPDATE playlists SET ' . implode(', ', $sets2) . ' WHERE id = ? AND user_id = ?');
                $up2->execute($params2);
            } else {
                throw $e;
            }
        }

        echo json_encode(['success' => true]);
        exit;
    }
    if (preg_match('#/rename$#', $path)) {
        $playlist_id = $data['playlist_id'] ?? null;
        $name = trim($data['name'] ?? '');
        if (!$playlist_id || !$name) { http_response_code(400); echo json_encode(['error' => 'playlist_id и name обязательны']); exit; }
        $stmt = $db->prepare('UPDATE playlists SET name = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$name, $playlist_id, $user_id]);
        echo json_encode(['success' => true]);
        exit;
    } elseif (preg_match('#/delete$#', $path)) {
        $playlist_id = $data['playlist_id'] ?? null;
        if (!$playlist_id) { http_response_code(400); echo json_encode(['error' => 'playlist_id обязателен']); exit; }
        $chk = $db->prepare('SELECT id, name FROM playlists WHERE id = ? AND user_id = ?');
        $chk->execute([$playlist_id, $user_id]);
        $plRow = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$plRow) {
            http_response_code(403);
            echo json_encode(['error' => 'Нет доступа к плейлисту']);
            exit;
        }
        if (strcasecmp(trim((string)$plRow['name']), 'Любимые треки') === 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Системный плейлист нельзя удалить']);
            exit;
        }
        $stmt = $db->prepare('DELETE FROM playlists WHERE id = ? AND user_id = ?');
        $stmt->execute([$playlist_id, $user_id]);
        echo json_encode(['success' => true]);
        exit;
    } else {
        $name = trim($data['name'] ?? '');
        $is_public = !empty($data['is_public']) ? 1 : 0;
        if (!$name) {
            http_response_code(400);
            echo json_encode(['error' => 'Имя плейлиста обязательно']);
            exit;
        }
        $cover = trim((string)($data['cover'] ?? ''));
        $trackIds = isset($data['track_ids']) && is_array($data['track_ids']) ? $data['track_ids'] : [];
        $trackIds = array_values(array_unique(array_filter(array_map('intval', $trackIds), function($v){ return $v > 0; })));

        if ($cover === '' && !empty($trackIds)) {
            $coverStmt = $db->prepare('SELECT cover FROM tracks WHERE id = ? AND cover IS NOT NULL AND TRIM(cover) != "" LIMIT 1');
            $coverStmt->execute([$trackIds[0]]);
            $coverRow = $coverStmt->fetch(PDO::FETCH_ASSOC);
            if ($coverRow && !empty($coverRow['cover'])) {
                $cover = trim((string)$coverRow['cover']);
            }
        }

        try {
            $stmt = $db->prepare('INSERT INTO playlists (user_id, name, is_public, cover) VALUES (?, ?, ?, ?)');
            $stmt->execute([$user_id, $name, $is_public, ($cover !== '' ? $cover : null)]);
        } catch (Throwable $e) {
            $stmt = $db->prepare('INSERT INTO playlists (user_id, name, is_public) VALUES (?, ?, ?)');
            $stmt->execute([$user_id, $name, $is_public]);
        }
        $playlistId = (int)$db->lastInsertId();

        if ($playlistId > 0 && !empty($trackIds)) {
            $insPt = $db->prepare('INSERT IGNORE INTO playlist_tracks (playlist_id, track_id, position) VALUES (?, ?, ?)');
            $pos = 1;
            foreach ($trackIds as $tid) {
                $insPt->execute([$playlistId, $tid, $pos]);
                $pos += 1;
            }
        }

        echo json_encode(['success' => true, 'playlist_id' => $playlistId]);
        exit;
    }
}

http_response_code(405);
echo json_encode(['error' => 'Метод не поддерживается']); 