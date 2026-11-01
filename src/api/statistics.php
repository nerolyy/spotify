<?php
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

$db = get_db_connection();
$user_id = $_SESSION['user_id'];

// Период: week, month, year, all
$period = isset($_GET['period']) ? $_GET['period'] : 'month';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

// Определяем дату начала периода
$dateFilter = '';
switch ($period) {
    case 'week':
        $dateFilter = "AND played_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $dateFilter = "AND played_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    case 'year':
        $dateFilter = "AND played_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
        break;
    case 'all':
    default:
        $dateFilter = '';
        break;
}

// Топ артистов
$stmt = $db->prepare("
    SELECT 
        track_artist as artist,
        COUNT(*) as play_count,
        SUM(duration) as total_duration
    FROM play_history 
    WHERE user_id = ? AND track_artist IS NOT NULL AND track_artist != '' $dateFilter
    GROUP BY track_artist 
    ORDER BY play_count DESC 
    LIMIT ?
");
$stmt->execute([$user_id, $limit]);
$top_artists = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Топ треков
$stmt = $db->prepare("
    SELECT 
        track_id,
        track_title as title,
        track_artist as artist,
        track_album as album,
        COUNT(*) as play_count,
        SUM(duration) as total_duration
    FROM play_history 
    WHERE user_id = ? AND track_title IS NOT NULL AND track_title != '' $dateFilter
    GROUP BY track_id, track_title, track_artist, track_album
    ORDER BY play_count DESC 
    LIMIT ?
");
$stmt->execute([$user_id, $limit]);
$top_tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Топ альбомов
$stmt = $db->prepare("
    SELECT 
        track_album as album,
        track_artist as artist,
        COUNT(*) as play_count,
        SUM(duration) as total_duration
    FROM play_history 
    WHERE user_id = ? AND track_album IS NOT NULL AND track_album != '' $dateFilter
    GROUP BY track_album, track_artist
    ORDER BY play_count DESC 
    LIMIT ?
");
$stmt->execute([$user_id, $limit]);
$top_albums = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Общее время прослушивания (в секундах)
$stmt = $db->prepare("
    SELECT 
        SUM(duration) as total_duration,
        COUNT(*) as total_plays,
        COUNT(DISTINCT track_id) as unique_tracks,
        COUNT(DISTINCT track_artist) as unique_artists
    FROM play_history 
    WHERE user_id = ? $dateFilter
");
$stmt->execute([$user_id]);
$total_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// График активности по дням (последние 30 дней)
$stmt = $db->prepare("
    SELECT 
        DATE(played_at) as date,
        COUNT(*) as play_count,
        SUM(duration) as total_duration
    FROM play_history 
    WHERE user_id = ? AND played_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(played_at)
    ORDER BY date ASC
");
$stmt->execute([$user_id]);
$daily_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

// График активности по часам (среднее за период)
$stmt = $db->prepare("
    SELECT 
        HOUR(played_at) as hour,
        COUNT(*) as play_count
    FROM play_history 
    WHERE user_id = ? $dateFilter
    GROUP BY HOUR(played_at)
    ORDER BY hour ASC
");
$stmt->execute([$user_id]);
$hourly_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Форматируем общее время
$total_duration = (int)($total_stats['total_duration'] ?? 0);
$hours = floor($total_duration / 3600);
$minutes = floor(($total_duration % 3600) / 60);

echo json_encode([
    'period' => $period,
    'top_artists' => $top_artists,
    'top_tracks' => $top_tracks,
    'top_albums' => $top_albums,
    'total_stats' => [
        'total_duration' => $total_duration,
        'total_duration_formatted' => $hours . ' ч ' . $minutes . ' мин',
        'total_plays' => (int)($total_stats['total_plays'] ?? 0),
        'unique_tracks' => (int)($total_stats['unique_tracks'] ?? 0),
        'unique_artists' => (int)($total_stats['unique_artists'] ?? 0),
    ],
    'daily_activity' => $daily_activity,
    'hourly_activity' => $hourly_activity,
], JSON_UNESCAPED_UNICODE);
?>

