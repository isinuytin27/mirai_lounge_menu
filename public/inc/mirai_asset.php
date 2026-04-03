<?php
declare(strict_types=1);

/**
 * URL-префикс папки public/ относительно DOCUMENT_ROOT.
 * Если сайт открыт как http://localhost/myproject/public/index.php, а не из корня vhost,
 * без префикса браузер запрашивает /assets/... с корня хоста и получает 404.
 */
function mirai_public_url_base(): string
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $override = trim((string)(getenv("MIRAI_PUBLIC_BASE") ?: ""));
    if ($override !== "" && $override[0] === "/") {
        $cache = rtrim($override, "/");
        return $cache;
    }

    $docRaw = (string)($_SERVER["DOCUMENT_ROOT"] ?? "");
    $doc = $docRaw !== "" ? realpath($docRaw) : false;
    $pub = realpath(dirname(__DIR__));

    if ($doc === false || $pub === false) {
        $cache = "";
        return $cache;
    }

    $docNorm = rtrim(str_replace("\\", "/", $doc), "/");
    $pubNorm = rtrim(str_replace("\\", "/", $pub), "/");

    if (!str_starts_with(strtolower($pubNorm), strtolower($docNorm))) {
        $cache = "";
        return $cache;
    }

    $rel = strlen($pubNorm) > strlen($docNorm)
        ? substr($pubNorm, strlen($docNorm))
        : "";
    $rel = trim(str_replace("\\", "/", $rel), "/");
    if ($rel === "") {
        $cache = "";
        return $cache;
    }

    $cache = "/" . $rel;
    return $cache;
}

/**
 * URL к файлу в public/: учёт подпапки + ?v=mtime. Внешние URL без изменений.
 */
function mirai_asset(string $pathOrUrl): string
{
    $raw = trim($pathOrUrl);
    if ($raw === "") {
        return "";
    }
    if (
        str_starts_with($raw, "http://")
        || str_starts_with($raw, "https://")
        || str_starts_with($raw, "//")
        || str_starts_with($raw, "data:")
    ) {
        return $raw;
    }

    $path = ltrim(str_replace(["\\", "\0"], ["/", ""], $raw), "/");
    $full = dirname(__DIR__) . "/" . $path;
    $v = is_file($full) ? filemtime($full) : time();

    $prefix = mirai_public_url_base();
    $url = ($prefix === "" ? "/" : $prefix . "/") . $path;

    return $url . "?v=" . $v;
}
