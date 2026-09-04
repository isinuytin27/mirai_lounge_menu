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
                const cart = loadCart();
                const count = cartCount(cart);
                // Все счётчики (старый бейдж + плавающая корзина).
                root.querySelectorAll("[data-cart-count]").forEach((el) => { el.textContent = String(count); });
                const badgeSvg = root.querySelector(".menu-cart-badge");
                if (badgeSvg) {
                    badgeSvg.classList.toggle("menu-cart-badge--empty", count <= 0);
                    badgeSvg.setAttribute("aria-label", count <= 0 ? "В корзине нет позиций" : "Позиций в корзине: " + count);
                }
                // Плавающая корзина: прячем счётчик и приглушаем, когда пусто.
                const fab = root.querySelector(".menu-cart-fab");
                if (fab) fab.toggleAttribute("data-cart-empty", count <= 0);
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
                syncBrandOverlay();
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

            /* Плавающий логотип лежит над вьюпортом — прячем его, пока открыт любой оверлей меню. */
            function syncBrandOverlay() {
                const viewer = root.querySelector("[data-item-viewer]");
                const viewerOpen = !!(viewer && !viewer.hidden);
                window.miraiBrand?.setOverlay(isCartSheetOpen() || viewerOpen);
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
                syncBrandOverlay();
            }

            function closeItemViewer() {
                itemViewerBackdrop.hidden = true;
                itemViewer.hidden = true;
                itemViewer.setAttribute("aria-hidden", "true");
                syncBrandOverlay();
            }

            // Полноэкранная карточка товара убрана: вся информация теперь на самой
            // карточке. Клик по телу карточки ничего не открывает — работают только
            // кнопки Добавить/±.

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

            /* === Плашки групп (уровень 1) → детали группы (уровень 2) === */
            (function initMenuGroups() {
                const track = root.querySelector("[data-menu-track]");
                const tiles = Array.from(root.querySelectorAll("[data-group-tile]"));
                const backBtn = root.querySelector("[data-menu-back]");
                const titleEl = root.querySelector("[data-menu-title]");
                const catsNav = root.querySelector("[data-menu-cats]");
                const emptyEl = root.querySelector("[data-menu-empty]");
                const barWheelEl = root.querySelector("[data-bar-wheel]");
                if (!track || !tiles.length) return;

                const DEFAULT_TITLE = titleEl ? titleEl.textContent.trim() : "Меню";

                // null — на плашках; для bar: "wheel" | "dishes"
                let subview = null;

                function showContent(visible) {
                    content.style.display = visible ? "" : "none";
                }

                // Обычная группа (Кальян/Кухня/Ночное): сразу список блюд + пилюли
                // Кухня-карусель: подсветка/увеличение карточки в центре (эффект колеса).
                function centerKitchenCard(grid) {
                    var cx = grid.getBoundingClientRect().left + grid.clientWidth / 2;
                    var cards = grid.querySelectorAll("[data-item]");
                    var best = null, bestD = Infinity;
                    cards.forEach(function (c) {
                        var r = c.getBoundingClientRect();
                        var d = Math.abs(r.left + r.width / 2 - cx);
                        if (d < bestD) { bestD = d; best = c; }
                    });
                    cards.forEach(function (c) {
                        if (c === best) c.setAttribute("data-centered", ""); else c.removeAttribute("data-centered");
                    });
                }
                function initKitchenCarousels() {
                    root.querySelectorAll('[data-cat-section][data-group="kitchen"] .menu-grid').forEach(function (grid) {
                        if (!grid.__carousel) {
                            grid.__carousel = true;
                            grid.addEventListener("scroll", function () { centerKitchenCard(grid); }, { passive: true });
                        }
                        centerKitchenCard(grid);
                    });
                }

                function openPlainGroup(groupId, label) {
                    let firstId = null;
                    let count = 0;
                    sections.forEach((s) => {
                        const inGroup = s.getAttribute("data-group") === groupId;
                        s.hidden = !inGroup;
                        if (inGroup) {
                            count++;
                            if (!firstId) firstId = s.id;
                        }
                    });
                    catButtons.forEach((b) => {
                        b.hidden = b.getAttribute("data-group") !== groupId;
                    });
                    if (barWheelEl) barWheelEl.hidden = true;
                    showContent(true);
                    root.removeAttribute("data-bar-dishes");
                    root.setAttribute("data-menu-group", groupId); // для CSS (напр. вид «Кальян»)
                    if (titleEl) titleEl.textContent = label || DEFAULT_TITLE;
                    // Одна категория в группе (Кальян) — пилюли не нужны
                    if (catsNav) catsNav.hidden = count <= 1;
                    if (emptyEl) emptyEl.hidden = count > 0;
                    if (firstId) setActiveCatById(firstId);
                    content.scrollTop = 0;
                    subview = null;
                    root.setAttribute("data-menu-level", "2");
                    // Кухня: инициализировать карусели (после раскладки).
                    if (groupId === "kitchen") {
                        requestAnimationFrame(function () { requestAnimationFrame(initKitchenCarousels); });
                    }
                }

                // «Бар»: колесо категорий + превью
                function openBarWheel(label) {
                    showContent(false);
                    if (catsNav) catsNav.hidden = true;
                    if (emptyEl) emptyEl.hidden = true;
                    if (barWheelEl) barWheelEl.hidden = false;
                    if (titleEl) titleEl.textContent = label || "Бар";
                    subview = "wheel";
                    root.setAttribute("data-menu-level", "2");
                    wheel && wheel.activate();
                }

                // «Бар» → выбран раздел: показываем его блюда (один столбец).
                // catsCsv — набор категорий (родитель + подкатегории), показываем секциями.
                // Если ни в одной нет товаров → заглушка «скоро».
                function openBarCategory(catsCsv, catTitle) {
                    if (barWheelEl) barWheelEl.hidden = true;
                    if (catsNav) catsNav.hidden = true;
                    if (titleEl) titleEl.textContent = catTitle || "Бар";

                    const ids = String(catsCsv || "")
                        .split(",")
                        .filter(Boolean)
                        .map((c) => "menu-" + c);
                    let has = false;
                    sections.forEach((s) => {
                        const show = ids.includes(s.id);
                        s.hidden = !show;
                        if (show) has = true;
                    });

                    showContent(has);
                    if (emptyEl) emptyEl.hidden = has;
                    root.setAttribute("data-bar-dishes", "1"); // карточки в один столбец
                    content.scrollTop = 0;
                    subview = "dishes";
                }

                function openGroup(groupId, label) {
                    // «Кальян» — премиальный экран-витрина выбора кальяна (карусель+чаши).
                    if (groupId === "hookah") { window.location.href = "/vitrina"; return; }
                    if (groupId === "bar") openBarWheel(label);
                    else openPlainGroup(groupId, label);
                }

                function backToTiles() {
                    if (barWheelEl) barWheelEl.hidden = true;
                    showContent(true);
                    root.removeAttribute("data-bar-dishes");
                    root.removeAttribute("data-menu-group");
                    if (titleEl) titleEl.textContent = DEFAULT_TITLE;
                    if (catsNav) catsNav.hidden = true;
                    if (backBtn) backBtn.hidden = true;
                    subview = null;
                    root.setAttribute("data-menu-level", "1");
                }

                // Кнопка «назад»: блюда бара → колесо; иначе → плашки
                function goBack() {
                    if (subview === "dishes") {
                        openBarWheel("Бар");
                        return;
                    }
                    backToTiles();
                }

                /* === Физика колеса категорий «Бар» === */
                function initWheel() {
                    const wheelEl = root.querySelector("[data-wheel]");
                    const listEl = root.querySelector("[data-wheel-list]");
                    const items = Array.from(root.querySelectorAll("[data-wheel-item]"));
                    const previewArt = root.querySelector("[data-wheel-preview-art]");
                    const previewCap = root.querySelector("[data-wheel-preview-cap]");
                    if (!wheelEl || !listEl || !items.length) return null;

                    const N = items.length;
                    const STEP = 0.39; // угловой шаг строки (рад) — даёт дугу как у Яндекса
                    const AMP = 58; // амплитуда горизонтального выезда (px)
                    let rowH = 56;
                    let offset = 0; // позиция в «строках» (0..N-1), дробная
                    let activeIdx = -1;
                    let raf = null;

                    function clampOffset(o) {
                        return Math.max(0, Math.min(N - 1, o));
                    }

                    function measure() {
                        const h = wheelEl.clientHeight || 480;
                        rowH = Math.max(46, Math.min(64, h / 7.5));
                        wheelEl.style.setProperty("--wheel-row", rowH + "px");
                    }

                    function setPreview(idx) {
                        const it = items[idx];
                        if (!it) return;
                        const img = it.getAttribute("data-preview") || "";
                        const title = it.getAttribute("data-title") || "";
                        if (previewArt) {
                            if (img) {
                                previewArt.style.backgroundImage = `url("${img}")`;
                            } else {
                                // Градиент-заглушка: оттенок из индекса (стабильный, разный для категорий)
                                const hue = Math.round((idx / N) * 320 + 200) % 360;
                                previewArt.style.backgroundImage =
                                    `linear-gradient(150deg, hsl(${hue} 70% 42%) 0%, hsl(${(hue + 40) % 360} 65% 22%) 100%)`;
                            }
                        }
                        if (previewCap) previewCap.textContent = title;
                    }

                    function updateActive() {
                        const idx = clampOffset(Math.round(offset));
                        if (idx === activeIdx) return;
                        activeIdx = idx;
                        items.forEach((it, i) =>
                            it.setAttribute("data-active", i === idx ? "true" : "false")
                        );
                        setPreview(idx);
                    }

                    function render() {
                        for (let i = 0; i < N; i++) {
                            const d = i - offset; // расстояние от центра в строках
                            const ad = Math.abs(d);
                            const y = d * rowH;
                            const x = AMP * (1 - Math.cos(Math.min(ad, 6) * STEP)); // дуга
                            const op = Math.max(0.12, 1.08 - 0.17 * ad); // затухание к краям
                            const sc = 1 + 0.16 * Math.max(0, 1 - ad); // лёгкий акцент центра
                            const st = items[i].style;
                            st.transform = `translateY(${y}px) translateX(${x}px) scale(${sc})`;
                            st.opacity = String(op);
                        }
                        updateActive();
                    }

                    /* Инерция + снап к ближайшему пункту, с ограничениями [0, N-1] */
                    function animateTo(target, vel) {
                        cancelAnimationFrame(raf);
                        target = clampOffset(target);
                        let v = vel || 0;
                        let last = performance.now();
                        function frame(now) {
                            const dt = Math.min(40, now - last) / 1000;
                            last = now;
                            // пружина к цели + затухание скорости
                            const k = 12; // жёсткость
                            const damp = 0.82;
                            const dist = target - offset;
                            v += dist * k * dt;
                            v *= damp;
                            offset += v * dt;
                            if (offset < 0) { offset = 0; v = 0; }
                            if (offset > N - 1) { offset = N - 1; v = 0; }
                            render();
                            if (Math.abs(dist) < 0.002 && Math.abs(v) < 0.02) {
                                offset = target;
                                render();
                                return;
                            }
                            raf = requestAnimationFrame(frame);
                        }
                        raf = requestAnimationFrame(frame);
                    }

                    function snap(vel) {
                        // прогноз остановки по скорости → ближайший пункт
                        const predicted = offset + (vel || 0) * 0.18;
                        animateTo(Math.round(predicted), vel);
                    }

                    /* --- Жесты перетаскивания --- */
                    let drag = null;
                    wheelEl.addEventListener("pointerdown", (e) => {
                        if (!e.isPrimary) return;
                        cancelAnimationFrame(raf);
                        drag = {
                            id: e.pointerId,
                            y0: e.clientY,
                            offset0: offset,
                            lastY: e.clientY,
                            lastT: performance.now(),
                            vel: 0,
                            moved: false,
                            target: e.target.closest("[data-wheel-item]"),
                        };
                        try { wheelEl.setPointerCapture(e.pointerId); } catch (_) {}
                    });
                    wheelEl.addEventListener("pointermove", (e) => {
                        if (!drag || e.pointerId !== drag.id) return;
                        const dy = e.clientY - drag.y0;
                        if (Math.abs(dy) > 4) drag.moved = true;
                        offset = clampOffset(drag.offset0 - dy / rowH);
                        const now = performance.now();
                        const dt = (now - drag.lastT) / 1000;
                        if (dt > 0) drag.vel = -((e.clientY - drag.lastY) / rowH) / dt;
                        drag.lastY = e.clientY;
                        drag.lastT = now;
                        render();
                    });
                    function endDrag(e) {
                        if (!drag || e.pointerId !== drag.id) return;
                        const d = drag;
                        try { wheelEl.releasePointerCapture(e.pointerId); } catch (_) {}
                        if (!d.moved) {
                            // тап: по центральному пункту — открыть, по другому — подвести к центру
                            const it = d.target;
                            if (it) {
                                const idx = Number(it.getAttribute("data-index"));
                                if (idx === activeIdx) {
                                    openBarCategory(it.getAttribute("data-cats"), it.getAttribute("data-title"));
                                    // Тач после pointerup шлёт «призрачный» click по тому же месту —
                                    // а там теперь карточка товара из открывшегося списка → откроется
                                    // товар. Глушим ровно один такой click.
                                    const swallow = (ev) => { ev.stopPropagation(); ev.preventDefault(); };
                                    document.addEventListener("click", swallow, { capture: true, once: true });
                                    setTimeout(() => document.removeEventListener("click", swallow, { capture: true }), 600);
                                } else {
                                    animateTo(idx, 0);
                                }
                            }
                            drag = null;
                            return;
                        }
                        const v = Math.max(-30, Math.min(30, d.vel));
                        drag = null;
                        snap(v);
                    }
                    wheelEl.addEventListener("pointerup", endDrag);
                    wheelEl.addEventListener("pointercancel", endDrag);

                    /* --- Колёсико мыши / тачпад --- */
                    let wheelSnapT = null;
                    wheelEl.addEventListener(
                        "wheel",
                        (e) => {
                            e.preventDefault();
                            cancelAnimationFrame(raf);
                            offset = clampOffset(offset + e.deltaY / rowH);
                            render();
                            clearTimeout(wheelSnapT);
                            wheelSnapT = setTimeout(() => animateTo(Math.round(offset), 0), 90);
                        },
                        { passive: false }
                    );

                    // Превью-картинка тоже открывает активную категорию
                    root.querySelector("[data-wheel-preview]")?.addEventListener("click", () => {
                        const it = items[activeIdx];
                        if (it) openBarCategory(it.getAttribute("data-cats"), it.getAttribute("data-title"));
                    });

                    return {
                        activate() {
                            measure();
                            offset = clampOffset(offset);
                            activeIdx = -1;
                            render();
                        },
                    };
                }

                const wheel = initWheel();

                tiles.forEach((t) => {
                    t.addEventListener("click", () => {
                        if (backBtn) backBtn.hidden = false;
                        openGroup(t.getAttribute("data-group"), t.getAttribute("data-label"));
                    });
                });

                // Хит из стрипа «🔥 Хиты» — открыть группу товара (как плашку группы).
                root.querySelectorAll("[data-hit-group]").forEach((h) => {
                    h.addEventListener("click", () => {
                        const gid = h.getAttribute("data-hit-group");
                        if (!gid) return;
                        const tile = tiles.find((t) => t.getAttribute("data-group") === gid);
                        if (backBtn) backBtn.hidden = false;
                        openGroup(gid, tile ? tile.getAttribute("data-label") : "");
                    });
                });
                backBtn?.addEventListener("click", goBack);

                /* При уходе с экрана меню возвращаемся на уровень плашек (после того, как экран уехал). */
                window.addEventListener("mirai:navigate", (e) => {
                    const d = (e && e.detail) || {};
                    if (!(d.x === 1 && d.y === 2)) setTimeout(backToTiles, 380);
                });

                window.addEventListener("resize", () => {
                    if (subview === "wheel" && wheel) wheel.activate();
                });

                backToTiles();
            })();

            updateCartBadge();
            hydrateCardsFromCart();
            renderCartSheet();
            syncOrderUi();
        })();
