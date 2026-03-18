<?php
declare(strict_types=1);

require_once __DIR__ . "/lib/auth.php";
require_once __DIR__ . "/lib/menu_storage.php";

admin_require_login();

$data = menu_storage_load();
$categories = $data["categories"] ?? [];
$products = $data["products"] ?? [];

function find_category_title(array $categories, string $id): string
{
    foreach ($categories as $c) {
        if (is_array($c) && (string)($c["id"] ?? "") === $id) return (string)($c["title"] ?? $id);
    }
    return $id;
}

$flashOk = "";
$flashErr = "";
$flashWarn = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = (string)($_POST["action"] ?? "");

    if ($action === "logout") {
        admin_logout();
        header("Location: /admin/login.php");
        exit;
    }

    if ($action === "create") {
        $categoryId = trim((string)($_POST["category_id"] ?? ""));
        $name = trim((string)($_POST["name"] ?? ""));
        $price = (int)($_POST["price"] ?? 0);
        $description = trim((string)($_POST["description"] ?? ""));
        $visible = !empty($_POST["visible"]);

        if ($categoryId === "" || $name === "" || $price < 0) {
            $flashErr = "Заполните категорию, название и цену (цена ≥ 0).";
        } else {
            $id = menu_storage_new_product_id($categoryId, $name);
            [$image, $uploadDebug] = menu_storage_handle_upload_debug($_FILES["image"] ?? null, $id);
            $imgSelected = !empty($_FILES["image"]["tmp_name"]) || (!empty($_FILES["image"]["name"]) && (int)($_FILES["image"]["error"] ?? 0) !== 4);
            if ($imgSelected && !$image) {
                $flashErr = "Не удалось загрузить изображение. " . $uploadDebug;
            }

            $products[] = [
                "id" => $id,
                "category_id" => $categoryId,
                "name" => $name,
                "price" => $price,
                "description" => $description,
                "image" => $image,
                "visible" => $visible,
                "updated_at" => date("c"),
            ];

            $data["products"] = $products;
            menu_storage_save($data);
            $flashOk = "Товар добавлен.";
            if (!$image) $flashWarn = "Товар добавлен без изображения — в гостевом меню будет плейсхолдер.";
        }
    }

    if ($action === "update") {
        $id = (string)($_POST["id"] ?? "");
        $idx = menu_storage_find_product_index($products, $id);
        if ($idx < 0) {
            $flashErr = "Товар не найден.";
        } else {
            $categoryId = trim((string)($_POST["category_id"] ?? ""));
            $name = trim((string)($_POST["name"] ?? ""));
            $price = (int)($_POST["price"] ?? 0);
            $description = trim((string)($_POST["description"] ?? ""));
            $visible = !empty($_POST["visible"]);

            if ($categoryId === "" || $name === "" || $price < 0) {
                $flashErr = "Заполните категорию, название и цену (цена ≥ 0).";
            } else {
                $products[$idx]["category_id"] = $categoryId;
                $products[$idx]["name"] = $name;
                $products[$idx]["price"] = $price;
                $products[$idx]["description"] = $description;
                $products[$idx]["visible"] = $visible;
                $products[$idx]["updated_at"] = date("c");

                [$newImage, $uploadDebug] = menu_storage_handle_upload_debug($_FILES["image"] ?? null, $id);
                $imgSelected = !empty($_FILES["image"]["tmp_name"]) || (!empty($_FILES["image"]["name"]) && (int)($_FILES["image"]["error"] ?? 0) !== 4);
                if ($imgSelected && !$newImage) {
                    $flashErr = "Не удалось загрузить изображение. " . $uploadDebug;
                }
                if ($newImage) $products[$idx]["image"] = $newImage;

                $data["products"] = $products;
                menu_storage_save($data);
                $flashOk = "Товар обновлен.";
                if (empty($products[$idx]["image"])) {
                    $flashWarn = "У товара сейчас нет изображения — в гостевом меню будет плейсхолдер.";
                }
            }
        }
    }

    if ($action === "delete") {
        $id = (string)($_POST["id"] ?? "");
        $idx = menu_storage_find_product_index($products, $id);
        if ($idx < 0) {
            $flashErr = "Товар не найден.";
        } else {
            array_splice($products, $idx, 1);
            $data["products"] = $products;
            menu_storage_save($data);
            $flashOk = "Товар удален.";
        }
    }

    // Перезагружаем данные после изменений
    $data = menu_storage_load();
    $categories = $data["categories"] ?? [];
    $products = $data["products"] ?? [];
}

