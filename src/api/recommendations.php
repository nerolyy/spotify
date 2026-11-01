<?php
// API для автоматических рекомендаций на основе прослушиваний
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Требуется авторизация']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$user_id = $_SESSION['user_id'];
$limit = (int)($_GET['limit'] ?? 20);

$db = get_db_connection();

try {
    // Убеждаемся, что таблицы существуют
    $db->exec("CREATE TABLE IF NOT EXISTS play_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        track_id INT NOT NULL,
        track_title VARCHAR(255) NOT NULL,
        track_artist VARCHAR(255) NOT NULL,
        album VARCHAR(255),
        duration INT,
        played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        play_duration INT DEFAULT 0,
        completed TINYINT(1) DEFAULT 0,
        INDEX idx_user_id (user_id),
        INDEX idx_track_id (track_id),
        INDEX idx_played_at (played_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Получаем топ артистов пользователя
    $stmt = $db->prepare("SELECT track_artist, COUNT(*) as play_count
                         FROM play_history 
                         WHERE user_id = ? AND played_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                         GROUP BY track_artist
                         ORDER BY play_count DESC
                         LIMIT 5");
    $stmt->execute([$user_id]);
    $topArtists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $recommendedTracks = [];
    
    if (count($topArtists) > 0) {
        // Рекомендуем треки от любимых артистов, которые пользователь еще не слушал
        $artistNames = array_column($topArtists, 'track_artist');
        $placeholders = implode(',', array_fill(0, count($artistNames), '?'));
        
        // Получаем треки, которые пользователь уже слушал
        $playedStmt = $db->prepare("SELECT DISTINCT track_id FROM play_history WHERE user_id = ?");
        $playedStmt->execute([$user_id]);
        $playedTrackIds = $playedStmt->fetchAll(PDO::FETCH_COLUMN);
        $playedPlaceholders = count($playedTrackIds) > 0 ? implode(',', array_fill(0, count($playedTrackIds), '?')) : '0';
        
        // Рекомендуем треки от любимых артистов, которые не были прослушаны
        $recommendPoolLimit = max($limit * 8, 80);
        $recommendStmt = $db->prepare("SELECT t.id, t.title, t.artist, t.album, t.duration, t.cover, t.file_path, t.video_url, t.explicit
                                      FROM tracks t
                                      WHERE t.artist IN ($placeholders)
                                      AND t.id NOT IN ($playedPlaceholders)
                                      ORDER BY t.id DESC
                                      LIMIT $recommendPoolLimit");
        
        $params = array_merge($artistNames, $playedTrackIds);
        $recommendStmt->execute($params);
        $recommendedTracks = $recommendStmt->fetchAll(PDO::FETCH_ASSOC);
        shuffle($recommendedTracks);
        $recommendedTracks = array_slice($recommendedTracks, 0, $limit);
    }
    
    // Если недостаточно рекомендаций, добавляем случайные треки
    if (count($recommendedTracks) < $limit) {
        $needed = $limit - count($recommendedTracks);
        $playedPlaceholders = count($playedTrackIds) > 0 ? implode(',', array_fill(0, count($playedTrackIds), '?')) : '0';
        
        $randomPoolLimit = max($needed * 8, 80);
        $randomStmt = $db->prepare("SELECT t.id, t.title, t.artist, t.album, t.duration, t.cover, t.file_path, t.video_url, t.explicit
                                   FROM tracks t
                                   WHERE t.id NOT IN ($playedPlaceholders)
                                   ORDER BY t.id DESC
                                   LIMIT $randomPoolLimit");
        
        $params = $playedTrackIds;
        $randomStmt->execute($params);
        $randomTracks = $randomStmt->fetchAll(PDO::FETCH_ASSOC);
        shuffle($randomTracks);
        $randomTracks = array_slice($randomTracks, 0, $needed);
        $recommendedTracks = array_merge($recommendedTracks, $randomTracks);
    }
    
    // Перемешиваем результаты
    shuffle($recommendedTracks);
    
    echo json_encode([
        'tracks' => array_slice($recommendedTracks, 0, $limit),
        'based_on' => array_slice($topArtists, 0, 3)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка сервера: ' . $e->getMessage()]);
}
?>
