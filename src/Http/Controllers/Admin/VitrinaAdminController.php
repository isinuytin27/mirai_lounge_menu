<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers\Admin;

use Mirai\Domain\Menu\HookahShowcaseRepository;
use Mirai\Infrastructure\Config\Config;
use Mirai\Infrastructure\Upload\FileUploader;
use Mirai\Infrastructure\Upload\UploadException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Views\Twig;

/**
 * Редактор витрины кальянов (админка): витринные поля кальянов, чаши, напитки.
 * Цена/имя/описание кальяна — в меню-админке (это товары); тут — картинки/геометрия/
 * наценки чаш/список напитков. За ролью admin_panel.
 */
final class VitrinaAdminController
{
    private const PHOTO_SUBDIR = 'assets/img/vitrina/uploads';

    public function __construct(
        private readonly HookahShowcaseRepository $repo,
        private readonly FileUploader $uploader,
        private readonly Config $config,
    ) {}

    public function index(Request $request, Response $response): Response
    {
        return Twig::fromRequest($request)->render($response, 'admin/vitrina/index.twig', [
            'hookahs' => $this->repo->adminHookahs(),
            'bowls' => $this->repo->adminBowls(),
            'drinks' => $this->repo->adminDrinks(),
            'flash' => $request->getQueryParams()['ok'] ?? null,
        ]);
    }

    public function saveHookah(Request $request, Response $response, array $args): Response
    {
        $b = (array) $request->getParsedBody();
        $this->repo->updateHookah((int) $args['id'], [
            'image' => $b['image'] ?? '', 'img_w' => $b['img_w'] ?? 0, 'img_h' => $b['img_h'] ?? 0,
            'anchors' => $b['anchors'] ?? '', 'model' => $b['model'] ?? '', 'active' => isset($b['active']),
        ]);

        return $this->back($response, 'Кальян обновлён');
    }

    public function saveBowl(Request $request, Response $response, array $args): Response
    {
        $b = (array) $request->getParsedBody();
        $id = isset($args['id']) ? (int) $args['id'] : null;
        if (trim((string) ($b['name'] ?? '')) !== '') {
            $this->repo->saveBowl($id, [
                'name' => $b['name'], 'image' => $b['image'] ?? '', 'img_w' => $b['img_w'] ?? 0,
                'img_h' => $b['img_h'] ?? 0, 'f' => $b['f'] ?? 0.5, 'extra' => $b['extra'] ?? 0,
                'kind' => $b['kind'] ?? '', 'active' => isset($b['active']),
            ]);
        }

        return $this->back($response, 'Чаша сохранена');
    }

    public function deleteBowl(Request $request, Response $response, array $args): Response
    {
        $this->repo->deleteBowl((int) $args['id']);

        return $this->back($response, 'Чаша удалена');
    }

    public function saveDrink(Request $request, Response $response, array $args): Response
    {
        $b = (array) $request->getParsedBody();
        $id = isset($args['id']) ? (int) $args['id'] : null;
        if (trim((string) ($b['name'] ?? '')) !== '') {
            $this->repo->saveDrink($id, ['name' => $b['name'], 'image' => $b['image'] ?? '', 'active' => isset($b['active'])]);
        }

        return $this->back($response, 'Напиток сохранён');
    }

    public function deleteDrink(Request $request, Response $response, array $args): Response
    {
        $this->repo->deleteDrink((int) $args['id']);

        return $this->back($response, 'Напиток удалён');
    }

    /** POST /admin/vitrina/image — загрузка картинки, возвращает путь (для инлайн-JS). */
    public function uploadImage(Request $request, Response $response): Response
    {
        $file = $request->getUploadedFiles()['image'] ?? null;
        if (!$file instanceof UploadedFileInterface) {
            return $this->json($response, 422, ['ok' => false, 'error' => 'no_file']);
        }
        try {
            $name = $this->uploader->save($file, $this->config->uploadDir(self::PHOTO_SUBDIR));
        } catch (UploadException $e) {
            return $this->json($response, 422, ['ok' => false, 'error' => $e->getMessage()]);
        }

        return $this->json($response, 200, ['ok' => true, 'path' => '/' . self::PHOTO_SUBDIR . '/' . $name]);
    }

    private function back(Response $response, string $msg): Response
    {
        return $response->withStatus(302)->withHeader('Location', '/admin/vitrina?ok=' . rawurlencode($msg));
    }

    /** @param array<string,mixed> $payload */
    private function json(Response $response, int $status, array $payload): Response
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_UNICODE));

        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
