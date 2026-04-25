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

// artıq login olubsa redirect et
if (isLoggedIn()) {
    if (isAdmin()) {
        redirectTo("admin.php");
    }

    redirectTo("dashboard.php");
}

$error = "";
$loginValue = "";

// redirect param + session return_to
$redirect = trim($_POST["redirect"] ?? $_GET["redirect"] ?? "");

// POST logic
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
            SELECT id, name, email, role, password_hash
            FROM users
            WHERE email = ? OR name = ?
            LIMIT 1
        ");
        $stmt->execute([$loginValue, $loginValue]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user["password_hash"])) {

            clearRateLimit($pdo, $rateKey);
            unset($_SESSION['rate_limit_notice'][$rateKey]);

            session_regenerate_id(true);

            $_SESSION["user_id"] = (int)$user["id"];
            $_SESSION["name"] = $user["name"];
            $_SESSION["email"] = $user["email"];
            $_SESSION["role"] = $user["role"];

            appLog('login_success', 'User logged in successfully', [
                'user_id' => $user["id"],
                'email' => $user["email"]
            ]);

            // 🔥 PRIORITY: return_to → redirect param → fallback
            $target = pullReturnTo(basePath("dashboard.php"));

            if ($redirect && isSafeRedirect($redirect)) {
                $target = $redirect;
            }

            redirect($target);

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
    <title>2tab | Daxil ol</title>
    <style>
        /* SƏNİN DİZAYNINA TOXUNMADIM */
        *{margin:0;padding:0;box-sizing:border-box;}
        body{min-height:100vh;font-family:Arial,sans-serif;background:linear-gradient(135deg,#eef2ff,#f8fafc);display:flex;align-items:center;justify-content:center;padding:20px;color:#1e293b;}
        .login-wrapper{width:100%;max-width:420px;}
        .login-card{background:#fff;border-radius:20px;padding:36px 30px;box-shadow:0 20px 50px rgba(15,23,42,.12);border:1px solid #e2e8f0;}
        .brand{text-align:center;margin-bottom:28px;}
        .brand-logo{width:140px;max-width:100%;height:auto;display:block;margin:0 auto 14px;}
        .brand p{font-size:14px;color:#64748b;line-height:1.5;}
        .alert{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;padding:12px 14px;border-radius:12px;margin-bottom:18px;font-size:14px;}
        .form-group{margin-bottom:18px;}
        label{display:block;margin-bottom:8px;font-size:14px;font-weight:600;color:#334155;}
        input{width:100%;padding:14px 15px;border:1px solid #cbd5e1;border-radius:12px;font-size:15px;outline:none;transition:.2s ease;background:#fff;}
        input:focus{border-color:#2563eb;box-shadow:0 0 0 4px rgba(37,99,235,.12);}
        .password-wrap{position:relative;}
.password-wrap input{padding-right:82px;}
.toggle-password{position:absolute;right:10px;top:50%;transform:translateY(-50%);border:none;background:#eef2ff;color:#2563eb;border-radius:9px;padding:7px 9px;font-size:12px;font-weight:700;cursor:pointer;}
        .btn{width:100%;border:none;border-radius:12px;padding:14px;font-size:15px;font-weight:700;cursor:pointer;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;transition:transform .15s ease;box-shadow:0 14px 24px rgba(37,99,235,.22);}
        .btn:hover{transform:translateY(-1px);}
        .footer-text{text-align:center;margin-top:22px;font-size:14px;color:#64748b;}
        .footer-text a{color:#2563eb;text-decoration:none;font-weight:600;}
        .footer-text a:hover{text-decoration:underline;}
    </style>
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">

        <div class="brand">
            <img src="<?= e(basePath('assets/icons/logo.png')) ?>" class="brand-logo">
            <p>Hesabınıza daxil olun və kitab bazarından istifadə edin.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

            <div class="form-group">
                <label>Ad və ya Email</label>
                <input type="text" name="login" value="<?= e($loginValue) ?>" required>
            </div>

            <div class="form-group">
                <label>Şifrə</label>
                <div class="password-wrap">
    <input type="password" name="password" id="loginPassword" required>
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
