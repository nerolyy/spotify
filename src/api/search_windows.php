<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Требуется авторизация']);
    exit;
}

// Ультра-быстрая версия поиска для Windows
try {
    $pdo = get_db_connection();
    
    $query = $_GET['q'] ?? '';
    $type = $_GET['type'] ?? 'all';
    
    if (empty($query)) {
        echo json_encode(['error' => 'Query parameter is required']);
        exit;
    }
    
    $results = [
        'tracks' => [],
        'artists' => [],
        'albums' => []
    ];
    
    // Простой поиск треков (только первые 10)
    if ($type === 'all' || $type === 'tracks') {
        $stmt = $pdo->prepare("SELECT id, title, artist, album, album_type, duration, file_path, cover, video_url, explicit FROM tracks WHERE LOWER(title) LIKE LOWER(?) OR LOWER(artist) LIKE LOWER(?) LIMIT 10");
        $searchTerm = '%' . $query . '%';
        $stmt->execute([$searchTerm, $searchTerm]);
        $results['tracks'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Простой поиск артистов (только первые 5)
    if ($type === 'all' || $type === 'artists') {
        $stmt = $pdo->prepare("
            SELECT
                t.artist AS name,
                COALESCE(NULLIF(a.cover, ''), MIN(t.cover)) AS cover,
                COUNT(*) AS track_count
            FROM tracks t
            LEFT JOIN artists a ON a.name = t.artist
            WHERE LOWER(t.artist) LIKE LOWER(?)
            GROUP BY t.artist, a.cover
            ORDER BY track_count DESC
            LIMIT 5
        ");
        $searchTerm = '%' . $query . '%';
        $stmt->execute([$searchTerm]);
        $results['artists'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Простой поиск альбомов (только первые 5)
    if ($type === 'all' || $type === 'albums') {
        $stmt = $pdo->prepare("SELECT DISTINCT album, artist, album_type, cover FROM tracks WHERE LOWER(album) LIKE LOWER(?) LIMIT 5");
        $searchTerm = '%' . $query . '%';
        $stmt->execute([$searchTerm]);
        $results['albums'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode($results);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error']);
}
?>
