<?php
require_once __DIR__ . '/../../../src/config/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Подключение к базе данных
    $pdo = get_db_connection();
    
    // Получаем количество треков для автоплея (по умолчанию 20)
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $limit = max(1, min($limit, 100)); // Ограничиваем от 1 до 100
    
    // Получаем случайные треки с фитами
    $query = "
        SELECT 
            t.id,
            t.title,
            t.artist,
            t.file_path,
            t.cover,
            t.duration,
            t.explicit,
            t.video_url,
            GROUP_CONCAT(ta.artist, ', ') as feats
        FROM tracks t
        LEFT JOIN track_artists ta ON t.id = ta.track_id AND ta.role = 'featured'
        WHERE t.file_path IS NOT NULL 
        AND t.file_path != ''
        GROUP BY t.id
        ORDER BY RAND()
        LIMIT :limit
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $tracks = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Нормализуем пути
        $filePath = $row['file_path'];
        if (!empty($filePath) && !str_starts_with($filePath, '/')) {
            $filePath = '/muzic2/' . ltrim($filePath, '/');
        }
        
        $cover = $row['cover'];
        if (!empty($cover) && !str_starts_with($cover, '/') && !str_starts_with($cover, 'http')) {
            $cover = '/muzic2/' . ltrim($cover, '/');
        }
        
        $videoUrl = $row['video_url'];
        if (!empty($videoUrl) && !str_starts_with($videoUrl, '/') && !str_starts_with($videoUrl, 'http')) {
            $videoUrl = '/muzic2/' . ltrim($videoUrl, '/');
        }
        
        $tracks[] = [
            'id' => (int)$row['id'],
            'title' => $row['title'] ?: 'Без названия',
            'artist' => $row['artist'] ?: 'Неизвестный артист',
            'feats' => $row['feats'] ?: '',
            'src' => $filePath,
            'file_path' => $filePath,
            'cover' => $cover ?: '/muzic2/tracks/covers/placeholder.jpg',
            'duration' => (int)$row['duration'],
            'explicit' => (bool)$row['explicit'],
            'video_url' => $videoUrl ?: ''
        ];
    }
    
    echo json_encode([
        'success' => true,
        'tracks' => $tracks,
        'count' => count($tracks)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка получения случайных треков: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
