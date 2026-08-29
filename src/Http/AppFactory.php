<?php

declare(strict_types=1);

namespace Mirai\Http;

use Mirai\Infrastructure\Config\Config;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

/**
 * Сборка Slim-приложения: контейнер, Twig, middleware, маршруты.
 * Единственная точка конфигурации HTTP-слоя.
 */
final class AppFactory
{
    public static function create(ContainerInterface $container): App
    {
        $config = $container->get(Config::class);
        $projectRoot = (string) $container->get('projectRoot');

        SlimAppFactory::setContainer($container);
        $app = SlimAppFactory::create();

        // Twig: шаблоны в src/View. Кэш выключен в dev, включён в prod.
        $twig = Twig::create($projectRoot . '/src/View', [
            'cache' => $config->isProd() ? $projectRoot . '/var/cache/twig' : false,
            'debug' => !$config->isProd(),
        ]);
        $twig->getEnvironment()->addGlobal('site', $config->site());
        $twig->getEnvironment()->addGlobal('app_version', $config->appVersion());

        $app->add(TwigMiddleware::create($app, $twig));
        $app->addRoutingMiddleware();
        $app->addBodyParsingMiddleware();

        // Показ ошибок только вне прода.
        $app->addErrorMiddleware(!$config->isProd(), true, true);

        (require __DIR__ . '/routes.php')($app);

        return $app;
    }
}
