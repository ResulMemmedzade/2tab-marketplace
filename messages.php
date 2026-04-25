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

    appLog('system_error', 'Messages list DB error', [
        'user_id' => $currentUserId,
        'error' => $e->getMessage(),
    ]);

    $conversations = [];
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2tab | Mesajlar</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f8fafc;
            color: #1e293b;
        }

        .container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px 32px;
        }

        .page-title {
            margin-bottom: 20px;
        }

        .page-title h1 {
            margin: 0 0 8px;
            font-size: 30px;
            color: #0f172a;
        }

        .page-title p {
            margin: 0;
            color: #64748b;
            line-height: 1.6;
        }

        .chat-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .chat-card {
            display: block;
            text-decoration: none;
            background: #fff;
            border-radius: 18px;
            padding: 18px 20px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            color: inherit;
            transition: 0.2s ease;
        }

        .chat-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.08);
            background: #fcfdff;
        }

        .chat-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .chat-name {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
        }

        .chat-time {
            font-size: 13px;
            color: #64748b;
            white-space: nowrap;
        }

        .chat-preview {
            color: #475569;
            line-height: 1.6;
            margin-bottom: 10px;
            word-break: break-word;
        }

        .chat-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .chat-email {
            font-size: 13px;
            color: #64748b;
        }

        .unread-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #2563eb;
            color: #ffffff;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            min-width: 28px;
        }

        .empty {
            color: #64748b;
            padding: 28px;
            border: 1px dashed #cbd5e1;
            border-radius: 16px;
            background: #fff;
            text-align: center;
            line-height: 1.8;
        }

        .empty strong {
            display: block;
            color: #0f172a;
            font-size: 18px;
            margin-bottom: 8px;
        }

        @media (max-width: 768px) {
            .chat-top {
                align-items: flex-start;
                flex-direction: column;
            }

            .chat-time {
                white-space: normal;
            }
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
                        $preview = "Şəkil göndərildi";
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
                                <?php echo e($chat["other_user_name"] ?: $chat["other_user_email"] ?: "İstifadəçi"); ?>
                            </div>
                            <div class="chat-time">
                                <?php echo e(formatRelativeTime($chat["last_message_at"] ?? $chat["created_at"])); ?>
                            </div>
                        </div>

                        <div class="chat-preview">
                            <?php echo e($preview ?: "Hələ mesaj yoxdur."); ?>
                        </div>

                        <div class="chat-footer">
    <?php if ((int)$chat["unread_count"] > 0): ?>
        <span class="unread-badge">
            <?php echo (int)$chat["unread_count"]; ?> yeni mesaj
        </span>
    <?php endif; ?>
</div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty">
                <strong>Hələ heç bir söhbət yoxdur</strong>
                Söhbət başladıqdan sonra bütün mesajlaşmalar burada görünəcək.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
