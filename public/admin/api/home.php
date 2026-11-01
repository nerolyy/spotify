<?php
require_once __DIR__ . '/../../../src/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new Exception('Метод не поддерживается');
    }
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        $body = [];
    }
    $action = $body['action'] ?? '';
    if ($action !== 'refresh_mood_mixes') {
        throw new Exception('Неизвестное действие');
    }
    $cachePath = __DIR__ . '/../../../tmp/cache/home_windows_v1.json';
    if (is_file($cachePath)) {
        @unlink($cachePath);
    }
    echo json_encode(['success' => true, 'message' => 'Миксы дня обновлены'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
