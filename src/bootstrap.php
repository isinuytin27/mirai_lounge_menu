<?php

declare(strict_types=1);

/**
 * Точка сборки DI-контейнера. Используется front controller'ом (public/index.php)
 * и консольными утилитами (bin/*). Возвращает готовый PSR-11 контейнер.
 */

use DI\ContainerBuilder;
use Mirai\Domain\Menu\MenuRepository;
use Mirai\Domain\Menu\ProductFinder;
use Mirai\Domain\Orders\OrderRepository;
use Mirai\Domain\Orders\OrderStore;
use Mirai\Domain\Orders\TableRegistry;
use Mirai\Domain\Orders\TableRepository;
use Mirai\Http\Middleware\RateLimitMiddleware;
use Mirai\Infrastructure\Config\Config;
use Mirai\Infrastructure\Notify\NullOrderNotifier;
use Mirai\Infrastructure\Notify\OrderNotifier;
use Mirai\Infrastructure\Security\TableSession;

use function DI\autowire;
use function DI\create;
use function DI\factory;
use function DI\get;

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

    // Привязки доменных интерфейсов к реализациям.
    ProductFinder::class => get(MenuRepository::class),
    OrderStore::class => get(OrderRepository::class),
    TableRegistry::class => get(TableRepository::class),
    OrderNotifier::class => autowire(NullOrderNotifier::class),

    // Rate-limit для приёма заказа: свой ключ, интервал 2с (как в старом API).
    RateLimitMiddleware::class => create()->constructor('order_submit', 2),
]);

return $builder->build();
