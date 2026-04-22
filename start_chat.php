<?php

require_once "config.php";


ensureCsrfToken();

function isSafeRedirect(string $redirect): bool
{
    if ($redirect === '') {
        return false;
    }

    if (preg_match('/^https?:\/\//i', $redirect)) {
        return false;
    }

    return str_starts_with($redirect, APP_BASE_URL . '/');
}

if (!isLoggedIn()) {
    $fallbackRedirect = basePath("books.php");

    $refererPath = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH);
    $refererQuery = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_QUERY);

    $redirect = $fallbackRedirect;

    if (!empty($refererPath) && str_starts_with($refererPath, APP_BASE_URL . '/')) {
        $redirect = $refererPath;
        if (!empty($refererQuery)) {
            $redirect .= "?" . $refererQuery;
        }
    }

    $_SESSION['return_to'] = $redirect;

    redirect(basePath("login.php?redirect=" . urlencode($redirect)));
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirectTo("books.php");
}

verifyCsrfToken($_POST['csrf_token'] ?? null);

$currentUserId = currentUserId();
$otherUserId = (int)($_POST["user_id"] ?? 0);

if ($currentUserId === null || $otherUserId <= 0 || $otherUserId === $currentUserId) {
    redirectTo("books.php");
}

try {
    $userCheckStmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $userCheckStmt->execute([$otherUserId]);
    $otherUserExists = $userCheckStmt->fetch();

    if (!$otherUserExists) {
        appLog('chat_action', 'Chat start attempted with invalid target user', [
            'current_user_id' => $currentUserId,
            'target_user_id' => $otherUserId,
        ]);

        redirectTo("books.php");
    }

    $userOneId = min($currentUserId, $otherUserId);
    $userTwoId = max($currentUserId, $otherUserId);

    $stmt = $pdo->prepare("
        SELECT id
        FROM conversations
        WHERE user_one_id = ? AND user_two_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userOneId, $userTwoId]);
    $conversation = $stmt->fetch();

    if ($conversation) {
        $conversationId = (int)$conversation["id"];

        appLog('chat_action', 'Existing conversation opened', [
            'conversation_id' => $conversationId,
            'current_user_id' => $currentUserId,
            'target_user_id' => $otherUserId,
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO conversations (user_one_id, user_two_id, last_message_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$userOneId, $userTwoId]);
        $conversationId = (int)$pdo->lastInsertId();

        appLog('chat_action', 'New conversation created', [
            'conversation_id' => $conversationId,
            'current_user_id' => $currentUserId,
            'target_user_id' => $otherUserId,
        ]);
    }

    redirect(basePath("conversation.php?id=" . $conversationId));
} catch (PDOException $e) {
    error_log($e->getMessage());

    appLog('system_error', 'Conversation start DB error', [
        'current_user_id' => $currentUserId,
        'target_user_id' => $otherUserId,
        'error' => $e->getMessage(),
    ]);

    redirectTo("books.php");
}
