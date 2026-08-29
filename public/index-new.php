<?php

declare(strict_types=1);

/**
 * Front controller нового стека (Slim). Пока сосуществует со старым public/index.php.
 * При переносе доменов заменит собой старый index.php как единая точка входа.
 */

use Mirai\Http\AppFactory;

/** @var \Psr\Container\ContainerInterface $container */
$container = require dirname(__DIR__) . '/src/bootstrap.php';

AppFactory::create($container)->run();
