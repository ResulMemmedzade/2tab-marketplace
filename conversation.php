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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --background: #faf8f5;
            --foreground: #2d2a26;
            --card: #ffffff;
            --card-foreground: #2d2a26;
            --primary: #c4704b;
            --primary-hover: #b5613c;
            --primary-foreground: #ffffff;
            --secondary: #f3efe9;
            --secondary-hover: #e8e2d9;
            --secondary-foreground: #4a4540;
            --muted: #f0ebe4;
            --muted-foreground: #7a756d;
            --border: #e5dfd6;
            --accent: #6b8f71;
            --radius: 16px;
            --radius-sm: 12px;
            --shadow-sm: 0 1px 3px rgba(45, 42, 38, 0.04), 0 1px 2px rgba(45, 42, 38, 0.06);
            --shadow: 0 4px 20px rgba(45, 42, 38, 0.06), 0 2px 8px rgba(45, 42, 38, 0.04);
            --shadow-lg: 0 12px 40px rgba(45, 42, 38, 0.08), 0 4px 16px rgba(45, 42, 38, 0.04);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            height: 100%;
            overflow: hidden;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--background);
            color: var(--foreground);
            -webkit-font-smoothing: antialiased;
        }

        #galleryImage,
        #cameraImage {
            display: none;
        }

        .chat-page {
            height: 100%;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            background: var(--background);
        }

        .chat-header {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 18px;
            background: var(--card);
            border-bottom: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            z-index: 30;
        }

        .back-btn {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-sm);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: var(--primary);
            background: var(--secondary);
            border: 1px solid var(--border);
            flex-shrink: 0;
            transition: all 0.2s ease;
        }

        .back-btn:hover {
            background: var(--secondary-hover);
            border-color: var(--primary);
        }

        .back-btn svg {
            width: 20px;
            height: 20px;
        }

        .chat-header-text { min-width: 0; flex: 1; }

        .chat-header-text h1 {
            font-size: 18px;
            font-weight: 700;
            color: var(--foreground);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            letter-spacing: -0.3px;
        }

        .chat-header-text p {
            margin-top: 3px;
            color: var(--muted-foreground);
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
            gap: 14px;
            background: var(--background);
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
            min-width: 105px;
            max-width: min(72%, 520px);
            padding: 12px 14px 26px;
            border-radius: var(--radius);
            line-height: 1.6;
            box-shadow: var(--shadow-sm);
            word-wrap: break-word;
            overflow-wrap: break-word;
            user-select: none;
            -webkit-user-select: none;
            touch-action: manipulation;
        }

        .message-row.mine .message-bubble {
            background: var(--primary);
            color: var(--primary-foreground);
            border-bottom-right-radius: 6px;
        }

        .message-row.other .message-bubble {
            background: var(--card);
            color: var(--foreground);
            border: 1px solid var(--border);
            border-bottom-left-radius: 6px;
        }

        .message-bubble.selected {
            outline: 3px solid rgba(196, 112, 75, 0.35);
            transform: scale(0.99);
        }

        .message-bubble.image-bubble {
            width: auto;
            min-width: 120px;
            max-width: 256px;
            padding: 8px 8px 26px;
            overflow: hidden;
        }

        .message-text {
            white-space: normal;
            font-size: 15px;
        }

        .message-footer {
            position: absolute;
            right: 10px;
            bottom: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            line-height: 1;
            opacity: 0.8;
            white-space: nowrap;
            pointer-events: none;
        }

        .message-row.other .message-footer {
            color: var(--muted-foreground);
        }

        .message-row.mine .message-footer {
            color: rgba(255,255,255,0.85);
        }

        .message-check {
            display: flex;
            align-items: center;
        }

        .message-check svg {
            width: 14px;
            height: 14px;
        }

        .chat-image {
            display: block;
            width: 100%;
            max-width: 240px;
            max-height: 320px;
            height: auto;
            border-radius: var(--radius-sm);
            object-fit: contain;
            cursor: pointer;
            background: transparent;
        }

        .edit-box {
            display: none;
            margin-top: 10px;
        }

        .edit-box textarea {
            width: 100%;
            min-height: 70px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            padding: 12px;
            font-size: 14px;
            font-family: inherit;
            color: var(--foreground);
            background: var(--card);
            resize: vertical;
        }

        .edit-box textarea:focus {
            outline: none;
            border-color: var(--primary);
        }

        .edit-box-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }

        .message-action-btn {
            border: none;
            border-radius: var(--radius-sm);
            padding: 10px 14px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s ease;
        }

        .edit-btn {
            background: var(--primary);
            color: var(--primary-foreground);
        }

        .edit-btn:hover {
            background: var(--primary-hover);
        }

        .delete-btn {
            background: #ef4444;
            color: #ffffff;
        }

        .delete-btn:hover {
            background: #dc2626;
        }

        .cancel-btn {
            background: var(--secondary);
            color: var(--secondary-foreground);
            border: 1px solid var(--border);
        }

        .cancel-btn:hover {
            background: var(--secondary-hover);
        }

        .empty-chat {
            color: var(--muted-foreground);
            text-align: center;
            padding: 60px 20px;
            line-height: 1.8;
            margin: auto 0;
        }

        .empty-chat strong {
            display: block;
            color: var(--foreground);
            margin-bottom: 8px;
            font-size: 18px;
            font-weight: 700;
        }

        .chat-form-wrap {
            flex-shrink: 0;
            background: var(--card);
            border-top: 1px solid var(--border);
            padding: 14px 16px calc(14px + env(safe-area-inset-bottom));
            z-index: 25;
        }

        .alert-error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 12px 14px;
            border-radius: var(--radius-sm);
            margin-bottom: 12px;
            display: none;
            font-size: 14px;
            font-weight: 500;
        }

        .image-preview {
            display: none;
            align-items: center;
            gap: 14px;
            background: var(--secondary);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 12px;
            margin-bottom: 12px;
        }

        .image-preview img {
            width: 64px;
            height: 64px;
            border-radius: var(--radius-sm);
            object-fit: cover;
            border: 1px solid var(--border);
            background: var(--card);
        }

        .image-preview-info {
            flex: 1;
            min-width: 0;
        }

        .image-preview-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--foreground);
            margin-bottom: 4px;
        }

        .image-preview-name {
            font-size: 13px;
            color: var(--muted-foreground);
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
            border-radius: var(--radius-sm);
            padding: 10px 14px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s ease;
        }

        .preview-send {
            background: var(--primary);
            color: var(--primary-foreground);
        }

        .preview-send:hover {
            background: var(--primary-hover);
        }

        .preview-cancel {
            background: var(--secondary);
            color: var(--secondary-foreground);
            border: 1px solid var(--border);
        }

        .preview-cancel:hover {
            background: var(--secondary-hover);
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
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 10px 10px 10px 16px;
            box-shadow: var(--shadow-sm);
            transition: all 0.2s ease;
        }

        .input-shell:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(196, 112, 75, 0.1);
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
            font-family: inherit;
            line-height: 1.5;
            color: var(--foreground);
            background: transparent;
            overflow-y: auto;
        }

        textarea::placeholder { color: var(--muted-foreground); }

        .send-btn {
            width: 44px;
            height: 44px;
            min-width: 44px;
            border: none;
            border-radius: 50%;
            background: var(--primary);
            color: var(--primary-foreground);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
            transition: all 0.2s ease;
        }

        .send-btn:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }

        .send-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .send-btn svg {
            width: 20px;
            height: 20px;
        }

        .attach-wrap {
            position: relative;
            flex-shrink: 0;
        }

        .attach-menu {
            display: none;
            position: absolute;
            right: 0;
            bottom: 56px;
            width: 160px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 8px;
            box-shadow: var(--shadow-lg);
            z-index: 80;
        }

        .attach-menu.is-open {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .attach-option {
            display: flex;
            align-items: center;
            gap: 10px;
            border: none;
            background: var(--secondary);
            color: var(--foreground);
            border-radius: var(--radius-sm);
            padding: 12px 14px;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            text-align: left;
            transition: all 0.2s ease;
        }

        .attach-option:hover {
            background: var(--secondary-hover);
            color: var(--primary);
        }

        .attach-option svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        .image-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 999;
            background: rgba(45, 42, 38, 0.95);
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
            border-radius: var(--radius-sm);
            background: #000;
        }

        .image-modal-close {
            position: fixed;
            top: 16px;
            right: 16px;
            width: 44px;
            height: 44px;
            border: none;
            border-radius: var(--radius-sm);
            background: var(--card);
            color: var(--foreground);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: var(--shadow);
            transition: all 0.2s ease;
        }

        .image-modal-close:hover {
            background: var(--secondary);
        }

        .image-modal-close svg {
            width: 22px;
            height: 22px;
        }

        .selection-bar {
            display: none;
            position: fixed;
            left: 12px;
            right: 12px;
            bottom: calc(12px + env(safe-area-inset-bottom));
            z-index: 800;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 14px 16px;
            box-shadow: var(--shadow-lg);
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .selection-bar.is-open {
            display: flex;
        }

        .selection-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--foreground);
        }

        .selection-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        @media (max-width: 768px) {
            .chat-page { background: var(--card); }

            .chat-header { padding: 12px 14px; }

            .back-btn {
                width: 40px;
                height: 40px;
            }

            .chat-header-text h1 { font-size: 17px; }
            .chat-header-text p { font-size: 12px; }

            .chat-messages {
                padding: 14px;
                gap: 12px;
            }

            .message-bubble {
                min-width: 105px;
                max-width: 85%;
            }

            .message-bubble.image-bubble {
                min-width: 120px;
                max-width: 240px;
                padding: 7px 7px 26px;
            }

            .chat-image {
                max-width: 226px;
                max-height: 280px;
            }

            .chat-form-wrap {
                padding: 12px 14px calc(12px + env(safe-area-inset-bottom));
            }

            .input-shell {
                border-radius: 22px;
                padding: 8px 8px 8px 14px;
                gap: 8px;
            }

            textarea {
                font-size: 16px;
                max-height: 100px;
            }

            .send-btn {
                width: 42px;
                height: 42px;
                min-width: 42px;
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
        <a href="<?= e(basePath('messages.php')) ?>" class="back-btn" aria-label="Mesajlara qayıt">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
            </svg>
        </a>

        <div class="chat-header-text">
            <h1><?= e($otherUser["name"] ?? "İstifadəçi") ?></h1>
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
                        $bubbleClass = $messageType === "image" ? "message-bubble image-bubble" : "message-bubble";
                        $canEdit = $isMine && $messageType === "text" && !$isRead;
                        $canDelete = $isMine;
                    ?>

                    <div class="message-row <?= $isMine ? 'mine' : 'other' ?>">
                        <div
                            class="<?= e($bubbleClass) ?>"
                            data-message-id="<?= (int)$msg["id"] ?>"
                            data-message-type="<?= e($messageType) ?>"
                            data-can-edit="<?= $canEdit ? '1' : '0' ?>"
                            data-can-delete="<?= $canDelete ? '1' : '0' ?>"
                        >
                            <?php if ($messageType === "image"): ?>
                                <img
                                    src="<?= e(basePath('uploads/' . ltrim((string)$msg["message"], '/'))) ?>"
                                    class="chat-image js-full-image"
                                    alt="Göndərilən şəkil"
                                >
                            <?php else: ?>
                                <div class="message-text"><?= nl2br(e($msg["message"])) ?></div>

                                <?php if ($canEdit): ?>
                                    <div class="edit-box">
                                        <textarea class="edit-message-input" required><?= e($msg["message"]) ?></textarea>
                                        <div class="edit-box-actions">
                                            <button type="button" class="message-action-btn edit-btn js-save-edit">Yadda saxla</button>
                                            <button type="button" class="message-action-btn cancel-btn js-cancel-edit">Bağla</button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <div class="message-footer">
                                <span class="message-time"><?= e(formatRelativeTime($msg["created_at"] ?? "")) ?></span>
                                <?php if ($isMine): ?>
                                    <span class="message-check">
                                        <?php if ($isRead): ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5M9 12.75l3 3" />
                                            </svg>
                                        <?php else: ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
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
                <input type="hidden" name="conversation_id" value="<?= (int)$conversationId ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

                <div class="input-shell">
                    <textarea
                        name="message"
                        id="messageInput"
                        placeholder="Mesajınızı yazın..."
                        required
                    ></textarea>

                    <input type="file" name="image" id="galleryImage" accept="image/*">
                    <input type="file" name="image" id="cameraImage" accept="image/*" capture="environment">

                    <div class="attach-wrap">
                        <button type="button" class="send-btn" id="imageBtn" aria-label="Fayl əlavə et">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                            </svg>
                        </button>

                        <div class="attach-menu" id="attachMenu">
                            <button type="button" class="attach-option" id="chooseGallery">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                </svg>
                                Qalereya
                            </button>
                            <button type="button" class="attach-option" id="chooseCamera">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                                </svg>
                                Kamera
                            </button>
                        </div>
                    </div>

                    <button type="button" class="send-btn" id="sendBtn" aria-label="Göndər">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                        </svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="selection-bar" id="selectionBar">
    <div class="selection-title">Mesaj seçildi</div>
    <div class="selection-actions">
        <button type="button" class="message-action-btn edit-btn" id="selectionEditBtn">Redaktə et</button>
        <button type="button" class="message-action-btn delete-btn" id="selectionDeleteBtn">Sil</button>
        <button type="button" class="message-action-btn cancel-btn" id="selectionCancelBtn">Bağla</button>
    </div>
</div>

<div class="image-modal" id="imageModal">
    <button type="button" class="image-modal-close" id="closeImageModal">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
    <img src="" alt="Tam ekran şəkil" id="modalImage">
</div>

<script>
const chatMessages = document.getElementById("chatMessages");
const chatForm = document.getElementById("chatForm");
const messageInput = document.getElementById("messageInput");
const sendBtn = document.getElementById("sendBtn");
const chatError = document.getElementById("chatError");

const imageBtn = document.getElementById("imageBtn");
const galleryImage = document.getElementById("galleryImage");
const cameraImage = document.getElementById("cameraImage");
const attachMenu = document.getElementById("attachMenu");
const chooseGallery = document.getElementById("chooseGallery");
const chooseCamera = document.getElementById("chooseCamera");

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

const csrfToken = <?= json_encode(csrfToken()) ?>;
const conversationId = <?= (int)$conversationId ?>;

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

const checkSvgSingle = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>`;

function appendMessage(messageText, timeText, messageId = "", canEdit = true) {
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

    const text = document.createElement("div");
    text.className = "message-text";
    text.innerHTML = nl2br(messageText);

    bubble.appendChild(text);

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
        bubble.appendChild(editBox);
    }

    const footer = document.createElement("div");
    footer.className = "message-footer";
    footer.innerHTML = `
        <span class="message-time">${escapeHtml(timeText)}</span>
        <span class="message-check">${checkSvgSingle}</span>
    `;

    bubble.appendChild(footer);
    row.appendChild(bubble);
    chatMessages.appendChild(row);

    bindLongPressToBubble(bubble);
    scrollToBottom(true);
}

function appendImageMessage(imageUrl, timeText, messageId = "") {
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

    const img = document.createElement("img");
    img.src = imageUrl;
    img.className = "chat-image js-full-image";
    img.alt = "Göndərilən şəkil";

    const footer = document.createElement("div");
    footer.className = "message-footer";
    footer.innerHTML = `
        <span class="message-time">${escapeHtml(timeText)}</span>
        <span class="message-check">${checkSvgSingle}</span>
    `;

    bubble.appendChild(img);
    bubble.appendChild(footer);
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

    if (galleryImage) galleryImage.value = "";
    if (cameraImage) cameraImage.value = "";
    if (previewImage) previewImage.src = "";
    if (previewName) previewName.textContent = "";
    if (imagePreview) imagePreview.style.display = "none";
}

function handleImageSelection(input) {
    hideError();

    if (!input.files || !input.files.length) {
        clearImagePreview();
        return;
    }

    const file = input.files[0];

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
    previewName.textContent = file.name || "camera-image.jpg";
    imagePreview.style.display = "flex";
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
        const response = await fetch("<?= e(basePath('send_message.php')) ?>", {
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
                data.message_id || "",
                true
            );

            messageInput.value = "";
            autoResizeTextarea();
            scrollToBottom(true);
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

        const response = await fetch("<?= e(basePath('send_chat_image.php')) ?>", {
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

            appendImageMessage(imageUrl, "indi", messageId);
            clearImagePreview();
            scrollToBottom(true);
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
        const response = await fetch("<?= e(basePath('delete_message.php')) ?>", {
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
        const response = await fetch("<?= e(basePath('edit_message.php')) ?>", {
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
    scrollToBottom(true);
});

if (messageInput) {
    messageInput.addEventListener("input", function () {
        autoResizeTextarea();
        hideError();
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

if (imageBtn && attachMenu) {
    imageBtn.addEventListener("click", function (event) {
        event.preventDefault();
        attachMenu.classList.toggle("is-open");
    });
}

if (chooseGallery && galleryImage) {
    chooseGallery.addEventListener("click", function () {
        attachMenu.classList.remove("is-open");
        galleryImage.click();
    });

    galleryImage.addEventListener("change", function () {
        handleImageSelection(galleryImage);
    });
}

if (chooseCamera && cameraImage) {
    chooseCamera.addEventListener("click", function () {
        attachMenu.classList.remove("is-open");
        cameraImage.click();
    });

    cameraImage.addEventListener("change", function () {
        handleImageSelection(cameraImage);
    });
}

document.addEventListener("click", function (event) {
    if (
        attachMenu &&
        imageBtn &&
        !attachMenu.contains(event.target) &&
        !imageBtn.contains(event.target)
    ) {
        attachMenu.classList.remove("is-open");
    }

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
</script>
</body>
</html>