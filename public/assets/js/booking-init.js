/* Инициализация виджета брони на экране booking (замена restoplace).
   Схему зала берём из нашего API (/api/booking/hall) — источник правды Postgres.
   Отправка идёт через MiraiStore (booking-store.js → /api/booking). */
(function () {
  "use strict";

  function todayISO() {
    var d = new Date();
    return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
  }

  // Занятость сегодняшнего дня → статус стола (грубая подсветка; точную накладку
  // по времени решает сервер при отправке).
  function toScheme(data) {
    var busy = {};
    (data.occupancy || []).forEach(function (o) { if (o.tableId) busy[o.tableId] = true; });
    return {
      bg: "/assets/booking/scheme/zal.png",
      ratio: "16 / 11",
      tables: (data.tables || []).map(function (t) {
        return {
          id: t.id, label: t.label, x: t.x, y: t.y,
          seats: t.seats, shape: t.shape || "square",
          status: busy[t.id] ? "busy" : "free",
          zone: t.zone || ""
        };
      })
    };
  }

  function init(scheme) {
    if (!window.MiraiBooking) return;
    window.MiraiBooking.init({
      venueName: "MIRAI LOUNGE",
      fab: false,                       // у экрана свой CTA
      daysAhead: 14,
      openHour: 16, closeHour: 5, slotStepMin: 30,
      minGuests: 1, maxGuests: 20,
      scheme: scheme,
      // MiraiStore (booking-store.js) перехватывает отправку; endpoint — как fallback.
      transport: { mode: "endpoint", endpoint: "/api/booking" },
      onSuccess: function (p) { if (window.console) console.info("[booking] бронь оформлена", p); }
    });

    // CTA на экране открывает модалку виджета.
    document.addEventListener("click", function (e) {
      var t = e.target.closest ? e.target.closest("[data-booking-open]") : null;
      if (t) { e.preventDefault(); window.MiraiBooking.open(); }
    });
  }

  function boot() {
    fetch("/api/booking/hall?date=" + todayISO())
      .then(function (r) { return r.json(); })
      .then(function (j) { init(toScheme(j && j.ok ? j : {})); })
      .catch(function () { init(toScheme({})); }); // без сети — пустая схема, форма всё равно работает
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
