<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Требуется авторизация']);
    exit;
}

$trackCols = 'id, title, artist, album, album_type, duration, file_path, cover, video_url, explicit, genre';

function muzic2_home_cache_path(): string
{
    $dir = __DIR__ . '/../../tmp/cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir . '/home_windows_v1.json';
}

function muzic2_home_cache_read(int $ttlSec): ?string
{
    $path = muzic2_home_cache_path();
    if (!is_file($path)) {
        return null;
    }
    $mtime = @filemtime($path);
    if (!$mtime || (time() - $mtime) > $ttlSec) {
        return null;
    }
    $raw = @file_get_contents($path);
    return ($raw !== false && $raw !== '') ? $raw : null;
}

function muzic2_home_cache_write(string $json): void
{
    $path = muzic2_home_cache_path();
    @file_put_contents($path, $json, LOCK_EX);
}

/**
 * Одним запросом по списку id (вместо сотен SELECT ... WHERE id = ?).
 */
function muzic2_fetch_tracks_by_ids(PDO $pdo, string $cols, array $ids): array
{
    $ids = array_values(array_unique(array_map('intval', array_filter($ids))));
    if ($ids === []) {
        return [];
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT $cols FROM tracks WHERE id IN ($ph)");
    $st->execute($ids);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Случайные треки: батч id + один IN-запрос (быстро на больших таблицах).
 */
function muzic2_random_tracks_fast(PDO $pdo, string $cols, int $need): array
{
    $stats = $pdo->query('SELECT MIN(id) AS mn, MAX(id) AS mx, COUNT(*) AS cnt FROM tracks')->fetch(PDO::FETCH_ASSOC);
    $cnt = (int)($stats['cnt'] ?? 0);
    if ($cnt === 0) {
        return [];
    }
    if ($cnt <= $need) {
        $stmt = $pdo->query("SELECT $cols FROM tracks ORDER BY id ASC");

        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }
    $mn = (int)($stats['mn'] ?? 1);
    $mx = (int)($stats['mx'] ?? 1);
    if ($mn > $mx) {
        return [];
    }
    $cap = min(400, max(80, $need * 4));
    $ids = [];
    $guard = 0;
    while (count($ids) < $cap && $guard < $cap * 4) {
        $guard++;
        $ids[random_int($mn, $mx)] = true;
    }
    $rows = muzic2_fetch_tracks_by_ids($pdo, $cols, array_keys($ids));
    shuffle($rows);
    if (count($rows) >= $need) {
        return array_slice($rows, 0, $need);
    }
    $lim = max($need, 80);
    $st = $pdo->query("SELECT $cols FROM tracks ORDER BY id DESC LIMIT $lim");
    $fallback = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    shuffle($fallback);

    return array_slice($fallback, 0, $need);
}

/**
 * Лайкнутые треки текущего пользователя (секция «Любимые треки» на главной).
 */
function muzic2_home_fetch_favorites(PDO $pdo, int $userId, int $limit = 4): array
{
    if ($userId <= 0 || $limit < 1) {
        return [];
    }
    $lim = (int) $limit;
    $sqlWithFeats = '
        SELECT t.id, t.title, t.artist, t.album, t.album_type, t.duration, t.file_path, t.cover, t.video_url, t.explicit,
        (SELECT GROUP_CONCAT(ta.artist ORDER BY ta.artist SEPARATOR ", ")
         FROM track_artists ta WHERE ta.track_id = t.id AND ta.role = "featured") AS feats
        FROM likes l
        INNER JOIN tracks t ON l.track_id = t.id
        WHERE l.user_id = ?
        ORDER BY l.created_at DESC
        LIMIT ' . $lim;
    $sqlSimple = '
        SELECT t.id, t.title, t.artist, t.album, t.album_type, t.duration, t.file_path, t.cover, t.video_url, t.explicit, NULL AS feats
        FROM likes l
        INNER JOIN tracks t ON l.track_id = t.id
        WHERE l.user_id = ?
        ORDER BY l.created_at DESC
        LIMIT ' . $lim;
    try {
        $stmt = $pdo->prepare($sqlWithFeats);
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $stmt = $pdo->prepare($sqlSimple);
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

function muzic2_home_attach_favorites_and_echo(PDO $pdo, array $data, int $userId): void
{
    $data['favorites'] = muzic2_home_fetch_favorites($pdo, $userId);
    $payload = json_encode($data);
    if ($payload === false) {
        throw new RuntimeException('json_encode_failed');
    }
    echo $payload;
}

function muzic2_home_daily_seed(string $salt = ''): int
{
    return (int) sprintf('%u', crc32(date('Y-m-d') . '|' . $salt));
}

function muzic2_seeded_shuffle(array &$arr, int $seed): void
{
    if ($arr === []) {
        return;
    }
    mt_srand($seed);
    for ($i = count($arr) - 1; $i > 0; $i--) {
        $j = mt_rand(0, $i);
        [$arr[$i], $arr[$j]] = [$arr[$j], $arr[$i]];
    }
}

function muzic2_ensure_mood_tags_table(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS mood_tags (
            id INT AUTO_INCREMENT PRIMARY KEY,
            track_id INT NOT NULL,
            mood VARCHAR(50) NOT NULL,
            confidence DECIMAL(3,2) DEFAULT 0.5,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_track_id (track_id),
            INDEX idx_mood (mood)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        // ignore
    }
}

function muzic2_fetch_mood_tracks(PDO $pdo, string $mood, int $limit, string $trackCols): array
{
    muzic2_ensure_mood_tags_table($pdo);
    $lim = max(1, (int) $limit);
    $fetchLimit = min(120, max($lim * 4, 40));

    $tCols = implode(', ', array_map(static fn (string $c): string => 't.' . trim($c), explode(',', $trackCols)));
    $sql = "SELECT $tCols, mt.confidence AS mood_confidence
            FROM tracks t
            LEFT JOIN mood_tags mt ON t.id = mt.track_id AND mt.mood = ?
            WHERE mt.mood = ? OR (
                (? = 'energetic' AND (
                    t.title LIKE '%rap%' OR t.title LIKE '%hip%' OR t.title LIKE '%rock%' OR
                    t.title LIKE '%энерг%' OR t.title LIKE '%драйв%'
                )) OR
                (? = 'calm' AND (
                    t.title LIKE '%lo-fi%' OR t.title LIKE '%chill%' OR t.title LIKE '%ambient%' OR
                    t.title LIKE '%спокой%' OR t.title LIKE '%медлен%'
                )) OR
                (? = 'sad' AND (
                    t.title LIKE '%sad%' OR t.title LIKE '%depression%' OR t.title LIKE '%lonely%' OR
                    t.title LIKE '%груст%' OR t.title LIKE '%одинок%'
                )) OR
                (? = 'happy' AND (
                    t.title LIKE '%happy%' OR t.title LIKE '%joy%' OR t.title LIKE '%party%' OR
                    t.title LIKE '%радост%' OR t.title LIKE '%весел%'
                )) OR
                (? = 'romantic' AND (
                    t.title LIKE '%love%' OR t.title LIKE '%romance%' OR t.title LIKE '%heart%' OR
                    t.title LIKE '%любов%' OR t.title LIKE '%роман%'
                )) OR
                (? = 'workout' AND (
                    t.title LIKE '%workout%' OR t.title LIKE '%gym%' OR t.title LIKE '%train%' OR
                    t.title LIKE '%тренир%' OR t.title LIKE '%спорт%'
                )) OR
                (? = 'focus' AND (
                    t.title LIKE '%focus%' OR t.title LIKE '%study%' OR t.title LIKE '%concentrate%' OR
                    t.title LIKE '%концентр%' OR t.title LIKE '%учеба%'
                ))
            )
            ORDER BY (mt.confidence IS NOT NULL) DESC, mt.confidence DESC, t.id DESC
            LIMIT $fetchLimit";

    $tracks = [];
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$mood, $mood, $mood, $mood, $mood, $mood, $mood, $mood, $mood]);
        $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $tracks = [];
    }

    foreach ($tracks as &$row) {
        unset($row['mood_confidence']);
    }
    unset($row);

    if (count($tracks) < $lim) {
        $seen = [];
        foreach ($tracks as $row) {
            $seen[(int) ($row['id'] ?? 0)] = true;
        }
        $extra = muzic2_random_tracks_fast($pdo, $trackCols, $lim + 12);
        foreach ($extra as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $tracks[] = $row;
            if (count($tracks) >= $lim) {
                break;
            }
        }
    }

    muzic2_seeded_shuffle($tracks, muzic2_home_daily_seed($mood));

    return array_slice($tracks, 0, $lim);
}

function muzic2_home_fetch_mood_mixes(PDO $pdo, string $trackCols, int $tracksPerMix = 10): array
{
    $presets = [
        ['mood' => 'energetic', 'title' => 'Утренний заряд', 'subtitle' => 'Энергия и драйв'],
        ['mood' => 'calm', 'title' => 'Спокойный вечер', 'subtitle' => 'Расслабление и чилл'],
        ['mood' => 'workout', 'title' => 'Для тренировки', 'subtitle' => 'Бит и мотивация'],
    ];
    $mixes = [];
    foreach ($presets as $preset) {
        $tracks = muzic2_fetch_mood_tracks($pdo, $preset['mood'], $tracksPerMix, $trackCols);
        $mixes[] = [
            'title' => $preset['title'],
            'subtitle' => $preset['subtitle'],
            'mood' => $preset['mood'],
            'cover' => $tracks[0]['cover'] ?? 'tracks/covers/placeholder.jpg',
            'track_count' => count($tracks),
            'tracks' => $tracks,
        ];
    }

    return $mixes;
}

try {
    $pdo = get_db_connection();
    try { $pdo->exec("ALTER TABLE tracks ADD COLUMN genre VARCHAR(120) NULL"); } catch (Throwable $e) { /* ignore */ }
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    // Short cache for first-screen speedup; favorites are always per-user.
    $cached = muzic2_home_cache_read(25);
    if ($cached !== null) {
        $data = json_decode($cached, true);
        if (is_array($data)) {
            muzic2_home_attach_favorites_and_echo($pdo, $data, $userId);
            exit;
        }
    }

    $pool = muzic2_random_tracks_fast($pdo, $trackCols, 20);
    $tracks = array_slice($pool, 0, 8);
    $mixes = muzic2_home_fetch_mood_mixes($pdo, $trackCols);

    $seenAlbums = [];
    $albums = [];
    foreach ($pool as $row) {
        $al = isset($row['album']) ? trim((string) $row['album']) : '';
        if ($al === '') {
            continue;
        }
        if (isset($seenAlbums[$al])) {
            continue;
        }
        $seenAlbums[$al] = true;
        $albums[] = [
            'album' => $al,
            'artist' => $row['artist'] ?? '',
            'album_type' => $row['album_type'] ?? '',
            'cover' => $row['cover'] ?? '',
        ];
        if (count($albums) >= 6) {
            break;
        }
    }
    if (count($albums) < 6) {
        // Без GROUP BY по всей таблице: последние строки + уникальность в PHP
        $st = $pdo->query("SELECT album, artist, album_type, cover FROM tracks WHERE album IS NOT NULL AND TRIM(album) <> '' ORDER BY id DESC LIMIT 150");
        $extra = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        shuffle($extra);
        foreach ($extra as $row) {
            $al = trim((string) ($row['album'] ?? ''));
            if ($al === '' || isset($seenAlbums[$al])) {
                continue;
            }
            $seenAlbums[$al] = true;
            $albums[] = [
                'album' => $al,
                'artist' => $row['artist'] ?? '',
                'album_type' => $row['album_type'] ?? '',
                'cover' => $row['cover'] ?? '',
            ];
            if (count($albums) >= 6) {
                break;
            }
        }
    }

    $artists = [];
    try {
        $artStmt = $pdo->query('SELECT name AS artist, cover FROM artists LIMIT 80');
        $artPool = $artStmt ? $artStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        shuffle($artPool);
        $artists = array_slice($artPool, 0, 6);
    } catch (Throwable $e) {
        $artists = [];
    }

    if (count($artists) < 6) {
        $seenA = [];
        foreach ($artists as $a) {
            $nm = trim((string) ($a['artist'] ?? ''));
            if ($nm !== '') {
                $seenA[$nm] = true;
            }
        }
        $need = 6 - count($artists);
        $st = $pdo->query("SELECT artist, cover FROM tracks WHERE artist IS NOT NULL AND TRIM(artist) <> '' ORDER BY id DESC LIMIT 120");
        $taPool = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        shuffle($taPool);
        foreach ($taPool as $row) {
            $nm = trim((string) ($row['artist'] ?? ''));
            if ($nm === '' || isset($seenA[$nm])) {
                continue;
            }
            $seenA[$nm] = true;
            $artists[] = $row;
            if (count($artists) >= 6) {
                break;
            }
        }
    }

    $shared = [
        'tracks' => $tracks,
        'albums' => $albums,
        'artists' => $artists,
        'mixes' => $mixes,
    ];
    $cachePayload = json_encode($shared);
    if ($cachePayload === false) {
        throw new RuntimeException('json_encode_failed');
    }
    muzic2_home_cache_write($cachePayload);
    muzic2_home_attach_favorites_and_echo($pdo, $shared, $userId);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
