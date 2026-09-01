(function () {
  "use strict";

  var cfgEl = document.getElementById("amateur-cup-config");
  var cfg = { slots_left: 0, max_slots: 10, registration_open: true };
  if (cfgEl) {
    try { cfg = Object.assign(cfg, JSON.parse(cfgEl.textContent || "{}")); }
    catch (e) { /* defaults */ }
  }

  var form = document.getElementById("regForm");
  var submitBtn = document.getElementById("submitBtn");
  var submitBtnHtml = submitBtn ? submitBtn.innerHTML : "";

  // ── Состав команды ──
  function buildPlayers() {
    var list = document.getElementById("playersList");
    if (!list) return;
    list.innerHTML = "";
    for (var i = 1; i <= 5; i++) {
      var row = document.createElement("div");
      row.className = "player-row";
      row.innerHTML =
        '<div class="player-num">' + i + '</div>' +
        '<input type="text" name="player_nick_' + i + '" placeholder="Никнейм в Steam" ' + (i === 1 ? "required" : "") + ' />' +
        '<input type="url" name="player_steam_' + i + '" class="steam-field" placeholder="https://steamcommunity.com/id/…" />';
      list.appendChild(row);
    }
  }
  buildPlayers();

  // ── Счётчик символов ──
  var commentArea = document.getElementById("commentArea");
  if (commentArea) {
    commentArea.addEventListener("input", function () {
      var c = document.getElementById("charCount");
      if (c) c.textContent = String(this.value.length);
    });
  }

  // ── Доступность регистрации ──
  function applyAvailability() {
    var banner = document.getElementById("noticeBanner");
    var closed = !cfg.registration_open || cfg.slots_left <= 0;
    if (banner) {
      banner.textContent = !cfg.registration_open
        ? "Регистрация на турнир закрыта."
        : "Все места заняты. Регистрация закрыта.";
      banner.classList.toggle("show", closed);
    }
    if (submitBtn) submitBtn.disabled = closed;

    var spots = document.getElementById("spotsDisplay");
    if (spots) {
      spots.textContent = cfg.slots_left + " / " + cfg.max_slots;
      spots.className = "info-value " +
        (cfg.slots_left <= 0 ? "spots-full" : cfg.slots_left <= 3 ? "spots-low" : "spots-ok");
    }
  }
  applyAvailability();

  // ── Валидация ──
  function showErr(id, show) {
    var e = document.getElementById("err-" + id);
    if (e) e.classList.toggle("show", show);
  }

  function markField(name, bad) {
    var e = document.querySelector('[name="' + name + '"]');
    if (e) e.classList.toggle("error", bad);
  }

  function validate() {
    var ok = true;
    ["teamName", "rating", "captainName", "captainSteam", "captainTelegram", "captainPhone"].forEach(function (f) {
      var el = document.querySelector('[name="' + f + '"]');
      var bad = !el || !el.value.trim();
      markField(f, bad);
      showErr(f, bad);
      if (bad) ok = false;
    });

    var steam = document.querySelector('[name="captainSteam"]');
    if (steam && steam.value.trim() && steam.value.toLowerCase().indexOf("steamcommunity.com") === -1) {
      markField("captainSteam", true);
      showErr("captainSteam", true);
      ok = false;
    }

    var n1 = document.querySelector('[name="player_nick_1"]');
    if (n1 && !n1.value.trim()) { n1.classList.add("error"); ok = false; }
    else if (n1) { n1.classList.remove("error"); }

    var hasSource = document.querySelectorAll('[name="source"]:checked').length > 0;
    showErr("source", !hasSource);
    if (!hasSource) ok = false;

    var rules = document.getElementById("rulesCheck");
    var privacy = document.getElementById("privacyCheck");
    showErr("rules", !(rules && rules.checked));
    showErr("privacy", !(privacy && privacy.checked));
    if (!(rules && rules.checked) || !(privacy && privacy.checked)) ok = false;

    return ok;
  }

  // ── Сбор данных ──
  function collectData() {
    var fd = new FormData(form);
    var players = [];
    for (var i = 1; i <= 5; i++) {
      var nick = (fd.get("player_nick_" + i) || "").trim();
      var steam = (fd.get("player_steam_" + i) || "").trim();
      if (nick) players.push({ nick: nick, steam: steam });
    }
    return {
      team_name: (fd.get("teamName") || "").trim(),
      rating: fd.get("rating") || "",
      experience: fd.get("experience") || "",
      captain_name: (fd.get("captainName") || "").trim(),
      captain_steam: (fd.get("captainSteam") || "").trim(),
      captain_telegram: (fd.get("captainTelegram") || "").trim(),
      captain_phone: (fd.get("captainPhone") || "").trim(),
      players: players,
      comment: (fd.get("comment") || "").trim(),
      sources: fd.getAll("source"),
      agree_rules: document.getElementById("rulesCheck").checked,
      agree_privacy: document.getElementById("privacyCheck").checked,
    };
  }

  function setError(msg) {
    var box = document.getElementById("submitError");
    if (box) box.textContent = msg || "";
  }

  // ── Отправка ──
  if (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      setError("");
      if (!validate()) {
        window.scrollTo({ top: 0, behavior: "smooth" });
        return;
      }
      if (!cfg.registration_open || cfg.slots_left <= 0) return;

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span class="spinner"></span> Отправляем…';

      fetch("/api/tournament-register.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(collectData()),
      })
        .then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
        .then(function (res) {
          if (res.body && res.body.ok) {
            if (typeof res.body.slots_left === "number") cfg.slots_left = res.body.slots_left;
            form.style.display = "none";
            var ok = document.getElementById("successScreen");
            if (ok) ok.classList.add("show");
            window.scrollTo({ top: 0, behavior: "smooth" });
            return;
          }
          var err = res.body && res.body.error;
          var msg = "Не удалось отправить заявку. Попробуйте ещё раз.";
          if (err === "full") msg = "Все места заняты — регистрация закрыта.";
          else if (err === "closed") msg = "Регистрация на турнир закрыта.";
          else if (err === "validation") msg = "Проверьте правильность заполнения полей.";
          else if (err === "too_fast") msg = "Слишком частая отправка. Подождите пару секунд.";
          setError(msg);
          if (err === "full" || err === "closed") {
            if (err === "full") cfg.slots_left = 0;
            if (err === "closed") cfg.registration_open = false;
            applyAvailability();
          } else {
            submitBtn.disabled = false;
          }
          submitBtn.innerHTML = submitBtnHtml;
        })
        .catch(function () {
          setError("Ошибка сети. Проверьте подключение и попробуйте снова.");
          submitBtn.disabled = false;
          submitBtn.innerHTML = submitBtnHtml;
        });
    });
  }
})();
