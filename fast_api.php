<?php
// Ultra fast API for Windows testing
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

// Simple database connection
try {
    $pdo = new PDO('mysql:host=localhost;port=8889;dbname=muzic2', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$action = $_GET['action'] ?? 'test';

switch ($action) {
    case 'test':
        echo json_encode(['status' => 'OK', 'time' => date('Y-m-d H:i:s')]);
        break;
        
    case 'user':
        session_start();
        if (isset($_SESSION['user_id'])) {
            $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['user_id' => $user['id'], 'username' => $user['username']]);
        } else {
            echo json_encode(['user_id' => null]);
        }
        break;
        
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $username = $input['username'] ?? '';
            $password = $input['password'] ?? '';
            
            $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = ? AND password = ?");
            $stmt->execute([$username, md5($password)]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                session_start();
                $_SESSION['user_id'] = $user['id'];
                echo json_encode(['success' => true, 'user' => $user]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            }
        }
        break;
        
    case 'playlists':
        session_start();
        if (!isset($_SESSION['user_id'])) {
            echo json_encode([]);
            break;
        }
        
        // Check if playlists table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'playlists'");
        if ($stmt->rowCount() == 0) {
            echo json_encode([]);
            break;
        }
        
        $stmt = $pdo->prepare("SELECT id, name FROM playlists WHERE user_id = ? LIMIT 10");
        $stmt->execute([$_SESSION['user_id']]);
        $playlists = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add track_count for each playlist
        foreach ($playlists as &$playlist) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM playlist_tracks WHERE playlist_id = ?");
            $stmt->execute([$playlist['id']]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC);
            $playlist['track_count'] = $count['count'];
        }
        
        echo json_encode($playlists);
        break;
        
    case 'tracks':
        $stmt = $pdo->prepare("SELECT id, title, artist, duration, cover FROM tracks LIMIT 20");
        $stmt->execute();
        $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($tracks);
        break;
        
    case 'albums':
        $stmt = $pdo->prepare("SELECT id, title, artist, cover FROM albums LIMIT 20");
        $stmt->execute();
        $albums = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($albums);
        break;
        
    default:
        echo json_encode(['error' => 'Unknown action']);
}
?>
