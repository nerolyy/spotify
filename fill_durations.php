<?php
require_once __DIR__ . '/src/config/db.php';

$db = get_db_connection();

$tracks = $db->query("SELECT id, file_path FROM tracks WHERE duration IS NULL OR duration = 0 OR duration = 180")->fetchAll();

foreach ($tracks as $track) {
    $file = __DIR__ . '/' . $track['file_path'];
    if (!file_exists($file)) {
        echo "Файл не найден: $file\n";
        continue;
    }
    $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($file);
    $output = shell_exec($cmd);
    $duration = (int)round(floatval($output));
    if ($duration > 0) {
        $db->prepare("UPDATE tracks SET duration = ? WHERE id = ?")->execute([$duration, $track['id']]);
        echo "OK: {$track['file_path']} = $duration сек\n";
    } else {
        echo "Ошибка определения длительности: {$track['file_path']}\n";
    }
}
echo "Готово!\n"; 