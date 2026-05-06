<?php

require_once __DIR__ . "/config.php";
require_once __DIR__ . "/vendor/autoload.php";

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

function sendPushNotificationToUser(PDO $pdo, int $userId, string $title, string $body, string $url = "/"): void
{
    if ($userId <= 0) {
        return;
    }

    if (VAPID_PUBLIC_KEY === "" || VAPID_PRIVATE_KEY === "") {
        error_log("Push notification skipped: VAPID keys are missing.");
        return;
    }

    $stmt = $pdo->prepare("
        SELECT id, endpoint, p256dh, auth
        FROM push_subscriptions
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);

    $subscriptions = $stmt->fetchAll();

    if (!$subscriptions) {
        return;
    }

    $auth = [
        "VAPID" => [
            "subject" => "mailto:support@2tab.store",
            "publicKey" => VAPID_PUBLIC_KEY,
            "privateKey" => VAPID_PRIVATE_KEY,
        ],
    ];

    $webPush = new WebPush($auth);

    $payload = json_encode([
        "title" => $title,
        "body" => $body,
        "url" => $url,
    ], JSON_UNESCAPED_UNICODE);

    foreach ($subscriptions as $sub) {
        try {
            $subscription = Subscription::create([
                "endpoint" => $sub["endpoint"],
                "publicKey" => $sub["p256dh"],
                "authToken" => $sub["auth"],
                "contentEncoding" => "aes128gcm",
            ]);

            $webPush->queueNotification($subscription, $payload);

        } catch (Throwable $e) {
            error_log("Push subscription create error: " . $e->getMessage());
        }
    }

    foreach ($webPush->flush() as $report) {
        $endpoint = $report->getRequest()->getUri()->__toString();

        if (!$report->isSuccess()) {
            error_log("Push failed for endpoint {$endpoint}: " . $report->getReason());

            if ($report->isSubscriptionExpired()) {
                $deleteStmt = $pdo->prepare("
                    DELETE FROM push_subscriptions
                    WHERE endpoint = ?
                ");
                $deleteStmt->execute([$endpoint]);
            }
        }
    }
}