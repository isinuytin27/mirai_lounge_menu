<?php
declare(strict_types=1);

require_once __DIR__ . "/mirai_menu_line.php";

function mirai_menu_public_load(): array
{
    /** @var array $cfg */
    $cfg = require dirname(__DIR__, 2) . "/config/config.php";
    $jsonPath = (string)($cfg["storage"]["menu_json_path"] ?? "");
    if ($jsonPath === "" || !is_file($jsonPath)) {
        return ["categories" => [], "products" => []];
    }
    $raw = file_get_contents($jsonPath);
    if ($raw === false || trim($raw) === "") {
        return ["categories" => [], "products" => []];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ["categories" => [], "products" => []];
    }
    return [
        "categories" => is_array($data["categories"] ?? null) ? $data["categories"] : [],
        "products" => is_array($data["products"] ?? null) ? $data["products"] : [],
    ];
}

/**
 * @return array{id: string, name: string, price: int, category_id: string, line: string}|null
 */
function mirai_menu_public_find_visible_product(string $productId): ?array
{
    $productId = trim($productId);
    if ($productId === "") {
        return null;
    }
    $data = mirai_menu_public_load();
    foreach ($data["products"] as $p) {
        if (!is_array($p)) {
            continue;
        }
        if ((string)($p["id"] ?? "") !== $productId) {
            continue;
        }
        if (array_key_exists("visible", $p) && !$p["visible"]) {
            return null;
        }
        $cid = (string)($p["category_id"] ?? "");
        if ($cid === "") {
            return null;
        }
        $name = trim((string)($p["name"] ?? ""));
        if ($name === "") {
            return null;
        }
        $price = (int)($p["price"] ?? 0);
        return [
            "id" => $productId,
            "name" => $name,
            "price" => $price,
            "category_id" => $cid,
            "line" => mirai_menu_line_for_category($cid),
        ];
    }
    return null;
}
