<?php
// Quick script to add sample content
require_once __DIR__ . '/src/config/db.php';

$db = get_db_connection();

// Sample data
$sampleTracks = [
    ['title' => 'Великая песня', 'artist' => 'Звезда музыки', 'album' => 'Золотые хиты', 'duration' => 240],
    ['title' => 'Мелодия души', 'artist' => 'Творческий гений', 'album' => 'Душевные песни', 'duration' => 180],
    ['title' => 'Ритм сердца', 'artist' => 'Музыкальный мастер', 'album' => 'Сердечные ритмы', 'duration' => 200],
    ['title' => 'Звуки ночи', 'artist' => 'Ночной артист', 'album' => 'Ночная коллекция', 'duration' => 220],
    ['title' => 'Утренний свет', 'artist' => 'Утренняя звезда', 'album' => 'Рассветные мелодии', 'duration' => 190],
    ['title' => 'Океан звуков', 'artist' => 'Морской музыкант', 'album' => 'Волны музыки', 'duration' => 260],
    ['title' => 'Городские ритмы', 'artist' => 'Городской артист', 'album' => 'Урбанистика', 'duration' => 210],
    ['title' => 'Лесная симфония', 'artist' => 'Природный музыкант', 'album' => 'Звуки природы', 'duration' => 300],
    ['title' => 'Космическая одиссея', 'artist' => 'Космический артист', 'album' => 'Звездные путешествия', 'duration' => 280],
    ['title' => 'Джазовая импровизация', 'artist' => 'Джазовый мастер', 'album' => 'Джазовые вечера', 'duration' => 320]
];

$sampleAlbums = [
    ['title' => 'Золотые хиты', 'artist' => 'Звезда музыки', 'year' => 2023, 'genre' => 'Поп'],
    ['title' => 'Душевные песни', 'artist' => 'Творческий гений', 'year' => 2022, 'genre' => 'Баллады'],
    ['title' => 'Сердечные ритмы', 'artist' => 'Музыкальный мастер', 'year' => 2023, 'genre' => 'Рок'],
    ['title' => 'Ночная коллекция', 'artist' => 'Ночной артист', 'year' => 2021, 'genre' => 'Электроника'],
    ['title' => 'Рассветные мелодии', 'artist' => 'Утренняя звезда', 'year' => 2023, 'genre' => 'Классика']
];

echo "<h1>Быстрое добавление тестовых данных</h1>";

// Add tracks
echo "<h2>Добавление треков...</h2>";
$addedTracks = 0;
foreach ($sampleTracks as $track) {
    try {
        $stmt = $db->prepare('
            INSERT INTO tracks (title, artist, album, duration, cover, audio, explicit, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ');
        $stmt->execute([
            $track['title'],
            $track['artist'],
            $track['album'],
            $track['duration'],
            '/muzic2/public/assets/img/placeholder.png',
            '/muzic2/public/assets/audio/placeholder.mp3',
            0
        ]);
        
        $trackId = $db->lastInsertId();
        
        // Add artist to track_artists
        $artistStmt = $db->prepare('
            INSERT IGNORE INTO track_artists (track_id, artist, role) 
            VALUES (?, ?, "main")
        ');
        $artistStmt->execute([$trackId, $track['artist']]);
        
        $addedTracks++;
        echo "✅ Добавлен трек: {$track['title']} - {$track['artist']}<br>";
    } catch (Exception $e) {
        echo "❌ Ошибка добавления трека {$track['title']}: " . $e->getMessage() . "<br>";
    }
}

// Add albums
echo "<h2>Добавление альбомов...</h2>";
$addedAlbums = 0;
foreach ($sampleAlbums as $album) {
    try {
        // Check if album exists
        $checkStmt = $db->prepare('SELECT id FROM albums WHERE title = ? AND artist = ?');
        $checkStmt->execute([$album['title'], $album['artist']]);
        
        if (!$checkStmt->fetch()) {
            $stmt = $db->prepare('
                INSERT INTO albums (title, artist, year, genre, cover, description, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([
                $album['title'],
                $album['artist'],
                $album['year'],
                $album['genre'],
                '/muzic2/public/assets/img/placeholder.png',
                'Описание альбома ' . $album['title']
            ]);
            
            $addedAlbums++;
            echo "✅ Добавлен альбом: {$album['title']} - {$album['artist']}<br>";
        } else {
            echo "⚠️ Альбом уже существует: {$album['title']} - {$album['artist']}<br>";
        }
    } catch (Exception $e) {
        echo "❌ Ошибка добавления альбома {$album['title']}: " . $e->getMessage() . "<br>";
    }
}

// Get statistics
$tracksCount = $db->query('SELECT COUNT(*) as count FROM tracks')->fetch()['count'];
$albumsCount = $db->query('SELECT COUNT(*) as count FROM albums')->fetch()['count'];
$artistsCount = $db->query('SELECT COUNT(DISTINCT artist) as count FROM tracks WHERE artist IS NOT NULL')->fetch()['count'];

echo "<h2>📊 Статистика</h2>";
echo "<p><strong>Всего треков:</strong> {$tracksCount}</p>";
echo "<p><strong>Всего альбомов:</strong> {$albumsCount}</p>";
echo "<p><strong>Всего артистов:</strong> {$artistsCount}</p>";
echo "<p><strong>Добавлено треков:</strong> {$addedTracks}</p>";
echo "<p><strong>Добавлено альбомов:</strong> {$addedAlbums}</p>";

echo "<h2>🎉 Готово!</h2>";
echo "<p><a href='admin.html'>Открыть админ-панель</a></p>";
echo "<p><a href='index.html'>Открыть главную страницу</a></p>";
?>
