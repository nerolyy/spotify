<?php
require_once __DIR__ . '/src/config/db.php';

$db = get_db_connection();

$tracks = $db->query("SELECT id, file_path FROM tracks WHERE album = 'Angel May Cry'")->fetchAll();

foreach ($tracks as $track) {
    $file = __DIR__ . '/' . $track['file_path'];
    if (!file_exists($file)) {
        echo "Файл не найден: $file\n";
        continue;
    }
    
    $duration = getMP3Duration($file);
    if ($duration > 0) {
        $db->prepare("UPDATE tracks SET duration = ? WHERE id = ?")->execute([$duration, $track['id']]);
        echo "OK: {$track['file_path']} = $duration сек\n";
    } else {
        echo "Ошибка определения длительности: {$track['file_path']}\n";
    }
}
echo "Готово!\n";

function getMP3Duration($file) {
    $handle = fopen($file, 'rb');
    if (!$handle) return 0;
    
    $size = filesize($file);
    $duration = 0;
    $offset = 0;
    
    while ($offset < $size) {
        fseek($handle, $offset);
        $header = fread($handle, 4);
        
        if (strlen($header) < 4) break;
        
        $bytes = unpack('C*', $header);
        
        if ($bytes[1] == 0xFF && ($bytes[2] & 0xE0) == 0xE0) {
            $version = ($bytes[2] >> 3) & 3;
            $layer = ($bytes[2] >> 1) & 3;
            $bitrateIndex = ($bytes[3] >> 4) & 15;
            $samplingRateIndex = ($bytes[3] >> 2) & 3;
            $padding = ($bytes[3] >> 1) & 1;
            
            if ($version == 0 || $layer != 1) {
                $offset += 1;
                continue;
            }
            
            $bitrates = [
                1 => [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 288, 320],
                2 => [0, 32, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 288, 320, 352],
                3 => [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 288, 320]
            ];
            
            $samplingRates = [
                1 => [44100, 48000, 32000],
                2 => [22050, 24000, 16000],
                3 => [11025, 12000, 8000]
            ];
            
            if (!isset($bitrates[$version][$bitrateIndex]) || !isset($samplingRates[$version][$samplingRateIndex])) {
                $offset += 1;
                continue;
            }
            
            $bitrate = $bitrates[$version][$bitrateIndex] * 1000;
            $samplingRate = $samplingRates[$version][$samplingRateIndex];
            
            if ($bitrate == 0 || $samplingRate == 0) {
                $offset += 1;
                continue;
            }
            
            $frameSize = (144 * $bitrate) / $samplingRate + $padding;
            $offset += $frameSize;
            $duration += 0.026;
        } else {
            $offset += 1;
        }
    }
    
    fclose($handle);
    return (int)$duration;
} 