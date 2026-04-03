<section class="screen menu">
    <?php
    require_once dirname(__DIR__) . "/inc/mirai_asset.php";
    require_once dirname(__DIR__) . "/inc/mirai_table_session.php";
    $miraiMenuTable = mirai_table_read_session();

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
            <div class="menu-topbar-row">
                <h2 class="menu-title">Меню</h2>
                <?php if ($miraiMenuTable !== null): ?>
                    <div class="menu-table-pill-wrap">
                        <div class="menu-table-pill" title="Стол из QR"><?= htmlspecialchars($miraiMenuTable["caption"], ENT_QUOTES, "UTF-8") ?></div>
                    </div>
                <?php endif; ?>
                <button type="button" class="menu-nav-home" data-menu-go-home aria-label="На главную">
                    <svg class="menu-nav-home-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                </button>
            </div>
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
                            $descShort = (string)($item["description_short"] ?? "");
                            $descMini = $descShort !== "" ? $descShort : $desc;
                            $price = (int)($item["price"] ?? 0);
                            $weight = (string)($item["weight"] ?? "");
                            $img = (string)($item["image"] ?? "");
                            $key = (string)($item["id"] ?? ($cat["id"] . "::" . $name));
                            $imgUrl = $img !== "" ? mirai_asset($img) : "";
                            ?>
                            <article class="menu-card menu-mini" data-item data-key="<?= htmlspecialchars($key) ?>" data-name="<?= htmlspecialchars($name) ?>" data-price="<?= $price ?>" data-description="<?= htmlspecialchars($desc) ?>" data-image="<?= htmlspecialchars($imgUrl, ENT_QUOTES, "UTF-8") ?>" tabindex="0" role="button">
                                <?php if ($img !== ""): ?>
                                <img class="menu-photo" src="<?= htmlspecialchars($imgUrl, ENT_QUOTES, "UTF-8") ?>" alt="<?= htmlspecialchars($name) ?>" loading="lazy">
                                <?php endif; ?>
                                <div class="menu-info">
                                    <div class="menu-card-head">
                                        <h4 class="menu-item-name"><?= htmlspecialchars($name) ?></h4>
                                    </div>
                                    <?php if ($descMini !== ""): ?>
                                    <p class="menu-item-desc"><?= htmlspecialchars($descMini) ?></p>
                                    <?php endif; ?>
                                    <div class="menu-actions">
                                        <div class="menu-price-row">
                                            <span class="menu-price"><?= $price ?> ₽</span>
                                            <?php if ($weight !== ""): ?>
                                            <span class="menu-weight"><?= htmlspecialchars($weight) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <button class="menu-add" type="button" data-add>Добавить</button>
                                        <div class="menu-qty" data-qty hidden>
                                            <button class="menu-qty-btn" type="button" data-minus aria-label="Уменьшить"><svg class="menu-qty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/></svg></button>
                                            <div class="menu-qty-count"><svg viewBox="0 0 48 24" class="menu-qty-num-svg"><text x="24" y="12" text-anchor="middle" dominant-baseline="central" font-family="Helvetica, Arial, sans-serif" font-size="16" font-weight="700" fill="rgba(255,255,255,0.92)" data-qty-count>1</text></svg></div>
                                            <button class="menu-qty-btn" type="button" data-plus aria-label="Увеличить"><svg class="menu-qty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></button>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>

        <button class="menu-cart-clear" type="button" data-clear-cart hidden>
            Очистить
        </button>

        <button class="menu-cart-tab" type="button" data-cart-toggle aria-label="Корзина">
            <div class="menu-cart-handle"></div>
            <div class="menu-cart-tab-label">Корзина
                <svg class="menu-cart-badge menu-cart-badge--empty" viewBox="0 0 56 32" width="56" height="32" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="В корзине нет позиций">
                    <rect class="menu-cart-badge-bg" x="0.5" y="0.5" width="55" height="31" rx="15.5"/>
                    <text class="menu-cart-badge-text" x="28" y="13" text-anchor="middle" dominant-baseline="central" data-cart-count>0</text>
                </svg>
            </div>
        </button>

        <div class="menu-item-viewer-backdrop" data-item-viewer-backdrop hidden></div>
        <div class="menu-item-viewer" data-item-viewer hidden aria-hidden="true">
            <div class="menu-item-viewer-inner">
                <button class="menu-item-viewer-close" type="button" data-item-viewer-close aria-label="Закрыть">✕</button>
                <div class="menu-item-viewer-photo" data-item-viewer-photo></div>
                <div class="menu-item-viewer-body">
                    <h3 class="menu-item-viewer-name" data-item-viewer-name></h3>
                    <p class="menu-item-viewer-desc" data-item-viewer-desc></p>
                    <div class="menu-item-viewer-foot">
                        <div class="menu-item-viewer-price" data-item-viewer-price></div>
                        <button class="menu-add" type="button" data-item-viewer-add>Добавить</button>
                        <div class="menu-qty" data-item-viewer-qty hidden>
                            <button class="menu-qty-btn" type="button" data-item-viewer-minus aria-label="Уменьшить"><svg class="menu-qty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/></svg></button>
                            <div class="menu-qty-count"><svg viewBox="0 0 48 24" class="menu-qty-num-svg"><text x="24" y="12" text-anchor="middle" dominant-baseline="central" font-family="Helvetica, Arial, sans-serif" font-size="16" font-weight="700" fill="rgba(255,255,255,0.92)" data-item-viewer-count>1</text></svg></div>
                            <button class="menu-qty-btn" type="button" data-item-viewer-plus aria-label="Увеличить"><svg class="menu-qty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="menu-cart-backdrop" data-cart-backdrop hidden></div>
        <aside class="menu-cart-sheet" data-cart-sheet aria-hidden="true">
            <div class="menu-cart-sheet-top" data-cart-close aria-label="Закрыть корзину">
                <div class="menu-cart-handle"></div>
                <div class="menu-cart-head">
                    <div class="menu-cart-title">Корзина</div>
                </div>
            </div>

            <div class="menu-cart-body" data-cart-body>
                <!-- items rendered by JS -->
            </div>

            <div class="menu-cart-foot">
                <div class="menu-cart-order-hint" data-order-hint hidden>Сканируйте QR на столе, чтобы отправлять заказ.</div>
                <div class="menu-cart-total">
                    <span class="menu-cart-total-label">Итого</span>
                    <span class="menu-cart-total-value" data-cart-total>0 ₽</span>
                </div>
                <div class="menu-cart-actions">
                    <button class="menu-cart-action menu-cart-action--submit" type="button" data-submit-order hidden>Отправить заказ</button>
                    <button class="menu-cart-action" type="button" data-clear-cart>Очистить</button>
                </div>
            </div>
        </aside>
    </div>

</section>