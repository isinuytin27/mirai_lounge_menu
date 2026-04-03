<?php
declare(strict_types=1);

require_once __DIR__ . "/lib/auth.php";
require_once __DIR__ . "/lib/vip_storage.php";
require_once __DIR__ . "/../public/inc/mirai_asset.php";

admin_require_login();

vip_storage_ensure_seed_files();

function vip_admin_base_url(): string
{
    $https = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
        || (string)($_SERVER["SERVER_PORT"] ?? "") === "443";
    $host = (string)($_SERVER["HTTP_HOST"] ?? "localhost");
    return ($https ? "https" : "http") . "://" . $host;
}

function vip_guest_url(string $slug, string $token): string
{
    return rtrim(vip_admin_base_url(), "/") . "/vipservice/" . rawurlencode($slug) . "?t=" . rawurlencode($token);
}

function vip_guest_display_name(array $g): string
{
    return trim((string)($g["last_name"] ?? "") . " " . (string)($g["first_name"] ?? ""));
}

$flashOk = "";
$flashErr = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = (string)($_POST["action"] ?? "");
    if ($action === "create_event") {
        $slug = trim((string)($_POST["slug"] ?? ""));
        $org = trim((string)($_POST["organization"] ?? ""));
        $date = trim((string)($_POST["event_date"] ?? ""));
        $limit = max(1, min(20, (int)($_POST["bar_free_limit"] ?? 2)));
        if ($slug === "" || !preg_match('/^[a-z0-9][a-z0-9_-]*$/i', $slug)) {
            $flashErr = "Slug: латиница, цифры, дефис (например gazprom26).";
        } elseif ($org === "" || $date === "") {
            $flashErr = "Заполните организацию и дату.";
        } else {
            $data = vip_storage_load_events();
            if (!isset($data["events"]) || !is_array($data["events"])) {
                $data["events"] = [];
            }
            foreach ($data["events"] as $e) {
                if (is_array($e) && strtolower((string)($e["slug"] ?? "")) === strtolower($slug)) {
                    $flashErr = "Такой slug уже есть.";
                    break;
                }
            }
            if ($flashErr === "") {
                $id = "evt_" . bin2hex(random_bytes(6));
                $data["events"][] = [
                    "id" => $id,
                    "slug" => strtolower($slug),
                    "organization" => $org,
                    "event_date" => $date,
                    "bar_free_limit" => $limit,
                    "bar_line" => "bar",
                    "active" => true,
                    "partner_logo" => "",
                ];
                vip_storage_save_events($data);
                $flashOk = "Мероприятие добавлено.";
                $up = vip_storage_partner_logo_handle_upload($_FILES["partner_logo"] ?? null);
                if ($up["error"] !== null) {
                    $flashErr = "Мероприятие создано. Логотип партнёра: " . $up["error"];
                } elseif ($up["path"] !== null && $up["path"] !== "") {
                    $data = vip_storage_load_events();
                    foreach ($data["events"] as $i => $row) {
                        if (is_array($row) && (string)($row["id"] ?? "") === $id) {
                            $data["events"][$i]["partner_logo"] = $up["path"];
                            vip_storage_save_events($data);
                            $flashOk = "Мероприятие добавлено, логотип партнёра загружен.";
                            break;
                        }
                    }
                }
            }
        }
    }
    if ($action === "update_event") {
        $eid = trim((string)($_POST["event_id"] ?? ""));
        $org = trim((string)($_POST["organization"] ?? ""));
        $date = trim((string)($_POST["event_date"] ?? ""));
        $limit = max(1, min(20, (int)($_POST["bar_free_limit"] ?? 2)));
        $removeLogo = !empty($_POST["remove_partner_logo"]);
        if ($eid === "" || $org === "" || $date === "") {
            $flashErr = "Заполните организацию и дату.";
        } else {
            $data = vip_storage_load_events();
            $idx = null;
            foreach ($data["events"] ?? [] as $i => $e) {
                if (is_array($e) && (string)($e["id"] ?? "") === $eid) {
                    $idx = (int)$i;
                    break;
                }
            }
            if ($idx === null) {
                $flashErr = "Мероприятие не найдено.";
            } else {
                $oldLogo = (string)($data["events"][$idx]["partner_logo"] ?? "");
                $newLogo = $oldLogo;
                $upload = vip_storage_partner_logo_handle_upload($_FILES["partner_logo"] ?? null);
                if ($upload["error"] !== null) {
                    $flashErr = $upload["error"];
                } elseif ($upload["path"] !== null && $upload["path"] !== "") {
                    vip_storage_partner_logo_unlink($oldLogo);
                    $newLogo = $upload["path"];
                } elseif ($removeLogo) {
                    vip_storage_partner_logo_unlink($oldLogo);
                    $newLogo = "";
                }
                if ($flashErr === "") {
                    $data["events"][$idx]["organization"] = $org;
                    $data["events"][$idx]["event_date"] = $date;
                    $data["events"][$idx]["bar_free_limit"] = $limit;
                    $data["events"][$idx]["partner_logo"] = $newLogo;
                    vip_storage_save_events($data);
                    $flashOk = "Мероприятие обновлено.";
                }
            }
        }
    }
    if ($action === "delete_event") {
        $id = trim((string)($_POST["event_id"] ?? ""));
        if ($id !== "") {
            vip_storage_delete_event($id);
            $flashOk = "Мероприятие и гости удалены.";
        }
    }
    if ($action === "toggle_event") {
        $id = (string)($_POST["event_id"] ?? "");
        $data = vip_storage_load_events();
        if (!isset($data["events"]) || !is_array($data["events"])) {
            $data["events"] = [];
        }
        foreach ($data["events"] as $i => $e) {
            if (!is_array($e) || (string)($e["id"] ?? "") !== $id) {
                continue;
            }
            $data["events"][$i]["active"] = empty($e["active"]);
            vip_storage_save_events($data);
            $flashOk = "Статус обновлён.";
            break;
        }
    }
    if ($action === "add_guest") {
        $eid = (string)($_POST["event_id"] ?? "");
        $ln = trim((string)($_POST["last_name"] ?? ""));
        $fn = trim((string)($_POST["first_name"] ?? ""));
        $org = trim((string)($_POST["organization"] ?? ""));
        if ($eid === "" || $fn === "" || $ln === "") {
            $flashErr = "Фамилия и имя обязательны.";
        } else {
            $ev = vip_storage_find_event_by_id($eid);
            if ($ev === null) {
                $flashErr = "Мероприятие не найдено.";
            } else {
                $gdata = vip_storage_load_guests();
                if (!isset($gdata["guests"]) || !is_array($gdata["guests"])) {
                    $gdata["guests"] = [];
                }
                $token = "vip_" . bin2hex(random_bytes(12));
                $gdata["guests"][] = [
                    "id" => "guest_" . bin2hex(random_bytes(6)),
                    "event_id" => $eid,
                    "token" => $token,
                    "first_name" => $fn,
                    "last_name" => $ln,
                    "organization" => $org !== "" ? $org : (string)($ev["organization"] ?? ""),
                    "free_used" => 0,
                    "lines" => [],
                ];
                vip_storage_save_guests($gdata);
                $flashOk = "Гость добавлен.";
            }
        }
    }
    if ($action === "update_guest") {
        $gid = trim((string)($_POST["guest_id"] ?? ""));
        $ln = trim((string)($_POST["last_name"] ?? ""));
        $fn = trim((string)($_POST["first_name"] ?? ""));
        $org = trim((string)($_POST["organization"] ?? ""));
        if ($gid === "" || $ln === "" || $fn === "") {
            $flashErr = "Фамилия и имя обязательны.";
        } elseif (!vip_storage_update_guest($gid, $ln, $fn, $org)) {
            $flashErr = "Не удалось сохранить гостя.";
        } else {
            $flashOk = "Гость обновлён.";
        }
    }
    if ($action === "delete_guest") {
        $gid = trim((string)($_POST["guest_id"] ?? ""));
        if ($gid !== "") {
            vip_storage_delete_guest($gid);
            $flashOk = "Гость удалён.";
        }
    }
    if ($action === "import_guests") {
        $eid = trim((string)($_POST["event_id"] ?? ""));
        $text = (string)($_POST["guest_lines"] ?? "");
        $ev = vip_storage_find_event_by_id($eid);
        if ($ev === null) {
            $flashErr = "Мероприятие не найдено.";
        } else {
            $org = (string)($ev["organization"] ?? "");
            $r = vip_storage_import_guest_lines($eid, $org, $text);
            $flashOk = "Импорт: добавлено " . (int)$r["ok"] . ", пропущено строк: " . (int)$r["skip"] . ".";
        }
    }
}

