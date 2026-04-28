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
    $status = $_POST["status"] ?? "active";

    $currentImage = $book["image"] ?? null;

    $allowedConditions = ["new", "like_new", "good", "fair", "poor"];
    $allowedLanguages = ["Azərbaycan", "İngilis", "Rus", "Türk", ""];
    $allowedGenres = [
        "Bədii","Elmi","Təhsil","Uşaq","Şəxsi inkişaf","Biznes",
        "Tarix","Din","Psixologiya","Roman","Detektiv","Fantastika",""
    ];
    $allowedStatuses = ["active", "sold", "hidden"];

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
    } elseif (!in_array($status, $allowedStatuses, true)) {
        $error = "Status düzgün deyil.";
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
                    published_year=?, status=?
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
                $status,
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
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-title {
            margin-bottom: 20px;
        }

        .page-title h1 {
            margin: 0 0 8px;
            font-size: 30px;
        }

        .page-title p {
            margin: 0;
            color: #64748b;
        }

        .card {
            background: #fff;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            border: 1px solid #e2e8f0;
        }

        .alert-error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 15px;
        }

        .alert-success {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #334155;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            font-size: 15px;
            outline: none;
            background: #fff;
        }

        input[type="file"] {
            padding: 10px 12px;
        }

        input:focus,
        textarea:focus,
        select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .book-image {
            width: 180px;
            height: 230px;
            object-fit: cover;
            border-radius: 14px;
            border: 1px solid #cbd5e1;
            margin-bottom: 18px;
            display: block;
            background: #f1f5f9;
        }

        .price-control {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .price-control input {
            flex: 1;
            margin: 0;
        }

        .price-btn {
            width: 44px;
            height: 44px;
            border: 1px solid #cbd5e1;
            background: #fff;
            border-radius: 12px;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            flex-shrink: 0;
        }

        .btn {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 13px 18px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
        }

        .back-link {
            display: inline-block;
            margin-top: 16px;
            text-decoration: none;
            color: #2563eb;
            font-weight: 600;
        }

        @media (max-width: 800px) {
            .row-2 {
                grid-template-columns: 1fr;
            }

            
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <div class="container">
        <div class="page-title">
            <h1>Kitabı redaktə et</h1>
            <p>Kitab məlumatlarını yenilə, statusu dəyiş və şəkli yenidən yüklə.</p>
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
                    <img class="book-image" src="<?php echo basePath('image.php'); ?>?file=<?php echo urlencode($book["image"]); ?>" alt="Kitab şəkli">
                <?php endif; ?>

                <div class="form-group">
                    <label for="image">Yeni kitab şəkli</label>
                    <small style="color:#64748b;">Maksimum ölçü: 10 MB (jpg, png, webp)</small>
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

                <div class="row-2">
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

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="active" <?php echo $book["status"] === "active" ? "selected" : ""; ?>>Aktiv</option>
                        <option value="sold" <?php echo $book["status"] === "sold" ? "selected" : ""; ?>>Satılıb</option>
                        <option value="hidden" <?php echo $book["status"] === "hidden" ? "selected" : ""; ?>>Gizli</option>
                    </select>
                </div>

                <button type="submit" class="btn">Yenilə</button>
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
