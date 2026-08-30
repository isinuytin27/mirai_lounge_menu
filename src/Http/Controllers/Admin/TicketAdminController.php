<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers\Admin;

use Mirai\Domain\Admin\AdminUser;
use Mirai\Domain\Support\Ticket;
use Mirai\Domain\Support\TicketRepository;
use Mirai\Http\Middleware\AuthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/** Тикеты в админке — Postgres. Заменяет admin/tickets.php + tickets_storage.php. */
final class TicketAdminController
{
    public function __construct(private readonly TicketRepository $repo) {}

    public function index(Request $request, Response $response): Response
    {
        return Twig::fromRequest($request)->render($response, 'admin/tickets/index.twig', [
            'tickets' => $this->repo->all(),
            'counts' => $this->repo->counts(),
            'categories' => Ticket::CATEGORIES,
            'priorities' => Ticket::PRIORITIES,
            'statuses' => Ticket::STATUSES,
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $b = (array) $request->getParsedBody();
        $title = trim((string) ($b['title'] ?? ''));
        if ($title !== '') {
            /** @var AdminUser|null $user */
            $user = $request->getAttribute(AuthMiddleware::ATTR);
            $this->repo->create(
                $title,
                trim((string) ($b['description'] ?? '')) ?: null,
                (string) ($b['category'] ?? 'other'),
                (string) ($b['priority'] ?? 'normal'),
                $user?->displayName(),
            );
        }

        return $this->back($response);
    }

    public function setStatus(Request $request, Response $response, array $args): Response
    {
        $b = (array) $request->getParsedBody();
        $this->repo->setStatus((string) $args['id'], (string) ($b['status'] ?? ''));

        return $this->back($response);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $this->repo->delete((string) $args['id']);

        return $this->back($response);
    }

    private function back(Response $response): Response
    {
        return $response->withStatus(302)->withHeader('Location', '/admin/tickets');
    }
}
