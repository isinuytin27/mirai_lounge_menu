/**
 * Мягкое предупреждение о возможном VPN/прокси.
 * Надёжного API «VPN включён» в браузере нет — используем эвристики и честную формулировку.
 */
(() => {
  const STORAGE_SNOOZE = "mirai_vpn_hint_snooze_until";
  const SESSION_SHOWN = "mirai_vpn_hint_shown_session";
  const SNOOZE_MS = 7 * 24 * 60 * 60 * 1000;
  const START_DELAY_MS = 950;

  function getSnoozeUntil() {
    try {
      const v = localStorage.getItem(STORAGE_SNOOZE);
      return v ? parseInt(v, 10) : 0;
    } catch (_) {
      return 0;
    }
  }

  function setSnooze() {
    try {
      localStorage.setItem(STORAGE_SNOOZE, String(Date.now() + SNOOZE_MS));
    } catch (_) {}
  }

  function tzSuggestsRussiaOrNeighbors() {
    const tz = Intl.DateTimeFormat().resolvedOptions().timeZone || "";
    return /^Europe\/(Moscow|Simferopol|Kaliningrad|Samara|Kirov|Volgograd|Astrakhan|Saratov|Ulyanovsk|Minsk|Kiev|Kyiv)|Asia\/(Novosibirsk|Omsk|Krasnoyarsk|Irkutsk|Yekaterinburg|Novokuznetsk|Barnaul|Tomsk|Vladivostok|Yakutsk|Magadan|Kamchatka|Sakhalin|Almaty|Qyzylorda|Aqtobe)/.test(
      tz
    );
  }

  function parseCfWarp(text) {
    if (!text || typeof text !== "string") return false;
    for (const line of text.split("\n")) {
      const i = line.indexOf("=");
      if (i < 0) continue;
      const k = line.slice(0, i).trim();
      const v = line.slice(i + 1).trim().toLowerCase();
      if (k === "warp" && (v === "on" || v === "plus" || v.startsWith("on") || v.includes("plus"))) {
        return true;
      }
    }
    return false;
  }

  function fetchWithTimeout(url, ms) {
    const ctrl = new AbortController();
    const t = setTimeout(() => ctrl.abort(), ms);
    return fetch(url, { signal: ctrl.signal, credentials: "omit" }).finally(() => clearTimeout(t));
  }

  async function detectLikelyVpnOrProxy() {
    let warp = false;
    let geoMismatch = false;

    try {
      const r = await fetchWithTimeout("https://www.cloudflare.com/cdn-cgi/trace", 4000);
      if (r.ok) {
        warp = parseCfWarp(await r.text());
      }
    } catch (_) {}

    try {
      const r = await fetchWithTimeout("https://ipwho.is/", 5000);
      if (!r.ok) return { warp, geoMismatch };
      const j = await r.json();
      if (!j || !j.success || !j.country_code) return { warp, geoMismatch };
      const cc = String(j.country_code).toUpperCase();
      const regional = ["RU", "BY", "KZ", "AM", "AZ", "GE", "KG", "MD", "TJ", "TM", "UZ", "UA"];
      if (tzSuggestsRussiaOrNeighbors() && !regional.includes(cc)) {
        geoMismatch = true;
      }
    } catch (_) {}

    return { warp, geoMismatch };
  }

  function removeModal(root) {
    root?.remove();
  }

  function showModal() {
    if (sessionStorage.getItem(SESSION_SHOWN)) return;
    sessionStorage.setItem(SESSION_SHOWN, "1");

    const root = document.createElement("div");
    root.className = "vpn-hint";
    root.setAttribute("role", "dialog");
    root.setAttribute("aria-modal", "true");
    root.setAttribute("aria-labelledby", "vpn-hint-title");

    root.innerHTML = `
      <div class="vpn-hint-backdrop" data-vpn-hint-dismiss></div>
      <div class="vpn-hint-card">
        <div class="vpn-hint-icon" aria-hidden="true">◉</div>
        <h2 class="vpn-hint-title" id="vpn-hint-title">Возможен VPN или прокси</h2>
        <p class="vpn-hint-text">
          Похоже, ваше подключение к интернету не совпадает с регионом, который обычно соответствует
          часовому поясу устройства. Так бывает при включённом VPN или корпоративном прокси.
          Для стабильной работы меню и заказа попробуйте отключить VPN на время визита.
        </p>
        <div class="vpn-hint-actions">
          <button type="button" class="vpn-hint-btn vpn-hint-btn-primary" data-vpn-hint-ok>Понятно</button>
          <button type="button" class="vpn-hint-btn vpn-hint-btn-quiet" data-vpn-hint-snooze>Не напоминать 7 дней</button>
        </div>
      </div>
    `;

    document.body.appendChild(root);

    const focusBtn = root.querySelector("[data-vpn-hint-ok]");
    focusBtn?.focus();

    root.addEventListener("click", (e) => {
      if (e.target.closest("[data-vpn-hint-dismiss]") || e.target.closest("[data-vpn-hint-ok]")) {
        removeModal(root);
        return;
      }
      if (e.target.closest("[data-vpn-hint-snooze]")) {
        setSnooze();
        removeModal(root);
      }
    });

    root.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        e.preventDefault();
        removeModal(root);
      }
    });
  }

  async function run() {
    if (getSnoozeUntil() > Date.now()) return;

    const { warp, geoMismatch } = await detectLikelyVpnOrProxy();
    if (!warp && !geoMismatch) return;

    showModal();
  }

  window.addEventListener("load", () => {
    setTimeout(run, START_DELAY_MS);
  });
})();
