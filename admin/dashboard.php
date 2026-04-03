<?php
declare(strict_types=1);

require_once __DIR__ . "/lib/auth.php";
require_once __DIR__ . "/lib/menu_storage.php";
require_once __DIR__ . "/lib/gallery_storage.php";

admin_require_login();

$validTabs = ["categories", "menu", "gallery"];
$tab = (string)($_GET["tab"] ?? $_POST["tab"] ?? "menu");
if (!in_array($tab, $validTabs, true)) $tab = "menu";

$data = menu_storage_load();
$categories = $data["categories"] ?? [];
$products = $data["products"] ?? [];

$galleryData = gallery_storage_load();
$galleryItems = $galleryData["items"] ?? [];

function find_category_title(array $categories, string $id): string
{
    foreach ($categories as $c) {
        if (is_array($c) && (string)($c["id"] ?? "") === $id) return (string)($c["title"] ?? $id);
    }
    return $id;
}

function admin_redirect(string $flashOk = "", string $flashWarn = "", string $flashErr = "", ?string $open = null, ?string $scroll = null, string $tab = "menu"): never
{
    if ($flashOk !== "") $_SESSION["admin_flash_ok"] = $flashOk;
    if ($flashWarn !== "") $_SESSION["admin_flash_warn"] = $flashWarn;
    if ($flashErr !== "") $_SESSION["admin_flash_err"] = $flashErr;
    $base = strtok($_SERVER["REQUEST_URI"] ?? "/admin/dashboard.php", "?");
    $q = ["tab" => $tab];
    if ($open !== null && $open !== "") $q["open"] = $open;
    if ($scroll !== null && $scroll !== "") $q["scroll"] = $scroll;
    header("Location: " . $base . "?" . http_build_query($q));
    exit;
}

$flashOk = "";
$flashErr = "";
$flashWarn = "";
$scrollOpen = (string)($_GET["open"] ?? "");
$scrollCat = (string)($_GET["scroll"] ?? "");

