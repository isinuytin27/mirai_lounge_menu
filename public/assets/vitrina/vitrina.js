/* ============================================================
   MIRAI — Витрина кальянов (порт design_handoff_vitrina_kalyanov).
   Данные — наш /api/hookah-showcase (Postgres). Карусель кальянов + отдельный
   трек чаш (композит на «шахты»), расчёт цены = кальян + наценка чаши × шахты.
   Кнопка «Заказать» шлёт в /api/order-submit (по подписанному столу).
   ============================================================ */
(function () {
  "use strict";

  var ACCENT = "#f5007d";
  var state = { h: 0, b: 0, cw: 430, bowlPulse: false };
  var DATA = { hookahs: [], bowls: [], drinks: [] };
  var EL = {};
  var pulseT = null, orderBusy = false;

  function fmt(n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, " ") + " ₽"; }
  function el(tag, css, html) { var e = document.createElement(tag); if (css) e.style.cssText = css; if (html != null) e.innerHTML = html; return e; }

  // Геометрия кальяна + чаш на шахтах (порт geom() из дизайна).
  function geom(h, bowl, count) {
    var maxH = 300, avail = Math.max(220, state.cw - 48);
    var s = Math.min(maxH / h.ih, avail / h.iw);
    var W = h.iw * s, H = h.ih * s;
    var anchors = (h.anchors || []).slice(0, count);
    var pulse = state.bowlPulse;
    var bowls = anchors.map(function (a) {
      var bw = a.w * W * bowl.f;
      var bh = bw * (bowl.ih / bowl.iw);
      return {
        bg: bowl.image, w: Math.round(bw), h: Math.round(bh),
        left: Math.round(a.cx * W - bw / 2), bottom: Math.round(H - bh * 0.2), bh: bh,
        op: pulse ? 0 : 1, tf: pulse ? "scale(.88) translateY(10px)" : "scale(1) translateY(0)"
      };
    });
    var top = bowls.length ? Math.max.apply(null, bowls.map(function (b) { return b.bh * 0.8; })) : 0;
    return { bg: h.image, w: Math.round(W), h: Math.round(H + top), hh: Math.round(H), bowls: bowls };
  }

  function curBowl() { return DATA.bowls[Math.min(state.b, DATA.bowls.length - 1)] || DATA.bowls[0]; }
  function curHookah() { return DATA.hookahs[state.h] || DATA.hookahs[0]; }
  function idx(node) { return Math.max(0, Math.round(node.scrollLeft / Math.max(1, node.clientWidth))); }

  // Перерисовать один слайд кальяна (кальян + чаши текущей чаши).
  function slideInner(h) {
    var g = geom(h, curBowl(), (h.anchors || []).length);
    var inner = el("div",
      "position:relative;width:" + g.w + "px;height:" + g.h + "px;" +
      "background-image:url('" + g.bg + "');background-position:center bottom;" +
      "background-size:100% " + g.hh + "px;background-repeat:no-repeat;" +
      "filter:drop-shadow(0 26px 30px rgba(0,0,0,.6))");
    g.bowls.forEach(function (bw) {
      inner.appendChild(el("div",
        "position:absolute;left:" + bw.left + "px;bottom:" + bw.bottom + "px;" +
        "width:" + bw.w + "px;height:" + bw.h + "px;background-image:url('" + bw.bg + "');" +
        "background-size:100% 100%;background-repeat:no-repeat;" +
        "filter:drop-shadow(0 8px 12px rgba(0,0,0,.5));opacity:" + bw.op + ";" +
        "transform:" + bw.tf + ";transition:opacity .22s ease,transform .22s ease"));
    });
    return inner;
  }

  function buildHookahTrack() {
    EL.hookah.innerHTML = "";
    DATA.hookahs.forEach(function (h) {
      var slide = el("div", "flex:0 0 100%;scroll-snap-align:center;display:flex;align-items:flex-end;justify-content:center;padding-bottom:24px");
      slide.appendChild(slideInner(h));
      EL.hookah.appendChild(slide);
    });
  }

  // Перекомпоновать чаши на всех слайдах (без пересборки трека) — при смене чаши.
  function refreshBowls() {
    var slides = EL.hookah.children;
    for (var i = 0; i < slides.length; i++) {
      var h = DATA.hookahs[i];
      if (h) slides[i].replaceChild(slideInner(h), slides[i].firstChild);
    }
  }

  function bowlTo(i) {
    state.b = i; state.bowlPulse = true; refreshBowls();
    clearTimeout(pulseT);
    requestAnimationFrame(function () {
      pulseT = setTimeout(function () { state.bowlPulse = false; refreshBowls(); }, 20);
    });
    renderPrice();
  }

  function buildDots() {
    EL.dots.innerHTML = "";
    DATA.hookahs.forEach(function (h, i) {
      var on = i === state.h;
      var d = el("div", "width:" + (on ? 24 : 6) + "px;height:6px;border-radius:3px;" +
        "background:" + (on ? ACCENT : "var(--sumi-400)") + ";" +
        "box-shadow:" + (on ? "0 0 14px " + ACCENT : "none") + ";cursor:pointer;transition:all .18s ease");
      d.addEventListener("click", function () { goHookah(i); });
      EL.dots.appendChild(d);
    });
  }

  function renderPrice() {
    var cur = curHookah(), bowl = curBowl();
    var units = (cur.anchors || []).length || 1;
    var total = cur.price + bowl.extra * units;
    EL.name.textContent = cur.name;
    EL.total.textContent = fmt(total);
    EL.desc.textContent = cur.desc || "";
    EL.model.textContent = cur.model || "";
    EL.note.textContent = bowl.extra ? (units > 1 ? units + " × чаша " + fmt(bowl.extra) : "чаша " + fmt(bowl.extra)) : "чаша в комплекте";
    EL.time.textContent = cur.time || "";
    EL.bowlBadge.textContent = units > 1 ? "Чаша " + bowl.name + " × " + units : "Чаша " + bowl.name;
    buildDots();
  }

  function goHookah(i) {
    EL.hookah.scrollTo({ left: i * EL.hookah.clientWidth, behavior: "smooth" });
    state.h = i; renderPrice();
  }

  // Заказ: кальян + чаша (наценка) -> /api/order-submit (по столу из cookie).
  function order() {
    if (orderBusy) return;
    var cur = curHookah(), bowl = curBowl();
    var units = (cur.anchors || []).length || 1;
    orderBusy = true; EL.order.textContent = "Отправляем…"; EL.order.disabled = true;
    fetch("/api/order-submit", {
      method: "POST", headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        items: [{ id: cur.slug, qty: 1, bowl: bowl.slug, bowl_name: bowl.name, bowl_extra: bowl.extra, units: units }]
      })
    })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        orderBusy = false; EL.order.disabled = false;
        if (res.ok && res.j && res.j.ok) {
          EL.order.textContent = "✓ Добавлено";
          setTimeout(function () { EL.order.textContent = "Заказать"; }, 1800);
        } else {
          EL.order.textContent = (res.j && res.j.error === "no_table") ? "Отсканируйте QR стола" : "Не вышло — ещё раз";
          setTimeout(function () { EL.order.textContent = "Заказать"; }, 2200);
        }
      })
      .catch(function () {
        orderBusy = false; EL.order.disabled = false;
        EL.order.textContent = "Нет сети"; setTimeout(function () { EL.order.textContent = "Заказать"; }, 2000);
      });
  }

  function buildDrinks() {
    EL.drinks.innerHTML = "";
    DATA.drinks.forEach(function (dr) {
      var card = el("div", "flex:0 0 108px;padding:8px;background:rgba(244,241,234,.035);border:1px solid var(--glass-border);" +
        "clip-path:polygon(10px 0,100% 0,100% calc(100% - 10px),calc(100% - 10px) 100%,0 100%,0 10px);" +
        "display:flex;flex-direction:column;gap:6px;align-items:center");
      card.appendChild(el("div", "width:92px;height:92px;background-image:url('" + dr.image + "');background-position:center;background-size:cover;background-repeat:no-repeat"));
      card.appendChild(el("div", "font-family:var(--font-body);font-weight:700;font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:var(--washi-faint);text-align:center", dr.name));
      EL.drinks.appendChild(card);
    });
  }

  function measure() {
    if (EL.hookah.clientWidth && Math.abs(EL.hookah.clientWidth - state.cw) > 2) {
      state.cw = EL.hookah.clientWidth; buildHookahTrack();
    }
  }

  function boot(data) {
    DATA = { hookahs: data.hookahs || [], bowls: data.bowls || [], drinks: data.drinks || [] };
    EL.root = document.getElementById("vitrina");
    EL.hookah = document.getElementById("trk-hookah");
    EL.bowl = document.getElementById("trk-bowl");
    EL.dots = document.getElementById("vt-dots");
    EL.name = document.getElementById("vt-name");
    EL.total = document.getElementById("vt-total");
    EL.desc = document.getElementById("vt-desc");
    EL.model = document.getElementById("vt-model");
    EL.note = document.getElementById("vt-note");
    EL.time = document.getElementById("vt-time");
    EL.bowlBadge = document.getElementById("vt-bowlbadge");
    EL.drinks = document.getElementById("vt-drinks");
    EL.order = document.getElementById("vt-order");

    // Трек чаш — пустые слайды под каждую чашу (только для позиции скролла).
    EL.bowl.innerHTML = "";
    DATA.bowls.forEach(function () { EL.bowl.appendChild(el("div", "flex:0 0 100%;scroll-snap-align:center;height:100%")); });

    state.cw = EL.hookah.clientWidth || 430;
    buildHookahTrack();
    buildDrinks();
    renderPrice();

    var hT = null, bT = null;
    EL.hookah.addEventListener("scroll", function () {
      clearTimeout(hT);
      hT = setTimeout(function () { var i = idx(EL.hookah); if (i !== state.h) { state.h = i; renderPrice(); } }, 60);
    });
    EL.bowl.addEventListener("scroll", function () {
      clearTimeout(bT);
      bT = setTimeout(function () { var i = idx(EL.bowl); if (i !== state.b) bowlTo(i); }, 60);
    });
    EL.order.addEventListener("click", order);
    window.addEventListener("resize", measure);
  }

  function init() {
    fetch("/api/hookah-showcase")
      .then(function (r) { return r.json(); })
      .then(function (j) { if (j && j.ok) boot(j); })
      .catch(function () {});
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();
