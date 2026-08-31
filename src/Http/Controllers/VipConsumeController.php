<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers;

use Mirai\Domain\Admin\AuthService;
use Mirai\Domain\Menu\MenuRepository;
use Mirai\Domain\Vip\VipRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Списание напитка бара VIP-гостю (staff-консоль). Порт public/api/vip-consume.php.
 * Требует админ-сессию (JSON 403), пишет в Postgres. Контракт: {ok, free_used, free_left}.
 */
final class VipConsumeController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly VipRepository $vip,
        private readonly MenuRepository $menu,
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        if ($this->auth->currentUser() === null) {
            return $this->json($response, 403, ['ok' => false, 'error' => 'auth']);
        }

        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return $this->json($response, 400, ['ok' => false, 'error' => 'bad_json']);
        }

        $slug = trim((string) ($body['event_slug'] ?? ''));
        $token = trim((string) ($body['token'] ?? ''));
        $productId = trim((string) ($body['product_id'] ?? ''));
        $paidByGuest = !empty($body['paid_by_guest']);

        if ($slug === '' || $token === '' || $productId === '') {
            return $this->json($response, 400, ['ok' => false, 'error' => 'fields']);
        }

        $event = $this->vip->findEventBySlug($slug);
        if ($event === null) {
            return $this->json($response, 404, ['ok' => false, 'error' => 'event']);
        }

        $guest = $this->vip->findGuestForEvent((string) $event['id'], $token);
        if ($guest === null) {
            return $this->json($response, 404, ['ok' => false, 'error' => 'guest']);
        }

        $product = $this->menu->findVisibleProduct($productId);
        if ($product === null) {
            return $this->json($response, 400, ['ok' => false, 'error' => 'product']);
        }

        $result = $this->vip->consume($event, $guest, $product->slug, $product->line, $paidByGuest);
        if (!$result['ok']) {
            $error = $result['error'] ?? 'fail';
            $code = in_array($error, ['limit_reached', 'not_bar'], true) ? 409 : 400;
            return $this->json($response, $code, ['ok' => false, 'error' => $error]);
        }

        return $this->json($response, 200, [
            'ok' => true,
            'free_used' => $result['free_used'] ?? 0,
            'free_left' => $result['free_left'] ?? 0,
            'guest' => [
                'free_used' => $result['free_used'] ?? 0,
                'free_left' => $result['free_left'] ?? 0,
            ],
        ]);
    }

    /** @param array<string,mixed> $payload */
    private function json(Response $response, int $status, array $payload): Response
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
