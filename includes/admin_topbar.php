<?php
require_once __DIR__ . '/../config.php';

?>

<style>
.topbar {
    background: #0f172a;
    color: white;
    padding: 18px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.topbar a {
    color: #cbd5f5;
    text-decoration: none;
    margin-left: 16px;
    font-weight: 600;
}

.topbar a:hover {
    color: white;
}

.brand {
    display: inline-flex;
    align-items: center;
    text-decoration: none;
}

.brand-logo {
    height: 56px;   /* 🔥 logonu böyütdük */
    width: auto;
    display: block;
}
</style>

<div class="topbar">
    <a href="<?= e(basePath('index.php')) ?>" class="brand">
        <img src="<?= e(basePath('logo.png')) ?>" alt="2tab loqosu" class="brand-logo">
    </a>

    <div>
        <a href="<?= e(basePath('dashboard.php')) ?>">Dashboard</a>
        <a href="<?= e(basePath('admin_books.php')) ?>">Kitablar</a>
        <a href="<?= e(basePath('admin_users.php')) ?>">İstifadəçilər</a>
        <a href="<?= e(basePath('logout.php')) ?>">Çıxış</a>
    </div>
</div>
