<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers\Admin;

use Mirai\Domain\Gallery\GalleryAdminRepository;
use Mirai\Infrastructure\Config\Config;
use Mirai\Infrastructure\Upload\FileUploader;
use Mirai\Infrastructure\Upload\UploadException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Views\Twig;

/** Галерея в админке — Postgres + загрузка файлов через FileUploader. */
final class GalleryAdminController
{
    private const SUBDIR = 'assets/img/gallery/uploads';

    public function __construct(
        private readonly GalleryAdminRepository $repo,
        private readonly FileUploader $uploader,
        private readonly Config $config,
    ) {}

    public function index(Request $request, Response $response): Response
    {
        return Twig::fromRequest($request)->render($response, 'admin/gallery/index.twig', [
            'items' => $this->repo->all(),
            'flash' => $request->getQueryParams()['msg'] ?? null,
            'error' => $request->getQueryParams()['err'] ?? null,
        ]);
    }

    public function upload(Request $request, Response $response): Response
    {
        $files = $request->getUploadedFiles();
        $file = $files['image'] ?? null;
        $caption = trim((string) (((array) $request->getParsedBody())['caption'] ?? ''));

        if (!$file instanceof UploadedFileInterface) {
            return $this->back($response, err: 'Файл не выбран');
        }

        try {
            $name = $this->uploader->save($file, $this->config->uploadDir(self::SUBDIR));
        } catch (UploadException $e) {
            return $this->back($response, err: $e->getMessage());
        }

        $this->repo->add(self::SUBDIR . '/' . $name, $caption ?: null);

        return $this->back($response, msg: 'Фото добавлено');
    }

    public function updateCaption(Request $request, Response $response, array $args): Response
    {
        $b = (array) $request->getParsedBody();
        $this->repo->updateCaption((string) $args['id'], trim((string) ($b['caption'] ?? '')) ?: null);

        return $this->back($response, msg: 'Подпись обновлена');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $image = $this->repo->delete((string) $args['id']);
        // Удаляем файл, если он в нашей папке загрузок (не трогаем сиды из репозитория).
        if ($image !== null && str_starts_with($image, self::SUBDIR . '/')) {
            $path = $this->config->uploadDir('') . '/' . $image;
            if (is_file($path)) {
                @unlink($path);
            }
        }

        return $this->back($response, msg: 'Фото удалено');
    }

    private function back(Response $response, ?string $msg = null, ?string $err = null): Response
    {
        $q = $msg !== null ? '?msg=' . rawurlencode($msg) : ($err !== null ? '?err=' . rawurlencode($err) : '');

        return $response->withStatus(302)->withHeader('Location', '/admin/gallery' . $q);
    }
}
