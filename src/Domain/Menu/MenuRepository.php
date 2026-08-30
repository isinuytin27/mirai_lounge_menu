<?php

declare(strict_types=1);

namespace Mirai\Domain\Menu;

use Mirai\Infrastructure\Db\Repository;

/**
 * Чтение меню из нормализованной схемы: menu_groups -> menu_categories -> products,
 * плюс граф гастропар product_pairings. Заменяет menu_storage.php + mirai_menu_public.php.
 */
final class MenuRepository extends Repository implements ProductFinder
{
    /**
     * Группы меню (плашки первого экрана) в порядке сортировки.
     *
     * @return list<array{slug:string,title:string}>
     */
    public function groups(): array
    {
        $rows = $this->fetchAll(
            'SELECT slug, title FROM menu_groups WHERE active = TRUE ORDER BY sort_order, title'
        );

        return array_map(static fn (array $r): array => [
            'slug' => (string) $r['slug'],
            'title' => (string) $r['title'],
        ], $rows);
    }

    /**
     * Видимое меню, сгруппированное по категориям (только непустые).
     *
     * @return list<array{category:Category,products:list<Product>}>
     */
    public function visibleMenu(): array
    {
        $rows = $this->fetchAll(
            "SELECT p.id, p.slug, p.name, p.price, p.description, p.description_short,
                    p.composition, p.portion_value, p.portion_unit, p.prep_time, p.image,
                    p.visible, p.available, p.sort_order,
                    c.id AS c_id, c.slug AS category_slug, c.title AS c_title,
                    c.line AS c_line, c.sort_order AS c_sort, g.slug AS group_slug
             FROM products p
             JOIN menu_categories c ON c.id = p.category_id
             LEFT JOIN menu_groups g ON g.id = c.group_id
             WHERE p.visible = TRUE
             ORDER BY c.sort_order, c.title, p.sort_order, p.name"
        );

        /** @var array<string,array{category:Category,products:list<Product>}> $byCat */
        $byCat = [];
        foreach ($rows as $r) {
            $catSlug = (string) $r['category_slug'];
            if (!isset($byCat[$catSlug])) {
                $byCat[$catSlug] = [
                    'category' => Category::fromRow([
                        'id' => $r['c_id'], 'slug' => $catSlug, 'title' => $r['c_title'],
                        'line' => $r['c_line'], 'group_slug' => $r['group_slug'], 'sort_order' => $r['c_sort'],
                    ]),
                    'products' => [],
                ];
            }
            $byCat[$catSlug]['products'][] = Product::fromRow($r, (string) $r['c_line']);
        }

        return array_values($byCat);
    }

    /** Найти видимый товар по slug (для валидации заказа). */
    public function findVisibleProduct(string $productSlug): ?Product
    {
        $row = $this->fetchOne(
            "SELECT p.id, p.slug, p.name, p.price, p.description, p.description_short,
                    p.composition, p.portion_value, p.portion_unit, p.prep_time, p.image,
                    p.visible, p.available, p.sort_order,
                    c.slug AS category_slug, COALESCE(c.line, :kitchen) AS line
             FROM products p
             LEFT JOIN menu_categories c ON c.id = p.category_id
             WHERE p.slug = :slug AND p.visible = TRUE",
            ['slug' => $productSlug, 'kitchen' => MenuLine::KITCHEN]
        );

        if ($row === null) {
            return null;
        }

        return Product::fromRow($row, (string) $row['line']);
    }

    /** Все категории (включая пустые): slug => title. Для подписей wheel. */
    public function allCategoryTitles(): array
    {
        $rows = $this->fetchAll('SELECT slug, title FROM menu_categories');
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['slug']] = (string) ($row['title'] ?? $row['slug']);
        }

        return $out;
    }

    /**
     * Граф гастропар: сопутствующие товары для набора товаров (по их slug).
     * Возвращает slug_товара => список PairedProduct (по весу ребра, затем sort_order).
     *
     * @param list<string> $productSlugs
     * @return array<string,list<PairedProduct>>
     */
    public function pairingsForSlugs(array $productSlugs): array
    {
        if ($productSlugs === []) {
            return [];
        }

        $params = [];
        $names = [];
        foreach ($productSlugs as $i => $slug) {
            $names[] = ':s' . $i;
            $params['s' . $i] = $slug;
        }
        $in = implode(', ', $names);

        $rows = $this->fetchAll(
            "SELECT src.slug AS from_slug, tgt.slug AS slug, tgt.name, tgt.price, tgt.image,
                    pp.kind, pp.weight, pp.note
             FROM product_pairings pp
             JOIN products src ON src.id = pp.product_id
             JOIN products tgt ON tgt.id = pp.paired_product_id
             WHERE src.slug IN ({$in}) AND tgt.visible = TRUE
             ORDER BY pp.weight DESC, pp.sort_order",
            $params
        );

        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['from_slug']][] = PairedProduct::fromRow($r);
        }

        return $out;
    }
}
