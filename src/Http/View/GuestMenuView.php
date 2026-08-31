<?php

declare(strict_types=1);

namespace Mirai\Http\View;

use Mirai\Domain\Menu\MenuRepository;
use Mirai\Domain\Menu\PairedProduct;
use Mirai\Domain\Menu\Product;
use Mirai\Domain\Menu\Recommender;

/**
 * View-model гостевого меню (drill-down: плашки-группы -> содержимое). Группы теперь
 * из БД (menu_groups), а не хардкод. Секции/товары из visibleMenu; гастропары считает
 * Recommender (граф категорий/тегов), а не ручные связки.
 */
final class GuestMenuView
{
    /** Порядок «колеса» бара (UX-раскладка; данные подтягиваются по slug категории). */
    private const BAR_STRUCTURE = [
        'author_cocktails', 'classic', 'tinctures', 'sours', 'tropical',
        'item_522b0eac', 'item', 'item_258c0a14', 'item_dcbc0045', 'item_b87d8ae4',
        'item_e06230e4', 'item_2a99d782', 'item_eb623cf2', 'item_695f114b', 'item_4f0f7740',
        'na_cocktails', 'tea_leaf', 'item_2f338739', 'item_5a485d13',
    ];

    /**
     * Собрать view-model напрямую из репозитория (загружает меню, группы, гастропары).
     *
     * @return array{groups:list<array{id:string,label:string,count:int}>, sections:list<array<string,mixed>>, bar_wheel:list<array<string,mixed>>}
     */
    public static function fromRepository(MenuRepository $menu, Recommender $recommender): array
    {
        $visible = $menu->visibleMenu();

        $all = [];
        foreach ($visible as $entry) {
            foreach ($entry['products'] as $p) {
                $all[] = $p;
            }
        }

        return self::build(
            $visible,
            $menu->groups(),
            $menu->allCategoryTitles(),
            $recommender->pairingsFor($all),
        );
    }

    /**
     * @param list<array{category:\Mirai\Domain\Menu\Category,products:list<Product>}> $visibleMenu
     * @param list<array{slug:string,title:string}> $groups
     * @param array<string,string> $allTitles slug => title (все категории)
     * @param array<string,list<PairedProduct>> $pairings slug товара => сопутствующие
     * @return array{groups:list<array{id:string,label:string,count:int}>, sections:list<array<string,mixed>>, bar_wheel:list<array<string,mixed>>}
     */
    public static function build(array $visibleMenu, array $groups, array $allTitles, array $pairings = []): array
    {
        $sections = [];
        $preview = [];
        $groupCount = [];

        foreach ($visibleMenu as $entry) {
            $category = $entry['category'];
            $groupSlug = $category->groupSlug ?? '';
            if ($groupSlug !== '') {
                $groupCount[$groupSlug] = ($groupCount[$groupSlug] ?? 0) + 1;
            }

            $items = [];
            foreach ($entry['products'] as $p) {
                if ($p->image !== null && !isset($preview[$category->slug])) {
                    $preview[$category->slug] = '/' . ltrim($p->image, '/');
                }
                $items[] = [
                    'id' => $p->slug,
                    'name' => $p->name,
                    'price' => $p->price,
                    'description' => $p->description,
                    'description_short' => $p->descriptionShort,
                    'composition' => $p->composition,
                    'weight' => $p->portionLabel() ?? $p->prepTime,
                    'image' => $p->image,
                    'available' => $p->available,
                    'pairings' => array_map(static fn (PairedProduct $pp): array => [
                        'slug' => $pp->slug,
                        'name' => $pp->name,
                        'price' => $pp->price,
                        'image' => $pp->image,
                        'kind' => $pp->kind,
                        'note' => $pp->note,
                    ], $pairings[$p->slug] ?? []),
                ];
            }

            $sections[] = [
                'id' => $category->slug,
                'title' => $category->title,
                'group' => $groupSlug,
                'items' => $items,
            ];
        }

        // Плашки групп (из БД) + число непустых категорий.
        $tiles = [];
        foreach ($groups as $g) {
            $tiles[] = [
                'id' => $g['slug'],
                'label' => $g['title'],
                'count' => $groupCount[$g['slug']] ?? 0,
            ];
        }

        // Колесо бара.
        $barWheel = [];
        foreach (self::BAR_STRUCTURE as $slug) {
            $barWheel[] = [
                'id' => $slug,
                'title' => $allTitles[$slug] ?? $slug,
                'cats' => $slug,
                'preview' => $preview[$slug] ?? '',
                'empty' => !isset($preview[$slug]) && !isset($allTitles[$slug]),
            ];
        }

        return ['groups' => $tiles, 'sections' => $sections, 'bar_wheel' => $barWheel];
    }
}
