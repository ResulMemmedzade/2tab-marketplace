<?php

require_once "config.php";
require_once "rate_limiter.php";
require_once "send_push_notification.php";

header('Content-Type: application/json; charset=UTF-8');

function responseJson($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit;
}

if (!isLoggedIn()) {
    responseJson(false, 'Giriş edilməyib.');
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responseJson(false, 'Yanlış sorğu.');
}

try {
    verifyCsrfToken($_POST['csrf_token'] ?? null);
} catch (Throwable $e) {
    responseJson(false, 'Sorğu etibarsızdır.');
}

$currentUserId = currentUserId();
$conversationId = (int)($_POST["conversation_id"] ?? 0);
$message = trim($_POST["message"] ?? "");

if ($currentUserId === null) {
    responseJson(false, 'Giriş edilməyib.');
}

if ($conversationId <= 0) {
    responseJson(false, 'Conversation ID yanlışdır.');
}

if ($message === "") {
    responseJson(false, 'Mesaj boş ola bilməz.');
}

if (mb_strlen($message) > 5000) {
    responseJson(false, 'Mesaj çox uzundur.');
}

if (containsSuspiciousPayload($message)) {
    addUserStrike($pdo, $currentUserId, 'XSS attempt in message');

    appLog('security', 'XSS payload detected in message', [
        'user_id' => $currentUserId,
        'conversation_id' => $conversationId
    ]);

    responseJson(false, 'Təhlükəli məzmun aşkar edildi.');
}

try {
    $stmt = $pdo->prepare("
        SELECT c.*
        FROM conversations c
        WHERE c.id = ?
          AND (c.user_one_id = ? OR c.user_two_id = ?)
        LIMIT 1
    ");
    $stmt->execute([$conversationId, $currentUserId, $currentUserId]);
    $conversation = $stmt->fetch();

    if (!$conversation) {
        appLog('chat_action', 'Unauthorized message send attempt', [
            'user_id' => $currentUserId,
            'conversation_id' => $conversationId,
        ]);

        responseJson(false, 'Bu söhbətə giriş icazəniz yoxdur.');
    }

    $messageRateKey = rateLimitKey('message', (string)$currentUserId);
    $rateStatus = isRateLimited($pdo, $messageRateKey, 60);

    if ($rateStatus['limited']) {
        $remainingSeconds = (int)$rateStatus['remaining_seconds'];

        appLog('rate_limit', 'Message spam limit triggered', [
            'user_id' => $currentUserId,
            'conversation_id' => $conversationId,
            'remaining_seconds' => $remainingSeconds
        ]);

        addUserStrike($pdo, $currentUserId, 'Mesaj spamı: qısa müddətdə çox mesaj göndərmə');

        responseJson(false, "Çox tez-tez mesaj göndərirsiniz. {$remainingSeconds} saniyə sonra yenidən cəhd edin.");
    }

    $attemptResult = registerRateLimitAttempt($pdo, $messageRateKey, 10, 60, 60);

    if ($attemptResult['limited']) {
        $remainingSeconds = (int)$attemptResult['remaining_seconds'];

        appLog('rate_limit', 'Message spam limit triggered after attempt', [
            'user_id' => $currentUserId,
            'conversation_id' => $conversationId,
            'remaining_seconds' => $remainingSeconds
        ]);

        addUserStrike($pdo, $currentUserId, 'Mesaj spamı: limit keçildi');

        responseJson(false, "Mesaj limiti keçildi. {$remainingSeconds} saniyə sonra yenidən cəhd edin.");
    }

    $stmt = $pdo->prepare("
        INSERT INTO messages (conversation_id, sender_id, message, message_type, is_read)
        VALUES (?, ?, ?, 'text', 0)
    ");
    $stmt->execute([$conversationId, $currentUserId, $message]);

    $messageId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("
        UPDATE conversations
        SET last_message_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$conversationId]);

    $receiverId = null;

    if ((int)$conversation["user_one_id"] === (int)$currentUserId) {
        $receiverId = (int)$conversation["user_two_id"];
    } elseif ((int)$conversation["user_two_id"] === (int)$currentUserId) {
        $receiverId = (int)$conversation["user_one_id"];
    }

    if ($receiverId !== null && function_exists("sendPushNotificationToUser")) {
        $senderName = $_SESSION["name"] ?? "2tab istifadəçisi";

        $notificationBody = mb_strlen($message) > 80
            ? mb_substr($message, 0, 80) . "..."
            : $message;

            sendPushNotificationToUser(
                $pdo,
                $receiverId,
                $_SESSION["name"] ?? "2tab",
                mb_substr($message, 0, 120),
                "/conversation.php?id=" . $conversationId
            );
    }

    appLog('chat_action', 'Message sent successfully', [
        'user_id' => $currentUserId,
        'conversation_id' => $conversationId,
        'message_id' => $messageId
    ]);

    responseJson(true, nl2br(e($message)), [
        'message_id' => $messageId,
        'raw_message' => $message,
        'message_type' => 'text',
        'is_read' => 0,
        'time' => 'indi'
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());

    appLog('system_error', 'Message send DB error', [
        'user_id' => $currentUserId,
        'conversation_id' => $conversationId,
        'error' => $e->getMessage(),
    ]);

    responseJson(false, 'Mesaj göndərilərkən xəta baş verdi.');
}