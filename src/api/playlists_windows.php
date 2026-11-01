<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
if (!empty($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Vary: Origin');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['playlists' => []]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

try {
    $pdo = get_db_connection();

    try {
        $ensureFav = $pdo->prepare(
            "INSERT INTO playlists (user_id, name, is_public, cover) SELECT ?, 'Любимые треки', 0, 'tracks/covers/favorites-playlist.png' " .
            "WHERE NOT EXISTS (SELECT 1 FROM playlists p WHERE p.user_id = ? AND p.name = 'Любимые треки')"
        );
        $ensureFav->execute([$user_id, $user_id]);
    } catch (Throwable $e) {
        $ensureFav = $pdo->prepare(
            "INSERT INTO playlists (user_id, name, is_public) SELECT ?, 'Любимые треки', 0 " .
            "WHERE NOT EXISTS (SELECT 1 FROM playlists p WHERE p.user_id = ? AND p.name = 'Любимые треки')"
        );
        $ensureFav->execute([$user_id, $user_id]);
    }

    try {
        $sql = 'SELECT p.id, p.name, p.created_at, p.cover,
                (SELECT COUNT(*) FROM playlist_tracks pt WHERE pt.playlist_id = p.id) AS track_count
                FROM playlists p
                WHERE p.user_id = ?
                ORDER BY p.created_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $playlists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        try {
            $sql = 'SELECT p.id, p.name, p.created_at,
                    (SELECT COUNT(*) FROM playlist_tracks pt WHERE pt.playlist_id = p.id) AS track_count
                    FROM playlists p
                    WHERE p.user_id = ?
                    ORDER BY p.created_at DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
            $playlists = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e2) {
            $stmt = $pdo->prepare(
                'SELECT p.id, p.name,
                (SELECT COUNT(*) FROM playlist_tracks pt WHERE pt.playlist_id = p.id) AS track_count
                 FROM playlists p WHERE p.user_id = ? ORDER BY p.id DESC'
            );
            $stmt->execute([$user_id]);
            $playlists = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        foreach ($playlists as &$pl2) { $pl2['cover'] = $pl2['cover'] ?? null; }
        unset($pl2);
    }

    foreach ($playlists as &$pl) {
        $pl['track_count'] = (int)($pl['track_count'] ?? 0);
        if (isset($pl['name']) && trim($pl['name']) === 'Любимые треки') {
            $pl['cover'] = 'tracks/covers/favorites-playlist.png';
        }
    }
    unset($pl);

    echo json_encode(['playlists' => $playlists]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['playlists' => [], 'error' => 'load_failed']);
}