$events = vip_storage_load_events()["events"] ?? [];
$guestsData = vip_storage_load_guests()["guests"] ?? [];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VIP / Корпоратив — Mirai Admin</title>
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div>
                <h1 class="title">Корпоратив / VIP</h1>
                <div class="muted admin-topbar-meta">NFC, лимит бара, журнал</div>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <a class="btn" href="/admin/dashboard.php">Админка</a>
                <form method="post" class="form-plain" action="/admin/dashboard.php">
                    <input type="hidden" name="action" value="logout">
                    <button class="btn btn-danger" type="submit">Выйти</button>
                </form>
            </div>
        </div>

        <?php if ($flashErr !== ""): ?>
            <div class="err"><?= htmlspecialchars($flashErr) ?></div>
        <?php endif; ?>
        <?php if ($flashOk !== ""): ?>
            <div class="ok"><?= htmlspecialchars($flashOk) ?></div>
        <?php endif; ?>

        <div class="card" style="margin-bottom:16px;">
            <h2>Новое мероприятие</h2>
            <form method="post" class="row-3" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_event">
                <div><label>Slug URL (латиница, цифры)</label><input name="slug" placeholder="gazprom26" required></div>
                <div><label>Организация</label><input name="organization" required></div>
                <div><label>Дата</label><input name="event_date" type="date" required></div>
                <div><label>Бесплатных позиций бара</label><input name="bar_free_limit" type="number" min="1" max="20" value="2"></div>
                <div style="grid-column:1/-1;">
                    <label>Логотип партнёра (SVG, PNG, JPG, WebP)</label>
                    <input name="partner_logo" type="file" accept=".svg,.png,.jpg,.jpeg,.webp,image/svg+xml,image/png,image/jpeg,image/webp">
                </div>
                <div style="display:flex;align-items:flex-end;"><button class="btn" type="submit">Создать</button></div>
            </form>
            <p class="hint hint-mt">Ссылки: <code>/vipservice/<strong>slug</strong>?t=…</code> · после деплоя перезагрузите nginx (исправление PHP под /vipservice/).</p>
        </div>

        <div class="card">
            <h2>Мероприятия и гости</h2>
            <?php foreach ($events as $ev): ?>
                <?php if (!is_array($ev)) {
                    continue;
                } ?>
                <?php
                $eid = (string)($ev["id"] ?? "");
                $slug = (string)($ev["slug"] ?? "");
                $active = !empty($ev["active"]);
                $partnerRel = trim((string)($ev["partner_logo"] ?? ""));
                $partnerPreviewUrl = "";
                if ($partnerRel !== "") {
                    $partnerFull = dirname(__DIR__) . "/public/" . str_replace("\\", "/", $partnerRel);
                    if (is_file($partnerFull)) {
                        $partnerPreviewUrl = mirai_asset($partnerRel);
                    }
                }
                ?>
                <div class="admin-cat" style="margin-bottom:20px;border:1px solid var(--border);border-radius:12px;padding:14px;">
                    <div class="admin-cat-head">
                        <div>
                            <strong><?= htmlspecialchars((string)($ev["organization"] ?? "")) ?></strong>
                            <span class="pill"><?= htmlspecialchars($slug) ?></span>
                            <span class="pill"><?= htmlspecialchars((string)($ev["event_date"] ?? "")) ?></span>
                            <span class="pill">лимит <?= (int)($ev["bar_free_limit"] ?? 2) ?></span>
                            <?= $active ? '<span class="pill">активно</span>' : '<span class="pill pill--hidden">выкл</span>' ?>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <form method="post" class="form-inline" onsubmit="return confirm('Удалить мероприятие и всех гостей?');">
                                <input type="hidden" name="action" value="delete_event">
                                <input type="hidden" name="event_id" value="<?= htmlspecialchars($eid) ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Удалить</button>
                            </form>
                            <form method="post" class="form-inline">
                                <input type="hidden" name="action" value="toggle_event">
                                <input type="hidden" name="event_id" value="<?= htmlspecialchars($eid) ?>">
                                <button type="submit" class="btn btn-sm"><?= $active ? "Деактивировать" : "Включить" ?></button>
                            </form>
                        </div>
                    </div>

                    <h3 class="muted" style="font-size:13px;margin:12px 0 8px;">Редактирование мероприятия</h3>
                    <form method="post" enctype="multipart/form-data" style="margin-bottom:16px;padding:12px;border-radius:10px;background:rgba(0,0,0,.15);border:1px solid var(--border);">
                        <input type="hidden" name="action" value="update_event">
                        <input type="hidden" name="event_id" value="<?= htmlspecialchars($eid) ?>">
                        <div class="admin-form-create-cat" style="margin-bottom:10px;">
                            <div class="admin-form-create-cat__field"><label>Организация</label><input name="organization" value="<?= htmlspecialchars((string)($ev["organization"] ?? ""), ENT_QUOTES, "UTF-8") ?>" required></div>
                            <div class="admin-form-create-cat__field"><label>Дата</label><input name="event_date" type="date" value="<?= htmlspecialchars((string)($ev["event_date"] ?? ""), ENT_QUOTES, "UTF-8") ?>" required></div>
                            <div class="admin-form-create-cat__field"><label>Бесплатных позиций бара</label><input name="bar_free_limit" type="number" min="1" max="20" value="<?= (int)($ev["bar_free_limit"] ?? 2) ?>"></div>
                        </div>
                        <div style="margin-bottom:10px;">
                            <label style="display:block;margin-bottom:6px;">Логотип партнёра (на гостевой странице рядом с Mirai)</label>
                            <?php if ($partnerPreviewUrl !== ""): ?>
                                <div style="margin-bottom:8px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                                    <img src="<?= htmlspecialchars($partnerPreviewUrl, ENT_QUOTES, "UTF-8") ?>" alt="" style="max-height:52px;max-width:220px;object-fit:contain;background:rgba(255,255,255,.06);padding:8px;border-radius:8px;">
                                    <label class="muted" style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                                        <input type="checkbox" name="remove_partner_logo" value="1"> Удалить текущий
                                    </label>
                                </div>
                            <?php endif; ?>
                            <input name="partner_logo" type="file" accept=".svg,.png,.jpg,.jpeg,.webp,image/svg+xml,image/png,image/jpeg,image/webp">
                            <p class="hint" style="margin:6px 0 0;">SVG, PNG, JPG, WebP. Новый файл заменит предыдущий.</p>
                        </div>
                        <button class="btn btn-sm" type="submit">Сохранить мероприятие</button>
                    </form>

                    <h3 class="muted" style="font-size:13px;margin:12px 0 8px;">Импорт списка (по строке: Фамилия Имя)</h3>
                    <form method="post" style="margin-bottom:16px;">
                        <input type="hidden" name="action" value="import_guests">
                        <input type="hidden" name="event_id" value="<?= htmlspecialchars($eid) ?>">
                        <textarea name="guest_lines" rows="8" style="width:100%;max-width:100%;box-sizing:border-box;padding:10px;border-radius:10px;border:1px solid var(--border);background:rgba(0,0,0,.2);color:inherit;font-family:inherit;font-size:13px;" placeholder="Незнанов Богдан&#10;Карамзин Николай"></textarea>
                        <div class="modal-actions" style="margin-top:10px;">
                            <button class="btn" type="submit">Импортировать</button>
                        </div>
                    </form>

                    <h3 class="muted" style="font-size:13px;margin:12px 0 8px;">Добавить гостя</h3>
                    <form method="post" class="admin-form-create-cat">
                        <input type="hidden" name="action" value="add_guest">
                        <input type="hidden" name="event_id" value="<?= htmlspecialchars($eid) ?>">
                        <div class="admin-form-create-cat__field"><label>Фамилия</label><input name="last_name" required autocomplete="off"></div>
                        <div class="admin-form-create-cat__field"><label>Имя</label><input name="first_name" required autocomplete="off"></div>
                        <div class="admin-form-create-cat__field"><label>Организация</label><input name="organization" placeholder="по умолчанию из мероприятия"></div>
                        <div style="display:flex;align-items:flex-end;"><button class="btn" type="submit">Добавить</button></div>
                    </form>

                    <h3 class="muted" style="font-size:13px;margin:16px 0 8px;">Гости и ссылки для NFC</h3>
                    <div class="admin-cat-list">
                        <?php foreach ($guestsData as $g): ?>
                            <?php if (!is_array($g) || (string)($g["event_id"] ?? "") !== $eid) {
                                continue;
                            } ?>
                            <?php
                            $url = vip_guest_url($slug, (string)($g["token"] ?? ""));
                            $gid = (string)($g["id"] ?? "");
                            ?>
                            <div class="admin-cat-row" style="flex-wrap:wrap;">
                                <div class="admin-cat-info admin-cat-info-grow" style="min-width:min(100%,240px);">
                                    <span class="admin-cat-name"><?= htmlspecialchars(vip_guest_display_name($g)) ?></span>
                                    <code class="admin-cat-id"><?= htmlspecialchars((string)($g["organization"] ?? "")) ?></code>
                                    <div class="muted" style="font-size:11px;word-break:break-all;margin-top:6px;">
                                        <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($url) ?></a>
                                    </div>
                                    <form method="post" style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);">
                                        <input type="hidden" name="action" value="update_guest">
                                        <input type="hidden" name="guest_id" value="<?= htmlspecialchars($gid) ?>">
                                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;max-width:400px;">
                                            <div><label style="font-size:11px;">Фамилия</label><input name="last_name" value="<?= htmlspecialchars((string)($g["last_name"] ?? ""), ENT_QUOTES, "UTF-8") ?>" required style="width:100%;"></div>
                                            <div><label style="font-size:11px;">Имя</label><input name="first_name" value="<?= htmlspecialchars((string)($g["first_name"] ?? ""), ENT_QUOTES, "UTF-8") ?>" required style="width:100%;"></div>
                                        </div>
                                        <div style="margin-top:8px;max-width:400px;">
                                            <label style="font-size:11px;">Организация</label><input name="organization" value="<?= htmlspecialchars((string)($g["organization"] ?? ""), ENT_QUOTES, "UTF-8") ?>" style="width:100%;">
                                        </div>
                                        <div class="modal-actions" style="margin-top:10px;">
                                            <button class="btn btn-sm" type="submit">Сохранить</button>
                                        </div>
                                    </form>
                                </div>
                                <div class="admin-cat-actions" style="flex-direction:column;align-items:stretch;">
                                    <button type="button" class="btn btn-sm" onclick="navigator.clipboard.writeText(<?= json_encode($url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)">Копировать URL</button>
                                    <form method="post" class="form-plain" onsubmit="return confirm('Удалить гостя?');">
                                        <input type="hidden" name="action" value="delete_guest">
                                        <input type="hidden" name="guest_id" value="<?= htmlspecialchars($gid) ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" style="width:100%;">Удалить</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
