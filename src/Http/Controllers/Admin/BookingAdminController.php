<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers\Admin;

use Mirai\Domain\Booking\BookingRepository;
use Mirai\Domain\Booking\HallRepository;
use Mirai\Infrastructure\Config\Config;
use Mirai\Infrastructure\Upload\FileUploader;
use Mirai\Infrastructure\Upload\UploadException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Views\Twig;

/**
 * Управление бронями (персонал): список, смена статуса, удаление, лист ожидания,
 * + редактор карты зала (столы/зоны/заметки → Postgres). Всё за ролями (без общего пароля).
 */
final class BookingAdminController
{
    /** Допустимые статусы брони. */
    private const STATUSES = ['confirmed', 'seated', 'cancelled', 'noshow'];

    /** Куда кладём фото заметок карты. */
    private const PHOTO_SUBDIR = 'assets/img/hall/uploads';

    public function __construct(
        private readonly BookingRepository $repo,
        private readonly HallRepository $hall,
        private readonly FileUploader $uploader,
        private readonly Config $config,
    ) {}

    // ---------------------------------------------------- редактор зала ----

    /** GET /admin/booking/hall-editor — страница редактора (standalone, за auth+role). */
    public function hallEditor(Request $request, Response $response): Response
    {
        return Twig::fromRequest($request)->render($response, 'booking/hall-editor.twig');
    }

    /** POST /admin/booking/hall — сохранить конфиг карты (JSON: positions/hotspots/notes). */
    public function saveHall(Request $request, Response $response): Response
    {
        $b = (array) $request->getParsedBody();
        $this->hall->saveTables(is_array($b['positions'] ?? null) ? $b['positions'] : []);
        $this->hall->saveZones(is_array($b['hotspots'] ?? null) ? $b['hotspots'] : []);
        $this->hall->saveNotes(is_array($b['notes'] ?? null) ? $b['notes'] : []);

        $response->getBody()->write((string) json_encode(['ok' => true]));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /** POST /admin/booking/hall/photo — загрузка фото заметки, возвращает путь. */
    public function uploadHallPhoto(Request $request, Response $response): Response
    {
        $file = $request->getUploadedFiles()['photo'] ?? null;
        if (!$file instanceof UploadedFileInterface) {
            return $this->jsonErr($response, 'no_file');
        }
        try {
            $name = $this->uploader->save($file, $this->config->uploadDir(self::PHOTO_SUBDIR));
        } catch (UploadException $e) {
            return $this->jsonErr($response, $e->getMessage());
        }

        $response->getBody()->write((string) json_encode(['ok' => true, 'path' => '/' . self::PHOTO_SUBDIR . '/' . $name]));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    private function jsonErr(Response $response, string $error): Response
    {
        $response->getBody()->write((string) json_encode(['ok' => false, 'error' => $error]));

        return $response->withStatus(422)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

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