// Группировка товаров по категориям (для списка/счетчиков)
$productsByCat = [];
foreach ($products as $p) {
    if (!is_array($p)) continue;
    $cid = (string)($p["category_id"] ?? "");
    if ($cid === "") $cid = "_unknown";
    if (!isset($productsByCat[$cid])) $productsByCat[$cid] = [];
    $productsByCat[$cid][] = $p;
}

// Стабильный порядок: как в категориях + "прочее"
$categoryOrder = [];
foreach ($categories as $c) {
    if (!is_array($c)) continue;
    $cid = (string)($c["id"] ?? "");
    if ($cid !== "") $categoryOrder[] = $cid;
}
if (isset($productsByCat["_unknown"])) $categoryOrder[] = "_unknown";

function category_title(array $categories, string $cid): string
{
    if ($cid === "_unknown") return "Без категории";
    return find_category_title($categories, $cid);
}

function sort_products_by_name(array &$items): void
{
    usort($items, function ($a, $b) {
        $an = is_array($a) ? (string)($a["name"] ?? "") : "";
        $bn = is_array($b) ? (string)($b["name"] ?? "") : "";
        return strcasecmp($an, $bn);
    });
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mirai Admin — Меню</title>
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div>
                <h1 class="title">Меню — управление</h1>
                <div class="muted" style="font-size: 12px; margin-top: 4px;">
                    Вход: <?= htmlspecialchars((string)($_SESSION["admin_username"] ?? "admin")) ?>
                    · Обновлено: <?= htmlspecialchars((string)($data["updated_at"] ?? "")) ?>
                </div>
            </div>

            <form method="post" style="margin:0">
                <input type="hidden" name="action" value="logout">
                <button class="btn btn-danger" type="submit">Выйти</button>
            </form>
        </div>

        <?php if ($flashErr !== ""): ?>
            <div class="err"><?= htmlspecialchars($flashErr) ?></div>
        <?php endif; ?>
        <?php if ($flashWarn !== ""): ?>
            <div class="err" style="border-color:rgba(255,200,80,.45);background:rgba(255,200,80,.08);color:rgba(255,245,220,.95);">
                <?= htmlspecialchars($flashWarn) ?>
            </div>
        <?php endif; ?>
        <?php if ($flashOk !== ""): ?>
            <div class="ok"><?= htmlspecialchars($flashOk) ?></div>
        <?php endif; ?>

        <div class="grid">
            <div class="card">
                <h2>Добавить товар</h2>

                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create">

                    <div class="row-3">
                        <div>
                            <label for="name">Название</label>
                            <input id="name" name="name" type="text" required value="">
                        </div>

                        <div>
                            <label for="price">Цена (₽)</label>
                            <input id="price" name="price" type="number" min="0" step="1" required value="0">
                        </div>

                        <div>
                            <label for="category">Категория</label>
                            <select id="category" name="category_id" required>
                                <option value="" disabled selected>Выберите…</option>
                                <?php foreach ($categories as $c): ?>
                                    <?php
                                    $cid = (string)($c["id"] ?? "");
                                    $ct = (string)($c["title"] ?? $cid);
                                    ?>
                                    <option value="<?= htmlspecialchars($cid) ?>">
                                        <?= htmlspecialchars($ct) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <label for="description">Описание</label>
                    <textarea id="description" name="description" placeholder="Коротко и по делу…"></textarea>

                    <label for="image">Фото (jpg/png/webp)</label>
                    <input id="image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp,image/*">

                    <div style="display:flex; align-items:center; gap:10px; margin-top: 10px;">
                        <label style="margin:0; display:flex; align-items:center; gap:8px; color: var(--text);">
                            <input type="checkbox" name="visible" value="1" checked>
                            Показывать гостям
                        </label>
                    </div>

                    <div style="height: 12px"></div>

                    <div class="actions">
                        <button class="btn" type="submit">Добавить</button>
                    </div>

                    <div style="height: 10px"></div>
                    <div class="hint">
                        Фото сохраняется в <code>public/assets/img/menu/uploads/</code> <b>без переименования</b> (имя файла как при загрузке).
                        Стоп‑лист — это чекбокс “Показывать гостям”.
                        <br>Если фото не выбрать — товар добавится <b>без изображения</b> (гости увидят плейсхолдер).
                    </div>
                </form>
            </div>

            <div class="card">
                <h2>Позиции (<?= count($products) ?>)</h2>

                <?php if (!count($products)): ?>
                    <div class="muted">Пока нет товаров. Добавьте первый выше.</div>
                <?php endif; ?>

                <?php
                $globalIndex = 0;
                foreach ($categoryOrder as $cid):
                    $items = $productsByCat[$cid] ?? [];
                    if (!count($items)) continue;
                    sort_products_by_name($items);
                    $catTitle = category_title($categories, $cid);
                ?>
                    <div class="admin-cat">
                        <div class="admin-cat-head">
                            <div style="font-weight:700;"><?= htmlspecialchars($catTitle) ?></div>
                            <span class="pill"><?= count($items) ?> шт.</span>
                        </div>

                        <div class="admin-list">
                            <?php $catIndex = 0; ?>
                            <?php foreach ($items as $p): ?>
                                <?php
                                $catIndex++;
                                $globalIndex++;
                                $pid = (string)($p["id"] ?? "");
                                $img = (string)($p["image"] ?? "");
                                $isVisible = !empty($p["visible"]);
                                $name = (string)($p["name"] ?? "");
                                $price = (int)($p["price"] ?? 0);
                                $desc = (string)($p["description"] ?? "");
                                ?>

                                <details class="admin-item" <?= isset($_GET["open"]) && (string)$_GET["open"] === $pid ? "open" : "" ?>>
                                    <summary class="admin-item-sum">
                                        <div class="admin-item-left">
                                            <?php if ($img !== ""): ?>
                                                <img class="thumb" src="/<?= htmlspecialchars($img) ?>" alt="">
                                            <?php else: ?>
                                                <div class="thumb" style="display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:10px;">—</div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="admin-num">#<?= $globalIndex ?> · <?= $catIndex ?>/<?= count($items) ?></div>
                                                <div style="font-weight:700;"><?= htmlspecialchars($name) ?></div>
                                                <div class="muted" style="font-size:12px; margin-top:4px;"><code><?= htmlspecialchars($pid) ?></code></div>
                                            </div>
                                        </div>

                                        <div class="admin-item-right">
                                            <div style="font-weight:700;"><?= $price ?> ₽</div>
                                            <?= $isVisible ? "<span class='pill'>Показ</span>" : "<span class='pill' style='border-color:rgba(255,80,80,.45);background:rgba(255,80,80,.10)'>Скрыт</span>" ?>
                                        </div>
                                    </summary>

                                    <div class="admin-item-body">
                                        <form method="post" enctype="multipart/form-data">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($pid) ?>">

                                            <div class="row-3">
                                                <div>
                                                    <label>Название</label>
                                                    <input name="name" type="text" required value="<?= htmlspecialchars($name) ?>">
                                                </div>
                                                <div>
                                                    <label>Цена (₽)</label>
                                                    <input name="price" type="number" min="0" step="1" required value="<?= $price ?>">
                                                </div>
                                                <div>
                                                    <label>Категория</label>
                                                    <select name="category_id" required>
                                                        <?php foreach ($categories as $c): ?>
                                                            <?php
                                                            $ccid = (string)($c["id"] ?? "");
                                                            $ct = (string)($c["title"] ?? $ccid);
                                                            $selected = $ccid !== "" && $ccid === (string)($p["category_id"] ?? "");
                                                            ?>
                                                            <option value="<?= htmlspecialchars($ccid) ?>" <?= $selected ? "selected" : "" ?>>
                                                                <?= htmlspecialchars($ct) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>

                                            <label>Описание</label>
                                            <textarea name="description" placeholder="Коротко и по делу…"><?= htmlspecialchars($desc) ?></textarea>

                                            <label>Фото (jpg/png/webp)</label>
                                            <input name="image" type="file" accept=".jpg,.jpeg,.png,.webp,image/*">
                                            <?php if ($img === ""): ?>
                                                <div class="hint">Сейчас изображение не задано — если не загрузить, товар сохранится <b>без фото</b>.</div>
                                            <?php endif; ?>

                                            <div style="display:flex; align-items:center; gap:10px; margin-top: 10px;">
                                                <label style="margin:0; display:flex; align-items:center; gap:8px; color: var(--text);">
                                                    <input type="checkbox" name="visible" value="1" <?= $isVisible ? "checked" : "" ?>>
                                                    Показывать гостям
                                                </label>
                                            </div>

                                            <div style="height: 10px"></div>
                                            <div class="actions">
                                                <button class="btn" type="submit">Сохранить</button>
                                            </div>
                                        </form>

                                        <div style="height: 10px"></div>
                                        <form method="post" style="margin:0" onsubmit="return confirm('Удалить товар?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($pid) ?>">
                                            <button class="btn btn-danger" type="submit">Удалить</button>
                                        </form>
                                    </div>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>

