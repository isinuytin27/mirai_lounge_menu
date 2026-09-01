<?php

declare(strict_types=1);

namespace Mirai\Domain\Menu;

use Mirai\Infrastructure\Db\Repository;

/**
 * Хиты продаж: курируемый ряд товаров вверху меню. Имя/цена/фото — из products,
 * бейдж/порядок — из top_sales. Чтение — публичное, запись — админка.
 */
final class TopSalesRepository extends Repository
{
    /**
     * Видимые хиты для гостя (товар виден и в наличии).
     *
     * @return list<array{slug:string,name:string,price:int,image:?string,badge:?string,group:string}>
     */
    public function visible(): array
    {
        $rows = $this->fetchAll(
            "SELECT p.slug, p.name, p.price, p.image, ts.badge, g.slug AS group_slug
             FROM top_sales ts
             JOIN products p ON p.id = ts.product_id
             JOIN menu_categories c ON c.id = p.category_id
             LEFT JOIN menu_groups g ON g.id = c.group_id
             WHERE ts.active = TRUE AND p.visible = TRUE AND p.available = TRUE
             ORDER BY ts.sort_order, ts.id"
        );

        return array_map(static fn (array $r): array => [
            'slug' => (string) $r['slug'],
            'name' => (string) $r['name'],
            'price' => (int) $r['price'],
            'image' => $r['image'] !== null && $r['image'] !== '' ? '/' . ltrim((string) $r['image'], '/') : null,
            'badge' => $r['badge'] !== null && $r['badge'] !== '' ? (string) $r['badge'] : null,
            'group' => (string) ($r['group_slug'] ?? ''),
        ], $rows);
    }

    // ------------------------------------------------------------ админка ----

    /**
     * Все хиты для админки (+ товары не в списке — для добавления).
     *
     * @return array{items:list<array<string,mixed>>,available:list<array{id:int,name:string}>}
     */
    public function adminAll(): array
    {
        $items = $this->fetchAll(
            "SELECT ts.id, ts.badge, ts.note, ts.active, ts.sort_order, p.id AS product_id, p.name, p.price, p.image
             FROM top_sales ts JOIN products p ON p.id = ts.product_id
             ORDER BY ts.sort_order, ts.id"
        );
        $available = $this->fetchAll(
            "SELECT id, name FROM products
             WHERE visible = TRUE AND id NOT IN (SELECT product_id FROM top_sales)
             ORDER BY name"
        );

        return [
            'items' => $items,
            'available' => array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'name' => (string) $r['name']], $available),
        ];
    }

    public function add(int $productId, ?string $badge, ?string $note): void
    {
        $this->execute(
            "INSERT INTO top_sales (product_id, badge, note, sort_order)
             VALUES (:pid, :badge, :note, (SELECT COALESCE(MAX(sort_order), -1) + 1 FROM top_sales))
             ON CONFLICT (product_id) DO NOTHING",
            ['pid' => $productId, 'badge' => self::nn($badge), 'note' => self::nn($note)]
        );
    }

    public function update(int $id, ?string $badge, ?string $note, bool $active): void
    {
        $this->execute(
            'UPDATE top_sales SET badge = :badge, note = :note, active = :active WHERE id = :id',
            ['badge' => self::nn($badge), 'note' => self::nn($note), 'active' => $active ? 'true' : 'false', 'id' => $id]
        );
    }

    /** Сдвинуть позицию вверх/вниз обменом sort_order с соседом. */
    public function move(int $id, string $dir): void
    {
        $rows = $this->fetchAll('SELECT id, sort_order FROM top_sales ORDER BY sort_order, id');
        $idx = null;
        foreach ($rows as $i => $r) {
            if ((int) $r['id'] === $id) {
                $idx = $i;
                break;
            }
        }
        if ($idx === null) {
            return;
        }
        $swap = $dir === 'up' ? $idx - 1 : $idx + 1;
        if ($swap < 0 || $swap >= count($rows)) {
            return;
        }
        $a = $rows[$idx];
        $b = $rows[$swap];
        $this->db->transactional(function () use ($a, $b): void {
            $st = $this->pdo()->prepare('UPDATE top_sales SET sort_order = :so WHERE id = :id');
            $st->execute(['so' => (int) $b['sort_order'], 'id' => (int) $a['id']]);
            $st->execute(['so' => (int) $a['sort_order'], 'id' => (int) $b['id']]);
        });
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM top_sales WHERE id = :id', ['id' => $id]);
    }

    private static function nn(?string $v): ?string
    {
        $s = trim((string) ($v ?? ''));
        return $s === '' ? null : $s;
    }
}
