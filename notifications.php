<?php
require_once __DIR__ . '/config.php';

requireLogin();

$pageTitle = 'Bildirişlər';
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle) ?> - 2tab</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        body {
            margin: 0;
            background: #faf8f5;
            color: #2b2623;
        }

        .notifications-page {
            min-height: calc(100vh - 80px);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 28px 18px;
        }

        .notifications-card {
            width: 100%;
            max-width: 560px;
            background: #ffffff;
            border: 1px solid #eee6df;
            border-radius: 26px;
            padding: 34px 28px 28px;
            box-shadow: 0 14px 35px rgba(90, 58, 40, 0.08);
        }

        .notifications-question {
            margin: 0 0 30px;
            color: #3a2d27;
            font-size: 27px;
            font-weight: 800;
            line-height: 1.35;
            text-align: center;
        }

        .notifications-actions {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .notification-btn {
            width: 100%;
            min-height: 56px;
            border: none;
            border-radius: 18px;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.18s ease;
        }

        .notification-btn-primary {
            background: #c66f45;
            color: #ffffff;
            box-shadow: 0 8px 18px rgba(198, 111, 69, 0.22);
        }

        .notification-btn-primary:hover {
            background: #b9633e;
            transform: translateY(-1px);
        }

        .notification-btn-secondary {
            background: #f4eee9;
            color: #8a5a42;
        }

        .notification-btn-secondary:hover {
            background: #eee4dc;
        }

        .notification-btn-refresh {
            background: transparent;
            color: #8a817b;
            border: 1px solid #e7ddd6;
        }

        .notification-btn-refresh:hover {
            background: #fbf8f5;
        }

        @media (max-width: 600px) {
            .notifications-page {
                align-items: flex-start;
                padding-top: 36px;
            }

            .notifications-card {
                padding: 32px 22px 24px;
                border-radius: 24px;
            }

            .notifications-question {
                font-size: 24px;
                margin-bottom: 28px;
            }

            .notification-btn {
                min-height: 54px;
                border-radius: 17px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/topbar.php'; ?>

<main class="notifications-page">
    <section class="notifications-card">

        <h1 class="notifications-question">
            Sizə yazılan mesajlardan daha tez xəbərdar olmaq üçün bildirişlərə icazə vermək istəyirsinizmi?
        </h1>

        <div class="notifications-actions">
            <button type="button" id="enableNotificationsBtn" class="notification-btn notification-btn-primary">
                İcazə ver
            </button>

            <a href="messages.php" class="notification-btn notification-btn-secondary">
                İcazə vermə
            </a>

            <button type="button" onclick="window.location.reload()" class="notification-btn notification-btn-refresh">
                Yenilə
            </button>
        </div>

    </section>
</main>

<script>
const VAPID_PUBLIC_KEY = <?= json_encode(VAPID_PUBLIC_KEY) ?>;
const CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;

function urlBase64ToUint8Array(base64String) {
    const padding = "=".repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
        .replace(/-/g, "+")
        .replace(/_/g, "/");

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }

    return outputArray;
}

document.addEventListener("DOMContentLoaded", function () {
    const enableBtn = document.getElementById("enableNotificationsBtn");

    enableBtn.addEventListener("click", async function () {
        try {
            if (!("serviceWorker" in navigator)) {
                alert("Bu brauzer service worker dəstəkləmir.");
                return;
            }

            if (!("PushManager" in window)) {
                alert("Bu brauzer push bildirişləri dəstəkləmir.");
                return;
            }

            if (!("Notification" in window)) {
                alert("Bu brauzer bildirişləri dəstəkləmir.");
                return;
            }

            if (!VAPID_PUBLIC_KEY) {
                alert("VAPID public key tapılmadı.");
                return;
            }

            const permission = await Notification.requestPermission();

            if (permission !== "granted") {
                alert("Bildiriş icazəsi verilmədi.");
                return;
            }

            const registration = await navigator.serviceWorker.register("service-worker.js");

            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
            });

            const formData = new FormData();
            formData.append("csrf_token", CSRF_TOKEN);
            formData.append("subscription", JSON.stringify(subscription));

            const response = await fetch("save_push_subscription.php", {
                method: "POST",
                body: formData,
                credentials: "same-origin"
            });

            const result = await response.json();

            if (!result.success) {
                alert(result.message || "Bildiriş yadda saxlanmadı.");
                return;
            }

            alert("Bildirişlər aktiv edildi.");
            window.location.href = "messages.php";

        } catch (error) {
            console.error(error);
            alert("Bildiriş aktiv edilə bilmədi. Local HTTP-də bu normal ola bilər.");
        }
    });
});
</script>

</body>
</html>