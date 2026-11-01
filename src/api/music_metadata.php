<?php
/**
 * API для получения метаданных музыки из бесплатных источников
 * Использует: iTunes, MusicBrainz, Last.fm, Cover Art Archive
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

/**
 * Получить метаданные трека из iTunes API
 */
function getMetadataFromiTunes($title, $artist, $limit = 1) {
    $query = urlencode("$artist $title");
    $url = "https://itunes.apple.com/search?term=$query&media=music&limit=" . intval($limit);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Muzic2/1.0');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        return null;
    }
    
    $data = json_decode($response, true);
    if (empty($data['results'])) {
        return null;
    }
    
    // Если limit = 1, возвращаем один результат (для обратной совместимости)
    if ($limit === 1) {
        $result = $data['results'][0];
        return [
            'title' => $result['trackName'] ?? $title,
            'artist' => $result['artistName'] ?? $artist,
            'album' => $result['collectionName'] ?? '',
            'cover' => str_replace('100x100', '600x600', $result['artworkUrl100'] ?? ''),
            'duration' => isset($result['trackTimeMillis']) ? round($result['trackTimeMillis'] / 1000) : 0,
            'genre' => $result['primaryGenreName'] ?? '',
            'year' => isset($result['releaseDate']) ? date('Y', strtotime($result['releaseDate'])) : '',
            'source' => 'iTunes',
            'trackNumber' => $result['trackNumber'] ?? null,
            'discNumber' => $result['discNumber'] ?? null,
            'collectionId' => $result['collectionId'] ?? null,
            'explicit' => isset($result['trackExplicitness']) && $result['trackExplicitness'] === 'explicit' ? 1 : 0,
            'previewUrl' => $result['previewUrl'] ?? '',
            'itunesUrl' => $result['trackViewUrl'] ?? ''
        ];
    }
    
    // Возвращаем массив всех результатов
    $tracks = [];
    foreach ($data['results'] as $result) {
        $tracks[] = [
            'title' => $result['trackName'] ?? '',
            'artist' => $result['artistName'] ?? '',
            'album' => $result['collectionName'] ?? '',
            'cover' => str_replace('100x100', '600x600', $result['artworkUrl100'] ?? ''),
            'duration' => isset($result['trackTimeMillis']) ? round($result['trackTimeMillis'] / 1000) : 0,
            'genre' => $result['primaryGenreName'] ?? '',
            'year' => isset($result['releaseDate']) ? date('Y', strtotime($result['releaseDate'])) : '',
            'source' => 'iTunes',
            'trackId' => $result['trackId'] ?? null,
            'trackNumber' => $result['trackNumber'] ?? null,
            'discNumber' => $result['discNumber'] ?? null,
            'collectionId' => $result['collectionId'] ?? null,
            'explicit' => isset($result['trackExplicitness']) && $result['trackExplicitness'] === 'explicit' ? 1 : 0,
            'previewUrl' => $result['previewUrl'] ?? '',
            'itunesUrl' => $result['trackViewUrl'] ?? ''
        ];
    }
    
    return $tracks;
}

/**
 * Получить все треки артиста из iTunes API
 */
