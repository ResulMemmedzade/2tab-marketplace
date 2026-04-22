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
    $sql .= " AND books.book_condition = ?";
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
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2tab | Bütün kitablar</title>
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
        max-width: 1200px;
        margin: 30px auto;
        padding: 0 20px 40px;
    }

    .page-title {
        margin-bottom: 20px;
    }

    .page-title h1 {
        margin: 0 0 8px;
        font-size: 32px;
        color: #0f172a;
    }

    .page-title p {
        margin: 0;
        color: #64748b;
        line-height: 1.6;
    }

    .page-header-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .header-actions {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
        min-width: 220px;
    }

    .filter-toggle-btn {
        width: 100%;
    }

    .filters-card {
        display: none;
    }

    .filters-card.is-open {
        display: block;
    }

    .filters-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 18px;
        margin-bottom: 24px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
    }

    .filters-title {
        margin: 0 0 14px;
        font-size: 18px;
        color: #0f172a;
    }

    .filter-grid {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto auto;
        gap: 12px;
        align-items: center;
    }

    input,
    select {
        width: 100%;
        padding: 13px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        font-size: 15px;
        background: #fff;
        outline: none;
        color: #0f172a;
    }

    input:focus,
    select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
    }

    .btn {
        border: none;
        border-radius: 12px;
        padding: 13px 18px;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        white-space: nowrap;
        transition: 0.2s ease;
    }

    .btn-primary {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 24px rgba(37, 99, 235, 0.18);
    }

    .btn-light {
        background: #e2e8f0;
        color: #1e293b;
    }

    .btn-light:hover {
        background: #cbd5e1;
    }

    .results-text {
        margin-bottom: 18px;
        color: #475569;
        font-size: 14px;
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

    .book-actions {
        margin-top: auto;
    }

    .book-actions .btn {
        width: 100%;
    }

    .empty {
        background: #ffffff;
        color: #475569;
        padding: 28px;
        border: 1px dashed #cbd5e1;
        border-radius: 18px;
        text-align: center;
        line-height: 1.7;
    }

    .empty strong {
        display: block;
        color: #0f172a;
        font-size: 18px;
        margin-bottom: 8px;
    }

    @media (max-width: 1100px) {
        .filter-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 900px) {
        .topbar {
            flex-direction: column;
            align-items: flex-start;
        }

        .header-actions {
            width: 100%;
        }

        .header-actions .btn {
            width: 100%;
        }

        .filter-grid {
            grid-template-columns: 1fr;
        }

        .page-title h1 {
            font-size: 28px;
        }

        .page-header-row {
            align-items: stretch;
        }

        .page-header-row .btn {
            width: 100%;
        }
    }

    @media (max-width: 640px) {
        .container {
            padding: 0 14px 32px;
        }

        .filters-card,
        .book-card,
        .empty {
            border-radius: 16px;
        }

        .book-grid {
            gap: 12px;
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

        .book-actions .btn {
            padding: 10px 12px;
            font-size: 13px;
        }
    }
</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/topbar.php'; ?>

<div class="container">
    <div class="page-title">
        <h1>Bütün kitablar</h1>
    </div>

    <div class="page-header-row">
        <div></div>

        <div class="header-actions">
            <a href="<?= e(basePath('add_book.php')) ?>" class="btn btn-primary">+ Kitab əlavə et</a>

            <button
                type="button"
                class="btn btn-light filter-toggle-btn"
                id="filterToggleBtn"
                aria-expanded="<?php echo ($search !== "" || $genre !== "" || $language !== "" || $condition !== "" || $sort !== "newest") ? 'true' : 'false'; ?>"
            >
                Filterlər
            </button>
        </div>
    </div>

    <div
        class="filters-card <?php echo ($search !== "" || $genre !== "" || $language !== "" || $condition !== "" || $sort !== "newest") ? 'is-open' : ''; ?>"
        id="filtersCard"
    >
        <h2 class="filters-title">Axtarış və filter</h2>

        <form method="GET" class="filter-grid">
            <input
                type="text"
                name="search"
                placeholder="Kitab adı, müəllif və ya təsvir üzrə axtar..."
                value="<?php echo e($search); ?>"
            >

            <select name="genre">
                <option value="">Bütün janrlar</option>
                <option value="Bədii" <?php echo $genre === "Bədii" ? "selected" : ""; ?>>Bədii</option>
                <option value="Elmi" <?php echo $genre === "Elmi" ? "selected" : ""; ?>>Elmi</option>
                <option value="Təhsil" <?php echo $genre === "Təhsil" ? "selected" : ""; ?>>Təhsil</option>
                <option value="Uşaq" <?php echo $genre === "Uşaq" ? "selected" : ""; ?>>Uşaq</option>
                <option value="Şəxsi inkişaf" <?php echo $genre === "Şəxsi inkişaf" ? "selected" : ""; ?>>Şəxsi inkişaf</option>
                <option value="Biznes" <?php echo $genre === "Biznes" ? "selected" : ""; ?>>Biznes</option>
                <option value="Tarix" <?php echo $genre === "Tarix" ? "selected" : ""; ?>>Tarix</option>
                <option value="Din" <?php echo $genre === "Din" ? "selected" : ""; ?>>Din</option>
                <option value="Psixologiya" <?php echo $genre === "Psixologiya" ? "selected" : ""; ?>>Psixologiya</option>
                <option value="Roman" <?php echo $genre === "Roman" ? "selected" : ""; ?>>Roman</option>
                <option value="Detektiv" <?php echo $genre === "Detektiv" ? "selected" : ""; ?>>Detektiv</option>
                <option value="Fantastika" <?php echo $genre === "Fantastika" ? "selected" : ""; ?>>Fantastika</option>
            </select>

            <select name="language">
                <option value="">Bütün dillər</option>
                <option value="Azərbaycan" <?php echo $language === "Azərbaycan" ? "selected" : ""; ?>>Azərbaycan</option>
                <option value="İngilis" <?php echo $language === "İngilis" ? "selected" : ""; ?>>İngilis</option>
                <option value="Rus" <?php echo $language === "Rus" ? "selected" : ""; ?>>Rus</option>
                <option value="Türk" <?php echo $language === "Türk" ? "selected" : ""; ?>>Türk</option>
            </select>

            <select name="condition">
                <option value="">Bütün vəziyyətlər</option>
                <option value="new" <?php echo $condition === "new" ? "selected" : ""; ?>>Yeni</option>
                <option value="like_new" <?php echo $condition === "like_new" ? "selected" : ""; ?>>Yeni kimi</option>
                <option value="good" <?php echo $condition === "good" ? "selected" : ""; ?>>Yaxşı</option>
                <option value="fair" <?php echo $condition === "fair" ? "selected" : ""; ?>>Orta</option>
                <option value="poor" <?php echo $condition === "poor" ? "selected" : ""; ?>>Köhnə</option>
            </select>

            <select name="sort">
                <option value="newest" <?php echo $sort === "newest" ? "selected" : ""; ?>>Ən yeni</option>
                <option value="oldest" <?php echo $sort === "oldest" ? "selected" : ""; ?>>Ən köhnə</option>
                <option value="price_asc" <?php echo $sort === "price_asc" ? "selected" : ""; ?>>Qiymət: əvvəl ucuz</option>
                <option value="price_desc" <?php echo $sort === "price_desc" ? "selected" : ""; ?>>Qiymət: əvvəl baha</option>
                <option value="title_asc" <?php echo $sort === "title_asc" ? "selected" : ""; ?>>A-dan Z-yə</option>
                <option value="title_desc" <?php echo $sort === "title_desc" ? "selected" : ""; ?>>Z-dən A-ya</option>
            </select>

            <button type="submit" class="btn btn-primary">Axtar</button>
            <a href="<?= e(basePath('books.php')) ?>" class="btn btn-light">Təmizlə</a>
        </form>
    </div>

    <div class="results-text">
        <?php if ($search !== "" || $genre !== "" || $language !== "" || $condition !== "" || $sort !== "newest"): ?>
            <?php echo count($books); ?> nəticə tapıldı.
        <?php else: ?>
            Hazırda <?php echo count($books); ?> kitab göstərilir.
        <?php endif; ?>
    </div>

    <?php if (count($books) > 0): ?>
        <div class="book-grid">
            <?php foreach ($books as $book): ?>
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

                    <h3><?php echo e($book["title"]); ?></h3>

                    <div class="book-author">
                        <?php echo e($book["author"]); ?>
                    </div>

                    <div class="book-price">
                        <?php echo e($book["price"]); ?> AZN
                    </div>

                    <div class="book-actions">
                        <a href="<?= e(basePath('book.php?id=' . (int)$book['id'])) ?>" class="btn btn-light">
                            Ətraflı bax
                        </a>
                    </div>
                </div>
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
    const savedScroll = sessionStorage.getItem("booksScrollY");
    if (savedScroll !== null) {
        window.scrollTo(0, parseInt(savedScroll, 10));
        sessionStorage.removeItem("booksScrollY");
    }

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