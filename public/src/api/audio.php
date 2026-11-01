<?php
// Audio proxy to enforce correct Content-Type and support Range requests
// Usage: /muzic2/public/src/api/audio.php?f=tracks/music/filename.mp3

// Security: allow only paths under project root and within tracks/music
$rel = isset($_GET['f']) ? (string)$_GET['f'] : '';
// Decode once or twice to tolerate accidental double-encoding from clients
$rel = urldecode($rel);
if (strpos($rel, '%') !== false) { $rel = urldecode($rel); }
// Normalize separators and strip traversal
$rel = str_replace(['\\', '..'], ['/', ''], $rel);
// Remove leading slashes
while (isset($rel[0]) && ($rel[0] === '/')) { $rel = substr($rel, 1); }
if ($rel === '' || strpos($rel, 'tracks/music/') !== 0) {
  http_response_code(400);
  echo 'Bad request';
  exit;
}

$root = realpath(__DIR__ . '/../../..');
$file = realpath($root . '/' . $rel);
// Fallback for Unicode normalization differences (e.g., macOS NFD vs NFC)
if (!$file || !is_file($file) || strpos($file, $root . '/tracks/') !== 0) {
  $candidate = $root . '/' . $rel;
  $dir = dirname($candidate);
  $base = basename($candidate);
  $picked = '';
  if (is_dir($dir)) {
    $dh = opendir($dir);
    if ($dh) {
      while (($fn = readdir($dh)) !== false) {
        if ($fn === '.' || $fn === '..') continue;
        $a = $fn; $b = $base;
        if (class_exists('Normalizer')) {
          $a = Normalizer::normalize($a, Normalizer::FORM_C);
          $b = Normalizer::normalize($b, Normalizer::FORM_C);
        }
        if ($a === $b) { $picked = $dir . '/' . $fn; break; }
      }
      closedir($dh);
    }
  }
  if ($picked && is_file($picked)) {
    $file = $picked;
  } else {
    http_response_code(404);
    echo 'Not found: ' . htmlspecialchars($rel);
    exit;
  }
}

$size = filesize($file);
$mime = 'audio/mpeg';
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
if ($ext === 'flac') { $mime = 'audio/flac'; }
else if ($ext === 'm4a' || $ext === 'mp4') { $mime = 'audio/mp4'; }
else if ($ext === 'wav') { $mime = 'audio/wav'; }
else if ($ext === 'ogg') { $mime = 'audio/ogg'; }

header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');
header('Content-Length: ' . $size);

// Handle Range requests for seeking
$range = isset($_SERVER['HTTP_RANGE']) ? $_SERVER['HTTP_RANGE'] : '';
if ($range) {
  preg_match('/bytes=(\d+)-(\d*)/', $range, $matches);
  $start = intval($matches[1]);
  $end = $matches[2] === '' ? $size - 1 : intval($matches[2]);
  $length = $end - $start + 1;
  
  http_response_code(206);
  header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
  header('Content-Length: ' . $length);
  
  $fp = fopen($file, 'rb');
  fseek($fp, $start);
  echo fread($fp, $length);
  fclose($fp);
} else {
  // Send entire file
  readfile($file);
}
?>







