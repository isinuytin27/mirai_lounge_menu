/* ============================================================
   MIRAI — Виджет бронирования  ·  booking.js
   Самодостаточный, без зависимостей. Подключается одним тегом.
   Инициализация:  MiraiBooking.init({ ...config })
   ============================================================ */
(function (global) {
  "use strict";

  // Откуда загружен ЭТОТ файл — картинки в шаблоне (логотип, облака) шьются
  // отсюда, а не от адреса страницы: если виджет встроен на чужом сайте,
  // относительный "assets/..." резолвился бы в несуществующий путь хоста.
  // Раздаётся из нашего стека по фиксированному пути — собственные картинки
  // виджета (логотип/облака/схема) шьются отсюда.
  var ASSET_BASE = "/assets/booking/";

  /* ---------------------------------------------------------
     КОНФИГ ПО УМОЛЧАНИЮ — переопредели через MiraiBooking.init()
     --------------------------------------------------------- */
  var DEFAULTS = {
    venueName: "MIRAI LOUNGE",            // название заведения
    jp: "ミライ",                          // катакана-акцент
    venuePhoto: "",                        // фото заведения (для боковой панели), напр. "assets/photos/venue.jpg"
    fab: true,                             // плавающая кнопка «Забронировать» (false — для 3D-карты)
    // --- расписание ---
    daysAhead: 14,                         // на сколько дней вперёд доступна бронь
    openHour: 16,                          // часы работы: открытие (16:00)
    closeHour: 5,                          // закрытие после полуночи (05:00 следующего дня)
    slotStepMin: 30,                       // шаг временных слотов, мин
    minLeadMinutes: 120,                   // нельзя бронировать ближе чем за N минут до слота
    bookingDurationMin: 120,               // длительность одной брони — для проверки пересечений на столе
    minGuests: 1,
    maxGuests: 20,
    weekdayOpen: { 1:16,2:16,3:16,4:16,5:16,6:16,0:16 }, // открытие по дням недели (16:00)
    // --- ЕДИНАЯ СХЕМА ЗАЛА (без деления на зоны) ---
    // tables: { id, label, x, y, seats, shape, status, zone? }
    //   x,y — проценты (0..100), центр позиции на плане; shape: "round"|"square"
    //   status: "free" | "busy"   ·  zone — подпись зоны (необяз., для информации)
    //   bg — фон-план зала (assets/scheme/...), ratio — пропорции карты "W / H"
    //   Расстановка соответствует «Плану расстановки мебели» MIRAI (позиции 1–8).
    scheme: {
      bg: "",
      ratio: "16 / 11",
      tables: [
        { id:"8", label:"8", x:45, y:16, seats:6, shape:"round",  status:"free", zone:"Food & Hookah" },
        { id:"1", label:"1", x:85, y:20, seats:6, shape:"square", status:"free", zone:"Lounge · Reception" },
        { id:"7", label:"7", x:45, y:46, seats:6, shape:"round",  status:"free", zone:"Food & Hookah" },
        { id:"2", label:"2", x:85, y:52, seats:6, shape:"square", status:"free", zone:"Lounge · Reception" },
        { id:"6", label:"6", x:15, y:50, seats:4, shape:"square", status:"free", zone:"VIP PS 1" },
        { id:"5", label:"5", x:18, y:82, seats:4, shape:"square", status:"free", zone:"VIP PS 1" },
        { id:"4", label:"4", x:48, y:82, seats:4, shape:"square", status:"free", zone:"Food & Hookah" },
        { id:"3", label:"3", x:82, y:82, seats:4, shape:"square", status:"free", zone:"VIP PS 2" }
      ]
    },
    // --- ОТПРАВКА БРОНИ ---
    transport: {
      mode: "telegram",          // "telegram" | "email" | "endpoint" | "demo"
      telegram: { botToken: "", chatId: "" },           // ← заполни
      email:    { endpoint: "" }, // POST-эндпоинт, который шлёт письмо (напр. formspree/свой бэк)
      endpoint: "",               // произвольный POST-URL для mode:"endpoint"
    },
    onSuccess: null               // колбэк(data) после успешной брони
  };

  var CFG = {};
  var EL = {};                    // ссылки на DOM
  var STATE = {
    step: 0,                      // 0..3
    guests: 2,
    date: null,                   // Date
    time: null,                   // "20:00"
    table: null,                  // id позиции (из 2D-схемы)
    preset: null,                 // { table:{id,label,zone,desc} } | { zone } — из 3D-карты
    name: "", phone: "", email: "", comment: ""
  };
  // Шаги задаются ключами; набор зависит от того, выбран ли стол заранее (на 3D-карте).
  var STEP_TITLE = { date: "Дата и гости", position: "Выбор места", contacts: "Контакты", done: "Готово" };
  var FLOW = ["date", "position", "contacts", "done"];   // пересобирается в open()
  var DOW = ["Вс","Пн","Вт","Ср","Чт","Пт","Сб"];
  var MON = ["янв","фев","мар","апр","мая","июн","июл","авг","сен","окт","ноя","дек"];

  /* ---------- ICONS ---------- */
  var I = {
    cal:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M3 9h18M8 2.5v4M16 2.5v4"/></svg>',
    close: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>',
    arrow: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
    back:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>',
    info:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/></svg>',
    check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5l5 5L20 6.5"/></svg>'
  };

  /* =========================================================
     PUBLIC API
     ========================================================= */
  var MiraiBooking = {
    init: function (opts) {
      CFG = deepMerge(clone(DEFAULTS), opts || {});
      STATE.guests = clamp(2, CFG.minGuests, CFG.maxGuests);
      buildDOM();
      bind();
      // прогреть кэш занятости столов для клиентской проверки в tableConflict()
      if (global.MiraiStore && typeof global.MiraiStore.pullOccupancy === "function") global.MiraiStore.pullOccupancy();
      return this;
    },
    open: open,
    close: close
  };

  /* =========================================================
     DOM BUILD
     ========================================================= */
  function buildDOM() {
    var root = document.createElement("div");
    root.className = "mb-root";
    root.innerHTML =
      (CFG.fab === false ? "" : fab()) +
      '<div class="mb-overlay" data-mb="overlay">' +
        '<div class="mb-modal" role="dialog" aria-modal="true" aria-label="Бронирование">' +
          aside() +
          '<div class="mb-main">' +
            '<div class="mb-head">' +
              '<div><h3 class="mb-head__t" data-mb="headTitle">Дата и гости</h3>' +
              '<div class="mb-head__c" data-mb="headCount">Шаг 1 из 4</div></div>' +
              '<button class="mb-close" data-mb="close" aria-label="Закрыть">' + I.close + '</button>' +
            '</div>' +
            '<div class="mb-body" data-mb="body"></div>' +
            '<div class="mb-foot" data-mb="foot">' +
              '<button class="mb-btn mb-btn--ghost" data-mb="back" style="visibility:hidden">' + I.back + '<span>Назад</span></button>' +
              '<div class="mb-foot__hint" data-mb="hint"></div>' +
              '<button class="mb-btn" data-mb="next"><span class="mb-spinner"></span><span class="mb-btn__txt">Далее</span>' + I.arrow + '</button>' +
            '</div>' +
          '</div>' +
        '</div>' +
      '</div>';
    document.body.appendChild(root);

    EL.root = root;
    EL.overlay = q(root, "overlay");
    EL.body = q(root, "body");
    EL.headTitle = q(root, "headTitle");
    EL.headCount = q(root, "headCount");
    EL.back = q(root, "back");
    EL.next = q(root, "next");
    EL.hint = q(root, "hint");
    EL.stepsUl = q(root, "stepsUl");
    renderStep();
  }

  // Перестраивает список шагов в боковой панели под текущий FLOW.
  function renderSteps() {
    EL.stepsUl.innerHTML = FLOW.map(function (k, i) {
      return '<li data-step="' + i + '"><span class="mb-step-num">' + (i + 1) + '</span>' + STEP_TITLE[k] + '</li>';
    }).join("");
    EL.steps = EL.stepsUl.querySelectorAll("li");
  }

  function fab() {
    return '<button class="mb-fab" data-mb="open">' + I.cal + '<span>Забронировать</span></button>';
  }

  // Абсолютный URL / data: — не трогаем; относительный — считаем от ASSET_BASE
  // (собственные картинки виджета), не от адреса страницы, куда он встроен.
  function assetUrl(rel) {
    return /^([a-z]+:|\/)/i.test(rel) ? rel : ASSET_BASE + rel;
  }

  function aside() {
    var steps = FLOW.map(function (k, i) {
      return '<li data-step="' + i + '"><span class="mb-step-num">' + (i + 1) + '</span>' + STEP_TITLE[k] + '</li>';
    }).join("");
    var photo = CFG.venuePhoto
      ? '<img class="mb-aside__photo" src="' + esc(assetUrl(CFG.venuePhoto)) + '" alt="' + esc(CFG.venueName) + '">' : "";
    return '<aside class="mb-aside">' +
      '<img class="mb-aside__logo" src="' + assetUrl("brand/mirai-logo.svg") + '" alt="MIRAI">' +
      '<div class="mb-aside__jp">' + esc(CFG.jp) + ' · 未来</div>' +
      '<h2 class="mb-aside__title">Бронь<br>стола</h2>' +
      photo +
      '<p class="mb-aside__sub">Выбери дату, зал и оставь контакты. Подтверждение — в течение пары минут.</p>' +
      '<ul class="mb-steps" data-mb="stepsUl">' + steps + '</ul>' +
      '<img class="mb-aside__clouds" src="' + assetUrl("brand/mirai-clouds.svg") + '" alt="">' +
    '</aside>';
  }

  /* =========================================================
     RENDER PER STEP
     ========================================================= */
  function curKey() { return FLOW[STATE.step]; }

  function renderStep() {
    renderSteps();
    var key = curKey();
    EL.headTitle.textContent = STEP_TITLE[key];
    EL.headCount.textContent = "Шаг " + (STATE.step + 1) + " из " + FLOW.length;
    // aside stepper state
    each(EL.steps, function (li, i) {
      li.classList.toggle("is-active", i === STATE.step);
      li.classList.toggle("is-done", i < STATE.step);
    });
    // back visibility — скрыт на первом шаге и на экране «Готово»
    EL.back.style.visibility = (STATE.step === 0 || key === "done") ? "hidden" : "visible";

    if (key === "date") renderDateGuests();
    else if (key === "position") renderPositions();
    else if (key === "contacts") renderContacts();
    else if (key === "done") renderSuccess();

    updateNav();
  }

  function renderDateGuests() {
    var dates = buildDates();
    var dateChips = dates.map(function (d) {
      var sel = STATE.date && sameDay(d, STATE.date) ? " is-sel" : "";
      return '<div class="mb-chip' + sel + '" data-date="' + d.toISOString() + '">' +
        '<div class="mb-chip__dow">' + DOW[d.getDay()] + '</div>' +
        '<div class="mb-chip__day">' + d.getDate() + '</div>' +
        '<div class="mb-chip__mon">' + MON[d.getMonth()] + '</div>' +
      '</div>';
    }).join("");

    var times = STATE.date ? buildTimes(STATE.date) : [];
    var timeBlock = STATE.date
      ? '<div class="mb-field"><label class="mb-label">Время <span class="mb-req">*</span></label>' +
        '<div class="mb-chips mb-chips--times">' + times.map(function (t) {
          var sel = STATE.time === t.v ? " is-sel" : "";
          var dis = t.disabled ? " is-disabled" : "";
          return '<div class="mb-chip' + sel + dis + '" data-time="' + t.v + '"><span class="mb-chip__time">' + t.v + '</span></div>';
        }).join("") + '</div></div>'
      : '<div class="mb-field"><div class="mb-note">' + I.info + '<span>Сначала выбери дату — покажем свободные слоты.</span></div></div>';

    var banner = (STATE.preset && placeLabel())
      ? '<div class="mb-field"><div class="mb-note">' + I.info + '<span>Бронируешь: <b>' + esc(placeLabel()) + '</b></span></div></div>'
      : '';

    var gMax = guestsCap();
    var capNote = (presetSeats() != null && presetSeats() < CFG.maxGuests)
      ? '<div class="mb-counter__hint">Максимум ' + presetSeats() + ' гост. за этим столом</div>' : '';

    EL.body.innerHTML =
      '<div class="mb-screen is-active">' +
        banner +
        '<div class="mb-field">' +
          '<label class="mb-label">Гостей <span class="mb-req">*</span></label>' +
          '<div class="mb-counter">' +
            '<button data-g="-1" ' + (STATE.guests <= CFG.minGuests ? "disabled" : "") + '>−</button>' +
            '<div class="mb-counter__val" data-mb="gval">' + STATE.guests + '</div>' +
            '<button data-g="1" ' + (STATE.guests >= gMax ? "disabled" : "") + '>+</button>' +
          '</div>' +
          capNote +
        '</div>' +
        '<div class="mb-field">' +
          '<label class="mb-label">Дата <span class="mb-req">*</span></label>' +
          '<div class="mb-chips mb-chips--dates">' + dateChips + '</div>' +
        '</div>' +
        timeBlock +
      '</div>';
  }

  function renderPositions() {
    var sc = CFG.scheme || {};
    var tables = sc.tables || [];
    var ratio = sc.ratio || "16 / 11";
    var bg = sc.bg ? '<img class="mb-scheme__bg" src="' + esc(assetUrl(sc.bg)) + '" alt="">' : "";

    var nodes = tables.map(function (t) {
      var status = t.status === "busy" ? "is-busy" : "is-free";
      var sel = STATE.table === t.id ? " is-sel" : "";
      var fits = t.seats >= STATE.guests;   // лимит гостей по вместимости стола
      var size = 36 + Math.max(0, (t.seats - 2)) * 5; // px
      var cls = "mb-table mb-table--" + (t.shape === "square" ? "square" : "round") + " " + status + sel + (fits ? "" : " is-small");
      var title = "Позиция " + (t.label || t.id) + " · " + t.seats + " мест" +
        (t.zone ? " · " + t.zone : "") +
        (t.status === "busy" ? " · занято" : (fits ? "" : " · мало мест для " + STATE.guests + " гостей"));
      return '<button class="' + cls + '" data-table="' + esc(t.id) + '"' +
        (t.status === "busy" || !fits ? " disabled" : "") +
        ' title="' + esc(title) + '"' +
        ' style="left:' + t.x + '%;top:' + t.y + '%;width:' + size + 'px;height:' + size + 'px;">' +
        '<span class="mb-table__seats">' + t.seats + '</span>' +
        '<span class="mb-table__cap">' + esc(t.label || t.id) + '</span>' +
      '</button>';
    }).join("");

    var selTable = tableById(STATE.table);
    var pick = selTable
      ? '<div class="mb-scheme-pick">Выбрана позиция <b>' + esc(selTable.label || selTable.id) + '</b> · ' +
        selTable.seats + ' мест' + (selTable.zone ? ' · ' + esc(selTable.zone) : '') + '</div>'
      : '<div class="mb-scheme-pick">Коснись свободной позиции на схеме</div>';

    EL.body.innerHTML =
      '<div class="mb-screen is-active">' +
        '<div class="mb-field"><label class="mb-label">Позиция <span class="mb-req">*</span></label>' +
          '<div class="mb-scheme" style="aspect-ratio:' + ratio + '">' +
            bg +
            '<div class="mb-scheme__glow"></div>' +
            '<div class="mb-scheme__label">' + esc(CFG.venueName || "") + '</div>' +
            nodes +
          '</div>' +
          '<div class="mb-legend">' +
            '<span class="lg-free"><i></i>Свободно</span>' +
            '<span class="lg-busy"><i></i>Занято</span>' +
            '<span class="lg-sel"><i></i>Выбрано</span>' +
          '</div>' +
          pick +
        '</div>' +
      '</div>';
  }

  function renderContacts() {
    EL.body.innerHTML =
      '<div class="mb-screen is-active">' +
        '<div class="mb-field">' +
          '<label class="mb-label">Имя <span class="mb-req">*</span></label>' +
          '<input class="mb-input" data-f="name" type="text" placeholder="Как тебя зовут" value="' + esc(STATE.name) + '">' +
          '<div class="mb-err-msg" data-e="name">Укажи имя</div>' +
        '</div>' +
        '<div class="mb-row2">' +
          '<div class="mb-field">' +
            '<label class="mb-label">Телефон <span class="mb-req">*</span></label>' +
            '<input class="mb-input" data-f="phone" type="tel" inputmode="tel" placeholder="+7 900 000-00-00" value="' + esc(STATE.phone) + '">' +
            '<div class="mb-err-msg" data-e="phone">Проверь номер</div>' +
          '</div>' +
          '<div class="mb-field">' +
            '<label class="mb-label">Email</label>' +
            '<input class="mb-input" data-f="email" type="email" inputmode="email" placeholder="по желанию" value="' + esc(STATE.email) + '">' +
            '<div class="mb-err-msg" data-e="email">Проверь email</div>' +
          '</div>' +
        '</div>' +
        '<div class="mb-field">' +
          '<label class="mb-label">Комментарий</label>' +
          '<textarea class="mb-textarea" data-f="comment" placeholder="Повод, пожелания по столу, аллергии…">' + esc(STATE.comment) + '</textarea>' +
        '</div>' +
        summaryHTML() +
      '</div>';
  }

  // Описание выбранного места: из 3D-карты (preset) или из 2D-схемы.
  function placeLabel() {
    if (STATE.preset && STATE.preset.table) {
      var t = STATE.preset.table;
      return (t.label || ("Стол " + t.id)) + (t.zone ? " · " + t.zone : "");
    }
    if (STATE.preset && STATE.preset.zone) return STATE.preset.zone;
    var st = tableById(STATE.table);
    if (st) return (st.label || st.id) + " · " + st.seats + " мест" + (st.zone ? " · " + st.zone : "");
    return "";
  }

  function summaryHTML() {
    var place = placeLabel();
    var rows = [
      ["Гостей", STATE.guests],
      ["Дата", STATE.date ? fmtDate(STATE.date) : "—"],
      ["Время", STATE.time || "—"]
    ];
    if (place) rows.push([STATE.preset && STATE.preset.zone && !STATE.preset.table ? "Зона" : "Стол", place]);
    return '<div class="mb-summary">' + rows.map(function (r) {
      return '<div class="mb-summary__row"><span class="mb-summary__k">' + r[0] + '</span><span class="mb-summary__v">' + r[1] + '</span></div>';
    }).join("") + '</div>';
  }

  function renderSuccess() {
    EL.headTitle.textContent = "Готово";
    EL.body.innerHTML =
      '<div class="mb-screen is-active"><div class="mb-success">' +
        '<div class="mb-success__mark">' + I.check + '</div>' +
        '<div class="mb-success__t">Бронь принята</div>' +
        '<div class="mb-success__jp">' + esc(CFG.jp) + ' · ありがとう</div>' +
        '<p class="mb-success__sub">' + esc(STATE.name) + ', ждём тебя ' + fmtDate(STATE.date) + ' в ' + STATE.time +
        '. Подтверждение придёт на телефон. Если планы изменятся — просто напиши нам.</p>' +
        (STATE.preset
          ? '<button class="mb-btn mb-btn--ghost" data-mb="close"><span>Закрыть</span></button>'
          : '<button class="mb-btn mb-btn--ghost" data-mb="restart"><span>Новая бронь</span></button>') +
      '</div></div>';
  }

  /* =========================================================
     NAV / VALIDATION
     ========================================================= */
  function updateNav() {
    var txt = EL.next.querySelector(".mb-btn__txt");
    var key = curKey();
    EL.hint.textContent = "";
    if (key === "date") {
      txt.textContent = "Далее";
      EL.next.disabled = !(STATE.date && STATE.time);
      if (STATE.date && STATE.time) EL.hint.textContent = fmtDate(STATE.date) + " · " + STATE.time;
    } else if (key === "position") {
      txt.textContent = "Далее";
      var hasTables = CFG.scheme && CFG.scheme.tables && CFG.scheme.tables.length;
      EL.next.disabled = !!(hasTables && !STATE.table);
      var st = tableById(STATE.table);
      if (st) EL.hint.textContent = "Позиция " + (st.label || st.id) + " · " + st.seats + " мест";
    } else if (key === "contacts") {
      txt.textContent = "Забронировать";
      EL.next.disabled = false;
    }
    if (key === "done") { EL.next.style.display = "none"; EL.back.style.display = "none"; }
    else { EL.next.style.display = ""; EL.back.style.display = ""; }
  }

  function validateContacts() {
    var ok = true;
    var name = STATE.name.trim();
    var phone = STATE.phone.replace(/[^\d+]/g, "");
    var email = STATE.email.trim();
    toggleErr("name", !!name); if (!name) ok = false;
    var phoneOk = phone.replace(/\D/g, "").length >= 10;
    toggleErr("phone", phoneOk); if (!phoneOk) ok = false;
    var emailOk = !email || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    toggleErr("email", emailOk); if (!emailOk) ok = false;
    return ok;
  }

  function toggleErr(field, valid) {
    var inp = EL.body.querySelector('[data-f="' + field + '"]');
    var msg = EL.body.querySelector('[data-e="' + field + '"]');
    if (inp) inp.classList.toggle("is-err", !valid);
    if (msg) msg.classList.toggle("is-show", !valid);
  }

  function goNext() {
    if (curKey() === "contacts") { if (!validateContacts()) return; submit(); return; }
    if (STATE.step < FLOW.length - 1) { STATE.step++; renderStep(); }
  }
  function goBack() { if (STATE.step > 0 && curKey() !== "done") { STATE.step--; renderStep(); } }

  /* =========================================================
     SUBMIT (telegram / email / endpoint / demo)
     ========================================================= */
  function payload() {
    var st = tableById(STATE.table);
    // приоритет: стол из 3D-карты (preset) → стол из 2D-схемы
    var pt = STATE.preset && STATE.preset.table ? STATE.preset.table : null;
    var pZone = STATE.preset && STATE.preset.zone ? STATE.preset.zone : null;
    return {
      venue: CFG.venueName,
      guests: STATE.guests,
      date: STATE.date ? fmtDate(STATE.date) : "",
      dateISO: STATE.date ? isoLocal(STATE.date) : "",
      time: STATE.time,
      table: pt ? (pt.label || ("Стол " + pt.id)) : (st ? (st.label || st.id) : ""),
      tableId: pt ? pt.id : (STATE.table || ""),
      tableSeats: st ? st.seats : null,
      zone: pt ? (pt.zone || "") : (pZone || (st && st.zone ? st.zone : "")),
      name: STATE.name.trim(),
      phone: STATE.phone.trim(),
      email: STATE.email.trim(),
      comment: STATE.comment.trim(),
      createdAt: new Date().toISOString()
    };
  }

  function tgText(p) {
    var lines = [
      "🆕 *Новая бронь* — " + p.venue,
      "",
      "👤 " + p.name + "   📞 " + p.phone,
      p.email ? "✉️ " + p.email : "",
      "📅 " + p.date + "   🕐 " + p.time,
      "🪑 " + (p.table ? p.table + (p.zone ? " (" + p.zone + ")" : "") : (p.zone || "—")) + "   · гостей: " + p.guests,
      p.comment ? "💬 " + p.comment : ""
    ];
    return lines.filter(Boolean).join("\n");
  }

  // "HH:MM" → минуты от полуночи; часы 00-11 считаем продолжением вечера
  // (клуб работает вечер-ночь, напр. 16:00-05:00) — та же логика, что на
  // сервере (server/app.py, _slot_minutes) — иначе полночь ломала бы сравнение.
  function slotMinutes(hhmm) {
    var p = (hhmm || "00:00").split(":"); var hh = +p[0] || 0, mm = +p[1] || 0;
    var total = hh * 60 + mm;
    if (hh < 12) total += 24 * 60;
    return total;
  }

  // Пересекается ли [time, time+bookingDurationMin) с уже существующей бронью
  // этого стола в этот день? (клиентская подсказка — окончательно решает
  // сервер тем же алгоритмом при отправке, см. persistBooking/submit)
  function tableConflict(tableId, dateISO, time) {
    if (!tableId || !dateISO) return false;
    var start = slotMinutes(time), end = start + (CFG.bookingDurationMin || 120);
    try {
      var list = JSON.parse(localStorage.getItem("mirai_bookings_v1")) || [];
      return list.some(function (b) {
        if (b.tableId !== tableId || b.dateISO !== dateISO || b.status === "cancelled") return false;
        var s = slotMinutes(b.time), e = s + (CFG.bookingDurationMin || 120);
        return start < e && s < end;
      });
    } catch (e) { return false; }
  }

  function submit() {
    var p = payload();
    // запрет накладок: стол уже занят на это время в этот день (клиентская
    // подсказка — окончательно решает сервер через persistBooking ниже)
    if (p.tableId && tableConflict(p.tableId, p.dateISO, p.time)) {
      EL.hint.textContent = "На это время стол уже забронирован. Выберите другое время или стол.";
      return;
    }
    setLoading(true);
    EL.hint.textContent = "";
    persistBooking(p).then(function (res) {
      setLoading(false);
      if (res && res.ok === false) {
        EL.hint.textContent = res.error === "table_taken"
          ? "На это время стол уже забронирован. Выберите другое время или стол."
          : "Не удалось отправить. Попробуй ещё раз.";
        return;
      }
      STATE.step = FLOW.indexOf("done"); renderStep();
      if (typeof CFG.onSuccess === "function") CFG.onSuccess(p);
    });
  }

  function postJSON(url, data) {
    return fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(data)
    }).then(function (r) { if (!r.ok) throw new Error("HTTP " + r.status); return r; });
  }

  function setLoading(on) { EL.next.classList.toggle("is-loading", on); EL.next.disabled = on; }

  // Сохраняет подтверждённую бронь. Источник правды — MiraiStore (локальный
  // API + SQLite, см. server/app.py), который и решает окончательно, не занят
  // ли стол (409 → { ok:false, error:"table_taken" }), плюс шлёт Telegram.
  // Возвращает Promise<{ok, error?}> — submit() ждёт его перед экраном успеха.
  function persistBooking(p) {
    var rec = {
      dateISO: p.dateISO, time: p.time, tableId: p.tableId, tableLabel: p.table, zone: p.zone,
      guests: p.guests, name: p.name, phone: p.phone, email: p.email, comment: p.comment,
      status: "confirmed", source: "widget", createdAt: p.createdAt
    };
    if (global.MiraiStore && typeof global.MiraiStore.addBooking === "function") {
      return global.MiraiStore.addBooking(rec, { notify: true });
    }
    // страница без store.js — старый режим (transport напрямую + локальный кэш)
    var t = CFG.transport || {};
    var saveLocal = function () {
      try {
        var KEY = "mirai_bookings_v1";
        var list = JSON.parse(localStorage.getItem(KEY)) || [];
        if (!Array.isArray(list)) list = [];
        rec.id = "b" + Date.now().toString(36) + Math.floor(Math.random() * 1000);
        list.push(rec);
        localStorage.setItem(KEY, JSON.stringify(list));
      } catch (e) { if (console && console.warn) console.warn("[MiraiBooking] persist failed:", e); }
      return { ok: true };
    };
    if (t.mode === "telegram" && t.telegram && t.telegram.botToken && t.telegram.chatId) {
      var url = "https://api.telegram.org/bot" + t.telegram.botToken + "/sendMessage";
      return postJSON(url, { chat_id: t.telegram.chatId, text: tgText(p), parse_mode: "Markdown" }).then(saveLocal).catch(function (e) { return { ok: false, error: "network_error" }; });
    } else if (t.mode === "email" && t.email && t.email.endpoint) {
      return postJSON(t.email.endpoint, p).then(saveLocal).catch(function () { return { ok: false, error: "network_error" }; });
    } else if (t.mode === "endpoint" && t.endpoint) {
      return postJSON(t.endpoint, p).then(saveLocal).catch(function () { return { ok: false, error: "network_error" }; });
    }
    // demo: ничего не шлём, просто показываем успех
    console.info("[MiraiBooking] DEMO payload (отправка не настроена):", p);
    return new Promise(function (res) { setTimeout(function () { res(saveLocal()); }, 650); });
  }

  /* =========================================================
     EVENTS
     ========================================================= */
  function bind() {
    EL.root.addEventListener("click", function (e) {
      var t = e.target;
      var mb = t.closest ? t.closest("[data-mb]") : null;
      if (mb) {
        var act = mb.getAttribute("data-mb");
        if (act === "open") return open();
        if (act === "close") return close();
        if (act === "overlay" && t === EL.overlay) return close();
        if (act === "next") return goNext();
        if (act === "back") return goBack();
        if (act === "restart") return restart();
      }
      // guests
      var g = t.closest ? t.closest("[data-g]") : null;
      if (g) { changeGuests(parseInt(g.getAttribute("data-g"), 10)); return; }
      // date
      var d = t.closest ? t.closest("[data-date]") : null;
      if (d) { STATE.date = new Date(d.getAttribute("data-date")); STATE.time = null; renderStep(); return; }
      // time
      var tm = t.closest ? t.closest("[data-time]") : null;
      if (tm && !tm.classList.contains("is-disabled")) { STATE.time = tm.getAttribute("data-time"); renderDateGuests(); updateNav(); return; }
      // position on scheme
      var tb = t.closest ? t.closest("[data-table]") : null;
      if (tb && !tb.disabled) { STATE.table = tb.getAttribute("data-table"); renderPositions(); updateNav(); return; }
    });

    // inputs (delegated)
    EL.body.addEventListener("input", function (e) {
      var f = e.target.getAttribute && e.target.getAttribute("data-f");
      if (f) {
        STATE[f] = e.target.value;
        if (f === "phone") e.target.value = formatPhone(e.target.value);
        STATE[f] = e.target.value;
      }
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && EL.overlay.classList.contains("is-open")) close();
    });
  }

  // Вместимость предвыбранного стола (карта), если известна — иначе null.
  function presetSeats() {
    var t = STATE.preset && STATE.preset.table;
    return (t && t.seats != null) ? +t.seats : null;
  }
  // Лимит гостей по вместимости стола — правило «не сажать больше, чем есть мест».
  function guestsCap() {
    var seats = presetSeats();
    return seats != null ? Math.max(CFG.minGuests, Math.min(CFG.maxGuests, seats)) : CFG.maxGuests;
  }

  function changeGuests(delta) {
    STATE.guests = clamp(STATE.guests + delta, CFG.minGuests, guestsCap());
    var v = EL.body.querySelector('[data-mb="gval"]');
    if (v) v.textContent = STATE.guests;
    renderDateGuests();
    updateNav();
  }

  function buildFlow() {
    FLOW = STATE.preset ? ["date", "contacts", "done"] : ["date", "position", "contacts", "done"];
  }

  function resetState() {
    STATE.step = 0; STATE.date = null; STATE.time = null; STATE.table = null;
    STATE.name = ""; STATE.phone = ""; STATE.email = ""; STATE.comment = "";
    STATE.guests = clamp(2, CFG.minGuests, guestsCap());
    EL.next.style.display = ""; EL.back.style.display = "";
  }

  // «Новая бронь» — сброс к полному флоу (без преселекта)
  function restart() { STATE.preset = null; buildFlow(); resetState(); renderStep(); }

  // open()                       — полный флоу (дата → выбор места → контакты)
  // open({ table:{id,label,zone,desc} }) — стол выбран на 3D-карте, шаг выбора места пропускается
  // open({ zone:"Lounge" })      — бронь по зоне без конкретного стола
  function open(opts) {
    opts = opts || {};
    if (opts.table) STATE.preset = { table: opts.table };
    else if (opts.zone) STATE.preset = { zone: opts.zone };
    else STATE.preset = null;
    buildFlow();
    resetState();
    if (opts.dateISO) { var pp = opts.dateISO.split("-"); STATE.date = new Date(+pp[0], +pp[1] - 1, +pp[2]); }
    renderStep();
    EL.overlay.classList.add("is-open"); document.body.style.overflow = "hidden";
  }
  function close() { EL.overlay.classList.remove("is-open"); document.body.style.overflow = ""; }

  /* =========================================================
     HELPERS
     ========================================================= */
  function buildDates() {
    var out = [], now = new Date();
    now.setHours(0, 0, 0, 0);
    for (var i = 0; i < CFG.daysAhead; i++) {
      var d = new Date(now); d.setDate(now.getDate() + i); out.push(d);
    }
    return out;
  }
  function buildTimes(date) {
    var out = [];
    var openH = (CFG.weekdayOpen && CFG.weekdayOpen[date.getDay()] != null) ? CFG.weekdayOpen[date.getDay()] : CFG.openHour;
    var closeH = CFG.closeHour;
    if (closeH <= openH) closeH += 24;   // закрытие после полуночи (напр. 16:00 → 05:00 = 29)
    var now = new Date();
    var totalMin = (closeH - openH) * 60;
    // Если стол уже известен (пришёл с карты) — заранее гасим слоты, которые
    // накладываются на его существующие брони в этот день (см. tableConflict).
    var presetTableId = STATE.preset && STATE.preset.table ? STATE.preset.table.id : null;
    var dISO = isoLocal(date);
    for (var m = 0; m < totalMin; m += CFG.slotStepMin) {
      var hh = openH + Math.floor(m / 60);   // может быть >= 24 — это слоты следующих суток
      var mm = m % 60;
      var label = pad(hh % 24) + ":" + pad(mm);
      // реальное время слота: setHours с hh>=24 корректно переносит на следующий день
      var slot = new Date(date); slot.setHours(hh, mm, 0, 0);
      var disabled = slot.getTime() < now.getTime() + (CFG.minLeadMinutes || 0) * 60000; // прошлое / ближе minLeadMinutes
      if (!disabled && presetTableId && tableConflict(presetTableId, dISO, label)) disabled = true;
      out.push({ v: label, disabled: disabled });
    }
    return out;
  }
  function tableById(tid) {
    if (!tid || !CFG.scheme || !CFG.scheme.tables) return null;
    return find(CFG.scheme.tables, function (t) { return t.id === tid; });
  }
  function fmtDate(d) { return DOW[d.getDay()] + ", " + d.getDate() + " " + MON[d.getMonth()]; }
  function formatPhone(v) {
    var digits = v.replace(/\D/g, "");
    if (digits[0] === "8") digits = "7" + digits.slice(1);
    if (digits[0] !== "7") digits = "7" + digits;
    digits = digits.slice(0, 11);
    var r = "+7";
    if (digits.length > 1) r += " " + digits.slice(1, 4);
    if (digits.length >= 5) r += " " + digits.slice(4, 7);
    if (digits.length >= 8) r += "-" + digits.slice(7, 9);
    if (digits.length >= 10) r += "-" + digits.slice(9, 11);
    return r;
  }

  /* micro-utils */
  function q(root, name) { return root.querySelector('[data-mb="' + name + '"]'); }
  function each(list, fn) { Array.prototype.forEach.call(list, fn); }
  function find(arr, fn) { for (var i = 0; i < arr.length; i++) if (fn(arr[i])) return arr[i]; return null; }
  function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }
  function pad(n) { return n < 10 ? "0" + n : "" + n; }
  function isoLocal(d) { return d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate()); }
  function sameDay(a, b) { return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate(); }
  function fmt(n) { return (n || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, " "); }
  function esc(s) { return (s == null ? "" : String(s)).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); }
  function clone(o) { return JSON.parse(JSON.stringify(o)); }
  function deepMerge(base, ext) {
    for (var k in ext) {
      if (!ext.hasOwnProperty(k)) continue;
      if (ext[k] && typeof ext[k] === "object" && !Array.isArray(ext[k]) && base[k] && typeof base[k] === "object")
        deepMerge(base[k], ext[k]);
      else base[k] = ext[k];
    }
    return base;
  }

  global.MiraiBooking = MiraiBooking;
})(window);
