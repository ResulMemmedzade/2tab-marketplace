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
    <title>Mənim kitablarım - 2tab</title>
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
            --destructive: #dc2626;
            --destructive-hover: #b91c1c;
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

        .page-header {
            margin-bottom: 24px;
        }

        .page-header h1 {
            margin: 0 0 8px;
            font-size: 32px;
            font-weight: 800;
            color: var(--foreground);
            letter-spacing: -0.5px;
        }

        .page-header p {
            margin: 0;
            color: var(--muted-foreground);
            font-size: 16px;
            line-height: 1.6;
        }

        .card {
            background: var(--card);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .toolbar-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--foreground);
            letter-spacing: -0.3px;
        }

        .alert-success {
            background: rgba(107, 143, 113, 0.12);
            color: #3d6b44;
            border: 1px solid rgba(107, 143, 113, 0.3);
            padding: 14px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 16px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .btn {
            border: none;
            border-radius: var(--radius-sm);
            padding: 12px 18px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            white-space: nowrap;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .btn svg {
            width: 18px;
            height: 18px;
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

        .btn-danger {
            background: transparent;
            color: var(--destructive);
            padding: 12px 14px;
        }

        .btn-danger:hover {
            background: rgba(220, 38, 38, 0.08);
        }

        .book-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .book-item {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 20px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 16px;
            background: var(--secondary);
            transition: all 0.2s ease;
        }

        .book-item:hover {
            box-shadow: var(--shadow-sm);
            border-color: transparent;
        }

        .book-image-wrap {
            position: relative;
        }

        .book-image {
            width: 140px;
            height: 180px;
            object-fit: cover;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            display: block;
            background: var(--card);
        }

        .no-image {
            width: 140px;
            height: 180px;
            border-radius: var(--radius-sm);
            border: 2px dashed var(--border);
            background: var(--card);
            color: var(--muted-foreground);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            text-align: center;
        }

        .book-content {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .book-item h3 {
            margin: 0 0 10px;
            font-size: 18px;
            font-weight: 700;
            color: var(--foreground);
            line-height: 1.4;
            letter-spacing: -0.3px;
        }

        .book-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .condition-new {
            background: rgba(34, 197, 94, 0.15);
            color: #15803d;
        }

        .condition-like-new {
            background: rgba(59, 130, 246, 0.15);
            color: #1d4ed8;
        }

        .condition-good {
            background: rgba(14, 165, 233, 0.15);
            color: #0369a1;
        }

        .condition-fair {
            background: rgba(249, 115, 22, 0.15);
            color: #c2410c;
        }

        .condition-poor {
            background: rgba(239, 68, 68, 0.15);
            color: #b91c1c;
        }

        .condition-default {
            background: var(--muted);
            color: var(--muted-foreground);
        }

        .status-active {
            background: rgba(34, 197, 94, 0.15);
            color: #15803d;
        }

        .status-sold {
            background: rgba(239, 68, 68, 0.15);
            color: #b91c1c;
        }

        .status-hidden {
            background: var(--muted);
            color: var(--muted-foreground);
        }

        .status-default {
            background: rgba(139, 92, 246, 0.15);
            color: #6d28d9;
        }

        .book-meta {
            font-size: 14px;
            color: var(--muted-foreground);
            margin-bottom: 12px;
            line-height: 1.8;
        }

        .book-meta strong {
            color: var(--foreground);
            font-weight: 600;
        }

        .book-price {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 12px;
        }

        .book-desc {
            color: var(--muted-foreground);
            line-height: 1.7;
            font-size: 14px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .inline-delete-form {
            display: inline;
            margin: 0;
            padding: 0;
        }

        .actions {
            margin-top: auto;
            padding-top: 14px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .empty {
            color: var(--muted-foreground);
            padding: 48px 24px;
            border: 2px dashed var(--border);
            border-radius: var(--radius);
            background: var(--secondary);
            text-align: center;
            line-height: 1.7;
        }

        .empty-icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 16px;
            color: var(--muted-foreground);
            opacity: 0.5;
        }

        .empty strong {
            display: block;
            color: var(--foreground);
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .empty p {
            margin: 0 0 20px;
            font-size: 15px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 16px;
                margin: 20px auto 40px;
            }

            .page-header h1 {
                font-size: 26px;
            }

            .card {
                padding: 18px;
            }

            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .book-item {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .book-image,
            .no-image {
                width: 100%;
                max-width: 200px;
                height: auto;
                aspect-ratio: 14 / 18;
            }

            .book-item h3 {
                font-size: 17px;
            }

            .actions {
                flex-direction: column;
                align-items: stretch;
            }

            .actions .btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 24px;
            }

            .card {
                padding: 16px;
            }

            .book-item {
                padding: 14px;
            }

            .toolbar-title {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/topbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>Mənim kitablarım</h1>
        <p>Paylaşdığın elanları burada idarə et.</p>
    </div>

    <div class="card">
        <div class="toolbar">
            <div class="toolbar-title">Əlavə etdiyim kitablar</div>
            <a href="<?= e(basePath('add_book.php')) ?>" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Yeni kitab əlavə et
            </a>
        </div>

        <?php if ($success): ?>
            <div class="alert-success">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <?= e($success) ?>
            </div>
        <?php endif; ?>

        <?php if (count($myBooks) > 0): ?>
            <div class="book-list">
                <?php foreach ($myBooks as $book): ?>
                    <?php
                    $conditionMeta = getConditionMeta($book["book_condition"] ?? "");
                    $statusMeta = getStatusMeta($book["status"] ?? "");
                    ?>
                    <div class="book-item">
                        <div class="book-image-wrap">
                            <?php if (!empty($book["image"])): ?>
                                <img class="book-image" src="<?= e(basePath('image.php?file=' . urlencode($book["image"]))) ?>" alt="<?= e($book["title"]) ?>">
                            <?php else: ?>
                                <div class="no-image">Şəkil yoxdur</div>
                            <?php endif; ?>
                        </div>

                        <div class="book-content">
                            <h3><?= e($book["title"]) ?></h3>

                            <div class="book-badges">
                                <span class="badge <?= e($conditionMeta['class']) ?>">
                                    <?= e($conditionMeta['text']) ?>
                                </span>
                                <span class="badge <?= e($statusMeta['class']) ?>">
                                    <?= e($statusMeta['text']) ?>
                                </span>
                            </div>

                            <div class="book-price"><?= e($book["price"]) ?> AZN</div>

                            <div class="book-meta">
                                <strong>Müəllif:</strong> <?= e($book["author"]) ?><br>
                                <strong>Janr:</strong> <?= e($book["genre"] ?: "Göstərilməyib") ?><br>
                                <strong>Dil:</strong> <?= e($book["language"] ?? "Göstərilməyib") ?>
                            </div>

                            <div class="book-desc">
                                <?= nl2br(e($book["description"] ?: "Təsvir əlavə edilməyib.")) ?>
                            </div>

                            <div class="actions">
                                <a href="<?= e(basePath('edit_book.php?id=' . (int)$book['id'])) ?>" class="btn btn-secondary">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                    </svg>
                                    Redaktə et
                                </a>

                                <form method="POST" action="<?= e(basePath('delete_book.php')) ?>" class="inline-delete-form">
                                    <input type="hidden" name="id" value="<?= (int)$book['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Bu kitabı silmək istədiyinizə əminsiniz?');">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                        Sil
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty">
                <svg class="empty-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                </svg>
                <strong>Hələ kitab əlavə etməmisiniz</strong>
                <p>İlk elanını əlavə et və kitablarını burada idarə et.</p>
                <a href="<?= e(basePath('add_book.php')) ?>" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Yeni kitab əlavə et
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>