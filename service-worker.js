self.addEventListener("push", function (event) {
    let data = {
        title: "2tab",
        body: "Yeni bildirişiniz var.",
        url: "/"
    };

    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {}
    }

    const options = {
        body: data.body || "Yeni bildirişiniz var.",
        icon: "/assets/icons/notification-icon.png",
        data: {
            url: data.url || "/"
        }
    };

    event.waitUntil(
        self.registration.showNotification(data.title || "2tab", options)
    );
});

self.addEventListener("notificationclick", function (event) {
    event.notification.close();

    const url = event.notification.data && event.notification.data.url
        ? event.notification.data.url
        : "/";

    event.waitUntil(
        clients.openWindow(url)
    );
});