<?php
declare(strict_types=1);

function mirai_push_subscriptions_path(): string
{
    /** @var array $cfg */
    $cfg = require dirname(__DIR__, 2) . "/config/config.php";
    return (string)($cfg["storage"]["push_subscriptions_json_path"] ?? dirname(__DIR__, 2) . "/data/push_subscriptions.json");
}

function mirai_push_load_subscriptions(): array
{
    $path = mirai_push_subscriptions_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (!is_file($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === "") {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return [];
    }
    $subs = $data["subscriptions"] ?? [];
    return is_array($subs) ? $subs : [];
}

/**
 * @param array{endpoint: string, keys?: array{p256dh?: string, auth?: string}} $subJson
 */
function mirai_push_save_subscription(array $subJson): void
{
    $endpoint = trim((string)($subJson["endpoint"] ?? ""));
    if ($endpoint === "") {
        return;
    }
    $path = mirai_push_subscriptions_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $fh = fopen($path, "c+");
    if ($fh === false) {
        return;
    }
    try {
        if (!flock($fh, LOCK_EX)) {
            return;
        }
        rewind($fh);
        $raw = stream_get_contents($fh);
        $data = ["subscriptions" => [], "updated_at" => date("c")];
        if (is_string($raw) && trim($raw) !== "") {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
                $data["subscriptions"] = is_array($data["subscriptions"] ?? null) ? $data["subscriptions"] : [];
            }
        }
        $list = $data["subscriptions"];
        $filtered = [];
        foreach ($list as $s) {
            if (!is_array($s)) {
                continue;
            }
            if ((string)($s["endpoint"] ?? "") === $endpoint) {
                continue;
            }
            $filtered[] = $s;
        }
        $filtered[] = [
            "endpoint" => $endpoint,
            "keys" => is_array($subJson["keys"] ?? null) ? $subJson["keys"] : [],
            "updated_at" => date("c"),
        ];
        $data["subscriptions"] = $filtered;
        $data["updated_at"] = date("c");
        rewind($fh);
        ftruncate($fh, 0);
        fwrite($fh, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        fflush($fh);
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

function mirai_push_notify_order(string $title, string $body, string $relativeUrl = "/orders/"): void
{
    /** @var array $cfg */
    $cfg = require dirname(__DIR__, 2) . "/config/config.php";
    $wp = $cfg["webpush"] ?? [];
    if (!is_array($wp)) {
        return;
    }
    $pub = trim((string)($wp["public_key"] ?? ""));
    $priv = trim((string)($wp["private_key"] ?? ""));
    if ($pub === "" || $priv === "") {
        return;
    }
    $autoload = dirname(__DIR__, 2) . "/vendor/autoload.php";
    if (!is_file($autoload)) {
        return;
    }
    require_once $autoload;

    $subs = mirai_push_load_subscriptions();
    if ($subs === []) {
        return;
    }

    $https = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
        || (string)($_SERVER["SERVER_PORT"] ?? "") === "443";
    $host = (string)($_SERVER["HTTP_HOST"] ?? "");
    $click = ($https ? "https" : "http") . "://" . $host . $relativeUrl;

    $auth = [
        "VAPID" => [
            "subject" => (string)($wp["subject"] ?? "mailto:admin@mirailounge.ru"),
            "publicKey" => $pub,
            "privateKey" => $priv,
        ],
    ];

    $webPush = new Minishlink\WebPush\WebPush($auth);
    $payload = json_encode(
        ["title" => $title, "body" => $body, "url" => $click],
        JSON_UNESCAPED_UNICODE
    );
    if ($payload === false) {
        return;
    }

    foreach ($subs as $row) {
        if (!is_array($row)) {
            continue;
        }
        try {
            $enc = (string)($row["contentEncoding"] ?? "aes128gcm");
            if ($enc !== "aesgcm" && $enc !== "aes128gcm") {
                $enc = "aes128gcm";
            }
            $sub = Minishlink\WebPush\Subscription::create([
                "endpoint" => (string)($row["endpoint"] ?? ""),
                "keys" => [
                    "p256dh" => (string)($row["keys"]["p256dh"] ?? ""),
                    "auth" => (string)($row["keys"]["auth"] ?? ""),
                ],
                "contentEncoding" => $enc,
            ]);
            $webPush->queueNotification($sub, $payload);
        } catch (Throwable) {
            continue;
        }
    }
    foreach ($webPush->flush() as $report) {
        if (!$report->isSuccess()) {
            // тихо: подписка могла протухнуть
        }
    }
}
