<?php

require_once "config.php";
require_once "rate_limiter.php";

requireLogin();
ensureCsrfToken();

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

$conversationId = (int)($_GET["id"] ?? $_POST["conversation_id"] ?? 0);

if ($conversationId <= 0) {
    redirectTo("books.php");
}

$stmt = $pdo->prepare("
    SELECT *
    FROM conversations
    WHERE id = ?
      AND (user_one_id = ? OR user_two_id = ?)
    LIMIT 1
");
$stmt->execute([$conversationId, $currentUserId, $currentUserId]);
$conversation = $stmt->fetch();

if (!$conversation) {
    redirectTo("books.php");
}

$otherUserId = ($conversation["user_one_id"] == $currentUserId)
    ? (int)$conversation["user_two_id"]
    : (int)$conversation["user_one_id"];

$stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$otherUserId]);
$otherUser = $stmt->fetch();

if (!$otherUser) {
    redirectTo("books.php");
}

$stmt = $pdo->prepare("
    SELECT m.*, u.name
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.conversation_id = ?
    ORDER BY m.created_at ASC, m.id ASC
");
$stmt->execute([$conversationId]);
$messages = $stmt->fetchAll();

$stmt = $pdo->prepare("
    UPDATE messages
    SET is_read = 1
    WHERE conversation_id = ?
      AND sender_id != ?
");
$stmt->execute([$conversationId, $currentUserId]);
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Mesajlaşma - 2tab</title>
    <style>
        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f8fafc;
            color: #1e293b;
        }

        .chat-page {
            height: 100vh;
            height: 100dvh;
            display: flex;
            flex-direction: column;
            background: #f8fafc;
        }

        .chat-header {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 18px;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 30;
        }

        .back-btn {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #2563eb;
            font-size: 24px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .back-btn:hover {
            background: #eff6ff;
        }

        .chat-header-text {
            min-width: 0;
        }

        .chat-header-text h1 {
            margin: 0;
            font-size: 20px;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-header-text p {
            margin: 4px 0 0;
            color: #64748b;
            font-size: 13px;
            line-height: 1.4;
        }

        .chat-body {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .chat-messages {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 18px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            background: #f8fafc;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
            scroll-behavior: smooth;
        }

        .message-row {
            display: flex;
            width: 100%;
        }

        .message-row.mine {
            justify-content: flex-end;
        }

        .message-row.other {
            justify-content: flex-start;
        }

        .message-bubble {
            max-width: min(72%, 520px);
            padding: 12px 14px;
            border-radius: 16px;
            line-height: 1.6;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .message-row.mine .message-bubble {
            background: #2563eb;
            color: white;
            border-bottom-right-radius: 6px;
        }

        .message-row.other .message-bubble {
            background: #ffffff;
            color: #1e293b;
            border: 1px solid #e2e8f0;
            border-bottom-left-radius: 6px;
        }

        .message-meta {
            font-size: 12px;
            margin-bottom: 8px;
            opacity: 0.88;
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            align-items: center;
        }

        .message-author {
            font-weight: 700;
        }

        .message-time {
            opacity: 0.92;
        }

        .empty-chat {
            color: #64748b;
            text-align: center;
            padding: 60px 20px;
            line-height: 1.8;
            margin: auto 0;
        }

        .empty-chat strong {
            display: block;
            color: #0f172a;
            margin-bottom: 8px;
            font-size: 18px;
        }

        .chat-form-wrap {
            flex-shrink: 0;
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            padding: 12px 14px calc(12px + env(safe-area-inset-bottom));
            position: sticky;
            bottom: 0;
            z-index: 25;
        }

        .alert-error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 10px 12px;
            border-radius: 12px;
            margin-bottom: 10px;
            display: none;
        }

        .chat-form {
            display: flex;
            align-items: flex-end;
            gap: 10px;
        }

        .input-shell {
            flex: 1;
            display: flex;
            align-items: flex-end;
            gap: 10px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 18px;
            padding: 10px 10px 10px 14px;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.04);
        }

        .input-shell:focus-within {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
        }

        textarea {
            flex: 1;
            border: none;
            outline: none;
            resize: none;
            min-height: 26px;
            max-height: 120px;
            padding: 6px 0;
            font-size: 16px;
            font-family: Arial, sans-serif;
            line-height: 1.5;
            color: #0f172a;
            background: transparent;
            overflow-y: auto;
        }

        textarea::placeholder {
            color: #64748b;
        }

        .send-btn {
            width: 46px;
            height: 46px;
            min-width: 46px;
            border: none;
            border-radius: 50%;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 20px;
            font-weight: 700;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.28);
            transition: transform 0.15s ease, opacity 0.15s ease;
        }

        .send-btn:hover {
            transform: translateY(-1px);
        }

        .send-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        @media (max-width: 768px) {
            .chat-page {
                background: #ffffff;
            }

            .chat-header {
                padding: 12px 14px;
            }

            .back-btn {
                width: 38px;
                height: 38px;
                font-size: 22px;
            }

            .chat-header-text h1 {
                font-size: 18px;
            }

            .chat-header-text p {
                font-size: 12px;
            }

            .chat-messages {
                padding: 14px;
                gap: 12px;
            }

            .message-bubble {
                max-width: 85%;
            }

            .chat-form-wrap {
                padding: 10px 12px calc(10px + env(safe-area-inset-bottom));
            }

            .input-shell {
                border-radius: 20px;
                padding: 8px 8px 8px 12px;
            }

            textarea {
                font-size: 16px;
                max-height: 100px;
            }

            .send-btn {
                width: 44px;
                height: 44px;
                min-width: 44px;
            }
        }
    </style>
</head>
<body>
    <div class="chat-page">
        <div class="chat-header">
            <a href="/messages.php" class="back-btn" aria-label="Mesajlara qayıt">←</a>

            <div class="chat-header-text">
                <h1><?php echo e($otherUser["name"] ?? "İstifadəçi"); ?></h1>
                <p>Mesajlaşmanı buradan davam etdirə bilərsiniz.</p>
            </div>
        </div>

        <div class="chat-body">
            <div class="chat-messages" id="chatMessages">
                <?php if (count($messages) > 0): ?>
                    <?php foreach ($messages as $msg): ?>
                        <?php $isMine = (int)$msg["sender_id"] === $currentUserId; ?>
                        <div class="message-row <?php echo $isMine ? 'mine' : 'other'; ?>">
                            <div class="message-bubble">
                                <div class="message-meta">
                                    <span class="message-author"><?php echo e($msg["name"] ?? "İstifadəçi"); ?></span>
                                    <span>•</span>
                                    <span class="message-time"><?php echo e(formatRelativeTime($msg["created_at"] ?? "")); ?></span>
                                </div>
                                <div><?php echo nl2br(e($msg["message"])); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-chat" id="emptyChat">
                        <strong>Hələ mesaj yoxdur</strong>
                        İlk mesajı siz yazın.
                    </div>
                <?php endif; ?>
            </div>

            <div class="chat-form-wrap">
                <div class="alert-error" id="chatError"><?php echo $error ? e($error) : ''; ?></div>

                <form method="POST" id="chatForm" class="chat-form">
                    <input type="hidden" name="conversation_id" value="<?php echo (int)$conversationId; ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

                    <div class="input-shell">
                        <textarea
                            name="message"
                            id="messageInput"
                            placeholder="Mesajınızı yazın..."
                            required
                        ></textarea>

                        <button type="button" class="send-btn" id="sendBtn" aria-label="Göndər">➤</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    const chatMessages = document.getElementById("chatMessages");
    const chatForm = document.getElementById("chatForm");
    const messageInput = document.getElementById("messageInput");
    const sendBtn = document.getElementById("sendBtn");
    const chatError = document.getElementById("chatError");
    const emptyChat = document.getElementById("emptyChat");

    function scrollToBottom(force = false) {
        if (!chatMessages) return;

        if (force) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
            return;
        }

        const distanceFromBottom = chatMessages.scrollHeight - chatMessages.scrollTop - chatMessages.clientHeight;
        if (distanceFromBottom < 150) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }

    function autoResizeTextarea() {
        if (!messageInput) return;
        messageInput.style.height = "auto";
        messageInput.style.height = Math.min(messageInput.scrollHeight, 120) + "px";
    }

    function showError(message) {
        if (!chatError) return;
        chatError.textContent = message;
        chatError.style.display = "block";
    }

    function hideError() {
        if (!chatError) return;
        chatError.textContent = "";
        chatError.style.display = "none";
    }

    function appendMessage(messageHtml, timeText, authorName) {
        if (!chatMessages) return;

        if (emptyChat) {
            emptyChat.remove();
        }

        const row = document.createElement("div");
        row.className = "message-row mine";

        const bubble = document.createElement("div");
        bubble.className = "message-bubble";

        const meta = document.createElement("div");
        meta.className = "message-meta";
        meta.innerHTML = `
            <span class="message-author">${authorName}</span>
            <span>•</span>
            <span class="message-time">${timeText}</span>
        `;

        const content = document.createElement("div");
        content.innerHTML = messageHtml;

        bubble.appendChild(meta);
        bubble.appendChild(content);
        row.appendChild(bubble);
        chatMessages.appendChild(row);

        scrollToBottom(true);
    }

    function keepComposerVisible() {
        setTimeout(() => {
            scrollToBottom(true);
        }, 80);

        setTimeout(() => {
            scrollToBottom(true);
        }, 220);
    }

    async function sendMessage() {
        hideError();

        const message = messageInput.value.trim();
        if (message === "") {
            showError("Mesaj boş ola bilməz.");
            return;
        }

        const formData = new FormData(chatForm);
        const myName = <?php echo json_encode($_SESSION["user_name"] ?? "Siz"); ?>;

        sendBtn.disabled = true;

        try {
            const response = await fetch("/send_message.php", {
                method: "POST",
                body: formData,
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                }
            });

            const data = await response.json();

            if (data.success) {
                appendMessage(data.message, data.time || "indi", myName);
                messageInput.value = "";
                autoResizeTextarea();
                keepComposerVisible();
            } else {
                showError(data.message || "Mesaj göndərilərkən xəta baş verdi.");
                keepComposerVisible();
            }
        } catch (error) {
            showError("Mesaj göndərilərkən xəta baş verdi.");
            keepComposerVisible();
        } finally {
            sendBtn.disabled = false;
        }
    }

    window.addEventListener("load", function () {
        hideError();
        scrollToBottom(true);
        autoResizeTextarea();
        keepComposerVisible();
    });

    if (messageInput) {
        messageInput.addEventListener("input", function () {
            autoResizeTextarea();
            hideError();
            keepComposerVisible();
        });

        messageInput.addEventListener("focus", function () {
            keepComposerVisible();
        });

        messageInput.addEventListener("click", function () {
            keepComposerVisible();
        });
    }

    if (sendBtn) {
    sendBtn.addEventListener("touchstart", function (event) {
        event.preventDefault();
        sendMessage();
    }, { passive: false });

    sendBtn.addEventListener("click", function (event) {
        event.preventDefault();
        sendMessage();
    });
}

    if (chatForm) {
        chatForm.addEventListener("submit", function (event) {
            event.preventDefault();
        });
    }

    window.addEventListener("resize", function () {
        keepComposerVisible();
    });

    if (window.visualViewport) {
        window.visualViewport.addEventListener("resize", keepComposerVisible);
        window.visualViewport.addEventListener("scroll", keepComposerVisible);
    }
</script>
</body>
</html>
