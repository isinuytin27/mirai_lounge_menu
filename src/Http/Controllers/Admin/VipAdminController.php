<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers\Admin;

use Mirai\Domain\Vip\VipRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/** VIP в админке — Postgres. Заменяет admin/vip.php + vip_storage.php. */
final class VipAdminController
{
    public function __construct(private readonly VipRepository $repo) {}

    public function index(Request $request, Response $response): Response
    {
        return Twig::fromRequest($request)->render($response, 'admin/vip/index.twig', [
            'events' => $this->repo->events(),
        ]);
    }

    public function createEvent(Request $request, Response $response): Response
    {
        $b = (array) $request->getParsedBody();
        $slug = trim((string) ($b['slug'] ?? ''));
        if ($slug === '') {
            return $response->withStatus(302)->withHeader('Location', '/admin/vip');
        }
        $id = $this->repo->saveEvent($this->eventData($b));

        return $response->withStatus(302)->withHeader('Location', '/admin/vip/' . $id);
    }

    public function editEvent(Request $request, Response $response, array $args): Response
    {
        $event = $this->repo->findEventById((string) $args['id']);
        if ($event === null) {
            return $response->withStatus(302)->withHeader('Location', '/admin/vip');
        }

        return Twig::fromRequest($request)->render($response, 'admin/vip/event.twig', [
            'event' => $event,
            'guests' => $this->repo->guests((string) $args['id']),
            'flash' => $request->getQueryParams()['ok'] ?? null,
        ]);
    }

    public function saveEvent(Request $request, Response $response, array $args): Response
    {
        $this->repo->saveEvent($this->eventData((array) $request->getParsedBody()), (string) $args['id']);

        return $response->withStatus(302)->withHeader('Location', '/admin/vip/' . $args['id'] . '?ok=Событие+сохранено');
    }

    public function deleteEvent(Request $request, Response $response, array $args): Response
    {
        $this->repo->deleteEvent((string) $args['id']);

        return $response->withStatus(302)->withHeader('Location', '/admin/vip');
    }

    public function addGuest(Request $request, Response $response, array $args): Response
    {
        $b = (array) $request->getParsedBody();
        $first = trim((string) ($b['first_name'] ?? ''));
        $last = trim((string) ($b['last_name'] ?? ''));
        if ($first !== '' || $last !== '') {
            $this->repo->addGuest((string) $args['id'], $first, $last, trim((string) ($b['organization'] ?? '')) ?: null);
        }

        return $response->withStatus(302)->withHeader('Location', '/admin/vip/' . $args['id'] . '?ok=Гость+добавлен');
    }

    public function deleteGuest(Request $request, Response $response, array $args): Response
    {
        $this->repo->deleteGuest((string) $args['gid']);

        return $response->withStatus(302)->withHeader('Location', '/admin/vip/' . $args['id']);
    }

    /**
     * @param array<string,mixed> $b
     * @return array<string,mixed>
     */
    private function eventData(array $b): array
    {
        return [
            'slug' => trim((string) ($b['slug'] ?? '')),
            'organization' => trim((string) ($b['organization'] ?? '')),
            'event_date' => trim((string) ($b['event_date'] ?? '')),
            'bar_free_limit' => (int) ($b['bar_free_limit'] ?? 2),
            'bar_line' => trim((string) ($b['bar_line'] ?? 'bar')) ?: 'bar',
            'active' => isset($b['active']),
        ];
    }
}
