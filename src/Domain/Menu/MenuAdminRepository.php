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
            'SELECT c.id, c.slug, c.title, c.line, c.sort_order, g.slug AS group_slug
             FROM menu_categories c LEFT JOIN menu_groups g ON g.id = c.group_id
             ORDER BY c.sort_order, c.title'
        );
        $prods = $this->fetchAll(
            'SELECT id, slug, category_id, name, price, visible, available, image, sort_order
             FROM products ORDER BY sort_order, name'
        );

        $pairingCounts = [];
        foreach ($this->fetchAll('SELECT product_id, count(*) AS c FROM product_pairings GROUP BY product_id') as $r) {
            $pairingCounts[(int) $r['product_id']] = (int) $r['c'];
        }

        $byCat = [];
        foreach ($prods as $p) {
            $cid = (int) $p['category_id'];
            $p['pairings'] = $pairingCounts[(int) $p['id']] ?? 0;
            $byCat[$cid][] = $p;
        }

        $out = [];
        foreach ($cats as $c) {
            $out[] = [
                'id' => (int) $c['id'],
                'slug' => (string) $c['slug'],
                'title' => (string) $c['title'],
                'group_slug' => isset($c['group_slug']) ? (string) $c['group_slug'] : null,
                'line' => (string) $c['line'],
                'products' => $byCat[(int) $c['id']] ?? [],
            ];
        }

        return $out;
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
        $this->execute(
            'UPDATE products SET
                name = :name, price = :price,
                description = :description, description_short = :description_short, composition = :composition,
                portion_value = :portion_value, portion_unit = :portion_unit, prep_time = :prep_time,
                visible = :visible, available = :available, updated_at = :now
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

    // ---------- граф гастропар ----------

    /**
     * Связки товара (для редактирования) с данными цели.
     * @return list<array<string,mixed>>
     */
    public function pairingsOf(string $slug): array
    {
        return $this->fetchAll(
            'SELECT pp.id, pp.kind, pp.weight, pp.note, tgt.slug, tgt.name, tgt.price
             FROM product_pairings pp
             JOIN products src ON src.id = pp.product_id
             JOIN products tgt ON tgt.id = pp.paired_product_id
             WHERE src.slug = :slug ORDER BY pp.weight DESC, pp.sort_order',
            ['slug' => $slug]
        );
    }

    /** Добавить ребро графа (гастропару). */
    public function addPairing(string $fromSlug, string $toSlug, string $kind, float $weight, ?string $note): void
    {
        if ($fromSlug === $toSlug) {
            return;
        }
        $this->execute(
            'INSERT INTO product_pairings (product_id, paired_product_id, kind, weight, note, created_at)
             SELECT s.id, t.id, :kind, :weight, :note, :now
             FROM products s, products t WHERE s.slug = :from AND t.slug = :to
             ON CONFLICT (product_id, paired_product_id, kind) DO UPDATE SET weight = EXCLUDED.weight, note = EXCLUDED.note',
            ['kind' => $kind, 'weight' => $weight, 'note' => $note ?: null, 'now' => date('c'), 'from' => $fromSlug, 'to' => $toSlug]
        );
    }

    public function removePairing(int $pairingId): void
    {
        $this->execute('DELETE FROM product_pairings WHERE id = :id', ['id' => $pairingId]);
    }

    /**
     * Список товаров для выбора цели связки (id/slug/name).
     * @return list<array{slug:string,name:string}>
     */
    public function productsBrief(): array
    {
        $rows = $this->fetchAll('SELECT slug, name FROM products ORDER BY name');
        return array_map(static fn (array $r): array => [
            'slug' => (string) $r['slug'],
            'name' => (string) $r['name'],
        ], $rows);
    }
}
