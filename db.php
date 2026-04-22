<?php

declare(strict_types=1);

$host = (string) env('DB_HOST', '127.0.0.1');
$dbname = (string) env('DB_NAME', '2tab');
$username = (string) env('DB_USER', 'root');
$password = (string) env('DB_PASS', '');

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log('DB Error: ' . $e->getMessage());

    if (defined('APP_DEBUG') && APP_DEBUG) {
        die('DB bağlantı xətası: ' . $e->getMessage());
    }

    http_response_code(500);
    die('Sistem müvəqqəti əlçatan deyil.');
}
