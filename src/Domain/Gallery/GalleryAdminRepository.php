<?php

declare(strict_types=1);

namespace Mirai\Domain\Gallery;

use Mirai\Infrastructure\Db\Repository;

/** Запись галереи из админки (Postgres). Заменяет write-часть gallery_storage.php. */
final class GalleryAdminRepository extends Repository
{
    /** @return list<array{id:string,image:string,caption:?string,sort_order:int}> */
    public function all(): array
    {
        $rows = $this->fetchAll('SELECT id, image, caption, sort_order FROM gallery_items ORDER BY sort_order, id');

        return array_map(static fn (array $r): array => [
            'id' => (string) $r['id'],
            'image' => (string) $r['image'],
            'caption' => isset($r['caption']) ? (string) $r['caption'] : null,
            'sort_order' => (int) $r['sort_order'],
        ], $rows);
    }

    public function add(string $imagePath, ?string $caption): void
    {
        $next = (int) ($this->fetchOne('SELECT COALESCE(MAX(sort_order), -1) + 1 AS n FROM gallery_items')['n'] ?? 0);
        $this->execute(
            'INSERT INTO gallery_items (id, image, caption, sort_order, created_at)
             VALUES (:id, :img, :cap, :sort, :now)',
            ['id' => 'gal_' . bin2hex(random_bytes(6)), 'img' => $imagePath, 'cap' => $caption ?: null, 'sort' => $next, 'now' => date('c')]
        );
    }

    public function updateCaption(string $id, ?string $caption): void
    {
        $this->execute(
            'UPDATE gallery_items SET caption = :cap, updated_at = :now WHERE id = :id',
            ['cap' => $caption ?: null, 'now' => date('c'), 'id' => $id]
        );
    }

    /** Удалить элемент. Возвращает путь к картинке (чтобы вызывающий мог удалить файл). */
    public function delete(string $id): ?string
    {
        $row = $this->fetchOne('SELECT image FROM gallery_items WHERE id = :id', ['id' => $id]);
        $this->execute('DELETE FROM gallery_items WHERE id = :id', ['id' => $id]);

        return $row !== null ? (string) $row['image'] : null;
    }
}
