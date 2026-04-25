<?php
require_once "config.php";

requireLogin();
ensureCsrfToken();

verifyCsrfToken($_POST["csrf_token"] ?? null);

$currentUserId = currentUserId();
$messageId = (int)($_POST["message_id"] ?? 0);
$newMessage = trim((string)($_POST["message"] ?? ""));

if ($messageId <= 0 || $newMessage === "") {
    http_response_code(400);
    exit("Invalid request");
}

$stmt = $pdo->prepare("
    SELECT m.id, m.conversation_id, m.sender_id, m.message_type
    FROM messages m
    JOIN conversations c ON m.conversation_id = c.id
    WHERE m.id = ?
      AND m.sender_id = ?
      AND (c.user_one_id = ? OR c.user_two_id = ?)
    LIMIT 1
");
$stmt->execute([$messageId, $currentUserId, $currentUserId, $currentUserId]);
$message = $stmt->fetch();

if (!$message) {
    http_response_code(403);
    exit("Access denied");
}

if (($message["message_type"] ?? "text") !== "text") {
    http_response_code(400);
    exit("Only text messages can be edited");
}

$stmt = $pdo->prepare("
    UPDATE messages
    SET message = ?
    WHERE id = ?
      AND sender_id = ?
");
$stmt->execute([$newMessage, $messageId, $currentUserId]);

redirect(basePath("conversation.php?id=" . (int)$message["conversation_id"]));