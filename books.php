<?php

require_once "config.php";

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

$user_id = currentUserId() ?? 0;

$search = trim($_GET["search"] ?? "");
$genre = trim($_GET["genre"] ?? "");
$condition = trim($_GET["condition"] ?? "");
$language = trim($_GET["language"] ?? "");
$sort = trim($_GET["sort"] ?? "newest");

$sortOptions = [
    'newest' => 'books.created_at DESC',
    'oldest' => 'books.created_at ASC',
    'price_asc' => 'books.price ASC',
    'price_desc' => 'books.price DESC',
    'title_asc' => 'books.title ASC',
    'title_desc' => 'books.title DESC'
];

$orderBy = $sortOptions[$sort] ?? $sortOptions['newest'];

$sql = "
    SELECT books.*, users.name AS seller_name, favorites.id AS favorite_id
    FROM books
    JOIN users ON books.seller_id = users.id
    LEFT JOIN favorites ON favorites.book_id = books.id AND favorites.user_id = ?
    WHERE books.status = 'active'
      AND books.is_deleted = 0
";

$params = [$user_id];

if ($search !== "") {
    $sql .= " AND (books.title LIKE ? OR books.author LIKE ? OR books.description LIKE ?)";
    $searchTerm = "%" . $search . "%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($genre !== "") {
    $sql .= " AND books.genre = ?";
    $params[] = $genre;
}

if ($language !== "") {
    $sql .= " AND books.language = ?";
    $params[] = $language;
}

if ($condition !== "") {
    $sql .= " AND LOWER(books.book_condition) = LOWER(?)";
    $params[] = $condition;
}

$sql .= " ORDER BY " . $orderBy;

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $books = [];
}

