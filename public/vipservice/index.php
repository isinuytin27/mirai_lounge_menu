<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/inc/mirai_asset.php";
require_once dirname(__DIR__) . "/inc/mirai_menu_public.php";
require_once dirname(__DIR__, 2) . "/admin/lib/auth.php";
require_once dirname(__DIR__, 2) . "/admin/lib/vip_storage.php";

header("Cache-Control: no-store, no-cache, must-revalidate");
header("X-Robots-Tag: noindex, nofollow");

vip_storage_ensure_seed_files();

/** Дата из поля type=date (YYYY-MM-DD) → ДД.ММ.ГГГГ */
function vip_display_event_date(string $raw): string
{
    $raw = trim($raw);
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
        return $m[3] . "." . $m[2] . "." . $m[1];
    }
    return $raw;
}

$reqPath = (string)(parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH) ?: "/");
if ($reqPath === "" || $reqPath[0] !== "/") {
    $reqPath = "/" . $reqPath;
}

$slug = "";
if (preg_match('#/vipservice/(?:index\.php)?/?([^/?]*)\/?$#', $reqPath, $m)) {
    $slug = trim((string)($m[1] ?? ""), "/");
}

$token = trim((string)($_GET["t"] ?? ""));
$gNum = isset($_GET["g"]) ? (int)$_GET["g"] : null;
if ($gNum !== null && $gNum < 1) {
    $gNum = null;
}

$event = $slug !== "" ? vip_storage_find_event_by_slug($slug) : null;
$guestBundle = null;
if ($event !== null) {
    $guestBundle = vip_storage_find_guest_for_event($event, $token !== "" ? $token : null, $gNum);
}

$staff = admin_is_logged_in();
$staffName = $staff ? (string)($_SESSION["admin_username"] ?? "") : "";

/** @var array<string,mixed>|null $guest */
$guest = $guestBundle["guest"] ?? null;

$guestFullName = "";
if (is_array($guest)) {
    $guestFullName = trim((string)($guest["last_name"] ?? "") . " " . (string)($guest["first_name"] ?? ""));
}

/** Путь и query для ?next= на login.php (только относительный путь с /) */
$loginNext = (string)($_SERVER["REQUEST_URI"] ?? "/vipservice/");
if ($loginNext === "" || ($loginNext[0] ?? "") !== "/") {
    $loginNext = "/vipservice/";
}

