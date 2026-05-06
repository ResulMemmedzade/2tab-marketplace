<?php

require_once "config.php";
require_once "upload_helper.php";
require_once "send_push_notification.php";

requireLogin();
ensureCsrfToken();

header("Content-Type: application/json; charset=utf-8");

function responseJson($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message
    ], $extra));
    exit;
}

try {
    verifyCsrfToken($_POST["csrf_token"] ?? null);

    $conversationId = (int)($_POST["conversation_id"] ?? 0);
    $userId = currentUserId();

    if ($conversationId <= 0 || !isset($_FILES["image"])) {
        responseJson(false, "Invalid request");
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM conversations
        WHERE id = ?
          AND (user_one_id = ? OR user_two_id = ?)
        LIMIT 1
    ");
    $stmt->execute([$conversationId, $userId, $userId]);
    $conversation = $stmt->fetch();

    if (!$conversation) {
        responseJson(false, "Access denied");
    }

    $file = $_FILES["image"];
    $uploadDir = __DIR__ . "/uploads/chat/";

    [$uploadOk, $uploadMessage, $savedFileName] = saveUploadedImage(
        $file,
        $uploadDir,
        10 * 1024 * 1024
    );

    if (!$uploadOk) {
        responseJson(false, $uploadMessage);
    }

    $dbPath = "chat/" . $savedFileName;

    $stmt = $pdo->prepare("
        INSERT INTO messages (conversation_id, sender_id, message, message_type, is_read)
        VALUES (?, ?, ?, 'image', 0)
    ");
    $stmt->execute([$conversationId, $userId, $dbPath]);

    $messageId = (int)$pdo->lastInsertId();

    $pdo->prepare("
        UPDATE conversations
        SET last_message_at = NOW()
        WHERE id = ?
    ")->execute([$conversationId]);

    $receiverId = null;

    if ((int)$conversation["user_one_id"] === (int)$userId) {
        $receiverId = (int)$conversation["user_two_id"];
    } elseif ((int)$conversation["user_two_id"] === (int)$userId) {
        $receiverId = (int)$conversation["user_one_id"];
    }

    if ($receiverId !== null && function_exists("sendPushNotificationToUser")) {
        $senderName = $_SESSION["name"] ?? "2tab istifadəçisi";

        sendPushNotificationToUser(
            $pdo,
            $receiverId,
            "Yeni şəkil - " . $senderName,
            "Sizə yeni şəkil göndərildi.",
            basePath("conversation.php?id=" . $conversationId)
        );
    }

    responseJson(true, "Şəkil göndərildi", [
        "message_id" => $messageId,
        "raw_message" => $dbPath,
        "message_type" => "image",
        "is_read" => 0,
        "image_url" => basePath("image.php?file=" . urlencode($dbPath)),
        "time" => "indi"
    ]);

} catch (Throwable $e) {
    error_log($e->getMessage());
    responseJson(false, "Server xətası");
}