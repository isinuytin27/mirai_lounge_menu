<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";

function menu_storage_upload_error_text(int $code): string
{
    // https://www.php.net/manual/en/features.file-upload.errors.php
    return match ($code) {
        UPLOAD_ERR_OK => "OK",
        UPLOAD_ERR_INI_SIZE => "Файл больше лимита upload_max_filesize в php.ini",
        UPLOAD_ERR_FORM_SIZE => "Файл больше лимита MAX_FILE_SIZE формы",
        UPLOAD_ERR_PARTIAL => "Файл загружен частично",
        UPLOAD_ERR_NO_FILE => "Файл не выбран",
        UPLOAD_ERR_NO_TMP_DIR => "Нет временной папки на сервере",
        UPLOAD_ERR_CANT_WRITE => "Не удалось записать файл на диск",
        UPLOAD_ERR_EXTENSION => "Загрузка остановлена расширением PHP",
        default => "Неизвестная ошибка загрузки (код $code)",
    };
}

function menu_storage_paths(): array
{
    $cfg = admin_config();
    $jsonPath = (string)($cfg["storage"]["menu_json_path"] ?? "");
    $uploadDir = (string)($cfg["storage"]["menu_upload_dir"] ?? "");
    $publicPrefix = (string)($cfg["storage"]["menu_upload_public_prefix"] ?? "");

    return [$jsonPath, $uploadDir, $publicPrefix];
}

function menu_storage_ensure_dirs(): void
{
    [$jsonPath, $uploadDir] = menu_storage_paths();
    $jsonDir = dirname($jsonPath);

    if (!is_dir($jsonDir)) {
        mkdir($jsonDir, 0775, true);
    }
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }
}

