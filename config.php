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
define('VAPID_PUBLIC_KEY', (string) env('VAPID_PUBLIC_KEY', ''));
define('VAPID_PRIVATE_KEY', (string) env('VAPID_PRIVATE_KEY', ''));
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
function enforceUserStatus(PDO $pdo): void
{
    if (!isset($_SESSION["user_id"])) {
        return;
    }

    $stmt = $pdo->prepare("
        SELECT status, ban_expires_at
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$_SESSION["user_id"]]);
    $user = $stmt->fetch();

    // user yoxdur → session öldür
    if (!$user) {
        session_destroy();
        redirectTo("login.php");
    }

    // 🔴 PERMANENT BAN
    if (($user["status"] ?? "active") === "banned") {
        session_destroy();

        appLog('session_killed', 'Banned user session destroyed', [
            'user_id' => $_SESSION["user_id"] ?? null
        ]);

        redirectTo("login.php?banned=1");
    }

    // 🟡 TEMP BAN
    if (($user["status"] ?? "active") === "temp_banned") {

        if (!empty($user["ban_expires_at"]) && strtotime($user["ban_expires_at"]) > time()) {

            session_destroy();

            appLog('session_killed', 'Temp banned user session destroyed', [
                'user_id' => $_SESSION["user_id"] ?? null,
                'ban_expires_at' => $user["ban_expires_at"]
            ]);

            redirectTo("login.php?temp_banned=1");

        } else {
            // ⏳ müddət bitib → aktiv et
            $stmt = $pdo->prepare("
                UPDATE users
                SET status = 'active',
                    ban_expires_at = NULL
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION["user_id"]]);
        }
    }
}
function addUserStrike(PDO $pdo, int $userId, string $reason, int $maxStrikes = 3, int $banMinutes = 30): void
{
    if ($userId <= 0) {
        return;
    }

    $stmt = $pdo->prepare("
        SELECT id, role, status, strike_count, temp_ban_count
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        return;
    }

    if (($user["role"] ?? "") === "admin") {
        return;
    }

    if (($user["status"] ?? "") === "banned") {
        return;
    }

    $newStrikeCount = ((int)($user["strike_count"] ?? 0)) + 1;
    $tempBanCount = (int)($user["temp_ban_count"] ?? 0);

    if ($newStrikeCount >= $maxStrikes) {

        if ($tempBanCount >= 2) {
            $stmt = $pdo->prepare("
                UPDATE users
                SET status = 'banned',
                    strike_count = ?,
                    banned_at = NOW(),
                    banned_reason = ?,
                    banned_by = NULL,
                    ban_expires_at = NULL
                WHERE id = ?
                  AND role != 'admin'
            ");
            $stmt->execute([$newStrikeCount, $reason, $userId]);
            $hideBooksStmt = $pdo->prepare("
    UPDATE books
    SET status = 'hidden'
    WHERE seller_id = ?
      AND status = 'active'
");
$hideBooksStmt->execute([$userId]);
            

            appLog('auto_permanent_ban', 'User automatically permanently banned', [
                'user_id' => $userId,
                'reason' => $reason,
                'previous_temp_ban_count' => $tempBanCount,
            ]);

            return;
        }

        $stmt = $pdo->prepare("
            UPDATE users
            SET status = 'temp_banned',
                strike_count = 0,
                temp_ban_count = temp_ban_count + 1,
                ban_expires_at = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                banned_at = NOW(),
                banned_reason = ?,
                banned_by = NULL
            WHERE id = ?
              AND role != 'admin'
        ");
        $stmt->execute([$banMinutes, $reason, $userId]);

        appLog('auto_temp_ban', 'User automatically temp banned', [
            'user_id' => $userId,
            'reason' => $reason,
            'new_temp_ban_count' => $tempBanCount + 1,
            'ban_minutes' => $banMinutes,
        ]);

        return;
    }

    $stmt = $pdo->prepare("
        UPDATE users
        SET status = 'flagged',
            strike_count = ?,
            banned_reason = ?
        WHERE id = ?
          AND role != 'admin'
          AND status != 'banned'
    ");
    $stmt->execute([$newStrikeCount, $reason, $userId]);

    appLog('user_strike', 'User strike added', [
        'user_id' => $userId,
        'reason' => $reason,
        'strike_count' => $newStrikeCount,
    ]);
}
function containsSuspiciousPayload(string $value): bool
{
    $patterns = [
        '/<\s*script/i',
        '/onerror\s*=/i',
        '/onload\s*=/i',
        '/javascript\s*:/i',
        '/<\s*svg/i',
        '/<\s*iframe/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $value)) {
            return true;
        }
    }

    return false;
}
require_once __DIR__ . '/auth.php';
if (isset($pdo) && function_exists('attemptRememberLogin')) {
    attemptRememberLogin($pdo);
}
