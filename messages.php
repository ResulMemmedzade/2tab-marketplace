<?php

require_once "config.php";

requireLogin();

$currentUserId = currentUserId();

function formatRelativeTime($dateTimeString)
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

    return date('d.m.Y H:i', $timestamp);
}

try {
    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.user_one_id,
            c.user_two_id,
            c.created_at,
            c.last_message_at,
            u.id AS other_user_id,
            u.name AS other_user_name,
            u.email AS other_user_email,
            (
                SELECT m.message
                FROM messages m
                WHERE m.conversation_id = c.id
                ORDER BY m.created_at DESC, m.id DESC
                LIMIT 1
            ) AS last_message,
            (
                SELECT m.message_type
                FROM messages m
                WHERE m.conversation_id = c.id
                ORDER BY m.created_at DESC, m.id DESC
                LIMIT 1
            ) AS last_message_type,
            (
                SELECT COUNT(*)
                FROM messages m2
                WHERE m2.conversation_id = c.id
                  AND m2.sender_id != ?
                  AND m2.is_read = 0
            ) AS unread_count
        FROM conversations c
        JOIN users u
            ON u.id = CASE
                WHEN c.user_one_id = ? THEN c.user_two_id
                ELSE c.user_one_id
            END
        WHERE c.user_one_id = ? OR c.user_two_id = ?
        ORDER BY c.last_message_at DESC, c.id DESC
    ");

    $stmt->execute([
        $currentUserId,
        $currentUserId,
        $currentUserId,
        $currentUserId
    ]);

    $conversations = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log($e->getMessage());
    $conversations = [];
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>2tab | Mesajlar</title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root {
    --background: #faf8f5;
    --foreground: #2d2a26;
    --card: #ffffff;
    --primary: #c4704b;
    --primary-hover: #b5613c;
    --secondary: #f3efe9;
    --muted: #f0ebe4;
    --muted-foreground: #7a756d;
    --border: #e5dfd6;
    --radius: 16px;
    --radius-sm: 12px;
    --shadow: 0 4px 20px rgba(45,42,38,0.06);
    --shadow-lg: 0 12px 40px rgba(45,42,38,0.08);
}

* { box-sizing: border-box; }

body {
    margin: 0;
    font-family: 'Inter', sans-serif;
    background: var(--background);
    color: var(--foreground);
}

.container {
    max-width: 1000px;
    margin: 30px auto;
    padding: 0 20px 40px;
}

.page-title h1 {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 20px;
}

.chat-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.chat-card {
    background: var(--card);
    border-radius: var(--radius);
    padding: 16px 18px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    text-decoration: none;
    color: inherit;
    transition: 0.25s ease;
}

.chat-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
}

.chat-top {
    display: flex;
    justify-content: space-between;
    margin-bottom: 6px;
}

.chat-name {
    font-weight: 600;
}

.chat-time {
    font-size: 13px;
    color: var(--muted-foreground);
}

.chat-preview {
    font-size: 14px;
    color: var(--muted-foreground);
    margin-bottom: 8px;
}

.unread-badge {
    background: var(--primary);
    color: #fff;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
}

.empty {
    background: var(--card);
    border: 2px dashed var(--border);
    border-radius: var(--radius);
    padding: 40px;
    text-align: center;
}
</style>
</head>

<body>
<?php require_once __DIR__ . '/includes/topbar.php'; ?>

<div class="container">
    <div class="page-title">
        <h1>Mesajlar</h1>
    </div>

    <?php if (count($conversations) > 0): ?>
        <div class="chat-list">
            <?php foreach ($conversations as $chat): ?>
                <?php
                $lastMessageType = $chat["last_message_type"] ?? "text";

                if ($lastMessageType === "image") {
                    $preview = "(Şəkil göndərilib)";
                } else {
                    $preview = trim((string)($chat["last_message"] ?? ""));
                    if ($preview !== "" && mb_strlen($preview) > 90) {
                        $preview = mb_substr($preview, 0, 90) . "...";
                    }
                }
                ?>
                <a class="chat-card" href="<?= e(basePath('conversation.php?id=' . (int)$chat["id"])) ?>">
                    <div class="chat-top">
                        <div class="chat-name">
                            <?= e($chat["other_user_name"] ?: "İstifadəçi") ?>
                        </div>
                        <div class="chat-time">
                            <?= e(formatRelativeTime($chat["last_message_at"] ?? $chat["created_at"])) ?>
                        </div>
                    </div>

                    <div class="chat-preview">
                        <?= e($preview ?: "Hələ mesaj yoxdur.") ?>
                    </div>

                    <?php if ((int)$chat["unread_count"] > 0): ?>
                        <span class="unread-badge">
                            <?= (int)$chat["unread_count"] ?> yeni mesaj
                        </span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty">
            <strong>Hələ heç bir söhbət yoxdur</strong><br>
            Söhbət başladıqdan sonra burada görünəcək.
        </div>
    <?php endif; ?>
</div>

</body>
</html>