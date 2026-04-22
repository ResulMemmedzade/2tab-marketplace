<?php

function rateLimitKey(string $prefix, string $identifier = ''): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $identifier = strtolower(trim($identifier));

    return $prefix . '|' . $ip . '|' . $identifier;
}

function isRateLimited(PDO $pdo, string $key, int $windowSeconds = 900): array
{
    $stmt = $pdo->prepare("SELECT * FROM rate_limits WHERE rate_key = ? LIMIT 1");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $now = time();

    if (!$row) {
        return [
            'limited' => false,
            'remaining_seconds' => 0
        ];
    }

    if ((int)$row['blocked_until'] > $now) {
        return [
            'limited' => true,
            'remaining_seconds' => (int)$row['blocked_until'] - $now
        ];
    }

    if (($now - (int)$row['window_start']) > $windowSeconds) {
        $resetStmt = $pdo->prepare("
            UPDATE rate_limits
            SET attempts = 0, window_start = ?, blocked_until = 0
            WHERE rate_key = ?
        ");
        $resetStmt->execute([$now, $key]);

        return [
            'limited' => false,
            'remaining_seconds' => 0
        ];
    }

    return [
        'limited' => false,
        'remaining_seconds' => 0
    ];
}

function registerRateLimitFailure(
    PDO $pdo,
    string $key,
    int $maxAttempts = 5,
    int $windowSeconds = 900,
    int $blockSeconds = 900
): void {
    $stmt = $pdo->prepare("SELECT * FROM rate_limits WHERE rate_key = ? LIMIT 1");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $now = time();

    if (!$row) {
        $attempts = 1;
        $blockedUntil = 0;

        if ($attempts >= $maxAttempts) {
            $blockedUntil = $now + $blockSeconds;
        }

        $insertStmt = $pdo->prepare("
            INSERT INTO rate_limits (rate_key, attempts, window_start, blocked_until)
            VALUES (?, ?, ?, ?)
        ");
        $insertStmt->execute([$key, $attempts, $now, $blockedUntil]);
        return;
    }

    $windowStart = (int)$row['window_start'];
    $attempts = (int)$row['attempts'];

    if (($now - $windowStart) > $windowSeconds) {
        $attempts = 1;
        $windowStart = $now;
        $blockedUntil = 0;
    } else {
        $attempts++;
        $blockedUntil = (int)$row['blocked_until'];
    }

    if ($attempts >= $maxAttempts) {
        $blockedUntil = $now + $blockSeconds;
    }

    $updateStmt = $pdo->prepare("
        UPDATE rate_limits
        SET attempts = ?, window_start = ?, blocked_until = ?
        WHERE rate_key = ?
    ");
    $updateStmt->execute([$attempts, $windowStart, $blockedUntil, $key]);
}

function clearRateLimit(PDO $pdo, string $key): void
{
    $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE rate_key = ?");
    $stmt->execute([$key]);
}
function registerRateLimitAttempt(
    PDO $pdo,
    string $key,
    int $maxAttempts = 10,
    int $windowSeconds = 60,
    int $blockSeconds = 60
): array {
    $stmt = $pdo->prepare("SELECT * FROM rate_limits WHERE rate_key = ? LIMIT 1");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $now = time();

    if (!$row) {
        $attempts = 1;
        $blockedUntil = 0;

        if ($attempts >= $maxAttempts) {
            $blockedUntil = $now + $blockSeconds;
        }

        $insertStmt = $pdo->prepare("
            INSERT INTO rate_limits (rate_key, attempts, window_start, blocked_until)
            VALUES (?, ?, ?, ?)
        ");
        $insertStmt->execute([$key, $attempts, $now, $blockedUntil]);

        return [
            'limited' => $blockedUntil > $now,
            'remaining_seconds' => $blockedUntil > $now ? ($blockedUntil - $now) : 0
        ];
    }

    $windowStart = (int)$row['window_start'];
    $attempts = (int)$row['attempts'];
    $blockedUntil = (int)$row['blocked_until'];

    if ($blockedUntil > $now) {
        return [
            'limited' => true,
            'remaining_seconds' => $blockedUntil - $now
        ];
    }

    if (($now - $windowStart) > $windowSeconds) {
        $attempts = 1;
        $windowStart = $now;
        $blockedUntil = 0;
    } else {
        $attempts++;
    }

    if ($attempts >= $maxAttempts) {
        $blockedUntil = $now + $blockSeconds;
    }

    $updateStmt = $pdo->prepare("
        UPDATE rate_limits
        SET attempts = ?, window_start = ?, blocked_until = ?
        WHERE rate_key = ?
    ");
    $updateStmt->execute([$attempts, $windowStart, $blockedUntil, $key]);

    return [
        'limited' => $blockedUntil > $now,
        'remaining_seconds' => $blockedUntil > $now ? ($blockedUntil - $now) : 0
    ];
}