<?php

declare(strict_types=1);

namespace Mirai\Domain\Menu;

use Mirai\Infrastructure\Db\Repository;

/**
 * Чтение меню. Заменяет menu_storage.php + mirai_menu_public.php (расчёт линии на лету).
 */
final class MenuRepository extends Repository
{
    /**
     * Видимое меню, сгруппированное по категориям (только непустые), в порядке сортировки.
     *
     * @return list<array{category:Category,products:list<Product>}>
     */
    public function visibleMenu(): array
    {
        $categories = $this->fetchAll(
            'SELECT id, title, line, sort_order FROM menu_categories ORDER BY sort_order, title'
        );
        $products = $this->fetchAll(
            'SELECT id, category_id, name, price, description, description_short, image, weight, visible, sort_order
             FROM menu_products WHERE visible = TRUE ORDER BY sort_order, name'
        );

        return self::buildMenu($categories, $products);
    }

    /** Найти один видимый продукт по id (для валидации заказа). Линия — из его категории. */
    public function findVisibleProduct(string $productId): ?Product
    {
        $row = $this->fetchOne(
            'SELECT p.id, p.category_id, p.name, p.price, p.description, p.description_short,
                    p.image, p.weight, p.visible, p.sort_order, COALESCE(c.line, :kitchen) AS line
             FROM menu_products p
             LEFT JOIN menu_categories c ON c.id = p.category_id
             WHERE p.id = :id AND p.visible = TRUE',
            ['id' => $productId, 'kitchen' => MenuLine::KITCHEN]
        );

        if ($row === null) {
            return null;
        }

        return Product::fromRow($row, (string) $row['line']);
    }

    /**
     * Чистая группировка (без БД) — тестируется изолированно.
     *
     * @param list<array<string,mixed>> $categoryRows
     * @param list<array<string,mixed>> $productRows
     * @return list<array{category:Category,products:list<Product>}>
     */
    public static function buildMenu(array $categoryRows, array $productRows): array
    {
        $categories = [];
        $lineById = [];
        foreach ($categoryRows as $row) {
            $category = Category::fromRow($row);
            $categories[$category->id] = $category;
            $lineById[$category->id] = $category->line;
        }

        /** @var array<string,list<Product>> $byCategory */
        $byCategory = [];
        foreach ($productRows as $row) {
            $categoryId = (string) ($row['category_id'] ?? '');
            $line = $lineById[$categoryId] ?? MenuLine::KITCHEN;
            $byCategory[$categoryId][] = Product::fromRow($row, $line);
        }

        $menu = [];
        foreach ($categories as $id => $category) {
            if (!empty($byCategory[$id])) {
                $menu[] = ['category' => $category, 'products' => $byCategory[$id]];
            }
        }

        return $menu;
    }
}
