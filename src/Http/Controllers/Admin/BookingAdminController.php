<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers\Admin;

use Mirai\Domain\Booking\BookingRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Управление бронями (персонал): список, смена статуса, удаление, лист ожидания.
 * Заменяет админку restoplace/аддона — в нашем стеке за ролями (без общего пароля).
 */
final class BookingAdminController
{
    /** Допустимые статусы брони. */
    private const STATUSES = ['confirmed', 'seated', 'cancelled', 'noshow'];

    public function __construct(private readonly BookingRepository $repo) {}

    public function index(Request $request, Response $response): Response
    {
        return Twig::fromRequest($request)->render($response, 'admin/booking/index.twig', [
            'bookings' => array_map(static fn ($b): array => $b->toArray(), $this->repo->all()),
            'waitlist' => $this->repo->waitlist(),
            'statuses' => self::STATUSES,
            'flash' => $request->getQueryParams()['ok'] ?? null,
        ]);
    }

    public function setStatus(Request $request, Response $response, array $args): Response
    {
        $status = (string) (((array) $request->getParsedBody())['status'] ?? '');
        if (in_array($status, self::STATUSES, true)) {
            $this->repo->setStatus((int) $args['id'], $status);
        }

        return $this->back($response);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $this->repo->delete((int) $args['id']);

        return $this->back($response);
    }

    public function waitlistDelete(Request $request, Response $response, array $args): Response
    {
        $this->repo->removeWaitlist((int) $args['id']);

        return $this->back($response);
    }

    private function back(Response $response): Response
    {
        return $response->withStatus(302)->withHeader('Location', '/admin/booking');
    }
}
