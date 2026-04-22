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
            return;
        }

        setReturnTo($fallback);

        if (function_exists('appLog')) {
            appLog('suspicious_activity', 'Unauthorized access attempt (not logged in)', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

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
