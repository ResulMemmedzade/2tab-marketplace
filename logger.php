<?php

declare(strict_types=1);

if (!function_exists('appLog')) {
    function appLog(string $type, string $message, array $context = []): void
    {
        try {
            // storage path config-dən gəlir
            $baseStorage = defined('UPLOAD_STORAGE_PATH')
                ? dirname(UPLOAD_STORAGE_PATH)
                : __DIR__;

            $logDir = $baseStorage . '/storage/logs';

            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }

            $logFile = $logDir . '/app.log';

            $entry = [
                'time' => date('Y-m-d H:i:s'),
                'type' => $type,
                'message' => $message,
                'user_id' => $_SESSION['user_id'] ?? null,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
                'method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'context' => $context
            ];

            $json = json_encode(
                $entry,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );

            if ($json === false) {
                $json = '{"error":"log_encoding_failed"}';
            }

            file_put_contents(
                $logFile,
                $json . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );

        } catch (Throwable $e) {
            // logging özündə error olsa app çökməsin
            error_log('Logging failed: ' . $e->getMessage());
        }
    }
}
