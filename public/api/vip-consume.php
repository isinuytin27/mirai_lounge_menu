<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["ok" => false, "error" => "method"], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once dirname(__DIR__, 2) . "/admin/lib/auth.php";
require_once dirname(__DIR__, 2) . "/admin/lib/vip_storage.php";

admin_start_session();
if (!admin_is_logged_in()) {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "auth"], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents("php://input");
$body = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "bad_json"], JSON_UNESCAPED_UNICODE);
    exit;
}

$slug = trim((string)($body["event_slug"] ?? ""));
$token = trim((string)($body["token"] ?? ""));
$productId = trim((string)($body["product_id"] ?? ""));
$paidByGuest = !empty($body["paid_by_guest"]);

if ($slug === "" || $token === "" || $productId === "") {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "fields"], JSON_UNESCAPED_UNICODE);
    exit;
}

$event = vip_storage_find_event_by_slug($slug);
if ($event === null) {
    http_response_code(404);
    echo json_encode(["ok" => false, "error" => "event"], JSON_UNESCAPED_UNICODE);
    exit;
}

$bundle = vip_storage_find_guest_for_event($event, $token, null);
if ($bundle === null) {
    http_response_code(404);
    echo json_encode(["ok" => false, "error" => "guest"], JSON_UNESCAPED_UNICODE);
    exit;
}

$guest = $bundle["guest"];
$idx = (int)$bundle["index"];
require_once dirname(__DIR__) . "/inc/mirai_menu_public.php";
$p = mirai_menu_public_find_visible_product($productId);
if ($p === null) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "product"], JSON_UNESCAPED_UNICODE);
    exit;
}

$name = (string)($p["name"] ?? $productId);
$result = vip_storage_consume($event, $guest, $idx, $productId, $name, $paidByGuest);

if (empty($result["ok"])) {
    $err = (string)($result["error"] ?? "fail");
    $code = match ($err) {
        "limit_reached" => 409,
        "not_bar" => 400,
        default => 400,
    };
    http_response_code($code);
    echo json_encode(["ok" => false, "error" => $err], JSON_UNESCAPED_UNICODE);
    exit;
}

$g = $result["guest"];
$limit = (int)($event["bar_free_limit"] ?? 2);
$used = (int)($g["free_used"] ?? 0);

echo json_encode(
    [
        "ok" => true,
        "free_used" => $used,
        "free_left" => max(0, $limit - $used),
        "guest" => [
            "free_used" => $used,
            "lines" => $g["lines"] ?? [],
        ],
    ],
    JSON_UNESCAPED_UNICODE
);
