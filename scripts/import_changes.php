<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../src/config/db.php';

// Используем функцию из config/db.php

$file = $argv[1] ?? '';
if (strpos($file, '--file=') === 0) {
    $file = substr($file, 7);
}

if (empty($file) || !file_exists($file)) {
    die("Usage: php import_changes.php --file=changes_export.json\n");
}

$response = ['success' => false, 'imported' => [], 'errors' => []];

try {
    $db = get_db_connection();
    $data = json_decode(file_get_contents($file), true);
    
    if (!$data || !isset($data['data'])) {
        throw new Exception("Invalid export file format");
    }
    
    $db->beginTransaction();
    
    // Импорт треков
    if (isset($data['data']['tracks']) && is_array($data['data']['tracks'])) {
        $insertTrack = $db->prepare("
            INSERT INTO tracks (id, title, artist, album, album_type, duration, file_path, cover, video_url, explicit, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                artist = VALUES(artist),
                album = VALUES(album),
                album_type = VALUES(album_type),
                duration = VALUES(duration),
                file_path = VALUES(file_path),
                cover = VALUES(cover),
                video_url = VALUES(video_url),
                explicit = VALUES(explicit),
                updated_at = VALUES(updated_at)
        ");
        
        foreach ($data['data']['tracks'] as $track) {
            try {
                $insertTrack->execute([
                    $track['id'],
                    $track['title'],
                    $track['artist'],
                    $track['album'],
                    $track['album_type'],
                    $track['duration'],
                    $track['file_path'],
                    $track['cover'],
                    $track['video_url'],
                    $track['explicit'],
                    $track['created_at'],
                    $track['updated_at']
                ]);
                $response['imported']['tracks'][] = $track['id'];
            } catch (Exception $e) {
                $response['errors'][] = "Track {$track['id']}: " . $e->getMessage();
            }
        }
    }
    
    // Импорт артистов
    if (isset($data['data']['artists']) && is_array($data['data']['artists'])) {
        $insertArtist = $db->prepare("
            INSERT INTO artists (id, name, cover, bio, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                cover = VALUES(cover),
                bio = VALUES(bio),
                updated_at = VALUES(updated_at)
        ");
        
        foreach ($data['data']['artists'] as $artist) {
            try {
                $insertArtist->execute([
                    $artist['id'],
                    $artist['name'],
                    $artist['cover'],
                    $artist['bio'],
                    $artist['created_at'],
                    $artist['updated_at']
                ]);
                $response['imported']['artists'][] = $artist['id'];
            } catch (Exception $e) {
                $response['errors'][] = "Artist {$artist['id']}: " . $e->getMessage();
            }
        }
    }
    
    $db->commit();
    $response['success'] = true;
    
} catch (Throwable $e) {
    $db->rollBack();
    $response['success'] = false;
    $response['error'] = $e->getMessage();
    $response['trace'] = $e->getTraceAsString();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
