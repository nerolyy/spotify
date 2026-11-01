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
    
    $duration = getAudioDuration($file);
    if ($duration > 0) {
        $db->prepare("UPDATE tracks SET duration = ? WHERE id = ?")->execute([$duration, $track['id']]);
        echo "OK: {$track['file_path']} = $duration сек\n";
    } else {
        echo "Ошибка определения длительности: {$track['file_path']}\n";
    }
}
echo "Готово!\n";

function getAudioDuration($file) {
    $handle = fopen($file, 'rb');
    if (!$handle) return 0;
    
    $data = fread($handle, 1024);
    fclose($handle);
    
    if (substr($data, 0, 3) === 'ID3') {
        return getID3Duration($file);
    } elseif (substr($data, 0, 4) === 'RIFF') {
        return getWAVDuration($file);
    } else {
        return estimateMP3Duration($file);
    }
}

function getID3Duration($file) {
    $handle = fopen($file, 'rb');
    fseek($handle, -128, SEEK_END);
    $tag = fread($handle, 128);
    fclose($handle);
    
    if (substr($tag, 0, 3) === 'TAG') {
        $size = filesize($file);
        $bitrate = 128000;
        return (int)($size * 8 / $bitrate);
    }
    return 0;
}

function getWAVDuration($file) {
    $handle = fopen($file, 'rb');
    fseek($handle, 24);
    $sampleRate = unpack('V', fread($handle, 4))[1];
    fseek($handle, 28);
    $byteRate = unpack('V', fread($handle, 4))[1];
    fclose($handle);
    
    if ($byteRate > 0) {
        $size = filesize($file) - 44;
        return (int)($size / $byteRate);
    }
    return 0;
}

function estimateMP3Duration($file) {
    $size = filesize($file);
    
    $handle = fopen($file, 'rb');
    if (!$handle) return 0;
    
    fseek($handle, 0);
    $header = fread($handle, 10);
    fclose($handle);
    
    if (strlen($header) < 10) return 0;
    
    $bytes = unpack('C*', $header);
    
    if ($bytes[1] == 0xFF && ($bytes[2] & 0xE0) == 0xE0) {
        $version = ($bytes[2] >> 3) & 3;
        $layer = ($bytes[2] >> 1) & 3;
        $bitrateIndex = ($bytes[3] >> 4) & 15;
        $samplingRateIndex = ($bytes[3] >> 2) & 3;
        
        if ($version == 0 || $layer != 1) {
            return (int)($size * 8 / 128000);
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
            return (int)($size * 8 / 128000);
        }
        
        $bitrate = $bitrates[$version][$bitrateIndex] * 1000;
        $samplingRate = $samplingRates[$version][$samplingRateIndex];
        
        if ($bitrate == 0 || $samplingRate == 0) {
            return (int)($size * 8 / 128000);
        }
        
        $frameSize = (144 * $bitrate) / $samplingRate;
        $frames = $size / $frameSize;
        $duration = ($frames * 1152) / $samplingRate;
        
        return (int)$duration;
    }
    
    return (int)($size * 8 / 128000);
} 