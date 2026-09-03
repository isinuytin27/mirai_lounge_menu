<?php

declare(strict_types=1);

namespace Mirai\Domain\Menu;

use Mirai\Infrastructure\Db\Repository;

/**
 * Запись меню из админки (Postgres). Заменяет write-часть menu_storage.php (JSON).
 * Читает ВСЕ товары/категории (включая скрытые) — в отличие от гостевого MenuRepository.
 */
final class MenuAdminRepository extends Repository
{
    /**
     * Все категории с товарами (включая скрытые/недоступные) для списка админки.
     *
     * @return list<array{id:int,slug:string,title:string,group_slug:?string,line:string,products:list<array<string,mixed>>}>
     */
    public function categoriesWithProducts(): array
    {
        $cats = $this->fetchAll(
            'SELECT c.id, c.slug, c.title, c.line, c.sort_order, c.group_id, c.active, g.slug AS group_slug
             FROM menu_categories c LEFT JOIN menu_groups g ON g.id = c.group_id
             ORDER BY c.sort_order, c.title'
        );
        $prods = $this->fetchAll(
            'SELECT id, slug, category_id, name, price, visible, available, image, sort_order
             FROM products ORDER BY sort_order, name'
        );

        $byCat = [];
        foreach ($prods as $p) {
            $byCat[(int) $p['category_id']][] = $p;
        }

        $out = [];
        foreach ($cats as $c) {
            $out[] = [
                'id' => (int) $c['id'],
                'slug' => (string) $c['slug'],
                'title' => (string) $c['title'],
                'group_slug' => isset($c['group_slug']) ? (string) $c['group_slug'] : null,
                'group_id' => $c['group_id'] !== null ? (int) $c['group_id'] : null,
                'line' => (string) $c['line'],
                'sort_order' => (int) $c['sort_order'],
                'active' => self::boolish($c['active'] ?? true),
                'products' => $byCat[(int) $c['id']] ?? [],
            ];
        }

        return $out;
    }

    /**
     * Все группы меню (для выпадашки при смене группы категории).
     *
     * @return list<array{id:int,slug:string,title:string}>
     */
    public function allGroups(): array
    {
        return array_map(static fn (array $g): array => [
            'id' => (int) $g['id'],
            'slug' => (string) $g['slug'],
            'title' => (string) $g['title'],
        ], $this->fetchAll('SELECT id, slug, title FROM menu_groups ORDER BY sort_order, title'));
    }

    /** Заменить картинку товара (путь от public, напр. assets/img/menu/uploads/x.webp). */
    public function updateProductImage(string $slug, string $image): void
    {
        $this->execute(
            'UPDATE products SET image = :image, updated_at = now() WHERE slug = :slug',
            ['image' => $image, 'slug' => $slug]
        );
    }

    /** Обновить категорию: название, группа, вкл/выкл. */
    public function updateCategory(int $id, string $title, ?int $groupId, bool $active): void
    {
        $this->execute(
            'UPDATE menu_categories SET title = :title, group_id = :gid, active = :active, updated_at = now()
             WHERE id = :id',
            ['title' => $title, 'gid' => $groupId, 'active' => $active ? 'true' : 'false', 'id' => $id]
        );
    }

    /** Переставить категорию вверх/вниз: обмен sort_order с соседом в той же группе. */
    public function moveCategory(int $id, string $dir): void
    {
        $this->db->transactional(function () use ($id, $dir): void {
            $cur = $this->fetchOne('SELECT id, group_id, sort_order FROM menu_categories WHERE id = :id', ['id' => $id]);
            if ($cur === null) {
                return;
            }
            $op = $dir === 'up' ? '<' : '>';
            $order = $dir === 'up' ? 'DESC' : 'ASC';
            $neighbor = $this->fetchOne(
                "SELECT id, sort_order FROM menu_categories
                 WHERE group_id IS NOT DISTINCT FROM :gid AND sort_order {$op} :ord
                 ORDER BY sort_order {$order} LIMIT 1",
                ['gid' => $cur['group_id'], 'ord' => $cur['sort_order']]
            );
            if ($neighbor === null) {
                return;
            }
            $this->execute('UPDATE menu_categories SET sort_order = :o WHERE id = :id',
                ['o' => (int) $neighbor['sort_order'], 'id' => (int) $cur['id']]);
            $this->execute('UPDATE menu_categories SET sort_order = :o WHERE id = :id',
                ['o' => (int) $cur['sort_order'], 'id' => (int) $neighbor['id']]);
        });
    }

    private static function boolish(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if (is_string($v)) {
            return in_array(strtolower($v), ['t', 'true', '1', 'yes'], true);
        }
        return (bool) $v;
    }

    /** @return array<string,mixed>|null полная строка товара по slug */
    public function getProduct(string $slug): ?array
    {
        return $this->fetchOne('SELECT * FROM products WHERE slug = :slug', ['slug' => $slug]);
    }

    /**
     * Обновить редактируемые поля товара.
     * @param array<string,mixed> $data
     */
    public function updateProduct(string $slug, array $data): void
    {
        /** @var list<string> $recTags */
        $recTags = $data['rec_tags'] ?? [];
        $this->execute(
            'UPDATE products SET
                name = :name, price = :price,
                description = :description, description_short = :description_short, composition = :composition,
                portion_value = :portion_value, portion_unit = :portion_unit, prep_time = :prep_time,
                visible = :visible, available = :available, rec_tags = CAST(:rec_tags AS jsonb), updated_at = :now
             WHERE slug = :slug',
            [
                'name' => (string) $data['name'],
                'price' => (int) $data['price'],
                'description' => $data['description'] ?: null,
                'description_short' => $data['description_short'] ?: null,
                'composition' => $data['composition'] ?: null,
                'portion_value' => $data['portion_value'] !== '' ? $data['portion_value'] : null,
                'portion_unit' => $data['portion_unit'] ?: null,
                'prep_time' => $data['prep_time'] ?: null,
                'visible' => !empty($data['visible']) ? 'true' : 'false',
                'available' => !empty($data['available']) ? 'true' : 'false',
                // пусто => null (наследует теги категории); иначе — переопределение
                'rec_tags' => $recTags === [] ? null : (string) json_encode($recTags),
                'now' => date('c'),
                'slug' => $slug,
            ]
        );
    }

    /** Быстрый тумблер visible|available. */
    public function toggle(string $slug, string $field, bool $value): void
    {
        if (!in_array($field, ['visible', 'available'], true)) {
            return;
        }
        $this->execute(
            "UPDATE products SET {$field} = :v, updated_at = :now WHERE slug = :slug",
            ['v' => $value ? 'true' : 'false', 'now' => date('c'), 'slug' => $slug]
        );
    }

    /**
     * Справочник тегов рекомендатора (для подсказок в редакторе), сгруппированный.
     * @return list<array{slug:string,ru:string,group:string}>
     */
    public function recTags(): array
    {
        $rows = $this->fetchAll('SELECT slug, ru, tag_group FROM rec_tags ORDER BY tag_group, ru');
        return array_map(static fn (array $r): array => [
            'slug' => (string) $r['slug'],
            'ru' => (string) $r['ru'],
            'group' => (string) $r['tag_group'],
        ], $rows);
    }
}
