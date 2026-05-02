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

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: public, max-age=86400');

readfile($fullPath);
exit;