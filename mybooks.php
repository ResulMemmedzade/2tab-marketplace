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

function getStatusMeta($rawStatus)
{
    $normalizedStatus = strtolower(trim((string)$rawStatus));

    $statusMap = [
        'active' => ['text' => 'Aktiv', 'class' => 'status-active'],
        'sold' => ['text' => 'Satılıb', 'class' => 'status-sold'],
        'hidden' => ['text' => 'Gizli', 'class' => 'status-hidden']
    ];

    return $statusMap[$normalizedStatus] ?? [
        'text' => $rawStatus ?: 'Naməlum',
        'class' => 'status-default'
    ];
}

$seller_id = currentUserId();

$stmt = $pdo->prepare("
    SELECT * FROM books
    WHERE seller_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$seller_id]);
$myBooks = $stmt->fetchAll();

$success = "";
if (isset($_GET['added']) && $_GET['added'] == '1') {
    $success = "Kitab uğurla əlavə olundu.";
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2tab | Mənim kitablarım</title>
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

        .page-title {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
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

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .toolbar-title {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
        }

        .alert-success {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 15px;
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

        .btn-edit {
            background: #dbeafe;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        .btn-edit:hover {
            background: #bfdbfe;
        }

        .btn-danger-link {
            background: transparent;
            border: none;
            color: #b91c1c;
            cursor: pointer;
            padding: 0;
            font: inherit;
            text-decoration: underline;
            font-weight: 600;
        }

        .book-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .book-item {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px;
            background: #f8fafc;
        }

        .book-image {
            width: 140px;
            height: 180px;
            object-fit: cover;
            border-radius: 12px;
            border: 1px solid #cbd5e1;
            margin-bottom: 12px;
            display: block;
            background: #fff;
        }

        .no-image {
            width: 140px;
            height: 180px;
            border-radius: 12px;
            border: 1px dashed #cbd5e1;
            margin-bottom: 12px;
            background: #fff;
            color: #94a3b8;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .book-item h3 {
            margin: 0 0 8px;
            font-size: 20px;
            color: #0f172a;
            line-height: 1.4;
        }

        .book-top-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            margin-top: 2px;
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
            background: #e2e8f0;
            color: #334155;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-sold {
            background: #fee2e2;
            color: #b91c1c;
        }

        .status-hidden {
            background: #e2e8f0;
            color: #334155;
        }

        .status-default {
            background: #ede9fe;
            color: #6d28d9;
        }

        .book-meta {
            font-size: 14px;
            color: #475569;
            margin-bottom: 10px;
            line-height: 1.7;
        }

        .book-meta strong {
            color: #0f172a;
        }

        .book-desc {
            color: #334155;
            line-height: 1.6;
        }

        .inline-delete-form {
            display: inline;
            margin: 0;
            padding: 0;
        }

        .actions {
            margin-top: 14px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .empty {
            color: #475569;
            padding: 24px;
            border: 1px dashed #cbd5e1;
            border-radius: 16px;
            background: #f8fafc;
            text-align: center;
            line-height: 1.7;
        }

        .empty strong {
            display: block;
            color: #0f172a;
            font-size: 18px;
            margin-bottom: 8px;
        }

        @media (max-width: 700px) {
            .container {
                padding: 0 14px 32px;
            }

            .card,
            .book-item,
            .empty {
                border-radius: 16px;
            }

            .page-title {
                flex-direction: column;
                align-items: stretch;
            }

            .page-title h1 {
                font-size: 26px;
            }

            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .btn {
                width: 100%;
            }

            .book-image,
            .no-image {
                width: 100%;
                max-width: 220px;
                height: auto;
                aspect-ratio: 14 / 18;
            }
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/topbar.php'; ?>

<div class="container">
    <div class="page-title">
        <div>
            <h1>Mənim kitablarım</h1>
            <p>Paylaşdığın elanları burada idarə et.</p>
        </div>
    </div>

    <div class="card">
        <div class="toolbar">
            <div class="toolbar-title">Əlavə etdiyim kitablar</div>
            <a href="<?= e(basePath('add_book.php')) ?>" class="btn btn-primary">+ Yeni kitab əlavə et</a>
        </div>

        <?php if ($success): ?>
            <div class="alert-success"><?php echo e($success); ?></div>
        <?php endif; ?>

        <?php if (count($myBooks) > 0): ?>
            <div class="book-list">
                <?php foreach ($myBooks as $book): ?>
                    <?php
                    $conditionMeta = getConditionMeta($book["book_condition"] ?? "");
                    $statusMeta = getStatusMeta($book["status"] ?? "");
                    ?>
                    <div class="book-item">
                        <?php if (!empty($book["image"])): ?>
                            <img class="book-image" src="/2tab/image.php?file=<?php echo urlencode($book["image"]); ?>" alt="Kitab şəkli">
                        <?php else: ?>
                            <div class="no-image">Şəkil yoxdur</div>
                        <?php endif; ?>

                        <h3><?php echo e($book["title"]); ?></h3>

                        <div class="book-top-meta">
                            <span class="badge <?php echo e($conditionMeta['class']); ?>">
                                <?php echo e($conditionMeta['text']); ?>
                            </span>
                            <span class="badge <?php echo e($statusMeta['class']); ?>">
                                <?php echo e($statusMeta['text']); ?>
                            </span>
                        </div>

                        <div class="book-meta">
                            <strong>Müəllif:</strong> <?php echo e($book["author"]); ?><br>
                            <strong>Qiymət:</strong> <?php echo e($book["price"]); ?> AZN<br>
                            <strong>Janr:</strong> <?php echo e($book["genre"] ?: "Göstərilməyib"); ?><br>
                            <strong>Dil:</strong> <?php echo e($book["language"] ?? "Göstərilməyib"); ?><br>
                            <strong>Vəziyyət:</strong> <?php echo e($conditionMeta['text']); ?><br>
                            <strong>Nəşr ili:</strong> <?php echo e($book["published_year"] ?: "Göstərilməyib"); ?><br>
                            <strong>Tarix:</strong> <?php echo e($book["created_at"]); ?>
                        </div>

                        <div class="book-desc">
                            <?php echo nl2br(e($book["description"] ?: "Təsvir əlavə edilməyib.")); ?>
                        </div>

                        <div class="actions">
                            <a href="<?= e(basePath('edit_book.php?id=' . (int)$book['id'])) ?>" class="btn btn-edit">Redaktə et</a>

                            <form method="POST" action="<?= e(basePath('delete_book.php')) ?>" class="inline-delete-form">
                                <input type="hidden" name="id" value="<?php echo (int)$book['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <button type="submit" class="btn-danger-link" onclick="return confirm('Bu kitabı silmək istədiyinizə əminsiniz?');">Sil</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty">
                <strong>Hələ kitab əlavə etməmisiniz</strong>
                İlk elanını əlavə et və kitablarını burada idarə et.
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>