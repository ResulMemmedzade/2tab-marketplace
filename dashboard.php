<?php
require_once "config.php";


requireLogin();

$user_id = (int)$_SESSION["user_id"];
$name = $_SESSION["name"] ?? "İstifadəçi";
$email = $_SESSION["email"] ?? "";

$unreadMessageCount = 0;

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
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2tab | Hesabım</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f8fafc;
            color: #1e293b;
        }

        .container {
            max-width: 1050px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .hero {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
            margin-bottom: 24px;
        }

        .hero h1 {
            margin: 0 0 10px;
            font-size: 32px;
            color: #0f172a;
        }

        .hero p {
            margin: 0;
            color: #64748b;
            line-height: 1.6;
        }

        .email-badge {
            display: inline-block;
            margin-top: 14px;
            padding: 8px 12px;
            border-radius: 999px;
            background: #eef2ff;
            color: #4338ca;
            font-size: 13px;
            font-weight: 700;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px;
        }

        .card {
            background: #fff;
            border-radius: 18px;
            padding: 22px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        }

        .card h3 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #0f172a;
        }

        .card p {
            margin: 0 0 16px;
            color: #64748b;
            line-height: 1.6;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            padding: 12px 16px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            border: none;
            cursor: pointer;
            min-height: 44px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
        }

        .btn-light {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        .btn-danger {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .logout-form {
            margin: 0;
        }

        @media (max-width: 700px) {
            .container {
                padding: 0 14px 28px;
            }

            .hero {
                padding: 22px 18px;
                border-radius: 16px;
            }

            .hero h1 {
                font-size: 26px;
            }

            .card {
                border-radius: 16px;
                padding: 18px;
            }

            .btn,
            .logout-form .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <div class="container">
        <div class="hero">
            <h1>Xoş gəldin, <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>!</h1>
            <p>Buradan hesabını idarə edə, kitablarını görə və favorilərinə baxa bilərsən.</p>

            <?php if ($email !== ""): ?>
                <div class="email-badge">
                    <?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="grid">
            <div class="card">
                <h3>Mənim kitablarım</h3>
                <p>Yeni kitab əlavə et, öz elanlarını redaktə və idarə et.</p>
                <a href="<?= e(basePath('mybooks.php')) ?>" class="btn btn-primary">Aç</a>
            </div>

            <div class="card">
                <h3>Favorilərim</h3>
                <p>Bəyəndiyin və sonra baxmaq istədiyin kitablar burada saxlanılır.</p>
                <a href="<?= e(basePath('favorites.php')) ?>" class="btn btn-light">Aç</a>
                </div>

            <div class="card">
                <h3>Kitablar</h3>
                <p>Platformadakı bütün aktiv kitab elanlarına bax və axtarış et.</p>
                <a href="<?= e(basePath('books.php')) ?>" class="btn btn-light">Bax</a>
                </div>

            <div class="card">
                <h3>Çıxış</h3>
                <p>Hesabından təhlükəsiz şəkildə çıxış et.</p>
                <form method="POST" action="<?= e(basePath('logout.php')) ?>" class="logout-form">                    <button type="submit" class="btn btn-danger">Çıxış et</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
