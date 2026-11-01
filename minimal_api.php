<?php
// Minimal API - no checks, no validation, just data
session_start();
require_once __DIR__ . '/src/config/db.php';

$action = $_GET['action'] ?? 'home';
$db = get_db_connection();

switch ($action) {
    case 'home':
        $tracks = $db->query("SELECT id, title, artist, cover FROM tracks LIMIT 5")->fetchAll();
        $albums = $db->query("SELECT id, title, artist, cover FROM albums LIMIT 5")->fetchAll();
        echo json_encode(['tracks' => $tracks, 'albums' => $albums]);
        break;
        
    case 'login':
        $input = json_decode(file_get_contents('php://input'), true);
        $login = $input['login'] ?? '';
        $password = $input['password'] ?? '';
        
        $stmt = $db->prepare("SELECT id, username FROM users WHERE username = ? AND password = ?");
        $stmt->execute([$login, md5($password)]);
        $user = $stmt->fetch();
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;
        
    case 'user':
        if (isset($_SESSION['user_id'])) {
            $stmt = $db->prepare("SELECT id, username FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            echo json_encode(['user_id' => $user['id'], 'username' => $user['username']]);
        } else {
            echo json_encode(['user_id' => null]);
        }
        break;
        
    case 'playlists':
        if (!isset($_SESSION['user_id'])) {
            echo json_encode([]);
            break;
        }
        
        $stmt = $db->prepare("SELECT id, name FROM playlists WHERE user_id = ? LIMIT 5");
        $stmt->execute([$_SESSION['user_id']]);
        $playlists = $stmt->fetchAll();
        
        foreach ($playlists as &$playlist) {
            $count = $db->prepare("SELECT COUNT(*) as count FROM playlist_tracks WHERE playlist_id = ?");
            $count->execute([$playlist['id']]);
            $playlist['track_count'] = $count->fetch()['count'];
        }
        
        echo json_encode($playlists);
        break;
        
    case 'playlist_tracks':
        $playlist_id = $_GET['playlist_id'] ?? null;
        if (!$playlist_id) {
            echo json_encode(['tracks' => []]);
            break;
        }
        
        $stmt = $db->prepare("SELECT t.id, t.title, t.artist, t.duration FROM playlist_tracks pt JOIN tracks t ON pt.track_id = t.id WHERE pt.playlist_id = ? LIMIT 20");
        $stmt->execute([$playlist_id]);
        echo json_encode(['tracks' => $stmt->fetchAll()]);
        break;
        
    default:
        echo json_encode(['error' => 'Unknown action']);
}
?>