$bodyClass = "vip-page";
if ($event !== null && $guest !== null && $staff) {
    $bodyClass .= " vip-page--staff";
} elseif ($event !== null && $guest !== null && !$staff) {
    $bodyClass .= " vip-page--guest-welcome";
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $guestFullName !== "" ? htmlspecialchars($guestFullName, ENT_QUOTES, "UTF-8") . " · " : "" ?>VIP · Mirai Lounge</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(mirai_asset("assets/css/vip.css"), ENT_QUOTES, "UTF-8") ?>">
</head>
<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, "UTF-8") ?>">
    <div class="vip-shell">
        <header class="vip-head">
            <div class="vip-brand">Mirai Lounge</div>
            <div class="vip-tag">Корпоратив / бар</div>
        </header>

        <?php if ($slug === ""): ?>
            <div class="vip-card">
                <h1>Спецобслуживание</h1>
                <p class="vip-muted">Откройте ссылку с NFC-карты или выберите мероприятие в админке.</p>
                <p class="vip-muted"><a href="/admin/vip.php">Админ: мероприятия VIP</a></p>
            </div>
        <?php elseif ($event === null): ?>
            <div class="vip-card vip-card--warn">
                <h1>Мероприятие не найдено</h1>
                <p>Проверьте ссылку на карте.</p>
            </div>
        <?php elseif ($guest === null): ?>
            <div class="vip-card vip-card--warn">
                <h1>Гость не найден</h1>
                <p>Проверьте ссылку или параметры <code>t</code> / <code>g</code>.</p>
                <p class="vip-muted"><a href="/admin/vip.php">Админка VIP</a></p>
            </div>
        <?php elseif (!$staff): ?>
            <?php
            $limitGw = (int)($event["bar_free_limit"] ?? 2);
            $usedGw = (int)($guest["free_used"] ?? 0);
            $leftGw = max(0, $limitGw - $usedGw);
            $eventDateDisplay = vip_display_event_date((string)($event["event_date"] ?? ""));
            $mono = mb_substr(trim((string)($guest["last_name"] ?? "")), 0, 1, "UTF-8");
            if ($mono !== "") {
                $mono = mb_strtoupper($mono, "UTF-8");
            }
            $miraiLogoUrl = mirai_asset("assets/img/logo/logo-vert.svg");
            $partnerLogoRel = trim((string)($event["partner_logo"] ?? ""));
            $partnerLogoUrl = "";
            if ($partnerLogoRel !== "") {
                $partnerFs = dirname(__DIR__) . "/" . str_replace("\\", "/", $partnerLogoRel);
                if (is_file($partnerFs)) {
                    $partnerLogoUrl = mirai_asset($partnerLogoRel);
                }
            }
            $partnerAlt = trim((string)($event["organization"] ?? "")) !== ""
                ? "Партнёр: " . (string)($event["organization"] ?? "")
                : "Партнёр";
            ?>
            <div class="vip-welcome">
                <div class="vip-welcome-logos" aria-label="Mirai Lounge и партнёр">
                    <img
                        class="vip-welcome-logo vip-welcome-logo--mirai"
                        src="<?= htmlspecialchars($miraiLogoUrl, ENT_QUOTES, "UTF-8") ?>"
                        alt="Mirai Lounge"
                        decoding="async"
                        loading="lazy"
                    />
                    <?php if ($partnerLogoUrl !== ""): ?>
                        <span class="vip-welcome-logos-divider" aria-hidden="true"></span>
                        <img
                            class="vip-welcome-logo vip-welcome-logo--partner"
                            src="<?= htmlspecialchars($partnerLogoUrl, ENT_QUOTES, "UTF-8") ?>"
                            alt="<?= htmlspecialchars($partnerAlt, ENT_QUOTES, "UTF-8") ?>"
                            decoding="async"
                            loading="lazy"
                        />
                    <?php endif; ?>
                </div>
                <p class="vip-welcome-tagline">Корпоратив / бар</p>
                <div class="vip-welcome-glow" aria-hidden="true"></div>
                <div class="vip-welcome-card">
                    <?php if ($mono !== ""): ?>
                        <div class="vip-welcome-monogram" aria-hidden="true"><?= htmlspecialchars($mono, ENT_QUOTES, "UTF-8") ?></div>
                    <?php endif; ?>
                    <p class="vip-welcome-kicker">Добро пожаловать</p>
                    <h1 class="vip-welcome-name"><?= htmlspecialchars($guestFullName, ENT_QUOTES, "UTF-8") ?></h1>
                    <?php if (trim((string)($guest["organization"] ?? "")) !== ""): ?>
                        <p class="vip-welcome-org"><?= htmlspecialchars((string)($guest["organization"] ?? ""), ENT_QUOTES, "UTF-8") ?></p>
                    <?php endif; ?>
                    <ul class="vip-welcome-facts">
                        <li>
                            <span class="vip-welcome-fact-label">Мероприятие</span>
                            <span class="vip-welcome-fact-val"><?= htmlspecialchars((string)($event["organization"] ?? ""), ENT_QUOTES, "UTF-8") ?></span>
                        </li>
                        <li>
                            <span class="vip-welcome-fact-label">Дата</span>
                            <span class="vip-welcome-fact-val"><?= htmlspecialchars($eventDateDisplay, ENT_QUOTES, "UTF-8") ?></span>
                        </li>
                    </ul>
                    <div class="vip-welcome-privilege">
                        <span class="vip-welcome-privilege-title">Программа бара</span>
                        <p class="vip-welcome-privilege-text">
                            Включено бесплатных позиций по бару: <strong><?= $limitGw ?></strong>.
                            Сейчас осталось: <strong><?= $leftGw ?></strong>.
                        </p>
                    </div>
                    <p class="vip-welcome-hint">Заказ напитков оформляет персонал при предъявлении карты. Приятного вечера.</p>
                </div>
                <footer class="vip-welcome-foot">
                    <a class="vip-welcome-link" href="/">Сайт Mirai Lounge</a>
                    <a class="vip-welcome-link vip-welcome-link--subtle" href="/admin/login.php?next=<?= rawurlencode($loginNext) ?>">Вход для персонала</a>
                </footer>
            </div>
        <?php else: ?>
            <?php
            $limit = (int)($event["bar_free_limit"] ?? 2);
            $used = (int)($guest["free_used"] ?? 0);
            $left = max(0, $limit - $used);
            $eventDateStaff = vip_display_event_date((string)($event["event_date"] ?? ""));
            $lines = is_array($guest["lines"] ?? null) ? $guest["lines"] : [];
            $menu = mirai_menu_public_load();
            $barProducts = [];
            foreach ($menu["products"] as $p) {
                if (!is_array($p)) {
                    continue;
                }
                if (array_key_exists("visible", $p) && !$p["visible"]) {
                    continue;
                }
                $cid = (string)($p["category_id"] ?? "");
                require_once dirname(__DIR__) . "/inc/mirai_menu_line.php";
                if (mirai_menu_line_for_category($cid) !== (string)($event["bar_line"] ?? "bar")) {
                    continue;
                }
                $barProducts[] = $p;
            }
            ?>
            <div class="vip-card vip-card--guest" data-vip-root
                data-event-slug="<?= htmlspecialchars($slug, ENT_QUOTES, "UTF-8") ?>"
                data-token="<?= htmlspecialchars((string)($guest["token"] ?? ""), ENT_QUOTES, "UTF-8") ?>">
                <h1 class="vip-guest-name"><?= htmlspecialchars(trim((string)($guest["last_name"] ?? "") . " " . (string)($guest["first_name"] ?? "")), ENT_QUOTES, "UTF-8") ?></h1>
                <p class="vip-org"><?= htmlspecialchars((string)($guest["organization"] ?? ""), ENT_QUOTES, "UTF-8") ?></p>
                <dl class="vip-meta">
                    <div><dt>Мероприятие</dt><dd><?= htmlspecialchars((string)($event["organization"] ?? ""), ENT_QUOTES, "UTF-8") ?></dd></div>
                    <div><dt>Дата</dt><dd><?= htmlspecialchars($eventDateStaff, ENT_QUOTES, "UTF-8") ?></dd></div>
                    <div><dt>Лимит бара (бесплатно)</dt><dd><?= (int)($event["bar_free_limit"] ?? 2) ?> позиций</dd></div>
                    <div><dt>Осталось бесплатно</dt><dd><strong data-vip-left><?= $left ?></strong></dd></div>
                    <div><dt>Сотрудник</dt><dd><?= htmlspecialchars($staffName, ENT_QUOTES, "UTF-8") ?></dd></div>
                </dl>

                <section class="vip-actions">
                    <h2>Списать напиток (бар)</h2>
                    <p class="vip-muted">Сначала бесплатные позиции (остаток <?= $left ?>), сверх лимита — отметьте «за счёт гостя».</p>
                    <div class="vip-bar-grid">
                        <?php foreach ($barProducts as $p): ?>
                            <?php
                            $pid = (string)($p["id"] ?? "");
                            $pname = (string)($p["name"] ?? "");
                            $price = (int)($p["price"] ?? 0);
                            ?>
                            <div class="vip-bar-item">
                                <span><?= htmlspecialchars($pname, ENT_QUOTES, "UTF-8") ?></span>
                                <span class="vip-price"><?= $price ?> ₽</span>
                                <div class="vip-bar-btns">
                                    <button type="button" class="btn-vip" data-vip-free data-product-id="<?= htmlspecialchars($pid, ENT_QUOTES, "UTF-8") ?>" data-product-name="<?= htmlspecialchars($pname, ENT_QUOTES, "UTF-8") ?>" <?= $left < 1 ? "disabled" : "" ?>>Бесплатно</button>
                                    <button type="button" class="btn-vip btn-vip--paid" data-vip-paid data-product-id="<?= htmlspecialchars($pid, ENT_QUOTES, "UTF-8") ?>" data-product-name="<?= htmlspecialchars($pname, ENT_QUOTES, "UTF-8") ?>">За счёт гостя</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($barProducts === []): ?>
                        <p class="vip-muted">В меню нет позиций линии «бар» — проверьте категории в админке.</p>
                    <?php endif; ?>
                </section>

                <section class="vip-log">
                    <h2>Журнал</h2>
                    <ul class="vip-log-list" data-vip-log>
                        <?php foreach (array_reverse($lines) as $ln): ?>
                            <?php if (!is_array($ln)) {
                                continue;
                            } ?>
                            <li>
                                <?= htmlspecialchars((string)($ln["ts"] ?? ""), ENT_QUOTES, "UTF-8") ?>
                                — <?= htmlspecialchars((string)($ln["name"] ?? ""), ENT_QUOTES, "UTF-8") ?>
                                <?php if (!empty($ln["paid_by_guest"])): ?>
                                    <span class="vip-pill">за счёт гостя</span>
                                <?php else: ?>
                                    <span class="vip-pill vip-pill--free">бесплатно</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>

                <p class="vip-footer"><a href="/admin/vip.php">Настройки VIP</a> · <a href="/admin/dashboard.php">Админка</a></p>
            </div>
            <script type="application/json" id="vip-page-meta"><?= json_encode(["slug" => $slug, "token" => (string)($guest["token"] ?? "")], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
            <script defer src="<?= htmlspecialchars(mirai_asset("assets/js/vip.js"), ENT_QUOTES, "UTF-8") ?>"></script>
        <?php endif; ?>
    </div>
</body>
</html>
