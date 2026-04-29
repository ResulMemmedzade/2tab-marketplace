<?php

require_once "config.php";

ensureCsrfToken();

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
    <title>Qeydiyyat - 2tab</title>

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

        .register-wrapper {
            width: 100%;
            max-width: 460px;
        }

        .register-card {
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
            padding: 14px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .alert-error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
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

        .password-note {
            margin-top: 8px;
            font-size: 12px;
            color: var(--muted-foreground);
            line-height: 1.5;
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

            .register-card {
                padding: 28px 22px;
            }

            .brand-logo {
                width: 100px;
            }

            .brand p {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
<div class="register-wrapper">
    <div class="register-card">

        <div class="brand">
            <img src="<?= e(basePath('assets/icons/logo.png')) ?>?v=2" alt="2tab" class="brand-logo">
            <p>Yeni hesab yaradın və kitab bazarına qoşulun.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <span><?= e($success) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

            <div class="form-group">
                <label>Ad</label>
                <input type="text" name="name" value="<?= e($name) ?>" placeholder="Adınızı daxil edin" required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= e($email) ?>" placeholder="email@numune.az" required>
            </div>

            <div class="form-group">
                <label>Şifrə</label>
                <div class="password-wrap">
                    <input type="password" name="password" id="registerPassword" placeholder="Şifrənizi daxil edin" required>
                    <button type="button" class="toggle-password" data-target="registerPassword">Göstər</button>
                </div>
                <div class="password-note">
                    Minimum 8 simvol, 1 böyük hərf, 1 kiçik hərf və 1 rəqəm.
                </div>
            </div>

            <div class="form-group">
                <label>Şifrə təkrarı</label>
                <div class="password-wrap">
                    <input type="password" name="confirm_password" id="confirmPassword" placeholder="Şifrənizi təkrar daxil edin" required>
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