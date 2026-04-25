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
    $stmt->execute([
        $user_id,
        $user_id,
        $user_id
    ]);
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

    
    .container {
        max-width: 1200px;
        margin: 30px auto;
        padding: 0 20px 40px;
    }

    .page-title h1 {
        margin: 0 0 8px;
        font-size: 32px;
        color: #0f172a;
    }

    .page-title p {
        margin: 0 0 24px;
        color: #64748b;
        line-height: 1.6;
    }

    .book-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .book-card {
        background: #fff;
        border-radius: 18px;
        padding: 12px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        border: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .book-image-wrap {
        width: 100%;
        height: 220px;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        margin-bottom: 12px;
    }

    .book-image {
        width: 100%;
        height: 100%;
        object-fit: contain;
        display: block;
        padding: 8px;
    }

    .no-image {
        width: 100%;
        height: 220px;
        border-radius: 14px;
        border: 1px dashed #cbd5e1;
        background: #f8fafc;
        color: #94a3b8;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        text-align: center;
        padding: 10px;
        margin-bottom: 12px;
    }

    .book-card h3 {
        margin: 0 0 8px;
        font-size: 22px;
        line-height: 1.3;
        color: #0f172a;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 58px;
    }

    .book-author {
        color: #64748b;
        font-size: 15px;
        margin-bottom: 10px;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .book-price {
        font-size: 20px;
        font-weight: 800;
        color: #2563eb;
        margin-bottom: 12px;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 7px 12px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 700;
        white-space: nowrap;
    }

    .book-condition {
        margin-bottom: 12px;
    }

    .condition-new {
        background: #22c55e;
        color: white;
    }

    .condition-like-new {
        background: #3b82f6;
        color: white;
    }

    .condition-good {
        background: #0ea5e9;
        color: white;
    }

    .condition-fair {
        background: #f97316;
        color: white;
    }

    .condition-poor {
        background: #ef4444;
        color: white;
    }

    .condition-default {
        background: #64748b;
        color: white;
    }

    .book-actions {
        margin-top: auto;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .book-actions .btn,
    .book-actions .favorite-btn {
        width: 100%;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        border: none;
        border-radius: 12px;
        padding: 12px 16px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        transition: 0.2s ease;
    }

    .btn-light {
        background: #e2e8f0;
        color: #1e293b;
    }

    .btn-light:hover {
        background: #cbd5e1;
    }

    .empty {
        color: #64748b;
        padding: 22px;
        border: 1px dashed #cbd5e1;
        border-radius: 16px;
        background: #fff;
        line-height: 1.7;
    }

    .favorite-form {
        display: block;
        margin: 0;
        padding: 0;
        width: 100%;
    }

    .favorite-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        padding: 11px 14px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 700;
        border: 1px solid #fecdd3;
        background: #ffe4e6;
        color: #be123c;
        cursor: pointer;
        font-family: Arial, sans-serif;
        transition: 0.2s ease;
    }

    .favorite-btn:hover {
        background: #fecdd3;
    }

    @media (max-width: 640px) {
        .container {
            padding: 0 14px 32px;
        }

        .book-grid {
            gap: 12px;
        }

        .book-card,
        .empty {
            border-radius: 16px;
        }

        .book-card {
            padding: 10px;
        }

        .book-image-wrap,
        .no-image {
            height: 180px;
        }

        .book-card h3 {
            font-size: 18px;
            min-height: 48px;
        }

        .book-author {
            font-size: 14px;
        }

        .book-price {
            font-size: 18px;
        }

        .book-actions .btn,
        .book-actions .favorite-btn {
            padding: 10px 12px;
            font-size: 13px;
        }

        .badge {
            display: inline-flex;
            width: auto;
            max-width: 100%;
            white-space: nowrap;
        }
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
                <div class="book-card">
                    <?php if (!empty($book["image"])): ?>
                        <div class="book-image-wrap">
                            <img
                                class="book-image"
                                src="<?= e(basePath('image.php?file=' . urlencode($book["image"]))) ?>"
                                alt="Kitab şəkli"
                            >
                        </div>
                    <?php else: ?>
                        <div class="no-image">Şəkil yoxdur</div>
                    <?php endif; ?>

                    <h3><?= e($book["title"]) ?></h3>

                    <div class="book-author">
                        <?php echo htmlspecialchars($book["author"], ENT_QUOTES, 'UTF-8'); ?>
                    </div>

                    <div class="book-price">
                        <?php echo htmlspecialchars($book["price"], ENT_QUOTES, 'UTF-8'); ?> AZN
                    </div>

                    <?php
                    $conditionMeta = getConditionMeta($book['book_condition'] ?? $book['condition'] ?? '');
                    ?>

                    <div class="book-condition">
                        <span class="badge <?= e($conditionMeta['class']) ?>">
                            <?= e($conditionMeta['text']) ?>
                        </span>
                    </div>

                    <div class="book-actions">
                        <a href="<?= e(basePath('book.php?id=' . (int)$book['id'])) ?>" class="btn btn-light">
                            Ətraflı bax
                        </a>

                        <form method="POST" action="<?= e(basePath('toggle_favorite.php')) ?>" class="favorite-form">
                            <input type="hidden" name="book_id" value="<?php echo (int)$book['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <button type="submit" class="favorite-btn">
                                ❤️ Favoridən çıxar
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty">Hələ heç bir kitab favoriyə əlavə edilməyib.</div>
    <?php endif; ?>
</div>
</body>
</html>