function getArtistTracksFromiTunes($artist, $maxResults = 2000, $albumName = '') {
    $artistLower = mb_strtolower($artist, 'UTF-8');
    $tracks = [];
    $trackIds = [];
    
    $perPage = 200; // максимальное значение, которое поддерживает iTunes API
    $totalFetched = 0;
    $page = 0;
    $maxPages = max(1, ceil($maxResults / $perPage));
    
    // Формируем поисковый запрос: если указан альбом, ищем по артисту и альбому
    $searchTerm = $albumName ? "$artist $albumName" : $artist;
    
    while ($page < $maxPages) {
        $offset = $page * $perPage;
        $query = http_build_query([
            'term' => $searchTerm,
            'media' => 'music',
            'entity' => 'song',
            'attribute' => $albumName ? 'allArtistTerm' : 'artistTerm',
            'limit' => $perPage,
            'offset' => $offset
        ]);
        
        $url = "https://itunes.apple.com/search?$query";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Muzic2/1.0');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
            break;
    }
    
    $data = json_decode($response, true);
    $results = $data['results'] ?? [];
    if (empty($results)) {
        break;
    }
        
        foreach ($results as $result) {
            // Берем только треки
            if (($result['kind'] ?? '') !== 'song') {
                continue;
            }
            
        $resultArtist = mb_strtolower($result['artistName'] ?? '', 'UTF-8');
            if (strpos($resultArtist, $artistLower) === false && strpos($artistLower, $resultArtist) === false) {
                continue;
            }
            
            $currentTrackId = $result['trackId'] ?? null;
            if ($currentTrackId && isset($trackIds[$currentTrackId])) {
                continue; // дубликат
            }
            
            // Фильтруем по альбому, если указан
            if ($albumName) {
                $resultAlbum = mb_strtolower($result['collectionName'] ?? '', 'UTF-8');
                $albumNameLower = mb_strtolower($albumName, 'UTF-8');
                if (strpos($resultAlbum, $albumNameLower) === false && strpos($albumNameLower, $resultAlbum) === false) {
                    continue;
                }
            }
            
            $tracks[] = [
                'title' => $result['trackName'] ?? '',
                'artist' => $result['artistName'] ?? '',
                'album' => $result['collectionName'] ?? '',
                'cover' => str_replace('100x100', '600x600', $result['artworkUrl100'] ?? ''),
                'duration' => isset($result['trackTimeMillis']) ? round($result['trackTimeMillis'] / 1000) : 0,
                'genre' => $result['primaryGenreName'] ?? '',
                'year' => isset($result['releaseDate']) ? date('Y', strtotime($result['releaseDate'])) : '',
                'source' => 'iTunes',
                'trackId' => $currentTrackId,
                'previewUrl' => $result['previewUrl'] ?? '',
                'trackNumber' => $result['trackNumber'] ?? null,
                'discNumber' => $result['discNumber'] ?? null,
                'collectionId' => $result['collectionId'] ?? null,
                'collectionPrice' => $result['collectionPrice'] ?? null,
                'currency' => $result['currency'] ?? null,
                'country' => $result['country'] ?? null,
                'explicit' => isset($result['trackExplicitness']) && $result['trackExplicitness'] === 'explicit' ? 1 : 0
            ];
            
            if ($currentTrackId) {
                $trackIds[$currentTrackId] = true;
            }
            
            $totalFetched++;
            if ($totalFetched >= $maxResults) {
                break 2;
            }
        }
        
        // Если вернулось меньше чем perPage, больше данных нет
        if (count($results) < $perPage) {
            break;
        }
        
        $page++;
    }
    
    return $tracks;
}

/**
 * Получить треки артиста из Deezer (public API, без ключа)
 */
