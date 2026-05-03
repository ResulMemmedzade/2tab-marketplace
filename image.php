<?php

require_once "config.php";

$file = $_GET['file'] ?? '';

if ($file === '') {
    http_response_code(404);
    exit('Image not found');
}

$file = str_replace('\\', '/', $file);
$file = ltrim($file, '/');

if (str_contains($file, '..')) {
    http_response_code(403);
    exit('Forbidden');
}

$baseDir = realpath(__DIR__ . '/uploads');
if ($baseDir === false) {
    http_response_code(404);
    exit('Upload folder not found');
}

$fullPath = realpath($baseDir . '/' . $file);

if ($fullPath === false || !str_starts_with($fullPath, $baseDir)) {
    http_response_code(404);
    exit('Image not found');
}

if (!is_file($fullPath)) {
    http_response_code(404);
    exit('Image not found');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $fullPath);
finfo_close($finfo);

$allowed = [
    'image/jpeg',
    'image/png',
    'image/webp',
];

if (!in_array($mime, $allowed, true)) {
    http_response_code(403);
    exit('Invalid image type');
}

$cacheSeconds = 2592000; // 30 days
$lastModified = filemtime($fullPath);
$etag = '"' . md5($file . '|' . filesize($fullPath) . '|' . $lastModified) . '"';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: public, max-age=' . $cacheSeconds . ', immutable');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cacheSeconds) . ' GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
header('ETag: ' . $etag);

if (
    ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag ||
    strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '') === $lastModified
) {
    http_response_code(304);
    exit;
}

readfile($fullPath);
exit;