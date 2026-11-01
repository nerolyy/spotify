<?php
/**
 * Экспортирует данные из MySQL в статические JSON-файлы
 * для офлайн-режима Electron приложения.
 */

require_once __DIR__ . '/../../src/config/db.php';

$targetDir = realpath(__DIR__ . '/../content/api');
if (!$targetDir) {
    fwrite(STDERR, "Не найдена папка app/content/api\n");
    exit(1);
}

$albumsDir = $targetDir . '/albums';
$artistsDir = $targetDir . '/artists';
foreach ([$albumsDir, $artistsDir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

function encode_key(string $value): string {
    // Используем стандартное URL-кодирование без замены % на _
    // Это соответствует encodeURIComponent в JavaScript
    return rawurlencode($value);
}

function normalize_media_path(?string $path, string $fallback = ''): string {
    if (!$path || trim($path) === '') return $fallback;
    $path = str_replace('\\', '/', trim($path));
    // Убираем относительные пути типа ../../ или ../
    $path = preg_replace('#\.\.?/#', '', $path);
    $pos = strpos($path, 'tracks/');
    if ($pos !== false) {
        $path = substr($path, $pos);
    }
    // Убираем начальные ./ и /
    $path = ltrim($path, './');
    // Если путь не начинается с tracks/, добавляем tracks/covers/ для обложек
    if ($fallback && strpos($fallback, 'covers') !== false && strpos($path, 'tracks/') === false) {
        $path = 'tracks/covers/' . basename($path);
    }
    return './' . ltrim($path, './');
}

// Функция для поиска обложки трека в БД
function find_track_cover($db, $trackId, $title, $artist, $album): ?string {
    if (!$db) return null;
    
    // Сначала ищем по track_id
    if ($trackId) {
        $stmt = $db->prepare("SELECT cover FROM tracks WHERE id = ? AND cover IS NOT NULL AND cover != '' AND cover != 'tracks/covers/placeholder.jpg' LIMIT 1");
        $stmt->execute([$trackId]);
        $cover = $stmt->fetchColumn();
        if ($cover) {
            $normalized = normalize_media_path($cover, '');
            if ($normalized && $normalized !== './tracks/covers/placeholder.jpg') {
                return $normalized;
            }
        }
    }
    
    // Затем ищем по title и artist (точное совпадение)
    if ($title && $artist) {
        $stmt = $db->prepare("SELECT cover FROM tracks WHERE title = ? AND artist = ? AND cover IS NOT NULL AND cover != '' AND cover != 'tracks/covers/placeholder.jpg' LIMIT 1");
        $stmt->execute([trim($title), trim($artist)]);
        $cover = $stmt->fetchColumn();
        if ($cover) {
            $normalized = normalize_media_path($cover, '');
            if ($normalized && $normalized !== './tracks/covers/placeholder.jpg') {
                return $normalized;
            }
        }
    }
    
    // Затем ищем по альбому (любой трек из альбома)
    if ($album) {
        $stmt = $db->prepare("SELECT cover FROM tracks WHERE album = ? AND cover IS NOT NULL AND cover != '' AND cover != 'tracks/covers/placeholder.jpg' LIMIT 1");
        $stmt->execute([trim($album)]);
        $cover = $stmt->fetchColumn();
        if ($cover) {
            $normalized = normalize_media_path($cover, '');
            if ($normalized && $normalized !== './tracks/covers/placeholder.jpg') {
                return $normalized;
            }
        }
    }
    
    // Ищем по артисту (любой трек артиста)
    if ($artist) {
        $stmt = $db->prepare("SELECT cover FROM tracks WHERE artist = ? AND cover IS NOT NULL AND cover != '' AND cover != 'tracks/covers/placeholder.jpg' LIMIT 1");
        $stmt->execute([trim($artist)]);
        $cover = $stmt->fetchColumn();
        if ($cover) {
            $normalized = normalize_media_path($cover, '');
            if ($normalized && $normalized !== './tracks/covers/placeholder.jpg') {
                return $normalized;
            }
        }
    }
    
    return null;
}

// Функция для проверки, является ли запись фантомной
function is_phantom_record($title, $artist): bool {
    // Проверяем, содержит ли имя артиста цифры в начале (типа "1715771107_K ai Angel")
    if (preg_match('/^\d+[_\s]/', $artist)) {
        return true;
    }
    // Проверяем, содержит ли название трека только цифры и подчеркивания
    if (preg_match('/^\d+[_\s]/', $title)) {
        return true;
    }
    // Проверяем на "Неизвестный артист" с подозрительными названиями
    if ($artist === 'Неизвестный артист' && preg_match('/^\d+[_\s]/', $title)) {
        return true;
    }
    // Фильтруем треки с только цифрами в названии
    if (preg_match('/^\d+$/', $title)) {
        return true;
    }
    return false;
}

function save_json(string $path, $data): void {
    file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function collect_tracks_from_filesystem(): array {
    $tracksDir = realpath(__DIR__ . '/../../tracks/music');
    if (!$tracksDir) return [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tracksDir, FilesystemIterator::SKIP_DOTS)
    );
    $supported = ['mp3','wav','flac','m4a','aac','ogg'];
    $tracks = [];
    $id = 1;

    foreach ($iterator as $file) {
        if ($file->isDir()) continue;
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, $supported, true)) continue;
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($tracksDir)));
        $relative = ltrim($relative, '/');
        $filePath = 'tracks/music/' . $relative;
        $base = $file->getBasename('.' . $ext);

        $artist = 'Неизвестный артист';
        $title = $base;
        if (strpos($base, ' - ') !== false) {
            [$maybeArtist, $maybeTitle] = explode(' - ', $base, 2);
            if ($maybeArtist && $maybeTitle) {
                $artist = trim($maybeArtist);
                $title = trim($maybeTitle);
            }
        }
        
        // Пропускаем фантомные записи
        if (is_phantom_record($title, $artist)) {
            continue;
        }

        $tracks[] = [
            'id' => $id++,
            'title' => $title,
            'artist' => $artist,
            'album' => $artist,
            'album_type' => 'album',
            'duration' => 0,
            'file_path' => './' . $filePath,
            'cover' => './tracks/covers/placeholder.jpg',
            'src' => './' . $filePath,
            'video_url' => '',
            'explicit' => 0
        ];
    }
    return $tracks;
}

