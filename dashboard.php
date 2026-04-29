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
    $stmt->execute([$user_id, $user_id, $user_id]);
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

    

    .container {
        max-width: 1200px;
        margin: 32px auto;
        padding: 0 20px;
    }

    .hero-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 40px;
        box-shadow: var(--shadow);
        margin-bottom: 24px;
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

    .hero-card h1 {
        font-size: 36px;
        font-weight: 800;
        color: var(--foreground);
        line-height: 1.1;
        letter-spacing: -1px;
        margin-bottom: 12px;
    }

    .hero-card p {
        color: var(--muted-foreground);
        font-size: 16px;
        line-height: 1.7;
        max-width: 560px;
    }

    .email-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 16px;
        padding: 8px 14px;
        border-radius: 999px;
        background: var(--secondary);
        color: var(--secondary-foreground);
        font-size: 13px;
        font-weight: 600;
        border: 1px solid var(--border);
    }

    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
    }

    .card {
        background: var(--card);
        border-radius: var(--radius);
        padding: 28px;
        border: 1px solid var(--border);
        box-shadow: var(--shadow-sm);
        display: flex;
        flex-direction: column;
        gap: 8px;
        transition: all 0.25s ease;
    }

    .card:hover {
        box-shadow: var(--shadow);
        transform: translateY(-2px);
    }

    .card h3 {
        font-size: 17px;
        font-weight: 700;
        color: var(--foreground);
        letter-spacing: -0.3px;
    }

    .card p {
        color: var(--muted-foreground);
        font-size: 14px;
        line-height: 1.6;
        flex: 1;
        margin-bottom: 8px;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-decoration: none;
        border: none;
        border-radius: var(--radius-sm);
        padding: 12px 20px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
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
    }

    .btn-secondary:hover {
        background: var(--secondary-hover);
    }

    .btn-danger {
        background: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }

    .btn-danger:hover {
        background: #fee2e2;
    }

    .logout-form {
        margin: 0;
    }

    @media (max-width: 768px) {
        .hero-card {
            padding: 28px 24px;
        }

        .hero-card h1 {
            font-size: 28px;
        }
    }

    @media (max-width: 480px) {
        .topbar {
            padding: 10px 16px;
        }

        .brand {
            font-size: 22px;
        }

        .container {
            padding: 0 16px;
            margin-top: 20px;
        }

        .hero-card {
            padding: 24px 20px;
        }

        .hero-card h1 {
            font-size: 24px;
        }

        .btn {
            width: 100%;
        }
    }
    </style>
</head>
<body>

<?php require_once "includes/topbar.php"; ?>

    <div class="container">
        <div class="hero-card">
            <div class="welcome-badge">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                </svg>
                Hesabım
            </div>
            <h1>Xoş gəldin, <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>!</h1>
            <p>Buradan hesabını idarə edə, kitablarını görə və favorilərinə baxa bilərsən.</p>

            <?php if ($email !== ""): ?>
                <div class="email-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                    </svg>
                    <?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>
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
                <a href="<?= e(basePath('favorites.php')) ?>" class="btn btn-secondary">Aç</a>
            </div>

            <div class="card">
                <h3>Kitablar</h3>
                <p>Platformadakı bütün aktiv kitab elanlarına bax və axtarış et.</p>
                <a href="<?= e(basePath('books.php')) ?>" class="btn btn-secondary">Bax</a>
            </div>

            <div class="card">
                <h3>Çıxış</h3>
                <p>Hesabından təhlükəsiz şəkildə çıxış et.</p>
                <form method="POST" action="<?= e(basePath('logout.php')) ?>" class="logout-form">
                    <button type="submit" class="btn btn-danger">Çıxış et</button>
                </form>
            </div>
        </div>
    </div>

</body>
</html>