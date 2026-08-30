/**
 * Плавающий логотип Mirai.
 * Один фиксированный бокс (#brand-logo) «облетает» из центра главной в строку «Меню»:
 *   • .logo-slot--home  — центр главного экрана (знак mirai, logo-vert.svg),
 *   • .logo-slot--menu  — строка «Меню» в шапке (лого лаунжа, logo-lounge.svg).
 * Бокс подгоняется под размер целевого слота (width/height/translate), а две картинки
 * внутри кроссфейдятся (mirai ↔ лаунж). На главной логотип статичен.
 * На остальных экранах скрыт. Реагирует на событие `mirai:navigate`.
 */
(function () {
  const wrap = document.getElementById("brand-logo");
  if (!wrap) return;

  const homeSlot = document.querySelector(".logo-slot--home");
  const menuSlot = document.querySelector(".logo-slot--menu");
  const imgMirai = wrap.querySelector(".brand-logo-mirai");
  const imgLounge = wrap.querySelector(".brand-logo-lounge");
  if (!homeSlot || !menuSlot) {
    wrap.style.display = "none";
    return;
  }

  const EASE = "cubic-bezier(.4,0,.2,1)";
  let overlayHidden = false; // поверх меню открыт оверлей (корзина/блюдо)
  let firstPlaced = false; // первая установка — без анимации

  /* Позиция элемента относительно его .screen = позиция на экране, когда тот активен. */
  function screenRect(el) {
    const screen = el.closest(".screen");
    const r = el.getBoundingClientRect();
    const s = screen ? screen.getBoundingClientRect() : { left: 0, top: 0 };
    return { l: r.left - s.left, t: r.top - s.top, w: r.width, h: r.height };
  }

  function place(slot, animate) {
    const r = screenRect(slot);
    if (r.w <= 0) return;
    wrap.style.transition = animate
      ? `transform .4s ${EASE}, width .4s ${EASE}, height .4s ${EASE}, opacity .3s ease`
      : "opacity .3s ease";
    wrap.style.width = r.w + "px";
    wrap.style.height = r.h + "px";
    wrap.style.transform = `translate(${r.l}px, ${r.t}px)`;
  }

  function update(x, y) {
    const onHome = x === 1 && y === 1;
    const onMenu = x === 1 && y === 2;
    if (onHome || onMenu) {
      place(onHome ? homeSlot : menuSlot, firstPlaced);
      wrap.style.opacity = overlayHidden && onMenu ? "0" : "1";
      if (imgMirai) imgMirai.style.opacity = onHome ? "1" : "0";
      if (imgLounge) imgLounge.style.opacity = onMenu ? "1" : "0";
    } else {
      wrap.style.opacity = "0";
    }
    firstPlaced = true;
  }

  function currentState() {
    return (window.navigation && window.navigation.state) || { x: 1, y: 1 };
  }

  function init() {
    const st = currentState();
    update(st.x, st.y);
  }

  window.addEventListener("mirai:navigate", (e) => {
    const d = (e && e.detail) || currentState();
    update(d.x, d.y);
  });

  let rt = null;
  window.addEventListener("resize", () => {
    clearTimeout(rt);
    rt = setTimeout(() => {
      firstPlaced = false; // переразместить мгновенно, без анимации
      const st = currentState();
      update(st.x, st.y);
    }, 120);
  });

  /* Прячем логотип, пока поверх меню открыт оверлей (корзина / просмотр блюда):
     #brand-logo лежит над вьюпортом и иначе перекрывал бы модалки. */
  window.miraiBrand = {
    setOverlay(hidden) {
      overlayHidden = !!hidden;
      const st = currentState();
      update(st.x, st.y);
    },
  };

  if (document.readyState !== "loading") init();
  else window.addEventListener("DOMContentLoaded", init);
})();
