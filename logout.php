<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Strict'
]);

session_start();

// Session məlumatlarını sil
$_SESSION = [];

// Cookie-ni də sil (çox vacib)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Session-u tam məhv et
session_destroy();

// əlavə olaraq ID regenerate (extra safety)
session_start();
session_regenerate_id(true);
session_destroy();

header("Location: /login.php");
exit;