$filtersActive = ($search !== "" || $genre !== "" || $language !== "" || $condition !== "" || $sort !== "newest");
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2tab | Bütün kitablar</title>
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
        margin: 24px auto 48px;
        padding: 0 20px;
    }

    .page-heading {
        margin-bottom: 16px;
    }

    .page-heading h1 {
        font-size: 28px;
        font-weight: 800;
        color: var(--foreground);
        letter-spacing: -0.7px;
        line-height: 1.15;
    }

    .top-controls {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-bottom: 18px;
    }

    .search-form {
        width: 100%;
    }

    .search-box-main {
        position: relative;
        width: 100%;
    }

    .search-box-main svg {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        width: 22px;
        height: 22px;
        color: var(--muted-foreground);
        pointer-events: none;
    }

    .search-box-main input {
        width: 100%;
        height: 58px;
        padding: 0 18px 0 52px;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        background: var(--card);
        color: var(--foreground);
        font-size: 16px;
        font-weight: 600;
        font-family: inherit;
        box-shadow: var(--shadow-sm);
        outline: none;
        transition: all 0.2s ease;
    }

    .search-box-main input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(196, 112, 75, 0.12), var(--shadow-sm);
    }

    .search-box-main input::placeholder {
        color: var(--muted-foreground);
        font-weight: 600;
    }

    .filter-toggle-btn {
        width: 100%;
        height: 56px;
        background: var(--secondary);
        color: var(--secondary-foreground);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        font-size: 16px;
        font-weight: 800;
        font-family: inherit;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        transition: all 0.2s ease;
    }

    .filter-toggle-btn:hover {
        background: var(--secondary-hover);
    }

    .filter-toggle-btn svg {
        width: 20px;
        height: 20px;
        transition: transform 0.2s ease;
    }

    .filter-toggle-btn[aria-expanded="true"] svg {
        transform: rotate(180deg);
    }

    .filters-card {
        display: none;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 18px;
        margin-bottom: 20px;
        box-shadow: var(--shadow-sm);
    }

    .filters-card.is-open {
        display: block;
    }

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr) auto auto;
        gap: 12px;
        align-items: center;
    }

    select {
        width: 100%;
        height: 48px;
        padding: 0 14px;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        font-size: 14px;
        background: var(--secondary);
        outline: none;
        color: var(--foreground);
        font-family: inherit;
        font-weight: 600;
        transition: all 0.2s ease;
    }

    select:focus {
        border-color: var(--primary);
        background: var(--card);
        box-shadow: 0 0 0 3px rgba(196, 112, 75, 0.1);
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
        border: none;
        border-radius: var(--radius-sm);
        height: 48px;
        padding: 0 18px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
        font-family: inherit;
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

    .results-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin: 0 0 18px;
    }

    .results-text {
        color: var(--muted-foreground);
        font-size: 15px;
        font-weight: 700;
    }

    .add-book-btn {
        min-width: 154px;
        border-radius: 14px;
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

    .book-card h3 {
        font-size: 16px;
        font-weight: 700;
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
        font-weight: 800;
        color: var(--primary);
        line-height: 1.2;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        padding: 6px 11px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
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
        color: var(--muted-foreground);
        padding: 38px 24px;
        border: 2px dashed var(--border);
        border-radius: var(--radius);
        text-align: center;
        line-height: 1.7;
    }

    .empty strong {
        display: block;
        color: var(--foreground);
        font-size: 18px;
        margin-bottom: 8px;
    }

    @media (max-width: 1050px) {
        .filter-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .filter-grid .btn {
            width: 100%;
        }
    }

    @media (max-width: 640px) {
        .container {
            margin: 18px auto 36px;
            padding: 0 16px;
        }

        .page-heading h1 {
            font-size: 24px;
        }

        .search-box-main input {
            height: 54px;
            font-size: 15px;
        }

        .filter-grid {
            grid-template-columns: 1fr;
        }

        .results-row {
            align-items: center;
        }

        .results-text {
            font-size: 14px;
        }

        .add-book-btn {
            min-width: auto;
            height: 44px;
            padding: 0 14px;
            font-size: 13px;
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

        .book-author {
            font-size: 13px;
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
    }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/topbar.php'; ?>

<div class="container">
    

    <div class="top-controls">
        <form method="GET" class="search-form">
            <input type="hidden" name="genre" value="<?= e($genre); ?>">
            <input type="hidden" name="language" value="<?= e($language); ?>">
            <input type="hidden" name="condition" value="<?= e($condition); ?>">
            <input type="hidden" name="sort" value="<?= e($sort); ?>">

            <div class="search-box-main">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                    type="text"
                    name="search"
                    placeholder="Kitab axtar"
                    value="<?= e($search); ?>"
                >
            </div>
        </form>

        <button
            type="button"
            class="filter-toggle-btn"
            id="filterToggleBtn"
            aria-expanded="<?= $filtersActive ? 'true' : 'false'; ?>"
        >
            Filterlər
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
    </div>

    <div
        class="filters-card <?= $filtersActive ? 'is-open' : ''; ?>"
        id="filtersCard"
    >
        <form method="GET" class="filter-grid">
            <input type="hidden" name="search" value="<?= e($search); ?>">

            <select name="genre">
                <option value="">Bütün janrlar</option>
                <option value="Bədii" <?= $genre === "Bədii" ? "selected" : ""; ?>>Bədii</option>
                <option value="Elmi" <?= $genre === "Elmi" ? "selected" : ""; ?>>Elmi</option>
                <option value="Təhsil" <?= $genre === "Təhsil" ? "selected" : ""; ?>>Təhsil</option>
                <option value="Uşaq" <?= $genre === "Uşaq" ? "selected" : ""; ?>>Uşaq</option>
                <option value="Şəxsi inkişaf" <?= $genre === "Şəxsi inkişaf" ? "selected" : ""; ?>>Şəxsi inkişaf</option>
                <option value="Biznes" <?= $genre === "Biznes" ? "selected" : ""; ?>>Biznes</option>
                <option value="Tarix" <?= $genre === "Tarix" ? "selected" : ""; ?>>Tarix</option>
                <option value="Din" <?= $genre === "Din" ? "selected" : ""; ?>>Din</option>
                <option value="Psixologiya" <?= $genre === "Psixologiya" ? "selected" : ""; ?>>Psixologiya</option>
                <option value="Roman" <?= $genre === "Roman" ? "selected" : ""; ?>>Roman</option>
                <option value="Detektiv" <?= $genre === "Detektiv" ? "selected" : ""; ?>>Detektiv</option>
                <option value="Fantastika" <?= $genre === "Fantastika" ? "selected" : ""; ?>>Fantastika</option>
            </select>

            <select name="language">
                <option value="">Bütün dillər</option>
                <option value="Azərbaycan" <?= $language === "Azərbaycan" ? "selected" : ""; ?>>Azərbaycan</option>
                <option value="İngilis" <?= $language === "İngilis" ? "selected" : ""; ?>>İngilis</option>
                <option value="Rus" <?= $language === "Rus" ? "selected" : ""; ?>>Rus</option>
                <option value="Türk" <?= $language === "Türk" ? "selected" : ""; ?>>Türk</option>
            </select>

            <select name="condition">
                <option value="">Bütün vəziyyətlər</option>
                <option value="new" <?= $condition === "new" ? "selected" : ""; ?>>Yeni</option>
                <option value="like_new" <?= $condition === "like_new" ? "selected" : ""; ?>>Yeni kimi</option>
                <option value="good" <?= $condition === "good" ? "selected" : ""; ?>>Yaxşı</option>
                <option value="fair" <?= $condition === "fair" ? "selected" : ""; ?>>Orta</option>
                <option value="poor" <?= $condition === "poor" ? "selected" : ""; ?>>Köhnə</option>
            </select>

            <select name="sort">
                <option value="newest" <?= $sort === "newest" ? "selected" : ""; ?>>Ən yeni</option>
                <option value="oldest" <?= $sort === "oldest" ? "selected" : ""; ?>>Ən köhnə</option>
                <option value="price_asc" <?= $sort === "price_asc" ? "selected" : ""; ?>>Qiymət: əvvəl ucuz</option>
                <option value="price_desc" <?= $sort === "price_desc" ? "selected" : ""; ?>>Qiymət: əvvəl baha</option>
                <option value="title_asc" <?= $sort === "title_asc" ? "selected" : ""; ?>>A-dan Z-yə</option>
                <option value="title_desc" <?= $sort === "title_desc" ? "selected" : ""; ?>>Z-dən A-ya</option>
            </select>

            <button type="submit" class="btn btn-primary">Tətbiq et</button>
            <a href="<?= e(basePath('books.php')) ?>" class="btn btn-secondary">Təmizlə</a>
        </form>
    </div>

    <div class="results-row">
        <div class="results-text">
            <?php if ($filtersActive): ?>
                <?= count($books); ?> nəticə tapıldı.
            <?php else: ?>
                Hazırda <?= count($books); ?> kitab göstərilir.
            <?php endif; ?>
        </div>

        <a href="<?= e(basePath('add_book.php')) ?>" class="btn btn-primary add-book-btn">
            + Kitab əlavə et
        </a>
    </div>

    <?php if (count($books) > 0): ?>
        <div class="book-grid">
            <?php foreach ($books as $book): ?>
                <?php
                $conditionMeta = getConditionMeta($book['book_condition'] ?? $book['condition'] ?? '');
                ?>
                <a href="<?= e(basePath('book.php?id=' . (int)$book['id'])) ?>" class="book-card">
                    <div class="book-image-wrap">
                        <?php if (!empty($book["image"])): ?>
                            <img
                                class="book-image"
                                src="<?= e(basePath('image.php?file=' . urlencode($book["image"]))) ?>"
                                alt="<?= e($book["title"]); ?>"
                                loading="lazy"
                            >
                        <?php else: ?>
                            <div class="no-image">Şəkil yoxdur</div>
                        <?php endif; ?>

                        <span class="badge <?= e($conditionMeta['class']) ?>">
                            <?= e($conditionMeta['text']) ?>
                        </span>
                    </div>

                    <h3><?= e($book["title"]); ?></h3>

                    <div class="book-author">
                        <?= e($book["author"]); ?>
                    </div>

                    <div class="book-meta">
                        <span class="book-price"><?= e($book["price"]); ?> AZN</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty">
            <strong>Nəticə tapılmadı</strong>
            Seçilən filterlərə uyğun aktiv kitab yoxdur. Filterləri dəyiş və ya təmizləyib yenidən yoxla.
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const filterToggleBtn = document.getElementById("filterToggleBtn");
    const filtersCard = document.getElementById("filtersCard");

    if (filterToggleBtn && filtersCard) {
        filterToggleBtn.addEventListener("click", function () {
            const isOpen = filtersCard.classList.toggle("is-open");
            filterToggleBtn.setAttribute("aria-expanded", isOpen ? "true" : "false");
        });
    }
});
</script>
</body>
</html>