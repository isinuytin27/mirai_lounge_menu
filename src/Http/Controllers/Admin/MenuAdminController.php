<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers\Admin;

use Mirai\Domain\Menu\MenuAdminRepository;
use Mirai\Domain\Menu\MenuRepository;
use Mirai\Domain\Menu\Recommender;
use Mirai\Infrastructure\Config\Config;
use Mirai\Infrastructure\Upload\FileUploader;
use Mirai\Infrastructure\Upload\UploadException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Views\Twig;

/**
 * Редактор меню в админке — пишет в Postgres (закрывает раздвоение с гостевым меню).
 * Заменяет write-часть старого admin/dashboard.php + menu_storage.php.
 */
final class MenuAdminController
{
    private const PHOTO_SUBDIR = 'assets/img/menu/uploads';

    public function __construct(
        private readonly MenuAdminRepository $repo,
        private readonly MenuRepository $menu,
        private readonly Recommender $recommender,
        private readonly FileUploader $uploader,
        private readonly Config $config,
    ) {}

    /** POST /admin/menu/product/{slug}/image — загрузить/заменить фото товара. */
    public function uploadProductImage(Request $request, Response $response, array $args): Response
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
        $path = self::PHOTO_SUBDIR . '/' . $name;
        $this->repo->updateProductImage((string) $args['slug'], $path);

