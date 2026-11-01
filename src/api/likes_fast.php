<?php
// Optimized likes API - minimal queries
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$db = get_db_connection();
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) { 
    echo json_encode(['tracks' => [], 'albums' => []]); 
    exit; 
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Simple track likes - minimal data
    $stmt = $db->prepare('SELECT t.id, t.title, t.artist, t.cover FROM likes l JOIN tracks t ON l.track_id = t.id WHERE l.user_id = ? LIMIT 20');
    $stmt->execute([$user_id]);
    $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Simple album likes - minimal data
    $stmt = $db->prepare('SELECT album_title FROM album_likes WHERE user_id = ? LIMIT 20');
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
        // Simple track like
        $stmt = $db->prepare('INSERT IGNORE INTO likes (user_id, track_id) VALUES (?, ?)');
        $stmt->execute([$user_id, $track_id]);
        echo json_encode(['success' => true]);
    } elseif ($album_title) {
        // Simple album like
        $stmt = $db->prepare('INSERT IGNORE INTO album_likes (user_id, album_title) VALUES (?, ?)');
        $stmt->execute([$user_id, $album_title]);
        echo json_encode(['success' => true]);
    }
    exit;
}

if ($method === 'DELETE') {
    $track_id = $data['track_id'] ?? null;
    $album_title = $data['album_title'] ?? null;
    
    if ($track_id) {
        // Simple track unlike
        $stmt = $db->prepare('DELETE FROM likes WHERE user_id = ? AND track_id = ?');
        $stmt->execute([$user_id, $track_id]);
        echo json_encode(['success' => true]);
    } elseif ($album_title) {
        // Simple album unlike
        $stmt = $db->prepare('DELETE FROM album_likes WHERE user_id = ? AND album_title = ?');
        $stmt->execute([$user_id, $album_title]);
        echo json_encode(['success' => true]);
    }
    exit;
}

echo json_encode(['error' => 'Invalid method']);
?>
