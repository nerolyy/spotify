<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try { $db = get_db_connection(); } catch (Exception $e) { echo json_encode([]); exit; }
$q = trim((string)($_GET['q'] ?? ''));
if ($q === '') { echo json_encode([]); exit; }
$st = $db->prepare('SELECT id, title, artist, album FROM tracks WHERE title LIKE ? OR artist LIKE ? ORDER BY id DESC LIMIT 50');
$like = '%'.$q.'%';
$st->execute([$like,$like]);
echo json_encode($st->fetchAll());
