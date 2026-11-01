<?php
/**
 * БЫСТРОЕ МАССОВОЕ ДОБАВЛЕНИЕ ТРЕКОВ
 * 
 * Использование:
 * 1. Поместите MP3 файлы в папку tracks/music/
 * 2. Откройте этот файл в браузере
 * 3. Или используйте через CLI: php bulk_add_tracks.php [путь_к_папке]
 * 
 * Автоматически извлекает метаданные из MP3 файлов
 */

require_once __DIR__ . '/src/config/db.php';
require_once __DIR__ . '/src/api/music_metadata.php';

// Проверка наличия getID3 (опционально, можно использовать только API)
$getID3_path = __DIR__ . '/vendor/james-heinrich/getid3/getid3/getid3.php';
$useGetID3 = file_exists($getID3_path);
if ($useGetID3) {
    require_once $getID3_path;
} else {
    echo "⚠️  getID3 не найден. Будет использоваться только API для метаданных.\n";
    echo "   Установите: composer require james-heinrich/getid3\n\n";
}

$db = get_db_connection();
$getID3 = $useGetID3 ? new getID3 : null;

// Получаем путь к папке
$musicDir = $argv[1] ?? __DIR__ . '/tracks/music/';
$musicDir = rtrim($musicDir, '/') . '/';

if (!is_dir($musicDir)) {
    die("❌ Папка не найдена: $musicDir\n");
}

echo "🚀 Начинаю массовое добавление треков из: $musicDir\n\n";

// Находим все MP3 файлы
$files = glob($musicDir . '*.mp3');
$totalFiles = count($files);

if ($totalFiles === 0) {
    die("❌ MP3 файлы не найдены в папке: $musicDir\n");
}

echo "📁 Найдено файлов: $totalFiles\n\n";

// Начинаем транзакцию для быстрой вставки
$db->beginTransaction();

$added = 0;
$skipped = 0;
$errors = 0;
$batchSize = 50; // Размер пакета для вставки
$batch = [];

