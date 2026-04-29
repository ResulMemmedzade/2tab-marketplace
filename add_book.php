<?php

require_once "config.php";
require_once "upload_helper.php";

requireLogin();
ensureCsrfToken();

$error = "";

$title = "";
$author = "";
$description = "";
$price_raw = "";
$genre = "";
$language = "";
$book_condition = "good";
$published_year = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        verifyCsrfToken($_POST['csrf_token'] ?? null);

        $user_id = currentUserId();
        $seller_id = $user_id;
        $status = "active";
        $imageName = null;

        // 🔥 BOOK SPAM CHECK
        if ($error === "") {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM books
                WHERE seller_id = ?
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$user_id]);
            $recentBookCount = (int)$stmt->fetchColumn();

            if ($recentBookCount >= 20) {
                addUserStrike($pdo, $user_id, 'Kitab spamı: 1 saatda çox sayda kitab əlavə etmə');

                appLog('rate_limit', 'Book spam detected', [
                    'user_id' => $user_id,
                    'recent_books' => $recentBookCount
                ]);

                $error = "Çox sayda kitab əlavə etdiniz. Bir az sonra yenidən yoxlayın.";
            }
        }

        $title = trim($_POST["title"] ?? "");
        $author = trim($_POST["author"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $price_raw = trim($_POST["price"] ?? "");
        $price = str_replace(",", ".", $price_raw);
        $genre = trim($_POST["genre"] ?? "");
        $language = trim($_POST["language"] ?? "");
        $book_condition = $_POST["book_condition"] ?? "good";
        $published_year = trim($_POST["published_year"] ?? "");

        // 🔥 XSS CHECK
        if ($error === "") {
            if (
                containsSuspiciousPayload($title) ||
                containsSuspiciousPayload($author) ||
                containsSuspiciousPayload($description)
            ) {
                addUserStrike($pdo, $user_id, 'XSS attempt in book');

                appLog('security', 'XSS payload detected in book form', [
                    'user_id' => $user_id
                ]);

                $error = "Təhlükəli məzmun aşkar edildi.";
            }
        }

        $allowedConditions = ["new", "like_new", "good", "fair", "poor"];
        $allowedLanguages = ["Azərbaycan", "İngilis", "Rus", "Türk", ""];
        $allowedGenres = [
            "Bədii", "Elmi", "Təhsil", "Uşaq", "Şəxsi inkişaf", "Biznes",
            "Tarix", "Din", "Psixologiya", "Roman", "Detektiv", "Fantastika", ""
        ];
        $allowedStatuses = ["active", "sold", "hidden"];

        if ($error === "") {
            if ($title === "" || $author === "" || $price === "") {
                $error = "Kitab adı, müəllif və qiymət mütləqdir.";
            } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $price) || (float)$price < 0) {
                appLog('input_validation', 'Invalid price format', ['price' => $price]);
                $error = "Qiymət düzgün daxil edilməyib.";
            } elseif (!in_array($genre, $allowedGenres, true)) {
                appLog('input_validation', 'Invalid genre', ['genre' => $genre]);
                $error = "Janr düzgün seçilməyib.";
            } elseif (!in_array($language, $allowedLanguages, true)) {
                appLog('input_validation', 'Invalid language', ['language' => $language]);
                $error = "Dil düzgün seçilməyib.";
            } elseif (!in_array($book_condition, $allowedConditions, true)) {
                appLog('input_validation', 'Invalid condition', ['condition' => $book_condition]);
                $error = "Kitab vəziyyəti düzgün deyil.";
            } elseif (!in_array($status, $allowedStatuses, true)) {
                appLog('input_validation', 'Invalid status', ['status' => $status]);
                $error = "Status düzgün deyil.";
            } elseif (
                $published_year !== "" &&
                (!ctype_digit($published_year) || (int)$published_year < 1900 || (int)$published_year > (int)date("Y"))
            ) {
                appLog('input_validation', 'Invalid published year', ['year' => $published_year]);
                $error = "Nəşr ili düzgün daxil edilməyib.";
            }
        }

        if ($error === "") {
            if (isset($_FILES["image"]) && $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE) {
                $uploadDir = rtrim(UPLOAD_STORAGE_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

                [$uploadOk, $uploadMessage, $savedFileName] = saveUploadedImage(
                    $_FILES["image"],
                    $uploadDir,
                    10 * 1024 * 1024
                );

                if (!$uploadOk) {
                    appLog('upload_error', 'Book image upload failed', [
                        'message' => $uploadMessage
                    ]);

                    $error = $uploadMessage;
                } else {
                    $imageName = $savedFileName;
                }
            }
        }

        if ($error === "") {
            $stmt = $pdo->prepare("
                INSERT INTO books (
                    seller_id, user_id, title, author, description,
                    price, image, genre, language, book_condition,
                    published_year, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $seller_id,
                $user_id,
                $title,
                $author,
                $description,
                $price,
                $imageName,
                $genre !== "" ? $genre : null,
                $language !== "" ? $language : null,
                $book_condition,
                $published_year !== "" ? (int)$published_year : null,
                $status
            ]);

            appLog('book_action', 'Book created successfully', [
                'title' => $title,
                'user_id' => $user_id
            ]);

            redirectTo("mybooks.php?added=1");
        }

    } catch (PDOException $e) {
        error_log($e->getMessage());

        appLog('system_error', 'DB error on book insert', [
            'error' => $e->getMessage()
        ]);

        $error = "Xəta baş verdi. Zəhmət olmasa sonra yenidən cəhd edin.";
    }
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2tab | Kitab əlavə et</title>
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
            max-width: 880px;
            margin: 30px auto;
            padding: 0 20px 40px;
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
            line-height: 1.6;
        }

        .card {
            background: #fff;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            border: 1px solid #e2e8f0;
        }

        .card h2 {
            margin-top: 0;
            margin-bottom: 18px;
            font-size: 22px;
            color: #0f172a;
        }

        .alert-error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 18px;
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
            color: #0f172a;
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

        .price-btn:hover {
            background: #f1f5f9;
        }

        @media (max-width: 900px) {
            .row-2,
            .row-3 {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 520px) {
            .container {
                padding: 0 14px 32px;
            }

            .card {
                border-radius: 16px;
                padding: 18px;
            }

            .page-title h1 {
                font-size: 26px;
            }
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/topbar.php'; ?>

<div class="container">
    <div class="page-title">
        <h1>Kitab əlavə et</h1>
        <p>Yeni kitab elanını əlavə et.</p>
    </div>

    <div class="card">
        <h2>Yeni kitab əlavə et</h2>

        <?php if ($error): ?>
            <div class="alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">

            <div class="form-group">
                <label for="image">Kitab şəkli</label>
                <small style="color:#64748b;">Maksimum ölçü: 10 MB (jpg, png, webp)</small>
                <input type="file" id="image" name="image">
            </div>

            <div class="form-group">
                <label for="title">Kitabın adı</label>
                <input type="text" id="title" name="title" placeholder="Məsələn: Gülün adı" required value="<?php echo e($title); ?>">
            </div>

            <div class="form-group">
                <label for="author">Müəllif</label>
                <input type="text" id="author" name="author" placeholder="Məsələn: Umberto Eco" required value="<?php echo e($author); ?>">
            </div>

            <div class="form-group">
                <label for="description">Təsvir</label>
                <textarea id="description" name="description" placeholder="Kitabın vəziyyəti, dili, qeyd və s."><?php echo e($description); ?></textarea>
            </div>

            <div class="row-2">
                <div class="form-group">
                    <label for="price">Qiymət</label>
                    <div class="price-control">
                        <button type="button" class="price-btn" onclick="changePrice(-0.5)">-</button>
                        <input type="text" id="price" name="price" placeholder="Məsələn: 12.5" inputmode="decimal" required value="<?php echo e($price_raw); ?>">
                        <button type="button" class="price-btn" onclick="changePrice(0.5)">+</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="genre">Janr</label>
                    <select id="genre" name="genre">
                        <option value="">Seçin</option>
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
                </div>
            </div>

            <div class="row-3">
                <div class="form-group">
                    <label for="language">Dil</label>
                    <select id="language" name="language">
                        <option value="">Seçin</option>
                        <option value="Azərbaycan" <?php echo $language === "Azərbaycan" ? "selected" : ""; ?>>Azərbaycan</option>
                        <option value="İngilis" <?php echo $language === "İngilis" ? "selected" : ""; ?>>İngilis</option>
                        <option value="Rus" <?php echo $language === "Rus" ? "selected" : ""; ?>>Rus</option>
                        <option value="Türk" <?php echo $language === "Türk" ? "selected" : ""; ?>>Türk</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="book_condition">Vəziyyət</label>
                    <select id="book_condition" name="book_condition">
                        <option value="new" <?php echo $book_condition === "new" ? "selected" : ""; ?>>Yeni</option>
                        <option value="like_new" <?php echo $book_condition === "like_new" ? "selected" : ""; ?>>Yeni kimi</option>
                        <option value="good" <?php echo $book_condition === "good" ? "selected" : ""; ?>>Yaxşı</option>
                        <option value="fair" <?php echo $book_condition === "fair" ? "selected" : ""; ?>>Orta</option>
                        <option value="poor" <?php echo $book_condition === "poor" ? "selected" : ""; ?>>Köhnə</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="published_year">Nəşr ili</label>
                    <select id="published_year" name="published_year">
                        <option value="">Seçin</option>
                        <?php for ($year = (int)date('Y'); $year >= 1900; $year--): ?>
                            <option value="<?php echo $year; ?>" <?php echo $published_year == $year ? "selected" : ""; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Kitabı əlavə et</button>
        </form>
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
