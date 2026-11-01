<?php
// API для отслеживания прослушиваний треков
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не поддерживается']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверный формат данных']);
    exit;
}

$user_id = $_SESSION['user_id'];
// Принимаем track_id как число или строку (для временных ID)
$track_id_raw = $data['track_id'] ?? null;
$track_id = null;

if ($track_id_raw !== null) {
    // Если это число, используем его
    if (is_numeric($track_id_raw)) {
        $track_id = (int)$track_id_raw;
    } else {
        // Если это строка (временный ID), используем её как есть, но конвертируем в число для БД
        // Используем хеш строки для создания числового ID
        $track_id = abs(crc32($track_id_raw)) % 2147483647; // Ограничиваем до INT_MAX
    }
}

$action = $data['action'] ?? 'start'; // start, update, complete

if ($track_id === null) {
    http_response_code(400);
    echo json_encode(['error' => 'track_id обязателен']);
    exit;
}

$db = get_db_connection();

try {
    // Убеждаемся, что таблица play_history существует
$db->exec("CREATE TABLE IF NOT EXISTS play_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
        track_id INT NOT NULL,
        track_title VARCHAR(255) NOT NULL,
        track_artist VARCHAR(255) NOT NULL,
        album VARCHAR(255),
        duration INT,
    played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        play_duration INT DEFAULT 0 COMMENT 'Сколько секунд было прослушано',
        completed TINYINT(1) DEFAULT 0 COMMENT 'Был ли трек прослушан до конца',
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
        error_log('Failed to add completed column: ' . $e->getMessage());
    }
    
    // Получаем информацию о треке
    $trackStmt = $db->prepare('SELECT id, title, artist, album, duration FROM tracks WHERE id = ?');
    $trackStmt->execute([$track_id]);
    $track = $trackStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$track) {
        // Если трек не найден в БД, используем данные из запроса
        $track = [
            'id' => $track_id,
            'title' => $data['title'] ?? 'Unknown',
            'artist' => $data['artist'] ?? 'Unknown',
            'album' => $data['album'] ?? null,
            'duration' => $data['duration'] ?? 0
        ];
    }
    
    if ($action === 'start') {
        // Начало прослушивания - создаем запись в истории
        // Используем данные из запроса, если трек не найден в БД
        $trackTitle = $track['title'] ?? ($data['title'] ?? 'Unknown');
        $trackArtist = $track['artist'] ?? ($data['artist'] ?? 'Unknown');
        $trackAlbum = $track['album'] ?? ($data['album'] ?? null);
        $trackDuration = $track['duration'] ?? ($data['duration'] ?? 0);
        
              $stmt = $db->prepare('INSERT INTO play_history (user_id, track_id, track_title, track_artist, album, duration, play_duration, completed) VALUES (?, ?, ?, ?, ?, ?, 0, 0)');
              $stmt->execute([
                  $user_id,
                  $track_id,
                  $trackTitle,
                  $trackArtist,
                  $trackAlbum,
                  $trackDuration
              ]);
        $history_id = $db->lastInsertId();
        
        echo json_encode(['success' => true, 'history_id' => $history_id], JSON_UNESCAPED_UNICODE);
        
    } else if ($action === 'update') {
        // Обновление времени прослушивания
        $play_duration = isset($data['play_duration']) ? (int)$data['play_duration'] : 0;
        
        // Находим последнюю запись для этого трека
        $lastStmt = $db->prepare('SELECT id FROM play_history WHERE user_id = ? AND track_id = ? ORDER BY played_at DESC LIMIT 1');
        $lastStmt->execute([$user_id, $track_id]);
        $lastRecord = $lastStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lastRecord) {
            $updateStmt = $db->prepare('UPDATE play_history SET play_duration = ? WHERE id = ?');
            $updateStmt->execute([$play_duration, $lastRecord['id']]);
        }
        
        echo json_encode(['success' => true]);
        
    } else if ($action === 'complete') {
        // Трек прослушан до конца
        // Используем данные из запроса, если трек не найден в БД
        $trackTitle = $track['title'] ?? ($data['title'] ?? 'Unknown');
        $trackArtist = $track['artist'] ?? ($data['artist'] ?? 'Unknown');
        $trackAlbum = $track['album'] ?? ($data['album'] ?? null);
        $trackDuration = $track['duration'] ?? ($data['duration'] ?? 0);
        $play_duration = isset($data['play_duration']) ? (int)$data['play_duration'] : $trackDuration;
        
        // Обновляем последнюю запись
        $lastStmt = $db->prepare('SELECT id FROM play_history WHERE user_id = ? AND track_id = ? ORDER BY played_at DESC LIMIT 1');
        $lastStmt->execute([$user_id, $track_id]);
        $lastRecord = $lastStmt->fetch(PDO::FETCH_ASSOC);
        
              if ($lastRecord) {
                  $updateStmt = $db->prepare('UPDATE play_history SET play_duration = ?, completed = 1 WHERE id = ?');
                  $updateStmt->execute([$play_duration, $lastRecord['id']]);
              } else {
                  // Создаем новую запись если не было
                  $stmt = $db->prepare('INSERT INTO play_history (user_id, track_id, track_title, track_artist, album, duration, play_duration, completed) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
                  $stmt->execute([
                      $user_id,
                      $track_id,
                      $trackTitle,
                      $trackArtist,
                      $trackAlbum,
                      $trackDuration,
                      $play_duration
                  ]);
              }
        
        // Обновляем агрегированную статистику
        $trackForStats = [
            'title' => $trackTitle,
            'artist' => $trackArtist,
            'album' => $trackAlbum,
            'duration' => $trackDuration
        ];
        updateAggregatedStats($db, $user_id, $trackForStats);
        
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка сервера: ' . $e->getMessage()]);
}

// Функция для обновления агрегированной статистики
function updateAggregatedStats($db, $user_id, $track) {
    // Убеждаемся, что таблица listening_stats существует
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS listening_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            stat_type ENUM('artist', 'track', 'album', 'genre') NOT NULL,
            stat_name VARCHAR(255) NOT NULL,
            play_count INT DEFAULT 0,
            total_duration INT DEFAULT 0,
            last_played TIMESTAMP NULL,
            period_type ENUM('all', 'day', 'week', 'month', 'year') DEFAULT 'all',
            period_start DATE NULL,
            UNIQUE KEY unique_stat (user_id, stat_type, stat_name, period_type, period_start),
            INDEX idx_user_id (user_id),
            INDEX idx_stat_type (stat_type),
            INDEX idx_period (period_type, period_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Exception $e) {
        // Игнорируем ошибки создания таблицы
    }
    
    // Обновляем статистику по артисту
    $artistStmt = $db->prepare('INSERT INTO listening_stats (user_id, stat_type, stat_name, play_count, total_duration, last_played, period_type, period_start) 
                                VALUES (?, ?, ?, 1, ?, NOW(), ?, ?)
                                ON DUPLICATE KEY UPDATE 
                                play_count = play_count + 1, 
                                total_duration = total_duration + VALUES(total_duration),
                                last_played = NOW()');
    
    $duration = $track['duration'] ?? 0;
    $artist = $track['artist'] ?? 'Unknown';
    $title = $track['title'] ?? 'Unknown';
    $album = $track['album'] ?? null;
    
    // Общая статистика
    $artistStmt->execute([$user_id, 'artist', $artist, $duration, 'all', null]);
    
    // Статистика по треку
    $trackStmt = $db->prepare('INSERT INTO listening_stats (user_id, stat_type, stat_name, play_count, total_duration, last_played, period_type, period_start) 
                              VALUES (?, ?, ?, 1, ?, NOW(), ?, ?)
                              ON DUPLICATE KEY UPDATE 
                              play_count = play_count + 1, 
                              total_duration = total_duration + VALUES(total_duration),
                              last_played = NOW()');
    $trackStmt->execute([$user_id, 'track', $title, $duration, 'all', null]);
    
    // Статистика по альбому
    if (!empty($album)) {
        $albumStmt = $db->prepare('INSERT INTO listening_stats (user_id, stat_type, stat_name, play_count, total_duration, last_played, period_type, period_start) 
                                  VALUES (?, ?, ?, 1, ?, NOW(), ?, ?)
                                  ON DUPLICATE KEY UPDATE 
                                  play_count = play_count + 1, 
                                  total_duration = total_duration + VALUES(total_duration),
                                  last_played = NOW()');
        $albumStmt->execute([$user_id, 'album', $album, $duration, 'all', null]);
    }
    
    // Статистика за сегодня
    $today = date('Y-m-d');
    $artistStmt->execute([$user_id, 'artist', $artist, $duration, 'day', $today]);
    $trackStmt->execute([$user_id, 'track', $title, $duration, 'day', $today]);
    if (!empty($album)) {
        $albumStmt->execute([$user_id, 'album', $album, $duration, 'day', $today]);
    }
    
    // Статистика за неделю
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $artistStmt->execute([$user_id, 'artist', $artist, $duration, 'week', $weekStart]);
    $trackStmt->execute([$user_id, 'track', $title, $duration, 'week', $weekStart]);
    if (!empty($album)) {
        $albumStmt->execute([$user_id, 'album', $album, $duration, 'week', $weekStart]);
    }
    
    // Статистика за месяц
    $monthStart = date('Y-m-01');
    $artistStmt->execute([$user_id, 'artist', $artist, $duration, 'month', $monthStart]);
    $trackStmt->execute([$user_id, 'track', $title, $duration, 'month', $monthStart]);
    if (!empty($album)) {
        $albumStmt->execute([$user_id, 'album', $album, $duration, 'month', $monthStart]);
    }
}
?>