foreach ($files as $index => $filePath) {
    $relativePath = str_replace(__DIR__ . '/', '', $filePath);
    $fileName = basename($filePath);
    
    echo "[" . ($index + 1) . "/$totalFiles] Обработка: $fileName\n";
    
    try {
        // Проверяем, не добавлен ли уже трек
        $checkStmt = $db->prepare('SELECT id FROM tracks WHERE file_path = ? OR file_path = ?');
        $checkStmt->execute([$relativePath, $filePath]);
        if ($checkStmt->fetch()) {
            echo "  ⚠️  Пропущен (уже в базе)\n";
            $skipped++;
            continue;
        }
        
        // Извлекаем данные из файла (если getID3 доступен)
        $title = '';
        $artist = '';
        $album = '';
        $duration = 0;
        $cover = '';
        
        if ($useGetID3) {
            $fileInfo = $getID3->analyze($filePath);
            getid3_lib::CopyTagsToComments($fileInfo);
            
            // Title
            if (!empty($fileInfo['tags']['id3v2']['title'][0])) {
                $title = trim($fileInfo['tags']['id3v2']['title'][0]);
            } elseif (!empty($fileInfo['tags']['id3v1']['title'][0])) {
                $title = trim($fileInfo['tags']['id3v1']['title'][0]);
            }
            
            // Artist
            if (!empty($fileInfo['tags']['id3v2']['artist'][0])) {
                $artist = trim($fileInfo['tags']['id3v2']['artist'][0]);
            } elseif (!empty($fileInfo['tags']['id3v1']['artist'][0])) {
                $artist = trim($fileInfo['tags']['id3v1']['artist'][0]);
            }
            
            // Album
            if (!empty($fileInfo['tags']['id3v2']['album'][0])) {
                $album = trim($fileInfo['tags']['id3v2']['album'][0]);
            } elseif (!empty($fileInfo['tags']['id3v1']['album'][0])) {
                $album = trim($fileInfo['tags']['id3v1']['album'][0]);
            }
            
            // Duration
            if (isset($fileInfo['playtime_seconds'])) {
                $duration = (int)round($fileInfo['playtime_seconds']);
            }
            
            // Cover из файла
            if (!empty($fileInfo['comments']['picture'][0]['data'])) {
                $coverData = $fileInfo['comments']['picture'][0]['data'];
                $coverExt = 'jpg';
                if (!empty($fileInfo['comments']['picture'][0]['image_mime'])) {
                    $mime = $fileInfo['comments']['picture'][0]['image_mime'];
                    if (strpos($mime, 'png') !== false) $coverExt = 'png';
                    if (strpos($mime, 'gif') !== false) $coverExt = 'gif';
                }
                
                $coverFileName = md5($filePath) . '.' . $coverExt;
                $coverPath = __DIR__ . '/tracks/covers/' . $coverFileName;
                
                if (!is_dir(__DIR__ . '/tracks/covers/')) {
                    mkdir(__DIR__ . '/tracks/covers/', 0755, true);
                }
                
                file_put_contents($coverPath, $coverData);
                $cover = 'tracks/covers/' . $coverFileName;
            }
        }
        
        // Если данных недостаточно, используем API
        if (empty($title) || empty($artist) || empty($cover) || empty($duration)) {
            // Пробуем извлечь базовую информацию из имени файла
            if (empty($title)) {
                $title = pathinfo($fileName, PATHINFO_FILENAME);
            }
            if (empty($artist)) {
                // Пробуем парсить имя файла: "Artist - Title.mp3"
                if (preg_match('/^(.+?)\s*-\s*(.+)$/', $title, $matches)) {
                    $artist = trim($matches[1]);
                    $title = trim($matches[2]);
                } else {
                    $artist = 'Неизвестный артист';
                }
            }
            
            // Получаем метаданные из iTunes API (с небольшой задержкой)
            usleep(100000); // 0.1 секунды чтобы не перегружать API
            $apiMetadata = getMetadataFromiTunes($title, $artist);
            if ($apiMetadata) {
                // Используем данные из API если они лучше
                if (empty($title) || $title === pathinfo($fileName, PATHINFO_FILENAME)) {
                    $title = $apiMetadata['title'];
                }
                if (empty($artist) || $artist === 'Неизвестный артист') {
                    $artist = $apiMetadata['artist'];
                }
                if (empty($album)) {
                    $album = $apiMetadata['album'] ?: 'Без альбома';
                }
                if (empty($duration) && !empty($apiMetadata['duration'])) {
                    $duration = $apiMetadata['duration'];
                }
                
                // Скачиваем обложку если её нет
                if (empty($cover) && !empty($apiMetadata['cover'])) {
                    $coverFileName = md5($filePath) . '.jpg';
                    $coverPath = __DIR__ . '/tracks/covers/' . $coverFileName;
                    
                    if (!is_dir(__DIR__ . '/tracks/covers/')) {
                        mkdir(__DIR__ . '/tracks/covers/', 0755, true);
                    }
                    
                    if (downloadCover($apiMetadata['cover'], $coverPath)) {
                        $cover = 'tracks/covers/' . $coverFileName;
                    }
                }
            }
        }
        
        // Fallback значения
        if (empty($title)) {
            $title = pathinfo($fileName, PATHINFO_FILENAME);
        }
        if (empty($artist)) {
            $artist = 'Неизвестный артист';
        }
        if (empty($album)) {
            $album = 'Без альбома';
        }
        if (empty($cover)) {
            // Ищем обложку в папке
            $coverFiles = glob(dirname($filePath) . '/*.{jpg,jpeg,png}', GLOB_BRACE);
            if (!empty($coverFiles)) {
                $cover = str_replace(__DIR__ . '/', '', $coverFiles[0]);
            } else {
                $cover = 'tracks/covers/placeholder.jpg';
            }
        }
        
        // Нормализуем путь к файлу (убираем /muzic2/ если есть)
        $normalizedPath = $relativePath;
        $normalizedPath = preg_replace('#^/+muzic2/+#', '', $normalizedPath);
        $normalizedPath = ltrim($normalizedPath, '/');
        
        // Убеждаемся что путь начинается с tracks/
        if (strpos($normalizedPath, 'tracks/') !== 0) {
            $normalizedPath = 'tracks/music/' . basename($normalizedPath);
        }
        
        // Подготавливаем данные для пакетной вставки
        $batch[] = [
            'title' => $title,
            'artist' => $artist,
            'album' => $album,
            'duration' => $duration,
            'file_path' => $normalizedPath,
            'cover' => $cover,
            'album_type' => 'album'
        ];
        
        // Вставляем пакетом каждые $batchSize треков
        if (count($batch) >= $batchSize) {
            insertBatch($db, $batch);
            $added += count($batch);
            $batch = [];
        }
        
        echo "  ✅ $title - $artist ($duration сек)\n";
        
    } catch (Exception $e) {
        echo "  ❌ Ошибка: " . $e->getMessage() . "\n";
        $errors++;
    }
}

// Вставляем оставшиеся треки
if (!empty($batch)) {
    insertBatch($db, $batch);
    $added += count($batch);
}

// Подтверждаем транзакцию
$db->commit();

echo "\n";
echo "═══════════════════════════════════════\n";
echo "✅ ГОТОВО!\n";
echo "═══════════════════════════════════════\n";
echo "Добавлено: $added треков\n";
echo "Пропущено: $skipped треков\n";
echo "Ошибок: $errors\n";
echo "═══════════════════════════════════════\n";

/**
 * Пакетная вставка треков (быстрее чем по одному)
 */
function insertBatch($db, $batch) {
    if (empty($batch)) return;
    
    // Подготавливаем SQL для пакетной вставки
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
}

