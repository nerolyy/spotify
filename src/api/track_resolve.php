<?php
/**
 * Возвращает id трека по относительному пути файла (как в audio.php?f=...).
 */
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$f = isset($_GET['f']) ? (string)$_GET['f'] : '';
$f = rawurldecode($f);
if (strpos($f, '%') !== false) {
    $f = rawurldecode($f);
}
$f = str_replace(['\\', '..'], ['/', ''], $f);
while ($f !== '' && ($f[0] === '/' || $f[0] === '\\')) {
    $f = substr($f, 1);
}

if ($f === '' || strpos($f, 'tracks/') !== 0) {
    echo json_encode(['id' => null]);
    exit;
}

try {
    $db = get_db_connection();
    $st = $db->prepare('SELECT id FROM tracks WHERE file_path = ? LIMIT 1');
    $st->execute([$f]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo json_encode(['id' => (int)$row['id']]);
        exit;
    }
    $norm = preg_replace('#^/+tracks/#', 'tracks/', $f);
    if ($norm !== $f) {
        $st->execute([$norm]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            echo json_encode(['id' => (int)$row['id']]);
            exit;
        }
    }
    $base = basename($f);
    if ($base !== '' && $base !== '.' && $base !== '..') {
        $st2 = $db->prepare('SELECT id FROM tracks WHERE file_path LIKE ? OR file_path LIKE ? LIMIT 2');
        $st2->execute(['%' . $base, '%/' . $base]);
        $rows = $st2->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) === 1) {
            echo json_encode(['id' => (int)$rows[0]['id']]);
            exit;
        }
    }
    echo json_encode(['id' => null]);
} catch (Throwable $e) {
    echo json_encode(['id' => null]);
}