function getArtistTracksFromDeezer($artist, $maxResults = 500, $albumName = '') {
    $artistLower = mb_strtolower($artist, 'UTF-8');
    $tracks = [];
    $trackIds = [];
    
    // Deezer поддерживает limit до 300 за запрос, но можно делать несколько запросов
    $limit = max(1, min(300, (int)$maxResults));
    
    // Формируем поисковый запрос
    $searchQuery = $albumName ? 'artist:"' . $artist . '" album:"' . $albumName . '"' : 'artist:"' . $artist . '"';
    
    // Делаем несколько запросов для получения большего количества треков
    $totalFetched = 0;
    $index = 0;
    
    while ($totalFetched < $maxResults && $index < 5) { // Максимум 5 запросов (до 1500 треков)
        $query = http_build_query([
            'q' => $searchQuery,
            'limit' => $limit,
            'index' => $index * $limit
        ]);
    
        $url = "https://api.deezer.com/search?$query";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Muzic2/1.0');
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$response) {
            break;
        }
        
        $data = json_decode($response, true);
        if (empty($data['data'])) {
            break;
        }
        
        foreach ($data['data'] as $item) {
            // `artist` и `album` приходят вложенными объектами
            $resultArtist = isset($item['artist']['name']) ? mb_strtolower($item['artist']['name'], 'UTF-8') : '';
            if (strpos($resultArtist, $artistLower) === false && strpos($artistLower, $resultArtist) === false) {
                continue;
            }
            
            // Фильтруем по альбому, если указан
            if ($albumName) {
                $resultAlbum = mb_strtolower($item['album']['title'] ?? '', 'UTF-8');
                $albumNameLower = mb_strtolower($albumName, 'UTF-8');
                if (strpos($resultAlbum, $albumNameLower) === false && strpos($albumNameLower, $resultAlbum) === false) {
                    continue;
                }
            }
            
            $currentTrackId = $item['id'] ?? null;
            if ($currentTrackId && isset($trackIds[$currentTrackId])) {
                continue; // пропускаем дубликаты
            }
            
            $album = $item['album']['title'] ?? '';
            $cover = $item['album']['cover_big'] ?? ($item['album']['cover_medium'] ?? ($item['album']['cover'] ?? ''));
            
            $tracks[] = [
                'title' => $item['title'] ?? '',
                'artist' => $item['artist']['name'] ?? '',
                'album' => $album,
                'cover' => $cover,
                'duration' => isset($item['duration']) ? (int)$item['duration'] : 0,
                'genre' => $item['genre'] ?? ($item['album']['genre'] ?? ''),
                'year' => isset($item['release_date']) ? date('Y', strtotime($item['release_date'])) : (isset($item['album']['release_date']) ? date('Y', strtotime($item['album']['release_date'])) : ''),
                'source' => 'Deezer',
                'trackId' => $currentTrackId,
                'previewUrl' => $item['preview'] ?? '',
                'trackNumber' => $item['track_position'] ?? null,
                'discNumber' => $item['disk_number'] ?? null,
                'albumId' => $item['album']['id'] ?? null,
                'explicit' => isset($item['explicit_lyrics']) && $item['explicit_lyrics'] ? 1 : 0,
                'bpm' => $item['bpm'] ?? null,
                'gain' => $item['gain'] ?? null
            ];
            
            if ($currentTrackId) {
                $trackIds[$currentTrackId] = true;
            }
            
            $totalFetched++;
            if ($totalFetched >= $maxResults) {
                break 2;
            }
        }
        
        // Если вернулось меньше треков, больше данных нет
        if (count($data['data']) < $limit) {
            break;
        }
        
        $index++;
    }
    
    return $tracks;
}

/**
 * Объединить результаты из нескольких источников и удалить дубликаты
 */
function mergeArtistTracks(array $lists, $maxResults = 1000) {
    $seen = [];
    $result = [];
    
    foreach ($lists as $tracks) {
        foreach ($tracks as $track) {
            $title = mb_strtolower(trim($track['title'] ?? ''), 'UTF-8');
            $artist = mb_strtolower(trim($track['artist'] ?? ''), 'UTF-8');
            if ($title === '' || $artist === '') {
                continue;
            }
            $key = $artist . '|' . $title;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $track;
            
            if (count($result) >= $maxResults) {
                return $result;
            }
        }
    }
    
    return $result;
}

/**
 * Получить обложку из Last.fm API
 */
function getCoverFromLastFM($artist, $album) {
    // Нужен API ключ Last.fm (можно получить бесплатно на last.fm/api)
    $apiKey = 'YOUR_LASTFM_API_KEY'; // Замените на свой ключ
    if ($apiKey === 'YOUR_LASTFM_API_KEY') {
        return null; // Пропускаем если ключ не настроен
    }
    
    $query = urlencode("$artist $album");
    $url = "http://ws.audioscrobbler.com/2.0/?method=album.getinfo&api_key=$apiKey&artist=" . urlencode($artist) . "&album=" . urlencode($album) . "&format=json";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if (!$response) return null;
    
    $data = json_decode($response, true);
    if (!empty($data['album']['image'][3]['#text'])) {
        return $data['album']['image'][3]['#text']; // Large размер
    }
    
    return null;
}

/**
 * Получить обложку из Cover Art Archive (MusicBrainz)
 */
function getCoverFromCoverArtArchive($mbid) {
    if (empty($mbid)) return null;
    
    $url = "https://coverartarchive.org/release/$mbid/front-500";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return $url;
    }
    
    return null;
}

/**
 * Получить метаданные из MusicBrainz
 */
