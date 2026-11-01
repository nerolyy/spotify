<?php
require_once __DIR__ . '/../../../src/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$apply = isset($_GET['apply']) && ($_GET['apply'] === '1' || strtolower($_GET['apply']) === 'true');

// Scan filesystem for existing audio and cover files
$root = realpath(__DIR__ . '/../../../');
$musicDir = $root . '/tracks/music';
$coversDir = $root . '/tracks/covers';

function listFiles($dir, $exts){
    $out = [];
    if (!is_dir($dir)) return $out;
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $f) {
        if (!$f->isFile()) continue;
        $ext = strtolower(pathinfo($f->getFilename(), PATHINFO_EXTENSION));
        if (!in_array($ext, $exts, true)) continue;
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen(realpath($dir))+1));
        $out[] = $rel;
    }
    return $out;
}

// Normalize name for fuzzy match
function norm($s){
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('~[\s_]+~u', ' ', $s);
    $s = preg_replace('~[^a-z0-9а-яё\s-]~u', '', $s);
    $s = trim($s);
    return $s;
}

try {
    $db = get_db_connection();
    $tracks = $db->query('SELECT id, title, artist, album, file_path, cover FROM tracks ORDER BY id ASC')->fetchAll();

    $musicFiles = listFiles($musicDir, ['mp3','m4a','flac','wav']);
    $coverFiles = listFiles($coversDir, ['jpg','jpeg','png']);

    // Build indices for quick matching
    $musicIndex = [];
    foreach ($musicFiles as $rel) {
        $base = pathinfo($rel, PATHINFO_FILENAME);
        $musicIndex[norm($base)] = $rel;
    }
    $coverIndex = [];
    foreach ($coverFiles as $rel) {
        $base = pathinfo($rel, PATHINFO_FILENAME);
        $coverIndex[norm($base)] = $rel;
    }

    $changes = [];
    foreach ($tracks as $t) {
        $id = (int)$t['id'];
        $title = (string)$t['title'];
        $artist = (string)$t['artist'];
        $album = (string)$t['album'];
        $origFile = (string)$t['file_path'];
        $origCover = (string)$t['cover'];

        // Try exact path existence first
        $newFile = $origFile;
        $newCover = $origCover;

        $fileOk = $origFile && file_exists($root . '/' . ltrim($origFile, '/'));
        $coverOk = $origCover && file_exists($root . '/' . ltrim($origCover, '/'));

        // Fuzzy for file
        if (!$fileOk) {
            $cands = [norm($artist . ' - ' . $title), norm($title), norm($album . ' - ' . $title)];
            foreach ($cands as $key) {
                if ($key && isset($musicIndex[$key])) {
                    $newFile = 'tracks/music/' . $musicIndex[$key];
                    $fileOk = true; break;
                }
            }
        }
        // Fallback: pick any file that contains normalized title
        if (!$fileOk && $title) {
            $nt = norm($title);
            foreach ($musicIndex as $k => $rel) {
                if ($nt !== '' && strpos($k, $nt) !== false) { $newFile = 'tracks/music/' . $rel; $fileOk = true; break; }
            }
        }

        // Fuzzy for cover
        if (!$coverOk) {
            $cands = [norm($artist . ' - ' . $album), norm($album), norm($artist), norm($title)];
            foreach ($cands as $key) {
                if ($key && isset($coverIndex[$key])) { $newCover = 'tracks/covers/' . $coverIndex[$key]; $coverOk = true; break; }
            }
        }

        // If still missing, leave original
        if ($newFile !== $origFile || $newCover !== $origCover) {
            $changes[] = [
                'id' => $id,
                'from' => ['file_path' => $origFile, 'cover' => $origCover],
                'to'   => ['file_path' => $newFile,  'cover' => $newCover]
            ];
        }
    }

    if ($apply && $changes) {
        $st = $db->prepare('UPDATE tracks SET file_path = ?, cover = ? WHERE id = ?');
        foreach ($changes as $c) { $st->execute([$c['to']['file_path'], $c['to']['cover'], $c['id']]); }
    }

    echo json_encode([
        'success' => true,
        'updated' => $apply ? count($changes) : 0,
        'pending' => !$apply ? count($changes) : 0,
        'preview' => !$apply ? $changes : [],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>

















