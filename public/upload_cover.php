<?php
// Simple image upload endpoint for cover images
// Saves files to ../tracks/covers and returns JSON with the stored path

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

    if (!isset($_FILES['cover'])) {
        throw new Exception('Файл не найден. Используйте поле "cover"');
    }

    $file = $_FILES['cover'];

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

    // Validate size (<= 15MB)
    $maxSize = 15 * 1024 * 1024; // 15 MB
    if ($file['size'] > $maxSize) {
        throw new Exception('Файл слишком большой. Максимум 15 МБ');
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
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif'
    ];

    if (!isset($allowed[$mime])) {
        throw new Exception('Недопустимый тип файла. Разрешены: JPG, PNG, WEBP, GIF');
    }

    $originalName = $file['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === 'jpeg') { $ext = 'jpg'; }

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
        $base = 'cover-' . uniqid();
    }

    // Ensure target directory exists
    $targetDir = __DIR__ . '/../tracks/covers';
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
    $relativePath = 'tracks/covers/' . $filename;

    echo json_encode([
        'success' => true,
        'message' => 'Файл успешно загружен',
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