function menu_storage_default(): array
{
    return [
        "categories" => [
            ["id" => "kalyan", "title" => "Кальян"],
            ["id" => "zakuski", "title" => "Закуски"],
            ["id" => "salaty", "title" => "Салаты"],
            ["id" => "goryachee", "title" => "Горячее"],
            ["id" => "pasta", "title" => "Паста"],
            ["id" => "pizza", "title" => "Пицца"],
            ["id" => "tea_leaf", "title" => "Чай листовой"],
            ["id" => "tea_signature", "title" => "Авторский чай"],
            ["id" => "desserts", "title" => "Десерты"],
            ["id" => "classic", "title" => "Классика"],
            ["id" => "tinctures", "title" => "Настойки"],
            ["id" => "sours", "title" => "Сауэры"],
            ["id" => "tropical", "title" => "Тропические коктейли"],
            ["id" => "na_cocktails", "title" => "Безалкогольные коктейли"],
            ["id" => "assorted", "title" => "В ассортименте"],
        ],
        // Стартовый набор, чтобы сразу видеть меню в админке.
        "products" => [
            [
                "id" => "kalyan_classic_mix",
                "category_id" => "kalyan",
                "name" => "Классический микс",
                "price" => 1600,
                "description" => "Крепость по запросу, 60–75 минут.",
                "image" => null,
                "visible" => true,
                "updated_at" => date("c"),
            ],
            [
                "id" => "kalyan_fruit_bowl",
                "category_id" => "kalyan",
                "name" => "Фруктовая чаша",
                "price" => 2200,
                "description" => "На грейпфруте/апельсине. Уточняйте наличие.",
                "image" => null,
                "visible" => true,
                "updated_at" => date("c"),
            ],
            [
                "id" => "zakuski_cheese_plate",
                "category_id" => "zakuski",
                "name" => "Сырная тарелка",
                "price" => 950,
                "description" => "Ассорти сыров, орехи, мёд.",
                "image" => null,
                "visible" => true,
                "updated_at" => date("c"),
            ],
            [
                "id" => "zakuski_bruschetta",
                "category_id" => "zakuski",
                "name" => "Брускетты",
                "price" => 690,
                "description" => "3 шт. С томатом и соусом.",
                "image" => null,
                "visible" => true,
                "updated_at" => date("c"),
            ],
            [
                "id" => "salaty_caesar_chicken",
                "category_id" => "salaty",
                "name" => "Цезарь с курицей",
                "price" => 790,
                "description" => "Классический соус, пармезан.",
                "image" => null,
                "visible" => true,
                "updated_at" => date("c"),
            ],
            [
                "id" => "goryachee_teriyaki_chicken",
                "category_id" => "goryachee",
                "name" => "Курица терияки",
                "price" => 980,
                "description" => "С рисом и овощами.",
                "image" => null,
                "visible" => true,
                "updated_at" => date("c"),
            ],
            [
                "id" => "pasta_carbonara",
                "category_id" => "pasta",
                "name" => "Карбонара",
                "price" => 890,
                "description" => "Бекон, пармезан, сливочный соус.",
                "image" => null,
                "visible" => true,
                "updated_at" => date("c"),
            ],
            [
                "id" => "pizza_margherita",
                "category_id" => "pizza",
                "name" => "Маргарита",
                "price" => 890,
                "description" => "Томат, моцарелла, базилик.",
                "image" => null,
                "visible" => true,
                "updated_at" => date("c"),
            ],
            [
                "id" => "pizza_pepperoni",
                "category_id" => "pizza",
                "name" => "Пепперони",
                "price" => 990,
                "description" => "Пикантная колбаса, моцарелла.",
                "image" => null,
                "visible" => false,
                "updated_at" => date("c"),
            ],
            [
                "id" => "tea_leaf_sencha",
                "category_id" => "tea_leaf",
                "name" => "Сенча",
                "price" => 450,
                "description" => "Чайник 500 мл.",
                "image" => null,
                "visible" => true,
                "updated_at" => date("c"),
            ],
            [
                "id" => "tea_leaf_oolong",
                "category_id" => "tea_leaf",
                "name" => "Улун",
                "price" => 490,
                "description" => "Чайник 500 мл.",
                "image" => null,
                "visible" => true,
                "updated_at" => date("c"),
            ],
            [
                "id" => "tea_signature_ginger_lemon",
                "category_id" => "tea_signature",
                "name" => "Имбирь–лимон",
                "price" => 590,
                "description" => "Чайник 700 мл.",
                "image" => null,
                "visible" => true,
                "updated_at" => date("c"),
            ],
            [
                "id" => "desserts_cheesecake",
                "category_id" => "desserts",
                "name" => "Чизкейк",
                "price" => 520,
                "description" => "Подача с ягодным соусом.",
                "image" => null,
                "visible" => true,
                "updated_at" => date("c"),
            ],
            [
                "id" => "classic_negroni",
                "category_id" => "classic",
                "name" => "Негрони",
                "price" => 690,
                "description" => "Джин, кампари, вермут.",
                "image" => null,
                "visible" => true,
                "updated_at" => date("c"),
            ],
            [
                "id" => "tinctures_cherry",
                "category_id" => "tinctures",
                "name" => "Вишнёвая",
                "price" => 250,
                "description" => "50 мл.",
                "image" => null,
                "visible" => true,
                "updated_at" => date("c"),
            ],
            [
                "id" => "sours_whiskey_sour",
                "category_id" => "sours",
                "name" => "Виски сауэр",
                "price" => 650,
                "description" => "Баланс кислого и сладкого.",
                "image" => null,
                "visible" => true,
                "updated_at" => date("c"),
            ],
            [
                "id" => "tropical_pina_colada",
                "category_id" => "tropical",
                "name" => "Пина Колада",
                "price" => 690,
                "description" => "Ананас, кокос, ром.",
                "image" => null,
                "visible" => true,
                "updated_at" => date("c"),
            ],
            [
                "id" => "na_cocktails_mojito",
                "category_id" => "na_cocktails",
                "name" => "Мохито 0%",
                "price" => 450,
                "description" => "Лайм, мята, содовая.",
                "image" => null,
                "visible" => true,
                "updated_at" => date("c"),
            ],
            [
                "id" => "assorted_juice_water",
                "category_id" => "assorted",
                "name" => "Соки/вода",
                "price" => 250,
                "description" => "Уточняйте по наличию.",
                "image" => null,
                "visible" => true,
                "updated_at" => date("c"),
            ],
        ],
        "updated_at" => date("c"),
    ];
}

