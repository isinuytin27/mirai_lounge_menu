<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["ok" => false, "error" => "method"], JSON_UNESCAPED_UNICODE);
    exit;
}

session_start();

$now = time();
$last = (int)($_SESSION["mirai_order_last_ts"] ?? 0);
if ($last > 0 && $now - $last < 2) {
    http_response_code(429);
    echo json_encode(["ok" => false, "error" => "too_fast"], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once dirname(__DIR__) . "/inc/mirai_table_session.php";
require_once dirname(__DIR__) . "/inc/mirai_menu_public.php";
require_once dirname(__DIR__) . "/inc/mirai_orders.php";
require_once dirname(__DIR__) . "/inc/mirai_telegram.php";
require_once dirname(__DIR__) . "/inc/mirai_push.php";

$tableSession = mirai_table_read_session();
if ($tableSession === null) {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "no_table"], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents("php://input");
$body = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "bad_json"], JSON_UNESCAPED_UNICODE);
    exit;
}

$itemsIn = $body["items"] ?? null;
if (!is_array($itemsIn) || $itemsIn === []) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "empty_items"], JSON_UNESCAPED_UNICODE);
    exit;
}

$resolved = [];
foreach ($itemsIn as $row) {
    if (!is_array($row)) {
        continue;
    }
    $pid = trim((string)($row["id"] ?? $row["product_id"] ?? ""));
    $qty = (int)($row["qty"] ?? 0);
    if ($pid === "" || $qty < 1 || $qty > 99) {
        continue;
    }
    $p = mirai_menu_public_find_visible_product($pid);
    if ($p === null) {
        continue;
    }
    $resolved[] = [
        "product_id" => $p["id"],
        "name" => $p["name"],
        "qty" => $qty,
        "price" => $p["price"],
        "line" => $p["line"],
        "category_id" => $p["category_id"],
    ];
}

if ($resolved === []) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "no_valid_items"], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $result = mirai_orders_submit(
        $tableSession["table_id"],
        $tableSession["caption"],
        $resolved
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "server"], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($result["ok"])) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => $result["error"] ?? "submit"], JSON_UNESCAPED_UNICODE);
    exit;
}

$_SESSION["mirai_order_last_ts"] = $now;

$orderId = (string)($result["order_id"] ?? "");
$append = !empty($result["append"]);
$newItems = is_array($result["new_items"] ?? null) ? $result["new_items"] : [];

$telegramOk = mirai_telegram_order_event(
    $orderId,
    $tableSession["caption"],
    $append,
    $newItems
);

$pushTitle = $append ? "Дозаказ" : "Новый заказ";
$pushBody = "{$orderId} · {$tableSession["caption"]}";
mirai_push_notify_order($pushTitle, $pushBody, "/orders/?id=" . rawurlencode($orderId));

echo json_encode(
    [
        "ok" => true,
        "order_id" => $orderId,
        "append" => $append,
        "telegram_ok" => $telegramOk,
    ],
    JSON_UNESCAPED_UNICODE
);
