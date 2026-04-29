<?php

require_once "config.php";

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

    

    .search-box {
        flex: 1;
        max-width: 400px;
        position: relative;
        display: none;
    }

    @media (min-width: 768px) {
        .search-box {
            display: block;
        }
    }

    .search-box input {
        width: 100%;
        padding: 10px 16px 10px 44px;
        border: 1px solid var(--border);
        border-radius: 999px;
        background: var(--secondary);
        font-size: 14px;
        color: var(--foreground);
        transition: all 0.2s ease;
    }

    .search-box input:focus {
        outline: none;
        border-color: var(--primary);
        background: var(--card);
        box-shadow: 0 0 0 3px rgba(196, 112, 75, 0.1);
    }

    .search-box input::placeholder {
        color: var(--muted-foreground);
    }

    .search-box svg {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        width: 18px;
        height: 18px;
        color: var(--muted-foreground);
    }

    .nav-icons {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    

    .hero {
        max-width: 1200px;
        margin: 32px auto 24px;
        padding: 0 20px;
    }

    .hero-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 40px;
        box-shadow: var(--shadow);
    }

    .welcome-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: var(--secondary);
        color: var(--primary);
        border-radius: 999px;
        padding: 8px 14px;
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 16px;
    }

    .hero h1 {
        font-size: 42px;
        font-weight: 800;
        color: var(--foreground);
        line-height: 1.1;
        letter-spacing: -1px;
        margin-bottom: 16px;
        max-width: 600px;
    }

    .hero p {
        color: var(--muted-foreground);
        font-size: 17px;
        line-height: 1.7;
        max-width: 680px;
        margin-bottom: 28px;
    }

    .hero-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
        border: none;
        border-radius: var(--radius-sm);
        padding: 14px 24px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
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
    }

    .btn-secondary:hover {
        background: var(--secondary-hover);
    }

    .section {
        max-width: 1200px;
        margin: 0 auto 48px;
        padding: 0 20px;
    }

    .section-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .section-head h2 {
        font-size: 26px;
        font-weight: 700;
        color: var(--foreground);
        letter-spacing: -0.5px;
    }

    .book-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
        align-items: stretch;
    }

    @media (min-width: 640px) {
        .book-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (min-width: 1024px) {
        .book-grid {
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
    }

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

    .book-card:focus-visible {
        outline: 3px solid rgba(196, 112, 75, 0.25);
        outline-offset: 3px;
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

    .condition-new {
        background: rgba(220, 252, 231, 0.95);
        color: #166534;
    }

    .condition-like-new {
        background: rgba(219, 234, 254, 0.95);
        color: #1d4ed8;
    }

    .condition-good {
        background: rgba(224, 242, 254, 0.95);
        color: #075985;
    }

    .condition-fair {
        background: rgba(255, 237, 213, 0.95);
        color: #c2410c;
    }

    .condition-poor {
        background: rgba(254, 226, 226, 0.95);
        color: #b91c1c;
    }

    .condition-default {
        background: rgba(240, 235, 228, 0.95);
        color: var(--muted-foreground);
    }

    .empty {
        background: var(--card);
        border: 2px dashed var(--border);
        border-radius: var(--radius);
        padding: 48px 24px;
        color: var(--muted-foreground);
        text-align: center;
        font-size: 15px;
    }

    @media (max-width: 768px) {
        .hero-card {
            padding: 28px 24px;
        }

        .hero h1 {
            font-size: 32px;
        }

        .hero p {
            font-size: 15px;
        }

        .section-head {
            flex-direction: column;
            align-items: flex-start;
        }

        .section-head h2 {
            font-size: 22px;
        }
    }

    @media (max-width: 480px) {
        .topbar {
            padding: 10px 16px;
        }

        .brand {
            font-size: 22px;
        }

        .nav-icon {
            width: 40px;
            height: 40px;
        }

        .hero {
            margin-top: 20px;
            padding: 0 16px;
        }

        .hero-card {
            padding: 24px 20px;
        }

        .hero h1 {
            font-size: 28px;
        }

        .section {
            padding: 0 16px;
        }

        .book-grid {
            gap: 12px;
        }

        .book-card {
            padding: 10px;
        }

        .book-card h3 {
            font-size: 14px;
            min-height: 38px;
        }

        .book-price {
            font-size: 16px;
        }

        .badge {
            font-size: 10px;
            padding: 5px 9px;
        }

        .book-image-wrap .badge {
            left: 8px;
            bottom: 8px;
        }

        .btn {
            padding: 12px 18px;
            font-size: 14px;
        }
    }
    </style>
</head>
<body>
<?php require_once "includes/topbar.php"; ?>

    <section class="hero">
        <div class="hero-card">
            <?php if ($isLoggedIn): ?>
                <div class="welcome-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                    </svg>
                    Xoş gəldin, <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <h1>Köhnə kitablar, yeni hekayələr.</h1>
            <p>
                2tab Azərbaycanın ixtisaslaşmış kitab bazarıdır. Siz burada axtardığınız ikinci əl kitabları tapmaqla yanaşı, əlinizdə olan kitabları da sata bilərsiniz.
            </p>

            <div class="hero-actions">
                <a href="<?= e(basePath('books.php')) ?>" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    Kitablara bax
                </a>
                <?php if ($isLoggedIn): ?>
                    <a href="<?= e(basePath('add_book.php')) ?>" class="btn btn-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Kitab paylaş
                    </a>
                <?php else: ?>
                    <a href="<?= e(basePath('login.php')) ?>?redirect=<?= urlencode(basePath('add_book.php')) ?>" class="btn btn-secondary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Kitab paylaş
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="section-head">
            <h2>Son əlavə olunan kitablar</h2>
            <a href="<?= e(basePath('books.php')) ?>" class="btn btn-secondary">Hamısına bax</a>
        </div>

        <?php if (count($latestBooks) > 0): ?>
            <div class="book-grid">
                <?php foreach ($latestBooks as $book): ?>
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
            <div class="empty">Hazırda aktiv kitab elanları yoxdur.</div>
        <?php endif; ?>
    </section>
</body>
</html>