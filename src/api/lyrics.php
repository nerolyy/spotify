<?php
session_start();
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

try {
	$db = get_db_connection();
	$db->exec("CREATE TABLE IF NOT EXISTS lyrics (
		track_id INT PRIMARY KEY,
		lrc MEDIUMTEXT,
		updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE
	)");
} catch (Exception $e) {
	http_response_code(500);
	echo json_encode(['error' => 'DB init failed']);
	exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
	$trackId = intval($_GET['track_id'] ?? 0);
	$title = isset($_GET['title']) ? trim((string)$_GET['title']) : '';
	$artist = isset($_GET['artist']) ? trim((string)$_GET['artist']) : '';

	if ($trackId <= 0 && ($title !== '' || $artist !== '')) {
		try {
			if ($title !== '' && $artist !== '') {
				$q = $db->prepare('SELECT id FROM tracks WHERE TRIM(LOWER(title))=TRIM(LOWER(?)) AND TRIM(LOWER(artist))=TRIM(LOWER(?)) ORDER BY id DESC LIMIT 1');
				$q->execute([$title, $artist]);
				$r = $q->fetch();
				if ($r && isset($r['id'])) { $trackId = (int)$r['id']; }
			}
			if ($trackId <= 0 && $title !== '') {
				if ($artist !== '') {
					$q = $db->prepare('SELECT id FROM tracks WHERE title LIKE ? AND artist LIKE ? ORDER BY id DESC LIMIT 1');
					$q->execute(['%'.$title.'%', '%'.$artist.'%']);
				} else {
					$q = $db->prepare('SELECT id FROM tracks WHERE title LIKE ? ORDER BY id DESC LIMIT 1');
					$q->execute(['%'.$title.'%']);
				}
				$r = $q->fetch();
				if ($r && isset($r['id'])) { $trackId = (int)$r['id']; }
			}
		} catch (Exception $e) { /* ignore */ }
	}

	if ($trackId <= 0) { echo json_encode(['track_id' => null, 'lrc' => null]); exit; }
	$st = $db->prepare('SELECT lrc FROM lyrics WHERE track_id = ?');
	$st->execute([$trackId]);
	$row = $st->fetch();
	echo json_encode(['track_id' => $trackId, 'lrc' => $row ? $row['lrc'] : null]);
	exit;
}

if ($method === 'POST') {
	$input = json_decode(file_get_contents('php://input'), true);
	$trackId = intval($input['track_id'] ?? 0);
	$lrc = (string)($input['lrc'] ?? '');
	if ($trackId <= 0) { http_response_code(400); echo json_encode(['error' => 'track_id required']); exit; }
	// Upsert
	$exists = $db->prepare('SELECT 1 FROM lyrics WHERE track_id = ?');
	$exists->execute([$trackId]);
	if ($exists->fetch()) {
		$upd = $db->prepare('UPDATE lyrics SET lrc = ?, updated_at = CURRENT_TIMESTAMP WHERE track_id = ?');
		$upd->execute([$lrc, $trackId]);
	} else {
		$ins = $db->prepare('INSERT INTO lyrics (track_id, lrc) VALUES (?, ?)');
		$ins->execute([$trackId, $lrc]);
	}
	echo json_encode(['success' => true]);
	exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
