<?php

declare(strict_types=1);

/**
 * Точка сборки DI-контейнера. Используется front controller'ом (public/index.php)
 * и консольными утилитами (bin/*). Возвращает готовый PSR-11 контейнер.
 */

use DI\ContainerBuilder;
use Mirai\Infrastructure\Config\Config;
use Mirai\Infrastructure\Security\TableSession;

use function DI\factory;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$projectRoot = dirname(__DIR__);

$builder = new ContainerBuilder();
$builder->useAutowiring(true);
$builder->addDefinitions([
    'projectRoot' => $projectRoot,
    Config::class => static fn (): Config => Config::load($projectRoot),
    // Скалярные аргументы — явная фабрика (автовайринг их не соберёт).
    TableSession::class => factory(static function (Config $config): TableSession {
        $t = $config->tableSession();
        return new TableSession($t['signing_key'], $t['ttl_seconds']);
    }),
]);

return $builder->build();
