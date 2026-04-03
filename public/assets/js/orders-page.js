/**
 * Страница /orders/: копирование текста для кухни, Web Push (если есть VAPID в meta).
 */
(() => {
  const btn = document.getElementById("orders-copy-kitchen");
  const box = document.getElementById("orders-kitchen-text");
  if (btn && box) {
    btn.addEventListener("click", () => {
      const t = box.textContent || "";
      const done = () => {
        btn.textContent = "Скопировано";
        setTimeout(() => {
          btn.textContent = "Скопировать";
        }, 2000);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(t).then(done).catch(() => {});
      } else {
        const ta = document.createElement("textarea");
        ta.value = t;
        document.body.appendChild(ta);
        ta.select();
        try {
          document.execCommand("copy");
        } catch (_) {}
        document.body.removeChild(ta);
        done();
      }
    });
  }
})();

(() => {
  const meta = document.querySelector('meta[name="mirai-vapid-public"]');
  const vapid = meta ? meta.getAttribute("content") : "";
  const pushBtn = document.getElementById("orders-push-btn");
  const pushStatus = document.getElementById("orders-push-status");
  if (!pushBtn || !vapid) return;

  function urlBase64ToUint8Array(base64String) {
    const padding = "=".repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/");
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; i += 1) outputArray[i] = rawData.charCodeAt(i);
    return outputArray;
  }

  pushBtn.addEventListener("click", () => {
    if (!("serviceWorker" in navigator) || !("PushManager" in window)) {
      if (pushStatus) pushStatus.textContent = "Push не поддерживается в этом браузере.";
      return;
    }
    if (pushStatus) pushStatus.textContent = "";
    Notification.requestPermission().then((perm) => {
      if (perm !== "granted") {
        if (pushStatus) pushStatus.textContent = "Разрешение не выдано.";
        return;
      }
      return navigator.serviceWorker
        .register("/sw.js")
        .then((reg) =>
          reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapid),
          })
        )
        .then((sub) =>
          fetch("/api/push-subscribe.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify(sub.toJSON()),
          })
        )
        .then((res) => {
          if (res.ok) {
            if (pushStatus) pushStatus.textContent = "Готово.";
            pushBtn.disabled = true;
          } else if (pushStatus) pushStatus.textContent = "Ошибка сохранения подписки.";
        })
        .catch(() => {
          if (pushStatus) pushStatus.textContent = "Ошибка.";
        });
    });
  });
})();
