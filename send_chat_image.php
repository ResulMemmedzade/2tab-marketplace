<?php

require_once "config.php";
require_once "upload_helper.php";
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

    $stmt = $GLOBALS["pdo"]->prepare("
        SELECT id
        FROM conversations
        WHERE id = ?
          AND (user_one_id = ? OR user_two_id = ?)
        LIMIT 1
    ");
    $stmt->execute([$conversationId, $userId, $userId]);

    if (!$stmt->fetch()) {
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
        INSERT INTO messages (conversation_id, sender_id, message, message_type)
        VALUES (?, ?, ?, 'image')
    ");
    $stmt->execute([$conversationId, $userId, $dbPath]);

    $messageId = (int)$pdo->lastInsertId();

    $pdo->prepare("
        UPDATE conversations
        SET last_message_at = NOW()
        WHERE id = ?
    ")->execute([$conversationId]);

    responseJson(true, "Şəkil göndərildi", [
        "message_id" => $messageId,
        "image_url" => basePath("uploads/" . $dbPath),
        "time" => "indi"
    ]);

} catch (Throwable $e) {
    error_log($e->getMessage());
    responseJson(false, "Server xətası");
}