<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../src/config/db.php';

// Используем функцию из config/db.php

$since = $argv[1] ?? '2024-01-01 00:00:00';
if (strpos($since, '--since=') === 0) {
    $since = substr($since, 8);
}

$response = ['success' => false, 'data' => [], 'timestamp' => date('Y-m-d H:i:s')];

try {
    $db = get_db_connection();
    
    // Экспорт треков
    $tracks = $db->prepare("
        SELECT id, title, artist, album, album_type, duration, file_path, cover, video_url, explicit, created_at, updated_at
        FROM tracks 
        WHERE created_at >= ? OR updated_at >= ?
        ORDER BY updated_at DESC
    ");
    $tracks->execute([$since, $since]);
    $response['data']['tracks'] = $tracks->fetchAll();
    
    // Экспорт артистов
    $artists = $db->prepare("
        SELECT id, name, cover, bio, created_at, updated_at
        FROM artists 
        WHERE created_at >= ? OR updated_at >= ?
        ORDER BY updated_at DESC
    ");
    $artists->execute([$since, $since]);
    $response['data']['artists'] = $artists->fetchAll();
    
    // Экспорт альбомов (из треков)
    $albums = $db->prepare("
        SELECT DISTINCT album, MIN(cover) as cover, COUNT(*) as track_count, MAX(updated_at) as last_updated
        FROM tracks 
        WHERE (created_at >= ? OR updated_at >= ?) AND album IS NOT NULL AND album != ''
        GROUP BY album
        ORDER BY last_updated DESC
    ");
    $albums->execute([$since, $since]);
    $response['data']['albums'] = $albums->fetchAll();
    
    $response['success'] = true;
    $response['counts'] = [
        'tracks' => count($response['data']['tracks']),
        'artists' => count($response['data']['artists']),
        'albums' => count($response['data']['albums'])
    ];
    
} catch (Throwable $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
    $response['trace'] = $e->getTraceAsString();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
