<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers\Admin;

use Mirai\Domain\Menu\MenuAdminRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Редактор меню в админке — пишет в Postgres (закрывает раздвоение с гостевым меню).
 * Заменяет write-часть старого admin/dashboard.php + menu_storage.php.
 */
final class MenuAdminController
{
    public function __construct(private readonly MenuAdminRepository $repo) {}

    public function index(Request $request, Response $response): Response
    {
        return Twig::fromRequest($request)->render($response, 'admin/menu/index.twig', [
            'categories' => $this->repo->categoriesWithProducts(),
            'flash' => $request->getQueryParams()['ok'] ?? null,
        ]);
    }

    public function editProduct(Request $request, Response $response, array $args): Response
    {
        $product = $this->repo->getProduct((string) $args['slug']);
        if ($product === null) {
            return $response->withStatus(302)->withHeader('Location', '/admin/menu');
        }

        return Twig::fromRequest($request)->render($response, 'admin/menu/product.twig', [
            'p' => $product,
            'pairings' => $this->repo->pairingsOf((string) $args['slug']),
            'catalog' => $this->repo->productsBrief(),
        ]);
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
        ]);

        return $response->withStatus(302)->withHeader('Location', '/admin/menu?ok=Товар+сохранён');
    }

    public function toggle(Request $request, Response $response, array $args): Response
    {
        $b = (array) $request->getParsedBody();
        $field = (string) $args['field'];
        $this->repo->toggle((string) $args['slug'], $field, !empty($b['value']));

        return $response->withStatus(302)->withHeader('Location', '/admin/menu');
    }

    public function addPairing(Request $request, Response $response, array $args): Response
    {
        $slug = (string) $args['slug'];
        $b = (array) $request->getParsedBody();
        $to = trim((string) ($b['to'] ?? ''));
        if ($to !== '') {
            $this->repo->addPairing(
                $slug,
                $to,
                (string) ($b['kind'] ?? 'gastro'),
                (float) ($b['weight'] ?? 1),
                trim((string) ($b['note'] ?? '')) ?: null,
            );
        }

        return $response->withStatus(302)->withHeader('Location', '/admin/menu/product/' . rawurlencode($slug));
    }

    public function removePairing(Request $request, Response $response, array $args): Response
    {
        $this->repo->removePairing((int) $args['id']);

        return $response->withStatus(302)->withHeader('Location', '/admin/menu/product/' . rawurlencode((string) $args['slug']));
    }
}
