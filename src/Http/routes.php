<?php

declare(strict_types=1);

use Mirai\Http\Controllers\HealthController;
use Mirai\Http\Controllers\SeoController;
use Mirai\Http\Controllers\VersionController;
use Slim\App;

/**
 * Единая таблица маршрутов. Пока — пилотные (health/version/SEO) сквозь новый стек.
 * По мере переноса доменов сюда добавляются их маршруты (public + admin + api).
 */
return static function (App $app): void {
    // Технический health-check (JSON) — без БД, для проверки живости стека.
    $app->get('/_health', HealthController::class);

    // Версия приложения (Twig-рендер) — доказывает, что вью-слой работает.
    $app->get('/version', VersionController::class);

    // SEO
    $app->get('/robots.txt', [SeoController::class, 'robots']);
    $app->get('/sitemap.xml', [SeoController::class, 'sitemap']);
};
