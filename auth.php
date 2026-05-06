<?php

declare(strict_types=1);

if (!function_exists('isLoggedIn')) {
    function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0;
    }
}

if (!function_exists('currentUserId')) {
    function currentUserId(): ?int
    {
        return isLoggedIn() ? (int) $_SESSION['user_id'] : null;
    }
}

if (!function_exists('currentUserRole')) {
    function currentUserRole(): ?string
    {
        return isset($_SESSION['role']) ? (string) $_SESSION['role'] : null;
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin(): bool
    {
        return currentUserRole() === 'admin';
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin(?string $fallback = null): void
    {
        if (isLoggedIn()) {
            global $pdo;

            if (function_exists('enforceUserStatus')) {
                enforceUserStatus($pdo);
            }

            return;
        }

        setReturnTo($fallback);

        flashError('Davam etmək üçün giriş edin.');
        redirectTo('login.php');
    }
}

if (!function_exists('requireAdmin')) {
    function requireAdmin(?string $fallback = null): void
    {
        requireLogin($fallback);

        if (isAdmin()) {
            return;
        }

        if (function_exists('appLog')) {
            appLog('suspicious_activity', 'Non-admin tried to access admin page', [
                'user_id' => $_SESSION['user_id'] ?? null,
                'name' => $_SESSION['name'] ?? null,
                'role' => $_SESSION['role'] ?? null,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        flashError('Bu səhifəyə giriş icazəniz yoxdur.');
        redirectTo('dashboard.php');
    }
}

if (!function_exists('ensureCsrfToken')) {
    function ensureCsrfToken(): void
    {
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
}

if (!function_exists('csrfToken')) {
    function csrfToken(): string
    {
        ensureCsrfToken();
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verifyCsrfToken')) {
    function verifyCsrfToken(?string $token): void
    {
        ensureCsrfToken();

        if (!is_string($token) || $token === '' || !hash_equals($_SESSION['csrf_token'], $token)) {
            if (function_exists('appLog')) {
                appLog('security_event', 'Invalid CSRF token', [
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            http_response_code(403);
            exit('Təhlükəsizlik yoxlaması uğursuz oldu.');
        }
    }
}
if (!function_exists('loginUserToSession')) {
    function loginUserToSession(array $user): void
    {
        session_regenerate_id(true);

        $_SESSION["user_id"] = (int)$user["id"];
        $_SESSION["name"] = $user["name"];
        $_SESSION["email"] = $user["email"];
        $_SESSION["role"] = $user["role"];
    }
}

if (!function_exists('createRememberToken')) {
    function createRememberToken(PDO $pdo, int $userId): void
    {
        $selector = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $validator);
        $expiresAt = date('Y-m-d H:i:s', time() + (60 * 60 * 24 * 30));

        $stmt = $pdo->prepare("
            INSERT INTO remember_tokens
                (user_id, selector, token_hash, expires_at, user_agent, ip_address)
            VALUES
                (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            $selector,
            $tokenHash,
            $expiresAt,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);

        $isHttps = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443)
        );

        setcookie('remember_me', $selector . ':' . $validator, [
            'expires' => time() + (60 * 60 * 24 * 30),
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }
}

if (!function_exists('clearRememberTokenCookie')) {
    function clearRememberTokenCookie(): void
    {
        $isHttps = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443)
        );

        setcookie('remember_me', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }
}

if (!function_exists('deleteCurrentRememberToken')) {
    function deleteCurrentRememberToken(PDO $pdo): void
    {
        $cookie = $_COOKIE['remember_me'] ?? '';

        if (!is_string($cookie) || !str_contains($cookie, ':')) {
            clearRememberTokenCookie();
            return;
        }

        [$selector] = explode(':', $cookie, 2);

        if ($selector !== '') {
            $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE selector = ?");
            $stmt->execute([$selector]);
        }

        clearRememberTokenCookie();
    }
}

if (!function_exists('attemptRememberLogin')) {
    function attemptRememberLogin(PDO $pdo): void
    {
        if (isLoggedIn()) {
            return;
        }

        $cookie = $_COOKIE['remember_me'] ?? '';

        if (!is_string($cookie) || !str_contains($cookie, ':')) {
            return;
        }

        [$selector, $validator] = explode(':', $cookie, 2);

        if (
            !preg_match('/^[a-f0-9]{32}$/', $selector) ||
            !preg_match('/^[a-f0-9]{64}$/', $validator)
        ) {
            clearRememberTokenCookie();
            return;
        }

        $stmt = $pdo->prepare("
            SELECT rt.*, u.id, u.name, u.email, u.role, u.status, u.ban_expires_at
            FROM remember_tokens rt
            JOIN users u ON u.id = rt.user_id
            WHERE rt.selector = ?
            LIMIT 1
        ");
        $stmt->execute([$selector]);
        $row = $stmt->fetch();

        if (!$row) {
            clearRememberTokenCookie();
            return;
        }

        if (strtotime($row['expires_at']) < time()) {
            $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE selector = ?");
            $stmt->execute([$selector]);
            clearRememberTokenCookie();
            return;
        }

        $validatorHash = hash('sha256', $validator);

        if (!hash_equals($row['token_hash'], $validatorHash)) {
            $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
            $stmt->execute([(int)$row['user_id']]);
            clearRememberTokenCookie();
            return;
        }

        if (($row["status"] ?? "active") === "banned") {
            clearRememberTokenCookie();
            return;
        }

        if (($row["status"] ?? "active") === "temp_banned") {
            if (!empty($row["ban_expires_at"]) && strtotime($row["ban_expires_at"]) > time()) {
                clearRememberTokenCookie();
                return;
            }
        }

        loginUserToSession([
            "id" => $row["id"],
            "name" => $row["name"],
            "email" => $row["email"],
            "role" => $row["role"],
        ]);

        $newValidator = bin2hex(random_bytes(32));
        $newTokenHash = hash('sha256', $newValidator);
        $newExpiresAt = date('Y-m-d H:i:s', time() + (60 * 60 * 24 * 30));

        $stmt = $pdo->prepare("
            UPDATE remember_tokens
            SET token_hash = ?,
                expires_at = ?,
                last_used_at = NOW()
            WHERE selector = ?
        ");
        $stmt->execute([$newTokenHash, $newExpiresAt, $selector]);

        $isHttps = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443)
        );

        setcookie('remember_me', $selector . ':' . $newValidator, [
            'expires' => time() + (60 * 60 * 24 * 30),
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }
}