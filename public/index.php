<?php

declare(strict_types=1);

/**
 * Front controller нового стека (Slim) — единая точка входа приложения.
 * Старый SPA-index сохранён как public/index-legacy.php (fallback, не обслуживается).
 */

use Mirai\Http\AppFactory;

/** @var \Psr\Container\ContainerInterface $container */
$container = require dirname(__DIR__) . '/src/bootstrap.php';

AppFactory::create($container)->run();