function menu_storage_load(): array
{
    menu_storage_ensure_dirs();
    [$jsonPath] = menu_storage_paths();

    if (!is_file($jsonPath)) {
        $data = menu_storage_default();
        file_put_contents($jsonPath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $data;
    }

    $raw = file_get_contents($jsonPath);
    if ($raw === false || trim($raw) === "") {
        return menu_storage_default();
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return menu_storage_default();
    }

    $data["categories"] = is_array($data["categories"] ?? null) ? $data["categories"] : [];
    $data["products"] = is_array($data["products"] ?? null) ? $data["products"] : [];

    return $data;
}

function menu_storage_save(array $data): void
{
    menu_storage_ensure_dirs();
    [$jsonPath] = menu_storage_paths();

    $data["updated_at"] = date("c");
    file_put_contents($jsonPath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function menu_storage_slugify(string $s): string
{
    $s = trim(mb_strtolower($s, "UTF-8"));
    $s = preg_replace("/[^a-z0-9_\\-]+/u", "_", $s) ?? $s;
    $s = preg_replace("/_+/", "_", $s) ?? $s;
    $s = trim($s, "_");
    return $s !== "" ? $s : "item";
}

function menu_storage_new_product_id(string $categoryId, string $name): string
{
    return $categoryId . "_" . menu_storage_slugify($name) . "_" . substr(bin2hex(random_bytes(6)), 0, 12);
}

function menu_storage_find_product_index(array $products, string $id): int
{
    foreach ($products as $i => $p) {
        if (is_array($p) && (string)($p["id"] ?? "") === $id) return (int)$i;
    }
    return -1;
}

function menu_storage_handle_upload(?array $file, string $productId): ?string
{
    if (!$file || !isset($file["tmp_name"])) return null;
    if (!empty($file["error"])) return null;
    if (!is_uploaded_file($file["tmp_name"])) return null;

    [$jsonPath, $uploadDir, $publicPrefix] = menu_storage_paths();
    $ext = strtolower(pathinfo((string)($file["name"] ?? ""), PATHINFO_EXTENSION));
    $allowed = ["jpg", "jpeg", "png", "webp"];
    if (!in_array($ext, $allowed, true)) return null;

    // Сохраняем файл под исходным именем (как загрузили).
    // Минимальная защита: убираем путь, оставляем только basename.
    $original = basename((string)($file["name"] ?? ""));
    $original = trim($original);
    if ($original === "") return null;

    $target = rtrim($uploadDir, "/") . "/" . $original;

    if (!move_uploaded_file($file["tmp_name"], $target)) return null;

    return rtrim($publicPrefix, "/") . "/" . $original;
}

/**
 * Возвращает [publicPath|null, debugInfoString]
 */
function menu_storage_handle_upload_debug(?array $file, string $productId): array
{
    if (!$file) return [null, "Файл не передан"];

    $name = (string)($file["name"] ?? "");
    $tmp = (string)($file["tmp_name"] ?? "");
    $err = (int)($file["error"] ?? 0);
    $size = (int)($file["size"] ?? 0);

    [$jsonPath, $uploadDir, $publicPrefix] = menu_storage_paths();
    $dirExists = is_dir($uploadDir) ? "yes" : "no";
    $dirWritable = is_dir($uploadDir) && is_writable($uploadDir) ? "yes" : "no";

    if ($err !== UPLOAD_ERR_OK) {
        return [null, "upload_error=$err (" . menu_storage_upload_error_text($err) . "), name=\"$name\", size=$size, uploadDir=\"$uploadDir\" (exists=$dirExists,writable=$dirWritable)"];
    }

    if ($tmp === "" || !is_uploaded_file($tmp)) {
        return [null, "tmp_invalid tmp=\"$tmp\", name=\"$name\", size=$size, uploadDir=\"$uploadDir\" (exists=$dirExists,writable=$dirWritable)"];
    }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed = ["jpg", "jpeg", "png", "webp"];
    if (!in_array($ext, $allowed, true)) {
        return [null, "bad_ext=\"$ext\", allowed=" . implode(",", $allowed) . ", name=\"$name\""];
    }

    $original = basename($name);
    $original = trim($original);
    if ($original === "") {
        return [null, "empty_basename name=\"$name\""];
    }

    $target = rtrim($uploadDir, "/") . "/" . $original;
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0775, true);
    }

    if (!move_uploaded_file($tmp, $target)) {
        $last = error_get_last();
        $msg = $last ? (string)($last["message"] ?? "") : "";
        return [null, "move_failed target=\"$target\", uploadDir=\"$uploadDir\" (exists=$dirExists,writable=$dirWritable), last_error=\"$msg\""];
    }

    return [rtrim($publicPrefix, "/") . "/" . $original, "ok name=\"$name\" -> \"$target\""];
}

