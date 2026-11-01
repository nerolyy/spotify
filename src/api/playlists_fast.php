<?php
// Optimized playlists API for Windows
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$db = get_db_connection();

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Не авторизован']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $playlist_id = $_GET['playlist_id'] ?? null;
    
    if ($playlist_id) {
        // Return tracks for specific playlist - optimized query
        $stmt = $db->prepare('
            SELECT t.id, t.title, t.artist, t.album, t.duration, t.cover, t.file_path AS src 
            FROM playlist_tracks pt 
            JOIN tracks t ON pt.track_id = t.id 
            WHERE pt.playlist_id = ? 
            ORDER BY pt.position ASC 
            LIMIT 50
        ');
        $stmt->execute([$playlist_id]);
        echo json_encode(['tracks' => $stmt->fetchAll()]);
    } else {
        // Return playlists - optimized query
        $stmt = $db->prepare('
            SELECT p.id, p.name, p.created_at,
                   COUNT(pt.track_id) as track_count
            FROM playlists p 
            LEFT JOIN playlist_tracks pt ON p.id = pt.playlist_id 
            WHERE p.user_id = ? 
            GROUP BY p.id 
            ORDER BY p.created_at DESC 
            LIMIT 20
        ');
        $stmt->execute([$user_id]);
        $playlists = $stmt->fetchAll();
        
        // Add special cover for "Любимые треки" playlist
        foreach ($playlists as &$playlist) {
            if ($playlist['name'] === 'Любимые треки') {
                $playlist['cover'] = 'tracks/covers/favorites-playlist.png';
            }
        }
        
        echo json_encode($playlists);
    }
    exit;
}

// Handle POST requests (create playlist)
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $name = $data['name'] ?? '';
    
    if (empty($name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Название плейлиста обязательно']);
        exit;
    }
    
    $stmt = $db->prepare('INSERT INTO playlists (user_id, name, is_public) VALUES (?, ?, 0)');
    $stmt->execute([$user_id, $name]);
    
    echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
    exit;
}

// Handle DELETE requests (delete playlist)
if ($method === 'DELETE') {
    $playlist_id = $_GET['playlist_id'] ?? null;
    
    if (!$playlist_id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID плейлиста обязателен']);
        exit;
    }
    
    // Delete playlist tracks first
    $stmt = $db->prepare('DELETE FROM playlist_tracks WHERE playlist_id = ?');
    $stmt->execute([$playlist_id]);
    
    // Delete playlist
    $stmt = $db->prepare('DELETE FROM playlists WHERE id = ? AND user_id = ?');
    $stmt->execute([$playlist_id, $user_id]);
    
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Метод не поддерживается']);
?>
