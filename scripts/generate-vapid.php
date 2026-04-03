#!/usr/bin/env php
<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$autoload = $root . "/vendor/autoload.php";
if (!is_file($autoload)) {
    fwrite(STDERR, "Сначала выполните: composer install\n");
    exit(1);
}
require_once $autoload;

$keys = Minishlink\WebPush\VAPID::createVapidKeys();
echo "Добавьте в .env (в корне проекта) или в config/config.php -> webpush:\n\n";
echo "MIRAI_VAPID_PUBLIC=" . $keys["publicKey"] . "\n";
echo "MIRAI_VAPID_PRIVATE=" . $keys["privateKey"] . "\n";
echo "\nНа Windows без локального PHP: docker\\gen-vapid.bat (контейнер mirai-php должен быть запущен).\n";
