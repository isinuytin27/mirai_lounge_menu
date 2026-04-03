<?php
declare(strict_types=1);

/**
 * Карта сайта для поисковиков (одна страница — SPA).
 * Подключите в Search Console: https://ваш-домен/sitemap.php
 */
$https = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
    || (string)($_SERVER["SERVER_PORT"] ?? "") === "443";
$host = (string)($_SERVER["HTTP_HOST"] ?? "localhost");
$origin = ($https ? "https" : "http") . "://" . $host;

$scriptDir = str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/"));
if ($scriptDir === "/" || $scriptDir === ".") {
    $home = $origin . "/";
} else {
    $home = rtrim($origin . $scriptDir, "/") . "/";
}

$lastmod = gmdate("Y-m-d");

header("Content-Type: application/xml; charset=UTF-8");
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc><?= htmlspecialchars($home, ENT_XML1 | ENT_QUOTES, "UTF-8") ?></loc>
    <lastmod><?= htmlspecialchars($lastmod, ENT_XML1 | ENT_QUOTES, "UTF-8") ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
  </url>
</urlset>
