<?php

declare(strict_types=1);

namespace Mirai\Domain\Menu;

use Mirai\Infrastructure\Db\Repository;

/**
 * Чтение меню из нормализованной схемы: menu_groups -> menu_categories -> products.
 * Гастропары считает Recommender (граф категорий/тегов). Заменяет menu_storage.php.
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
                    p.visible, p.available, p.sort_order, p.rec_tags,
                    c.id AS c_id, c.slug AS category_slug, c.title AS c_title,
                    c.line AS c_line, c.sort_order AS c_sort, c.rec_category, g.slug AS group_slug
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

    /**
     * Плоский список видимых товаров (кандидаты рекомендатора).
     *
     * @return list<Product>
     */
    public function visibleProducts(): array
    {
        $out = [];
        foreach ($this->visibleMenu() as $entry) {
            foreach ($entry['products'] as $p) {
                $out[] = $p;
            }
        }

        return $out;
    }

    /** Товар по slug с полями рекомендатора (без фильтра видимости) — для превью в админке. */
    public function productWithRec(string $slug): ?Product
    {
        $row = $this->fetchOne(
            "SELECT p.id, p.slug, p.name, p.price, p.description, p.description_short,
                    p.composition, p.portion_value, p.portion_unit, p.prep_time, p.image,
                    p.visible, p.available, p.sort_order, p.rec_tags,
                    c.slug AS category_slug, COALESCE(c.line, :kitchen) AS line, c.rec_category
             FROM products p
             LEFT JOIN menu_categories c ON c.id = p.category_id
             WHERE p.slug = :slug",
            ['slug' => $slug, 'kitchen' => MenuLine::KITCHEN]
        );

        return $row !== null ? Product::fromRow($row, (string) $row['line']) : null;
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
}
