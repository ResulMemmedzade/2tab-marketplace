<?php

require_once "config.php";

ensureCsrfToken();

// artıq login olubsa redirect et
if (isLoggedIn()) {
    redirectTo("dashboard.php");
}

$error = "";
$success = "";

$name = "";
$email = "";

function isStrongPassword(string $password): bool
{
    return
        strlen($password) >= 8 &&
        preg_match('/[A-Z]/', $password) &&
        preg_match('/[a-z]/', $password) &&
        preg_match('/[0-9]/', $password);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    verifyCsrfToken($_POST["csrf_token"] ?? null);

    $name = trim($_POST["name"] ?? "");
    $email = strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    $role = "buyer";

    if ($name === "" || $email === "" || $password === "" || $confirm_password === "") {
        $error = "Bütün sahələri doldurun.";
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 50) {
        $error = "Ad 2-50 simvol aralığında olmalıdır.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email formatı düzgün deyil.";
    } elseif ($password !== $confirm_password) {
        $error = "Şifrələr eyni deyil.";
    } elseif (!isStrongPassword($password)) {
        $error = "Şifrə minimum 8 simvol, 1 böyük hərf, 1 kiçik hərf və 1 rəqəm içərməlidir.";
    } else {

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {

            appLog('register_attempt', 'Duplicate email registration attempt', [
                'email' => $email
            ]);

            $error = "Bu email artıq qeydiyyatdan keçib.";

        } else {

            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, password_hash, role)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$name, $email, $password_hash, $role]);

            session_regenerate_id(true);

            $_SESSION["user_id"] = (int)$pdo->lastInsertId();
            $_SESSION["name"] = $name;
            $_SESSION["email"] = $email;
            $_SESSION["role"] = $role;

            appLog('register_success', 'New user registered', [
                'user_id' => $_SESSION["user_id"],
                'email' => $email
            ]);

            redirectTo("dashboard.php");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2tab | Qeydiyyat</title>

    <style>
        /* DİZAYNA TOXUNMADIM */
        *{margin:0;padding:0;box-sizing:border-box;}
        body{min-height:100vh;font-family:Arial,sans-serif;background:linear-gradient(135deg,#eef2ff,#f8fafc);display:flex;align-items:center;justify-content:center;padding:20px;color:#1e293b;}
        .wrapper{width:100%;max-width:460px;}
        .card{background:#fff;border-radius:20px;padding:36px 30px;box-shadow:0 20px 50px rgba(15,23,42,.12);border:1px solid #e2e8f0;}
        .brand{text-align:center;margin-bottom:28px;}
        .brand-logo{width:140px;max-width:100%;height:auto;display:block;margin:0 auto 14px;}
        .brand p{font-size:14px;color:#64748b;line-height:1.5;}
        .alert{padding:12px 14px;border-radius:12px;margin-bottom:18px;font-size:14px;}
        .alert-error{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;}
        .alert-success{background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;}
        .form-group{margin-bottom:18px;}
        label{display:block;margin-bottom:8px;font-size:14px;font-weight:600;color:#334155;}
        input{width:100%;padding:14px 15px;border:1px solid #cbd5e1;border-radius:12px;font-size:15px;outline:none;background:#fff;}
        input:focus{border-color:#2563eb;box-shadow:0 0 0 4px rgba(37,99,235,.12);}
        .password-wrap{position:relative;}
.password-wrap input{padding-right:82px;}
.toggle-password{position:absolute;right:10px;top:50%;transform:translateY(-50%);border:none;background:#eef2ff;color:#2563eb;border-radius:9px;padding:7px 9px;font-size:12px;font-weight:700;cursor:pointer;}
        .btn{width:100%;border:none;border-radius:12px;padding:14px;font-size:15px;font-weight:700;cursor:pointer;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;box-shadow:0 14px 24px rgba(37,99,235,.22);}
        .footer-text{text-align:center;margin-top:22px;font-size:14px;color:#64748b;}
        .footer-text a{color:#2563eb;text-decoration:none;font-weight:600;}
        .footer-text a:hover{text-decoration:underline;}
        .password-note{margin-top:8px;font-size:12px;color:#64748b;}
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">

        <div class="brand">
            <img src="<?= e(basePath('assets/icons/logo.png')) ?>" class="brand-logo">
            <p>Yeni hesab yaradın və kitab bazarına qoşulun.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

            <div class="form-group">
                <label>Ad</label>
                <input type="text" name="name" value="<?= e($name) ?>" required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= e($email) ?>" required>
            </div>

            <div class="form-group">
                <label>Şifrə</label>
                <div class="password-wrap">
    <input type="password" name="password" id="registerPassword" required>
    <button type="button" class="toggle-password" data-target="registerPassword">Göstər</button>
</div>
                <div class="password-note">
                    Minimum 8 simvol, 1 böyük hərf, 1 kiçik hərf və 1 rəqəm.
                </div>
            </div>

            <div class="form-group">
    <label>Şifrə təkrarı</label>
    <div class="password-wrap">
        <input type="password" name="confirm_password" id="confirmPassword" required>
        <button type="button" class="toggle-password" data-target="confirmPassword">Göstər</button>
    </div>
</div>

            <button type="submit" class="btn">Qeydiyyatdan keç</button>
        </form>

        <div class="footer-text">
            Hesabınız var? <a href="<?= e(basePath('login.php')) ?>">Daxil ol</a>
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
