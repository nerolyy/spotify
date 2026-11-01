<?php
// File upload API for admin panel
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не поддерживается']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Файл не был загружен']);
    exit;
}

$file = $_FILES['file'];
$allowedTypes = ['audio/mpeg', 'audio/wav', 'audio/flac', 'audio/mp4', 'audio/x-m4a'];
$allowedExtensions = ['mp3', 'wav', 'flac', 'm4a'];

// Check file type
$fileType = $file['type'];
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($fileType, $allowedTypes) && !in_array($fileExtension, $allowedExtensions)) {
    http_response_code(400);
    echo json_encode(['error' => 'Неподдерживаемый тип файла. Разрешены: MP3, WAV, FLAC, M4A']);
    exit;
}

// Check file size (max 50MB)
$maxSize = 50 * 1024 * 1024; // 50MB
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => 'Файл слишком большой. Максимум 50MB']);
    exit;
}

// Create upload directory if it doesn't exist
$uploadDir = __DIR__ . '/../../public/assets/audio/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$originalName = pathinfo($file['name'], PATHINFO_FILENAME);
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = $originalName . '_' . time() . '_' . uniqid() . '.' . $extension;
$filepath = $uploadDir . $filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Generate URL
    $url = '/muzic2/public/assets/audio/uploads/' . $filename;
    
    // Get file duration if possible
    $duration = 0;
    if (function_exists('shell_exec')) {
        $ffprobe = shell_exec("ffprobe -v quiet -show_entries format=duration -of csv=p=0 " . escapeshellarg($filepath) . " 2>/dev/null");
        if ($ffprobe) {
            $duration = intval(floatval(trim($ffprobe)));
        }
    }
    
    echo json_encode([
        'success' => true,
        'url' => $url,
        'filename' => $filename,
        'size' => $file['size'],
        'duration' => $duration,
        'type' => $fileType
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка загрузки файла']);
}
?>
