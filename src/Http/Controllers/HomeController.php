<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers;

use Mirai\Domain\Gallery\GalleryRepository;
use Mirai\Domain\Menu\MenuRepository;
use Mirai\Domain\Menu\Recommender;
use Mirai\Domain\Orders\TableRegistry;
use Mirai\Http\Middleware\TableSessionMiddleware;
use Mirai\Http\View\GuestMenuView;
use Mirai\Infrastructure\Security\TableCookie;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Гостевая витрина (SPA) на новом стеке: лоадер + сетка экранов + свайпы.
 * Порт public/index.php: те же экраны (about/booking/home/menu/gallery), лоадер,
 * оригинальные CSS/JS. Данные меню/галереи — из Postgres.
 *
 * Также обрабатывает QR вида /?table=<id> (как оригинальный index.php).
 */
final class HomeController
{
    public function __construct(
        private readonly MenuRepository $menu,
        private readonly Recommender $recommender,
        private readonly GalleryRepository $gallery,
        private readonly TableRegistry $tables,
        private readonly TableCookie $tableCookie,
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        // QR: /?table=<id> — валидируем стол, ставим cookie, редиректим на / без query.
        $tableId = trim((string) ($request->getQueryParams()['table'] ?? ''));
        if ($tableId !== '') {
            $redirect = $response->withStatus(302)->withHeader('Location', $request->getUri()->getPath() ?: '/');
            if ($this->tables->activeExists($tableId)) {
                $caption = $this->tables->captionOf($tableId) ?? $tableId;
                $https = $request->getUri()->getScheme() === 'https';
                $redirect = $redirect->withHeader('Set-Cookie', $this->tableCookie->header($tableId, $caption, $https));
            }
            return $redirect;
        }

        /** @var array{tid:string,caption:string}|null $table */
        $table = $request->getAttribute(TableSessionMiddleware::ATTR);

        return Twig::fromRequest($request)->render($response, 'index.twig', [
            'menu_view' => GuestMenuView::fromRepository($this->menu, $this->recommender),
            'gallery' => $this->gallery->all(),
            'table_caption' => $table['caption'] ?? null,
            'table_bound' => $table !== null,
        ]);
    }
}
