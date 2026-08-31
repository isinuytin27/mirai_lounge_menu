<?php
declare(strict_types=1);

/** @var array $cfg */
$cfg = require dirname(__DIR__) . "/config/config.php";

require_once __DIR__ . "/inc/mirai_table_session.php";
mirai_table_handle_query_param();

$miraiTableSession = mirai_table_read_session();
$miraiOrderClient = [
    "tableBound" => $miraiTableSession !== null,
    "tableCaption" => $miraiTableSession !== null ? $miraiTableSession["caption"] : null,
];

if (!headers_sent()) {
    header("Cache-Control: no-cache, private, must-revalidate");
    header("Pragma: no-cache");
}
$site = is_array($cfg["site"] ?? null) ? $cfg["site"] : [];

$name = (string)($site["name"] ?? "Mirai Lounge");
$seoTitle = (string)($site["title"] ?? $name);
$description = (string)($site["description"] ?? "");
$keywords = (string)($site["keywords"] ?? "");

$https = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
    || (string)($_SERVER["SERVER_PORT"] ?? "") === "443";
$host = (string)($_SERVER["HTTP_HOST"] ?? "localhost");
$origin = ($https ? "https" : "http") . "://" . $host;

$reqPath = (string)($_SERVER["REQUEST_URI"] ?? "/");
$reqPath = strtok($reqPath, "?") ?: "/";
if ($reqPath === "" || $reqPath[0] !== "/") {
    $reqPath = "/" . $reqPath;
}

$canonical = trim((string)($site["canonical_url"] ?? ""));
if ($canonical === "") {
    $canonical = $origin . $reqPath;
}

$ogPath = ltrim((string)($site["og_image_path"] ?? "favicon.png"), "/");
$ogImage = $origin . "/" . $ogPath;

$twitterSite = trim((string)($site["twitter_site"] ?? ""));
$themeColor = (string)($site["theme_color"] ?? "#000000");

$jsonLd = [
    "@context" => "https://schema.org",
    "@type" => "FoodEstablishment",
    "name" => $name,
    "description" => $description,
    "url" => $canonical,
];
if ($ogImage !== "") {
    $jsonLd["image"] = $ogImage;
}

$jsonLdScript = json_encode(
    $jsonLd,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
if ($jsonLdScript === false) {
    $jsonLdScript = "{}";
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($description, ENT_QUOTES, "UTF-8") ?>">
    <?php if ($keywords !== ""): ?>
    <meta name="keywords" content="<?= htmlspecialchars($keywords, ENT_QUOTES, "UTF-8") ?>">
    <?php endif; ?>
    <meta name="robots" content="index, follow">
    <meta name="author" content="<?= htmlspecialchars($name, ENT_QUOTES, "UTF-8") ?>">
    <link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES, "UTF-8") ?>">
    <meta name="yandex-verification" content="d9ad069b849eba48">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= htmlspecialchars($name, ENT_QUOTES, "UTF-8") ?>">
    <meta property="og:title" content="<?= htmlspecialchars($seoTitle, ENT_QUOTES, "UTF-8") ?>">
    <meta property="og:description" content="<?= htmlspecialchars($description, ENT_QUOTES, "UTF-8") ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonical, ENT_QUOTES, "UTF-8") ?>">
    <meta property="og:locale" content="ru_RU">
    <meta property="og:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES, "UTF-8") ?>">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($seoTitle, ENT_QUOTES, "UTF-8") ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($description, ENT_QUOTES, "UTF-8") ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES, "UTF-8") ?>">
    <?php if ($twitterSite !== ""): ?>
    <meta name="twitter:site" content="<?= htmlspecialchars($twitterSite, ENT_QUOTES, "UTF-8") ?>">
    <?php endif; ?>

    <meta name="theme-color" content="<?= htmlspecialchars($themeColor, ENT_QUOTES, "UTF-8") ?>">

    <title><?= htmlspecialchars($seoTitle, ENT_QUOTES, "UTF-8") ?></title>
    <link rel="icon" href="/favicon.png" type="image/png">

    <link rel="stylesheet" href="/assets/css/reset.css">
    <link rel="stylesheet" href="/assets/css/variables.css">
    <link rel="stylesheet" href="/assets/css/main.css">

    <link rel="stylesheet" href="/assets/css/menu.css">
    <link rel="stylesheet" href="/assets/css/booking.css">
    <link rel="stylesheet" href="/assets/css/gallery.css">
    <link rel="stylesheet" href="/assets/css/about.css">
    <link rel="stylesheet" href="/assets/css/loader.css">

    <script type="application/ld+json"><?= $jsonLdScript ?></script>

    <script defer src="/assets/js/loader.js"></script>
</head>
<body>

    <script type="application/json" id="mirai-order-context"><?= json_encode($miraiOrderClient, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

    <div id="app-loader" class="app-loader" aria-label="Загрузка">
        <div class="loader-card">
            <img class="loader-logo" src="/assets/img/logo/logo-vert.svg" alt="Mirai Lounge" loading="eager" />
            <h1 class="loader-title">MIRAI LOUNGE</h1>
            <div class="loader-sub" data-loader-text>Подготавливаем меню…</div>
            <div class="loader-bar" role="progressbar" aria-valuetext="Загрузка">
                <i></i>
            </div>
        </div>
    </div>

    <div id="viewport">

        <?php include "screens/about.php"; ?>
        <?php include "screens/booking.php"; ?>
        <?php include "screens/home.php"; ?>
        <?php include "screens/menu.php"; ?>
        <?php include "screens/gallery.php"; ?>

    </div>

    <script defer src="/assets/js/mirai-bootstrap.js"></script>
    <script defer src="/assets/js/app.js"></script>
    <script defer src="/assets/js/navigation.js"></script>
    <script defer src="/assets/js/gestures.js"></script>
    <script defer src="/assets/js/about.js"></script>
    <script defer src="/assets/js/gallery.js"></script>
    <script defer src="/assets/js/menu.js"></script>
    <script defer src="/assets/js/vpn-hint.js"></script>

</body>
</html>
