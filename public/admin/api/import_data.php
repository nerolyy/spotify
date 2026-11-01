<?php
require_once __DIR__ . '/../../../src/config/db.php';
header('Content-Type: application/json; charset=utf-8');

function norm_name(string $v): string {
    $v = mb_strtolower($v, 'UTF-8');
    $v = str_replace(['_', '-', '.', '(', ')', '[', ']', '{', '}', ',', '"', "'"], ' ', $v);
    $v = preg_replace('/\s+/u', ' ', $v);
    return trim((string)$v);
}

function rel_from_root(string $root, string $absolute): string {
    $root = str_replace('\\', '/', $root);
    $absolute = str_replace('\\', '/', $absolute);
    if (strpos($absolute, $root) === 0) return ltrim(substr($absolute, strlen($root)), '/');
    return ltrim($absolute, '/');
}

function list_files_recursive(string $dir, array $exts): array {
    $result = [];
    if (!is_dir($dir)) return $result;
    $exts = array_map('strtolower', $exts);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile()) continue;
        $ext = strtolower((string)$file->getExtension());
        if (in_array($ext, $exts, true)) $result[] = $file->getPathname();
    }
    return $result;
}

function split_artist_title(string $baseName): array {
    $raw = trim($baseName);
    $raw = preg_replace('/\s*\(SkySound\.cc\)\s*/iu', ' ', $raw);
    $raw = preg_replace('/\s*_corrected\s*$/iu', '', (string)$raw);
    $raw = str_replace('_', ' ', (string)$raw);
    $raw = preg_replace('/\s+/u', ' ', (string)$raw);
    $raw = trim((string)$raw);
    if ($raw === '') return ['Неизвестный артист', 'Без названия'];

    // Pattern: Artist - Title
    $partsDash = preg_split('/\s*-\s*/u', (string)$raw, 2);
    if (is_array($partsDash) && count($partsDash) === 2) {
        $artist = trim((string)$partsDash[0]);
        $title = trim((string)$partsDash[1]);
        // strip trailing random code chunks like OJEDD9
        $title = preg_replace('/\b[A-Z0-9]{5,}\b$/u', '', (string)$title);
        $title = trim((string)$title);
        if ($artist !== '' && $title !== '') return [$artist, $title];
    }

    // Pattern: Artist1, Artist2, Title
    $partsComma = array_values(array_filter(array_map('trim', explode(',', (string)$raw)), fn($v) => $v !== ''));
    if (count($partsComma) >= 3) {
        $title = (string)array_pop($partsComma);
        $artist = implode(', ', $partsComma);
        if ($artist !== '' && $title !== '') return [$artist, $title];
    }
    if (count($partsComma) === 2) {
        $artist = (string)$partsComma[0];
        $title = (string)$partsComma[1];
        if ($artist !== '' && $title !== '') return [$artist, $title];
    }

    return ['Неизвестный артист', $raw];
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Only POST']);
        exit;
    }

    $action = trim((string)($_GET['action'] ?? ''));
    if ($action === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'action is required']);
        exit;
    }

    $root = realpath(__DIR__ . '/../../../');
    if ($root === false) throw new Exception('Cannot resolve project root');
    $musicDir = $root . '/tracks/music';
    $videosDir = $root . '/tracks/videos';

    $db = get_db_connection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE IF NOT EXISTS lyrics (
        track_id INT PRIMARY KEY,
        lrc MEDIUMTEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    if ($action === 'tracks') {
        $audioFiles = list_files_recursive($musicDir, ['mp3', 'wav', 'm4a', 'flac', 'aac', 'ogg']);
        $findByPath = $db->prepare('SELECT id FROM tracks WHERE file_path = ? LIMIT 1');
        $insertTrack = $db->prepare('INSERT INTO tracks (title, artist, album, album_type, duration, file_path, cover) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $added = 0; $exists = 0;

        $db->beginTransaction();
        foreach ($audioFiles as $absPath) {
            $relative = str_replace('\\', '/', rel_from_root($root, $absPath));
            $findByPath->execute([$relative]);
            if ($findByPath->fetch(PDO::FETCH_ASSOC)) { $exists++; continue; }

            $base = pathinfo($absPath, PATHINFO_FILENAME);
            [$artist, $title] = split_artist_title((string)$base);
            $album = basename((string)dirname($absPath));
            if ($album === '' || $album === '.' || $album === 'music') $album = $title;

            $insertTrack->execute([$title, $artist, $album, 'single', 0, $relative, 'tracks/covers/placeholder.jpg']);
            $added++;
        }
        $db->commit();
        echo json_encode(['success' => true, 'action' => 'tracks', 'added' => $added, 'exists' => $exists, 'total_files' => count($audioFiles)]);
        exit;
    }

    if ($action === 'repair_tracks') {
        $st = $db->query("SELECT id, file_path, artist, title, album FROM tracks WHERE album = 'Синглы' OR artist = 'Неизвестный артист' OR artist REGEXP '^[0-9]+$'");
        $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        $upd = $db->prepare('UPDATE tracks SET artist = ?, title = ?, album = ?, album_type = ? WHERE id = ?');
        $fixed = 0;
        $skipped = 0;

        $db->beginTransaction();
        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            if ($id <= 0) { $skipped++; continue; }
            $filePath = (string)($r['file_path'] ?? '');
            $base = pathinfo($filePath, PATHINFO_FILENAME);
            [$artistParsed, $titleParsed] = split_artist_title((string)$base);
            if ($titleParsed === '' || $titleParsed === 'Без названия') { $skipped++; continue; }
            $artist = $artistParsed !== '' ? $artistParsed : 'Неизвестный артист';
            $title = $titleParsed;
            $album = $title; // для синглов каждый трек отдельный релиз
            $upd->execute([$artist, $title, $album, 'single', $id]);
            $fixed++;
        }
        $db->commit();
        echo json_encode(['success' => true, 'action' => 'repair_tracks', 'fixed' => $fixed, 'skipped' => $skipped, 'total_candidates' => count($rows)]);
        exit;
    }

    if ($action === 'rollback_import') {
        // Safe rollback for recently imported tracks:
        // removes tracks that match import signature (single + duration 0 + placeholder cover)
        // within recent time window. Keeps older/original tracks intact.
        $minutes = isset($_GET['minutes']) ? (int)$_GET['minutes'] : 180;
        if ($minutes < 5) $minutes = 5;
        if ($minutes > 1440) $minutes = 1440;

        $sel = $db->prepare("
            SELECT id FROM tracks
            WHERE album_type = 'single'
              AND duration = 0
              AND (cover = 'tracks/covers/placeholder.jpg' OR cover IS NULL OR cover = '')
              AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $sel->execute([$minutes]);
        $ids = array_map(fn($r) => (int)$r['id'], $sel->fetchAll(PDO::FETCH_ASSOC));
        if (!$ids) {
            echo json_encode(['success' => true, 'action' => 'rollback_import', 'deleted' => 0, 'minutes' => $minutes]);
            exit;
        }

        $db->beginTransaction();
        $in = implode(',', array_fill(0, count($ids), '?'));

        // Cleanup related data first (if tables exist).
        try { $db->prepare("DELETE FROM lyrics WHERE track_id IN ($in)")->execute($ids); } catch (Throwable $e) {}
        try { $db->prepare("DELETE FROM track_artists WHERE track_id IN ($in)")->execute($ids); } catch (Throwable $e) {}
        try { $db->prepare("DELETE FROM playlist_tracks WHERE track_id IN ($in)")->execute($ids); } catch (Throwable $e) {}
        try { $db->prepare("DELETE FROM likes WHERE track_id IN ($in)")->execute($ids); } catch (Throwable $e) {}
        try { $db->prepare("DELETE FROM play_history WHERE track_id IN ($in)")->execute($ids); } catch (Throwable $e) {}

        $del = $db->prepare("DELETE FROM tracks WHERE id IN ($in)");
        $del->execute($ids);
        $db->commit();

        echo json_encode([
            'success' => true,
            'action' => 'rollback_import',
            'deleted' => count($ids),
            'minutes' => $minutes
        ]);
        exit;
    }

    // shared track maps for lyrics/videos
    $tracks = $db->query('SELECT id, title, artist, file_path FROM tracks')->fetchAll(PDO::FETCH_ASSOC);
    $byFileBase = [];
    $byArtistTitle = [];
    foreach ($tracks as $t) {
        $id = (int)($t['id'] ?? 0);
        if ($id <= 0) continue;
        $base = norm_name(pathinfo((string)($t['file_path'] ?? ''), PATHINFO_FILENAME));
        if ($base !== '' && !isset($byFileBase[$base])) $byFileBase[$base] = $id;
        $key = norm_name((string)$t['artist'] . ' - ' . (string)$t['title']);
        if ($key !== '' && !isset($byArtistTitle[$key])) $byArtistTitle[$key] = $id;
    }

    if ($action === 'lyrics') {
        $lrcFiles = list_files_recursive($musicDir, ['lrc']);
        $upsert = $db->prepare('INSERT INTO lyrics (track_id, lrc) VALUES (?, ?) ON DUPLICATE KEY UPDATE lrc=VALUES(lrc), updated_at=CURRENT_TIMESTAMP');
        $linked = 0; $skipped = 0;

        $db->beginTransaction();
        foreach ($lrcFiles as $lrcAbs) {
            $base = norm_name(pathinfo($lrcAbs, PATHINFO_FILENAME));
            $trackId = $byFileBase[$base] ?? $byArtistTitle[$base] ?? null;
            if (!$trackId) { $skipped++; continue; }
            $lrc = @file_get_contents($lrcAbs);
            if ($lrc === false || trim($lrc) === '') { $skipped++; continue; }
            $upsert->execute([(int)$trackId, $lrc]);
            $linked++;
        }
        $db->commit();
        echo json_encode(['success' => true, 'action' => 'lyrics', 'linked' => $linked, 'skipped' => $skipped, 'total_files' => count($lrcFiles)]);
        exit;
    }

    if ($action === 'videos') {
        $videoFiles = array_merge(
            list_files_recursive($videosDir, ['mp4', 'webm', 'mov', 'avi', 'mkv', 'm4v']),
            list_files_recursive($musicDir, ['mp4', 'webm', 'mov', 'avi', 'mkv', 'm4v'])
        );
        $videoFiles = array_values(array_unique($videoFiles));
        $upd = $db->prepare('UPDATE tracks SET video_url = ? WHERE id = ?');
        $linked = 0; $skipped = 0;

        $db->beginTransaction();
        foreach ($videoFiles as $videoAbs) {
            $base = norm_name(pathinfo($videoAbs, PATHINFO_FILENAME));
            $trackId = $byFileBase[$base] ?? $byArtistTitle[$base] ?? null;
            if (!$trackId) { $skipped++; continue; }
            $videoRel = str_replace('\\', '/', rel_from_root($root, $videoAbs));
            $upd->execute([$videoRel, (int)$trackId]);
            $linked++;
        }
        $db->commit();
        echo json_encode(['success' => true, 'action' => 'videos', 'linked' => $linked, 'skipped' => $skipped, 'total_files' => count($videoFiles)]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