$db = null;
try {
    $db = get_db_connection();
} catch (Throwable $e) {
    fwrite(STDERR, "⚠️  Не удалось подключиться к БД: " . $e->getMessage() . PHP_EOL);
}

$tracks = [];
if ($db) {
    $trackSql = 'SELECT id, title, artist, album, album_type, duration, file_path, cover, video_url, explicit 
                 FROM tracks ORDER BY created_at DESC';
    $stmt = $db->query($trackSql);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Пропускаем фантомные записи
        if (is_phantom_record($row['title'], $row['artist'])) {
            continue;
        }
        
        $filePath = normalize_media_path($row['file_path'], '');
        if (!$filePath || $filePath === './') continue; // Пропускаем треки без файла
        
        // Ищем обложку трека - сначала проверяем, есть ли она в записи
        $coverPath = null;
        if (!empty($row['cover']) && $row['cover'] !== 'tracks/covers/placeholder.jpg') {
            $coverPath = normalize_media_path($row['cover'], '');
            // Проверяем, что путь правильный
            if ($coverPath && $coverPath !== './tracks/covers/placeholder.jpg') {
                // Обложка найдена в записи
            } else {
                $coverPath = null;
            }
        }
        
        // Если обложки нет, ищем её в БД
        if (!$coverPath) {
            $foundCover = find_track_cover($db, (int)$row['id'], $row['title'], $row['artist'], $row['album']);
            if ($foundCover) {
                $coverPath = $foundCover;
            } else {
                $coverPath = './tracks/covers/placeholder.jpg';
            }
        }
        
        $tracks[] = [
            'id' => (int)$row['id'],
            'title' => trim($row['title']),
            'artist' => trim($row['artist']),
            'album' => trim($row['album']),
            'album_type' => $row['album_type'],
            'duration' => (int)($row['duration'] ?? 0),
            'file_path' => $filePath,
            'cover' => $coverPath,
            'src' => $filePath,
            'video_url' => $row['video_url'] ?? '',
            'explicit' => (int)!empty($row['explicit'])
        ];
    }
}

if (!$tracks) {
    fwrite(STDERR, "Использую файловую систему для создания каталога...\n");
    $tracks = collect_tracks_from_filesystem();
}

if (!$tracks) {
    fwrite(STDERR, "Не удалось собрать треки — офлайн JSON не создан.\n");
    exit(0);
}

save_json($targetDir . '/tracks.json', ['tracks' => $tracks]);

// Готовим агрегированные данные по альбомам и артистам
$albumsMap = [];
$artistsMap = [];

