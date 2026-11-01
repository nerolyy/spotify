<?php
// Включаем отображение ошибок для отладки
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/src/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Метод не разрешен']);
    exit;
}

try {
    // Получаем данные из POST запроса
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Неверные данные');
    }
    
    // Валидация обязательных полей
    $required_fields = ['album_name', 'artist_name', 'album_type', 'selectedCover', 'tracks'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            throw new Exception("Поле {$field} обязательно для заполнения");
        }
    }
    
    if (empty($data['tracks']) || !is_array($data['tracks'])) {
        throw new Exception('Добавьте хотя бы один трек');
    }
    
    $db = get_db_connection();
    
    // Начинаем транзакцию
    $db->beginTransaction();
    
    $album_name = $data['album_name'];
    $artist_name = $data['artist_name'];
    $album_type = $data['album_type'];
    $cover_image = trim($data['selectedCover'] ?? '');
    $release_year = $data['release_year'] ?? date('Y');
    $description = $data['description'] ?? '';
    
    // Если обложка не указана, пытаемся найти её в треках
    if (empty($cover_image) && !empty($data['tracks'])) {
        foreach ($data['tracks'] as $track) {
            if (!empty($track['cover']) && trim($track['cover'])) {
                $cover_image = trim($track['cover']);
                break;
            }
        }
    }
    
    // Логируем обложку для отладки
    error_log("Album cover: " . ($cover_image ?: 'EMPTY'));
    
    $inserted_tracks = 0;
    $total_duration = 0;
    
    // Проверяем и добавляем расширенные поля в таблицу (если их нет)
    $columns_to_add = [
        'track_number' => 'INT DEFAULT NULL',
        'disc_number' => 'INT DEFAULT NULL',
        'genre' => 'VARCHAR(255) DEFAULT NULL',
        'year' => 'INT DEFAULT NULL',
        'preview_url' => 'TEXT DEFAULT NULL',
        'collection_id' => 'VARCHAR(255) DEFAULT NULL'
    ];
    
    foreach ($columns_to_add as $column => $definition) {
        try {
            // Проверяем существование колонки
            $check = $db->query("SHOW COLUMNS FROM tracks LIKE '$column'");
            if ($check->rowCount() == 0) {
                $db->exec("ALTER TABLE tracks ADD COLUMN $column $definition");
            }
        } catch (PDOException $e) {
            // Игнорируем ошибки если колонка уже существует или другая ошибка
        }
    }
    
    // Добавляем каждый трек с расширенными метаданными
    foreach ($data['tracks'] as $track) {
        
        $sql = "INSERT INTO tracks (title, artist, album, album_type, duration, file_path, cover, explicit, track_number, disc_number, genre, year, preview_url, collection_id, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $db->prepare($sql);
        $duration = isset($track['duration']) ? intval($track['duration']) : 0;
        $file_path = isset($track['file_path']) ? $track['file_path'] : 'tracks/music/placeholder.mp3';
        $explicit = isset($track['explicit']) ? intval($track['explicit']) : 0;
        $trackNumber = isset($track['trackNumber']) ? intval($track['trackNumber']) : null;
        $discNumber = isset($track['discNumber']) ? intval($track['discNumber']) : null;
        $genre = isset($track['genre']) ? $track['genre'] : null;
        $year = isset($track['year']) && $track['year'] ? intval($track['year']) : null;
        $previewUrl = isset($track['previewUrl']) ? $track['previewUrl'] : null;
        $collectionId = isset($track['collectionId']) ? $track['collectionId'] : null;
        
        // Используем обложку трека, если она есть, иначе обложку альбома
        $track_cover = !empty($track['cover']) && trim($track['cover']) ? trim($track['cover']) : $cover_image;
        
        try {
            $result = $stmt->execute([
                $track['title'],
                $artist_name,
                $album_name,
                $album_type,
                $duration,
                $file_path,
                $track_cover, // Используем обложку трека или альбома
                $explicit,
                $trackNumber,
                $discNumber,
                $genre,
                $year,
                $previewUrl,
                $collectionId
            ]);
            
            if ($result) {
                $inserted_tracks++;
                $total_duration += $duration;
            }
        } catch (PDOException $e) {
            // Игнорируем ошибки дублирования (если трек уже существует)
            if (strpos($e->getMessage(), 'Duplicate') === false && strpos($e->getMessage(), 'UNIQUE') === false) {
                throw new Exception("Ошибка при добавлении трека '{$track['title']}': " . $e->getMessage());
            }
        }
    }
    
    // Подтверждаем транзакцию
    $db->commit();
    
    // Форматируем продолжительность
    $hours = floor($total_duration / 3600);
    $minutes = floor(($total_duration % 3600) / 60);
    $seconds = $total_duration % 60;
    
    $formatted_duration = '';
    if ($hours > 0) {
        $formatted_duration .= $hours . 'ч ';
    }
    $formatted_duration .= $minutes . 'м ' . $seconds . 'с';
    
    echo json_encode([
        'success' => true,
        'message' => 'Альбом успешно добавлен',
        'data' => [
            'album_name' => $album_name,
            'artist_name' => $artist_name,
            'album_type' => $album_type,
            'tracks_count' => $inserted_tracks,
            'total_duration' => $formatted_duration,
            'cover' => $cover_image
        ]
    ]);
    
} catch (Exception $e) {
    // Откатываем транзакцию в случае ошибки
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_details' => $e->getTraceAsString()
    ]);
} catch (PDOException $e) {
    // Откатываем транзакцию в случае ошибки БД
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка базы данных: ' . $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
}
?>






