if (isset($_SESSION["admin_flash_ok"])) {
    $flashOk = (string)$_SESSION["admin_flash_ok"];
    unset($_SESSION["admin_flash_ok"]);
}
if (isset($_SESSION["admin_flash_err"])) {
    $flashErr = (string)$_SESSION["admin_flash_err"];
    unset($_SESSION["admin_flash_err"]);
}
if (isset($_SESSION["admin_flash_warn"])) {
    $flashWarn = (string)$_SESSION["admin_flash_warn"];
    unset($_SESSION["admin_flash_warn"]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = (string)($_POST["action"] ?? "");
    $postTab = (string)($_POST["tab"] ?? $tab);

    if ($action === "logout") {
        admin_logout();
        header("Location: /admin/login.php");
        exit;
    }

    if ($action === "create") {
        $tab = "menu";
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
            $imgErr = "";
            if ($imgSelected && !$image) $imgErr = "Не удалось загрузить изображение. " . $uploadDebug;

            $descriptionShort = trim((string)($_POST["description_short"] ?? ""));
            $weight = trim((string)($_POST["weight"] ?? ""));
            $products[] = [
                "id" => $id, "category_id" => $categoryId, "name" => $name, "price" => $price,
                "description" => $description, "description_short" => $descriptionShort,
                "weight" => $weight, "image" => $image, "visible" => $visible, "updated_at" => date("c"),
            ];
            $data["products"] = $products;
            menu_storage_save($data);
            $warn = !$image ? "Товар добавлен без изображения." : "";
            admin_redirect("Товар добавлен.", $warn, $imgErr, $id, null, "menu");
        }
    }

    if ($action === "update") {
        $tab = "menu";
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
                $products[$idx]["description_short"] = trim((string)($_POST["description_short"] ?? ""));
                $products[$idx]["weight"] = trim((string)($_POST["weight"] ?? ""));
                $products[$idx]["visible"] = $visible;
                $products[$idx]["updated_at"] = date("c");
                [$newImage, $uploadDebug] = menu_storage_handle_upload_debug($_FILES["image"] ?? null, $id);
                $imgSelected = !empty($_FILES["image"]["tmp_name"]) || (!empty($_FILES["image"]["name"]) && (int)($_FILES["image"]["error"] ?? 0) !== 4);
                $imgErr = "";
                if ($imgSelected && !$newImage) $imgErr = "Не удалось загрузить изображение. " . $uploadDebug;
                if ($newImage) $products[$idx]["image"] = $newImage;
                $data["products"] = $products;
                menu_storage_save($data);
                $warn = empty($products[$idx]["image"]) ? "У товара сейчас нет изображения." : "";
                admin_redirect("Товар обновлен.", $warn, $imgErr, $id, null, "menu");
            }
        }
    }

    if ($action === "delete") {
        $tab = "menu";
        $id = (string)($_POST["id"] ?? "");
        $idx = menu_storage_find_product_index($products, $id);
        if ($idx < 0) {
            $flashErr = "Товар не найден.";
        } else {
            $catId = (string)($products[$idx]["category_id"] ?? "");
            array_splice($products, $idx, 1);
            $data["products"] = $products;
            menu_storage_save($data);
            admin_redirect("Товар удален.", "", "", null, $catId !== "" ? $catId : null, "menu");
        }
    }

    if ($action === "category_create") {
        $tab = "categories";
        $title = trim((string)($_POST["category_title"] ?? ""));
        if ($title === "") {
            $flashErr = "Введите название категории.";
        } else {
            $newId = menu_storage_create_category($categories, $title);
            $data["categories"] = $categories;
            menu_storage_save($data);
            admin_redirect("Категория «" . htmlspecialchars($title) . "» добавлена.", "", "", null, $newId, "categories");
        }
    }

    if ($action === "category_update") {
        $tab = "categories";
        $id = (string)($_POST["category_id"] ?? "");
        $title = trim((string)($_POST["category_title"] ?? ""));
        if ($title === "") {
            $flashErr = "Введите название категории.";
        } elseif (menu_storage_update_category($categories, $id, $title)) {
            $data["categories"] = $categories;
            menu_storage_save($data);
            admin_redirect("Категория обновлена.", "", "", null, $id, "categories");
        } else {
            $flashErr = "Категория не найдена.";
        }
    }

    if ($action === "category_delete") {
        $tab = "categories";
        $id = (string)($_POST["category_id"] ?? "");
        $err = menu_storage_delete_category($categories, $products, $id);
        if ($err === "") {
            $data["categories"] = $categories;
            menu_storage_save($data);
            admin_redirect("Категория удалена.", "", "", "", "", "categories");
        } else {
            $flashErr = $err;
        }
    }

    if ($action === "category_move_up" || $action === "category_move_down") {
        $tab = "categories";
        $id = (string)($_POST["category_id"] ?? "");
        $dir = $action === "category_move_up" ? -1 : 1;
        if (menu_storage_category_move($categories, $id, $dir)) {
            $data["categories"] = $categories;
            menu_storage_save($data);
            admin_redirect("Порядок категорий обновлен.", "", "", null, $id, "categories");
        } else {
            $flashErr = "Не удалось изменить порядок.";
        }
    }

    if ($action === "product_move_up" || $action === "product_move_down") {
        $tab = "menu";
        $id = (string)($_POST["product_id"] ?? "");
        $dir = $action === "product_move_up" ? -1 : 1;
        if (menu_storage_product_move($products, $id, $dir)) {
            $data["products"] = $products;
            menu_storage_save($data);
            admin_redirect("Порядок позиций обновлен.", "", "", "", "", "menu");
        } else {
            $flashErr = "Не удалось изменить порядок.";
        }
    }

    if ($action === "gallery_create") {
        $tab = "gallery";
        $caption = trim((string)($_POST["caption"] ?? ""));
        [$image, $uploadErr] = gallery_storage_handle_upload_debug($_FILES["image"] ?? null);
        $imgSelected = !empty($_FILES["image"]["tmp_name"]) || (!empty($_FILES["image"]["name"]) && (int)($_FILES["image"]["error"] ?? 0) !== 4);

        if (!$imgSelected || !$image) {
            $flashErr = $imgSelected ? ("Не удалось загрузить фото. " . $uploadErr) : "Выберите фото.";
        } elseif ($caption === "") {
            $flashErr = "Введите подпись.";
        } else {
            $id = gallery_storage_new_id();
            $galleryItems[] = ["id" => $id, "image" => $image, "caption" => $caption];
            $galleryData["items"] = $galleryItems;
            gallery_storage_save($galleryData);
            admin_redirect("Позиция добавлена.", "", "", $id, null, "gallery");
        }
    }

    if ($action === "gallery_update") {
        $tab = "gallery";
        $id = (string)($_POST["item_id"] ?? "");
        $caption = trim((string)($_POST["caption"] ?? ""));
        $idx = gallery_storage_find_index($galleryItems, $id);

        if ($idx < 0) {
            $flashErr = "Позиция не найдена.";
        } elseif ($caption === "") {
            $flashErr = "Введите подпись.";
        } else {
            $galleryItems[$idx]["caption"] = $caption;
            [$newImage, $uploadErr] = gallery_storage_handle_upload_debug($_FILES["image"] ?? null);
            $imgSelected = !empty($_FILES["image"]["tmp_name"]) || (!empty($_FILES["image"]["name"]) && (int)($_FILES["image"]["error"] ?? 0) !== 4);
            $imgErr = "";
            if ($imgSelected) {
                if ($newImage) $galleryItems[$idx]["image"] = $newImage;
                else $imgErr = "Не удалось загрузить фото. " . $uploadErr;
            }
            $galleryData["items"] = $galleryItems;
            gallery_storage_save($galleryData);
            admin_redirect("Позиция обновлена.", "", $imgErr, $id, null, "gallery");
        }
    }

    if ($action === "gallery_delete") {
        $tab = "gallery";
        $id = (string)($_POST["item_id"] ?? "");
        $idx = gallery_storage_find_index($galleryItems, $id);
        if ($idx < 0) {
            $flashErr = "Позиция не найдена.";
        } else {
            array_splice($galleryItems, $idx, 1);
            $galleryData["items"] = $galleryItems;
            gallery_storage_save($galleryData);
            admin_redirect("Позиция удалена.", "", "", "", "", "gallery");
        }
    }

    if ($action === "gallery_move_up" || $action === "gallery_move_down") {
        $tab = "gallery";
        $id = (string)($_POST["item_id"] ?? "");
        $dir = $action === "gallery_move_up" ? -1 : 1;
        if (gallery_storage_move($galleryItems, $id, $dir)) {
            $galleryData["items"] = $galleryItems;
            gallery_storage_save($galleryData);
            admin_redirect("Порядок обновлен.", "", "", "", "", "gallery");
        } else {
            $flashErr = "Не удалось изменить порядок.";
        }
    }

    $data = menu_storage_load();
    $categories = $data["categories"] ?? [];
    $products = $data["products"] ?? [];
    $galleryData = gallery_storage_load();
    $galleryItems = $galleryData["items"] ?? [];
}

