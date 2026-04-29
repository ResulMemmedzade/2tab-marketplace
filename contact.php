<?php
require_once "config.php";

ensureCsrfToken();

$user_id = currentUserId();
?>

<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Əlaqə - 2tab</title>
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

        .page {
            max-width: 520px;
            margin: 0 auto;
            padding: 24px 20px 48px;
        }

        .top-row {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: var(--radius-sm);
            background: var(--card);
            border: 1px solid var(--border);
            text-decoration: none;
            color: var(--foreground);
            box-shadow: var(--shadow-sm);
            transition: all 0.2s ease;
        }

        .back-btn:hover {
            background: var(--secondary);
            border-color: var(--primary);
            color: var(--primary);
        }

        .back-btn svg {
            width: 20px;
            height: 20px;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 28px 24px;
            box-shadow: var(--shadow);
        }

        h1 {
            margin: 0 0 10px;
            font-size: 28px;
            font-weight: 800;
            color: var(--foreground);
            text-align: center;
            letter-spacing: -0.5px;
        }

        .subtitle {
            margin: 0 0 24px;
            text-align: center;
            color: var(--muted-foreground);
            line-height: 1.6;
            font-size: 15px;
        }

        .contact-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .contact-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            width: 100%;
            padding: 16px;
            border-radius: var(--radius-sm);
            background: var(--secondary);
            border: 1px solid var(--border);
            text-decoration: none;
            color: var(--foreground);
            transition: all 0.2s ease;
            cursor: pointer;
            font-family: inherit;
            font-size: inherit;
            text-align: left;
        }

        .contact-item:hover {
            background: var(--secondary-hover);
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .contact-left {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-sm);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .icon svg {
            width: 22px;
            height: 22px;
        }

        .icon-instagram {
            background: linear-gradient(135deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
            color: white;
        }

        .icon-email {
            background: rgba(196, 112, 75, 0.15);
            color: var(--primary);
        }

        .icon-phone {
            background: rgba(107, 143, 113, 0.15);
            color: var(--accent);
        }

        .icon-chat {
            background: rgba(59, 130, 246, 0.15);
            color: #3b82f6;
        }

        .contact-text {
            min-width: 0;
        }

        .contact-title {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 3px;
            color: var(--foreground);
        }

        .contact-value {
            font-size: 14px;
            color: var(--muted-foreground);
            word-break: break-word;
        }

        .arrow {
            color: var(--muted-foreground);
            flex-shrink: 0;
            transition: transform 0.2s ease;
        }

        .arrow svg {
            width: 18px;
            height: 18px;
        }

        .contact-item:hover .arrow {
            transform: translateX(3px);
            color: var(--primary);
        }

        .admin-form {
            margin-top: 16px;
        }

        .admin-link {
            margin-top: 16px;
        }

        .note {
            margin-top: 20px;
            text-align: center;
            color: var(--muted-foreground);
            font-size: 13px;
            line-height: 1.6;
        }

        @media (max-width: 480px) {
            .page {
                padding: 16px 16px 40px;
            }

            .card {
                padding: 22px 18px;
            }

            h1 {
                font-size: 24px;
            }

            .subtitle {
                font-size: 14px;
            }

            .contact-item {
                padding: 14px;
            }

            .icon {
                width: 40px;
                height: 40px;
            }

            .icon svg {
                width: 20px;
                height: 20px;
            }

            .contact-title {
                font-size: 14px;
            }

            .contact-value {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>

<div class="page">
    <div class="top-row">
        <a href="javascript:history.back()" class="back-btn" aria-label="Geri">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
            </svg>
        </a>
    </div>

    <div class="card">
        <h1>Bizimlə əlaqə</h1>
        <p class="subtitle">
            Sualların, təkliflərin və ya hər hansı problemin varsa, aşağıdakı vasitələrlə bizimlə əlaqə saxlaya bilərsən.
        </p>

        <div class="contact-list">
            <a class="contact-item" href="https://instagram.com/2tab.store" target="_blank" rel="noopener noreferrer">
                <div class="contact-left">
                    <div class="icon icon-instagram">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                        </svg>
                    </div>
                    <div class="contact-text">
                        <div class="contact-title">Instagram</div>
                        <div class="contact-value">@2tab.store</div>
                    </div>
                </div>
                <div class="arrow">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </div>
            </a>

            <a class="contact-item" href="mailto:2tab.books@gmail.com">
                <div class="contact-left">
                    <div class="icon icon-email">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                        </svg>
                    </div>
                    <div class="contact-text">
                        <div class="contact-title">E-poçt</div>
                        <div class="contact-value">2tab.books@gmail.com</div>
                    </div>
                </div>
                <div class="arrow">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </div>
            </a>

            <a class="contact-item" href="tel:+994773007540">
                <div class="contact-left">
                    <div class="icon icon-phone">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                        </svg>
                    </div>
                    <div class="contact-text">
                        <div class="contact-title">Telefon</div>
                        <div class="contact-value">077 300 75 40</div>
                    </div>
                </div>
                <div class="arrow">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </div>
            </a>

            <?php if ($user_id): ?>
                <form method="POST" action="<?= e(basePath('start_chat.php')) ?>" class="admin-form">
                    <input type="hidden" name="user_id" value="3">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <button type="submit" class="contact-item">
                        <div class="contact-left">
                            <div class="icon icon-chat">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                                </svg>
                            </div>
                            <div class="contact-text">
                                <div class="contact-title">Adminlə əlaqə</div>
                                <div class="contact-value">Birbaşa mesaj göndər</div>
                            </div>
                        </div>
                        <div class="arrow">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </div>
                    </button>
                </form>
            <?php else: ?>
                <a href="<?= e(basePath('login.php?redirect=' . urlencode(basePath('contact.php')))) ?>" class="contact-item admin-link">
                    <div class="contact-left">
                        <div class="icon icon-chat">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                            </svg>
                        </div>
                        <div class="contact-text">
                            <div class="contact-title">Adminlə chat</div>
                            <div class="contact-value">Giriş et və yaz</div>
                        </div>
                    </div>
                    <div class="arrow">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </div>
                </a>
            <?php endif; ?>
        </div>

        <div class="note">
            Cavablar mümkün qədər qısa müddətdə veriləcək.
        </div>
    </div>
</div>

</body>
</html>