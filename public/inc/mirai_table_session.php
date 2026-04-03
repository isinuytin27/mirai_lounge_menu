<?php
declare(strict_types=1);

require_once __DIR__ . "/mirai_gallery_public.php";

function mirai_table_config(): array
{
    /** @var array $cfg */
    $cfg = require dirname(__DIR__, 2) . "/config/config.php";
    $sec = $cfg["orders_security"] ?? [];
    if (!is_array($sec)) {
        $sec = [];
    }
    return [
        "cookie_name" => (string)($sec["table_cookie_name"] ?? "mirai_table"),
        "ttl" => max(3600, (int)($sec["table_cookie_ttl_seconds"] ?? 28800)),
        "signing_key" => (string)($sec["signing_key"] ?? ""),
    ];
}

function mirai_table_b64url_encode(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), "+/", "-_"), "=");
}

function mirai_table_b64url_decode(string $b64): string|false
{
    $pad = strlen($b64) % 4;
    if ($pad) {
        $b64 .= str_repeat("=", 4 - $pad);
    }
    $b64 = strtr($b64, "-_", "+/");
    return base64_decode($b64, true);
}

/**
 * Установить cookie сессии стола (после валидации id из галереи).
 */
function mirai_table_set_cookie(string $tableId, string $caption): void
{
    $c = mirai_table_config();
    if ($c["signing_key"] === "") {
        return;
    }
    $exp = time() + $c["ttl"];
    $payload = [
        "tid" => $tableId,
        "cap" => $caption,
        "exp" => $exp,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return;
    }
    $body = mirai_table_b64url_encode($json);
    $sig = hash_hmac("sha256", $body, $c["signing_key"], false);
    $token = $body . "." . $sig;

    $https = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
        || (string)($_SERVER["SERVER_PORT"] ?? "") === "443";

    setcookie($c["cookie_name"], $token, [
        "expires" => $exp,
        "path" => "/",
        "secure" => $https,
        "httponly" => true,
        "samesite" => "Lax",
    ]);
}

/**
 * Обработка ?table= из QR: валидный id → cookie и редирект без query.
 */
function mirai_table_handle_query_param(): void
{
    $q = $_GET["table"] ?? null;
    if (!is_string($q)) {
        return;
    }
    $q = trim($q);
    if ($q === "") {
        return;
    }
    $found = mirai_gallery_public_find_table($q);
    if ($found === null) {
        return;
    }
    mirai_table_set_cookie($found["id"], $found["caption"]);
    $path = parse_url((string)($_SERVER["REQUEST_URI"] ?? "/"), PHP_URL_PATH);
    if (!is_string($path) || $path === "") {
        $path = "/";
    }
    header("Location: " . $path, true, 302);
    exit;
}

/**
 * @return array{table_id: string, caption: string}|null
 */
function mirai_table_read_session(): ?array
{
    $c = mirai_table_config();
    if ($c["signing_key"] === "") {
        return null;
    }
    $name = $c["cookie_name"];
    $token = (string)($_COOKIE[$name] ?? "");
    if ($token === "" || !str_contains($token, ".")) {
        return null;
    }
    $dot = strrpos($token, ".");
    if ($dot === false) {
        return null;
    }
    $body = substr($token, 0, $dot);
    $sig = substr($token, $dot + 1);
    if ($body === "" || $sig === "" || !hash_equals(hash_hmac("sha256", $body, $c["signing_key"], false), $sig)) {
        return null;
    }
    $json = mirai_table_b64url_decode($body);
    if ($json === false) {
        return null;
    }
    $payload = json_decode($json, true);
    if (!is_array($payload)) {
        return null;
    }
    $exp = (int)($payload["exp"] ?? 0);
    if ($exp < time()) {
        return null;
    }
    $tid = trim((string)($payload["tid"] ?? ""));
    $cap = trim((string)($payload["cap"] ?? ""));
    if ($tid === "") {
        return null;
    }
    if ($cap === "") {
        $cap = $tid;
    }
    $still = mirai_gallery_public_find_table($tid);
    if ($still === null) {
        return null;
    }
    return ["table_id" => $still["id"], "caption" => $still["caption"]];
}
