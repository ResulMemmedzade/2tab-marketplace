<?php

require_once "config.php";
require_once "upload_helper.php";

requireLogin();
ensureCsrfToken();

$seller_id = currentUserId();
$id = (int)($_GET["id"] ?? $_POST["id"] ?? 0);

if ($id <= 0) {
    redirectTo("mybooks.php");
}

try {
    $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ? AND seller_id = ?");
    $stmt->execute([$id, $seller_id]);
    $book = $stmt->fetch();
} catch (PDOException $e) {
    error_log($e->getMessage());
    redirectTo("mybooks.php");
}

if (!$book) {
    redirectTo("mybooks.php");
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    verifyCsrfToken($_POST['csrf_token'] ?? null);

    $title = trim($_POST["title"] ?? "");
    $author = trim($_POST["author"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $price_raw = trim($_POST["price"] ?? "");
    $price = str_replace(",", ".", $price_raw);
    $genre = trim($_POST["genre"] ?? "");
    $language = trim($_POST["language"] ?? "");
    $book_condition = $_POST["book_condition"] ?? "good";
    $published_year = trim($_POST["published_year"] ?? "");
    $currentImage = $book["image"] ?? null;

    $allowedConditions = ["new", "like_new", "good", "fair", "poor"];
    $allowedLanguages = ["Azərbaycan", "İngilis", "Rus", "Türk", ""];
    $allowedGenres = [
        "Bədii","Elmi","Təhsil","Uşaq","Şəxsi inkişaf","Biznes",
        "Tarix","Din","Psixologiya","Roman","Detektiv","Fantastika",""
    ];

    if ($title === "" || $author === "" || $price === "") {
        $error = "Kitab adı, müəllif və qiymət mütləqdir.";
    } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $price) || (float)$price < 0) {
        $error = "Qiymət düzgün deyil.";
    } elseif (!in_array($genre, $allowedGenres, true)) {
        $error = "Janr düzgün deyil.";
    } elseif (!in_array($language, $allowedLanguages, true)) {
        $error = "Dil düzgün deyil.";
    } elseif (!in_array($book_condition, $allowedConditions, true)) {
        $error = "Vəziyyət düzgün deyil.";
    } elseif (
        $published_year !== "" &&
        (!ctype_digit($published_year) ||
         (int)$published_year < 1900 ||
         (int)$published_year > (int)date("Y"))
    ) {
        $error = "Nəşr ili düzgün deyil.";
    } else {

        $newImageName = $currentImage;

        if (!empty($_FILES["image"]["name"])) {
            $uploadDir = rtrim(UPLOAD_STORAGE_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        
            [$uploadOk, $uploadMessage, $savedFileName] = saveUploadedImage(
                $_FILES["image"],
                $uploadDir,
                10 * 1024 * 1024
            );
        
            if (!$uploadOk) {
                $error = $uploadMessage;
            } else {
                if ($currentImage) {
                    $oldPath = $uploadDir . basename($currentImage);
                    if (is_file($oldPath)) {
                        unlink($oldPath);
                    }
                }
        
                $newImageName = $savedFileName;
            }
        }

        if (!$error) {

            $stmt = $pdo->prepare("
                UPDATE books SET
                    title=?, author=?, description=?, price=?,
                    image=?, genre=?, language=?, book_condition=?,
                    published_year=?
                WHERE id=? AND seller_id=?
            ");

            $stmt->execute([
                $title,
                $author,
                $description,
                $price,
                $newImageName,
                $genre ?: null,
                $language ?: null,
                $book_condition,
                $published_year ?: null,
                $id,
                $seller_id
            ]);

            $success = "Uğurla yeniləndi.";

            $stmt = $pdo->prepare("SELECT * FROM books WHERE id=? AND seller_id=?");
            $stmt->execute([$id, $seller_id]);
            $book = $stmt->fetch();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2tab | Kitabı redaktə et</title>
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
            max-width: 1000px;
            margin: 32px auto 48px;
            padding: 0 20px;
        }

        .page-card,
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .page-card {
            padding: 32px;
            margin-bottom: 20px;
        }

        .page-title h1 {
            font-size: 34px;
            font-weight: 800;
            color: var(--foreground);
            letter-spacing: -0.8px;
            line-height: 1.15;
            margin-bottom: 8px;
        }

        .page-title p {
            color: var(--muted-foreground);
            font-size: 15px;
            line-height: 1.7;
        }

        .card {
            padding: 28px;
        }

        .alert-error,
        .alert-success {
            padding: 13px 14px;
            border-radius: var(--radius-sm);
            margin-bottom: 18px;
            font-size: 14px;
            font-weight: 600;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .current-image-wrap {
            margin-bottom: 20px;
        }

        .current-image-label {
            display: block;
            margin-bottom: 10px;
            font-size: 14px;
            font-weight: 700;
            color: var(--secondary-foreground);
        }

        .book-image {
            width: 190px;
            height: 250px;
            object-fit: cover;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            display: block;
            background: var(--muted);
            box-shadow: var(--shadow-sm);
        }

        .form-group {
            margin-bottom: 18px;
        }

        .row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 14px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 700;
            color: var(--secondary-foreground);
        }

        .field-help {
            display: block;
            color: var(--muted-foreground);
            font-size: 13px;
            margin-bottom: 8px;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 14px;
            outline: none;
            background: var(--secondary);
            color: var(--foreground);
            font-family: inherit;
            transition: all 0.2s ease;
        }

        input[type="file"] {
            padding: 11px 12px;
            cursor: pointer;
        }

        input:focus,
        textarea:focus,
        select:focus {
            border-color: var(--primary);
            background: var(--card);
            box-shadow: 0 0 0 3px rgba(196, 112, 75, 0.1);
        }

        textarea {
            min-height: 130px;
            resize: vertical;
        }

        .price-control {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .price-control input {
            flex: 1;
        }

        .price-btn {
            width: 44px;
            height: 44px;
            border: 1px solid var(--border);
            background: var(--secondary);
            color: var(--secondary-foreground);
            border-radius: var(--radius-sm);
            font-size: 20px;
            font-weight: 800;
            cursor: pointer;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }

        .price-btn:hover {
            background: var(--secondary-hover);
            transform: translateY(-1px);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            border: none;
            border-radius: var(--radius-sm);
            padding: 14px 22px;
            font-size: 15px;
            font-weight: 600;
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

        .back-link {
            display: inline-flex;
            align-items: center;
            margin-top: 18px;
            text-decoration: none;
            color: var(--primary);
            font-weight: 700;
            font-size: 14px;
        }

        .back-link:hover {
            color: var(--primary-hover);
        }

        .submit-row {
            display: flex;
            justify-content: flex-start;
            margin-top: 8px;
        }

        @media (max-width: 900px) {
            .row-2,
            .row-3 {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }

        @media (max-width: 640px) {
            .container {
                margin: 20px auto 36px;
                padding: 0 16px;
            }

            .page-card {
                padding: 24px 20px;
            }

            .card {
                padding: 22px 18px;
            }

            .page-title h1 {
                font-size: 30px;
            }

            .book-image {
                width: 150px;
                height: 205px;
            }

            .btn {
                width: 100%;
                padding: 13px 18px;
                font-size: 14px;
            }

            .back-link {
                justify-content: center;
                width: 100%;
            }
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/topbar.php'; ?>

<div class="container">
    <div class="page-card">
        <div class="page-title">
            <h1>Kitabı redaktə et</h1>
            <p>Kitab məlumatlarını yenilə və istəsən yeni şəkil yüklə.</p>
        </div>
    </div>

    <div class="card">
        <?php if ($error): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="id" value="<?php echo (int)$book['id']; ?>">

            <?php if (!empty($book["image"])): ?>
                <div class="current-image-wrap">
                    <span class="current-image-label">Hazırkı şəkil</span>
                    <img class="book-image" src="<?php echo basePath('image.php'); ?>?file=<?php echo urlencode($book["image"]); ?>" alt="Kitab şəkli">
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="image">Yeni kitab şəkli</label>
                <small class="field-help">Maksimum ölçü: 10 MB (jpg, png, webp)</small>
                <input type="file" id="image" name="image">
            </div>

            <div class="form-group">
                <label for="title">Kitabın adı</label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($book["title"], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div class="form-group">
                <label for="author">Müəllif</label>
                <input type="text" id="author" name="author" value="<?php echo htmlspecialchars($book["author"], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div class="form-group">
                <label for="description">Təsvir</label>
                <textarea id="description" name="description"><?php echo htmlspecialchars($book["description"], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <div class="row-2">
                <div class="form-group">
                    <label for="price">Qiymət</label>
                    <div class="price-control">
                        <button type="button" class="price-btn" onclick="changePrice(-0.5)">-</button>
                        <input type="text" id="price" name="price" value="<?php echo htmlspecialchars($book["price"], ENT_QUOTES, 'UTF-8'); ?>" inputmode="decimal" required>
                        <button type="button" class="price-btn" onclick="changePrice(0.5)">+</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="genre">Janr</label>
                    <select id="genre" name="genre">
                        <option value="">Seçin</option>
                        <option value="Bədii" <?php echo $book["genre"] === "Bədii" ? "selected" : ""; ?>>Bədii</option>
                        <option value="Elmi" <?php echo $book["genre"] === "Elmi" ? "selected" : ""; ?>>Elmi</option>
                        <option value="Təhsil" <?php echo $book["genre"] === "Təhsil" ? "selected" : ""; ?>>Təhsil</option>
                        <option value="Uşaq" <?php echo $book["genre"] === "Uşaq" ? "selected" : ""; ?>>Uşaq</option>
                        <option value="Şəxsi inkişaf" <?php echo $book["genre"] === "Şəxsi inkişaf" ? "selected" : ""; ?>>Şəxsi inkişaf</option>
                        <option value="Biznes" <?php echo $book["genre"] === "Biznes" ? "selected" : ""; ?>>Biznes</option>
                        <option value="Tarix" <?php echo $book["genre"] === "Tarix" ? "selected" : ""; ?>>Tarix</option>
                        <option value="Din" <?php echo $book["genre"] === "Din" ? "selected" : ""; ?>>Din</option>
                        <option value="Psixologiya" <?php echo $book["genre"] === "Psixologiya" ? "selected" : ""; ?>>Psixologiya</option>
                        <option value="Roman" <?php echo $book["genre"] === "Roman" ? "selected" : ""; ?>>Roman</option>
                        <option value="Detektiv" <?php echo $book["genre"] === "Detektiv" ? "selected" : ""; ?>>Detektiv</option>
                        <option value="Fantastika" <?php echo $book["genre"] === "Fantastika" ? "selected" : ""; ?>>Fantastika</option>
                    </select>
                </div>
            </div>

            <div class="row-3">
                <div class="form-group">
                    <label for="book_condition">Vəziyyət</label>
                    <select id="book_condition" name="book_condition">
                        <option value="new" <?php echo $book["book_condition"] === "new" ? "selected" : ""; ?>>Yeni</option>
                        <option value="like_new" <?php echo $book["book_condition"] === "like_new" ? "selected" : ""; ?>>Yeni kimi</option>
                        <option value="good" <?php echo $book["book_condition"] === "good" ? "selected" : ""; ?>>Yaxşı</option>
                        <option value="fair" <?php echo $book["book_condition"] === "fair" ? "selected" : ""; ?>>Orta</option>
                        <option value="poor" <?php echo $book["book_condition"] === "poor" ? "selected" : ""; ?>>Köhnə</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="language">Dil</label>
                    <select id="language" name="language">
                        <option value="">Seçin</option>
                        <option value="Azərbaycan" <?php echo ($book["language"] ?? "") === "Azərbaycan" ? "selected" : ""; ?>>Azərbaycan</option>
                        <option value="İngilis" <?php echo ($book["language"] ?? "") === "İngilis" ? "selected" : ""; ?>>İngilis</option>
                        <option value="Rus" <?php echo ($book["language"] ?? "") === "Rus" ? "selected" : ""; ?>>Rus</option>
                        <option value="Türk" <?php echo ($book["language"] ?? "") === "Türk" ? "selected" : ""; ?>>Türk</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="published_year">Nəşr ili</label>
                    <select id="published_year" name="published_year">
                        <option value="">Seçin</option>
                        <?php for ($year = (int)date('Y'); $year >= 1900; $year--): ?>
                            <option value="<?php echo $year; ?>" <?php echo ((string)$book["published_year"] === (string)$year) ? "selected" : ""; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div class="submit-row">
                <button type="submit" class="btn btn-primary">Yenilə</button>
            </div>
        </form>

        <a class="back-link" href="<?php echo basePath('mybooks.php'); ?>">← Geri qayıt</a>
    </div>
</div>

<script>
function changePrice(amount) {
    const input = document.getElementById("price");
    let current = parseFloat((input.value || "0").replace(",", "."));

    if (isNaN(current)) {
        current = 0;
    }

    current += amount;

    if (current < 0) {
        current = 0;
    }

    input.value = current.toFixed(2).replace(/\.00$/, "").replace(/(\.\d)0$/, "$1");
}
</script>
</body>
</html>