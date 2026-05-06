<?php

require_once "config.php";

requireLogin();

header("Content-Type: application/json; charset=utf-8");

function responseJson($success, $message, $extra = [])
{
    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message
    ], $extra));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responseJson(false, "Yanlış sorğu.");
}

try {
    verifyCsrfToken($_POST["csrf_token"] ?? null);

    $currentUserId = currentUserId();

    if ($currentUserId === null) {
        responseJson(false, "Giriş edilməyib.");
    }

    $payload = $_POST["subscription"] ?? "";

    if (!is_string($payload) || trim($payload) === "") {
        responseJson(false, "Subscription boşdur.");
    }

    $subscription = json_decode($payload, true);

    if (!is_array($subscription)) {
        responseJson(false, "Subscription formatı yanlışdır.");
    }

    $endpoint = $subscription["endpoint"] ?? "";
    $p256dh = $subscription["keys"]["p256dh"] ?? "";
    $auth = $subscription["keys"]["auth"] ?? "";

    if (!is_string($endpoint) || $endpoint === "" || !is_string($p256dh) || $p256dh === "" || !is_string($auth) || $auth === "") {
        responseJson(false, "Subscription məlumatları tam deyil.");
    }

    $stmt = $pdo->prepare("
        INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            p256dh = VALUES(p256dh),
            auth = VALUES(auth),
            updated_at = CURRENT_TIMESTAMP
    ");

    $stmt->execute([
        $currentUserId,
        $endpoint,
        $p256dh,
        $auth
    ]);

    responseJson(true, "Subscription yadda saxlanıldı.");

} catch (Throwable $e) {
    error_log($e->getMessage());
    responseJson(false, "Server xətası.");
}