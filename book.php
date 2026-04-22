<?php

require_once 'config.php';

ensureCsrfToken();

function getConditionMeta($rawCondition)
{
    $normalizedCondition = strtolower(trim((string)$rawCondition));

    $conditionMap = [
        'new' => ['text' => 'Yeni', 'class' => 'condition-new'],
        'like new' => ['text' => 'Yeni kimi', 'class' => 'condition-like-new'],
        'like_new' => ['text' => 'Yeni kimi', 'class' => 'condition-like-new'],
        'very good' => ['text' => 'Yaxşı', 'class' => 'condition-good'],
        'good' => ['text' => 'Yaxşı', 'class' => 'condition-good'],
        'used' => ['text' => 'Orta', 'class' => 'condition-fair'],
        'fair' => ['text' => 'Orta', 'class' => 'condition-fair'],
        'acceptable' => ['text' => 'Orta', 'class' => 'condition-fair'],
        'old' => ['text' => 'Köhnə', 'class' => 'condition-poor'],
        'poor' => ['text' => 'Köhnə', 'class' => 'condition-poor']
    ];

    return $conditionMap[$normalizedCondition] ?? [
        'text' => $rawCondition ?: 'Qeyd olunmayıb',
        'class' => 'condition-default'
    ];
}

$unreadMessageCount = 0;
$currentUserId = currentUserId() ?? 0;

if ($currentUserId) {
    try {
        $unreadStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM messages m
            JOIN conversations c ON m.conversation_id = c.id
            WHERE m.sender_id != ?
              AND m.is_read = 0
              AND (c.user_one_id = ? OR c.user_two_id = ?)
        ");
        $unreadStmt->execute([
            $currentUserId,
            $currentUserId,
            $currentUserId
        ]);
        $unreadMessageCount = (int)$unreadStmt->fetchColumn();
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $unreadMessageCount = 0;
    }
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirectTo('books.php');
}

$bookId = (int) $_GET['id'];