        return $this->json($response, 200, ['ok' => true, 'path' => '/' . $path]);
    }

    private function json(Response $response, int $status, array $data): Response
    {
        $response->getBody()->write((string) json_encode($data, JSON_UNESCAPED_UNICODE));

        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public function index(Request $request, Response $response): Response
    {
        return Twig::fromRequest($request)->render($response, 'admin/menu/index.twig', [
            'categories' => $this->repo->categoriesWithProducts(),
            'groups' => $this->repo->allGroups(),
            'flash' => $request->getQueryParams()['ok'] ?? null,
        ]);
    }

    /** POST /admin/menu/category/{id} — переименовать / сменить группу / вкл-выкл. */
    public function saveCategory(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();
        $title = trim((string) ($body['title'] ?? ''));
        $groupId = ($body['group_id'] ?? '') !== '' ? (int) $body['group_id'] : null;
        $active = !empty($body['active']);
        if ($title !== '') {
            $this->repo->updateCategory((int) $args['id'], $title, $groupId, $active);
        }

        return $response->withStatus(302)->withHeader('Location', '/admin/menu?ok=Категория сохранена');
    }

    /** POST /admin/menu/category/{id}/move/{dir} — порядок категории. */
    public function moveCategory(Request $request, Response $response, array $args): Response
    {
        $dir = $args['dir'] === 'up' ? 'up' : 'down';
        $this->repo->moveCategory((int) $args['id'], $dir);

        return $response->withStatus(302)->withHeader('Location', '/admin/menu');
    }

    /** POST /admin/menu/product — создать товар в категории → на страницу редактирования. */
    public function addProduct(Request $request, Response $response): Response
    {
        $b = (array) $request->getParsedBody();
        $catId = (int) ($b['category_id'] ?? 0);
        $name = trim((string) ($b['name'] ?? ''));
        $price = (int) ($b['price'] ?? 0);
        if ($catId <= 0 || $name === '') {
            return $response->withStatus(302)->withHeader('Location', '/admin/menu?ok=Укажите+название+и+категорию');
        }
        $slug = $this->repo->createProduct($catId, $name, $price);

        return $response->withStatus(302)->withHeader('Location', '/admin/menu/product/' . rawurlencode($slug));
    }

    /** POST /admin/menu/product/{slug}/delete — удалить товар. */
    public function deleteProduct(Request $request, Response $response, array $args): Response
    {
        $this->repo->deleteProduct((string) $args['slug']);

        return $response->withStatus(302)->withHeader('Location', '/admin/menu?ok=Товар+удалён');
    }

    /** POST /admin/menu/category — создать категорию в группе. */
    public function addCategory(Request $request, Response $response): Response
    {
        $b = (array) $request->getParsedBody();
        $groupId = (int) ($b['group_id'] ?? 0);
        $title = trim((string) ($b['title'] ?? ''));
        if ($groupId > 0 && $title !== '') {
            $this->repo->createCategory($groupId, $title, $this->lineForGroup($groupId));
        }

        return $response->withStatus(302)->withHeader('Location', '/admin/menu?ok=Категория+добавлена');
    }

    /** POST /admin/menu/category/{id}/delete — удалить категорию (с товарами). */
    public function deleteCategory(Request $request, Response $response, array $args): Response
    {
        $this->repo->deleteCategory((int) $args['id']);

        return $response->withStatus(302)->withHeader('Location', '/admin/menu?ok=Категория+удалена');
    }

    /** Линия выдачи по группе: кальян→hookah, бар→bar, остальное→kitchen. */
    private function lineForGroup(int $groupId): string
    {
        foreach ($this->repo->allGroups() as $g) {
            if ($g['id'] === $groupId) {
                return match ($g['slug']) {
                    'hookah' => 'hookah',
                    'bar' => 'bar',
                    default => 'kitchen',
                };
            }
        }

        return 'kitchen';
    }

    public function editProduct(Request $request, Response $response, array $args): Response
    {
        $slug = (string) $args['slug'];
        $product = $this->repo->getProduct($slug);
        if ($product === null) {
            return $response->withStatus(302)->withHeader('Location', '/admin/menu');
        }

        return Twig::fromRequest($request)->render($response, 'admin/menu/product.twig', [
            'p' => $product,
            'rec_tags' => $this->decodeTags($product['rec_tags'] ?? null),
            'all_tags' => $this->repo->recTags(),
            'recommendations' => $this->previewRecommendations($slug),
        ]);
    }

    /**
     * Что рекомендует движок для этого товара (read-only превью): считаем по видимому
     * меню + сам товар (даже если он скрыт).
     *
     * @return list<\Mirai\Domain\Menu\PairedProduct>
     */
    private function previewRecommendations(string $slug): array
    {
        $target = $this->menu->productWithRec($slug);
        if ($target === null) {
            return [];
        }
        $set = [];
        foreach ($this->menu->visibleProducts() as $p) {
            $set[$p->slug] = $p;
        }
        $set[$target->slug] = $target;

        $pairs = $this->recommender->pairingsFor(array_values($set));

        return $pairs[$slug] ?? [];
    }

    /**
     * @return list<string>
     */
    private function decodeTags(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $raw = json_decode($raw, true);
        }
        return is_array($raw) ? array_values(array_map('strval', $raw)) : [];
    }

    public function saveProduct(Request $request, Response $response, array $args): Response
    {
        $slug = (string) $args['slug'];
        $b = (array) $request->getParsedBody();
        $this->repo->updateProduct($slug, [
            'name' => $b['name'] ?? '',
            'price' => $b['price'] ?? 0,
            'description' => $b['description'] ?? '',
            'description_short' => $b['description_short'] ?? '',
            'composition' => $b['composition'] ?? '',
            'portion_value' => trim((string) ($b['portion_value'] ?? '')),
            'portion_unit' => $b['portion_unit'] ?? '',
            'prep_time' => $b['prep_time'] ?? '',
            'visible' => isset($b['visible']),
            'available' => isset($b['available']),
            'rec_tags' => $this->parseTags((string) ($b['rec_tags'] ?? '')),
        ]);

        return $response->withStatus(302)->withHeader('Location', '/admin/menu?ok=Товар+сохранён');
    }

    /**
     * Разбор поля тегов: «anchor smoky, chill» -> [anchor, smoky, chill] (по пробелам/запятым).
     *
     * @return list<string>
     */
    private function parseTags(string $raw): array
    {
        $parts = preg_split('/[\s,]+/u', trim($raw)) ?: [];

        return array_values(array_unique(array_filter(array_map('strval', $parts), static fn (string $s): bool => $s !== '')));
    }

    public function toggle(Request $request, Response $response, array $args): Response
    {
        $b = (array) $request->getParsedBody();
        $field = (string) $args['field'];
        $this->repo->toggle((string) $args['slug'], $field, !empty($b['value']));

        return $response->withStatus(302)->withHeader('Location', '/admin/menu');
    }
}
