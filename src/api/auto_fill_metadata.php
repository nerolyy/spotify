<?php
/**
 * Автоматическое заполнение метаданных для треков из бесплатных API
 * Использует iTunes, MusicBrainz, Last.fm для получения недостающих данных
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/music_metadata.php';

header('Content-Type: application/json');

$db = get_db_connection();

// Получаем треки с неполными метаданными
$tracks = $db->query("
    SELECT id, title, artist, album, cover, duration 
    FROM tracks 
    WHERE (cover IS NULL OR cover = '' OR cover = 'tracks/covers/placeholder.jpg')
       OR (duration IS NULL OR duration = 0)
       OR (album IS NULL OR album = '')
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($tracks)) {
    echo json_encode(['success' => true, 'message' => 'Все треки уже имеют полные метаданные', 'updated' => 0]);
    exit;
}

$updated = 0;
$errors = 0;

foreach ($tracks as $track) {
    try {
        $updates = [];
        $params = [];
        
        // Получаем метаданные из API
        $metadata = getMetadataFromiTunes($track['title'], $track['artist']);
        
        if ($metadata) {
            // Обновляем обложку если её нет
            if (empty($track['cover']) || $track['cover'] === 'tracks/covers/placeholder.jpg') {
                if (!empty($metadata['cover'])) {
                    $coverFileName = md5($track['id'] . $track['title']) . '.jpg';
                    $coverPath = __DIR__ . '/../../tracks/covers/' . $coverFileName;
                    
                    if (downloadCover($metadata['cover'], $coverPath)) {
                        $updates[] = 'cover = ?';
                        $params[] = 'tracks/covers/' . $coverFileName;
                    }
                }
            }
            
            // Обновляем длительность если её нет
            if (empty($track['duration']) && !empty($metadata['duration'])) {
                $updates[] = 'duration = ?';
                $params[] = $metadata['duration'];
            }
            
            // Обновляем альбом если его нет
            if (empty($track['album']) && !empty($metadata['album'])) {
                $updates[] = 'album = ?';
                $params[] = $metadata['album'];
            }
            
            // Выполняем обновление
            if (!empty($updates)) {
                $params[] = $track['id'];
                $sql = 'UPDATE tracks SET ' . implode(', ', $updates) . ' WHERE id = ?';
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $updated++;
            }
        }
        
        // Небольшая задержка чтобы не перегружать API
        usleep(200000); // 0.2 секунды
        
    } catch (Exception $e) {
        $errors++;
        error_log("Error updating track {$track['id']}: " . $e->getMessage());
    }
}

echo json_encode([
    'success' => true,
    'updated' => $updated,
    'errors' => $errors,
    'total' => count($tracks)
]);







