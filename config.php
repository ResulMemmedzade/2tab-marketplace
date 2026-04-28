<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Environment loader
|--------------------------------------------------------------------------
*/
if (!function_exists('loadEnv')) {
    function loadEnv(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);

            $name = trim($name);
            $value = trim($value);

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
}

loadEnv(__DIR__ . '/.env');

/*
|--------------------------------------------------------------------------
| App config
|--------------------------------------------------------------------------
*/
define('APP_ENV', (string) env('APP_ENV', 'local'));
define('APP_DEBUG', filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN));
define('APP_BASE_URL', rtrim((string) env('APP_BASE_URL', ''), '/'));
define('UPLOAD_STORAGE_PATH', (string) env('UPLOAD_STORAGE_PATH', 'C:/wamp64/2tab_uploads'));

/*
|--------------------------------------------------------------------------
| Error handling
|--------------------------------------------------------------------------
*/
ini_set('log_errors', '1');

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
}

/*
|--------------------------------------------------------------------------
| Security headers
|--------------------------------------------------------------------------
*/
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 0');
/*
|--------------------------------------------------------------------------
| Session security
|--------------------------------------------------------------------------
*/
if (session_status() === PHP_SESSION_NONE) {

    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443)
    );

    session_set_cookie_params([
        'httponly' => true,
        'secure' => $isHttps,
        'samesite' => 'Strict',
        'path' => '/',
    ]);

    session_start();
}

/*
|--------------------------------------------------------------------------
| Session hijack protection
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
}

if ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
    session_destroy();
    exit;
}

/*
|--------------------------------------------------------------------------
| Session init
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['session_initialized'])) {
    session_regenerate_id(true);
    $_SESSION['session_initialized'] = true;
}

/*
|--------------------------------------------------------------------------
| Upload path check
|--------------------------------------------------------------------------
*/
if (!is_dir(UPLOAD_STORAGE_PATH)) {
    error_log("Upload path does not exist: " . UPLOAD_STORAGE_PATH);
}

/*
|--------------------------------------------------------------------------
| Shared helpers
|--------------------------------------------------------------------------
*/
if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('basePath')) {
    function basePath(string $path = ''): string
    {
        $base = APP_BASE_URL;

        if ($path === '') {
            return $base;
        }

        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): never
    {
        header('Location: ' . $path);
        exit;
    }
}

if (!function_exists('redirectTo')) {
    function redirectTo(string $path = ''): never
    {
        redirect(basePath($path));
    }
}

if (!function_exists('setReturnTo')) {
    function setReturnTo(?string $fallback = null): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? $fallback ?? basePath('dashboard.php');

        if (!is_string($requestUri) || $requestUri === '') {
            $requestUri = $fallback ?? basePath('dashboard.php');
        }

        $_SESSION['return_to'] = $requestUri;
    }
}

if (!function_exists('pullReturnTo')) {
    function pullReturnTo(?string $default = null): string
    {
        $target = $_SESSION['return_to'] ?? $default ?? basePath('dashboard.php');
        unset($_SESSION['return_to']);

        if (!is_string($target) || $target === '') {
            return $default ?? basePath('dashboard.php');
        }

        return $target;
    }
}

if (!function_exists('flashSuccess')) {
    function flashSuccess(string $message): void
    {
        $_SESSION['flash_success'] = $message;
    }
}

if (!function_exists('flashError')) {
    function flashError(string $message): void
    {
        $_SESSION['flash_error'] = $message;
    }
}

if (!function_exists('getFlashSuccess')) {
    function getFlashSuccess(): ?string
    {
        if (!isset($_SESSION['flash_success'])) {
            return null;
        }

        $message = $_SESSION['flash_success'];
        unset($_SESSION['flash_success']);

        return is_string($message) ? $message : null;
    }
}

if (!function_exists('getFlashError')) {
    function getFlashError(): ?string
    {
        if (!isset($_SESSION['flash_error'])) {
            return null;
        }

        $message = $_SESSION['flash_error'];
        unset($_SESSION['flash_error']);

        return is_string($message) ? $message : null;
    }
}

/*
|--------------------------------------------------------------------------
| Bootstrap
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/auth.php';
