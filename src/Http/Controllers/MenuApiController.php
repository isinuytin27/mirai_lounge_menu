<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers;

use Mirai\Domain\Menu\MenuRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * JSON видимого меню — источник и для будущего Vite-фронта, и для проверки данных.
 */
final class MenuApiController
{
    public function __construct(private readonly MenuRepository $menu) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $categories = [];
        foreach ($this->menu->visibleMenu() as $group) {
            $category = $group['category'];
            $categories[] = [
                'id' => $category->id,
                'title' => $category->title,
                'line' => $category->line,
                'products' => array_map(static fn ($p): array => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'price' => $p->price,
                    'description' => $p->description,
                    'description_short' => $p->descriptionShort,
                    'image' => $p->image,
                    'weight' => $p->weight,
                ], $group['products']),
            ];
        }

        $response->getBody()->write(
            (string) json_encode(['ok' => true, 'categories' => $categories], JSON_UNESCAPED_UNICODE)
        );

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