foreach ($tracks as $track) {
    // Пропускаем фантомные записи при агрегации
    if (is_phantom_record($track['title'], $track['artist'])) {
        continue;
    }
    
    // Используем оригинальное имя для кодирования, чтобы соответствовать JavaScript
    $albumKey = encode_key($track['album']);
    
    if (!isset($albumsMap[$albumKey])) {
        // Ищем обложку альбома
        $albumCover = $track['cover'];
        if ($albumCover === './tracks/covers/placeholder.jpg' && $db) {
            $stmt = $db->prepare("SELECT cover FROM tracks WHERE album = ? AND cover IS NOT NULL AND cover != '' AND cover != 'tracks/covers/placeholder.jpg' LIMIT 1");
            $stmt->execute([$track['album']]);
            $foundCover = $stmt->fetchColumn();
            if ($foundCover) {
                $albumCover = './' . normalize_media_path($foundCover, '');
            }
        }
        
        $albumsMap[$albumKey] = [
            'title' => $track['album'],
            'artist' => $track['artist'],
            'album_type' => $track['album_type'],
            'cover' => $albumCover,
            'tracks' => [],
            'total_duration' => 0
        ];
    }
    $albumsMap[$albumKey]['tracks'][] = [
        'id' => $track['id'],
        'title' => $track['title'],
        'artist' => $track['artist'],
        'duration' => $track['duration'],
        'src' => $track['src'],
        'cover' => $track['cover'],
        'video_url' => $track['video_url'],
        'explicit' => $track['explicit']
    ];
    $albumsMap[$albumKey]['total_duration'] += $track['duration'];

    // Используем оригинальное имя артиста для кодирования
    $artistKey = encode_key($track['artist']);
    if (!isset($artistsMap[$artistKey])) {
        // Ищем обложку артиста
        $artistCover = $track['cover'];
        if ($artistCover === './tracks/covers/placeholder.jpg' && $db) {
            $stmt = $db->prepare("SELECT cover FROM tracks WHERE artist = ? AND cover IS NOT NULL AND cover != '' AND cover != 'tracks/covers/placeholder.jpg' LIMIT 1");
            $stmt->execute([$track['artist']]);
            $foundCover = $stmt->fetchColumn();
            if ($foundCover) {
                $artistCover = './' . normalize_media_path($foundCover, '');
            }
        }
        
        $artistsMap[$artistKey] = [
            'name' => $track['artist'],
            'cover' => $artistCover,
            'total_tracks' => 0,
            'albums' => [],
            'tracks' => []
        ];
    }
    $artistsMap[$artistKey]['total_tracks'] += 1;
    $artistsMap[$artistKey]['tracks'][] = [
        'id' => $track['id'],
        'title' => $track['title'],
        'album' => $track['album'],
        'duration' => $track['duration'],
        'src' => $track['src'],
        'cover' => $track['cover'],
        'explicit' => $track['explicit']
    ];
    $artistsMap[$artistKey]['albums'][$albumKey] = [
        'title' => $track['album'],
        'cover' => $albumsMap[$albumKey]['cover'],
        'track_count' => isset($artistsMap[$artistKey]['albums'][$albumKey])
            ? $artistsMap[$artistKey]['albums'][$albumKey]['track_count'] + 1
            : 1
    ];
}

// Сохраняем список альбомов
$albumCards = [];
foreach ($albumsMap as $key => $album) {
    $albumCards[] = [
        'slug' => $key,
        'title' => $album['title'],
        'artist' => $album['artist'],
        'cover' => $album['cover'],
        'track_count' => count($album['tracks'])
    ];

    save_json($albumsDir . '/' . $key . '.json', [
        'title' => $album['title'],
        'artist' => $album['artist'],
        'cover' => $album['cover'],
        'album_type' => $album['album_type'],
        'total_duration' => $album['total_duration'],
        'tracks' => $album['tracks']
    ]);
}
save_json($targetDir . '/all_albums.json', ['albums' => $albumCards]);

// Сохраняем артистов
$artistCards = [];
foreach ($artistsMap as $key => $artist) {
    $artistCards[] = [
        'slug' => $key,
        'name' => $artist['name'],
        'cover' => $artist['cover'],
        'track_count' => $artist['total_tracks']
    ];
    $albums = array_values($artist['albums']);
    usort($albums, fn($a, $b) => strcmp($a['title'], $b['title']));
    $topTracks = array_slice($artist['tracks'], 0, 10);

    save_json($artistsDir . '/' . $key . '.json', [
        'name' => $artist['name'],
        'verified' => true,
        'monthly_listeners' => rand(100000, 5000000),
        'cover' => $artist['cover'],
        'total_tracks' => $artist['total_tracks'],
        'total_albums' => count($artist['albums']),
        'total_duration' => array_sum(array_column($artist['tracks'], 'duration')),
        'top_tracks' => $topTracks,
        'albums' => $albums,
        'tracks' => $artist['tracks']
    ]);
}

// Главная страница
$homeData = [
    'tracks' => array_slice($tracks, 0, 20),
    'albums' => array_slice($albumCards, 0, 12),
    'artists' => array_slice($artistCards, 0, 12),
    'favorites' => array_slice($tracks, 0, 8),
    'mixes' => array_slice($tracks, 8, 12)
];
save_json($targetDir . '/home.json', $homeData);
save_json($targetDir . '/home_windows.json', $homeData);

// Поисковой индекс
$searchData = [
    'tracks' => $tracks,
    'artists' => $artistCards,
    'albums' => $albumCards
];
save_json($targetDir . '/search.json', $searchData);

// Дополнительные статичные файлы
save_json($targetDir . '/likes.json', ['tracks' => [], 'albums' => []]);
save_json($targetDir . '/windows_likes.json', ['tracks' => [], 'albums' => []]);
save_json($targetDir . '/user.json', ['user' => null, 'authenticated' => false]);
save_json($targetDir . '/windows_auth.json', ['user' => null, 'authenticated' => false]);
save_json($targetDir . '/login.json', ['success' => false, 'error' => 'Авторизация недоступна в офлайн-режиме']);
save_json($targetDir . '/playlists.json', ['playlists' => []]);
save_json($targetDir . '/library.json', [
    'tracks' => $tracks,
    'albums' => $albumCards,
    'artists' => $artistCards
]);

echo "Офлайн JSON успешно сгенерирован.\n";
