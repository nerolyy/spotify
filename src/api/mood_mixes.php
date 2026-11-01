<?php
// API для миксов по настроению
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

$mood = $_GET['mood'] ?? 'energetic'; // energetic, calm, sad, happy, romantic, workout, focus
$limit = (int)($_GET['limit'] ?? 20);

$db = get_db_connection();

try {
    // Убеждаемся, что таблицы существуют
    $db->exec("CREATE TABLE IF NOT EXISTS mood_tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        track_id INT NOT NULL,
        mood VARCHAR(50) NOT NULL,
        confidence DECIMAL(3,2) DEFAULT 0.5,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_track_id (track_id),
        INDEX idx_mood (mood)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Получаем треки с нужным настроением
    // Если настроений нет в БД, используем эвристику на основе названий и артистов
    $stmt = $db->prepare("SELECT t.id, t.title, t.artist, t.album, t.duration, t.cover, t.file_path, t.video_url, t.explicit
                         FROM tracks t
                         LEFT JOIN mood_tags mt ON t.id = mt.track_id AND mt.mood = ?
                         WHERE mt.mood = ? OR (
                             -- Эвристика для определения настроения по названию и артисту
                             (? = 'energetic' AND (
                                 t.title LIKE '%rap%' OR t.title LIKE '%hip%' OR t.title LIKE '%rock%' OR
                                 t.title LIKE '%энерг%' OR t.title LIKE '%драйв%'
                             )) OR
                             (? = 'calm' AND (
                                 t.title LIKE '%lo-fi%' OR t.title LIKE '%chill%' OR t.title LIKE '%ambient%' OR
                                 t.title LIKE '%спокой%' OR t.title LIKE '%медлен%'
                             )) OR
                             (? = 'sad' AND (
                                 t.title LIKE '%sad%' OR t.title LIKE '%depression%' OR t.title LIKE '%lonely%' OR
                                 t.title LIKE '%груст%' OR t.title LIKE '%одинок%'
                             )) OR
                             (? = 'happy' AND (
                                 t.title LIKE '%happy%' OR t.title LIKE '%joy%' OR t.title LIKE '%party%' OR
                                 t.title LIKE '%радост%' OR t.title LIKE '%весел%'
                             )) OR
                             (? = 'romantic' AND (
                                 t.title LIKE '%love%' OR t.title LIKE '%romance%' OR t.title LIKE '%heart%' OR
                                 t.title LIKE '%любов%' OR t.title LIKE '%роман%'
                             )) OR
                             (? = 'workout' AND (
                                 t.title LIKE '%workout%' OR t.title LIKE '%gym%' OR t.title LIKE '%train%' OR
                                 t.title LIKE '%тренир%' OR t.title LIKE '%спорт%'
                             )) OR
                             (? = 'focus' AND (
                                 t.title LIKE '%focus%' OR t.title LIKE '%study%' OR t.title LIKE '%concentrate%' OR
                                 t.title LIKE '%концентр%' OR t.title LIKE '%учеба%'
                             ))
                         )
                         ORDER BY mt.confidence DESC, RAND()
                         LIMIT ?");
    
    $stmt->execute([$mood, $mood, $mood, $mood, $mood, $mood, $mood, $mood, $limit]);
    $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Если недостаточно треков, добавляем случайные
    if (count($tracks) < $limit) {
        $needed = $limit - count($tracks);
        $randomStmt = $db->prepare("SELECT t.id, t.title, t.artist, t.album, t.duration, t.cover, t.file_path, t.video_url, t.explicit
                                   FROM tracks t
                                   WHERE t.id NOT IN (SELECT track_id FROM mood_tags WHERE mood = ?)
                                   ORDER BY RAND()
                                   LIMIT ?");
        $randomStmt->execute([$mood, $needed]);
        $randomTracks = $randomStmt->fetchAll(PDO::FETCH_ASSOC);
        $tracks = array_merge($tracks, $randomTracks);
    }
    
    // Перемешиваем результаты
    shuffle($tracks);
    
    echo json_encode([
        'mood' => $mood,
        'tracks' => array_slice($tracks, 0, $limit)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка сервера: ' . $e->getMessage()]);
}
?>
