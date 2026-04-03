document.addEventListener("DOMContentLoaded", () => {
  const root = document.querySelector("[data-gallery]");
  if (!root) return;

  const thumbs = Array.from(root.querySelectorAll("[data-gallery-thumb]"));
  const backdrop = root.querySelector("[data-gallery-backdrop]");
  const viewer = root.querySelector("[data-gallery-viewer]");
  const track = root.querySelector("[data-gallery-track]");
  const titleEl = root.querySelector("[data-gallery-title]");
  const subEl = root.querySelector("[data-gallery-sub]");

  const btnPrev = root.querySelector("[data-gallery-prev]");
  const btnNext = root.querySelector("[data-gallery-next]");
  const btnClose = root.querySelector("[data-gallery-close]");

  const total = thumbs.length;
  let idx = 0;

  const setOpen = (open) => {
    if (!backdrop || !viewer) return;
    backdrop.hidden = !open;
    viewer.hidden = !open;
    viewer.setAttribute("aria-hidden", open ? "false" : "true");
  };

  const render = () => {
    if (!track) return;
    track.style.transform = `translate3d(${-idx * 100}%,0,0)`;
    const thumb = thumbs[idx];
    const caption = thumb?.getAttribute("data-caption") || `Позиция №${idx + 1}`;
    if (titleEl) titleEl.textContent = caption;
    if (subEl) subEl.textContent = `${idx + 1}/${total}`;
  };

  const wrapIndex = (n) => {
    if (!total) return 0;
    return ((n % total) + total) % total;
  };
  const go = (n) => {
    idx = wrapIndex(n);
    render();
  };

  thumbs.forEach((t) => {
    t.addEventListener("click", () => {
      const n = Number(t.getAttribute("data-idx") || 0);
      setOpen(true);
      go(n);
    });
  });

  btnPrev?.addEventListener("click", () => go(idx - 1));
  btnNext?.addEventListener("click", () => go(idx + 1));
  btnClose?.addEventListener("click", () => setOpen(false));
  backdrop?.addEventListener("click", () => setOpen(false));

  // swipe inside viewer
  const stage = root.querySelector("[data-gallery-stage]");
  let sx = 0;
  let dx = 0;
  let swiping = false;

  stage?.addEventListener("pointerdown", (e) => {
    if (viewer?.hidden) return;
    swiping = true;
    sx = e.clientX;
    dx = 0;
    stage.setPointerCapture(e.pointerId);
  });

  stage?.addEventListener("pointermove", (e) => {
    if (!swiping) return;
    dx = e.clientX - sx;
  });

  stage?.addEventListener("pointerup", () => {
    if (!swiping) return;
    swiping = false;
    const threshold = 50;
    if (dx < -threshold) go(idx + 1);
    else if (dx > threshold) go(idx - 1);
  });

  // Force closed state on init.
  setOpen(false);
  render();
});