(() => {
  const loader = document.getElementById("app-loader");
  if (!loader) return;

  const MIN_SHOW_MS = 600;
  const MAX_WAIT_MS = 3500;
  const start = Date.now();
  const textEl = loader.querySelector("[data-loader-text]");

  const messages = ["Подготавливаем меню…", "Загружаюсь…", "Ищу шрифты…"];
  let msgIdx = 0;
  let msgTimer = null;

  const startMessages = () => {
    if (!textEl) return;
    const tick = () => {
      msgIdx = (msgIdx + 1) % messages.length;
      textEl.textContent = messages[msgIdx];
      msgTimer = setTimeout(tick, 850);
    };
    msgTimer = setTimeout(tick, 850);
  };

  const hide = () => {
    if (loader.classList.contains("is-hidden")) return;
    if (msgTimer) clearTimeout(msgTimer);
    const elapsed = Date.now() - start;
    const delay = Math.max(0, MIN_SHOW_MS - elapsed);
    setTimeout(() => {
      loader.classList.add("is-hidden");
      // optional cleanup after transition
      setTimeout(() => loader.remove(), 700);
    }, delay);
  };

  const fontsReady = document.fonts?.ready?.catch(() => undefined) ?? Promise.resolve();
  const windowLoaded = new Promise((resolve) => {
    if (document.readyState === "complete") return resolve();
    window.addEventListener("load", () => resolve(), { once: true });
  });

  startMessages();

  Promise.race([
    Promise.all([fontsReady, windowLoaded]),
    new Promise((resolve) => setTimeout(resolve, MAX_WAIT_MS)),
  ]).then(hide);
})();

