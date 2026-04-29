<?php

require_once "config.php";

requireLogin();
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

$user_id = currentUserId();

$unreadMessageCount = 0;

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM messages m
        JOIN conversations c ON m.conversation_id = c.id
        WHERE m.sender_id != ?
          AND m.is_read = 0
          AND (c.user_one_id = ? OR c.user_two_id = ?)
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $unreadMessageCount = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $unreadMessageCount = 0;
}

try {
    $stmt = $pdo->prepare("
        SELECT books.*, users.name AS seller_name
        FROM favorites
        JOIN books ON favorites.book_id = books.id
        JOIN users ON books.seller_id = users.id
        WHERE favorites.user_id = ?
          AND books.is_deleted = 0
          AND books.status = 'active'
        ORDER BY favorites.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $favorites = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());

    appLog('system_error', 'Favorites fetch error', [
        'user_id' => $user_id,
        'error' => $e->getMessage()
    ]);

    $favorites = [];
}
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2tab | Favorilər</title>
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
        --shadow-lg: 0 12px 40px rgba(45, 42, 38, 0.08), 0 4px 16px rgba(45, 42, 38, 0.04);
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
        margin: 32px auto 48px;
        padding: 0 20px;
    }

    .page-title {
        margin-bottom: 24px;
    }

    .page-title h1 {
        font-size: 32px;
        font-weight: 800;
        color: var(--foreground);
        letter-spacing: -0.7px;
        margin-bottom: 8px;
    }

    .page-title p {
        color: var(--muted-foreground);
        font-size: 15px;
        line-height: 1.7;
    }

    .book-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
        align-items: stretch;
    }

    @media (min-width: 640px) {
        .book-grid { grid-template-columns: repeat(3, 1fr); }
    }

    @media (min-width: 1024px) {
        .book-grid { grid-template-columns: repeat(4, 1fr); gap: 20px; }
    }

    /* index.php ilə eyni book-card */
    .book-card {
        background: var(--card);
        border-radius: var(--radius);
        padding: 12px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        height: 100%;
        min-height: 100%;
        transition: all 0.25s ease;
        text-decoration: none;
        color: inherit;
        cursor: pointer;
    }

    .book-card:hover {
        box-shadow: var(--shadow-lg);
        transform: translateY(-4px);
        border-color: transparent;
    }

    .book-image-wrap {
        position: relative;
        width: 100%;
        aspect-ratio: 3 / 4;
        border-radius: var(--radius-sm);
        background: var(--muted);
        overflow: hidden;
        margin-bottom: 14px;
        flex-shrink: 0;
    }

    .book-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
        display: block;
    }

    .book-card:hover .book-image {
        transform: scale(1.05);
    }

    .no-image {
        width: 100%;
        height: 100%;
        background: var(--muted);
        color: var(--muted-foreground);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        text-align: center;
        padding: 12px;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        padding: 6px 11px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.25px;
        white-space: nowrap;
    }

    .book-image-wrap .badge {
        position: absolute;
        left: 10px;
        bottom: 10px;
        z-index: 2;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.14);
        backdrop-filter: blur(8px);
    }

    .condition-new      { background: rgba(220, 252, 231, 0.95); color: #166534; }
    .condition-like-new { background: rgba(219, 234, 254, 0.95); color: #1d4ed8; }
    .condition-good     { background: rgba(224, 242, 254, 0.95); color: #075985; }
    .condition-fair     { background: rgba(255, 237, 213, 0.95); color: #c2410c; }
    .condition-poor     { background: rgba(254, 226, 226, 0.95); color: #b91c1c; }
    .condition-default  { background: rgba(240, 235, 228, 0.95); color: var(--muted-foreground); }

    .book-card h3 {
        font-size: 16px;
        font-weight: 600;
        line-height: 1.35;
        color: var(--foreground);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        margin-bottom: 6px;
        min-height: 44px;
    }

    .book-author {
        color: var(--muted-foreground);
        font-size: 13px;
        margin-bottom: 12px;
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 20px;
    }

    .book-meta {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 8px;
        margin-top: auto;
        padding-top: 8px;
    }

    .book-price {
        font-size: 18px;
        font-weight: 700;
        color: var(--primary);
        line-height: 1.2;
    }

    /* Favori çıxar düyməsi — kartın altında ayrıca */
    .favorite-form {
        margin: 10px 0 0;
        padding: 0;
    }

    .favorite-btn {
        width: 100%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        border: 1px solid #fecaca;
        border-radius: var(--radius-sm);
        padding: 10px 14px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        font-family: inherit;
        transition: all 0.2s ease;
        background: #fee2e2;
        color: #b91c1c;
    }

    .favorite-btn:hover {
        background: #fecaca;
        transform: translateY(-1px);
    }

    /* Wrapper: kart + favori düymə birlikdə */
    .book-card-wrapper {
        display: flex;
        flex-direction: column;
    }

    .empty {
        background: var(--card);
        border: 2px dashed var(--border);
        border-radius: var(--radius);
        padding: 48px 24px;
        color: var(--muted-foreground);
        text-align: center;
        font-size: 15px;
        line-height: 1.7;
    }

    @media (max-width: 480px) {
        .container { margin-top: 24px; padding: 0 16px 40px; }
        .page-title h1 { font-size: 28px; }
        .book-grid { gap: 12px; }
        .book-card { padding: 10px; }
        .book-card h3 { font-size: 14px; min-height: 38px; }
        .book-price { font-size: 16px; }
        .badge { font-size: 10px; padding: 5px 9px; }
        .book-image-wrap .badge { left: 8px; bottom: 8px; }
        .favorite-btn { font-size: 12px; padding: 9px 12px; }
    }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/topbar.php'; ?>

<div class="container">
    <div class="page-title">
        <h1>Favorilər</h1>
        <p>Bəyəndiyin və sonra baxmaq istədiyin kitablar burada görünür.</p>
    </div>

    <?php if (count($favorites) > 0): ?>
        <div class="book-grid">
            <?php foreach ($favorites as $book): ?>
                <?php
                $conditionMeta = getConditionMeta($book['book_condition'] ?? $book['condition'] ?? '');
                ?>
                <a href="<?= e(basePath('book.php')) ?>?id=<?= (int)$book['id'] ?>" class="book-card">
                    <div class="book-image-wrap">
                        <?php if (!empty($book["image"])): ?>
                            <img
                                class="book-image"
                                src="<?= e(basePath('image.php')) ?>?file=<?= urlencode($book["image"]) ?>"
                                alt="<?= htmlspecialchars($book["title"], ENT_QUOTES, 'UTF-8') ?>"
                                loading="lazy"
                            >
                        <?php else: ?>
                            <div class="no-image">Şəkil yoxdur</div>
                        <?php endif; ?>

                        <span class="badge <?= htmlspecialchars($conditionMeta['class'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($conditionMeta['text'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <h3><?= htmlspecialchars($book["title"], ENT_QUOTES, 'UTF-8') ?></h3>

                    <div class="book-author">
                        <?= htmlspecialchars($book["author"], ENT_QUOTES, 'UTF-8') ?>
                    </div>

                    <div class="book-meta">
                        <span class="book-price"><?= htmlspecialchars($book["price"], ENT_QUOTES, 'UTF-8') ?> AZN</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty">Hələ heç bir kitab favoriyə əlavə edilməyib.</div>
    <?php endif; ?>
</div>
</body>
</html>