$stmt = $pdo->prepare("
    SELECT * 
    FROM books 
    WHERE id = ?
      AND is_deleted = 0
    LIMIT 1
");
$stmt->execute([$bookId]);
$book = $stmt->fetch();

if (!$book) {
    redirectTo('books.php');
}

$isOwner = $currentUserId > 0 && (int)$currentUserId === (int)($book['user_id'] ?? 0);
$isVisibleToPublic = ($book['status'] ?? 'active') === 'active';

if (!$isVisibleToPublic && !$isOwner) {
    redirectTo('books.php');
}

$seller = null;

if (isset($book['user_id']) && (int)$book['user_id'] > 0) {
    $userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $userStmt->execute([(int)$book['user_id']]);
    $seller = $userStmt->fetch();
}

$sellerName = 'Naməlum satıcı';

if ($seller) {
    if (!empty($seller['username'])) {
        $sellerName = $seller['username'];
    } elseif (!empty($seller['name'])) {
        $sellerName = $seller['name'];
    } elseif (!empty($seller['full_name'])) {
        $sellerName = $seller['full_name'];
    } elseif (!empty($seller['email'])) {
        $sellerName = $seller['email'];
    }
}

$imagePath = basePath('assets/default.png');

if (!empty($book['image'])) {
    $imagePath = basePath('image.php?file=' . urlencode($book['image']));
}

$rawCondition = $book['book_condition'] ?? $book['condition'] ?? '';
$conditionMeta = getConditionMeta($rawCondition);
$conditionText = $conditionMeta['text'];

$isFavorite = false;

if ($currentUserId) {
    $favoriteStmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND book_id = ? LIMIT 1");
    $favoriteStmt->execute([$currentUserId, (int)$book['id']]);
    $isFavorite = (bool)$favoriteStmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($book['title'] ?? 'Kitab detalı') ?> - 2tab</title>
    <style>
    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        font-family: Arial, sans-serif;
        background: #f8fafc;
        color: #1e293b;
    }

 

    .message-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .message-badge {
        min-width: 22px;
        height: 22px;
        padding: 0 7px;
        border-radius: 999px;
        background: #dc2626;
        color: #ffffff;
        font-size: 12px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .container {
        width: 90%;
        max-width: 1150px;
        margin: 30px auto 40px;
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 18px;
        text-decoration: none;
        color: #2563eb;
        font-weight: 700;
    }

    .back-link:hover {
        text-decoration: underline;
    }

    .book-detail {
        display: grid;
        grid-template-columns: 420px 1fr;
        gap: 30px;
        background: #fff;
        border-radius: 22px;
        padding: 26px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        border: 1px solid #e2e8f0;
    }

    .book-image-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 560px;
    }

    .book-image-card img {
        width: 100%;
        max-width: 100%;
        height: 520px;
        object-fit: contain;
        display: block;
        border-radius: 14px;
        background: #f8fafc;
    }

    .book-info {
        display: flex;
        flex-direction: column;
    }

    .book-info h1 {
        margin: 0 0 12px;
        font-size: 36px;
        color: #0f172a;
        line-height: 1.25;
    }

    .author {
        font-size: 19px;
        color: #64748b;
        margin-bottom: 18px;
        line-height: 1.5;
    }

    .price-row {
        display: flex;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
        margin-bottom: 18px;
    }

    .price {
        font-size: 30px;
        font-weight: 800;
        color: #2563eb;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 8px 14px;
        border-radius: 999px;
        font-size: 14px;
        font-weight: 700;
    }

    .condition-new {
        background: #dcfce7;
        color: #166534;
    }

    .condition-like-new {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .condition-good {
        background: #e0f2fe;
        color: #075985;
    }

    .condition-fair {
        background: #ffedd5;
        color: #c2410c;
    }

    .condition-poor {
        background: #fee2e2;
        color: #b91c1c;
    }

    .condition-default {
        background: #e2e8f0;
        color: #334155;
    }

    .meta-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
        margin-top: 6px;
        margin-bottom: 24px;
    }

    .meta-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 14px 16px;
    }

    .meta-label {
        display: block;
        font-size: 13px;
        font-weight: 700;
        color: #64748b;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }

    .meta-value {
        color: #0f172a;
        line-height: 1.6;
        font-size: 15px;
        word-break: break-word;
    }

    .description-box {
        margin-top: 4px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 18px;
    }

    .description-box h3 {
        margin: 0 0 12px;
        color: #0f172a;
        font-size: 20px;
    }

    .description-box p {
        margin: 0;
        line-height: 1.8;
        color: #334155;
        white-space: pre-line;
    }

    .actions {
        margin-top: 24px;
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 12px 18px;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 700;
        transition: 0.2s;
        border: none;
        cursor: pointer;
        font-family: Arial, sans-serif;
        font-size: 15px;
        white-space: nowrap;
    }

    .btn-primary {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 24px rgba(37, 99, 235, 0.18);
    }

    .btn-secondary {
        background: #e2e8f0;
        color: #1e293b;
    }

    .btn-secondary:hover {
        background: #cbd5e1;
    }

    .inline-form {
        display: inline;
        margin: 0;
    }

    @media (max-width: 980px) {
        .book-detail {
            grid-template-columns: 1fr;
        }

        .book-image-card {
            min-height: auto;
        }

        .book-image-card img {
            height: 420px;
        }

        .book-info h1 {
            font-size: 30px;
        }
    }

    @media (max-width: 700px) {
        .meta-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 520px) {
        .container {
            width: calc(100% - 28px);
            margin: 24px auto 32px;
        }

        .book-detail {
            padding: 16px;
            border-radius: 16px;
            gap: 18px;
        }

        .book-image-card {
            padding: 12px;
            border-radius: 14px;
        }

        .book-image-card img {
            height: 300px;
        }

        .book-info h1 {
            font-size: 24px;
        }

        .author {
            font-size: 16px;
        }

        .price {
            font-size: 24px;
        }

        .description-box {
            padding: 16px;
        }

        .actions {
            flex-direction: column;
        }

        .actions .btn,
        .actions a,
        .actions form {
            width: 100%;
        }

        .actions form .btn {
            width: 100%;
        }
    }
</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/topbar.php'; ?>

<div class="container">
    <a class="back-link" href="<?= e(basePath('books.php')) ?>">← Kitablara qayıt</a>

    <div class="book-detail">
        <div class="book-image-card">
            <img src="<?= e($imagePath) ?>" alt="<?= e($book['title'] ?? 'Kitab şəkli') ?>">
        </div>

        <div class="book-info">
            <h1><?= e($book['title'] ?? 'Başlıq yoxdur') ?></h1>

            <div class="author">
                <?= e($book['author'] ?? 'Naməlum müəllif') ?>
            </div>

            <div class="price-row">
                <div class="price">
                    <?= e($book['price'] ?? '0') ?> ₼
                </div>

                <div class="badge <?= e($conditionMeta['class']) ?>">
                    <?= e($conditionText) ?>
                </div>
            </div>

            <div class="meta-grid">
                <div class="meta-card">
                    <span class="meta-label">Janr</span>
                    <div class="meta-value"><?= e($book['genre'] ?? 'Qeyd olunmayıb') ?></div>
                </div>

                <div class="meta-card">
                    <span class="meta-label">Satıcı</span>
                    <div class="meta-value"><?= e($sellerName) ?></div>
                </div>

                <?php if (!empty($book['language'])): ?>
                    <div class="meta-card">
                        <span class="meta-label">Dil</span>
                        <div class="meta-value"><?= e($book['language']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($book['publisher'])): ?>
                    <div class="meta-card">
                        <span class="meta-label">Nəşriyyat</span>
                        <div class="meta-value"><?= e($book['publisher']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($book['year'])): ?>
                    <div class="meta-card">
                        <span class="meta-label">Nəşr ili</span>
                        <div class="meta-value"><?= e($book['year']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($book['published_year'])): ?>
                    <div class="meta-card">
                        <span class="meta-label">Nəşr ili</span>
                        <div class="meta-value"><?= e($book['published_year']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($book['created_at'])): ?>
                    <div class="meta-card">
                        <span class="meta-label">Elan tarixi</span>
                        <div class="meta-value"><?= e($book['created_at']) ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="description-box">
                <h3>Təsvir</h3>
                <p><?= e($book['description'] ?? 'Bu kitab üçün təsvir əlavə edilməyib.') ?></p>
            </div>

            <div class="actions">
                <?php if ($currentUserId): ?>
                    <form method="POST" action="<?= e(basePath('toggle_favorite.php')) ?>" class="inline-form">
                        <input type="hidden" name="book_id" value="<?= (int)$book['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <button type="submit" class="btn btn-secondary">
                            <?= $isFavorite ? '❤️ Favoridən çıxar' : '🤍 Favorilərə əlavə et' ?>
                        </button>
                    </form>
                <?php else: ?>
                    <a href="<?= e(basePath('login.php?redirect=' . urlencode(basePath('book.php?id=' . (int)$book['id'])))) ?>" class="btn btn-secondary">
                        ❤️ Favorilərə əlavə et
                    </a>
                <?php endif; ?>

                <?php if ($currentUserId && $currentUserId != (int)($book['user_id'] ?? 0)): ?>
                    <form method="POST" action="<?= e(basePath('start_chat.php')) ?>" class="inline-form">
                        <input type="hidden" name="user_id" value="<?= (int)($book['user_id'] ?? 0) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <button type="submit" class="btn btn-primary">
                            Satıcıya yaz
                        </button>
                    </form>
                <?php elseif (!$currentUserId): ?>
                    <a href="<?= e(basePath('login.php?redirect=' . urlencode(basePath('book.php?id=' . (int)$book['id'])))) ?>" class="btn btn-primary">
                        Satıcıya yaz
                    </a>
                <?php endif; ?>

                <?php if ($currentUserId && $currentUserId == (int)($book['user_id'] ?? 0)): ?>
                    <a href="<?= e(basePath('edit_book.php?id=' . (int)$book['id'])) ?>" class="btn btn-primary">
                        Kitabı redaktə et
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
