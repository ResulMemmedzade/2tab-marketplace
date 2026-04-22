<?php

require_once "config.php";

$file = trim((string)($_GET['file'] ?? ''));
$file = basename($file);

if ($file === '') {
    http_response_code(404);
    exit('Image not found');
}

/*
|--------------------------------------------------------------------------
| Filename sanity check
|--------------------------------------------------------------------------
*/
if (!preg_match('/^[A-Za-z0-9._-]+$/', $file)) {
    http_response_code(404);
    exit('Image not found');
}

/*
|--------------------------------------------------------------------------
| Book access control
|--------------------------------------------------------------------------
| active  -> hamı görə bilər
| hidden  -> yalnız sahibi
| sold    -> yalnız sahibi
| deleted -> heç kim
*/
try {
    $stmt = $pdo->prepare("
        SELECT id, user_id, seller_id, status, is_deleted
        FROM books
        WHERE image = ?
        LIMIT 1
    ");
    $stmt->execute([$file]);
    $book = $stmt->fetch();
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Server error');
}

if (!$book) {
    http_response_code(404);
    exit('Image not found');
}

if ((int)($book['is_deleted'] ?? 0) === 1) {
    http_response_code(404);
    exit('Image not found');
}

$currentUserId = currentUserId();
$ownerId = (int)($book['user_id'] ?? $book['seller_id'] ?? 0);
$status = (string)($book['status'] ?? 'active');

if ($status !== 'active' && $currentUserId !== $ownerId) {
    http_response_code(403);
    exit('Access denied');
}

/*
|--------------------------------------------------------------------------
| Physical file checks
|--------------------------------------------------------------------------
*/
$path = rtrim(UPLOAD_STORAGE_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;

if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit('Image not found');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);

if ($finfo === false) {
    http_response_code(500);
    exit('Server error');
}

$mimeType = finfo_file($finfo, $path);
finfo_close($finfo);

$allowedMimeTypes = [
    'image/jpeg',
    'image/png',
    'image/webp',
];

if (!in_array($mimeType, $allowedMimeTypes, true)) {
    http_response_code(403);
    exit('Invalid file type');
}

$fileSize = filesize($path);

if ($fileSize === false) {
    http_response_code(500);
    exit('Server error');
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . rawurlencode($file) . '"');
header('Cache-Control: private, max-age=86400');

readfile($path);
exit;
