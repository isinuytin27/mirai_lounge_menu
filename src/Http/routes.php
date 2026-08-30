<?php

declare(strict_types=1);

use Mirai\Http\Controllers\Admin\AdminAuthController;
use Mirai\Http\Controllers\Admin\AdminDashboardController;
use Mirai\Http\Controllers\Admin\GalleryAdminController;
use Mirai\Http\Controllers\Admin\MenuAdminController;
use Mirai\Http\Controllers\Admin\TicketAdminController;
use Mirai\Http\Controllers\HealthController;
use Mirai\Http\Controllers\HomeController;
use Mirai\Http\Controllers\MenuApiController;
use Mirai\Http\Controllers\MenuPageController;
use Mirai\Http\Controllers\OrderSubmitController;
use Mirai\Http\Controllers\SeoController;
use Mirai\Http\Controllers\TableEntryController;
use Mirai\Http\Controllers\VersionController;
use Mirai\Http\Middleware\AuthMiddleware;
use Mirai\Http\Middleware\CsrfMiddleware;
use Mirai\Http\Middleware\RateLimitMiddleware;
use Mirai\Http\Middleware\RoleMiddleware;
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

    // --- Админка (Postgres-аутентификация) ---
    $app->get('/admin/login', [AdminAuthController::class, 'showLogin']);
    $app->post('/admin/login', [AdminAuthController::class, 'login'])->add(CsrfMiddleware::class);
    $app->get('/admin/logout', [AdminAuthController::class, 'logout']);

    // Обзор — за входом и правом на админ-панель.
    $app->get('/admin', AdminDashboardController::class)
        ->add(new RoleMiddleware('admin_panel'))
        ->add(AuthMiddleware::class);

    // Меню-админка (запись в Postgres). Auth -> Role -> CSRF (на мутациях).
    $app->group('/admin/menu', function ($g): void {
        $g->get('', [MenuAdminController::class, 'index']);
        $g->get('/product/{slug}', [MenuAdminController::class, 'editProduct']);
        $g->post('/product/{slug}', [MenuAdminController::class, 'saveProduct']);
        $g->post('/toggle/{slug}/{field}', [MenuAdminController::class, 'toggle']);
        $g->post('/product/{slug}/pairing', [MenuAdminController::class, 'addPairing']);
        $g->post('/product/{slug}/pairing/{id}/delete', [MenuAdminController::class, 'removePairing']);
    })
        ->add(CsrfMiddleware::class)
        ->add(new RoleMiddleware('admin_panel'))
        ->add(AuthMiddleware::class);

    // Тикеты (Postgres). Право tickets = owner/admin.
    $app->group('/admin/tickets', function ($g): void {
        $g->get('', [TicketAdminController::class, 'index']);
        $g->post('', [TicketAdminController::class, 'create']);
        $g->post('/{id}/status', [TicketAdminController::class, 'setStatus']);
        $g->post('/{id}/delete', [TicketAdminController::class, 'delete']);
    })
        ->add(CsrfMiddleware::class)
        ->add(new RoleMiddleware('tickets'))
        ->add(AuthMiddleware::class);

    // Галерея (Postgres + загрузка файлов).
    $app->group('/admin/gallery', function ($g): void {
        $g->get('', [GalleryAdminController::class, 'index']);
        $g->post('/upload', [GalleryAdminController::class, 'upload']);
        $g->post('/{id}/caption', [GalleryAdminController::class, 'updateCaption']);
        $g->post('/{id}/delete', [GalleryAdminController::class, 'delete']);
    })
        ->add(CsrfMiddleware::class)
        ->add(new RoleMiddleware('admin_panel'))
        ->add(AuthMiddleware::class);
};
