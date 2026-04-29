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

$currentUserId = currentUserId() ?? 0;

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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
    :root {
        --background: #faf8f5;
        --foreground: #2d2a26;
        --card: #ffffff;
        --primary: #c4704b;
        --primary-hover: #b5613c;
        --primary-foreground: #ffffff;
        --secondary: #f3efe9;
        --secondary-hover: #e8e2d9;
        --secondary-foreground: #4a4540;
        --muted: #f0ebe4;
        --muted-foreground: #7a756d;
        --border: #e5dfd6;
        --radius: 16px;
        --radius-sm: 12px;
        --shadow-sm: 0 1px 3px rgba(45, 42, 38, 0.04), 0 1px 2px rgba(45, 42, 38, 0.06);
        --shadow: 0 4px 20px rgba(45, 42, 38, 0.06), 0 2px 8px rgba(45, 42, 38, 0.04);
    }

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        background: var(--background);
        color: var(--foreground);
        line-height: 1.5;
        -webkit-font-smoothing: antialiased;
    }

    .container {
        max-width: 1200px;
        margin: 24px auto 48px;
        padding: 0 20px;
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 20px;
        text-decoration: none;
        color: var(--primary);
        font-weight: 600;
        font-size: 15px;
        transition: all 0.2s ease;
    }

    .back-link:hover {
        color: var(--primary-hover);
    }

    .back-link svg {
        width: 18px;
        height: 18px;
    }

    .book-detail {
        display: grid;
        grid-template-columns: 400px 1fr;
        gap: 32px;
        background: var(--card);
        border-radius: var(--radius);
        padding: 28px;
        box-shadow: var(--shadow);
        border: 1px solid var(--border);
    }

    .book-image-card {
        position: relative;
        background: var(--muted);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        overflow: hidden;
        cursor: zoom-in;
    }

    .book-image-card img {
        width: 100%;
        height: 520px;
        object-fit: contain;
        background: var(--muted);
        display: block;
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
        background: var(--muted);
        color: var(--muted-foreground);
    }

    .book-info {
        display: flex;
        flex-direction: column;
    }

    .book-info h1 {
        margin: 0 0 10px;
        font-size: 32px;
        font-weight: 800;
        color: var(--foreground);
        line-height: 1.2;
        letter-spacing: -0.5px;
    }

    .author {
        font-size: 17px;
        color: var(--muted-foreground);
        margin-bottom: 20px;
    }

    .price {
        font-size: 32px;
        font-weight: 800;
        color: var(--primary);
        margin-bottom: 24px;
    }

    .meta-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        margin-bottom: 24px;
    }

    .meta-card {
        background: var(--secondary);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 14px 16px;
    }

    .meta-card.condition-card {
        border-color: rgba(196, 112, 75, 0.22);
    }

    .meta-label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        color: var(--muted-foreground);
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .meta-value {
        color: var(--foreground);
        font-size: 15px;
        font-weight: 600;
        word-break: break-word;
    }

    .condition-value {
        display: inline-flex;
        align-items: center;
        width: fit-content;
        padding: 6px 11px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.25px;
    }

    .description-box {
        background: var(--secondary);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 20px;
        flex: 1;
    }

    .description-box h3 {
        margin: 0 0 12px;
        color: var(--foreground);
        font-size: 16px;
        font-weight: 700;
    }

    .description-box p {
        margin: 0;
        line-height: 1.7;
        color: var(--muted-foreground);
        white-space: pre-line;
        font-size: 15px;
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
        gap: 8px;
        padding: 14px 24px;
        border-radius: var(--radius-sm);
        text-decoration: none;
        font-weight: 600;
        font-size: 15px;
        border: none;
        cursor: pointer;
        font-family: inherit;
        transition: all 0.2s ease;
    }

    .btn-primary {
        background: var(--primary);
        color: var(--primary-foreground);
        box-shadow: var(--shadow-sm);
    }

    .btn-primary:hover {
        background: var(--primary-hover);
        transform: translateY(-1px);
        box-shadow: var(--shadow);
    }

    .btn-secondary {
        background: var(--secondary);
        color: var(--secondary-foreground);
        border: 1px solid var(--border);
    }

    .btn-secondary:hover {
        background: var(--secondary-hover);
    }

    .btn svg {
        width: 18px;
        height: 18px;
    }

    .inline-form {
        display: inline;
        margin: 0;
    }

    .image-modal {
        position: fixed;
        inset: 0;
        z-index: 9999;
        background: rgba(20, 18, 16, 0.88);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 24px;
    }

    .image-modal.active {
        display: flex;
    }

    .image-modal img {
        max-width: 96vw;
        max-height: 92vh;
        object-fit: contain;
        border-radius: 14px;
        background: #ffffff;
        box-shadow: 0 20px 80px rgba(0, 0, 0, 0.35);
    }

    .image-modal-close {
        position: fixed;
        top: 18px;
        right: 18px;
        width: 42px;
        height: 42px;
        border: none;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.92);
        color: #2d2a26;
        font-size: 28px;
        line-height: 1;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    @media (max-width: 980px) {
        .book-detail {
            grid-template-columns: 1fr;
            gap: 24px;
        }

        .book-image-card img {
            height: 400px;
        }

        .book-info h1 {
            font-size: 28px;
        }
    }

    @media (max-width: 640px) {
        .container {
            padding: 0 16px;
            margin: 20px auto 40px;
        }

        .book-detail {
            padding: 20px;
        }

        .book-image-card img {
            height: 320px;
        }

        .book-info h1 {
            font-size: 24px;
        }

        .author {
            font-size: 15px;
        }

        .price {
            font-size: 26px;
        }

        .meta-grid {
            grid-template-columns: 1fr;
        }

        .actions {
            flex-direction: column;
        }

        .actions .btn,
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
        <a class="back-link" href="<?= e(basePath('books.php')) ?>">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
            </svg>
            Kitablara qayıt
        </a>

        <div class="book-detail">
            <div class="book-image-card" id="bookImageOpen">
                <img src="<?= e($imagePath) ?>" alt="<?= e($book['title'] ?? 'Kitab şəkli') ?>">
            </div>

            <div class="book-info">
                <h1><?= e($book['title'] ?? 'Başlıq yoxdur') ?></h1>

                <div class="author">
                    <?= e($book['author'] ?? 'Naməlum müəllif') ?>
                </div>

                <div class="price">
                    <?= e($book['price'] ?? '0') ?> AZN
                </div>

                <div class="meta-grid">
                    <div class="meta-card condition-card">
                        <span class="meta-label">Vəziyyət</span>
                        <div class="meta-value">
                            <span class="condition-value <?= e($conditionMeta['class']) ?>">
                                <?= e($conditionText) ?>
                            </span>
                        </div>
                    </div>

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
                                <svg xmlns="http://www.w3.org/2000/svg" fill="<?= $isFavorite ? 'currentColor' : 'none' ?>" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                                </svg>
                                <?= $isFavorite ? 'Favoridən çıxar' : 'Favorilərə əlavə et' ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <a href="<?= e(basePath('login.php?redirect=' . urlencode(basePath('book.php?id=' . (int)$book['id'])))) ?>" class="btn btn-secondary">
                            Favorilərə əlavə et
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

    <div class="image-modal" id="imageModal">
        <button type="button" class="image-modal-close" id="imageModalClose">×</button>
        <img src="<?= e($imagePath) ?>" alt="<?= e($book['title'] ?? 'Kitab şəkli') ?>">
    </div>

    <script>
    (function () {
        const openTarget = document.getElementById('bookImageOpen');
        const modal = document.getElementById('imageModal');
        const closeBtn = document.getElementById('imageModalClose');

        if (!openTarget || !modal || !closeBtn) return;

        function openModal() {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        openTarget.addEventListener('click', openModal);
        closeBtn.addEventListener('click', closeModal);

        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    })();
    </script>
</body>
</html>