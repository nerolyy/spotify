<?php
header('Content-Type: application/json');

$request = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

$path = preg_replace('#^/[^/]+#', '', $request);

switch (true) {
    case preg_match('#^/api/register#', $path):
        require __DIR__ . '/../src/api/register.php';
        break;
    case preg_match('#^/api/login#', $path):
        require __DIR__ . '/../src/api/login.php';
        break;
    case preg_match('#^/api/tracks/similar#', $path):
        require __DIR__ . '/../src/api/tracks_similar.php';
        break;
    case preg_match('#^/api/tracks#', $path):
        require __DIR__ . '/../src/api/tracks.php';
        break;
    case preg_match('#^/api/playlists#', $path):
        require __DIR__ . '/../src/api/playlists.php';
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}

