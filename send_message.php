<?php

require_once "config.php";
require_once "rate_limiter.php";

header('Content-Type: application/json; charset=UTF-8');

if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Giriş edilməyib.'
    ]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        'success' => false,
        'message' => 'Yanlış sorğu.'
    ]);
    exit;
}

try {
    verifyCsrfToken($_POST['csrf_token'] ?? null);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Sorğu etibarsızdır.'
    ]);
    exit;
}

$currentUserId = currentUserId();
$conversationId = (int) ($_POST["conversation_id"] ?? 0);
$message = trim($_POST["message"] ?? "");

if ($currentUserId === null) {
    echo json_encode([
        'success' => false,
        'message' => 'Giriş edilməyib.'
    ]);
    exit;
}

if ($conversationId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Conversation ID yanlışdır.'
    ]);
    exit;
}

if ($message === "") {
    echo json_encode([
        'success' => false,
        'message' => 'Mesaj boş ola bilməz.'
    ]);
    exit;
}

if (mb_strlen($message) > 5000) {
    echo json_encode([
        'success' => false,
        'message' => 'Mesaj çox uzundur.'
    ]);
    exit;
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

        echo json_encode([
            'success' => false,
            'message' => 'Bu söhbətə giriş icazəniz yoxdur.'
        ]);
        exit;
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

        echo json_encode([
            'success' => false,
            'message' => "Çox tez-tez mesaj göndərirsiniz. {$remainingSeconds} saniyə sonra yenidən cəhd edin."
        ]);
        exit;
    }

    $attemptResult = registerRateLimitAttempt($pdo, $messageRateKey, 10, 60, 60);

    if ($attemptResult['limited']) {
        $remainingSeconds = (int)$attemptResult['remaining_seconds'];

        appLog('rate_limit', 'Message spam limit triggered after attempt', [
            'user_id' => $currentUserId,
            'conversation_id' => $conversationId,
            'remaining_seconds' => $remainingSeconds
        ]);

        echo json_encode([
            'success' => false,
            'message' => "Mesaj limiti keçildi. {$remainingSeconds} saniyə sonra yenidən cəhd edin."
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO messages (conversation_id, sender_id, message)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$conversationId, $currentUserId, $message]);

    $stmt = $pdo->prepare("
        UPDATE conversations
        SET last_message_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$conversationId]);

    appLog('chat_action', 'Message sent successfully', [
        'user_id' => $currentUserId,
        'conversation_id' => $conversationId,
    ]);

    echo json_encode([
        'success' => true,
        'message' => nl2br(e($message)),
        'time' => 'indi'
    ]);
    exit;

} catch (PDOException $e) {
    error_log($e->getMessage());

    appLog('system_error', 'Message send DB error', [
        'user_id' => $currentUserId,
        'conversation_id' => $conversationId,
        'error' => $e->getMessage(),
    ]);

    echo json_encode([
        'success' => false,
        'message' => 'Mesaj göndərilərkən xəta baş verdi.'
    ]);
    exit;
}