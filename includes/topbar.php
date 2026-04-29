<?php

require_once __DIR__ . '/../config.php';

$user_id = $_SESSION["user_id"] ?? 0;
$is_admin = false;

if ($user_id) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $userRole = $stmt->fetchColumn();
    if ($userRole === 'admin') {
        $is_admin = true;
    }
}

$unreadMessageCount = 0;

if ($user_id) {
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
}
?>

<style>
.topbar {
    position: sticky;
    top: 0;
    z-index: 100;
    background: #ffffff;
    border-bottom: 1px solid #e5dfd6;
    padding: 12px 20px;
    box-shadow: 0 1px 3px rgba(45, 42, 38, 0.04), 0 1px 2px rgba(45, 42, 38, 0.06);
}

.topbar-inner {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.brand img {
    height: 50px;
    width: auto;
    display: block;
}

.nav-icons {
    display: flex;
    align-items: center;
    gap: 6px;
}

.nav-link {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 14px;
    border-radius: 12px;
    color: #7a756d;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    transition: all 0.2s ease;
}

.nav-link:hover {
    background: #f3efe9;
    color: #2d2a26;
}

.nav-link .badge-dot {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 10px;
    height: 10px;
    background: #c4704b;
    border: 2px solid #ffffff;
    border-radius: 50%;
}

.nav-link .msg-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: 999px;
    background: #c4704b;
    color: #ffffff;
    font-size: 11px;
    font-weight: 800;
    line-height: 1;
}

.nav-link-admin {
    background: #f3efe9;
    color: #c4704b;
    border: 1px solid #e5dfd6;
}

.nav-link-admin:hover {
    background: #e8e2d9;
    color: #b5613c;
}

@media (max-width: 480px) {
    .topbar { padding: 10px 16px; }
    .brand img { height: 40px; }
    .nav-link { padding: 8px 10px; font-size: 13px; }
}
</style>

<header class="topbar">
    <div class="topbar-inner">
        <a href="<?= e(basePath('index.php')) ?>" class="brand" title="2tab">
        <img src="<?= e(basePath('assets/icons/logo.png')) ?>?v=2" alt="2tab">
        </a>

        <nav class="nav-icons">
            <?php if ($is_admin): ?>
                <a href="<?= e(basePath('admin.php')) ?>" class="nav-link nav-link-admin">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                    </svg>
                    
                </a>
            <?php endif; ?>

            <a href="<?= e(basePath('books.php')) ?>" class="nav-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                </svg>
                
            </a>
            

            <?php if ($user_id): ?>
                <a href="<?= e(basePath('messages.php')) ?>" class="nav-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                    </svg>
                    
                    <?php if ($unreadMessageCount > 0): ?>
                        <span class="msg-badge"><?= $unreadMessageCount ?></span>
                    <?php endif; ?>
                </a>

                <a href="<?= e(basePath('dashboard.php')) ?>" class="nav-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    
                </a>
            <?php else: ?>
                <a href="<?= e(basePath('login.php')) ?>" class="nav-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Giriş
                </a>
            <?php endif; ?>
            <a href="<?= e(basePath('contact.php')) ?>" class="nav-link">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25v7.5A2.25 2.25 0 0118.75 18h-13.5A2.25 2.25 0 013 15.75v-7.5M3 8.25l9 6 9-6M3 6.75A2.25 2.25 0 015.25 4.5h13.5A2.25 2.25 0 0121 6.75v1.5l-9 6-9-6v-1.5z" />
    </svg>
    
</a>
        </nav>
    </div>
</header>