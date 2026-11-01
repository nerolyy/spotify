<?php
declare(strict_types=1);

/**
 * Bulk import helper:
 * - ensures tracks for all audio files in tracks/music
 * - links .lrc lyrics files to tracks
 * - links video files to tracks.video_url
 *
 * Usage:
 *   php scripts/import_tracks_lyrics_videos.php
 *   php scripts/import_tracks_lyrics_videos.php "C:\MAMP\htdocs\muzic2\tracks\music" "C:\MAMP\htdocs\muzic2\tracks\videos"
 */

require_once __DIR__ . '/../src/config/db.php';

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Cannot resolve project root.\n");
    exit(1);
}

$args = $argv;
array_shift($args); // script

$opt = [
    'host' => '127.0.0.1',
    'port' => 8889,
    'user' => 'root',
    'pass' => 'root',
    'db'   => 'muzic2',
];

$positional = [];
for ($i = 0; $i < count($args); $i++) {
    $a = (string)$args[$i];
    if (strpos($a, '--') === 0) {
        $k = substr($a, 2);
        if (array_key_exists($k, $opt) && isset($args[$i + 1])) {
            $opt[$k] = (string)$args[$i + 1];
            $i++;
            continue;
        }
    }
    $positional[] = $a;
}

$musicDir = $positional[0] ?? ($root . DIRECTORY_SEPARATOR . 'tracks' . DIRECTORY_SEPARATOR . 'music');
$videosDir = $positional[1] ?? ($root . DIRECTORY_SEPARATOR . 'tracks' . DIRECTORY_SEPARATOR . 'videos');

$musicDir = rtrim((string)$musicDir, "\\/");
$videosDir = rtrim((string)$videosDir, "\\/");

if (!is_dir($musicDir)) {
    fwrite(STDERR, "Music directory not found: {$musicDir}\n");
    exit(1);
}

function normalize_name(string $v): string
{
    $v = mb_strtolower($v, 'UTF-8');
    $v = str_replace(['_', '-', '.', '(', ')', '[', ']', '{', '}', ',', '"', "'"], ' ', $v);
    $v = preg_replace('/\s+/u', ' ', $v);
    return trim((string)$v);
}

function rel_from_root(string $root, string $absolute): string
{
    $root = str_replace('\\', '/', $root);
    $absolute = str_replace('\\', '/', $absolute);
    if (strpos($absolute, $root) === 0) {
        return ltrim(substr($absolute, strlen($root)), '/');
    }
    return ltrim($absolute, '/');
}

function list_files_recursive(string $dir, array $exts): array
{
    $result = [];
    if (!is_dir($dir)) return $result;
    $exts = array_map('strtolower', $exts);
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile()) continue;
        $ext = strtolower((string)$file->getExtension());
        if (in_array($ext, $exts, true)) {
            $result[] = $file->getPathname();
        }
    }
    return $result;
}

function split_artist_title(string $baseName): array
{
    $raw = trim($baseName);
    $raw = preg_replace('/\s*\(SkySound\.cc\)\s*/iu', ' ', $raw);
    $raw = preg_replace('/\s*_corrected\s*$/iu', '', (string)$raw);
    $raw = preg_replace('/\s+/u', ' ', (string)$raw);
    $parts = preg_split('/\s*-\s*/u', (string)$raw, 2);
    if (is_array($parts) && count($parts) === 2) {
        $artist = trim((string)$parts[0]);
        $title = trim((string)$parts[1]);
        if ($artist !== '' && $title !== '') {
            return [$artist, $title];
        }
    }
    return ['Неизвестный артист', $raw !== '' ? $raw : 'Без названия'];
}

