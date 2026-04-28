<?php

require_once "config.php";

session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax'
]);

session_start();

if (!isset($_SESSION['session_initialized'])) {
    session_regenerate_id(true);
    $_SESSION['session_initialized'] = true;
}

require_once "config.php";
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

$user_id = $_SESSION["user_id"] ?? 0;
$unreadMessageCount = 0;

if ($user_id) {
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
}

try {
    $stmt = $pdo->prepare("
        SELECT books.*, users.name AS seller_name
        FROM books
        JOIN users ON books.seller_id = users.id
        WHERE books.status = 'active'
          AND books.is_deleted = 0
        ORDER BY books.created_at DESC
        LIMIT 8
    ");
    $stmt->execute();
    $latestBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $latestBooks = [];
}

$isLoggedIn = isset($_SESSION["user_id"]);
$userName = $_SESSION["name"] ?? "";
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2tab | İkinci əl kitab bazarı</title>
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

    

    .brand {
        font-size: 24px;
        font-weight: bold;
        color: #2563eb;
        text-decoration: none;
    }

    .nav {
        display: flex;
        gap: 16px;
        align-items: center;
        flex-wrap: wrap;
    }

    .nav a {
        text-decoration: none;
        color: #334155;
        font-weight: 600;
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
    }

    .btn-primary {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: white;
    }

    .btn-light {
        background: #e2e8f0;
        color: #1e293b;
    }

    .hero {
        max-width: 1200px;
        margin: 30px auto 20px;
        padding: 0 20px;
    }

    .hero-card {
        background: linear-gradient(135deg, #eff6ff, #ffffff);
        border: 1px solid #dbeafe;
        border-radius: 24px;
        padding: 36px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
    }

    .hero h1 {
        margin: 0 0 14px;
        font-size: 40px;
        color: #0f172a;
        max-width: 700px;
    }

    .hero p {
        margin: 0 0 24px;
        color: #475569;
        font-size: 17px;
        line-height: 1.7;
        max-width: 760px;
    }

    .hero-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .section {
        max-width: 1200px;
        margin: 24px auto 40px;
        padding: 0 20px;
    }

    .section-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        margin-bottom: 18px;
    }

    .section-head h2 {
        margin: 0;
        font-size: 28px;
    }

    .section-head p {
        margin: 6px 0 0;
        color: #64748b;
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
    }

    .book-actions .btn {
        width: 100%;
    }

    .empty {
        background: #fff;
        border: 1px dashed #cbd5e1;
        border-radius: 16px;
        padding: 22px;
        color: #64748b;
    }

    .welcome-badge {
        display: inline-block;
        background: #eef2ff;
        color: #2563eb;
        border-radius: 999px;
        padding: 8px 12px;
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 14px;
    }

    @media (max-width: 900px) {
        .topbar {
            flex-direction: column;
            align-items: flex-start;
        }

        .hero h1 {
            font-size: 32px;
        }

        .section-head {
            flex-direction: column;
            align-items: flex-start;
        }
    }

    @media (max-width: 640px) {
        .section {
            padding: 0 14px;
        }

        .book-grid {
            gap: 12px;
        }

        .book-card {
            padding: 10px;
            border-radius: 16px;
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

        .book-actions .btn {
            padding: 10px 12px;
            font-size: 13px;
        }
    }
    @media (max-width: 520px) {
    .book-price {
        margin-bottom: 6px;
    }

    .book-condition {
        display: block;
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
    </div>

    <div class="hero">
        <div class="hero-card">
            <?php if ($isLoggedIn): ?>
                <div class="welcome-badge">Xoş gəldin, <?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <h1>Köhnə kitablar,yeni hekayələr.</h1>
            <p>
                2tab Azərbaycanın ixtisaslaşmış kitab bazarıdır.Siz burada axtardığınız 2-ci əl kitabları tapmaqla yanaşı,əlinizdə olan kitabları da sata bilərsiniz.
            </p>

            <div class="hero-actions">
                <a href="/books.php" class="btn btn-primary">Kitablara bax</a>

                <?php if ($isLoggedIn): ?>
                    <a href="/add_book.php" class="btn btn-light">Kitab paylaş</a>
                <?php else: ?>
                    <a href="/login.php?redirect=%2Fadd_book.php" class="btn btn-light">Kitab paylaş</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-head">
            <div>
                <h2>Son əlavə olunan kitablar</h2>
                
            </div>
            <a href="/books.php" class="btn btn-light">Hamısına bax</a>
        </div>

        <?php if (count($latestBooks) > 0): ?>
            <div class="book-grid">
                <?php foreach ($latestBooks as $book): ?>
                    <div class="book-card">
    <?php if (!empty($book["image"])): ?>
        <div class="book-image-wrap">
            <img
                class="book-image"
                src="/image.php?file=<?php echo urlencode($book["image"]); ?>"
                alt="Kitab şəkli"
            >
        </div>
    <?php else: ?>
        <div class="no-image">Şəkil yoxdur</div>
    <?php endif; ?>

    <h3><?php echo htmlspecialchars($book["title"], ENT_QUOTES, 'UTF-8'); ?></h3>

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
    <span class="badge <?= htmlspecialchars($conditionMeta['class'], ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars($conditionMeta['text'], ENT_QUOTES, 'UTF-8') ?>
    </span>
</div>
    <div class="book-actions">
        <a href="/book.php?id=<?php echo (int)$book['id']; ?>" class="btn btn-light">Ətraflı bax</a>
    </div>
</div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty">Hazırda aktiv kitab elanları yoxdur.</div>
        <?php endif; ?>
    </div>
</body>
</html>
