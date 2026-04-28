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

    http_response_code(500);

    if (defined('APP_DEBUG') && APP_DEBUG) {
        echo 'DB bağlantı xətası: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        exit;
    }

    echo 'Sistem müvəqqəti əlçatan deyil.';
    exit;
}
