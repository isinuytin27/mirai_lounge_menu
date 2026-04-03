(function () {
  "use strict";

  var root = document.querySelector("[data-vip-root]");
  if (!root) return;

  var metaEl = document.getElementById("vip-page-meta");
  var meta = {};
  try {
    meta = metaEl && metaEl.textContent ? JSON.parse(metaEl.textContent) : {};
  } catch (_) {}

  var slug = meta.slug || root.getAttribute("data-event-slug") || "";
  var token = meta.token || root.getAttribute("data-token") || "";

  function api(path, body) {
    return fetch(path, {
      method: "POST",
      headers: { "Content-Type": "application/json; charset=UTF-8" },
      credentials: "same-origin",
      body: JSON.stringify(body),
    }).then(function (r) {
      return r.json().then(function (j) {
        return { ok: r.ok, status: r.status, json: j };
      });
    });
  }

  function setLeft(n) {
    var el = document.querySelector("[data-vip-left]");
    if (el) el.textContent = String(n);
    root.querySelectorAll("[data-vip-free]").forEach(function (btn) {
      btn.disabled = n < 1;
    });
  }

  function prependLog(ts, name, paidGuest) {
    var ul = document.querySelector("[data-vip-log]");
    if (!ul) return;
    var li = document.createElement("li");
    li.textContent = ts + " — " + name + " ";
    var span = document.createElement("span");
    span.className = paidGuest ? "vip-pill" : "vip-pill vip-pill--free";
    span.textContent = paidGuest ? "за счёт гостя" : "бесплатно";
    li.appendChild(span);
    ul.insertBefore(li, ul.firstChild);
  }

  root.addEventListener("click", function (e) {
    var t = e.target;
    if (!(t instanceof HTMLElement)) return;
    var free = t.closest("[data-vip-free]");
    var paid = t.closest("[data-vip-paid]");
    if (!free && !paid) return;
    var paidByGuest = !!paid;
    var btn = free || paid;
    var pid = btn.getAttribute("data-product-id");
    var pname = btn.getAttribute("data-product-name") || pid;
    if (!pid) return;

    btn.disabled = true;
    api("/api/vip-consume.php", {
      event_slug: slug,
      token: token,
      product_id: pid,
      paid_by_guest: paidByGuest,
    })
      .then(function (res) {
        btn.disabled = false;
        if (!res.ok || !res.json || !res.json.ok) {
          var err = (res.json && res.json.error) || "error";
          alert(err === "limit_reached" ? "Бесплатный лимит исчерпан — используйте «За счёт гостя»." : err === "not_bar" ? "Позиция не относится к бару для этого мероприятия." : "Не удалось списать");
          return;
        }
        var j = res.json;
        setLeft(j.free_left != null ? j.free_left : 0);
        prependLog(new Date().toISOString().slice(0, 19).replace("T", " "), pname, paidByGuest);
      })
      .catch(function () {
        btn.disabled = false;
        alert("Ошибка сети");
      });
  });
})();
