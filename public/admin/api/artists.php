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

function find_artist_id_by_name(PDO $db, string $name): ?int {
    $st = $db->prepare('SELECT id FROM artists WHERE TRIM(LOWER(name)) = TRIM(LOWER(?)) LIMIT 1');
    $st->execute([$name]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || !isset($row['id'])) return null;
    $id = (int)$row['id'];
    return $id > 0 ? $id : null;
}

try {
    $db = get_db_connection();

    // NOTE: DDL/migrations must not run here (hot path). Use /muzic2/scripts/setup_db.php for setup.

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        if ($limit < 1) $limit = 1;
        if ($limit > 200) $limit = 200;
        $after = isset($_GET['after']) ? (string)$_GET['after'] : '';

        // Fast path: equality join can use indexes on tracks(artist, id)
        // Track counts are still computed, but the join predicate is now indexable.
        $where = '1=1';
        $params = [];
        if ($q !== '') {
            $where .= ' AND a.name LIKE ?';
            $params[] = '%'.$q.'%';
        }
        if ($after !== '') {
            $where .= ' AND a.name > ?';
            $params[] = $after;
        }

        $sql = "SELECT a.name AS artist,
                       a.cover AS artist_cover,
                       a.bio AS bio,
                       a.promo_video AS promo_video,
                       (
                         SELECT COUNT(DISTINCT t.id)
                         FROM tracks t
                         LEFT JOIN track_artists ta ON ta.track_id = t.id AND ta.role = 'featured'
                         WHERE TRIM(LOWER(t.artist)) = TRIM(LOWER(a.name))
                            OR TRIM(LOWER(ta.artist)) = TRIM(LOWER(a.name))
                       ) AS tracks,
                       (
                         SELECT MIN(t.cover)
                         FROM tracks t
                         LEFT JOIN track_artists ta ON ta.track_id = t.id AND ta.role = 'featured'
                         WHERE TRIM(LOWER(t.artist)) = TRIM(LOWER(a.name))
                            OR TRIM(LOWER(ta.artist)) = TRIM(LOWER(a.name))
                       ) AS track_cover
                FROM artists a
                WHERE $where
                ORDER BY a.name ASC
                LIMIT $limit";
        $st = $db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();

        $nextAfter = '';
        if ($rows && count($rows) > 0) {
            $last = end($rows);
            $nextAfter = (string)($last['artist'] ?? '');
        }

        echo json_encode(['success'=>true, 'data'=>$rows, 'next_after'=>$nextAfter], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Read JSON body
    $raw = file_get_contents('php://input');
    admin_log(['method'=>$_SERVER['REQUEST_METHOD'] ?? '','raw'=>$raw]);
    $body = json_decode($raw, true);
    if (!is_array($body)) $body = [];

    $action = isset($body['action']) ? (string)$body['action'] : '';

    if ($action === 'create') {
        $name = trim((string)($body['name'] ?? ''));
        $cover = trim((string)($body['cover'] ?? ''));
        $bio = trim((string)($body['bio'] ?? ''));
        $promoVideo = trim((string)($body['promo_video'] ?? ''));
        if ($name === '') throw new Exception('Введите имя артиста');
        $existingId = find_artist_id_by_name($db, $name);
        if ($existingId) {
            $st = $db->prepare('UPDATE artists SET name = ?, cover = ?, bio = ?, promo_video = ? WHERE id = ?');
            $st->execute([$name, $cover, $bio, $promoVideo, $existingId]);
        } else {
            $st = $db->prepare('INSERT INTO artists (name, cover, bio, promo_video) VALUES (?, ?, ?, ?)');
            $st->execute([$name, $cover, $bio, $promoVideo]);
        }
        echo json_encode(['success'=>true]);
        exit;
    }

    if ($action === 'update') {
        $name = trim((string)($body['name'] ?? ''));
        $name_new = trim((string)($body['name_new'] ?? $name));
        $cover = trim((string)($body['cover'] ?? ''));
        $bio = trim((string)($body['bio'] ?? ''));
        $promoVideo = trim((string)($body['promo_video'] ?? ''));
        if ($name === '') throw new Exception('Введите текущее имя артиста');
        $db->beginTransaction();
        $existingId = find_artist_id_by_name($db, $name);
        if (strcasecmp($name, $name_new) !== 0) {
            $st = $db->prepare('UPDATE tracks SET artist = ? WHERE TRIM(LOWER(artist)) = TRIM(LOWER(?))');
            $st->execute([$name_new, $name]);
        }
        if ($existingId) {
            $st = $db->prepare('UPDATE artists SET name = ?, cover = ?, bio = ?, promo_video = ? WHERE id = ?');
            $st->execute([$name_new, $cover, $bio, $promoVideo, $existingId]);
        } else {
            $st = $db->prepare('INSERT INTO artists (name, cover, bio, promo_video) VALUES (?, ?, ?, ?)');
            $st->execute([$name_new, $cover, $bio, $promoVideo]);
        }
        $db->commit();
        echo json_encode(['success'=>true]);
        exit;
    }

    if ($action === 'delete') {
        $name = trim((string)($body['name'] ?? ''));
        if ($name === '') throw new Exception('Введите имя артиста');
        $st = $db->prepare('DELETE FROM artists WHERE TRIM(LOWER(name)) = TRIM(LOWER(?))');
        $st->execute([$name]);
        echo json_encode(['success'=>true]);
        exit;
    }

    throw new Exception('Неизвестное действие');
} catch (Throwable $e) {
    admin_log(['error'=>$e->getMessage(), 'trace'=>$e->getTraceAsString()]);
    http_response_code(400);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
