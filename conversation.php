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
            height: 100%;
            min-height: 100dvh;
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
            padding: 18px 18px 12px;
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
            position: relative;
            max-width: min(72%, 520px);
            padding: 12px 14px;
            border-radius: 16px;
            line-height: 1.6;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
            word-wrap: break-word;
            overflow-wrap: break-word;
            user-select: none;
            -webkit-user-select: none;
            touch-action: manipulation;
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

        .message-bubble.selected {
            outline: 3px solid rgba(37, 99, 235, 0.35);
            transform: scale(0.99);
        }

        .message-bubble.image-bubble {
            width: fit-content;
            max-width: min(82%, 280px);
            padding: 8px;
            overflow: hidden;
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

        .message-text {
            white-space: normal;
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

        .message-action-btn {
            border: none;
            border-radius: 9px;
            padding: 8px 11px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .edit-btn {
            background: #2563eb;
            color: #ffffff;
        }

        .delete-btn {
            background: #ef4444;
            color: #ffffff;
        }

        .cancel-btn {
            background: #e2e8f0;
            color: #1e293b;
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

        .selection-bar {
            display: none;
            position: fixed;
            left: 12px;
            right: 12px;
            bottom: calc(12px + env(safe-area-inset-bottom));
            z-index: 800;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 12px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.18);
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .selection-bar.is-open {
            display: flex;
        }

        .selection-title {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
        }

        .selection-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
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
                padding: 14px 14px 10px;
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
                        $isRead = (int)($msg["is_read"] ?? 0) === 1;
                        $messageStatus = $isRead ? "Oxundu ✓✓" : "Göndərildi ✓";
                        $bubbleClass = $messageType === "image" ? "message-bubble image-bubble" : "message-bubble";
                        $canEdit = $isMine && $messageType === "text" && !$isRead;
                        $canDelete = $isMine;
                    ?>

                    <div class="message-row <?php echo $isMine ? 'mine' : 'other'; ?>">
                        <div
                            class="<?php echo e($bubbleClass); ?>"
                            data-message-id="<?php echo (int)$msg["id"]; ?>"
                            data-message-type="<?php echo e($messageType); ?>"
                            data-can-edit="<?php echo $canEdit ? '1' : '0'; ?>"
                            data-can-delete="<?php echo $canDelete ? '1' : '0'; ?>"
                        >
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

                                <?php if ($canEdit): ?>
                                    <div class="edit-box">
                                        <textarea class="edit-message-input" required><?php echo e($msg["message"]); ?></textarea>
                                        <div class="edit-box-actions">
                                            <button type="button" class="message-action-btn edit-btn js-save-edit">Yadda saxla</button>
                                            <button type="button" class="message-action-btn cancel-btn js-cancel-edit">Bağla</button>
                                        </div>
                                    </div>
                                <?php endif; ?>
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

        <div class="chat-form-wrap" id="composerWrap">
            <div class="alert-error" id="chatError"></div>

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

                    <input type="file" name="image" id="chatImage" style="display:none;" accept="image/*">

                    <button type="button" class="send-btn" id="imageBtn" aria-label="Şəkil">📷</button>
                    <button type="button" class="send-btn" id="sendBtn" aria-label="Göndər">➤</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="selection-bar" id="selectionBar">
    <div class="selection-title">Mesaj seçildi</div>
    <div class="selection-actions">
        <button type="button" class="message-action-btn edit-btn" id="selectionEditBtn">Edit</button>
        <button type="button" class="message-action-btn delete-btn" id="selectionDeleteBtn">Sil</button>
        <button type="button" class="message-action-btn cancel-btn" id="selectionCancelBtn">Bağla</button>
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

const selectionBar = document.getElementById("selectionBar");
const selectionEditBtn = document.getElementById("selectionEditBtn");
const selectionDeleteBtn = document.getElementById("selectionDeleteBtn");
const selectionCancelBtn = document.getElementById("selectionCancelBtn");

let selectedImageFile = null;
let selectedImagePreviewUrl = null;
let selectedBubble = null;
let pressTimer = null;
let didLongPress = false;

const csrfToken = <?php echo json_encode(csrfToken()); ?>;
const conversationId = <?php echo (int)$conversationId; ?>;
const myName = <?php echo json_encode($_SESSION["name"] ?? $_SESSION["user_name"] ?? "Siz"); ?>;

function scrollToBottom(force = false) {
    if (!chatMessages) return;

    requestAnimationFrame(function () {
        if (force) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
            return;
        }

        const distanceFromBottom = chatMessages.scrollHeight - chatMessages.scrollTop - chatMessages.clientHeight;

        if (distanceFromBottom < 180) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    });
}

function keepComposerVisible() {
    scrollToBottom(true);
    setTimeout(() => scrollToBottom(true), 80);
    setTimeout(() => scrollToBottom(true), 220);
    setTimeout(() => scrollToBottom(true), 420);
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
    keepComposerVisible();
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

function nl2br(value) {
    return escapeHtml(value).replace(/\n/g, "<br>");
}

function clearSelection() {
    if (selectedBubble) {
        selectedBubble.classList.remove("selected");
    }

    selectedBubble = null;

    if (selectionBar) {
        selectionBar.classList.remove("is-open");
    }
}

function openSelection(bubble) {
    if (!bubble) return;

    if (bubble.dataset.canDelete !== "1") {
        return;
    }

    clearSelection();

    selectedBubble = bubble;
    selectedBubble.classList.add("selected");

    if (selectionEditBtn) {
        selectionEditBtn.style.display = bubble.dataset.canEdit === "1" ? "inline-flex" : "none";
    }

    if (selectionDeleteBtn) {
        selectionDeleteBtn.style.display = bubble.dataset.canDelete === "1" ? "inline-flex" : "none";
    }

    if (selectionBar) {
        selectionBar.classList.add("is-open");
    }
}

function bindLongPressToBubble(bubble) {
    if (!bubble || bubble.dataset.longPressBound === "1") return;

    bubble.dataset.longPressBound = "1";

    bubble.addEventListener("touchstart", function () {
        didLongPress = false;

        pressTimer = setTimeout(function () {
            didLongPress = true;
            openSelection(bubble);
        }, 650);
    }, { passive: true });

    bubble.addEventListener("touchend", function () {
        clearTimeout(pressTimer);
    });

    bubble.addEventListener("touchmove", function () {
        clearTimeout(pressTimer);
    });

    bubble.addEventListener("mousedown", function () {
        didLongPress = false;

        pressTimer = setTimeout(function () {
            didLongPress = true;
            openSelection(bubble);
        }, 650);
    });

    bubble.addEventListener("mouseup", function () {
        clearTimeout(pressTimer);
    });

    bubble.addEventListener("mouseleave", function () {
        clearTimeout(pressTimer);
    });
}

function bindAllLongPress() {
    document.querySelectorAll(".message-bubble").forEach(bindLongPressToBubble);
}

function appendMessage(messageText, timeText, authorName, messageId = "", canEdit = true) {
    if (!chatMessages) return;

    removeEmptyChat();

    const row = document.createElement("div");
    row.className = "message-row mine";

    const bubble = document.createElement("div");
    bubble.className = "message-bubble";
    bubble.dataset.messageId = messageId;
    bubble.dataset.messageType = "text";
    bubble.dataset.canEdit = canEdit ? "1" : "0";
    bubble.dataset.canDelete = "1";

    const meta = document.createElement("div");
    meta.className = "message-meta";
    meta.innerHTML = `
        <span class="message-author">${escapeHtml(authorName)}</span>
        <span>•</span>
        <span class="message-time">${escapeHtml(timeText)}</span>
        <span>•</span>
        <span class="message-status">Göndərildi ✓</span>
    `;

    const text = document.createElement("div");
    text.className = "message-text";
    text.innerHTML = nl2br(messageText);

    if (canEdit) {
        const editBox = document.createElement("div");
        editBox.className = "edit-box";
        editBox.innerHTML = `
            <textarea class="edit-message-input" required>${escapeHtml(messageText)}</textarea>
            <div class="edit-box-actions">
                <button type="button" class="message-action-btn edit-btn js-save-edit">Yadda saxla</button>
                <button type="button" class="message-action-btn cancel-btn js-cancel-edit">Bağla</button>
            </div>
        `;
        bubble.appendChild(meta);
        bubble.appendChild(text);
        bubble.appendChild(editBox);
    } else {
        bubble.appendChild(meta);
        bubble.appendChild(text);
    }

    row.appendChild(bubble);
    chatMessages.appendChild(row);

    bindLongPressToBubble(bubble);
    scrollToBottom(true);
}

function appendImageMessage(imageUrl, timeText, authorName, messageId = "") {
    if (!chatMessages) return;

    removeEmptyChat();

    const row = document.createElement("div");
    row.className = "message-row mine";

    const bubble = document.createElement("div");
    bubble.className = "message-bubble image-bubble";
    bubble.dataset.messageId = messageId;
    bubble.dataset.messageType = "image";
    bubble.dataset.canEdit = "0";
    bubble.dataset.canDelete = "1";

    const meta = document.createElement("div");
    meta.className = "message-meta";
    meta.innerHTML = `
        <span class="message-author">${escapeHtml(authorName)}</span>
        <span>•</span>
        <span class="message-time">${escapeHtml(timeText)}</span>
        <span>•</span>
        <span class="message-status">Göndərildi ✓</span>
    `;

    const img = document.createElement("img");
    img.src = imageUrl;
    img.className = "chat-image js-full-image";
    img.alt = "Göndərilən şəkil";

    bubble.appendChild(meta);
    bubble.appendChild(img);
    row.appendChild(bubble);
    chatMessages.appendChild(row);

    bindLongPressToBubble(bubble);
    scrollToBottom(true);
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
        const response = await fetch("<?php echo e(basePath('send_message.php')); ?>", {
            method: "POST",
            body: formData,
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            }
        });

        let data = null;

        try {
            data = await response.json();
        } catch (e) {
            data = null;
        }

        if (response.ok && data && data.success) {
            appendMessage(
                data.raw_message || message,
                data.time || "indi",
                myName,
                data.message_id || "",
                true
            );

            messageInput.value = "";
            autoResizeTextarea();
            keepComposerVisible();
        } else {
            showError((data && data.message) ? data.message : "Mesaj göndərilərkən xəta baş verdi.");
        }
    } catch (error) {
        showError("Mesaj göndərilərkən xəta baş verdi.");
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
    formData.append("conversation_id", conversationId);
    formData.append("csrf_token", csrfToken);

    if (sendImageBtn) {
        sendImageBtn.disabled = true;
        sendImageBtn.textContent = "Göndərilir...";
    }

    try {
        const localPreviewUrl = selectedImagePreviewUrl;

        const response = await fetch("<?php echo e(basePath('upload_chat_image.php')); ?>", {
            method: "POST",
            body: formData,
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            }
        });

        let data = null;

        try {
            data = await response.json();
        } catch (e) {
            data = null;
        }

        if (response.ok) {
            const imageUrl = data && data.image_url ? data.image_url : localPreviewUrl;
            const messageId = data && data.message_id ? data.message_id : "";

            appendImageMessage(imageUrl, "indi", myName, messageId);
            clearImagePreview();
            keepComposerVisible();
        } else {
            showError((data && data.message) ? data.message : "Şəkil göndərilə bilmədi.");
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

async function deleteSelectedMessage() {
    if (!selectedBubble) return;

    const messageId = selectedBubble.dataset.messageId;

    if (!messageId) {
        showError("Mesaj ID tapılmadı. Səhifəni yeniləyib yenidən yoxla.");
        return;
    }

    if (!confirm("Bu mesaj silinsin?")) {
        return;
    }

    const formData = new FormData();
    formData.append("csrf_token", csrfToken);
    formData.append("message_id", messageId);

    try {
        const response = await fetch("<?php echo e(basePath('delete_message.php')); ?>", {
            method: "POST",
            body: formData,
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            }
        });

        if (response.ok || response.redirected) {
            const row = selectedBubble.closest(".message-row");
            if (row) row.remove();
            clearSelection();
            keepComposerVisible();
        } else {
            showError("Mesaj silinmədi.");
        }
    } catch (e) {
        showError("Mesaj silinmədi.");
    }
}

function openEditForSelected() {
    if (!selectedBubble) return;

    if (selectedBubble.dataset.canEdit !== "1") {
        showError("Bu mesaj artıq oxunub və edit etmək olmaz.");
        clearSelection();
        return;
    }

    const editBox = selectedBubble.querySelector(".edit-box");

    if (!editBox) {
        showError("Bu mesaj edit edilə bilməz.");
        clearSelection();
        return;
    }

    editBox.style.display = "block";

    const textarea = editBox.querySelector(".edit-message-input");
    if (textarea) {
        textarea.focus();
        textarea.selectionStart = textarea.value.length;
        textarea.selectionEnd = textarea.value.length;
    }

    clearSelection();
    keepComposerVisible();
}

async function saveEdit(button) {
    const bubble = button.closest(".message-bubble");
    if (!bubble) return;

    const messageId = bubble.dataset.messageId;
    const textarea = bubble.querySelector(".edit-message-input");
    const textBox = bubble.querySelector(".message-text");
    const editBox = bubble.querySelector(".edit-box");

    if (!messageId || !textarea) {
        showError("Edit üçün məlumat tapılmadı.");
        return;
    }

    const newMessage = textarea.value.trim();

    if (newMessage === "") {
        showError("Mesaj boş ola bilməz.");
        return;
    }

    const formData = new FormData();
    formData.append("csrf_token", csrfToken);
    formData.append("message_id", messageId);
    formData.append("message", newMessage);

    button.disabled = true;

    try {
        const response = await fetch("<?php echo e(basePath('edit_message.php')); ?>", {
            method: "POST",
            body: formData,
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            }
        });

        if (response.ok || response.redirected) {
            if (textBox) {
                textBox.innerHTML = nl2br(newMessage);
            }

            if (editBox) {
                editBox.style.display = "none";
            }
        } else {
            showError("Mesaj edit olunmadı.");
        }
    } catch (e) {
        showError("Mesaj edit olunmadı.");
    } finally {
        button.disabled = false;
    }
}

window.addEventListener("load", function () {
    hideError();
    bindAllLongPress();
    autoResizeTextarea();
    keepComposerVisible();
});

if (messageInput) {
    messageInput.addEventListener("input", function () {
        autoResizeTextarea();
        hideError();
    });

    messageInput.addEventListener("focus", function () {
        keepComposerVisible();
    });
}

if (sendBtn) {
    sendBtn.addEventListener("click", function (event) {
        event.preventDefault();
        sendMessage();
    });

    sendBtn.addEventListener("touchstart", function (event) {
        event.preventDefault();
        sendMessage();
    }, { passive: false });
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

        if (file.size > 10 * 1024 * 1024) {
            clearImagePreview();
            showError("Şəkil maksimum 10 MB ola bilər.");
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

        keepComposerVisible();
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
        keepComposerVisible();
    });
}

if (selectionEditBtn) {
    selectionEditBtn.addEventListener("click", function () {
        openEditForSelected();
    });
}

if (selectionDeleteBtn) {
    selectionDeleteBtn.addEventListener("click", function () {
        deleteSelectedMessage();
    });
}

if (selectionCancelBtn) {
    selectionCancelBtn.addEventListener("click", function () {
        clearSelection();
    });
}

document.addEventListener("click", function (event) {
    const saveBtn = event.target.closest(".js-save-edit");
    if (saveBtn) {
        saveEdit(saveBtn);
        return;
    }

    const cancelBtn = event.target.closest(".js-cancel-edit");
    if (cancelBtn) {
        const editBox = cancelBtn.closest(".edit-box");
        if (editBox) {
            editBox.style.display = "none";
        }
        return;
    }

    const img = event.target.closest(".js-full-image");
    if (img && imageModal && modalImage) {
        if (didLongPress) {
            didLongPress = false;
            return;
        }

        modalImage.src = img.src;
        imageModal.classList.add("is-open");
    }
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
        sendMessage();
    });
}

window.addEventListener("resize", function () {
    keepComposerVisible();
});

if (window.visualViewport) {
    window.visualViewport.addEventListener("resize", function () {
        keepComposerVisible();
    });

    window.visualViewport.addEventListener("scroll", function () {
        keepComposerVisible();
    });
}
</script>
</body>
</html>