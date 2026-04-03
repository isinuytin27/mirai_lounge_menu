<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";

function gallery_storage_paths(): array
{
    $cfg = admin_config();
    return [
        (string)($cfg["storage"]["gallery_json_path"] ?? ""),
        (string)($cfg["storage"]["gallery_upload_dir"] ?? ""),
        (string)($cfg["storage"]["gallery_upload_public_prefix"] ?? ""),
    ];
}

function gallery_storage_ensure_dirs(): void
{
    [$jsonPath, $uploadDir] = gallery_storage_paths();
    $jsonDir = dirname($jsonPath);
    if (!is_dir($jsonDir)) mkdir($jsonDir, 0775, true);
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
}

function gallery_storage_default(): array
{
    return [
        "items" => [
            ["id" => "pos_1", "image" => "assets/img/interior/1-2.webp", "caption" => "Стол №1"],
            ["id" => "pos_2", "image" => "assets/img/interior/4-2.webp", "caption" => "Стол №2"],
            ["id" => "pos_3", "image" => "assets/img/interior/5-2.webp", "caption" => "Стол №3"],
            ["id" => "pos_4", "image" => "assets/img/interior/6-2.webp", "caption" => "Стол №4"],
            ["id" => "pos_5", "image" => "assets/img/interior/7-2.webp", "caption" => "Стол №5"],
            ["id" => "pos_6", "image" => "assets/img/interior/8.webp", "caption" => "Стол №6"],
            ["id" => "pos_7", "image" => "assets/img/interior/9-2.webp", "caption" => "Стол №7"],
            ["id" => "pos_8", "image" => "assets/img/interior/10-2.webp", "caption" => "Стол №8"],
        ],
        "updated_at" => date("c"),
    ];
}

function gallery_storage_load(): array
{
    gallery_storage_ensure_dirs();
    [$jsonPath] = gallery_storage_paths();

    if (!is_file($jsonPath)) {
        $data = gallery_storage_default();
        file_put_contents($jsonPath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $data;
    }

    $raw = file_get_contents($jsonPath);
    if ($raw === false || trim($raw) === "") return gallery_storage_default();

    $data = json_decode($raw, true);
    if (!is_array($data)) return gallery_storage_default();

    $data["items"] = is_array($data["items"] ?? null) ? $data["items"] : [];
    return $data;
}

function gallery_storage_save(array $data): void
{
    gallery_storage_ensure_dirs();
    [$jsonPath] = gallery_storage_paths();
    $data["updated_at"] = date("c");
    file_put_contents($jsonPath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function gallery_storage_find_index(array $items, string $id): int
{
    foreach ($items as $i => $it) {
        if (is_array($it) && (string)($it["id"] ?? "") === $id) return (int)$i;
    }
    return -1;
}

function gallery_storage_new_id(): string
{
    return "pos_" . bin2hex(random_bytes(6));
}

function gallery_storage_handle_upload_debug(?array $file): array
{
    if (!$file) return [null, "Файл не передан"];

    $name = (string)($file["name"] ?? "");
    $tmp = (string)($file["tmp_name"] ?? "");
    $err = (int)($file["error"] ?? 0);

    [, $uploadDir, $publicPrefix] = gallery_storage_paths();

    if ($err !== UPLOAD_ERR_OK) {
        $msg = match ($err) {
            UPLOAD_ERR_INI_SIZE => "Файл больше лимита",
            UPLOAD_ERR_FORM_SIZE => "Файл слишком большой",
            UPLOAD_ERR_PARTIAL => "Загрузка прервана",
            UPLOAD_ERR_NO_FILE => "Файл не выбран",
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => "Ошибка сервера",
            default => "Ошибка загрузки ($err)",
        };
        return [null, $msg];
    }

    if ($tmp === "" || !is_uploaded_file($tmp)) return [null, "Неверный файл"];

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ["jpg", "jpeg", "png", "webp"], true)) return [null, "Допустимы jpg, png, webp"];

    $base = basename($name);
    $base = preg_replace('/[^a-zA-Z0-9._-]/', '_', trim($base)) ?: 'image';
    $base = substr($base, 0, 80);
    $base = pathinfo($base, PATHINFO_FILENAME) . "." . $ext;
    $target = rtrim($uploadDir, "/") . "/" . $base;

    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);
    if (!move_uploaded_file($tmp, $target)) return [null, "Не удалось сохранить файл"];

    return [rtrim($publicPrefix, "/") . "/" . $base, "ok"];
}

function gallery_storage_move(array &$items, string $id, int $direction): bool
{
    $idx = gallery_storage_find_index($items, $id);
    if ($idx < 0) return false;
    $newIdx = $idx + $direction;
    if ($newIdx < 0 || $newIdx >= count($items)) return false;
    $tmp = $items[$idx];
    $items[$idx] = $items[$newIdx];
    $items[$newIdx] = $tmp;
    return true;
}
