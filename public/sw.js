/* eslint-disable no-restricted-globals */
self.addEventListener("push", function (event) {
  var data = {};
  if (event.data) {
    try {
      data = event.data.json();
    } catch (_) {}
  }
  var title = data.title || "Mirai Lounge";
  var body = data.body || "Новый заказ";
  var url = data.url || "/orders/";
  event.waitUntil(
    self.registration.showNotification(title, {
      body: body,
      icon: "/favicon.png",
      badge: "/favicon.png",
      data: { url: url },
    })
  );
});

self.addEventListener("notificationclick", function (event) {
  event.notification.close();
  var url = (event.notification.data && event.notification.data.url) || "/orders/";
  event.waitUntil(
    clients.matchAll({ type: "window", includeUncontrolled: true }).then(function (clientList) {
      for (var i = 0; i < clientList.length; i++) {
        var c = clientList[i];
        if ("focus" in c) return c.focus();
      }
      if (clients.openWindow) return clients.openWindow(url);
    })
  );
});
