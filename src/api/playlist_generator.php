<?php
// API для автогенерации плейлистов
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

$db = get_db_connection();

try {
    $type = $_GET['type'] ?? 'mood'; // mood, time, activity, weather
    $value = $_GET['value'] ?? '';
    $limit = min((int)($_GET['limit'] ?? 30), 100);
    
    $tracks = [];
    
    if ($type === 'mood') {
        // Используем существующий API настроений
        $mood = $value ?: 'energetic';
        $stmt = $db->prepare("SELECT t.id, t.title, t.artist, t.album, t.duration, t.cover, t.file_path as src, COALESCE(t.video_url, '') AS video_url, t.explicit
                             FROM tracks t
                             LEFT JOIN mood_tags mt ON t.id = mt.track_id AND mt.mood = ?
                             WHERE mt.mood = ? OR (
                                 (? = 'energetic' AND (t.title LIKE '%rap%' OR t.title LIKE '%hip%' OR t.title LIKE '%rock%')) OR
                                 (? = 'calm' AND (t.title LIKE '%lo-fi%' OR t.title LIKE '%chill%' OR t.title LIKE '%ambient%')) OR
                                 (? = 'sad' AND (t.title LIKE '%sad%' OR t.title LIKE '%depression%' OR t.title LIKE '%lonely%')) OR
                                 (? = 'happy' AND (t.title LIKE '%happy%' OR t.title LIKE '%joy%' OR t.title LIKE '%party%')) OR
                                 (? = 'romantic' AND (t.title LIKE '%love%' OR t.title LIKE '%romance%' OR t.title LIKE '%heart%')) OR
                                 (? = 'workout' AND (t.title LIKE '%workout%' OR t.title LIKE '%gym%' OR t.title LIKE '%train%')) OR
                                 (? = 'focus' AND (t.title LIKE '%focus%' OR t.title LIKE '%study%' OR t.title LIKE '%concentrate%'))
                             )
                             ORDER BY mt.confidence DESC, RAND()
                             LIMIT ?");
        $stmt->execute([$mood, $mood, $mood, $mood, $mood, $mood, $mood, $mood, $limit]);
        $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } 
    elseif ($type === 'time') {
        // Генерация по времени суток
        $hour = (int)date('H');
        $mood = 'energetic';
        
        if ($hour >= 6 && $hour < 12) {
            $mood = 'energetic'; // Утро - энергичная музыка
        } elseif ($hour >= 12 && $hour < 18) {
            $mood = 'happy'; // День - позитивная музыка
        } elseif ($hour >= 18 && $hour < 22) {
            $mood = 'calm'; // Вечер - спокойная музыка
        } else {
            $mood = 'calm'; // Ночь - спокойная музыка
        }
        
        if ($value) $mood = $value;
        
        $stmt = $db->prepare("SELECT t.id, t.title, t.artist, t.album, t.duration, t.cover, t.file_path as src, COALESCE(t.video_url, '') AS video_url, t.explicit
                             FROM tracks t
                             LEFT JOIN mood_tags mt ON t.id = mt.track_id AND mt.mood = ?
                             WHERE mt.mood = ? OR (
                                 (? = 'energetic' AND (t.title LIKE '%rap%' OR t.title LIKE '%hip%' OR t.title LIKE '%rock%')) OR
                                 (? = 'calm' AND (t.title LIKE '%lo-fi%' OR t.title LIKE '%chill%' OR t.title LIKE '%ambient%')) OR
                                 (? = 'happy' AND (t.title LIKE '%happy%' OR t.title LIKE '%joy%' OR t.title LIKE '%party%'))
                             )
                             ORDER BY mt.confidence DESC, RAND()
                             LIMIT ?");
        $stmt->execute([$mood, $mood, $mood, $mood, $mood, $limit]);
        $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    elseif ($type === 'activity') {
        // Генерация по активности
        $activity = $value ?: 'workout';
        $mood = 'energetic';
        
        $activityMap = [
            'workout' => 'energetic',
            'running' => 'energetic',
            'work' => 'focus',
            'study' => 'focus',
            'relax' => 'calm',
            'party' => 'happy',
            'sleep' => 'calm'
        ];
        
        $mood = $activityMap[$activity] ?? 'energetic';
        
        $stmt = $db->prepare("SELECT t.id, t.title, t.artist, t.album, t.duration, t.cover, t.file_path as src, COALESCE(t.video_url, '') AS video_url, t.explicit
                             FROM tracks t
                             LEFT JOIN mood_tags mt ON t.id = mt.track_id AND mt.mood = ?
                             WHERE mt.mood = ? OR (
                                 (? = 'energetic' AND (t.title LIKE '%rap%' OR t.title LIKE '%hip%' OR t.title LIKE '%rock%' OR t.title LIKE '%workout%')) OR
                                 (? = 'focus' AND (t.title LIKE '%focus%' OR t.title LIKE '%study%' OR t.title LIKE '%concentrate%')) OR
                                 (? = 'calm' AND (t.title LIKE '%lo-fi%' OR t.title LIKE '%chill%' OR t.title LIKE '%ambient%')) OR
                                 (? = 'happy' AND (t.title LIKE '%happy%' OR t.title LIKE '%joy%' OR t.title LIKE '%party%'))
                             )
                             ORDER BY mt.confidence DESC, RAND()
                             LIMIT ?");
        $stmt->execute([$mood, $mood, $mood, $mood, $mood, $mood, $limit]);
        $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    elseif ($type === 'weather') {
        // Генерация по погоде (эвристика)
        $weather = $value ?: 'sunny';
        $mood = 'happy';
        
        $weatherMap = [
            'sunny' => 'happy',
            'rainy' => 'calm',
            'cloudy' => 'sad',
            'snowy' => 'calm',
            'stormy' => 'energetic'
        ];
        
        $mood = $weatherMap[$weather] ?? 'happy';
        
        $stmt = $db->prepare("SELECT t.id, t.title, t.artist, t.album, t.duration, t.cover, t.file_path as src, COALESCE(t.video_url, '') AS video_url, t.explicit
                             FROM tracks t
                             LEFT JOIN mood_tags mt ON t.id = mt.track_id AND mt.mood = ?
                             WHERE mt.mood = ? OR (
                                 (? = 'energetic' AND (t.title LIKE '%rap%' OR t.title LIKE '%hip%' OR t.title LIKE '%rock%')) OR
                                 (? = 'calm' AND (t.title LIKE '%lo-fi%' OR t.title LIKE '%chill%' OR t.title LIKE '%ambient%' OR t.title LIKE '%rain%')) OR
                                 (? = 'sad' AND (t.title LIKE '%sad%' OR t.title LIKE '%depression%' OR t.title LIKE '%lonely%')) OR
                                 (? = 'happy' AND (t.title LIKE '%happy%' OR t.title LIKE '%joy%' OR t.title LIKE '%sun%'))
                             )
                             ORDER BY mt.confidence DESC, RAND()
                             LIMIT ?");
        $stmt->execute([$mood, $mood, $mood, $mood, $mood, $mood, $limit]);
        $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Если недостаточно треков, добавляем случайные
    if (count($tracks) < $limit) {
        $needed = $limit - count($tracks);
        $randomStmt = $db->prepare("SELECT t.id, t.title, t.artist, t.album, t.duration, t.cover, t.file_path as src, COALESCE(t.video_url, '') AS video_url, t.explicit
                                   FROM tracks t
                                   ORDER BY RAND()
                                   LIMIT ?");
        $randomStmt->execute([$needed]);
        $randomTracks = $randomStmt->fetchAll(PDO::FETCH_ASSOC);
        $tracks = array_merge($tracks, $randomTracks);
    }
    
    // Перемешиваем результаты
    shuffle($tracks);
    
    echo json_encode([
        'type' => $type,
        'value' => $value,
        'tracks' => array_slice($tracks, 0, $limit)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка сервера: ' . $e->getMessage()]);
}
?>

