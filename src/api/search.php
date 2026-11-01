<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Требуется авторизация']);
    exit;
}

require_once '../config/db.php';

try {
    $db = get_db_connection();
    
    $query = $_GET['q'] ?? '';
    $type = $_GET['type'] ?? 'all'; // all, tracks, artists, albums
    
    if (empty($query)) {
        echo json_encode(['error' => 'Query parameter is required']);
        exit;
    }
    
    $results = [
        'tracks' => [],
        'artists' => [],
        'albums' => []
    ];
    
    // Search tracks
    if ($type === 'all' || $type === 'tracks') {
        $stmt = $db->prepare("
            SELECT 
                id,
                title,
                duration,
                file_path as src,
                cover,
                artist,
                album,
                COALESCE(video_url, '') AS video_url,
                explicit
            FROM tracks 
            WHERE 
                title LIKE ? OR 
                artist LIKE ? OR 
                album LIKE ?
            ORDER BY 
                CASE 
                    WHEN title LIKE ? THEN 1
                    WHEN artist LIKE ? THEN 2
                    WHEN album LIKE ? THEN 3
                    ELSE 4
                END,
                title
            LIMIT 50
        ");
        
        $searchTerm = '%' . $query . '%';
        $exactMatch = $query . '%';
        
        $stmt->execute([
            $searchTerm, $searchTerm, $searchTerm,
            $exactMatch, $exactMatch, $exactMatch
        ]);
        
        $results['tracks'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Search artists (deduplicated by normalized name)
    if ($type === 'all' || $type === 'artists') {
        $stmt = $db->prepare("
            SELECT
                TRIM(LOWER(artist)) AS norm_name,
                MIN(artist) AS name,
                MIN(cover) AS cover,
                COUNT(*) AS track_count
            FROM tracks 
            WHERE LOWER(artist) LIKE LOWER(?)
            GROUP BY norm_name
            ORDER BY 
                CASE WHEN norm_name LIKE LOWER(?) THEN 1 ELSE 2 END,
                track_count DESC,
                name ASC
            LIMIT 20
        ");
        
        $searchTerm = '%' . $query . '%';
        $exactMatch = $query . '%';
        
        $stmt->execute([$searchTerm, $exactMatch]);
        
        $results['artists'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Drop helper column
        foreach ($results['artists'] as &$a) { unset($a['norm_name']); }
    }
    
    // Search albums (deduplicated by normalized title)
    if ($type === 'all' || $type === 'albums') {
        $stmt = $db->prepare("
            SELECT
                TRIM(LOWER(album)) AS norm_album,
                MIN(album) AS title,
                MIN(artist) AS artist,
                MIN(cover) AS cover,
                MIN(album_type) AS album_type,
                COUNT(*) AS track_count
            FROM tracks 
            WHERE LOWER(album) LIKE LOWER(?) OR LOWER(artist) LIKE LOWER(?)
            GROUP BY norm_album
            ORDER BY 
                CASE WHEN norm_album LIKE LOWER(?) THEN 1 ELSE 2 END,
                track_count DESC,
                title ASC
            LIMIT 20
        ");
        
        $searchTerm = '%' . $query . '%';
        $exactMatch = $query . '%';
        
        $stmt->execute([$searchTerm, $searchTerm, $exactMatch]);
        
        $results['albums'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results['albums'] as &$a) { unset($a['norm_album']); }
    }
    
    echo json_encode($results);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
