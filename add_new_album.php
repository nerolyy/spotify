<?php
/**
 * Скрипт для добавления нового альбома в базу данных
 * Заполните данные ниже и запустите этот файл
 */

require_once 'src/config/db.php';

// ===========================================
// ЗАПОЛНИТЕ ДАННЫЕ НИЖЕ ДЛЯ НОВОГО АЛЬБОМА
// ===========================================

$new_album_data = [
    'album_name' => 'Название альбома',
    'artist_name' => 'Имя артиста', 
    'album_type' => 'album', // 'album', 'ep', или 'single'
    'cover_image' => 'tracks/covers/имя_файла.jpg', // путь к обложке
    'release_year' => 2024, // год выпуска
    'description' => 'Описание альбома',
    'tracks' => [
        [
            'title' => 'Название трека 1',
            'duration' => 180, // в секундах
            'file_path' => 'tracks/music/файл1.mp3',
            'track_number' => 1
        ],
        [
            'title' => 'Название трека 2', 
            'duration' => 200,
            'file_path' => 'tracks/music/файл2.mp3',
            'track_number' => 2
        ],
        [
            'title' => 'Название трека 3',
            'duration' => 160,
            'file_path' => 'tracks/music/файл3.mp3', 
            'track_number' => 3
        ]
        // Добавьте больше треков по необходимости
    ]
];

// ===========================================
// НЕ ИЗМЕНЯЙТЕ КОД НИЖЕ ЭТОЙ СТРОКИ
// ===========================================

try {
    $db = get_db_connection();
    
    echo "<h2>Добавление нового альбома: {$new_album_data['album_name']}</h2>";
    echo "<h3>Артист: {$new_album_data['artist_name']}</h3>";
    echo "<h3>Тип: {$new_album_data['album_type']}</h3>";
    echo "<h3>Обложка: {$new_album_data['cover_image']}</h3>";
    echo "<h3>Количество треков: " . count($new_album_data['tracks']) . "</h3>";
    
    echo "<hr>";
    echo "<h3>Треки:</h3>";
    echo "<ol>";
    
    $total_duration = 0;
    $inserted_tracks = 0;
    
    foreach ($new_album_data['tracks'] as $track) {
        echo "<li>{$track['title']} ({$track['duration']} сек.)</li>";
        
        // Вставляем трек в базу данных
        $sql = "INSERT INTO tracks (title, artist, album, album_type, duration, file_path, cover) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            $track['title'],
            $new_album_data['artist_name'],
            $new_album_data['album_name'],
            $new_album_data['album_type'],
            $track['duration'],
            $track['file_path'],
            $new_album_data['cover_image']
        ]);
        
        if ($result) {
            $inserted_tracks++;
            $total_duration += $track['duration'];
        } else {
            echo "<p style='color: red;'>Ошибка при добавлении трека: {$track['title']}</p>";
        }
    }
    
    echo "</ol>";
    
    echo "<hr>";
    echo "<h3>Результат:</h3>";
    echo "<p style='color: green;'>✅ Успешно добавлено треков: {$inserted_tracks}</p>";
    echo "<p>📊 Общая продолжительность альбома: " . gmdate("H:i:s", $total_duration) . "</p>";
    echo "<p>🎵 Альбом: <strong>{$new_album_data['album_name']}</strong> от <strong>{$new_album_data['artist_name']}</strong></p>";
    
    if ($inserted_tracks > 0) {
        echo "<p style='color: green; font-weight: bold;'>🎉 Альбом успешно добавлен в базу данных!</p>";
        echo "<p>Теперь вы можете:</p>";
        echo "<ul>";
        echo "<li>Просмотреть альбом на главной странице</li>";
        echo "<li>Открыть страницу альбома</li>";
        echo "<li>Воспроизводить треки через плеер</li>";
        echo "</ul>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Ошибка: " . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавление альбома</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h2, h3 {
            color: #333;
        }
        hr {
            margin: 20px 0;
            border: 1px solid #ddd;
        }
        ol {
            background: white;
            padding: 15px;
            border-radius: 5px;
        }
        li {
            margin: 5px 0;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <h1>🎵 Добавление нового альбома</h1>
    <p>Этот скрипт поможет вам добавить новый альбом в базу данных.</p>
    
    <h2>📝 Инструкция:</h2>
    <ol>
        <li>Откройте файл <code>add_new_album.php</code> в редакторе</li>
        <li>Заполните данные в массиве <code>$new_album_data</code></li>
        <li>Добавьте все треки альбома в массив <code>tracks</code></li>
        <li>Убедитесь, что файлы музыки и обложки находятся в правильных папках</li>
        <li>Запустите этот файл в браузере</li>
    </ol>
    
    <h2>📁 Структура папок:</h2>
    <ul>
        <li><strong>Музыка:</strong> <code>tracks/music/</code></li>
        <li><strong>Обложки:</strong> <code>tracks/covers/</code></li>
    </ul>
    
    <h2>🎨 Доступные обложки:</h2>
    <ul>
        <li>Kai-Angel-ANGEL-MAY-CRY-07.jpg</li>
        <li>Снимок экрана 2025-07-14 в 07.03.03.png</li>
        <li>Снимок экрана 2025-07-19 в 11.56.58.png</li>
        <li>Heavymetal2.webp.png</li>
        <li>m1000x1000.jpeg</li>
        <li>Без названия (1).jpeg</li>
        <li>Без названия (2).jpeg</li>
    </ul>
    
    <p><strong>Примечание:</strong> После добавления альбома, он автоматически появится на сайте!</p>
</body>
</html>






