$productsByCat = [];
foreach ($products as $p) {
    if (!is_array($p)) continue;
    $cid = (string)($p["category_id"] ?? "");
    if ($cid === "") $cid = "_unknown";
    if (!isset($productsByCat[$cid])) $productsByCat[$cid] = [];
    $productsByCat[$cid][] = $p;
}
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

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mirai Admin</title>
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div>
                <h1 class="title">Панель управления</h1>
                <div class="muted admin-topbar-meta">
                    Вход: <?= htmlspecialchars((string)($_SESSION["admin_username"] ?? "admin")) ?>
                </div>
            </div>
            <form method="post" class="form-plain">
                <input type="hidden" name="action" value="logout">
                <button class="btn btn-danger" type="submit">Выйти</button>
            </form>
        </div>

        <?php if ($flashErr !== ""): ?>
            <div class="err"><?= htmlspecialchars($flashErr) ?></div>
        <?php endif; ?>
        <?php if ($flashWarn !== ""): ?>
            <div class="err flash-warn">
                <?= htmlspecialchars($flashWarn) ?>
            </div>
        <?php endif; ?>
        <?php if ($flashOk !== ""): ?>
            <div class="ok"><?= htmlspecialchars($flashOk) ?></div>
        <?php endif; ?>

        <div class="admin-layout">
            <nav class="admin-nav">
                <a href="/admin/dashboard.php?tab=categories" class="<?= $tab === "categories" ? "active" : "" ?>">Категории</a>
                <a href="/admin/dashboard.php?tab=menu" class="<?= $tab === "menu" ? "active" : "" ?>">Меню</a>
                <a href="/admin/dashboard.php?tab=gallery" class="<?= $tab === "gallery" ? "active" : "" ?>">Галерея</a>
                <a href="/orders/">Заказы</a>
                <a href="/admin/vip.php">VIP / Корпоратив</a>
            </nav>

            <main class="admin-main">
                <?php if ($tab === "categories"): ?>
                    <!-- КАТЕГОРИИ -->
                    <div class="card">
                        <h2>Категории (порядок в меню)</h2>
                        <p class="muted admin-lead">
                            Порядок категорий соответствует отображению в гостевом меню. Используйте ↑↓ для изменения.
                        </p>
                        <form method="post" class="admin-form-create-cat">
                            <input type="hidden" name="action" value="category_create">
                            <input type="hidden" name="tab" value="categories">
                            <div class="admin-form-create-cat__field">
                                <label for="new_cat_title">Новая категория</label>
                                <input id="new_cat_title" name="category_title" type="text" placeholder="Название категории" required>
                            </div>
                            <button class="btn" type="submit">Создать</button>
                        </form>
                        <div class="admin-cat-list">
                            <?php foreach ($categories as $i => $c):
                                $cid = (string)($c["id"] ?? "");
                                $ctitle = (string)($c["title"] ?? $cid);
                                if ($cid === "") continue;
                                $prodCount = $productsByCat[$cid] ?? [];
                                $prodCount = is_array($prodCount) ? count($prodCount) : 0;
                                $canMoveUp = $i > 0;
                                $canMoveDown = $i < count($categories) - 1;
                            ?>
                            <div class="admin-cat-row" id="catrow-<?= htmlspecialchars($cid) ?>">
                                <div class="admin-cat-order">
                                    <form method="post" class="form-inline">
                                        <input type="hidden" name="action" value="category_move_up">
                                        <input type="hidden" name="category_id" value="<?= htmlspecialchars($cid) ?>">
                                        <input type="hidden" name="tab" value="categories">
                                        <button type="submit" class="btn-icon" title="Поднять" <?= !$canMoveUp ? "disabled" : "" ?>>↑</button>
                                    </form>
                                    <form method="post" class="form-inline">
                                        <input type="hidden" name="action" value="category_move_down">
                                        <input type="hidden" name="category_id" value="<?= htmlspecialchars($cid) ?>">
                                        <input type="hidden" name="tab" value="categories">
                                        <button type="submit" class="btn-icon" title="Опустить" <?= !$canMoveDown ? "disabled" : "" ?>>↓</button>
                                    </form>
                                </div>
                                <div class="admin-cat-info">
                                    <span class="admin-cat-num"><?= $i + 1 ?>.</span>
                                    <span class="admin-cat-name"><?= htmlspecialchars($ctitle) ?></span>
                                    <code class="admin-cat-id"><?= htmlspecialchars($cid) ?></code>
                                    <span class="pill"><?= $prodCount ?> шт.</span>
                                </div>
                                <div class="admin-cat-actions">
                                    <button type="button" class="btn btn-sm" onclick="editCat('<?= htmlspecialchars(addslashes($cid)) ?>', '<?= htmlspecialchars(addslashes($ctitle)) ?>')">Изменить</button>
                                    <form method="post" class="form-inline" onsubmit="return confirm('Удалить категорию «<?= htmlspecialchars(addslashes($ctitle)) ?>»?');">
                                        <input type="hidden" name="action" value="category_delete">
                                        <input type="hidden" name="category_id" value="<?= htmlspecialchars($cid) ?>">
                                        <input type="hidden" name="tab" value="categories">
                                        <button type="submit" class="btn btn-sm btn-danger" <?= $prodCount > 0 ? "disabled title='В категории есть товары'" : "" ?>>Удалить</button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!count($categories)): ?>
                            <div class="muted">Нет категорий. Создайте первую выше.</div>
                        <?php endif; ?>
                    </div>
                    <div id="editCatModal" class="modal">
                        <div class="modal-inner">
                            <h3>Редактировать категорию</h3>
                            <form method="post" id="editCatForm">
                                <input type="hidden" name="action" value="category_update">
                                <input type="hidden" name="tab" value="categories">
                                <input type="hidden" name="category_id" id="editCatId">
                                <label for="editCatTitle">Название</label>
                                <input id="editCatTitle" name="category_title" type="text" required>
                                <div class="modal-actions">
                                    <button class="btn" type="submit">Сохранить</button>
                                    <button type="button" class="btn js-modal-close" data-modal="editCatModal">Отмена</button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php elseif ($tab === "menu"): ?>
                    <!-- МЕНЮ -->
                    <div class="grid">
                        <div class="card">
                            <h2>Добавить товар</h2>
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="create">
                                <input type="hidden" name="tab" value="menu">
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
                                        <label for="weight">Граммовка</label>
                                        <input id="weight" name="weight" type="text" placeholder="250 г">
                                    </div>
                                    <div>
                                        <label for="category">Категория</label>
                                        <select id="category" name="category_id" required>
                                            <option value="" disabled selected>Выберите…</option>
                                            <?php foreach ($categories as $c):
                                                $cid = (string)($c["id"] ?? "");
                                                $ct = (string)($c["title"] ?? $cid);
                                            ?>
                                                <option value="<?= htmlspecialchars($cid) ?>"><?= htmlspecialchars($ct) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <label for="description">Описание полное (для карточки товара)</label>
                                <textarea id="description" name="description" placeholder="Подробное описание…"></textarea>
                                <label for="description_short">Описание краткое (для миниатюры)</label>
                                <input id="description_short" name="description_short" type="text" placeholder="1–2 фразы для превью">
                                <label for="image">Фото (jpg/png/webp)</label>
                                <input id="image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp,image/*">
                                <div class="admin-checkbox-row">
                                    <label class="admin-checkbox-label">
                                        <input type="checkbox" name="visible" value="1" checked> Показывать гостям
                                    </label>
                                </div>
                                <div class="spacer-h-12"></div>
                                <div class="actions"><button class="btn" type="submit">Добавить</button></div>
                                <div class="hint hint-mt">
                                    Краткое — показывается в миниатюре, полное — в раскрытой карточке. Стоп‑лист — чекбокс «Показывать гостям».
                                </div>
                            </form>
                        </div>
                        <div class="card">
                            <h2>Позиции (<?= count($products) ?>)</h2>
                            <p class="muted admin-lead">
                                Порядок внутри категории — ↑↓. Порядок категорий задаётся во вкладке «Категории».
                            </p>
                            <?php if (!count($products)): ?>
                                <div class="muted">Пока нет товаров.</div>
                            <?php else:
                                $globalIndex = 0;
                                foreach ($categoryOrder as $cid):
                                    $items = $productsByCat[$cid] ?? [];
                                    if (!count($items)) continue;
                                    $catTitle = category_title($categories, $cid);
                            ?>
                            <div class="admin-cat" id="cat-<?= htmlspecialchars($cid) ?>">
                                <div class="admin-cat-head">
                                    <div class="admin-cat-head-title"><?= htmlspecialchars($catTitle) ?></div>
                                    <span class="pill"><?= count($items) ?> шт.</span>
                                </div>
                                <div class="admin-list">
                                    <?php $catIndex = 0; foreach ($items as $p):
                                        $catIndex++;
                                        $globalIndex++;
                                        $pid = (string)($p["id"] ?? "");
                                        $img = (string)($p["image"] ?? "");
                                        $isVisible = !empty($p["visible"]);
                                        $name = (string)($p["name"] ?? "");
                                        $price = (int)($p["price"] ?? 0);
                                        $desc = (string)($p["description"] ?? "");
                                        $descShort = (string)($p["description_short"] ?? "");
                                        $canMoveUp = $catIndex > 1;
                                        $canMoveDown = $catIndex < count($items);
                                    ?>
                                    <details class="admin-item" id="item-<?= htmlspecialchars($pid) ?>" <?= $scrollOpen === $pid ? "open" : "" ?>>
                                        <summary class="admin-item-sum">
                                            <div class="admin-item-order">
                                                <span class="admin-item-num">№<?= $catIndex ?></span>
                                                <form method="post" class="form-plain"><input type="hidden" name="action" value="product_move_up"><input type="hidden" name="product_id" value="<?= htmlspecialchars($pid) ?>"><input type="hidden" name="tab" value="menu"><button type="submit" class="btn-icon" title="Поднять" <?= !$canMoveUp ? "disabled" : "" ?>>↑</button></form>
                                                <form method="post" class="form-plain"><input type="hidden" name="action" value="product_move_down"><input type="hidden" name="product_id" value="<?= htmlspecialchars($pid) ?>"><input type="hidden" name="tab" value="menu"><button type="submit" class="btn-icon" title="Опустить" <?= !$canMoveDown ? "disabled" : "" ?>>↓</button></form>
                                            </div>
                                            <div class="admin-item-left">
                                                <?php if ($img !== ""): ?>
                                                    <img class="thumb" src="/<?= htmlspecialchars($img) ?>" alt="">
                                                <?php else: ?>
                                                    <div class="thumb thumb-placeholder">—</div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="admin-num">#<?= $globalIndex ?> · <?= $catIndex ?>/<?= count($items) ?></div>
                                                    <div class="admin-item-title"><?= htmlspecialchars($name) ?></div>
                                                    <div class="muted admin-item-code"><code><?= htmlspecialchars($pid) ?></code></div>
                                                </div>
                                            </div>
                                            <div class="admin-item-right">
                                                <div class="admin-item-price"><?= $price ?> ₽</div>
                                                <?= $isVisible ? "<span class='pill'>Показ</span>" : "<span class='pill pill--hidden'>Скрыт</span>" ?>
                                            </div>
                                        </summary>
                                        <div class="admin-item-body">
                                            <form method="post" enctype="multipart/form-data">
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="id" value="<?= htmlspecialchars($pid) ?>">
                                                <input type="hidden" name="tab" value="menu">
                                                <?php $weight = (string)($p["weight"] ?? ""); ?>
                                                <div class="row-3">
                                                    <div><label>Название</label><input name="name" type="text" required value="<?= htmlspecialchars($name) ?>"></div>
                                                    <div><label>Цена (₽)</label><input name="price" type="number" min="0" required value="<?= $price ?>"></div>
                                                    <div><label>Граммовка</label><input name="weight" type="text" value="<?= htmlspecialchars($weight) ?>" placeholder="250 г"></div>
                                                    <div><label>Категория</label>
                                                        <select name="category_id" required>
                                                            <?php foreach ($categories as $c): $ccid = (string)($c["id"] ?? ""); $ct = (string)($c["title"] ?? $ccid); $selected = $ccid === (string)($p["category_id"] ?? ""); ?>
                                                                <option value="<?= htmlspecialchars($ccid) ?>" <?= $selected ? "selected" : "" ?>><?= htmlspecialchars($ct) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <label>Описание полное</label>
                                                <textarea name="description"><?= htmlspecialchars($desc) ?></textarea>
                                                <label>Описание краткое (для миниатюры)</label>
                                                <input name="description_short" type="text" value="<?= htmlspecialchars($descShort) ?>" placeholder="1–2 фразы">
                                                <label>Фото</label>
                                                <input name="image" type="file" accept=".jpg,.jpeg,.png,.webp,image/*">
                                                <div class="admin-field-mt">
                                                    <label class="admin-checkbox-label"><input type="checkbox" name="visible" value="1" <?= $isVisible ? "checked" : "" ?>> Показывать гостям</label>
                                                </div>
                                                <div class="actions admin-actions-mt"><button class="btn" type="submit">Сохранить</button></div>
                                            </form>
                                            <form method="post" class="admin-field-mt" onsubmit="return confirm('Удалить товар?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= htmlspecialchars($pid) ?>">
                                                <input type="hidden" name="tab" value="menu">
                                                <button class="btn btn-danger" type="submit">Удалить</button>
                                            </form>
                                        </div>
                                    </details>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- ГАЛЕРЕЯ -->
                    <div class="card card-mb">
                        <h2>Добавить позицию (стол)</h2>
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="gallery_create">
                            <input type="hidden" name="tab" value="gallery">
                            <div class="row-3">
                                <div>
                                    <label for="new_caption">Подпись</label>
                                    <input id="new_caption" name="caption" type="text" placeholder="Например: Стол у окна" required>
                                </div>
                                <div>
                                    <label for="new_image">Фото</label>
                                    <input id="new_image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp,image/*" required>
                                </div>
                                <div class="admin-form-actions-end">
                                    <button class="btn" type="submit">Добавить</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="card">
                        <h2>Позиции галереи (<?= count($galleryItems) ?>)</h2>
                        <p class="muted admin-lead">
                            Порядок соответствует отображению в гостевой галерее. Используйте ↑↓ для изменения.
                        </p>
                        <?php if (!count($galleryItems)): ?>
                            <div class="muted">Нет позиций. Добавьте первую выше.</div>
                        <?php else: ?>
                            <div class="admin-cat-list">
                                <?php foreach ($galleryItems as $i => $it):
                                    $itId = (string)($it["id"] ?? "");
                                    $img = (string)($it["image"] ?? "");
                                    $caption = (string)($it["caption"] ?? "");
                                    $canUp = $i > 0;
                                    $canDown = $i < count($galleryItems) - 1;
                                ?>
                                <div class="admin-cat-row" id="item-<?= htmlspecialchars($itId) ?>">
                                    <div class="admin-cat-order">
                                        <form method="post" class="form-inline">
                                            <input type="hidden" name="action" value="gallery_move_up">
                                            <input type="hidden" name="item_id" value="<?= htmlspecialchars($itId) ?>">
                                            <input type="hidden" name="tab" value="gallery">
                                            <button type="submit" class="btn-icon" title="Поднять" <?= !$canUp ? "disabled" : "" ?>>↑</button>
                                        </form>
                                        <form method="post" class="form-inline">
                                            <input type="hidden" name="action" value="gallery_move_down">
                                            <input type="hidden" name="item_id" value="<?= htmlspecialchars($itId) ?>">
                                            <input type="hidden" name="tab" value="gallery">
                                            <button type="submit" class="btn-icon" title="Опустить" <?= !$canDown ? "disabled" : "" ?>>↓</button>
                                        </form>
                                    </div>
                                    <div class="admin-cat-info admin-cat-info-grow">
                                        <?php if ($img !== ""): ?>
                                            <img class="thumb" src="/<?= htmlspecialchars($img) ?>" alt="">
                                        <?php else: ?>
                                            <div class="thumb thumb-placeholder">—</div>
                                        <?php endif; ?>
                                        <div>
                                            <span class="admin-cat-num"><?= $i + 1 ?>.</span>
                                            <span class="admin-cat-name"><?= htmlspecialchars($caption) ?></span>
                                            <code class="admin-cat-id"><?= htmlspecialchars($itId) ?></code>
                                        </div>
                                    </div>
                                    <div class="admin-cat-actions">
                                        <button type="button" class="btn btn-sm" onclick="editGalleryItem('<?= htmlspecialchars(addslashes($itId)) ?>', '<?= htmlspecialchars(addslashes($caption)) ?>', '<?= htmlspecialchars(addslashes($img)) ?>')">Изменить</button>
                                        <form method="post" class="form-inline" onsubmit="return confirm('Удалить позицию?');">
                                            <input type="hidden" name="action" value="gallery_delete">
                                            <input type="hidden" name="item_id" value="<?= htmlspecialchars($itId) ?>">
                                            <input type="hidden" name="tab" value="gallery">
                                            <button type="submit" class="btn btn-sm btn-danger">Удалить</button>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div id="editGalleryModal" class="modal">
                        <div class="modal-inner">
                            <h3>Редактировать позицию</h3>
                            <form method="post" enctype="multipart/form-data" id="editGalleryForm">
                                <input type="hidden" name="action" value="gallery_update">
                                <input type="hidden" name="tab" value="gallery">
                                <input type="hidden" name="item_id" id="editGalleryId">
                                <label for="editGalleryCaption">Подпись</label>
                                <input id="editGalleryCaption" name="caption" type="text" required>
                                <label for="editGalleryImage">Фото (оставьте пустым, чтобы не менять)</label>
                                <input id="editGalleryImage" name="image" type="file" accept=".jpg,.jpeg,.png,.webp,image/*">
                                <div id="editGalleryPreview"></div>
                                <div class="modal-actions">
                                    <button class="btn" type="submit">Сохранить</button>
                                    <button type="button" class="btn js-modal-close" data-modal="editGalleryModal">Отмена</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script type="application/json" id="admin-dashboard-context"><?= json_encode(["open" => $scrollOpen, "scroll" => $scrollCat], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <script defer src="/admin/assets/js/dashboard.js"></script>
</body>
</html>
