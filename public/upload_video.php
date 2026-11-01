<?php
// Simple video upload endpoint for promo videos
// Saves files to ../tracks/videos and returns JSON with the stored path

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Метод не разрешен']);
        exit;
    }

    if (!isset($_FILES['video'])) {
        throw new Exception('Файл не найден. Используйте поле "video"');
    }

    $file = $_FILES['video'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $map = [
            UPLOAD_ERR_INI_SIZE => 'Размер файла превышает upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'Размер файла превышает MAX_FILE_SIZE формы',
            UPLOAD_ERR_PARTIAL => 'Файл был загружен только частично',
            UPLOAD_ERR_NO_FILE => 'Файл не был загружен',
            UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка',
            UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск',
            UPLOAD_ERR_EXTENSION => 'Загрузка остановлена расширением PHP',
        ];
        $msg = $map[$file['error']] ?? ('Код ошибки: ' . $file['error']);
        throw new Exception('Ошибка загрузки файла: ' . $msg);
    }

    // Validate size (<= 100MB for videos)
    $maxSize = 100 * 1024 * 1024; // 100 MB
    if ($file['size'] > $maxSize) {
        throw new Exception('Файл слишком большой. Максимум 100 МБ');
    }

    // Validate MIME and extension (fileinfo may be disabled; fall back gracefully)
    $mime = '';
    try {
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$finfo->file($file['tmp_name']);
        }
    } catch (Throwable $e) {
        $mime = '';
    }
    if (!$mime) {
        $mime = (string)($file['type'] ?? '');
    }
    
    // Расширенный список поддерживаемых форматов
    $allowed = [
        'video/mp4' => 'mp4',
        'video/x-m4v' => 'm4v',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
        'video/x-quicktime' => 'mov', // Альтернативный MIME для MOV
        'video/x-msvideo' => 'avi',
        'video/avi' => 'avi',
        'application/octet-stream' => null // Для некоторых MOV файлов
    ];
    
    // Проверяем расширение файла как fallback
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['mp4', 'm4v', 'webm', 'mov', 'avi'];
    
    // Если MIME не распознан, но расширение допустимо, разрешаем загрузку
    if (!isset($allowed[$mime]) && in_array($ext, $allowedExts)) {
        // Для MOV файлов часто определяется как application/octet-stream
        if ($ext === 'mov' && ($mime === 'application/octet-stream' || $mime === 'video/quicktime')) {
            $mime = 'video/quicktime';
        } else {
            // Используем расширение для определения типа
            $mimeMap = [
                'mp4' => 'video/mp4',
                'm4v' => 'video/x-m4v',
                'webm' => 'video/webm',
                'mov' => 'video/quicktime',
                'avi' => 'video/x-msvideo'
            ];
            if (isset($mimeMap[$ext])) {
                $mime = $mimeMap[$ext];
            }
        }
    }

    if (!isset($allowed[$mime]) && !in_array($ext, $allowedExts)) {
        throw new Exception('Недопустимый тип файла. Разрешены: MP4, M4V, WEBM, MOV, AVI. Обнаружен: ' . $mime . ' (расширение: ' . $ext . ')');
    }

    $originalName = $file['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === '') {
        $ext = $allowed[$mime] ?? 'mp4';
    }
    // Для MOV файлов убеждаемся, что расширение правильное
    if ($mime === 'video/quicktime' && $ext !== 'mov') {
        $ext = 'mov';
    }

    // Sanitize and slugify filename
    $base = pathinfo($originalName, PATHINFO_FILENAME);
    $base = trim($base);
    // transliterate (may fail silently if iconv not available)
    $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base);
    if ($trans !== false) { $base = $trans; }
    $base = strtolower($base);
    $base = preg_replace('/[^a-z0-9\-_]+/i', '-', $base);
    $base = preg_replace('/-+/', '-', $base);
    $base = trim($base, '-_');
    if ($base === '' || $base === null) {
        $base = 'video-' . uniqid();
    }

    // Ensure target directory exists
    $targetDir = __DIR__ . '/../tracks/videos';
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new Exception('Не удалось создать директорию загрузки');
        }
    }

    // Resolve filename avoiding collisions
    $filename = $base . '.' . $ext;
    $targetPath = $targetDir . '/' . $filename;
    if (file_exists($targetPath)) {
        $filename = $base . '-' . date('Ymd-His') . '-' . substr(uniqid('', true), -6) . '.' . $ext;
        $targetPath = $targetDir . '/' . $filename;
    }

    // Extra security check
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new Exception('Недопустимый файл');
    }

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Не удалось сохранить файл');
    }

    // Build relative path from project root for DB usage
    $relativePath = 'tracks/videos/' . $filename;

    echo json_encode([
        'success' => true,
        'message' => 'Видео успешно загружено',
        'path' => $relativePath,
        'filename' => $filename,
        'mime' => $mime,
        'size' => $file['size']
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

