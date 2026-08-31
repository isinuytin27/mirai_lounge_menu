<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers;

use Mirai\Domain\Admin\AuthService;
use Mirai\Domain\Menu\MenuRepository;
use Mirai\Domain\Vip\VipRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * VIP-страница по slug: гость (по токену) видит остаток бесплатных напитков,
 * staff (админ-сессия) — консоль списания. Порт public/vipservice/index.php, данные из Postgres.
 */
final class VipServiceController
{
    public function __construct(
        private readonly VipRepository $vip,
        private readonly AuthService $auth,
        private readonly MenuRepository $menu,
    ) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $slug = (string) $args['slug'];
        $token = trim((string) ($request->getQueryParams()['t'] ?? ''));
        $event = $this->vip->findEventBySlug($slug);
        $guest = ($event !== null && $token !== '')
            ? $this->vip->findGuestForEvent((string) $event['id'], $token)
            : null;
        $isStaff = $this->auth->currentUser() !== null;

        $barProducts = [];
        if ($event !== null && $guest !== null && $isStaff) {
            $barLine = (string) ($event['bar_line'] ?? 'bar');
            foreach ($this->menu->visibleMenu() as $grp) {
                foreach ($grp['products'] as $p) {
                    if ($p->line === $barLine) {
                        $barProducts[] = ['slug' => $p->slug, 'name' => $p->name, 'price' => $p->price];
                    }
                }
            }
        }

        $freeLeft = null;
        if ($guest !== null && $event !== null) {
            $freeLeft = max(0, (int) $event['bar_free_limit'] - (int) $guest['free_used']);
        }

        return Twig::fromRequest($request)->render($response, 'vip/service.twig', [
            'event' => $event,
            'guest' => $guest,
            'is_staff' => $isStaff,
            'token' => $token,
            'free_left' => $freeLeft,
            'bar_products' => $barProducts,
        ]);
    }
}
