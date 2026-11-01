<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

try {
    $db = get_db_connection();
    
    // Get all albums with their info
    $stmt = $db->prepare('SELECT 
        album,
        MIN(artist) AS artist,
        MIN(album_type) AS album_type,
        MIN(cover) AS cover,
        MAX(id) AS last_track_id
    FROM tracks 
    GROUP BY album 
    ORDER BY album');
    $stmt->execute();
    $albums = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['albums' => $albums]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
