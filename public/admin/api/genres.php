<?php
require_once __DIR__ . '/../../../src/config/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $db = get_db_connection();
    try { $db->exec("ALTER TABLE tracks ADD COLUMN genre VARCHAR(120) NULL"); } catch (Throwable $e) { /* ignore */ }
    $rows = $db->query("SELECT DISTINCT genre FROM tracks WHERE genre IS NOT NULL AND TRIM(genre) <> '' ORDER BY genre ASC LIMIT 200")->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['success' => true, 'data' => array_values($rows ?: [])], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
