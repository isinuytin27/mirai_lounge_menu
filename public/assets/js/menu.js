        (function () {
            const root = document.querySelector("[data-menu]");
            if (!root) return;

            const content = root.querySelector("[data-menu-content]");
            const catButtons = Array.from(root.querySelectorAll("[data-cat-btn]"));
            const sections = Array.from(root.querySelectorAll("[data-cat-section]"));

            const CART_KEY = "mirai_cart_v1";

            function loadCart() {
                try {
                    const raw = localStorage.getItem(CART_KEY);
                    const data = raw ? JSON.parse(raw) : { items: {} };
                    if (!data || typeof data !== "object" || !data.items || typeof data.items !== "object") return { items: {} };
                    return data;
                } catch (_) {
                    return { items: {} };
                }
            }

            function saveCart(cart) {
                localStorage.setItem(CART_KEY, JSON.stringify(cart));
            }

            function cartCount(cart) {
                return Object.values(cart.items).reduce((sum, it) => sum + (it.qty || 0), 0);
            }

            function syncOrderUi() {
                const hint = root.querySelector("[data-order-hint]");
                const subBtn = root.querySelector("[data-submit-order]");
                if (!hint || !subBtn) return;
                const cart = loadCart();
                const count = cartCount(cart);
                const o = typeof window.__MIRAI_ORDER__ === "object" && window.__MIRAI_ORDER__ ? window.__MIRAI_ORDER__ : {};
                const bound = !!o.tableBound;
                hint.hidden = bound || count <= 0;
                subBtn.hidden = !bound || count <= 0;
            }

            function updateCartBadge() {
                const countEl = root.querySelector("[data-cart-count]");
                const badgeSvg = root.querySelector(".menu-cart-badge");
                if (!countEl || !badgeSvg) return;
                const cart = loadCart();
                const count = cartCount(cart);
                countEl.textContent = String(count);
                badgeSvg.classList.toggle("menu-cart-badge--empty", count <= 0);
                badgeSvg.setAttribute("aria-label", count <= 0 ? "В корзине нет позиций" : "Позиций в корзине: " + count);
                const clearBtn = root.querySelector("[data-clear-cart]");
                if (clearBtn) clearBtn.hidden = count <= 0;
                syncOrderUi();
            }

            function setSheetOpen(open) {
                const sheet = root.querySelector("[data-cart-sheet]");
                const backdrop = root.querySelector("[data-cart-backdrop]");
                if (!sheet || !backdrop) return;
                sheet.classList.toggle("open", open);
                sheet.setAttribute("aria-hidden", open ? "false" : "true");
                backdrop.hidden = !open;
            }

            function openCartSheet() {
                renderCartSheet();
                setSheetOpen(true);
            }

            function closeCartSheet() {
                setSheetOpen(false);
            }

            function isCartSheetOpen() {
                const sh = root.querySelector("[data-cart-sheet]");
                return !!(sh && sh.classList.contains("open"));
            }

            /** Свайп вверх с вкладки — открыть; вниз с шапки шторки или затемнения — закрыть (не трогаем скролл списка внутри шторки). */
            (function initCartSwipeGestures() {
                const OPEN_PX = 56;
                const CLOSE_PX = 56;
                const AXIS = 1.15;

                let swipe = null;

                function clearSwipe() {
                    swipe = null;
                }

                function onPointerDown(e) {
                    if (!e.isPrimary || (e.pointerType === "mouse" && e.button !== 0)) return;

                    const t = e.target;
                    const tab = t.closest(".menu-cart-tab");
                    const sheet = root.querySelector("[data-cart-sheet]");
                    const backdrop = root.querySelector("[data-cart-backdrop]");
                    const sheetTop = t.closest("[data-cart-close]");
                    const inSheet = t.closest("[data-cart-sheet]");
                    const inBackdrop = t.closest("[data-cart-backdrop]");

                    if (tab && !isCartSheetOpen()) {
                        swipe = {
                            pointerId: e.pointerId,
                            x0: e.clientX,
                            y0: e.clientY,
                            mode: "open",
                            el: tab,
                        };
                        try {
                            tab.setPointerCapture(e.pointerId);
                        } catch (_) {}
                        return;
                    }

                    if (!isCartSheetOpen()) return;

                    if (inBackdrop && !inSheet) {
                        swipe = {
                            pointerId: e.pointerId,
                            x0: e.clientX,
                            y0: e.clientY,
                            mode: "close-backdrop",
                            el: backdrop,
                        };
                        try {
                            backdrop.setPointerCapture(e.pointerId);
                        } catch (_) {}
                        return;
                    }

                    if (sheetTop && inSheet) {
                        if (t.closest("button")) return;
                        swipe = {
                            pointerId: e.pointerId,
                            x0: e.clientX,
                            y0: e.clientY,
                            mode: "close-top",
                            el: sheetTop,
                        };
                        try {
                            sheetTop.setPointerCapture(e.pointerId);
                        } catch (_) {}
                    }
                }

                function onPointerMove(e) {
                    if (!swipe || e.pointerId !== swipe.pointerId) return;
                    const dx = e.clientX - swipe.x0;
                    const dy = e.clientY - swipe.y0;
                    swipe.lastDx = dx;
                    swipe.lastDy = dy;
                }

                function onPointerUp(e) {
                    if (!swipe || e.pointerId !== swipe.pointerId) return;
                    const dx = swipe.lastDx ?? 0;
                    const dy = swipe.lastDy ?? 0;
                    const mode = swipe.mode;
                    const el = swipe.el;
                    clearSwipe();
                    try {
                        if (el && el.releasePointerCapture) el.releasePointerCapture(e.pointerId);
                    } catch (_) {}

                    const vertical = Math.abs(dy) >= Math.abs(dx) * AXIS;

                    if (mode === "open" && vertical && dy <= -OPEN_PX) {
                        openCartSheet();
                        return;
                    }
                    if (mode === "close-top" && vertical && dy >= CLOSE_PX) {
                        closeCartSheet();
                        return;
                    }
                    if (mode === "close-backdrop" && vertical && dy >= CLOSE_PX) {
                        closeCartSheet();
                    }
                }

                function onPointerCancel(e) {
                    if (!swipe || e.pointerId !== swipe.pointerId) return;
                    const el = swipe.el;
                    clearSwipe();
                    try {
                        if (el && el.releasePointerCapture) el.releasePointerCapture(e.pointerId);
                    } catch (_) {}
                }

                root.addEventListener("pointerdown", onPointerDown);
                root.addEventListener("pointermove", onPointerMove);
                root.addEventListener("pointerup", onPointerUp);
                root.addEventListener("pointercancel", onPointerCancel);
            })();

            function formatRub(n) {
                try {
                    return new Intl.NumberFormat("ru-RU").format(n) + " ₽";
                } catch (_) {
                    return String(n) + " ₽";
                }
            }

            function renderCartSheet() {
                const sheetBody = root.querySelector("[data-cart-body]");
                const totalEl = root.querySelector("[data-cart-total]");
                if (!sheetBody || !totalEl) return;

                const cart = loadCart();
                const items = Object.values(cart.items || {});

                if (!items.length) {
                    sheetBody.innerHTML = '<div class="menu-cart-empty">Корзина пустая</div>';
                    totalEl.textContent = "0 ₽";
                    syncOrderUi();
                    return;
                }

                const total = items.reduce((sum, it) => sum + (Number(it.price) || 0) * (Number(it.qty) || 0), 0);
                totalEl.textContent = formatRub(total);

                sheetBody.innerHTML = items
                    .map((it) => {
                        const key = String(it.key || "");
                        const name = String(it.name || "");
                        const qty = Number(it.qty || 0);
                        const price = Number(it.price || 0);
                        const sum = price * qty;
                        return `
                          <div class="menu-cart-row" data-cart-row data-key="${key.replace(/"/g, "&quot;")}">
                            <div class="menu-cart-row-main">
                              <div class="menu-cart-row-name">${name.replace(/</g, "&lt;").replace(/>/g, "&gt;")}</div>
                              <div class="menu-cart-row-sub">${formatRub(price)} × ${qty} = ${formatRub(sum)}</div>
                            </div>
                            <div class="menu-cart-row-qty">
                              <button class="menu-cart-qty-btn" type="button" data-cart-minus aria-label="Уменьшить"><svg class="menu-qty-icon menu-cart-qty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/></svg></button>
                              <div class="menu-cart-qty-num"><svg viewBox="0 0 56 48" class="menu-cart-qty-num-svg"><text x="28" y="21" text-anchor="middle" dominant-baseline="central" font-family="Helvetica, Arial, sans-serif" font-size="26" font-weight="800" fill="white">${qty}</text></svg></div>
                              <button class="menu-cart-qty-btn" type="button" data-cart-plus aria-label="Увеличить"><svg class="menu-qty-icon menu-cart-qty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></button>
                            </div>
                          </div>
                        `;
                    })
                    .join("");
                syncOrderUi();
            }

            function setActiveCatById(sectionId) {
                catButtons.forEach((btn) => {
                    const target = btn.getAttribute("data-target") || "";
                    btn.setAttribute("aria-current", target === "#" + sectionId ? "true" : "false");
                });
            }

            catButtons.forEach((btn) => {
                btn.addEventListener("click", () => {
                    const target = btn.getAttribute("data-target");
                    if (!target) return;
                    const el = root.querySelector(target);
                    if (!el) return;
                    // scrollIntoView может дергать общий скролл страницы.
                    // Скроллим только внутренний контейнер списка.
                    const topbar = root.querySelector(".menu-topbar");
                    const topbarH = topbar ? topbar.getBoundingClientRect().height : 0;
                    const contentRect = content.getBoundingClientRect();
                    const elRect = el.getBoundingClientRect();
                    const delta = (elRect.top - contentRect.top) + content.scrollTop;
                    const y = Math.max(0, delta - topbarH - 10);
                    content.scrollTo({ top: y, behavior: "smooth" });
                    setActiveCatById(el.id);
                });
            });

            const itemViewerBackdrop = root.querySelector("[data-item-viewer-backdrop]");
            const itemViewer = root.querySelector("[data-item-viewer]");
            const itemViewerClose = root.querySelector("[data-item-viewer-close]");

            function openItemViewer(card) {
                const key = card.getAttribute("data-key");
                const name = card.getAttribute("data-name");
                const price = Number(card.getAttribute("data-price") || 0);
                const desc = card.getAttribute("data-description") || "";
                const img = card.getAttribute("data-image") || "";

                root.querySelector("[data-item-viewer-name]").textContent = name;
                const descEl = root.querySelector("[data-item-viewer-desc]");
                descEl.textContent = desc;
                descEl.hidden = !desc;
                root.querySelector("[data-item-viewer-price]").textContent = price + " ₽";

                const photoEl = root.querySelector("[data-item-viewer-photo]");
                photoEl.innerHTML = "";
                if (img) {
                    const imgEl = document.createElement("img");
                    const u = String(img).trim();
                    imgEl.src =
                        /^https?:\/\//i.test(u) || u.startsWith("/") || u.startsWith("data:")
                            ? u
                            : "/" + u.replace(/^\/+/, "");
                    imgEl.alt = name;
                    photoEl.appendChild(imgEl);
                    photoEl.hidden = false;
                } else {
                    photoEl.hidden = true;
                }

                const addBtn = root.querySelector("[data-item-viewer-add]");
                const qtyWrap = root.querySelector("[data-item-viewer-qty]");
                const countEl = root.querySelector("[data-item-viewer-count]");
                const cart = loadCart();
                const qty = Number(cart.items?.[key]?.qty || 0);
                if (qty > 0) {
                    addBtn.hidden = true;
                    qtyWrap.hidden = false;
                    countEl.textContent = String(qty);
                } else {
                    addBtn.hidden = false;
                    qtyWrap.hidden = true;
                }

                itemViewer?.setAttribute("data-viewer-key", key || "");
                itemViewerBackdrop.hidden = false;
                itemViewer.hidden = false;
                itemViewer.setAttribute("aria-hidden", "false");
            }

            function closeItemViewer() {
                itemViewerBackdrop.hidden = true;
                itemViewer.hidden = true;
                itemViewer.setAttribute("aria-hidden", "true");
            }

            root.addEventListener("click", (e) => {
                const card = e.target.closest("[data-item]");
                if (!card) return;
                const addBtn = e.target.closest("[data-add]");
                const plusBtn = e.target.closest("[data-plus]");
                const minusBtn = e.target.closest("[data-minus]");
                if (!addBtn && !plusBtn && !minusBtn) {
                    e.preventDefault();
                    openItemViewer(card);
                    return;
                }
            });

            root.addEventListener("keydown", (e) => {
                const card = e.target.closest("[data-item]");
                if (!card || e.key !== "Enter") return;
                const addBtn = e.target.closest("[data-add]");
                const plusBtn = e.target.closest("[data-plus]");
                const minusBtn = e.target.closest("[data-minus]");
                if (!addBtn && !plusBtn && !minusBtn) {
                    e.preventDefault();
                    openItemViewer(card);
                }
            });

            root.addEventListener("click", (e) => {
                const card = e.target.closest("[data-item]");
                if (!card) return;

                const addBtn = e.target.closest("[data-add]");
                const plusBtn = e.target.closest("[data-plus]");
                const minusBtn = e.target.closest("[data-minus]");
                if (!addBtn && !plusBtn && !minusBtn) return;

                const key = card.getAttribute("data-key");
                const name = card.getAttribute("data-name");
                const price = Number(card.getAttribute("data-price") || 0);
                if (!key || !name) return;

                const cart = loadCart();
                const existing = cart.items[key] || { key, name, price, qty: 0 };
                const currQty = Number(existing.qty || 0);
                let nextQty = currQty;

                if (addBtn || plusBtn) nextQty = currQty + 1;
                if (minusBtn) nextQty = Math.max(0, currQty - 1);

                if (nextQty <= 0) {
                    delete cart.items[key];
                } else {
                    existing.qty = nextQty;
                    cart.items[key] = existing;
                }

                saveCart(cart);
                updateCartBadge();

                // Сразу переключаем UI карточки на степпер.
                updateCardQty(card, nextQty);
            });

            root.addEventListener("click", (e) => {
                const row = e.target.closest("[data-cart-row]");
                if (!row) return;
                const key = row.getAttribute("data-key");
                if (!key) return;

                const plus = e.target.closest("[data-cart-plus]");
                const minus = e.target.closest("[data-cart-minus]");
                if (!plus && !minus) return;

                const cart = loadCart();
                const existing = cart.items[key];
                const currQty = Number(existing?.qty || 0);
                let nextQty = currQty;
                if (plus) nextQty = currQty + 1;
                if (minus) nextQty = Math.max(0, currQty - 1);

                if (nextQty <= 0) {
                    delete cart.items[key];
                } else {
                    cart.items[key] = { ...existing, qty: nextQty };
                }

                saveCart(cart);
                updateCartBadge();
                hydrateCardsFromCart();
                renderCartSheet();
            });

            function updateCardQty(card, qty) {
                const add = card.querySelector("[data-add]");
                const qtyWrap = card.querySelector("[data-qty]");
                const countEl = card.querySelector("[data-qty-count]");
                if (!add || !qtyWrap || !countEl) return;

                if (qty > 0) {
                    qtyWrap.hidden = false;
                    add.hidden = true;
                    countEl.textContent = String(qty);
                } else {
                    qtyWrap.hidden = true;
                    add.hidden = false;
                    countEl.textContent = "0";
                }
            }

            function hydrateCardsFromCart() {
                const cart = loadCart();
                root.querySelectorAll("[data-item]").forEach((card) => {
                    const key = card.getAttribute("data-key");
                    if (!key) return;
                    const qty = Number(cart.items?.[key]?.qty || 0);
                    updateCardQty(card, qty);
                });
            }

            const observer = new IntersectionObserver(
                (entries) => {
                    const visible = entries
                        .filter((e) => e.isIntersecting)
                        .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
                    if (!visible || !visible.target || !visible.target.id) return;
                    setActiveCatById(visible.target.id);
                },
                { root: content, threshold: [0.2, 0.35, 0.5, 0.65, 0.8] }
            );

            sections.forEach((s) => observer.observe(s));

            root.querySelector("[data-cart-toggle]")?.addEventListener("click", openCartSheet);

            root.querySelectorAll("[data-clear-cart]").forEach((btn) => btn.addEventListener("click", () => {
                const cart = loadCart();
                if (!cartCount(cart)) return;
                const ok = confirm("Очистить корзину?");
                if (!ok) return;
                saveCart({ items: {} });
                updateCartBadge();
                hydrateCardsFromCart();
                renderCartSheet();
            }));

            root.querySelector("[data-submit-order]")?.addEventListener("click", () => {
                const o = typeof window.__MIRAI_ORDER__ === "object" && window.__MIRAI_ORDER__ ? window.__MIRAI_ORDER__ : {};
                if (!o.tableBound) {
                    alert("Отсканируйте QR-код на столе, затем снова откройте меню.");
                    return;
                }
                const cart = loadCart();
                const items = Object.values(cart.items || {})
                    .filter((it) => (Number(it.qty) || 0) > 0)
                    .map((it) => ({ id: String(it.key || ""), qty: Number(it.qty) || 0 }));
                if (!items.length) return;
                const btn = root.querySelector("[data-submit-order]");
                if (btn) btn.disabled = true;
                fetch("/api/order-submit.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    credentials: "same-origin",
                    body: JSON.stringify({ items }),
                })
                    .then((res) => res.json().then((data) => ({ ok: res.ok, data })))
                    .then(({ ok, data }) => {
                        if (!ok || !data || !data.ok) {
                            const err = (data && data.error) || "error";
                            if (err === "no_table") {
                                alert("Сессия стола истекла. Отсканируйте QR ещё раз.");
                            } else if (err === "no_valid_items") {
                                alert("Не удалось отправить: позиции недоступны в меню.");
                            } else if (err === "too_fast") {
                                alert("Подождите пару секунд и попробуйте снова.");
                            } else {
                                alert("Не удалось отправить заказ. Попробуйте позже.");
                            }
                            return;
                        }
                        saveCart({ items: {} });
                        updateCartBadge();
                        hydrateCardsFromCart();
                        renderCartSheet();
                        closeCartSheet();
                        let msg = data.append ? "Дозаказ отправлен." : "Заказ отправлен.";
                        if (data.telegram_ok === false) {
                            msg += "\n\nУведомление в Telegram не дошло — заказ в панели /orders/ всё равно есть. Смотрите логи PHP на сервере.";
                        }
                        alert(msg);
                    })
                    .catch(() => {
                        alert("Ошибка сети. Проверьте подключение.");
                    })
                    .finally(() => {
                        if (btn) btn.disabled = false;
                    });
            });

            root.querySelectorAll("[data-cart-close]").forEach((btn) => btn.addEventListener("click", closeCartSheet));
            root.querySelector("[data-cart-backdrop]")?.addEventListener("click", closeCartSheet);

            itemViewerClose?.addEventListener("click", closeItemViewer);
            itemViewerBackdrop?.addEventListener("click", closeItemViewer);

            root.addEventListener("click", (e) => {
                const vAdd = e.target.closest("[data-item-viewer-add]");
                const vPlus = e.target.closest("[data-item-viewer-plus]");
                const vMinus = e.target.closest("[data-item-viewer-minus]");
                if (!vAdd && !vPlus && !vMinus) return;
                const key = itemViewer?.getAttribute("data-viewer-key");
                const name = root.querySelector("[data-item-viewer-name]")?.textContent || "";
                const price = parseInt(root.querySelector("[data-item-viewer-price]")?.textContent || "0", 10);
                if (!key || !name) return;

                const cart = loadCart();
                const existing = cart.items[key] || { key, name, price, qty: 0 };
                let nextQty = Number(existing.qty || 0);
                if (vAdd || vPlus) nextQty++;
                if (vMinus) nextQty = Math.max(0, nextQty - 1);

                if (nextQty <= 0) delete cart.items[key];
                else { existing.qty = nextQty; cart.items[key] = existing; }
                saveCart(cart);
                updateCartBadge();
                hydrateCardsFromCart();

                const addBtn = root.querySelector("[data-item-viewer-add]");
                const qtyWrap = root.querySelector("[data-item-viewer-qty]");
                const countEl = root.querySelector("[data-item-viewer-count]");
                if (nextQty > 0) {
                    addBtn.hidden = true;
                    qtyWrap.hidden = false;
                    countEl.textContent = String(nextQty);
                } else {
                    addBtn.hidden = false;
                    qtyWrap.hidden = true;
                }
            });

            updateCartBadge();
            hydrateCardsFromCart();
            renderCartSheet();
            syncOrderUi();
        })();