function getMetadataFromMusicBrainz($title, $artist) {
    $query = urlencode("recording:\"$title\" AND artist:\"$artist\"");
    $url = "https://musicbrainz.org/ws/2/recording/?query=$query&fmt=json&limit=1";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Muzic2/1.0 (contact@example.com)');
    $response = curl_exec($ch);
    curl_close($ch);
    
    if (!$response) return null;
    
    $data = json_decode($response, true);
    if (empty($data['recordings'])) {
        return null;
    }
    
    $recording = $data['recordings'][0];
    $release = !empty($recording['releases'][0]) ? $recording['releases'][0] : null;
    
    return [
        'title' => $recording['title'] ?? $title,
        'artist' => !empty($recording['artist-credit'][0]['name']) ? $recording['artist-credit'][0]['name'] : $artist,
        'album' => $release['title'] ?? '',
        'mbid' => $release['id'] ?? null,
        'year' => $release['date'] ?? '',
        'source' => 'MusicBrainz'
    ];
}

/**
 * Скачать обложку и сохранить локально
 */
function downloadCover($url, $savePath) {
    if (empty($url)) return false;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $imageData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$imageData) {
        return false;
    }
    
    $dir = dirname($savePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    return file_put_contents($savePath, $imageData) !== false;
}

// Обработка запроса
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST') {
    $action = $input['action'] ?? '';
    
    if ($action === 'get_metadata') {
        $title = trim($input['title'] ?? '');
        $artist = trim($input['artist'] ?? '');
        $album = trim($input['album'] ?? '');
        $getAllTracks = !empty($input['get_all_tracks']) && $input['get_all_tracks'] === true;
        
        if (empty($artist)) {
            http_response_code(400);
            echo json_encode(['error' => 'Артист обязателен']);
            exit;
        }
        
        // Если запрашиваются все треки артиста
        if ($getAllTracks || empty($title)) {
            // Собираем треки из iTunes и Deezer, затем объединяем и убираем дубликаты
            // Увеличиваем лимиты: iTunes до 2000, Deezer до 500
            $itunesTracks = getArtistTracksFromiTunes($artist, 2000, $album);
            $deezerTracks = getArtistTracksFromDeezer($artist, 500, $album);
            $tracks = mergeArtistTracks([$itunesTracks, $deezerTracks], 2500);
            
            if (!empty($tracks)) {
                echo json_encode(['success' => true, 'tracks' => $tracks, 'count' => count($tracks)]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Треки не найдены']);
            }
            exit;
        }
        
        // Обычный поиск одного трека
        $metadata = null;
        $cover = null;
        
        // Пробуем iTunes (самый быстрый и надёжный)
        $metadata = getMetadataFromiTunes($title, $artist, 1);
        
        // Если iTunes не дал результат, пробуем MusicBrainz
        if (!$metadata) {
            $metadata = getMetadataFromMusicBrainz($title, $artist);
        }
        
        // Если есть альбом, пробуем получить обложку из Last.fm
        if (!empty($album) && empty($metadata['cover'])) {
            $cover = getCoverFromLastFM($artist, $album);
            if ($cover) {
                $metadata['cover'] = $cover;
            }
        }
        
        // Если есть MBID, пробуем Cover Art Archive
        if (!empty($metadata['mbid']) && empty($metadata['cover'])) {
            $cover = getCoverFromCoverArtArchive($metadata['mbid']);
            if ($cover) {
                $metadata['cover'] = $cover;
            }
        }
        
        if ($metadata) {
            echo json_encode(['success' => true, 'data' => $metadata]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Метаданные не найдены']);
        }
        exit;
    }
    
    if ($action === 'get_artist_tracks') {
        $artist = trim($input['artist'] ?? '');
        $limit = intval($input['limit'] ?? 1000);
        if ($limit <= 0) {
            $limit = 1000;
        }
        
        if (empty($artist)) {
            http_response_code(400);
            echo json_encode(['error' => 'Артист обязателен']);
            exit;
        }
        
        // Собираем треки из iTunes и Deezer и объединяем
        // Увеличиваем лимиты
        $albumName = trim($input['album'] ?? '');
        $itunesLimit = max($limit, 2000); // Минимум 2000 для iTunes
        $deezerLimit = max(min($limit, 500), 500); // Минимум 500 для Deezer
        $itunesTracks = getArtistTracksFromiTunes($artist, $itunesLimit, $albumName);
        $deezerTracks = getArtistTracksFromDeezer($artist, $deezerLimit, $albumName);
        $tracks = mergeArtistTracks([$itunesTracks, $deezerTracks], max($limit, 2500));
        
        if (!empty($tracks)) {
            echo json_encode(['success' => true, 'tracks' => $tracks, 'count' => count($tracks)]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Треки не найдены']);
        }
        exit;
    }
    
    if ($action === 'download_cover') {
        $url = $input['url'] ?? '';
        $savePath = $input['save_path'] ?? '';
        
        if (empty($url) || empty($savePath)) {
            http_response_code(400);
            echo json_encode(['error' => 'URL и путь сохранения обязательны']);
            exit;
        }
        
        $result = downloadCover($url, $savePath);
        echo json_encode(['success' => $result]);
        exit;
    }
    
    if ($action === 'save_track') {
        require_once __DIR__ . '/../config/db.php';
        
        $title = trim($input['title'] ?? '');
        $artist = trim($input['artist'] ?? '');
        $album = trim($input['album'] ?? '');
        $duration = intval($input['duration'] ?? 0);
        $cover = trim($input['cover'] ?? '');
        $filePath = trim($input['file_path'] ?? '');
        $trackId = intval($input['track_id'] ?? 0);
        
        if (empty($title) || empty($artist)) {
            http_response_code(400);
            echo json_encode(['error' => 'Название и артист обязательны']);
            exit;
        }
        
        // Нормализуем путь к файлу - должен быть в формате tracks/music/filename.mp3
        function normalizeFilePath($path) {
            if (empty($path)) return '';
            
            // Нормализуем разделители
            $path = str_replace('\\', '/', $path);
            
            // Убираем ведущие слэши и /muzic2/
            $path = preg_replace('#^/+muzic2/+#', '', $path);
            $path = ltrim($path, '/');
            
            // Если путь уже начинается с tracks/, возвращаем как есть
            if (strpos($path, 'tracks/') === 0) {
                return $path;
            }
            
            // Если это абсолютный путь, извлекаем относительный
            $root = realpath(__DIR__ . '/../../');
            if ($root && (strpos($path, '/') === 0 || strpos($path, $root) === 0)) {
                $fullPath = realpath($path);
                if ($fullPath && strpos($fullPath, $root) === 0) {
                    $path = substr($fullPath, strlen($root) + 1);
                    // Если получили путь с tracks/, возвращаем
                    if (strpos($path, 'tracks/') === 0) {
                        return $path;
                    }
                }
            }
            
            // Пробуем найти tracks/ в пути
            $idx = strpos($path, 'tracks/');
            if ($idx !== false) {
                return substr($path, $idx);
            }
            
            // Если ничего не помогло, предполагаем что это имя файла в tracks/music/
            return 'tracks/music/' . basename($path);
        }
        
        $filePath = normalizeFilePath($filePath);
        
        try {
            $db = get_db_connection();
            
            // Если указан track_id, обновляем существующий трек
            if ($trackId > 0) {
                $updates = [];
                $params = [];
                
                if (!empty($title)) {
                    $updates[] = 'title = ?';
                    $params[] = $title;
                }
                if (!empty($artist)) {
                    $updates[] = 'artist = ?';
                    $params[] = $artist;
                }
                if (!empty($album)) {
                    $updates[] = 'album = ?';
                    $params[] = $album;
                }
                if ($duration > 0) {
                    $updates[] = 'duration = ?';
                    $params[] = $duration;
                }
                if (!empty($cover)) {
                    // Скачиваем обложку если это URL
                    if (filter_var($cover, FILTER_VALIDATE_URL)) {
                        $coverFileName = md5($trackId . $title) . '.jpg';
                        $coverPath = __DIR__ . '/../../tracks/covers/' . $coverFileName;
                        
                        if (!is_dir(__DIR__ . '/../../tracks/covers/')) {
                            mkdir(__DIR__ . '/../../tracks/covers/', 0755, true);
                        }
                        
                        if (downloadCover($cover, $coverPath)) {
                            $cover = 'tracks/covers/' . $coverFileName;
                        }
                    }
                    $updates[] = 'cover = ?';
                    $params[] = $cover;
                }
                
                if (!empty($updates)) {
                    $params[] = $trackId;
                    $sql = 'UPDATE tracks SET ' . implode(', ', $updates) . ' WHERE id = ?';
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    echo json_encode(['success' => true, 'message' => 'Трек обновлён', 'id' => $trackId]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Нет данных для обновления']);
                }
            } else {
                // Добавляем новый трек
                if (empty($filePath)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Путь к файлу обязателен для нового трека']);
                    exit;
                }
                
                // Проверяем и копируем файл в tracks/music/ если нужно
                $root = realpath(__DIR__ . '/../../');
                $targetPath = $root . '/' . $filePath;
                $targetDir = dirname($targetPath);
                
                // Если файл не находится в tracks/music/, копируем его туда
                if (strpos($filePath, 'tracks/music/') === 0) {
                    // Путь уже правильный, проверяем существует ли файл
                    if (!file_exists($targetPath)) {
                        // Пробуем найти исходный файл по оригинальному пути
                        $originalPath = trim($input['file_path'] ?? '');
                        if ($originalPath && file_exists($originalPath)) {
                            // Создаем директорию если нужно
                            if (!is_dir($targetDir)) {
                                mkdir($targetDir, 0755, true);
                            }
                            // Копируем файл
                            if (copy($originalPath, $targetPath)) {
                                // Файл скопирован
                            } else {
                                http_response_code(500);
                                echo json_encode(['error' => 'Не удалось скопировать файл']);
                                exit;
                            }
                        } else {
                            // Файл не найден, но продолжаем (может быть это URL)
                        }
                    }
                }
                
                // Скачиваем обложку если это URL
                if (!empty($cover) && filter_var($cover, FILTER_VALIDATE_URL)) {
                    $coverFileName = md5($title . $artist) . '.jpg';
                    $coverPath = __DIR__ . '/../../tracks/covers/' . $coverFileName;
                    
                    if (!is_dir(__DIR__ . '/../../tracks/covers/')) {
                        mkdir(__DIR__ . '/../../tracks/covers/', 0755, true);
                    }
                    
                    if (downloadCover($cover, $coverPath)) {
                        $cover = 'tracks/covers/' . $coverFileName;
                    } else {
                        $cover = 'tracks/covers/placeholder.jpg';
                    }
                } elseif (empty($cover)) {
                    $cover = 'tracks/covers/placeholder.jpg';
                }
                
                $stmt = $db->prepare('
                    INSERT INTO tracks (title, artist, album, album_type, duration, file_path, cover, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ');
                $stmt->execute([
                    $title,
                    $artist,
                    $album ?: 'Без альбома',
                    'album',
                    $duration,
                    $filePath,
                    $cover
                ]);
                
                $newId = $db->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Трек добавлен', 'id' => $newId]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Ошибка сохранения: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'search_tracks') {
        $query = trim($input['query'] ?? '');
        $limit = intval($input['limit'] ?? 10);
        
        if (empty($query)) {
            http_response_code(400);
            echo json_encode(['error' => 'Запрос обязателен']);
            exit;
        }
        
        // Поиск через iTunes
        $searchQuery = urlencode($query);
        $url = "https://itunes.apple.com/search?term=$searchQuery&media=music&limit=$limit";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Muzic2/1.0');
        $response = curl_exec($ch);
        curl_close($ch);
        
        if (!$response) {
            http_response_code(500);
            echo json_encode(['error' => 'Ошибка поиска']);
            exit;
        }
        
        $data = json_decode($response, true);
        $tracks = [];
        
        if (!empty($data['results'])) {
            foreach ($data['results'] as $result) {
                if ($result['kind'] === 'song') {
                    $tracks[] = [
                        'title' => $result['trackName'] ?? '',
                        'artist' => $result['artistName'] ?? '',
                        'album' => $result['collectionName'] ?? '',
                        'cover' => str_replace('100x100', '600x600', $result['artworkUrl100'] ?? ''),
                        'duration' => isset($result['trackTimeMillis']) ? round($result['trackTimeMillis'] / 1000) : 0,
                        'preview_url' => $result['previewUrl'] ?? '',
                        'itunes_url' => $result['trackViewUrl'] ?? ''
                    ];
                }
            }
        }
        
        echo json_encode(['success' => true, 'tracks' => $tracks, 'total' => count($tracks)]);
        exit;
    }
}

http_response_code(405);
echo json_encode(['error' => 'Метод не поддерживается']);

