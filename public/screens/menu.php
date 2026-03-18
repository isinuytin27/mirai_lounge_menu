<section class="screen menu">
    <?php
    $placeholderSvg = "data:image/svg+xml;charset=UTF-8," . rawurlencode(
        "<svg xmlns='http://www.w3.org/2000/svg' width='800' height='600' viewBox='0 0 800 600'>
          <defs>
            <linearGradient id='g' x1='0' x2='1' y1='0' y2='1'>
              <stop offset='0' stop-color='#151515'/>
              <stop offset='1' stop-color='#060606'/>
            </linearGradient>
          </defs>
          <rect width='800' height='600' fill='url(#g)'/>
          <circle cx='580' cy='180' r='120' fill='rgba(255,255,255,0.06)'/>
          <circle cx='260' cy='420' r='170' fill='rgba(255,255,255,0.04)'/>
          <path d='M190 360c120-140 320-140 420 0' fill='none' stroke='rgba(255,255,255,0.08)' stroke-width='14' stroke-linecap='round'/>
          <text x='40' y='80' fill='rgba(255,255,255,0.35)' font-family='Helvetica, Arial, sans-serif' font-size='34'>Фото скоро будет</text>
        </svg>"
    );

    $cfg = require dirname(__DIR__, 2) . "/config/config.php";
    $jsonPath = (string)($cfg["storage"]["menu_json_path"] ?? "");

    $categories = [];
    $products = [];

    if ($jsonPath !== "" && is_file($jsonPath)) {
        $raw = file_get_contents($jsonPath);
        $data = $raw ? json_decode($raw, true) : null;
        if (is_array($data)) {
            $categories = is_array($data["categories"] ?? null) ? $data["categories"] : [];
            $products = is_array($data["products"] ?? null) ? $data["products"] : [];
        }
    }

    // Группируем товары по категориям, фильтруем скрытые
    $byCat = [];
    foreach ($products as $p) {
        if (!is_array($p)) continue;
        if (array_key_exists("visible", $p) && !$p["visible"]) continue;
        $cid = (string)($p["category_id"] ?? "");
        if ($cid === "") continue;
        if (!isset($byCat[$cid])) $byCat[$cid] = [];
        $byCat[$cid][] = $p;
    }

    $menu = [];
    foreach ($categories as $c) {
        if (!is_array($c)) continue;
        $cid = (string)($c["id"] ?? "");
        $title = (string)($c["title"] ?? $cid);
        if ($cid === "") continue;
        $items = $byCat[$cid] ?? [];
        if (!count($items)) continue;
        $menu[] = [
            "id" => $cid,
            "title" => $title,
            "items" => $items,
        ];
    }
    ?>

    <div class="menu-wrap" data-menu>
        <header class="menu-topbar">
            <h2 class="menu-title">Меню</h2>
            <nav class="menu-cats" aria-label="Категории меню">
                <?php foreach ($menu as $idx => $cat): ?>
                    <button
                        class="menu-cat"
                        type="button"
                        data-cat-btn
                        data-target="#menu-<?= htmlspecialchars($cat["id"]) ?>"
                        aria-current="<?= $idx === 0 ? "true" : "false" ?>"
                    >
                        <?= htmlspecialchars($cat["title"]) ?>
                    </button>
                <?php endforeach; ?>
            </nav>
        </header>

        <div class="menu-content" data-menu-content>
            <?php foreach ($menu as $cat): ?>
                <section class="menu-section" id="menu-<?= htmlspecialchars($cat["id"]) ?>" data-cat-section>
                    <h3 class="menu-section-title"><?= htmlspecialchars($cat["title"]) ?></h3>

                    <div class="menu-grid">
                        <?php foreach ($cat["items"] as $item): ?>
                            <?php
                            $name = (string)($item["name"] ?? "");
                            $desc = (string)($item["description"] ?? "");
                            $price = (int)($item["price"] ?? 0);
                            $img = (string)($item["image"] ?? "");
                            $imgSrc = $img !== "" ? $img : $placeholderSvg;
                            $key = (string)($item["id"] ?? ($cat["id"] . "::" . $name));
                            ?>
                            <article class="menu-card" data-item data-key="<?= htmlspecialchars($key) ?>" data-name="<?= htmlspecialchars($name) ?>" data-price="<?= $price ?>">
                                <img class="menu-photo" src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($name) ?>" loading="lazy">
                                <div class="menu-info">
                                    <div class="menu-card-head">
                                        <h4 class="menu-item-name"><?= htmlspecialchars($name) ?></h4>
                                        <div class="menu-price"><?= $price ?> ₽</div>
                                    </div>
                                    <p class="menu-item-desc"><?= htmlspecialchars($desc) ?></p>
                                    <div class="menu-actions">
                                        <button class="menu-add" type="button" data-add>Добавить</button>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>

        <button class="menu-cart-fab" type="button" data-cart>
            Корзина <span class="menu-cart-badge" data-cart-count>0</span>
        </button>
    </div>

    <script>
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

            function updateCartBadge() {
                const badge = root.querySelector("[data-cart-count]");
                if (!badge) return;
                const cart = loadCart();
                badge.textContent = String(cartCount(cart));
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

            root.addEventListener("click", (e) => {
                const addBtn = e.target.closest("[data-add]");
                if (!addBtn) return;
                const card = addBtn.closest("[data-item]");
                if (!card) return;

                const key = card.getAttribute("data-key");
                const name = card.getAttribute("data-name");
                const price = Number(card.getAttribute("data-price") || 0);
                if (!key || !name) return;

                const cart = loadCart();
                const existing = cart.items[key] || { key, name, price, qty: 0 };
                existing.qty = (existing.qty || 0) + 1;
                cart.items[key] = existing;
                saveCart(cart);
                updateCartBadge();

                addBtn.textContent = "Добавлено";
                clearTimeout(addBtn._t);
                addBtn._t = setTimeout(() => (addBtn.textContent = "Добавить в корзину"), 900);
            });

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

            root.querySelector("[data-cart]")?.addEventListener("click", () => {
                const cart = loadCart();
                const items = Object.values(cart.items);
                if (!items.length) {
                    alert("Корзина пустая");
                    return;
                }
                const lines = items
                    .map((it) => `${it.name} × ${it.qty} — ${it.price * it.qty} ₽`)
                    .join("\n");
                const total = items.reduce((sum, it) => sum + it.price * it.qty, 0);
                alert(`${lines}\n\nИтого: ${total} ₽`);
            });

            updateCartBadge();
        })();
    </script>
</section>