<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers\Admin;

use Mirai\Domain\Tournaments\TournamentRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/** Турниры в админке — Postgres. Заменяет admin/tournaments.php + tournaments_storage.php. */
final class TournamentAdminController
{
    public function __construct(private readonly TournamentRepository $repo) {}

    public function index(Request $request, Response $response): Response
    {
        return Twig::fromRequest($request)->render($response, 'admin/tournaments/index.twig', [
            'settings' => $this->repo->settings(),
            'applications' => $this->repo->applications(),
            'counts' => $this->repo->counts(),
            'slots_left' => $this->repo->slotsLeft(),
            'statuses' => TournamentRepository::STATUSES,
            'sources' => TournamentRepository::SOURCES,
            'flash' => $request->getQueryParams()['ok'] ?? null,
        ]);
    }

    public function saveSettings(Request $request, Response $response): Response
    {
        $b = (array) $request->getParsedBody();
        $this->repo->saveSettings([
            'title' => trim((string) ($b['title'] ?? '')) ?: null,
            'max_slots' => (int) ($b['max_slots'] ?? 10),
            'format' => $b['format'] ?? '',
            'roster' => $b['roster'] ?? '',
            'deadline' => $b['deadline'] ?? '',
            'fee' => $b['fee'] ?? '',
            'registration_open' => isset($b['registration_open']),
        ]);

        return $this->back($response);
    }

    public function appStatus(Request $request, Response $response, array $args): Response
    {
        $b = (array) $request->getParsedBody();
        $this->repo->setStatus((string) $args['id'], (string) ($b['status'] ?? ''));

        return $this->back($response);
    }

    public function appDelete(Request $request, Response $response, array $args): Response
    {
        $this->repo->delete((string) $args['id']);

        return $this->back($response);
    }

    private function back(Response $response): Response
    {
        return $response->withStatus(302)->withHeader('Location', '/admin/tournaments?ok=Сохранено');
    }
}
