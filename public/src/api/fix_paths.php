<?php
require_once __DIR__ . '/../../../src/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$apply = isset($_GET['apply']) && ($_GET['apply'] === '1' || strtolower($_GET['apply']) === 'true');

function to_abs_music($v){
    $s = trim((string)$v);
    if ($s === '') return $s;
    if (preg_match('~^https?://~i', $s)) return $s;
    $s = ltrim($s, '/');
    $pos = stripos($s, 'tracks/');
    if ($pos !== false) $s = substr($s, $pos);
    if (!preg_match('~^tracks/music/~i', $s)) {
        // If points to covers by mistake, leave as-is
        if (stripos($s, 'tracks/covers/') === 0) return $s;
        // Force under tracks/music
        $s = 'tracks/music/' . basename($s);
    }
    return $s;
}
function to_abs_cover($v){
    $s = trim((string)$v);
    if ($s === '') return $s;
    if (preg_match('~^https?://|^data:~i', $s)) return $s;
    $s = ltrim($s, '/');
    $pos = stripos($s, 'tracks/');
    if ($pos !== false) $s = substr($s, $pos);
    if (!preg_match('~^tracks/covers/~i', $s)) {
        $s = 'tracks/covers/' . basename($s ?: 'placeholder.jpg');
    }
    return $s;
}

try {
    $db = get_db_connection();
    $rows = $db->query('SELECT id, file_path, cover FROM tracks ORDER BY id ASC')->fetchAll();
    $changes = [];
    foreach ($rows as $r) {
        $id = (int)$r['id'];
        $orig_file = (string)$r['file_path'];
        $orig_cover = (string)$r['cover'];
        $new_file = to_abs_music($orig_file);
        $new_cover = to_abs_cover($orig_cover);
        if ($new_file !== $orig_file || $new_cover !== $orig_cover) {
            $changes[] = [
                'id' => $id,
                'from' => ['file_path' => $orig_file, 'cover' => $orig_cover],
                'to'   => ['file_path' => $new_file,  'cover' => $new_cover]
            ];
        }
    }
    if ($apply && $changes) {
        $st = $db->prepare('UPDATE tracks SET file_path = ?, cover = ? WHERE id = ?');
        foreach ($changes as $c) {
            $st->execute([$c['to']['file_path'], $c['to']['cover'], $c['id']]);
        }
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

















