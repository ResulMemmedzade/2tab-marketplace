<?php

require_once "config.php";

requireAdmin();

$user_id = currentUserId();
$name = $_SESSION["name"] ?? "Admin";

$unreadMessageCount = 0;
$totalUsers = 0;
$totalBooks = 0;
$activeBooks = 0;
$soldBooks = 0;
$hiddenBooks = 0;
$totalFavorites = 0;
$totalConversations = 0;
$totalMessages = 0;
$latestBooks = [];
$latestUsers = [];

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

    $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalBooks = (int)$pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
    $activeBooks = (int)$pdo->query("SELECT COUNT(*) FROM books WHERE status = 'active'")->fetchColumn();
    $soldBooks = (int)$pdo->query("SELECT COUNT(*) FROM books WHERE status = 'sold'")->fetchColumn();
    $hiddenBooks = (int)$pdo->query("SELECT COUNT(*) FROM books WHERE status = 'hidden'")->fetchColumn();
    $totalFavorites = (int)$pdo->query("SELECT COUNT(*) FROM favorites")->fetchColumn();
    $totalConversations = (int)$pdo->query("SELECT COUNT(*) FROM conversations")->fetchColumn();
    $totalMessages = (int)$pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT books.*, users.name AS seller_name
        FROM books
        JOIN users ON books.seller_id = users.id
        ORDER BY books.created_at DESC
        LIMIT 8
    ");
    $stmt->execute();
    $latestBooks = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT id, name, email, role, created_at
        FROM users
        ORDER BY id DESC
        LIMIT 8
    ");
    $stmt->execute();
    $latestUsers = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log($e->getMessage());

    appLog('system_error', 'Admin dashboard DB error', [
        'user_id' => $user_id,
        'error' => $e->getMessage(),
    ]);
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2tab | Admin Panel</title>
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
        
        .admin-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 24px;
}
        .topbar {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 18px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .brand {
            font-size: 22px;
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

        .container {
            max-width: 1250px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-title {
            margin-bottom: 24px;
        }

        .page-title h1 {
            margin: 0 0 8px;
            font-size: 32px;
            color: #0f172a;
        }

        .page-title p {
            margin: 0;
            color: #64748b;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }
        
        .stat-card {
    transition: 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.1);
}
        .stat-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        }

        .stat-card .label {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 30px;
            font-weight: 700;
            color: #0f172a;
        }

        .grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 24px;
        }

        .card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        }

        .card h2 {
            margin: 0 0 18px;
            font-size: 22px;
            color: #0f172a;
        }

        .list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .item {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 14px;
            background: #f8fafc;
        }
        
        .item {
    transition: 0.15s ease;
}

.item:hover {
    background: #f1f5f9;
    transform: translateY(-2px);
} 
        .item-title {
            font-weight: 700;
            margin-bottom: 8px;
            color: #0f172a;
        }

        .item-meta {
            color: #475569;
            font-size: 14px;
            line-height: 1.7;
        }

        .badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    margin-top: 8px;
}

/* 🔥 yeni */
.badge-admin {
    background: #fee2e2;
    color: #b91c1c;
}

.badge-buyer {
    background: #dbeafe;
    color: #1d4ed8;
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
    box-shadow: 0 10px 20px rgba(37, 99, 235, 0.2);
}

.btn-primary:hover {
    transform: translateY(-1px);
}

        .btn-light {
            background: #e2e8f0;
            color: #1e293b;
        }

        @media (max-width: 950px) {
            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/admin_topbar.php'; ?>

    <div class="container">
        <div class="page-title">
            <h1>Admin panel</h1>
            <p>Xoş gəldin, <?= e($name) ?>. Sistemin ümumi vəziyyətini buradan idarə edə bilərsən.</p>
        </div>
        <div class="admin-actions">
    <a href="<?= e(basePath('admin_books.php')) ?>" class="btn btn-primary">Kitabları idarə et</a>
    <a href="<?= e(basePath('admin_users.php')) ?>" class="btn btn-light">İstifadəçiləri idarə et</a>
</div>
        

        <div class="stats-grid">
            <div class="stat-card">
                <div class="label">Ümumi istifadəçi</div>
                <div class="value"><?php echo $totalUsers; ?></div>
            </div>

            <div class="stat-card">
                <div class="label">Ümumi kitab</div>
                <div class="value"><?php echo $totalBooks; ?></div>
            </div>

            <div class="stat-card">
                <div class="label">Aktiv kitab</div>
                <div class="value"><?php echo $activeBooks; ?></div>
            </div>

            <div class="stat-card">
                <div class="label">Satılmış kitab</div>
                <div class="value"><?php echo $soldBooks; ?></div>
            </div>

            <div class="stat-card">
                <div class="label">Gizli kitab</div>
                <div class="value"><?php echo $hiddenBooks; ?></div>
            </div>

            <div class="stat-card">
                <div class="label">Favori sayı</div>
                <div class="value"><?php echo $totalFavorites; ?></div>
            </div>

            <div class="stat-card">
                <div class="label">Söhbət sayı</div>
                <div class="value"><?php echo $totalConversations; ?></div>
            </div>

            <div class="stat-card">
                <div class="label">Mesaj sayı</div>
                <div class="value"><?php echo $totalMessages; ?></div>
            </div>
        </div>

        <div class="grid">
            <div class="card">
                <h2>Son əlavə olunan kitablar</h2>

                <div class="list">
                    <?php foreach ($latestBooks as $book): ?>
                        <div class="item-title"><?= e($book["title"]) ?></div>
<div class="item-meta">
    <strong>Satıcı:</strong> <?= e($book["seller_name"]) ?><br>
    <strong>Müəllif:</strong> <?= e($book["author"]) ?><br>
    <strong>Qiymət:</strong> <?= e($book["price"]) ?> AZN<br>
    <strong>Status:</strong> <?= e($book["status"]) ?>
</div>
                    <?php endforeach; ?>
                </div>

                
            </div>

            <div class="card">
                <h2>Son qeydiyyatdan keçən istifadəçilər</h2>

                <div class="list">
                    <?php foreach ($latestUsers as $user): ?>
                        <div class="item">
    <div class="item-title"><?= e($user["name"]) ?></div>
    <div class="item-meta">
        <strong>Email:</strong> <?= e($user["email"]) ?><br>
        <strong>Rol:</strong> <?= e($user["role"]) ?><br>
        <strong>Tarix:</strong> <?= e($user["created_at"]) ?>
    </div>
    <span class="badge <?= $user['role'] === 'admin' ? 'badge-admin' : 'badge-buyer'; ?>">
        <?= e($user["role"]) ?>
    </span>
</div>
                    <?php endforeach; ?>
                </div>

                
            </div>
        </div>
    </div>
</body>
</html>
