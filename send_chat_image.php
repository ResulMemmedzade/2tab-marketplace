<?php

require_once "config.php";

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

    if ($file["error"] !== UPLOAD_ERR_OK) {
        responseJson(false, "Upload error");
    }

    if ($file["size"] > 10 * 1024 * 1024) {
        responseJson(false, "Max 10MB");
    }

    $uploadDir = __DIR__ . "/uploads/chat/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file["tmp_name"]);
    finfo_close($finfo);

    $allowedMimeTypes = [
        "image/jpeg" => "jpg",
        "image/png"  => "png",
        "image/webp" => "webp"
    ];

    if (!isset($allowedMimeTypes[$mime])) {
        responseJson(false, "Invalid file type");
    }

    $extension = $allowedMimeTypes[$mime];
    $filename = bin2hex(random_bytes(16)) . "." . $extension;

    $targetPath = $uploadDir . $filename;

    if (!move_uploaded_file($file["tmp_name"], $targetPath)) {
        responseJson(false, "Şəkil serverə yüklənə bilmədi");
    }

    $dbPath = "chat/" . $filename;

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