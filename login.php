<?php

require_once "config.php";
require_once "rate_limiter.php";

ensureCsrfToken();

if (!isset($_SESSION['rate_limit_notice'])) {
    $_SESSION['rate_limit_notice'] = [];
}

function isSafeRedirect(string $redirect): bool
{
    if ($redirect === "") {
        return false;
    }

    if (preg_match('/^https?:\/\//i', $redirect)) {
        return false;
    }

    return str_starts_with($redirect, "/");
}

if (isLoggedIn()) {
    if (isAdmin()) {
        redirectTo("admin.php");
    }

    redirectTo("dashboard.php");
}

$error = "";
$loginValue = "";

$redirect = trim($_POST["redirect"] ?? $_GET["redirect"] ?? "");

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    verifyCsrfToken($_POST["csrf_token"] ?? null);

    $loginValue = trim($_POST["login"] ?? "");
    $password = $_POST["password"] ?? "";

    $rateKey = rateLimitKey('login', $loginValue);
    $limitStatus = isRateLimited($pdo, $rateKey, 900);

    if ($limitStatus['limited']) {

        if (!isset($_SESSION['rate_limit_notice'][$rateKey])) {
            appLog('rate_limit', 'Login temporarily blocked', [
                'login_input' => $loginValue,
                'remaining_seconds' => $limitStatus['remaining_seconds']
            ]);

            $_SESSION['rate_limit_notice'][$rateKey] = true;
        }

        $remainingMinutes = ceil($limitStatus['remaining_seconds'] / 60);
        $error = "Çox sayda uğursuz cəhd oldu. Təxminən {$remainingMinutes} dəqiqə sonra yenidən yoxlayın.";

    } elseif ($loginValue === "" || $password === "") {

        $error = "Bütün sahələri doldurun.";

    } else {

        $stmt = $pdo->prepare("
            SELECT id, name, email, role, password_hash, status, ban_expires_at
            FROM users
            WHERE email = ? OR name = ?
            LIMIT 1
        ");
        $stmt->execute([$loginValue, $loginValue]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user["password_hash"])) {

            if (($user["status"] ?? "active") === "banned") {
                $error = "Hesabınız bloklanıb. Əgər bunun səhv olduğunu düşünürsünüzsə, adminlə əlaqə saxlayın.";
        
                appLog('login_blocked', 'Banned user tried to login', [
                    'user_id' => $user["id"],
                    'email' => $user["email"],
                    'status' => $user["status"],
                ]);
        
            } elseif (($user["status"] ?? "active") === "temp_banned") {
        
                if (!empty($user["ban_expires_at"]) && strtotime($user["ban_expires_at"]) > time()) {
                    $remainingMinutes = ceil((strtotime($user["ban_expires_at"]) - time()) / 60);
                    $error = "Hesabınız müvəqqəti bloklanıb. Təxminən {$remainingMinutes} dəqiqə sonra yenidən yoxlayın.";
        
                    appLog('login_blocked', 'Temp banned user tried to login', [
                        'user_id' => $user["id"],
                        'email' => $user["email"],
                        'ban_expires_at' => $user["ban_expires_at"],
                    ]);
                } else {
                    $activateStmt = $pdo->prepare("
                        UPDATE users
                        SET status = 'active',
                            ban_expires_at = NULL
                        WHERE id = ?
                    ");
                    $activateStmt->execute([(int)$user["id"]]);
        
                    clearRateLimit($pdo, $rateKey);
                    unset($_SESSION['rate_limit_notice'][$rateKey]);
        
                    loginUserToSession($user);
createRememberToken($pdo, (int)$user["id"]);
        
                    redirectTo("dashboard.php");
                }
        
            } else {
        
                clearRateLimit($pdo, $rateKey);
                unset($_SESSION['rate_limit_notice'][$rateKey]);
        
                loginUserToSession($user);
createRememberToken($pdo, (int)$user["id"]);
        
                appLog('login_success', 'User logged in successfully', [
                    'user_id' => $user["id"],
                    'email' => $user["email"]
                ]);
        
                $target = pullReturnTo(basePath("dashboard.php"));
        
                if ($redirect && isSafeRedirect($redirect)) {
                    $target = $redirect;
                }
        
                redirect($target);
            }

        } else {

            registerRateLimitFailure($pdo, $rateKey, 5, 900, 900);

            appLog('login_fail', 'Failed login attempt', [
                'login_input' => $loginValue
            ]);

            $error = "Ad/email və ya şifrə yanlışdır.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daxil ol - 2tab</title>
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
            --input: #faf8f5;
            --radius: 16px;
            --radius-sm: 12px;
            --shadow-sm: 0 1px 3px rgba(45, 42, 38, 0.04), 0 1px 2px rgba(45, 42, 38, 0.06);
            --shadow: 0 4px 20px rgba(45, 42, 38, 0.06), 0 2px 8px rgba(45, 42, 38, 0.04);
            --shadow-lg: 0 12px 40px rgba(45, 42, 38, 0.08), 0 4px 16px rgba(45, 42, 38, 0.04);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--background);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            color: var(--foreground);
            -webkit-font-smoothing: antialiased;
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
        }

        .login-card {
            background: var(--card);
            border-radius: var(--radius);
            padding: 36px 32px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
        }

        .brand {
            text-align: center;
            margin-bottom: 28px;
        }

        .brand-logo {
            width: 120px;
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto 16px;
        }

        .brand p {
            font-size: 15px;
            color: var(--muted-foreground);
            line-height: 1.6;
        }

        .alert {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 14px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .alert svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
            color: var(--foreground);
        }

        input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 15px;
            font-family: inherit;
            outline: none;
            transition: all 0.2s ease;
            background: var(--input);
            color: var(--foreground);
        }

        input::placeholder {
            color: var(--muted-foreground);
        }

        input:focus {
            border-color: var(--primary);
            background: var(--card);
            box-shadow: 0 0 0 3px rgba(196, 112, 75, 0.1);
        }

        .password-wrap {
            position: relative;
        }

        .password-wrap input {
            padding-right: 90px;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: var(--secondary);
            color: var(--primary);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s ease;
        }

        .toggle-password:hover {
            background: var(--secondary-hover);
        }

        .btn {
            width: 100%;
            border: none;
            border-radius: var(--radius-sm);
            padding: 14px 20px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            background: var(--primary);
            color: var(--primary-foreground);
            font-family: inherit;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
        }

        .btn:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }

        .footer-text {
            text-align: center;
            margin-top: 24px;
            font-size: 14px;
            color: var(--muted-foreground);
        }

        .footer-text a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
        }

        .footer-text a:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            body {
                padding: 16px;
            }

            .login-card {
                padding: 28px 22px;
            }

            .brand-logo {
                width: 200px;
            }

            .brand p {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">

        <div class="brand">
            <img src="<?= e(basePath('assets/icons/logo.png')) ?>?v=2" alt="2tab" class="brand-logo">
            <p>Hesabınıza daxil olun və kitab bazarından istifadə edin.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

            <div class="form-group">
                <label>Ad və ya Email</label>
                <input type="text" name="login" value="<?= e($loginValue) ?>" placeholder="email@numune.az" required>
            </div>

            <div class="form-group">
                <label>Şifrə</label>
                <div class="password-wrap">
                    <input type="password" name="password" id="loginPassword" placeholder="Şifrənizi daxil edin" required>
                    <button type="button" class="toggle-password" data-target="loginPassword">Göstər</button>
                </div>
            </div>

            <button type="submit" class="btn">Daxil ol</button>
        </form>

        <div class="footer-text">
            Hesabınız yoxdur? <a href="<?= e(basePath('register.php')) ?>">Qeydiyyatdan keç</a>
        </div>

    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".toggle-password").forEach(function (btn) {
        btn.addEventListener("click", function () {
            const input = document.getElementById(btn.dataset.target);
            const show = input.type === "password";
            input.type = show ? "text" : "password";
            btn.textContent = show ? "Gizlət" : "Göstər";
        });
    });
});
</script>
</body>
</html>