<?php

declare(strict_types=1);

namespace Mirai\Http\Controllers;

use Mirai\Domain\Menu\MenuRepository;
use Mirai\Domain\Menu\Recommender;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * JSON видимого меню — источник и для будущего Vite-фронта, и для проверки данных.
 */
final class MenuApiController
{
    public function __construct(
        private readonly MenuRepository $menu,
        private readonly Recommender $recommender,
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $visible = $this->menu->visibleMenu();

        // Гастропары считает движок рекомендаций (граф категорий/тегов).
        $all = [];
        foreach ($visible as $group) {
            foreach ($group['products'] as $p) {
                $all[] = $p;
            }
        }
        $pairings = $this->recommender->pairingsFor($all);

        $categories = [];
        foreach ($visible as $group) {
            $category = $group['category'];
            $categories[] = [
                'slug' => $category->slug,
                'title' => $category->title,
                'line' => $category->line,
                'group' => $category->groupSlug,
                'products' => array_map(static function ($p) use ($pairings): array {
                    return [
                        'slug' => $p->slug,
                        'name' => $p->name,
                        'price' => $p->price,
                        'description' => $p->description,
                        'description_short' => $p->descriptionShort,
                        'composition' => $p->composition,
                        'portion' => $p->portionLabel(),
                        'prep_time' => $p->prepTime,
                        'image' => $p->image,
                        'available' => $p->available,
                        'pairings' => array_map(static fn ($pp): array => [
                            'slug' => $pp->slug,
                            'name' => $pp->name,
                            'price' => $pp->price,
                            'image' => $pp->image,
                            'kind' => $pp->kind,
                            'note' => $pp->note,
                        ], $pairings[$p->slug] ?? []),
                    ];
                }, $group['products']),
            ];
        }

        $response->getBody()->write(
            (string) json_encode(['ok' => true, 'categories' => $categories], JSON_UNESCAPED_UNICODE)
        );

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
