<?php
declare(strict_types=1);

/**
 * Чтение галереи для публичного сайта (без сессии админки).
 */

function mirai_gallery_public_load_items(): array
{
    /** @var array $cfg */
    $cfg = require dirname(__DIR__, 2) . "/config/config.php";
    $jsonPath = (string)($cfg["storage"]["gallery_json_path"] ?? "");
    if ($jsonPath === "" || !is_file($jsonPath)) {
        return [];
    }
    $raw = file_get_contents($jsonPath);
    if ($raw === false || trim($raw) === "") {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return [];
    }
    $items = $data["items"] ?? [];
    return is_array($items) ? $items : [];
}

/**
 * @return array{id: string, caption: string}|null
 */
function mirai_gallery_public_find_table(string $tableId): ?array
{
    $tableId = trim($tableId);
    if ($tableId === "") {
        return null;
    }
    foreach (mirai_gallery_public_load_items() as $it) {
        if (!is_array($it)) {
            continue;
        }
        $id = (string)($it["id"] ?? "");
        if ($id !== $tableId) {
            continue;
        }
        $caption = trim((string)($it["caption"] ?? ""));
        if ($caption === "") {
            $caption = $id;
        }
        return ["id" => $id, "caption" => $caption];
    }
    return null;
}
