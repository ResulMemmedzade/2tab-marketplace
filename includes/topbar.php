<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

$user_id = $_SESSION["user_id"] ?? 0;
$is_admin = false;

if ($user_id) {
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $userRole = $stmt->fetchColumn();

        if ($userRole === 'admin') {
            $is_admin = true;
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}
$unreadMessageCount = 0;

if ($user_id) {
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
}
?>

<style>
.topbar-spacer {
    height: 84px;
}

.topbar {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1000;
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    padding: 10px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    box-sizing: border-box;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.topbar.topbar-hidden {
    transform: translateY(-100%);
}

.topbar.topbar-scrolled {
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
}

.brand {
    display: inline-flex;
    align-items: center;
    text-decoration: none;
    flex-shrink: 0;
}

.brand-logo {
    height: 42px;
    width: auto;
    display: block;
}

.topbar-nav {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
}

.topbar a {
    text-decoration: none;
    color: #334155;
    font-weight: 600;
    font-size: 15px;
    line-height: 1.2;
}

.topbar a:hover {
    color: #2563eb;
}

.topbar-nav a {
    padding: 10px 12px;
    border-radius: 10px;
    white-space: nowrap;
}

.topbar-nav a:hover {
    background: #f8fafc;
}

.message-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.message-badge {
    min-width: 22px;
    height: 22px;
    padding: 0 7px;
    border-radius: 999px;
    background: #dc2626;
    color: #ffffff;
    font-size: 12px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.mobile-nav-icon,
.mobile-nav-text {
    display: none;
}

@media (max-width: 768px) {
    .topbar-spacer {
        height: 122px;
    }

    .topbar {
        align-items: flex-start;
        flex-direction: column;
        padding: 10px 12px;
        gap: 10px;
    }

    .brand {
        width: 100%;
        justify-content: center;
    }

    .brand-logo {
        height: 30px;
        max-width: 100%;
        object-fit: contain;
    }

    .topbar-nav {
        width: 100%;
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
        align-items: stretch;
        justify-content: stretch;
    }

    .topbar-nav a {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 58px;
        padding: 8px 6px;
        font-size: 11px;
        text-align: center;
        white-space: normal;
        word-break: break-word;
        background: #f8fafc;
        color: #334155;
        border-radius: 12px;
        position: relative;
        gap: 4px;
    }

    .topbar-nav a:hover {
        background: #f1f5f9;
    }

    .mobile-nav-icon {
        display: block;
        width: 20px;
        height: 20px;
        object-fit: contain;
        flex-shrink: 0;
    }

    .mobile-nav-text {
        display: block;
        font-size: 11px;
        font-weight: 700;
        line-height: 1.1;
    }

    .desktop-link-text {
        display: none;
    }

    .message-link {
        gap: 4px;
    }

    .message-badge {
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        font-size: 10px;
        position: absolute;
        top: 4px;
        right: 4px;
    }
}

@media (max-width: 420px) {
    .topbar-spacer {
        height: 116px;
    }

    .topbar {
        padding: 8px 10px;
    }

    .brand-logo {
        height: 50px;
    }

    .topbar-nav {
        gap: 6px;
    }

    .topbar-nav a {
        min-height: 54px;
        padding: 6px 4px;
    }

    .mobile-nav-icon {
        width: 18px;
        height: 18px;
    }

    .mobile-nav-text {
        font-size: 10px;
    }
}
</style>

<div class="topbar" id="siteTopbar">
    <a href="/2tab/index.php" class="brand">
        <img src="/2tab/logo.png" alt="2tab loqosu" class="brand-logo">
    </a>

    <div class="topbar-nav">

        <!-- Hesab -->
        <a href="<?php echo $user_id ? '/2tab/dashboard.php' : '/2tab/login.php'; ?>">
            <img src="/2tab/assets/icons/account.png" alt="Hesabım" class="mobile-nav-icon">
            <span class="mobile-nav-text"><?php echo $user_id ? 'Hesabım' : 'Giriş'; ?></span>
            <span class="desktop-link-text"><?php echo $user_id ? 'Hesabım' : 'Giriş'; ?></span>
        </a>

        <!-- Kitablar -->
        <a href="/2tab/books.php">
            <img src="/2tab/assets/icons/books.png" alt="Kitablar" class="mobile-nav-icon">
            <span class="mobile-nav-text">Kitablar</span>
            <span class="desktop-link-text">Bütün kitablar</span>
        </a>

        <!-- Mesajlar -->
        <?php if ($user_id): ?>
            <a href="/2tab/messages.php" class="message-link">
                <img src="/2tab/assets/icons/chat.png" alt="Mesajlar" class="mobile-nav-icon">
                <span class="mobile-nav-text">Mesajlar</span>
                <span class="desktop-link-text">Mesajlar</span>

                <?php if ($unreadMessageCount > 0): ?>
                    <span class="message-badge"><?php echo $unreadMessageCount; ?></span>
                <?php endif; ?>
            </a>
        <?php else: ?>
            <a href="/2tab/login.php" class="message-link">
                <img src="/2tab/assets/icons/chat.png" alt="Mesajlar" class="mobile-nav-icon">
                <span class="mobile-nav-text">Mesajlar</span>
                <span class="desktop-link-text">Giriş</span>
            </a>
        <?php endif; ?>

        <!-- ADMIN PANEL -->
        <?php if ($is_admin): ?>
            <a href="/2tab/admin/dashboard.php">
                <img src="/2tab/assets/icons/admin.png" alt="Admin" class="mobile-nav-icon">
                <span class="mobile-nav-text">Admin</span>
                <span class="desktop-link-text">Admin panel</span>
            </a>
        <?php endif; ?>

    </div>
</div>

<div class="topbar-spacer"></div>

<script>
(function () {
    const topbar = document.getElementById('siteTopbar');
    if (!topbar) return;

    let lastScrollY = window.scrollY;

    window.addEventListener('scroll', function () {
        const currentScrollY = window.scrollY;

        if (currentScrollY <= 10) {
            topbar.classList.remove('topbar-hidden');
            topbar.classList.remove('topbar-scrolled');
        } else {
            topbar.classList.add('topbar-scrolled');

            if (currentScrollY > lastScrollY && currentScrollY > 80) {
                topbar.classList.add('topbar-hidden');
            } else {
                topbar.classList.remove('topbar-hidden');
            }
        }

        lastScrollY = currentScrollY;
    }, { passive: true });
})();
</script>