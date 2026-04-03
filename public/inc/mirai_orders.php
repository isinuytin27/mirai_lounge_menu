<?php
declare(strict_types=1);

function mirai_orders_json_path(): string
{
    /** @var array $cfg */
    $cfg = require dirname(__DIR__, 2) . "/config/config.php";
    return (string)($cfg["storage"]["orders_json_path"] ?? dirname(__DIR__, 2) . "/data/orders.json");
}

function mirai_orders_ensure_dir(): void
{
    $path = mirai_orders_json_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

function mirai_orders_default_data(): array
{
    return ["orders" => [], "updated_at" => date("c")];
}

function mirai_orders_load_unlocked(string $path): array
{
    if (!is_file($path)) {
        return mirai_orders_default_data();
    }
    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === "") {
        return mirai_orders_default_data();
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return mirai_orders_default_data();
    }
    $data["orders"] = is_array($data["orders"] ?? null) ? $data["orders"] : [];
    return $data;
}

function mirai_orders_save_unlocked(string $path, array $data): void
{
    $data["updated_at"] = date("c");
    file_put_contents(
        $path,
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

/**
 * @template T
 * @param callable(array): T $fn
 * @return T
 */
function mirai_orders_with_lock(callable $fn)
{
    mirai_orders_ensure_dir();
    $path = mirai_orders_json_path();
    $fh = fopen($path, "c+");
    if ($fh === false) {
        throw new RuntimeException("orders file");
    }
    try {
        if (!flock($fh, LOCK_EX)) {
            throw new RuntimeException("orders lock");
        }
        rewind($fh);
        $raw = stream_get_contents($fh);
        $data = mirai_orders_default_data();
        if (is_string($raw) && trim($raw) !== "") {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
                $data["orders"] = is_array($data["orders"] ?? null) ? $data["orders"] : [];
            }
        }
        $result = $fn($data);
        rewind($fh);
        ftruncate($fh, 0);
        $data["updated_at"] = date("c");
        fwrite($fh, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        fflush($fh);
        return $result;
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

function mirai_orders_new_id(): string
{
    return "ord_" . bin2hex(random_bytes(5));
}

/**
 * @param list<array{product_id: string, name: string, qty: int, price: int, line: string, category_id: string}> $newItems
 * @return array{ok: bool, order_id?: string, append?: bool, new_items?: array, error?: string}
 */
function mirai_orders_submit(string $tableId, string $tableCaption, array $newItems): array
{
    if ($newItems === []) {
        return ["ok" => false, "error" => "empty_items"];
    }
    $now = date("c");
    $meta = mirai_orders_with_lock(function (array &$data) use ($tableId, $tableCaption, $newItems, $now) {
        $orders = &$data["orders"];
        $openIdx = -1;
        foreach ($orders as $i => $o) {
            if (!is_array($o)) {
                continue;
            }
            if ((string)($o["table_id"] ?? "") !== $tableId) {
                continue;
            }
            if ((string)($o["status"] ?? "") !== "open") {
                continue;
            }
            $openIdx = (int)$i;
            break;
        }
        $rows = [];
        foreach ($newItems as $it) {
            $rows[] = [
                "product_id" => (string)$it["product_id"],
                "name" => (string)$it["name"],
                "qty" => (int)$it["qty"],
                "price" => (int)$it["price"],
                "line" => (string)$it["line"],
                "category_id" => (string)$it["category_id"],
                "added_at" => $now,
            ];
        }
        if ($openIdx >= 0) {
            $items = $orders[$openIdx]["items"] ?? [];
            if (!is_array($items)) {
                $items = [];
            }
            foreach ($rows as $r) {
                $items[] = $r;
            }
            $orders[$openIdx]["items"] = $items;
            $orders[$openIdx]["updated_at"] = $now;
            return [
                "order_id" => (string)($orders[$openIdx]["id"] ?? ""),
                "append" => true,
                "new_items" => $rows,
            ];
        }
        $id = mirai_orders_new_id();
        $orders[] = [
            "id" => $id,
            "table_id" => $tableId,
            "table_caption" => $tableCaption,
            "status" => "open",
            "created_at" => $now,
            "updated_at" => $now,
            "closed_at" => null,
            "items" => $rows,
        ];
        return [
            "order_id" => $id,
            "append" => false,
            "new_items" => $rows,
        ];
    });
    $meta["ok"] = true;
    return $meta;
}

/**
 * @return array{ok: bool, error?: string}
 */
function mirai_orders_close(string $orderId): array
{
    $orderId = trim($orderId);
    if ($orderId === "") {
        return ["ok" => false, "error" => "bad_id"];
    }
    $now = date("c");
    mirai_orders_with_lock(function (array &$data) use ($orderId, $now): void {
        foreach ($data["orders"] as &$o) {
            if (!is_array($o)) {
                continue;
            }
            if ((string)($o["id"] ?? "") !== $orderId) {
                continue;
            }
            $o["status"] = "closed";
            $o["closed_at"] = $now;
            $o["updated_at"] = $now;
            break;
        }
        unset($o);
    });
    return ["ok" => true];
}

/**
 * @return list<array>
 */
function mirai_orders_list_for_staff(): array
{
    mirai_orders_ensure_dir();
    $path = mirai_orders_json_path();
    $data = mirai_orders_load_unlocked($path);
    $orders = $data["orders"] ?? [];
    if (!is_array($orders)) {
        return [];
    }
    usort($orders, static function ($a, $b) {
        $ta = strtotime((string)(is_array($a) ? ($a["updated_at"] ?? $a["created_at"] ?? "") : ""));
        $tb = strtotime((string)(is_array($b) ? ($b["updated_at"] ?? $b["created_at"] ?? "") : ""));
        return $tb <=> $ta;
    });
    return array_values(array_filter($orders, "is_array"));
}

/**
 * @return array<string, mixed>|null
 */
function mirai_orders_get(string $orderId): ?array
{
    $orderId = trim($orderId);
    foreach (mirai_orders_list_for_staff() as $o) {
        if ((string)($o["id"] ?? "") === $orderId) {
            return $o;
        }
    }
    return null;
}

/**
 * Текст для кухни (все позиции линии kitchen в заказе).
 */
function mirai_orders_kitchen_text(array $order): string
{
    $lines = [];
    $id = (string)($order["id"] ?? "");
    $cap = (string)($order["table_caption"] ?? "");
    $lines[] = "Заказ {$id}";
    $lines[] = "Стол: {$cap}";
    $lines[] = "";
    $items = $order["items"] ?? [];
    if (!is_array($items)) {
        $items = [];
    }
    $any = false;
    foreach ($items as $it) {
        if (!is_array($it)) {
            continue;
        }
        if ((string)($it["line"] ?? "") !== "kitchen") {
            continue;
        }
        $any = true;
        $name = (string)($it["name"] ?? "");
        $qty = (int)($it["qty"] ?? 0);
        $lines[] = "• {$name} × {$qty}";
    }
    if (!$any) {
        return "Заказ {$id}\nСтол: {$cap}\n\n(нет позиций кухни)";
    }
    return implode("\n", $lines);
}

/**
 * Группировка позиций по линии для UI.
 *
 * @return array{hookah: list<array>, bar: list<array>, kitchen: list<array>}
 */
function mirai_orders_group_by_line(array $order): array
{
    $out = ["hookah" => [], "bar" => [], "kitchen" => []];
    $items = $order["items"] ?? [];
    if (!is_array($items)) {
        return $out;
    }
    foreach ($items as $it) {
        if (!is_array($it)) {
            continue;
        }
        $line = (string)($it["line"] ?? "kitchen");
        if (!isset($out[$line])) {
            $line = "kitchen";
        }
        $out[$line][] = $it;
    }
    return $out;
}
