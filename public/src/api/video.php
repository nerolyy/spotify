<?php
// Simple video proxy to enforce correct Content-Type and support Range
// Usage: /muzic2/public/src/api/video.php?f=tracks/video/Agent-provo-video.mp4

// Security: allow only paths under project root and within tracks/video or tracks/music
$rel = isset($_GET['f']) ? (string)$_GET['f'] : '';
// Decode once or twice to tolerate accidental double-encoding from clients
$rel = urldecode($rel);
if (strpos($rel, '%') !== false) { $rel = urldecode($rel); }
// Normalize separators and strip traversal
$rel = str_replace(['\\', '..'], ['/', ''], $rel);
// Remove leading slashes
while (isset($rel[0]) && ($rel[0] === '/')) { $rel = substr($rel, 1); }
// Allow tracks/video, tracks/videos, and tracks/music directories
if ($rel === '' || (strpos($rel, 'tracks/video/') !== 0 && strpos($rel, 'tracks/videos/') !== 0 && strpos($rel, 'tracks/music/') !== 0)) {
  http_response_code(400);
  echo 'Bad request';
  exit;
}

$root = realpath(__DIR__ . '/../../..');
$file = realpath($root . '/' . $rel);
// Fallback for Unicode normalization differences (e.g., macOS NFD vs NFC)
// Allow tracks/video, tracks/videos, and tracks/music
if (!$file || !is_file($file) || (strpos($file, $root . '/tracks/video/') !== 0 && strpos($file, $root . '/tracks/videos/') !== 0 && strpos($file, $root . '/tracks/music/') !== 0)) {
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
    echo 'Not found';
    exit;
  }
}

$size = filesize($file);
$mime = 'video/mp4';
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
if ($ext === 'webm') { 
    $mime = 'video/webm'; 
} else if ($ext === 'm4v') { 
    $mime = 'video/mp4'; 
} else if ($ext === 'mov') { 
    $mime = 'video/quicktime'; // MOV files use QuickTime MIME type
} else if ($ext === 'avi') { 
    $mime = 'video/x-msvideo'; 
}

header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');

$start = 0; $length = $size; $end = $size - 1;
if (isset($_SERVER['HTTP_RANGE'])) {
  if (preg_match('/bytes=(\d+)-(\d*)/i', $_SERVER['HTTP_RANGE'], $m)) {
    $start = (int)$m[1];
    if ($m[2] !== '') { $end = (int)$m[2]; }
    if ($end >= $size) { $end = $size - 1; }
    if ($start > $end) { $start = 0; }
    $length = $end - $start + 1;
    header('HTTP/1.1 206 Partial Content');
    header("Content-Range: bytes $start-$end/$size");
  }
}
header('Content-Length: ' . $length);

$fp = fopen($file, 'rb');
if ($fp === false) { http_response_code(500); exit; }
if ($start > 0) fseek($fp, $start);
$buf = 8192;
while (!feof($fp) && $length > 0) {
  $read = ($length > $buf) ? $buf : $length;
  $data = fread($fp, $read);
  if ($data === false) break;
echo $data;
flush();
$length -= strlen($data);
}
fclose($fp);
// done
?>


