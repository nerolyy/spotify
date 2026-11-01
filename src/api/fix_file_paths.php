<?php
/**
 * Исправление путей к файлам в базе данных
 * Нормализует все пути к формату tracks/music/filename.mp3
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$apply = isset($_GET['apply']) && ($_GET['apply'] === '1' || strtolower($_GET['apply']) === 'true');

function normalizeFilePath($path) {
    if (empty($path)) return '';
    
    // Нормализуем разделители
    $path = str_replace('\\', '/', $path);
    
    // Убираем ведущие слэши и /muzic2/
    $path = preg_replace('#^/+muzic2/+#', '', $path);
    $path = ltrim($path, '/');
    
    // Если путь уже начинается с tracks/, возвращаем как есть
    if (strpos($path, 'tracks/') === 0) {
        return $path;
    }
    
    // Если это абсолютный путь, извлекаем относительный
    $root = realpath(__DIR__ . '/../../');
    if ($root && (strpos($path, '/') === 0 || strpos($path, $root) === 0)) {
        $fullPath = realpath($path);
        if ($fullPath && strpos($fullPath, $root) === 0) {
            $path = substr($fullPath, strlen($root) + 1);
            // Если получили путь с tracks/, возвращаем
            if (strpos($path, 'tracks/') === 0) {
                return $path;
            }
        }
    }
    
    // Пробуем найти tracks/ в пути
    $idx = strpos($path, 'tracks/');
    if ($idx !== false) {
        return substr($path, $idx);
    }
    
    // Если ничего не помогло, предполагаем что это имя файла в tracks/music/
    return 'tracks/music/' . basename($path);
}

try {
    $db = get_db_connection();
    $tracks = $db->query('SELECT id, file_path FROM tracks ORDER BY id ASC')->fetchAll();
    
    $changes = [];
    foreach ($tracks as $track) {
        $id = (int)$track['id'];
        $originalPath = (string)$track['file_path'];
        $normalizedPath = normalizeFilePath($originalPath);
        
        if ($normalizedPath !== $originalPath) {
            $changes[] = [
                'id' => $id,
                'from' => $originalPath,
                'to' => $normalizedPath
            ];
        }
    }
    
    if ($apply && !empty($changes)) {
        $stmt = $db->prepare('UPDATE tracks SET file_path = ? WHERE id = ?');
        foreach ($changes as $change) {
            $stmt->execute([$change['to'], $change['id']]);
        }
    }
    
    echo json_encode([
        'success' => true,
        'updated' => $apply ? count($changes) : 0,
        'pending' => !$apply ? count($changes) : 0,
        'preview' => !$apply ? array_slice($changes, 0, 20) : [], // Показываем первые 20 для предпросмотра
        'total' => count($tracks)
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}







