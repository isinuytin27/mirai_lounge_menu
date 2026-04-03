<?php
declare(strict_types=1);

/**
 * Маршрутизатор для встроенного сервера PHP при запуске ИЗ КОРНЯ репозитория:
 *
 *   php -S 127.0.0.1:8080 router.php
 *
 * Иначе ссылки вида /assets/css/main.css отдают 404 (файлы лежат в public/assets/).
 * Альтернатива: cd public && php -S 127.0.0.1:8080
 */
$public = realpath(__DIR__ . "/public");
if ($public === false) {
    http_response_code(500);
    header("Content-Type: text/plain; charset=UTF-8");
    echo "router.php: каталог public/ не найден.\n";
    exit(1);
}

$uriPath = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH);
$uriPath = is_string($uriPath) ? rawurldecode($uriPath) : "/";
if ($uriPath === "" || $uriPath[0] !== "/") {
    $uriPath = "/" . $uriPath;
}

$fullPath = realpath($public . $uriPath);
if ($fullPath !== false && str_starts_with($fullPath, $public) && is_file($fullPath)) {
    return false;
}

if ($fullPath !== false && str_starts_with($fullPath, $public) && is_dir($fullPath)) {
    $indexInDir = $fullPath . "/index.php";
    if (is_file($indexInDir)) {
        chdir($fullPath);
        $_SERVER["SCRIPT_FILENAME"] = $indexInDir;
        require $indexInDir;
        return true;
    }
}

/* Корпоратив VIP: /vipservice/slug без отдельного файла на каждый slug */
if (str_starts_with($uriPath, "/vipservice")) {
    $vipIndex = $public . "/vipservice/index.php";
    if (is_file($vipIndex)) {
        chdir($public . "/vipservice");
        $_SERVER["SCRIPT_FILENAME"] = $vipIndex;
        require $vipIndex;
        return true;
    }
}

chdir($public);
$_SERVER["SCRIPT_FILENAME"] = $public . "/index.php";
require $public . "/index.php";
return true;
