<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/../admin/lib/auth.php";
require_once dirname(__DIR__) . "/inc/mirai_orders.php";
require_once dirname(__DIR__) . "/inc/mirai_asset.php";

admin_start_session();

if (!admin_is_logged_in()) {
    header("Location: /orders/login.php?next=" . rawurlencode($_SERVER["REQUEST_URI"] ?? "/orders/"));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = (string)($_POST["action"] ?? "");
    if ($action === "close") {
        $oid = trim((string)($_POST["order_id"] ?? ""));
        mirai_orders_close($oid);
        header("Location: /orders/?id=" . rawurlencode($oid));
        exit;
    }
}

/** @var array $cfg */
$cfg = require dirname(__DIR__, 2) . "/config/config.php";
$wp = $cfg["webpush"] ?? [];
$vapidPublic = is_array($wp) ? trim((string)($wp["public_key"] ?? "")) : "";

$viewId = trim((string)($_GET["id"] ?? ""));
$order = $viewId !== "" ? mirai_orders_get($viewId) : null;
$allOrders = mirai_orders_list_for_staff();

function orders_fmt_time(string $iso): string
{
    $t = strtotime($iso);
    if ($t === false) {
        return $iso;
    }
    return date("d.m.Y H:i", $t);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0a0a0a">
    <?php if ($vapidPublic !== ""): ?>
        <meta name="mirai-vapid-public" content="<?= htmlspecialchars($vapidPublic, ENT_QUOTES, "UTF-8") ?>">
    <?php endif; ?>
    <title>Заказы — Mirai Lounge</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="stylesheet" href="<?= htmlspecialchars(mirai_asset("assets/css/orders.css"), ENT_QUOTES, "UTF-8") ?>">
</head>
<body class="orders-page">
    <div class="orders-shell">
        <header class="orders-topbar">
            <h1>Заказы</h1>
            <nav class="orders-links" aria-label="Навигация">
                <a href="/admin/dashboard.php">Админка</a>
                <a href="/orders/logout.php">Выйти</a>
            </nav>
        </header>

        <?php if ($vapidPublic !== ""): ?>
            <p class="orders-lead orders-lead--push">
                <button type="button" class="orders-btn orders-btn--primary orders-btn--small" id="orders-push-btn">Включить push-уведомления</button>
                <span id="orders-push-status" class="orders-push-status" aria-live="polite"></span>
            </p>
        <?php endif; ?>

        <?php if ($order === null): ?>
            <?php if ($viewId !== ""): ?>
                <div class="orders-flash orders-flash--err">Заказ не найден.</div>
            <?php endif; ?>

            <?php if ($allOrders === []): ?>
                <p class="orders-lead">Пока нет заказов.</p>
            <?php else: ?>
                <ul class="orders-list">
                    <?php foreach ($allOrders as $o): ?>
                        <?php
                        if (!is_array($o)) {
                            continue;
                        }
                        $oid = (string)($o["id"] ?? "");
                        $cap = (string)($o["table_caption"] ?? "");
                        $st = (string)($o["status"] ?? "");
                        $upd = (string)($o["updated_at"] ?? $o["created_at"] ?? "");
                        $items = $o["items"] ?? [];
                        $n = is_array($items) ? count($items) : 0;
                        ?>
                        <li>
                            <a class="orders-card" href="/orders/?id=<?= htmlspecialchars($oid, ENT_QUOTES, "UTF-8") ?>">
                                <strong><?= htmlspecialchars($cap, ENT_QUOTES, "UTF-8") ?></strong>
                                <span class="orders-card-meta">
                                    <span class="orders-badge <?= $st === "open" ? "orders-badge--open" : "orders-badge--closed" ?>"><?= $st === "open" ? "Открыт" : "Закрыт" ?></span>
                                    <span><?= htmlspecialchars($oid, ENT_QUOTES, "UTF-8") ?></span>
                                    <span><?= htmlspecialchars((string)$n, ENT_QUOTES, "UTF-8") ?> поз.</span>
                                    <span><?= htmlspecialchars(orders_fmt_time($upd), ENT_QUOTES, "UTF-8") ?></span>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php else: ?>
            <?php
            $oid = (string)($order["id"] ?? "");
            $cap = (string)($order["table_caption"] ?? "");
            $st = (string)($order["status"] ?? "");
            $groups = mirai_orders_group_by_line($order);
            $kitchenText = mirai_orders_kitchen_text($order);
            ?>
            <a class="orders-back" href="/orders/">← Все заказы</a>
            <div class="orders-detail-head">
                <h2><?= htmlspecialchars($cap, ENT_QUOTES, "UTF-8") ?></h2>
                <div class="orders-card-meta">
                    <span class="orders-badge <?= $st === "open" ? "orders-badge--open" : "orders-badge--closed" ?>"><?= $st === "open" ? "Открыт" : "Закрыт" ?></span>
                    <span><?= htmlspecialchars($oid, ENT_QUOTES, "UTF-8") ?></span>
                    <span><?= htmlspecialchars(orders_fmt_time((string)($order["updated_at"] ?? $order["created_at"] ?? "")), ENT_QUOTES, "UTF-8") ?></span>
                </div>
            </div>

            <?php
            $labels = ["hookah" => "Кальян", "bar" => "Бар", "kitchen" => "Кухня"];
            foreach ($labels as $key => $label):
                $rows = $groups[$key] ?? [];
                if ($rows === []) {
                    continue;
                }
                ?>
                <section class="orders-section">
                    <h3><?= htmlspecialchars($label, ENT_QUOTES, "UTF-8") ?></h3>
                    <ul class="orders-lines">
                        <?php foreach ($rows as $it): ?>
                            <?php if (!is_array($it)) {
                                continue;
                            } ?>
                            <li><?= htmlspecialchars((string)($it["name"] ?? ""), ENT_QUOTES, "UTF-8") ?> × <?= (int)($it["qty"] ?? 0) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>

            <section class="orders-section">
                <h3>Текст для кухни (Telegram)</h3>
                <div class="orders-kitchen-box" id="orders-kitchen-text"><?= htmlspecialchars($kitchenText, ENT_QUOTES, "UTF-8") ?></div>
                <div class="orders-row-actions">
                    <button type="button" class="orders-btn orders-btn--primary orders-btn--small" id="orders-copy-kitchen">Скопировать</button>
                </div>
            </section>

            <?php if ($st === "open"): ?>
                <form class="orders-row-actions" method="post" action="" onsubmit="return confirm('Закрыть заказ для этого стола? Новая отправка меню откроет новый заказ.');">
                    <input type="hidden" name="action" value="close">
                    <input type="hidden" name="order_id" value="<?= htmlspecialchars($oid, ENT_QUOTES, "UTF-8") ?>">
                    <button type="submit" class="orders-btn orders-btn--danger">Закрыть заказ</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script defer src="<?= htmlspecialchars(mirai_asset("assets/js/orders-page.js"), ENT_QUOTES, "UTF-8") ?>"></script>
</body>
</html>
