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
        * { box-sizing: border-box; }

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

        .back-btn:hover { background: #eff6ff; }

        .chat-header-text { min-width: 0; }

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

        .message-row.mine { justify-content: flex-end; }
        .message-row.other { justify-content: flex-start; }

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

        .message-bubble.image-bubble {
            width: fit-content;
            max-width: min(82%, 280px);
            padding: 8px;
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

        .message-author { font-weight: 700; }
        .message-time { opacity: 0.92; }
        .message-status { opacity: 0.9; font-size: 12px; }

        .chat-image {
            display: block;
            max-width: 240px;
            max-height: 320px;
            width: auto;
            height: auto;
            border-radius: 12px;
            object-fit: contain;
            cursor: pointer;
            background: transparent;
        }

        .message-actions {
            margin-top: 8px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .message-action-btn {
            border: none;
            border-radius: 9px;
            padding: 6px 9px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .edit-btn {
            background: rgba(255, 255, 255, 0.22);
            color: #ffffff;
        }

        .delete-btn {
            background: rgba(239, 68, 68, 0.95);
            color: #ffffff;
        }

        .edit-box {
            display: none;
            margin-top: 8px;
        }

        .edit-box textarea {
            width: 100%;
            min-height: 70px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.45);
            padding: 10px;
            font-size: 14px;
            color: #0f172a;
            background: #fff;
            resize: vertical;
        }

        .edit-box-actions {
            display: flex;
            gap: 8px;
            margin-top: 8px;
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

        .image-preview {
            display: none;
            align-items: center;
            gap: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 10px;
            margin-bottom: 10px;
        }

        .image-preview img {
            width: 64px;
            height: 64px;
            border-radius: 12px;
            object-fit: cover;
            border: 1px solid #e2e8f0;
            background: #fff;
        }

        .image-preview-info {
            flex: 1;
            min-width: 0;
        }

        .image-preview-title {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .image-preview-name {
            font-size: 12px;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .preview-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        .preview-btn {
            border: none;
            border-radius: 10px;
            padding: 9px 11px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .preview-send {
            background: #2563eb;
            color: #fff;
        }

        .preview-cancel {
            background: #e2e8f0;
            color: #1e293b;
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

        textarea::placeholder { color: #64748b; }

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

        .send-btn:hover { transform: translateY(-1px); }

        .send-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .image-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 999;
            background: rgba(15, 23, 42, 0.92);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .image-modal.is-open {
            display: flex;
        }

        .image-modal img {
            max-width: 100%;
            max-height: 90vh;
            object-fit: contain;
            border-radius: 12px;
            background: #000;
        }

        .image-modal-close {
            position: fixed;
            top: 14px;
            right: 14px;
            width: 42px;
            height: 42px;
            border: none;
            border-radius: 50%;
            background: #ffffff;
            color: #0f172a;
            font-size: 22px;
            font-weight: 700;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .chat-page { background: #ffffff; }

            .chat-header { padding: 12px 14px; }

            .back-btn {
                width: 38px;
                height: 38px;
                font-size: 22px;
            }

            .chat-header-text h1 { font-size: 18px; }
            .chat-header-text p { font-size: 12px; }

            .chat-messages {
                padding: 14px;
                gap: 12px;
            }

            .message-bubble { max-width: 85%; }

            .message-bubble.image-bubble {
                max-width: 78%;
                padding: 7px;
            }

            .chat-image {
                max-width: 210px;
                max-height: 280px;
            }

            .chat-form-wrap {
                padding: 10px 12px calc(10px + env(safe-area-inset-bottom));
            }

            .input-shell {
                border-radius: 20px;
                padding: 8px 8px 8px 12px;
                gap: 8px;
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

            .image-preview {
                align-items: flex-start;
            }

            .preview-actions {
                flex-direction: column;
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
                    <?php
                        $isMine = (int)$msg["sender_id"] === $currentUserId;
                        $messageType = $msg["message_type"] ?? "text";
                        $messageStatus = ((int)($msg["is_read"] ?? 0) === 1) ? "Oxundu ✓✓" : "Göndərildi ✓";
                        $bubbleClass = $messageType === "image" ? "message-bubble image-bubble" : "message-bubble";
                    ?>
                    <div class="message-row <?php echo $isMine ? 'mine' : 'other'; ?>">
                        <div class="<?php echo e($bubbleClass); ?>">
                            <div class="message-meta">
                                <span class="message-author"><?php echo e($msg["name"] ?? "İstifadəçi"); ?></span>
                                <span>•</span>
                                <span class="message-time"><?php echo e(formatRelativeTime($msg["created_at"] ?? "")); ?></span>
                                <?php if ($isMine): ?>
                                    <span>•</span>
                                    <span class="message-status"><?php echo e($messageStatus); ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if ($messageType === "image"): ?>
                                <img
                                    src="<?php echo e(basePath('uploads/' . ltrim((string)$msg["message"], '/'))); ?>"
                                    class="chat-image js-full-image"
                                    alt="Göndərilən şəkil"
                                >
                            <?php else: ?>
                                <div class="message-text"><?php echo nl2br(e($msg["message"])); ?></div>

                                <?php if ($isMine): ?>
                                    <div class="edit-box" id="editBox<?php echo (int)$msg["id"]; ?>">
                                        <form method="POST" action="<?php echo e(basePath('edit_message.php')); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
                                            <input type="hidden" name="message_id" value="<?php echo (int)$msg["id"]; ?>">
                                            <textarea name="message" required><?php echo e($msg["message"]); ?></textarea>
                                            <div class="edit-box-actions">
                                                <button type="submit" class="message-action-btn edit-btn">Yadda saxla</button>
                                                <button type="button" class="message-action-btn delete-btn js-cancel-edit" data-edit-box="editBox<?php echo (int)$msg["id"]; ?>">Bağla</button>
                                            </div>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($isMine): ?>
                                <div class="message-actions">
                                    <?php if ($messageType === "text"): ?>
                                        <button type="button" class="message-action-btn edit-btn js-edit-btn" data-edit-box="editBox<?php echo (int)$msg["id"]; ?>">Edit</button>
                                    <?php endif; ?>

                                    <form method="POST" action="<?php echo e(basePath('delete_message.php')); ?>" onsubmit="return confirm('Bu mesaj silinsin?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
                                        <input type="hidden" name="message_id" value="<?php echo (int)$msg["id"]; ?>">
                                        <button type="submit" class="message-action-btn delete-btn">Sil</button>
                                    </form>
                                </div>
                            <?php endif; ?>
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

            <div class="image-preview" id="imagePreview">
                <img src="" alt="Seçilən şəkil" id="previewImage">
                <div class="image-preview-info">
                    <div class="image-preview-title">Şəkil seçildi</div>
                    <div class="image-preview-name" id="previewName"></div>
                </div>
                <div class="preview-actions">
                    <button type="button" class="preview-btn preview-send" id="sendImageBtn">Göndər</button>
                    <button type="button" class="preview-btn preview-cancel" id="cancelImageBtn">Ləğv et</button>
                </div>
            </div>

            <form method="POST" id="chatForm" class="chat-form">
                <input type="hidden" name="conversation_id" value="<?php echo (int)$conversationId; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">

                <div class="input-shell">
                    <textarea
                        name="message"
                        id="messageInput"
                        placeholder="Mesajınızı yazın..."
                        required
                    ></textarea>

                    <input type="file" id="chatImage" style="display:none;" accept="image/*">

                    <button type="button" class="send-btn" id="imageBtn" aria-label="Şəkil">📷</button>
                    <button type="button" class="send-btn" id="sendBtn" aria-label="Göndər">➤</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="image-modal" id="imageModal">
    <button type="button" class="image-modal-close" id="closeImageModal">×</button>
    <img src="" alt="Tam ekran şəkil" id="modalImage">
</div>

<script>
const chatMessages = document.getElementById("chatMessages");
const chatForm = document.getElementById("chatForm");
const messageInput = document.getElementById("messageInput");
const sendBtn = document.getElementById("sendBtn");
const chatError = document.getElementById("chatError");

const imageBtn = document.getElementById("imageBtn");
const chatImage = document.getElementById("chatImage");
const imagePreview = document.getElementById("imagePreview");
const previewImage = document.getElementById("previewImage");
const previewName = document.getElementById("previewName");
const sendImageBtn = document.getElementById("sendImageBtn");
const cancelImageBtn = document.getElementById("cancelImageBtn");

const imageModal = document.getElementById("imageModal");
const modalImage = document.getElementById("modalImage");
const closeImageModal = document.getElementById("closeImageModal");

let selectedImageFile = null;
let selectedImagePreviewUrl = null;

const myName = <?php echo json_encode($_SESSION["name"] ?? $_SESSION["user_name"] ?? "Siz"); ?>;

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

function escapeHtml(value) {
    return String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function removeEmptyChat() {
    const currentEmptyChat = document.getElementById("emptyChat");
    if (currentEmptyChat) {
        currentEmptyChat.remove();
    }
}

function appendMessage(messageHtml, timeText, authorName, statusText = "Göndərildi ✓") {
    if (!chatMessages) return;

    removeEmptyChat();

    const row = document.createElement("div");
    row.className = "message-row mine";

    const bubble = document.createElement("div");
    bubble.className = "message-bubble";

    const meta = document.createElement("div");
    meta.className = "message-meta";
    meta.innerHTML = `
        <span class="message-author">${escapeHtml(authorName)}</span>
        <span>•</span>
        <span class="message-time">${escapeHtml(timeText)}</span>
        <span>•</span>
        <span class="message-status">${escapeHtml(statusText)}</span>
    `;

    const content = document.createElement("div");
    content.innerHTML = messageHtml;

    bubble.appendChild(meta);
    bubble.appendChild(content);
    row.appendChild(bubble);
    chatMessages.appendChild(row);

    scrollToBottom(true);
}

function appendImageMessage(imageUrl, timeText, authorName, statusText = "Göndərildi ✓") {
    if (!chatMessages) return;

    removeEmptyChat();

    const safeUrl = escapeHtml(imageUrl);

    const row = document.createElement("div");
    row.className = "message-row mine";

    const bubble = document.createElement("div");
    bubble.className = "message-bubble image-bubble";

    const meta = document.createElement("div");
    meta.className = "message-meta";
    meta.innerHTML = `
        <span class="message-author">${escapeHtml(authorName)}</span>
        <span>•</span>
        <span class="message-time">${escapeHtml(timeText)}</span>
        <span>•</span>
        <span class="message-status">${escapeHtml(statusText)}</span>
    `;

    const img = document.createElement("img");
    img.src = safeUrl;
    img.className = "chat-image js-full-image";
    img.alt = "Göndərilən şəkil";

    bubble.appendChild(meta);
    bubble.appendChild(img);
    row.appendChild(bubble);
    chatMessages.appendChild(row);

    scrollToBottom(true);
}

function keepComposerVisible() {
    setTimeout(() => scrollToBottom(true), 80);
    setTimeout(() => scrollToBottom(true), 220);
}

function clearImagePreview() {
    selectedImageFile = null;

    if (selectedImagePreviewUrl) {
        URL.revokeObjectURL(selectedImagePreviewUrl);
        selectedImagePreviewUrl = null;
    }

    if (chatImage) chatImage.value = "";
    if (previewImage) previewImage.src = "";
    if (previewName) previewName.textContent = "";
    if (imagePreview) imagePreview.style.display = "none";
}

async function sendMessage() {
    hideError();

    const message = messageInput.value.trim();
    if (message === "") {
        showError("Mesaj boş ola bilməz.");
        return;
    }

    const formData = new FormData(chatForm);
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

async function sendSelectedImage() {
    hideError();

    if (!selectedImageFile) {
        showError("Şəkil seçilməyib.");
        return;
    }

    const formData = new FormData();
    formData.append("image", selectedImageFile);
    formData.append("conversation_id", <?php echo (int)$conversationId; ?>);
    formData.append("csrf_token", "<?php echo e(csrfToken()); ?>");

    if (sendImageBtn) {
        sendImageBtn.disabled = true;
        sendImageBtn.textContent = "Göndərilir...";
    }

    try {
        const localPreviewUrl = selectedImagePreviewUrl;

        const response = await fetch("/send_chat_image.php", {
            method: "POST",
            body: formData,
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            }
        });

        if (response.ok) {
            appendImageMessage(localPreviewUrl, "indi", myName, "Göndərildi ✓");
            clearImagePreview();
            keepComposerVisible();
        } else {
            showError("Şəkil göndərilə bilmədi.");
        }
    } catch (e) {
        showError("Şəkil göndərilə bilmədi.");
    } finally {
        if (sendImageBtn) {
            sendImageBtn.disabled = false;
            sendImageBtn.textContent = "Göndər";
        }
    }
}

window.addEventListener("load", function () {
    hideError();
    scrollToBottom(true);
    autoResizeTextarea();
});

if (messageInput) {
    messageInput.addEventListener("input", function () {
        autoResizeTextarea();
        hideError();
    });

    messageInput.addEventListener("focus", function () {
        setTimeout(() => scrollToBottom(true), 250);
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

if (imageBtn && chatImage) {
    imageBtn.addEventListener("click", function () {
        chatImage.click();
    });

    chatImage.addEventListener("change", function () {
        hideError();

        if (!chatImage.files || !chatImage.files.length) {
            clearImagePreview();
            return;
        }

        const file = chatImage.files[0];

        if (!file.type.startsWith("image/")) {
            clearImagePreview();
            showError("Yalnız şəkil faylı seçilə bilər.");
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            clearImagePreview();
            showError("Şəkil maksimum 5 MB ola bilər.");
            return;
        }

        selectedImageFile = file;

        if (selectedImagePreviewUrl) {
            URL.revokeObjectURL(selectedImagePreviewUrl);
        }

        selectedImagePreviewUrl = URL.createObjectURL(file);

        previewImage.src = selectedImagePreviewUrl;
        previewName.textContent = file.name;
        imagePreview.style.display = "flex";

        setTimeout(() => scrollToBottom(true), 150);
    });
}

if (sendImageBtn) {
    sendImageBtn.addEventListener("click", function () {
        sendSelectedImage();
    });
}

if (cancelImageBtn) {
    cancelImageBtn.addEventListener("click", function () {
        clearImagePreview();
    });
}

document.querySelectorAll(".js-edit-btn").forEach(function (btn) {
    btn.addEventListener("click", function () {
        const editBoxId = btn.dataset.editBox;
        const editBox = document.getElementById(editBoxId);
        if (editBox) {
            editBox.style.display = editBox.style.display === "block" ? "none" : "block";
        }
    });
});

document.querySelectorAll(".js-cancel-edit").forEach(function (btn) {
    btn.addEventListener("click", function () {
        const editBoxId = btn.dataset.editBox;
        const editBox = document.getElementById(editBoxId);
        if (editBox) {
            editBox.style.display = "none";
        }
    });
});

document.addEventListener("click", function (event) {
    const img = event.target.closest(".js-full-image");
    if (!img || !imageModal || !modalImage) return;

    modalImage.src = img.src;
    imageModal.classList.add("is-open");
});

if (closeImageModal) {
    closeImageModal.addEventListener("click", function () {
        imageModal.classList.remove("is-open");
        modalImage.src = "";
    });
}

if (imageModal) {
    imageModal.addEventListener("click", function (event) {
        if (event.target === imageModal) {
            imageModal.classList.remove("is-open");
            modalImage.src = "";
        }
    });
}

if (chatForm) {
    chatForm.addEventListener("submit", function (event) {
        event.preventDefault();
    });
}

window.addEventListener("resize", function () {
    setTimeout(() => scrollToBottom(true), 150);
});

if (window.visualViewport) {
    window.visualViewport.addEventListener("resize", function () {
        setTimeout(() => scrollToBottom(true), 180);
    });
}
</script>
</body>
</html>