try {
    // Prefer explicit DSN from script args; fallback to app-level connection helper.
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            (string)$opt['host'],
            (int)$opt['port'],
            (string)$opt['db']
        );
        $db = new PDO($dsn, (string)$opt['user'], (string)$opt['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 3,
        ]);
    } catch (Throwable $e) {
        $db = get_db_connection();
    }
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ensure lyrics table exists for lrc import.
    $db->exec("CREATE TABLE IF NOT EXISTS lyrics (
        track_id INT PRIMARY KEY,
        lrc MEDIUMTEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $audioExts = ['mp3', 'wav', 'm4a', 'flac', 'aac', 'ogg'];
    $videoExts = ['mp4', 'webm', 'mov', 'avi', 'mkv', 'm4v'];

    $audioFiles = list_files_recursive($musicDir, $audioExts);
    $lrcFiles = list_files_recursive($musicDir, ['lrc']);
    $videoFiles = list_files_recursive($videosDir, $videoExts);

    // Also link videos that were put directly into tracks/music.
    $videoFiles = array_merge($videoFiles, list_files_recursive($musicDir, $videoExts));
    $videoFiles = array_values(array_unique($videoFiles));

    $db->beginTransaction();

    $addedTracks = 0;
    $existingTracks = 0;
    $linkedLyrics = 0;
    $linkedVideos = 0;

    // 1) Ensure tracks for all audio files.
    $findByPath = $db->prepare('SELECT id FROM tracks WHERE file_path = ? LIMIT 1');
    $insertTrack = $db->prepare('INSERT INTO tracks (title, artist, album, album_type, duration, file_path, cover) VALUES (?, ?, ?, ?, ?, ?, ?)');

    foreach ($audioFiles as $absPath) {
        $relative = rel_from_root($root, $absPath);
        $relative = str_replace('\\', '/', $relative);

        $findByPath->execute([$relative]);
        $row = $findByPath->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['id'])) {
            $existingTracks++;
            continue;
        }

        $base = pathinfo($absPath, PATHINFO_FILENAME);
        [$artist, $title] = split_artist_title((string)$base);
        $album = basename((string)dirname($absPath));
        if ($album === '' || $album === '.' || $album === 'music') {
            $album = 'Синглы';
        }

        $insertTrack->execute([
            $title,
            $artist,
            $album,
            'single',
            0,
            $relative,
            'tracks/covers/placeholder.jpg',
        ]);
        $addedTracks++;
    }

    // Build lookups after import.
    $tracks = $db->query('SELECT id, title, artist, file_path FROM tracks')->fetchAll(PDO::FETCH_ASSOC);
    $byFileBase = [];
    $byArtistTitle = [];
    foreach ($tracks as $t) {
        $id = (int)($t['id'] ?? 0);
        if ($id <= 0) continue;
        $path = (string)($t['file_path'] ?? '');
        $base = normalize_name(pathinfo($path, PATHINFO_FILENAME));
        if ($base !== '' && !isset($byFileBase[$base])) {
            $byFileBase[$base] = $id;
        }
        $key = normalize_name((string)$t['artist'] . ' - ' . (string)$t['title']);
        if ($key !== '' && !isset($byArtistTitle[$key])) {
            $byArtistTitle[$key] = $id;
        }
    }

    // 2) Attach lyrics from .lrc.
    $upsertLyrics = $db->prepare("
        INSERT INTO lyrics (track_id, lrc)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE lrc = VALUES(lrc), updated_at = CURRENT_TIMESTAMP
    ");
    foreach ($lrcFiles as $lrcAbs) {
        $base = normalize_name(pathinfo($lrcAbs, PATHINFO_FILENAME));
        if ($base === '') continue;
        $trackId = $byFileBase[$base] ?? $byArtistTitle[$base] ?? null;
        if (!$trackId) continue;

        $lrc = @file_get_contents($lrcAbs);
        if ($lrc === false || trim($lrc) === '') continue;
        $upsertLyrics->execute([(int)$trackId, $lrc]);
        $linkedLyrics++;
    }

    // 3) Attach videos to tracks.video_url.
    $updVideo = $db->prepare('UPDATE tracks SET video_url = ? WHERE id = ?');
    foreach ($videoFiles as $videoAbs) {
        $base = normalize_name(pathinfo($videoAbs, PATHINFO_FILENAME));
        if ($base === '') continue;
        $trackId = $byFileBase[$base] ?? $byArtistTitle[$base] ?? null;
        if (!$trackId) continue;
        $videoRel = rel_from_root($root, $videoAbs);
        $videoRel = str_replace('\\', '/', $videoRel);
        $updVideo->execute([$videoRel, (int)$trackId]);
        $linkedVideos++;
    }

    $db->commit();

    echo "Done.\n";
    echo "Tracks added: {$addedTracks}\n";
    echo "Tracks already existed: {$existingTracks}\n";
    echo "Lyrics linked: {$linkedLyrics}\n";
    echo "Videos linked: {$linkedVideos}\n";
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}

