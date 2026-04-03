/**
 * Читает JSON из #mirai-order-context и выставляет window.__MIRAI_ORDER__ до menu.js и др.
 */
(() => {
  const el = document.getElementById("mirai-order-context");
  if (!el) {
    window.__MIRAI_ORDER__ = window.__MIRAI_ORDER__ || {
      tableBound: false,
      tableCaption: null,
    };
    return;
  }
  try {
    window.__MIRAI_ORDER__ = JSON.parse(el.textContent || "{}");
  } catch {
    window.__MIRAI_ORDER__ = { tableBound: false, tableCaption: null };
  }
})();
