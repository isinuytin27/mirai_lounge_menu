<?php

declare(strict_types=1);

/**
 * Конфигурация Phinx-миграций. Креды БД берём из того же Config, что и приложение,
 * поэтому миграции и рантайм всегда смотрят в одну базу.
 */

require_once __DIR__ . '/vendor/autoload.php';

use Mirai\Infrastructure\Config\Config;

$db = Config::load(__DIR__)->db();

return [
    'paths' => [
        'migrations' => __DIR__ . '/migrations',
        'seeds' => __DIR__ . '/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinx_migrations',
        'default_environment' => 'app',
        'app' => [
            'adapter' => 'pgsql',
            'host' => $db['host'],
            'name' => $db['name'],
            'user' => $db['user'],
            'pass' => $db['password'],
            'port' => $db['port'],
            'charset' => 'utf8',
        ],
    ],
    'version_order' => 'creation',
];
