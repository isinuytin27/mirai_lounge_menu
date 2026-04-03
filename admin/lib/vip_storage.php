<?php
declare(strict_types=1);

require_once __DIR__ . "/auth.php";

function vip_storage_config_paths(): array
{
    $cfg = admin_config();
    $st = $cfg["storage"] ?? [];
    $e = (string)($st["vip_events_json_path"] ?? "");
    $g = (string)($st["vip_guests_json_path"] ?? "");
    return [$e, $g];
}

function vip_storage_ensure_dirs(): void
{
    [$eventsPath, $guestsPath] = vip_storage_config_paths();
    foreach ([dirname($eventsPath), dirname($guestsPath)] as $dir) {
        if ($dir !== "" && !is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }
    vip_storage_partner_ensure_upload_dir();
}

/** @return array{0: string, 1: string} */
function vip_storage_partner_config(): array
{
    $cfg = admin_config();
    $st = $cfg["storage"] ?? [];
    $dir = (string)($st["vip_partner_upload_dir"] ?? "");
    $prefix = (string)($st["vip_partner_upload_public_prefix"] ?? "assets/img/vip/partner_uploads");
    return [$dir, $prefix];
}

function vip_storage_partner_ensure_upload_dir(): void
{
    [$dir] = vip_storage_partner_config();
    if ($dir !== "" && !is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

function vip_storage_partner_logo_unlink(string $relativePath): void
{
    $relativePath = trim(str_replace("\\", "/", $relativePath), "/");
    if ($relativePath === "") {
        return;
    }
    [, $prefix] = vip_storage_partner_config();
    $prefix = trim(str_replace("\\", "/", $prefix), "/");
    if (!str_starts_with($relativePath, $prefix . "/")) {
        return;
    }
    $baseName = basename($relativePath);
    if ($baseName === "" || $baseName === "." || $baseName === "..") {
        return;
    }
    $proj = dirname(__DIR__, 2);
    $full = $proj . "/public/" . $relativePath;
    $real = realpath($full);
    $allowedDir = realpath($proj . "/public/" . $prefix);
    if ($real === false || $allowedDir === false || !str_starts_with($real, $allowedDir)) {
        return;
    }
    if (is_file($real)) {
        @unlink($real);
    }
}

/**
 * @return array{path: string|null, error: string|null}
 */
function vip_storage_partner_logo_handle_upload(?array $file): array
{
    if ($file === null || !isset($file["error"])) {
        return ["path" => null, "error" => null];
    }
    $err = (int)$file["error"];
    if ($err === UPLOAD_ERR_NO_FILE) {
        return ["path" => null, "error" => null];
    }
    vip_storage_partner_ensure_upload_dir();
    [$uploadDir, $publicPrefix] = vip_storage_partner_config();
    if ($uploadDir === "") {
        return ["path" => null, "error" => "Не настроена папка загрузки"];
    }
    $tmp = (string)($file["tmp_name"] ?? "");
    $name = (string)($file["name"] ?? "");
    if ($err !== UPLOAD_ERR_OK) {
        $msg = match ($err) {
            UPLOAD_ERR_INI_SIZE => "Файл больше лимита сервера",
            UPLOAD_ERR_FORM_SIZE => "Файл слишком большой",
            UPLOAD_ERR_PARTIAL => "Загрузка прервана",
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => "Ошибка сервера",
            default => "Ошибка загрузки ($err)",
        };
        return ["path" => null, "error" => $msg];
    }
    if ($tmp === "" || !is_uploaded_file($tmp)) {
        return ["path" => null, "error" => "Неверный файл"];
    }
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed = ["svg", "png", "jpg", "jpeg", "webp"];
    if (!in_array($ext, $allowed, true)) {
        return ["path" => null, "error" => "Допустимы SVG, PNG, JPG, WebP"];
    }
    $safe = "partner_" . bin2hex(random_bytes(8)) . "." . $ext;
    $target = rtrim($uploadDir, "/") . "/" . $safe;
    if (!move_uploaded_file($tmp, $target)) {
        return ["path" => null, "error" => "Не удалось сохранить файл"];
    }
    $rel = trim(str_replace("\\", "/", $publicPrefix), "/") . "/" . $safe;
    return ["path" => $rel, "error" => null];
}

/** Пустое хранилище при первом деплое (мероприятия и гости создаются в админке). */
function vip_storage_default_events(): array
{
    return ["events" => []];
}

function vip_storage_default_guests(): array
{
    return ["guests" => []];
}

function vip_storage_ensure_seed_files(): void
{
    vip_storage_ensure_dirs();
    [$ep, $gp] = vip_storage_config_paths();
    if ($ep !== "" && !is_file($ep)) {
        vip_storage_save_events(vip_storage_default_events());
    }
    if ($gp !== "" && !is_file($gp)) {
        vip_storage_save_guests(vip_storage_default_guests());
    }
}

function vip_storage_load_events(): array
{
    [$path] = vip_storage_config_paths();
    if ($path === "" || !is_file($path)) {
        vip_storage_ensure_seed_files();
        if (is_file($path)) {
            $raw = file_get_contents($path);
            $data = $raw ? json_decode($raw, true) : null;
            if (is_array($data) && is_array($data["events"] ?? null)) {
                return $data;
            }
        }
        return vip_storage_default_events();
    }
    $raw = file_get_contents($path);
    $data = $raw ? json_decode($raw, true) : null;
    if (!is_array($data) || !is_array($data["events"] ?? null)) {
        return vip_storage_default_events();
    }
    return $data;
}

function vip_storage_save_events(array $data): void
{
    vip_storage_ensure_dirs();
    [$path] = vip_storage_config_paths();
    if ($path === "") {
        return;
    }
    file_put_contents(
        $path,
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n",
        LOCK_EX
    );
}

function vip_storage_load_guests(): array
{
    [, $path] = vip_storage_config_paths();
    if ($path === "" || !is_file($path)) {
        vip_storage_ensure_seed_files();
        if (is_file($path)) {
            $raw = file_get_contents($path);
            $data = $raw ? json_decode($raw, true) : null;
            if (is_array($data) && is_array($data["guests"] ?? null)) {
                return $data;
            }
        }
        return vip_storage_default_guests();
    }
    $raw = file_get_contents($path);
    $data = $raw ? json_decode($raw, true) : null;
    if (!is_array($data) || !is_array($data["guests"] ?? null)) {
        return vip_storage_default_guests();
    }
    return $data;
}

function vip_storage_save_guests(array $data): void
{
    vip_storage_ensure_dirs();
    [, $path] = vip_storage_config_paths();
    if ($path === "") {
        return;
    }
    file_put_contents(
        $path,
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n",
        LOCK_EX
    );
}

/** @return array<string,mixed>|null */
function vip_storage_find_event_by_slug(string $slug): ?array
{
    $slug = trim($slug);
    if ($slug === "") {
        return null;
    }
    $data = vip_storage_load_events();
    $events = is_array($data["events"] ?? null) ? $data["events"] : [];
    foreach ($events as $e) {
        if (!is_array($e)) {
            continue;
        }
        if ((string)($e["slug"] ?? "") === $slug && !empty($e["active"])) {
            return $e;
        }
    }
    return null;
}

/** @return array<string,mixed>|null */
function vip_storage_find_event_by_id(string $id): ?array
{
    $data = vip_storage_load_events();
    $events = is_array($data["events"] ?? null) ? $data["events"] : [];
    foreach ($events as $e) {
        if (!is_array($e)) {
            continue;
        }
        if ((string)($e["id"] ?? "") === $id) {
            return $e;
        }
    }
    return null;
}

/**
 * Поиск гостя: token (приоритет) или номер g в рамках события (1-based, демо).
 * @return array{guest: array, index: int}|null
 */
function vip_storage_find_guest_for_event(array $event, ?string $token, ?int $gNum): ?array
{
    $eventId = (string)($event["id"] ?? "");
    $data = vip_storage_load_guests();
    $guests = is_array($data["guests"] ?? null) ? $data["guests"] : [];
    $token = $token !== null ? trim($token) : "";

    if ($token !== "") {
        foreach ($guests as $idx => $guest) {
            if (!is_array($guest)) {
                continue;
            }
            if ((string)($guest["event_id"] ?? "") !== $eventId) {
                continue;
            }
            if ((string)($guest["token"] ?? "") === $token) {
                return ["guest" => $guest, "index" => (int)$idx];
            }
        }
        return null;
    }

    if ($gNum !== null && $gNum >= 1) {
        $n = 0;
        foreach ($guests as $idx => $guest) {
            if (!is_array($guest)) {
                continue;
            }
            if ((string)($guest["event_id"] ?? "") !== $eventId) {
                continue;
            }
            $n++;
            if ($n === $gNum) {
                return ["guest" => $guest, "index" => (int)$idx];
            }
        }
    }

    return null;
}

/** @return array<string,mixed>|null */
function vip_storage_find_guest_by_id(string $guestId): ?array
{
    $guestId = trim($guestId);
    if ($guestId === "") {
        return null;
    }
    $data = vip_storage_load_guests();
    $guests = is_array($data["guests"] ?? null) ? $data["guests"] : [];
    foreach ($guests as $guest) {
        if (!is_array($guest)) {
            continue;
        }
        if ((string)($guest["id"] ?? "") === $guestId) {
            return $guest;
        }
    }
    return null;
}

function vip_storage_delete_event(string $eventId): void
{
    $eventId = trim($eventId);
    if ($eventId === "") {
        return;
    }
    $edata = vip_storage_load_events();
    if (!isset($edata["events"]) || !is_array($edata["events"])) {
        $edata["events"] = [];
    }
    foreach ($edata["events"] as $e) {
        if (is_array($e) && (string)($e["id"] ?? "") === $eventId) {
            vip_storage_partner_logo_unlink((string)($e["partner_logo"] ?? ""));
            break;
        }
    }
    $edata["events"] = array_values(array_filter($edata["events"], static function ($e) use ($eventId) {
        return is_array($e) && (string)($e["id"] ?? "") !== $eventId;
    }));
    vip_storage_save_events($edata);

    $gdata = vip_storage_load_guests();
    if (!isset($gdata["guests"]) || !is_array($gdata["guests"])) {
        $gdata["guests"] = [];
    }
    $gdata["guests"] = array_values(array_filter($gdata["guests"], static function ($g) use ($eventId) {
        return is_array($g) && (string)($g["event_id"] ?? "") !== $eventId;
    }));
    vip_storage_save_guests($gdata);
}

function vip_storage_delete_guest(string $guestId): void
{
    $guestId = trim($guestId);
    if ($guestId === "") {
        return;
    }
    $gdata = vip_storage_load_guests();
    if (!isset($gdata["guests"]) || !is_array($gdata["guests"])) {
        return;
    }
    $gdata["guests"] = array_values(array_filter($gdata["guests"], static function ($g) use ($guestId) {
        return is_array($g) && (string)($g["id"] ?? "") !== $guestId;
    }));
    vip_storage_save_guests($gdata);
}

function vip_storage_update_guest(string $guestId, string $lastName, string $firstName, string $organization): bool
{
    $guestId = trim($guestId);
    $lastName = trim($lastName);
    $firstName = trim($firstName);
    if ($guestId === "" || $lastName === "" || $firstName === "") {
        return false;
    }
    $gdata = vip_storage_load_guests();
    if (!isset($gdata["guests"]) || !is_array($gdata["guests"])) {
        return false;
    }
    foreach ($gdata["guests"] as $i => $g) {
        if (!is_array($g) || (string)($g["id"] ?? "") !== $guestId) {
            continue;
        }
        $gdata["guests"][$i]["last_name"] = $lastName;
        $gdata["guests"][$i]["first_name"] = $firstName;
        $gdata["guests"][$i]["organization"] = trim($organization);
        vip_storage_save_guests($gdata);
        return true;
    }
    return false;
}

/**
 * Импорт строк «Фамилия Имя» (и при необходимости отчество в имени).
 * @return array{ok: int, skip: int}
 */
function vip_storage_import_guest_lines(string $eventId, string $defaultOrg, string $text): array
{
    $eventId = trim($eventId);
    if ($eventId === "") {
        return ["ok" => 0, "skip" => 0];
    }
    $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
    $gdata = vip_storage_load_guests();
    if (!isset($gdata["guests"]) || !is_array($gdata["guests"])) {
        $gdata["guests"] = [];
    }
    $ok = 0;
    $skip = 0;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === "" || str_starts_with($line, "#")) {
            continue;
        }
        $parts = preg_split('/\s+/u', $line) ?: [];
        $parts = array_values(array_filter($parts, static fn ($p) => $p !== ""));
        if (count($parts) < 2) {
            $skip++;
            continue;
        }
        $lastName = $parts[0];
        $firstName = implode(" ", array_slice($parts, 1));
        $token = "vip_" . bin2hex(random_bytes(12));
        $gdata["guests"][] = [
            "id" => "guest_" . bin2hex(random_bytes(6)),
            "event_id" => $eventId,
            "token" => $token,
            "first_name" => $firstName,
            "last_name" => $lastName,
            "organization" => $defaultOrg,
            "free_used" => 0,
            "lines" => [],
        ];
        $ok++;
    }
    vip_storage_save_guests($gdata);
    return ["ok" => $ok, "skip" => $skip];
}

/**
 * Списание: бесплатно (если есть лимит) или за счёт гостя.
 * @return array{ok: bool, error?: string, guest?: array}
 */
function vip_storage_consume(
    array $event,
    array $guest,
    int $guestIndex,
    string $productId,
    string $productName,
    bool $paidByGuest
): array {
    require_once dirname(__DIR__, 2) . "/public/inc/mirai_menu_public.php";
    $p = mirai_menu_public_find_visible_product($productId);
    if ($p === null) {
        return ["ok" => false, "error" => "unknown_product"];
    }
    $line = (string)($p["line"] ?? "");
    $eventLine = (string)($event["bar_line"] ?? "bar");
    if ($line !== $eventLine) {
        return ["ok" => false, "error" => "not_bar"];
    }

    $limit =  (int)($event["bar_free_limit"] ?? 2);
    $freeUsed = (int)($guest["free_used"] ?? 0);

    if ($paidByGuest) {
        $entry = [
            "ts" => date("c"),
            "product_id" => $productId,
            "name" => $productName,
            "paid_free" => false,
            "paid_by_guest" => true,
        ];
    } else {
        if ($freeUsed >= $limit) {
            return ["ok" => false, "error" => "limit_reached"];
        }
        $entry = [
            "ts" => date("c"),
            "product_id" => $productId,
            "name" => $productName,
            "paid_free" => true,
            "paid_by_guest" => false,
        ];
        $guest["free_used"] = $freeUsed + 1;
    }

    $lines = is_array($guest["lines"] ?? null) ? $guest["lines"] : [];
    $lines[] = $entry;
    $guest["lines"] = $lines;

    $all = vip_storage_load_guests();
    $all["guests"][$guestIndex] = $guest;
    vip_storage_save_guests($all);

    return ["ok" => true, "guest" => $guest];
}
