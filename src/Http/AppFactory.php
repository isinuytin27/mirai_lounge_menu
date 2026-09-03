<?php

declare(strict_types=1);

namespace Mirai\Http;

use Mirai\Infrastructure\Config\Config;
use Mirai\Infrastructure\Security\Csrf;
use Mirai\Infrastructure\View\ViteAssets;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Twig\TwigFunction;

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
        // Версия ассетов для cache-busting (?v=): в dev — метка времени (всегда свежо),
        // в prod — версия приложения (обновляется при релизе).
        $twig->getEnvironment()->addGlobal('av', $config->isProd() ? $config->appVersion() : (string) time());

        // CSRF-токен для форм админки: ленивая функция (сессию стартует только при вызове,
        // т.е. на админ-страницах, а не на гостевых).
        $csrf = $container->get(Csrf::class);
        $twig->getEnvironment()->addFunction(new TwigFunction(
            'csrf_token',
            static fn (): string => $csrf->token(),
        ));

        // vite('entries/menu.js') -> хешированные <link>/<script> из манифеста.
        $vite = new ViteAssets($projectRoot . '/public');
        $twig->getEnvironment()->addFunction(new TwigFunction(
            'vite',
            static fn (string $entry): string => $vite->tags($entry),
            ['is_safe' => ['html']],
        ));

        // asset_meta('assets/img/...') -> {ext, size, bad} для ярлычков в админке.
        // «Плохой» ассет = не webp ИЛИ больше 2 МБ (сигнал переоптимизировать).
        $twig->getEnvironment()->addFunction(new TwigFunction(
            'asset_meta',
            static function (?string $path) use ($projectRoot): array {
                $rel = ltrim((string) $path, '/');
                $abs = $projectRoot . '/public/' . $rel;
                $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
                $bytes = $rel !== '' && is_file($abs) ? (int) filesize($abs) : 0;
                $bad = ($ext !== '' && $ext !== 'webp') || $bytes > 2 * 1024 * 1024;
                if ($bytes >= 1048576) {
                    $size = number_format($bytes / 1048576, 1) . ' МБ';
                } elseif ($bytes >= 1024) {
                    $size = (int) round($bytes / 1024) . ' КБ';
                } else {
                    $size = $bytes . ' Б';
                }
                return ['ext' => $ext ?: '—', 'size' => $bytes ? $size : '—', 'bad' => $bad, 'bytes' => $bytes];
            },
        ));

        $app->add(TwigMiddleware::create($app, $twig));
        $app->addRoutingMiddleware();
        $app->addBodyParsingMiddleware();

        // Показ ошибок только вне прода.
        $app->addErrorMiddleware(!$config->isProd(), true, true);

        (require __DIR__ . '/routes.php')($app);

        return $app;
    }
}
