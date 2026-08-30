<?php

declare(strict_types=1);

use Mirai\Http\Controllers\HealthController;
use Mirai\Http\Controllers\HomeController;
use Mirai\Http\Controllers\MenuApiController;
use Mirai\Http\Controllers\MenuPageController;
use Mirai\Http\Controllers\OrderSubmitController;
use Mirai\Http\Controllers\SeoController;
use Mirai\Http\Controllers\TableEntryController;
use Mirai\Http\Controllers\VersionController;
use Mirai\Http\Middleware\RateLimitMiddleware;
use Mirai\Http\Middleware\TableSessionMiddleware;
use Slim\App;

/**
 * Единая таблица маршрутов (public + api). Админ/staff-маршруты добавятся в срезе Auth.
 */
return static function (App $app): void {
    // Технический health-check (JSON).
    $app->get('/_health', HealthController::class);

    // Версия (Twig).
    $app->get('/version', VersionController::class);

    // QR-вход стола: /t?table=<id> -> cookie + redirect.
    $app->get('/t', TableEntryController::class);

    // Гостевая витрина (SPA: лоадер + свайпы). Стол — из cookie; /?table= обрабатывает QR.
    $app->get('/', HomeController::class)->add(TableSessionMiddleware::class);
    // Отдельная страница меню (без SPA-сетки).
    $app->get('/menu', MenuPageController::class)->add(TableSessionMiddleware::class);

    // API меню (JSON) — для фронта и проверки данных.
    $app->get('/api/menu', MenuApiController::class);

    // Приём заказа. table-session (проверка cookie) + rate-limit (too_fast).
    // Legacy-путь .php сохранён, чтобы текущий menu.js работал без правок.
    $app->map(['POST'], '/api/order-submit[.php]', OrderSubmitController::class)
        ->add(RateLimitMiddleware::class)
        ->add(TableSessionMiddleware::class);

    // SEO.
    $app->get('/robots.txt', [SeoController::class, 'robots']);
    $app->get('/sitemap.xml', [SeoController::class, 'sitemap']);
};
