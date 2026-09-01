/* ============================================================
   MIRAI — слой данных брони для нашего стека (замена store.js аддона).
   Виджет booking.js ждёт объект MiraiStore с методами:
     pullOccupancy(dateISO?) — прогреть кэш занятости (для подсказок tableConflict)
     addBooking(rec)         — создать бронь, вернуть Promise<{ok, error?}>
     addWaitlist(rec)        — записать в лист ожидания
   Источник правды — наш Postgres через /api/booking/*. Окончательное решение по
   накладкам принимает сервер (409 → {ok:false, error:'table_taken'}); localStorage —
   лишь клиентская подсказка. Без общего пароля/SQLite/Telegram.
   ============================================================ */
(function (global) {
  "use strict";

  var CACHE = "mirai_bookings_v1"; // формат, который читает tableConflict() в booking.js

  function todayISO() {
    var d = new Date();
    return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
  }

  function lget() {
    try { var v = JSON.parse(localStorage.getItem(CACHE)); return Array.isArray(v) ? v : []; }
    catch (e) { return []; }
  }
  function lset(list) { try { localStorage.setItem(CACHE, JSON.stringify(list)); } catch (e) {} }

  function api(path, opts) {
    opts = opts || {};
    opts.headers = Object.assign({ "Content-Type": "application/json" }, opts.headers || {});
    return fetch(path, opts);
  }

  var MiraiStore = {
    online: function () { return true; },

    /** Прогреть занятость на дату (по умолчанию — сегодня): пишем в кэш брони этого дня. */
    pullOccupancy: function (dateISO) {
      var date = dateISO || todayISO();
      return api("/api/booking/hall?date=" + encodeURIComponent(date))
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (!j || !j.ok) return;
          // Заменяем в кэше записи этого дня свежей занятостью.
          var rest = lget().filter(function (b) { return b.dateISO !== date; });
          (j.occupancy || []).forEach(function (o) {
            rest.push({ tableId: o.tableId, dateISO: o.dateISO, time: o.time, status: o.status });
          });
          lset(rest);
        })
        .catch(function () {});
    },

    /** Создать бронь. Возвращает {ok, error?} — как ждёт submit() в booking.js. */
    addBooking: function (rec) {
      return api("/api/booking", { method: "POST", body: JSON.stringify(rec) })
        .then(function (r) { return r.json().then(function (j) { return { http: r.status, j: j }; }); })
        .then(function (res) {
          if (res.http === 200 && res.j && res.j.ok) {
            // Обновляем клиентский кэш, чтобы подсказка о занятости сразу учла новую бронь.
            var list = lget();
            list.push({ tableId: rec.tableId, dateISO: rec.dateISO, time: rec.time, status: "confirmed" });
            lset(list);
            return { ok: true, booking: res.j.booking };
          }
          return { ok: false, error: (res.j && res.j.error) || "error" };
        })
        .catch(function () { return { ok: false, error: "network_error" }; });
    },

    addWaitlist: function (rec) {
      return api("/api/booking/waitlist", { method: "POST", body: JSON.stringify(rec) })
        .then(function (r) { return r.json(); })
        .catch(function () { return { ok: false, error: "network_error" }; });
    }
  };

  global.MiraiStore = MiraiStore;
})(window);
