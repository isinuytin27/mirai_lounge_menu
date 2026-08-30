let startX = 0;
let startY = 0;

let currentX = 0;
let currentY = 0;

let dragging = false;
let axis = null;
let pendingFromScrollable = false;

const THRESHOLD = 80;
const START_THRESHOLD = 10;

viewport.addEventListener("pointerdown", (e) => {
  if (e.target.closest(".nav-hints .hint")) return;
  if (e.target.closest("[data-gallery-go-home], [data-booking-home], [data-about-home], [data-menu-go-home]")) return;
  if (e.target.closest("iframe")) return;
  if (e.target.closest(".gallery-viewer")) return;
  if (e.target.closest(".menu-cats")) return;
  /* Колесо категорий «Бар» крутится само — не даём свайпу таскать экран */
  if (e.target.closest(".menu-wheel")) return;
  if (e.target.closest("[data-item-viewer]")) return;
  /* Корзина меню: свайпы вверх/вниз обрабатывает сам экран меню */
  if (e.target.closest(".menu-cart-tab")) return;
  if (e.target.closest(".menu-cart-sheet")) return;
  if (e.target.closest(".menu-cart-backdrop")) return;
  if (e.target.closest(".menu-cart-clear")) return;

  dragging = true;
  axis = null;
  pendingFromScrollable = false;

  startX = e.clientX;
  startY = e.clientY;

  // Если жест начался внутри скроллируемой зоны (меню, о нас),
  // даем вертикальному движению работать как скролл.
  // Горизонтальный свайп при явном движении по X перехватим позже.
  if (e.target.closest(".menu-content") || e.target.closest(".about-content")) {
    pendingFromScrollable = true;
    dragging = false;
    return;
  }

  viewport.setPointerCapture(e.pointerId);
});

viewport.addEventListener("pointermove", (e) => {
  if (!dragging && !pendingFromScrollable) return;

  currentX = e.clientX;
  currentY = e.clientY;

  const dx = currentX - startX;
  const dy = currentY - startY;

  if (pendingFromScrollable) {
    if (!axis) {
      if (Math.abs(dx) > Math.abs(dy)) axis = "x";
      else axis = "y";
    }

    // Вертикаль — это скролл списка, не вмешиваемся.
    if (axis === "y") return;

    // По X — только если пользователь действительно "свайпит".
    if (Math.abs(dx) < START_THRESHOLD) return;

    pendingFromScrollable = false;
    dragging = true;
    try {
      viewport.setPointerCapture(e.pointerId);
    } catch (_) {}
  }

  if (!axis) {
    if (Math.abs(dx) > Math.abs(dy)) axis = "x";
    else axis = "y";
  }

  /* не даем двигать экран слишком далеко */

  const maxDrag = 120;

  const limitedDX = Math.max(Math.min(dx, maxDrag), -maxDrag);
  const limitedDY = Math.max(Math.min(dy, maxDrag), -maxDrag);

  let moveX = navigation.state.x * 100;
  let moveY = navigation.state.y * 100;

  viewport.style.transition = "none";

  if (axis === "x") {
    viewport.style.transform = `translate3d(${-((moveX * window.innerWidth) / 100) + limitedDX}px,-${moveY}dvh,0)`;
  }

  if (axis === "y") {
    viewport.style.transform = `translate3d(-${moveX}dvw,${-((moveY * window.innerHeight) / 100) + limitedDY}px,0)`;
  }
});

viewport.addEventListener("pointerup", (e) => {
  if (pendingFromScrollable) {
    pendingFromScrollable = false;
    axis = null;
    return;
  }

  if (!dragging) return;

  dragging = false;

  const dx = startX - currentX;
  const dy = startY - currentY;

  if (axis === "x") {
    if (dx > THRESHOLD) navigation.move(1, 0);
    else if (dx < -THRESHOLD) navigation.move(-1, 0);
    else navigation.render(true);
  }

  if (axis === "y") {
    if (dy > THRESHOLD) navigation.move(0, 1);
    else if (dy < -THRESHOLD) navigation.move(0, -1);
    else navigation.render(true);
  }
});

viewport.addEventListener("pointercancel", () => {
  dragging = false;
  pendingFromScrollable = false;
  navigation.render(true);
});
