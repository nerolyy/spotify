<?php
// API для получения статистики прослушиваний
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
$period = $_GET['period'] ?? 'all'; // all, day, week, month, year
$type = $_GET['type'] ?? 'all'; // all, artists, tracks, albums, genres
$limit = (int)($_GET['limit'] ?? 10);

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
    
    // Проверяем и добавляем колонку completed, если её нет
    try {
        $checkCompleted = $db->query("SHOW COLUMNS FROM play_history LIKE 'completed'");
        if ($checkCompleted->rowCount() == 0) {
            $db->exec("ALTER TABLE play_history ADD COLUMN completed TINYINT(1) DEFAULT 0 AFTER play_duration");
        }
    } catch (Exception $e) {
        // Игнорируем ошибки
    }
    
    // Проверяем и добавляем колонки, если их нет
    try {
        $checkColumn = $db->query("SHOW COLUMNS FROM play_history LIKE 'play_duration'");
        if ($checkColumn->rowCount() == 0) {
            $db->exec("ALTER TABLE play_history ADD COLUMN play_duration INT DEFAULT 0 AFTER duration");
        }
        
        // Проверяем колонку album
        $checkAlbum = $db->query("SHOW COLUMNS FROM play_history LIKE 'album'");
        if ($checkAlbum->rowCount() == 0) {
            $db->exec("ALTER TABLE play_history ADD COLUMN album VARCHAR(255) AFTER track_artist");
        }
    } catch (Exception $e) {
        // Игнорируем ошибки при проверке/добавлении колонок
    }
    
    $result = [];
    
    // Определяем период
    $dateFilter = '';
    if ($period === 'day') {
        $dateFilter = "AND DATE(played_at) = CURDATE()";
    } else if ($period === 'week') {
        $dateFilter = "AND played_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    } else if ($period === 'month') {
        $dateFilter = "AND played_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    } else if ($period === 'year') {
        $dateFilter = "AND played_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
    }
    
    // Топ артистов
    if ($type === 'all' || $type === 'artists') {
        // Безопасно встраиваем limit, так как он уже приведен к int
        $limitSafe = (int)$limit;
        // Только артисты, которые существуют в основной таблице artists
        $stmt = $db->prepare("SELECT 
            a.name as name,
            COUNT(*) as play_count,
            COALESCE(SUM(ph.play_duration), 0) as total_duration,
            MAX(ph.played_at) as last_played,
            MAX(COALESCE(NULLIF(a.cover, ''), 'tracks/covers/placeholder.jpg')) as cover
        FROM play_history ph
        INNER JOIN artists a ON a.name = ph.track_artist
        INNER JOIN tracks t ON t.id = ph.track_id
        WHERE ph.user_id = ? $dateFilter
        GROUP BY a.name
        ORDER BY play_count DESC, total_duration DESC
        LIMIT $limitSafe");
        $stmt->execute([$user_id]);
        $artists = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result['artists'] = $artists;
    }
    
    // Топ треков
    if ($type === 'all' || $type === 'tracks') {
        // Безопасно встраиваем limit, так как он уже приведен к int
        $limitSafe = (int)$limit;
        // Проверяем наличие колонки album перед использованием
        $hasAlbumColumn = false;
        try {
            $checkAlbum = $db->query("SHOW COLUMNS FROM play_history LIKE 'album'");
            $hasAlbumColumn = ($checkAlbum->rowCount() > 0);
        } catch (Exception $e) {
            $hasAlbumColumn = false;
        }
        
        $albumField = $hasAlbumColumn ? 'ph.album' : 'NULL as album';
        $groupByAlbum = $hasAlbumColumn ? ', ph.album' : '';
        
        // Сначала проверяем, сколько всего записей в play_history
        $countStmt = $db->prepare("SELECT COUNT(*) as total FROM play_history WHERE user_id = ? $dateFilter");
        $countStmt->execute([$user_id]);
        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        error_log("Stats API: Total play_history records for user $user_id period $period: $totalCount");
        
        // Получаем все уникальные треки с правильной агрегацией
        // Группируем только по title и artist, чтобы не терять треки
        $stmt = $db->prepare("SELECT 
            ph.track_title as title,
            ph.track_artist as artist,
            MAX($albumField) as album,
            COUNT(*) as play_count,
            COALESCE(SUM(ph.play_duration), 0) as total_duration,
            MAX(ph.played_at) as last_played,
            MAX(COALESCE(t.duration, ph.duration, 0)) as duration,
            MAX(t.cover) as cover,
            MAX(COALESCE(NULLIF(t.file_path, ''), '')) as file_path,
            MAX(COALESCE(t.video_url, '')) as video_url,
            MAX(COALESCE(t.explicit, 0)) as explicit,
            MIN(ph.track_id) as track_id
        FROM play_history ph
        INNER JOIN tracks t ON ph.track_id = t.id
        WHERE ph.user_id = ? $dateFilter
        GROUP BY ph.track_title, ph.track_artist
        ORDER BY play_count DESC, total_duration DESC
        LIMIT $limitSafe");
        $stmt->execute([$user_id]);
        $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Дополнительно пытаемся получить обложки для треков, у которых их нет
        // Сначала пытаемся найти обложку по track_id, потом по альбому
        foreach ($tracks as &$track) {
            // Если обложки нет или она пустая
            if (empty($track['cover']) || $track['cover'] === 'tracks/covers/placeholder.jpg' || trim($track['cover']) === '') {
                // Сначала пытаемся найти обложку по track_id
                if (!empty($track['track_id'])) {
                    $trackStmt = $db->prepare("SELECT cover FROM tracks WHERE id = ? AND cover IS NOT NULL AND cover != '' LIMIT 1");
                    $trackStmt->execute([$track['track_id']]);
                    $trackCover = $trackStmt->fetchColumn();
                    if ($trackCover) {
                        $track['cover'] = $trackCover;
                    }
                }
                
                // Если всё ещё нет обложки, пытаемся найти по названию трека и артисту
                if (($track['cover'] === 'tracks/covers/placeholder.jpg' || empty($track['cover']) || trim($track['cover']) === '') && !empty($track['title']) && !empty($track['artist'])) {
                    $titleStmt = $db->prepare("SELECT cover FROM tracks WHERE title = ? AND artist = ? AND cover IS NOT NULL AND cover != '' LIMIT 1");
                    $titleStmt->execute([$track['title'], $track['artist']]);
                    $titleCover = $titleStmt->fetchColumn();
                    if ($titleCover) {
                        $track['cover'] = $titleCover;
                    }
                }
                
                // Если всё ещё нет обложки, пытаемся найти по альбому
                if (($track['cover'] === 'tracks/covers/placeholder.jpg' || empty($track['cover']) || trim($track['cover']) === '') && !empty($track['album'])) {
                    $albumStmt = $db->prepare("SELECT cover FROM tracks WHERE album = ? AND cover IS NOT NULL AND cover != '' LIMIT 1");
                    $albumStmt->execute([$track['album']]);
                    $albumCover = $albumStmt->fetchColumn();
                    if ($albumCover) {
                        $track['cover'] = $albumCover;
                    }
                }
                
                // Если всё ещё нет обложки, используем placeholder
                if (empty($track['cover']) || trim($track['cover']) === '') {
                    $track['cover'] = 'tracks/covers/placeholder.jpg';
                }
            }
        }
        unset($track);
        
        // Логируем количество треков для отладки
        error_log('Stats API: Found ' . count($tracks) . ' tracks for user ' . $user_id . ' period ' . $period);
        
        $result['tracks'] = $tracks;
    }
    
    // Топ альбомов
    if ($type === 'all' || $type === 'albums') {
        // Безопасно встраиваем limit, так как он уже приведен к int
        $limitSafe = (int)$limit;
        $stmt = $db->prepare("SELECT 
            ph.album as title,
            ph.track_artist as artist,
            COUNT(*) as play_count,
            COALESCE(SUM(ph.play_duration), 0) as total_duration,
            MAX(ph.played_at) as last_played
        FROM play_history ph
        INNER JOIN tracks t ON ph.track_id = t.id
        WHERE ph.user_id = ? AND ph.album IS NOT NULL AND ph.album != '' $dateFilter
        GROUP BY ph.album, ph.track_artist
        ORDER BY play_count DESC, total_duration DESC
        LIMIT $limitSafe");
        $stmt->execute([$user_id]);
        $result['albums'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Общее время прослушивания
    $stmt = $db->prepare("SELECT 
        COALESCE(SUM(ph.play_duration), 0) as total_seconds,
        COUNT(*) as total_plays,
        COUNT(DISTINCT ph.track_id) as unique_tracks,
        COUNT(DISTINCT ph.track_artist) as unique_artists
    FROM play_history ph
    INNER JOIN tracks t ON ph.track_id = t.id
    WHERE ph.user_id = ? $dateFilter");
    $stmt->execute([$user_id]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $result['summary'] = [
        'total_time' => (int)($summary['total_seconds'] ?? 0),
        'total_plays' => (int)($summary['total_plays'] ?? 0),
        'unique_tracks' => (int)($summary['unique_tracks'] ?? 0),
        'unique_artists' => (int)($summary['unique_artists'] ?? 0)
    ];
    
    // График активности по дням (последние 30 дней)
    $stmt = $db->prepare("SELECT 
        DATE(ph.played_at) as date,
        COUNT(*) as plays,
        COALESCE(SUM(ph.play_duration), 0) as duration
    FROM play_history ph
    INNER JOIN tracks t ON ph.track_id = t.id
    WHERE ph.user_id = ? AND ph.played_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(ph.played_at)
    ORDER BY date ASC");
    $stmt->execute([$user_id]);
    $result['activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
      } catch (Exception $e) {
          http_response_code(500);
          error_log('Stats API Error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
          echo json_encode(['error' => 'Ошибка сервера: ' . $e->getMessage(), 'trace' => $e->getTraceAsString()]);
      }
?>