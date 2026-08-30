const state = {
  x: 1,
  y: 1,
  animating: false,
};

/* Жесткие лимиты координат */

const LIMITS = {
  minX: 0,
  maxX: 2,

  minY: 0,
  maxY: 2,
};

/* существующие экраны */

const SCREENS = new Set(["1,0", "1,1", "0,1", "2,1", "1,2"]);

function render(animated = true) {
  if (animated) {
    state.animating = true;
    /* iOS-like drawer-кривая (Emil) — смена экранов ощущается «дороже» */
    viewport.style.transition = "transform .35s var(--ease-drawer)";

    setTimeout(() => {
      state.animating = false;
    }, 350);
  } else {
    viewport.style.transition = "none";
  }

  viewport.style.transform = `translate3d(-${state.x * 100}dvw,-${state.y * 100}dvh,0)`;

  try {
    window.dispatchEvent(
      new CustomEvent("mirai:navigate", {
        detail: { x: state.x, y: state.y, animated },
      })
    );
  } catch (_) {}
}

/* проверка координат */

function isValid(x, y) {
  if (x < LIMITS.minX) return false;
  if (x > LIMITS.maxX) return false;

  if (y < LIMITS.minY) return false;
  if (y > LIMITS.maxY) return false;

  if (!SCREENS.has(`${x},${y}`)) return false;

  return true;
}

/* движение */

function move(dx, dy) {
  if (state.animating) return;

  const nx = state.x + dx;
  const ny = state.y + dy;

  if (!isValid(nx, ny)) {
    /* если экран запрещен — возвращаемся */

    render(true);
    return;
  }

  state.x = nx;
  state.y = ny;

  render(true);
}

function goTo(x, y) {
  if (state.animating) return;
  if (state.x === x && state.y === y) return;
  if (!isValid(x, y)) return;

  state.x = x;
  state.y = y;

  render(true);
}

window.navigation = {
  move,
  goTo,
  render,
  state,
};

render(false);
