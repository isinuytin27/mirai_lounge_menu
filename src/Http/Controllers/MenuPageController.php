<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers;

use Mirai\Domain\Menu\MenuRepository;
use Mirai\Domain\Menu\Recommender;
use Mirai\Http\Middleware\TableSessionMiddleware;
use Mirai\Http\View\GuestMenuView;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Отдельная страница меню (без SPA-сетки) — тот же экран меню, что и в витрине,
 * но как самостоятельный роут. Данные из Postgres.
 */
final class MenuPageController
{
    public function __construct(
        private readonly MenuRepository $menu,
        private readonly Recommender $recommender,
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        /** @var array{tid:string,caption:string}|null $table */
        $table = $request->getAttribute(TableSessionMiddleware::ATTR);

        return Twig::fromRequest($request)->render($response, 'menu.twig', [
            'menu_view' => GuestMenuView::fromRepository($this->menu, $this->recommender),
            'table_caption' => $table['caption'] ?? null,
            'table_bound' => $table !== null,
        ]);
    }
}
