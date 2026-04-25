<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
.topbar-spacer {
    height: 80px;
}

/* TOPBAR */
.topbar {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    background: #fff;
    border-bottom: 1px solid #e5e7eb;
    z-index: 1000;
    transition: transform 0.25s ease;
}

.topbar-inner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 18px;
}

/* LOGO */
.brand img {
    height: 40px;
}

/* DESKTOP NAV */
.topbar-nav-desktop {
    display: flex;
    gap: 16px;
    align-items: center;
}

.topbar-nav-desktop a {
    text-decoration: none;
    color: #374151;
    font-weight: 600;
    padding: 8px 10px;
    border-radius: 8px;
    transition: 0.2s;
}

.topbar-nav-desktop a:hover {
    background: rgba(37, 99, 235, 0.08);
}

.message-link {
    display: inline-flex !important;
    align-items: center;
    gap: 6px;
}

.message-badge {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: 999px;
    background: #dc2626 !important;
    color: #ffffff !important;
    font-size: 12px;
    font-weight: 700;
    line-height: 1;
}

/* MOBILE NAV */
.topbar-nav-mobile {
    display: none;
}

/* MOBILE */
@media (max-width: 768px) {

    .topbar-spacer {
        height: 120px;
    }

    .topbar-inner {
        justify-content: center;
    }

    .topbar-nav-desktop {
        display: none;
    }

    .topbar-nav-mobile {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(0, 1fr));
        border-top: 1px solid #f1f5f9;
    }

    .topbar-nav-mobile a {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 10px 4px;
        text-decoration: none;
        color: #374151;
        font-size: 11px;
        font-weight: 600;
        position: relative;
        gap: 5px;
    }

    .topbar-nav-mobile a:hover {
        background: rgba(37, 99, 235, 0.08);
    }

    .topbar-nav-mobile img {
        width: 20px;
        height: 20px;
    }

    .topbar-nav-mobile .badge {
    position: absolute;
    top: 4px;
    right: 10px;
    background: #dc2626;
    color: #fff;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 999px;
}
}
</style>

<div class="topbar">
    <div class="topbar-inner">

        <!-- LOGO -->
        <a href="/index.php" class="brand">
            <img src="/logo.png" alt="2tab">
        </a>

        <!-- DESKTOP NAV -->
        <div class="topbar-nav-desktop">
            <a href="<?php echo $user_id ? '/dashboard.php' : '/login.php'; ?>">
                <?php echo $user_id ? 'Hesabım' : 'Giriş'; ?>
            </a>

            <a href="/books.php">Kitablar</a>

            <a href="/messages.php" class="message-link">
                Mesajlar
                <?php if ($unreadMessageCount > 0): ?>
                    <span class="message-badge"><?php echo $unreadMessageCount; ?></span>
                <?php endif; ?>
            </a>

            <a href="/contact.php">Əlaqə</a>

            <?php if ($is_admin): ?>
                <a href="/admin/dashboard.php">Admin</a>
            <?php endif; ?>
        </div>

    </div>

    <!-- MOBILE NAV -->
    <div class="topbar-nav-mobile">

        <a href="<?php echo $user_id ? '/dashboard.php' : '/login.php'; ?>">
            <img src="/assets/icons/account.png">
            <span><?php echo $user_id ? 'Hesabım' : 'Giriş'; ?></span>
        </a>

        <a href="/books.php">
            <img src="/assets/icons/books.png">
            <span>Kitablar</span>
        </a>

        <a href="/messages.php">
            <img src="/assets/icons/chat.png">
            <span>Mesajlar</span>

            <?php if ($unreadMessageCount > 0): ?>
                <span class="badge"><?php echo $unreadMessageCount; ?></span>
            <?php endif; ?>
        </a>

        <a href="/contact.php">
            <img src="/assets/icons/contact.png">
            <span>Əlaqə</span>
        </a>

        <?php if ($is_admin): ?>
            <a href="/admin/dashboard.php">
                <img src="/assets/icons/admin.png">
                <span>Admin</span>
            </a>
        <?php endif; ?>

    </div>
</div>

<div class="topbar-spacer"></div>

<script>
(function () {
    const topbar = document.querySelector('.topbar');
    if (!topbar) return;

    let lastScrollY = window.scrollY;

    window.addEventListener('scroll', function () {
        const currentScrollY = window.scrollY;

        if (currentScrollY <= 10) {
            topbar.style.transform = "translateY(0)";
            return;
        }

        if (currentScrollY > lastScrollY) {
            topbar.style.transform = "translateY(-100%)";
        } else {
            topbar.style.transform = "translateY(0)";
        }

        lastScrollY = currentScrollY;
    }, { passive: true });
})();
</script>