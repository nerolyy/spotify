<?php
/**
 * Пример данных для нового альбома
 * Скопируйте эти данные в add_new_album.php и измените по необходимости
 */

$example_album_data = [
    'album_name' => 'Новый альбом',
    'artist_name' => 'Новый артист', 
    'album_type' => 'album', // 'album', 'ep', или 'single'
    'cover_image' => 'tracks/covers/Kai-Angel-ANGEL-MAY-CRY-07.jpg', // выберите из доступных
    'release_year' => 2024,
    'description' => 'Описание нового альбома',
    'tracks' => [
        [
            'title' => 'Первый трек',
            'duration' => 180, // 3 минуты в секундах
            'file_path' => 'tracks/music/первый_трек.mp3',
            'track_number' => 1
        ],
        [
            'title' => 'Второй трек', 
            'duration' => 200, // 3 минуты 20 секунд
            'file_path' => 'tracks/music/второй_трек.mp3',
            'track_number' => 2
        ],
        [
            'title' => 'Третий трек',
            'duration' => 160, // 2 минуты 40 секунд
            'file_path' => 'tracks/music/третий_трек.mp3', 
            'track_number' => 3
        ],
        [
            'title' => 'Четвертый трек',
            'duration' => 220, // 3 минуты 40 секунд
            'file_path' => 'tracks/music/четвертый_трек.mp3',
            'track_number' => 4
        ]
    ]
];

// Доступные обложки для выбора:
$available_covers = [
    'tracks/covers/Kai-Angel-ANGEL-MAY-CRY-07.jpg',
    'tracks/covers/Снимок экрана 2025-07-14 в 07.03.03.png',
    'tracks/covers/Снимок экрана 2025-07-19 в 11.56.58.png',
    'tracks/covers/Heavymetal2.webp.png',
    'tracks/covers/m1000x1000.jpeg',
    'tracks/covers/Без названия (1).jpeg',
    'tracks/covers/Без названия (2).jpeg'
];

// Примеры готовых альбомов:

// 1. Рок альбом
$rock_album = [
    'album_name' => 'Гром и молнии',
    'artist_name' => 'Рок группа',
    'album_type' => 'album',
    'cover_image' => 'tracks/covers/Heavymetal2.webp.png',
    'release_year' => 2024,
    'description' => 'Энергичный рок альбом с мощными риффами',
    'tracks' => [
        ['title' => 'Гром', 'duration' => 240, 'file_path' => 'tracks/music/гром.mp3', 'track_number' => 1],
        ['title' => 'Молнии', 'duration' => 200, 'file_path' => 'tracks/music/молнии.mp3', 'track_number' => 2],
        ['title' => 'Буря', 'duration' => 300, 'file_path' => 'tracks/music/буря.mp3', 'track_number' => 3],
        ['title' => 'Ураган', 'duration' => 180, 'file_path' => 'tracks/music/ураган.mp3', 'track_number' => 4]
    ]
];

// 2. Электронный EP
$electronic_ep = [
    'album_name' => 'Цифровые сны',
    'artist_name' => 'Электронный артист',
    'album_type' => 'ep',
    'cover_image' => 'tracks/covers/m1000x1000.jpeg',
    'release_year' => 2024,
    'description' => 'Экспериментальный электронный EP',
    'tracks' => [
        ['title' => 'Синтезатор', 'duration' => 180, 'file_path' => 'tracks/music/синтезатор.mp3', 'track_number' => 1],
        ['title' => 'Бит', 'duration' => 160, 'file_path' => 'tracks/music/бит.mp3', 'track_number' => 2],
        ['title' => 'Ритм', 'duration' => 200, 'file_path' => 'tracks/music/ритм.mp3', 'track_number' => 3]
    ]
];

// 3. Поп сингл
$pop_single = [
    'album_name' => 'Хит сезона',
    'artist_name' => 'Поп звезда',
    'album_type' => 'single',
    'cover_image' => 'tracks/covers/Без названия (1).jpeg',
    'release_year' => 2024,
    'description' => 'Новый поп хит',
    'tracks' => [
        ['title' => 'Хит сезона', 'duration' => 210, 'file_path' => 'tracks/music/хит_сезона.mp3', 'track_number' => 1]
    ]
];

echo "<h1>Примеры данных для альбомов</h1>";
echo "<h2>1. Рок альбом</h2>";
echo "<pre>" . json_encode($rock_album, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";

echo "<h2>2. Электронный EP</h2>";
echo "<pre>" . json_encode($electronic_ep, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";

echo "<h2>3. Поп сингл</h2>";
echo "<pre>" . json_encode($pop_single, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";

echo "<h2>Доступные обложки:</h2>";
echo "<ul>";
foreach ($available_covers as $cover) {
    echo "<li><code>$cover</code></li>";
}
echo "</ul>";
?>






















