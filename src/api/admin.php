<?php
// Admin API for quick content management
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Нормализуем путь к файлу - должен быть в формате tracks/music/filename.mp3
function normalizeFilePath($path) {
    if (empty($path)) return '';
    
    // Нормализуем разделители
    $path = str_replace('\\', '/', $path);
    
    // Убираем ведущие слэши и /muzic2/
    $path = preg_replace('#^/+muzic2/+#', '', $path);
    $path = ltrim($path, '/');
    
    // Если путь уже начинается с tracks/, возвращаем как есть
    if (strpos($path, 'tracks/') === 0) {
        return $path;
    }
    
    // Если это абсолютный путь, извлекаем относительный
    $root = realpath(__DIR__ . '/../../');
    if ($root && (strpos($path, '/') === 0 || strpos($path, $root) === 0)) {
        $fullPath = realpath($path);
        if ($fullPath && strpos($fullPath, $root) === 0) {
            $path = substr($fullPath, strlen($root) + 1);
            // Если получили путь с tracks/, возвращаем
            if (strpos($path, 'tracks/') === 0) {
                return $path;
            }
        }
    }
    
    // Пробуем найти tracks/ в пути
    $idx = strpos($path, 'tracks/');
    if ($idx !== false) {
        return substr($path, $idx);
    }
    
    // Если ничего не помогло, предполагаем что это имя файла в tracks/music/
    return 'tracks/music/' . basename($path);
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$db = get_db_connection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'stats';
    
    if ($action === 'stats') {
        // Get statistics
        $tracks = $db->query('SELECT COUNT(*) as count FROM tracks')->fetch()['count'];
        $albums = $db->query('SELECT COUNT(DISTINCT album) as count FROM tracks')->fetch()['count'];
        $artists = $db->query('SELECT COUNT(DISTINCT artist) as count FROM tracks WHERE artist IS NOT NULL')->fetch()['count'];
        $users = $db->query('SELECT COUNT(*) as count FROM users')->fetch()['count'];
        
        echo json_encode([
            'tracks' => $tracks,
            'albums' => $albums,
            'artists' => $artists,
            'users' => $users
        ]);
        exit;
    }
    
    if ($action === 'get_artist_videos') {
        try {
            $stmt = $db->prepare('
                SELECT av.*, 
                       GROUP_CONCAT(tvm.track_id) as mapped_track_ids
                FROM artist_videos av
                LEFT JOIN track_video_mapping tvm ON av.id = tvm.artist_video_id
                GROUP BY av.id
                ORDER BY av.created_at DESC
            ');
            $stmt->execute();
            $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process videos to include track mappings
            foreach ($videos as &$video) {
                $video['track_mappings'] = [];
                if ($video['mapped_track_ids']) {
                    $trackIds = explode(',', $video['mapped_track_ids']);
                    foreach ($trackIds as $trackId) {
                        $video['track_mappings'][] = ['track_id' => intval($trackId)];
                    }
                }
                unset($video['mapped_track_ids']);
            }
            
            echo json_encode(['success' => true, 'videos' => $videos]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    
    if ($action === 'add_track') {
        $title = trim($data['title'] ?? '');
        $artist = trim($data['artist'] ?? '');
        $album = trim($data['album'] ?? '');
        $duration = intval($data['duration'] ?? 0);
        $cover = trim($data['cover'] ?? '');
        $albumType = in_array(($data['album_type'] ?? 'album'), ['album','ep','single']) ? $data['album_type'] : 'album';
        $filePath = trim($data['file_path'] ?? ($data['audio'] ?? ''));
        
        if (!$title || !$artist || !$filePath) {
            http_response_code(400);
            echo json_encode(['error' => 'Название, артист и файл аудио обязательны']);
            exit;
        }
        
        $filePath = normalizeFilePath($filePath);
        
        // Проверяем и копируем файл в tracks/music/ если нужно
        $root = realpath(__DIR__ . '/../../');
        $targetPath = $root . '/' . $filePath;
        $targetDir = dirname($targetPath);
        
        // Если файл не находится в tracks/music/, копируем его туда
        if (strpos($filePath, 'tracks/music/') === 0) {
            // Путь уже правильный, проверяем существует ли файл
            if (!file_exists($targetPath)) {
                // Пробуем найти исходный файл
                $originalPath = trim($data['file_path'] ?? ($data['audio'] ?? ''));
                if ($originalPath && file_exists($originalPath)) {
                    // Создаем директорию если нужно
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }
                    // Копируем файл
                    if (copy($originalPath, $targetPath)) {
                        // Файл скопирован
                    } else {
                        http_response_code(500);
                        echo json_encode(['error' => 'Не удалось скопировать файл']);
                        exit;
                    }
                } else {
                    // Файл не найден, но продолжаем (может быть это URL)
                }
            }
        }
        
        try {
            // Insert track
            $stmt = $db->prepare('
                INSERT INTO tracks (title, artist, album, album_type, duration, file_path, cover, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([$title, $artist, $album, $albumType, $duration, $filePath, $cover]);
            
            $trackId = $db->lastInsertId();
            
            // track_artists table is not used in current schema
            
            echo json_encode(['success' => true, 'id' => $trackId]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Ошибка добавления трека: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'add_album') {
        $title = trim($data['title'] ?? '');
        $artist = trim($data['artist'] ?? '');
        $albumType = in_array(($data['album_type'] ?? 'album'), ['album','ep','single']) ? $data['album_type'] : 'album';
        $cover = trim($data['cover'] ?? '');
        
        if (!$title || !$artist) {
            http_response_code(400);
            echo json_encode(['error' => 'Название и артист обязательны']);
            exit;
        }
        
        try {
            // Create minimal placeholder track so album appears in listings
            $stmt = $db->prepare('
                INSERT INTO tracks (title, artist, album, album_type, duration, file_path, cover, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute(['Intro', $artist, $title, $albumType, 0, 'tracks/music/placeholder.mp3', $cover]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Ошибка добавления альбома: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'add_artist') {
        $name = trim($data['name'] ?? '');
        $genre = trim($data['genre'] ?? '');
        $country = trim($data['country'] ?? '');
        $avatar = trim($data['avatar'] ?? '');
        $description = trim($data['description'] ?? '');
        
        if (!$name) {
            http_response_code(400);
            echo json_encode(['error' => 'Имя артиста обязательно']);
            exit;
        }
        
        try {
            // Check if artist exists
            $checkStmt = $db->prepare('SELECT COUNT(*) as count FROM tracks WHERE artist = ?');
            $checkStmt->execute([$name]);
            $exists = $checkStmt->fetch()['count'] > 0;
            
            if ($exists) {
                echo json_encode(['success' => true, 'message' => 'Артист уже существует в базе']);
                exit;
            }
            
            // Create a sample track for the artist (no track_artists table)
            $stmt = $db->prepare('
                INSERT INTO tracks (title, artist, album, album_type, duration, file_path, cover, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([
                'Добро пожаловать - ' . $name,
                $name,
                'Сборник',
                'album',
                180,
                normalizeFilePath('tracks/music/placeholder.mp3'),
                $avatar
            ]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Ошибка добавления артиста: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'bulk_add_tracks') {
        $tracks = $data['tracks'] ?? [];
        
        if (empty($tracks)) {
            http_response_code(400);
            echo json_encode(['error' => 'Нет треков для добавления']);
            exit;
        }
        
        $added = 0;
        $errors = [];
        
        foreach ($tracks as $track) {
            try {
                $albumType = in_array(($track['album_type'] ?? 'album'), ['album','ep','single']) ? $track['album_type'] : 'album';
                $filePath = $track['file_path'] ?? ($track['audio'] ?? 'tracks/music/placeholder.mp3');
                $filePath = normalizeFilePath($filePath);
                $stmt = $db->prepare('
                    INSERT INTO tracks (title, artist, album, album_type, duration, file_path, cover, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ');
                $stmt->execute([
                    $track['title'] ?? 'Без названия',
                    $track['artist'] ?? 'Неизвестный артист',
                    $track['album'] ?? '',
                    $albumType,
                    $track['duration'] ?? 180,
                    $filePath,
                    $track['cover'] ?? ''
                ]);
                
                $added++;
            } catch (Exception $e) {
                $errors[] = $track['title'] . ': ' . $e->getMessage();
            }
        }
        
        echo json_encode([
            'success' => true,
            'added' => $added,
            'total' => count($tracks),
            'errors' => $errors
        ]);
        exit;
    }
    
    // Handle artist videos
    if ($action === 'add_artist_video') {
        try {
            $artist = trim($_POST['artist'] ?? '');
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $videoPath = trim($_POST['video_path'] ?? '');
            $thumbnail = trim($_POST['thumbnail'] ?? '');
            $videoType = $_POST['video_type'] ?? 'other';
            $duration = intval($_POST['duration'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($artist) || empty($title) || empty($videoPath)) {
                throw new Exception('Артист, название и путь к видео обязательны');
            }
            
            $stmt = $db->prepare('
                INSERT INTO artist_videos (artist, title, description, video_path, thumbnail, video_type, duration, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([$artist, $title, $description, $videoPath, $thumbnail, $videoType, $duration, $isActive]);
            
            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'update_artist_video') {
        try {
            $id = intval($_POST['id'] ?? 0);
            $artist = trim($_POST['artist'] ?? '');
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $videoPath = trim($_POST['video_path'] ?? '');
            $thumbnail = trim($_POST['thumbnail'] ?? '');
            $videoType = $_POST['video_type'] ?? 'other';
            $duration = intval($_POST['duration'] ?? 0);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($artist) || empty($title) || empty($videoPath)) {
                throw new Exception('Артист, название и путь к видео обязательны');
            }
            
            $stmt = $db->prepare('
                UPDATE artist_videos 
                SET artist = ?, title = ?, description = ?, video_path = ?, thumbnail = ?, 
                    video_type = ?, duration = ?, is_active = ?
                WHERE id = ?
            ');
            $stmt->execute([$artist, $title, $description, $videoPath, $thumbnail, $videoType, $duration, $isActive, $id]);
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'delete_artist_video') {
        try {
            $id = intval($data['id'] ?? 0);
            
            // Delete track mappings first
            $db->prepare('DELETE FROM track_video_mapping WHERE artist_video_id = ?')->execute([$id]);
            
            // Delete video
            $stmt = $db->prepare('DELETE FROM artist_videos WHERE id = ?');
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'update_track_video_mapping') {
        try {
            $videoId = intval($data['video_id'] ?? 0);
            $trackIds = $data['track_ids'] ?? [];
            
            // Remove existing mappings
            $db->prepare('DELETE FROM track_video_mapping WHERE artist_video_id = ?')->execute([$videoId]);
            
            // Add new mappings
            if (!empty($trackIds)) {
                $stmt = $db->prepare('INSERT INTO track_video_mapping (artist_video_id, track_id) VALUES (?, ?)');
                foreach ($trackIds as $trackId) {
                    $stmt->execute([$videoId, intval($trackId)]);
                }
            }
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'remove_track_video_mapping') {
        try {
            $videoId = intval($data['video_id'] ?? 0);
            $trackId = intval($data['track_id'] ?? 0);
            
            $stmt = $db->prepare('DELETE FROM track_video_mapping WHERE artist_video_id = ? AND track_id = ?');
            $stmt->execute([$videoId, $trackId]);
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

http_response_code(405);
echo json_encode(['error' => 'Метод не поддерживается']);
?>
