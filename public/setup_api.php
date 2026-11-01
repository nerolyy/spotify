<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../src/config/db.php';

$action = $_GET['action'] ?? '';

function getResponse($success, $data = [], $error = null) {
    $response = ['success' => $success];
    if ($data) $response = array_merge($response, $data);
    if ($error) $response['error'] = $error;
    return $response;
}

try {
    switch ($action) {
        case 'check_env':
            // Проверка PHP и подключения к БД
            try {
                $db = get_db_connection();
            } catch (Exception $e) {
                echo json_encode(getResponse(false, [], 'Ошибка подключения к БД: ' . $e->getMessage()));
                exit;
            }
            $version = $db->query('SELECT VERSION()')->fetchColumn();
            
            echo json_encode(getResponse(true, [
                'php_version' => PHP_VERSION,
                'mysql_version' => $version,
                'message' => 'Окружение готово к работе'
            ]));
            break;
            
        case 'init_db':
            // Инициализация БД (аналог setup_db.php)
            try {
                $db = get_db_connection();
            } catch (Exception $e) {
                echo json_encode(getResponse(false, [], 'Ошибка подключения к БД: ' . $e->getMessage()));
                exit;
            }
            $response = ['success' => false, 'executed' => [], 'health' => []];
            
            // Проверяем существование таблиц
            $stmt = $db->query("SHOW TABLES LIKE 'tracks'");
            $tracksTableExists = $stmt->fetch() !== false;
        
            if (!$tracksTableExists) {
                // Применяем schema.sql
                $schemaSql = file_get_contents(__DIR__ . '/../../db/schema.sql');
                if ($schemaSql) {
                    $db->exec($schemaSql);
                    $response['executed'][] = 'schema.sql применен';
                }
            }
        
            // Убеждаемся что нужные колонки существуют
            try { 
                $db->exec("ALTER TABLE tracks ADD COLUMN video_url VARCHAR(500) NULL"); 
                $response['executed'][] = 'Добавлена колонка video_url'; 
            } catch (Throwable $e) { /* игнорируем если существует */ }
            
            try { 
                $db->exec("ALTER TABLE tracks ADD COLUMN explicit TINYINT(1) NOT NULL DEFAULT 0"); 
                $response['executed'][] = 'Добавлена колонка explicit'; 
            } catch (Throwable $e) { /* игнорируем если существует */ }
            
            try { 
                $db->exec("CREATE TABLE IF NOT EXISTS artists (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL UNIQUE, cover VARCHAR(255), bio TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)"); 
                $response['executed'][] = 'Создана таблица artists'; 
            } catch (Throwable $e) { /* игнорируем если существует */ }
        
            $response['success'] = true;
            $response['health']['tracks_count'] = $db->query("SELECT COUNT(*) FROM tracks")->fetchColumn();
            $response['health']['artists_count'] = $db->query("SELECT COUNT(*) FROM artists")->fetchColumn();
            
            echo json_encode($response);
            break;
            
        case 'import_data':
            // Импорт данных из data/changes/latest.json
            $importFile = __DIR__ . '/../data/changes/latest.json';
            
            if (!file_exists($importFile)) {
                echo json_encode(getResponse(false, [], 'Файл data/changes/latest.json не найден. Сначала экспортируйте данные на исходном устройстве.'));
                break;
            }
            
            try {
                $db = get_db_connection();
            } catch (Exception $e) {
                echo json_encode(getResponse(false, [], 'Ошибка подключения к БД: ' . $e->getMessage()));
                exit;
            }
            $data = json_decode(file_get_contents($importFile), true);
            
            if (!$data || !isset($data['tracks'])) {
                echo json_encode(getResponse(false, [], 'Неверный формат файла экспорта'));
                break;
            }
            
            $db->beginTransaction();
            $imported = ['tracks' => 0, 'artists' => 0];
            
            // Импорт треков
            $insertTrack = $db->prepare("
                INSERT INTO tracks (id, title, artist, album, album_type, duration, file_path, cover, video_url, explicit)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    title = VALUES(title),
                    artist = VALUES(artist),
                    album = VALUES(album),
                    album_type = VALUES(album_type),
                    duration = VALUES(duration),
                    file_path = VALUES(file_path),
                    cover = VALUES(cover),
                    video_url = VALUES(video_url),
                    explicit = VALUES(explicit)
            ");
            
            foreach ($data['tracks'] as $track) {
                $insertTrack->execute([
                    $track['id'],
                    $track['title'],
                    $track['artist'],
                    $track['album'],
                    $track['album_type'] ?? '',
                    $track['duration'] ?? 0,
                    $track['file_path'],
                    $track['cover'],
                    $track['video_url'] ?? '',
                    $track['explicit'] ?? 0
                ]);
                $imported['tracks']++;
            }
            
            // Импорт артистов
            if (isset($data['artists'])) {
                $insertArtist = $db->prepare("
                    INSERT INTO artists (id, name, cover, bio)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        name = VALUES(name),
                        cover = VALUES(cover),
                        bio = VALUES(bio)
                ");
                
                foreach ($data['artists'] as $artist) {
                    $insertArtist->execute([
                        $artist['id'],
                        $artist['name'],
                        $artist['cover'] ?? '',
                        $artist['bio'] ?? ''
                    ]);
                    $imported['artists']++;
                }
            }
            
            $db->commit();
            echo json_encode(getResponse(true, ['imported' => $imported]));
            break;
            
        case 'check_health':
            // Проверка работоспособности
            try {
                $db = get_db_connection();
            } catch (Exception $e) {
                echo json_encode(getResponse(false, [], 'Ошибка подключения к БД: ' . $e->getMessage()));
                exit;
            }
            
            $tracksCount = $db->query("SELECT COUNT(*) FROM tracks")->fetchColumn();
            $artistsCount = $db->query("SELECT COUNT(*) FROM artists")->fetchColumn();
            
            // Проверяем API
            $apiTest = file_get_contents('http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/src/api/home.php');
            $apiWorks = $apiTest && strpos($apiTest, '"tracks"') !== false;
            
            echo json_encode(getResponse(true, [
                'health' => [
                    'tracks_count' => $tracksCount,
                    'artists_count' => $artistsCount,
                    'api_works' => $apiWorks
                ]
            ]));
            break;
            
        default:
            echo json_encode(getResponse(false, [], 'Неизвестное действие'));
    }
    
} catch (Throwable $e) {
    echo json_encode(getResponse(false, [], $e->getMessage()));
}
?>
