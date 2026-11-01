<?php
/**
 * API для массовой загрузки треков через веб-интерфейс
 * Автоматически извлекает метаданные из MP3
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__ . '/../config/db.php';

// Проверка getID3
$getID3_path = __DIR__ . '/../../vendor/james-heinrich/getid3/getid3/getid3.php';
if (!file_exists($getID3_path)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'getID3 не установлен']);
    exit;
}
require_once $getID3_path;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Только POST']);
    exit;
}

if (empty($_FILES['files'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Файлы не загружены']);
    exit;
}

$db = get_db_connection();
$getID3 = new getID3;

$files = $_FILES['files'];
$uploadDir = __DIR__ . '/../../tracks/music/';
$coversDir = __DIR__ . '/../../tracks/covers/';

// Создаём папки если нет
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
if (!is_dir($coversDir)) mkdir($coversDir, 0755, true);

$db->beginTransaction();

$added = 0;
$skipped = 0;
$errors = 0;
$batch = [];

// Обрабатываем файлы
$fileCount = is_array($files['name']) ? count($files['name']) : 1;

for ($i = 0; $i < $fileCount; $i++) {
    $fileName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
    $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
    $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
    
    if ($error !== UPLOAD_ERR_OK) {
        $errors++;
        continue;
    }
    
    // Проверяем тип файла
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $tmpName);
    finfo_close($finfo);
    
    if ($mimeType !== 'audio/mpeg' && !preg_match('/\.mp3$/i', $fileName)) {
        $errors++;
        continue;
    }
    
    // Перемещаем файл
    $safeFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
    $targetPath = $uploadDir . $safeFileName;
    
    if (!move_uploaded_file($tmpName, $targetPath)) {
        $errors++;
        continue;
    }
    
    $relativePath = 'tracks/music/' . $safeFileName;
    
    // Проверяем, не добавлен ли уже
    $checkStmt = $db->prepare('SELECT id FROM tracks WHERE file_path = ?');
    $checkStmt->execute([$relativePath]);
    if ($checkStmt->fetch()) {
        $skipped++;
        continue;
    }
    
    try {
        // Извлекаем метаданные
        $fileInfo = $getID3->analyze($targetPath);
        getid3_lib::CopyTagsToComments($fileInfo);
        
        $title = '';
        $artist = '';
        $album = '';
        $duration = 0;
        $cover = 'tracks/covers/placeholder.jpg';
        
        // Title
        if (!empty($fileInfo['tags']['id3v2']['title'][0])) {
            $title = trim($fileInfo['tags']['id3v2']['title'][0]);
        } elseif (!empty($fileInfo['tags']['id3v1']['title'][0])) {
            $title = trim($fileInfo['tags']['id3v1']['title'][0]);
        } else {
            $title = pathinfo($safeFileName, PATHINFO_FILENAME);
        }
        
        // Artist
        if (!empty($fileInfo['tags']['id3v2']['artist'][0])) {
            $artist = trim($fileInfo['tags']['id3v2']['artist'][0]);
        } elseif (!empty($fileInfo['tags']['id3v1']['artist'][0])) {
            $artist = trim($fileInfo['tags']['id3v1']['artist'][0]);
        } else {
            $artist = 'Неизвестный артист';
        }
        
        // Album
        if (!empty($fileInfo['tags']['id3v2']['album'][0])) {
            $album = trim($fileInfo['tags']['id3v2']['album'][0]);
        } elseif (!empty($fileInfo['tags']['id3v1']['album'][0])) {
            $album = trim($fileInfo['tags']['id3v1']['album'][0]);
        } else {
            $album = 'Без альбома';
        }
        
        // Duration
        if (isset($fileInfo['playtime_seconds'])) {
            $duration = (int)round($fileInfo['playtime_seconds']);
        }
        
        // Cover
        if (!empty($fileInfo['comments']['picture'][0]['data'])) {
            $coverData = $fileInfo['comments']['picture'][0]['data'];
            $coverExt = 'jpg';
            if (!empty($fileInfo['comments']['picture'][0]['image_mime'])) {
                $mime = $fileInfo['comments']['picture'][0]['image_mime'];
                if (strpos($mime, 'png') !== false) $coverExt = 'png';
            }
            $coverFileName = md5($targetPath) . '.' . $coverExt;
            $coverPath = $coversDir . $coverFileName;
            file_put_contents($coverPath, $coverData);
            $cover = 'tracks/covers/' . $coverFileName;
        }
        
        // Добавляем в пакет
        $batch[] = [
            'title' => $title,
            'artist' => $artist,
            'album' => $album,
            'duration' => $duration,
            'file_path' => $relativePath,
            'cover' => $cover,
            'album_type' => 'album'
        ];
        
    } catch (Exception $e) {
        $errors++;
        error_log("Error processing $fileName: " . $e->getMessage());
    }
}

// Пакетная вставка
if (!empty($batch)) {
    $values = [];
    $params = [];
    
    foreach ($batch as $track) {
        $values[] = '(?, ?, ?, ?, ?, ?, ?, NOW())';
        $params[] = $track['title'];
        $params[] = $track['artist'];
        $params[] = $track['album'];
        $params[] = $track['album_type'];
        $params[] = $track['duration'];
        $params[] = $track['file_path'];
        $params[] = $track['cover'];
    }
    
    $sql = 'INSERT INTO tracks (title, artist, album, album_type, duration, file_path, cover, created_at) VALUES ' . implode(', ', $values);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $added = count($batch);
}

$db->commit();

echo json_encode([
    'success' => true,
    'added' => $added,
    'skipped' => $skipped,
    'errors' => $errors,
    'total' => $fileCount
]);







