<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers;

use Mirai\Domain\Booking\BookingRepository;
use Mirai\Domain\Booking\HallRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Публичное API брони (замена restoplace): карта зала + занятость, создание брони,
 * лист ожидания. Доступ персонала (список/статусы) — в BookingAdminController за ролями.
 */
final class BookingApiController
{
    public function __construct(
        private readonly BookingRepository $repo,
        private readonly HallRepository $hall,
    ) {}

    /** GET /api/booking/hall?date=YYYY-MM-DD — карта зала (столы/зоны/заметки) + занятость. */
    public function hall(Request $request, Response $response): Response
    {
        $date = $this->date($request->getQueryParams()['date'] ?? '');

        return $this->json($response, 200, [
            'ok' => true,
            'date' => $date,
            'tables' => $this->hall->tables(),
            'zones' => $this->hall->zones(),
            'notes' => $this->hall->notes(),
            'occupancy' => $this->repo->occupancy($date),
        ]);
    }

    /** POST /api/booking — создать бронь (гость). */
    public function create(Request $request, Response $response): Response
    {
        $b = (array) $request->getParsedBody();

        $name = trim((string) ($b['name'] ?? ''));
        $phone = trim((string) ($b['phone'] ?? ''));
        $date = $this->date($b['dateISO'] ?? '');
        if ($name === '' || $phone === '') {
            return $this->json($response, 422, ['ok' => false, 'error' => 'name_phone_required']);
        }

        $b['dateISO'] = $date;
        $b['source'] = 'widget';
        $result = $this->repo->create($b);

        if (!$result['ok']) {
            $status = ($result['error'] ?? '') === 'table_taken' ? 409 : 422;
            return $this->json($response, $status, ['ok' => false, 'error' => $result['error'] ?? 'invalid']);
        }

        return $this->json($response, 200, ['ok' => true, 'booking' => $result['booking']->toArray()]);
    }

    /** POST /api/booking/waitlist — встать в лист ожидания (когда всё занято). */
    public function waitlist(Request $request, Response $response): Response
    {
        $b = (array) $request->getParsedBody();
        $name = trim((string) ($b['name'] ?? ''));
        $phone = trim((string) ($b['phone'] ?? ''));
        if ($name === '' || $phone === '') {
            return $this->json($response, 422, ['ok' => false, 'error' => 'name_phone_required']);
        }
        $b['dateISO'] = $this->date($b['dateISO'] ?? '');

        $id = $this->repo->addWaitlist($b);

        return $this->json($response, 200, ['ok' => true, 'id' => $id]);
    }

    private function date(mixed $v): string
    {
        $s = trim((string) ($v ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) === 1 ? $s : date('Y-m-d');
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function json(Response $response, int $status, array $payload): Response
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_UNICODE));

        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
