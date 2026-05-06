<?php

require_once "config.php";

requireLogin();

header("Content-Type: application/json; charset=utf-8");

function responseJson($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message
    ], $extra));
    exit;
}

function formatRelativeTimeForFetch($dateTimeString)
{
    if (empty($dateTimeString)) return "";

    $timestamp = strtotime($dateTimeString);
    if ($timestamp === false) return $dateTimeString;

    $diff = time() - $timestamp;

    if ($diff < 60) return "indi";

    $minutes = floor($diff / 60);
    if ($minutes < 60) return $minutes . " dəq əvvəl";

    $hours = floor($diff / 3600);
    if ($hours < 24) return $hours . " saat əvvəl";

    $days = floor($diff / 86400);
    if ($days === 1) return "dünən";

    if ($days < 7) return $days . " gün əvvəl";

    return date("d.m.Y H:i", $timestamp);
}

try {
    $currentUserId = currentUserId();
    $conversationId = (int)($_GET["conversation_id"] ?? 0);
    $afterId = (int)($_GET["after_id"] ?? 0);

    if ($currentUserId === null) {
        responseJson(false, "Giriş edilməyib.");
    }

    if ($conversationId <= 0) {
        responseJson(false, "Conversation ID yanlışdır.");
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM conversations
        WHERE id = ?
          AND (user_one_id = ? OR user_two_id = ?)
        LIMIT 1
    ");
    $stmt->execute([$conversationId, $currentUserId, $currentUserId]);

    if (!$stmt->fetch()) {
        responseJson(false, "Bu söhbətə giriş icazəniz yoxdur.");
    }

    $pdo->prepare("
        UPDATE messages
        SET is_read = 1
        WHERE conversation_id = ?
          AND sender_id != ?
          AND is_read = 0
    ")->execute([$conversationId, $currentUserId]);

    $stmt = $pdo->prepare("
        SELECT id, conversation_id, sender_id, message, message_type, is_read, created_at
        FROM messages
        WHERE conversation_id = ?
          AND id > ?
        ORDER BY created_at ASC, id ASC
    ");
    $stmt->execute([$conversationId, $afterId]);

    $messages = [];

    while ($row = $stmt->fetch()) {
        $messageType = $row["message_type"] ?? "text";
        $rawMessage = (string)($row["message"] ?? "");

        $messages[] = [
            "id" => (int)$row["id"],
            "sender_id" => (int)$row["sender_id"],
            "is_mine" => (int)$row["sender_id"] === (int)$currentUserId,
            "message" => $rawMessage,
            "message_type" => $messageType,
            "is_read" => (int)($row["is_read"] ?? 0),
            "time" => formatRelativeTimeForFetch($row["created_at"] ?? ""),
            "image_url" => $messageType === "image"
                ? basePath("image.php?file=" . urlencode($rawMessage))
                : null
        ];
    }

    $stmt = $pdo->prepare("
        SELECT id, is_read
        FROM messages
        WHERE conversation_id = ?
          AND sender_id = ?
    ");
    $stmt->execute([$conversationId, $currentUserId]);

    $readStatuses = [];
    while ($row = $stmt->fetch()) {
        $readStatuses[] = [
            "id" => (int)$row["id"],
            "is_read" => (int)$row["is_read"]
        ];
    }

    responseJson(true, "OK", [
        "messages" => $messages,
        "read_statuses" => $readStatuses
    ]);

} catch (Throwable $e) {
    error_log($e->getMessage());
    responseJson(false, "Server xətası.");
}