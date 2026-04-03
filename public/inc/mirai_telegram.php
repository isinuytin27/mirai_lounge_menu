<?php
declare(strict_types=1);

/**
 * Отправка в Telegram. Ошибки пишутся в PHP error_log (docker: docker compose logs php).
 */

function mirai_telegram_network_hint(string $message): string
{
    $m = strtolower($message);
    if (
        str_contains($m, "timed out")
        || str_contains($m, "timeout")
        || str_contains($m, "connection refused")
        || str_contains($m, "could not resolve")
        || str_contains($m, "failed to connect")
    ) {
        return " С сервера нет доступа до api.telegram.org (фаервол, блокировка Telegram, нет исходящего HTTPS)."
            . " Проверьте с хоста: curl -I https://api.telegram.org"
            . " или задайте в .env MIRAI_TG_HTTP_PROXY (HTTP-прокси до интернета).";
    }
    return "";
}

function mirai_telegram_send(string $text): bool
{
    /** @var array $cfg */
    $cfg = require dirname(__DIR__, 2) . "/config/config.php";
    $tg = $cfg["telegram"] ?? [];
    if (!is_array($tg)) {
        error_log("mirai telegram: нет секции telegram в config");
        return false;
    }
    $token = trim((string)($tg["bot_token"] ?? ""));
    $chatId = trim((string)($tg["chat_id"] ?? ""));
    $proxy = trim((string)($tg["http_proxy"] ?? ""));
    if ($token === "" || $chatId === "") {
        error_log("mirai telegram: пустой bot_token или chat_id");
        return false;
    }
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $payload = [
        "chat_id" => $chatId,
        "text" => $text,
        "disable_web_page_preview" => true,
    ];
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        error_log("mirai telegram: json_encode payload failed");
        return false;
    }

    $root = dirname(__DIR__, 2);
    $autoload = $root . "/vendor/autoload.php";
    if (is_file($autoload)) {
        require_once $autoload;
        try {
            $clientOpts = [
                "timeout" => 25,
                "connect_timeout" => 15,
                "http_errors" => false,
            ];
            if ($proxy !== "") {
                $clientOpts["proxy"] = $proxy;
            }
            $client = new GuzzleHttp\Client($clientOpts);
            $response = $client->post($url, [
                "headers" => ["Content-Type" => "application/json; charset=UTF-8"],
                "body" => $body,
            ]);
            $raw = (string)$response->getBody();
            return mirai_telegram_parse_response($raw, (int)$response->getStatusCode());
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            error_log("mirai telegram Guzzle: " . $msg . mirai_telegram_network_hint($msg));
        }
    }

    if (extension_loaded("curl")) {
        $ch = curl_init($url);
        if ($ch !== false) {
            $curlOpts = [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ["Content-Type: application/json; charset=UTF-8"],
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_CONNECTTIMEOUT => 15,
            ];
            if ($proxy !== "") {
                $curlOpts[CURLOPT_PROXY] = $proxy;
                $curlOpts[CURLOPT_HTTPPROXYTUNNEL] = true;
            }
            curl_setopt_array($ch, $curlOpts);
            $raw = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $cerr = curl_error($ch);
            curl_close($ch);
            if ($raw !== false && $raw !== "") {
                return mirai_telegram_parse_response((string)$raw, $code > 0 ? $code : 0);
            }
            $log = $cerr !== "" ? $cerr : "пустой ответ";
            error_log("mirai telegram cURL: " . $log . mirai_telegram_network_hint($log));
            return false;
        }
    }

    if ($proxy !== "") {
        error_log("mirai telegram: задан MIRAI_TG_HTTP_PROXY — нужен cURL/Guzzle; file_get_contents прокси к Telegram не использует.");
        return false;
    }

    if (!filter_var(ini_get("allow_url_fopen"), FILTER_VALIDATE_BOOLEAN)) {
        error_log("mirai telegram: allow_url_fopen выключен; установите расширение curl или включите allow_url_fopen.");
        return false;
    }

    $ctx = stream_context_create([
        "http" => [
            "method" => "POST",
            "header" => "Content-Type: application/json; charset=UTF-8\r\n",
            "content" => $body,
            "timeout" => 25,
            "ignore_errors" => true,
        ],
        "ssl" => [
            "verify_peer" => true,
            "verify_peer_name" => true,
        ],
    ]);
    $res = @file_get_contents($url, false, $ctx);
    if ($res === false) {
        error_log("mirai telegram: нет ответа (file_get_contents)." . mirai_telegram_network_hint("connection timed out"));
        return false;
    }
    return mirai_telegram_parse_response($res, 200);
}

function mirai_telegram_parse_response(string $raw, int $httpStatus): bool
{
    $j = json_decode($raw, true);
    if (is_array($j) && !empty($j["ok"])) {
        return true;
    }
    $desc = is_array($j) ? (string)($j["description"] ?? json_encode($j, JSON_UNESCAPED_UNICODE)) : $raw;
    error_log("mirai telegram API fail HTTP={$httpStatus}: {$desc}");
    return false;
}

function mirai_telegram_order_event(
    string $orderId,
    string $tableCaption,
    bool $isAppend,
    array $newItems
): bool {
    $title = $isAppend ? "Дозаказ" : "Новый заказ";
    $lines = ["🔔 {$title}", "", "№ {$orderId}", "Стол: {$tableCaption}"];
    $lines[] = "";
    foreach ($newItems as $it) {
        if (!is_array($it)) {
            continue;
        }
        $n = (string)($it["name"] ?? "");
        $q = (int)($it["qty"] ?? 0);
        $ln = (string)($it["line"] ?? "");
        $tag = match ($ln) {
            "hookah" => "кальян",
            "bar" => "бар",
            default => "кухня",
        };
        $lines[] = "• {$n} × {$q} ({$tag})";
    }
    $lines[] = "";
    $lines[] = "Откройте панель заказов на сайте.";
    return mirai_telegram_send(implode("\n", $lines));
}
