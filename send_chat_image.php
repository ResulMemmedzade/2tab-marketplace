<?php
require_once "config.php";

ensureCsrfToken();

if (!isLoggedIn()) {
    http_response_code(403);
    exit("Unauthorized");
}

verifyCsrfToken($_POST["csrf_token"] ?? null);

$conversation_id = (int)($_POST["conversation_id"] ?? 0);
$user_id = currentUserId();

if (!$conversation_id || !isset($_FILES['image'])) {
    exit("Invalid request");
}

// 🔐 Conversation access yoxla
$stmt = $pdo->prepare("
    SELECT id FROM conversations
    WHERE id = ?
      AND (user_one_id = ? OR user_two_id = ?)
");
$stmt->execute([$conversation_id, $user_id, $user_id]);

if (!$stmt->fetch()) {
    exit("Access denied");
}

// 📦 File yoxlaması
$file = $_FILES['image'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    exit("Upload error");
}

if ($file['size'] > 5 * 1024 * 1024) {
    exit("Max 5MB");
}

// MIME check
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowed = ['image/jpeg', 'image/png', 'image/webp'];

if (!in_array($mime, $allowed)) {
    exit("Invalid file type");
}

// 📁 Save file
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid("chat_", true) . "." . $ext;

$uploadPath = __DIR__ . "/uploads/chat/" . $filename;

move_uploaded_file($file['tmp_name'], $uploadPath);

// 💾 DB insert
$stmt = $pdo->prepare("
    INSERT INTO messages (conversation_id, sender_id, message, message_type)
    VALUES (?, ?, ?, 'image')
");
$stmt->execute([$conversation_id, $user_id, "chat/" . $filename]);

// update last_message_at
$pdo->prepare("
    UPDATE conversations
    SET last_message_at = NOW()
    WHERE id = ?
")->execute([$conversation_id]);

// redirect back
redirect(basePath("conversation.php?id=" . $conversation_id));